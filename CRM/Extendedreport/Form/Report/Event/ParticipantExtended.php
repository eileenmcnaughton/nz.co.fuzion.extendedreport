<?php

/**
 * Class CRM_Extendedreport_Form_Report_Event_ParticipantExtended
 */
class CRM_Extendedreport_Form_Report_Event_ParticipantExtended extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_summary = NULL;
  protected $_baseTable = 'civicrm_participant';
  protected $skipACL = FALSE;
  protected $_contribField = FALSE;
  protected $_lineitemField = FALSE;
  protected $_groupFilter = TRUE;
  protected $_tagFilter = TRUE;
  protected $_relationship_tab = TRUE;

  protected $_customGroupExtends = array(
    'Participant',
    'Contact',
    'Individual',
    'Event',
  );

  public $_drilldownReport = array('event/income' => 'Link to Detail Report');
  protected $_participantTable = 'civicrm_participant';

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_autoIncludeIndexedFieldsAsOrderBys = 1;
    $this->_customGroupGroupBy = 1;

    // Check if CiviCampaign is a) enabled and b) has active campaigns
    $config = CRM_Core_Config::singleton();
    $campaignEnabled = in_array("CiviCampaign", $config->enableComponents);
    if ($campaignEnabled) {
      $getCampaigns = CRM_Campaign_BAO_Campaign::getPermissionedCampaigns(NULL, NULL, TRUE, FALSE, TRUE);
      $this->activeCampaigns = $getCampaigns['campaigns'];
      asort($this->activeCampaigns);
    }

    $this->_columns = array(
      'civicrm_contact' => array_merge($this->getColumns('Contact'), array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => array(
          'sort_name' => array(
            'title' => ts('Participant Name'),
            'default' => TRUE,
            'no_repeat' => TRUE,
            'dbAlias' => 'contact_civireport.sort_name',
          ),
          'first_name' => array(
            'title' => ts('First Name'),
          ),
          'last_name' => array(
            'title' => ts('Last Name'),
          ),
          'id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'gender_id' => array(
            'title' => ts('Gender'),
          ),
          'birth_date' => array(
            'title' => ts('Birth Date'),
          ),
          'age' => array(
            'title' => ts('Age'),
            'dbAlias' => 'TIMESTAMPDIFF(YEAR, contact_civireport.birth_date, CURDATE())',
          ),
          'age_at_event' => array(
            'title' => ts('Age at Event'),
            'dbAlias' => 'TIMESTAMPDIFF(YEAR, contact_civireport.birth_date, event_civireport.start_date)',
          ),
          'employer_id' => array(
            'title' => ts('Organization'),
          ),
        ),
        'grouping' => 'contact-fields',
        'order_bys' => array(
          'sort_name' => array(
            'title' => ts('Last Name, First Name'),
            'default' => '1',
            'default_weight' => '0',
            'default_order' => 'ASC',
          ),
          'gender_id' => array(
            'name' => 'gender_id',
            'title' => ts('Gender'),
          ),
          'birth_date' => array(
            'name' => 'birth_date',
            'title' => ts('Birth Date'),
          ),
          'age' => array(
            'name' => 'age',
            'title' => ts('Age'),
          ),
          'age_at_event' => array(
            'name' => 'age_at_event',
            'title' => ts('Age at Event'),
          ),
        ),
        'filters' => array(
          'sort_name' => array(
            'title' => ts('Participant Name'),
            'operator' => 'like',
          ),
          'gender_id' => array(
            'title' => ts('Gender'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Core_PseudoConstant::get('CRM_Contact_DAO_Contact', 'gender_id'),
          ),
          'birth_date' => array(
            'title' => ts('Birth Date'),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
          ),
        ),
      )),
      'civicrm_email' => array(
        'dao' => 'CRM_Core_DAO_Email',
        'fields' => array(
          'email' => array(
            'title' => ts('Email'),
            'no_repeat' => TRUE,
          ),
        ),
        'grouping' => 'contact-fields',
        'filters' => array(
          'email' => array(
            'title' => ts('Participant E-mail'),
            'operator' => 'like',
          ),
        ),
      ),
      'civicrm_address' => array(
        'dao' => 'CRM_Core_DAO_Address',
        'fields' => array(
          'street_address' => NULL,
          'city' => NULL,
          'postal_code' => NULL,
          'state_province_id' => array(
            'title' => ts('State/Province'),
          ),
          'country_id' => array(
            'title' => ts('Country'),
          ),
        ),
        'grouping' => 'contact-fields',
      ),
      'civicrm_participant' => array(
        'dao' => 'CRM_Event_DAO_Participant',
        'fields' => array(
          'participant_id' => array(
            'title' => 'Participant ID',
            'default' => TRUE,
          ),
          'participant_record' => array(
            'name' => 'id',
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'event_id' => array(
            'default' => TRUE,
            'type' => CRM_Utils_Type::T_STRING,
          ),
          'status_id' => array(
            'title' => ts('Status'),
            'default' => TRUE,
          ),
          'role_id' => array(
            'title' => ts('Role'),
            'default' => TRUE,
          ),
          'registered_by_id' => array(
            'title' => ts('Registered by'),
            'default' => TRUE,
          ),
          'source' => array(
            'title' => ts('Source'),
            'default' => TRUE,
          ),
          'fee_currency' => array(
            'required' => TRUE,
            'no_display' => TRUE,
          ),
          'participant_fee_level' => NULL,
          'participant_fee_amount' => NULL,
          'participant_register_date' => array('title' => ts('Registration Date')),
        ),
        'grouping' => 'event-fields',
        'filters' => array(
          'event_id' => array(
            'name' => 'event_id',
            'title' => ts('Event'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $this->getEventFilterOptions(),
          ),
          'source' => array(
            'name' => 'source',
            'title' => ts('Source'),
            'operator' => 'like',
          ),
          'sid' => array(
            'name' => 'status_id',
            'title' => ts('Participant Status'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Event_PseudoConstant::participantStatus(NULL, NULL, 'label'),
          ),
          'rid' => array(
            'name' => 'role_id',
            'title' => ts('Participant Role'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Event_PseudoConstant::participantRole(),
          ),
          'participant_register_date' => array(
            'title' => 'Registration Date',
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
          'participant_registered_by_id' => array(
            'title' => ts('Registered By ID'),
            'operatorType' => CRM_Report_Form::OP_INT,
            'default' => "",
            'type' => CRM_Utils_Type::T_INT,
          ),
          'fee_currency' => array(
            'title' => ts('Fee Currency'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Core_OptionGroup::values('currencies_enabled'),
            'default' => NULL,
            'type' => CRM_Utils_Type::T_STRING,
          ),

        ),
        'order_bys' => array(
          'event_id' => array(
            'title' => ts('Event'),
            'default_weight' => '1',
            'default_order' => 'ASC',
          ),
          'registered_by_id' => array(
            'title' => ts('Registered by ID/Name'),
            'default_weight' => '1',
            'default_order' => 'ASC',
          ),
          'source' => array(
            'title' => ts('Source'),
            'default_weight' => '1',
            'default_order' => 'ASC',
          ),
        ),
      ),
    ) +
    $this->getColumns('Phone') + array(
      'civicrm_event' => array(
        'dao' => 'CRM_Event_DAO_Event',
        'fields' => array(
          'event_type_id' => array(
            'title' => ts('Event Type'),
            'type' => CRM_Utils_Type::T_INT,
          ),
          'event_start_date' => array('title' => ts('Event Start Date')),
        ),
        'grouping' => 'event-fields',
        'filters' => array(
          'eid' => array(
            'name' => 'event_type_id',
            'title' => ts('Event Type'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Core_OptionGroup::values('event_type'),
            'type' => CRM_Utils_Type::T_INT,
          ),
          'event_start_date' => array(
            'title' => ts('Event Start Date'),
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
        ),
        'order_bys' => array(
          'event_type_id' => array(
            'title' => ts('Event Type'),
            'default_weight' => '2',
            'default_order' => 'ASC',
            'type' => CRM_Utils_Type::T_INT,
          ),
        ),
      ),
      'civicrm_contribution' => array(
        'dao' => 'CRM_Contribute_DAO_Contribution',
        'fields' => array(
          'contribution_id' => array(
            'name' => 'id',
            'no_display' => TRUE,
            'required' => TRUE,
            'csv_display' => TRUE,
            'title' => ts('Contribution ID'),
          ),
          'financial_type_id' => array(
            'title' => ts('Financial Type'),
            'type' => CRM_Utils_Type::T_INT,
          ),
          'receive_date' => array('title' => ts('Payment Date')),
          'contribution_status_id' => array('title' => ts('Contribution Status')),
          'payment_instrument_id' => array('title' => ts('Payment Type')),
          'contribution_source' => array(
            'name' => 'source',
            'title' => ts('Contribution Source'),
          ),
          'currency' => array(
            'required' => TRUE,
            'no_display' => TRUE,
          ),
          'trxn_id' => NULL,
          'honor_type_id' => array('title' => ts('Honor Type')),
          'fee_amount' => array('title' => ts('Transaction Fee')),
          'net_amount' => NULL,
        ),
        'grouping' => 'contrib-fields',
        'filters' => array(
          'receive_date' => array(
            'title' => 'Payment Date',
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
          'financial_type_id' => array(
            'title' => ts('Financial Type'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Contribute_PseudoConstant::financialType(),
            'type' => CRM_Utils_Type::T_INT,
          ),
          'currency' => array(
            'title' => ts('Contribution Currency'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Core_OptionGroup::values('currencies_enabled'),
            'default' => NULL,
            'type' => CRM_Utils_Type::T_STRING,
          ),
          'payment_instrument_id' => array(
            'title' => ts('Payment Type'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Contribute_PseudoConstant::paymentInstrument(),
            'type' => CRM_Utils_Type::T_INT,
          ),
          'contribution_status_id' => array(
            'title' => ts('Contribution Status'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Contribute_PseudoConstant::contributionStatus(),
            'default' => NULL,
            'type' => CRM_Utils_Type::T_INT,
          ),
        ),
      ),
      'civicrm_line_item' => array(
        'dao' => 'CRM_Price_DAO_LineItem',
        'grouping' => 'priceset-fields',
        'filters' => array(
          'price_field_value_id' => array(
            'name' => 'price_field_value_id',
            'title' => ts('Fee Level'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $this->getPriceLevels(),
            'type' => CRM_Utils_Type::T_INT,
          ),
        ),
      ),
    ) +
    $this->getColumns('Note') + array(
      'civicrm_note' => array(
        'dao' => 'CRM_Core_DAO_Note',
        'fields' => array(
          'note' => array('title' => ts('Note')),
        ),
      ),
    )
    + $this->getColumns('Relationship', array(
        'fields' => FALSE,
        'filters' => FALSE,
        'join_filters' => TRUE,
        'group_by' => FALSE,
    )) +
    $this->getColumns('Contact', array(
        'fields' => TRUE,
        'join_fields' => TRUE,
        'filters' => FALSE,
        'prefix' => 'related_',
        'prefix_label' => 'Related Contact ',
    )) +
    $this->getColumns('Email', array(
      'prefix' => 'related_',
      'prefix_label' => 'Related Contact ',
    )) +
    $this->getColumns('Phone', array(
      'fields' => TRUE,
      'join_fields' => TRUE,
      'filters' => FALSE,
      'prefix' => 'related_',
      'prefix_label' => 'Related Contact ',
    ));

    $this->_options = array(
      'blank_column_begin' => array(
        'title' => ts('Blank column at the Beginning'),
        'type' => 'checkbox',
      ),
      'blank_column_end' => array(
        'title' => ts('Blank column at the End'),
        'type' => 'select',
        'options' => array(
          '' => '-select-',
          1 => ts('One'),
          2 => ts('Two'),
          3 => ts('Three'),
        ),
      ),
    );

    // If we have active campaigns add those elements to both the fields and filters
    if ($campaignEnabled && !empty($this->activeCampaigns)) {
      $this->_columns['civicrm_participant']['fields']['campaign_id'] = array(
        'title' => ts('Campaign'),
        'default' => 'false',
      );
      $this->_columns['civicrm_participant']['filters']['campaign_id'] = array(
        'title' => ts('Campaign'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => $this->activeCampaigns,
      );
      $this->_columns['civicrm_participant']['order_bys']['campaign_id'] = array(
        'title' => ts('Campaign'),
      );
    }

    $this->_currencyColumn = 'civicrm_participant_fee_currency';
    parent::__construct();
  }

  /**
   * Get price levels.
   *
   * @return array
   */
  protected function getPriceLevels() {
    $query = "
SELECT     DISTINCT cv.label, cv.id
FROM      civicrm_price_field_value cv
LEFT JOIN civicrm_price_field cf ON cv.price_field_id = cf.id
LEFT JOIN civicrm_price_set_entity ce ON ce.price_set_id = cf.price_set_id
WHERE     ce.entity_table = 'civicrm_event'
GROUP BY  cv.label
";
    $dao = CRM_Core_DAO::executeQuery($query);
    $elements = array();
    while ($dao->fetch()) {
      $elements[$dao->id] = "$dao->label\n";
    }

    return $elements;
  }

  /**
   * Declare from clauses used in the from clause for this report.
   *
   * @return array
   */
  public function fromClauses() {
    return array(
      'event_from_participant',
      'contact_from_participant',
      'note_from_participant',
      'phone_from_contact',
      'address_from_contact',
      'email_from_contact',
      'related_contact_from_participant',
    );
  }

  /**
   * Generate report FROM clause.
   */
  public function from() {
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
            LEFT JOIN civicrm_line_item line_item_civireport
              ON line_item_civireport.entity_table = 'civicrm_participant'
              AND line_item_civireport.entity_id = {$this->_aliases['civicrm_participant']}.id
      ";
    }
  }

  /**
   * Generate report where clause.
   */
  public function where() {
    $clauses = array();
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('filters', $table)) {
        foreach ($table['filters'] as $fieldName => $field) {
          $clause = NULL;

          if (CRM_Utils_Array::value('type', $field) & CRM_Utils_Type::T_DATE) {
            $relative = CRM_Utils_Array::value("{$fieldName}_relative", $this->_params);
            $from = CRM_Utils_Array::value("{$fieldName}_from", $this->_params);
            $to = CRM_Utils_Array::value("{$fieldName}_to", $this->_params);

            if ($relative || $from || $to) {
              $clause = $this->dateClause($field['dbAlias'], $relative, $from, $to, $field['type']);
            }
          }
          else {
            $op = CRM_Utils_Array::value("{$fieldName}_op", $this->_params);

            if ($fieldName == 'rid') {
              $value = CRM_Utils_Array::value("{$fieldName}_value", $this->_params);
              if (!empty($value)) {
                $clause = "( {$field['dbAlias']} REGEXP '[[:<:]]" . implode('[[:>:]]|[[:<:]]', $value) . "[[:>:]]' )";
              }
              $op = NULL;
            }

            if ($op) {
              $clause = $this->whereClause($field,
                $op,
                CRM_Utils_Array::value("{$fieldName}_value", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_min", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_max", $this->_params)
              );
            }
          }

          if (!empty($clause)) {
            $clauses[] = $clause;
          }
        }
      }
    }
    if (empty($clauses)) {
      $this->_where = "WHERE {$this->_aliases['civicrm_participant']}.is_test = 0 ";
    }
    else {
      $this->_where = "WHERE {$this->_aliases['civicrm_participant']}.is_test = 0 AND " . implode(' AND ', $clauses);
    }
    if ($this->_aclWhere) {
      $this->_where .= " AND {$this->_aclWhere} ";
    }
  }

  /**
   * Alter row display.
   *
   * @param array $rows
   */
  public function alterDisplay(&$rows) {
    // custom code to alter rows

    $entryFound = FALSE;
    $eventType = CRM_Core_OptionGroup::values('event_type');

    $financialTypes = CRM_Contribute_PseudoConstant::financialType();
    $contributionStatus = CRM_Contribute_PseudoConstant::contributionStatus();
    $paymentInstruments = CRM_Contribute_PseudoConstant::paymentInstrument();
    $honorTypes = CRM_Core_OptionGroup::values('honor_type', FALSE, FALSE, FALSE, NULL, 'label');
    $genders = CRM_Core_PseudoConstant::get('CRM_Contact_DAO_Contact', 'gender_id', array('localize' => TRUE));

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
          $value = array();
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

          $result = civicrm_api3('Participant', 'get', array(
            'sequential' => 1,
            'return' => "contact_id",
            'id' => $our_participant_id,
          ));
          $our_contact_id = $result['values']['0']['contact_id'];

          $result = civicrm_api3('Contact', 'get', array(
            'sequential' => 1,
            'return' => "sort_name",
            'id' => $our_contact_id,
          ));
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
