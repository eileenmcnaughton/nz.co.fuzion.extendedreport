<?php

use CRM_Extendedreport_ExtensionUtil as E;

/**
 * @property mixed _aliases
 * @property mixed deleted_labels
 */
class CRM_Extendedreport_Form_Report_ExtendedReport extends CRM_Report_Form {

  use CRM_Extendedreport_Form_Report_ColumnDefinitionTrait;

  protected $_extraFrom = '';
  protected $_summary = NULL;
  protected $_exposeContactID = FALSE;
  protected $_customGroupExtends = array();
  protected $_baseTable = 'civicrm_contact';
  protected $_editableFields = TRUE;
  protected $_rollup = '';
  protected $_fieldSpecs = array();
  public $_defaults = array();

  protected $contactIDField;

  protected $metaData = [];

  /**
   * All available filter fields with metadata.
   *
   * @var array
   */
  protected $availableFilters = array();

  /**
   * Is this report a pivot chart.
   *
   * @var bool
   */
  protected $isPivot = FALSE;
  /**
   * Report ID to link through to.
   *
   * Generally if we are linking to the same report we should keep the id of the report we are on
   * (filter within the report) but just the general report if not.
   *
   * @var int|null
   */
  protected $linkedReportID;

  /**
   * Available templates.
   *
   * @var array
   */
  protected $_templates = array();

  /**
   * Add a tab for adding a join relationship?
   *
   * @var bool
   */
  protected $joinFiltersTab  = FALSE;

  protected $_customFields = array();

  /**
   * Denotes whether a temporary table should be defined as temporary.
   *
   * This can be set to empty when debugging.
   *
   * @var string
   */
  protected $temporary = ' TEMPORARY ';

  /**
   * @var string
   */
  protected $_from;

  /**
   * Flag to indicate if result-set is to be stored in a class variable which could be retrieved using getResultSet() method.
   *
   * @var boolean
   */
  protected $_storeResultSet = FALSE;

  /**
   * When _storeResultSet Flag is set use this var to store result set in form of array
   *
   * @var boolean
   */
  protected $_resultSet = array();
  /**
   * An instruction not to add a Group By
   * This is relevant where the group by might be otherwise added after the code that determines it
   * should not be added is processed but the code does not want to mess with other fields / processing
   * e.g. where stat fields are being added but other settings cause it to not be desirable to add a group by
   * such as in pivot charts when no row header is set
   * @var $_noGroupBY boolean
   */
  protected $_noGroupBY = FALSE;
  protected $_outputMode = array();
  protected $_customGroupOrderBy = TRUE;

  /**
   * Fields available to be added as Column headers in pivot style report
   * @var array
   */
  protected $_aggregateColumnHeaderFields = array();

  /**
   * Fields available to be added as Rows in pivot style report
   * @var array
   */
  protected $_aggregateRowFields = array();

  /**
   * Include NULL values in aggregate (pivot) fields
   * @var boolean
   */
  protected $_aggregatesIncludeNULL = TRUE;


  /**
   * Allow the aggregate column to be unset which will just give totalss
   * @var boolean
   */
  protected $_aggregatesColumnsOptions = TRUE;

  /**
   * Add a total column to aggregate (pivot) fields
   * @var bool _aggregatesAddTotal
   */
  protected $_aggregatesAddTotal = TRUE;
  /**
   * we will set $this->aliases['civicrm_contact'] to match the primary contact because many upstream functions
   * (e.g tag filters)
   * assume the join will be on that field
   * @var string
   */
  protected $_primaryContactPrefix = '';

  /*
   * adding support for a single date in here
   */
  CONST OP_SINGLEDATE = 3;

  /**
   * array of extended custom data fields. this is populated by functions like getContactColumns
   */
  protected $_customGroupExtended = array();

  /**
   * Use $temporary to choose whether to generate permanent or temporary tables
   * ie. for debugging it's good to set to ''
   */
  protected $_temporary = ' TEMPORARY ';

  protected $_customGroupAggregates;

  /**
   * Filters to be applied to a join
   *
   * (currently only relationships are supported).
   *
   * @var array
   */
  protected $_join_filters = array();

  /**
   * Custom field filters
   *
   * Array of custom fields defined as filters. We use this to determine which tables to include.
   *
   * @var array
   */
  protected $_custom_fields_filters = array();

  /**
   * Custom fields selected for display
   *
   * Array of custom fields that have been selected for display.
   * We use this to determine which tables to include.
   *
   * @var array
   */
  protected $_custom_fields_selected = array();

  /**
   * Clauses to be applied to the relationship join as extracted from the input.
   *
   * @var array
   */
  protected $joinClauses = array();

  /**
   * generate a temp table of records that meet criteria & then build the query
   */
  protected $_preConstrain = FALSE;
  /**
   * Set to true once temp table has been generated
   */
  protected $_preConstrained = FALSE;
  /**
   * Name of table that links activities to cases. The 'real' table can be replaced by a temp table
   * during processing when a pre-filter is required (e.g we want all cases whether or not they
   * have an activity of type x but we only want activities of type x)
   * (See case with Activity Pivot)
   *
   * @var string
   */
  protected $_caseActivityTable = 'civicrm_case_activity';

  protected $financialTypePseudoConstant = 'financialType';
  /**
   * The contact_is deleted clause gets added whenever we call the ACL clause - if we don't want
   * it we will specifically allow skipping it
   * @boolean skipACLContactDeletedClause
   */
  protected $_skipACLContactDeletedClause = FALSE;
  protected $whereClauses = array();

  protected $_groupByArray = array();

  /**
   * If we have stat fields that are set we may want to force the group by.
   *
   * @var bool
   */
  protected $isForceGroupBy = FALSE;

  /**
   * Can this report be used on a contact tab.
   *
   * The report must support contact_id in the url for this to work.
   *
   * @var bool
   */
  protected $isSupportsContactTab = FALSE;

  /**
   * DAOs for custom data fields to use.
   *
   * The format is more a refactoring stage than an end result.
   *
   * @var array
   */
  protected $customDataDAOs = [];

  /**
   * Has the report been optimised for group filtering.
   *
   * The functionality for group filtering has been improved but not
   * all reports have been adjusted to take care of it.
   *
   * This property exists to highlight the reports which are still using the
   * slow method & allow group filtering to still work for them until they
   * can be migrated.
   *
   * In order to protect extensions we have to default to TRUE - but I have
   * separately marked every class with a groupFilter in the hope that will trigger
   * people to fix them as they touch them.
   *
   * CRM-19170
   *
   * @var bool
   */
  protected $groupFilterNotOptimised = FALSE;

  /**
   * Class constructor.
   */
  public function __construct() {
    parent::__construct();
    $this->addTemplateSelector();
    if ($this->_customGroupAggregates) {
      $this->addAggregateSelectorsToForm();
    }
    if ($this->isSupportsContactTab) {
      $this->_options = [
        'contact_dashboard_tab' => [
          'title' => ts('Display as tab on contact record'),
          'type' => 'checkbox',
        ],
        'contact_reportlet' => [
          'title' => ts('Make available for contact summary page (requires contact layout editor extension)'),
          'type' => 'checkbox',
        ],
        'contact_id_filter_field' => [
          'title' => ts('Select field to use as contact filter'),
          'type' => 'select',
          'options' => $this->getContactFilterFieldOptions(),
        ],
        'number_of_rows_to_render' => [
          'title' => ts('Override default number of rows with'),
          'type' => 'text',
        ],
      ];
    }
  }

  /**
   * Get metadata for the report.
   *
   * @return array
   */
  public function getMetadata() {
    if (empty($this->metaData)) {
      $definitionTypes = ['fields', 'filters', 'join_filters', 'group_bys', 'order_bys'];
      $this->metaData = array_fill_keys($definitionTypes, []);
      $this->metaData['having'] = [];

      foreach ($this->_columns as $table => $tableSpec) {
        foreach ($definitionTypes as $type) {
          foreach ($tableSpec['metadata'] as $fieldName => $fieldSpec) {
            if (!isset($this->metaData[$fieldName])) {
              $this->metaData['metadata'][$fieldName] = array_merge($fieldSpec, ['table_name' => $table]);
            }
            if ($fieldSpec['is_' . $type]) {
              $this->metaData[$type][$fieldName] = array_merge($fieldSpec, ['table_name' => $table]);
            }
            if ($type === 'filters' && !empty($fieldSpec['having'])) {
              $this->metaData['having'][$fieldName] = array_merge($fieldSpec, ['table_name' => $table]);
            }
          }
        }
      }
    }
    return $this->metaData;
  }

  /**
   * Build the tag filter field to display on the filters tab.
   */
  public function buildTagFilter() {
    $entityTable = $this->_columns[$this->_tagFilterTable]['table_name'];
    $contactTags = CRM_Core_BAO_Tag::getTags($entityTable);
    if (!empty($contactTags)) {
      $this->_columns += $this->buildColumns([
        'tagid' => [
          'name' => 'tag_id',
          'title' => ts('Tag'),
          'type' => CRM_Utils_Type::T_INT,
          'tag' => TRUE,
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'options' => $contactTags,
          'is_filters' => TRUE,
          'is_fields' => FALSE,
          'is_group_bys' => FALSE,
          'is_order_bys' => FALSE,
          'is_join_filters' => FALSE,
        ],
      ], 'civicrm_tag', 'CRM_Core_DAO_Tag', 'tag', [], ['no_field_disambiguation' => TRUE]);
    }
    $this->_columns['civicrm_tag']['group_title'] = ts('Tags');
  }

  /**
   * Get the report ID if determined.
   *
   * @return int
   */
  public function getInstanceID() {
    return $this->_id;
  }

  /**
   * Adds group filters to _columns (called from _Construct).
   */
  public function buildGroupFilter() {
    $this->_columns += $this->buildColumns(
      [
        'gid' => [
          'name' => 'group_id',
          'title' => ts('Group'),
          'type' => CRM_Utils_Type::T_INT,
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'group' => TRUE,
          'options' => CRM_Core_PseudoConstant::nestedGroup(),
          'alias' => 'civicrm_group_gid',
          'is_filters' => TRUE,
          'is_fields' => FALSE,
          'is_group_bys' => FALSE,
          'is_order_bys' => FALSE,
          'is_join_filters' => FALSE,
          'dbAlias' => 'group.group_id',
        ],
      ],
      'civicrm_group', 'CRM_Contact_DAO_GroupContact', 'group', [], ['no_field_disambiguation' => TRUE]
    );
  }

  /**
   * Wrapper for getOptions / pseudoconstant to get contact type options.
   *
   * @return array
   */
  function getLocationTypeOptions() {
    return $this->_getOptions('address', 'location_type_id');
  }

  /**
   * Pre process function.
   *
   * Called prior to build form.
   *
   * Backported to provide CRM-12687 which is in 4.4 and to prevent inappropriate
   * defaults being set for group by in core function. Further wrangling
   * (not in core) for uniquename matching against core metadata.
   *
   * https://github.com/eileenmcnaughton/nz.co.fuzion.extendedreport/issues/12
   */
  public function preProcess() {
    $this->preProcessCommon();

    if (!$this->_id) {
      $this->addBreadCrumb();
    }
    $this->ensureBaoIsSetIfPossible();

    $this->mergeExtendedConfigurationIntoReportData();

    foreach ($this->_columns as $tableName => $table) {
      $this->_aliases[$tableName] = $this->setTableAlias($table, $tableName);
      $expFields = $this->getMetadataForFields($table);

      // Extended reports customisation starts ==
      // We don't want all the schema data copied onto group_bys or order_bys.
      // Ideally we ONLY want it in metadata & other fields can
      // 'dip into that' as required. But a lot to untangle before then....
      // allowing it on group_bys & order_bys can lead to required fields defaulting
      // to being a group by.
      $fieldGroups = array('fields', 'filters', 'metadata', 'join_filters', 'group_bys', 'order_bys');
      // Extended reports customisation ends ==

      foreach ($fieldGroups as $fieldGrp) {
        if (!empty($table[$fieldGrp]) && is_array($table[$fieldGrp])) {
          foreach ($table[$fieldGrp] as $fieldName => $field) {
            // $name is the field name used to reference the BAO/DAO export fields array
            $name = isset($field['name']) ? $field['name'] : $fieldName;

            // Sometimes the field name key in the BAO/DAO export fields array is
            // different from the actual database field name.
            // Unset $field['name'] so that actual database field name can be obtained
            // from the BAO/DAO export fields array.
            unset($field['name']);

            if (array_key_exists($name, $expFields)) {
              if (empty($field)) {
                $this->_columns[$tableName][$fieldGrp][$fieldName] = $expFields[$name];
              }
              else {
                foreach ($expFields[$name] as $property => $val) {
                  if (!array_key_exists($property, $field)) {
                    $this->_columns[$tableName][$fieldGrp][$fieldName][$property] = $val;
                  }
                }
              }
            }

            // fill other vars
            if (!empty($field['no_repeat'])) {
              $this->_noRepeats[] = "{$tableName}_{$fieldName}";
            }
            if (!empty($field['no_display'])) {
              $this->_noDisplay[] = "{$tableName}_{$fieldName}";
            }

            // set alias = table-name, unless already set
            $alias = isset($field['alias']) ? $field['alias'] : (
            isset($this->_columns[$tableName]['alias']) ? $this->_columns[$tableName]['alias'] : $tableName
            );
            $this->_columns[$tableName][$fieldGrp][$fieldName]['alias'] = $alias;

            // set name = fieldName, unless already set
            if (!isset($this->_columns[$tableName][$fieldGrp][$fieldName]['name'])) {
              $this->_columns[$tableName][$fieldGrp][$fieldName]['name'] = $name;
            }

            // set dbAlias = alias.name, unless already set
            if (!isset($this->_columns[$tableName][$fieldGrp][$fieldName]['dbAlias'])) {
              $this->_columns[$tableName][$fieldGrp][$fieldName]['dbAlias']
                = $alias . '.' .
                $this->_columns[$tableName][$fieldGrp][$fieldName]['name'];
            }
          }
        }
      }

      // Copy filters to a separate handy variable.
      foreach (array('filters', 'join_filters') as $filterString) {
        if (array_key_exists($filterString, $table)) {
          $property = '_' . $filterString;
          $this->{$property}[$tableName] = $this->_columns[$tableName][$filterString];
        }
      }

      if (array_key_exists('group_bys', $table)) {
        $groupBys[$tableName] = $this->_columns[$tableName]['group_bys'];
      }

      if (array_key_exists('fields', $table)) {
        $reportFields[$tableName] = $this->_columns[$tableName]['fields'];
      }
    }

    if ($this->joinFiltersTab ) {
      $this->addJoinFiltersTab();
    }
    if ($this->_force) {
      $this->setDefaultValues(FALSE);
    }
    elseif (($contact_id = $this->getContactIdFilter()) !== FALSE) {
      $this->_params[$this->contactIDField . '_value'] = $contact_id;
      $this->_params[$this->contactIDField . '_op'] = $contact_id;
    }

    CRM_Report_Utils_Get::processFilter($this->_filters, $this->_defaults);
    CRM_Report_Utils_Get::processGroupBy($groupBys, $this->_defaults);
    CRM_Report_Utils_Get::processFields($reportFields, $this->_defaults);
    CRM_Report_Utils_Get::processChart($this->_defaults);

    if ($this->_force && !$this->noController) {
      $this->_formValues = $this->_defaults;
      $this->postProcess();
    }
  }

  /**
   * Setter for $_params.
   *
   * @param array $params
   */
  public function setParams($params) {
    if (empty($params)) {
      $this->_params = $params;
      return;
    }
    $extendedFieldKeys = $this->getConfiguredFieldsFlatArray();
    if(!empty($extendedFieldKeys)) {
      $fields = $params['fields'];
      if (isset($this->_formValues['extended_fields'])) {
        foreach ($this->_formValues['extended_fields'] as $index => $extended_field) {
          $fieldName = $extended_field['name'];
          if (!isset($fields[$fieldName])) {
            unset($this->_formValues['extended_fields'][$index]);
          }
        }
        $fieldsToAdd = array_diff_key($fields, $extendedFieldKeys);
        foreach (array_keys($fieldsToAdd) as $fieldName) {
          $this->_formValues['extended_fields'][] = [
            'name' => $fieldName,
            'title' => $this->getMetadataByType('fields')[$fieldName]['title']
          ];
        }
        // We use array_merge to re-index from 0
        $params['extended_fields'] = array_merge($this->_formValues['extended_fields']);
      }
    }
    $this->_params = $params;
  }

  /**
   * Get metadata for a particular type.
   *
   * @param string $type
   *   - fields
   *   - filters
   *   - join_filters
   *   - group_bys
   *   - order_bys
   *
   * @return array
   */
  protected function getMetadataByType($type) {
    return $this->getMetadata()[$type];
  }

  /**
   * Get metadata for a particular type.
   *
   * @param string $type
   *   - fields
   *   - filters
   *   - join_filters
   *   - group_bys
   *   - order_bys
   *
   * @return array
   */
  protected function getMetadataByAlias($type) {
    $metadata = $this->getMetadata()[$type];
    $return = [];
    foreach ($metadata as $key => $value) {
      $return[$value['alias']] = $value;
    }
    return $return;
  }

  /**
   * Generate the SELECT clause and set class variable $_select.
   */
  function select() {
    if ($this->_preConstrain && !$this->_preConstrained) {
      $this->_select = " SELECT DISTINCT {$this->_aliases[$this->_baseTable]}.id";
      return;
    }

    if ($this->_customGroupAggregates) {
      return;
    }
    $this->storeGroupByArray();
    $this->storeOrderByArray();
    $this->unsetBaseTableStatsFieldsWhereNoGroupBy();

    $selectedFields = $this->getSelectedFields();

    $select = [];
    // Where we need fields for a having clause & the are not selected we
    // add them to the select clause (but not to headers because - hey
    // you didn't ask for them).
    $havingFields = $this->getSelectedHavings();
    $havingsToAdd = array_diff_key($havingFields, $selectedFields);
    foreach ($havingsToAdd as $fieldName => $spec) {
      $select[$fieldName] = "{$spec['selectAlias']} as {$spec['table_name']}_{$fieldName}";
    }

    foreach ($this->getOrderBysNotInSelectedFields() as $fieldName => $spec) {
      $select[$fieldName . '_ordering'] = $this->getBasicFieldSelectClause($spec, $spec['alias']);
    }

    foreach ($selectedFields as $fieldName => $field) {
      $this->addAdditionalRequiredFields($field, $field['table_name']);
      $tableName = $field['table_name'];
      // 1. In many cases we want select clause to be built in slightly different way
      // for a particular field of a particular type.
      // 2. This method when used should receive params by reference and modify $this->_columnHeaders
      // as needed.
      $selectClause = $this->selectClause($tableName, 'fields', $fieldName, $field);
      if ($selectClause) {
        $select[$fieldName] = $selectClause;
        continue;
      }

      // include statistics columns only if set
      if (!empty($field['statistics']) && !empty($this->_groupByArray)) {
        $select = $this->addStatisticsToSelect($field, $tableName, $fieldName, $select);
      }
      else {
        $alias = isset($field['alias']) ? $field['alias'] : "{$tableName}_{$fieldName}";
        $this->_columnHeaders[$field['alias']]['title'] = CRM_Utils_Array::value('title', $field);
        $this->_selectAliases[] = $alias;
        $this->_columnHeaders[$field['alias']]['type'] = CRM_Utils_Array::value('type', $field);
        $select[$fieldName] = $this->getBasicFieldSelectClause($field, $alias);
      }
    }

    foreach ($this->_columns as $tableName => $table) {
      // select for group bys
      if (array_key_exists('group_bys', $table)) {
        foreach ($this->_columns[$tableName]['group_bys'] as $fieldName => $field) {
          // 1. In many cases we want select clause to be built in slightly different way
          // for a particular field of a particular type.
          // 2. This method when used should receive params by reference and modify $this->_columnHeaders
          // as needed.
          $selectClause = $this->selectClause($tableName, 'group_bys', $fieldName, $field);
          if ($selectClause) {
            $select[] = $selectClause;
            continue;
          }

          if (!empty($this->_params['group_bys']) &&
            !empty($this->_params['group_bys'][$fieldName]) &&
            !empty($this->_params['group_bys_freq'])
          ) {
            switch (CRM_Utils_Array::value($fieldName, $this->_params['group_bys_freq'])) {
              case 'YEARWEEK':
                $select[] = "DATE_SUB({$field['dbAlias']}, INTERVAL WEEKDAY({$field['dbAlias']}) DAY) AS {$tableName}_{$fieldName}_start";
                $select[] = "YEARWEEK({$field['dbAlias']}) AS {$tableName}_{$fieldName}_subtotal";
                $select[] = "WEEKOFYEAR({$field['dbAlias']}) AS {$tableName}_{$fieldName}_interval";
                $field['title'] = 'Week';
                break;

              case 'YEAR':
                $select[] = "MAKEDATE(YEAR({$field['dbAlias']}), 1)  AS {$tableName}_{$fieldName}_start";
                $select[] = "YEAR({$field['dbAlias']}) AS {$tableName}_{$fieldName}_subtotal";
                $select[] = "YEAR({$field['dbAlias']}) AS {$tableName}_{$fieldName}_interval";
                $field['title'] = 'Year';
                break;

              case 'MONTH':
                $select[] = "DATE_SUB({$field['dbAlias']}, INTERVAL (DAYOFMONTH({$field['dbAlias']})-1) DAY) as {$tableName}_{$fieldName}_start";
                $select[] = "MONTH({$field['dbAlias']}) AS {$tableName}_{$fieldName}_subtotal";
                $select[] = "MONTHNAME({$field['dbAlias']}) AS {$tableName}_{$fieldName}_interval";
                $field['title'] = 'Month';
                break;

              case 'QUARTER':
                $select[] = "STR_TO_DATE(CONCAT( 3 * QUARTER( {$field['dbAlias']} ) -2 , '/', '1', '/', YEAR( {$field['dbAlias']} ) ), '%m/%d/%Y') AS {$tableName}_{$fieldName}_start";
                $select[] = "QUARTER({$field['dbAlias']}) AS {$tableName}_{$fieldName}_subtotal";
                $select[] = "QUARTER({$field['dbAlias']}) AS {$tableName}_{$fieldName}_interval";
                $field['title'] = 'Quarter';
                break;
            }
            // for graphs and charts -
            if (!empty($this->_params['group_bys_freq'][$fieldName])) {
              $this->_interval = $field['title'];
              $this->_columnHeaders["{$tableName}_{$fieldName}_start"]['title']
                = $field['title'] . ' Beginning';
              $this->_columnHeaders["{$tableName}_{$fieldName}_start"]['type'] = $field['type'];
              $this->_columnHeaders["{$tableName}_{$fieldName}_start"]['group_by'] = $this->_params['group_bys_freq'][$fieldName];

              // just to make sure these values are transferred to rows.
              // since we 'll need them for calculation purpose,
              // e.g making subtotals look nicer or graphs
              $this->_columnHeaders["{$tableName}_{$fieldName}_interval"] = array('no_display' => TRUE);
              $this->_columnHeaders["{$tableName}_{$fieldName}_subtotal"] = array('no_display' => TRUE);
            }
          }
        }
      }
    }

    if (empty($select)) {
      // CRM-21412 Do not give fatal error on report when no fields selected
      $select = array(1);
    }

    $this->_selectClauses = $select;
    $this->_select = "SELECT " . implode(', ', $select) . " ";
  }

  /**
   * Function to do a simple cross-tab.
   *
   * Generally a rowHeader and a columnHeader will be defined.
   *
   * Column Header is optional - in which case a single total column will show.
   */
  function aggregateSelect() {
    if (empty($this->_customGroupAggregates)) {
      return;
    }

    $columnFields = $this->getFieldBreakdownForAggregates('column');
    $rowFields = $this->getFieldBreakdownForAggregates('row');
    $selectedTables = array();
    $rowColumns = $this->extractCustomFields($rowFields, $selectedTables, 'row_header');

    if (empty($rowColumns)) {
      if (empty($rowFields)) {
        $this->addRowHeader(FALSE, [], FALSE);
      }
      else {
        foreach ($rowFields as $field => $fieldDetails) {
          //only one but we don't know the name
          //we wrote this as purely a custom field against custom field. In process of refactoring to allow
          // another field like event - so here we have no custom field .. must be a non custom field...
          $tableAlias = $fieldDetails[0];
          $tableName = array_search($tableAlias, $this->_aliases);
          $fieldAlias = str_replace('-', '_', $tableName . '_' . $field);
          $this->addRowHeader($tableAlias, $this->getMetadata()['metadata'][$tableName . '_' . $field], $fieldAlias, $this->getPropertyForField($field, 'title', $tableName));
        }
      }

    }
    else {
      $rowHeader = $this->_params['aggregate_row_headers'];
      $rowHeaderFieldName = $rowColumns[$rowHeader]['name'];
      $this->_columnHeaders[$rowHeaderFieldName] = [
        'title' => $rowColumns[$rowHeader]['title']
      ];
    }
    $columnColumns = $this->extractCustomFields($columnFields, $selectedTables, 'column_header');
    if (empty($columnColumns)) {
      foreach ($columnFields as $field => $fieldDetails) { //only one but we don't know the name
        //we wrote this as purely a custom field against custom field. In process of refactoring to allow
        // another field like event - so here we have no custom field .. must be a non custom field...
        if (empty($fieldDetails)) {
          // This could happen if no column is specified - which is valid - resulting in
          // just one total column.
          $this->addColumnAggregateSelect('', '', array());
          continue;
        }
        $tableAlias = $fieldDetails[0];
        $spec = array();
        $tableName = array_search($tableAlias, $this->_aliases);
        if (!empty($this->_columns[$tableName]['metadata'][$field])) {
          $spec = $this->_columns[$tableName]['metadata'][$field];
        }
        else {
          foreach ($this->_columns[$tableName]['metadata'] as $fieldSpec) {
            if ($fieldSpec['name'] == $field) {
              $spec = $fieldSpec;
            }
          }
        }
        $fieldName = !empty($spec['name']) ? $spec['name'] : $field;
        $this->addColumnAggregateSelect($fieldName, $spec['dbAlias'], $spec);
      }
    }
    foreach ($selectedTables as $selectedTable => $properties) {
      $extendsTable = $properties['extends_table'];
      $this->_extraFrom .= "
      LEFT JOIN {$properties['name']} $selectedTable ON {$selectedTable}.entity_id = {$this->_aliases[$extendsTable]}.id";
    }
  }

  /**
   * Get property for a specified field. Naming conventions are a bit shaky
   * so we use this function to avoid constantly doing this work.
   *
   * @param string $fieldName
   * @param string $property
   * @param string $tableName
   *
   * @return mixed
   * @throws \Exception
   */
  function getPropertyForField($fieldName, $property, $tableName) {
    if (isset($this->_columns[$tableName]['metadata'])) {
      if (isset($this->_columns[$tableName]['metadata'][$fieldName])) {
        return $this->_columns[$tableName]['metadata'][$fieldName][$property];
      }
      foreach ($this->_columns[$tableName]['metadata'] as $fieldSpec) {
        if ($fieldSpec['name'] == $fieldName) {
          return $fieldSpec[$property];
        }
      }
    }
    throw new Exception('No metadata found for ' . $fieldName . ' in table ' . $tableName);
  }

  /**
   * Add Select for pivot chart style report
   *
   * @param string $fieldName
   * @param string $dbAlias
   * @param array $spec
   *
   * @throws Exception
   */
  function addColumnAggregateSelect($fieldName, $dbAlias, $spec) {
    if (empty($fieldName)) {
      $this->addAggregateTotal($fieldName);
      return;
    }
    $options = $this->getCustomFieldOptions($spec);

    if (!empty($this->_params[$fieldName . '_value']) && CRM_Utils_Array::value($fieldName . '_op', $this->_params) == 'in') {
      $options['values'] = array_intersect_key($options, array_flip($this->_params[$fieldName . '_value']));
    }

    $filterSpec = array(
      'field' => array('name' => $fieldName),
      'table' => array('alias' => $spec['table_name'])
    );

    if ($this->getFilterFieldValue($spec)) {
      // for now we will literally just handle IN
      if ($filterSpec['field']['op'] == 'in') {
        $options = array_intersect_key($options, array_flip($filterSpec['field']['value']));
        $this->_aggregatesIncludeNULL = FALSE;
      }
    }

    foreach ($options as $option) {
      $fieldAlias = str_replace(array(
        '-',
        '+',
        '\/',
        '/',
        ')',
        '('
      ), '_', "{$fieldName}_" . strtolower(str_replace(' ', '', $option['value'])));

      // htmlType is set for custom data and tells us the field will be stored using hex(01) separators.
      if (!empty($spec['htmlType']) && in_array($spec['htmlType'], array(
          'CheckBox',
          'MultiSelect'
        ))
      ) {
        $this->_select .= " , SUM( CASE WHEN {$dbAlias} LIKE '%" . CRM_Core_DAO::VALUE_SEPARATOR . $option['value'] . CRM_Core_DAO::VALUE_SEPARATOR . "%' THEN 1 ELSE 0 END ) AS $fieldAlias ";
      }
      else {
        $this->_select .= " , SUM( CASE {$dbAlias} WHEN '{$option['value']}' THEN 1 ELSE 0 END ) AS $fieldAlias ";
      }
      $this->_columnHeaders[$fieldAlias] = array(
        'title' => $option['label'],
        'type' => CRM_Utils_Type::T_INT
      );
      $this->_statFields[] = $fieldAlias;
    }
    if ($this->_aggregatesIncludeNULL && !empty($this->_params['fields']['include_null'])) {
      $fieldAlias = "{$fieldName}_null";
      $this->_columnHeaders[$fieldAlias] = array(
        'title' => ts('Unknown'),
        'type' => CRM_Utils_Type::T_INT
      );
      $this->_select .= " , SUM( IF (({$dbAlias} IS NULL OR {$dbAlias} = ''), 1, 0)) AS $fieldAlias ";
      $this->_statFields[] = $fieldAlias;
    }
    if ($this->_aggregatesAddTotal) {
      $this->addAggregateTotal($fieldName);
    }
  }

  /**
   * Function exists to take what we know about the field & determine if the
   * report has been filtered by it.
   *
   * This is because there are multiple naming conventions in play.
   *
   * The name in $this->_params is
   *
   * contribution_financial_type_id_value
   *
   * where contribution_financial_type_id is the key of the field within the
   * the 'fields' array of one of the tables.
   *
   * We are currently only dealing with the situation where we have the field's
   * real name and the tables alias.
   *
   * @param array $spec
   *   Array containing the name descriptors we have.
   *   If a value is found it will be added to the spec.
   *
   * @return string
   */
  function getFilterFieldValue(&$spec) {
    $fieldName = $spec['table_name'] . '_' . $spec['name'];
    if (isset($this->_params[$fieldName . '_value'])) {
      $spec['field']['value'] = $this->_params[$fieldName . '_value'];
      $spec['field']['op'] = $this->_params[$fieldName . '_op'];
      return $this->_params[$fieldName . '_value'];
    }
    return '';
  }

  /**
   * @param $fieldName
   */
  function addAggregateTotal($fieldName) {
    $fieldAlias = "{$fieldName}_total";
    $this->_columnHeaders[$fieldAlias] = array(
      'title' => ts('Total'),
      'type' => CRM_Utils_Type::T_INT
    );
    $this->_select .= " , SUM( IF (1 = 1, 1, 0)) AS $fieldAlias ";
    $this->_statFields[] = $fieldAlias;
  }

  /**
   * From clause build where baseTable & fromClauses are defined.
   */
  function from() {
    if (!empty($this->_baseTable)) {
      if (!empty($this->_aliases['civicrm_contact'])) {
        $this->buildACLClause($this->_aliases['civicrm_contact']);
      }

      $this->_from = "FROM {$this->_baseTable} " . (empty($this->_aliases[$this->_baseTable]) ? '' : $this->_aliases[$this->_baseTable]);
      $availableClauses = $this->getAvailableJoins();
      foreach ($this->fromClauses() as $clauseKey => $fromClause) {
        if (is_array($fromClause)) {
          // we might be adding the same join more than once (should have made it an array from the start)
          $fn = $availableClauses[$clauseKey]['callback'];
          foreach ($fromClause as $fromTable => $fromSpec) {
            $this->$fn($fromTable, $fromSpec);
          }
        }
        else {
          $fn = $availableClauses[$fromClause]['callback'];
          $extra = array();
          if (isset($this->_joinFilters[$fromClause])) {
            $extra = $this->_joinFilters[$fromClause];
          }
          $append = $this->$fn('', $extra);
          if ($append && !empty($extra)) {
            foreach ($extra as $table => $field) {
              $this->_from .= " AND {$this->_aliases[$table]}.{$field} ";
            }
          }
        }

      }
      if (strstr($this->_from, 'civicrm_contact')) {
        $this->_from .= $this->_aclFrom;
      }
      $this->_from .= $this->_extraFrom;
    }
  }

