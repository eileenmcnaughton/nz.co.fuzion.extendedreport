<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array(
  0 => array(
    'name' => 'Extended Report - Participant Line Item Report',
    'entity' => 'ReportTemplate',
    'params' => array(
      'version' => 3,
      'label' => 'Extended Report - Participant Line Items',
      'description' => 'Extended Report - Line Item report for participants',
      'class_name' => 'CRM_Extendedreport_Form_Report_Price_Lineitemparticipant',
      'report_url' => 'price/lineitemparticipant',
      'component' => 'CiviEvent',
    ),
  ),
);
