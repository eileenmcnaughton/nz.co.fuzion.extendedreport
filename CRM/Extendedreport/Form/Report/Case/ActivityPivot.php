<?php

/**
 * Class CRM_Extendedreport_Form_Report_Case_ActivityPivot
 */
class CRM_Extendedreport_Form_Report_Case_ActivityPivot extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_baseTable = 'civicrm_activity';

  protected $_customGroupAggregates = TRUE;

  protected $_rollup = 'WITH ROLLUP';

  protected $_aggregatesAddPercentage = TRUE;

  protected $_potentialCriteria = [];

  protected $isPivot = TRUE;

  protected $_noFields = TRUE;

  /**
   * Class constructor.
   *
   * @throws \CRM_Core_Exception
   */
  public function __construct() {
    $this->_customGroupExtended['civicrm_case'] = [
      'extends' => ['Case'],
      'filters' => TRUE,
      'title' => ts('Case'),
    ];
    $this->_customGroupExtended['civicrm_activity'] = [
      'extends' => ['Activity'],
      'filters' => TRUE,
      'title' => ts('Activity'),
    ];

    $this->_columns = $this->getColumns('Activity', [
          'fields' => FALSE,
        ]
      )
      + $this->getColumns('Contact', [])
      + $this->getColumns('Case', [
          'fields' => FALSE,
        ]
      );

    $this->_columns['civicrm_contact']['fields']['gender_id']['no_display'] = TRUE;
    $this->_columns['civicrm_contact']['fields']['gender_id']['title'] = 'Gender';

    $this->_tagFilter = TRUE;
    $this->_groupFilter = TRUE;
    parent::__construct();
  }

  /**
   * @return array
   */
  public function fromClauses(): array {
    return [
      'case_from_activity',
      'contact_from_case',
    ];
  }
}
