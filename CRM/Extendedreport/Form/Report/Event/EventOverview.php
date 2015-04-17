<?php

/**
 * Class CRM_Extendedreport_Form_Report_Event_EventOverview
 */
class CRM_Extendedreport_Form_Report_Event_EventOverview extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_event';
  protected $skipACL = TRUE;
  protected $_customGroupExtends = array('Event');
  protected $_customGroupGroupBy = TRUE;
  protected $_autoIncludeIndexedFieldsAsOrderBys = TRUE;
  protected $_add2groupSupported = FALSE;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_columns = $this->getColumns('Event', array()) +
    $this->getColumns('EventSummary');
    parent::__construct();
  }

  /**
   * Get from clauses.
   *
   * @return array
   */
  public function fromClauses() {
    return array(
      'eventsummary_from_event',
    );
  }
}
