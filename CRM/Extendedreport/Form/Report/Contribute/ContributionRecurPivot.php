<?php

/**
 * Class CRM_Extendedreport_Form_Report_ContributionPivot
 */
class CRM_Extendedreport_Form_Report_Contribute_ContributionRecurPivot extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_baseTable = 'civicrm_contribution_recur';

  protected $_customGroupAggregates = TRUE;

  protected $_rollup = 'WITH ROLLUP';

  protected $isPivot = TRUE;

  protected $_potentialCriteria = [];

  protected $_noFields = TRUE;

  protected $_customGroupExtends = ['ContributionRecur', 'Contact', 'Individual', 'Household', 'Organization'];

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_columns = $this->getColumns('ContributionRecur', [
        'fields' => FALSE,
      ])
      + $this->getColumns('Contact', [
        'fields' => FALSE,
      ])
      + $this->getColumns('Address', [
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
  public function fromClauses(): array {
    return [
      'contact_from_contribution_recur',
      'address_from_contact',
    ];
  }
}
