<?php
use CRM_Extendedreport_ExtensionUtil as E;

/**
 * ReportTemplate.Getmetadata API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_report_template_getmetadata_spec(&$spec) {

}

/**
 * ReportTemplate.Getmetadata API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_report_template_getmetadata($params) {
  if (empty($params['report_id'])) {
    $params['report_id'] = civicrm_api3('report_instance', 'getvalue', array('id' => $params['instance_id'], 'return' => 'report_id'));
  }

  $class = (string) civicrm_api3('option_value', 'getvalue', array(
      'option_group_name' => 'report_template',
      'return' => 'name',
      'value' => $params['report_id'],
    )
  );

  $reportInstance = new $class();
  if (!empty($params['instance_id'])) {
    $reportInstance->setID($params['instance_id']);
  }
  $metadata = $reportInstance->getMetadata();
  return civicrm_api3_create_success($metadata, $params, 'ReportTemplate');
}
