<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return [
  0 => [
    'name' => 'Extended Report - Contributions with Price Set data',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Contributions with Price Set data',
      'description' => 'Extended Report - Line item Report (based on contributions)',
      'class_name' => 'CRM_Extendedreport_Form_Report_Price_Contributionbased',
      'report_url' => 'price/contributionbased',
      'component' => 'CiviContribute',
    ],
  ],
];
