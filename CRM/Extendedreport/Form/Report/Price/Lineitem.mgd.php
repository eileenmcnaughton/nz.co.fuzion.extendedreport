<?php
// This file declares a managed database record of type "OptionValue".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
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
