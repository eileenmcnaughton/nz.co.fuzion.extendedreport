<?php

require_once __DIR__ . '/BaseTestClass.php';

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
class RelationshipExtendedTest extends BaseTestClass implements HeadlessInterface, HookInterface {

  protected $contacts = array();

  /**
   * @return \Civi\Test\CiviEnvBuilder
   */
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
    $components = array();
    $dao = new CRM_Core_DAO_Component();
    while ($dao->fetch()) {
      $components[$dao->id] = $dao->name;
    }
    civicrm_api3('Setting', 'create', array('enable_components' => $components));
    $this->createCustomGroupWithField();

    $contact = $this->callAPISuccess('Contact', 'create', array('organization_name' => 'Amazons', 'last_name' => 'Woman', 'contact_type' => 'Organization', 'custom_' . $this->customFieldID => 'super org'));
    $this->contacts[] = $contact['id'];
    $contact = $this->callAPISuccess('Contact', 'create', array('first_name' => 'Wonder', 'last_name' => 'Woman', 'contact_type' => 'Individual', 'employer_id' => $contact['id'], 'custom_' . $this->customFieldID => 'just a gal'));
    $this->contacts[] = $contact['id'];

  }

  public function tearDown() {
    parent::tearDown();
    $this->callAPISuccess('CustomField', 'delete', array('id' => $this->customFieldID));
    $this->callAPISuccess('CustomGroup', 'delete', array('id' => $this->customGroupID));
    foreach ($this->contacts as $contact) {
      $this->callAPISuccess('Contact', 'delete', ['id' => $contact]);
    }
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_cache');
    CRM_Core_PseudoConstant::flush();
  }

  /**
   * Test the report with group filter.
   */
  public function testReport() {
    $customFieldPrefix = 'custom_contact_a__' . $this->customFieldID;
    $params = array(
      'report_id' => 'relationshipextended',
      'fields' => array (
        'relationship_type_label_a_b' => '1',
      ),
      $customFieldPrefix . '_op' => "like",
      $customFieldPrefix .  '_value' => '%g%',
    );
    $rows = $this->getRows($params);
    $this->assertEquals(1, count($rows));
    $this->assertEquals('Employee of', $rows[0]['civicrm_relationship_type_relationship_type_label_a_b']);
  }

  /**
   * Test the report with group filter.
   */
  public function testReportWithGroupFilter() {
    $params = array(
      'report_id' => 'relationshipextended',
      'fields' => array (
        'relationship_type_label_a_b' => '1',
      ),
      'gid_op' => 'in',
      'gid_value' => array(1),
      'contact_a_civicrm_contact_civicrm_value_' . $this->customGroup['table_name'] . 'custom_ ' . $this->customFieldID . '_op' => "like",
      'contact_a_civicrm_contact_civicrm_value_' . $this->customGroup['table_name'] . 'custom_ ' . $this->customFieldID . '_value' => 'h',
    );
    $rows = $this->getRows($params);
    $this->assertEquals(array(), $rows);
  }

}
