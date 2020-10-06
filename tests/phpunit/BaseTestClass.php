<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

require_once __DIR__ . '/../../extendedreport.php';

/**
 * FIXME - Add test description.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class BaseTestClass extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  use \Civi\Test\Api3TestTrait;

  /**
   * @var int
   */
  protected $customFieldID;

  /**
   * @var int
   */
  protected $customGroupID;

  protected $ids = [];

  /**
   * Set up for headless tests.
   *
   * @return \Civi\Test\CiviEnvBuilder
   *
   * @throws \CRM_Extension_Exception_ParseException
   */
  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  /**
   * Clean up after test.
   *
   * @throws \CRM_Core_Exception
   */
  public function tearDown() {
    foreach ($this->ids as $entity => $entityIDs) {
      foreach ($entityIDs as $entityID) {
        try {
          if (strtolower($entity) === 'contact') {
            $this->cleanUpContact($entityID);
          }
          else {
            civicrm_api3($entity, 'delete', ['id' => $entityID]);
          }
        }
        catch (CiviCRM_API3_Exception $e) {
          // No harm done - it was a best effort cleanup
        }
      }
    }
  }

  /**
   * Delete a contact, first removing blocking entities.
   *
   * @param int $contactId
   *
   * @throws \CRM_Core_Exception
   */
  public function cleanUpContact(int $contactId) {
    $contributions = $this->callAPISuccess('Contribution', 'get', [
      'contact_id' => $contactId,
    ])['values'];
    foreach ($contributions as $id => $details) {
      $this->callAPISuccess('Contribution', 'delete', [
        'id' => $id,
      ]);
    }
    $this->callAPISuccess('Contact', 'delete', [
      'id' => $contactId,
      'skip_undelete' => TRUE,
    ]);
  }

  /**
   * @var string
   *   SQL returned from the report.
   */
  protected $sql;

  protected $labels = [];

  /**
   * @param array $params
   *
   * @return array|int
   * @throws \CRM_Core_Exception
   */
  protected function getRows(array $params) {
    $params['options']['metadata'] = ['title', 'labels', 'sql'];
    $rows = $this->callAPISuccess('ReportTemplate', 'getrows', $params);
    $this->sql = $rows['metadata']['sql'];
    $this->labels = $rows['metadata']['labels'] ?? [];
    $rows = $rows['values'];
    return $rows;
  }

  /**
   * Create a custom group with a single text custom field.
   *
   * This is breaking my heart - do it with traits - but not until next month
   * when 5.3 gets removed.
   *
   * @param array $inputParams
   * @param string $entity
   *
   * @return array
   *   ids of created objects
   *
   * @throws \CRM_Core_Exception
   */
  protected function createCustomGroupWithField($inputParams = [], $entity = 'Contact'): array {
    $params = ['title' => $entity];
    $params['extends'] = $entity;
    CRM_Core_PseudoConstant::flush();

    $groups = $this->callAPISuccess('CustomGroup', 'get', ['name' => $entity]);
    // cleanup first to save misery.
    $customGroupParams = empty($groups['count']) ? ['custom_group_id' => ['IS NULL' => 1]] : ['custom_group_id' => ['IN' => array_keys($groups['values'])]];
    $fields = $this->callAPISuccess('CustomField', 'get', $customGroupParams, print_r($groups, 1));
    foreach ($fields['values'] as $field) {
      // delete from the table as it may be an orphan & if not the group drop will sort out.
      CRM_Core_DAO::executeQuery('DELETE FROM civicrm_custom_field WHERE id = ' . (int) $field['id']);
    }

    foreach ($groups['values'] as $group) {
      if (CRM_Core_DAO::singleValueQuery("SHOW TABLES LIKE '" . $group['table_name'] . "'")) {
        $this->callAPISuccess('CustomGroup', 'delete', ['id' => $group['id']]);
      }
      else {
        CRM_Core_DAO::executeQuery('DELETE FROM civicrm_custom_group WHERE id = ' . $group['id']);
      }

    }
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_cache');
    CRM_Core_PseudoConstant::flush();

    $customGroup = $this->CustomGroupCreate($params);
    $customFieldParams = [
      'custom_group_id' => $customGroup['id'],
      'label' => $entity,
    ];
    if (!empty($inputParams['CustomField'])) {
      $customFieldParams = array_merge($customFieldParams, $inputParams['CustomField']);
    }
    $customField = $this->customFieldCreate($customFieldParams);
    $this->customGroupID = $customGroup['id'];
    $this->customGroup = $customGroup['values'][$customGroup['id']];
    $this->customFieldID = $customField['id'];
    CRM_Core_PseudoConstant::flush();

    return [
      'custom_group_id' => $customGroup['id'],
      'custom_field_id' => $customField['id'],
    ];
  }

  /**
   * Create custom group.
   *
   * @param array $params
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  public function customGroupCreate($params = []) {
    $defaults = [
      'title' => 'new custom group',
      'extends' => 'Contact',
      'domain_id' => 1,
      'style' => 'Inline',
      'is_active' => 1,
    ];

    $params = array_merge($defaults, $params);

    if (strlen($params['title']) > 13) {
      $params['title'] = substr($params['title'], 0, 13);
    }

    //have a crack @ deleting it first in the hope this will prevent derailing our tests
    $this->callAPISuccess('custom_group', 'get', [
      'title' => $params['title'],
      ['api.custom_group.delete' => 1],
    ]);

    return $this->callAPISuccess('custom_group', 'create', $params);
  }


  /**
   * Create custom field.
   *
   * @param array $params
   *   (custom_group_id) is required.
   *
   * @return array
   * @throws \CRM_Core_Exception
   * @throws \Exception
   */
  protected function customFieldCreate($params) {
    $params = array_merge([
      'label' => 'Custom Field',
      'data_type' => 'String',
      'html_type' => 'Text',
      'is_searchable' => 1,
      'is_active' => 1,
      'default_value' => 'defaultValue',
    ], $params);

    $result = $this->callAPISuccess('custom_field', 'create', $params);
    // these 2 functions are called with force to flush static caches
    CRM_Core_BAO_CustomField::getTableColumnGroup($result['id'], 1);
    CRM_Core_Component::getEnabledComponents(1);
    return $result;
  }

  /**
   * Get data for the specified number of contacts of the specified type.
   *
   * @param string $contactType
   * @param int $quantity
   *
   * @return array
   */
  public function getContactData($contactType, $quantity): array {
    switch ($contactType) {
      case 'Individual':
        $contacts = $this->getIndividuals();
        break;

      case 'Household':
        $contacts = $this->getHouseholds();
        break;

      case 'Organization':
        $contacts = $this->getOrganizations();
        break;
    }
    foreach ($contacts as $index => $contact) {
      $contacts[$index]['contact_type'] = $contactType;
    }
    return array_intersect_key($contacts, range(0, ($quantity - 1)));
  }

  public function getIndividuals() {
    return [
      ['first_name' => 'Nelson', 'last_name' => 'Mandela'],
      ['first_name' => 'William', 'last_name' => 'Wallace'],
      ['first_name' => 'Hannibal', 'last_name' => 'Lector'],
      ['first_name' => 'Snow', 'last_name' => 'White'],
      ['first_name' => 'Roger', 'last_name' => 'Rabbit'],
      ['first_name' => 'Jessica', 'last_name' => 'Rabbit'],
      ['first_name' => 'Master', 'last_name' => 'Of the universe'],
      ['first_name' => 'Bilbo', 'last_name' => 'Baggins'],
      ['first_name' => 'Simon', 'last_name' => 'Cowell'],
      ['first_name' => 'Danger', 'last_name' => 'Mouse'],
    ];
  }

  public function getHouseholds() {
    return [
      ['household_name' => 'The Shaw household'],
      ['household_name' => 'The Brady bunch'],
      ['household_name' => 'Home sweet home'],
      ['household_name' => 'Our castle'],
      ['household_name' => 'The Shire'],
      ['household_name' => 'Ronald MacDonald House'],
      ['household_name' => 'Adams family household'],
      ['household_name' => 'The Royal household'],
      ['household_name' => 'The Royle household'],
      ['household_name' => 'The vickerage'],
    ];
  }

  public function getOrganizations() {
    return [
      ['organization_name' => 'Shady Inc'],
      ['organization_name' => 'Dodgey Corp'],
      ['organization_name' => 'Sleazy Ltd'],
      ['organization_name' => 'Dubious LLC'],
      ['organization_name' => 'Underhand Trust'],
      ['organization_name' => 'Acme Inc'],
      ['organization_name' => 'Denizens Incorporated'],
      ['organization_name' => '45 Incorporated'],
      ['organization_name' => 'Rascals Group'],
      ['organization_name' => 'Cheats Ltd'],
    ];
  }

  /**
   * Enable all components.
   *
   * @throws \CRM_Core_Exception
   */
  protected function enableAllComponents() {
    $components = [];
    $dao = CRM_Core_DAO::executeQuery('SELECT id, name FROM civicrm_component');
    while ($dao->fetch()) {
      $components[$dao->id] = $dao->name;
    }
    $this->callAPISuccess('Setting', 'create', ['enable_components' => $components]);
  }

  /**
   * Get all extended reports reports except for ones involving log tables.
   *
   * @return array
   */
  public function getAllNonLoggingReports(): array {
    $reports = $this->getAllReports();
    $return = [];
    foreach ($reports as $report) {
      $return[] = [$report['params']['report_url']];
    }
    return $return;
  }

  /**
   * Get all extended reports reports.
   *
   * @return array
   */
  public function getAllReports(): array {
    $reports = [];
    extendedreport_civicrm_managed($reports);
    return $reports;
  }

  /**
   * Create contacts for test.
   *
   * @param int $quantity
   * @param string $type
   *
   * @return array|int
   *
   * @throws \CRM_Core_Exception
   */
  protected function createContacts($quantity = 1, $type = 'Individual') {
    $data = $this->getContactData($type, $quantity);
    $contacts = [];
    foreach ($data as $params) {
      $contact = $this->callAPISuccess('Contact', 'create', $params);
      $contacts[$contact['id']] = $contact['values'][$contact['id']];
      $this->ids['Contact'][] = $contact['id'];
    }
    return $contacts;
  }

  /**
   * Create a pledge dataset.
   *
   * We create 3 pledges
   *  - started one year ago $40,000 for Wonder Woman, 2 $10000 payments made (12 months & 6 months ago).
   *  - started just now $80,000 for Cat Woman, no payments made
   *  - started one month ago $100000 for Heros Inc, no payments made
   *
   * @throws \CRM_Core_Exception
   */
  public function setUpPledgeData() {
    $contacts = [
      [
        'first_name' => 'Wonder',
        'last_name' => 'Woman',
        'contact_type' => 'Individual',
        'api.pledge.create' => [
          'installments' => 4,
          'financial_type_id' => 'Donation',
          'amount' => 40000,
          'start_date' => '1 year ago',
          'create_date' => '1 year ago',
          'original_installment_amount' => 10000,
          'frequency_unit' => 'month',
          'frequency_interval' => 3,
        ],
        'api.contribution.create' => [
          [
            'financial_type_id' => 'Donation',
            'total_amount' => 10000,
            'receive_date' => '1 year ago',
          ],
          [
            'financial_type_id' => 'Donation',
            'total_amount' => 10000,
            'receive_date' => '6 months ago',
          ],
        ],
      ],
      [
        'first_name' => 'Cat',
        'last_name' => 'Woman',
        'contact_type' => 'Individual',
        'api.pledge.create' => [
          'installments' => 1,
          'financial_type_id' => 'Donation',
          'amount' => 80000,
          'start_date' => 'now',
          'create_date' => 'now',
          'original_installment_amount' => 80000,
        ],
      ],
      [
        'organization_name' => 'Heros Inc.',
        'contact_type' => 'Organization',
        'api.pledge.create' => [
          'installments' => 7,
          'financial_type_id' => 'Donation',
          'start_date' => '1 month ago',
          'create_date' => '1 month ago',
          'original_installment_amount' => 14285.71,
          'amount' => 100000,
        ],
      ],
    ];
    // Store the ids for later cleanup.
    $pledges = $this->callAPISuccess('Pledge', 'get', [])['values'];
    $this->ids['Pledge'] = array_keys($pledges);

    foreach ($contacts as $params) {
      $contact = $this->callAPISuccess('Contact', 'create', $params);
      $contributions = $this->callAPISuccess('Contribution', 'get', ['contact_id' => $contact['id']]);
      $pledges = $this->callAPISuccess('Pledge', 'get', ['contact_id' => $contact['id']]);
      foreach ($contributions['values'] as $contribution) {
        $this->callAPISuccess('PledgePayment', 'create', [
          'contribution_id' => $contribution['id'],
          'pledge_id' => $pledges['id'],
          'status_id' => 'Completed',
          'actual_amount' => $contribution['total_amount'],
        ]);
      }
      if (($params['organization_name'] ?? NULL) === 'Heros Inc.') {
        $this->callAPISuccess('PledgePayment', 'get', [
          'pledge_id' => $pledges['id'],
          'options' => ['limit' => 1, 'sort' => 'scheduled_date DESC'],
          'api.PledgePayment.create' => [
            'scheduled_amount' => 14285.74,
            'scheduled_date' => '2 years ago',
          ],
        ]);
      }
    }

  }

}
