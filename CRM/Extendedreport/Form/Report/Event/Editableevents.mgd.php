<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 =>
  array (
    'name' => 'Editable Evnts',
    'entity' => 'ReportTemplate',
    'params' =>
    array (
      'version' => 3,
      'label' => 'Editable event Grid',
      'description' => 'Editable Event Report',
      'class_name' => 'CRM_Extendedreport_Form_Report_Event_Editableevents',
      'report_url' => 'event/form_event_editableevents',
      'component' => 'CiviEvent',
    ),
  ),
);