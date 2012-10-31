<?php

class CRM_Extendedreport_Form_Report_Contact_Extendedcontact extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_contact';
  protected $skipACL = true;
  protected $_customGroupAggregates = true;


  function __construct() {
    $this->_columns = $this->getContactColumns();
    parent::__construct();

  }
}
