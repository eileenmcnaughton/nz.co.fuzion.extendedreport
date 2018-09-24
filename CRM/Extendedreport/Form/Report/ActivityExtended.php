<?php

/**
 * Class CRM_Extendedreport_Form_Report_ActivityExtended
 */
class CRM_Extendedreport_Form_Report_ActivityExtended extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_customGroupExtends = ['Activity', 'Contact', 'Individual', 'Household', 'Organization'];

  protected $_editableFields = FALSE;

  /**
   * Can this report be used on a contact tab.
   *
   * The report must support contact_id in the url for this to work.
   *
   * @var bool
   */
  protected $isSupportsContactTab = TRUE;

  /**
   * @var bool
   */
  protected $_exposeContactID = FALSE;

  /**
   * @var string
   */
  protected $_baseTable = 'civicrm_activity';

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_columns = $this->getColumns(
      'Contact',
      array(
        'prefix' => '',
        'prefix_label' => 'Source Contact ::',
        'filters' => TRUE,
      )
    ) + $this->getColumns(
        'Contact',
        array(
          'prefix' => 'target_',
          'group_by' => TRUE,
          'prefix_label' => 'Target Contact ::',
          'filters' => TRUE,
        )
    ) + $this->getColumns(
        'Contact', array(
          'prefix' => 'assignee_',
          'prefix_label' => 'Assignee Contact ::',
          'filters' => TRUE,
        )
    ) + $this->getColumns('Activity', array('group_by' => TRUE));
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

    if ($this->isTableSelected('civicrm_email')) {
      $this->_from .= "
       LEFT JOIN civicrm_email civicrm_email_source
         ON {$this->_aliases['civicrm_activity']}.source_contact_id = civicrm_email_source.contact_id
         AND civicrm_email_source.is_primary = 1
         AND civicrm_email_source.is_deleted = 0

       LEFT JOIN civicrm_email civicrm_email_target
         ON {$this->_aliases['civicrm_activity_target']}.target_contact_id = civicrm_email_target.contact_id
         AND civicrm_email_target.is_primary = 1
         AND civicrm_email_target.is_deleted = 0

       LEFT JOIN civicrm_email civicrm_email_assignee
        ON {$this->_aliases['civicrm_activity_assignment']}.assignee_contact_id = civicrm_email_assignee.contact_id
        AND civicrm_email_assignee.is_primary = 1
        AND civicrm_email_assignee.is_deleted = 0
        ";
    }
  }

  /**
   *
   */
  function postProcess() {
    // get the acl clauses built before we assemble the query
    //@todo - find out why the parent doesn't do this - or if it now does
    $this->buildACLClause($this->_aliases['civicrm_contact']);
    parent::postProcess();
  }
}
