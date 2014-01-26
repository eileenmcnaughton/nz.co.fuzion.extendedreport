<?php

class CRM_Extendedreport_Form_Report_Case_CasePivot extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_case';
  protected $skipACL = true;
  protected $_customGroupAggregates = true;
  protected $_aggregatesIncludeNULL = TRUE;
  protected $_aggregatesAddTotal = TRUE;
  protected $_rollup = 'WITH ROLLUP';
  public $_drilldownReport = array();
  protected $_potentialCriteria = array(
  );

  function __construct() {
    $this->_customGroupExtended['civicrm_case'] = array(
      'extends' => array('Case'),
      'filters' => TRUE,
      'title'  => ts('Case'),
    );

    $this->_columns = $this->getColumns('Case', array(
      'fields' => false,)
    )
    + $this->getColumns('Contact', array());
    $this->_columns['civicrm_case']['fields']['id']['required'] = true;
    $this->_columns['civicrm_contact']['fields']['id']['required'] = true;
 //  $this->_columns['civicrm_case']['fields']['id']['alter_display'] = 'alterCaseID';
    $this->_columns['civicrm_case']['fields']['id']['title'] = 'Case';
    $this->_columns['civicrm_contact']['fields']['gender_id']['no_display'] = true;
    $this->_columns['civicrm_contact']['fields']['gender_id']['title'] = 'Gender';
    $this->_columns['civicrm_contact']['fields']['gender_id']['alter_display'] = 'alterGenderID';

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
      'contact_from_case',
    );
  }
}
