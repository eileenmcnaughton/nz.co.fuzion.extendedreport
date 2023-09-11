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
class ContributionPivotTest extends BaseTestClass {

  protected $contacts = [];

  /**
   * Test rows retrieval.
   *
   * @dataProvider getRowVariants
   *
   * @param $overrides
   */
  public function testGetRows($overrides): void {
    $this->ids['Contact'][0] = $this->callAPISuccess('Contact', 'create', [
      'contact_type' => 'Individual',
      'first_name' => 'Charlie',
      'last_name' => 'Chaplin',
    ])['id'];
    foreach ([2, 3, 4] as $year) {
      $this->callAPISuccess('Contribution', 'create', [
        'contact_id' => $this->ids['Contact'][0],
        'financial_type_id' => 'Donation',
        'receive_date' => (2000 + $year) . '09-09',
        'payment_instrument_id' => 'Cash',
        'total_amount' => 2345.44,
        'skipRecentView' => TRUE,
      ]);
    }
    $params = array_merge([
      'report_id' => 'contribution/pivot'
    ], $overrides);

    $rows = $this->callAPISuccess('ReportTemplate', 'getrows', $params)['values'];
    $this->assertNotEmpty($rows);
  }

  public function getRowVariants(): array {
    return [
      [['aggregate_column_headers' => 'contribution_total_amount_year']],
      [['aggregate_column_headers' => 'contribution_total_amount_year', 'aggregate_row_headers' => 'contribution_financial_type_id']],
    ];
  }

}
