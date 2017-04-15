<?php

/**
 * Class CRM_Extendedreport_Form_Report_Case_CasePivot
 */
class CRM_Extendedreport_Form_Report_Case_CasePivot extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_case';
  protected $skipACL = FALSE;
  protected $_skipACLContactDeletedClause = TRUE;
  protected $_customGroupAggregates = TRUE;
  protected $_aggregatesIncludeNULL = TRUE;
  protected $_aggregatesAddTotal = TRUE;
  protected $_aggregatesAddPercentage = TRUE;
  protected $_rollup = 'WITH ROLLUP';
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

    $this->_columns = $this->getColumns('Case', array(
          'fields' => FALSE,
        )
      ) + $this->getColumns('Contact', array('fields' => FALSE));

    // $this->_columns['civicrm_case']['fields']['id']['alter_display'] = 'alterCaseID';
    $this->_columns['civicrm_case']['fields']['case_civireport_id']['title'] = 'Case';
    $this->_columns['civicrm_case']['fields']['case_civireport_id']['required'] = TRUE;
    $this->_columns['civicrm_case']['fields']['case_civireport_status_id']['options'] = CRM_Case_BAO_Case::buildOptions('status_id');
    $this->_columns['civicrm_case']['fields']['case_civireport_status_id']['no_display'] = TRUE;
    $this->_columns['civicrm_case']['filters']['case_civireport_is_deleted']['default'] = 0;

    $this->_aggregateRowFields = array(
      'case_civireport:id' => 'Case',
      'case_civireport:status_id' => 'Case Status',
      'civicrm_contact:gender_id' => 'Gender',
    );
    $this->_aggregateColumnHeaderFields = array(
      'civicrm_contact:gender_id' => 'Gender',
      'case_civireport:status_id' => 'Case Status',
    );
    $this->_tagFilter = TRUE;
    $this->_groupFilter = TRUE;
    parent::__construct();
  }

  /**
   * Declare from joins.
   *
   * @return array
   */
  public function fromClauses() {
    return array(
      'contact_from_case',
    );
  }
}
