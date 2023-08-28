<?php
// This file declares a managed database record of type "OptionValue".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
return [
  [
    'name' => 'Extended Report - Relationships',
    'entity' => 'OptionValue',
    'params' => [
      'version' => 4,
      'match' => ['name', 'option_group_id'],
      'values' => [
        'label' => 'Extended Report - Relationships',
        'option_group_id:name' => 'report_template',
        'description' => 'Extended Report - Relationships, includes data from both sides of the relationship',
        'name' => 'CRM_Extendedreport_Form_Report_RelationshipExtended',
        'value' => 'relationshipextended',
        'component' => '',
      ],
    ],
  ],
];
