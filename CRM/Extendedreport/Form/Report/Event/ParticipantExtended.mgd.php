<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array(
  0 => array(
    'name' => 'Extended Report - Participants',
    'entity' => 'ReportTemplate',
    'params' => array(
      'version' => 3,
      'label' => 'Extended Report - Participants',
      'description' => 'Extended Report - Participants, includes multi-contact custom fields',
      'class_name' => 'CRM_Extendedreport_Form_Report_Event_ParticipantExtended',
      'report_url' => 'event/participantlistex',
      'component' => 'CiviEvent',
    ),
  ),
);
