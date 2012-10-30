<?php

class CRM_Extendedreport_Form_Report_ActivityExtended extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_customGroupExtended = array(
      'contact_activity' => array(
          'extends' => array('Activity'),
          'title'  => 'Activity',
      ),
      'civicrm_contact' => array(
          'extends' => array('Individual', 'Contact'),
          'title'  => 'Source Contact',
      ),
      'target_civicrm_contact' => array(
          'extends' => array('Individual', 'Contact', 'Organization'),
          'title'  => 'Target Contact',
      ),
  );
  protected $_addressField = FALSE;
  protected $_emailField = FALSE;
  protected $_summary = NULL;
  protected $_exposeContactID = FALSE;
  protected $_customGroupGroupBy = FALSE;
  protected $_baseTable = 'civicrm_activity';

  function __construct() {
    $this->_columns = $this->getContactColumns()
    + $this->getContactColumns(array('prefix' => '', 'prefix_label' => 'Source Contact '))
    + $this->getContactColumns(array('prefix' => 'target_', 'prefix_label' => 'Target Contact '))
    + $this->getActivityColumns();
    parent::__construct();
  }

/*
 * Should remove all this to parent class
 */
  function from() {

    $this->_from = "
    FROM civicrm_activity {$this->_aliases['civicrm_activity']}

   LEFT JOIN civicrm_activity_target
   ON {$this->_aliases['civicrm_activity']}.id = civicrm_activity_target.activity_id

   LEFT JOIN civicrm_activity_assignment
   ON {$this->_aliases['civicrm_activity']}.id = civicrm_activity_assignment.activity_id

   LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
   ON {$this->_aliases['civicrm_activity']}.source_contact_id = {$this->_aliases['civicrm_contact']}.id

   LEFT JOIN civicrm_contact {$this->_aliases['target_civicrm_contact']}
   ON civicrm_activity_target.target_contact_id = {$this->_aliases['target_civicrm_contact']}.id

   LEFT JOIN civicrm_contact civicrm_contact_assignee
   ON civicrm_activity_assignment.assignee_contact_id = civicrm_contact_assignee.id

   {$this->_aclFrom}

   LEFT JOIN civicrm_case_activity case_activity_civireport
   ON case_activity_civireport.activity_id = {$this->_aliases['civicrm_activity']}.id
   LEFT JOIN civicrm_case
   ON case_activity_civireport.case_id = civicrm_case.id ";

   if ($this->isTableSelected('civicrm_email')) {
   $this->_from .= "
   LEFT JOIN civicrm_email civicrm_email_source
   ON {$this->_aliases['civicrm_activity']}.source_contact_id = civicrm_email_source.contact_id AND
   civicrm_email_source.is_primary = 1

   LEFT JOIN civicrm_email civicrm_email_target
                         ON {$this->_aliases['civicrm_activity_target']}.target_contact_id = civicrm_email_target.contact_id AND
                         civicrm_email_target.is_primary = 1

                         LEFT JOIN civicrm_email civicrm_email_assignee
                         ON {$this->_aliases['civicrm_activity_assignment']}.assignee_contact_id = civicrm_email_assignee.contact_id AND
                         civicrm_email_assignee.is_primary = 1 ";
    }
    $this->addAddressFromClause();
        $this->selectableCustomDataFrom();
  }

  function postProcess() {

    $this->beginPostProcess();

    // get the acl clauses built before we assemble the query
    $this->buildACLClause($this->_aliases['civicrm_contact']);
    $sql = $this->buildQuery(TRUE);
    $rows = array();
    $this->buildRows($sql, $rows);

    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }
}
