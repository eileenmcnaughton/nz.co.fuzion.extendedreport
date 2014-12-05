<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array(
  0 => array(
    'name' => 'Extended Report - Pivot data membership report',
    'entity' => 'ReportTemplate',
    'params' => array(
      'version' => 3,
      'label' => 'Extended Report - Pivot data membership report',
      'description' => 'Extended Report - Pivot data Membership Report',
      'class_name' => 'CRM_Extendedreport_Form_Report_Member_MembershipPivot',
      'report_url' => 'member/membershippivot',
      'component' => 'CiviMember',
    ),
  ),
);
