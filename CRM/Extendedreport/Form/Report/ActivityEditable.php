<?php

/**
 * Class CRM_Extendedreport_Form_Report_ActivityExtended
 */
class CRM_Extendedreport_Form_Report_ActivityEditable extends CRM_Extendedreport_Form_Report_ExtendedReport {

  /**
   * @var array
   */
  protected $_customGroupExtends = ['Activity'];

  /**
   * @var string
   */
  protected $_baseTable = 'civicrm_activity';

  /**
   * Class constructor.
   *
   * @throws \CRM_Core_Exception
   */
  public function __construct() {
    $this->_columns = $this->getColumns('Activity', ['fields_defaults' => ['activity_type_id', 'details', 'subject']])
      + $this->getColumns('Contact', ['prefix' => 'target_', 'prefix_label' => 'Target Contact ::', 'filters' => TRUE])
      + $this->getColumns('Contact', ['prefix' => 'assignee_', 'prefix_label' => 'Assignee Contact ::', 'filters' => TRUE]);
    $this->_columns['civicrm_activity']['metadata']['activity_id']['required_sql'] = TRUE;
    parent::__construct();
  }

  /**
   * Generate from clause.
   *
   * @return array
   */
  public function fromClauses(): array {
    return [
      'activity_target_from_activity',
      'activity_assignee_from_activity',
    ];
  }
}
