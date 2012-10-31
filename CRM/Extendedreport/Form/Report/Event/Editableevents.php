<?php

require_once 'CRM/Report/Form.php';

class CRM_Extendedreport_Form_Report_Event_Editableevents extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_event';
  protected $skipACL = true;
  protected $_customGroupExtends = array( 'Event' );
  protected $_customGroupGroupBy = TRUE;

  function __construct() {
    $this->_columns = $this->getEventColumns();
    parent::__construct();
  }
}
