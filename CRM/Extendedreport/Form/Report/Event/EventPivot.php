<?php

/**
 * Class CRM_Extendedreport_Form_Report_Event_EventPivot
 */
class CRM_Extendedreport_Form_Report_Event_EventPivot extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_participant';
  protected $skipACL = TRUE;
  protected $_customGroupAggregates = TRUE;
  protected $_aggregatesIncludeNULL = TRUE;
  protected $_aggregatesAddTotal = TRUE;
  protected $isPivot = TRUE;
  protected $_rollup = 'WITH ROLLUP';
  public $_drilldownReport = array('event/participantlist' => 'Link to Participants');
  protected $_participantTable = 'civicrm_participant';
  protected $_noFields = TRUE;
  protected $_potentialCriteria = array(
    'rid',
    'sid',
  );

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_customGroupExtended['civicrm_event'] = array(
      'extends' => array('Event'),
      'filters' => TRUE,
      'title' => ts('Event'),
    );
    $this->_customGroupExtended['civicrm_participant'] = array(
      'extends' => array('Participant'),
      'filters' => TRUE,
      'title' => ts('Participant'),
    );

    $this->_columns = $this->getColumns('Event', array(
          'fields' => FALSE,
        )
      )
      + $this->getColumns('Participant', array('fields' => FALSE,))
      + $this->getColumns('Contact', array('fields' => FALSE,));
    $this->_columns['civicrm_event']['fields']['id']['required'] = TRUE;
    $this->_columns['civicrm_event']['fields']['id']['alter_display'] = 'alterEventID';
    $this->_columns['civicrm_event']['fields']['id']['title'] = 'Event';

    $this->_aggregateRowFields = array(
      'event:id' => 'Event',
    );

    $this->_aggregateColumnHeaderFields = array(
      'participant:status_id' => ts('Participant Status'),
    );
    $this->_groupFilter = TRUE;
    parent::__construct();
  }

  /**
   * Declare from clauses used in the from clause for this report.
   *
   * @return array
   */
  public function fromClauses() {
    return array(
      'event_from_participant',
      'contact_from_participant',
    );
  }
}
