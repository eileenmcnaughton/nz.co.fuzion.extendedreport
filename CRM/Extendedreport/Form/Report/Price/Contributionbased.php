<?php

require_once 'CRM/Report/Form.php';

class CRM_Extendedreport_Form_Report_Price_Contributionbased extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_contribution';
  function __construct() {
    $this->_columns = $this->getContactColumns()
    //bhugh, to include registered by contact name in report

    + $this->getRegisteredForParticipantColumns()
    + $this->getRegisteredForContactColumns()
    + $this->getContactFromParticipantColumns()
    
    //bhugh, to include address columns in report
    + $this->getAddressColumns()   
    //bhugh, 2012/09, so that relationship fields (spouse, membership contact for orgs) can be included in reoprt
    + $this->getRelationshipColumns()            
    + $this->getRelationshipKeyContactColumns()         
    //to get address data for the key relationship contact (spouse/membership contact)
    + $this->getAddressColumns(array (
            'prefix' => 'key_relationship_contact_',
            'prefix_label' => 'Key Relationship ',
            'group_by' => false,
            'order_by' => true,
            'filters' => true,
            'defaults' => array(
              'country_id' => TRUE
            )
         )   
      )
    + $this->getEventColumns()
    + $this->getParticipantColumns()
    + $this->getContributionColumns()
    + $this->getPriceFieldColumns()
    + $this->getPriceFieldValueColumns()
    + $this->getLineItemColumns()
    ;
     

    parent::__construct();
  }
  function fromClauses() {
    return array(
        'lineItem_from_contribution',
        'contact_from_contribution',
        'priceFieldValue_from_lineItem',
        'priceField_from_lineItem',
        'participant_from_lineItem',
        'event_from_participant',

        //bhugh,2012/09, to allow inclusion of address, email, phone, relationship fields
        'address_from_contact',
        'email_from_contact',
        'phone_from_contact',
        'registeredforparticipant_from_participant',
        'registeredforcontact_from_registeredforparticipant',
        'participant_contact_from_participant',        
       //bhugh, 2012/09, allow spouse, head of household, & owner/manager to be imported as well
        'relationship_from_contact',
        'keycontact_from_relationship',
        'email_from_keyrelationship_contact',
        'phone_from_keyrelationship_contact',
        'address_from_keyrelationship_contact',    

    );
  }
}
