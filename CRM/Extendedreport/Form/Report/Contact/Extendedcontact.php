<?php

class CRM_Extendedreport_Form_Report_Contact_Extendedcontact extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_contact';
  protected $skipACL = true;
  protected $_customGroupAggregates = true;


  function __construct() {
    $this->_columns = $this->getContactColumns(array(
      'fields' => false,
      'order_by' => false)
    );
    $this->_columns['civicrm_contact']['fields']['id']['required'] = true;
    parent::__construct();
  }
}
