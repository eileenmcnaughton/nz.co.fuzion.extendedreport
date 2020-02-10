<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return [
  0 => [
    'name' => 'Extended Report - Price Set Line item Report',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Price Set Line Items',
      'description' => 'Extended Report - Line Item report for price sets',
      'class_name' => 'CRM_Extendedreport_Form_Report_Price_Lineitem',
      'report_url' => 'price/lineitem',
      'component' => 'CiviContribute',
    ],
  ],
];
