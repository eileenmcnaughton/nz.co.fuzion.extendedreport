<?php
// This file declares a managed database record of type "OptionValue".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
return [
  0 => [
    'name' => 'Extended Report - Pivot data membership report',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Pivot data membership report',
      'description' => 'Extended Report - Pivot data Membership Report',
      'class_name' => 'CRM_Extendedreport_Form_Report_Member_MembershipPivot',
      'report_url' => 'member/membershippivot',
      'component' => 'CiviMember',
    ],
  ],
];
