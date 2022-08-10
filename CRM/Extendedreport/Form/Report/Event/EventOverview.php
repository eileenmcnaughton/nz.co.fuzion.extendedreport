<?php

/**
 * Class CRM_Extendedreport_Form_Report_Event_EventOverview
 */
class CRM_Extendedreport_Form_Report_Event_EventOverview extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_baseTable = 'civicrm_event';

  protected $_customGroupExtends = ['Event'];

  protected $_customGroupGroupBy = TRUE;

  protected $_autoIncludeIndexedFieldsAsOrderBys = TRUE;

  protected $_add2groupSupported = FALSE;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_columns = $this->getColumns('Event', []) +
      $this->getColumns('EventSummary')
    + $this->getColumns('Address');
    $this->_whereClauses[] = 'event.is_template = 0';
    parent::__construct();
  }

  /**
   * Get from clauses.
   *
   * @return array
   */
  public function fromClauses(): array {
    return [
      'eventsummary_from_event',
      'address_from_event',
    ];
  }
}
