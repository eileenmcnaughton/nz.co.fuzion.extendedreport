<?php

namespace Civi\Extendedreport\Contribute;

use Civi\Extendedreport\BaseTestClass;

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
class ContributionOverviewExtendedTest extends BaseTestClass {

  protected $contacts = [];

  public function setUp(): void {
    parent::setUp();
    $this->enableAllComponents();
    $contact = $this->callAPISuccess('Contact', 'create', ['first_name' => 'Wonder', 'last_name' => 'Woman', 'contact_type' => 'Individual']);
    $this->ids['Contact'][] = $contact['id'];

    $this->callAPISuccess('Contribution', 'create', [
      'contact_id' => $contact['id'],
      'receive_date' => '2017-08-09',
      'total_amount' => 5,
      'financial_type_id' => 'Donation',
    ]);
    $this->callAPISuccess('Contribution', 'create', [
      'contact_id' => $contact['id'],
      'receive_date' => '1 month ago',
      'total_amount' => 500,
      'financial_type_id' => 'Donation',
    ]);
    $this->callAPISuccess('Contribution', 'create', [
      'contact_id' => $contact['id'],
      'receive_date' => '1 month ago',
      'total_amount' => 10,
      'financial_type_id' => 'Member Dues',
    ]);
  }

  /**
   * Test the ContributionOverviewExtended report with group by.
   */
  public function testContributionExtendedReport(): void {
    $this->callAPISuccess('Order', 'create', ['contact_id' => $this->ids['Contact'][0], 'total_amount' => 5, 'financial_type_id' => 2, 'contribution_status_id' => 'Pending', 'api.Payment.create' => ['total_amount' => 5]]);
    $params = [
      'report_id' => 'contribution/overview',
      'fields' => [
        'contribution_financial_type_id' => '1',
        'contribution_total_amount' => '1',
      ],
      'order_bys' => [
        1 => [
          'column' => '-',
        ],
      ],
      'group_bys' => [
        'contribution_financial_type_id' => 1,
      ],
    ];
    $rows = $this->getRows($params);
    $this->assertEquals('Member Dues', $rows[1]['civicrm_contribution_contribution_financial_type_id'], print_r($this->sql, TRUE) . print_r($rows, TRUE));
  }

  /**
   * Test the ContributionOverviewExtended report with to filter.
   */
  public function testContributionExtendedReportFilterReceiveDate(): void {
    $rows = $this->getRows([
      'report_id' => 'contribution/overview',
      'fields' => ['civicrm_contact_display_name' => '1'],
      'contribution_receive_date_relative' => '0',
      'contribution_receive_date_from' => '',
      'contribution_receive_date_from_time' => '',
      'contribution_receive_date_to' => '12/31/2017',
      'contribution_receive_date_to_time' => '',
    ]);
    $this->assertCount(1, $rows);
  }

}
