<?php

/**
 * Class CRM_Extendedreport_Form_Report_Pledge_Income
 */
class CRM_Extendedreport_Form_Report_Pledge_Income extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_pledge_payment';
  protected $skipACL = FALSE;
  protected $_customGroupExtends = array(
    'Pledge',
    'Contact',
    'Individual',
    'Organization',
    'Household',
  );

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_columns = $this->getColumns('PledgePayment', array(
      'fields_defaults' => array('scheduled_amount', 'scheduled_date')
    ))
    + $this->getColumns('Contact')
    + $this->getColumns('Pledge');
    parent::__construct();
  }

  /**
   * Declare joins.
   *
   * @return array
   */
  public function fromClauses() {
    return array(
      'pledge_from_pledge_payment',
      'contact_from_pledge',
    );
  }

}
