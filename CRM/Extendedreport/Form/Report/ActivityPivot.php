<?php

/**
 * Class CRM_Extendedreport_Form_Report_ActivityPivot
 */
class CRM_Extendedreport_Form_Report_ActivityPivot extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_activity';
  protected $skipACL = FALSE;
  protected $isPivot = TRUE;
  protected $_customGroupAggregates = TRUE;
  protected $_aggregatesIncludeNULL = TRUE;
  protected $_aggregatesAddTotal = TRUE;
  protected $_rollup = 'WITH ROLLUP';
  public $_drilldownReport = array();
  protected $_potentialCriteria = array();
  protected $_noFields = TRUE;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_customGroupExtended['civicrm_activity'] = array(
      'extends' => array('Activity'),
      'filters' => TRUE,
      'title' => ts('Activity'),
    );

    $this->_columns = $this->getColumns('Activity', array(
        'fields' => FALSE,
      )
    )//   + $this->getColumns('Contact', array())
    ;

    $this->_aggregateRowFields = array(
      'activity:activity_activity_type_id' => 'Activity Type',
      'activity:activity_status_id' => 'Activity Status',
      'activity:activity_result' => 'Activity Result',
      'activity:activity_subject' => 'Activity Subject',
      //      'civicrm_contact_civireport:gender_id' => 'Gender',
    );
    $this->_aggregateColumnHeaderFields = array(
      'activity:activity_activity_type_id' => 'Activity Type',
      'activity:activity_status_id' => 'Activity Status',
    );
    parent::__construct();
  }

  /**
   * Get from join clauses.
   *
   * @return array
   */
  public function fromClauses() {
    return array();
  }
}
