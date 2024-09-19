<?php

/**
 * Class CRM_Extendedreport_Form_Report_Event_ParticipantExtended
 */
class CRM_Extendedreport_Form_Report_Event_ParticipantExtended extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_summary = NULL;

  protected $_baseTable = 'civicrm_participant';

  protected $_groupFilter = TRUE;

  protected $_tagFilter = TRUE;

  protected $joinFiltersTab = TRUE;

  protected $_customGroupExtends = [
    'Participant',
    'Contact',
    'Individual',
    'Event',
  ];

  public $_drilldownReport = ['event/income' => 'Link to Detail Report'];

  protected $_participantTable = 'civicrm_participant';

  /**
   * Class constructor.
   *
   * @throws \CRM_Core_Exception
   */
  public function __construct() {
    $this->_autoIncludeIndexedFieldsAsOrderBys = 1;
    $this->_customGroupGroupBy = 1;
    $campaignEnabled = $this->isCampaignEnabled();

    $this->_columns = $this->getColumns('Contact')
      + $this->getColumns('Email')
      + $this->getColumns('Address')
      + $this->getColumns('Participant')
      + $this->getColumns('Phone')
      + $this->getColumns('Event')
      + $this->getColumns('Contribution')
      + $this->getColumns('LineItem')
      + $this->getColumns('Note')
      + $this->getColumns('Relationship', [
        'fields' => FALSE,
        'filters' => FALSE,
        'join_filters' => TRUE,
        'group_by' => FALSE,
      ]) +
      $this->getColumns('Contact', [
        'fields' => TRUE,
        'join_fields' => TRUE,
        'prefix' => 'related_',
        'prefix_label' => 'Related Contact ',
      ]) +
      $this->getColumns('Email', [
        'prefix' => 'related_',
        'prefix_label' => 'Related Contact ',
      ]) +
      $this->getColumns('Phone', [
        'fields' => TRUE,
        'join_fields' => TRUE,
        'filters' => FALSE,
        'prefix' => 'related_',
        'prefix_label' => 'Related Contact ',
      ]);

    $this->_options = [
      'blank_column_begin' => [
        'title' => ts('Blank column at the Beginning'),
        'type' => 'checkbox',
      ],
      'blank_column_end' => [
        'title' => ts('Blank column at the End'),
        'type' => 'select',
        'options' => [
          '' => '-select-',
          1 => ts('One'),
          2 => ts('Two'),
          3 => ts('Three'),
        ],
      ],
    ];

    $this->_columns['civicrm_contact']['metadata']['age_at_event_start'] = [
      'title' => ts('Age at Event Start'),
      'is_fields' => TRUE,
      'is_order_bys' => TRUE,
      'is_filters' => TRUE,
      'is_group_bys' => TRUE,
      'is_join_filters' => FALSE,
      'is_aggregate_columns' => FALSE,
      'is_aggregate_rows' => FALSE,
      'type' => CRM_Utils_Type::T_INT,
      'table_key' => 'civicrm_contact',
      'alias' => 'civicrm_contact_age_at_event_start',
      'operatorType' => CRM_Report_Form::OP_INT,
      'dbAlias' => 'TIMESTAMPDIFF(YEAR, civicrm_contact.birth_date, event.start_date)',
    ];

    $this->_columns['civicrm_contact']['metadata']['age_at_event_end'] = [
      'title' => ts('Age at Event End'),
      'is_fields' => TRUE,
      'is_order_bys' => TRUE,
      'is_filters' => TRUE,
      'is_group_bys' => TRUE,
      'is_join_filters' => FALSE,
      'is_aggregate_columns' => FALSE,
      'is_aggregate_rows' => FALSE,
      'type' => CRM_Utils_Type::T_INT,
      'table_key' => 'civicrm_contact',
      'alias' => 'civicrm_contact_age_at_event_end',
      'operatorType' => CRM_Report_Form::OP_INT,
      'dbAlias' => 'TIMESTAMPDIFF(YEAR, civicrm_contact.birth_date, event.end_date)',
    ];

    // If we have active campaigns add those elements to both the fields and filters
    if ($campaignEnabled && !empty($this->activeCampaigns)) {
      $this->_columns['civicrm_participant']['fields']['campaign_id'] = [
        'title' => ts('Campaign'),
        'default' => 'false',
      ];
      $this->_columns['civicrm_participant']['filters']['campaign_id'] = [
        'title' => ts('Campaign'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => $this->activeCampaigns,
      ];
      $this->_columns['civicrm_participant']['order_bys']['campaign_id'] = [
        'title' => ts('Campaign'),
      ];
    }

    $this->_currencyColumn = 'civicrm_participant_fee_currency';
    parent::__construct();
  }

  /**
   * Overriding for the sake of handling relationship type ID.
   *
   * @throws \CRM_Core_Exception
   */
  public function postProcess(): void {
    $this->beginPostProcess();
    $originalRelationshipTypes = [];

    $relationships = [];
    if ($this->_params['relationship_relationship_type_id_value'] ?? FALSE) {
      $originalRelationshipTypes = $this->_params['relationship_relationship_type_id_value'];
      foreach ($this->_params['relationship_relationship_type_id_value'] as $relString) {
        $relType = explode('_', $relString);
        $relationships[] = (int) $relType[0];
      }
    }
    $this->_params['relationship_relationship_type_id_value'] = $relationships;
    $sql = $this->buildQuery();
    $this->addToDeveloperTab($sql);
    $rows = [];
    $this->buildRows($sql, $rows);
    $this->_params['relationship_type_id_value'] = $originalRelationshipTypes;
    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }

  /**
   * Declare from clauses used in the from clause for this report.
   *
   * @return array
   */
  public function fromClauses(): array {
    $fromClauses = [
      'event_from_participant',
      'contact_from_participant',
      'note_from_participant',
      'primary_phone_from_contact',
      'address_from_contact',
      'email_from_contact',
      'related_contact_from_participant',
    ];
    return $fromClauses;
  }

  /**
   * Generate report FROM clause.
   */
  public function from(): void {
    parent::from();
    if ($this->isTableSelected('civicrm_contribution')) {
      $this->_from .= "
             LEFT JOIN civicrm_participant_payment pp
                    ON ({$this->_aliases['civicrm_participant']}.id  = pp.participant_id)
             LEFT JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
                    ON (pp.contribution_id  = {$this->_aliases['civicrm_contribution']}.id)
      ";
    }
    if ($this->isTableSelected('civicrm_line_item')) {
      $this->_from .= "
            LEFT JOIN civicrm_line_item {$this->_aliases['civicrm_line_item']}
              ON {$this->_aliases['civicrm_line_item']}.entity_table = 'civicrm_participant'
              AND {$this->_aliases['civicrm_line_item']}.entity_id = {$this->_aliases['civicrm_participant']}.id
      ";
    }
  }

  /**
   * Alter row display.
   *
   * @param array $rows
   *
   * @throws \CRM_Core_Exception
   */
  public function alterDisplay(&$rows): void {
    // custom code to alter rows

    $entryFound = FALSE;
    $eventType = CRM_Core_OptionGroup::values('event_type');

    $financialTypes = CRM_Contribute_BAO_Contribution::buildOptions('financial_type_id', 'get');
    $contributionStatus = CRM_Contribute_PseudoConstant::contributionStatus();
    $paymentInstruments = CRM_Contribute_BAO_Contribution::buildOptions('payment_instrument_id', 'get');
    $honorTypes = CRM_Core_OptionGroup::values('honor_type', FALSE, FALSE, FALSE, NULL, 'label');
    $genders = CRM_Core_PseudoConstant::get('CRM_Contact_DAO_Contact', 'gender_id', ['localize' => TRUE]);

    foreach ($rows as $rowNum => $row) {
      // make count columns point to detail report
      // convert display name to links
      if (array_key_exists('civicrm_participant_event_id', $row)) {
        if ($value = $row['civicrm_participant_event_id']) {
          $rows[$rowNum]['civicrm_participant_event_id'] = CRM_Event_PseudoConstant::event($value, FALSE);

          $url = CRM_Report_Utils_Report::getNextUrl('event/income',
            'reset=1&force=1&id_op=in&id_value=' . $value,
            $this->_absoluteUrl, $this->_id, $this->_drilldownReport
          );
          $rows[$rowNum]['civicrm_participant_event_id_link'] = $url;
          $rows[$rowNum]['civicrm_participant_event_id_hover'] = ts("View Event Income Details for this Event");
        }
        $entryFound = TRUE;
      }

      // handle event type id
      if (array_key_exists('civicrm_event_event_type_id', $row)) {
        if ($value = $row['civicrm_event_event_type_id']) {
          $rows[$rowNum]['civicrm_event_event_type_id'] = $eventType[$value];
        }
        $entryFound = TRUE;
      }

      // handle participant status id
      if (array_key_exists('civicrm_participant_status_id', $row)) {
        if ($value = $row['civicrm_participant_status_id']) {
          $rows[$rowNum]['civicrm_participant_status_id'] = CRM_Event_PseudoConstant::participantStatus($value, FALSE, 'label');
        }
        $entryFound = TRUE;
      }

      // handle participant role id
      if (array_key_exists('civicrm_participant_role_id', $row)) {
        if ($value = $row['civicrm_participant_role_id']) {
          $roles = explode(CRM_Core_DAO::VALUE_SEPARATOR, $value);
          $value = [];
          foreach ($roles as $role) {
            $value[$role] = CRM_Event_PseudoConstant::participantRole($role, FALSE);
          }
          $rows[$rowNum]['civicrm_participant_role_id'] = implode(', ', $value);
        }
        $entryFound = TRUE;
      }

      // handle registered_by_id -> replace ID with the Name of the Contact instead
      if (array_key_exists('civicrm_participant_registered_by_id', $row)) {
        if ($value = $row['civicrm_participant_registered_by_id']) {
          // find the contact ID of this participant ID

          $our_participant_id = $row['civicrm_participant_registered_by_id'];

          $result = civicrm_api3('Participant', 'get', [
            'sequential' => 1,
            'return' => "contact_id",
            'id' => $our_participant_id,
          ]);
          $our_contact_id = $result['values']['0']['contact_id'];

          $result = civicrm_api3('Contact', 'get', [
            'sequential' => 1,
            'return' => "sort_name",
            'id' => $our_contact_id,
          ]);
          $our_sort_name = $result['values']['0']['sort_name'];

          $rows[$rowNum]['civicrm_participant_registered_by_id'] = $our_sort_name;

          $viewUrl = CRM_Utils_System::url("civicrm/contact/view/participant",
            "reset=1&id=$our_participant_id&cid=$our_contact_id&action=view&context=participant"
          );
          $Title = ts('View Participant Details');
          $rows[$rowNum]['civicrm_participant_registered_by_id'] = "<a title='$Title' href=$viewUrl>$our_sort_name</a>";

        }
        $entryFound = TRUE;
      }

      // Handle value separator in Fee Level
      if (array_key_exists('civicrm_participant_participant_fee_level', $row)) {
        if ($value = $row['civicrm_participant_participant_fee_level']) {
          CRM_Event_BAO_Participant::fixEventLevel($value);
          $rows[$rowNum]['civicrm_participant_participant_fee_level'] = $value;
        }
        $entryFound = TRUE;
      }

      // Convert display name to link
      if (($displayName = CRM_Utils_Array::value('civicrm_contact_sort_name', $row)) &&
        ($cid = CRM_Utils_Array::value('civicrm_contact_id', $row)) &&
        ($id = CRM_Utils_Array::value('civicrm_participant_participant_record', $row))
      ) {
        $viewUrl = CRM_Utils_System::url("civicrm/contact/view/participant",
          "reset=1&id=$id&cid=$cid&action=view&context=participant"
        );

        $participantTitle = ts('View Participant Record');

        $rows[$rowNum]['civicrm_contact_sort_name'] = "<a title='$participantTitle' href=$viewUrl>$displayName</a>";

        $entryFound = TRUE;
      }

      // handle participant registered by
      if (array_key_exists('civicrm_participant_participant_registered_by_id', $row)) {
        if ($value = $row['civicrm_participant_participant_registered_by_id']) {
          $details = CRM_Event_BAO_Participant::participantDetails($value);

          $viewUrl = CRM_Utils_System::url("civicrm/contact/view/participant",
            "reset=1&id=" . $row['civicrm_participant_participant_registered_by_id'] .
            "&cid=" . $details['cid'] . "&action=view&context=participant"
          );

          $participantTitle = ts('View Participant Record');

          $rows[$rowNum]['civicrm_participant_participant_registered_by_id'] =
            "<a title='$participantTitle' href=$viewUrl>" . $details['name'] . "</a>";
          //$rows[$rowNum]['civicrm_participant_participant_registered_by_id'] = $details['name'];
        }
        $entryFound = TRUE;
      }

      // Handle country id
      if (array_key_exists('civicrm_address_country_id', $row)) {
        if ($value = $row['civicrm_address_country_id']) {
          $rows[$rowNum]['civicrm_address_country_id'] = CRM_Core_PseudoConstant::country($value, TRUE);
        }
        $entryFound = TRUE;
      }

      // Handle state/province id
      if (array_key_exists('civicrm_address_state_province_id', $row)) {
        if ($value = $row['civicrm_address_state_province_id']) {
          $rows[$rowNum]['civicrm_address_state_province_id'] = CRM_Core_PseudoConstant::stateProvince($value, TRUE);
        }
        $entryFound = TRUE;
      }

      // Handle employer id
      if (array_key_exists('civicrm_contact_employer_id', $row)) {
        if ($value = $row['civicrm_contact_employer_id']) {
          $rows[$rowNum]['civicrm_contact_employer_id'] = CRM_Contact_BAO_Contact::displayName($value);
          $url = CRM_Utils_System::url('civicrm/contact/view',
            'reset=1&cid=' . $value, $this->_absoluteUrl
          );
          $rows[$rowNum]['civicrm_contact_employer_id_link'] = $url;
          $rows[$rowNum]['civicrm_contact_employer_id_hover'] = ts('View Contact Summary for this Contact.');
        }
      }

      // Convert campaign_id to campaign title
      if (array_key_exists('civicrm_participant_campaign_id', $row)) {
        if ($value = $row['civicrm_participant_campaign_id']) {
          $rows[$rowNum]['civicrm_participant_campaign_id'] = $this->activeCampaigns[$value];
          $entryFound = TRUE;
        }
      }

      // handle contribution status
      if (array_key_exists('civicrm_contribution_contribution_status_id', $row)) {
        if ($value = $row['civicrm_contribution_contribution_status_id']) {
          $rows[$rowNum]['civicrm_contribution_contribution_status_id'] = $contributionStatus[$value];
        }
        $entryFound = TRUE;
      }

      // handle payment instrument
      if (array_key_exists('civicrm_contribution_payment_instrument_id', $row)) {
        if ($value = $row['civicrm_contribution_payment_instrument_id']) {
          $rows[$rowNum]['civicrm_contribution_payment_instrument_id'] = $paymentInstruments[$value];
        }
        $entryFound = TRUE;
      }

      // handle financial type
      if (array_key_exists('civicrm_contribution_financial_type_id', $row)) {
        if ($value = $row['civicrm_contribution_financial_type_id']) {
          $rows[$rowNum]['civicrm_contribution_financial_type_id'] = $financialTypes[$value];
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_contribution_honor_type_id', $row)) {
        if ($value = $row['civicrm_contribution_honor_type_id']) {
          $rows[$rowNum]['civicrm_contribution_honor_type_id'] = $honorTypes[$value];
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_contact_gender_id', $row)) {
        if ($value = $row['civicrm_contact_gender_id']) {
          $rows[$rowNum]['civicrm_contact_gender_id'] = $genders[$value];
        }
        $entryFound = TRUE;
      }

      // display birthday in the configured custom format
      if (array_key_exists('civicrm_contact_birth_date', $row)) {
        if ($value = $row['civicrm_contact_birth_date']) {
          $rows[$rowNum]['civicrm_contact_birth_date'] = CRM_Utils_Date::customFormat($row['civicrm_contact_birth_date'], '%Y%m%d');
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_phone_phone', $row)) {
        $rows[$rowNum]['civicrm_phone_phone'] = $this->alterPhoneGroup($row['civicrm_phone_phone']);
      }

      // skip looking further in rows, if first row itself doesn't
      // have the column we need
      if (!$entryFound) {
        break;
      }
    }
    parent::alterDisplay($rows);
  }

}
