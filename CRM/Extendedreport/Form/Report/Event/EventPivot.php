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
   * Class constructor
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
  function alterDisplay(&$rows) {
    parent::alterDisplay($rows);

    $this->_columnHeaders = array_slice($this->_columnHeaders, 0, 1, true) +
    $this->_columnHeaders = array('civicrm_event_title' => array("title"=> ts('Event Title'))) +
    array_slice($this->_columnHeaders, 1, NULL, true);

    foreach ($rows as $rowNum => $row) {          
      if (array_key_exists('civicrm_event_id', $row)) {
        if ($eventId = $row['civicrm_event_id']) {
           $eventTitle = CRM_Event_PseudoConstant::event($eventId, FALSE);
           $eventURL = CRM_Report_Utils_Report::getNextUrl('event/income',
             'reset=1&force=1&id_op=in&id_value=' . $eventId,
             $this->_absoluteUrl, $this->_id, $this->_drilldownReport
            );
            $titleValue = array ('civicrm_event_title' => $eventTitle,
              'civicrm_event_title_link' => $eventURL,
              'civicrm_event_title_hover' => ts("View Event Income Details for this Event"));
            $rows[$rowNum] = array_merge($rows[$rowNum], $titleValue);
        }
      }         
    }       
  }  
}