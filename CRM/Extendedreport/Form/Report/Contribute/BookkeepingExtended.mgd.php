<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array(
  0 => array(
    'name' => 'Extended Report - Bookkeeping with extra fields',
    'entity' => 'ReportTemplate',
    'params' => array(
      'version' => 3,
      'label' => 'Extended Report - Bookkeeping with extra fields',
      'description' => 'Extended Report - Bookkeeping with extra fields',
      'class_name' => 'CRM_Extendedreport_Form_Report_Contribute_BookkeepingExtended',
      'report_url' => 'contribution/bookkeeping_extended',
      'component' => 'CiviContribute',
    ),
  ),
);
