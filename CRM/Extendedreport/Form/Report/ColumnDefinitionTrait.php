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
  function getActivityColumns($options = []) {
    $defaultOptions = [
      'prefix' => '',
      'prefix_label' => '',
      'fields' => TRUE,
      'group_by' => FALSE,
      'order_by' => TRUE,
      'filters' => TRUE,
      'fields_defaults' => [],
      'filters_defaults' => [],
      'group_bys_defaults' => [],
      'order_by_defaults' => [],
    ];

    $options = array_merge($defaultOptions, $options);
    $defaults = $this->getDefaultsFromOptions($options);

    $spec = [
      'id' => [
        'name' => 'id',
        'title' => ts('Activity ID'),
        'is_group_bys' => $options['group_by'],
        'is_fields' => TRUE,
      ],
      'source_record_id' => [
        'no_display' => TRUE,
        'required' => FALSE,
      ],
      'activity_type_id' => [
        'title' => ts('Activity Type'),
        'alter_display' => 'alterActivityType',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_PseudoConstant::activityType(TRUE, TRUE),
        'name' => 'activity_type_id',
        'type' => CRM_Utils_Type::T_INT,
      ],
      'subject' => [
        'title' => ts('Subject'),
        'name' => 'subject',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'crm_editable' => [
          'id_table' => 'civicrm_activity',
          'id_field' => 'id',
          'entity' => 'activity',
        ],

      ],
      'activity_date_time' => [
        'title' => ts('Activity Date'),
        'default' => TRUE,
        'name' => 'activity_date_time',
        'operatorType' => CRM_Report_Form::OP_DATE,
        'type' => CRM_Utils_Type::T_DATE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'status_id' => [
        'title' => ts('Activity Status'),
        'name' => 'status_id',
        'type' => CRM_Utils_Type::T_STRING,
        'alter_display' => 'alterPseudoConstant',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_PseudoConstant::activityStatus(),
        'crm_editable' => [
          'id_table' => 'civicrm_activity',
          'id_field' => 'id',
          'entity' => 'activity',
          'options' => $this->_getOptions('activity', 'activity_status_id'),
        ],
      ],
      'duration' => [
        'title' => ts('Duration (sum for all contacts)'),
        'type' => CRM_Utils_Type::T_INT,
        'statistics' => [
          'sum' => ts('Total Duration'),
        ],
        'is_fields' => TRUE,
      ],
      'duration_each' => [
        'title' => ts('Duration (for each contact)'),
        'name' => 'duration',
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
      ],
      'details' => [
        'title' => ts('Activity Details'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'type' => CRM_Utils_Type::T_TEXT,
        'crm_editable' => [
          'id_table' => 'civicrm_activity',
          'id_field' => 'id',
          'entity' => 'activity',
        ],

      ],
      'result' => [
        'title' => ts('Activity Result'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'type' => CRM_Utils_Type::T_TEXT,
        'crm_editable' => [
          'id_table' => 'civicrm_activity',
          'id_field' => 'id',
          'entity' => 'activity',
        ],
      ],
      'is_current_revision' => [
        'type' => CRM_Report_Form::OP_INT,
        'operatorType' => CRM_Report_Form::OP_SELECT,
        'title' => ts("Current Revision"),
        'name' => 'is_current_revision',
        'options' => ['1' => 'Yes', '0' => 'No',],
        'is_filters' => TRUE,
      ],
      'is_deleted' => [
        'type' => CRM_Report_Form::OP_INT,
        'operatorType' => CRM_Report_Form::OP_SELECT,
        'title' => ts("Is activity deleted"),
        'name' => 'is_deleted',
        'options' => ['0' => 'No', '1' => 'Yes',],
        'is_filters' => TRUE,
      ],

    ];
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
      return ['civicrm_case' => ['fields' => [], 'metadata' => []]];
    }

    $spec = [
      'civicrm_case' => [
        'fields' => [
          'id' => [
            'title' => ts('Case ID'),
            'name' => 'id',
            'is_fields' => TRUE,
          ],
          'subject' => [
            'title' => ts('Case Subject'),
            'default' => TRUE,
            'is_fields' => TRUE,
            'is_filters' => TRUE,
          ],
          'status_id' => [
            'title' => ts('Case Status'),
            'default' => TRUE,
            'name' => 'status_id',
            'is_fields' => TRUE,
            'is_filters' => TRUE,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Case_BAO_Case::buildOptions('case_status_id'),
            'type' => CRM_Utils_Type::T_INT,
          ],
          'case_type_id' => [
            'title' => ts('Case Type'),
            'is_fields' => TRUE,
            'is_filters' => TRUE,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Case_BAO_Case::buildOptions('case_type_id'),
            'name' => 'case_type_id',
            'type' => CRM_Utils_Type::T_INT,
          ],
          'start_date' => [
            'title' => ts('Case Start Date'),
            'name' => 'start_date',
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
            'is_fields' => TRUE,
            'is_filters' => TRUE,
          ],
          'end_date' => [
            'title' => ts('Case End Date'),
            'name' => 'end_date',
            'is_fields' => TRUE,
            'is_filters' => TRUE,
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
          ],
          'is_deleted' => [
            'name' => 'is_deleted',
            'title' => ts('Case Deleted?'),
            'type' => CRM_Utils_Type::T_BOOLEAN,
            'is_fields' => TRUE,
            'is_filters' => TRUE,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'options' => ['' => '--select--'] + CRM_Case_BAO_Case::buildOptions('is_deleted'),
          ],
        ],
      ],

    ];
    // Case is a special word in mysql so pass an alias to prevent it from using case.
    return $this->buildColumns($spec['civicrm_case']['fields'], $options['prefix'] . 'civicrm_case', 'CRM_Case_DAO_Case', 'case_civireport');
  }

  /**
   * @param array $options
   *
   * @return array
   */
  function getContactColumns($options = []) {
    $defaultOptions = [
      'prefix' => '',
      'prefix_label' => '',
      'group_by' => TRUE,
      'order_by' => TRUE,
      'filters' => TRUE,
      'fields' => TRUE,
      'custom_fields' => ['Individual', 'Contact', 'Organization'],
      'fields_defaults' => ['display_name', 'id'],
      'filters_defaults' => [],
      'group_bys_defaults' => [],
      'order_by_defaults' => ['sort_name ASC'],
      'contact_type' => NULL,
    ];

    $options = array_merge($defaultOptions, $options);
    $orgOnly = FALSE;
    if (CRM_Utils_Array::value('contact_type', $options) == 'Organization') {
      $orgOnly = TRUE;
    }
    $tableAlias = $options['prefix'] . 'civicrm_contact';

    $spec = [
      $options['prefix'] . 'display_name' => [
        'name' => 'display_name',
        'title' => ts($options['prefix_label'] . 'Contact Name'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'contact_id' => [
        'name' => 'id',
        'title' => ts($options['prefix_label'] . 'Contact ID'),
        'alter_display' => 'alterContactID',
        'type' => CRM_Utils_Type::T_INT,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_contact_filter' => TRUE,
      ],
      $options['prefix'] . 'external_identifier' => [
        'name' => 'external_identifier',
        'title' => ts($options['prefix_label'] . 'External ID'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
      ],
      $options['prefix'] . 'sort_name' => [
        'name' => 'sort_name',
        'title' => ts($options['prefix_label'] . 'Contact Name (in sort format)'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'contact_type' => [
        'title' => ts($options['prefix_label'] . 'Contact Type'),
        'name' => 'contact_type',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contact_BAO_Contact::buildOptions('contact_type'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
      ],
      $options['prefix'] . 'contact_sub_type' => [
        'title' => ts($options['prefix_label'] . 'Contact Sub Type'),
        'name' => 'contact_sub_type',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contact_BAO_Contact::buildOptions('contact_sub_type'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
      ],
    ];
    $individualFields = [
      $options['prefix'] . 'first_name' => [
        'name' => 'first_name',
        'title' => ts($options['prefix_label'] . 'First Name'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'middle_name' => [
        'name' => 'middle_name',
        'title' => ts($options['prefix_label'] . 'Middle Name'),
        'is_fields' => TRUE,
      ],
      $options['prefix'] . 'last_name' => [
        'name' => 'last_name',
        'title' => ts($options['prefix_label'] . 'Last Name'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'nick_name' => [
        'name' => 'nick_name',
        'title' => ts($options['prefix_label'] . 'Nick Name'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'gender_id' => [
        'name' => 'gender_id',
        'title' => ts($options['prefix_label'] . 'Gender'),
        'options' => CRM_Contact_BAO_Contact::buildOptions('gender_id'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'alter_display' => 'alterGenderID',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'birth_date' => [
        'title' => ts($options['prefix_label'] . 'Birth Date'),
        'operatorType' => CRM_Report_Form::OP_DATE,
        'type' => CRM_Utils_Type::T_DATE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'age' => [
        'title' => ts($options['prefix_label'] . 'Age'),
        'dbAlias' => 'TIMESTAMPDIFF(YEAR, ' . $tableAlias . '.birth_date, CURDATE())',
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
      ],
    ];
    if (!$orgOnly) {
      $spec = array_merge($spec, $individualFields);
    }

    if (!empty($options['custom_fields'])) {
      $this->_customGroupExtended[$options['prefix'] . 'civicrm_contact'] = [
        'extends' => $options['custom_fields'],
        'title' => $options['prefix_label'],
        'filters' => $options['filters'],
        'prefix' => $options['prefix'],
        'prefix_label' => $options['prefix_label'],
      ];
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
    $defaultOptions = [
      'prefix' => '',
      'prefix_label' => '',
      'fields' => TRUE,
      'group_by' => FALSE,
      'order_by' => TRUE,
      'filters' => TRUE,
      'defaults' => [
        'country_id' => TRUE,
      ],
    ];
    $options = array_merge($defaultOptions, $options);
    $activityFields['civicrm_activity']['fields'] = [
      'activity_type_id' => [
        'title' => ts('Latest Activity Type'),
        'default' => FALSE,
        'type' => CRM_Utils_Type::T_STRING,
        'alter_display' => 'alterActivityType',
        'is_fields' => TRUE,
      ],
      'activity_date_time' => [
        'title' => ts('Latest Activity Date'),
        'default' => FALSE,
        'is_fields' => TRUE,
      ],
    ];
    return $this->buildColumns($activityFields['civicrm_activity']['fields'], $options['prefix'] . 'civicrm_activity', 'CRM_Activity_DAO_Activity');
  }

}
