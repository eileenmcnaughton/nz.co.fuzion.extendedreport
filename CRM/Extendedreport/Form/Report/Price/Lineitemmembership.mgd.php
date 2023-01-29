<?php
// This file declares a managed database record of type "OptionValue".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
return [
  0 => [
    'name' => 'Extended Report - Membership Price Set Report',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Membership Price Set Report',
      'description' => 'Extended Report - Memberships with Price set information',
      'class_name' => 'CRM_Extendedreport_Form_Report_Price_Lineitemmembership',
      'report_url' => 'price/lineitemmembership',
      'component' => 'CiviMember',
    ],
  ],
];
