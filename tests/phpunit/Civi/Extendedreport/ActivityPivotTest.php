<?php

namespace Civi\Extendedreport;

use CRM_Core_PseudoConstant;

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
class ActivityPivotTest extends BaseTestClass {

  /**
   * Test the future income report with some data.
   *
   */
  public function testPivotReport(): void {
    $contact = $this->callAPISuccess('Contact', 'create', ['contact_type' => 'Individual', 'email' => 'demo@example.com']);
    $this->ids['Contact'][] = $contact['id'];
    $this->callAPISuccess('Activity', 'create', ['source_contact_id' => $contact['id'], 'activity_type_id' => 'Meeting', 'status_id' => 'Scheduled']);
    $this->callAPISuccess('Activity', 'create', ['source_contact_id' => $contact['id'], 'activity_type_id' => 'Meeting', 'status_id' => 'Completed']);
    $this->callAPISuccess('Activity', 'create', ['source_contact_id' => $contact['id'], 'activity_type_id' => 'Phone Call', 'status_id' => 'Completed']);

    $params = [
      'report_id' => 'activity/pivot',
      'aggregate_column_headers' => 'activity_status_id',
      'aggregate_row_headers' => 'activity_activity_type_id',
    ];
    $completedStatusID = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'status_id', 'Completed');
    $scheduledStatusID = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'status_id', 'Scheduled');
    $rows = $this->getRows($params);
    $this->assertStringContainsString('SUM( CASE', $this->sql[0]);
    $this->assertEquals('Meeting', $rows[0]['civicrm_activity_activity_activity_type_id']);
    $this->assertEquals(2, $rows[0]['status_id_total']);
    $this->assertEquals(1, $rows[0]['status_id_' . $completedStatusID]);
    $this->assertEquals(1, $rows[0]['status_id_' . $scheduledStatusID]);
    $this->assertEquals('Phone Call', $rows[1]['civicrm_activity_activity_activity_type_id']);
    $this->assertEquals(1, $rows[1]['status_id_total']);
    $this->assertEquals(1, $rows[1]['status_id_' . $completedStatusID]);
    $this->assertEquals(0, $rows[1]['status_id_' . $scheduledStatusID]);
  }

  /**
   * Test that there is not an sql error from the custom join failing.
   *
   * @throws \CRM_Core_Exception
   */
  public function testCustomDataInPivot(): void {
    $ids = $this->createCustomGroupWithField([], 'Activity');
    $this->getRows([
      'report_id' => 'activity/pivot',
      'aggregate_column_headers' => 'activity_activity_type_id',
      'aggregate_row_headers' => 'custom_' . $ids['custom_field_id'],
    ]);
  }

}
