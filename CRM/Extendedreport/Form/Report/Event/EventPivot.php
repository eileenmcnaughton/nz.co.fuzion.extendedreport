<?php

class CRM_Extendedreport_Form_Report_Event_EventPivot extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_participant';
  protected $skipACL = true;
  protected $_customGroupAggregates = true;

  function __construct() {
    $this->_customGroupExtended['civicrm_participant'] = array(
      'extends' => array('Participant'),
      'filters' => TRUE,
      'title'  => ts('Participant'),
    );

    $this->_columns = $this->getEventColumns(array(
      'fields' => false,)
    )
    + $this->getParticipantColumns();
    $this->_columns['civicrm_event']['fields']['id']['required'] = true;
    parent::__construct();
  }

  function fromClauses( ) {
    return array(
      'event_from_participant',
    );
  }
}
