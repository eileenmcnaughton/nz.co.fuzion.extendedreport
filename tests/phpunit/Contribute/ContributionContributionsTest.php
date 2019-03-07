<?php

require_once __DIR__ . '../../BaseTestClass.php';

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Test contribution DetailExtended class.
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
class ContributionContributionsTest extends BaseTestClass implements HeadlessInterface, HookInterface, TransactionalInterface {

  protected $contacts = array();

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  /**
   * Test metadata retrieval.
   */
  public function testGetMetadata() {
    $metadata = $this->callAPISuccess('ReportTemplate', 'getmetadata', ['report_id' => 'contribution/contributions'])['values'];
    $this->assertEquals('Contribution ID', $metadata['fields']['contribution_id']['title']);
    $this->assertTrue(is_array($metadata['fields']['contribution_id']));
  }

  /**
   * Test rows retrieval.
   *
   * @param array $overrides
   *   array to override function parameters
   *
   * @dataProvider getRowVariants
   */
  public function testGetRows($overrides) {
    $params = array_merge([
      'report_id' => 'contribution/contributions',
      'fields' => [
        'contribution_id' => '1',
        'contribution_financial_type_id' => '1',
        'contribution_campaign_id' => '1',
        'contribution_source' => '1',
      ],
    ], $overrides);
    $this->callAPISuccess('ReportTemplate', 'getrows', $params)['values'];
  }

  /**
   * Get variations of data to test against the report.
   *
   * @return array
   */
  public function getRowVariants() {
    return [
      [
        [
          'order_bys' => [['column' => 'contribution_check_number', 'order' => 'ASC']],
          'group_bys' => ['contribution_campaign_id' => '1'],
        ]
      ],
      [
        [
          'order_bys' => [['column' => 'contribution_check_number', 'order' => 'DESC']],
          'group_bys' => ['contribution_campaign_id' => '1'],
        ]
      ],
      [
        [
          'order_bys' => [['column' => 'contribution_check_number', 'order' => 'DESC', 'section' => 1]],
          'group_bys' => ['contribution_campaign_id' => '1'],
        ]
      ],
      [
        [
          'order_bys' => [['column' => 'contribution_check_number', 'order' => 'DESC', 'section' => 1, 'pageBreak' => 1]],
          'group_bys' => ['contribution_campaign_id' => '1'],
        ]
      ],
      [
        [
          'order_bys' => [['column' => 'contribution_check_number', 'order' => 'ASC']],
        ]
      ],
      [
        [
          'order_bys' => [['column' => 'contribution_check_number', 'order' => 'DESC']],
        ]
      ],
      [
        [
          'order_bys' => [['column' => 'contribution_check_number', 'order' => 'DESC', 'section' => 1]],
        ]
      ],
      [
        [
          'order_bys' => [['column' => 'contribution_check_number', 'order' => 'DESC', 'section' => 1, 'pageBreak' => 1]],
        ]
      ],
      [
        [
          'extended_order_bys' => [['column' => 'contribution_check_number', 'order' => 'DESC', 'title' => 'special']],
        ]
      ],
      [
        [
          'fields' => [
            'contribution_financial_type_id' => '1',
            'contribution_receive_date' => '1',
            'contribution_total_amount' => '1',
            'civicrm_contact_display_name' => '1',
          ],
          'order_bys' => [1 => ['column' => 'civicrm_contact_sort_name', 'order' => 'DESC', 'section' => 1]],
        ]
      ]
    ];
  }

  /**
   * Test that is doesn't matter if the having filter is selected.
   */
  public function testGetRowsHavingFilterNotSelected() {
    $params = [
      'report_id' => 'contribution/contributions',
      'contribution_total_amount_sum_op' => 'lte',
      'contribution_total_amount_sum_value' => '1000',
      'group_bys' => [
          'contribution_financial_type_id' => '1',
          'contribution_campaign_id' => '1',
      ],
      'order_bys' => [['column' => 'contribution_source', 'order' => 'ASC']],
    ];
    $this->callAPISuccess('ReportTemplate', 'getrows', $params)['values'];
  }

  /**
   * Test that between filter is respected.
   */
  public function testGetRowsHavingFilterBetween() {
    // Create 2 contacts - one with total of $50 & one with total of $100.
    $this->createContacts(2);
    $this->callAPISuccess('Contribution', 'create', [
      'financial_type_id' => 'Donation',
      'total_amount' => 50,
      'contact_id' => $this->ids['Contact'][0],
    ]);
    $this->callAPISuccess('Contribution', 'create', [
      'financial_type_id' => 'Donation',
      'total_amount' => 50,
      'contact_id' => $this->ids['Contact'][1],
    ]);
    $this->callAPISuccess('Contribution', 'create', [
      'financial_type_id' => 'Donation',
      'total_amount' => 50,
      'contact_id' => $this->ids['Contact'][1],
    ]);

    $params = [
      'report_id' => 'contribution/contributions',
      'contribution_total_amount_sum_op' => 'bw',
      'contribution_total_amount_sum_min' => '51',
      'contribution_total_amount_sum_max' => '100',
      'group_bys' => [
        'civicrm_contact_contact_id' => '1',
      ],
      'fields' => array_fill_keys(['contribution_financial_type_id', 'contribution_total_amount', 'civicrm_contact_contact_id'], 1),
    ];
    $rows = $this->callAPISuccess('ReportTemplate', 'getrows', $params)['values'];
    $this->assertEquals(1, count($rows));
    $this->assertEquals(100, $rows[0]['civicrm_contribution_contribution_total_amount_sum']);
    $this->assertEquals(2, $rows[0]['civicrm_contribution_contribution_total_amount_count']);
    // Make sure total amount is optional
    unset($params['fields']['contribution_total_amount']);
    $params['options']['metadata'] = ['labels'];
    $rows = $this->callAPISuccess('ReportTemplate', 'getrows', $params);
    $this->assertEquals(1, $rows['count']);
    $this->assertEquals([
      'civicrm_contact_civicrm_contact_contact_id' => 'Contact ID',
      'civicrm_contribution_contribution_financial_type_id' => 'Contribution Type (Financial)',
    ], $rows['metadata']['labels']);
  }

  /**
   * Test that is doesn't matter if the having filter is selected.
   */
  public function testGetRowsFilterCustomData() {
    $this->enableAllComponents();
    $ids = $this->createCustomGroupWithField([]);
    $contacts = $this->createContacts(2);
    foreach ($contacts as $contact) {
      $contribution = $this->callAPISuccess('Contribution', 'create', [
        'total_amount' => 4,
        'financial_type_id' => 'Donation',
        'contact_id' => $contact['id'],
      ]);
      $this->callAPISuccess('Contact', 'create', ['id' => $contact['id'], 'custom_' . $ids['custom_field_id'] => $contribution['id']]);
    }

    $params = [
      'report_id' => 'contribution/contributions',
      'fields' => ['contribution_id'],
      'custom_' . $ids['custom_field_id'] . '_op' => 'eq',
      'custom_' . $ids['custom_field_id'] . '_value' => $contribution['id'],
    ];
    $rows = $this->callAPISuccess('ReportTemplate', 'getrows', $params)['values'];
    $this->assertEquals(1, count($rows));
  }

}
