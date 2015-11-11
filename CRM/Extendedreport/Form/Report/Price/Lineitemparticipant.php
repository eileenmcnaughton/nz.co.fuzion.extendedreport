<?php
/**
 * Class CRM_Extendedreport_Form_Report_Price_Lineitemparticipant
 */
class CRM_Extendedreport_Form_Report_Price_Lineitemparticipant extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_addressField = FALSE;

  protected $_emailField = FALSE;

  protected $_summary = NULL;

  protected $_customGroupExtends = array('Participant', 'Individual', 'Contact');

  protected $_baseTable = 'civicrm_line_item';

  protected $_aclTable = 'civicrm_contact';

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_columns = $this->getColumns('Contact') +
    $this->getColumns('Event') +
    $this->getColumns('Participant') +
    $this->getColumns('Contribution') +
    $this->getColumns('PriceField') +
    $this->getColumns('PriceFieldValue') +
    $this->getColumns('LineItem') +
    $this->getColumns('Address');
    parent::__construct();
  }

  /**
   * PreProcess function.
   */
  public function preProcess() {
    parent::preProcess();
  }

  /**
   * Select from clauses to use.
   *
   * (from those advertised using $this->getAvailableJoins())
   *
   * @return array
   */
  public function fromClauses() {
    return array(
      'priceFieldValue_from_lineItem',
      'priceField_from_lineItem',
      'participant_from_lineItem',
      'contribution_from_participant',
      'contact_from_participant',
      'event_from_participant',
      'address_from_contact',
    );
  }
}
