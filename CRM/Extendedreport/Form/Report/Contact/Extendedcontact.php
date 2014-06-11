<?php

/**
 * Class CRM_Extendedreport_Form_Report_Contact_Extendedcontact
 */
class CRM_Extendedreport_Form_Report_Contact_Extendedcontact extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_contact';
  protected $skipACL = TRUE;
  protected $_customGroupAggregates = TRUE;


  /**
   *
   */
  function __construct() {
    $this->_columns = $this->getContactColumns(array(
        'fields' => FALSE,
        'order_by' => FALSE
      )
    );
    $this->_columns['civicrm_contact']['fields']['id']['required'] = TRUE;
    parent::__construct();
  }
}
