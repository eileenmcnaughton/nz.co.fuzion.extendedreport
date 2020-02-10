<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return [
  0 => [
    'name' => 'Extended Report - Pledge TBNS Report',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Pledge TBNS',
      'description' => 'Extended Report - Pledge in this range but not Since',
      'class_name' => 'CRM_Extendedreport_Form_Report_Pledge_Btbns',
      'report_url' => 'pledge/tbns',
      'component' => 'CiviPledge',
    ],
  ],
];
