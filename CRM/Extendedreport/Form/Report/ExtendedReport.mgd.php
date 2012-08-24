<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 => 
  array (
    'name' => 'CRM_Extendedreport_Form_Report_ExtendedReport',
    'entity' => 'ReportTemplate',
    'params' => 
    array (
      'version' => 3,
      'label' => 'ExtendedReport',
      'description' => 'ExtendedReport (nz.co.fuzion.extendedreport)',
      'class_name' => 'CRM_Extendedreport_Form_Report_ExtendedReport',
      'report_url' => 'nz.co.fuzion.extendedreport/extendedreport',
      'component' => 'CiviContribute',
    ),
  ),
);