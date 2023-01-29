<?php
// This file declares a managed database record of type "OptionValue".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
return [
  0 => [
    'name' => 'Extended Report - Contributions',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Contributions',
      'description' => 'Extended Report - Contributions',
      'class_name' => 'CRM_Extendedreport_Form_Report_Contribute_Contributions',
      'report_url' => 'contribution/contributions',
      'component' => 'CiviContribute',
    ],
  ],
];
