<?php

class CRM_Extendedreport_Form_Report_Case_ActivityPivot extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_activity';
  protected $skipACL = FALSE;
  protected $_customGroupAggregates = true;
  protected $_aggregatesIncludeNULL = TRUE;
  protected $_aggregatesAddTotal = TRUE;
  protected $_rollup = 'WITH ROLLUP';
  protected $_aggregatesAddPercentage = TRUE;
  public $_drilldownReport = array();
  protected $_potentialCriteria = array(
  );

  function __construct() {
    $this->_customGroupExtended['civicrm_case'] = array(
      'extends' => array('Case'),
      'filters' => TRUE,
      'title'  => ts('Case'),
    );
    $this->_customGroupExtended['civicrm_activity'] = array(
      'extends' => array('Activity'),
      'filters' => TRUE,
      'title'  => ts('Activity'),
    );

    $this->_columns = $this->getColumns('Activity', array(
      'fields' => false,)
    )
    + $this->getColumns('Contact', array())
    + $this->getColumns('Case', array(
      'fields' => false,)
    );

    $this->_columns['civicrm_contact']['fields']['gender_id']['no_display'] = true;
    $this->_columns['civicrm_contact']['fields']['gender_id']['title'] = 'Gender';

    $this->_aggregateRowFields  = array(
      'case_civireport:id' => 'Case',
      'civicrm_contact_civireport:gender_id' => 'Gender',
    );
    $this->_aggregateColumnHeaderFields  = array(
      'civicrm_contact_civireport:gender_id' => 'Gender',
    );
    parent::__construct();
  }

  function fromClauses( ) {
    return array(
      'case_from_activity',
      'contact_from_case',
    );
  }
}