  /**
   *  constrainedWhere applies to Where clauses applied AFTER the
   * 'pre-constrained' report universe is created.
   *
   * For example the universe might be limited to a group of contacts in the first round
   * in the second round this Where clause is applied
   */
  function constrainedWhere() {
  }

  /**
   * @return array
   */
  function fromClauses() {
    return array();
  }

  /**
   * We're overriding the parent class so we can populate a 'group_by' array for other functions use
   * e.g. editable fields are turned off when groupby is used
   */
  function groupBy() {
    $this->storeGroupByArray();
    $groupedColumns = array();
    if (!empty($this->_groupByArray)) {
      foreach (array_keys($this->_groupByArray) as $groupByColumn) {
        if (isset($this->_columnHeaders[$groupByColumn])) {
          $columnValues = $this->_columnHeaders[$groupByColumn];
          $groupedColumns[$groupByColumn] = $columnValues;
        }
      }
      $this->_columnHeaders = $groupedColumns + $this->_columnHeaders;
      $this->_groupBy = "GROUP BY " . implode(', ', $this->_groupByArray);
      if (!empty($this->_sections)) {
        // if we have group bys & sections the sections need to be grouped
        //otherwise we won't interfere with the parent class
        // assumption is that they shouldn't co-exist but there are many reasons for setting group bys
        // that don't relate to the confusion of the odd form appearance
        foreach ($this->_sections as $section) {
          $this->_groupBy .= ", " . $section['dbAlias'];
        }
      }
      if (!empty($this->_statFields) && empty($this->_orderByArray) &&
        (count($this->_groupByArray) <= 1 || !$this->_having)
        && $this->_rollup !== FALSE
        && !$this->isInProcessOfPreconstraining()
      ) {
        $this->_rollup = " WITH ROLLUP";
      }
      $this->_groupBy .= ' ' . $this->_rollup;
    }
  }

  /**
   * Define order by clause.
   */
  function orderBy() {
    parent::orderBy();
  }

  /**
   * Overriden to draw source info from 'metadata' and not rely on it being in 'fields'.
   *
   * In some cases other functions want to know which fields are selected for ordering by
   * Separating this into a separate function allows it to be called separately from constructing
   * the order by clause
   */
  function storeOrderByArray() {

    $isGroupBy = !empty($this->_groupByArray);
    $selectedOrderBys = $this->getSelectedOrderBys();
    $selectedFields = $this->getSelectedFields();
    $selectedFieldsToAdd = array_diff_key($selectedOrderBys, $selectedFields);
    foreach ($selectedFieldsToAdd as $fieldName => $field) {
      $this->_params['fields'][$fieldName] = 1;
      $this->_columns[$field['table_name']][$field['column']]['no_display'] = 1;
    }

    $orderBys = [];

    if (!empty($selectedOrderBys)) {
      // Process order_bys in user-specified order
      foreach ($selectedOrderBys as $selectedOrderBy) {
        $fieldAlias = $selectedOrderBy['alias'];
        // Record any section headers for assignment to the template
        if (CRM_Utils_Array::value('section', $selectedOrderBy)) {
          $this->_sections[$selectedOrderBy['alias']] = $selectedOrderBy;
        }
        $fieldAlias = $selectedOrderBy['alias'];
        if ($isGroupBy && !empty($selectedOrderBy['statistics']) && !empty($selectedOrderBy['statistics']['sum'])) {
          $fieldAlias .= '_sum';
        }
        $orderBys[] = "({$fieldAlias}) {$selectedOrderBy['order']}";
      }
    }

    $this->_orderByArray = $orderBys;
    $this->assign('sections', $this->_sections);
  }

  /**
   * Store join filters as an array in a similar way to the filters.
   */
  protected function storeJoinFiltersArray() {
    foreach ($this->getSelectedJoinFilters() as $fieldName => $field) {
        $clause = NULL;
        $clause = $this->generateFilterClause($field, $fieldName, 'join_filter_');
        if (!empty($clause)) {
          $this->joinClauses[$field['table_name']][] = $clause;
          if ($field['name'] == 'relationship_type_id') {
            $relationshipLabel = civicrm_api3('relationship_type', 'getvalue', array(
              'id' => $this->_params["{$fieldName}_value"],
              'return' => 'label_a_b',
            ));
          foreach (array_keys($this->_columns) as $columnLabel) {
            if (stristr($columnLabel, 'related_civicrm')) {
              if (!empty($this->_columns[$columnLabel]['fields'])) {
                foreach ($this->_columns[$columnLabel]['fields'] as &$field) {
                  $field['title'] = str_replace('Related Contact', $relationshipLabel, $field['title']);
                  $field['title'] = str_replace('of ', '', $field['title']);
                }
              }
            }
          }
        }
      }
    }
  }

  /**
   * We are switching to saving metadata in ... metadata.
   *
   * Make sure this is set.
   *
   * The reason for this is endless bugginess when filters, groupbys etc rely on metadata
   * coming from fields.
   *
   * @param string $tableName
   *
   * @return array
   *   The table spec with the metadata key added.
   */
  function setMetadataForTable($tableName) {
    if (CRM_Utils_Array::value('fields', $this->_columns[$tableName])) {
      $this->_columns[$tableName]['metadata'] = $this->_columns[$tableName]['fields'];
    }
    elseif (CRM_Utils_Array::value('filters', $this->_columns[$tableName])) {
      $this->_columns[$tableName]['metadata'] = $this->_columns[$tableName]['filters'];
    }
    else {
      $this->_columns[$tableName]['metadata'] = array();
    }
    return $this->_columns[$tableName];
  }

  /**
   * Store group bys into array - so we can check elsewhere (e.g editable fields) what is grouped.
   *
   * Overriden to draw source info from 'metadata' and not rely on it being in 'fields'.
   */
  function storeGroupByArray() {

    if (CRM_Utils_Array::value('group_bys', $this->_params) &&
      is_array($this->_params['group_bys']) &&
      !empty($this->_params['group_bys'])
    ) {
      foreach ($this->getSelectedGroupBys() as $fieldName => $fieldData) {
        $groupByKey = $fieldData['alias'];
        if (!empty($fieldData['frequency']) && !empty($this->_params['group_bys_freq'])) {
          $groupByFrequency = CRM_Utils_Array::value($fieldName, $this->_params['group_bys_freq']);
          switch ($groupByFrequency) {
            case 'FISCALYEAR' :
              $this->_groupByArray[$groupByKey . '_start'] = self::fiscalYearOffset($fieldData['dbAlias']);
              break;

            case 'YEAR' :
              $this->_groupByArray[$groupByKey . '_start'] = " {$groupByFrequency}({$fieldData['dbAlias']})";
              break;

            default :
              $this->_groupByArray[$groupByKey . '_start'] =
                "EXTRACT(YEAR_{$groupByFrequency} FROM {$fieldData['dbAlias']})";
              break;
          }

        }
        else {
          if (!in_array($fieldData['dbAlias'], $this->_groupByArray)) {
            $this->_groupByArray[$groupByKey] = $fieldData['dbAlias'];
          }
        }
      }
    }

    $this->calculateStatsFields();
    $this->isForceGroupBy = (!empty($this->_statFields) && !$this->_noGroupBY && isset($this->_aliases[$this->_baseTable]));
    // if a stat field has been selected then do a group by - this is not in parent
    if ($this->isForceGroupBy && empty($this->_groupByArray)) {
      $this->_groupByArray[$this->_baseTable . '_id'] = $this->_aliases[$this->_baseTable] . ".id";

    }
  }

