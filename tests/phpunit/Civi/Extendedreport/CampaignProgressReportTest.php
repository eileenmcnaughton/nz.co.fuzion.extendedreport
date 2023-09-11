<?php

namespace Civi\Extendedreport;

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
class CampaignProgressReportTest extends BaseTestClass {

  protected $contacts = [];

  public function setUp(): void {
    parent::setUp();
    $this->enableAllComponents();
    $contact = $this->callAPISuccess('Contact', 'create', ['first_name' => 'Wonder', 'last_name' => 'Woman', 'contact_type' => 'Individual']);
    $this->contacts[] = $contact['id'];
  }

  /**
   * Test the Progress report.
   *
   * @dataProvider getReportParameters
   *
   * @param array $params
   *   Parameters to pass to the report
   */
  public function testProgressReport(array $params): void {
    $this->callAPISuccess('Order', 'create', ['contact_id' => $this->contacts[0], 'total_amount' => 5, 'financial_type_id' => 2, 'contribution_status_id' => 'Pending', 'api.Payment.create' => ['total_amount' => 5]]);
    // Just checking no error at the moment.
    $this->getRows($params);
  }

  /**
   * Get datasets for testing the report
   */
  public function getReportParameters(): array {
    return [
      'basic' => [
        [
          'report_id' => 'campaign/progress',
          'fields' => [
            'campaign_id' => '1',
            'total_amount' => '1',
            'paid_amount' => '1',
            'balance_amount' => '1',
          ],
        ],
      ],
      'group_by_variant' => [
        [
          'report_id' => 'campaign/progress',
          'fields' => [
            'campaign_id' => '1',
            'total_amount' => '1',
            'paid_amount' => '1',
            'balance_amount' => '1',
          ],
          'group_bys' => [
            'campaign_id' => '1',
          ],
        ],
      ],
    ];
  }

}
