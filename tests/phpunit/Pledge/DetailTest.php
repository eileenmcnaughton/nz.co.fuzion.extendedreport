<?php

require_once __DIR__ . '/../BaseTestClass.php';

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
class Pledge_DetailTest extends BaseTestClass implements HeadlessInterface, HookInterface {

  protected $ids = [];

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    parent::setUp();
  }

  public function tearDown() {
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_pledge');
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_group');
    parent::tearDown();
  }

  /**
   * Test the future income report with some data.
   */
  public function testPledgeDetailReport() {
    $this->setUpPledgeData();
    $params = array(
      'report_id' => 'pledge/details',
      'fields' => [
        'civicrm_contact_display_name' => '1',
        'civicrm_contact_contact_id' => '1',
        'pledge_amount' => '1',
        'balance_amount' => 1,
      ],
      'effective_date_op' => 'to',
      'effective_date_value' => date('Y-m-d', strtotime('3 weeks ago')),
    );
    $rows = $this->getRows($params);
    $this->assertEquals(3, count($rows));
    $this->assertEquals(20000, $rows[0]['civicrm_pledge_payment_balance_amount_sum']);
  }

}
