<?php

/**
 * Class CRM_Extendedreport_Form_Report_Case_ActivityPivot
 */
class CRM_Extendedreport_Form_Report_Case_ActivityPivot extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_activity';
  protected $skipACL = FALSE;
  protected $_customGroupAggregates = TRUE;
  protected $_aggregatesIncludeNULL = TRUE;
  protected $_aggregatesAddTotal = TRUE;
  protected $_rollup = 'WITH ROLLUP';
  protected $_aggregatesAddPercentage = TRUE;
  public $_drilldownReport = array();
  protected $_potentialCriteria = array();
  protected $isPivot = TRUE;
  protected $_noFields = TRUE;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_customGroupExtended['civicrm_case'] = array(
      'extends' => array('Case'),
      'filters' => TRUE,
      'title' => ts('Case'),
    );
    $this->_customGroupExtended['civicrm_activity'] = array(
      'extends' => array('Activity'),
      'filters' => TRUE,
      'title' => ts('Activity'),
    );

    $this->_columns = $this->getColumns('Activity', array(
          'fields' => FALSE,
        )
      )
      + $this->getColumns('Contact', array())
      + $this->getColumns('Case', array(
          'fields' => FALSE,
        )
      );

    $this->_columns['civicrm_contact']['fields']['gender_id']['no_display'] = TRUE;
    $this->_columns['civicrm_contact']['fields']['gender_id']['title'] = 'Gender';

    $this->_aggregateRowFields = array(
      'case_civireport:id' => 'Case',
      'civicrm_contact_civireport:gender_id' => 'Gender',
    );
    $this->_aggregateColumnHeaderFields = array(
      'civicrm_contact_civireport:gender_id' => 'Gender',
    );
    $this->_tagFilter = TRUE;
    $this->_groupFilter = TRUE;
    parent::__construct();
  }

  /**
   * @return array
   */
  function fromClauses() {
    return array(
      'case_from_activity',
      'contact_from_case',
    );
  }
}
