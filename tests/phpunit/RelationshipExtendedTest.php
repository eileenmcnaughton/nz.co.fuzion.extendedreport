<?php

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
class RelationshipExtendedTest extends BaseTestClass {

  protected $contacts = [];

  /**
   * @throws \CRM_Core_Exception
   */
  public function setUp(): void {
    parent::setUp();
    $components = [];
    $dao = new CRM_Core_DAO_Component();
    while ($dao->fetch()) {
      $components[$dao->id] = $dao->name;
    }
    $this->callAPISuccess('Setting', 'create', ['enable_components' => $components]);
    $this->createCustomGroupWithField();

    $contact = $this->callAPISuccess('Contact', 'create', [
      'organization_name' => 'Amazons',
      'last_name' => 'Woman',
      'contact_type' => 'Organization',
      'custom_' . $this->customFieldID => 'super org',
    ]);
    $this->contacts[] = $contact['id'];
    $contact = $this->callAPISuccess('Contact', 'create', [
      'first_name' => 'Wonder',
      'last_name' => 'Woman',
      'contact_type' => 'Individual',
      'employer_id' => $contact['id'],
      'custom_' . $this->customFieldID => 'just a gal',
    ]);
    $this->contacts[] = $contact['id'];

  }

  /**
   */
  public function tearDown(): void {
    parent::tearDown();
    $this->callAPISuccess('CustomField', 'delete', ['id' => $this->customFieldID]);
    $this->callAPISuccess('CustomGroup', 'delete', ['id' => $this->customGroupID]);
    foreach ($this->contacts as $contact) {
      $this->callAPISuccess('Contact', 'delete', ['id' => $contact]);
    }
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_cache');
    CRM_Core_PseudoConstant::flush();
  }

  /**
   * Test the report with group filter.
   */
  public function testReport(): void {
    $customFieldPrefix = 'custom_contact_a__' . $this->customFieldID;
    $params = [
      'report_id' => 'relationshipextended',
      'fields' => [
        'relationship_type_label_a_b' => '1',
      ],
      $customFieldPrefix . '_op' => 'like',
      $customFieldPrefix . '_value' => '%g%',
    ];
    $rows = $this->getRows($params);
    $this->assertCount(1, $rows);
    $this->assertEquals('Employee of', $rows[0]['civicrm_relationship_type_relationship_type_label_a_b']);
  }

  /**
   * Test the report with group filter.
   */
  public function testReportWithGroupFilter(): void {
    $params = [
      'report_id' => 'relationshipextended',
      'fields' => [
        'relationship_type_label_a_b' => '1',
      ],
      'gid_op' => 'in',
      'gid_value' => [1],
      'contact_a_civicrm_contact_civicrm_value_' . $this->customGroup['table_name'] . 'custom_ ' . $this->customFieldID . '_op' => "like",
      'contact_a_civicrm_contact_civicrm_value_' . $this->customGroup['table_name'] . 'custom_ ' . $this->customFieldID . '_value' => 'h',
    ];
    $rows = $this->getRows($params);
    $this->assertEquals([], $rows);
  }

}
