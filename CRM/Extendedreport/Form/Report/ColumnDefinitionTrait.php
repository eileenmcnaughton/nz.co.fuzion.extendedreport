<?php
/**
 * Created by IntelliJ IDEA.
 * User: emcnaughton
 * Date: 9/1/18
 * Time: 1:05 AM
 */

/**
 * Trait CRM_Extendedreport_Form_Report_ColumnDefinitionTrait
 *
 * This trait serves to organise the long getColumns functions into one function.
 *
 * It is for code organisation & may or may not make the most sense long term.
 */
trait CRM_Extendedreport_Form_Report_ColumnDefinitionTrait {
  /*
 * Function to get Activity Columns
 * @param array $options column options
 */
  /**
   * @param array $options
   *
   * @return array
   */
  function getActivityColumns($options = array()) {
    $defaultOptions = array(
      'prefix' => '',
      'prefix_label' => '',
      'fields' => TRUE,
      'group_by' => FALSE,
      'order_by' => TRUE,
      'filters' => TRUE,
      'fields_defaults' => array(),
      'filters_defaults' => array(),
      'group_bys_defaults' => array(),
      'order_by_defaults' => array(),
    );

    $options = array_merge($defaultOptions, $options);
    $defaults = $this->getDefaultsFromOptions($options);

    $spec = array(
      'id' => array(
        'no_display' => TRUE,
        'required' => TRUE,
        'is_group_bys' => $options['group_by'],
      ),
      'source_record_id' => array(
        'no_display' => TRUE,
        'required' => FALSE,
      ),
      'activity_type_id' => array(
        'title' => ts('Activity Type'),
        'alter_display' => 'alterActivityType',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_PseudoConstant::activityType(TRUE, TRUE),
        'name' => 'activity_type_id',
        'type' => CRM_Utils_Type::T_INT,
      ),
      'subject' => array(
        'title' => ts('Subject'),
        'name' => 'subject',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'crm_editable' => array(
          'id_table' => 'civicrm_activity',
          'id_field' => 'id',
          'entity' => 'activity',
        ),

      ),
      'source_contact_id' => array(
        'no_display' => TRUE,
        'required' => FALSE,
        'is_fields' => TRUE,
      ),
      'activity_date_time' => array(
        'title' => ts('Activity Date'),
        'default' => TRUE,
        'name' => 'activity_date_time',
        'operatorType' => CRM_Report_Form::OP_DATE,
        'type' => CRM_Utils_Type::T_DATE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ),
      'status_id' => array(
        'title' => ts('Activity Status'),
        'name' => 'status_id',
        'type' => CRM_Utils_Type::T_STRING,
        'alter_display' => 'alterActivityStatus',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_PseudoConstant::activityStatus(),
        'crm_editable' => array(
          'id_table' => 'civicrm_activity',
          'id_field' => 'id',
          'entity' => 'activity',
          'options' => $this->_getOptions('activity', 'activity_status_id'),
        ),
      ),
      'duration' => array(
        'title' => ts('Duration (sum for all contacts)'),
        'type' => CRM_Utils_Type::T_INT,
        'statistics' => array(
          'sum' => ts('Total Duration')
        ),
        'is_fields' => TRUE,
      ),
      'duration_each' => array(
        'title' => ts('Duration (for each contact)'),
        'name' => 'duration',
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
      ),
      'details' => array(
        'title' => ts('Activity Details'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'type' => CRM_Utils_Type::T_TEXT,
        'crm_editable' => array(
          'id_table' => 'civicrm_activity',
          'id_field' => 'id',
          'entity' => 'activity',
        ),

      ),
      'result' => array(
        'title' => ts('Activity Result'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'type' => CRM_Utils_Type::T_TEXT,
        'crm_editable' => array(
          'id_table' => 'civicrm_activity',
          'id_field' => 'id',
          'entity' => 'activity',
        ),
      ),
      'is_current_revision' => array(
        'type' => CRM_Report_Form::OP_INT,
        'operatorType' => CRM_Report_Form::OP_SELECT,
        'title' => ts("Current Revision"),
        'name' => 'is_current_revision',
        'options' => array('1' => 'Yes', '0' => 'No',),
        'is_filters' => TRUE,
      ),
      'is_deleted' => array(
        'type' => CRM_Report_Form::OP_INT,
        'operatorType' => CRM_Report_Form::OP_SELECT,
        'title' => ts("Is activity deleted"),
        'name' => 'is_deleted',
        'options' => array('0' => 'No', '1' => 'Yes',),
        'is_filters' => TRUE,
      ),

    );
    return $this->buildColumns($spec, $options['prefix'] . 'civicrm_activity', 'CRM_Activity_DAO_Activity', NULL, $defaults, $options);
  }

  /**
   * Get columns for Case.
   *
   * @param $options
   *
   * @return array
   */
  function getCaseColumns($options) {
    $config = CRM_Core_Config::singleton();
    if (!in_array('CiviCase', $config->enableComponents)) {
      return array('civicrm_case' => array('fields' => array(), 'metadata' => array()));
    }

    $spec = array(
      'civicrm_case' => array(
        'fields' => array(
          'id' => array(
            'title' => ts('Case ID'),
            'name' => 'id',
            'is_fields' => TRUE,
          ),
          'subject' => array(
            'title' => ts('Case Subject'),
            'default' => TRUE,
            'is_fields' => TRUE,
            'is_filters' => TRUE,
          ),
          'status_id' => array(
            'title' => ts('Case Status'),
            'default' => TRUE,
            'name' => 'status_id',
            'is_fields' => TRUE,
            'is_filters' => TRUE,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Case_BAO_Case::buildOptions('case_status_id'),
            'type' => CRM_Utils_Type::T_INT,
          ),
          'case_type_id' => array(
            'title' => ts('Case Type'),
            'is_fields' => TRUE,
            'is_filters' => TRUE,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Case_BAO_Case::buildOptions('case_type_id'),
            'name' => 'case_type_id',
            'type' => CRM_Utils_Type::T_INT,
          ),
          'start_date' => array(
            'title' => ts('Case Start Date'),
            'name' => 'start_date',
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
            'is_fields' => TRUE,
            'is_filters' => TRUE,
          ),
          'end_date' => array(
            'title' => ts('Case End Date'),
            'name' => 'end_date',
            'is_fields' => TRUE,
            'is_filters' => TRUE,
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
          ),
          'duration' => array(
            'name' => 'duration',
            'title' => ts('Duration (Days)'),
            'is_fields' => TRUE,
            'is_filters' => TRUE,
            'type' => CRM_Utils_Type::T_INT,
          ),
          'is_deleted' => array(
            'name' => 'is_deleted',
            'title' => ts('Case Deleted?'),
            'type' => CRM_Utils_Type::T_BOOLEAN,
            'is_fields' => TRUE,
            'is_filters' => TRUE,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'options' => array('' => '--select--') + CRM_Case_BAO_Case::buildOptions('is_deleted'),
          )
        )
      ),

    );
    // Case is a special word in mysql so pass an alias to prevent it from using case.
    return $this->buildColumns($spec['civicrm_case']['fields'], $options['prefix'] . 'civicrm_case', 'CRM_Case_DAO_Case', 'case_civireport');
  }

  /**
   * @param array $options
   *
   * @return array
   */
  function getContactColumns($options = array()) {
    $defaultOptions = array(
      'prefix' => '',
      'prefix_label' => '',
      'group_by' => TRUE,
      'order_by' => TRUE,
      'filters' => TRUE,
      'fields' => TRUE,
      'custom_fields' => array('Individual', 'Contact', 'Organization'),
      'fields_defaults' => array('display_name', 'id'),
      'filters_defaults' => array(),
      'group_bys_defaults' => array(),
      'order_by_defaults' => array('sort_name ASC'),
      'contact_type' => NULL,
    );

    $options = array_merge($defaultOptions, $options);
    $orgOnly = FALSE;
    if (CRM_Utils_Array::value('contact_type', $options) == 'Organization') {
      $orgOnly = TRUE;
    }
    $tableAlias = $options['prefix'] . 'civicrm_contact';

    $spec = array(
      $options['prefix'] . 'display_name' => array(
        'name' => 'display_name',
        'title' => ts($options['prefix_label'] . 'Contact Name'),
        'is_fields' => TRUE,
      ),
      $options['prefix'] . 'contact_id' => array(
        'name' => 'id',
        'title' => ts($options['prefix_label'] . 'Contact ID'),
        'alter_display' => 'alterContactID',
        'type' => CRM_Utils_Type::T_INT,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_contact_filter' => TRUE,
      ),
      $options['prefix'] . 'external_identifier' => array(
        'name' => 'external_identifier',
        'title' => ts($options['prefix_label'] . 'External ID'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
      ),
      $options['prefix'] . 'sort_name' => array(
        'name' => 'sort_name',
        'title' => ts($options['prefix_label'] . 'Contact Name (in sort format)'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ),
      $options['prefix'] . 'contact_type' => array(
        'title' => ts($options['prefix_label'] . 'Contact Type'),
        'name' => 'contact_type',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contact_BAO_Contact::buildOptions('contact_type'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
      ),
      $options['prefix'] . 'contact_sub_type' => array(
        'title' => ts($options['prefix_label'] . 'Contact Sub Type'),
        'name' => 'contact_sub_type',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contact_BAO_Contact::buildOptions('contact_sub_type'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
      ),
    );
    $individualFields = array(
      $options['prefix'] . 'first_name' => array(
        'name' => 'first_name',
        'title' => ts($options['prefix_label'] . 'First Name'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ),
      $options['prefix'] . 'middle_name' => array(
        'name' => 'middle_name',
        'title' => ts($options['prefix_label'] . 'Middle Name'),
        'is_fields' => TRUE,
      ),
      $options['prefix'] . 'last_name' => array(
        'name' => 'last_name',
        'title' => ts($options['prefix_label'] . 'Last Name'),
        'default_order' => 'ASC',
        'is_fields' => TRUE,
      ),
      $options['prefix'] . 'nick_name' => array(
        'name' => 'nick_name',
        'title' => ts($options['prefix_label'] . 'Nick Name'),
        'is_fields' => TRUE,
      ),
      $options['prefix'] . 'gender_id' => array(
        'name' => 'gender_id',
        'title' => ts($options['prefix_label'] . 'Gender'),
        'options' => CRM_Contact_BAO_Contact::buildOptions('gender_id'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'alter_display' => 'alterGenderID',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ),
      'birth_date' => array(
        'title' => ts($options['prefix_label'] . 'Birth Date'),
        'operatorType' => CRM_Report_Form::OP_DATE,
        'type' => CRM_Utils_Type::T_DATE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ),
      'age' => array(
        'title' => ts($options['prefix_label'] . 'Age'),
        'dbAlias' => 'TIMESTAMPDIFF(YEAR, ' . $tableAlias . '.birth_date, CURDATE())',
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
      ),
    );
    if (!$orgOnly) {
      $spec = array_merge($spec, $individualFields);
    }

    if (!empty($options['custom_fields'])) {
      $this->_customGroupExtended[$options['prefix'] . 'civicrm_contact'] = array(
        'extends' => $options['custom_fields'],
        'title' => $options['prefix_label'],
        'filters' => $options['filters'],
        'prefix' => $options['prefix'],
        'prefix_label' => $options['prefix_label'],
      );
    }

    return $this->buildColumns($spec, $options['prefix'] . 'civicrm_contact', 'CRM_Contact_DAO_Contact', $tableAlias, $this->getDefaultsFromOptions($options), $options);
  }

  /**
   * Function to get Activity Columns.
   *
   * @param array $options column options
   *
   * @return array
   */
  function getLatestActivityColumns($options) {
    $defaultOptions = array(
      'prefix' => '',
      'prefix_label' => '',
      'fields' => TRUE,
      'group_by' => FALSE,
      'order_by' => TRUE,
      'filters' => TRUE,
      'defaults' => array(
        'country_id' => TRUE
      ),
    );
    $options = array_merge($defaultOptions, $options);
    $activityFields['civicrm_activity']['fields'] = array(
      'activity_type_id' => array(
        'title' => ts('Latest Activity Type'),
        'default' => FALSE,
        'type' => CRM_Utils_Type::T_STRING,
        'alter_display' => 'alterActivityType',
        'is_fields' => TRUE,
      ),
      'activity_date_time' => array(
        'title' => ts('Latest Activity Date'),
        'default' => FALSE,
        'is_fields' => TRUE,
      ),
    );
    return $this->buildColumns($activityFields['civicrm_activity']['fields'], $options['prefix'] . 'civicrm_activity', 'CRM_Activity_DAO_Activity');
  }

}