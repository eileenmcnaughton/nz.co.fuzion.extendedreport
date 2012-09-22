<?php

class CRM_Extendedreport_Form_Report_ExtendedReport extends CRM_Report_Form {
  protected $_addressField = FALSE;

  protected $_emailField = FALSE;

  protected $_summary = NULL;
  protected $_exposeContactID = FALSE;
  protected $_customGroupExtends = array();
  protected $_baseTable = 'civicrm_contact';
  protected $_editableFields = TRUE;
  protected $_groupByArray = array();
  /*
   * Change time filters to time date filters by setting this to 1
   */
  protected $_timeDateFilters = 0;
  /*
   * Use $temporary to choose whether to generate permanent or temporary tables
   * ie. for debugging it's good to set to ''
   */
  protected $_temporary = '  ';

  function __construct() {

    parent::__construct();
  }

  function preProcess() {
    parent::preProcess();
  }

  function select() {
    parent::select();
  }
  /*
* From clause build where baseTable & fromClauses are defined
*/
  function from() {
    if (!empty($this->_baseTable)) {
      if(!empty($this->_aliases['civicrm_contact'])){
        $this->buildACLClause($this->_aliases['civicrm_contact']);
      }
      $this->_from = "FROM {$this->_baseTable} {$this->_aliases[$this->_baseTable]}";
      $availableClauses = $this->getAvailableJoins();
      foreach ($this->fromClauses() as $fromClause) {
        $fn = $availableClauses[$fromClause]['callback'];
        $this->$fn();
      }
      if (strstr($this->_from, 'civicrm_contact')) {
        $this->_from .= $this->_aclFrom;
      }
    }
  }
  /*
* Define any from clauses in use (child classes to override)
*/
  function fromClauses() {
    return array();
  }
/*
 * We're overriding the parent class so we can populate a 'group_by' array for other functions use
 * e.g. editable fields are turned off when groupby is used
 */
  function groupBy() {
    $this->storeGroupByArray();
    if (!empty($this->_groupByArray)) {
      $this->_groupBy = "GROUP BY " . implode(', ', $this->_groupByArray);
    }
  }

  function orderBy() {
    parent::orderBy();
  }
/*
 * Store group bys into array - so we can check elsewhere (e.g editable fields) what is grouped
 */
  function storeGroupByArray(){
    if (CRM_Utils_Array::value('group_bys', $this->_params) &&
        is_array($this->_params['group_bys']) &&
        !empty($this->_params['group_bys'])
    ) {
      foreach ($this->_columns as $tableName => $table) {
        if (array_key_exists('group_bys', $table)) {
          foreach ($table['group_bys'] as $fieldName => $field) {
            if (CRM_Utils_Array::value($fieldName, $this->_params['group_bys'])) {
              $this->_groupByArray[] = $field['dbAlias'];
            }
          }
        }
      }
    }
    // if a stat field has been selected the do a group by - this is not in parent
    if (!empty($this->_statFields) && empty($this->_groupByArray)) {
      $this->_groupByArray[] = $this->_aliases[$this->_baseTable] . ".id";
    }
  }

  function statistics(&$rows) {
    return parent::statistics($rows);
  }

