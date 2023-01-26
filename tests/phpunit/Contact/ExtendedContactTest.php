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
class Contact_ExtendedContactTest extends BaseTestClass implements HeadlessInterface, HookInterface, TransactionalInterface {

  protected $contacts = [];

  public function setUp(): void {
    parent::setUp();
    $this->enableAllComponents();
    $this->createCustomGroupWithField(['CustomField' => ['html_type' => 'CheckBox', 'option_values' => ['two' => 'A couple', 'three' => 'A few', 'four' => 'Too Many']]]);
    $contact = $this->callAPISuccess('Contact', 'create', ['organization_name' => 'Amazons', 'last_name' => 'Woman', 'contact_type' => 'Organization', 'custom_' . $this->customFieldID => 'three']);

    $this->contacts[] = $contact['id'];
    $contact = $this->callAPISuccess('Contact', 'create', [
      'first_name' => 'Wonder',
      'last_name' => 'Woman',
      'contact_type' => 'Individual',
      'employer_id' => $contact['id'],
      'custom_' . $this->customFieldID => 'two',
      'gender_id' => 'Female',
    ]);
    $this->contacts[] = $contact['id'];
  }

  /**
   * Clean up after test.
   *
   * @throws \CRM_Core_Exception
   */
  public function tearDown(): void {
    parent::tearDown();
    $this->callAPISuccess('CustomField', 'delete', ['id' => $this->customFieldID]);
    $this->callAPISuccess('CustomGroup', 'delete', ['id' => $this->customGroupID]);
    foreach ($this->contacts as $contact) {
      $this->callAPISuccess('Contact', 'delete', ['id' => $contact, 'skip_undelete' => TRUE]);
    }
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_cache');
    CRM_Core_PseudoConstant::flush();
  }

  /**
   * Test rows retrieval.
   */
  public function testGetRows() {
    $params = [
      'report_id' => 'contact/contactextended',
      'aggregate_column_headers' => 'civicrm_contact_gender_id',
      'aggregate_row_headers' => 'custom_' . $this->customFieldID,
    ];
    $this->callAPISuccess('ReportTemplate', 'getrows', $params)['values'];

    $params = [
      'report_id' => 'contact/contactextended',
      'aggregate_column_headers' => 'custom_' . $this->customFieldID,
      'aggregate_row_headers' => 'civicrm_contact_gender_id',
    ];
    $rows = $this->callAPISuccess('ReportTemplate', 'getrows', $params)['values'];
    $this->assertEquals('Female', $rows[1]['civicrm_contact_civicrm_contact_gender_id']);
  }

}
