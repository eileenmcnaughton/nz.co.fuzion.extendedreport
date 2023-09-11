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
class LineItemParticipantTest extends BaseTestClass {

  protected $contacts = [];

  public function setUp(): void {
    parent::setUp();
    $this->enableAllComponents();
    $contact = $this->callAPISuccess('Contact', 'create', ['first_name' => 'Wonder', 'last_name' => 'Woman', 'contact_type' => 'Individual']);
    $this->contacts[] = $contact['id'];
  }

  /**
   * Test the report runs.
   *
   * @dataProvider getReportParameters
   *
   * @param array $params
   *   Parameters to pass to the report
   *
   * @throws \CRM_Core_Exception
   */
  public function testReport($params) {
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
          'report_id' => 'price/lineitemparticipant',
          'fields' => [
            'event_event_id' => '1',
            'civicrm_contact_display_name' => '1',
            'contribution_payment_instrument_id' => 1,
            'email_email' => 1,
          ],
        ],
      ],
    ];
  }
}
