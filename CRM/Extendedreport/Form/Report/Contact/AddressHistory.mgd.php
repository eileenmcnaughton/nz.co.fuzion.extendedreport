<?php
// This file declares a managed database record of type "OptionValue".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
return [
  0 =>
    [
      'name' => 'Address History Report',
      'entity' => 'ReportTemplate',
      'is_require_logging' => TRUE,
      'params' =>
        [
          'version' => 3,
          'label' => 'Address History',
          'description' => 'ContactAddress History',
          'class_name' => 'CRM_Extendedreport_Form_Report_Contact_AddressHistory',
          'report_url' => 'contact/addresshistory',
          'component' => '',
        ],
    ],
];
