<?php
// This file declares a managed database record of type "OptionValue".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
return [
  0 => [
    'name' => 'Extended Report - Pledge SYBUNT Report',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Pledge SYBUNT',
      'description' => 'Extended Report - Pledge Some Year but not This Year',
      'class_name' => 'CRM_Extendedreport_Form_Report_Pledge_Sybunt',
      'report_url' => 'pledge/sybnt',
      'component' => 'CiviPledge',
    ],
  ],
];
