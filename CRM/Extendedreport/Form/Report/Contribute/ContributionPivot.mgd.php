<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
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
