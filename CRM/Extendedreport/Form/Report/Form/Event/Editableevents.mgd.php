<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 =>
  array (
    'name' => 'CRM_Extendedreport_Form_Report_Form_Event_Editableevents',
    'entity' => 'ReportTemplate',
    'params' =>
    array (
      'version' => 3,
      'label' => 'Form_Event_Editableevents',
      'description' => 'Editable Event Report',
      'class_name' => 'CRM_Extendedreport_Form_Report_Form_Event_Editableevents',
      'report_url' => 'event/form_event_editableevents',
      'component' => 'CiviEvent',
    ),
  ),
);