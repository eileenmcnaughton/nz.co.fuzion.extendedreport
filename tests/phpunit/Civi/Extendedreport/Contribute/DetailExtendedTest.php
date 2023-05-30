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
class DetailExtendedTest extends BaseTestClass {

  /**
   * Test joining in the batch table.
   */
  public function testBatchJoin(): void {
    $batchID = $this->callAPISuccess('Batch', 'create', ['title' => 'batch', 'status_id' => 'Open'])['id'];
    $this->ids['contact'][] = $this->callAPISuccess('Contact', 'create', ['contact_type' => 'Individual', 'email' => 'e@example.com'])['id'];
    $this->callAPISuccess('Contribution', 'create', [
      'contact_id' => $this->ids['contact'][0],
      'total_amount' => 5,
      'financial_type_id' => 'Donation',
      'batch_id' => $batchID,
      'status_id' => 'Completed',
    ]);

    $this->callAPISuccess('Contribution', 'create', [
      'contact_id' => $this->ids['contact'][0],
      'total_amount' => 15,
      'financial_type_id' => 'Donation',
      'status_id' => 'Completed',
    ]);

    $params = [
      'report_id' => 'contribution/detailextended',
      'fields' => [
        'contribution_id' => '1',
        'contribution_financial_type_id' => '1',
        'contribution_campaign_id' => '1',
        'contribution_source' => '1',
        'batch_title' => 1,
      ],
      'batch_title_op' => 'nnll',
      'batch_title_value' => '',
    ];
    $rows = $this->callAPISuccess('ReportTemplate', 'getrows', $params)['values'];
    $this->assertCount(1, $rows);
  }

}
