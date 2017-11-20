<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 =>
    array (
      'name' => 'Address History Report',
      'entity' => 'ReportTemplate',
      'is_require_logging' => TRUE,
      'params' =>
        array (
          'version' => 3,
          'label' => 'Address History',
          'description' => 'ContactAddress History',
          'class_name' => 'CRM_Extendedreport_Form_Report_Contact_AddressHistory',
          'report_url' => 'contact/addresshistory',
          'component' => '',
        ),
    ),
);
