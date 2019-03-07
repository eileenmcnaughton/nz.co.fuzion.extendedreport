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
class Contact_AddressHistoryTest extends BaseTestClass implements HeadlessInterface, HookInterface, TransactionalInterface {

  protected $contacts = array();

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    $env = \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
    return $env;
  }

  public function setUp() {
    parent::setUp();
    $contact = $this->callAPISuccess('Contact', 'create', array('first_name' => 'Wonder', 'last_name' => 'Woman', 'contact_type' => 'Individual'));
    $this->contacts[] = $contact['id'];
    Civi::settings()->set('logging', TRUE);
  }

  public function tearDown() {
    parent::tearDown();
    Civi::settings()->set('logging', FALSE);
  }

  /**
   * Test rows retrieval.
   */
  public function testGetRows() {
    $params = [
      'report_id' => 'contact/addresshistory',
    ];
    $rows = $this->getRows($params);
    $this->assertEquals('15 Main St<br />
Collinsville, Connecticut 6022<br />
United States<br />', trim($rows[0]['log_civicrm_address_address_display_address']));
    $this->assertEquals([
      'log_civicrm_address_address_display_address' => 'Display Address',
      'log_civicrm_address_log_date' => 'Change Date',
      'log_civicrm_address_log_conn_id' => 'Connection',
      'log_civicrm_address_log_user_id' => 'Changed By',
      'log_civicrm_address_log_action' => 'Change action',
    ], $this->labels);
  }

}
