<?php
// This file declares a managed database record of type "OptionValue".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
return [
  0 => [
    'name' => 'Extended Report - Flexible  ontact Report',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Flexible contact report',
      'description' => 'Extended Report - Report has basic contact information with a few extensions such as multiple phones, tag lists, latest activity',
      'class_name' => 'CRM_Extendedreport_Form_Report_Contact_Basiccontact',
      'report_url' => 'contact/contactbasic',
      'component' => '',
    ],
  ],
];
