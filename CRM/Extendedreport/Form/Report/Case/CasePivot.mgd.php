<?php
// This file declares a managed database record of type "OptionValue".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
return [
  0 => [
    'name' => 'Extended Report - Case Pivot Chart',
    'entity' => 'OptionValue',
    'params' => [
      'version' => 4,
      'match' => ['name', 'option_group_id'],
      'values' => [
        'label' => 'Extended Report - Case Pivot Chart',
        'option_group_id:name' => 'report_template',
        'description' => 'Extended Report - Case Pivot Chart',
        'name' => 'CRM_Extendedreport_Form_Report_Case_CasePivot',
        'value' => 'case/pivot',
        'component' => 'CiviCase',
      ],
    ],
  ],
];
