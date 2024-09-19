<?php

/**
 * Class CRM_Extendedreport_Form_Report_ActivityPivot
 */
class CRM_Extendedreport_Form_Report_ActivityPivot extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_baseTable = 'civicrm_activity';

  protected $_customGroupExtends = ['Activity'];

  protected $isPivot = TRUE;

  protected $_customGroupAggregates = TRUE;

  protected $_rollup = 'WITH ROLLUP';

  protected $_potentialCriteria = [];

  protected $_noFields = TRUE;

  /**
   * Class constructor.
   *
   * @throws \CRM_Core_Exception
   */
  public function __construct() {
    $this->_customGroupExtended['civicrm_activity'] = [
      'extends' => ['Activity'],
      'filters' => TRUE,
      'title' => ts('Activity'),
    ];

    $this->_columns = $this->getColumns('Activity', [
        'fields' => FALSE,
      ]
    );
    parent::__construct();
  }

}
