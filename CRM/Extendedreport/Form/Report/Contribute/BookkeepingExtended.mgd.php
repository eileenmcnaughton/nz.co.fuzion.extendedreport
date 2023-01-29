<?php
// This file declares a managed database record of type "OptionValue".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
return [
  0 => [
    'name' => 'Extended Report - Bookkeeping with extra fields',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Bookkeeping with extra fields',
      'description' => 'Extended Report - Bookkeeping with extra fields',
      'class_name' => 'CRM_Extendedreport_Form_Report_Contribute_BookkeepingExtended',
      'report_url' => 'contribution/bookkeeping_extended',
      'component' => 'CiviContribute',
    ],
  ],
];
