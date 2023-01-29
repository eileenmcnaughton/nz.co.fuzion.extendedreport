<?php
// This file declares a managed database record of type "OptionValue".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
return [
  0 => [
    'name' => 'Extended Report - Contributions Detail with extra fields',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Contributions Detail with extra fields',
      'description' => 'Extended Report - Contributions= Detail with extra fields',
      'class_name' => 'CRM_Extendedreport_Form_Report_Contribute_DetailExtended',
      'report_url' => 'contribution/detailextended',
      'component' => 'CiviContribute',
    ],
  ],
];
