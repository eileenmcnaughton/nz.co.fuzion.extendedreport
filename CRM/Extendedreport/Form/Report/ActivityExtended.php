<?php

require_once 'CRM/Report/Form.php';

class CRM_Extendedreport_Form_Report_ActivityExtended extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_customGroupExtends = array('Activity');
  protected $_customGroupExtendsJQ = array(
      'contact_activity' => array(
          'extends' => array('Activity'),
          'title'  => 'Activity',
      ),
      'civicrm_contact' => array(
          'extends' => array('Individual', 'Contact'),
          'title'  => 'Source Contact',
      ),
      'target_civicrm_contact' => array(
          'extends' => array('Individual', 'Contact'),
          'title'  => 'Target Contact',
      ),
  );
  protected $_addressField = FALSE;

  protected $_emailField = FALSE;

  protected $_summary = NULL;
  protected $_exposeContactID = FALSE;
  protected $_customGroupGroupBy = FALSE; function __construct() {
    $this->_columns = $this->getContactColumns()
    + $this->getActivityColumns()
    + $this->getContactColumns(array('prefix' => '', 'prefix_label' => 'Source Contact '))
    + $this->getContactColumns(array('prefix' => 'target_', 'prefix_label' => 'Target Contact '));
    parent::__construct();
  }

  function preProcess() {
    $this->assign('reportTitle', ts('Membership Detail Report'));
    parent::preProcess();
  }

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
                 LEFT JOIN civicrm_option_value
                 ON ( {$this->_aliases['civicrm_activity']}.activity_type_id = civicrm_option_value.value )
                     LEFT JOIN civicrm_option_group
                     ON civicrm_option_group.id = civicrm_option_value.option_group_id
                     LEFT JOIN civicrm_case_activity case_activity_civireport
                     ON case_activity_civireport.activity_id = {$this->_aliases['civicrm_activity']}.id
                     LEFT JOIN civicrm_case
                         ON case_activity_civireport.case_id = civicrm_case.id
                         LEFT JOIN civicrm_case_contact
                         ON civicrm_case_contact.case_id = civicrm_case.id ";

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

  function alterDisplay(&$rows) {
    // custom code to alter rows
    $entryFound = FALSE;
    $checkList = array();
    foreach ($rows as $rowNum => $row) {

      if (!empty($this->_noRepeats) && $this->_outputMode != 'csv') {
        // not repeat contact display names if it matches with the one
        // in previous row
        $repeatFound = FALSE;
        foreach ($row as $colName => $colVal) {
          if (CRM_Utils_Array::value($colName, $checkList) &&
            is_array($checkList[$colName]) &&
            in_array($colVal, $checkList[$colName])
          ) {
            $rows[$rowNum][$colName] = "";
            $repeatFound = TRUE;
          }
          if (in_array($colName, $this->_noRepeats)) {
            $checkList[$colName][] = $colVal;
          }
        }
      }

      if (array_key_exists('civicrm_membership_membership_type_id', $row)) {
        if ($value = $row['civicrm_membership_membership_type_id']) {
          $rows[$rowNum]['civicrm_membership_membership_type_id'] = CRM_Member_PseudoConstant::membershipType($value, FALSE);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_address_state_province_id', $row)) {
        if ($value = $row['civicrm_address_state_province_id']) {
          $rows[$rowNum]['civicrm_address_state_province_id'] = CRM_Core_PseudoConstant::stateProvince($value, FALSE);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_address_country_id', $row)) {
        if ($value = $row['civicrm_address_country_id']) {
          $rows[$rowNum]['civicrm_address_country_id'] = CRM_Core_PseudoConstant::country($value, FALSE);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_contact_sort_name', $row) &&
        $rows[$rowNum]['civicrm_contact_sort_name'] &&
        array_key_exists('civicrm_contact_id', $row)
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view",
          'reset=1&cid=' . $row['civicrm_contact_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_contact_sort_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_sort_name_hover'] = ts("View Contact Summary for this Contact.");
        $entryFound = TRUE;
      }

      if (!$entryFound) {
        break;
      }
    }
  }
}
