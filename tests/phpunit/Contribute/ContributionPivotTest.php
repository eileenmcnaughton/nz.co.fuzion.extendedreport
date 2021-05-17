<?php

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
   * @throws \CRM_Core_Exception
   */
  public function testGetRows($overrides): void {
    $params = array_merge([
      'report_id' => 'contribution/pivot'], $overrides);
    $this->callAPISuccess('ReportTemplate', 'getrows', $params)['values'];
  }

  public function getRowVariants(): array {
    return [
      [['aggregate_column_headers' => 'contribution_total_amount_year']]
    ];
  }

}
