<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return [
  0 => [
    'name' => 'Extended Report - Event Overview',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Event Overview',
      'description' => 'Extended Report - Statistics across events',
      'class_name' => 'CRM_Extendedreport_Form_Report_Event_EventOverview',
      'report_url' => 'event/overview',
      'component' => 'CiviEvent',
    ],
  ],
];
