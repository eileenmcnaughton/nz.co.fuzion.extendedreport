<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return [
  0 => [
    'name' => 'Extended Report - Pledge Detail Report',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Pledge Detail',
      'description' => 'Extended Report - Pledge Detail',
      'class_name' => 'CRM_Extendedreport_Form_Report_Pledge_Detail',
      'report_url' => 'pledge/details',
      'component' => 'CiviPledge',
    ],
  ],
];
