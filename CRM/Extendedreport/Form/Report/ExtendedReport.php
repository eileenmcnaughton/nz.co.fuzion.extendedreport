<?php

require_once 'CRM/Report/Form.php';
require_once 'CRM/Event/PseudoConstant.php';
require_once 'CRM/Member/PseudoConstant.php';

class CRM_Extendedreport_Form_Report_ExtendedReport extends CRM_Report_Form {
  protected $_addressField = FALSE;

  protected $_emailField = FALSE;

  protected $_summary = NULL;
  protected $_exposeContactID = FALSE;
  protected $_customGroupExtends = array();
  protected $_baseTable = 'civicrm_contact';
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
        //print ($fn);
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

  function groupBy() {
    parent::groupBy();
    //@todo - need to re-visit this - bad behaviour from pa
    if ($this->_groupBy == 'GROUP BY') {
      $this->_groupBY = NULL;
    }
    // if a stat field has been selected the do a group by
    if (!empty($this->_statFields) && empty($this->_groupBy)) {
      $this->_groupBy[] = $this->_aliases[$this->_baseTable] . ".id";
    }
    //@todo - this should be in the parent function or at parent level - perhaps build query should do this?
    if (!empty($this->_groupBy) && is_array($this->_groupBy)) {
      $this->_groupBy = 'GROUP BY ' . implode(',', $this->_groupBy);
    }
  }

