<?php

/**
 * Class CRM_Extendedreport_Form_Report_Case_CasePivot
 */
class CRM_Extendedreport_Form_Report_Case_CasePivot extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_baseTable = 'civicrm_case';

  protected $_skipACLContactDeletedClause = TRUE;

  protected $_customGroupAggregates = TRUE;

  protected $_aggregatesIncludeNULL = TRUE;

  protected $_aggregatesAddTotal = TRUE;

  protected $_aggregatesAddPercentage = TRUE;

  protected $_rollup = 'WITH ROLLUP';

  public $_drilldownReport = [];

  protected $_potentialCriteria = [];

  protected $isPivot = TRUE;

  protected $_noFields = TRUE;

  protected $_customGroupExtends = ['Case', 'Contact'];

  /**
   * Class constructor.
   */
  public function __construct() {

    $this->_columns = $this->getColumns('Case', [
          'fields' => FALSE,
        ]
      ) + $this->getColumns('Contact', ['fields' => FALSE]);

    // $this->_columns['civicrm_case']['fields']['id']['alter_display'] = 'alterCaseID';
    $this->_columns['civicrm_case']['fields']['case_civireport_id']['title'] = 'Case';
    $this->_columns['civicrm_case']['fields']['case_civireport_id']['required'] = TRUE;
    $this->_columns['civicrm_case']['fields']['case_civireport_status_id']['options'] = CRM_Case_BAO_Case::buildOptions('status_id');
    $this->_columns['civicrm_case']['fields']['case_civireport_status_id']['no_display'] = TRUE;
    $this->_columns['civicrm_case']['filters']['case_civireport_is_deleted']['default'] = 0;

    $this->_columns['civicrm_case']['metadata']['case_civireport_id'] = array_merge(
      ['is_fields' => FALSE, 'is_filters' => FALSE, 'is_group_bys' => FALSE, 'is_order_bys' => FALSE, 'is_join_filters' => FALSE, 'is_aggregate_columns' => FALSE],
      $this->_columns['civicrm_case']['fields']['case_civireport_id']
    );
    $this->_columns['civicrm_case']['metadata']['case_civireport_status_id'] = array_merge(
      [
        'is_fields' => FALSE,
        'is_filters' => FALSE,
        'is_group_bys' => FALSE,
        'is_order_bys' => FALSE,
        'is_join_filters' => FALSE,
        'is_aggregate_columns' => TRUE,
      ],
      $this->_columns['civicrm_case']['fields']['case_civireport_status_id']
    );
    $this->_columns['civicrm_case']['metadata']['case_civireport_is_deleted'] = array_merge(
      [
        'is_fields' => FALSE,
        'is_filters' => FALSE,
        'is_group_bys' => FALSE,
        'is_order_bys' => FALSE,
        'is_join_filters' => FALSE,
        'is_aggregate_columns' => FALSE,
      ],
      $this->_columns['civicrm_case']['filters']['case_civireport_is_deleted']
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
  public function fromClauses(): array {
    return [
      'contact_from_case',
    ];
  }
}
