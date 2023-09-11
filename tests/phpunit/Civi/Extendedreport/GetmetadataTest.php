<?php

namespace Civi\Extendedreport;


/**
 * ReportTemplate.Getmetadata API Test Case
 * This is a generic test class implemented with PHPUnit.
 *
 * @group headless
 */
class GetmetadataTest extends BaseTestClass {

  /**
   * Simple example test case.
   *
   * Note how the function name begins with the word "test".
   *
   * @throws \CRM_Core_Exception
   */
  public function testApiMetadata(): void {
    $ids = $this->createCustomGroupWithField(['CustomField' => ['data_type' => 'Int', 'default_value' => 2]], 'Pledge');
    $dateField = $this->customFieldCreate([
      'custom_group_id' => $ids['custom_group_id'],
      'data_type' => 'Date',
      'default_value' => '',
      'html_type' => 'Select Date',
      'name' => 'date_field',
      'label' => 'date_field',
    ]);
    $selectField = $this->customFieldCreate([
      'custom_group_id' => $ids['custom_group_id'],
      'data_type' => 'String',
      'default_value' => '',
      'html_type' => 'Select',
      'name' => 'select_field',
      'label' => 'select_field',
    ]);

    $multiSelectField = $this->customFieldCreate([
      'custom_group_id' => $ids['custom_group_id'],
      'data_type' => 'String',
      'default_value' => '',
      'html_type' => 'Multi-Select',
      'name' => 'multi_select_field',
      'label' => 'multi_select_field',
    ]);

    $booleanField = $this->customFieldCreate([
      'custom_group_id' => $ids['custom_group_id'],
      'data_type' => 'Boolean',
      'default_value' => '',
      'html_type' => 'Radio',
      'name' => 'bool_select_field',
      'label' => 'bool_select_field',
    ]);

    $result = $this->callAPISuccess('ReportTemplate', 'Getmetadata', ['debug' => 1, 'report_id' => 'pledge/details'])['values'];
    $filters = $result['filters'];

    foreach ($filters as $fieldName => $filter) {
      $this->assertEquals(TRUE, $filter['is_filters']);
      $this->assertEquals($result['metadata'][$fieldName], $filter);
      $knownNoFieldFilters = ['effective_date', 'tagid', 'gid', 'pledge_payment_status_id', 'civicrm_contact_is_deleted'];
      if (!in_array($fieldName, $knownNoFieldFilters, TRUE)) {
        $this->assertEquals($result['fields'][$fieldName], $filter, 'mismatch in ' . $fieldName);
      }
    }
    $this->assertNotEmpty($result['order_bys']['custom_' . $ids['custom_field_id']]);
    $this->assertNotTrue(empty($result['group_bys']['custom_' . $ids['custom_field_id']]));
    $this->assertEquals(\CRM_Report_Form::OP_INT, $filters['custom_' . $ids['custom_field_id']]['operatorType']);
    $this->assertEquals(\CRM_Report_Form::OP_DATE, $filters['custom_' . $dateField['id']]['operatorType']);
    $this->assertEquals(\CRM_Report_Form::OP_MULTISELECT, $filters['custom_' . $selectField['id']]['operatorType']);
    $this->assertEquals(\CRM_Report_Form::OP_MULTISELECT_SEPARATOR, $filters['custom_' . $multiSelectField['id']]['operatorType']);
    $this->assertEquals('Pledge', $filters['custom_' . $multiSelectField['id']]['table_label']);
    $this->assertEquals(\CRM_Report_Form::OP_SELECT, $filters['custom_' . $booleanField['id']]['operatorType']);

    foreach ([$dateField['id'], $ids['custom_field_id'], $selectField['id'], $multiSelectField['id'], $booleanField['id']] as $id) {
      $this->callAPISuccess('CustomField', 'delete', ['id' => $id]);
    }
  }

  /**
   * Test getmetdata works on all reports.
   *
   * @dataProvider getAllNonLoggingReports
   *
   * @param string $reportID
   *
   */
  public function testApiMetadataAllReports(string $reportID): void {
    $result = $this->callAPISuccess('ReportTemplate', 'Getmetadata', ['report_id' => $reportID, 'debug' => 1])['values'];
    $filters = $result['filters'];
    foreach ($filters as $fieldName => $filter) {
      $this->assertEquals(TRUE, $filter['is_filters']);
      $this->assertEquals($result['metadata'][$fieldName], $filter, 'for report ' . $reportID . ' and ' . $fieldName);
      $knownNoFieldFilters = ['effective_date', 'tagid', 'gid', 'pledge_payment_status_id'];
      if (!in_array($fieldName, $knownNoFieldFilters) && $filter['is_fields']) {
        $this->assertEquals($result['fields'][$fieldName], $filter);
        $this->assertNotEmpty($filter['operatorType'], $fieldName . ' has no operator Type');
      }
    }
  }

  /**
   * Test the metadata generated for the address history report.
   *
   */
  public function testApiMetadataContactFilters(): void {
    $result = $this->callAPISuccess('ReportTemplate', 'Getmetadata', ['report_id' => 'contact/addresshistory'])['values'];
    $this->assertEquals(TRUE, $result['metadata']['contact_id']['is_contact_filter']);
    $this->assertEmpty($result['order_bys']);
  }

}
