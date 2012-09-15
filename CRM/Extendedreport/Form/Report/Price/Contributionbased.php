<?php

require_once 'CRM/Report/Form.php';

class CRM_Extendedreport_Form_Report_Price_Contributionbased extends CRM_Report_Form_Extended {
  protected $_customGroupExtends = array( 'Contribution', 'Membership', 'Contact', 'Individual', 'Household', 'Organization', 'Participant', 'Event','Activity', 'Address', 'Campaign', 'Case',  'Grant', 'Group', 'ParticipantEventName', 'ParticipantEventType');
  protected $_baseTable = 'civicrm_contribution';
  function __construct() {
    $this->_columns = $this->getContactColumns()
    + $this->getContributionColumns()
    + $this->getPriceFieldColumns()
    + $this->getPriceFieldValueColumns()
    + $this->getLineItemColumns();


    parent::__construct();
  }
  function fromClauses() {
    return array(
        'lineItem_from_contribution',
        'contact_from_contribution',
        'priceFieldValue_from_lineItem',
        'priceField_from_lineItem'
    );
  }
}
