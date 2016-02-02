<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array(
  0 => array(
    'name' => 'Extended Report - Paid and committed funds',
    'entity' => 'ReportTemplate',
    'params' => array(
      'version' => 3,
      'label' => 'Paid and committed funds',
      'description' => 'All pledges and all payments',
      'class_name' => 'CRM_Extendedreport_Form_Report_Pledge_PaidAndCommitted',
      'report_url' => 'pledge/paidandcommitted',
      'component' => 'CiviPledge',
    ),
  ),
);
