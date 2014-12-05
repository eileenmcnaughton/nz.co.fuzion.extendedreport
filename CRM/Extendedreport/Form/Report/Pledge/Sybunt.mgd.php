<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array(
  0 => array(
    'name' => 'Extended Report - Pledge SYBUNT Report',
    'entity' => 'ReportTemplate',
    'params' => array(
      'version' => 3,
      'label' => 'Extended Report - Pledge SYBUNT',
      'description' => 'Extended Report - Pledge Some Year but not This Year',
      'class_name' => 'CRM_Extendedreport_Form_Report_Pledge_Sybunt',
      'report_url' => 'pledge/sybnt',
      'component' => 'CiviPledge',
    ),
  ),
);
