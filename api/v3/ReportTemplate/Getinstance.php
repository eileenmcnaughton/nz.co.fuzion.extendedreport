<?php

use CRM_Extendedreport_ExtensionUtil as E;

/**
 * ReportTemplate.getinstance API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_report_template_getinstance_spec(&$spec) {
}

/**
 * ReportTemplate.getinstance API
 *
 * Wrapper for ReportInstance.get that ensures php deserialization is done.
 *
 * @param array $params
 *
 * @return array API result descriptor
 *
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_report_template_getinstance($params) {
  $result = civicrm_api3('ReportInstance', 'get', $params);
  foreach ($result['values'] as $index => $value) {
    if (!is_array($value['form_values'])) {
      $result['values'][$index]['form_values'] = unserialize($value['form_values']);
    }
  }
  return $result;
}
