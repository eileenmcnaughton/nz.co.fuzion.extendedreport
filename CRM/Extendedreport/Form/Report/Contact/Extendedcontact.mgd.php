<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 =>
  array (
    'name' => 'CRM_Extendedreport_Form_Report_Contact_Extendedcontact',
    'entity' => 'ReportTemplate',
    'params' =>
    array (
      'version' => 3,
      'label' => 'Contact_Extendedcontact',
      'description' => 'Flexible Contact Report',
      'class_name' => 'CRM_Extendedreport_Form_Report_Contact_Extendedcontact',
      'report_url' => 'contact/contactextended',
      'component' => 'Contact',
    ),
  ),
);