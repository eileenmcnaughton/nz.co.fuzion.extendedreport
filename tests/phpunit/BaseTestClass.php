<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

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
class BaseTestClass extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  use \Civi\Test\Api3TestTrait;

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  /**
   * @var string
   *   SQL returned from the report.
   */
  protected $sql;

  /**
   * @param $params
   * @return array|int
   */
  protected function getRows($params) {
    $params['options']['metadata'] = array('title', 'label', 'sql');
    $rows = $this->callAPISuccess('ReportTemplate', 'getrows', $params);
    $this->sql = $rows['metadata']['sql'];
    $rows = $rows['values'];
    return $rows;
  }

  /**
   * Create a custom group with a single text custom field.
   *
   * This is breaking my heart - do it with traits - but not until next month
   * when 5.3 gets removed.
   *
   * @param array $params
   * @param string $entity
   *
   * @return array
   *   ids of created objects
   */
  protected function createCustomGroupWithField($params = array(), $entity = 'Contact') {
    $params = array('title' => $entity);
    $params['extends'] = $entity;
    CRM_Core_PseudoConstant::flush();

    // cleanup first to save misery.
    $fields = $this->callAPISuccess('CustomField', 'get', array('name' => $entity));
    foreach ($fields['values'] as $field) {
      $this->callAPISuccess('CustomField', 'delete', array('id' => $field['id']));
    }
    $groups = $this->callAPISuccess('CustomGroup', 'get', array('name' => $entity));
    foreach ($groups['values'] as $group) {
      if (CRM_Core_DAO::singleValueQuery("SHOW TABLES LIKE '" . $group['table_name'] . "'")) {
        $this->callAPISuccess('CustomGroup', 'delete', array('id' => $group['id']));
      }
      else {
        CRM_Core_DAO::executeQuery('DELETE FROM civicrm_group WHERE id = ' . $group['id']);
      }

    }
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_cache');
    CRM_Core_PseudoConstant::flush();

    $customGroup = $this->CustomGroupCreate($params);
    $customField = $this->customFieldCreate(array('custom_group_id' => $customGroup['id'], 'label' => $entity));
    $this->customGroupID = $customGroup['id'];
    $this->customGroup = $customGroup['values'][$customGroup['id']];
    $this->customFieldID = $customField['id'];
    CRM_Core_PseudoConstant::flush();

    return array('custom_group_id' => $customGroup['id'], 'custom_field_id' => $customField['id']);
  }

  /**
   * Create custom group.
   *
   * @param array $params
   * @return array
   */
  public function customGroupCreate($params = array()) {
    $defaults = array(
      'title' => 'new custom group',
      'extends' => 'Contact',
      'domain_id' => 1,
      'style' => 'Inline',
      'is_active' => 1,
    );

    $params = array_merge($defaults, $params);

    if (strlen($params['title']) > 13) {
      $params['title'] = substr($params['title'], 0, 13);
    }

    //have a crack @ deleting it first in the hope this will prevent derailing our tests
    $this->callAPISuccess('custom_group', 'get', array(
      'title' => $params['title'],
      array('api.custom_group.delete' => 1),
    ));

    return $this->callAPISuccess('custom_group', 'create', $params);
  }


  /**
   * Create custom field.
   *
   * @param array $params
   *   (custom_group_id) is required.
   * @return array
   */
  protected function customFieldCreate($params) {
    $params = array_merge(array(
      'label' => 'Custom Field',
      'data_type' => 'String',
      'html_type' => 'Text',
      'is_searchable' => 1,
      'is_active' => 1,
      'default_value' => 'defaultValue',
    ), $params);

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
  public function getContactData($contactType, $quantity) {
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

}
