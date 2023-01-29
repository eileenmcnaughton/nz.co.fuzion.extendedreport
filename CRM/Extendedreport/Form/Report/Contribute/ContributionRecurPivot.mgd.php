<?php
// This file declares a managed database record of type "OptionValue".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
return [
  0 => [
    'name' => 'Extended Report - Recurring Contribution Pivot Chart',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Recurring Contribution Pivot Chart',
      'description' => 'Extended Report - Contribution Recur Pivot Chart',
      'class_name' => 'CRM_Extendedreport_Form_Report_Contribute_ContributionRecurPivot',
      'report_url' => 'contribution/recur-pivot',
      'component' => 'CiviContribute',
    ],
  ],
];
