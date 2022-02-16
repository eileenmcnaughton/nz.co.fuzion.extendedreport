<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
return [
  0 => [
    'name' => 'Extended Report - Activities',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Activities',
      'description' => 'Extended Report - Activities, includes multi-contact custom fields',
      'class_name' => 'CRM_Extendedreport_Form_Report_ActivityExtended',
      'report_url' => 'activityextended',
      'component' => '',
    ],
  ],
];