  function postProcess() {
    if (!empty($this->_aclTable) && CRM_Utils_Array::value($this->_aclTable, $this->_aliases)) {
      $this->buildACLClause($this->_aliases[$this->_aclTable]);
    }
    parent::postProcess();
  }
  /*
   * We are overriding this so that we can add time if required
   */
  function addDateRange( $name, $from = '_from', $to = '_to', $label = 'From:', $dateFormat = 'searchDate', $required = FALSE) {
    if($this->_timeDateFilters){
      $this->addDateTime( $name . '_from', $label   , $required, array( 'formatType' => $dateFormat ) );
      $this->addDateTime( $name . '_to'  , ts('To:'), $required, array( 'formatType' => $dateFormat ) );
    }
    else{
      parent::addDateRange($name, $from, $to, $label, $dateFormat, $required);
    }
  }
  function alterDisplay(&$rows) {
    parent::alterDisplay($rows);

    //THis is all generic functionality which can hopefully go into the parent class
    // it introduces the option of defining an alter display function as part of the column definition
    // @tod tidy up the iteration so it happens in this function

    if(!empty($this->_rollup ) && !empty($this->_groupBysArray)){
      $this->assignSubTotalLines($rows);
    }

    list($firstRow) = $rows;
    // no result to alter
    if (empty($firstRow)) {
      return;
    }
    $selectedFields = array_keys($firstRow);
    $alterfunctions = $altermap = array();
    foreach ($this->_columns as $tablename => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $field => $specs) {
          if (in_array($tablename . '_' . $field, $selectedFields)){
            if( array_key_exists('alter_display', $specs)) {
              $alterfunctions[$tablename . '_' . $field] = $specs['alter_display'];
              $altermap[$tablename . '_' . $field] = $field;
              $alterspecs[$tablename . '_' . $field] = null;
            }
            if( $this->_editableFields && array_key_exists('crm_editable', $specs)) {
              //id key array is what the array would look like if the ONLY group by field is our id field
              // in which case it should be editable - in any other group by scenario it shouldn't be
              $idKeyArray = array($this->_aliases[$specs['crm_editable']['id_table']] . "." . $specs['crm_editable']['id_field']);
              if(empty($this->_groupByArray) || $this->_groupByArray == $idKeyArray){
                $alterfunctions[$tablename . '_' . $field] = 'alterCrmEditable';
                $altermap[$tablename . '_' . $field] = $field;
                $alterspecs[$tablename . '_' . $field] = $specs['crm_editable'];
              }
            }
          }
        }
      }
    }
    if (empty($alterfunctions)) {
      // - no manipulation to be done
      return;
    }

    foreach ($rows as $index => & $row) {
      foreach ($row as $selectedfield => $value) {
        if (array_key_exists($selectedfield, $alterfunctions)) {
          $rows[$index][$selectedfield] = $this->$alterfunctions[$selectedfield]($value, $row, $selectedfield, $altermap[$selectedfield], $alterspecs[$selectedfield]);
        }
      }
    }
  }
  /*
   * Was hoping to avoid over-riding this - but it doesn't pass enough data to formatCustomValues by default
   */
  function alterCustomDataDisplay(&$rows) {
    // custom code to alter rows having custom values
    if (empty($this->_customGroupExtends)) {
      return;
    }

    $customFieldIds = array();
    foreach ($this->_params['fields'] as $fieldAlias => $value) {
      if ($fieldId = CRM_Core_BAO_CustomField::getKeyID($fieldAlias)) {
        $customFieldIds[$fieldAlias] = $fieldId;
      }
    }
    if (empty($customFieldIds)) {
      return;
    }

    $customFields = $fieldValueMap = array();
    $customFieldCols = array('column_name', 'data_type', 'html_type', 'option_group_id', 'id');

    // skip for type date and ContactReference since date format is already handled
    $query = "
SELECT cg.table_name, cf." . implode(", cf.", $customFieldCols) . ", ov.value, ov.label
FROM  civicrm_custom_field cf
INNER JOIN civicrm_custom_group cg ON cg.id = cf.custom_group_id
LEFT JOIN civicrm_option_value ov ON cf.option_group_id = ov.option_group_id
WHERE cg.extends IN ('" . implode("','", $this->_customGroupExtends) . "') AND
      cg.is_active = 1 AND
      cf.is_active = 1 AND
      cf.is_searchable = 1 AND
      cf.data_type   NOT IN ('ContactReference', 'Date') AND
      cf.id IN (" . implode(",", $customFieldIds) . ")";

    $dao = CRM_Core_DAO::executeQuery($query);
    while ($dao->fetch()) {
      foreach ($customFieldCols as $key) {
        $customFields[$dao->table_name . '_custom_' . $dao->id][$key] = $dao->$key;
      }
      if ($dao->option_group_id) {
        $fieldValueMap[$dao->option_group_id][$dao->value] = $dao->label;
      }
    }
    $dao->free();

    $entryFound = FALSE;
    foreach ($rows as $rowNum => $row) {
      foreach ($row as $tableCol => $val) {
        if (array_key_exists($tableCol, $customFields)) {
          $rows[$rowNum][$tableCol] = $this->formatCustomValues($val, $customFields[$tableCol], $fieldValueMap, $row);
          $entryFound = TRUE;
        }
      }

      // skip looking further in rows, if first row itself doesn't
      // have the column we need
      if (!$entryFound) {
        break;
      }
    }
  }
  /*
   * We are overriding this function to apply crm-editable where appropriate
   * It would be more efficient if we knew the entity being extended (which the parent function
   * does know) but we want to avoid extending any functions we don't have to
   */
  function formatCustomValues($value, $customField, $fieldValueMap, $row) {
    if(!empty($this->_customGroupExtends) && count($this->_customGroupExtends) ==1){
      //lets only extend apply editability where only one entity extended
      // we can easily extend to contact combos
      list($entity) =  $this->_customGroupExtends ;
      $entity_table = strtolower('civicrm_' . $entity);
      $idKeyArray = array($this->_aliases[$entity_table] . '.id');
      if(empty($this->_groupByArray) || $this->_groupByArray == $idKeyArray){
        $entity_field = $entity_table . '_id';
        $entityID = $row[$entity_field];
      }
    }
    if (CRM_Utils_System::isNull($value)) {
      return;
    }

    $htmlType = $customField['html_type'];

    switch ($customField['data_type']) {
      case 'Boolean':
        if ($value == '1') {
          $retValue = ts('Yes');
        }
        else {
          $retValue = ts('No');
        }
        break;

      case 'Link':
        $retValue = CRM_Utils_System::formatWikiURL($value);
        break;

      case 'File':
        $retValue = $value;
        break;

      case 'Memo':
        $retValue = $value;
        break;

      case 'Float':
        if ($htmlType == 'Text') {
          $retValue = (float)$value;
          break;
        }
      case 'Money':
        if ($htmlType == 'Text') {
          $retValue = CRM_Utils_Money::format($value, NULL, '%a');
          break;
        }
      case 'String':
      case 'Int':
        if (in_array($htmlType, array(
        'Text', 'TextArea'))) {
        $retValue = $value;
        if(!empty($entity_field)){
          $retValue = "<div id={$entity}-{$entityID} class='crm-entity'>
          <span class='crm-editable crmf-{$customField} crm-editable-enabled' data-action='create'>" . $value . "</span></div>";
        }
        break;
        }
      case 'StateProvince':
      case 'Country':

        switch ($htmlType) {
          case 'Multi-Select Country':
            $value = explode(CRM_Core_DAO::VALUE_SEPARATOR, $value);
            $customData = array();
            foreach ($value as $val) {
              if ($val) {
                $customData[] = CRM_Core_PseudoConstant::country($val, FALSE);
              }
            }
            $retValue = implode(', ', $customData);
            break;

          case 'Select Country':
            $retValue = CRM_Core_PseudoConstant::country($value, FALSE);
            break;

          case 'Select State/Province':
            $retValue = CRM_Core_PseudoConstant::stateProvince($value, FALSE);
            break;

          case 'Multi-Select State/Province':
            $value = explode(CRM_Core_DAO::VALUE_SEPARATOR, $value);
            $customData = array();
            foreach ($value as $val) {
              if ($val) {
                $customData[] = CRM_Core_PseudoConstant::stateProvince($val, FALSE);
              }
            }
            $retValue = implode(', ', $customData);
            break;

          case 'Select':
          case 'Radio':
          case 'Autocomplete-Select':
            $retValue = $fieldValueMap[$customField['option_group_id']][$value];
            break;

          case 'CheckBox':
          case 'AdvMulti-Select':
          case 'Multi-Select':
            $value = explode(CRM_Core_DAO::VALUE_SEPARATOR, $value);
            $customData = array();
            foreach ($value as $val) {
              if ($val) {
                $customData[] = $fieldValueMap[$customField['option_group_id']][$val];
              }
            }
            $retValue = implode(', ', $customData);
            break;

          default:
            $retValue = $value;
        }
        break;

      default:
        $retValue = $value;
    }

    return $retValue;
  }

  function assignSubTotalLines(&$rows){
     foreach ($rows as $index => & $row) {
       $orderFields = array_intersect_key(array_flip($this->_groupBysArray), $row);
     }
  }

  function getLineItemColumns() {
    return array(
      'civicrm_line_item' =>
      array(
        'dao' => 'CRM_Price_BAO_LineItem',
        'fields' =>
        array(
          'qty' =>
          array('title' => ts('Quantity'),
            'type' => CRM_Utils_Type::T_INT,
            'statistics' =>
            array('sum' => ts('Total Quantity Selected')),
          ),
          'unit_price' =>
          array('title' => ts('Unit Price'),
          ),
          'line_total' =>
          array('title' => ts('Line Total'),
            'type' => CRM_Utils_Type::T_MONEY,
            'statistics' =>
            array('sum' => ts('Total of Line Items')),
          ),
        ),
        'participant_count' =>
        array('title' => ts('Participant Count'),
          'statistics' =>
          array('sum' => ts('Total Participants')),
        ),
        'filters' =>
        array(
          'qty' =>
          array('title' => ts('Quantity'),
            'type' => CRM_Utils_Type::T_INT,
            'operator' => CRM_Report_Form::OP_INT,
          ),
        ),
        'group_bys' =>
        array(
          'price_field_id' =>
          array('title' => ts('Price Field'),
          ),
          'price_field_value_id' =>
          array('title' => ts('Price Field Option'),
          ),
          'line_item_id' =>
          array('title' => ts('Individual Line Item'),
            'name' => 'id',
          ),
        ),
      ),
    );
  }

  function getPriceFieldValueColumns() {
    return array(
      'civicrm_price_field_value' =>
      array(
        'dao' => 'CRM_Price_BAO_FieldValue',
        'fields' => array(
          'price_field_value_label' =>
          array('title' => ts('Price Field Value Label'),
            'name' => 'label',
          ),
        ),
        'filters' =>
        array(
          'price_field_value_label' =>
          array('title' => ts('Price Fields Value Label'),
            'type' => CRM_Utils_Type::T_STRING,
            'operator' => 'like',
            'name' => 'label',
          ),
        ),
        'order_bys' =>
        array(
          'label' =>
          array('title' => ts('Price Field Value Label'),
          ),
        ),
        'group_bys' =>
        //note that we have a requirement to group by label such that all 'Promo book' lines
        // are grouped together across price sets but there may be a separate need to group
        // by id so that entries in one price set are distinct from others. Not quite sure what
        // to call the distinction for end users benefit
        array(
          'price_field_value_label' =>
          array('title' => ts('Price Field Value Label'),
            'name' => 'label',
          ),
        ),
      ),
    );
  }

  function getPriceFieldColumns() {
    return array(
      'civicrm_price_field' =>
      array(
        'dao' => 'CRM_Price_BAO_Field',
        'fields' =>
        array(
          'price_field_label' =>
          array('title' => ts('Price Field Label'),
            'name' => 'label',
          ),
        ),
        'filters' =>
        array(
          'price_field_label' =>
          array('title' => ts('Price Field Label'),
            'type' => CRM_Utils_Type::T_STRING,
            'operator' => 'like',
            'name' => 'label',
          ),
        ),
        'order_bys' =>
        array(
          'price_field_label' =>
          array('title' => ts('Price Field Label'),
                'name' => 'label',
          ),
        ),
        'group_bys' =>
        array(
          'price_field_label' =>
          array('title' => ts('Price Field Label'),
            'name' => 'label',
          ),
        ),
      ),
    );
  }

  function getParticipantColumns() {
    static $_events = array();
    if (!isset($_events['all'])) {
      CRM_Core_PseudoConstant::populate($_events['all'], 'CRM_Event_DAO_Event', FALSE, 'title', 'is_active', "is_template IS NULL OR is_template = 0", 'end_date DESC');
    }
    return array(
      'civicrm_participant' =>
      array(
        'dao' => 'CRM_Event_DAO_Participant',
        'fields' =>
        array('participant_id' => array('title' => 'Participant ID'),
          'participant_record' => array(
            'name' => 'id',
            'title' => 'Participant ID',
          ),
          'event_id' => array('title' => ts('Event ID'),
            'type' => CRM_Utils_Type::T_STRING,
            'alter_display' => 'alterEventID',
          ),
          'status_id' => array('title' => ts('Event Participant Status'),
            'alter_display' => 'alterParticipantStatus',
          ),
          'role_id' => array('title' => ts('Role'),
            'alter_display' => 'alterParticipantRole',
          ),
          'participant_fee_level' => NULL,
          'participant_fee_amount' => NULL,
          'participant_register_date' => array('title' => ts('Registration Date')),
        ),
        'grouping' => 'event-fields',
        'filters' =>
        array(
          'event_id' => array('name' => 'event_id',
            'title' => ts('Event'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $_events['all'],
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
            'title' => ' Registration Date',
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
        ),
        'order_bys' =>
        array(
          'event_id' =>
          array('title' => ts('Event'), 'default_weight' => '1', 'default_order' => 'ASC'),
        ),
        'group_bys' =>
        array(
          'event_id' =>
          array('title' => ts('Event')),
        ),
      ),
    );
  }

  function getMembershipColumns() {
    return array(
      'civicrm_membership' => array(
        'dao' => 'CRM_Member_DAO_Membership',
        'grouping' => 'member-fields',
        'fields' => array(

          'membership_type_id' => array(
            'title' => 'Membership Type',
            'alter_display' => 'alterMembershipTypeID',

          ),
          'status_id' => array(
            'title' => 'Membership Status',
            'alter_display' => 'alterMembershipStatusID',
          ),
          'join_date' => NULL,
          'start_date' => array(
            'title' => ts('Current Cycle Start Date'),
          ),
          'end_date' => array(
            'title' => ts('Current Membership Cycle End Date'),
          ),

           'id' => array(
                'title' => 'Membership ID / Count',
                'name' => 'id',
                'statistics' =>
                array('count' => ts('Number of Memberships')),
            ),
        ),
        'group_bys' => array(
          'membership_type_id' => array(
            'title' => ts('Membership Type'),
          ),
          'status_id' => array(
                'title' => ts('Membership Status'),
            ),
           'end_date' => array(
               'title' => 'Current Membership Cycle End Date',
              'frequency' => TRUE,
               'type' => CRM_Utils_Type::T_DATE,
            )
        ),
        'filters' => array(
          'join_date' => array(
            'type' => CRM_Utils_Type::T_DATE,
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
            'membership_end_date' => array(
                'name' => 'end_date',
                'title' => 'Membership Expiry',
                'type' => CRM_Utils_Type::T_DATE,
                'operatorType' => CRM_Report_Form::OP_DATE,
            ),
            'membership_status_id' => array(
                'name' => 'status_id',
                'title' => 'Membership Status',
                'type' => CRM_Utils_Type::T_INT,
                'options' => CRM_Member_PseudoConstant::membershipStatus(),
                'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            ),
        ),
      ),
    );
  }

  function getMembershipTypeColumns() {
    require_once 'CRM/Member/PseudoConstant.php';
    return array(
      'civicrm_membership_type' => array(
        'dao' => 'CRM_Member_DAO_MembershipType',
        'grouping' => 'member-fields',
        'filters' => array(
          'gid' => array(
            'name' => 'id',
            'title' => ts('Membership Types'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'type' => CRM_Utils_Type::T_INT + CRM_Utils_Type::T_ENUM,
            'options' => CRM_Member_PseudoConstant::membershipType(),
          ),
        ),
      ),
    );
  }

  function getEventColumns() {
    return array(
      'civicrm_event' => array(
        'dao' => 'CRM_Event_DAO_Event',
        'fields' =>
        array(
          'id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'title' => array('title' => ts('Event Title'),
            'required' => TRUE,
            'crm_editable' => array(
                'id_table' => 'civicrm_event',
                'id_field' => 'id',
                'entity' => 'event',
             ),
          ),
          'event_type_id' => array('title' => ts('Event Type'),
            'alter_display' => 'alterEventType',
          ),
          'fee_label' => array('title' => ts('Fee Label')),
          'event_start_date' => array('title' => ts('Event Start Date'),
          ),
          'event_end_date' => array('title' => ts('Event End Date')),
          'max_participants' => array('title' => ts('Capacity'),
            'type' => CRM_Utils_Type::T_INT,
            'crm_editable' => array(
              'id_table' => 'civicrm_event',
              'id_field' => 'id',
              'entity' => 'event'
            ),
          ),
          'is_active' => array(
             'title' => ts('Is Active'),
             'type' => CRM_Utils_Type::T_INT,
             'crm_editable' => array(
               'id_table' => 'civicrm_event',
               'id_field' => 'id',
               'entity' => 'event',
               'options' => array('0' => 'No', '1' => 'Yes'),
              ),
            ),
           'is_public' => array(
                'title' => ts('Is Publicly Visible'),
                'type' => CRM_Utils_Type::T_INT,
                'crm_editable' => array(
                    'id_table' => 'civicrm_event',
                    'id_field' => 'id',
                    'entity' => 'event'
                ),
            ),
        ),
        'grouping' => 'event-fields',
        'filters' => array(
          'event_type_id' => array(
            'name' => 'event_type_id',
            'title' => ts('Event Type'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Core_OptionGroup::values('event_type'),
          ),
          'event_title' => array(
            'name' => 'title',
            'title' => ts('Event Title'),
            'operatorType' => CRM_Report_Form::OP_STRING,
          ),
          'event_start_date' => array(
            'title' => ts('Event start date'),
            'default_weight' => '1',
            'default_order' => 'ASC',
          )
        ),
        'order_bys' => array(
          'event_type_id' => array(
            'title' => ts('Event Type'),
            'default_weight' => '2',
            'default_order' => 'ASC',
          ),
          ),
        'group_bys' => array(
          'event_type_id' => array(
          'title' => ts('Event Type'),
          ),
        ),
      )
    );
  }

  function getContributionColumns() {
    return array(
      'civicrm_contribution' =>
      array(
        'dao' => 'CRM_Contribute_DAO_Contribution',
        'fields' =>
        array(
          'contribution_id' => array(
            'title' => ts('Contribution ID'),
            'name' => 'id',
          ),
          'contribution_type_id' => array('title' => ts('Contribution Type'),
            'default' => TRUE,
            'alter_display' => 'alterContributionType',
          ),
          'payment_instrument_id' => array('title' => ts('Payment Instrument'),
            'alter_display' => 'alterPaymentType',
          ),
          'source' => array('title' => 'Contribution Source'),
          'trxn_id' => NULL,
          'receive_date' => array('default' => TRUE),
          'receipt_date' => NULL,
          'fee_amount' => NULL,
          'net_amount' => NULL,
          'total_amount' => array('title' => ts('Amount'),
            'statistics' =>
            array('sum' => ts('Total Amount')),
            'type' => CRM_Utils_Type::T_MONEY,
          ),
        ),
        'filters' =>
        array(
          'receive_date' =>
          array('operatorType' => CRM_Report_Form::OP_DATE),
          'contribution_type_id' =>
          array('title' => ts('Contribution Type'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Contribute_PseudoConstant::contributionType(),
          ),
          'payment_instrument_id' =>
          array('title' => ts('Payment Type'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Contribute_PseudoConstant::paymentInstrument(),
          ),
          'contribution_status_id' =>
          array('title' => ts('Contribution Status'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Contribute_PseudoConstant::contributionStatus(),
          ),
          'total_amount' =>
          array('title' => ts('Contribution Amount')),
        ),
        'order_bys' =>
        array(
          'payment_instrument_id' =>
          array('title' => ts('Payment Instrument'),
          ),
         'contribution_type_id' =>
          array('title' => ts('Contribution Type'),
          ),
        ),
        'group_bys' =>
        array(
          'contribution_type_id' =>
          array('title' => ts('Contribution Type')),
          'payment_instrument_id' =>
          array('title' => ts('Payment Instrument')),
          'contribution_id' =>
          array('title' => ts('Individual Contribution'),
            'name' => 'id',
          ),
          'source' => array('title' => 'Contribution Source'),
        ),
        'grouping' => 'contribution-fields',
      ),
    );
  }

  function getContactColumns() {
    return array(
      'civicrm_contact' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => array(
          'display_name' => array(
            'title' => ts('Contact Name'),
          ),
          'id' => array(
            'title' => ts('Contact ID'),
            'alter_display' => 'alterContactID',
          ),
          'first_name' => array(
            'title' => ts('First Name'),
          ),
          'middle_name' => array(
            'title' => ts('Middle Name'),
          ),
          'last_name' => array(
            'title' => ts('Last Name'),
          ),
          'nick_name' => array(
            'title' => ts('Nick Name'),
            'alter_display' => 'alterNickName',
          ),
        ),
        'filters' => array(
          'id' => array(
            'title' => ts('Contact ID'),
          )
          ,
          'sort_name' => array(
            'title' => ts('Contact Name'),
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
        ),
      ),
    );
  }

  function getCaseColumns() {
    return array(
      'civicrm_case' => array(
        'dao' => 'CRM_Case_DAO_Case',
        'fields' => array(
          'id' => array(
            'title' => ts('Case ID'),
            'required' => false
          ),
          'subject' => array(
            'title' => ts('Case Subject'),
            'default' => true
          ),
          'status_id' => array(
            'title' => ts('Status'),
            'default' => true
          ),
          'case_type_id' => array(
            'title' => ts('Case Type'),
            'default' => true
          ),
          'case_start_date' => array(
            'title' => ts('Case Start Date'),
            'name' => 'start_date',
            'default' => true
          ),
          'case_end_date' => array(
            'title' => ts('Case End Date'),
            'name' => 'end_date',
            'default' => true
          ),
          'case_duration' => array(
            'name' => 'duration',
            'title' => ts('Duration (Days)'),
            'default' => false
          ),
          'case_is_deleted' => array(
            'name' => 'is_deleted',
            'title' => ts('Case Deleted?'),
            'default' => false,
            'type' => CRM_Utils_Type::T_INT
          )
        ),
        'filters' => array(
          'case_start_date' => array(
            'title' => ts('Case Start Date'),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
            'name' => 'start_date',
          ),
          'case_end_date' => array(
            'title' => ts('Case End Date'),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
            'name' => 'end_date'
          ),
          'case_type_id' => array(
            'title' => ts('Case Type'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $this->case_types
          ),
          'case_status_id' => array(
            'title' => ts('Case Status'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $this->case_statuses,
            'name' => 'status_id'
          ),
          'case_is_deleted' => array(
            'title' => ts('Case Deleted?'),
            'type' => CRM_Report_Form::OP_INT,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'options' => $this->deleted_labels,
            'default' => 0,
            'name' => 'is_deleted'
          )
        )
      )
    );
  }

  /*
* function for adding address fields to construct function in reports
* @param array $options Options for the report
* - prefix prefix to add (e.g. 'honor' when getting address details for honor contact
* - prefix_label optional prefix lable eg. "Honoree " for front end
* - group_by enable these fields for group by - default false
* - order_by enable these fields for order by
* - filters enable these fields for filtering
* - defaults - (is this working?) values to pre-populate
* @return array address fields for construct clause
*/
  function getAddressColumns($options = array()) {
    $defaultOptions = array(
      'prefix' => '',
      'prefix_label' => '',
      'group_by' => false,
      'order_by' => true,
      'filters' => true,
      'defaults' => array(
        'country_id' => TRUE
      ),
     );

    $options = array_merge($defaultOptions,$options);

    $addressFields = array(
      $options['prefix'] . 'civicrm_address' => array(
        'dao' => 'CRM_Core_DAO_Address',
        'name' => 'civicrm_address',
        'alias' => $options['prefix'] . 'civicrm_address',
        'fields' => array(
          $options['prefix'] . 'name' => array(
            'title' => ts($options['prefix_label'] . 'Address Name'),
            'default' => CRM_Utils_Array::value('name', $options['defaults'], FALSE),
            'name' => 'name',
          ),
          $options['prefix'] . 'street_address' => array(
            'title' => ts($options['prefix_label'] . 'Street Address'),
            'default' => CRM_Utils_Array::value('street_address', $options['defaults'], FALSE),
            'name' => 'street_address',
          ),
          $options['prefix'] . 'supplemental_address_1' => array(
            'title' => ts($options['prefix_label'] . 'Supplementary Address Field 1'),
            'default' => CRM_Utils_Array::value('supplemental_address_1', $options['defaults'], FALSE),
            'name' => 'supplemental_address_1',
          ),
          $options['prefix'] . 'supplemental_address_2' => array(
            'title' => ts($options['prefix_label'] . 'Supplementary Address Field 2'),
            'default' => CRM_Utils_Array::value('supplemental_address_2', $options['defaults'], FALSE),
            'name' => 'supplemental_address_2',
          ),
          $options['prefix'] . 'street_number' => array(
            'name' => 'street_number',
            'title' => ts($options['prefix_label'] . 'Street Number'),
            'type' => 1,
            'default' => CRM_Utils_Array::value('street_number', $options['defaults'], FALSE),
            'name' => 'street_number',
          ),
          $options['prefix'] . 'street_name' => array(
            'name' => 'street_name',
            'title' => ts($options['prefix_label'] . 'Street Name'),
            'type' => 1,
            'default' => CRM_Utils_Array::value('street_name', $options['defaults'], FALSE),
            'name' => 'street_name',
          ),
          $options['prefix'] . 'street_unit' => array(
            'name' => 'street_unit',
            'title' => ts($options['prefix_label'] . 'Street Unit'),
            'type' => 1,
            'default' => CRM_Utils_Array::value('street_unit', $options['defaults'], FALSE),
            'name' => 'street_unit',
          ),
          $options['prefix'] . 'city' => array(
            'title' => ts($options['prefix_label'] . 'City'),
            'default' => CRM_Utils_Array::value('city', $options['defaults'], FALSE),
            'name' => 'city',
          ),
          $options['prefix'] . 'postal_code' => array(
            'title' => ts($options['prefix_label'] . 'Postal Code'),
            'default' => CRM_Utils_Array::value('postal_code', $options['defaults'], FALSE),
            'name' => 'postal_code',
          ),
          $options['prefix'] . 'county_id' => array(
            'title' => ts($options['prefix_label'] . 'County'),
            'default' => CRM_Utils_Array::value('county_id', $options['defaults'], FALSE),
            'alter_display' => 'alterCountyID',
            'name' => 'county_id',
          ),
          $options['prefix'] . 'state_province_id' => array(
            'title' => ts($options['prefix_label'] . 'State/Province'),
            'default' => CRM_Utils_Array::value('state_province_id', $options['defaults'], FALSE),
            'alter_display' => 'alterStateProvinceID',
            'name' => 'state_province_id',
          ),
          $options['prefix'] . 'country_id' => array(
            'title' => ts($options['prefix_label'] . 'Country'),
            'default' => CRM_Utils_Array::value('country_id', $options['defaults'], FALSE),
            'alter_display' => 'alterCountryID',
            'name' => 'country_id',
          ),
        ),
        'grouping' => 'location-fields',
      ),
    );

    if ($options['filters']) {
      $addressFields[$options['prefix'] .'civicrm_address']['filters'] = array(
        $options['prefix'] . 'street_number' => array(
          'title' => ts($options['prefix_label'] . 'Street Number'),
          'type' => 1,
          'name' => 'street_number',
        ),
        $options['prefix'] . 'street_name' => array(
          'title' => ts($options['prefix_label'] . 'Street Name'),
          'name' => $options['prefix'] . 'street_name',
          'operator' => 'like',
        ),
        $options['prefix'] . 'postal_code' => array(
          'title' => ts($options['prefix_label'] . 'Postal Code'),
          'type' => 1,
          'name' => 'postal_code',
        ),
        $options['prefix'] . 'city' => array(
          'title' => ts($options['prefix_label'] . 'City'),
          'operator' => 'like',
          'name' => 'city',
        ),
        $options['prefix'] . 'county_id' => array(
          'name' => 'county_id',
          'title' => ts($options['prefix_label'] . 'County'),
          'type' => CRM_Utils_Type::T_INT,
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'options' => CRM_Core_PseudoConstant::county(),
        ),
        $options['prefix'] . 'state_province_id' => array(
          'name' => 'state_province_id',
          'title' => ts($options['prefix_label'] . 'State/Province'),
          'type' => CRM_Utils_Type::T_INT,
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'options' => CRM_Core_PseudoConstant::stateProvince(),
        ),
        $options['prefix'] . 'country_id' => array(
          'name' => 'country_id',
          'title' => ts($options['prefix_label'] . 'Country'),
          'type' => CRM_Utils_Type::T_INT,
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'options' => CRM_Core_PseudoConstant::country(),
        ),
      );
    }

    if ($options['order_by']) {
      $addressFields[$options['prefix'] . 'civicrm_address']['order_bys'] = array(
        $options['prefix'] . 'street_name' => array(
          'title' => ts($options['prefix_label'] . 'Street Name'),
          'name' => 'street_name',
        ),
        $options['prefix'] . 'street_number' => array(
          'title' => ts($options['prefix_label'] . 'Odd / Even Street Number'),
          'name' => 'street_number',
        ),
        $options['prefix'] . 'street_address' => array(
          'title' => ts($options['prefix_label'] . 'Street Address'),
          'name' => 'street_address',
        ),
        $options['prefix'] . 'city' => array(
          'title' => ts($options['prefix_label'] . 'City'),
          'name' => 'city',
        ),
        $options['prefix'] . 'postal_code' => array(
          'title' => ts($options['prefix_label'] . 'Post Code'),
          'name' => 'postal_code',
        ),
      );
    }

    if ($options['group_by']) {
      $addressFields['civicrm_address']['group_bys'] = array(
        $options['prefix'] . 'street_address' => array(
          'title' => ts($options['prefix_label'] . 'Street Address'),
          'name' => 'street_address',
        ),
        $options['prefix'] . 'city' => array(
          'title' => ts($options['prefix_label'] . 'City'),
          'name' => 'city',
        ),
        $options['prefix'] . 'postal_code' => array(
          'title' => ts($options['prefix_label'] . 'Post Code'),
          'name' => 'postal_code',
        ),
        $options['prefix'] . 'state_province_id' => array(
          'title' => ts($options['prefix_label'] . 'State/Province'),
          'name' => 'state_province_id',
        ),
        $options['prefix'] . 'country_id' => array(
          'title' => ts($options['prefix_label'] . 'Country'),
          'name' => 'country_id',
        ),
        $options['prefix'] . 'county_id' => array(
          'title' => ts($options['prefix_label'] . 'County'),
          'name' => 'county_id',
        ),
      );
    }
    return $addressFields;
  }

  /*
* Get Information about advertised Joins
*/
  function getAvailableJoins() {
    return array(
      'priceFieldValue_from_lineItem' => array(
        'leftTable' => 'civicrm_line_item',
        'rightTable' => 'civicrm_price_field_value',
        'callback' => 'joinPriceFieldValueFromLineItem',
      ),
      'priceField_from_lineItem' => array(
        'leftTable' => 'civicrm_line_item',
        'rightTable' => 'civicrm_price_field',
        'callback' => 'joinPriceFieldFromLineItem',
      ),
      'participant_from_lineItem' => array(
        'leftTable' => 'civicrm_line_item',
        'rightTable' => 'civicrm_participant',
        'callback' => 'joinParticipantFromLineItem',
      ),
      'contribution_from_lineItem' => array(
        'leftTable' => 'civicrm_line_item',
        'rightTable' => 'civicrm_contribution',
        'callback' => 'joinContributionFromLineItem',
      ),
      'membership_from_lineItem' => array(
        'leftTable' => 'civicrm_line_item',
        'rightTable' => 'civicrm_membership',
        'callback' => 'joinMembershipFromLineItem',
      ),
      'contribution_from_participant' => array(
        'leftTable' => 'civicrm_participant',
        'rightTable' => 'civicrm_contribution',
        'callback' => 'joinContribution:git FromParticipant',
      ),
      'contribution_from_membership' => array(
        'leftTable' => 'civicrm_membership',
        'rightTable' => 'civicrm_contribution',
        'callback' => 'joinContributionFromMembership',
      ),
      'membership_from_contribution' => array(
        'leftTable' => 'civicrm_contribution',
        'rightTable' => 'civicrm_membership',
        'callback' => 'joinMembershipFromContribution',
      ),
      'membershipType_from_membership' => array(
        'leftTable' => 'civicrm_membership',
        'rightTable' => 'civicrm_membership_type',
        'callback' => 'joinMembershipTypeFromMembership',
      ),
      'lineItem_from_contribution' => array(
        'leftTable' => 'civicrm_contribution',
        'rightTable' => 'civicrm_line_item',
        'callback' => 'joinLineItemFromContribution',
      ),
      'lineItem_from_membership' => array(
        'leftTable' => 'civicrm_membership',
        'rightTable' => 'civicrm_line_item',
        'callback' => 'joinLineItemFromMembership',
      ),
      'contact_from_participant' => array(
        'leftTable' => 'civicrm_participant',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinContactFromParticipant',
      ),
      'contact_from_membership' => array(
        'leftTable' => 'civicrm_membership',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinContactFromMembership',
      ),
      'contact_from_contribution' => array(
        'leftTable' => 'civicrm_contribution',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinContactFromContribution',
      ),
      'event_from_participant' => array(
        'leftTable' => 'civicrm_participant',
        'rightTable' => 'civicrm_event',
        'callback' => 'joinEventFromParticipant',
      ),
      'address_from_contact' => array(
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_address',
        'callback' => 'joinAddressFromContact',
      ),
    );
  }

  /*
* Add join from contact table to address. Prefix will be added to both tables
* as it's assumed you are using it to get address of a secondary contact
*/
  function joinAddressFromContact( $prefix = '') {
    $this->_from .= " LEFT JOIN civicrm_address {$this->_aliases[$prefix . 'civicrm_address']}
ON {$this->_aliases[$prefix . 'civicrm_address']}.contact_id = {$this->_aliases[$prefix . 'civicrm_contact']}.id";
  }

  function joinPriceFieldValueFromLineItem() {
    $this->_from .= " LEFT JOIN civicrm_price_field_value {$this->_aliases['civicrm_price_field_value']}
ON {$this->_aliases['civicrm_line_item']}.price_field_value_id = {$this->_aliases['civicrm_price_field_value']}.id";
  }

  function joinPriceFieldFromLineItem() {
    $this->_from .= "
LEFT JOIN civicrm_price_field {$this->_aliases['civicrm_price_field']}
ON {$this->_aliases['civicrm_line_item']}.price_field_id = {$this->_aliases['civicrm_price_field']}.id
";
  }
  /*
* Define join from line item table to participant table
*/
  function joinParticipantFromLineItem() {
    $this->_from .= " LEFT JOIN civicrm_participant {$this->_aliases['civicrm_participant']}
ON ( {$this->_aliases['civicrm_line_item']}.entity_id = {$this->_aliases['civicrm_participant']}.id
AND {$this->_aliases['civicrm_line_item']}.entity_table = 'civicrm_participant')
";
  }

  /*
* Define join from line item table to Membership table. Seems to be still via contribution
* as the entity. Have made 'inner' to restrict does that make sense?
*/
  function joinMembershipFromLineItem() {
    $this->_from .= " INNER JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
ON ( {$this->_aliases['civicrm_line_item']}.entity_id = {$this->_aliases['civicrm_contribution']}.id
AND {$this->_aliases['civicrm_line_item']}.entity_table = 'civicrm_contribution')
LEFT JOIN civicrm_membership_payment pp
ON {$this->_aliases['civicrm_contribution']}.id = pp.contribution_id
LEFT JOIN civicrm_membership {$this->_aliases['civicrm_membership']}
ON pp.membership_id = {$this->_aliases['civicrm_membership']}.id
";
  }
  /*
* Define join from Participant to Contribution table
*/
  function joinContributionFromParticipant() {
    $this->_from .= " LEFT JOIN civicrm_participant_payment pp
ON {$this->_aliases['civicrm_participant']}.id = pp.participant_id
LEFT JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
ON pp.contribution_id = {$this->_aliases['civicrm_contribution']}.id
";
  }

  /*
* Define join from Membership to Contribution table
*/
  function joinContributionFromMembership() {
    $this->_from .= " LEFT JOIN civicrm_membership_payment pp
ON {$this->_aliases['civicrm_membership']}.id = pp.membership_id
LEFT JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
ON pp.contribution_id = {$this->_aliases['civicrm_contribution']}.id
";
  }

  function joinParticipantFromContribution() {
    $this->_from .= " LEFT JOIN civicrm_participant_payment pp
ON {$this->_aliases['civicrm_contribution']}.id = pp.contribution_id
LEFT JOIN civicrm_participant {$this->_aliases['civicrm_participant']}
ON pp.participant_id = {$this->_aliases['civicrm_participant']}.id";
  }

  function joinMembershipFromContribution() {
    $this->_from .= "
LEFT JOIN civicrm_membership_payment pp
ON {$this->_aliases['civicrm_contribution']}.id = pp.contribution_id
LEFT JOIN civicrm_membership {$this->_aliases['civicrm_membership']}
ON pp.membership_id = {$this->_aliases['civicrm_membership']}.id";
  }

  function joinMembershipTypeFromMembership() {
    $this->_from .= "
LEFT JOIN civicrm_membership_type {$this->_aliases['civicrm_membership_type']}
ON {$this->_aliases['civicrm_membership']}.membership_type_id = {$this->_aliases['civicrm_membership_type']}.id
";
  }

  function joinContributionFromLineItem() {
    $temporary = $this->_temporary;
    $tempTable = 'civicrm_temp_report_line_items' . rand(1, 10000);
    $createTablesql = "
      CREATE $temporary TABLE $tempTable (
      `lid` INT(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Line Item',
      `id` INT(10) UNSIGNED NULL DEFAULT '0' COMMENT 'Contribution ID',
      INDEX `ContributionId` (`id`), `LineItemId` (`lid`),
    )
  COLLATE='utf8_unicode_ci'
  ENGINE=InnoDB;";

    $insertContributionRecordsSql = "
     INSERT INTO $tempTable
     SELECT line_item_civireport.id as lid, contribution_civireport_direct.id
     FROM civicrm_line_item line_item_civireport
     LEFT JOIN civicrm_contribution contribution_civireport_direct
     ON (line_item_civireport.entity_id = contribution_civireport_direct.id AND line_item_civireport.entity_table = 'civicrm_contribution')
     WHERE contribution_civireport_direct.id IS NOT NULL
     ";

    $insertParticipantRecordsSql = "
      INSERT INTO $tempTable
      SELECT line_item_civireport.id as lid, contribution_civireport.id
      FROM civicrm_line_item line_item_civireport
      LEFT JOIN civicrm_participant participant_civireport
      ON (line_item_civireport.entity_id = participant_civireport.id AND line_item_civireport.entity_table = 'civicrm_participant')
      LEFT JOIN civicrm_participant_payment pp
      ON participant_civireport.id = pp.participant_id
      LEFT JOIN civicrm_contribution contribution_civireport
      ON pp.contribution_id = contribution_civireport.id
    ";

    $insertMembershipRecordSql = "
      INSERT INTO $tempTable
      SELECT line_item_civireport.id as lid,contribution_civireport.id
      FROM civicrm_line_item line_item_civireport
      LEFT JOIN civicrm_membership membership_civireport
      ON (line_item_civireport.entity_id =membership_civireport.id AND line_item_civireport.entity_table = 'civicrm_membership')
      LEFT JOIN civicrm_membership_payment pp
      ON membership_civireport.id = pp.membership_id
      LEFT JOIN civicrm_contribution contribution_civireport
      ON pp.contribution_id = contribution_civireport.id
    ";
    CRM_Core_DAO::executeQuery($createTablesql);
    CRM_Core_DAO::executeQuery($insertContributionRecordsSql);
    CRM_Core_DAO::executeQuery($insertParticipantRecordsSql);
    CRM_Core_DAO::executeQuery($insertMembershipRecordSql);
    $this->_from .= "
      LEFT JOIN $tempTable as line_item_mapping
      ON line_item_mapping.lid = {$this->_aliases['civicrm_line_item']}.id
      LEFT JOIN civicrm_contribution as {$this->_aliases['civicrm_contribution']}
      ON {$this->_aliases['civicrm_contribution']}.lid = {$this->_aliases['civicrm_line_item']}.id
    ";
  }

  function joinLineItemFromContribution() {
    $temporary = $this->_temporary;// because we like to change this for debugging
    $tempTable = 'civicrm_temp_report_line_item_map' . rand(1, 10000);
    $createTablesql = "
    CREATE  $temporary TABLE $tempTable (
    `contid` INT(10) UNSIGNED NULL DEFAULT '0' COMMENT 'Contribution ID',
    `lid` INT(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Line Item',
    INDEX `ContributionId` (`contid`),
    INDEX `LineItemId` (`lid`)
    )
    COLLATE='utf8_unicode_ci'
    ENGINE=InnoDB;";

    $insertContributionRecordsSql = "
    INSERT INTO $tempTable
    SELECT contribution_civireport_direct.id AS contid, line_item_civireport.id as lid
    FROM civicrm_contribution contribution_civireport_direct
    LEFT JOIN civicrm_line_item line_item_civireport ON (line_item_civireport.line_total > 0 AND line_item_civireport.entity_id = contribution_civireport_direct.id AND line_item_civireport.entity_table = 'civicrm_contribution')
    WHERE line_item_civireport.id IS NOT NULL
    ";

    $insertParticipantRecordsSql = "
    INSERT INTO $tempTable
    SELECT contribution_civireport_direct.id AS contid, line_item_civireport.id as lid
    FROM civicrm_contribution contribution_civireport_direct
    LEFT JOIN civicrm_participant_payment pp ON contribution_civireport_direct.id = pp.contribution_id
    LEFT JOIN civicrm_participant p ON pp.participant_id = p.id
    LEFT JOIN civicrm_line_item line_item_civireport ON (line_item_civireport.line_total > 0 AND line_item_civireport.entity_id = p.id AND line_item_civireport.entity_table = 'civicrm_participant')
    WHERE line_item_civireport.id IS NOT NULL
    ";

    $insertMembershipRecordSql = "
    INSERT INTO $tempTable
    SELECT contribution_civireport_direct.id AS contid, line_item_civireport.id as lid
    FROM civicrm_contribution contribution_civireport_direct
    LEFT JOIN civicrm_membership_payment pp ON contribution_civireport_direct.id = pp.contribution_id
    LEFT JOIN civicrm_membership p ON pp.membership_id = p.id
    LEFT JOIN civicrm_line_item line_item_civireport ON (line_item_civireport.line_total > 0 AND line_item_civireport.entity_id = p.id AND line_item_civireport.entity_table = 'civicrm_membership')
    WHERE line_item_civireport.id IS NOT NULL
    ";

    CRM_Core_DAO::executeQuery($createTablesql);
    CRM_Core_DAO::executeQuery($insertContributionRecordsSql);
    CRM_Core_DAO::executeQuery($insertParticipantRecordsSql);
    CRM_Core_DAO::executeQuery($insertMembershipRecordSql);
    $this->_from .= "
    LEFT JOIN $tempTable as line_item_mapping
    ON line_item_mapping.contid = {$this->_aliases['civicrm_contribution']}.id
    LEFT JOIN civicrm_line_item as {$this->_aliases['civicrm_line_item']}
    ON {$this->_aliases['civicrm_line_item']}.id = line_item_mapping.lid

    ";
  }

  function joinLineItemFromMembership() {

    // this can be stored as a temp table & indexed for more speed. Not done at this stage.
    // another option is to cache it but I haven't tried to put that code in yet (have used it before for one hour caching
    $this->_from .= "
LEFT JOIN (
SELECT contribution_civireport_direct.id AS contid, line_item_civireport.*
FROM civicrm_contribution contribution_civireport_direct
LEFT JOIN civicrm_line_item line_item_civireport
ON (line_item_civireport.line_total > 0 AND line_item_civireport.entity_id = contribution_civireport_direct.id AND line_item_civireport.entity_table = 'civicrm_contribution')

WHERE line_item_civireport.id IS NOT NULL

UNION

SELECT contribution_civireport_direct.id AS contid, line_item_civireport.*
FROM civicrm_contribution contribution_civireport_direct
LEFT JOIN civicrm_membership_payment pp ON contribution_civireport_direct.id = pp.contribution_id
LEFT JOIN civicrm_membership p ON pp.membership_id = p.id
LEFT JOIN civicrm_line_item line_item_civireport ON (line_item_civireport.line_total > 0 AND line_item_civireport.entity_id = p.id AND line_item_civireport.entity_table = 'civicrm_membership')
WHERE line_item_civireport.id IS NOT NULL
) as {$this->_aliases['civicrm_line_item']}
ON {$this->_aliases['civicrm_line_item']}.contid = {$this->_aliases['civicrm_contribution']}.id
";
  }

  function joinContactFromParticipant() {
    $this->_from .= " LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
ON {$this->_aliases['civicrm_participant']}.contact_id = {$this->_aliases['civicrm_contact']}.id";
  }

  function joinContactFromMembership() {
    $this->_from .= " LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
ON {$this->_aliases['civicrm_membership']}.contact_id = {$this->_aliases['civicrm_contact']}.id";
  }

  function joinContactFromContribution() {
    $this->_from .= " LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
ON {$this->_aliases['civicrm_contribution']}.contact_id = {$this->_aliases['civicrm_contact']}.id";
  }

  function joinEventFromParticipant() {
    $this->_from .= " LEFT JOIN civicrm_event {$this->_aliases['civicrm_event']}
ON ({$this->_aliases['civicrm_event']}.id = {$this->_aliases['civicrm_participant']}.event_id ) AND
({$this->_aliases['civicrm_event']}.is_template IS NULL OR
{$this->_aliases['civicrm_event']}.is_template = 0)";
  }

  /*
   * Retrieve text for contribution type from pseudoconstant
  */
  function alterCrmEditable($value, &$row, $selectedfield, $criteriaFieldName, $specs) {
    $id_field = $specs['id_table'] . '_' . $specs['id_field'];
    if(empty($id_field)){
      return;
    }
    $entityID = $row[$id_field];
    $entity = $specs['entity'];
    $extra = '';
    if(!empty($specs['options'])){
      $specs['options']['selected'] = $value;
      $extra = "data-type='select' data-options='" . json_encode($specs['options'])  . "'";
      $value = $specs['options'][$value];
    }//nodeName == "INPUT" && this.type=="checkbox"
    return "<div id={$entity}-{$entityID} class='crm-entity'>
    <span class='crm-editable crmf-{$criteriaFieldName} editable_select crm-editable-enabled' data-action='create' $extra>" . $value . "</span></div>";
  }

  /*
* Retrieve text for contribution type from pseudoconstant
*/
  function alterNickName($value, &$row) {
    if(empty($row['civicrm_contact_id'])){
      return;
    }
    $contactID = $row['civicrm_contact_id'];
    return "<div id=contact-{$contactID} class='crm-entity'>
<span class='crm-editable crmf-nick_name crm-editable-enabled' data-action='create'>" . $value . "</span></div>";
  }

  /*
* Retrieve text for contribution type from pseudoconstant
*/
  function alterContributionType($value, &$row) {
    return is_string(CRM_Contribute_PseudoConstant::contributionType($value, FALSE)) ? CRM_Contribute_PseudoConstant::contributionType($value, FALSE) : '';
  }
  /*
* Retrieve text for contribution status from pseudoconstant
*/
  function alterContributionStatus($value, &$row) {
    return CRM_Contribute_PseudoConstant::contributionStatus($value);
  }
  /*
* Retrieve text for payment instrument from pseudoconstant
*/
  function alterEventType($value, &$row) {
    return CRM_Event_PseudoConstant::eventType($value);
  }

  function alterEventID($value, &$row) {
    return is_string(CRM_Event_PseudoConstant::event($value, FALSE)) ? CRM_Event_PseudoConstant::event($value, FALSE) : '';
  }

  function alterMembershipTypeID($value, &$row) {
    return is_string(CRM_Member_PseudoConstant::membershipType($value, FALSE)) ? CRM_Member_PseudoConstant::membershipType($value, FALSE) : '';
  }

  function alterMembershipStatusID($value, &$row) {
    return is_string(CRM_Member_PseudoConstant::membershipStatus($value, FALSE)) ? CRM_Member_PseudoConstant::membershipStatus($value, FALSE) : '';
  }

  function alterCountryID($value, &$row, $selectedfield, $criteriaFieldName) {
    $url = CRM_Utils_System::url(CRM_Utils_System::currentPath(), "reset=1&force=1&{$criteriaFieldName}_op=in&{$criteriaFieldName}_value={$value}", $this->_absoluteUrl);
    $row[$selectedfield . '_link'] = $url;
    $row[$selectedfield . '_hover'] = ts("%1 for this country.", array(
        1 => $value,
      ));
    $countries = CRM_Core_PseudoConstant::country($value, FALSE);
    if(!is_array($countries)){
      return $countries;
    }
  }

  function alterCountyID($value, &$row,$selectedfield, $criteriaFieldName) {
    $url = CRM_Utils_System::url(CRM_Utils_System::currentPath(), "reset=1&force=1&{$criteriaFieldName}_op=in&{$criteriaFieldName}_value={$value}", $this->_absoluteUrl);
    $row[$selectedfield . '_link'] = $url;
    $row[$selectedfield . '_hover'] = ts("%1 for this county.", array(
        1 => $value,
      ));
    $counties = CRM_Core_PseudoConstant::county($value, FALSE);
    if(!is_array($counties)){
      return $counties;
    }
  }

  function alterStateProvinceID($value, &$row, $selectedfield, $criteriaFieldName) {
    $url = CRM_Utils_System::url(CRM_Utils_System::currentPath(), "reset=1&force=1&{$criteriaFieldName}_op=in&{$criteriaFieldName}_value={$value}", $this->_absoluteUrl);
    $row[$selectedfield . '_link'] = $url;
    $row[$selectedfield . '_hover'] = ts("%1 for this state.", array(
        1 => $value,
      ));

    $states = CRM_Core_PseudoConstant::stateProvince($value, FALSE);
    if(!is_array($states)){
      return $states;
    }
  }

  function alterContactID($value, &$row, $fieldname) {
    $row[$fieldname . '_link'] = CRM_Utils_System::url("civicrm/contact/view", 'reset=1&cid=' . $value, $this->_absoluteUrl);
    return $value;
  }

  function alterParticipantStatus($value) {
    if (empty($value)) {
      return;
    }
    return CRM_Event_PseudoConstant::participantStatus($value, FALSE, 'label');
  }

  function alterParticipantRole($value) {
    if (empty($value)) {
      return;
    }
    $roles = explode(CRM_Core_DAO::VALUE_SEPARATOR, $value);
    $value = array();
    foreach ($roles as $role) {
      $value[$role] = CRM_Event_PseudoConstant::participantRole($role, FALSE);
    }
    return implode(', ', $value);
  }

  function alterPaymentType($value) {
    $paymentInstruments = CRM_Contribute_PseudoConstant::paymentInstrument();
    return $paymentInstruments[$value];
  }
}
