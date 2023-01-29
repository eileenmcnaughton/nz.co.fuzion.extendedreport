<?php
// This file declares a managed database record of type "OptionValue".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
return [
  0 => [
    'name' => 'Extended Report - Paid and committed funds',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Paid and committed funds',
      'description' => 'All pledges and all payments',
      'class_name' => 'CRM_Extendedreport_Form_Report_Pledge_PaidAndCommitted',
      'report_url' => 'pledge/paidandcommitted',
      'component' => 'CiviPledge',
    ],
  ],
];
