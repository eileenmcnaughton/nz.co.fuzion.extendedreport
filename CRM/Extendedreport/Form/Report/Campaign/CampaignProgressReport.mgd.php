<?php
// This file declares a managed database record of type "OptionValue".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_manage
if (CRM_Core_Component::isEnabled('CiviCampaign')) {
  return [
    [
      'name' => 'Extended Report - Campaign progress',
      'entity' => 'OptionValue',
      'params' => [
        'version' => 4,
        'match' => ['name'],
        'values' => [
          'label' => 'Extended Report - Campaign progress',
          'option_group_id:name' => 'report_template',
          'description' => 'Extended Report - Campaign progress',
          'name' => 'CRM_Extendedreport_Form_Report_Campaign_CampaignProgressReport',
          'value' => 'campaign/progress',
          'component' => 'CiviCampaign',
        ],
      ],
    ],
  ];
}

return [];
