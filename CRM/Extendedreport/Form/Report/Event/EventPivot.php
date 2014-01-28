<?php

class CRM_Extendedreport_Form_Report_Event_EventPivot extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_participant';
  protected $skipACL = true;
  protected $_customGroupAggregates = true;
  protected $_aggregatesIncludeNULL = TRUE;
  protected $_aggregatesAddTotal = TRUE;
  protected $_rollup = 'WITH ROLLUP';
  public $_drilldownReport = array('event/participantlist' => 'Link to Participants');
  protected $_potentialCriteria = array(
    'rid',
    'sid',
  );

  function __construct() {
    $this->_customGroupExtended['civicrm_event'] = array(
      'extends' => array('Event'),
      'filters' => TRUE,
      'title'  => ts('Event'),
    );
    $this->_customGroupExtended['civicrm_participant'] = array(
      'extends' => array('Participant'),
      'filters' => TRUE,
      'title'  => ts('Participant'),
    );

    $this->_columns = $this->getColumns('Event', array(
      'fields' => false,)
    )
    + $this->getColumns('Participant', array('fields' => false,));
    $this->_columns['civicrm_event']['fields']['id']['required'] = true;
    $this->_columns['civicrm_event']['fields']['id']['alter_display'] = 'alterEventID';
    $this->_columns['civicrm_event']['fields']['id']['title'] = 'Event';

    $this->_aggregateRowFields  = array(
      'event_civireport:id' => 'Event'
    );
    parent::__construct();
  }

  function fromClauses( ) {
    return array(
      'event_from_participant',
    );
  }
}
