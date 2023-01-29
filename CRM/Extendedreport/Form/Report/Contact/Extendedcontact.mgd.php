<?php
/**
 * // This file declares a managed database record of type "OptionValue".
 * The record will be automatically inserted, updated, or deleted from the
 * database as appropriate. For more details, see "hook_civicrm_managed" at:
 * http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
 */
return [
  0 => [
    'name' => 'Extended Report - Pivot data contact report',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Pivot data contact report',
      'description' => 'Extended Report - Pivot data Contact Report',
      'class_name' => 'CRM_Extendedreport_Form_Report_Contact_Extendedcontact',
      'report_url' => 'contact/contactextended',
      'component' => '',
    ],
  ],
];
