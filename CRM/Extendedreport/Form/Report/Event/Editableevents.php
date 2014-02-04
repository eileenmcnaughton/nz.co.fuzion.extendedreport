<?php

class CRM_Extendedreport_Form_Report_Event_Editableevents extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_event';
  protected $skipACL = true;
  protected $_customGroupExtends = array( 'Event' );
  protected $_customGroupGroupBy = TRUE;

  function __construct() {
    $this->_columns = $this->getEventColumns();
    $this->_columns['civicrm_event']['fields']['id'] = array(
      'title' => 'id',
      'required' => TRUE,
    );
    parent::__construct();
  }
}
