<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return [
  0 => [
    'name' => 'Extended Report - Pledge LYBUNS Report',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Pledge LYBUNS',
      'description' => 'Extended Report - Pledge Last Year but not Since',
      'class_name' => 'CRM_Extendedreport_Form_Report_Pledge_Lybuns',
      'report_url' => 'pledgelybns',
      'component' => 'CiviPledge',
    ],
  ],
];