  protected function isSelfGrouped() {
    if ($this->_groupByArray == array($this->_baseTable . '_id' => $this->_aliases[$this->_baseTable] . ".id")) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Calculate whether we have stats fields.
   *
   * This will cause a group by.
   */
  protected function calculateStatsFields() {
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('metadata', $table)) {
        foreach ($table['metadata'] as $fieldName => $field) {
          if (!empty($field['required']) ||
            !empty($this->_params['fields'][$fieldName])
          ) {
            if (!empty($field['statistics'])) {
              foreach ($field['statistics'] as $stat => $label) {
                $alias = "{$tableName}_{$fieldName}_{$stat}";
                switch (strtolower($stat)) {
                  case 'max':
                  case 'sum':
                  case 'count':
                  case 'count_distinct':
                  case 'avg':
                    $this->_statFields[$label] = $alias;
                    break;
                }
              }
            }
          }
        }
      }
    }
  }

  /**
   * It's not useful to do stats on the base table if no group by is going on
   * the table is likely to be involved in left joins & give a bad answer for no reason
   * (still pondering how to deal with turned totaling on & off appropriately)
   *
   **/
  protected function unsetBaseTableStatsFieldsWhereNoGroupBy() {
    if (empty($this->_groupByArray) && !empty($this->_columns[$this->_baseTable]['fields'])) {
      foreach ($this->_columns[$this->_baseTable]['fields'] as $fieldname => $field) {
        if (isset($field['statistics'])) {
          unset($this->_columns[$this->_baseTable]['fields'][$fieldname]['statistics']);
        }
      }
    }
  }

  /**
   * Add filters to report.
   *
   * Case self::OP_SINGLEDATE added for reports which deal with 'before date x'
   * versus after date x. e.g Sybunt, fundraising reports.
   *
   * Handling for join_filters.
   */
  public function addFilters() {
    foreach (array('filters', 'join_filters') as $filterString) {
      $filters = $filterGroups = array();
      $count = 1;
      foreach ($this->getMetadataByType($filterString) as $fieldName => $field) {
        $table = $field['table_name'];
        if ($filterString === 'filters') {
          $filterGroups[$table] = array(
            'group_title' => $this->_columns[$table]['group_title'],
            'use_accordian_for_field_selection' => TRUE,
          );
        }
        $prefix = ($filterString === 'join_filters') ? 'join_filter_' : '';
        $filters[$table][$prefix . $fieldName] = $field;
        $this->addFilterFieldsToReport($field, $fieldName, $filters, $table, $count, $prefix);
      }

      if (!empty($filters) && $filterString == 'filters') {
        $this->tabs['Filters'] = array(
          'title' => ts('Filters'),
          'tpl' => 'Filters',
          'div_label' => 'set-filters',
        );
        $this->assign('filterGroups', $filterGroups);
      }
      $this->assign($filterString, $filters);
    }
  }

  /**
   * Function to assign the tabs to the template in the correct order.
   *
   * We want the tabs to wind up in this order (if not overridden).
   *
   *   - Field Selection
   *   - Group Bys
   *   - Order Bys
   *   - Other Options
   *   - Filters
   */
  protected function assignTabs() {
    $order = array(
      'Aggregate',
      'FieldSelection',
      'GroupBy',
      'OrderBy',
      'ReportOptions',
      'Filters',
    );
    $order = array_intersect_key(array_fill_keys($order, 1), $this->tabs);
    $order = array_merge($order, $this->tabs);
    if (isset($this->tabs['Aggregate'])) {
      unset($this->tabs['FieldSelection']);
      unset($this->tabs['GroupBy']);
      unset($this->tabs['OrderBy']);
    }
    $this->assign('tabs', $order);
  }


  /**
   * Add columns to report.
   */
  public function addColumns() {
    $options = array();
    $colGroups = [];

    foreach ($this->getMetadataByType('fields') as $fieldName => $field) {
      $tableName = $field['table_name'];
      $groupTitle = $this->_columns[$tableName]['group_title'];

      if (!$groupTitle && isset($table['group_title'])) {
        $groupTitle = $field['group_title'];
      }
      $colGroups[$tableName]['use_accordian_for_field_selection'] = TRUE;

      $colGroups[$tableName]['fields'][$fieldName] = CRM_Utils_Array::value('title', $field);
      if ($groupTitle && empty($colGroups[$tableName]['group_title'])) {
        $colGroups[$tableName]['group_title'] = $groupTitle;
      }
      $options[$fieldName] = CRM_Utils_Array::value('title', $field);

    }

    $this->addCheckBox("fields", ts('Select Columns'), $options, NULL,
      NULL, NULL, NULL, $this->_fourColumnAttribute, TRUE
    );
    if (!empty($colGroups)) {
      $this->tabs['FieldSelection'] = array(
        'title' => ts('Columns'),
        'tpl' => 'FieldSelection',
        'div_label' => 'col-groups',
      );

      // Note this assignment is only really required in buildForm. It is being 'over-called'
      // to reduce risk of being missed due to overridden functions.
      $this->assignTabs();
    }

    $this->assign('colGroups', $colGroups);
  }

  /**
   * Backport of 4.6
   *
   * Add options defined in $this->_options to the report.
   */
  public function addOptions() {
    if (!empty($this->_options)) {
      // FIXME: For now lets build all elements as checkboxes.
      // Once we clear with the format we can build elements based on type

      foreach ($this->_options as $fieldName => $field) {
        $options = array();
        if ($field['type'] == 'select') {
          $this->addElement('select', "{$fieldName}", $field['title'], $field['options']);
        }
        elseif ($field['type'] == 'checkbox') {
          $options[$field['title']] = $fieldName;
          $this->addCheckBox($fieldName, NULL,
            $options, NULL,
            NULL, NULL, NULL, $this->_fourColumnAttribute
          );
        }
        else {
          $this->addElement($field['type'], $fieldName, $field['title']);
        }
      }
    }
    if (!empty($this->_options)) {
      $this->tabs['ReportOptions'] = array(
        'title' => ts('Display Options'),
        'tpl' => 'ReportOptions',
        'div_label' => 'other-options',
      );
    }
    $this->assign('otherOptions', $this->_options);
    $this->assignTabs();
  }

  /**
   * Add order bys
   */
  public function addOrderBys() {
    $options = array();

    foreach ($this->getMetadataByType('order_bys') as $fieldName => $field) {
      $options[$fieldName] = $field['title'];
    }

    asort($options);

    $this->assign('orderByOptions', $options);
    if (!empty($options)) {
      $this->tabs['OrderBy'] = array(
        'title' => ts('Sorting'),
        'tpl' => 'OrderBy',
        'div_label' => 'order-by-elements',
      );
    }

    if (!empty($options)) {
      $options = array(
          '-' => ' - none - ',
        ) + $options;
      for ($i = 1; $i <= 5; $i++) {
        $this->addElement('select', "order_bys[{$i}][column]", ts('Order by Column'), $options);
        $this->addElement('select', "order_bys[{$i}][order]", ts('Order by Order'), array(
          'ASC' => 'Ascending',
          'DESC' => 'Descending',
        ));
        $this->addElement('checkbox', "order_bys[{$i}][section]", ts('Order by Section'), FALSE, array('id' => "order_by_section_$i"));
        $this->addElement('checkbox', "order_bys[{$i}][pageBreak]", ts('Page Break'), FALSE, array('id' => "order_by_pagebreak_$i"));
      }
    }
    $this->assignTabs();
  }


  /**
   * Set default values.
   *
   * We have over-ridden this to provide the option of setting single date fields with defaults.
   *
   * @param boolean $freeze
   *
   * @return array
   */
  public function setDefaultValues($freeze = TRUE) {
    $freezeGroup = array();
    $overrides = [];

    // FIXME: generalizing form field naming conventions would reduce
    // Lots of lines below.
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          if (empty($field['no_display'])) {
            if (isset($field['required'])) {
              // set default
              $this->_defaults['fields'][$fieldName] = 1;

              if ($freeze) {
                // find element object, so that we could use quickform's freeze method
                // for required elements
                $obj = $this->getElementFromGroup("fields", $fieldName);
                if ($obj) {
                  $freezeGroup[] = $obj;
                }
              }
            }
            elseif (isset($field['default'])) {
              $this->_defaults['fields'][$fieldName] = $field['default'];
            }
          }
        }
      }

      if (array_key_exists('group_bys', $table)) {
        foreach ($table['group_bys'] as $fieldName => $field) {
          if (isset($field['default'])) {
            if (!empty($field['frequency'])) {
              $this->_defaults['group_bys_freq'][$fieldName] = 'MONTH';
            }
            $this->_defaults['group_bys'][$fieldName] = $field['default'];
          }
        }
      }
      if (array_key_exists('filters', $table)) {
        foreach ($table['filters'] as $fieldName => $field) {
          if (isset($field['default'])) {
            if (CRM_Utils_Array::value('type', $field) & CRM_Utils_Type::T_DATE
              // This is the overriden part.
              && !(CRM_Utils_Array::value('operatorType', $field) == self::OP_SINGLEDATE)
            ) {
              if (is_array($field['default'])) {
                $this->_defaults["{$fieldName}_from"] = CRM_Utils_Array::value('from', $field['default']);
                $this->_defaults["{$fieldName}_to"] = CRM_Utils_Array::value('to', $field['default']);
                $this->_defaults["{$fieldName}_relative"] = 0;
              }
              else {
                $this->_defaults["{$fieldName}_relative"] = $field['default'];
              }
            }
            else {
              $this->_defaults["{$fieldName}_value"] = $field['default'];
            }
          }
          //assign default value as "in" for multiselect
          //operator, To freeze the select element
          if (CRM_Utils_Array::value('operatorType', $field) ==
            CRM_Report_Form::OP_MULTISELECT
          ) {
            $this->_defaults["{$fieldName}_op"] = 'in';
          }
          if (CRM_Utils_Array::value('operatorType', $field) ==
            // This is the OP_ENTITY_REF value. The constant is not registered in 4.4.
            256
          ) {
            $this->_defaults["{$fieldName}_op"] = 'in';
          }
          elseif (CRM_Utils_Array::value('operatorType', $field) ==
            CRM_Report_Form::OP_MULTISELECT_SEPARATOR
          ) {
            $this->_defaults["{$fieldName}_op"] = 'mhas';
          }
          elseif ($op = CRM_Utils_Array::value('default_op', $field)) {
            $this->_defaults["{$fieldName}_op"] = $op;
          }
        }
      }

      if (
        empty($this->_formValues['order_bys']) &&
        (array_key_exists('order_bys', $table) &&
          is_array($table['order_bys']))
      ) {
        if (!array_key_exists('order_bys', $this->_defaults)) {
          $this->_defaults['order_bys'] = array();
        }
        foreach ($table['order_bys'] as $fieldName => $field) {
          if (!empty($field['default']) || !empty($field['default_order']) ||
            CRM_Utils_Array::value('default_is_section', $field) ||
            !empty($field['default_weight'])
          ) {
            $order_by = array(
              'column' => $fieldName,
              'order' => CRM_Utils_Array::value('default_order', $field, 'ASC'),
              'section' => CRM_Utils_Array::value('default_is_section', $field, 0),
            );

            if (!empty($field['default_weight'])) {
              $this->_defaults['order_bys'][(int) $field['default_weight']] = $order_by;
            }
            else {
              array_unshift($this->_defaults['order_bys'], $order_by);
            }
          }
        }
      }

      foreach ($this->_options as $fieldName => $field) {
        if (isset($field['default'])) {
          $this->_defaults['options'][$fieldName] = $field['default'];
        }
      }
    }

    if (!empty($this->_submitValues)) {
      $this->preProcessOrderBy($this->_submitValues);
    }
    else {
      $this->preProcessOrderBy($this->_defaults);
    }

    // lets finish freezing task here itself
    if (!empty($freezeGroup)) {
      foreach ($freezeGroup as $elem) {
        $elem->freeze();
      }
    }

    if ($this->_formValues) {
      $this->_defaults = array_merge($this->_defaults, $this->_formValues, $overrides);
    }

    if ($this->_instanceValues) {
      $this->_defaults = array_merge($this->_defaults, $this->_instanceValues);
    }

    CRM_Report_Form_Instance::setDefaultValues($this, $this->_defaults);
    $contact_id = $this->getContactIdFilter();
    $this->_defaults[$this->contactIDField . '_value'] = $contact_id;
    $this->_defaults[$this->contactIDField . '_op'] = 'in';
    return $this->_defaults;
  }

  /**
   *  Note: $fieldName param allows inheriting class to build operationPairs
   * specific to a field.
   *
   * @param string $type
   * @param null $fieldName
   *
   * @return array
   */
  function getOperationPair($type = "string", $fieldName = NULL) {
    if ($type == self::OP_SINGLEDATE) {
      return array(
        'to' => ts('Until Date'),
        'from' => ts('From Date'),
      );
    }
    if ($type === self::OP_STRING) {
      return [
        'has' => ts('Contains'),
        'sw' => ts('Starts with'),
        'ew' => ts('Ends with'),
        'nhas' => ts('Does not contain'),
        'eq' => ts('Is equal to'),
        'neq' => ts('Is not equal to'),
        'nll' => ts('Is empty (Null)'),
        'nnll' => ts('Is not empty (Null)'),
        'rlike' => ts('Regex is true')
      ];
    }
    return parent::getOperationPair($type, $fieldName);

  }

  /**
   * Wrapper for retrieving options for a field.
   *
   * @param string $entity
   * @param string $field
   * @param string $action
   *
   * @return array
   */
  protected function _getOptions($entity, $field, $action = 'get') {
    static $allOptions = array();
    $key = "{$entity}_{$field}";
    if (isset($allOptions[$key])) {
      return $allOptions[$key];
    }
    $options = civicrm_api3($entity, 'getoptions', array(
      'field' => $field,
      'action' => $action
    ));
    $allOptions[$key] = $options['values'];
    return $allOptions[$key];
  }

  /**
   * @param $rows
   *
   * @return mixed
   */
  function statistics(&$rows) {
    return parent::statistics($rows);
  }

  /**
   * re-order column headers.
   *
   * This is based on the input field 'fields' and shuffling group bys to the left.
   */
  function reOrderColumnHeaders() {
    $fieldMap = array();
    foreach ($this->_columns as $tableName => $table) {
      if (!empty($table['fields']) && is_array($table['fields']) && !empty($this->_params['fields'])) {
        foreach ($table['fields'] as $fieldName => $fieldSpec) {
          if (!empty($this->_params['fields'][$fieldName])) {
            $fieldMap[$fieldName] = $tableName . '_' . $fieldName;
          }
        }
      }
    }

    foreach (array_keys($this->_groupByArray) as $groupByField) {
      if (stristr($groupByField, '_start')) {
        $ungroupedField = str_replace(array('_start'), '', $groupByField);
        unset($this->_columnHeaders[$ungroupedField]);
        $fieldMapKey = array_search($ungroupedField, $fieldMap);
        if ($fieldMapKey) {
          $fieldMap[$fieldMapKey] = $fieldMap[$fieldMapKey] . '_start';
        }
      }
    }

    $fieldMap = array_merge(CRM_Utils_Array::value('fields', $this->_params, array()), $fieldMap);
    $this->_columnHeaders = array_merge(array_intersect_key(array_flip($fieldMap), $this->_columnHeaders), $this->_columnHeaders);
  }

  /**
   * Mostly overriding this for ease of adding in debug.
   */
  function postProcess() {

    try {
      if (!empty($this->_aclTable) && CRM_Utils_Array::value($this->_aclTable, $this->_aliases)) {
        $this->buildACLClause($this->_aliases[$this->_aclTable]);
      }
      $this->beginPostProcess();

      $sql = $this->buildQuery();
      $this->reOrderColumnHeaders();
      // build array of result based on column headers. This method also allows
      // modifying column headers before using it to build result set i.e $rows.
      $rows = array();
      $this->addToDeveloperTab($sql);
      $this->buildRows($sql, $rows);
      $this->addAggregatePercentRow($rows);
      // format result set.
      $this->formatDisplay($rows);

      // assign variables to templates
      $this->doTemplateAssignment($rows);

      // do print / pdf / instance stuff if needed
      $this->endPostProcess($rows);
    } catch (Exception $e) {
      $err['message'] = $e->getMessage();
      $err['trace'] = $e->getTrace();

      foreach ($err['trace'] as $fn) {
        if ($fn['function'] == 'raiseError') {
          foreach ($fn['args'] as $arg) {
            $err['sql_error'] = $arg;
          }
        }
        if ($fn['function'] == 'simpleQuery') {
          foreach ($fn['args'] as $arg) {
            $err['sql_query'] = $arg;
          }
        }
      }

      if (function_exists('dpm')) {
        dpm($err);
        dpm($this->_columns);;
      }
      else {
        CRM_Core_Error::debug($err);
      }
    }
  }

  /**
   * Override parent to include additional storage action.
   */
  public function beginPostProcessCommon() {
    parent::beginPostProcessCommon();
    $this->storeParametersOnForm();
  }

  /**
   * Add a field as a stat sum field.
   *
   * @param $tableName
   * @param $fieldName
   * @param $field
   *
   * @return string
   */
  protected function selectStatSum(&$tableName, &$fieldName, &$field) {
    $alias = "{$tableName}_{$fieldName}_sum";
    $this->_columnHeaders[$alias]['title'] = CRM_Utils_Array::value('title', $field);
    $this->_columnHeaders[$alias]['type'] = CRM_Utils_Array::value('type', $field);
    $this->_statFields[CRM_Utils_Array::value('title', $field)] = $alias;
    $this->_selectAliases[] = $alias;
    return $alias;
  }

  /**
   * Add an extra row with percentages for a single row result to the chart (this is where
   * there is no grandTotal row
   *
   * @param array $rows
   */
  private function addAggregatePercentRow($rows) {
    if (!empty($this->_aggregatesAddPercentage) && count($rows) == 1 && $this->_aggregatesAddTotal) {
      foreach ($rows as $row) {
        $total = end($row);
        //   reset($row);
        $stats = array();
        foreach ($row as $key => $column) {
          $stats[$key] = $total ? sprintf("%.1f%%", $column / $total * 100) : '0.00%';
        }
        $this->assign('grandStat', $stats);
      }
    }
  }

  /**
   * Interpret the parameters into their various types.
   *
   * In the parent class this is done all over the show but here we want to figure
   * out what is on the form upfront and then deal with it separately.
   *
   * We define
   * $this->_custom_fields_selected
   * $this->_custom_fields_filters
   */
  protected function storeParametersOnForm() {
    foreach (array_keys($this->_columns) as $tableName) {
      foreach (array('filters', 'fields') as $fieldSet) {
        if (!isset($this->_columns[$tableName][$fieldSet])) {
          $this->_columns[$tableName][$fieldSet] = array();
        }
      }
      foreach ($this->_columns[$tableName]['filters'] as $fieldName => $field) {
        if (!empty($table['extends'])) {
          $this->_columns[$tableName][$fieldName]['metadata'] = $table['extends'];
        }
        if (empty($this->_columns[$tableName]['metadata'])) {
          $this->_columns[$tableName] = $this->setMetaDataForTable($tableName);
        }
        if (!isset($this->_columns[$tableName]['metadata']) || !isset($this->_columns[$tableName]['metadata'][$fieldName])) {
          // Need to get this down to none but for now...
          continue;
        }
        $this->availableFilters[$fieldName] = $this->_columns[$tableName]['metadata'][$fieldName];
      }
    }

    $this->_custom_fields_selected = CRM_Utils_Array::value('custom_fields', $this->_params, array());

    if ($this->_params === NULL) {
      $this->_params = array();
    }
    foreach ($this->_params as $key => $param) {
      if (substr($key, 0, 7) == 'custom_') {
        $splitField = explode('_', $key);
        $field = $splitField[0] . '_' . $splitField[1];
        foreach ($this->_columns as $table => $spec) {
          if (!empty($spec['filters'])
            && is_array($spec['filters'])
            && array_key_exists($field, $spec['filters'])
            && !empty($field)
            && (isset($this->_params[$field . '_value'])
              && $this->_params[$field . '_value'] != NULL
              || !empty($this->_params[$field . '_relative'])
            ) ||
            CRM_Utils_Array::value($field . '_op', $this->_params) == 'nll'
          ) {
            $fieldName = $this->mapFieldExtends($field, $spec);
            if (!in_array($fieldName, $this->_custom_fields_filters)) {
              $this->_custom_fields_filters[] = $this->mapFieldExtends($field, $spec);
            }
          }
        }
      }
    }
    if (!isset($this->_params['fields'])) {
      $this->_params['fields'] = array();
    }
  }

  /**
   * Over-written to allow pre-constraints
   *
   * @param boolean $applyLimit
   *
   * @return string
   */

  function buildQuery($applyLimit = TRUE) {
    if (empty($this->_params)) {
      $this->_params = $this->controller->exportValues($this->_name);
    }
    $this->buildGroupTempTable();
    $this->storeJoinFiltersArray();
    $this->storeWhereHavingClauseArray();
    $this->storeGroupByArray();
    $this->storeOrderByArray();
    $this->select();
    $this->from();
    $this->where();
    $this->aggregateSelect();
    $this->extendedCustomDataFrom();

    if ($this->isInProcessOfPreconstraining()) {
      $this->generateTempTable();
      $this->_preConstrained = TRUE;
      $this->select();
      $this->from();
      $this->extendedCustomDataFrom();
      $this->constrainedWhere();
      $this->aggregateSelect();
    }
    $this->orderBy();
    $this->groupBy();
    // order_by columns not selected for display need to be included in SELECT
    $unselectedSectionColumns = $this->unselectedSectionColumns();
    foreach ($unselectedSectionColumns as $alias => $section) {
      $this->_select .= ", {$section['dbAlias']} as {$alias}";
    }

    if ($applyLimit && !CRM_Utils_Array::value('charts', $this->_params)) {
      if (!empty($this->_params['number_of_rows_to_render'])) {
        $this->_dashBoardRowCount = $this->_params['number_of_rows_to_render'];
      }
      $this->limit();
    }

    CRM_Utils_Hook::alterReportVar('sql', $this, $this);
    $sql = "{$this->_select} {$this->_from} {$this->_where} {$this->_groupBy} {$this->_having} {$this->_orderBy} ";
    if (!$this->_rollup) {
      $sql .= $this->_limit;
    }
    return $sql;
  }

  /**
   * We are over-riding this because the current choice is NO acls or automatically adding contact.is_deleted
   * which is a pain when left joining form another table
   * @see CRM_Report_Form::buildACLClause($tableAlias)
   *
   * @param string $tableAlias
   *
   */
  function buildACLClause($tableAlias = 'contact_a') {
    list($this->_aclFrom, $this->_aclWhere) = CRM_Contact_BAO_Contact_Permission::cacheClause($tableAlias);
    if ($this->_skipACLContactDeletedClause && CRM_Core_Permission::check('access deleted contacts')) {
      if (trim($this->_aclWhere) == "{$tableAlias}.is_deleted = 0") {
        $this->_aclWhere = NULL;
      }
      else {
        $this->_aclWhere = str_replace("AND {$tableAlias}.is_deleted = 0", '', $this->_aclWhere);
      }
    }
  }

  /**
   * Generate a temp table to reflect the pre-constrained report group
   * This could be a group of contacts on whom we are going to do a series of contribution
   * comparisons.
   *
   * We apply where criteria from the form to generate this
   *
   * We create a temp table of their ids in the first instance
   * and use this as the base
   */
  function generateTempTable() {
    $tempTable = 'civicrm_report_temp_' . $this->_baseTable . date('d_H_I') . rand(1, 10000);
    $sql = "CREATE {$this->_temporary} TABLE $tempTable
      (`id` INT(10) UNSIGNED NULL DEFAULT '0',
        INDEX `id` (`id`)
      )
      COLLATE='utf8_unicode_ci'
      ENGINE=HEAP;";
    CRM_Core_DAO::executeQuery($sql);
    $sql = "INSERT INTO $tempTable
      {$this->_select} {$this->_from} {$this->_where} {$this->_limit} ";
    CRM_Core_DAO::executeQuery($sql);
    $this->_aliases[$tempTable] = $this->_aliases[$this->_baseTable];
    $this->_baseTable = $tempTable;
    $this->_tempTables['base'] = $tempTable;
  }

  /**
   * get name of template file
   *
   * @return string
   */
  function getTemplateFileName() {
    $defaultTpl = parent::getTemplateFileName();

    if (in_array($this->_outputMode, array('print', 'pdf'))) {
      if ($this->_params['templates']) {
        $defaultTpl = 'CRM/Extendedreport/Form/Report/CustomTemplates/' . $this->_params['templates'] . '.tpl';
      }
    }

    if (!CRM_Utils_File::isIncludable('templates/' . $defaultTpl)) {
      $defaultTpl = 'CRM/Report/Form.tpl';
    }
    return $defaultTpl;
  }

  /**
   * Over-ridden to handle order_bys.
   *
   * @param bool $addFields
   * @param array $permCustomGroupIds
   */
  function addCustomDataToColumns($addFields = TRUE, $permCustomGroupIds = array()) {
    if (empty($this->_customGroupExtends)) {
      return;
    }
    if (!is_array($this->_customGroupExtends)) {
      $this->_customGroupExtends = array(
        $this->_customGroupExtends
      );
    }
    $this->addCustomDataForEntities($this->_customGroupExtends);

  }

  protected function userHasAllCustomGroupAccess() {
    return CRM_Core_Permission::check('access all custom data');
  }

  /**
   * Add tab for selecting template.
   */
  function addTemplateSelector() {
    if (!empty($this->_templates)) {

      //$templatesDir = str_replace('CRM/Extendedreport', 'templates/CRM/Extendedreport', __DIR__);
      $this->add('select', 'templates', ts('Select Alternate Template'), $this->_templates, FALSE,
        array('id' => 'templates', 'title' => ts('- select -'),)
      );

      $this->tabs['Template'] = array(
        'title' => ts('Template'),
        'tpl' => 'Template',
        'div_label' => 'set-template',
      );
    }
  }

  /**
   * Take API Styled field and add extra params required in report class
   *
   * @param string $field
   */
  function getCustomFieldDetails(&$field) {
    $types = array(
      'Date' => CRM_Utils_Type::T_DATE,
      'Boolean' => CRM_Utils_Type::T_INT,
      'Int' => CRM_Utils_Type::T_INT,
      'Money' => CRM_Utils_Type::T_MONEY,
      'Float' => CRM_Utils_Type::T_FLOAT,
    );

    $field['name'] = $field['column_name'];
    $field['title'] = $field['label'];
    $field['dataType'] = $field['data_type'];
    $field['htmlType'] = $field['html_type'];
    $field['type'] = CRM_Utils_Array::value($field['dataType'], $types, CRM_Utils_Type::T_STRING);

    if ($field['type'] == CRM_Utils_Type::T_DATE && !empty($field['time_format'])) {
      $field['type'] = CRM_Utils_Type::T_TIMESTAMP;
    }
  }

  /**
   * Extract the relevant filters from the DAO query
   *
   * @param $field
   * @param $fieldName
   * @param $filter
   *
   * @return
   */
  function extractFieldsAndFilters($field, $fieldName, &$filter) {
    $htmlType = CRM_Utils_Array::value('html_type', $field);
    switch ($field['dataType']) {
      case 'Date':
        $filter['operatorType'] = CRM_Report_Form::OP_DATE;
        $filter['type'] = CRM_Utils_Type::T_DATE;
        // CRM-6946, show time part for datetime date fields
        if (!empty($field['time_format'])) {
          $field['type'] = CRM_Utils_Type::T_TIMESTAMP;
        }
        break;

      case 'Boolean':
        // filters
        $filter['operatorType'] = CRM_Report_Form::OP_SELECT;
        // filters
        $filter['options'] = array(
          '' => ts('- select -'),
          1 => ts('Yes'),
          0 => ts('No'),
        );
        $filter['type'] = CRM_Utils_Type::T_INT;
        break;

      case 'Int':
        // filters
        $filter['operatorType'] = CRM_Report_Form::OP_INT;
        $filter['type'] = CRM_Utils_Type::T_INT;
        break;

      case 'Money':
        $filter['operatorType'] = CRM_Report_Form::OP_FLOAT;
        $filter['type'] = CRM_Utils_Type::T_MONEY;
        break;

      case 'Float':
        $filter['operatorType'] = CRM_Report_Form::OP_FLOAT;
        $filter['type'] = CRM_Utils_Type::T_FLOAT;
        break;

      case 'String':
        $filter['type'] = CRM_Utils_Type::T_STRING;

        if (!empty($field['option_group_id'])) {
          if (in_array($htmlType, array(
            'Multi-Select',
            'AdvMulti-Select',
            'CheckBox'
          ))
          ) {
            $filter['operatorType'] = CRM_Report_Form::OP_MULTISELECT_SEPARATOR;
          }
          else {
            $filter['operatorType'] = CRM_Report_Form::OP_MULTISELECT;
          }
          if ($this->_customGroupFilters) {
            $filter['options'] = array();
            $ogDAO = CRM_Core_DAO::executeQuery("SELECT ov.value, ov.label FROM civicrm_option_value ov WHERE ov.option_group_id = %1 ORDER BY ov.weight", array(
              1 => array(
                $field['option_group_id'],
                'Integer'
              )
            ));
            while ($ogDAO->fetch()) {
              $filter['options'][$ogDAO->value] = $ogDAO->label;
            }
          }
        }
        break;

      case 'StateProvince':
        if (in_array($htmlType, array(
          'Multi-Select State/Province'
        ))
        ) {
          $filter['operatorType'] = CRM_Report_Form::OP_MULTISELECT_SEPARATOR;
        }
        else {
          $filter['operatorType'] = CRM_Report_Form::OP_MULTISELECT;
        }
        $filter['options'] = CRM_Core_PseudoConstant::stateProvince();
        break;

      case 'Country':
        if (in_array($htmlType, array(
          'Multi-Select Country'
        ))
        ) {
          $filter['operatorType'] = CRM_Report_Form::OP_MULTISELECT_SEPARATOR;
        }
        else {
          $filter['operatorType'] = CRM_Report_Form::OP_MULTISELECT;
        }
        $filter['options'] = CRM_Core_PseudoConstant::country();
        break;

      case 'ContactReference':
        $filter['type'] = CRM_Utils_Type::T_STRING;
        $filter['name'] = 'display_name';
        $filter['alias'] = "contact_{$fieldName}_civireport";

        $field[$fieldName]['type'] = CRM_Utils_Type::T_STRING;
        $field[$fieldName]['name'] = 'display_name';
        $field['alias'] = "contact_{$fieldName}_civireport";
        break;

      default:
        $field['type'] = CRM_Utils_Type::T_STRING;
        $filter['type'] = CRM_Utils_Type::T_STRING;
    }
    return $field;
  }

  /**
   * Build custom data from clause.
   *
   * Overridden to support custom data for multiple entities of the same type.
   */
  public function extendedCustomDataFrom() {
    foreach ($this->_columns as $table => $prop) {
      if (empty($prop['extends']) || !$this->isCustomTableSelected($table)) {
        continue;
      }

      $baseJoin = CRM_Utils_Array::value($prop['extends'], $this->_customGroupExtendsJoin, "{$this->_aliases[$prop['extends_table']]}.id");

      $customJoin = is_array($this->_customGroupJoin) ? $this->_customGroupJoin[$table] : $this->_customGroupJoin;
      if (!stristr($this->_from, $this->_aliases[$table])) {
        // Protect against conflict with selectableCustomFrom.
        $this->_from .= "
{$customJoin} {$prop['table_name']} {$this->_aliases[$table]} ON {$this->_aliases[$table]}.entity_id = {$baseJoin}";
      }
      // handle for ContactReference
      if (array_key_exists('fields', $prop)) {
        foreach ($prop['fields'] as $fieldName => $field) {
          if (CRM_Utils_Array::value('dataType', $field) ==
            'ContactReference'
          ) {
            $customFieldID = CRM_Core_BAO_CustomField::getKeyID($fieldName);
            if (!$customFieldID) {
              // seems it can be passed with wierd things appended...
              continue;
            }
            $columnName = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_CustomField', CRM_Core_BAO_CustomField::getKeyID($fieldName), 'column_name');
            $this->_from .= "
LEFT JOIN civicrm_contact {$field['alias']} ON {$field['alias']}.id = {$this->_aliases[$table]}.{$columnName} ";
          }
        }
      }
    }
  }

  /**
   * Is the custom table selected for any purpose.
   *
   * If we retrieve fields from it, filter on it etc we need to join it in.
   *
   * @param string $table
   *
   * @return bool
   */
  protected function isCustomTableSelected($table) {
    $selected = array_merge(
      $this->getSelectedFilters(),
      $this->getSelectedFields(),
      $this->getSelectedOrderBys(),
      $this->getSelectedAggregateRows(),
      $this->getSelectedAggregateColumns(),
      $this->getSelectedGroupBys()
    );
    foreach ($selected as $spec) {
      if ($spec['table_name'] == $table) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Map extends = 'Entity' to a connection to the relevant table
   *
   * @param $field
   * @param $spec
   *
   * @return string
   * @return string
   * @internal param $field
   */
  private function mapFieldExtends($field, $spec) {
    $extendable = array(
      'Activity' => 'civicrm_activity',
      'Relationship' => 'civicrm_relationship',
      'Contribution' => 'civicrm_contribution',
      'Group' => 'civicrm_group',
      'Membership' => 'civicrm_membership',
      'Event' => 'civicrm_event',
      'Participant' => 'civicrm_participant',
      'Pledge' => 'civicrm_pledge',
      'Grant' => 'civicrm_grant',
      'Address' => 'civicrm_address',
      'Campaign' => 'civicrm_campaign',
    );

    if (!empty($extendable[$spec['extends']])) {
      return $extendable[$spec['extends']] . ':' . $field;
    }
    else {
      return 'civicrm_contact:' . $field;
    }
  }


  /**
   * Here we can define select clauses for any particular row. At this stage we are going
   * to csv tags
   *
   * @param string $tableName
   * @param string $tableKey
   * @param string $fieldName
   * @param array $field
   *
   * @return bool|string
   */
  function selectClause(&$tableName, $tableKey, &$fieldName, &$field) {
    if ($fieldName == 'phone_phone') {
      $alias = "{$tableName}_{$fieldName}";
      $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = CRM_Utils_Array::value('title', $field);
      $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = CRM_Utils_Array::value('type', $field);
      $this->_columnHeaders["{$tableName}_{$fieldName}"]['dbAlias'] = CRM_Utils_Array::value('dbAlias', $field);
      $this->_selectAliases[] = $alias;
      return " GROUP_CONCAT(CONCAT({$field['dbAlias']},':', {$this->_aliases[$tableName]}.location_type_id, ':', {$this->_aliases[$tableName]}.phone_type_id) ) as $alias";
    }
    if (!empty($field['pseudofield'])) {
      $alias = "{$tableName}_{$fieldName}";
      $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = CRM_Utils_Array::value('title', $field);
      $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = CRM_Utils_Array::value('type', $field);
      $this->_columnHeaders["{$tableName}_{$fieldName}"]['dbAlias'] = CRM_Utils_Array::value('dbAlias', $field);
      $this->_selectAliases[] = $alias;
      return ' 1 as  ' . $alias;
    }

    if ((!empty($this->_groupByArray) || $this->isForceGroupBy)) {
      if ($tableKey === 'fields' && (empty($field['statistics']) || in_array('GROUP_CONCAT', $field['statistics']))) {
        $label = CRM_Utils_Array::value('title', $field);
        $alias = "{$tableName}_{$fieldName}";
        $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = $label;
        $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = $field['type'];
        $this->_selectAliases[] = $alias;
        if (empty($this->_groupByArray[$tableName . '_' . $fieldName])) {
          return "GROUP_CONCAT(DISTINCT {$field['dbAlias']}) as $alias";
        }
        return "({$field['dbAlias']}) as $alias";
      }
    }

  }

  /**
   * Function extracts the custom fields array where it is preceded by a table prefix
   * This allows us to include custom fields from multiple contacts (for example) in one report
   *
   * @param $customFields
   * @param $selectedTables
   * @param string $context
   *
   * @return array
   * @throws \Exception
   */
  function extractCustomFields(&$customFields, &$selectedTables, $context = 'select') {
    $myColumns = array();
    $metadata = $this->getMetadataByType('metadata');
    $selectedFields = array_intersect_key($metadata, $customFields);
    foreach ($selectedFields as $fieldName => $selectedField) {
      $tableName = $selectedField['table_name'];
      $customFieldsToTables[$fieldName] = $tableName;
      $fieldAlias = $selectedField['alias'];
      $tableAlias = $tableName;
      // these should be in separate functions
      if ($context == 'select' && (!$this->_preConstrain || $this->_preConstrained)) {
        $this->_select .= ", {$tableAlias}.{$metadata[$fieldName]['name']} as $fieldAlias ";
      }
      if ($context == 'row_header') {
        $this->addRowHeader($tableAlias, $selectedField, $fieldAlias);
      }
      if ($context == 'column_header') {
        $this->addColumnAggregateSelect($metadata[$fieldName]['name'], $selectedField['dbAlias'], $metadata[$fieldName]);
      }
      if (!isset($selectedField['type'])) {
        $selectedField['type'] = 'String';
      }
      // we compile the columns here but add them @ the end to preserve order
      $myColumns[$customFields[$fieldName][0] . ":" . $fieldName] = $selectedField;
    }
    return $myColumns;
  }

  /**
   * Add null option to an option filter
   *
   * @param string $table
   * @param string $fieldName
   * @param string $label
   */
  protected function addNullToFilterOptions($table, $fieldName, $label = '--does not exist--') {
    $this->_columns[$table]['filters'][$fieldName]['options'] = array('' => $label) + $this->_columns[$table]['filters'][$fieldName]['options'];
  }

  /**
   * Add row as the header for a pivot table. If it is to be the header it must be selected
   * and be the group by.
   *
   * @param string $tableAlias
   * @param array $selectedField
   * @param string $fieldAlias
   * @param string $title
   */
  private function addRowHeader($tableAlias, $selectedField, $fieldAlias, $title = '') {
    if (empty($tableAlias)) {
      $this->_select = 'SELECT 1 '; // add a fake value just to save lots of code to calculate whether a comma is required later
      $this->_rollup = NULL;
      $this->_noGroupBY = TRUE;
      return;
    }
    $this->_select = "SELECT {$selectedField['dbAlias']} as $fieldAlias ";
    if (!in_array($fieldAlias, $this->_groupByArray)) {
      $this->_groupByArray[] = $fieldAlias;
    }
    $this->_groupBy = "GROUP BY $fieldAlias " . $this->_rollup;
    $this->_columnHeaders[$fieldAlias] = array('title' => $title,);
    $key = array_search($fieldAlias, $this->_noDisplay);
    if (is_int($key)) {
      unset($this->_noDisplay[$key]);
    }
  }


  /**
   * @param $rows
   */
  function alterDisplay(&$rows) {
    if (!empty($this->_defaults['report_id']) && $this->_defaults['report_id'] == reset($this->_drilldownReport)) {
      $this->linkedReportID = $this->_id;
    }
    parent::alterDisplay($rows);

    //THis is all generic functionality which can hopefully go into the parent class
    // it introduces the option of defining an alter display function as part of the column definition
    // @todo tidy up the iteration so it happens in this function

    if (!empty($this->_rollup) && !empty($this->_groupBysArray)) {
      $this->assignSubTotalLines($rows);
    }

    if (empty($rows)) {
      return;
    }
    list($firstRow) = $rows;
    // no result to alter
    if (empty($firstRow)) {
      return;
    }

    $selectedFields = array_keys($firstRow);
    $alterFunctions = $alterMap = array();

    foreach ($this->getSelectedAggregateRows() as $pivotRowField => $pivotRowFieldSpec) {
      $pivotRowIsCustomField = substr($pivotRowField, 0, 7) == 'custom_';
      if ($pivotRowIsCustomField && $pivotRowFieldSpec['html_type'] != 'Text') {
        $alias = $pivotRowFieldSpec['alias'];
        $alterFunctions[$alias] = 'alterFromOptions';
        $alterMap[$alias] = $pivotRowField;
        $alterSpecs[$alias] = $pivotRowFieldSpec;
      }
    }

    $fieldData = $this->getMetadataByAlias('metadata');
    $chosen = array_intersect_key($fieldData, $firstRow);
    foreach ($fieldData as $fieldAlias => $specs) {
      if (isset($chosen[$fieldAlias]) && array_key_exists('alter_display', $specs)) {
        $alterFunctions[$fieldAlias] = $specs['alter_display'];
        $alterMap[$fieldAlias] = $fieldAlias;
        $alterSpecs[$fieldAlias] = $specs;
      }
      if (in_array($fieldAlias . '_sum', $selectedFields)
        && !empty($this->_groupByArray) && isset($specs['statistics']) && isset($specs['statistics']['cumulative'])) {
        $this->_columnHeaders[$fieldAlias . '_cumulative']['title'] = $specs['statistics']['cumulative'];
        $this->_columnHeaders[$fieldAlias . '_cumulative']['type'] = $specs['type'];
        $alterFunctions[$fieldAlias . '_sum'] = 'alterCumulative';
        $alterMap[$fieldAlias . '_sum'] = $fieldAlias;
        $alterSpecs[$fieldAlias . '_sum'] = $specs['name'];
      }
      if ($this->_editableFields && array_key_exists('crm_editable', $specs) && !empty($this->_aliases[$specs['crm_editable']['id_table']])) {
        //id key array is what the array would look like if the ONLY group by field is our id field
        // in which case it should be editable - in any other group by scenario it shouldn't be
        $idKeyArray = array($this->_aliases[$specs['crm_editable']['id_table']] . "." . $specs['crm_editable']['id_field']);
        if (empty($this->_groupByArray) || $this->_groupByArray == $idKeyArray) {
          $alterFunctions[$fieldAlias] = 'alterCrmEditable';
          $alterMap[$fieldAlias] = $fieldAlias;
          $alterSpecs[$fieldAlias] = $specs['crm_editable'];
          $alterSpecs[$fieldAlias]['field_name'] = $specs['name'];
        }
      }
      // Add any alters that can be intuited from the field specs.
      // So far only boolean but a lot more could be.
      if (empty($alterSpecs[$fieldAlias]) && $specs['type'] == CRM_Utils_Type::T_BOOLEAN) {
        $alterFunctions[$fieldAlias] = 'alterBoolean';
        $alterMap[$fieldAlias] = $fieldAlias;
        $alterSpecs[$fieldAlias] = NULL;
      }
    }

    if ($this->_rollup) {
      //we want to be able to unset rows so here
      $this->alterRollupRows($rows);
    }

    if (empty($alterFunctions)) {
      // - no manipulation to be done
      return;
    }
    foreach ($rows as $index => & $row) {
      foreach ($row as $selectedField => $value) {
        if (array_key_exists($selectedField, $alterFunctions)) {
          $rows[$index][$selectedField] = $this->{$alterFunctions[$selectedField]}($value, $row, $selectedField, $alterMap[$selectedField], $alterSpecs[$selectedField]);
        }
      }
    }

  }

  /**
   * Format display output.
   *
   * Overriding this as the alterDisplay functionality from extended reports has recently been
   * upstreamed & we need more time to reconcile to that - it's been running twice
   * and causing some mis-fires.
   *
   * @param array $rows
   * @param bool $pager
   */
  public function formatDisplay(&$rows, $pager = TRUE) {
    // set pager based on if any limit was applied in the query.
    if ($pager) {
      $this->setPager();
    }

    // unset columns not to be displayed.
    foreach ($this->_columnHeaders as $key => $value) {
      if (!empty($value['no_display'])) {
        unset($this->_columnHeaders[$key]);
      }
    }

    // unset columns not to be displayed.
    if (!empty($rows)) {
      foreach ($this->_noDisplay as $noDisplayField) {
        foreach ($rows as $rowNum => $row) {
          unset($this->_columnHeaders[$noDisplayField]);
        }
      }
    }

    // build array of section totals
    $this->sectionTotals();

    // process grand-total row
    $this->grandTotal($rows);

    // use this method for formatting rows for display purpose.
    $this->alterDisplay($rows);
    CRM_Utils_Hook::alterReportVar('rows', $rows, $this);

    // use this method for formatting custom rows for display purpose.
    $this->alterCustomDataDisplay($rows);
  }

  /**
   * If rollup is in use we want to dmarcarate rollou rows.
   *
   * With rollup the very last row will be a summary row.
   *
   * There will be an unknown number of summary rows in the middle depending on
   * the data and the number of rows.
   *
   * Each distinct combination of group by fields results in a summary row. So it might look
   * like
   *
   * Canvasser | Campaign Type | Campaign | Raised
   *
   * Mickey    |Phone         | Morning  | $50
   * Mickey    |Phone         | Evening  | $100
   * Mickey    |Phone         |          | $150
   * Mickey    |Doors         |          | $600
   * Mickey    |Doors         |          | $600
   * Mickey    |              |          | $750
   * |         |              |          | $750
   *
   * @param $rows
   */
  function alterRollupRows(&$rows) {
    if (count($rows) === 1) {
      // If the report only returns one row there is no rollup.
      return;
    }
    $groupBys = array_reverse(array_fill_keys(array_keys($this->_groupByArray), NULL));
    $firstRow = reset($rows);
    foreach ($groupBys as $field => $groupBy) {
      $fieldKey = isset($firstRow[$field]) ? $field : str_replace(array(
        '_YEAR',
        '_MONTH'
      ), '_start', $field);
      if (isset($firstRow[$fieldKey])) {
        unset($groupBys[$field]);
        $groupBys[$fieldKey] = $firstRow[$fieldKey];
      }
    }
    $groupByLabels = array_keys($groupBys);

    $altered = array();
    $fieldsToUnSetForSubtotalLines = array();
    //on this first round we'll get a list of keys that are not groupbys or stats
    foreach (array_keys($firstRow) as $rowField) {
      if (!array_key_exists($rowField, $groupBys) && substr($rowField, -4) != '_sum' && !substr($rowField, -7) != '_count') {
        $fieldsToUnSetForSubtotalLines[] = $rowField;
      }
    }

    $statLayers = count($this->_groupByArray);

    //I don't know that this precaution is required?          $this->fixSubTotalDisplay($rows[$rowNum], $this->_statFields);
    if (count($this->_statFields) == 0) {
      return;
    }

    foreach (array_keys($rows) as $rowNumber) {
      $nextRow = CRM_Utils_Array::value($rowNumber +1, $rows);
      if ($nextRow === NULL) {
        $this->updateRollupRow($rows[$rowNumber], $fieldsToUnSetForSubtotalLines);
      }
      else {
        $this->alterRowForRollup($rows[$rowNumber], $nextRow, $groupBys, $rowNumber, $statLayers, $groupByLabels, $altered, $fieldsToUnSetForSubtotalLines);
      }
    }
  }


  /**
   * Use the options for the field to map the display value.
   *
   * @param string $value
   * @param array $row
   * @param string $selectedField
   * @param $criteriaFieldName
   * @param array $specs
   *
   * @return string
   */
  function alterFromOptions($value, &$row, $selectedField, $criteriaFieldName, $specs) {
    if ($specs['data_type'] == 'ContactReference') {
      if (!empty($row[$selectedField])) {
        return CRM_Contact_BAO_Contact::displayName($row[$selectedField]);
      }
      return NULL;
    }
    $value = trim($value, CRM_Core_DAO::VALUE_SEPARATOR);
    $options = $this->getCustomFieldOptions($specs);
    foreach ($options as $option) {
      if ($option['value'] === $value) {
        return $option['label'];
      }
    }
    return $value;
  }

  /**
   * Was hoping to avoid over-riding this - but it doesn't pass enough data to formatCustomValues by default
   * Am using it in a pretty hacky way to also cover the select box custom fields
   *
   * @param $rows
   */
  function alterCustomDataDisplay(&$rows) {

    // custom code to alter rows having custom values
    if (empty($this->_customGroupExtends) && empty($this->_customGroupExtended)) {
      return;
    }
    $extends = $this->_customGroupExtends;
    foreach ($this->_customGroupExtended as $table => $spec) {
      $extends = array_merge($extends, $spec['extends']);
    }

    $customFieldIds = array();
    if (!isset($this->_params['fields']) || !is_array($this->_params['fields'])) {
      $this->_params['fields'] = array();
    }
    foreach ($this->_params['fields'] as $fieldAlias => $value) {
      $fieldId = CRM_Core_BAO_CustomField::getKeyID($fieldAlias);
      if ($fieldId) {
        $customFieldIds[$fieldAlias] = $fieldId;
      }
    }
    if (!empty($this->_params['custom_fields']) && is_array($this->_params['custom_fields'])) {
      foreach ($this->_params['custom_fields'] as $fieldAlias => $value) {
        $fieldName = explode(':', $value);
        $fieldId = CRM_Core_BAO_CustomField::getKeyID($fieldName[1]);
        if ($fieldId) {
          $customFieldIds[str_replace(':', '_', $value)] = $fieldId;
        }
      }
    }

    if (empty($customFieldIds)) {
      return;
    }

    $customFields = $fieldValueMap = array();
    $customFieldCols = array(
      'column_name',
      'data_type',
      'html_type',
      'option_group_id',
      'id'
    );

    // skip for type date and ContactReference since date format is already handled
    $query = "
SELECT cg.table_name, cf." . implode(", cf.", $customFieldCols) . ", ov.value, ov.label
FROM  civicrm_custom_field cf
INNER JOIN civicrm_custom_group cg ON cg.id = cf.custom_group_id
LEFT JOIN civicrm_option_value ov ON cf.option_group_id = ov.option_group_id
WHERE cg.extends IN ('" . implode("','", $extends) . "') AND
      cg.is_active = 1 AND
      cf.is_active = 1 AND
      cf.is_searchable = 1 AND
      cf.data_type   NOT IN ('ContactReference', 'Date') AND
      cf.id IN (" . implode(",", $customFieldIds) . ")";

    $dao = CRM_Core_DAO::executeQuery($query);
    while ($dao->fetch()) {
      foreach ($customFieldCols as $key) {
        $customFields[$dao->table_name . '_custom_' . $dao->id][$key] = $dao->$key;
        // this is v hacky - we are supporting 'std style & JQ style here
        if (!empty($this->_params['custom_fields'])) {
          foreach ($customFieldIds as $custFieldName => $custFieldKey) {
            if ($dao->id == $custFieldKey) {
              $customFields[$custFieldName] = $customFields[$dao->table_name . '_custom_' . $dao->id];
            }
          }
        }
      }
      if ($dao->option_group_id) {
        $fieldValueMap[$dao->option_group_id][$dao->value] = $dao->label;
      }
    }
    $entryFound = FALSE;
    foreach ($rows as $rowNum => $row) {
      foreach ($row as $tableCol => $val) {
        if (array_key_exists($tableCol, $customFields)) {
          $rows[$rowNum][$tableCol] = $this->formatCustomValues($val, $customFields[$tableCol], $fieldValueMap, $row);
          if (!empty($this->_drilldownReport)) {
            foreach ($this->_drilldownReport as $baseUrl => $label) {
              // Only one - that was a crap way of grabbing it. Too late to think of
              // an elegant one.
            }

            $fieldName = 'custom_' . $customFields[$tableCol]['id'];
            $criteriaQueryParams = CRM_Report_Utils_Report::getPreviewCriteriaQueryParams($this->_defaults, $this->_params);
            $groupByCriteria = $this->getGroupByCriteria($tableCol, $row);

            $url = CRM_Report_Utils_Report::getNextUrl($baseUrl,
              "reset=1&force=1&{$criteriaQueryParams}&" .
              $fieldName . "_op=in&{$fieldName}_value=" . $this->commaSeparateCustomValues($val) . $groupByCriteria,
              $this->_absoluteUrl, $this->linkedReportID
            );
            $rows[$rowNum][$tableCol . '_link'] = $url;
          }
          $entryFound = TRUE;
        }
      }

      // skip looking further in rows, if first row itself doesn't
      // have the column we need
      if (!$entryFound) {
        break;
      }
    }
  }

  /**
   * Reformat custom value, removing first & last separator and using commas for the rest.
   *
   * @param string $value
   *
   * @return string
   */
  protected function commaSeparateCustomValues($value) {
    if (empty($value)) {
      return '';
    }

    if (substr($value, 0, 1) == CRM_Core_DAO::VALUE_SEPARATOR) {
      $value = substr($value, 1);
    }
    if (substr($value, -1, 1) == CRM_Core_DAO::VALUE_SEPARATOR) {
      $value = substr($value, 0, -1);
    }
    return implode(',', explode(CRM_Core_DAO::VALUE_SEPARATOR, $value));
  }

  /**
   * We are overriding this function to apply crm-editable where appropriate
   * It would be more efficient if we knew the entity being extended (which the parent function
   * does know) but we want to avoid extending any functions we don't have to
   *
   * @param $value
   * @param $customField
   * @param $fieldValueMap
   * @param array $row
   *
   * @return float|string|void
   */
  function formatCustomValues($value, $customField, $fieldValueMap, $row = array()) {
    if (!empty($this->_customGroupExtends) && count($this->_customGroupExtends) == 1) {
      //lets only extend apply editability where only one entity extended
      // we can easily extend to contact combos
      list($entity) = $this->_customGroupExtends;
      $entity_table = strtolower('civicrm_' . $entity);
      $idKeyArray = array($this->_aliases[$entity_table] . '.id');
      if (empty($this->_groupByArray) || $this->_groupByArray == $idKeyArray) {
        $entity_field = $entity_table . '_id';
        $entityID = $row[$entity_field];
      }
    }
    if (CRM_Utils_System::isNull($value) && !in_array($customField['data_type'], array(
        'String',
        'Int'
      ))
    ) {
      // we will return unless it is potentially an editable field
      return;
    }

    $htmlType = $customField['html_type'];

    switch ($customField['data_type']) {
      case 'Boolean':
        if ($value == '1') {
          $retValue = ts('Yes');
        }
        else {
          $retValue = ts('No');
        }
        break;

      case 'Link':
        $retValue = CRM_Utils_System::formatWikiURL($value);
        break;

      case 'File':
        $retValue = $value;
        break;

      case 'Memo':
        $retValue = $value;
        break;

      case 'Float':
        if ($htmlType == 'Text') {
          $retValue = (float) $value;
          break;
        }
      case 'Money':
        if ($htmlType == 'Text') {
          $retValue = CRM_Utils_Money::format($value, NULL, '%a');
          break;
        }
      case 'String':
      case 'Int':
        if (in_array($htmlType, array(
          'Text',
          'TextArea',
          'Select',
          'Radio'
        ))
        ) {
          if ($htmlType == 'Select' || $htmlType == 'Radio') {
            $retValue = CRM_Utils_Array::value($value, $fieldValueMap[$customField['option_group_id']]);
          } else {
            $retValue = $value;
          }
          $extra = '';
          if (($htmlType == 'Select' || $htmlType == 'Radio') && !empty($entity)) {
            $options = civicrm_api($entity, 'getoptions', array(
              'version' => 3,
              'field' => 'custom_' . $customField['id']
            ));
            $options = $options['values'];
            $options['selected'] = $value;
            $extra = "data-type='select' data-options='" . json_encode($options, JSON_HEX_APOS) . "'";
            $value = $options[$value];
          }
          if (!empty($entity_field)) {
            $retValue = "<div id={$entity}-{$entityID} class='crm-entity'>" .
              "<span class='crm-editable crmf-custom_{$customField['id']} crm-editable' data-action='create' $extra >" . $value . "</span></div>";
          }
          break;
        }
      case 'StateProvince':
      case 'Country':

        switch ($htmlType) {
          case 'Multi-Select Country':
            $value = explode(CRM_Core_DAO::VALUE_SEPARATOR, $value);
            $customData = array();
            foreach ($value as $val) {
              if ($val) {
                $customData[] = CRM_Core_PseudoConstant::country($val, FALSE);
              }
            }
            $retValue = implode(', ', $customData);
            break;

          case 'Select Country':
            $retValue = CRM_Core_PseudoConstant::country($value, FALSE);
            break;

          case 'Select State/Province':
            $retValue = CRM_Core_PseudoConstant::stateProvince($value, FALSE);
            break;

          case 'Multi-Select State/Province':
            $value = explode(CRM_Core_DAO::VALUE_SEPARATOR, $value);
            $customData = array();
            foreach ($value as $val) {
              if ($val) {
                $customData[] = CRM_Core_PseudoConstant::stateProvince($val, FALSE);
              }
            }
            $retValue = implode(', ', $customData);
            break;

          case 'Select':
          case 'Radio':
          case 'Autocomplete-Select':
            $retValue = $fieldValueMap[$customField['option_group_id']][$value];
            break;

          case 'CheckBox':
          case 'AdvMulti-Select':
          case 'Multi-Select':
            $value = explode(CRM_Core_DAO::VALUE_SEPARATOR, $value);
            $customData = array();
            foreach ($value as $val) {
              if ($val) {
                $customData[] = $fieldValueMap[$customField['option_group_id']][$val];
              }
            }
            $retValue = implode(', ', $customData);
            break;

          default:
            $retValue = $value;
        }
        break;

      default:
        $retValue = $value;
    }

    return $retValue;
  }

  /**
   * @param $rows
   */
  function assignSubTotalLines(&$rows) {
    foreach ($rows as $index => & $row) {
      $orderFields = array_intersect_key(array_flip($this->_groupBysArray), $row);
    }
  }
  /*
   * Function is over-ridden to support multiple add to groups
   */
  /**
   * @param $groupID
   */
  function add2group($groupID) {
    if (is_numeric($groupID) && isset($this->_aliases['civicrm_contact'])) {
      $contact = CRM_Utils_Array::value('btn_group_contact', $this->_submitValues, 'civicrm_contact');
      $select = "SELECT DISTINCT {$this->_aliases[$contact]}.id AS addtogroup_contact_id";
      //    $select = str_ireplace('SELECT SQL_CALC_FOUND_ROWS ', $select, $this->_select);

      $sql = "{$select} {$this->_from} {$this->_where} AND {$this->_aliases[$contact]}.id IS NOT NULL {$this->_groupBy}  {$this->_having} {$this->_orderBy}";
      $sql = str_replace('WITH ROLLUP', '', $sql);
      $dao = CRM_Core_DAO::executeQuery($sql);

      $contact_ids = array();
      // Add resulting contacts to group
      while ($dao->fetch()) {
        $contact_ids[$dao->addtogroup_contact_id] = $dao->addtogroup_contact_id;
      }

      CRM_Contact_BAO_GroupContact::addContactsToGroup($contact_ids, $groupID);
      CRM_Core_Session::setStatus(ts("Listed contact(s) have been added to the selected group."));
    }
  }

  /**
   * check if a table exists
   *
   * @param string $tableName Name of table
   *
   * @return bool
   */
  function tableExists($tableName) {
    $sql = "SHOW TABLES LIKE '{$tableName}'";
    $result = CRM_Core_DAO::executeQuery($sql);
    $result->fetch();
    return $result->N ? TRUE : FALSE;
  }

  /**
   * Function is over-ridden to support multiple add to groups
   */
  function buildInstanceAndButtons() {
    CRM_Report_Form_Instance::buildForm($this);
    $this->_actionButtonName = $this->getButtonName('submit');
    $this->addTaskMenu($this->getActions($this->_id));
    $this->assign('instanceForm', $this->_instanceForm);

    if (CRM_Core_Permission::check('administer Reports') && $this->_add2groupSupported) {
      $this->addElement('select', 'groups', ts('Group'),
        array('' => ts('- select group -')) + CRM_Core_PseudoConstant::staticGroup()
      );
      if (!empty($this->_add2GroupcontactTables) && is_array($this->_add2GroupcontactTables) && count($this->_add2GroupcontactTables > 1)) {
        $this->addElement('select', 'btn_group_contact', ts('Contact to Add'),
          array('' => ts('- choose contact -')) + $this->_add2GroupcontactTables
        );
      }
      $this->assign('group', TRUE);
    }

    $label = ts('Add these Contacts to Group');
    $this->addElement('submit', $this->_groupButtonName, $label, array('onclick' => 'return checkGroup();'));

    $this->addChartOptions();
    $this->addButtons(array(
        array(
          'type' => 'submit',
          'name' => ts('Preview Report'),
          'isDefault' => TRUE,
        ),
      )
    );
  }

  /**
   * Function to add columns because I wasn't enjoying adding filters to each fn.
   *
   * @param string $type
   * @param array $options
   *
   * @return
   */
   protected function getColumns($type, $options = array()) {
     $defaultOptions = array(
      'prefix' => '',
      'prefix_label' => '',
      'fields' => TRUE,
      'group_by' => FALSE,
      'order_by' => TRUE,
      'filters' => TRUE,
      'join_filters' => FALSE,
      'join_fields' => FALSE,
      'fields_defaults' => array(),
      'filters_defaults' => array(),
      'group_bys_defaults' => array(),
      'order_by_defaults' => array(),
    );
    $options = array_merge($defaultOptions, $options);

    $fn = 'get' . $type . 'Columns';
    $columns = $this->$fn($options);

    foreach (array(
               'filters',
               'group_by',
               'order_by',
               'join_filters'
             ) as $type) {
      if (!$options[$type]) {
        foreach ($columns as $tables => &$table) {
          if (isset($table[$type])) {
            $table[$type] = array();
          }
        }
      }
    }

    if (!$options['fields']) {
      foreach ($columns as $tables => &$table) {
        if (isset($table['fields'])) {
          // We still retrieve them all but unset any defaults & set no_display.
          foreach ($table['fields'] as &$field) {
            $field['no_display'] = TRUE;
            $field['required'] = FALSE;
          }
        }
      }
    }
    return $columns;
  }

  /**
   * Build the columns.
   *
   * The normal report class needs you to remember to do a few things that are often erratic
   * 1) use a unique key for any field that might not be unique (e.g. start date, label)
   * - this class will always prepend an alias to the key & set the 'name' if you don't set it yourself.
   * - note that it assumes the value being passed in is the actual table fieldname
   *
   * 2) set the field & set it to no display if you don't want the field but you might want to use the field in other
   * contexts - the code looks up the fields array for data - so it both defines the field spec & the fields you want to show
   *
   * @param array $specs
   * @param string $tableName
   * @param string $tableAlias
   * @param string $daoName
   * @param string $tableAlias
   * @param array $defaults
   * @param array $options Options
   *    - group_title
   *
   * @return array
   */
  protected function buildColumns($specs, $tableName, $daoName = NULL, $tableAlias = NULL, $defaults = array(), $options = array()) {

    if (!$tableAlias) {
      $tableAlias = str_replace('civicrm_', '', $tableName);
    }
    $types = array('filters', 'group_bys', 'order_bys', 'join_filters');
    $columns = array($tableName => array_fill_keys($types, array()));
    if (!empty($daoName)) {
      $columns[$tableName]['bao'] = $daoName;
    }
    if ($tableAlias) {
      $columns[$tableName]['alias'] = $tableAlias;
    }
    $exportableFields = $this->getMetadataForFields(['dao' => $daoName]);

    foreach ($specs as $specName => $spec) {
      unset($spec['default']);
      if (empty($spec['name'])) {
        $spec['name'] = $specName;
      }
      if (empty($spec['dbAlias'])) {
        $spec['dbAlias'] = $tableAlias . '.' . $spec['name'];
      }
      $daoSpec = CRM_Utils_Array::value($spec['name'], $exportableFields, CRM_Utils_Array::value($tableAlias . '_' . $spec['name'], $exportableFields, []));
      $spec = array_merge($daoSpec , $spec);
      if (!isset($columns[$tableName]['table_name']) && isset($spec['table_name'])) {
        $columns[$tableName]['table_name'] = $spec['table_name'];
      }

      if (!isset($spec['operatorType'])) {
        $spec['operatorType'] = $this->getOperatorType($spec['type'], $spec);
      }
      foreach (array_merge($types, ['fields']) as $type) {
        if (!isset($spec['is_' . $type])) {
          $spec['is_' . $type] = FALSE;
        }
      }

      $fieldAlias = (empty($options['no_field_disambiguation']) ? $tableAlias . '_' : '') . $specName;
      $spec['alias'] = $tableName . '_' . $fieldAlias;
      $columns[$tableName]['metadata'][$fieldAlias] = $spec;
      $columns[$tableName]['fields'][$fieldAlias] = $spec;
      if (isset($defaults['fields_defaults']) && in_array($spec['name'], $defaults['fields_defaults'])) {
        $columns[$tableName]['fields'][$fieldAlias]['default'] = TRUE;
      }

      if (empty($spec['is_fields']) || (isset($options['fields_excluded']) && in_array($specName, $options['fields_excluded']))) {
        $columns[$tableName]['fields'][$fieldAlias]['no_display'] = TRUE;
      }

      if (!empty($spec['is_filters']) && !empty($spec['statistics']) && !empty($options) && !empty($options['group_by'])) {
        foreach ($spec['statistics'] as $statisticName => $statisticLabel) {
          $columns[$tableName]['filters'][$fieldAlias . '_' .  $statisticName] = array_merge($spec, [
            'title' => E::ts('Aggregate filter : ') . $statisticLabel,
            'having' => TRUE,
            'dbAlias' => $tableName . '_' . $fieldAlias . '_' .  $statisticName,
            'selectAlias' => "{$statisticName}({$tableAlias}.{$spec['name']})",
            'is_fields' => FALSE,
          ]);
          $columns[$tableName]['metadata'][$fieldAlias . '_' .  $statisticName] = $columns[$tableName]['filters'][$fieldAlias . '_' .  $statisticName];
        }
      }

      foreach ($types as $type) {
        if (!empty($spec['is_' . $type])) {
          if ($type === 'join_filters') {
            $fieldAlias = 'join__' . $fieldAlias;
          }
          $columns[$tableName][$type][$fieldAlias] = $spec;
          if (isset($defaults[$type . '_defaults']) && isset($defaults[$type . '_defaults'][$spec['name']])) {
            $columns[$tableName][$type][$fieldAlias]['default'] = $defaults[$type . '_defaults'][$spec['name']];
          }
        }
      }
    }
    $columns[$tableName]['prefix'] = isset($options['prefix']) ? $options['prefix'] : '';
    $columns[$tableName]['prefix_label'] = isset($options['prefix_label']) ? $options['prefix_label'] : '';
    if (isset($options['group_title'])) {
      $groupTitle = $options['group_title'];
    }
    else {

      // We can make one up but it won't be translated....
      $groupTitle = ucfirst(str_replace('_', ' ', str_replace('civicrm_', '', $tableName)));
    }
    $columns[$tableName]['group_title'] = $groupTitle;
    return $columns;
  }

  /**
   * Get the columns for the line items table.
   *
   * @param array $options
   *
   * @return array
   */
  protected function getLineItemColumns($options) {
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
    $defaults = $this->getDefaultsFromOptions($options);

    $specs = array(
      'financial_type_id' => array(
        'title' => ts('Line Item Financial Type'),
        'type' => CRM_Utils_Type::T_INT,
        'alter_display' => 'alterFinancialType',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contribute_PseudoConstant::financialType(),
        'statistics' => array('GROUP_CONCAT'),
      ),
      'id' => array(
        'title' => ts('Individual Line Item'),
        'type' => CRM_Utils_Type::T_INT,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
      ),
      'participant_count' => array(
        'title' => ts('Participant Count'),
        'type' => CRM_Utils_Type::T_INT,
        'statistics' => array(
          'sum' => ts('Total Participants')
        ),
        'is_fields' => TRUE,
      ),
      'price_field_id' => array(
        'title' => ts('Price Field (line item)'),
        'type' => CRM_Utils_Type::T_INT,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
      ),
      'price_field_value_id' => array(
        'title' => ts('Price Field Option (line item)'),
        'type' => CRM_Utils_Type::T_INT,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
      ),
      'qty' => array(
        'title' => ts('Quantity'),
        'type' => CRM_Utils_Type::T_INT,
        'operator' => CRM_Report_Form::OP_INT,
        'statistics' => array(
          'sum' => ts('Total Quantity Selected')
        ),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ),
      'unit_price' => array(
        'title' => ts('Unit Price'),
        'type' => CRM_Utils_Type::T_MONEY,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ),
      'contribution_id' => array(
        'title' => ts('Contribution Count'),
        'type' => CRM_Utils_Type::T_INT,
        'statistics' => array(
          'count' => ts('Count of Contributions')
        ),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ),
      'line_total' => array(
        'title' => ts('Line Total'),
        'type' => CRM_Utils_Type::T_MONEY,
        'statistics' => array(
          'sum' => ts('Total of Line Items')
        ),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ),
      'label' => array(
        'title' => ts('Line Label'),
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ),
      'tax_amount' => array(
        'title' => ts('Tax Amount'),
        'type' => CRM_Utils_Type::T_MONEY,
        'statistics' => array(
          'sum' => ts('Tax Total of Line Items')
        ),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ),
    );

    return $this->buildColumns($specs, 'civicrm_line_item', 'CRM_Price_BAO_LineItem', NULL, $defaults);
  }

  /**
   * @param array $options
   *
   * @return array
   */
  function getPriceFieldValueColumns($options) {
    $pseudoMethod = $this->financialTypePseudoConstant;
    $specs = array(
      'label' => array(
        'title' => ts('Price Field Value Label'),
        'type' => CRM_Utils_Type::T_STRING,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_fields' => TRUE,
        'is_group_bys' => TRUE,
      ),
      'max_value' => array(
        'title' => 'Price Option Maximum',
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
      ),
      'financial_type_id' => array(
        'title' => 'Price Option Financial Type',
        'type' => CRM_Utils_Type::T_INT,
        'alter_display' => 'alterFinancialType',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'is_fields' => TRUE,
        'options' => CRM_Contribute_PseudoConstant::$pseudoMethod(),
      ),
    );
    return $this->buildColumns($specs, 'civicrm_price_field_value', 'CRM_Price_BAO_PriceFieldValue');
  }

  /**
   * Get column specs for civicrm_price_fields.
   *
   * @return array
   */
  protected function getPriceFieldColumns() {
    $specs = array(
      'price_field_label' => array(
        'title' => ts('Price Field Label'),
        'type' => CRM_Utils_Type::T_STRING,
        'name' => 'label',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
        'operator' => 'like',
      ),
    );
    return $this->buildColumns($specs, 'civicrm_price_field', 'CRM_Price_BAO_PriceField');
  }

  /**
   * @param array $options
   *
   * @return array
   */
  protected function getParticipantColumns($options = array()) {
    static $_events = array();
    if (!isset($_events['all'])) {
      CRM_Core_PseudoConstant::populate($_events['all'], 'CRM_Event_DAO_Event', FALSE, 'title', 'is_active', "is_template IS NULL OR is_template = 0", 'title');
    }
    $specs = array(
      'id' => array(
        'title' => 'Participant ID',
        'is_fields' => TRUE,
        'name' => 'id',
        'type' => CRM_Utils_Type::T_INT,
      ),
      'participant_event_id' => array(
        'title' => ts('Event ID'),
        'name' => 'event_id',
        'type' => CRM_Utils_Type::T_STRING,
        'alter_display' => 'alterEventID',
        'is_filters' => TRUE,
        'is_fields' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => $_events['all'],
      ),
      'participant_status_id' => array(
        'name' => 'status_id',
        'title' => ts('Event Participant Status'),
        'alter_display' => 'alterParticipantStatus',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Event_PseudoConstant::participantStatus(NULL, NULL, 'label'),
        'type' => CRM_Utils_Type::T_INT,
      ),
      'participant_role_id' => array(
        'name' => 'role_id',
        'title' => ts('Participant Role'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT_SEPARATOR,
        'options' => CRM_Event_PseudoConstant::participantRole(),
        'alter_display' => 'alterParticipantRole',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ),
      'participant_fee_level' => array(
        'name' => 'fee_level',
        'type' => CRM_Utils_Type::T_STRING,
        'operator' => 'like',
        'title' => ts('Participant Fee Level'),
        'is_fields' => TRUE,
      ),
      'participant_fee_amount' => NULL,
      'register_date' => array(
        'title' => 'Registration Date',
        'operatorType' => CRM_Report_Form::OP_DATE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ),
    );

    return $this->buildColumns($specs, 'civicrm_participant', 'CRM_Event_BAO_Participant');
  }

  /**
   * @param array $options
   *
   * @return array
   */
  protected function getMembershipColumns($options) {
    $specs = [
      'membership_type_id' => array(
        'title' => 'Membership Type',
        'alter_display' => 'alterMembershipTypeID',
        'options' => $this->_getOptions('membership', 'membership_type_id', $action = 'get'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'name' => 'membership_type_id',
        'type' => CRM_Utils_Type::T_INT,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
      ),
      'membership_status_id' => array(
        'name' => 'status_id',
        'title' => 'Membership Status',
        'alter_display' => 'alterMembershipStatusID',
        'options' => $this->_getOptions('membership', 'status_id', $action = 'get'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'type' => CRM_Utils_Type::T_INT,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
      ),
      'join_date' => array(
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'type' => CRM_Utils_Type::T_DATE,
        'operatorType' => CRM_Report_Form::OP_DATE,
      ),
      'start_date' => array(
        'name' => 'start_date',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'title' => ts('Current Cycle Start Date'),
        'type' => CRM_Utils_Type::T_DATE,
        'operatorType' => CRM_Report_Form::OP_DATE,
      ),
      'end_date' => array(
        'name' => 'end_date',
        'is_fields' => TRUE,
        'title' => ts('Current Membership Cycle End Date'),
        'include_null' => TRUE,
        'is_group_bys' => TRUE,
        'type' => CRM_Utils_Type::T_DATE,
        'operatorType' => CRM_Report_Form::OP_DATE,
      ),
      'id' => array(
        'title' => 'Membership ID / Count',
        'name' => 'id',
        'statistics' => array('count' => ts('Number of Memberships')),
      ),
      'contact_id' => array(
        'title' => 'Membership Contact ID',
        'name' => 'contact_id',
        'is_filters' => TRUE,
      ),
    ];
    return $this->buildColumns($specs, $options['prefix'] . 'civicrm_membership', 'CRM_Member_DAO_Membership');
  }

  /**
   * Get columns from the membership log table.
   *
   * @param array $options
   *
   * @return array
   */
  function getMembershipLogColumns($options = array()) {
    $columns = array(
      'civicrm_membership_log' => array(
        'grouping' => 'member-fields',
        'fields' => array(
          'membership_type_id' => array(
            'title' =>  ts($options['prefix_label'] . 'Membership Type'),
            'alter_display' => 'alterMembershipTypeID',
            'options' => $this->_getOptions('membership', 'membership_type_id', $action = 'get'),
            'is_fields' => TRUE,
            'is_filters' => TRUE,
            'is_group_bys' => TRUE,
            'name' => 'membership_type_id',
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          ),
          'membership_status_id' => array(
            'name' => 'status_id',
            'title' =>  ts($options['prefix_label'] . 'Membership Status'),
            'alter_display' => 'alterMembershipStatusID',
            'options' => $this->_getOptions('membership', 'status_id', $action = 'get'),
            'is_fields' => TRUE,
            'is_filters' => TRUE,
            'is_group_bys' => TRUE,
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          ),
          'start_date' => array(
            'name' => 'start_date',
            'is_fields' => TRUE,
            'is_filters' => TRUE,
            'title' => ts($options['prefix_label'] . ' Start Date'),
            'type' => CRM_Utils_Type::T_DATE,
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
          'end_date' => array(
            'name' => 'end_date',
            'is_fields' => TRUE,
            'title' =>  ts($options['prefix_label'] . ' Membership Cycle End Date'),
            'include_null' => TRUE,
            'is_group_bys' => TRUE,
            'type' => CRM_Utils_Type::T_DATE,
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
        )));
    return $this->buildColumns($columns['civicrm_membership_log']['fields'], $options['prefix'] . 'civicrm_membership_log', 'CRM_Member_DAO_MembershipLog', array(), $options);
  }

  function getFinancialAccountColumns($options = array()) {
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
    $defaults = $this->getDefaultsFromOptions($options);
    $spec = array(
      'accounting_code' => array(
        'title' => ts($options['prefix_label'] . 'Financial Account Code'),
        'name' => 'accounting_code',
        'type' => CRM_Utils_Type::T_STRING,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contribute_PseudoConstant::financialAccount(NULL, NULL, 'accounting_code', 'accounting_code'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ),
      'name' => array(
        'title' => ts($options['prefix_label'] . 'Financial Account Name'),
        'name' => 'name',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contribute_PseudoConstant::financialAccount(),
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ),
    );
    return $this->buildColumns($spec, $options['prefix'] . 'civicrm_financial_account', 'CRM_Financial_DAO_FinancialAccount', NULL, $defaults);
  }

  protected function getFinancialTrxnColumns() {
    $specs = array(
        'check_number' => array(
          'title' => ts('Cheque #'),
          'default' => TRUE,
          'type' => CRM_Utils_Type::T_STRING,
        ),
        'payment_instrument_id' => array(
          'title' => ts('Payment Instrument'),
          'default' => TRUE,
          'alter_display' => 'alterPaymentType',
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'options' => CRM_Contribute_PseudoConstant::paymentInstrument(),
          'type' => CRM_Utils_Type::T_INT,
          'is_fields' => TRUE,
          'is_filters' => TRUE,
          'is_order_bys' => TRUE,
        ),
        'currency' => array(
          'required' => TRUE,
          'no_display' => FALSE,
          'type' => CRM_Utils_Type::T_STRING,
          'title' => ts('Currency'),
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'options' => CRM_Core_OptionGroup::values('currencies_enabled'),
          'is_fields' => TRUE,
          'is_filters' => TRUE,
        ),
        'trxn_date' => array(
          'title' => ts('Transaction Date'),
          'default' => TRUE,
          'type' => CRM_Utils_Type::T_DATE,
          'operatorType' => CRM_Report_Form::OP_DATE,
          'is_fields' => TRUE,
          'is_filters' => TRUE,
        ),
        'trxn_id' => array(
          'title' => ts('Transaction #'),
          'default' => TRUE,
          'is_fields' => TRUE,
          'is_filters' => TRUE,
          'type' => CRM_Utils_Type::T_STRING,
        ),
        'financial_trxn_status_id' => array(
          'name' => 'status_id',
          'is_fields' => TRUE,
          'is_filters' => TRUE,
          'title' => ts('Transaction Status'),
          'filters_default' => array(1),
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'type' => CRM_Utils_Type::T_INT,
          'options' => CRM_Contribute_PseudoConstant::contributionStatus(),
        )
      );
      return $this->buildColumns($specs, 'civicrm_financial_trxn', 'CRM_Core_BAO_FinancialTrxn');
  }

  /**
   * @return array
   */
  protected function getFinancialTypeColumns() {
    $specs = [
      'name' => array(
        'title' => ts('Financial_type'),
        'type' => CRM_Utils_Type::T_STRING,
      ),
      'accounting_code' => array(
        'title' => ts('Accounting Code'),
        'type' => CRM_Utils_Type::T_STRING,
      ),
      'is_deductible' => array(
        'title' => ts('Tax Deductible'),
        'type' => CRM_Utils_Type::T_BOOLEAN,
      )
    ];
    return $this->buildColumns($specs, 'civicrm_financial_type', 'CRM_Financial_DAO_FinancialType', NULL);
  }

  /**
   * Get the columns for the pledge payment.
   *
   * @param array $options
   *
   * @return array
   */
  protected function getPledgePaymentColumns($options) {
    $specs = array(
      $options['prefix'] . 'actual_amount' => array(
        'title' => ts($options['prefix'] . 'Amount Paid'),
        'type' => CRM_Utils_Type::T_MONEY,
        'statistics' => array('sum' => ts('Total Amount Paid')),
        'is_fields' => TRUE,
      ),
      $options['prefix'] . 'scheduled_date' => array(
        'type' => CRM_Utils_Type::T_DATE,
        'title' => ts($options['prefix'] . 'Scheduled Payment Due'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
        'frequency' => TRUE,
      ),
      $options['prefix'] . 'scheduled_amount' => array(
        'type' => CRM_Utils_Type::T_MONEY,
        'title' => ts($options['prefix_label'] .'Amount to be paid'),
        'is_fields' => TRUE,
        'statistics' => array(
          'sum' => ts('Amount to be paid'),
          'cumulative' => ts('Cumulative to be paid')),
      ),
      $options['prefix'] . 'status_id' => array(
        'type' => CRM_Utils_Type::T_INT,
        'title' => ts($options['prefix_label'] . 'Payment Status'),
        'is_fields' => FALSE,
        'is_filters' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contribute_PseudoConstant::contributionStatus(),
        'alter_display' => 'alterContributionStatus',
      ),
    );
    if (!empty($options['is_actions'])) {
      $specs = array_merge($specs, $this->getPledgePaymentActions());
    }

    return $this->buildColumns($specs, $options['prefix'] . 'civicrm_pledge_payment', 'CRM_Pledge_BAO_PledgePayment', $options['prefix'] . 'pledge_payment', $this->getDefaultsFromOptions($options), $options);

  }

  /**
   * Get the columns for the pledge payment.
   *
   * @param array $options
   *
   * @return array
   */
  protected function getNextPledgePaymentColumns($options) {
    $specs = array(
      $options['prefix'] . 'scheduled_date' => array(
        'type' => CRM_Utils_Type::T_DATE,
        'title' => ts('Next Payment Due'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
        'frequency' => TRUE,
      ),
      $options['prefix'] . 'scheduled_amount' => array(
        'type' => CRM_Utils_Type::T_MONEY,
        'title' => ts('Next payment Amount'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ),
    );
    if (!empty($options['is_actions'])) {
      $specs = array_merge($specs, $this->getPledgePaymentActions());
    }
    return $this->buildColumns($specs, $options['prefix'] . 'civicrm_pledge_payment', 'CRM_Pledge_BAO_PledgePayment', $options['prefix'] . 'civicrm_pledge_payment', $this->getDefaultsFromOptions($options));
  }

  /**
   * Get actions for pledge payments.
   *
   * @return array
   */
  protected function getPledgePaymentActions() {
    $actions = array(
      'add_payment' => array(
        'type' => CRM_Utils_Type::T_INT,
        'title' => ts('Payment Link'),
        'name' => 'id',
        'alter_display' => 'alterPledgePaymentLink',
        // Otherwise it will be supressed. We retrieve & alter.
        'is_fields' => TRUE,
      ),
    );
    return $actions;
  }

  /**
   * @return array
   */
  function getMembershipTypeColumns($options) {
    $spec = array(
      'membership_type_id' => array(
        'name' => 'id',
        'title' => ts('Membership Types'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'type' => CRM_Utils_Type::T_INT,
        'options' => CRM_Member_PseudoConstant::membershipType(),
        'is_fields' => TRUE,
      ),
    );
    return $this->buildColumns($spec, $options['prefix'] . 'civicrm_membership_type', 'CRM_Member_DAO_MembershipType');

  }

  /**
   * Get a standardized array of <select> options for "Event Title"
   * - taken from core event class.
   *
   * @return array
   */
  function getEventFilterOptions() {
    $events = array();
    $query = "
      select id, start_date, title from civicrm_event
      where (is_template IS NULL OR is_template = 0) AND is_active
      order by title ASC, start_date
    ";
    $dao = CRM_Core_DAO::executeQuery($query);
    while ($dao->fetch()) {
      $events[$dao->id] = "{$dao->title} - " . CRM_Utils_Date::customFormat(substr($dao->start_date, 0, 10)) . " (ID {$dao->id})";
    }
    return $events;
  }

  /**
   * @param array $options
   *
   * @return array
   */
  function getEventColumns($options = array()) {
    $specs = array(
      'event_id' => array(
        'name' => 'id',
        'is_fields' => TRUE,
        'title' => ts('Event ID'),
        'type' => CRM_Utils_Type::T_INT,
      ),
      'title' => array(
        'title' => ts('Event Title'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'crm_editable' => array(
          'id_table' => 'civicrm_event',
          'id_field' => 'id',
          'entity' => 'event',
        ),
        'type' => CRM_Utils_Type::T_STRING,
        'name' => 'title',
        'operatorType' => CRM_Report_Form::OP_STRING,
      ),
      'event_type_id' => array(
        'title' => ts('Event Type'),
        'alter_display' => 'alterEventType',
        'name' => 'event_type_id',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_OptionGroup::values('event_type'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,

      ),
      'fee_label' => array(
        'title' => ts('Fee Label'),
        'is_fields' => TRUE,
      ),
      'event_start_date' => array(
        'title' => ts('Event Start Date'),
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
        'operatorType' => CRM_Report_Form::OP_DATE,
        'name' => 'start_date',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ),
      'event_end_date' => array(
        'title' => ts('Event End Date'),
        'is_fields' => TRUE,
        'name' => 'end_date',
      ),
      'max_participants' => array(
        'title' => ts('Capacity'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'crm_editable' => array(
          'id_table' => 'civicrm_event',
          'id_field' => 'id',
          'entity' => 'event'
        ),
      ),
      'is_active' => array(
        'title' => ts('Is Active'),
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_INT,
        'crm_editable' => array(
          'id_table' => 'civicrm_event',
          'id_field' => 'id',
          'entity' => 'event',
          'options' => array('0' => 'No', '1' => 'Yes'),
        ),
      ),
      'is_public' => array(
        'title' => ts('Is Publicly Visible'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'crm_editable' => array(
          'id_table' => 'civicrm_event',
          'id_field' => 'id',
          'entity' => 'event'
        ),
      ),
    );
    return $this->buildColumns($specs, 'civicrm_event', 'CRM_Event_DAO_Event', NULL, $this->getDefaultsFromOptions($options));
  }

  /**
   * Get Columns for Event totals Summary
   *
   * @param array $options
   *
   * @return array
   */
  function getEventSummaryColumns($options = array()) {
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
    // $fields['civicrm_event_summary' . $options['prefix']]['fields'] =
    $specs =  array(
        'registered_amount' . $options['prefix'] => array(
          'title' => $options['prefix_label'] . ts('Total Income'),
          'default' => TRUE,
          'type' => CRM_Utils_Type::T_MONEY,
          'statistics' => array('sum' => ts('Total Income')),
          'is_fields' => TRUE,
        ),
        'paid_amount' . $options['prefix'] => array(
          'title' => $options['prefix_label'] . ts('Paid Up Income'),
          'default' => TRUE,
          'type' => CRM_Utils_Type::T_MONEY,
          'statistics' => array('sum' => ts('Total Paid Up Income')),
          'is_fields' => TRUE,
        ),
        'pending_amount' . $options['prefix'] => array(
          'title' => $options['prefix_label'] . ts('Pending Income'),
          'default' => TRUE,
          'type' => CRM_Utils_Type::T_MONEY,
          'statistics' => array('sum' => ts('Total Pending Income')),
          'is_fields' => TRUE,
        ),
        'registered_count' . $options['prefix'] => array(
          'title' => $options['prefix_label'] . ts('No. Participants'),
          'default' => TRUE,
          'type' => CRM_Utils_Type::T_INT,
          'statistics' => array('sum' => ts('Total No. Participants')),
          'is_fields' => TRUE,
        ),
        'paid_count' . $options['prefix'] => array(
          'title' => $options['prefix_label'] . ts('Paid Up Participants'),
          'default' => TRUE,
          'type' => CRM_Utils_Type::T_INT,
          'statistics' => array('sum' => ts('Total No,. Paid Up Participants')),
          'is_fields' => TRUE,
        ),
        'pending_count' . $options['prefix'] => array(
          'title' => $options['prefix_label'] . ts('Pending Participants'),
          'default' => TRUE,
          'type' => CRM_Utils_Type::T_INT,
          'statistics' => array('sum' => ts('Total Pending Participants')),
          'is_fields' => TRUE,
        ),
      );
    return $this->buildColumns($specs, 'civicrm_event_summary' . $options['prefix'], NULL, NULL, $this->getDefaultsFromOptions($options));
  }

  /**
   *
   * @param array
   *
   * @return array
   */
  function getCampaignColumns() {

    if (!CRM_Campaign_BAO_Campaign::isCampaignEnable()) {
      return array('civicrm_campaign' => array('fields' => array(), 'metadata' => array()));
    }
    $specs = array(
      'campaign_type_id' => array(
        'title' => ts('Campaign Type'),
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_fields' => TRUE,
        'is_group_bys' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Campaign_BAO_Campaign::buildOptions('campaign_type_id'),
        'alter_display' => 'alterCampaignType',
      ),
      'id' => array(
        'title' => ts('Campaign'),
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_fields' => TRUE,
        'is_group_bys' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Campaign_BAO_Campaign::getCampaigns(),
        'alter_display' => 'alterCampaign',
      ),
      'goal_revenue' => array(
        'title' => ts('Revenue goal'),
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_MONEY,
      ),
    );
    return $this->buildColumns($specs, 'civicrm_campaign', 'CRM_Campaign_BAO_Campaign');
  }

  /**
   *
   * @param array
   *
   * @return array
   */
  function getContributionColumns($options) {

    $options = array_merge(['group_title' => E::ts('Contributions')], $options);
    $specs = array(
      'id' => array(
        'title' => ts('Contribution ID'),
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_fields' => TRUE,
        'is_group_bys' => TRUE,
      ),
      'financial_type_id' => array(
        'title' => ts('Contribution Type (Financial)'),
        'type' => CRM_Utils_Type::T_INT,
        'alter_display' => 'alterFinancialType',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contribute_PseudoConstant::financialType(),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
      ),
      'payment_instrument_id' => array(
        'title' => ts('Payment Instrument'),
        'type' => CRM_Utils_Type::T_INT,
        'alter_display' => 'alterPaymentType',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contribute_PseudoConstant::paymentInstrument(),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
      ),
      'contribution_status_id' => array(
        'title' => ts('Contribution Status'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contribute_PseudoConstant::contributionStatus(),
        'alter_display' => 'alterContributionStatus',
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
      ),
      'campaign_id' => array(
        'title' => ts('Campaign'),
        'type' => CRM_Utils_Type::T_INT,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Campaign_BAO_Campaign::getCampaigns(),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
        'alter_display' => 'alterCampaign',
      ),
      'source' => array(
        'title' => 'Contribution Source',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ),
      'trxn_id' => array('is_fields' => TRUE, 'is_order_bys' => TRUE,),
      'receive_date' => array(
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
        'operatorType' => CRM_Report_Form::OP_DATETIME,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ),
      'receipt_date' => array('is_fields' => TRUE, 'is_order_bys' => TRUE,),
      'total_amount' => array(
        'title' => ts('Contribution Amount'),
        'statistics' => array('count' => ts('No. Contributions'), 'sum' => ts('Total Amount')),
        'type' => CRM_Utils_Type::T_MONEY,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
      ),
      'fee_amount' => array('is_fields' => TRUE),
      'net_amount' => array('is_fields' => TRUE),
      'check_number' => array('is_fields' => TRUE, 'is_order_bys' => TRUE,),
      'contribution_page_id' => array(
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'title' => ts('Contribution Page'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => $this->_getOptions('Contribution', 'contribution_page_id'),
        'type' => CRM_Utils_Type::T_INT,
      ),
      'currency' => array(
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'title' => 'Currency',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_OptionGroup::values('currencies_enabled'),
        'default' => NULL,
        'type' => CRM_Utils_Type::T_STRING,
      ),
      'is_test' => array(
        'is_fields' => FALSE,
        'is_filters' => TRUE,
        'is_order_bys' => FALSE,
        'title' => 'Is a test transaction?',
        'operatorType' => CRM_Report_Form::OP_SELECT,
        'options' => array('' => '--select--') + CRM_Contribute_BAO_Contribution::buildOptions('is_test'),
        'default' => NULL,
        'type' => CRM_Utils_Type::T_STRING,
      ),
      'contact_id' => array(
        'title' => ts('Contribution Contact ID'),
        'name' => 'contact_id',
        'is_filters' => TRUE,
      ),
    );
    return $this->buildColumns($specs, 'civicrm_contribution', 'CRM_Contribute_BAO_Contribution', NULL, $this->getDefaultsFromOptions($options), $options);
  }

  /**
   * Get Columns for Contact Contribution Summary
   *
   * @param array $options
   *
   * @return array
   */
  function getContributionSummaryColumns($options = array()) {
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

    $spec =
      array(
        'contributionsummary' . $options['prefix'] => array(
          'title' => $options['prefix_label'] . ts('Contribution Details'),
          'default' => TRUE,
          'required' => TRUE,
          'alter_display' => 'alterDisplaytable2csv',
        ),

      );

    return $this->buildColumns($spec, $options['prefix'] . 'civicrm_contribution_summary', 'CRM_Contribute_DAO_Contribution', NULL, $defaults);
  }

  /**
   * Get columns for the batch.
   *
   * @return array
   */
  public function getBatchColumns() {
    if (!CRM_Batch_BAO_Batch::singleValueQuery("SELECT COUNT(*) FROM civicrm_batch")) {
      return array();
    }
    $specs = array(
      'title' => array(
        'title' => ts('Batch Title'),
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_fields' => TRUE,
        'is_group_bys' => TRUE,
        // keep free form text... there could be lots of batches after a while
        // make selection unwieldy.
        'type' => CRM_Utils_Type::T_STRING,
      ),
      'status_id' => array(
        'title' => ts('Batch Status'),
        'is_filters' => TRUE,
        'is_order_bys' => FALSE,
        'is_fields' => TRUE,
        'is_group_bys' => FALSE,
        // keep free form text... there could be lots of batches after a while
        // make selection unwieldy.
        'alter_display' => 'alterBatchStatus',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Batch_BAO_Batch::buildOptions("status_id"),
        'type' => CRM_Utils_Type::T_INT,
      ),
    );
    return $this->buildColumns($specs, 'civicrm_batch', 'CRM_Batch_DAO_Batch');
  }

  /**
   * Get phone columns to add to array
   *
   * @param array $options
   *  - prefix Prefix to add to table (in case of more than one instance of the table)
   *  - prefix_label Label to give columns from this phone table instance
   *
   * @return array phone columns definition
   */
  function getPhoneColumns($options = array()) {
    $defaultOptions = array(
      'prefix' => '',
      'prefix_label' => '',
      'group_by' => FALSE,
      'order_by' => TRUE,
      'filters' => TRUE,
      'defaults' => array(),
      'subquery' => TRUE,
      'fields_defaults' => array(),
      'filters_defaults' => array(),
      'group_bys_defaults' => array(),
      'order_by_defaults' => array(),
    );

    $options = array_merge($defaultOptions, $options);
    $defaults = $this->getDefaultsFromOptions($options);

    $spec = array(
      $options['prefix'] . 'phone' => array(
        'title' => ts($options['prefix_label'] . 'Phone'),
        'name' => 'phone',
        'is_fields' => TRUE,
      ),
    );

    if ($options['subquery']) {
      $spec[$options['prefix'] . 'phone']['alter_display'] = 'alterPhoneGroup';
    }
    return $this->buildColumns($spec, $options['prefix'] . 'civicrm_phone', 'CRM_Core_DAO_Phone', NULL, $defaults);
  }

  /**
   * Get phone columns to add to array
   *
   * @param array $options
   *  - prefix Prefix to add to table (in case of more than one instance of the table)
   *  - prefix_label Label to give columns from this phone table instance
   *
   * @return array pledge columns definition
   */
  protected function getPledgeColumns($options = array()) {
    $spec = array(
      'id' => array(
        'no_display' => TRUE,
        'required' => TRUE
      ),
      'contact_id' => array(
        'no_display' => TRUE,
        'required' => TRUE
      ),
      'amount' => array(
        'title' => ts('Pledged Amount'),
        'statistics' => array('sum' => ts('Total Pledge Amount')),
        'type' => CRM_Utils_Type::T_MONEY,
        'name' => 'amount',
        'operatorType' => CRM_Report_Form::OP_INT,
        'is_fields' => TRUE,
      ),
      'financial_type_id' => array(
        'title' => ts('Financial Type'),
        'type' => CRM_Utils_Type::T_INT,
        'alter_display' => 'alterFinancialType',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contribute_PseudoConstant::financialType(),
      ),
      'frequency_unit' => array(
        'title' => ts('Frequency Unit'),
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_STRING,
      ),
      'installments' => array(
        'title' => ts('Installments'),
        'is_fields' => TRUE,
      ),
      'create_date' => array(
        'title' => ts('Pledge Made Date'),
        'operatorType' => CRM_Report_Form::OP_DATE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ),
      'start_date' => array(
        'title' => ts('Pledge Start Date'),
        'type' => CRM_Utils_Type::T_DATE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ),
      'end_date' => array(
        'title' => ts('Pledge End Date'),
        'type' => CRM_Utils_Type::T_DATE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ),
      'status_id' => array(
        'name' => 'status_id',
        'title' => ts('Pledge Status'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_OptionGroup::values('contribution_status'),
        'is_fields' => TRUE,
        'is_group_bys' => TRUE,
        'type' => CRM_Utils_Type::T_INT,
      ),
      'campaign_id' => array(
        'title' => ts('Campaign'),
        'type' => CRM_Utils_Type::T_INT,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Campaign_BAO_Campaign::getCampaigns(),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
        'alter_display' => 'alterCampaign',
      ),
    );

    return $this->buildColumns($spec, 'civicrm_pledge', 'CRM_Pledge_DAO_Pledge');
  }

  /**
   * Get email columns.
   *
   * @param array $options column options

   * @param array $options
   *
   * @return array
   */
  function getEmailColumns($options = array()) {
    $defaultOptions = array(
      'prefix' => '',
      'prefix_label' => '',
      'group_by' => FALSE,
      'order_by' => TRUE,
      'filters' => TRUE,
      'fields_defaults' => array('display_name', 'id'),
      'filters_defaults' => array(),
      'group_bys_defaults' => array(),
      'order_by_defaults' => array('sort_name ASC'),
    );
    $options = array_merge($defaultOptions, $options);
    $defaults = $this->getDefaultsFromOptions($options);

    $fields = array(
      'email' => array(
        'title' => ts($options['prefix_label'] . 'Email'),
        'name' => 'email',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
        'type' => CRM_Utils_Type::T_STRING,
        'operatorType' => CRM_Report_Form::OP_STRING,
      ),
    );
    return $this->buildColumns($fields, $options['prefix'] . 'civicrm_email', 'CRM_Core_DAO_Email', NULL, $defaults, $options);
  }

  /**
   * Get email columns.
   *
   * @param array $options column options

   * @param array $options
   *
   * @return array
   */
  function getWebsiteColumns($options = array()) {
    $defaultOptions = array(
      'prefix' => '',
      'prefix_label' => '',
      'group_by' => FALSE,
      'order_by' => TRUE,
      'filters' => TRUE,
      'fields_defaults' => array('display_name', 'id'),
      'filters_defaults' => array(),
      'group_bys_defaults' => array(),
      'order_by_defaults' => array('sort_name ASC'),
    );
    $options = array_merge($defaultOptions, $options);
    $defaults = $this->getDefaultsFromOptions($options);

    $fields = array(
      'url' => array(
        'title' => $options['prefix_label'] . ts('Website'),
        'name' => 'url',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
        'type' => CRM_Utils_Type::T_STRING,
        'operatorType' => CRM_Report_Form::OP_STRING,
      ),
      'website_type_id' => array(
        'title' => $options['prefix_label'] . ts('Website Type'),
        'name' => 'website_type_id',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
        'type' => CRM_Utils_Type::T_STRING,
        'is_join_filters' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_BAO_Website::buildOptions('website_type_id'),
        'alter_display' => 'alterWebsiteTypeId',
      ),
    );
    return $this->buildColumns($fields, $options['prefix'] . 'civicrm_website', 'CRM_Core_DAO_Website', NULL, $defaults, $options);
  }

  /*
   * Get note columns
   * @param array $options column options
   */
  /**
   * @param array $options
   *
   * @return array
   */
  function getNoteColumns($options = array()) {
    $defaultOptions = array(
      'prefix' => '',
      'prefix_label' => '',
      'group_by' => FALSE,
      'order_by' => TRUE,
      'filters' => TRUE,
    );
    $options = array_merge($defaultOptions, $options);

    $fields = array(
      'note' => array(
        'title' => ts($options['prefix_label'] . 'Note'),
        'name' => 'note',
        'is_fields' => TRUE,
      ),
    );
    return $this->buildColumns($fields, $options['prefix'] . 'civicrm_note', 'CRM_Core_DAO_Note');
  }

  /**
   * Get columns for relationship fields.
   *
   * @param array $options
   *
   * @return array
   */
  function getRelationshipColumns($options = array()) {
    $defaultOptions = array(
      'prefix' => '',
      'prefix_label' => '',
      'group_by' => FALSE,
      'order_by' => TRUE,
      'filters' => TRUE,
      'fields_defaults' => array('display_name', 'id'),
      'filters_defaults' => array(),
      'group_bys_defaults' => array(),
      'order_by_defaults' => array('sort_name ASC'),
    );

    $options = array_merge($defaultOptions, $options);
    $defaults = $this->getDefaultsFromOptions($options);
    $prefix = $options['prefix'];
    $specs = array(
      $prefix . 'id' => array(
        'name' => 'id',
        'title' => ts('Relationship ID'),
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_STRING,
      ),
      $prefix . 'relationship_start_date' => array(
        'title' => ts('Relationship Start Date'),
        'name' => 'start_date',
        'type' => CRM_Utils_Type::T_DATE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_join_filters' => TRUE,
      ),
      $prefix . 'relationship_end_date' => array(
        'title' => ts('Relationship End Date'),
        'name' => 'end_date',
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_DATE,
        'is_filters' => TRUE,
        'is_join_filters' => TRUE,
      ),
      $prefix . 'relationship_description' => array(
        'title' => ts('Description'),
        'name' => 'description',
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ),
      $prefix . 'relationship_is_active' => array(
        'title' => ts('Relationship Status'),
        'name' => 'is_active',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => array(
          '' => '- Any -',
          1 => 'Active',
          0 => 'Inactive',
        ),
        'is_filters' => TRUE,
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_INT,
        'is_join_filters' => TRUE,
      ),
      $prefix . 'relationship_type_id' => array(
        'name' => 'relationship_type_id',
        'title' => ts('Relationship Type'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => array(
            '' => '- any relationship type -'
          ) +
          CRM_Contact_BAO_Relationship::getContactRelationshipType(NULL, 'null', NULL, NULL, TRUE),
        'type' => CRM_Utils_Type::T_INT,
        'is_filters' => TRUE,
        'is_join_filters' => FALSE,
      ),
      // For the join filters we will use a one-way option list to make our life easier.
      $prefix . 'join_relationship_type_id' => array(
        'name' => 'relationship_type_id',
        'title' => ts('Relationship Type'),
        'operatorType' => CRM_Report_Form::OP_SELECT,
        'options' => array(
            '' => '- any relationship type -'
          ) +
          $this->getRelationshipABOptions(),
        'type' => CRM_Utils_Type::T_INT,
        'is_join_filters' => TRUE,
      ),


    );

    return $this->buildColumns($specs, 'civicrm_relationship', 'CRM_Contact_BAO_Relationship', NULL, $defaults);
  }

  protected function getRelationshipTypeColumns($options = array()) {
    $defaultOptions = array(
      'prefix' => '',
      'prefix_label' => '',
      'group_by' => FALSE,
      'order_by' => TRUE,
      'filters' => TRUE,
      'fields_defaults' => array('display_name', 'id'),
      'filters_defaults' => array(),
      'group_bys_defaults' => array(),
      'order_by_defaults' => array('sort_name ASC'),
    );

    $options = array_merge($defaultOptions, $options);
    $defaults = $this->getDefaultsFromOptions($options);
    $specs = array(
      'label_a_b' => array(
        'title' => ts('Relationship A-B '),
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => 1,
        'is_filters' => 1,
      ),
      'label_b_a' => array(
        'title' => ts('Relationship B-A '),
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => 1,
        'is_filters' => 1,
      ),
      'contact_type_a' => array(
        'title' => ts('Contact Type  A'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contact_BAO_Contact::buildOptions('contact_type'),
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => 0,
        'is_filters' => 1,
      ),
      'contact_type_b' => array(
        'title' => ts('Contact Type  B'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contact_BAO_Contact::buildOptions('contact_type'),
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => 0,
        'is_filters' => 1,
      ),
    );
    return $this->buildColumns($specs, 'civicrm_relationship_type', 'CRM_Contact_BAO_RelationshipType', NULL, $defaults);
  }

  /**
   * Get an array of relationships in an mono a-b direction
   *
   * @return array
   *   Options in a format id => label
   */
  protected function getRelationshipABOptions() {
    $relationshipTypes = civicrm_api3('relationship_type', 'get', array(
      'contact_type_a' => 'Individual',
      'is_active' => TRUE,
    ));
    $options = array();
    foreach ($relationshipTypes['values'] as $values) {
      $options[$values['id']] = $values['label_a_b'];
    }

    return $options;
  }

  /**
   * Add tab to report allowing a relationship to be chosen for extension.
   */
  protected function addJoinFiltersTab() {
    $this->tabs['Relationships'] = array(
      'title' => ts('Join Filters'),
      'tpl' => 'Relationships',
      'div_label' => 'set-relationships',
    );
  }

  /**
   * Filter statistics.
   *
   * @param array $statistics
   */
  public function filterStat(&$statistics) {
    foreach ($this->getSelectedFilters() as $fieldName => $field) {
      $statistics['filters'][] = $this->getQillForField($field, $fieldName);
    }
    foreach ($this->getSelectedJoinFilters() as $fieldName => $field) {
      $join = $this->getQillForField($field, $fieldName, 'join_filter_');
      $join['title'] = E::ts('%1 only included based on filter ', [$field['entity']]) . $join['title'];
      $statistics['filters'][] = $join;
    }
  }

  /*
  * function for adding address fields to construct function in reports
  * @param array $options Options for the report
  * - prefix prefix to add (e.g. 'honor' when getting address details for honor contact
  * - prefix_label optional prefix lable eg. "Honoree " for front end
  * - group_by enable these fields for group by - default false
  * - order_by enable these fields for order by
  * - filters enable these fields for filtering
  * - defaults - (is this working?) values to pre-populate
  * @return array address fields for construct clause
  */
  /**
   * Get address columns to add to array.
   *
   * @param array $options
   *  - prefix Prefix to add to table (in case of more than one instance of the table)
   *  - prefix_label Label to give columns from this address table instance
   *
   * @return array address columns definition
   */
  function getAddressColumns($options = array()) {
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
      $options['prefix'] . 'name' => array(
        'title' => ts($options['prefix_label'] . 'Address Name'),
        'name' => 'name',
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_STRING,
      ),
      $options['prefix'] . 'display_address' => array(
        'title' => ts($options['prefix_label'] . 'Display Address'),
        'pseudofield' => TRUE,
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_STRING,
        'requires' => array(
          $options['prefix'] . 'address_name',
          $options['prefix'] . 'address_supplemental_address_1',
          $options['prefix'] . 'address_supplemental_address_2',
          $options['prefix'] . 'address_supplemental_address_3',
          $options['prefix'] . 'address_street_address',
          $options['prefix'] . 'address_city',
          $options['prefix'] . 'address_postal_code',
          $options['prefix'] . 'address_postal_code_suffix',
          $options['prefix'] . 'address_county_id',
          $options['prefix'] . 'address_country_id',
          $options['prefix'] . 'address_state_province_id',
          $options['prefix'] . 'address_is_primary',
          $options['prefix'] . 'address_location_type_id',
        ),
        'alter_display' => 'alterDisplayAddress',
      ),
      $options['prefix'] . 'street_number' => array(
        'name' => 'street_number',
        'title' => ts($options['prefix_label'] . 'Street Number'),
        'type' => 1,
        'crm_editable' => array(
          'id_table' => 'civicrm_address',
          'id_field' => 'id',
          'entity' => 'address',
        ),
        'is_fields' => TRUE,
      ),
      $options['prefix'] . 'street_name' => array(
        'name' => 'street_name',
        'title' => ts($options['prefix_label'] . 'Street Name'),
        'type' => 1,
        'crm_editable' => array(
          'id_table' => 'civicrm_address',
          'id_field' => 'id',
          'entity' => 'address',
        ),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'operator' => 'like',
        'is_order_bys' => TRUE,
      ),
      $options['prefix'] . 'street_address' => array(
        'title' => ts($options['prefix_label'] . 'Street Address'),
        'name' => 'street_address',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,

      ),
      $options['prefix'] . 'supplemental_address_1' => array(
        'title' => ts($options['prefix_label'] . 'Supplementary Address Field 1'),
        'name' => 'supplemental_address_1',
        'crm_editable' => array(
          'id_table' => 'civicrm_address',
          'id_field' => 'id',
          'entity' => 'address',
        ),
        'is_fields' => TRUE,
      ),
      $options['prefix'] . 'supplemental_address_2' => array(
        'title' => ts($options['prefix_label'] . 'Supplementary Address Field 2'),
        'name' => 'supplemental_address_2',
        'crm_editable' => array(
          'id_table' => 'civicrm_address',
          'id_field' => 'id',
          'entity' => 'address',
        ),
        'is_fields' => TRUE,
      ),
      $options['prefix'] . 'supplemental_address_3' => array(
        'title' => ts($options['prefix_label'] . 'Supplementary Address Field 3'),
        'name' => 'supplemental_address_3',
        'crm_editable' => array(
          'id_table' => 'civicrm_address',
          'id_field' => 'id',
          'entity' => 'address',
        ),
        'is_fields' => TRUE,
      ),
      $options['prefix'] . 'street_number' => array(
        'name' => 'street_number',
        'title' => ts($options['prefix_label'] . 'Street Number'),
        'type' => 1,
        'is_order_bys' => TRUE,
        'is_filters' => TRUE,
        'is_fields' => TRUE,
      ),
      $options['prefix'] . 'street_name' => array(
        'name' => 'street_name',
        'title' => ts($options['prefix_label'] . 'Street Name'),
        'type' => 1,
        'is_fields' => TRUE,
      ),
      $options['prefix'] . 'street_unit' => array(
        'name' => 'street_unit',
        'title' => ts($options['prefix_label'] . 'Street Unit'),
        'type' => 1,
        'is_fields' => TRUE,
      ),
      $options['prefix'] . 'city' => array(
        'title' => ts($options['prefix_label'] . 'City'),
        'name' => 'city',
        'operator' => 'like',
        'crm_editable' => array(
          'id_table' => 'civicrm_address',
          'id_field' => 'id',
          'entity' => 'address',
        ),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
      ),
      $options['prefix'] . 'postal_code' => array(
        'title' => ts($options['prefix_label'] . 'Postal Code'),
        'name' => 'postal_code',
        'type' => 1,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
      ),
      $options['prefix'] . 'postal_code_suffix' => array(
        'title' => ts($options['prefix_label'] . 'Postal Code Suffix'),
        'name' => 'postal_code',
        'type' => 1,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
      ),
      $options['prefix'] . 'county_id' => array(
        'title' => ts($options['prefix_label'] . 'County'),
        'alter_display' => 'alterCountyID',
        'name' => 'county_id',
        'type' => CRM_Utils_Type::T_INT,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_PseudoConstant::county(),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
      ),
      $options['prefix'] . 'state_province_id' => array(
        'title' => ts($options['prefix_label'] . 'State/Province'),
        'alter_display' => 'alterStateProvinceID',
        'name' => 'state_province_id',
        'type' => CRM_Utils_Type::T_INT,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_PseudoConstant::stateProvince(),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
      ),
      $options['prefix'] . 'country_id' => array(
        'title' => ts($options['prefix_label'] . 'Country'),
        'alter_display' => 'alterCountryID',
        'name' => 'country_id',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'type' => CRM_Utils_Type::T_INT,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_PseudoConstant::country(),
      ),
      $options['prefix'] . 'location_type_id' => array(
        'name' => 'is_primary',
        'title' => ts($options['prefix_label'] . 'Location Type'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'alter_display' => 'alterLocationTypeID',
      ),
      $options['prefix'] . 'id' => array(
        'title' => ts($options['prefix_label'] . ' Address ID'),
        'name' => 'id',
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_INT,
      ),
      $options['prefix'] . 'is_primary' => array(
        'name' => 'is_primary',
        'title' => ts($options['prefix_label'] . 'Primary Address?'),
        'type' => CRM_Utils_Type::T_BOOLEAN,
        'is_fields' => TRUE,
      ),
    );
    return $this->buildColumns($spec, $options['prefix'] . 'civicrm_address', 'CRM_Core_DAO_Address', NULL, $defaults);
  }

  /**
   * Get Specification
   * for tag columns.
   * @param array $options
   *
   * @return array
   */
  function getTagColumns($options = array()) {
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
      'tag_name' => array(
        'name' => 'name',
        'title' => 'Tags associated with this person',
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_STRING,
      )
    );

    return $this->buildColumns($spec, $options['prefix'] . 'civicrm_tag', 'CRM_Core_DAO_EntityTag', NULL, $defaults);
  }

  /**
   * Get Information about advertised Joins.
   *
   * @return array
   */
  function getAvailableJoins() {
    return array(
      'batch_from_financialTrxn' => array(
        'leftTable' => 'civicrm_financial_trxn',
        'rightTable' => 'civicrm_batch',
        'callback' => 'joinBatchFromFinancialTrxn'
      ),
      'campaign_fromPledge' => array(
        'leftTable' => 'civicrm_pledge',
        'rightTable' => 'civicrm_campaign',
        'callback' => 'joinCampaignFromPledge',
      ),
      'pledge_from_pledge_payment' => array(
        'leftTable' => 'civicrm_pledge_payment',
        'rightTable' => 'civicrm_pledge',
        'callback' => 'joinPledgeFromPledgePayment',
      ),
      'priceFieldValue_from_lineItem' => array(
        'leftTable' => 'civicrm_line_item',
        'rightTable' => 'civicrm_price_field_value',
        'callback' => 'joinPriceFieldValueFromLineItem',
      ),
      'priceField_from_lineItem' => array(
        'leftTable' => 'civicrm_line_item',
        'rightTable' => 'civicrm_price_field',
        'callback' => 'joinPriceFieldFromLineItem',
      ),
      'participant_from_lineItem' => array(
        'leftTable' => 'civicrm_line_item',
        'rightTable' => 'civicrm_participant',
        'callback' => 'joinParticipantFromLineItem',
      ),
      'contribution_from_lineItem' => array(
        'leftTable' => 'civicrm_line_item',
        'rightTable' => 'civicrm_contribution',
        'callback' => 'joinContributionFromLineItem',
      ),
      'membership_from_lineItem' => array(
        'leftTable' => 'civicrm_line_item',
        'rightTable' => 'civicrm_membership',
        'callback' => 'joinMembershipFromLineItem',
      ),
      'contribution_from_contact' => array(
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_contribution',
        'callback' => 'joinContributionFromContact',
      ),
      'contribution_from_participant' => array(
        'leftTable' => 'civicrm_participant',
        'rightTable' => 'civicrm_contribution',
        'callback' => 'joinContributionFromParticipant',
      ),
      'contribution_from_membership' => array(
        'leftTable' => 'civicrm_membership',
        'rightTable' => 'civicrm_contribution',
        'callback' => 'joinContributionFromMembership',
      ),
      'financial_trxn_from_contribution' => array(
        'leftTable' => 'civicrm_contribution',
        'rightTable' => 'civicrm_financial_trxn',
        'callback' => 'joinFinancialTrxnFromContribution',
      ),
      'membership_from_contribution' => array(
        'leftTable' => 'civicrm_contribution',
        'rightTable' => 'civicrm_membership',
        'callback' => 'joinMembershipFromContribution',
      ),
      'membershipType_from_membership' => array(
        'leftTable' => 'civicrm_membership',
        'rightTable' => 'civicrm_membership_type',
        'callback' => 'joinMembershipTypeFromMembership',
      ),
      'membershipStatus_from_membership' => array(
        'leftTable' => 'civicrm_membership',
        'rightTable' => 'civicrm_membership_status',
        'callback' => 'joinMembershipStatusFromMembership',
      ),
      'lineItem_from_contribution' => array(
        'leftTable' => 'civicrm_contribution',
        'rightTable' => 'civicrm_line_item',
        'callback' => 'joinLineItemFromContribution',
      ),
      'lineItem_from_financialTrxn' => array(
        'leftTable' => 'civicrm_financial_trxn',
        'rightTable' => 'civicrm_contribution',
        'callback' => 'joinLineItemFromFinancialTrxn',
      ),
      'contact_from_participant' => array(
        'leftTable' => 'civicrm_participant',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinContactFromParticipant',
      ),
      'contact_from_membership' => array(
        'leftTable' => 'civicrm_membership',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinContactFromMembership',
      ),
      'contact_from_contribution' => array(
        'leftTable' => 'civicrm_contribution',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinContactFromContribution',
      ),
      'contact_from_pledge' => array(
        'leftTable' => 'civicrm_pledge',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinContactFromPledge',
      ),
      'next_payment_from_pledge' => array(
        'leftTable' => 'civicrm_pledge',
        'rightTable' => 'civicrm_pledge_payment',
        'callback' => 'joinNextPaymentFromPledge',
      ),
      'event_from_participant' => array(
        'leftTable' => 'civicrm_participant',
        'rightTable' => 'civicrm_event',
        'callback' => 'joinEventFromParticipant',
      ),
      'eventsummary_from_event' => array(
        'leftTable' => 'civicrm_event',
        'rightTable' => 'civicrm_event_summary',
        'callback' => 'joinEventSummaryFromEvent',
      ),
      'address_from_contact' => array(
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_address',
        'callback' => 'joinAddressFromContact',
      ),
      'contact_from_address' => array(
        'leftTable' => 'civicrm_address',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinContactFromAddress',
      ),
      'email_from_contact' => array(
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_email',
        'callback' => 'joinEmailFromContact',
      ),
      'website_from_contact' => array(
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_website',
        'callback' => 'joinWebsiteFromContact',
      ),
      'primary_phone_from_contact' => array(
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_phone',
        'callback' => 'joinPrimaryPhoneFromContact',
      ),
      'phone_from_contact' => array(
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_phone',
        'callback' => 'joinPhoneFromContact',
      ),
      'latestactivity_from_contact' => array(
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_email',
        'callback' => 'joinLatestActivityFromContact',
      ),
      'entitytag_from_contact' => array(
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_tag',
        'callback' => 'joinEntityTagFromContact',
      ),
      'contribution_summary_table_from_contact' => array(
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_contribution_summary',
        'callback' => 'joinContributionSummaryTableFromContact',
      ),
      'contact_from_case' => array(
        'leftTable' => 'civicrm_case',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinContactFromCase',
      ),
      'case_from_activity' => array(
        'leftTable' => 'civicrm_activity',
        'rightTable' => 'civicrm_case',
        'callback' => 'joinCaseFromActivity',
      ),
      'case_activities_from_case' => array(
        'callback' => 'joinCaseActivitiesFromCase',
      ),
      'single_contribution_comparison_from_contact' => array(
        'callback' => 'joinContributionSinglePeriod'
      ),
      'activity_from_case' => array(
        'leftTable' => 'civicrm_case',
        'rightTable' => 'civicrm_activity',
        'callback' => 'joinActivityFromCase',
      ),
      'activity_target_from_activity' => array(
        'leftTable' => 'civicrm_activity',
        'rightTable' => 'civicrm_activity_contact',
        'callback' => 'joinActivityTargetFromActivity',
      ),
      'related_contact_from_participant' => array(
        'leftTable' => 'civicrm_participant',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinRelatedContactFromParticipant',
      ),
      'note_from_participant' => array(
        'leftTable' => 'civicrm_participant',
        'rightTable' => 'civicrm_note',
        'callback' => 'joinNoteFromParticipant',
      ),
    );
  }

  /**
   * Define join from Activity to Activity Target
   */
  protected function joinActivityTargetFromActivity() {
    $activityContacts = CRM_Core_OptionGroup::values('activity_contacts', FALSE, FALSE, FALSE, NULL, 'name');
    $targetID = CRM_Utils_Array::key('Activity Targets', $activityContacts);
    $this->_from .= "
      LEFT JOIN civicrm_activity_contact civicrm_activity_target
        ON {$this->_aliases['civicrm_activity']}.id = civicrm_activity_target.activity_id
        AND civicrm_activity_target.record_type_id = {$targetID}
      LEFT JOIN civicrm_contact {$this->_aliases['target_civicrm_contact']}
        ON civicrm_activity_target.contact_id = {$this->_aliases['target_civicrm_contact']}.id
        AND {$this->_aliases['target_civicrm_contact']}.is_deleted = 0
      ";
  }

  /**
   * Define join from Activity to Activity Assignee
   */
  protected function joinActivityAssigneeFromActivity() {
    $activityContacts = CRM_Core_OptionGroup::values('activity_contacts', FALSE, FALSE, FALSE, NULL, 'name');
    $assigneeID = CRM_Utils_Array::key('Activity Assignees', $activityContacts);
    $this->_from .= "
      LEFT JOIN civicrm_activity_contact civicrm_activity_assignment
        ON {$this->_aliases['civicrm_activity']}.id = civicrm_activity_assignment.activity_id
        AND civicrm_activity_assignment.record_type_id = {$assigneeID}
      LEFT JOIN civicrm_contact {$this->_aliases['assignee_civicrm_contact']}
        ON civicrm_activity_assignment.contact_id = {$this->_aliases['assignee_civicrm_contact']}.id
     ";
  }

  /**
   * Define join from Activity to Activity Source.
   */
   protected function joinActivitySourceFromActivity() {
    $activityContacts = CRM_Core_OptionGroup::values('activity_contacts', FALSE, FALSE, FALSE, NULL, 'name');
    $sourceID = CRM_Utils_Array::key('Activity Source', $activityContacts);
    $this->_from .= "
      LEFT JOIN civicrm_activity_contact civicrm_activity_source
      ON {$this->_aliases['civicrm_activity']}.id = civicrm_activity_source.activity_id
      AND civicrm_activity_source.record_type_id = {$sourceID}
      LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
      ON civicrm_activity_source.contact_id = {$this->_aliases['civicrm_contact']}.id
      ";
  }

  /**
   * Add join from contact table to address.
   *
   * Prefix will be added to both tables as it's assumed you are using it to get address of a secondary contact
   *
  * @param string $prefix prefix to add to table names
  * @param array $extra extra join parameters
   *
  * @return bool true or false to denote whether extra filters can be appended to join
   */
  protected function joinAddressFromContact($prefix = '', $extra = array()) {

    $this->_from .= " LEFT JOIN civicrm_address {$this->_aliases[$prefix . 'civicrm_address']}
    ON {$this->_aliases[$prefix . 'civicrm_address']}.contact_id = {$this->_aliases[$prefix . 'civicrm_contact']}.id
    AND {$this->_aliases[$prefix . 'civicrm_address']}.is_primary = 1
    ";
    return TRUE;
  }

  /**
   * Add join from address table to contact.
   *
   * @param string $prefix prefix to add to table names
   * @param array $extra extra join parameters
   *
   * @return bool true or false to denote whether extra filters can be appended to join
   */
  protected  function joinContactFromAddress($prefix = '', $extra = array()) {

    $this->_from .= " LEFT JOIN civicrm_contact {$this->_aliases[$prefix . 'civicrm_contact']}
    ON {$this->_aliases[$prefix . 'civicrm_address']}.contact_id = {$this->_aliases[$prefix . 'civicrm_contact']}.id
    ";
    return TRUE;
  }

  /**
  * Add join from contact table to email.
   *
   * Prefix will be added to both tables as it's assumed you are using it to get address of a secondary contact.
   *
   * @param string $prefix
   * @param array $extra
   */
  protected function joinEmailFromContact($prefix = '', $extra = array()) {
    $this->_from .= " LEFT JOIN civicrm_email {$this->_aliases[$prefix . 'civicrm_email']}
   ON {$this->_aliases[$prefix . 'civicrm_email']}.contact_id = {$this->_aliases[$prefix . 'civicrm_contact']}.id
   AND {$this->_aliases[$prefix . 'civicrm_email']}.is_primary = 1
";
  }

  /**
   * Add join from contact table to email.
   *
   * Prefix will be added to both tables as it's assumed you are using it to get address of a secondary contact.
   *
   * @param string $prefix
   * @param array $extra
   */
  protected function joinWebsiteFromContact($prefix = '', $extra = array()) {
    $this->_from .= " LEFT JOIN civicrm_website {$this->_aliases[$prefix . 'civicrm_website']}
   ON {$this->_aliases[$prefix . 'civicrm_website']}.contact_id = {$this->_aliases[$prefix . 'civicrm_contact']}.id
";
    if (!empty($this->joinClauses['civicrm_website'])) {
      $this->_from .= ' AND ' . implode(',', $this->joinClauses['civicrm_website']);
    }
  }

   protected function joinCampaignFromPledge($prefix = '', $extra = array()) {
    $this->_from .= " LEFT JOIN civicrm_campaign {$this->_aliases[$prefix . 'civicrm_campaign']}
     ON {$this->_aliases[$prefix . 'civicrm_campaign']}.id = {$this->_aliases[$prefix . 'civicrm_pledge']}.campaign_id
    ";
  }
  /**
   * Add join from contact table to phone.
   *
   * This join may include multiple phones & should be used when displaying the phone block.
   *
   * @param string $prefix
   * @param array $extra
   */
  function joinPhoneFromContact($prefix = '', $extra = array()) {
    $this->_from .= " LEFT JOIN civicrm_phone {$this->_aliases[$prefix . 'civicrm_phone']}
    ON {$this->_aliases[$prefix . 'civicrm_phone']}.contact_id = {$this->_aliases[$prefix . 'civicrm_contact']}.id
    AND {$this->_aliases[$prefix . 'civicrm_phone']}.is_primary = 1
    ";
  }

  /**
   * Add join from contact table to primary phone.
   *
   * @param string $prefix
   * @param array $extra
   */
  function joinPrimaryPhoneFromContact($prefix = '', $extra = []) {
    $this->_from .= " LEFT JOIN civicrm_phone {$this->_aliases[$prefix . 'civicrm_phone']}
    ON {$this->_aliases[$prefix . 'civicrm_phone']}.contact_id = {$this->_aliases[$prefix . 'civicrm_contact']}.id
    AND {$this->_aliases[$prefix . 'civicrm_phone']}.is_primary = 1
    ";
  }

  /*
   *
   */
  /**
   * @param string $prefix
   */
  function joinEntityTagFromContact($prefix = '', $extra = array()) {
    if (!$this->isTableSelected($prefix . 'civicrm_tag')) {
      return;
    }
    $identifier = 'entity_tag';
    if (!isset($this->temporaryTables[$identifier])) {
      $tmpTableName = $this->createTemporaryTableFromColumns($identifier, '`contact_id` INT(10) NOT NULL, `name` varchar(255) NULL');
      $this->executeReportQuery("ALTER TABLE $tmpTableName ADD index (contact_id)");

      $sql = " INSERT INTO $tmpTableName
      SELECT entity_id AS contact_id, GROUP_CONCAT(name SEPARATOR ', ') as name
      FROM civicrm_entity_tag et
      LEFT JOIN civicrm_tag t ON et.tag_id = t.id
      GROUP BY et.entity_id
    ";

      $this->executeReportQuery($sql);
    }
    $this->_from .= "
    LEFT JOIN {$this->temporaryTables[$identifier]['name']} {$this->_aliases[$prefix . 'civicrm_tag']}
    ON {$this->_aliases[$prefix . 'civicrm_contact']}.id = {$this->_aliases[$prefix . 'civicrm_tag']}.contact_id
    ";
  }

  /*
   * At this stage we are making this unfilterable but later will add
   * some options to filter this join. We'll do a full temp table for now
   * We create 3 temp tables because we can't join twice onto a temp table (for inserting)
   * & it's hard to see how to otherwise avoid nasty joins or unions
   *
   *
   */
  function joinLatestActivityFromContact() {
    if (!$this->isTableSelected('civicrm_activity')) {
      return;
    }
    static $tmpTableName = NULL;
    if (empty($tmpTableName)) {

      $tmpTableName = 'civicrm_report_temp_lastestActivity' . date('his') . rand(1, 1000);
      $sql = "CREATE {$this->_temporary} TABLE $tmpTableName
   (
    `contact_id` INT(10) NOT NULL,
    `id` INT(10) NULL,
    `activity_type_id` VARCHAR(50) NULL,
    `activity_date_time` DATETIME NULL,
    PRIMARY KEY (`contact_id`)
  )
  ENGINE=HEAP;";
      CRM_Core_DAO::executeQuery($sql);

      $sql = "
      REPLACE INTO $tmpTableName
      SELECT contact_id, a.id, activity_type_id, activity_date_time
      FROM
      (  SELECT contact_id, a.id, activity_type_id, activity_date_time FROM
        civicrm_activity_contact ac
        LEFT JOIN civicrm_activity a ON a.id = ac.activity_id
        ORDER BY contact_id,  activity_date_time DESC
      ) as a
      GROUP BY contact_id
      ";
      CRM_Core_DAO::disableFullGroupByMode();
      CRM_Core_DAO::executeQuery($sql);
    }
    $this->_from .= " LEFT JOIN $tmpTableName {$this->_aliases['civicrm_activity']}
   ON {$this->_aliases['civicrm_activity']}.contact_id = {$this->_aliases['civicrm_contact']}.id";

  }

  function joinPriceFieldValueFromLineItem() {
    $this->_from .= " LEFT JOIN civicrm_price_field_value {$this->_aliases['civicrm_price_field_value']}
ON {$this->_aliases['civicrm_line_item']}.price_field_value_id = {$this->_aliases['civicrm_price_field_value']}.id";
  }

  function joinPriceFieldFromLineItem() {
    $this->_from .= "
LEFT JOIN civicrm_price_field {$this->_aliases['civicrm_price_field']}
ON {$this->_aliases['civicrm_line_item']}.price_field_id = {$this->_aliases['civicrm_price_field']}.id
";
  }

  /**
   * Define join from line item table to participant table.
   */
  protected function joinParticipantFromLineItem() {
    $this->_from .= " LEFT JOIN civicrm_participant {$this->_aliases['civicrm_participant']}
ON ( {$this->_aliases['civicrm_line_item']}.entity_id = {$this->_aliases['civicrm_participant']}.id
AND {$this->_aliases['civicrm_line_item']}.entity_table = 'civicrm_participant')
";
  }

  /**
   * Define join from pledge payment table to pledge table..
   */
  protected function joinPledgeFromPledgePayment() {
    $this->_from .= "
     LEFT JOIN civicrm_pledge {$this->_aliases['civicrm_pledge']}
     ON {$this->_aliases['civicrm_pledge_payment']}.pledge_id = {$this->_aliases['civicrm_pledge']}.id";
  }

  /**
   * Define join from pledge table to pledge payment table.
   */
  protected function joinPledgePaymentFromPledge() {
    $until = CRM_Utils_Array::value('effective_date_value', $this->_params);
    $pledgePaymentStatuses = civicrm_api3('PledgePayment', 'getoptions', array('field' => 'status_id'));
    $toPayIDs = array(array_search('Pending', $pledgePaymentStatuses['values']), array_search('Overdue', $pledgePaymentStatuses['values']));
    $this->_from .= "
      LEFT JOIN
      (SELECT p.*, p2.id, p2.scheduled_amount as next_scheduled_amount
      FROM (
        SELECT pledge_id, sum(if(status_id = 1, actual_amount, 0)) as actual_amount,
          IF(
            MIN(if(status_id IN (" . implode(',', $toPayIDs)  . "), scheduled_date, '2200-01-01')) <> '2200-01-01',
            MIN(if(status_id IN (" . implode(',', $toPayIDs)  . "), scheduled_date, '2200-01-01')),
          '') as scheduled_date,
          SUM(scheduled_amount) as scheduled_amount
        FROM civicrm_pledge_payment";
    if ($until) {
      $this->_from .=
        ' INNER JOIN civicrm_contribution c ON c.id = contribution_id  AND c.receive_date <="'
        .CRM_Utils_Type::validate(CRM_Utils_Date::processDate($until, 235959), 'Integer') . '"';
    }
    $this->_from .= "
       GROUP BY pledge_id) as p
      LEFT JOIN civicrm_pledge_payment p2
       ON p.pledge_id = p2.pledge_id AND p.scheduled_date = p2.scheduled_date
       AND p2.status_id IN (" . implode(',', $toPayIDs)  . ")
      ";
    $this->_from .= "
     ) as {$this->_aliases['civicrm_pledge_payment']}
     ON {$this->_aliases['civicrm_pledge_payment']}.pledge_id = {$this->_aliases['civicrm_pledge']}.id";
  }

  /**
   * Join the pledge to the next payment due.
   */
  protected function joinNextPaymentFromPledge() {
    CRM_Core_DAO::executeQuery('DROP TEMPORARY TABLE IF EXISTS next_pledge_payment');
    if (!$this->isTableSelected('next_civicrm_pledge_payment')) {
      $this->_from .= "";
    }
    else {
      CRM_Core_DAO::executeQuery('
        CREATE TEMPORARY TABLE next_pledge_payment
        SELECT pledge_id, 1 as id, min(scheduled_date) as next_scheduled_date
        FROM civicrm_pledge_payment
        WHERE status_id IN (2,6)
        GROUP BY pledge_id ORDER BY scheduled_date DESC;
      ');
      CRM_Core_DAO::executeQuery("
        ALTER TABLE next_pledge_payment
        ADD INDEX index_pledge_id (`pledge_id`),
        ADD COLUMN next_scheduled_amount  decimal(20,2) DEFAULT NULL,
        ADD COLUMN next_status_id  int(11) DEFAULT NULL
       ");
      CRM_Core_DAO::executeQuery("
        UPDATE next_pledge_payment np
        INNER JOIN civicrm_pledge_payment pp
        ON pp.pledge_id = np.pledge_id
        AND pp.scheduled_date = np.next_scheduled_date
        AND pp.status_id IN (2,6)
        SET np.id = pp.id,
        np.next_scheduled_amount = pp.scheduled_amount,
        np.next_status_id = pp.status_id
      ");
      $this->_from .= "
      LEFT JOIN next_pledge_payment {$this->_aliases['next_civicrm_pledge_payment']}
      ON {$this->_aliases['next_civicrm_pledge_payment']}.pledge_id = {$this->_aliases['civicrm_pledge']}.id
      ";
    }
  }
  /**
   * Define conditional join to related contact from participant.
   *
   * The parameters for this come from the relationship tab.
   */
  protected function joinRelatedContactFromParticipant() {
    if (1 || !empty($this->joinClauses)
      || $this->isTableSelected($this->_aliases['related_civicrm_contact'])
      || $this->isTableSelected($this->_aliases['related_civicrm_phone'])
    ) {
      $this->_from .= "
       LEFT JOIN civicrm_relationship {$this->_aliases['civicrm_relationship']}
         ON {$this->_aliases['civicrm_participant']}.contact_id = {$this->_aliases['civicrm_relationship']}.contact_id_a";
      if (!empty($this->joinClauses)) {
        $this->_from .= " AND " . implode($this->joinClauses['civicrm_relationship']);
      }
      $this->_from .= " LEFT JOIN civicrm_contact {$this->_aliases['related_civicrm_contact']}
        ON {$this->_aliases['civicrm_relationship']}.contact_id_b = {$this->_aliases['related_civicrm_contact']}.id";
      $this->_from .= " LEFT JOIN civicrm_phone {$this->_aliases['related_civicrm_phone']}
        ON {$this->_aliases['related_civicrm_phone']}.contact_id = {$this->_aliases['related_civicrm_contact']}.id
        AND {$this->_aliases['related_civicrm_phone']}.is_primary = 1";
      $this->_from .= " LEFT JOIN civicrm_email {$this->_aliases['related_civicrm_email']}
        ON {$this->_aliases['related_civicrm_email']}.contact_id = {$this->_aliases['related_civicrm_contact']}.id
        AND {$this->_aliases['related_civicrm_email']}.is_primary = 1
        ";

    }
  }

  /**
   * Define join from relationship table to relationship type table.
   */
  protected function joinRelationshipTypeFromRelationship() {
    $this->_from .= "
      INNER JOIN civicrm_relationship_type {$this->_aliases['civicrm_relationship_type']}
      ON ( {$this->_aliases['civicrm_relationship']}.relationship_type_id  =
      {$this->_aliases['civicrm_relationship_type']}.id  ) 
    ";
  }

  /**
   * Define join from line item table to Membership table.
   */
  protected function joinMembershipFromLineItem() {
    $this->_from .= "
      LEFT JOIN civicrm_membership {$this->_aliases['civicrm_membership']}
      ON {$this->_aliases['civicrm_line_item']}.entity_id = {$this->_aliases['civicrm_membership']}.id
      AND {$this->_aliases['civicrm_line_item']}.entity_table = 'civicrm_membership'
    ";
  }

  /**
   * Define join from Contact to Contribution table
   */
  function joinContributionFromContact() {
    if (empty($this->_aliases['civicrm_contact'])) {
      $this->_aliases['civicrm_contact'] = 'civireport_contact';
    }
    $this->_from .= " LEFT JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
    ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_contribution']}.contact_id
    AND {$this->_aliases['civicrm_contribution']}.is_test = 0
  ";
  }

  /**
   * Define join from Participant to Contribution table
   */
  function joinContributionFromParticipant() {
    $this->_from .= " LEFT JOIN civicrm_participant_payment pp
ON {$this->_aliases['civicrm_participant']}.id = pp.participant_id
LEFT JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
ON pp.contribution_id = {$this->_aliases['civicrm_contribution']}.id
";
  }

  /**
   * Define join from Membership to Contribution table
   */
  function joinContributionFromMembership() {
    $this->_from .= "
      LEFT JOIN civicrm_membership_payment pp
      ON {$this->_aliases['civicrm_membership']}.id = pp.membership_id
  LEFT JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
  ON pp.contribution_id = {$this->_aliases['civicrm_contribution']}.id
";
  }

  function joinParticipantFromContribution() {
    $this->_from .= " LEFT JOIN civicrm_participant_payment pp
ON {$this->_aliases['civicrm_contribution']}.id = pp.contribution_id
LEFT JOIN civicrm_participant {$this->_aliases['civicrm_participant']}
ON pp.participant_id = {$this->_aliases['civicrm_participant']}.id";
  }

  function joinMembershipFromContribution() {
    $this->_from .= "
LEFT JOIN civicrm_membership_payment pp
ON {$this->_aliases['civicrm_contribution']}.id = pp.contribution_id
LEFT JOIN civicrm_membership {$this->_aliases['civicrm_membership']}
ON pp.membership_id = {$this->_aliases['civicrm_membership']}.id";
  }

  function joinMembershipTypeFromMembership() {
    $this->_from .= "
LEFT JOIN civicrm_membership_type {$this->_aliases['civicrm_membership_type']}
ON {$this->_aliases['civicrm_membership']}.membership_type_id = {$this->_aliases['civicrm_membership_type']}.id
";
  }

  /**
   *
   */
  function joinMembershipStatusFromMembership() {
    $this->_from .= "
    LEFT JOIN civicrm_membership_status {$this->_aliases['civicrm_membership_status']}
    ON {$this->_aliases['civicrm_membership']}.status_id = {$this->_aliases['civicrm_membership_status']}.id
    ";
  }

  /**
   * Join contribution table from line item.
   */
  protected function joinContributionFromLineItem() {
    $this->_from .= "
      LEFT JOIN civicrm_contribution as {$this->_aliases['civicrm_contribution']}
      ON {$this->_aliases['civicrm_line_item']}.contribution_id = {$this->_aliases['civicrm_contribution']}.id
    ";
  }

  /**
   * Join line item table from contribution.
   */
  protected function joinLineItemFromContribution() {
    $this->_from .= "
      LEFT JOIN civicrm_line_item as {$this->_aliases['civicrm_line_item']}
      ON {$this->_aliases['civicrm_line_item']}.contribution_id = {$this->_aliases['civicrm_contribution']}.id
    ";
  }


  protected function joinLineItemFromFinancialTrxn() {
    $this->_from .= "
    LEFT JOIN civicrm_entity_financial_trxn {$this->_aliases['civicrm_entity_financial_trxn']}_item
      ON ({$this->_aliases['civicrm_financial_trxn']}.id = {$this->_aliases['civicrm_entity_financial_trxn']}_item.financial_trxn_id
      AND {$this->_aliases['civicrm_entity_financial_trxn']}_item.entity_table = 'civicrm_financial_item')
    LEFT JOIN civicrm_financial_item fitem
      ON fitem.id = {$this->_aliases['civicrm_entity_financial_trxn']}_item.entity_id
    LEFT JOIN civicrm_financial_account credit_financial_item_financial_account
      ON fitem.financial_account_id = credit_financial_item_financial_account.id
    LEFT JOIN civicrm_line_item {$this->_aliases['civicrm_line_item']}
      ON  fitem.entity_id = {$this->_aliases['civicrm_line_item']}.id AND fitem.entity_table = 'civicrm_line_item'";
  }

  /**
   * Join financial transaction table from contribution table.
   */
  protected function joinFinancialTrxnFromContribution() {
    $this->_from .= "
      LEFT JOIN civicrm_entity_financial_trxn {$this->_aliases['civicrm_entity_financial_trxn']}
      ON ({$this->_aliases['civicrm_contribution']}.id = {$this->_aliases['civicrm_entity_financial_trxn']}.entity_id
      AND {$this->_aliases['civicrm_entity_financial_trxn']}.entity_table = 'civicrm_contribution')
      LEFT JOIN civicrm_financial_trxn {$this->_aliases['civicrm_financial_trxn']}
      ON {$this->_aliases['civicrm_financial_trxn']}.id = {$this->_aliases['civicrm_entity_financial_trxn']}.financial_trxn_id
    ";
  }

  /**
   * Join batch table from Financial Trxn.
   */
  protected function joinBatchFromFinancialTrxn() {
    if (!CRM_Batch_BAO_Batch::singleValueQuery("SELECT COUNT(*) FROM civicrm_batch")) {
      return array();
    }
    $this->_from .= "
      LEFT  JOIN civicrm_entity_batch entity_batch
        ON entity_batch.entity_id = {$this->_aliases['civicrm_financial_trxn']}.id
        AND entity_batch.entity_table = 'civicrm_financial_trxn'
      LEFT  JOIN civicrm_batch {$this->_aliases['civicrm_batch']}
        ON {$this->_aliases['civicrm_batch']}.id = entity_batch.batch_id";
  }


  protected function joinContactFromParticipant() {
    $this->_from .= "
      LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
ON {$this->_aliases['civicrm_participant']}.contact_id = {$this->_aliases['civicrm_contact']}.id
    ";

  }

  function joinNoteFromParticipant() {
    $this->_from .= " LEFT JOIN civicrm_note {$this->_aliases['civicrm_note']}
ON {$this->_aliases['civicrm_participant']}.id = {$this->_aliases['civicrm_note']}.entity_id";
  }

  function joinContactFromMembership() {
    $this->_from .= " LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
ON {$this->_aliases['civicrm_membership']}.contact_id = {$this->_aliases['civicrm_contact']}.id";
  }

  function joinContactFromContribution() {
    $this->_from .= " LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
ON {$this->_aliases['civicrm_contribution']}.contact_id = {$this->_aliases['civicrm_contact']}.id";
  }

  /**
   * Define join from pledge table to contact table.
   */
  function joinContactFromPledge() {
    $this->_from .= "
      LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
      ON {$this->_aliases['civicrm_pledge']}.contact_id = {$this->_aliases['civicrm_contact']}.id
    ";
  }

  /**
   * Define join from participant table to event table.
   */
  function joinEventFromParticipant() {
    $this->_from .= " LEFT JOIN civicrm_event {$this->_aliases['civicrm_event']}
ON ({$this->_aliases['civicrm_event']}.id = {$this->_aliases['civicrm_participant']}.event_id ) AND
({$this->_aliases['civicrm_event']}.is_template IS NULL OR
{$this->_aliases['civicrm_event']}.is_template = 0)";
  }

  /**
   * @param $prefix
   */
  function joinEventSummaryFromEvent($prefix) {
    $tempTable = 'civicrm_report_temp_contsumm' . $prefix . date('d_H_I') . rand(1, 10000);
    $registeredStatuses = CRM_Event_PseudoConstant::participantStatus(NULL, "class = 'Positive'");
    $registeredStatuses = implode(', ', array_keys($registeredStatuses));
    $pendingStatuses = CRM_Event_PseudoConstant::participantStatus(NULL, "class = 'Pending'");
    $pendingStatuses = implode(', ', array_keys($pendingStatuses));

    //@todo currently statuses are hard-coded as 1 for complete & 5-6 for pending
    $createSQL = "
    CREATE {$this->temporary} table  $tempTable (
      `event_id` INT(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'FK to Event ID',
      `paid_amount` DECIMAL(42,2) NULL DEFAULT 0,
      `registered_amount` DECIMAL(48,6) NULL DEFAULT 0,
      `pending_amount` DECIMAL(48,6) NOT NULL DEFAULT '0',
      `paid_count` INT(10) UNSIGNED NULL DEFAULT '0',
      `registered_count` INT(10) UNSIGNED NULL DEFAULT '0',
      `pending_count` INT(10) UNSIGNED NULL DEFAULT '0',
      PRIMARY KEY (`event_id`)
    )";
    $tempPayments = CRM_Core_DAO::executeQuery($createSQL);
    $tempPayments = CRM_Core_DAO::executeQuery(
      "INSERT INTO  $tempTable  (
       SELECT event_id
          , COALESCE(sum(total_amount)) as paid_amount
          , 0 as registered_amount
          , 0 as pending_amount
      , COALESCE(count(p.id)) as paid_count, 0 as registered_count, 0 as pending_count
       FROM civicrm_participant p
       LEFT JOIN civicrm_participant_payment pp on p.id = pp.participant_id
       LEFT JOIN civicrm_contribution c ON c.id = pp.contribution_id
       WHERE status_id IN ( $registeredStatuses )
       GROUP BY event_id)");
    $replaceSQL = "
      INSERT INTO $tempTable (event_id, pending_amount, pending_count)
      SELECT * FROM (
        SELECT event_id
        , COALESCE(sum(total_amount),0) as pending_amount
        , COALESCE(count(p.id)) as pending_count
        FROM civicrm_participant p
        LEFT JOIN civicrm_participant_payment pp on p.id = pp.participant_id
        LEFT JOIN civicrm_contribution c ON c.id = pp.contribution_id
        WHERE status_id IN ( $pendingStatuses ) GROUP BY event_id
      ) as p
      ON DUPLICATE KEY UPDATE pending_amount = p.pending_amount, pending_count = p.pending_count;
    ";

    $updateSQL = "UPDATE $tempTable SET registered_amount = (pending_amount  + paid_amount)
      , registered_count = (pending_count  + paid_count) ";
    CRM_Core_DAO::executeQuery($replaceSQL);
    CRM_Core_DAO::executeQuery($updateSQL);
    $this->_from .= "
      LEFT JOIN $tempTable {$this->_aliases['civicrm_event_summary' . $prefix]}
      ON {$this->_aliases['civicrm_event_summary' . $prefix]}.event_id = {$this->_aliases['civicrm_event']}.id
    ";
  }

  /**
   *
   * @param string $prefix
   * @param array $extra
   */
  function joinContributionSummaryTableFromContact($prefix, $extra) {
    CRM_Core_DAO::executeQuery("SET group_concat_max_len=15000");
    $tempTable = 'civicrm_report_temp_contsumm' . $prefix . date('d_H_I') . rand(1, 10000);
    $dropSql = "DROP TABLE IF EXISTS $tempTable";
    $criteria = " is_test = 0 ";
    if (!empty($extra['criteria'])) {
      $criteria .= " AND " . implode(' AND ', $extra['criteria']);
    }
    $createSql = "
      CREATE TABLE $tempTable (
      `contact_id` INT(10) UNSIGNED NOT NULL COMMENT 'Foreign key to civicrm_contact.id .',
      `contributionsummary{$prefix}` longtext NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
      INDEX `contact_id` (`contact_id`)
      )
      COLLATE='utf8_unicode_ci'
      ENGINE=InnoDB";
    $insertSql = "
      INSERT INTO
      $tempTable
      SELECT  contact_id,
        CONCAT('<table><tr>',
        GROUP_CONCAT(
        CONCAT(
        '<td>', DATE_FORMAT(receive_date,'%m-%d-%Y'),
        '</td><td>', financial_type_name,
        '</td><td>',total_amount, '</td>')
        ORDER BY receive_date DESC SEPARATOR  '<tr></tr>' )
      ,'</tr></table>') as contributions{$prefix}
      FROM (SELECT contact_id, receive_date, total_amount, name as financial_type_name
        FROM civicrm_contribution {$this->_aliases['civicrm_contribution']}
        LEFT JOIN civicrm_financial_type financial_type
        ON financial_type.id = {$this->_aliases['civicrm_contribution']}.financial_type_id
      WHERE $criteria
        ORDER BY receive_date DESC ) as conts
      GROUP BY contact_id
      ORDER BY NULL
     ";

    CRM_Core_DAO::executeQuery($dropSql);
    CRM_Core_DAO::executeQuery($createSql);
    CRM_Core_DAO::executeQuery($insertSql);
    $this->_from .= " LEFT JOIN $tempTable {$this->_aliases['civicrm_contribution_summary' . $prefix]}
      ON {$this->_aliases['civicrm_contribution_summary' . $prefix]}.contact_id = {$this->_aliases['civicrm_contact']}.id";
  }

  /**
   *
   */
  function joinCaseFromContact() {
    $this->_from .= " LEFT JOIN civicrm_case_contact casecontact ON casecontact.contact_id = {$this->_aliases['civicrm_contact']}.id
    LEFT JOIN civicrm_case {$this->_aliases['civicrm_case']} ON {$this->_aliases['civicrm_case']}.id = casecontact.case_id ";
  }

  /**
   *
   */
  function joinActivityFromCase() {
    $this->_from .= "
      LEFT JOIN {$this->_caseActivityTable} cca ON cca.case_id = {$this->_aliases['civicrm_case']}.id
      LEFT JOIN civicrm_activity {$this->_aliases['civicrm_activity']} ON {$this->_aliases['civicrm_activity']}.id = cca.activity_id";
  }

  /**
   *
   */
  function joinCaseFromActivity() {
    $this->_from .= "
      LEFT JOIN civicrm_case_activity cca ON {$this->_aliases['civicrm_activity']}.id = cca.activity_id
      LEFT JOIN civicrm_case {$this->_aliases['civicrm_case']} ON cca.case_id = {$this->_aliases['civicrm_case']}.id
    ";
  }

  /**
   *
   */
  function joinContactFromCase() {
    $this->_from .= "
    LEFT JOIN civicrm_case_contact ccc ON ccc.case_id = {$this->_aliases['civicrm_case']}.id
    LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']} ON {$this->_aliases['civicrm_contact']}.id = ccc.contact_id ";
  }

  /**
   * Get URL string of criteria to potentially pass to subreport - obtains
   * potential criteria from $this->_potenial criteria
   * @return string url string
   */
  function getCriteriaString() {
    $queryURL = "reset=1&force=1";
    if (!empty($this->_potentialCriteria) && is_array($this->_potentialCriteria)) {
      foreach ($this->_potentialCriteria as $criterion) {
        $name = $criterion . '_value';
        $op = $criterion . '_op';
        if (empty($this->_params[$name])) {
          continue;
        }
        $criterionValue = is_array($this->_params[$name]) ? implode(',', $this->_params[$name]) : $this->_params[$name];
        $queryURL .= "&{$name}=" . $criterionValue . "&{$op}=" . $this->_params[$op];
      }
    }
    return $queryURL;
  }
  /*
   * Retrieve text for contribution type from pseudoconstant
  */
  /**
   * @param string $value
   * @param array $row
   * @param string $selectedField
   * @param $criteriaFieldName
   * @param array $specs
   *
   * @return string
   */
  function alterCrmEditable($value, &$row, $selectedField, $criteriaFieldName, $specs) {
    $id_field = $specs['id_table'] . '_' . $specs['id_field'];
    if (empty($row[$id_field])) {
      // Check one more possible field...
      $id_field = $specs['id_table'] . '_' . $specs['entity'] . '_' . $specs['id_field'];
      if (empty($row[$id_field])) {
        //FIXME For some reason, the event id is returned with the entity repeated twice.
        //which means we need a tertiary check. This just a temporary fix
        $id_field = $specs['id_table'] . '_' . $specs['entity']. '_' . $specs['entity'] . '_' . $specs['id_field'];
        if (empty($row[$id_field])) {
          // If the relevant id has not been set on the report the field cannot be editable.
          return $value;
        }
      }
    }
    $entityID = $row[$id_field];
    $entity = $specs['entity'];
    $extra = $class = '';
    if (!empty($specs['options'])) {
      $specs['options']['selected'] = $value;
      $extra = "data-type='select'";
      $value = $specs['options'][$value];
      $class = 'editable_select';
    }
    //nodeName == "INPUT" && this.type=="checkbox"
    $editableDiv = "<div data-id='{$entityID}' data-entity='{$entity}' class='crm-entity'>" .
      "<span class='crm-editable crmf-{$specs['field_name']} $class ' data-action='create' $extra>" . $value . "</span></div>";
    return $editableDiv;
  }

  /*
* Retrieve text for contribution type from pseudoconstant
*/
  /**
   * @param $value
   * @param $row
   *
   * @return string
   */
  function alterNickName($value, &$row) {
    if (empty($row['civicrm_contact_id'])) {
      return;
    }
    $contactID = $row['civicrm_contact_id'];
    return "<div id=contact-{$contactID} class='crm-entity'><span class='crm-editable crmf-nick_name crm-editable-enabled' data-action='create'>" . $value . "</span></div>";
  }


  /**
   * Retrieve text for contribution type from pseudoconstant.
   *
   * @param $value
   * @param $row
   *
   * @return string
   */
  function alterFinancialType($value, &$row, $selectedField, $criteriaFieldName) {
    if ($this->_drilldownReport) {
      $criteriaQueryParams = CRM_Report_Utils_Report::getPreviewCriteriaQueryParams($this->_defaults, $this->_params);
      $url = CRM_Report_Utils_Report::getNextUrl(key($this->_drilldownReport),
        "reset=1&force=1&{$criteriaQueryParams}&" .
        "{$criteriaFieldName}_op=in&{$criteriaFieldName}_value={$value}",
        $this->_absoluteUrl, $this->_id
      );
      $row[$selectedField . '_link'] = $url;
    }
    $row[$selectedField . '_raw'] = $value;
    $financialTypes = explode(',', $value);
    $display = array();
    foreach ($financialTypes as $financialType) {
      $displayType = is_string(CRM_Contribute_PseudoConstant::financialType($financialType)) ? CRM_Contribute_PseudoConstant::financialType($financialType) : '';
      // Index the array in order to display each type only once.
      $display[$displayType] = $displayType;
    }
    return implode('|', $display);
  }

  /**
   * Retrieve text for contribution status from pseudoconstant
   * @param $value
   * @param $row
   *
   * @return array
   */
  function alterContributionStatus($value, &$row) {
    return CRM_Contribute_PseudoConstant::contributionStatus($value);
  }

  /**
   * @param $value
   * @param $row
   *
   * @return array
   */
  function alterCampaign($value, &$row) {
    $campaigns = CRM_Campaign_BAO_Campaign::getCampaigns();
    return CRM_Utils_Array::value($value, $campaigns);
  }

  /**
   * @param $value
   *
   * @return mixed
   */
  function alterCampaignType($value) {
    $values = CRM_Campaign_BAO_Campaign::buildOptions('campaign_type_id');
    return $values[$value];
  }

  /*
* Retrieve text for payment instrument from pseudoconstant
*/
  /**
   * @param int|null $value
   * @param array $row
   *
   * @return string
   *   Event label.
   */
  function alterEventType($value, &$row) {
    if (empty($value)) {
      return '';
    }
    return CRM_Event_PseudoConstant::eventType($value);
  }

  /**
   * replace event id with name & link to drilldown report
   *
   * @param string $value
   * @param array $row
   * @param string $selectedfield
   * @param string $criteriaFieldName
   *
   * @return string
   */
  function alterEventID($value, &$row, $selectedfield, $criteriaFieldName) {
    if (isset($this->_drilldownReport)) {
      $criteriaString = $this->getCriteriaString();
      $url = CRM_Report_Utils_Report::getNextUrl(implode(',', array_keys($this->_drilldownReport)),
        $criteriaString . '&event_id_op=in&event_id_value=' . $value,
        $this->_absoluteUrl, $this->_id, $this->_drilldownReport
      );
      $row[$selectedfield . '_link'] = $url;
      $row[$selectedfield . '_hover'] = ts(implode(',', $this->_drilldownReport));
    }
    return is_string(CRM_Event_PseudoConstant::event($value, FALSE)) ? CRM_Event_PseudoConstant::event($value, FALSE) : '';
  }

  /**
   * replace case id
   *
   * @param string $value
   * @param array $row
   * @param string $selectedField
   * @param string $criteriaFieldName
   */
  function alterCaseID($value, &$row, $selectedField, $criteriaFieldName) {
  }


  /**
   * @param $value
   * @param $row
   *
   * @return array|string
   */
  function alterMembershipTypeID($value, &$row) {
    return is_string(CRM_Member_PseudoConstant::membershipType($value, FALSE)) ? CRM_Member_PseudoConstant::membershipType($value, FALSE) : '';
  }

  /**
   * @param $value
   * @param $row
   *
   * @return array|string
   */
  function alterMembershipStatusID($value, &$row) {
    return is_string(CRM_Member_PseudoConstant::membershipStatus($value, FALSE)) ? CRM_Member_PseudoConstant::membershipStatus($value, FALSE) : '';
  }

  /**
   * @param $value
   * @param $row
   * @param $selectedField
   * @param $criteriaFieldName
   *
   * @return array
   */
  function alterCountryID($value, &$row, $selectedField, $criteriaFieldName) {
    $url = CRM_Utils_System::url(CRM_Utils_System::currentPath(), "reset=1&force=1&{$criteriaFieldName}_op=in&{$criteriaFieldName}_value={$value}", $this->_absoluteUrl);
    $row[$selectedField . '_link'] = $url;
    $row[$selectedField . '_hover'] = ts("%1 for this country.", array(
      1 => $value,
    ));
    $countries = CRM_Core_PseudoConstant::country($value, FALSE);
    if (!is_array($countries)) {
      return $countries;
    }
  }

  /**
   * @param $value
   * @param $row
   * @param $selectedfield
   * @param $criteriaFieldName
   *
   * @return array
   */
  function alterCountyID($value, &$row, $selectedfield, $criteriaFieldName) {
    $url = CRM_Utils_System::url(CRM_Utils_System::currentPath(), "reset=1&force=1&{$criteriaFieldName}_op=in&{$criteriaFieldName}_value={$value}", $this->_absoluteUrl);
    $row[$selectedfield . '_link'] = $url;
    $row[$selectedfield . '_hover'] = ts("%1 for this county.", array(
      1 => $value,
    ));
    $counties = CRM_Core_PseudoConstant::county($value, FALSE);
    if (!is_array($counties)) {
      return $counties;
    }
  }

  /**
   * @param $value
   * @param $row
   * @param $selectedfield
   * @param $criteriaFieldName
   *
   * @return array
   */
  function alterCumulative($value, &$row, $selectedfield, $criteriaFieldName) {
    if (!isset(\Civi::$statics[__CLASS__]) || !isset(\Civi::$statics[__CLASS__][$selectedfield . 'cumulative'])) {
      \Civi::$statics[__CLASS__][$selectedfield . 'cumulative'] = 0;
    }

    if (empty($row['is_rollup'])) {
      \Civi::$statics[__CLASS__][$selectedfield . 'cumulative'] = \Civi::$statics[__CLASS__][$selectedfield . 'cumulative'] + $value;
    }
    $row[str_replace('_sum', '_cumulative', $selectedfield)] = \Civi::$statics[__CLASS__][$selectedfield . 'cumulative'];
    return $value;
  }

  /**
   * @param $value
   * @param $row
   * @param $selectedfield
   * @param $criteriaFieldName
   *
   * @return mixed
   */
  function alterLocationTypeID($value, &$row, $selectedfield, $criteriaFieldName) {
    $values = $this->getLocationTypeOptions();
    return CRM_Utils_Array::value($value, $values);
  }

  /**
   * @param $value
   * @param $row
   * @param $selectedfield
   * @param $criteriaFieldName
   *
   * @return mixed
   */
  function alterGenderID($value, &$row, $selectedfield, $criteriaFieldName) {
    $values = CRM_Contact_BAO_Contact::buildOptions('gender_id');
    return CRM_Utils_Array::value($value, $values);
  }

  /**
   * @param $value
   * @param $row
   * @param $selectedfield
   * @param $criteriaFieldName
   *
   * @return array
   */
  function alterStateProvinceID($value, &$row, $selectedfield, $criteriaFieldName) {
    $url = CRM_Utils_System::url(CRM_Utils_System::currentPath(), "reset=1&force=1&{$criteriaFieldName}_op=in&{$criteriaFieldName}_value={$value}", $this->_absoluteUrl);
    $row[$selectedfield . '_link'] = $url;
    $row[$selectedfield . '_hover'] = ts("%1 for this state.", array(
      1 => $value,
    ));

    $states = CRM_Core_PseudoConstant::stateProvince($value, FALSE);
    if (!is_array($states)) {
      return $states;
    }
  }

  /**
   * @param $value
   * @param $row
   * @param $fieldname
   *
   * @return mixed
   */
  function alterContactID($value, &$row, $fieldname) {
    $nameField = substr($fieldname, 0, -2) . 'name';
    static $first = TRUE;
    static $viewContactList = FALSE;
    if ($first) {
      $viewContactList = CRM_Core_Permission::check('access CiviCRM');
      $first = FALSE;
    }

    if (!$viewContactList) {
      return $value;
    }
    if (array_key_exists($nameField, $row)) {
      $row[$nameField . '_link'] = CRM_Utils_System::url("civicrm/contact/view", 'reset=1&cid=' . $value, $this->_absoluteUrl);
    }
    else {
      $row[$fieldname . '_link'] = CRM_Utils_System::url("civicrm/contact/view", 'reset=1&cid=' . $value, $this->_absoluteUrl);
    }
    return $value;
  }

  /**
   * @param $value
   *
   * @return mixed
   */
  function alterParticipantStatus($value) {
    if (empty($value)) {
      return;
    }
    return CRM_Event_PseudoConstant::participantStatus($value, FALSE, 'label');
  }

  /**
   * @param $value
   *
   * @return string
   */
  function alterParticipantRole($value) {
    if (empty($value)) {
      return;
    }
    $roles = explode(CRM_Core_DAO::VALUE_SEPARATOR, $value);
    $value = array();
    foreach ($roles as $role) {
      $value[$role] = CRM_Event_PseudoConstant::participantRole($role, FALSE);
    }
    return implode(', ', $value);
  }

  /**
   * @param int $value
   *
   * @return string
   */
  function alterPaymentType($value) {
    $paymentInstruments = CRM_Contribute_PseudoConstant::paymentInstrument();
    return CRM_Utils_Array::value($value, $paymentInstruments);
  }

  /**
   * Convert the pledge payment id to a link if grouped by only pledge payment id.
   *
   * @param id $value
   *
   * @return string
   */
  protected function alterPledgePaymentLink($value, &$row, $selectedField) {
    if ($this->_groupByArray !== array('civicrm_pledge_payment_id' => 'pledge_payment.id')
     && $this->_groupByArray !== array('civicrm_pledge_payment_id' => 'civicrm_pledge_payment.id')
    ) {
      CRM_Core_Session::setStatus(ts('Pledge payment link not added'), ts('The pledge payment link cannot be added if the grouping options on the report make it ambiguous'));
      return '';
    }
    if (empty($value)) {
      return $value;
    }
    if (isset($row['civicrm_pledge_pledge_contact_id'])) {
      $contactID = $row['civicrm_pledge_pledge_contact_id'];
    }
    else {
      $contactID = CRM_Core_DAO::singleValueQuery(
        "SELECT contact_id FROM civicrm_pledge_payment pp
         LEFT JOIN civicrm_pledge p ON pp.pledge_id = p.id
         WHERE pp.id = " . $value
      );
    }
    $row[$selectedField . '_link'] = CRM_Utils_System::url('civicrm/contact/view/contribution', 'reset=1&action=add&cid=' . $contactID . '&context=pledge&ppid=' . $value);
    $row[$selectedField . '_hover'] = ts('Record a payment received for this pledged payment');
    $row[$selectedField . '_class'] = "action-item crm-hover-button crm-popup";
    return ts('Record Payment');
  }

  /**
   * @param $value
   *
   * @return mixed
   */
  function alterActivityType($value) {
    $activityTypes = $activityType = CRM_Core_PseudoConstant::activityType(TRUE, TRUE, FALSE, 'label', TRUE);
    return CRM_Utils_Array::value($value, $activityTypes);
  }

  /**
   * @param $value
   *
   * @return mixed
   */
  function alterBatchStatus($value) {
    if (!$value) {
      return ts("N/A");
    }
    $values = CRM_Batch_BAO_Batch::buildOptions('status_id');
    return $values[$value];
  }

  /**
   * @param $value
   *
   * @return mixed
   */
  function alterBoolean($value) {
    $options = array(0 => ts('No'), 1 => ts('Yes'));
    if (isset($options[$value])) {
      return $options[$value];
    }
    return $value;
  }

  /**
   * @param $value
   * @param $bao
   * @param $fieldName
   * @return bool|null|string
   */
  function alterPseudoConstant($value, &$row, $selectedField, $criteriaFieldName, $spec) {
    return CRM_Core_PseudoConstant::getLabel($spec['bao'], $spec['name'], $value);
  }

  /**
   * @param $value
   *
   * @return mixed
   */
  function alterWebsiteTypeId($value) {
    return CRM_Core_PseudoConstant::getLabel('CRM_Core_BAO_Website', 'website_type_id', $value);
  }

  /**
   * We are going to convert phones to an array
   *
   * @param $value
   *
   * @return array|string
   */
  function alterPhoneGroup($value) {

    $locationTypes = $this->getLocationTypeOptions();
    $phoneTypes = $this->_getOptions('phone', 'phone_type_id');
    $phones = explode(',', $value);
    $return = array();
    $html = "<table>";
    foreach ($phones as $phone) {
      if (empty($phone)) {
        continue;
      }
      $keys = explode(':', $phone);
      $return[] = $locationTypes[$keys[1]] . ' : ' . $keys[0];
      if (!empty($keys[2])) {
        $phoneTypeString = ' (' . $phoneTypes[$keys[2]] . ') ';
      }
      $html .= "<tr><td>" . $locationTypes[$keys[1]] . $phoneTypeString . " : " . $keys[0] . "</td></tr>";
    }

    if (in_array($this->_outputMode, array('print'))) {
      return implode($return, '<br>');
    }

    $html .= "</table>";
    return $html;
  }

  /**
   * If in csv mode we will output line breaks
   *
   * @param string $value
   *
   * @return mixed|string
   */
  function alterDisplaycsvbr2nt($value) {
    if ($this->_outputMode == 'csv') {
      return preg_replace('/<br\\s*?\/??>/i', "\n", $value);
    }
    return $value;
  }

  /**
   * If in csv mode we will output line breaks in the table
   *
   * @param string $value
   *
   * @return mixed|string
   */
  function alterDisplaytable2csv($value) {
    if ($this->_outputMode == 'csv') {
      // return
      $value = preg_replace('/<\/tr\\s*?\/??>/i', "\n", $value);
      $value = preg_replace('/<\/td\\s*?\/??>/i', " - ", $value);
    }
    return $value;
  }

  /**
   * @param string $value
   * @param array $row
   * @param array $selectedField
   * @param string $criteriaFieldName
   *
   * @return string
   */
  protected function alterDisplayAddress($value, &$row, $selectedField, $criteriaFieldName) {
    $address = array();
    $tablePrefix = str_replace('display_address', '', $selectedField);

    $format = \Civi::settings()->get('address_format');
    $tokens = $this->extractTokens($format);

    foreach ($tokens as $token) {
      // ug token names not very standardised.
      $keyName = $tablePrefix . str_replace('address_', '', $token);
      $keyName = str_replace('supplemental_', 'supplemental_address_', $keyName);
      if (array_key_exists($keyName, $row)) {
        $address[$token] = $row[$keyName];
      }
      elseif (!empty($row[$keyName . '_id'])) {
        if ($token == 'country') {
          $address[$token] = CRM_Core_PseudoConstant::country($row[$keyName . '_id']);
        }
        elseif ($token == 'state_province') {
          $address[$token] = CRM_Core_PseudoConstant::stateProvince($row[$keyName . '_id']);
        }
        elseif ($token == 'county') {
          $address[$token] = CRM_Core_PseudoConstant::county($row[$keyName . '_id']);
        }
      }
    }
    $value = CRM_Utils_Address::format($address, $format);
    if ($this->_outputMode != 'csv') {
      $value = nl2br($value);
    }
    return $value;

  }

  /**
   * Regex the (contact) tokens out of a string.
   *
   * @param string $string
   * @return array
   */
  protected function extractTokens($string) {
    $tokens = array();
    preg_match_all('/(?<!\{|\\\\)\{contact.(\w+)\}(?!\})/',
      $string,
      $tokens,
      PREG_PATTERN_ORDER
    );
    return $tokens[1];
  }

  /**
   * Set table alias.
   *
   * @param array $table
   * @param string $tableName
   *
   * @return string
   *   Alias for table.
   */
  protected function setTableAlias($table, $tableName) {
    if (!isset($table['alias'])) {
      $this->_columns[$tableName]['alias'] = substr($tableName, 8) .
        '_civireport';
    }
    else {
      $this->_columns[$tableName]['alias'] = $table['alias'];
    }
    return $this->_columns[$tableName]['alias'];
  }

  /**
   * @param array $field
   * @param string $fieldName
   * @param array $filters
   * @param string $table
   * @param int $count
   */
  protected function addFilterFieldsToReport($field, $fieldName, $filters, $table, $count, $prefix) {
    $operations = CRM_Utils_Array::value('operations', $field);
    if (empty($operations)) {
      $operations = $this->getOperationPair(
        CRM_Utils_Array::value('operatorType', $field));
    }

    switch (CRM_Utils_Array::value('operatorType', $field)) {
      case CRM_Report_Form::OP_MONTH:
        if (!array_key_exists('options', $field) ||
          !is_array($field['options']) || empty($field['options'])
        ) {
          // If there's no option list for this filter, define one.
          $field['options'] = array(
            1 => ts('January'),
            2 => ts('February'),
            3 => ts('March'),
            4 => ts('April'),
            5 => ts('May'),
            6 => ts('June'),
            7 => ts('July'),
            8 => ts('August'),
            9 => ts('September'),
            10 => ts('October'),
            11 => ts('November'),
            12 => ts('December'),
          );
          // Add this option list to this column _columns. This is
          // required so that filter statistics show properly.
          $this->_columns[$table]['filters'][$fieldName]['options'] = $field['options'];
        }
      case CRM_Report_Form::OP_MULTISELECT:
      case CRM_Report_Form::OP_MULTISELECT_SEPARATOR:
        // assume a multi-select field
        if (!empty($field['options']) ||
          $fieldName == 'state_province_id' || $fieldName == 'county_id'
        ) {
          $element = $this->addElement('select', "{$prefix}{$fieldName}_op", ts('Operator:'), $operations);
          if (count($operations) <= 1) {
            $element->freeze();
          }
          if ($fieldName == 'state_province_id' ||
            $fieldName == 'county_id'
          ) {
            $this->addChainSelect($prefix . $fieldName . '_value', array(
              'multiple' => TRUE,
              'label' => NULL,
              'class' => 'huge',
            ));
          }
          else {
            $this->addElement('select', "{$prefix}{$fieldName}_value", NULL, $field['options'], array(
              'style' => 'min-width:250px',
              'class' => 'crm-select2 huge',
              'multiple' => TRUE,
              'placeholder' => ts('- select -'),
            ));
          }
        }
        break;

      case CRM_Report_Form::OP_SELECT:
        // assume a select field
        $this->addElement('select', "{$prefix}{$fieldName}_op", ts('Operator:'), $operations);
        if (!empty($field['options'])) {
          $this->addElement('select', "{$prefix}{$fieldName}_value", NULL, $field['options']);
        }
        break;

      case 256:
        $this->addElement('select', "{$prefix}{$fieldName}_op", ts('Operator:'), $operations);
        $this->setEntityRefDefaults($field, $table);
        $this->addEntityRef("{$prefix}{$fieldName}_value", NULL, $field['attributes']);
        break;

      case CRM_Report_Form::OP_DATE:
        // build datetime fields
        CRM_Core_Form_Date::buildDateRange($this, $prefix . $fieldName, $count, '_from', '_to', 'From:', FALSE, $operations);
        break;

      case CRM_Report_Form::OP_DATETIME:
        // build datetime fields
        CRM_Core_Form_Date::buildDateRange($this, $prefix . $fieldName, $count, '_from', '_to', 'From:', FALSE, $operations, 'searchDate', TRUE);
        break;
      case self::OP_SINGLEDATE:
        // build single datetime field
        $this->addElement('select', "{$prefix}{$fieldName}_op", ts('Operator:'), $operations);
        $this->addDate("{$prefix}{$fieldName}_value", ts(''), FALSE);
        break;
      case CRM_Report_Form::OP_INT:
      case CRM_Report_Form::OP_FLOAT:
        // and a min value input box
        $this->add('text', "{$prefix}{$fieldName}_min", ts('Min'));
        // and a max value input box
        $this->add('text', "{$prefix}{$fieldName}_max", ts('Max'));
      default:
        // default type is string
        $this->addElement('select', "{$prefix}{$fieldName}_op", ts('Operator:'), $operations,
          array('onchange' => "return showHideMaxMinVal( '" . $prefix . $fieldName. "', this.value );")
        );
        // we need text box for value input
        $this->add('text', "{$prefix}{$fieldName}_value", NULL, array('class' => 'huge'));
        break;
    }
  }

  /**
   * Generate clause for the selected filter.
   *
   * @param array $field
   * @param string $fieldName
   *
   * @return string
   *   Relevant where clause.
   */
  protected function generateFilterClause($field, $fieldName, $prefix = '') {
    if (CRM_Utils_Array::value('type', $field) & CRM_Utils_Type::T_DATE) {
      if (CRM_Utils_Array::value('operatorType', $field) == CRM_Report_Form::OP_MONTH) {
        $op = CRM_Utils_Array::value("{$prefix}{$fieldName}_op", $this->_params);
        $value = CRM_Utils_Array::value("{$prefix}{$fieldName}_value", $this->_params);
        if (is_array($value) && !empty($value)) {
          $clause = "(month({$field['dbAlias']}) $op (" . implode(', ', $value) . '))';
        }
        return $clause;
      }
      else {
        $relative = CRM_Utils_Array::value("{$prefix}{$fieldName}_relative", $this->_params);
        $from = CRM_Utils_Array::value("{$prefix}{$fieldName}_from", $this->_params);
        $to = CRM_Utils_Array::value("{$prefix}{$fieldName}_to", $this->_params);
        $fromTime = CRM_Utils_Array::value("{$prefix}{$fieldName}_from_time", $this->_params);
        $toTime = CRM_Utils_Array::value("{$prefix}{$fieldName}_to_time", $this->_params);
        // next line is the changed one
        if (!empty($field['clause'])) {
          $clause = '';
          eval("\$clause = \"{$field['clause']}\";");
          $clauses[] = $clause;
          if (!empty($clauses)) {
            return implode(' AND ', $clauses);
          }
          return NULL;
        }
        $clause = $this->dateClause($field['dbAlias'], $relative, $from, $to, $field, $fromTime, $toTime);
        return $clause;
      }
    }
    else {
      $op = CRM_Utils_Array::value("{$prefix}{$fieldName}_op", $this->_params);
      if ($op) {
        $clause = $this->whereClause($field,
          $op,
          CRM_Utils_Array::value("{$prefix}{$fieldName}_value", $this->_params),
          CRM_Utils_Array::value("{$prefix}{$fieldName}_min", $this->_params),
          CRM_Utils_Array::value("{$prefix}{$fieldName}_max", $this->_params)
        );
        return $clause;
      }
    }
  }

  /**
   * Get the selected pivot chart column fields as an array.
   *
   * @param string $type
   *   Row or column to denote the fields we are extracting.
   *
   * @return array Selected fields in format
   * Selected fields in format
   * array('custom_2' => array('civicrm_contact');
   * ie fieldname => table alias
   */
  protected function getFieldBreakdownForAggregates($type) {
    if (empty($this->_params['aggregate_' . $type . '_headers'])) {
      return array();
    }
    $columnHeader = $this->_params['aggregate_' . $type . '_headers'];
    $fieldArr = explode(":", $columnHeader);
    return array($fieldArr[1] => array($fieldArr[0]));
  }

  /**
   * Add the fields to select the aggregate fields to the report.
   */
  protected function addAggregateSelectorsToForm() {
    if (!$this->isPivot) {
      return;
    }
    $this->_aggregateColumnHeaderFields = array('' => ts('--Select--')) + $this->_aggregateColumnHeaderFields;

    foreach ($this->_customGroupExtended as $key => $groupSpec) {
      $customDAOs = $this->getCustomDataDAOs($groupSpec['extends']);
      foreach ($customDAOs as $customField) {
        $tableKey = $customField['prefix'] . $customField['table_name'];
        $prefix = $customField['prefix'];
        $fieldName = 'custom_' . ($prefix ? $prefix . '_' : '') . $customField['id'];
        $this->addCustomTableToColumns($customField, $customField['table_name'], $prefix, $customField['prefix_label'], $tableKey);
        $this->_columns[$tableKey]['metadata'][$fieldName] = $this->getCustomFieldMetadata($customField, $customField['prefix_label']);
        if (!empty($groupSpec['filters'])) {
          $this->_columns[$tableKey]['metadata'][$fieldName]['is_filters'] = TRUE;
          $this->_columns[$tableKey]['filters'][$fieldName] = $this->_columns[$tableKey]['metadata'][$fieldName];
        }
        $this->_aggregateRowFields[$key . ':' . $prefix . 'custom_' . $customField['id']] = $customField['prefix_label'] . $customField['label'];
        if (in_array($customField['html_type'], ['Select', 'CheckBox'])) {
          $this->_aggregateColumnHeaderFields[$key . ':' . $prefix . 'custom_' . $customField['id']] = $customField['prefix_label'] . $customField['label'];
        }
      }

    }
    $this->_aggregateRowFields = array('' => ts('--Select--')) + $this->_aggregateRowFields;
    $this->add('select', 'aggregate_column_headers', ts('Aggregate Report Column Headers'), $this->_aggregateColumnHeaderFields, FALSE,
      array('id' => 'aggregate_column_headers', 'title' => ts('- select -'))
    );
    $this->add('select', 'aggregate_row_headers', ts('Row Fields'), $this->_aggregateRowFields, FALSE,
      array('id' => 'aggregate_row_headers', 'title' => ts('- select -'))
    );
    $this->_columns[$this->_baseTable]['fields']['include_null'] = array(
      'title' => 'Show column for unknown',
      'pseudofield' => TRUE,
      'default' => TRUE,
    );
    $this->tabs['Aggregate'] = array(
      'title' => ts('Pivot table'),
      'tpl' => 'Aggregate',
      'div_label' => 'set-aggregate',
    );

    $this->assignTabs();
  }

  /**
   * Get the name of the field selected for the pivot table row.
   *
   * @return string
   */
  protected function getPivotRowFieldName() {
    if (!empty($this->_params['aggregate_row_headers'])) {
      $aggregateField = explode(':', $this->_params['aggregate_row_headers']);
      return $aggregateField[1];
    }
  }

  /**
   * Get the name of the field selected for the pivot table row.
   *
   * @return string
   */
  protected function getPivotRowTableAlias() {
    if (!empty($this->_params['aggregate_row_headers'])) {
      $aggregateField = explode(':', $this->_params['aggregate_row_headers']);
      return $aggregateField[0];
    }
  }

  /**
   * @param $tableCol
   * @param $row
   *
   * @return string
   */
  protected function getGroupByCriteria($tableCol, $row) {
    $otherGroupedFields = array_diff(array_keys($this->_groupByArray), array($tableCol));
    $groupByCriteria = '';
    foreach ($otherGroupedFields as $field) {
      $fieldParts = explode ('_', $field);
      // argh can't be bothered doing this properly right now.
      $tableName =  $fieldParts[0] . '_' .  $fieldParts[1];
      unset($fieldParts[0], $fieldParts[1]);
      if (!isset($this->_columns[$tableName])) {
        $tableName .= '_' .  $fieldParts[2];
        unset( $fieldParts[2]);
      }
      $presumedName = implode('_', $fieldParts);
      if (isset($this->_columns[$tableName]) && isset($this->_columns[$tableName]['metadata'][$presumedName])) {
        $value = isset($row["{$field}_raw"]) ? $row["{$field}_raw"] : $row[$field];
        $groupByCriteria .= "&{$presumedName}_op=in&{$presumedName}_value=" . $value;
      }
    }

    return $groupByCriteria;
  }

  /**
   * @param $options
   *
   * @return array
   */
  protected function getDefaultsFromOptions($options) {
    $defaults = array(
      'fields_defaults' => $options['fields_defaults'],
      'filters_defaults' => $options['filters_defaults'],
      'group_bys_defaults' => $options['group_bys_defaults'],
      'order_by_defaults' => $options['order_by_defaults'],
    );
    return $defaults;
  }

  /**
   * @param $row
   * @param $nextRow
   * @param $groupBys
   * @param $rowNumber
   * @param $statLayers
   *
   * @param $groupByLabels
   * @param $altered
   * @param $fieldsToUnSetForSubtotalLines
   *
   * @return mixed
   */
  private function alterRowForRollup(&$row, $nextRow, &$groupBys, $rowNumber, $statLayers, $groupByLabels, $altered, $fieldsToUnSetForSubtotalLines) {
    foreach ($groupBys as $field => $groupBy) {
      if (($rowNumber + 1) < $statLayers) {
        continue;
      }
      if (empty($row[$field]) && empty($row['is_rollup'])) {
        $valueIndex = array_search($field, $groupBys) + 1;
        if (!isset($groupByLabels[$valueIndex])) {
          return;
        }
        $groupedValue = $groupByLabels[$valueIndex];
        if (!($nextRow) || $nextRow[$groupedValue] != $row[$groupedValue]) {
          //we set altered because we are started from the lowest grouping & working up & if both have changed only want to act on the lowest
          //(I think)
          $altered[$rowNumber] = TRUE;
//          $row[$groupedValue] = "<span class= 'report-label'> {$row[$groupedValue]} (Subtotal)</span> ";
          $this->updateRollupRow($row, $fieldsToUnSetForSubtotalLines);
        }
      }
      $groupBys[$field] = $row[$field];
    }
  }

  /**
   * Update a row identified as a rollup row.
   *
   * @param $row
   * @param $fieldsToUnSetForSubtotalLines
   *
   * @return mixed
   */
  protected function updateRollupRow(&$row, $fieldsToUnSetForSubtotalLines) {
    foreach ($fieldsToUnSetForSubtotalLines as $unsetField) {
      $row[$unsetField] = '';
    }
    $row['is_rollup'] = TRUE;
  }

  /**
   * Add statistics columns.
   *
   * This version should be in 4.7.16+.
   *
   * If a group by is in play then add columns for the statistics fields.
   *
   * This would lead to a new field in the $row such as $fieldName_sum and a new, matching
   * column header field.
   *
   * @param array $field
   * @param string $tableName
   * @param string $fieldName
   * @param array $select
   *
   * @return array
   */
  protected function addStatisticsToSelect($field, $tableName, $fieldName, $select) {
    foreach ($field['statistics'] as $stat => $label) {
      $alias = "{$tableName}_{$fieldName}_{$stat}";
      switch (strtolower($stat)) {
        case 'max':
        case 'sum':
          $select[] = "$stat({$field['dbAlias']}) as $alias";
          $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['title'] = $label;
          $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['type'] = $field['type'];
          if (!isset($stat['cummulative'])) {
            $this->_statFields[$label] = $alias;
          }
          $this->_selectAliases[] = $alias;
          break;

        case 'cumulative':
          $alias = "{$tableName}_{$fieldName}_sum";
          $select[] = "SUM({$field['dbAlias']}) as $alias";
          $this->_selectAliases[] = $alias;
          break;

        case 'count':
          $select[] = "COUNT({$field['dbAlias']}) as $alias";
          $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['title'] = $label;
          $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['type'] = CRM_Utils_Type::T_INT;
          $this->_statFields[$label] = $alias;
          $this->_selectAliases[] = $alias;
          break;

        case 'count_distinct':
          $select[] = "COUNT(DISTINCT {$field['dbAlias']}) as $alias";
          $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['title'] = $label;
          $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['type'] = CRM_Utils_Type::T_INT;
          $this->_statFields[$label] = $alias;
          $this->_selectAliases[] = $alias;
          break;

        case 'avg':
          $select[] = "ROUND(AVG({$field['dbAlias']}),2) as $alias";
          $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['title'] = $label;
          $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['type'] = $field['type'];
          $this->_statFields[$label] = $alias;
          $this->_selectAliases[] = $alias;
          break;

        case 'display':
          $select[] = "{$field['dbAlias']} as $alias";
          $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['title'] = $label;
          $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['type'] = $field['type'];
          $this->_selectAliases[] = $alias;
          break;
      }
    }
    return $select;
  }

  /**
   * Get SQL operator from form text version.
   *
   * @param string $operator
   *
   * @return string
   */
  public function getSQLOperator($operator = "like") {
    if ($operator === 'rlike') {
      return 'RLIKE';
    }
    return parent::getSQLOperator($operator);
  }

  protected function isInProcessOfPreconstraining() {
    return $this->_preConstrain && !$this->_preConstrained;
  }

  /**
   * Parse contact_id from the url or api input params.
   *
   * This is an extended reports feature which supports reports on contact records as tabs or reportlets.
   * The report must set $this->isSupportsContactTab to show in those places
   * and parse contact_id in params or cid in the url- either like this or otherwise.
   * The report can rely on this mechanism or it's own method in the whereClause.
   * See address history for the latter option & LineitemMembership for the former.
   *
   * @return int|null
   */
  protected function getContactIdFilter() {
    if (!empty($this->_defaults['contact_id_filter_field'])) {
      $this->contactIDField = $this->_defaults['contact_id_filter_field'];
    }
    if (empty($this->contactIDField)) {
      return NULL;
    }

    if (!empty($this->contactIDField)) {
      if (CRM_Utils_Array::value('contact_id', $this->_params)) {
        return $this->_params['contact_id'];
      }
      return CRM_Utils_Request::retrieveValue('cid', 'Int', CRM_Utils_Request::retrieveValue('contact_id', 'Int'));
    }
    return NULL;
  }

  /**
   * @param $table
   *
   * @return array
   */
  protected function getMetadataForFields($table) {
    // higher preference to bao object
    $daoOrBaoName = CRM_Utils_Array::value('bao', $table, CRM_Utils_Array::value('dao', $table));
    if (!$daoOrBaoName) {
      return [];
    }

    $entity = CRM_Core_DAO_AllCoreTables::getBriefName(str_replace('BAO', 'DAO', $daoOrBaoName));
    if ($entity) {
      $expFields = civicrm_api3($entity, 'getfields', [])['values'];
    }


    if (empty($expFields) && $daoOrBaoName) {
      if (method_exists($daoOrBaoName, 'exportableFields')) {
        $expFields = $daoOrBaoName::exportableFields();
      }
      else {
        $expFields = $daoOrBaoName::export();
      }
    }

    foreach ($expFields as $fieldName => $field) {
      if (isset($field['required'])) {
        unset($expFields[$fieldName]['required']);
      }
      if (isset($field['default'])) {
        unset($expFields[$fieldName]['default']);
      }
      if (!isset($field['table_name'])) {
        $expFields[$fieldName]['table_name'] = CRM_Core_DAO_AllCoreTables::getTableForClass($daoOrBaoName);
      }
      if (!empty($field['type'])) {
        $expFields[$fieldName]['operatorType'] = $this->getOperatorType($field['type'], $field, $expFields[$fieldName]['table_name']);
      }
      // Double index any unique fields to ensure we find a match
      // later on. For example the metadata keys
      // contribution_campaign_id rather than campaign_id
      // this is not super predictable so we ensure that they are keyed by
      // both possibilities
      if (!empty($field['name']) && $field['name'] != $fieldName) {
        $expFields[$field['name']] = $expFields[$fieldName];
      }
    }
    return $expFields;
  }

  /**
   * Ensure 'bao' is set if available.
   *
   * Resolve ambiguity with 'dao' option.
   */
  protected function ensureBaoIsSetIfPossible() {
    foreach ($this->_columns as $tableName => $table) {
      if (empty($table['bao']) && !empty($table['dao'])) {
        $this->_columns[$tableName]['bao'] = $table['dao'];
      }
    }
  }

  /**
   * Store Where clauses into an array.
   *
   * Breaking out this step makes over-riding more flexible as the clauses can be used in constructing a
   * temp table that may not be part of the final where clause or added
   * in other functions
   */
  public function storeWhereHavingClauseArray() {
    $filters = $this->getSelectedFilters();
    foreach ($filters as $filterName => $field) {
      if (!empty($field['pseudofield'])) {
        continue;
      }
      $clause = NULL;
      $clause = $this->generateFilterClause($field, $filterName);
      if (!empty($clause)) {
        $this->whereClauses[$filterName] = $clause;
        if (CRM_Utils_Array::value('having', $field)) {
          $this->_havingClauses[$filterName] = $clause;
        }
        else {
          $this->_whereClauses[] = $clause;
        }
      }
    }
  }

  /**
   * Add any additional fields reqired to support the specified field.
   *
   * @param array $specs
   * @param string $table
   */
  protected function addAdditionalRequiredFields($specs, $table) {
    if (empty($specs['requires'])) {
      return;
    }
    foreach ($specs['requires'] as $requiredField) {
      if (empty($this->_params['fields'][$requiredField])) {
        $this->_params['fields'][$requiredField] = 1;
        $this->_columns[$table]['fields'][$requiredField]['no_display'] = 1;
        $this->_noDisplay[] = $table . '_' . $requiredField;
      }
    }
  }

  /**
   * Get contact filter field options in a [name => label] array format.
   * @return array
   */
  protected function getContactFilterFieldOptions() {
    $fields = $this->getContactFilterFields();
    $options = [];
    foreach ($fields as $fieldName => $spec) {
      $options[$fieldName] = $spec['title'];
    }
    return $options;
  }

  /**
   * Get fields that can be used as a contact filter.
   *
   * @return array
   */
  protected function getContactFilterFields() {
    $fields = [];
    foreach ($this->getMetadataByType('filters') as $fieldName => $spec) {
      if (!empty($spec['is_contact_filter'])) {
        $fields[$fieldName] = $spec;
      }
    }
    return $fields;
  }

  /**
   * @return array
   */
  protected function getSelectedFields() {
    $fields = $this->getMetadataByType('fields');
    $selectedFields = array_intersect_key($fields, $this->getConfiguredFieldsFlatArray());
    foreach ($fields as $fieldName => $field) {
      if (!empty($field['required'])) {
        $selectedFields[$fieldName] = $field;
      }
    }
    return $selectedFields;
  }

  /**
   * @return array
   */
  protected function getSelectedGroupBys() {
    $groupBys = $this->getMetadataByType('group_bys');
    return array_intersect_key($groupBys, CRM_Utils_Array::value('group_bys', $this->_params, []));
  }

  /**
   * @return array
   */
  protected function getSelectedOrderBys() {
    $orderBys = $this->getMetadataByType('order_bys');
    $result = [];
    foreach ($this->_params['order_bys'] as $order_by) {
      if (isset($orderBys[$order_by['column']])) {
        $result[$order_by['column']] = array_merge(
          $order_by, $orderBys[$order_by['column']]
        );
      }
    }
    return $result;
  }

  /**
   * Get any order bys that are not already in the selected fields.
   *
   * @return array
   */
  public function getOrderBysNotInSelectedFields() {
    return array_diff_key($this->getSelectedOrderBys(), $this->getSelectedFields());
  }

  /**
   * @return array
   */
  protected function getSelectedFilters() {
    $selectedFilters = [];
    $filters = $this->getMetadataByType('filters');
    foreach ($this->_params as $key => $value) {
      if (substr($key, -6, 6) === '_value' && ($value !== '' && $value !== NULL && $value !== "NULL" && $value !== [])) {
        $selectedFilters[] = substr($key, 0, strlen($key) - 6);
      }
      if (substr($key, -9, 9) === '_relative' && !empty($value)) {
        $selectedFilters[] = substr($key, 0, strlen($key) - 9);
      }
      if (substr($key, -5, 5) === '_from' && !empty($value)) {
        $selectedFilters[] = substr($key, 0, strlen($key) - 5);
      }
    }
    return array_intersect_key($filters, array_flip($selectedFilters));
  }

  /**
   * Get any selected join filters.
   *
   * @return array
   */
  protected function getSelectedJoinFilters() {
    $selectedFilters = [];
    $filters = $this->getMetadataByType('join_filters');
    foreach ($this->_params as $key => $value) {
      if (substr($key, 0, 11) === 'join_filter') {
        if (substr($key, -6, 6) === '_value' && ($value !== '' && $value !== NULL && $value !== "NULL" && $value !== [])) {
          $selectedFilters[] = str_replace('join_filter_', '', substr($key, 0, strlen($key) - 6));
        }
        if (substr($key, -9, 9) === '_relative' && !empty($value)) {
          $selectedFilters[] = str_replace('join_filter_', '', substr($key, 0, strlen($key) - 9));
        }
      }
    }
    return array_intersect_key($filters, array_flip($selectedFilters));
  }

  /**
   * @return array
   */
  protected function getSelectedHavings() {
    $havings = $this->getMetadataByType('having');
    $selectedHavings = [];
    foreach ($havings as $field => $spec) {
      if (isset($this->_params[$field . '_value']) && (
        $this->_params[$field . '_value'] === 0 || !empty($this->_params[$field . '_value'])
        )) {
        $selectedHavings[$field] = $spec;
      }
    }
    return $selectedHavings;
  }

  /**
   * @return array
   */
  protected function getSelectedAggregateColumns() {
    $metadata = $this->getMetadataByType('metadata');
    $columnAggregate = $this->getFieldBreakdownForAggregates('column');
    return array_intersect_key($metadata,  $columnAggregate);
  }

  /**
   * @return array
   */
  protected function getSelectedAggregateRows() {
    $metadata = $this->getMetadataByType('metadata');
    return array_intersect_key($metadata, $this->getFieldBreakdownForAggregates('row'));
  }

  /**
   * @param $field
   * @param $currentTable
   * @param string $prefix
   * @param string $prefixLabel
   */
  protected function addCustomDataTable($field, $currentTable, $prefix = '', $prefixLabel) {
    $tableKey = $prefix . $currentTable;

    $fieldName = 'custom_' . ($prefix ? $prefix . '_' : '') . $field['id'];

    $field['is_filters'] = $this->_customGroupFilters ? TRUE : FALSE;
    $field['is_join_filters'] = $this->_customGroupFilters ? TRUE : FALSE;
    $field['is_group_bys'] = $this->_customGroupGroupBy ? TRUE : FALSE;
    $field['is_order_bys'] = $this->_customGroupOrderBy ? TRUE : FALSE;
    if ($this->_customGroupFilters) {
      $field = $this->addCustomDataFilters($field, $fieldName);
    }
    $this->_columns[$tableKey]['metadata'][$fieldName] = $field;

    $curFields[$fieldName] = $field;

    // Add totals field
    if ($curFields[$fieldName]['type'] === CRM_Utils_Type::T_INT) {
      $this->_columns[$tableKey]['metadata'][$fieldName . '_qty'] = array_merge(
        $field, ['title' => "{$field['label']} Quantity", 'statistics' => ['count' => ts("Quantity Selected")]]
      );
    }
  }


  /**
   * @param array $field
   * @param string $fieldName
   *
   * @return array
   */
  protected function addCustomDataFilters($field, $fieldName) {

    switch ($field['data_type']) {

      case 'StateProvince':
        if (in_array($field['html_type'], array(
          'Multi-Select State/Province'
        ))
        ) {
          $field['operatorType'] = CRM_Report_Form::OP_MULTISELECT_SEPARATOR;
        }
        else {
          $field['operatorType'] = CRM_Report_Form::OP_MULTISELECT;
        }
        $field['options'] = CRM_Core_PseudoConstant::stateProvince();
        break;

      case 'Country':
        if (in_array($field['html_type'], array(
          'Multi-Select Country'
        ))
        ) {
          $field['operatorType'] = CRM_Report_Form::OP_MULTISELECT_SEPARATOR;
        }
        else {
          $field['operatorType'] = CRM_Report_Form::OP_MULTISELECT;
        }
        $field['options'] = CRM_Core_PseudoConstant::country();
        break;

      case 'ContactReference':
        $field['name'] = 'display_name';
        $field['alias'] = "contact_{$fieldName}_civireport";
        break;
    }
    return $field;
  }

  protected function getFieldType($field) {
    switch ($field['data_type']) {
      case 'Date':
        if ($field['time_format']) {
          return CRM_Utils_Type::T_TIMESTAMP;
        }
        return CRM_Utils_Type::T_DATE;
        break;

      case 'Boolean':
        return CRM_Utils_Type::T_BOOLEAN;
        break;

      case 'Int':
        return CRM_Utils_Type::T_INT;
        break;

      case 'Money':
        return CRM_Utils_Type::T_MONEY;
        break;

      case 'Float':
        return CRM_Utils_Type::T_FLOAT;
        break;

      case 'String':
        return CRM_Utils_Type::T_STRING;
        break;

      case 'StateProvince':
      case 'Country':
      case 'ContactReference':
      default:
        return CRM_Utils_Type::T_STRING;

    }
  }

  /**
   * @param array $extends
   */
  protected function addCustomDataForEntities($extends) {
    $fields = $this->getCustomDataDAOs($extends);
    foreach ($fields as $field) {
      $prefixLabel = trim(CRM_Utils_Array::value('prefix_label', $field));
      $prefix = trim(CRM_Utils_Array::value('prefix', $field));
      if ($prefixLabel) {
        $prefixLabel .= ' ';
      }
      $field = $this->getCustomFieldMetadata($field, $prefixLabel, $prefix);
      $this->addCustomTableToColumns($field, $field['table_name'], $prefix, $prefixLabel, $prefix . $field['table_name']);
      $this->addCustomDataTable($field, $field['table_name'], $prefix, $prefixLabel);
    }
  }

  /**
   * @param $extends
   *
   * @return array
   */
  protected function getCustomDataDAOs($extends) {
    $extendsKey = implode(',', $extends);
    if (isset($this->customDataDAOs[$extendsKey])) {
      return $this->customDataDAOs[$extendsKey];
    }
    $customGroupWhere = '';
    if (!$this->userHasAllCustomGroupAccess()) {
      $permissionedCustomGroupIDs = CRM_ACL_API::group(CRM_Core_Permission::VIEW, NULL, 'civicrm_custom_group', NULL, NULL);
      if (empty($permCustomGroupIds)) {
        return [];
      }
      $customGroupWhere = "cg.id IN (" . implode(',', $permissionedCustomGroupIDs) . ") AND";
    }
    $extendsMap = [];
    $extendsEntities = array_flip($extends);
    foreach (array_keys($extendsEntities) as $extendsEntity) {
      if (in_array($extendsEntity, [
        'Individual',
        'Household',
        'Organziation'
      ])) {
        $extendsEntities['Contact'] = TRUE;
        unset($extendsEntities[$extendsEntity]);
      }
    }
    foreach ($this->_columns as $table => $spec) {
      $entityName = (isset($spec['bao']) ? CRM_Core_DAO_AllCoreTables::getBriefName(str_replace('BAO', 'DAO', $spec['bao'])) : '');
      if ($entityName && in_array($entityName, $extendsEntities)) {
        $extendsMap[$entityName][$spec['prefix']] = $spec['prefix_label'];
      }
    }
    $extendsString = implode("','", $extends);
    $sql = "
SELECT cg.table_name, cg.title, cg.extends, cf.id as cf_id, cf.label,
       cf.column_name, cf.data_type, cf.html_type, cf.option_group_id, cf.time_format
FROM   civicrm_custom_group cg
INNER  JOIN civicrm_custom_field cf ON cg.id = cf.custom_group_id
WHERE cg.extends IN ('" . $extendsString . "') AND
  {$customGroupWhere}
  cg.is_active = 1 AND
  cf.is_active = 1 AND
  cf.is_searchable = 1
  ORDER BY cg.weight, cf.weight";
    $customDAO = CRM_Core_DAO::executeQuery($sql);

    $curTable = NULL;
    $fields = [];
    while ($customDAO->fetch()) {
      $entityName = $customDAO->extends;
      if (in_array($entityName, ['Individual', 'Household', 'Organization'])) {
        $entityName = 'Contact';
      }
      foreach ($extendsMap[$entityName] as $prefix => $label) {
        $fields[$prefix . $customDAO->column_name] = [
          'title' => $customDAO->title,
          'extends' => $customDAO->extends,
          'id' => $customDAO->cf_id,
          'label' => $customDAO->label,
          'table_label' => $customDAO->title,
          'column_name' => $customDAO->column_name,
          'data_type' => $customDAO->data_type,
          'dataType' => $customDAO->data_type,
          'html_type' => $customDAO->html_type,
          'option_group_id' => $customDAO->option_group_id,
          'time_format' => $customDAO->time_format,
          'prefix' => $prefix,
          'prefix_label' => $label,
          'table_name' => $customDAO->table_name,
        ];
        $fields[$prefix . $customDAO->column_name]['type'] = $this->getFieldType($fields[$prefix . $customDAO->column_name]);
      }
    }
    $this->customDataDAOs[$extendsKey] = $fields;
    return $fields;
  }

  /**
   * @param $field
   * @param $currentTable
   * @param $prefix
   * @param $prefixLabel
   * @param $tableKey
   */
  protected function addCustomTableToColumns($field, $currentTable, $prefix, $prefixLabel, $tableKey) {
    $entity = $field['extends'];
    if (in_array($entity, ['Individual', 'Organization', 'Household'])) {
      $entity = 'Contact';
    }
    if (!isset($this->_columns[$tableKey])) {
      $this->_columns[$tableKey]['extends'] = $field['extends'];
      $this->_columns[$tableKey]['grouping'] = $prefix . $field['table_name'];
      $this->_columns[$tableKey]['group_title'] = $prefixLabel . $field['table_label'];
      $this->_columns[$tableKey]['name'] = $field['table_name'];
      $this->_columns[$tableKey]['fields'] = [];
      $this->_columns[$tableKey]['filters'] = [];
      $this->_columns[$tableKey]['join_filters'] = [];
      $this->_columns[$tableKey]['group_bys'] = [];
      $this->_columns[$tableKey]['order_bys'] = [];
      $this->_columns[$tableKey]['aggregates'] = [];
      $this->_columns[$tableKey]['prefix'] = $prefix;
      $this->_columns[$tableKey]['table_name'] = $currentTable;
      $this->_columns[$tableKey]['alias'] = $prefix . $currentTable;
      $this->_columns[$tableKey]['extends_table'] = $prefix . CRM_Core_DAO_AllCoreTables::getTableForClass(CRM_Core_DAO_AllCoreTables::getFullName($entity));
    }
  }

  /**
   * @param $field
   * @param $prefixLabel
   * @param string $prefix
   *
   * @return mixed
   */
  protected function getCustomFieldMetadata($field, $prefixLabel, $prefix = '') {
    $field = array_merge($field, [
      'name' => $field['column_name'],
      'title' => $prefixLabel . $field['label'],
      'dataType' => $field['data_type'],
      'htmlType' => $field['html_type'],
      'operatorType' => $this->getOperatorType($this->getFieldType($field), [], []),
      'is_fields' => TRUE,
      'is_filters' => TRUE,
      'is_group_bys' => FALSE,
      'is_order_bys' => FALSE,
      'is_join_filters' => FALSE,
      'type' => $this->getFieldType($field),
      'dbAlias' =>  $prefix . $field['table_name'] . '.' . $field['column_name'],
      'alias' => $prefix . $field['table_name'] . '_' . 'custom_' . $field['id'],
    ]);

    if (!empty($field['option_group_id'])) {
      if (in_array($field['html_type'], ['Multi-Select', 'AdvMulti-Select', 'CheckBox'])) {
        $field['operatorType'] = CRM_Report_Form::OP_MULTISELECT_SEPARATOR;
      }
      else {
        $field['operatorType'] = CRM_Report_Form::OP_MULTISELECT;
      }

      $ogDAO = CRM_Core_DAO::executeQuery("SELECT ov.value, ov.label FROM civicrm_option_value ov WHERE ov.option_group_id = %1 ORDER BY ov.weight", array(
        1 => [$field['option_group_id'], 'Integer']));
      while ($ogDAO->fetch()) {
        $field['options'][$ogDAO->value] = $ogDAO->label;
      }
    }

    if ($field['type'] === CRM_Utils_Type::T_BOOLEAN) {
     $field['options'] = [
        '' => ts('- select -'),
        1 => ts('Yes'),
        0 => ts('No')
      ];
    }
    return $field;
  }

  /**
   * @param $spec
   *
   * @return array
   * @throws \Exception
   */
  protected function getCustomFieldOptions($spec) {
    $options = array();
    if (!empty($spec['options'])) {
      return $options;
    }

    // Data type is set for custom fields but not core fields.
    if (CRM_Utils_Array::value('data_type', $spec) == 'Boolean') {
      $options = array(
        'values' => array(
          0 => array('label' => 'No', 'value' => 0),
          1 => array('label' => 'Yes', 'value' => 1)
        )
      );
    }
    elseif (!empty($spec['options'])) {
      foreach ($spec['options'] as $option => $label) {
        $options['values'][$option] = array(
          'label' => $label,
          'value' => $option
        );
      }
    }
    else {
      if (empty($spec['option_group_id'])) {
        throw new Exception('currently column headers need to be radio or select');
      }
      $options = civicrm_api('option_value', 'get', array(
        'version' => 3,
        'options' => array('limit' => 50,),
        'option_group_id' => $spec['option_group_id'],
      ));
    }
    return $options['values'];
  }

  /**
   * @param string $identifier
   * @param string $columns - eg '`contact_id` INT(10) NOT NULL, `name` varchar(255) NULL'
   *
   * @return string
   */
  protected function createTemporaryTableFromColumns($identifier, $columns) {
    $isNotTrueTemporary = !$this->_temporary;
    $tmpTableName = CRM_Utils_SQL_TempTable::build()
      ->setUtf8(TRUE)
      ->setId($identifier)
      ->createWithColumns($columns)
      ->getName();

    $this->temporaryTables[$identifier] = [
      'temporary' => !$isNotTrueTemporary,
      'name' => $tmpTableName
    ];

    $sql = 'CREATE ' . ($isNotTrueTemporary ? '' : 'TEMPORARY ') . "TABLE $tmpTableName $columns  DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ";
    $this->addToDeveloperTab($sql);
    return $tmpTableName;
  }

  /**
   * @param $type
   * @param array $spec
   * @param $table
   *
   * @return mixed
   */
  protected function getOperatorType($type, $spec = [], $table = '') {
    $typeMap = [
      CRM_Utils_Type::T_INT => CRM_Report_Form::OP_INT,
      CRM_Utils_Type::T_STRING => CRM_Report_Form::OP_STRING,
      CRM_Utils_Type::T_MONEY => CRM_Report_Form::OP_FLOAT,
      CRM_Utils_Type::T_FLOAT => CRM_Utils_Type::T_FLOAT,
      CRM_Utils_Type::T_DATE => CRM_Report_Form::OP_DATE,
      (CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME) => CRM_Report_Form::OP_DATE,
      CRM_Utils_Type::T_BOOLEAN => CRM_Report_Form::OP_SELECT,
      CRM_Utils_Type::T_LONGTEXT => CRM_Report_Form::OP_STRING,
      CRM_Utils_Type::T_TIMESTAMP => CRM_Report_Form::OP_DATE,
    ];
    return $typeMap[$type];
    switch ($type) {


      default:
        if (!empty($table['bao']) &&
          array_key_exists('pseudoconstant', $spec)
        ) {
          // with multiple options operator-type is generally multi-select
          return CRM_Report_Form::OP_MULTISELECT;
        }
        break;
    }
    return FALSE;
  }

  /**
   * @param $field
   * @param $fieldName
   * @param string $prefix
   *
   * @return array
   */
  protected function getQillForField($field, $fieldName, $prefix = '') {
    if ((CRM_Utils_Array::value('type', $field) & CRM_Utils_Type::T_DATE ||
        CRM_Utils_Array::value('type', $field) & CRM_Utils_Type::T_TIME) &&
      CRM_Utils_Array::value('operatorType', $field) !=
      CRM_Report_Form::OP_MONTH
    ) {
      list($from, $to)
        = CRM_Utils_Date::getFromTo(
        CRM_Utils_Array::value("{$prefix}{$fieldName}_relative", $this->_params),
        CRM_Utils_Array::value("{$prefix}{$fieldName}_from", $this->_params),
        CRM_Utils_Array::value("{$prefix}{$fieldName}_to", $this->_params),
        CRM_Utils_Array::value("{$prefix}{$fieldName}_from_time", $this->_params),
        CRM_Utils_Array::value("{$prefix}{$fieldName}_to_time", $this->_params, '235959')
      );
      $from_time_format = !empty($this->_params["{$prefix}{$fieldName}_from_time"]) ? 'h' : 'd';
      $from = CRM_Utils_Date::customFormat($from, NULL, array($from_time_format));

      $to_time_format = !empty($this->_params["{$prefix}{$fieldName}_to_time"]) ? 'h' : 'd';
      $to = CRM_Utils_Date::customFormat($to, NULL, array($to_time_format));

      if ($from || $to) {
        return array(
          'title' => $field['title'],
          'value' => ts("Between %1 and %2", array(1 => $from, 2 => $to)),
        );
      }
      elseif (in_array($rel = CRM_Utils_Array::value("{$prefix}{$fieldName}_relative", $this->_params),
        array_keys($this->getOperationPair(CRM_Report_Form::OP_DATE))
      )) {
        $pair = $this->getOperationPair(CRM_Report_Form::OP_DATE);
        return array(
          'title' => $field['title'],
          'value' => $pair[$rel],
        );
      }
    }
    else {
      $op = CRM_Utils_Array::value("{$prefix}{$fieldName}_op", $this->_params);
      $value = NULL;
      if ($op) {
        $pair = $this->getOperationPair(
          CRM_Utils_Array::value('operatorType', $field),
          $fieldName
        );
        $min = CRM_Utils_Array::value("{$prefix}{$fieldName}_min", $this->_params);
        $max = CRM_Utils_Array::value("{$prefix}{$fieldName}_max", $this->_params);
        $val = CRM_Utils_Array::value("{$prefix}{$fieldName}_value", $this->_params);
        if (in_array($op, array('bw', 'nbw')) && ($min || $max)) {
          $value = "{$pair[$op]} $min " . ts('and') . " $max";
        }
        elseif ($val && CRM_Utils_Array::value('operatorType', $field) & self::OP_ENTITYREF) {
          $this->setEntityRefDefaults($field, $field['table_name']);
          $result = civicrm_api3($field['attributes']['entity'], 'getlist',
            array('id' => $val) +
            CRM_Utils_Array::value('api', $field['attributes'], array()));
          $values = array();
          foreach ($result['values'] as $v) {
            $values[] = $v['label'];
          }
          $value = "{$pair[$op]} " . implode(', ', $values);
        }
        elseif ($op == 'nll' || $op == 'nnll') {
          $value = $pair[$op];
        }
        elseif (is_array($val) && (!empty($val))) {
          $options = CRM_Utils_Array::value('options', $field, array());
          foreach ($val as $key => $valIds) {
            if (isset($options[$valIds])) {
              $val[$key] = $options[$valIds];
            }
          }
          $pair[$op] = (count($val) == 1) ? (($op == 'notin' || $op ==
            'mnot') ? ts('Is Not') : ts('Is')) : CRM_Utils_Array::value($op, $pair);
          $val = implode(', ', $val);
          $value = "{$pair[$op]} " . $val;
        }
        elseif (!is_array($val) && (!empty($val) || $val == '0') &&
          isset($field['options']) &&
          is_array($field['options']) && !empty($field['options'])
        ) {
          $value = CRM_Utils_Array::value($op, $pair) . " " .
            CRM_Utils_Array::value($val, $field['options'], $val);
        }
        elseif ($val) {
          $value = CRM_Utils_Array::value($op, $pair) . " " . $val;
        }
      }
      if ($value && empty($field['no_display'])) {
        return array(
          'title' => CRM_Utils_Array::value('title', $field),
          'value' => $value,
        );
      }
    }
    return [];
  }

  /**
   * Is the field valid for a title.
   *
   * There is no obvious core validation rule to prevent dodginess but still permit spaces
   * (& possibly other chars) - so let's use this limited offering for now & we can make more permissive
   * later.
   *
   * @param string $string
   *
   * @return bool
   */
  protected function isValidTitle($string) {
    return CRM_Utils_Rule::alphanumeric(str_replace(' ', '', $string));
  }

  /**
   * Get the configured fields as a flat array
   *
   * This is in the same format as 'fields' so is easy to compare.
   *
   * @return array
   */
  protected function getConfiguredFieldsFlatArray() {
    $sortingArray = [];
    foreach ($this->getExtendedFieldsSelection() as $configuredExtendedField) {
      $sortingArray[$configuredExtendedField['name']] = 1;
    }
    return empty($sortingArray) ? CRM_Utils_Array::value('fields', $this->_params, []) : $sortingArray;
  }

  /**
   * Merge data configured in angular form into main report data.
   *
   * The angular form saves
   * 1) extended fields data
   * 2) extended order_bys data
   *
   * We save the extended_fields data in it's own key and when loading the report we merge information from it back
   * into the report metadata - notably
   *  - changes to field titles
   *  - re-ordering of fields.
   *  - fall back option of the field is null (what field to use instead)
   * These can be unset from the Quickform page but not configured & if unset the config is lost.
   *
   * The QF page already saves order by data in an array. However, it may be lost :-(
   */
  protected function mergeExtendedConfigurationIntoReportData() {
    $this->mergeExtendedFieldConfiguration();
  }

  /**
   * Merge 'extended_fields' into fields
   */
  protected function mergeExtendedFieldConfiguration() {
    $orderedFieldsArray = $this->getConfiguredFieldsFlatArray();
    foreach ($this->getExtendedFieldsSelection() as $configuredExtendedField) {
      $fieldName = $configuredExtendedField['name'];
      if (isset($this->metaData['fields'][$fieldName])) {
        if ($this->isValidTitle($configuredExtendedField['title'])) {
          $this->metaData['fields'][$fieldName]['title'] = $configuredExtendedField['title'];
        }
        if (!empty($configuredExtendedField['field_on_null'])) {
          $nullString = [];
          foreach ($configuredExtendedField['field_on_null'] as $fallbackField) {
            if ($this->isValidTitle($fallbackField['title'])) {
              $nullString[] = $fallbackField['title'];
            }
          }
          $this->metaData['fields'][$fieldName]['title'] .= '(' . implode(', ', $nullString) . ')';
          $this->metaData['fields'][$fieldName]['field_on_null'] = $configuredExtendedField['field_on_null'];

        }
      }
    }
    if (!empty($orderedFieldsArray)) {
      $orderedFieldsArray = array_intersect_key($orderedFieldsArray, $this->getMetadataByType('fields'));
      // This array merge re-orders them.
      $this->metaData['fields'] = array_merge($orderedFieldsArray, $this->getMetadataByType('fields'));
      $this->_formValues['fields'] = $orderedFieldsArray;
    }
  }

  /**
   * @return mixed
   */
  protected function getExtendedFieldsSelection() {
    return CRM_Utils_Array::value('extended_fields', $this->_formValues, CRM_Utils_Array::value('extended_fields', $this->_params, []));
  }

  /**
   * @param array $field
   * @param string $alias
   *
   * @return string
   */
  protected function getBasicFieldSelectClause($field, $alias) {
    $fieldString = $field['dbAlias'];
    if ($this->groupConcatTested && (!empty($this->_groupByArray) || $this->isForceGroupBy)) {
      if ((empty($field['statistics']) || in_array('GROUP_CONCAT', $field['statistics']))) {

        if (empty($this->_groupByArray[$alias])) {
          return "GROUP_CONCAT(DISTINCT {$field['dbAlias']}) as $alias";
        }
        return "({$field['dbAlias']}) as $alias";
      }
    }
    if (!empty($field['field_on_null'])) {
      $fallbacks = [];
      foreach ($field['field_on_null'] as $fallback) {
        $fallbacks[] = $this->getMetadataByType('filters')[$fallback['name']]['dbAlias'];
      }
      $fieldString = 'COALESCE(' . $fieldString . ',' . implode(',', $fallbacks) . ')';
    }
    return "$fieldString as $alias";
  }

}
