<?php

namespace Civi\Extendedreport;

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
class ParticipantExtendedTest extends BaseTestClass {

  /**
   * Test the future income report with some data.
   */
  public function testReportDateSql(): void {
    $params = [
      'report_id' => 'event/participantlistex',
      'fields' => [
        'civicrm_contact_display_name' => '1',
        'civicrm_contact_contact_id' => '1',
        'civicrm_contact_contact_type' => '1',
        'civicrm_contact_contact_sub_type' => '1',
        'civicrm_contact_last_name' => '1',
        'civicrm_contact_nick_name' => '1',
        'civicrm_contact_age' => '1',
        'email_email' => '1',
        'address_name' => '1',
        'address_display_address' => '1',
        'address_street_address' => '1',
        'address_supplemental_address_1' => '1',
        'address_street_unit' => '1',
        'address_city' => '1',
        'address_county_id' => '1',
        'address_state_province_id' => '1',
        'address_id' => '1',
        'address_is_primary' => '1',
        'participant_id' => '1',
        'participant_participant_event_id' => '1',
        'participant_participant_role_id' => '1',
        'participant_participant_fee_level' => '1',
        'participant_register_date' => '1',
        'phone_phone' => '1',
        'event_event_id' => '1',
        'event_title' => '1',
        'event_event_type_id' => '1',
        'event_event_start_date' => '1',
        'event_event_end_date' => '1',
        'event_is_public' => '1',
        'contribution_id' => '1',
        'contribution_financial_type_id' => '1',
        'contribution_campaign_id' => '1',
        'contribution_source' => '1',
        'contribution_receipt_date' => '1',
        'contribution_total_amount' => '1',
        'contribution_check_number' => '1',
        'contribution_contribution_page_id' => '1',
        'line_item_financial_type_id' => '1',
        'line_item_participant_count' => '1',
        'line_item_contribution_id' => '1',
        'line_item_line_total' => '1',
        'note_note' => '1',
        'related_civicrm_contact_related_display_name' => '1',
        'related_civicrm_contact_related_contact_id' => '1',
        'related_civicrm_contact_related_contact_type' => '1',
        'related_civicrm_contact_related_contact_sub_type' => '1',
        'related_civicrm_contact_related_last_name' => '1',
        'related_civicrm_contact_related_nick_name' => '1',
        'related_civicrm_contact_age' => '1',
        'related_email_email' => '1',
        'related_phone_related_phone' => '1',
      ],
    ];
    $this->getRows($params);
  }

  /**
   * Test custom data on main entity.
   */
  public function testCustomData(): void {
    $ids = $this->createCustomGroupWithField([]);
    $this->getRows([
      'report_id' => 'event/participantlistex',
      'fields' => [
        'civicrm_contact_display_name' => 1,
        'custom_' . $ids['custom_field_id'] => 1,
      ],
    ]);
  }

}
