<?php

/**
 * Class CRM_Extendedreport_Form_Report_ContributionPivot
 */
class CRM_Extendedreport_Form_Report_Contribute_ContributionPivot extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_baseTable = 'civicrm_contribution_recur';

  protected $skipACL = FALSE;

  protected $_customGroupAggregates = TRUE;

  protected $_aggregatesIncludeNULL = TRUE;

  protected $_aggregatesAddTotal = TRUE;

  protected $_rollup = 'WITH ROLLUP';

  public $_drilldownReport = [];

  protected $isPivot = TRUE;

  protected $_potentialCriteria = [];

  protected $_noFields = TRUE;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_columns = $this->getColumns('ContributionRecur', [
        'fields' => FALSE,
      ])
    + $this->_columns = $this->getColumns('Contact', [
        'fields' => FALSE,
    ])
    + $this->_columns = $this->getColumns('Address', [
        'fields' => FALSE,
        'aggregate_rows' => TRUE,
      ]);
    parent::__construct();
  }

  /**
   * Get sql FROM clauses.
   *
   * @return array
   */
  public function fromClauses() {
    return [
      'contact_from_contribution_recur',
      'address_from_contact',
    ];
  }
}
