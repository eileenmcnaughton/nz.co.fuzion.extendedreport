<?php
/**
 * Created by IntelliJ IDEA.
 * User: emcnaughton
 * Date: 9/1/18
 * Time: 1:05 AM
 */


use CRM_Extendedreport_ExtensionUtil as E;

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
        'title' => E::ts('Activity ID'),
        'is_group_bys' => $options['group_by'],
        'is_fields' => TRUE,
      ],
      'source_record_id' => [
        'no_display' => TRUE,
        'required' => FALSE,
      ],
      'activity_type_id' => [
        'title' => E::ts('Activity Type'),
        'alter_display' => 'alterActivityType',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => $this->_getOptions('activity', 'activity_type_id'),
        'name' => 'activity_type_id',
        'type' => CRM_Utils_Type::T_INT,
        'crm_editable' => [
          'id_table' => 'civicrm_activity',
          'id_field' => 'id',
          'entity' => 'activity',
          'options' => $this->_getOptions('activity', 'activity_type_id'),
        ],
      ],
      'subject' => [
        'title' => E::ts('Subject'),
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
        'title' => E::ts('Activity Date'),
        'default' => TRUE,
        'name' => 'activity_date_time',
        'operatorType' => CRM_Report_Form::OP_DATE,
        'type' => CRM_Utils_Type::T_DATE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'status_id' => [
        'title' => E::ts('Activity Status'),
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
        'title' => E::ts('Duration (sum for all contacts)'),
        'type' => CRM_Utils_Type::T_INT,
        'statistics' => [
          'sum' => E::ts('Total Duration'),
        ],
        'is_fields' => TRUE,
      ],
      'duration_each' => [
        'title' => E::ts('Duration (for each contact)'),
        'name' => 'duration',
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'crm_editable' => [
          'id_table' => 'civicrm_activity',
          'id_field' => 'id',
          'entity' => 'activity',
        ],
      ],
      'details' => [
        'title' => E::ts('Activity Details'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'type' => CRM_Utils_Type::T_TEXT,
        'crm_editable' => [
          'id_table' => 'civicrm_activity',
          'id_field' => 'id',
          'entity' => 'activity',
        ],
      ],
      'location' => [
        'title' => E::ts('Location'),
        'type' => CRM_Utils_Type::T_STRING,
        'is_filters' => TRUE,
        'is_fields' => TRUE,
        'crm_editable' => [
          'id_table' => 'civicrm_activity',
          'id_field' => 'id',
          'entity' => 'activity',
        ],
      ],
      'priority_id' => [
        'title' => E::ts('Priority'),
        'type' => CRM_Utils_Type::T_STRING,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'alter_display' => 'alterPseudoConstant',
        'options' => $this->_getOptions('activity', 'priority_id'),
        'is_filters' => TRUE,
        'is_fields' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
        'crm_editable' => [
          'id_table' => 'civicrm_activity',
          'id_field' => 'id',
          'entity' => 'activity',
          'options' => $this->_getOptions('activity', 'priority_id'),
        ],
      ],
      'result' => [
        'title' => E::ts('Activity Result'),
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
        'title' => E::ts("Current Revision"),
        'name' => 'is_current_revision',
        'options' => ['1' => 'Yes', '0' => 'No',],
        'is_filters' => TRUE,
      ],
      'is_deleted' => [
        'type' => CRM_Report_Form::OP_INT,
        'operatorType' => CRM_Report_Form::OP_SELECT,
        'title' => E::ts("Is activity deleted"),
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
            'title' => E::ts('Case ID'),
            'name' => 'id',
            'is_fields' => TRUE,
          ],
          'subject' => [
            'title' => E::ts('Case Subject'),
            'default' => TRUE,
            'is_fields' => TRUE,
            'is_filters' => TRUE,
          ],
          'status_id' => [
            'title' => E::ts('Case Status'),
            'default' => TRUE,
            'name' => 'status_id',
            'is_fields' => TRUE,
            'is_filters' => TRUE,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Case_BAO_Case::buildOptions('case_status_id'),
            'type' => CRM_Utils_Type::T_INT,
          ],
          'case_type_id' => [
            'title' => E::ts('Case Type'),
            'is_fields' => TRUE,
            'is_filters' => TRUE,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Case_BAO_Case::buildOptions('case_type_id'),
            'name' => 'case_type_id',
            'type' => CRM_Utils_Type::T_INT,
          ],
          'start_date' => [
            'title' => E::ts('Case Start Date'),
            'name' => 'start_date',
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
            'is_fields' => TRUE,
            'is_filters' => TRUE,
          ],
          'end_date' => [
            'title' => E::ts('Case End Date'),
            'name' => 'end_date',
            'is_fields' => TRUE,
            'is_filters' => TRUE,
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
          ],
          'is_deleted' => [
            'name' => 'is_deleted',
            'title' => E::ts('Case Deleted?'),
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
        'title' => E::ts($options['prefix_label'] . 'Contact Name'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'contact_id' => [
        'name' => 'id',
        'title' => E::ts($options['prefix_label'] . 'Contact ID'),
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
        'title' => E::ts($options['prefix_label'] . 'External ID'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
      ],
      $options['prefix'] . 'sort_name' => [
        'name' => 'sort_name',
        'title' => E::ts($options['prefix_label'] . 'Contact Name (in sort format)'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'contact_type' => [
        'title' => E::ts($options['prefix_label'] . 'Contact Type'),
        'name' => 'contact_type',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contact_BAO_Contact::buildOptions('contact_type'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
      ],
      $options['prefix'] . 'contact_sub_type' => [
        'title' => E::ts($options['prefix_label'] . 'Contact Sub Type'),
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
        'title' => E::ts($options['prefix_label'] . 'First Name'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'middle_name' => [
        'name' => 'middle_name',
        'title' => E::ts($options['prefix_label'] . 'Middle Name'),
        'is_fields' => TRUE,
      ],
      $options['prefix'] . 'last_name' => [
        'name' => 'last_name',
        'title' => E::ts($options['prefix_label'] . 'Last Name'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'nick_name' => [
        'name' => 'nick_name',
        'title' => E::ts($options['prefix_label'] . 'Nick Name'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'gender_id' => [
        'name' => 'gender_id',
        'title' => E::ts($options['prefix_label'] . 'Gender'),
        'options' => CRM_Contact_BAO_Contact::buildOptions('gender_id'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'alter_display' => 'alterGenderID',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'birth_date' => [
        'title' => E::ts($options['prefix_label'] . 'Birth Date'),
        'operatorType' => CRM_Report_Form::OP_DATE,
        'type' => CRM_Utils_Type::T_DATE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'age' => [
        'title' => E::ts($options['prefix_label'] . 'Age'),
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
        'title' => E::ts('Latest Activity Type'),
        'default' => FALSE,
        'type' => CRM_Utils_Type::T_STRING,
        'alter_display' => 'alterActivityType',
        'is_fields' => TRUE,
      ],
      'activity_date_time' => [
        'title' => E::ts('Latest Activity Date'),
        'default' => FALSE,
        'is_fields' => TRUE,
      ],
    ];
    return $this->buildColumns($activityFields['civicrm_activity']['fields'], $options['prefix'] . 'civicrm_activity', 'CRM_Activity_DAO_Activity');
  }

  /**
   * @param array $options
   *
   * @return array
   */
  function getContributionRecurColumns($options = []) {
    $spec = [
      'id' => [
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'type' => CRM_Utils_Type::T_INT,
        'statistics' => ['count' => E::ts('Numer of recurring profiles')],
      ],
      'payment_processor_id' => [
        'title' => E::ts('Payment Processor'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'alter_display' => 'alterPseudoConstant',
        'options' => CRM_Contribute_BAO_ContributionRecur::buildOptions('payment_processor_id', 'get'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' =>  TRUE,
        'is_order_bys' => TRUE,
      ],
      'currency' => [
        'title' => E::ts("Currency"),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_OptionGroup::values('currencies_enabled'),
        'default' => NULL,
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'contribution_status_id' => [
        'title' => E::ts('Recurring Contribution Status'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contribute_PseudoConstant::contributionStatus(),
        'default' => [5],
        'type' => CRM_Utils_Type::T_INT,
        'is_group_bys' =>  TRUE,
        'is_order_bys' => TRUE,
      ],
      'frequency_interval' => [
        'title' => E::ts('Frequency interval'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'is_filters' => TRUE
      ],
      'frequency_unit' => [
        'title' => E::ts('Frequency unit'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_OptionGroup::values('recur_frequency_units'),
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'amount' => [
        'title' => E::ts('Installment Amount'),
        'type' => CRM_Utils_Type::T_MONEY,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' =>  TRUE,
        'is_order_bys' => TRUE,
      ],
      'installments' => [
        'title' => E::ts('Installments'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'start_date' => [
        'title' => E::ts('Start Date'),
        'operatorType' => CRM_Report_Form::OP_DATE,
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'create_date' => [
        'title' => E::ts('Create Date'),
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
        'is_filters' => TRUE,
      ],
      'modified_date' => [
        'title' => E::ts('Modified Date'),
        'operatorType' => CRM_Report_Form::OP_DATE,
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'cancel_date' => [
        'title' => E::ts('Cancel Date'),
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
        'is_filters' => TRUE,
      ],
      'cancel_reason' => [
        'title' => E::ts('Cancellation Reason'),
        'operatorType' => CRM_Report_Form::OP_STRING,
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' =>  TRUE,
        'is_order_bys' => TRUE,
      ],
      'end_date' => [
        'title' => E::ts('End Date'),
        'operatorType' => CRM_Report_Form::OP_DATE,
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'next_sched_contribution_date' => [
        'title' => E::ts('Next Scheduled Contribution Date'),
        'operatorType' => CRM_Report_Form::OP_DATE,
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'failure_count' => [
        'title' => E::ts('Failure Count'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'type' => CRM_Utils_Type::T_INT,
      ],
      'failure_retry_date' => [
        'title' => E::ts('Failure Retry Date'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
      ],
      'financial_type_id' => [
        'title' => E::ts('Financial Type'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Financial_BAO_FinancialType::getAvailableFinancialTypes(),
        'type' => CRM_Utils_Type::T_INT,
      ],
    ];
    return $this->buildColumns($spec, $options['prefix'] . 'civicrm_contribution_recur', 'CRM_Contribute_BAO_ContributionRecur', NULL, $this->getDefaultsFromOptions($options), $options);
  }

}
