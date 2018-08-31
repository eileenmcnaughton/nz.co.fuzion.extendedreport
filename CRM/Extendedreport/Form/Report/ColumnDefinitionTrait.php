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
        'default' => array(1),
        'name' => 'is_current_revision',
        'options' => array('1' => 'Yes', '0' => 'No',),
        'is_filters' => TRUE,
      ),
      'is_deleted' => array(
        'type' => CRM_Report_Form::OP_INT,
        'operatorType' => CRM_Report_Form::OP_SELECT,
        'title' => ts("Is activity deleted"),
        'default' => array(0),
        'name' => 'is_deleted',
        'options' => array('0' => 'No', '1' => 'Yes',),
        'is_filters' => TRUE,
      ),

    );
    return $this->buildColumns($spec, $options['prefix'] . 'civicrm_activity', 'CRM_Activity_DAO_Activity', NULL, $defaults, $options);
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