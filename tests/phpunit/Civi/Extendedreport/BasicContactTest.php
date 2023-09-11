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
class BasicContactTest extends BaseTestClass {

  protected $contacts = [];

  /**
   */
  public function setUp(): void {
    parent::setUp();
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
   */
  public function tearDown(): void {
    $fields = $this->callAPISuccess('CustomField', 'get', ['custom_group_id' => $this->customGroupID])['values'];
    foreach ($fields as $field) {
      $this->callAPISuccess('CustomField', 'delete', ['id' => $field['id']]);
    }

    $this->callAPISuccess('CustomGroup', 'delete', ['id' => $this->customGroupID]);
    parent::tearDown();
  }

  /**
   * Test custom field filter works.
   *
   * @throws \CRM_Core_Exception
   */
  public function testCustomFieldFilter(): void {
    $customField = $this->customFieldCreate(['html_type' => 'Autocomplete-Select', 'data_type' => 'ContactReference', 'default_value' => '', 'custom_group_id' => $this->customGroupID]);
    $this->callAPISuccess('Contact', 'create', ['custom_' . $customField['id'] => $this->contacts[0], 'id' => $this->contacts['1']]);
    $params = [
      'report_id' => 'contact/contactbasic',
      'custom_' . $customField['id'] . '_value' => 'Amaz',
      'custom_' . $customField['id'] . '_op' => 'has',
      'fields' => [
        'civicrm_contact_display_name' => '1',
        'civicrm_contact_contact_id' => '1',
        'custom_' . $customField['id'] => '1',
        'class' => NULL,
      ],
    ];
    $rows = $this->getRows($params);
    $this->assertEquals([
      0 =>
        [
          'civicrm_contact_civicrm_contact_display_name' => 'Wonder Woman',
          'civicrm_contact_civicrm_contact_contact_id' => $this->contacts[1],
          'contact_custom_' . $customField['id'] . '_civireport' => 'Amazons',
          'civicrm_contact_civicrm_contact_contact_id_link' => '/index.php?q=civicrm/contact/view&amp;reset=1&amp;cid=' . $this->contacts[1],
          'class' => NULL,
        ],
    ], $rows);
    $params['custom_' . $customField['id'] . '_value'] = 'Wonder';
    $rows = $this->getRows($params);
    $this->assertEmpty($rows);
  }

}
