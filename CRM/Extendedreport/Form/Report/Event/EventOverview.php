<?php

class CRM_Extendedreport_Form_Report_Event_EventOverview extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_event';
  protected $skipACL = true;
  protected $_customGroupExtends = array( 'Event' );
  protected $_customGroupGroupBy = TRUE;

  function __construct() {
    $this->_columns = $this->getEventColumns()
    +$this->getEventSummaryColumns();
    parent::__construct();
  }

  function fromClauses( ) {
    return array(
      'eventsummary_from_event',
    );
  }

}
