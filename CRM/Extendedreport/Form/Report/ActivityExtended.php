<?php

/**
 * Class CRM_Extendedreport_Form_Report_ActivityExtended
 */
class CRM_Extendedreport_Form_Report_ActivityExtended extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_customGroupExtends = ['Activity', 'Contact', 'Individual', 'Household', 'Organization'];

  protected $_editableFields = FALSE;

  /**
   * @var string
   */
  protected $_baseTable = 'civicrm_activity';

  /**
   * Class constructor.
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function __construct() {
    $this->_columns = $this->getColumns(
        'Contact',
        [
          'prefix' => '',
          'prefix_label' => 'Source Contact ::',
          'filters' => TRUE,
        ]
      ) + $this->getColumns(
        'Email',
        [
          'prefix' => '',
          'prefix_label' => 'Source Contact Email ::',
          'filters' => TRUE,
        ]
      ) + $this->getColumns(        
        'Contact',
        [
          'prefix' => 'target_',
          'group_by' => TRUE,
          'prefix_label' => 'Target Contact ::',
          'filters' => TRUE,
        ]
      ) + $this->getColumns(
        'Email',
        [
          'prefix' => 'target_',
          'prefix_label' => 'Target Contact Email ::',
          'filters' => TRUE,
        ]
      ) + $this->getColumns(
        'Contact', [
          'prefix' => 'assignee_',
          'prefix_label' => 'Assignee Contact ::',
          'filters' => TRUE,
        ]
      ) + $this->getColumns(
        'Email',
        [
          'prefix' => 'assignee_',
          'prefix_label' => 'Assignee Contact Email ::',
          'filters' => TRUE,
        ]
      ) + $this->getColumns('Activity', ['group_by' => TRUE]);
    parent::__construct();
  }

  /**
   * Generate From clause.
   *
   * @todo Should remove all this to parent class
   */
  public function from() {
    $this->_from = "
    FROM civicrm_activity {$this->_aliases['civicrm_activity']}";
    $this->joinActivityTargetFromActivity();
    $this->joinActivityAssigneeFromActivity();
    $this->joinActivitySourceFromActivity();
    $this->_from .= " {$this->_aclFrom} ";
    if ($this->isTableSelected('civicrm_case')) {
      $this->_from .= "
       LEFT JOIN civicrm_case_activity case_activity_civireport
         ON case_activity_civireport.activity_id = {$this->_aliases['civicrm_activity']}.id
       LEFT JOIN civicrm_case
         ON case_activity_civireport.case_id = civicrm_case.id ";
    }
    
    
    $this->joinEmailFromContact();
    $this->joinEmailFromContact('target_');
    $this->joinEmailFromContact('assignee_');
  }

}
