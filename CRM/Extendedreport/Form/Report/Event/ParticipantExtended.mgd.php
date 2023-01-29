<?php
// This file declares a managed database record of type "OptionValue".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
return [
  0 => [
    'name' => 'Extended Report - Participants',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Extended Report - Participants',
      'description' => 'Extended Report - Participants, includes multi-contact custom fields',
      'class_name' => 'CRM_Extendedreport_Form_Report_Event_ParticipantExtended',
      'report_url' => 'event/participantlistex',
      'component' => 'CiviEvent',
    ],
  ],
];
