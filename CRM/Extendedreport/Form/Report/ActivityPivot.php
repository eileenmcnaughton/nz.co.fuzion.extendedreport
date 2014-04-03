<?php

class CRM_Extendedreport_Form_Report_ActivityPivot extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_activity';
  protected $skipACL = FALSE;
  protected $_customGroupAggregates = TRUE;
  protected $_aggregatesIncludeNULL = TRUE;
  protected $_aggregatesAddTotal = TRUE;
  protected $_rollup = 'WITH ROLLUP';
  public $_drilldownReport = array();
  protected $_potentialCriteria = array();

  function __construct() {
    $this->_customGroupExtended['civicrm_activity'] = array(
      'extends' => array('Activity'),
      'filters' => TRUE,
      'title'  => ts('Activity'),
    );

    $this->_columns = $this->getColumns('Activity', array(
      'fields' => false,)
    )
 //   + $this->getColumns('Contact', array())
    ;
//    $this->_columns['civicrm_contact']['fields']['id']['required'] = true;
//    $this->_columns['civicrm_contact']['fields']['gender_id']['no_display'] = true;
//    $this->_columns['civicrm_contact']['fields']['gender_id']['title'] = 'Gender';

    $this->_aggregateRowFields  = array(
      'activity_civireport:activity_type_id' => 'Activity Type',
      'activity_civireport:result' => 'Activity Result',
//      'civicrm_contact_civireport:gender_id' => 'Gender',
    );
    $this->_aggregateColumnHeaderFields  = array(
//      'civicrm_contact_civireport:gender_id' => 'Gender',
    );
    parent::__construct();
  }

  function fromClauses( ) {
    return array();
  }
}