  function orderBy() {
    parent::orderBy();
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
          if (in_array($tablename . '_' . $field, $selectedFields) && array_key_exists('alter_display', $specs)) {
            $alterfunctions[$tablename . '_' . $field] = $specs['alter_display'];
            $altermap[$tablename . '_' . $field] = $field;
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
          $rows[$index][$selectedfield] = $this->$alterfunctions[$selectedfield]($value, $row, $selectedfield, $altermap[$selectedfield]);
        }
      }
    }
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
        'alias' => 'civicrm_participant_columns',
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
          'is_test' => array('title' => ts('Test Participant?')),
          'is_pay_later' => array('title' => ts('Pay-Later Participant?')),

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
            'title' => 'Registration Date',
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
          'is_test' => array('title' => ts('Test Membership?')),
          'is_pay_later' => array('title' => ts('Pay-Later Membership?')),
 
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
          ),
          'event_type_id' => array('title' => ts('Event Type'),
            'required' => TRUE,
            'alter_display' => 'alterEventType',
          ),
          'fee_label' => array('title' => ts('Fee Label')),
          'event_start_date' => array('title' => ts('Event Start Date'),
          ),
          'event_end_date' => array('title' => ts('Event End Date')),
          'max_participants' => array('title' => ts('Capacity'),
            'type' => CRM_Utils_Type::T_INT,
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
        ),
        'order_bys' => array(
          'event_type_id' => array(
            'title' => ts('Event Type'),
            'default_weight' => '2',
            'default_order' => 'ASC',
          ),
          //bhugh, 2012/09, to allow ordering by event start date
          'event_start_date' => array(
            'title' => ts('Event start date'),
            'default_weight' => '1',
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
            //bhugh, added title for contrib iD which was previously blank
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
          'total_amount' => array('title' => ts('Total Amount'),
            'statistics' =>
            array('sum' => ts('Total Amount')),
            'type' => CRM_Utils_Type::T_MONEY,
          ),
          'contribution_status_id' =>
            array('title' => ts('Contribution Status'),
            'alter_display' => 'alterContributionStatus',
          ),
          'is_test' => array('title' => ts('Test Contribution?')),
          'is_pay_later' => array('title' => ts('Pay-Later Contribution?')),
          

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
          'receive_date' =>
          array('title' => ts('Receive date'),
                'default_weight' => '0',
                'default_order' => 'DESC', 
          ),
          'receipt_date' =>
          array('title' => ts('Receipt date'),
                'default_weight' => '0',
                'default_order' => 'DESC', 
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
         // bhugh 2012/09/05 - to include email & phone in contact options for report always.
      'civicrm_email' => array(
        'dao' => 'CRM_Core_DAO_Email',
        'name' => 'civicrm_email',
        'alias' => 'civicrm_email',
        'fields' =>
        array(
          'email' =>
          array('title' => ts('Contact Email'),
            'default' => TRUE,
            'no_repeat' => TRUE,
          ),
        ),
        'grouping' => 'contact-fields',
      ),
      'civicrm_phone' =>
      array(
        'dao' => 'CRM_Core_DAO_Phone',
        'name' => 'civicrm_phone',
        'alias' => 'civicrm_phone',
        'fields' =>
        array(
          'phone' =>
          array('title' => ts('Contact Phone'),
            'default' => TRUE,
            'no_repeat' => TRUE,
          ),
        ),
        'grouping' => 'contact-fields',
      ),
    );
  }


  function getContactFromParticipantColumns() {
    return array(
      'civicrm_contact_from_participant' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'alias' => 'civicrm_contact_from_participant',
        'name' => 'civicrm_contact',
        'fields' => array(
          'display_name_from_participant' => array(
            'name' => 'display_name',
            'title' => ts('Contact Name (Event Participant)'),
          ),
          'id_from_participant' => array(
            'name' => 'id',
            'title' => ts('Contact ID (Event Participant)'),
            'alter_display' => 'alterContactID',
          ),
          'first_name_from_participant' => array(
            'name' => 'first_name',          
            'title' => ts('First Name (Event Participant)'),
          ),
          'last_name_from_participant' => array(
            'name' => 'last_name',          
            'title' => ts('Last Name (Event Participant)'),
          ),
          'nick_name_from_participant' => array(
            'name' => 'nick_name',          
            'title' => ts('Nick Name (Event Participant)'),
            'alter_display' => 'alterNickName',
          ),
        ),
      ),    
    );
  }

  //bhugh, 2012/09, we want to get the individual contact name for each org contact, for key relationshiops (spouse for individuals OR membership contact for orgs). First we pull in the relationship info.   
  
  function getRelationshipColumns() {
    return array( 'civicrm_relationship' =>
      array(
        'dao' => 'CRM_Contact_DAO_Relationship',
        'name' => 'civicrm_relationship',
        'alias' => 'civicrm_relationship',
         
        'fields' =>
        array(
          'relationship_id' =>
          array(
            'name' => 'id',
            'title' => ts('Relationship ID'),
            'no_repeat' => TRUE,
            'default' => TRUE,
          ),
          'relationship_type_id' =>
          array('title' => ts('Relationship Type'),
            'default' => TRUE,
          ),

          'contact_id_a' =>
          array('title' => ts('Relationship With (Contact ID A)'),
            'default' => TRUE,
          ),
          'contact_id_b' =>
          array('title' => ts('Relationship With (Contact ID B)'),
            'default' => TRUE,
          ),
          'start_date' =>
          array(
            'title' => 'Relationship Start Date ',
            'type' => CRM_Report_Form::OP_DATE,
          ),
          'end_date' =>
          array(
            'title' => 'Relationship End Date ',
            'type' => CRM_Report_Form::OP_DATE,
          ),
         ),
      )
     );
  }

  //bhugh, 2012/09, we want to get the individual contact name for each org contact, for key relationships (spouse for individuals OR membership contact for orgs). Now that we have the relationship info we can pull in the contact info for that related contact.
  function getRelationshipKeyContactColumns() {
    return array( 'civicrm_relationshipKeyContact' =>
      array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'name' => 'civicrm_contact',
        'alias' => 'civicrm_relationshipKeyContact',
         
        'fields' =>
        array(
          'id_keyrelationship' =>
          array(
            'name' => 'id',
            'title' => ts('Contact ID of Key Relationship'),
            'no_repeat' => TRUE,
            'default' => TRUE,
          ),
          'display_name_keyrelationship' => array(
            'name' => 'display_name',
            'title' => ts('Contact Name of Key Relationship'),
          ),          
          'first_name_keyrelationship' =>
          array(
            'name' => 'first_name',
            'title' => ts('First Name of Key Relationship'),
            'default' => TRUE,
          ),
          'middle_name_keyrelationship' =>
          array(
            'name' => 'middle_name',
            'title' => ts('Middle Name of Key Relationship'),
            'default' => TRUE,
          ),
          'last_name_keyrelationship' =>
          array(
            'name' => 'last_name', 
            'title' => ts('Last Name of Key Relationship'),
            'default' => TRUE,
          ),
          'nick_name_keyrelationship' =>
          array(
            'name' => 'nick_name',            
            'title' => ts('Nick Name of Key Relationship'),
            'default' => TRUE,
          ),

        ),
        
                    'grouping' => 'keyrelationship-contact-fields',
      ),  


          
      //civicrm_relationshipKeyContact_phone
      //civicrm_relationshipKeyContact_email
      // bhugh 2012/09/05 - to include email & phone in contact options for key relationship contact.
      'civicrm_relationshipKeyContact_email' => array(
        'dao' => 'CRM_Core_DAO_Email',
        'name' => 'civicrm_email',
        'alias' => 'civicrm_email_keyrelationship',
        'fields' =>
        array(
          'email_keyrelationship' =>
          array('title' => ts('Email of Key Relationship'),
            'name' => 'email',  
            'default' => TRUE,
            'no_repeat' => TRUE,
          ),
        ),
        'grouping' => 'keyrelationship-contact-fields',
      ),
      'civicrm_relationshipKeyContact_phone' =>
      array(
        'dao' => 'CRM_Core_DAO_Phone',
        'name' => 'civicrm_phone',
        'alias' => 'civicrm_phone_keyrelationship',
        'fields' =>
        array(
          'phone_keyrelationship' =>
          array('title' => ts('Phone of Key Relationship'),
            'name' => 'phone',            
            'default' => TRUE,
            'no_repeat' => TRUE,
          ),
        ),
        'grouping' => 'keyrelationship-contact-fields',
      ),

     );
  }

  //bhugh, 2012/09, we want to get the individual contact name for each org contact, for key relationships (spouse for individuals OR membership contact for orgs). Now that we have the relationship info we can pull in the contact info for that related contact.
  function getRegisteredByParticipantColumns() {
    return array( 'civicrm_registeredByParticipant' =>
      array(
        'dao' => 'CRM_Event_DAO_Participant',
        'name' => 'civicrm_participant',
        'alias' => 'civicrm_registeredByParticipant',
        
        'fields' =>
        array(
          'participant_id_registeredby' =>
          array(
            'name' => 'id',
            'title' => ts('Participant ID of Registered-by Contact'),
            'no_repeat' => TRUE,
            'default' => TRUE,
          ),
          
        ),
        
                    'grouping' => 'registeredby-contact-fields',
      ),  
          

     );
  }

 
  //bhugh, 2012/09, we want to get contact name etc for the event participant
  //  registered-by contact 
  function getRegisteredByContactColumns() {
    return array( 'civicrm_registeredByContact' =>
      array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'name' => 'civicrm_contact',
        'alias' => 'civicrm_registeredByContact',
        
        'fields' =>
        array(
          'id_registeredby' =>
          array(
            'name' => 'id',
            'title' => ts('Contact ID of Registered-by Contact'),
            'no_repeat' => TRUE,
            'default' => TRUE,
          ),
          'display_name_registeredby' => array(
            'name' => 'display_name',
            'title' => ts('Contact Name of Registered-by Contact'),
          ),          

          'first_name_registeredby' =>
          array(
            'name' => 'first_name',
            'title' => ts('First Name of Registered-by Contact'),
            'default' => TRUE,
          ),
          'middle_name_registeredby' =>
          array(
            'name' => 'middle_name',
            'title' => ts('Middle Name of Registered-by Contact'),
            'default' => TRUE,
          ),
          'last_name_registeredby' =>
          array(
            'name' => 'last_name', 
            'title' => ts('Last Name of Registered-by Contact'),
            'default' => TRUE,
          ),
          'nick_name_registeredby' =>
          array(
            'name' => 'nick_name',            
            'title' => ts('Nick Name of Registered-by Contact'),
            'default' => TRUE,
          ),

        ),
        
                    'grouping' => 'registeredby-contact-fields',
      ),  
          

     );
  }

  //bhugh, 2012/09, we want to get the individual contact name for each org contact, for key relationships (spouse for individuals OR membership contact for orgs). Now that we have the relationship info we can pull in the contact info for that related contact.
  function getRegisteredForParticipantColumns() {
    return array( 'civicrm_registeredForParticipant' =>
      array(
        'dao' => 'CRM_Event_DAO_Participant',
        'name' => 'civicrm_participant',
        'alias' => 'civicrm_registeredForParticipant',
        
        'fields' =>
        array(
          'participant_id_registeredfor' =>
          array(
            'name' => 'id',
            'title' => ts('Participant ID of Registered-for Contact'),
            'no_repeat' => TRUE,
            'default' => TRUE,
          ),
          
        ),
        
                    'grouping' => 'registeredfor-contact-fields',
      ),  
          

     );
  }

 
  //bhugh, 2012/09, we want to get contact name etc for the event participant
  //  registered-by contact 
  function getRegisteredForContactColumns() {
    return array( 'civicrm_registeredForContact' =>
      array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'name' => 'civicrm_contact',
        'alias' => 'civicrm_registeredForContact',
        
        'fields' =>
        array(
          'id_registeredfor' =>
          array(
            'name' => 'id',
            'title' => ts('Contact ID of Registered-for Contact'),
            'no_repeat' => TRUE,
            'default' => TRUE,
          ),
          'display_name_registeredfor' => array(
            'name' => 'display_name',
            'title' => ts('Contact Name of Registered-for Contact'),
          ),          

          'first_name_registeredfor' =>
          array(
            'name' => 'first_name',
            'title' => ts('First Name of Registered-for Contact'),
            'default' => TRUE,
          ),
          'middle_name_registeredfor' =>
          array(
            'name' => 'middle_name',
            'title' => ts('Middle Name of Registered-for Contact'),
            'default' => TRUE,
          ),
          'last_name_registeredfor' =>
          array(
            'name' => 'last_name', 
            'title' => ts('Last Name of Registered-for Contact'),
            'default' => TRUE,
          ),
          'nick_name_registeredfor' =>
          array(
            'name' => 'nick_name',            
            'title' => ts('Nick Name of Registered-for Contact'),
            'default' => TRUE,
          ),

        ),
        
                    'grouping' => 'registeredfor-contact-fields',
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
        //bhugh, fix? Not sure why this has the :git 
        //'callback' => 'joinContribution:git FromParticipant',
        'callback' => 'joinContributionFromParticipant',
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

      'participant_contact_from_participant' => array(
        'leftTable' => 'civicrm_participant',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinParticipantContactFromParticipant',
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
      'contact_from_contribution_or_participant' => array(
        'leftTable' => 'civicrm_contribution',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinContactFromContributionOrParticipant',
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

      //bhugh, 2012/09, added so that phone & email can be included in reports
      'email_from_contact' => array(
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_email',
        'callback' => 'joinEmailFromContact',
      ),
      'phone_from_contact' => array(
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_phone',
        'callback' => 'joinPhoneFromContact',
      ),

      //bhugh, 2012/09, pull relationship
      'relationship_from_contact' => array(
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_relationship',
        'callback' => 'joinRelationshipFromContact',
      ),

      //bhugh, 2012/09
      //to pull back the name & other contact info from the key related contact (spouse or membership contact for org)
      'keycontact_from_relationship' => array(
        'leftTable' => 'civicrm_relationship',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinKeyContactFromRelationship',
      ),
      
      //bhugh, 2012/09
      //to pull back the id of the participant who registered this contact (if there is one)
      'registeredbyparticipant_from_participant' => array(
        'leftTable' => 'civicrm_participant',
        'rightTable' => 'civicrm_participant',
        'callback' => 'joinRegisteredByParticipantFromParticipant',
      ),


      //bhugh, 2012/09
      //to pull back the id of the Contact of the participant who registered this contact (if there is one)
      'registeredbycontact_from_registeredbyparticipant' => array(
        'leftTable' => 'civicrm_participant',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinRegisteredByContactFromRegisteredByParticipant',
      ),
            
      //bhugh, 2012/09
      //to pull back the id of the participant who registered for this contact (if there is one)
      'registeredforparticipant_from_participant' => array(
        'leftTable' => 'civicrm_participant',
        'rightTable' => 'civicrm_participant',
        'callback' => 'joinRegisteredForParticipantFromParticipant',
      ),

      //bhugh, 2012/09
      //to pull back the id of the Contact of the participant who registered this contact (if there is one)
      'registeredforcontact_from_registeredforparticipant' => array(
        'leftTable' => 'civicrm_participant',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinRegisteredForContactFromRegisteredForParticipant',
      ),

      //bhugh, 2012/09, added so that phone & email for key relationship contact can be included in reports
      'email_from_keyrelationship_contact' => array(
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_email',
        'callback' => 'joinEmailFromKeyRelationshipContact',
      ),
      'phone_from_keyrelationship_contact' => array(
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_phone',
        'callback' => 'joinPhoneFromKeyRelationshipContact',
      ),
      'address_from_keyrelationship_contact' => array(
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_address',
        'callback' => 'joinAddressFromKeyRelationshipContact',
      ),
      
    );
  }

  /*
* Add join from contact table to address. Prefix will be added to both tables
* as it's assumed you are using it to get address of a secondary contact
*/
  function joinAddressFromContact( $prefix = '') {
    $this->_from .= " LEFT JOIN civicrm_address {$this->_aliases[$prefix . 'civicrm_address']}
ON {$this->_aliases[$prefix . 'civicrm_address']}.contact_id = {$this->_aliases[$prefix . 'civicrm_contact']}.id ".
    //bhugh, 2012/09, added this to ensure that the PRIMARY address is always selected
    "AND {$this->_aliases[$prefix . 'civicrm_address']}.is_primary = 1 
    ";
  }

  //bhugh, 2012/09, to allow PRIMARY email of contact to be included in the report
  function joinEmailFromContact() {
    $this->_from .= " LEFT JOIN civicrm_email {$this->_aliases[ 'civicrm_email']}
ON {$this->_aliases[ 'civicrm_email']}.contact_id = {$this->_aliases['civicrm_contact']}.id AND
     {$this->_aliases['civicrm_email']}.is_primary = 1 
     ";
  }

  //bhugh, 2012/09, to allow PRIMARY phone of contact to be included in the report
  function joinPhoneFromContact() {
    $this->_from .= " LEFT JOIN civicrm_phone {$this->_aliases[ 'civicrm_phone']}
ON {$this->_aliases[ 'civicrm_phone']}.contact_id = {$this->_aliases['civicrm_contact']}.id AND
                      {$this->_aliases['civicrm_phone']}.is_primary = 1 
  ";
  }

    //bhugh, 2012/09, to allow PRIMARY email of key relationship contact to be included in the report
  function joinEmailFromKeyRelationshipContact() {
    $this->_from .= " LEFT JOIN civicrm_email {$this->_aliases[ 'civicrm_relationshipKeyContact_email']}
ON {$this->_aliases[ 'civicrm_relationshipKeyContact_email']}.contact_id = {$this->_aliases['civicrm_relationshipKeyContact']}.id AND
     {$this->_aliases['civicrm_relationshipKeyContact_email']}.is_primary = 1 
     ";
  }

  //bhugh, 2012/09, to allow PRIMARY phone of key relationship contact to be included in the report
  function joinPhoneFromKeyRelationshipContact() {
    $this->_from .= " LEFT JOIN civicrm_phone {$this->_aliases[ 'civicrm_relationshipKeyContact_phone']}
ON {$this->_aliases[ 'civicrm_relationshipKeyContact_phone']}.contact_id = {$this->_aliases['civicrm_relationshipKeyContact']}.id AND
                      {$this->_aliases['civicrm_relationshipKeyContact_phone']}.is_primary = 1 
  ";
  }
  
  

  //bhugh, 2012/09, to allow PRIMARY address of key relationship contact to be included in the report.  we diverge slightly from the naming convention in joinAddressFromContact() or we could just use that
  function joinAddressFromKeyRelationshipContact() {
    $this->_from .= " LEFT JOIN civicrm_address {$this->_aliases[ 'key_relationship_contact_civicrm_address']}
ON {$this->_aliases[ 'key_relationship_contact_civicrm_address']}.contact_id = {$this->_aliases['civicrm_relationshipKeyContact']}.id AND
                      {$this->_aliases['key_relationship_contact_civicrm_address']}.is_primary = 1 
  ";
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
* bhugh, 2012/09, added registered_by_id line to pick up payments
* from ppl who paid on behalf of another person.  SQL update #1, works with #2 (below)
*/
  function joinParticipantFromLineItem() {
    $this->_from .= " LEFT JOIN civicrm_participant {$this->_aliases['civicrm_participant']}
ON ( ( {$this->_aliases['civicrm_line_item']}.entity_id = {$this->_aliases['civicrm_participant']}.id  ) ".

" AND {$this->_aliases['civicrm_line_item']}.entity_table = 'civicrm_participant')

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
   * bhugh, 2012/09 , added registered_by_id clauses to get registered_by payments to show up on summary lists
   */
  function joinContributionFromParticipant() {
    $this->_from .= " LEFT JOIN civicrm_participant_payment pp
ON ( {$this->_aliases['civicrm_participant']}.id = pp.participant_id  " .

//bhugh, 2012/09, SQL Update #2. Including this and #1 (above) brings in the
//paid-for participant into the transactions list as separate record 

" OR {$this->_aliases['civicrm_participant']}.registered_by_id = pp.participant_id ) ".


"LEFT JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
ON ( pp.contribution_id = {$this->_aliases['civicrm_contribution']}.id )  

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
ON pp.participant_id = {$this->_aliases['civicrm_participant']}.id  ";
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

    // this can be stored as a temp table & indexed for more speed. Not done at this state.
    // another option is to cache it but I haven't tried to put that code in yet (have used it before for one hour caching
    //bhugh, 2012/09, second union & left join block put in place to catch the event "registered by" payments
   
    $this->_from .= " LEFT JOIN (SELECT line_item_civireport.id as lid, contribution_civireport_direct.*
FROM civicrm_line_item line_item_civireport
LEFT JOIN civicrm_contribution contribution_civireport_direct
ON (line_item_civireport.entity_id = contribution_civireport_direct.id AND line_item_civireport.entity_table = 'civicrm_contribution')


WHERE contribution_civireport_direct.id IS NOT NULL

UNION 
SELECT line_item_civireport.id as lid, contribution_civireport.*
FROM civicrm_line_item line_item_civireport
LEFT JOIN civicrm_participant participant_civireport
ON (line_item_civireport.entity_id = participant_civireport.id AND line_item_civireport.entity_table = 'civicrm_participant')

LEFT JOIN civicrm_participant_payment pp
ON participant_civireport.id = pp.participant_id
LEFT JOIN civicrm_contribution contribution_civireport
ON pp.contribution_id = contribution_civireport.id

" .

"UNION 
SELECT line_item_civireport.id as lid, contribution_civireport.*
FROM civicrm_line_item line_item_civireport
INNER JOIN civicrm_participant participant_civireport
ON (    line_item_civireport.entity_id = participant_civireport.id AND     
    line_item_civireport.entity_table = 'civicrm_participant')

INNER JOIN civicrm_participant_payment pp
ON participant_civireport.registered_by_id = pp.participant_id
LEFT JOIN civicrm_contribution contribution_civireport
ON pp.contribution_id = contribution_civireport.id

" .

"UNION 
SELECT line_item_civireport.id as lid,contribution_civireport.*
FROM civicrm_line_item line_item_civireport
LEFT JOIN civicrm_membership membership_civireport
ON (line_item_civireport.entity_id =membership_civireport.id AND line_item_civireport.entity_table = 'civicrm_membership')

LEFT JOIN civicrm_membership_payment pp
ON membership_civireport.id = pp.membership_id
LEFT JOIN civicrm_contribution contribution_civireport
ON pp.contribution_id = contribution_civireport.id
) as {$this->_aliases['civicrm_contribution']}
ON {$this->_aliases['civicrm_contribution']}.lid = {$this->_aliases['civicrm_line_item']}.id  
";
  }
  
  function joinLineItemFromContribution() {
  
    // this can be stored as a temp table & indexed for more speed. Not done at this stage.
    // another option is to cache it but I haven't tried to put that code in yet (have used it before for one hour caching


    $this->_from .= "
LEFT JOIN (
SELECT contribution_civireport_direct.id AS contid, line_item_civireport.*
FROM civicrm_contribution contribution_civireport_direct
LEFT JOIN civicrm_line_item line_item_civireport ON (line_item_civireport.line_total > 0 AND line_item_civireport.entity_id = contribution_civireport_direct.id AND line_item_civireport.entity_table = 'civicrm_contribution')
WHERE line_item_civireport.id IS NOT NULL

UNION
SELECT contribution_civireport_direct.id AS contid, line_item_civireport.*
FROM civicrm_contribution contribution_civireport_direct
LEFT JOIN civicrm_participant_payment pp ON contribution_civireport_direct.id = pp.contribution_id
LEFT JOIN civicrm_participant p ON ( pp.participant_id = p.id 
OR pp.participant_id = p.registered_by_id ) 
LEFT JOIN civicrm_line_item line_item_civireport ON (line_item_civireport.line_total > 0 AND line_item_civireport.entity_id = p.id AND line_item_civireport.entity_table = 'civicrm_participant')
WHERE line_item_civireport.id IS NOT NULL

" .

"UNION

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

  //bhugh, if we have a registered_by event participant the event participant 
  //contact can be different from the main/contributions contact.
  //this gets the contact info from the event participant contact
  function joinParticipantContactFromParticipant() {
    $this->_from .= " LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact_from_participant']}
ON {$this->_aliases['civicrm_participant']}.contact_id = {$this->_aliases['civicrm_contact_from_participant']}.id";
  }

                    
  function joinContactFromMembership() {
    $this->_from .= " LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
ON {$this->_aliases['civicrm_membership']}.contact_id = {$this->_aliases['civicrm_contact']}.id";
  }
  //this has a fatal flaw in that it will not bring in line items which are paid
  //for for one event participant by another -- the registered_by_id person
  //who is not the participant but the one who paid
  //joinContactFromContributionOrParticipant is designed to fix this problem
  //by also bringing in the extra payments that are made as part of a donation
  // in civicrm_contribution but credited to another user in civicrm_participant
    function joinContactFromContribution() {
    $this->_from .= " LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
ON {$this->_aliases['civicrm_contribution']}.contact_id = {$this->_aliases['civicrm_contact']}.id";
  }

  //bhugh, 2012/09, this is a modded function that will pull in all line items from civicrm_contribution AND all the extra event participant payments
  //made on behalf of another person (registered_by_id).
  //However, upon experimentation this didn't seem necessary so it is rem-ed out.
  //The function name is used in the other files though so I have left it
  
  function joinContactFromContributionOrParticipant() {
    $this->_from .= " LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']} ON ( {$this->_aliases['civicrm_contribution']}.contact_id = {$this->_aliases['civicrm_contact']}.id )" .
/*    
    " OR {$this->_aliases['civicrm_participant']}.contact_id = {$this->_aliases['civicrm_contact']}.id) 
    ";
*/
 ""; 
  }

 //Bring in a key relationship for the contact -- either the spouse, for individuals (relationship type = 2), household contac for households (type 6),  or the owner/manager for organizations (relationship type = 14)
 //Because we don't have a way of designating and enforcing 'primary' on 
 //these relationships they join is prone to bringing in more than one 
 //row at a time, which messes a lot of things up.
 //The fix: MAX(z.id) clause is a way of selecting only one relationship match.  Min or Max is arbitrary, and there should be at most one match, but if there is more than one match we have a lot of problems with doubled (or tripled, quadrupled, totals, doubled quantity selected, and so on, because two matches here create two lines in the SQL results and we are expecting just one line.  Chose Max rather than MIN on the assumption that the most recently entered relationship is most likely to be most important or most currently active.
 //TODO: Ideally we would be able to select which relationships to include here via the filter section on the report form, rather than having them hardcoded in.
  function joinRelationshipFromContact() {
    $this->_from .= " LEFT JOIN civicrm_relationship {$this->_aliases['civicrm_relationship']}
               on ( {$this->_aliases['civicrm_relationship']}.contact_id_b={$this->_aliases['civicrm_contact']}.id  
               OR {$this->_aliases['civicrm_relationship']}.contact_id_a={$this->_aliases['civicrm_contact']}.id)
               

 AND 
               ({$this->_aliases['civicrm_relationship']}.is_active = 1 AND 
                ( {$this->_aliases['civicrm_relationship']}.relationship_type_id=2 AND  
                   {$this->_aliases['civicrm_contact']}.contact_type = 'Individual') OR 
                ( {$this->_aliases['civicrm_relationship']}.relationship_type_id=18 AND  
                  {$this->_aliases['civicrm_contact']}.contact_type = 'Organization')
                ) 
        AND {$this->_aliases['civicrm_relationship']}.id in 
        
        ( SELECT MAX(z.id) from civicrm_relationship AS z 
         WHERE (z.contact_id_b={$this->_aliases['civicrm_contact']}.id 
               OR z.contact_id_A={$this->_aliases['civicrm_contact']}.id)  
                  and ((z.relationship_type_id=14 AND  
                  {$this->_aliases['civicrm_contact']}.contact_type = 'Organization') OR (z.relationship_type_id=2 AND  
                  {$this->_aliases['civicrm_contact']}.contact_type = 'Individual') OR (z.relationship_type_id=6 AND  
                  {$this->_aliases['civicrm_contact']}.contact_type = 'Household')) )  
       ";

  }

  //bhugh, 2012/09
  function joinKeyContactFromRelationship() {
    $this->_from .= " LEFT JOIN civicrm_contact {$this->_aliases['civicrm_relationshipKeyContact']}
        ON ({$this->_aliases['civicrm_contact']}.id <> {$this->_aliases['civicrm_relationship']}.contact_id_a 
        AND {$this->_aliases['civicrm_relationshipKeyContact']}.id={$this->_aliases['civicrm_relationship']}.contact_id_a) 
        OR ({$this->_aliases['civicrm_contact']}.id <> {$this->_aliases['civicrm_relationship']}.contact_id_b 
        AND {$this->_aliases['civicrm_relationshipKeyContact']}.id={$this->_aliases['civicrm_relationship']}.contact_id_b)
     ";
   }  
     
  //bhugh, 2012/09       
  function joinRegisteredByParticipantFromParticipant() {
    $this->_from .= " LEFT JOIN civicrm_participant {$this->_aliases['civicrm_registeredByParticipant']} ON 
       {$this->_aliases['civicrm_participant']}.registered_by_id = {$this->_aliases['civicrm_registeredByParticipant']}.id 
    ";
  }  

  //bhugh, 2012/09    
  function joinRegisteredByContactFromRegisteredByParticipant() {  
    $this->_from .= " LEFT JOIN civicrm_contact {$this->_aliases['civicrm_registeredByContact']} ON 
       {$this->_aliases['civicrm_registeredByParticipant']}.contact_id = {$this->_aliases['civicrm_registeredByContact']}.id 
    ";

  }

  //bhugh, 2012/09
  function joinRegisteredForParticipantFromParticipant() {
    $this->_from .= " LEFT JOIN civicrm_participant {$this->_aliases['civicrm_registeredForParticipant']} ON 
       {$this->_aliases['civicrm_participant']}.id = {$this->_aliases['civicrm_registeredForParticipant']}.registered_by_id 
    ";
  }  
  
  //bhugh, 2012/09
  function joinRegisteredForContactFromRegisteredForParticipant() {  
    $this->_from .= " LEFT JOIN civicrm_contact {$this->_aliases['civicrm_registeredForContact']} ON 
       {$this->_aliases['civicrm_registeredForParticipant']}.contact_id = {$this->_aliases['civicrm_registeredForContact']}.id 
    ";

  }
  
  
  function joinEventFromParticipant() {
    $this->_from .= " LEFT JOIN civicrm_event {$this->_aliases['civicrm_event']}
ON ({$this->_aliases['civicrm_event']}.id = 
{$this->_aliases['civicrm_participant']}.event_id ) AND
({$this->_aliases['civicrm_event']}.is_template IS NULL OR
{$this->_aliases['civicrm_event']}.is_template = 0)";
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
<span class='crm-editable crmf-nick_name crm-editable-enabled' data-action='create'>
" . $value . "</span></div>";
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
    $row[$selectedfield . '_hover'] = ts("State/province ID=%1 for this state.", array(
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
