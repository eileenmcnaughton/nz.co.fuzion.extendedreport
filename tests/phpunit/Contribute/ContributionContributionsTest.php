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
   */
  public function testGetRows() {
    $params = [
      'report_id' => 'contribution/contributions',
      'fields' => [
        'contribution_id' => '1',
        'contribution_financial_type_id' => '1',
        'contribution_campaign_id' => '1',
        'contribution_source' => '1',
      ],
      'group_bys' => ['contribution_campaign_id' => '1'],
      'order_bys' => [['column' => 'contribution_check_number', 'order' => 'ASC']],
    ];
    $this->callAPISuccess('ReportTemplate', 'getrows', $params)['values'];
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

}
