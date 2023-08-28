<?php
// This file declares a managed database record of type "OptionValue".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
return [
  0 => [
    'name' => 'Extended Report - Activity Pivot Chart (CiviCase)',
    'entity' => 'OptionValue',
    'params' => [
      'match' => ['name', 'option_group_id'],
      'version' => 4,
      'values' => [
        'label' => 'Extended Report - Activity Pivot Chart (CiviCase)',
        'option_group_id:name' => 'report_template',
        'description' => 'Extended Report - Activity Pivot Chart',
        'name' => 'CRM_Extendedreport_Form_Report_Case_ActivityPivot',
        'value' => 'case/activity/pivot',
        'component' => 'CiviCase',
      ],
    ],
  ],
];
