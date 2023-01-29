<?php
// This file declares a managed database record of type "OptionValue".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
return [
  0 => [
    'name' => 'Extended Report - Contribution Pivot Chart',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Contribution Pivot Chart',
      'description' => 'Extended Report - Contribution Pivot Chart',
      'class_name' => 'CRM_Extendedreport_Form_Report_Contribute_ContributionPivot',
      'report_url' => 'contribution/pivot',
      'component' => 'CiviContribute',
    ],
  ],
];
