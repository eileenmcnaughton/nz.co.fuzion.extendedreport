<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array(
  0 => array(
    'name' => 'Extended Report - Flexible  ontact Report',
    'entity' => 'ReportTemplate',
    'params' => array(
      'version' => 3,
      'label' => 'Extended Report - Flexible contact report',
      'description' => 'Extended Report - Report has basic contact information with a few extensions such as multiple phones, tag lists, latest activity',
      'class_name' => 'CRM_Extendedreport_Form_Report_Contact_Basiccontact',
      'report_url' => 'contact/contactbasic',
      'component' => '',
    ),
  ),
);
