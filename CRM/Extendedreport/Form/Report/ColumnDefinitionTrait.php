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

  /**
   * Function to get Activity Columns
   *
   * @param array $options column options
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  public function getActivityColumns(array $options = []): array {
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
        'options' => $this->_getOptions('activity', 'activity_status_id'),
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
   * @param array $options
   *
   * @return array
   *
   * @throws \CRM_Core_Exception
   */
  public function getCaseColumns(array $options): array {
    if (!in_array('CiviCase', CRM_Core_Config::singleton()->enableComponents, TRUE)) {
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
   * Get columns for the contact table.
   *
   * @param array $options
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getContactColumns($options = []): array {
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
      'is_required_for_acls' => empty($options['prefix']),
    ];

    $options = array_merge($defaultOptions, $options);
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
      $options['prefix'] . 'is_deleted' => [
        'title' => $options['prefix_label'] . E::ts('Is deleted'),
        'name' => 'is_deleted',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contact_BAO_Contact::buildOptions('is_deleted'),
        'is_fields' => FALSE,
        'is_filters' => TRUE,
        'is_group_bys' => FALSE,
      ],
      $options['prefix'] . 'external_identifier' => [
        'name' => 'external_identifier',
        'title' => $options['prefix_label'] . E::ts('External ID'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
      ],
      $options['prefix'] . 'sort_name' => [
        'name' => 'sort_name',
        'title' => $options['prefix_label'] . E::ts('Contact Name (in sort format)'),
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
      $options['prefix'] . 'organization_name' => [
        'title' => $options['prefix_label'] . E::ts('Organization Name'),
        'name' => 'organization_name',
        'operatorType' => CRM_Report_Form::OP_STRING,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
      ],
      $options['prefix'] . 'external_identifier' => [
        'title' => $options['prefix_label'] . ts('Contact identifier from external system'),
        'name' => 'external_identifier',
        'is_fields' => TRUE,
        'is_filters' => FALSE,
        'is_group_bys' => FALSE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'preferred_language' => [
        'title' => $options['prefix_label'] . ts('Preferred Language'),
        'name' => 'preferred_language',
        'alter_display' => 'alterPseudoConstant',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'preferred_communication_method' => [
        'title' => $options['prefix_label'] . ts('Preferred Communication Method'),
        'alter_display' => 'alterPseudoConstant',
        'name' => 'preferred_communication_method',
        'is_fields' => TRUE,
        'is_filters' => FALSE,
        'is_group_bys' => FALSE,
        'is_order_bys' => FALSE,
      ],
      $options['prefix'] . 'email_greeting_display' => [
        'name' => 'email_greeting_display',
        'title' => E::ts($options['prefix_label'] . 'Email Greeting'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'postal_greeting_display' => [
        'name' => 'postal_greeting_display',
        'title' => E::ts($options['prefix_label'] . 'Postal Greeting'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'addressee_display' => [
        'name' => 'addressee_display',
        'title' => E::ts($options['prefix_label'] . 'Addressee'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
    ];
    foreach (['do_not_email', 'do_not_phone', 'do_not_mail', 'do_not_sms', 'is_opt_out'] as $field) {
      $spec[$options['prefix'] . $field] = [
        'name' => $field,
        'type' => CRM_Utils_Type::T_BOOLEAN,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => FALSE,
        'options' =>  [
          '' => ts('Any'),
          '0' => ts('No'),
          '1' => ts('Yes'),
        ],
      ];
    }
    $individualFields = [
      $options['prefix'] . 'first_name' => [
        'name' => 'first_name',
        'title' => $options['prefix_label'] . E::ts('First Name'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'middle_name' => [
        'name' => 'middle_name',
        'title' => $options['prefix_label'] . E::ts('Middle Name'),
        'is_fields' => TRUE,
      ],
      $options['prefix'] . 'last_name' => [
        'name' => 'last_name',
        'title' => $options['prefix_label'] . E::ts('Last Name'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'nick_name' => [
        'name' => 'nick_name',
        'title' => $options['prefix_label'] . E::ts('Nick Name'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'formal_title' => [
        'name' => 'formal_title',
        'title' => $options['prefix_label'] . E::ts( 'Formal Title'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'prefix_id' => [
        'name' => 'prefix_id',
        'title' => $options['prefix_label'] . E::ts('Prefix'),
        'options' => CRM_Contact_BAO_Contact::buildOptions('prefix_id'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'alter_display' => 'alterPseudoConstant',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      $options['prefix'] . 'suffix_id' => [
        'name' => 'suffix_id',
        'title' => $options['prefix_label'] . E::ts('Suffix'),
        'options' => CRM_Contact_BAO_Contact::buildOptions('suffix_id'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'alter_display' => 'alterPseudoConstant',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      $options['prefix'] . 'gender_id' => [
        'name' => 'gender_id',
        'title' => $options['prefix_label'] . E::ts('Gender'),
        'options' => CRM_Contact_BAO_Contact::buildOptions('gender_id'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'alter_display' => 'alterGenderID',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'birth_date' => [
        'title' => $options['prefix_label'] . E::ts('Birth Date'),
        'operatorType' => CRM_Report_Form::OP_DATE,
        'type' => CRM_Utils_Type::T_DATE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'deceased_date' => [
        'title' => $options['prefix_label'] . E::ts('Deceased Date'),
        'operatorType' => CRM_Report_Form::OP_DATE,
        'type' => CRM_Utils_Type::T_DATE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'age' => [
        'title' => $options['prefix_label'] . E::ts('Age'),
        'dbAlias' => 'TIMESTAMPDIFF(YEAR, ' . $tableAlias . '.birth_date, CURDATE())',
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
      ],
      $options['prefix'] . 'is_deceased' => [
        'title' => $options['prefix_label'] . E::ts('Is deceased'),
        'name' => 'is_deceased',
        'type' => CRM_Utils_Type::T_BOOLEAN,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => FALSE,
        'options' =>  [
          '' => ts('Any'),
          '0' => ts('No'),
          '1' => ts('Yes'),
        ],
      ],
      $options['prefix'] . 'job_title' => [
        'name' => 'job_title',
        'title' => E::ts($options['prefix_label'] . 'Job Title'),
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      $options['prefix'] . 'employer_id' => [
        'title' => $options['prefix_label'] . ts('Current Employer'),
        'type' => CRM_Utils_Type::T_INT,
        'name' => 'employer_id',
        'alter_display' => 'alterEmployerID',
        'is_fields' => TRUE,
        'is_filters' => FALSE,
        'is_group_bys' => TRUE,
      ],
    ];
    if ($options['contact_type'] !== 'Organization') {
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
   * @throws \CRM_Core_Exception
   */
  protected function getLatestActivityColumns(array $options): array {
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
   * @throws \CRM_Core_Exception
   */
  protected function getContributionRecurColumns(array $options = []): array {
    $spec = [
      'id' => [
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'type' => CRM_Utils_Type::T_INT,
        'statistics' => ['count' => E::ts('Number of recurring profiles')],
        'is_order_bys' => TRUE,
      ],
      'payment_processor_id' => [
        'title' => E::ts('Payment Processor'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'alter_display' => 'alterPseudoConstant',
        'options' => CRM_Contribute_BAO_ContributionRecur::buildOptions('payment_processor_id', 'get'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
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
        'is_order_bys' => TRUE,
      ],
      'contribution_status_id' => [
        'title' => E::ts('Recurring Contribution Status'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contribute_BAO_ContributionRecur::buildOptions('contribution_status_id'),
        'default' => [5],
        'alter_display' => 'alterByOptions',
        'type' => CRM_Utils_Type::T_INT,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'frequency_interval' => [
        'title' => E::ts('Frequency interval'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'frequency_unit' => [
        'title' => E::ts('Frequency unit'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_OptionGroup::values('recur_frequency_units'),
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'amount' => [
        'title' => E::ts('Installment Amount'),
        'type' => CRM_Utils_Type::T_MONEY,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'installments' => [
        'title' => E::ts('Installments'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'start_date' => [
        'title' => E::ts('Start Date'),
        'operatorType' => CRM_Report_Form::OP_DATE,
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'create_date' => [
        'title' => E::ts('Create Date'),
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'modified_date' => [
        'title' => E::ts('Modified Date'),
        'operatorType' => CRM_Report_Form::OP_DATE,
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'cancel_date' => [
        'title' => E::ts('Cancel Date'),
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'cancel_reason' => [
        'title' => E::ts('Cancellation Reason'),
        'operatorType' => CRM_Report_Form::OP_STRING,
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'end_date' => [
        'title' => E::ts('End Date'),
        'operatorType' => CRM_Report_Form::OP_DATE,
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'next_sched_contribution_date' => [
        'title' => E::ts('Next Scheduled Contribution Date'),
        'operatorType' => CRM_Report_Form::OP_DATE,
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'failure_count' => [
        'title' => E::ts('Failure Count'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'type' => CRM_Utils_Type::T_INT,
        'is_order_bys' => TRUE,
      ],
      'failure_retry_date' => [
        'title' => E::ts('Failure Retry Date'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
        'is_order_bys' => TRUE,
      ],
      'financial_type_id' => [
        'title' => E::ts('Financial Type'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Financial_BAO_FinancialType::getAvailableFinancialTypes(),
        'type' => CRM_Utils_Type::T_INT,
        'is_order_bys' => TRUE,
      ],
    ];
    return $this->buildColumns($spec, $options['prefix'] . 'civicrm_contribution_recur', 'CRM_Contribute_BAO_ContributionRecur', NULL, $this->getDefaultsFromOptions($options), $options);
  }

  /**
   * Function to get ContributionSoft columns
   *
   * @param array $options
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getContributionSoftColumns(array $options = []): array {
    $spec = [
      'id' => [
        'is_fields' => FALSE,
        'is_filters' => FALSE,
        'type' => CRM_Utils_Type::T_INT,
        'is_order_bys' => FALSE,
      ],
      'contribution_id' => [
        'is_fields' => FALSE,
        'is_filters' => FALSE,
        'type' => CRM_Utils_Type::T_INT,
        'is_order_bys' => FALSE,
        'default' => TRUE,
      ],
      'amount' => [
        'default' => TRUE,
        'type' => CRM_Utils_Type::T_MONEY,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'soft_credit_type_id' => [
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'type' => CRM_Utils_Type::T_INT,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contribute_BAO_ContributionSoft::buildOptions('soft_credit_type_id'),
        'alter_display' => 'alterPseudoConstant',
        'is_order_bys' => TRUE,
      ],
      'currency' => [
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_OptionGroup::values('currencies_enabled'),
        'default' => NULL,
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
    ];
    return $this->buildColumns($spec, $options['prefix'] . 'civicrm_contribution_soft', 'CRM_Contribute_BAO_ContributionSoft', NULL, $this->getDefaultsFromOptions($options), $options);
  }

  /**
   * Function to get Grant columns.
   *
   * @param array $options column options
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getGrantColumns(array $options = []): array {
    $defaultOptions = $this->getDefaultOptions();

    $options = array_merge($defaultOptions, $options);
    $defaults = $this->getDefaultsFromOptions($options);
    $specs = [
      'grant_type_id' => [
        'title' => ts('Grant Type'),
        'is_fields' => 1,
        'is_filters' => 1,
        'is_group_bys' => 1,
        'is_order_bys' => 1,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_PseudoConstant::get('CRM_Grant_DAO_Grant', 'grant_type_id'),
        'alter_display' => 'alterPseudoConstant',
      ],
      'status_id' => [
        'title' => ts('Grant Status'),
        'is_fields' => 1,
        'is_filters' => 1,
        'is_group_bys' => 1,
        'is_order_bys' => 1,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_PseudoConstant::get('CRM_Grant_DAO_Grant', 'status_id'),
        'alter_display' => 'alterPseudoConstant',
      ],
      'amount_total' => [
        'title' => ts('Amount Requested'),
        'type' => CRM_Utils_Type::T_MONEY,
        'is_fields' => 1,
        'is_filters' => 1,
        'is_order_bys' => 1,
      ],
      'amount_granted' => [
        'title' => ts('Amount Granted'),
        'is_fields' => 1,
        'is_filters' => 1,
        'is_order_bys' => 1,
      ],
      'application_received_date' => [
        'title' => ts('Application Received'),
        'default' => TRUE,
        'type' => CRM_Utils_Type::T_DATE,
        'is_fields' => 1,
        'is_filters' => 1,
        'is_group_bys' => 1,
        'is_order_bys' => 1,
      ],
      'money_transfer_date' => [
        'title' => ts('Money Transfer Date'),
        'type' => CRM_Utils_Type::T_DATE,
        'is_fields' => 1,
        'is_filters' => 1,
        'is_group_bys' => 1,
        'is_order_bys' => 1,
      ],
      'grant_due_date' => [
        'title' => ts('Grant Report Due'),
        'type' => CRM_Utils_Type::T_DATE,
        'is_fields' => 1,
        'is_filters' => 1,
        'is_group_bys' => 1,
        'is_order_bys' => 1,
      ],
      'decision_date' => [
        'title' => ts('Grant Decision Date'),
        'type' => CRM_Utils_Type::T_DATE,
        'is_fields' => 1,
        'is_filters' => 1,
        'is_group_bys' => 1,
        'is_order_bys' => 1,
      ],
      'rationale' => [
        'title' => ts('Rationale'),
        'is_fields' => 1,
      ],
      'grant_report_received' => [
        'title' => ts('Grant Report Received'),
        'is_fields' => 1,
        'is_filters' => 1,
      ],
    ];
    return $this->buildColumns($specs, 'civicrm_grant', 'CRM_Grant_BAO_Grant', 'grants', $defaults);
  }

  /**
   * Function to get Product columns.
   *
   * @param array $options column options
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getProductColumns(array $options = []): array {
    $defaultOptions = $this->getDefaultOptions();

    $options = array_merge($defaultOptions, $options);
    $defaults = $this->getDefaultsFromOptions($options);
    $specs = [
      'name' => [
        'is_fields' => 1,
        'is_filters' => 1,
        'is_group_bys' => 1,
        'is_order_bys' => 1,
      ],
      'description' => [
        'is_fields' => 1,
        'is_filters' => 1,
        'is_group_bys' => 1,
        'is_order_bys' => 1,
      ],
      'sku' => [
        'is_fields' => 1,
        'is_filters' => 1,
        'is_group_bys' => 1,
        'is_order_bys' => 1,
      ],
    ];
    return $this->buildColumns($specs, 'civicrm_product', 'CRM_Contribute_DAO_Product', 'product', $defaults);
  }

  /**
   * Function to get Product Contribution columns.
   *
   * @param array $options column options
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getContributionProductColumns(array $options = []): array {
    $defaultOptions = $this->getDefaultOptions();

    $options = array_merge($defaultOptions, $options);
    $defaults = $this->getDefaultsFromOptions($options);
    $specs = [
      'product_option' => [
        'is_fields' => 1,
        'is_filters' => 1,
        'is_group_bys' => 1,
        'is_order_bys' => 1,
      ],
      'fulfilled_date' => [
        'is_fields' => 1,
        'is_filters' => 1,
        'is_group_bys' => 1,
        'is_order_bys' => 1,
      ],
    ];
    return $this->buildColumns($specs, 'civicrm_contribution_product', 'CRM_Contribute_DAO_ContributionProduct', 'contribution_product', $defaults);
  }

  /**
   * Get generic default options.
   *
   * @return array
   */
  protected function getDefaultOptions(): array {
    return [
      'prefix' => '',
      'prefix_label' => '',
      'group_by' => FALSE,
      'order_by' => TRUE,
      'filters' => TRUE,
      'fields_defaults' => [],
      'filters_defaults' => [],
      'group_bys_defaults' => [],
      'order_by_defaults' => ['sort_name ASC'],
    ];
  }

}
