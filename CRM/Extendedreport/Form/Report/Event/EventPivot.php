<?php

/**
 * Class CRM_Extendedreport_Form_Report_Event_EventPivot
 */
class CRM_Extendedreport_Form_Report_Event_EventPivot extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_baseTable = 'civicrm_participant';

  protected $_customGroupAggregates = TRUE;

  protected $isPivot = TRUE;

  protected $_rollup = 'WITH ROLLUP';

  public $_drilldownReport = ['event/participantlist' => 'Link to Participants'];

  protected $_participantTable = 'civicrm_participant';

  protected $_noFields = TRUE;

  protected $_customGroupExtends = ['Participant', 'Event', 'Contact', 'Individual', 'Household', 'Organization'];

  protected $_potentialCriteria = [
    'rid',
    'sid',
  ];

  /**
   * Class constructor.
   *
   * @throws \CRM_Core_Exception
   */
  public function __construct() {
    $this->_customGroupExtended['civicrm_event'] = [
      'extends' => ['Event'],
      'filters' => TRUE,
      'title' => ts('Event'),
    ];
    $this->_customGroupExtended['civicrm_participant'] = [
      'extends' => ['Participant'],
      'filters' => TRUE,
      'title' => ts('Participant'),
    ];

    $this->_columns = $this->getColumns('Event', [
          'fields' => FALSE,
        ]
      )
      + $this->getColumns('Participant', ['fields' => FALSE,])
      + $this->getColumns('Contact', ['fields' => FALSE,]);
    $this->_columns['civicrm_event']['fields']['id']['required'] = TRUE;
    $this->_columns['civicrm_event']['fields']['id']['alter_display'] = 'alterEventID';
    $this->_columns['civicrm_event']['fields']['id']['title'] = 'Event';

    $this->_groupFilter = TRUE;
    parent::__construct();
  }

  /**
   * Declare from clauses used in the from clause for this report.
   *
   * @return array
   */
  public function fromClauses(): array {
    return [
      'event_from_participant',
      'contact_from_participant',
    ];
  }

}
