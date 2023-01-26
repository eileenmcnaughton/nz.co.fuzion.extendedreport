<?php

namespace Civi\Extendedreport;

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
class BookkeepingExtendedTest extends BaseTestClass {

  protected $contacts = [];

  /**
   *
   */
  public function setUp(): void {
    parent::setUp();
    $this->enableAllComponents();
    $contact = $this->callAPISuccess('Contact', 'create', ['first_name' => 'Wonder', 'last_name' => 'Woman', 'contact_type' => 'Individual']);
    $this->ids['Contact'][] = $contact['id'];
  }

  /**
   * Test the bookkeeping report with some data.
   */
  public function testBookkeepingReport(): void {
    $this->callAPISuccess('Order', 'create', [
      'contact_id' => $this->ids['Contact'][0],
      'total_amount' => 5,
      'financial_type_id' => 2,
      'contribution_status_id' => 'Pending',
      'api.Payment.create' => ['total_amount' => 5],
    ]);
    $params = [
      'report_id' => 'contribution/bookkeeping_extended',
      'fields' => [
        'civicrm_contact_display_name' => '1',
        'membership_membership_type_id' => '1',
        'membership_membership_status_id' => '1',
        'membership_join_date' => '1',
        'membership_start_date' => '1',
        'line_item_financial_type_id' => '1',
        'line_item_line_total' => '1',
        'line_item_tax_amount' => '1',
        'contribution_source' => '1',
        'contribution_receive_date' => '1',
        'contribution_receipt_date' => '1',
        'financial_trxn_currency' => '1',
        'entity_financial_trxn_amount' => '1',
      ],
    ];
    $rows = $this->getRows($params);
    $this->assertEquals(date('Y-m-d'), date('Y-m-d', strtotime($rows[0]['civicrm_contribution_contribution_receive_date'])));
    $this->assertEquals('USD', $rows[0]['civicrm_financial_trxn_financial_trxn_currency']);
    // If this line fails data cleanup is a likely culprit - this should be the only contribution in the database
    // We might be better to ditch the TransactionalInterface as it has long been fraught.
    $this->assertEquals('5.00', $rows[0]['civicrm_entity_financial_trxn_entity_financial_trxn_amount_sum']);
  }

}
