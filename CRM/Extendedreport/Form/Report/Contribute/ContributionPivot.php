<?php

/**
 * Class CRM_Extendedreport_Form_Report_ContributionPivot
 */
class CRM_Extendedreport_Form_Report_Contribute_ContributionPivot extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_contribution';
  protected $skipACL = FALSE;
  protected $_customGroupAggregates = TRUE;
  protected $_aggregatesIncludeNULL = TRUE;
  protected $_aggregatesAddTotal = TRUE;
  protected $_rollup = 'WITH ROLLUP';
  public $_drilldownReport = array();
  protected $isPivot = TRUE;
  protected $_potentialCriteria = array();
  protected $_noFields = TRUE;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_customGroupExtended['civicrm_contribution'] = array(
      'extends' => array('Contribution'),
      'filters' => TRUE,
      'title' => ts('Contribution'),
    );

    $this->_columns = $this->getColumns('Contribution', array(
        'fields' => FALSE,
    )) +
    $this->_columns = $this->getColumns('Contact', array(
      'fields' => FALSE,
    )) + $this->_columns = $this->getColumns('Address', array(
       'fields' => FALSE,
    ));

    $this->_aggregateRowFields = array(
      'contribution:contribution_campaign_id' => 'Campaign',
      'address:address_county_id' => 'County',
      'address:address_city' => 'City',
      'contribution:contribution_financial_type_id' => 'Financial Type',
    );
    $this->_aggregateColumnHeaderFields = array(
      'contribution:contribution_financial_type_id' => 'Financial Type',
      'contribution:contribution_campaign_id' => 'Campaign',
    );
    parent::__construct();
  }

  /**
   * Get sql FROM clauses.
   *
   * @return array
   */
  public function fromClauses() {
    return array(
      'contact_from_contribution',
      'address_from_contact',
    );
  }
}
