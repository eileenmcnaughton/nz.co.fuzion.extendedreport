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

  protected $_aclTable = 'target_civicrm_contact';

  /**
   * Class constructor.
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function __construct() {
    $this->_columns = $this->getColumns('Activity', ['fields_defaults' => ['activity_type_id', 'details', 'subject']])
      + $this->getColumns('Contact', ['prefix' => 'target_']);
    $this->_columns['civicrm_activity']['metadata']['activity_id']['required_sql'] = TRUE;
    parent::__construct();
  }

  /**
   * Generate From clause.
   */
  public function fromClauses() {
    return [
      'activity_target_from_activity',
    ];
  }
}
