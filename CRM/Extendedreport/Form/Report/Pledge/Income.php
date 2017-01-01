<?php

/**
 * Class CRM_Extendedreport_Form_Report_Contact_Basiccontact
 */
class CRM_Extendedreport_Form_Report_Pledge_Income extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_pledge_payment';
  protected $skipACL = FALSE;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_columns = $this->getColumns('PledgePayment');
    parent::__construct();
  }

}
