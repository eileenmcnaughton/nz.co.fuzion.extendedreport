<?php
if (!defined('ASSUME_GRANT_INSTALLED')) {
  define('ASSUME_GRANT_INSTALLED', FALSE);
}
if (!ASSUME_GRANT_INSTALLED) {
  $civiGrant = civicrm_api3('Extension', 'get', [
    'key' => 'civigrant',
    'status' => 'installed',
  ]);

  if (empty($civiGrant['values'])) {
    return [];
  }
}
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
return [
  0 => [
    'name' => 'Extended Report - Grant Detail',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Grant Detail',
      'description' => 'Extended Report - Grant Detail',
      'class_name' => 'CRM_Extendedreport_Form_Report_Grant_Detail',
      'report_url' => 'grant/detailextended',
      'component' => '',
    ],
  ],
];
