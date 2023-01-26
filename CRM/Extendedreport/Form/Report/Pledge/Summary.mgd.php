<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
return [
  0 => [
    'name' => 'Extended Report - Pledge Summary Report',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Pledge Summary',
      'description' => 'Extended Report - Pledge Summary',
      'class_name' => 'CRM_Extendedreport_Form_Report_Pledge_Summary',
      'report_url' => 'pledge/overview',
      'component' => 'CiviPledge',
    ],
  ],
];
