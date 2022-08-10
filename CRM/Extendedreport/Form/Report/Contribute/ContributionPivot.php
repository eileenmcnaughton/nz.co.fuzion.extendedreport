<?php

/**
 * Class CRM_Extendedreport_Form_Report_ContributionPivot
 */
class CRM_Extendedreport_Form_Report_Contribute_ContributionPivot extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_baseTable = 'civicrm_contribution';

  protected $_customGroupAggregates = TRUE;

  protected $_aggregatesIncludeNULL = TRUE;

  protected $_aggregatesAddTotal = TRUE;

  protected $_rollup = 'WITH ROLLUP';

  public $_drilldownReport = [];

  protected $isPivot = TRUE;

  protected $_potentialCriteria = [];

  protected $_noFields = TRUE;

  protected $_customGroupExtends = ['Contribution', 'Contact', 'Individual', 'Household', 'Organization'];

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_customGroupExtended['civicrm_contribution'] = [
      'extends' => ['Contribution'],
      'filters' => TRUE,
      'title' => ts('Contribution'),
    ];

    $this->_columns = $this->getColumns('Contribution', [
        'fields' => FALSE,
      ]) +
      $this->_columns = $this->getColumns('Contact', [
          'fields' => FALSE,
        ]) + $this->_columns = $this->getColumns('Address', [
          'fields' => FALSE,
          'aggregate_rows' => TRUE,
        ]);
    // Ensure that a grand total result shows even if rows returned are more than 50 as this report doesn't do paging.
    $this->setAddPaging(FALSE);
    parent::__construct();
  }

  /**
   * Get sql FROM clauses.
   *
   * @return array
   */
  public function fromClauses(): array {
    return [
      'contact_from_contribution',
      'address_from_contact',
    ];
  }
}
