<?php

/**
 * ReportTemplate.Getmetadata API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_report_template_getmetadata_spec(array &$spec): void {

}

/**
 * ReportTemplate.Getmetadata API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @throws \CRM_Core_Exception
 *
 * @see civicrm_api3_create_error
 * @see civicrm_api3_create_success
 */
function civicrm_api3_report_template_getmetadata(array $params): array {
  if (empty($params['report_id'])) {
    $params['report_id'] = civicrm_api3('ReportInstance', 'getvalue', ['id' => $params['instance_id'], 'return' => 'report_id']);
  }

  $class = (string) civicrm_api3('OptionValue', 'getvalue', [
      'option_group_name' => 'report_template',
      'return' => 'name',
      'value' => $params['report_id'],
      'options' => ['limit' => 1],
    ]
  );

  $reportInstance = new $class();
  /* @var $reportInstance \CRM_Extendedreport_Form_Report_ExtendedReport */
  if (!empty($params['instance_id'])) {
    $reportInstance->setID($params['instance_id']);
  }
  $metadata = $reportInstance->getMetadata();
  return civicrm_api3_create_success($metadata, $params, 'ReportTemplate');
}
