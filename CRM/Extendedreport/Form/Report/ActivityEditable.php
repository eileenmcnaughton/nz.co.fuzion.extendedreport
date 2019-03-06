<?php

/**
 * Class CRM_Extendedreport_Form_Report_ActivityExtended
 */
class CRM_Extendedreport_Form_Report_ActivityEditable extends CRM_Extendedreport_Form_Report_ExtendedReport {
  /**
   * @var array
   */
  protected $_customGroupExtends = array('Activity');
  /**
   * @var bool
   */
  protected $_customGroupGroupBy = FALSE;
  /**
   * @var string
   */
  protected $_baseTable = 'civicrm_activity';

  protected $skipACL = FALSE;

  protected $_aclTable = 'target_civicrm_contact';

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_columns = $this->getColumns('Activity', ['fields_defaults' => ['activity_type_id', 'details', 'subject']])
      + $this->getColumns('Contact', ['prefix' => 'target_']);
    $this->_columns['civicrm_activity']['metadata']['activity_id']['required'] = TRUE;
    $this->_columns['civicrm_activity']['metadata']['activity_id']['no_display'] = TRUE;
    parent::__construct();
  }

  /**
   * Generate From clause.
   */
  function fromClauses() {
    return array(
      'activity_target_from_activity'
    );
  }
}
