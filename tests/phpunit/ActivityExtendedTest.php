<?php

require_once __DIR__ . '/BaseTestClass.php';

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

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
class ActivityExtendedTest extends BaseTestClass implements HeadlessInterface, HookInterface, TransactionalInterface {

  /**
   * Test the future income report with some data.
   *
   * @throws \CRM_Core_Exception
   */
  public function testReportDateSql() {
    $params = [
      'report_id' => 'activityextended',
      'activity_activity_date_time_relative' => 'previous.month',
    ];
    $this->getRows($params);
    $this->assertContains('235959', $this->sql[0]);
  }

  /**
   * Test filtering on cid.
   *
   * @throws \CRM_Core_Exception
   */
  public function testCidFilter() {
    $params = [
      'report_id' => 'activityextended',
      'fields' =>
        [
          'activity_activity_type_id' => '1',
          'activity_subject' => '1',
          'activity_activity_date_time' => '1',
          'activity_status_id' => '1',
        ],
    ];
    $this->getRows($params);
  }

}
