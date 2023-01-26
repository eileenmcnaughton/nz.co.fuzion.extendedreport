<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
return [
  0 => [
    'name' => 'Extended Report - Activity Pivot Chart (CiviCase)',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Activity Pivot Chart (CiviCase)',
      'description' => 'Extended Report - Activity Pivot Chart',
      'class_name' => 'CRM_Extendedreport_Form_Report_Case_ActivityPivot',
      'report_url' => 'case/activity/pivot',
      'component' => 'CiviCase',
    ],
  ],
];
