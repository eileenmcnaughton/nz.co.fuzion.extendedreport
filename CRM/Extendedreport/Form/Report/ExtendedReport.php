<?php

use Civi\API\Exception\UnauthorizedException;
use Civi\Api4\Contact;
use CRM_Extendedreport_ExtensionUtil as E;

/**
 * @property mixed _aliases
 * @property mixed deleted_labels
 */
class CRM_Extendedreport_Form_Report_ExtendedReport extends CRM_Report_Form {

  use CRM_Extendedreport_Form_Report_ColumnDefinitionTrait;

  protected $_extraFrom = '';

  protected $_exposeContactID = FALSE;

  protected $_customGroupExtends = [];

  protected $_baseTable = 'civicrm_contact';

  protected $_editableFields = TRUE;

  protected $_rollup = '';

  protected $contactIDField;

  protected $metaData = [];

  /**
   * @var array
   */
  protected $activeCampaigns = [];

  /**
   * All available filter fields with metadata.
   *
   * @var array
   */
  protected $availableFilters = [];

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
  protected $_templates = [];

  /**
   * Add a tab for adding a join relationship?
   *
   * @var bool
   */
  protected $joinFiltersTab = FALSE;

  /**
   * Array of tables with their statuses - relevant for things like Batch which might be absent from a DB.
   *
   * @var array
   */
  protected $tableStatuses = [];

  /**
   * Denotes whether a temporary table should be defined as temporary.
   *
   * This can be set to empty when debugging.
   *
   * @var string
   */
  protected $temporary = ' TEMPORARY ';

  /**
   * When _storeResultSet Flag is set use this var to store result set in form of array
   *
   * @var boolean
   */
  protected $_resultSet = [];

  /**
   * An instruction not to add a Group By
   * This is relevant where the group by might be otherwise added after the code that determines it
   * should not be added is processed but the code does not want to mess with other fields / processing
   * e.g. where stat fields are being added but other settings cause it to not be desirable to add a group by
   * such as in pivot charts when no row header is set
   *
   * @var $_noGroupBY boolean
   */
  protected $_noGroupBY = FALSE;

  protected $_outputMode = [];

  protected $_customGroupOrderBy = TRUE;

  /**
   * Include NULL values in aggregate (pivot) fields
   *
   * @var boolean
   */
  protected $_aggregatesIncludeNULL = TRUE;


  /**
   * Allow the aggregate column to be unset which will just give totals
   *
   * @var boolean
   */
  protected $_aggregatesColumnsOptions = TRUE;

  /**
   * Add a total column to aggregate (pivot) fields
   *
   * @var bool _aggregatesAddTotal
   */
  protected $_aggregatesAddTotal = TRUE;

  /**
   * we will set $this->aliases['civicrm_contact'] to match the primary contact because many upstream functions
   * (e.g tag filters)
   * assume the join will be on that field
   *
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
  protected $_customGroupExtended = [];

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
  protected $_join_filters = [];

  /**
   * Custom field filters
   *
   * Array of custom fields defined as filters. We use this to determine which tables to include.
   *
   * @var array
   */
  protected $_custom_fields_filters = [];

  /**
   * Custom fields selected for display
   *
   * Array of custom fields that have been selected for display.
   * We use this to determine which tables to include.
   *
   * @var array
   */
  protected $_custom_fields_selected = [];

  /**
   * Clauses to be applied to the relationship join as extracted from the input.
   *
   * @var array
   */
  protected $joinClauses = [];

  /**
   * generate a temp table of records that meet criteria & then build the query
   */
  protected $_preConstrain = FALSE;

  /**
   * Set to true once temp table has been generated
   */
  protected $_preConstrained = FALSE;

  /**
   * Tables required to ensure acls are present.
   *
   * @var array
   */
  protected $aclTables = [];

  /**
   * Name of table that links activities to cases. The 'real' table can be replaced by a temp table
   * during processing when a pre-filter is required (e.g we want all cases whether or not they
   * have an activity of type x but we only want activities of type x)
   * (See case with Activity Pivot)
   *
   * @var string
   */
  protected string $_caseActivityTable = 'civicrm_case_activity';

  protected $financialTypePseudoConstant = 'financialType';

  /**
   * The contact_is deleted clause gets added whenever we call the ACL clause - if we don't want
   * it we will specifically allow skipping it
   *
   * @boolean skipACLContactDeletedClause
   */
  protected $_skipACLContactDeletedClause = FALSE;

  protected $whereClauses = [];

  /**
   * Can this report be used on a contact tab.
   *
   * The report must support contact_id in the url for this to work.
   *
   * @var bool
   */
  protected $isSupportsContactTab = TRUE;

  /**
   * DAOs for custom data fields to use.
   *
   * The format is more a refactoring stage than an end result.
   *
   * @var array
   */
  protected array $customDataDAOs = [];

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
   * Filters to apply to join.
   *
   * This might be deprecated for $this->getMetaDataByType('join_filters');
   *
   * @var array
   */
  protected $_joinFilters = [];

  /**
   * Tables created in order to pre-constrain results for performance.
   *
   * @var array
   */
  protected $_tempTables;

  /**
   * Class constructor.
   *
   * @throws \CRM_Core_Exception
   */
  public function __construct() {
    CRM_Core_DAO::executeQuery("SET group_concat_max_len=1500000");
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
  public function getMetadata():array {
    if (empty($this->metaData)) {
      $this->rebuildMetadata();
    }
    return $this->metaData;
  }

  /**
   * Build the tag filter field to display on the filters tab.
   *
   * @throws \CRM_Core_Exception
   */
  public function buildTagFilter(): void {
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
          'is_aggregate_columns' => FALSE,
          'is_aggregate_rows' => FALSE,
        ],
      ], 'civicrm_tag', 'CRM_Core_DAO_Tag', 'tag', [], ['no_field_disambiguation' => TRUE]);
    }
    $this->_columns['civicrm_tag']['group_title'] = ts('Tags');
  }

  /**
   * Get the report ID if determined.
   *
   * @return int|null
   */
  public function getInstanceID(): ?int {
    return $this->_id;
  }

  /**
   * Adds group filters to _columns (called from _Construct).
   *
   * @throws \CRM_Core_Exception
   */
  public function buildGroupFilter(): void {
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
          'is_aggregate_columns' => FALSE,
          'is_aggregate_rows' => FALSE,
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
   * @throws \CRM_Core_Exception
   */
  public function getLocationTypeOptions(): array {
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
   * @throws \Exception
   */
  public function preProcess(): void {
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
      $fieldGroups = [
        'fields',
        'filters',
        'metadata',
        'join_filters',
        'group_bys',
        'order_bys',
      ];
      // Extended reports customisation ends ==

      foreach ($fieldGroups as $fieldGrp) {
        if (!empty($table[$fieldGrp]) && is_array($table[$fieldGrp])) {
          foreach ($table[$fieldGrp] as $fieldName => $field) {
            // $name is the field name used to reference the BAO/DAO export fields array
            $name = $field['name'] ?? $fieldName;

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
              $this->_noRepeats[] = "{$tableName}_$fieldName";
            }
            if (!empty($field['no_display'])) {
              $this->_noDisplay[] = "{$tableName}_$fieldName";
            }

            // set alias = table-name, unless already set
            $alias = $field['alias'] ?? (
                $this->_columns[$tableName]['alias'] ?? $tableName
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
      foreach (['filters', 'join_filters'] as $filterString) {
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

    if ($this->joinFiltersTab) {
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
  public function setParams($params): void {
    if (empty($params) || $params === ['order_bys' => NULL]) {
      return;
    }
    $extendedFieldKeys = $this->getConfiguredFieldsFlatArray();
    if (!empty($extendedFieldKeys)) {
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
            'title' => $this->getMetadataByType('fields')[$fieldName]['title'],
          ];
        }
        // We use array_merge to re-index from 0
        $params['extended_fields'] = array_merge($this->_formValues['extended_fields']);
      }
    }
    if (empty($params) && !empty ($this->_params)) {
      // The parent function calls this twice to 'handle dashlets' - but uses some 'co-incidental'
      // parameters rather than information to do so.
      return;
    }
    $params['order_bys'] = $params['extended_order_bys'] = $this->getConfiguredOrderBys($params);
    // Renumber from 0
    $params['extended_order_bys'] = array_merge($params['extended_order_bys']);

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
  protected function getMetadataByType(string $type): array {
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
  protected function getMetadataByAlias(string $type): array {
    $metadata = $this->getMetadata()[$type];
    $return = [];
    foreach ($metadata as $key => $value) {
      $return[$value['alias']] = $value;
      $return[$value['alias']]['fieldName'] = $key;
    }
    return $return;
  }

  /**
   * Generate the SELECT clause and set class variable $_select.
   */
  public function select(): void {
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
    $configuredFields = $this->getConfiguredFieldsFlatArray();

    $select = [];
    // Where we need fields for a having clause & the are not selected we
    // add them to the select clause (but not to headers because - hey
    // you didn't ask for them).
    $havingFields = $this->getSelectedHavings();
    $havingsToAdd = array_diff_key($havingFields, $selectedFields);
    foreach ($havingsToAdd as $fieldName => $spec) {
      $select[$fieldName] = "{$spec['selectAlias']} as {$spec['table_name']}_$fieldName";
    }

    foreach ($this->getOrderBysNotInSelectedFields() as $fieldName => $spec) {
      if (($fieldStats = $this->getFieldStatistics($spec)) !== []) {
        foreach ($fieldStats as $stat => $label) {
          $alias = $this->getStatisticsAlias($spec['table_name'], $fieldName, $stat);
          $select[$fieldName . '_' . $stat] = $this->getStatisticsSelectClause($spec, $stat) . " as $alias";
        }
      }
      else {
        $select[$fieldName] = $this->getBasicFieldSelectClause($spec, $spec['alias']) . " as  {$spec['alias']} ";
      }
    }

    foreach ($selectedFields as $fieldName => $field) {
      $this->addAdditionalRequiredFields($field, $field['table_name']);

      // Only display required_sql field as column if selected from UI.
      if (!empty($field['required_sql']) && !array_key_exists($fieldName, $configuredFields)) {
        $this->_noDisplay[] = $field['table_name'] . '_' . $fieldName;
      }

      $tableName = $field['table_name'];
      $alias = $field['alias'] ?? "{$tableName}_$fieldName";
      $fieldStats = $this->getFieldStatistics($field);
      if ($fieldStats === []) {
        $this->_selectAliases["{$tableName}_$fieldName"] = $field;
        $this->addFieldToColumnHeaders($field, $alias);
      }
      else {
        foreach ($fieldStats as $stat => $label) {
          $alias = $this->getStatisticsAlias($tableName, $fieldName, $stat);
          $this->_selectAliases[$alias] = $field;
          $this->_selectAliases[$alias]['title'] = $label;

          if (in_array($stat, ['sum', 'count'])) {
            $this->_selectAliases[$alias]['stat'] = $stat;
          }
          $statSpec = array_merge($field, [
            'type' => in_array($stat, ['count', 'count_distinct']) ? CRM_Utils_Type::T_INT : $field['type'],
            'title' => $label,
          ]);

          if ($stat !== 'cumulative') {
            $this->addFieldToColumnHeaders($statSpec, $alias);
          }
        }
      }

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
      if ($fieldStats !== []) {
        foreach ($fieldStats as $stat => $label) {
          $alias = $this->getStatisticsAlias($tableName, $fieldName, $stat);
          if (!in_array($stat, ['cumulative', 'display'])) {
            $this->_statFields[$label] = $alias;
          }
          $select[$fieldName . '_' . $stat] = $this->getStatisticsSelectClause($field, $stat) . " as $alias";
        }
      }
      else {
        $this->_selectAliases[$alias] = $alias;
        if (isset($this->_columnHeaders[$field['alias']])) {
          // is this actually changing the type at all?
          $this->_columnHeaders[$field['alias']]['type'] = $field['type'] ?? NULL;
        }
        else {
          $this->_columnHeaders[$field['alias']] = ['type' => $field['type'] ?? NULL];
        }
        $select[$fieldName] = $this->getBasicFieldSelectClause($field, $alias) . " as $alias ";
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
              $this->_columnHeaders["{$tableName}_{$fieldName}_interval"] = ['no_display' => TRUE];
              $this->_columnHeaders["{$tableName}_{$fieldName}_subtotal"] = ['no_display' => TRUE];
            }
          }
        }
      }
    }

    if (empty($select)) {
      // CRM-21412 Do not give fatal error on report when no fields selected
      $select = [1];
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
   *
   * @throws \CRM_Core_Exception
   */
  protected function aggregateSelect(): void {
    if (empty($this->_customGroupAggregates)) {
      return;
    }

    $columnFields = $this->getAggregateField('column');
    $rowFields = $this->getAggregateFieldSpec('row');

    if (empty($rowFields)) {
      $this->addRowHeader(FALSE, [], FALSE);
    }
    foreach ($rowFields as $fieldDetails) {
      $this->addRowHeader(1, $fieldDetails, $fieldDetails['alias'], $fieldDetails['title']);
    }

    foreach ($columnFields as $fieldDetails) {
      //only one but we don't know the name
      if (array_key_exists($this->_params['aggregate_column_headers'], $this->getMetadataByType('aggregate_columns'))) {
        $spec = $this->getMetadataByType('aggregate_columns')[$this->_params['aggregate_column_headers']];
      }
      if ($this->_params['aggregate_column_headers'] === 'contribution_total_amount_year' || $this->_params['aggregate_column_headers'] === 'contribution_total_amount_month') {
        $columnType = explode('_', $this->_params['aggregate_column_headers']);
        $columnType = end($columnType);
        $spec = [
          'table_name' => 'civicrm_contribution',
          'group_title' => E::ts('Contributions'),
          'name' => 'total_amount',
          'type' => '1',
          'title' => 'Breakdown By ' . $columnType,
          'description' => '',
          'export' => TRUE,
          'where' => 'civicrm_contribution.total_amount',
          'entity' => 'Contribution',
          'bao' => 'CRM_Contribute_BAO_Contribution',
          'options' => [
            $columnType,
          ],
          'is_fields' => FALSE,
          'is_filters' => FALSE,
          'is_order_bys' => FALSE,
          'is_group_bys' => TRUE,
          'table_key' => 'civicrm_contribution',
          'dbAlias' => 'contribution.total_amount',
          'is_join_filters' => FALSE,
          'is_aggregate_columns' => TRUE,
          'is_aggregate_rows' => FALSE,
          'alias' => 'civicrm_contribution_contribution_total_amount',
        ];
      }

      $this->addColumnAggregateSelect($spec['name'], $spec['dbAlias'], $spec);
    }

  }

  /**
   * Add Select for pivot chart style report
   *
   * @param string $fieldName
   * @param string $dbAlias
   * @param array $spec
   *
   * @throws \CRM_Core_Exception
   */
  protected function addColumnAggregateSelect(string $fieldName, string $dbAlias, array $spec): void {
    if (empty($fieldName)) {
      $this->addAggregateTotal($fieldName);
      return;
    }
    $options = $this->getCustomFieldOptions($spec);

    if (!empty($this->_params[$fieldName . '_value']) && CRM_Utils_Array::value($fieldName . '_op', $this->_params) === 'in') {
      $options['values'] = array_intersect_key($options, array_flip($this->_params[$fieldName . '_value']));
    }

    // for now we will literally just handle IN
    if ($this->getFilterFieldValue($spec) && $spec['field']['op'] === 'in') {
      $options = array_intersect_key($options, array_flip($spec['field']['value']));
      $this->_aggregatesIncludeNULL = FALSE;
    }

    foreach ($options as $optionValue => $optionLabel) {
      $fieldAlias = str_replace([
        '-',
        '+',
        '\/',
        '/',
        ')',
        '(',
      ], '_', "{$fieldName}_" . strtolower(str_replace(' ', '', $optionValue)));

      // htmlType is set for custom data and tells us the field will be stored using hex(01) separators.
      if (!empty($spec['htmlType']) && ($spec['htmlType'] === 'CheckBox' || strpos($spec['htmlType'], 'Multi') !== FALSE)) {
        $this->_select .= " , SUM( CASE WHEN $dbAlias LIKE '%" . CRM_Core_DAO::VALUE_SEPARATOR . $optionValue . CRM_Core_DAO::VALUE_SEPARATOR . "%' THEN 1 ELSE 0 END ) AS $fieldAlias ";
      }
      else {
        $this->_select .= " , SUM( CASE $dbAlias WHEN '$optionValue' THEN 1 ELSE 0 END ) AS $fieldAlias ";
      }
      $this->_columnHeaders[$fieldAlias] = [
        'title' => $optionLabel,
        'type' => CRM_Utils_Type::T_INT,
      ];
      $this->_statFields[] = $fieldAlias;
    }
    if ($this->_aggregatesIncludeNULL && !empty($this->_params['fields']['include_null'])) {
      $fieldAlias = "{$fieldName}_null";
      $this->_columnHeaders[$fieldAlias] = [
        'title' => ts('Unknown'),
        'type' => CRM_Utils_Type::T_INT,
      ];
      $this->_select .= " , SUM( IF (($dbAlias IS NULL OR $dbAlias = ''), 1, 0)) AS $fieldAlias ";
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
   * @return string|array
   */
  protected function getFilterFieldValue(array &$spec) {
    $fieldName = $spec['table_name'] . '_' . $spec['name'];
    $valueKey = $fieldName . '_value';
    if (isset($this->_params[$valueKey])) {
      $spec['field']['value'] = $this->_params[$valueKey];
      $spec['field']['op'] = $this->_params[$fieldName . '_op'];
      return $this->_params[$fieldName . '_value'];
    }
    return '';
  }

  /**
   * @param string $fieldName
   */
  protected function addAggregateTotal(string $fieldName): void {
    $fieldAlias = "{$fieldName}_total";
    $this->_columnHeaders[$fieldAlias] = [
      'title' => ts('Total'),
      'type' => CRM_Utils_Type::T_INT,
    ];
    $this->_select .= " , SUM( IF (1 = 1, 1, 0)) AS $fieldAlias ";
    $this->_statFields[] = $fieldAlias;
  }

  /**
   * From clause build where baseTable & fromClauses are defined.
   */
  public function from(): void {
    if (!empty($this->_baseTable)) {
      $tableAlias = (empty($this->_aliases[$this->_baseTable]) ? '' : $this->_aliases[$this->_baseTable]);
      $this->setFromBase($this->_baseTable, 'id', $tableAlias);

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
          $extra = $this->_joinFilters[$fromClause] ?? [];
          $append = $this->$fn('', $extra);
          if ($append && !empty($extra)) {
            foreach ($extra as $table => $field) {
              $this->_from .= " AND {$this->_aliases[$table]}.$field ";
            }
          }
        }

      }
      if (strpos($this->_from, 'civicrm_contact') !== FALSE) {
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
  protected function constrainedWhere(): void {
  }

  /**
   * @return array
   */
  protected function fromClauses(): array {
    return [];
  }

  /**
   * We're overriding the parent class so we can populate a 'group_by' array for other functions use
   * e.g. editable fields are turned off when groupby is used
   */
  public function groupBy(): void {
    $this->storeGroupByArray();
    $groupedColumns = [];
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
      if ($this->_rollup !== FALSE
        && !empty($this->_statFields) && empty($this->_orderByArray) &&
        (count($this->_groupByArray) <= 1 || !$this->_having)
        && !$this->isInProcessOfPreconstraining()
      ) {
        $this->_rollup = " WITH ROLLUP";
      }
      $this->_groupBy .= ' ' . $this->_rollup;
    }
  }

  /**
   * Overriden to draw source info from 'metadata' and not rely on it being in 'fields'.
   *
   * In some cases other functions want to know which fields are selected for ordering by
   * Separating this into a separate function allows it to be called separately from constructing
   * the order by clause
   */
  public function storeOrderByArray(): void {

    $isGroupBy = !empty($this->_groupByArray);
    $selectedOrderBys = $this->getSelectedOrderBys();

    $orderBys = [];

    if (!empty($selectedOrderBys)) {
      // Process order_bys in user-specified order
      foreach ($selectedOrderBys as $selectedOrderBy) {
        $fieldAlias = $selectedOrderBy['alias'];
        // Record any section headers for assignment to the template
        if (CRM_Utils_Array::value('section', $selectedOrderBy)) {
          $this->_sections[$selectedOrderBy['alias']] = $selectedOrderBy;
        }
        if ($isGroupBy && !empty($selectedOrderBy['statistics']) && !empty($selectedOrderBy['statistics']['sum'])) {
          $fieldAlias .= '_sum';
        }
        $orderBys[] = "($fieldAlias) {$selectedOrderBy['order']}";
      }
    }

    $this->_orderByArray = $orderBys;
    $this->assign('sections', $this->_sections);
  }

  /**
   * Store join filters as an array in a similar way to the filters.
   *
   * @throws \CRM_Core_Exception
   */
  protected function storeJoinFiltersArray(): void {
    foreach ($this->getSelectedJoinFilters() as $fieldName => $field) {
      $clause = $this->generateFilterClause($field, $fieldName, 'join_filter_');
      if (!empty($clause)) {
        $this->joinClauses[$field['table_name']][] = $clause;
        if ($field['name'] === 'relationship_type_id') {
          $relationshipLabel = civicrm_api3('relationship_type', 'getvalue', [
            'id' => $this->_params["{$fieldName}_value"],
            'return' => 'label_a_b',
          ]);
          foreach (array_keys($this->_columns) as $columnLabel) {
            if ((stripos($columnLabel, 'related_civicrm') !== FALSE) && !empty($this->_columns[$columnLabel]['fields'])) {
              foreach ($this->_columns[$columnLabel]['fields'] as &$columnField) {
                $columnField['title'] = str_replace('Related Contact', $relationshipLabel, $columnField['title']);
                $columnField['title'] = str_replace('of ', '', $columnField['title']);
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
  public function setMetadataForTable(string $tableName): array {
    if (CRM_Utils_Array::value('fields', $this->_columns[$tableName])) {
      $this->_columns[$tableName]['metadata'] = $this->_columns[$tableName]['fields'];
    }
    elseif (CRM_Utils_Array::value('filters', $this->_columns[$tableName])) {
      $this->_columns[$tableName]['metadata'] = $this->_columns[$tableName]['filters'];
    }
    else {
      $this->_columns[$tableName]['metadata'] = [];
    }
    return $this->_columns[$tableName];
  }

  /**
   * Store group bys into array - so we can check elsewhere (e.g editable fields) what is grouped.
   *
   * Overridden to draw source info from 'metadata' and not rely on it being in 'fields'.
   */
  public function storeGroupByArray(): void {

    if (CRM_Utils_Array::value('group_bys', $this->_params) &&
      is_array($this->_params['group_bys']) &&
      !empty($this->_params['group_bys'])
    ) {
      foreach ($this->getSelectedGroupBys() as $fieldName => $fieldData) {
        $groupByKey = $fieldData['alias'];
        if (!empty($fieldData['frequency']) && !empty($this->_params['group_bys_freq'])) {
          $groupByFrequency = CRM_Utils_Array::value($fieldName, $this->_params['group_bys_freq']);
          switch ($groupByFrequency) {
            case 'FISCALYEAR':
              $this->_groupByArray[$groupByKey . '_start'] = $this->fiscalYearOffset($fieldData['dbAlias']);
              break;

            case 'YEAR':
              $this->_groupByArray[$groupByKey . '_start'] = " $groupByFrequency({$fieldData['dbAlias']})";
              break;

            default:
              $this->_groupByArray[$groupByKey . '_start'] =
                "EXTRACT(YEAR_$groupByFrequency FROM {$fieldData['dbAlias']})";
              break;
          }

        }
        else if (!in_array($fieldData['dbAlias'], $this->_groupByArray)) {
          $this->_groupByArray[$groupByKey] = $fieldData['dbAlias'];
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

  protected function isSelfGrouped(): bool {
    return $this->_groupByArray == [$this->_baseTable . '_id' => $this->_aliases[$this->_baseTable] . ".id"];
  }

  /**
   * Calculate whether we have stats fields.
   *
   * This will cause a group by.
   */
  protected function calculateStatsFields(): void {
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('metadata', $table)) {
        foreach ($table['metadata'] as $fieldName => $field) {
          if (!empty($field['required']) ||
            !empty($this->_params['fields'][$fieldName])
          ) {
            if (!empty($field['statistics'])) {
              foreach ($field['statistics'] as $stat => $label) {
                $alias = $this->getStatisticsAlias($tableName, $fieldName, $stat);
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
  protected function unsetBaseTableStatsFieldsWhereNoGroupBy(): void {
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
   *
   * @throws \CRM_Core_Exception
   */
  public function addFilters(): void {
    foreach (['filters', 'join_filters'] as $filterString) {
      $filters = $filterGroups = [];
      $count = 1;
      foreach ($this->getMetadataByType($filterString) as $fieldName => $field) {
        $table = $field['table_key'] ?? '';
        $groupingKey = $field['group_title'] ?? '';
        if ($filterString === 'filters') {
          $filterGroups[$groupingKey]['group_title'] = $field['group_title'] ?? '';
          if (isset($field['use_accordion_for_field_selection'])) {
            $filterGroups[$groupingKey]['use_accordion_for_field_selection'] = $field['table_key']['use_accordion_for_field_selection'];
          }
          else {
            $filterGroups[$groupingKey]['use_accordion_for_field_selection'] = TRUE;
          }
        }
        $prefix = ($filterString === 'join_filters') ? 'join_filter_' : '';
        $filterGroups[$groupingKey]['tables'][$table][$prefix . $fieldName] = $field;
        $filters[$table][$prefix . $fieldName] = $field;
        $this->addFilterFieldsToReport($field, $fieldName, $table, $count, $prefix);
      }

      if (!empty($filters) && $filterString === 'filters') {
        $this->tabs['Filters'] = [
          'title' => ts('Filters'),
          'tpl' => 'Filters',
          'div_label' => 'set-filters',
        ];
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
  protected function assignTabs(): void {
    $order = [
      'Aggregate',
      'FieldSelection',
      'GroupBy',
      'OrderBy',
      'ReportOptions',
      'Filters',
    ];
    $order = array_intersect_key(array_fill_keys($order, 1), $this->tabs);
    $order = array_merge($order, $this->tabs);
    if (isset($this->tabs['Aggregate'])) {
      unset($this->tabs['FieldSelection'], $this->tabs['GroupBy'], $this->tabs['OrderBy']);
    }
    $this->assign('tabs', $order);
  }


  /**
   * Add columns to report.
   */
  public function addColumns(): void {
    $options = [];
    $colGroups = [];

    foreach ($this->getMetadataByType('fields') as $fieldName => $field) {
      $tableName = $field['table_key'];
      $colGroups[$tableName]['use_accordian_for_field_selection'] = TRUE;
      $colGroups[$tableName]['fields'][$fieldName] = $field['title'] ?? '';
      $colGroups[$tableName]['group_title'] = $field['group_title'];
      $options[$fieldName] = $field['title'] ?? '';

    }

    $this->addCheckBox("fields", ts('Select Columns'), $options, NULL,
      NULL, NULL, NULL, $this->_fourColumnAttribute, TRUE
    );
    if (!empty($colGroups)) {
      $this->tabs['FieldSelection'] = [
        'title' => ts('Columns'),
        'tpl' => 'FieldSelection',
        'div_label' => 'col-groups',
      ];

      // Note this assignment is only really required in buildForm. It is being 'over-called'
      // to reduce risk of being missed due to overridden functions.
      $this->assignTabs();
    }

    $this->assign('colGroups', $colGroups);
  }

  /**
   * Add order bys
   */
  public function addOrderBys(): void {
    $options = [];

    foreach ($this->getMetadataByType('order_bys') as $fieldName => $field) {
      $options[$fieldName] = $field['title'];
    }

    asort($options);

    $this->assign('orderByOptions', $options);
    if (!empty($options)) {
      $this->tabs['OrderBy'] = [
        'title' => ts('Sorting'),
        'tpl' => 'OrderBy',
        'div_label' => 'order-by-elements',
      ];
    }

    if (!empty($options)) {
      $options = [
          '-' => ' - none - ',
        ] + $options;
      for ($i = 1; $i <= 5; $i++) {
        $this->addElement('select', "order_bys[$i][column]", ts('Order by Column'), $options);
        $this->addElement('select', "order_bys[$i][order]", ts('Order by Order'), [
          'ASC' => 'Ascending',
          'DESC' => 'Descending',
        ]);
        $this->addElement('checkbox', "order_bys[$i][section]", ts('Order by Section'), FALSE, ['id' => "order_by_section_$i"]);
        $this->addElement('checkbox', "order_bys[$i][pageBreak]", ts('Page Break'), FALSE, ['id' => "order_by_pagebreak_$i"]);
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
   *
   * @throws \CRM_Core_Exception
   */
  public function setDefaultValues($freeze = TRUE): array {
    $freezeGroup = [];
    $overrides = [];

    foreach ($this->_options as $optionName => $field) {
      if (isset($field['default'])) {
        $this->_defaults['options'][$optionName] = $field['default'];
      }
    }

    foreach ($this->getMetadataByType('fields') as $fieldName => $field) {
      if (empty($field['no_display'])) {
        if (isset($field['required'])) {
          // set default
          $this->_defaults['fields'][$fieldName] = 1;

          if ($freeze) {
            // find element object, so that we could use quickform's freeze method
            // for required elements
            $obj = $this->getElementFromGroup('fields', $fieldName);
            if ($obj) {
              $freezeGroup[] = $obj;
            }
          }
        }
        elseif (!empty($field['is_fields_default'])) {
          $this->_defaults['fields'][$fieldName] = TRUE;
        }
      }
    }

    foreach ($this->getMetadataByType('group_bys') as $fieldName => $field) {
      if (isset($field['is_group_bys_default'])) {
        if (!empty($field['frequency'])) {
          $this->_defaults['group_bys_freq'][$fieldName] = 'MONTH';
        }
        $this->_defaults['group_bys'][$fieldName] = TRUE;
      }
    }

    foreach ($this->getMetadataByType('filters') as $fieldName => $field) {
      if (isset($field['default'])) {
        if (CRM_Utils_Array::value('type', $field) & CRM_Utils_Type::T_DATE
          // This is the overriden part.
          && !(CRM_Utils_Array::value('operatorType', $field) === self::OP_SINGLEDATE)
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

    $this->_defaults['order_bys'] = [];
    foreach ($this->getMetadataByType('filters') as $fieldName => $field) {
      if (!empty($field['default']) || !empty($field['default_order']) ||
        CRM_Utils_Array::value('default_is_section', $field) ||
        !empty($field['default_weight'])
      ) {
        $order_by = [
          'column' => $fieldName,
          'order' => CRM_Utils_Array::value('default_order', $field, 'ASC'),
          'section' => CRM_Utils_Array::value('default_is_section', $field, 0),
        ];

        if (!empty($field['default_weight'])) {
          $this->_defaults['order_bys'][(int) $field['default_weight']] = $order_by;
        }
        else {
          array_unshift($this->_defaults['order_bys'], $order_by);
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
    if ($contact_id) {
      $this->_defaults[$this->contactIDField . '_value'] = $contact_id;
      $this->_defaults[$this->contactIDField . '_op'] = 'in';
    }
    return $this->_defaults;
  }

  /**
   *  Note: $fieldName param allows inheriting class to build operationPairs
   * specific to a field.
   *
   * @param string $type
   * @param string|null $fieldName
   *
   * @return array
   */
  public function getOperationPair($type = "string", $fieldName = NULL): array {
    if ($type == self::OP_SINGLEDATE) {
      return [
        'to' => ts('Until Date'),
        'from' => ts('From Date'),
      ];
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
        'rlike' => ts('Regex is true'),
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
   * @throws \CRM_Core_Exception
   */
  protected function _getOptions(string $entity, string $field, string $action = 'get'): array {
    static $allOptions = [];
    $key = "{$entity}_$field";
    if (isset($allOptions[$key])) {
      return $allOptions[$key];
    }
    $options = civicrm_api3($entity, 'getoptions', [
      'field' => $field,
      'action' => $action,
    ]);
    $allOptions[$key] = $options['values'];
    return $allOptions[$key];
  }

  /**
   * re-order column headers.
   *
   * This is based on the input field 'fields' and shuffling group bys to the left.
   *
   */
  protected function reOrderColumnHeaders(): void {
    $fieldMap = [];
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
      if (stripos($groupByField, '_start') !== FALSE) {
        $ungroupedField = str_replace('_start', '', $groupByField);
        unset($this->_columnHeaders[$ungroupedField]);
        $fieldMapKey = array_search($ungroupedField, $fieldMap, TRUE);
        if ($fieldMapKey) {
          $fieldMap[$fieldMapKey] = $fieldMap[$fieldMapKey] . '_start';
        }
      }
    }

    $fieldMap = array_merge(CRM_Utils_Array::value('fields', $this->_params, []), $fieldMap);
    $this->_columnHeaders = array_merge(array_intersect_key(array_flip($fieldMap), $this->_columnHeaders), $this->_columnHeaders);
  }

  /**
   * Mostly overriding this for ease of adding in debug.
   */
  public function postProcess(): void {

    try {
      $this->beginPostProcess();

      $sql = $this->buildQuery();
      $this->reOrderColumnHeaders();
      // build array of result based on column headers. This method also allows
      // modifying column headers before using it to build result set i.e $rows.
      $rows = [];
      $this->addToDeveloperTab($sql);
      $this->buildRows($sql, $rows);
      $this->addAggregatePercentRow($rows);
      $this->formatDisplay($rows);

      // assign variables to templates
      $this->doTemplateAssignment($rows);

      // do print / pdf / instance stuff if needed
      $this->endPostProcess($rows);
    }
    catch (Exception $e) {
      $err['message'] = $e->getMessage();
      $err['trace'] = $e->getTrace();

      foreach ($err['trace'] as $fn) {
        if ($fn['function'] === 'raiseError') {
          foreach ($fn['args'] as $arg) {
            $err['sql_error'] = $arg;
          }
        }
        if ($fn['function'] === 'simpleQuery') {
          foreach ($fn['args'] as $arg) {
            $err['sql_query'] = $arg;
          }
        }
      }
      CRM_Core_Error::debug('error', $err);
    }
  }

  /**
   * We are overriding the rows as we want the custom data by contribution.
   * here we are getting contribution total amount by year.
   *
   * @param string $rowFieldId
   * @param string $columnType = month / year
   * @param string $header = `contribution_total_amount_year` / 'contribution_total_amount_year'
   *
   * @return array|null
   *
   * @throws \CRM_Core_Exception
   */
  public function buildContributionTotalAmountByBreakdown(string $rowFieldId, string $columnType, string $header): ?array {
    if ($header === 'contribution_total_amount_year' || $header === 'contribution_total_amount_month') {
      $where = '';
      $clause = '';

      $filters = $this->getSelectedFilters();
      // Create a filters if available.
      foreach ($filters as $filterName => $field) {
        $clause .= $this->generateFilterClause($field, $filterName) . " AND ";
      }
      // Get the row field data for adding where conditions.
      $rowFields = $this->getAggregateFieldSpec('row');

      if (is_numeric($rowFieldId) && $rowFieldId !== 'HEADER') {
        $where = "Where " . $rowFields[0]['dbAlias'] . " = " . $rowFieldId;
      }
      elseif (!empty($rowFieldId) && is_string($rowFieldId) && $rowFieldId !== 'HEADER') {
        $where = "Where " . $rowFields[0]['dbAlias'] . " LIKE '%" . $rowFieldId . "%'";
      }

      // Custom fields join.
      $customJoin = '';
      if (!empty($rowFields[0]['id'])) {
        // Check custom field is available.
        $customField = civicrm_api3('CustomField', 'get', [
          'sequential' => 1,
          'id' => $rowFields[0]['id'],
        ]);
        if (!empty($customField['values'][0])) {
          $customJoin = " LEFT JOIN " . $rowFields[0]['table_name'] . " " . $rowFields[0]['table_name'] . "
          ON " . $rowFields[0]['table_name'] . ".entity_id = civicrm_contact.id";
        }
      }

      if (!empty($clause)) {
        $clause = preg_replace('/\W\w+\s*(\W*)$/', '$1', $clause);

        if ($where) {
          $where .= " AND " . $clause;
        }
        else {
          $where .= "Where " . $clause;
        }
      }

      $filterDateSelect = "";
      $filterDateGroupBy = "";
      if ($columnType === 'month') {
        $filterDateSelect = ", year(`receive_date`) as month_year";
        $filterDateGroupBy = ", year(`receive_date`)";
      }

      $select = "Select " . $columnType . "(`receive_date`) as " . $columnType . ", sum(`total_amount`) as amount" . $filterDateSelect;
      $from = "from civicrm_contribution contribution";
      $join = "LEFT JOIN civicrm_contact civicrm_contact
        ON contribution.contact_id = civicrm_contact.id
        LEFT JOIN civicrm_address address
        ON address.contact_id = civicrm_contact.id
        AND address.is_primary = 1" . $customJoin;
      $groupBy = "group by " . $columnType . "(`receive_date`)" . $filterDateGroupBy;

      $sql = $select . " " . $from . " " . $join . " " . $where . " " . $groupBy;

      $dao = CRM_Core_DAO::executeQuery($sql);
      $result = [];
      $result['total_amount_total'] = 0;
      while ($dao->fetch()) {
        $result[$this->getAggregateRowFieldAlias()] = $rowFieldId;
        $result['total_amount_total'] += $dao->amount;

        if (!empty($dao->month_year) && $columnType === 'month') {
          $result['total_amount_' . $dao->$columnType . '_' . $dao->month_year] = $dao->amount;
        }
        else {
          $result['total_amount_' . $dao->$columnType] = $dao->amount;
        }
      }
      return $result;
    }
    return NULL;
  }

  /**
   * Override parent to include additional storage action.
   */
  public function beginPostProcessCommon(): void {
    parent::beginPostProcessCommon();
    $this->storeParametersOnForm();
  }

  /**
   * Add a field as a stat sum field.
   *
   * @param string $tableName
   * @param string $fieldName
   * @param array $field
   *
   * @return string
   */
  protected function selectStatSum(string $tableName, string $fieldName, array $field): string {
    $alias = "{$tableName}_{$fieldName}_sum";
    $this->addFieldToColumnHeaders($field, $alias);
    $this->_columnHeaders[$alias]['type'] = CRM_Utils_Array::value('type', $field);
    $this->_statFields[CRM_Utils_Array::value('title', $field)] = $alias;
    $this->_selectAliases[$alias] = $alias;
    return $alias;
  }

  /**
   * Add an extra row with percentages for a single row result to the chart (this is where
   * there is no grandTotal row
   *
   * @param array $rows
   */
  private function addAggregatePercentRow(array $rows): void {
    if (!empty($this->_aggregatesAddPercentage) && count($rows) == 1 && $this->_aggregatesAddTotal) {
      foreach ($rows as $row) {
        $total = end($row);
        //   reset($row);
        $stats = [];
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
  protected function storeParametersOnForm(): void {
    foreach (array_keys($this->_columns) as $tableName) {
      foreach (['filters', 'fields'] as $fieldSet) {
        if (!isset($this->_columns[$tableName][$fieldSet])) {
          $this->_columns[$tableName][$fieldSet] = [];
        }
      }
      foreach ($this->_columns[$tableName]['filters'] as $fieldName => $field) {
        if (!empty($table['extends'])) {
          $this->_columns[$tableName][$fieldName]['metadata'] = $table['extends'];
        }
        if (empty($this->_columns[$tableName]['metadata'])) {
          $this->_columns[$tableName] = $this->setMetaDataForTable($tableName);
        }
        if (!isset($this->_columns[$tableName]['metadata'], $this->_columns[$tableName]['metadata'][$fieldName])) {
          // Need to get this down to none but for now...
          continue;
        }
        $this->availableFilters[$fieldName] = $this->_columns[$tableName]['metadata'][$fieldName];
      }
    }

    $this->_custom_fields_selected = CRM_Utils_Array::value('custom_fields', $this->_params, []);

    if ($this->_params === NULL) {
      $this->_params = [];
    }
    foreach ($this->_params as $key => $param) {
      if (strpos($key, 'custom_') === 0) {
        $splitField = explode('_', $key);
        $field = ($splitField[0] . '_' . $splitField[1]);
        $operatorIsNull = ($this->_params[$field . '_op'] ?? '') === 'nll';
        $filters = $spec['filters'] ?? [];
        $criteriaValue = $this->_params[$field . '_value'] ?? NULL;
        foreach ($this->_columns as $spec) {
          if (array_key_exists($field, $filters)
            && ($criteriaValue !== NULL
              || !empty($this->_params[$field . '_relative']) ||
            $operatorIsNull)
          ) {
            $fieldName = $this->mapFieldExtends($field, $spec);
            if (!in_array($fieldName, $this->_custom_fields_filters, TRUE)) {
              $this->_custom_fields_filters[] = $this->mapFieldExtends($field, $spec);
            }
          }
        }
      }
    }
    if (!isset($this->_params['fields'])) {
      $this->_params['fields'] = [];
    }
  }

  /**
   * Over-written to allow pre-constraints
   *
   * @param boolean $applyLimit
   *
   * @return string
   * @throws \CRM_Core_Exception
   */
  public function buildQuery($applyLimit = TRUE): string {
    if (empty($this->_params)) {
      $this->_params = $this->controller->exportValues($this->_name);
    }
    // Call selected tables as this may be unset.
    // note test covers this so if it is later removed & tests don't fail it is no longer
    // needed (which would be ideal).
    $this->selectedTables();
    $this->buildGroupTempTable();
    $this->storeJoinFiltersArray();
    $this->storeWhereHavingClauseArray();
    $this->storeGroupByArray();
    $this->storeOrderByArray();
    $this->buildPermissionClause();
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

    if ($applyLimit && !CRM_Utils_Array::value('charts', $this->_params)) {
      if (!empty($this->_params['number_of_rows_to_render'])) {
        $this->_dashBoardRowCount = $this->_params['number_of_rows_to_render'];
      }
      $this->limit();
    }

    $sql = "$this->_select $this->_from $this->_where $this->_groupBy $this->_having $this->_orderBy ";
    if (!$this->_rollup) {
      $sql .= $this->_limit;
    }

    CRM_Utils_Hook::alterReportVar('sql', $sql, $this);
    return $sql;
  }

  /**
   * Generate a temp table to reflect the pre-constrained report group
   * This could be a group of contacts on whom we are going to do a series of
   * contribution comparisons.
   *
   * We apply where criteria from the form to generate this
   *
   * We create a temp table of their ids in the first instance
   * and use this as the base
   *
   * @noinspection PhpUnhandledExceptionInspection
   */
  protected function generateTempTable(): void {
    $tempTable = 'civicrm_report_temp_' . $this->_baseTable . date('d_H_I') . random_int(1, 10000);
    $sql = "CREATE $this->_temporary TABLE $tempTable
      (`id` INT(10) UNSIGNED NULL DEFAULT '0',
        INDEX `id` (`id`)
      )
      COLLATE='utf8_unicode_ci'
      ENGINE=HEAP;";
    CRM_Core_DAO::executeQuery($sql);
    $sql = "INSERT INTO $tempTable
      $this->_select $this->_from $this->_where $this->_limit ";
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
  public function getTemplateFileName(): string {
    $defaultTpl = parent::getTemplateFileName();

    if (in_array($this->_outputMode, [
        'print',
        'pdf'
      ]) && array_key_exists('templates', $this->_params) && $this->_params['templates']) {
        $defaultTpl = 'CRM/Extendedreport/Form/Report/CustomTemplates/' . $this->_params['templates'] . '.tpl';
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
   *
   * @throws \CRM_Core_Exception
   */
  public function addCustomDataToColumns($addFields = TRUE, $permCustomGroupIds = []): void {
    if (empty($this->_customGroupExtends)) {
      return;
    }
    if (!is_array($this->_customGroupExtends)) {
      $this->_customGroupExtends = [
        $this->_customGroupExtends,
      ];
    }
    $this->addCustomDataForEntities($this->_customGroupExtends);

  }

  /**
   * Add group by options to the report.
   */
  public function addGroupBys(): void {
    $options = $freqElements = [];

    foreach ($this->getMetadataByType('group_bys') as $fieldName => $field) {
      if (empty($field['no_display'])) {
        $options[$field['title']] = $fieldName;
        if (!empty($field['frequency'])) {
          $freqElements[$field['title']] = $field['title'];
        }
      }
    }
    $this->addCheckBox("group_bys", ts('Group by columns'), $options, NULL,
      NULL, NULL, NULL, $this->_fourColumnAttribute
    );
    $this->assign('groupByElements', $options);
    if (!empty($options)) {
      $this->tabs['GroupBy'] = [
        'title' => ts('Grouping'),
        'tpl' => 'GroupBy',
        'div_label' => 'group-by-elements',
      ];
    }

    foreach ($freqElements as $name) {
      $this->addElement('select', "group_bys_freq[$name]",
        ts('Frequency'), $this->_groupByDateFreq
      );
    }
  }

  protected function userHasAllCustomGroupAccess(): bool {
    return CRM_Core_Permission::check('access all custom data');
  }

  /**
   * Add tab for selecting template.
   *
   * @throws \CRM_Core_Exception
   */
  protected function addTemplateSelector(): void {
    if (!empty($this->_templates)) {

      //$templatesDir = str_replace('CRM/Extendedreport', 'templates/CRM/Extendedreport', __DIR__);
      $this->add('select', 'templates', ts('Select Alternate Template'), $this->_templates, FALSE,
        ['id' => 'templates', 'title' => ts('- select -'), 'class' => 'crm-select2']
      );

      $this->tabs['Template'] = [
        'title' => ts('Template'),
        'tpl' => 'Template',
        'div_label' => 'set-template',
      ];
    }
  }

  /**
   * Take API Styled field and add extra params required in report class
   *
   * @param array $field
   */
  protected function getCustomFieldDetails(array &$field): void {
    $types = [
      'Date' => CRM_Utils_Type::T_DATE,
      'Boolean' => CRM_Utils_Type::T_INT,
      'Int' => CRM_Utils_Type::T_INT,
      'Money' => CRM_Utils_Type::T_MONEY,
      'Float' => CRM_Utils_Type::T_FLOAT,
    ];

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
   * Build custom data from clause.
   *
   * Overridden to support custom data for multiple entities of the same type.
   */
  public function extendedCustomDataFrom(): void {
    foreach ($this->getMetadataByType('metadata') as $prop) {
      $table = $prop['table_name'];
      if (empty($prop['extends']) || !$this->isTableSelected($prop['prefix'] . $table)) {
        continue;
      }

      $baseJoin = $this->_customGroupExtendsJoin[$prop['extends']] ??  "{$this->_aliases[$prop['extends_table']]}.id";
      $customJoin = is_array($this->_customGroupJoin) ? $this->_customGroupJoin[$table] : $this->_customGroupJoin;
      $tableKey = CRM_Utils_Array::value('prefix', $prop) . $prop['table_name'];
      if (stripos($this->_from, $this->_aliases[$tableKey]) === FALSE) {
        // Protect against conflict with selectableCustomFrom.
        $this->_from .= "
$customJoin {$prop['table_name']} {$this->_aliases[$tableKey]} ON {$this->_aliases[$tableKey]}.entity_id = $baseJoin";
      }
      if (CRM_Utils_Array::value('data_type', $prop) === 'ContactReference'
        // Checking prop['statistics'] is a bit of a hack - we want to exclude aggregate fields
        // we don't join twice.
        && empty($prop['statistics'])) {
        $this->_from .= "
LEFT JOIN civicrm_contact {$prop['alias']} ON {$prop['alias']}.id = {$this->_aliases[$tableKey]}.{$prop['column_name']} ";
      }
    }
  }

  /**
   * Map extends = 'Entity' to a connection to the relevant table
   *
   * @param string $field
   * @param array $spec
   *
   * @return string
   */
  private function mapFieldExtends(string $field, array $spec): string {
    $extendable = [
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
    ];

    if (!empty($extendable[$spec['extends']])) {
      return $extendable[$spec['extends']] . ':' . $field;
    }

    return 'civicrm_contact:' . $field;
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
  public function selectClause(&$tableName, $tableKey, &$fieldName, &$field) {
    if ($fieldName === 'phone_phone') {
      $alias = "{$tableName}_$fieldName";
      $this->_columnHeaders["{$tableName}_$fieldName"]['type'] = CRM_Utils_Array::value('type', $field);
      $this->_columnHeaders["{$tableName}_$fieldName"]['dbAlias'] = CRM_Utils_Array::value('dbAlias', $field);
      return " GROUP_CONCAT(CONCAT({$field['dbAlias']},':', {$this->_aliases[$tableName]}.location_type_id, ':', {$this->_aliases[$tableName]}.phone_type_id) ) as $alias";
    }
    if (!empty($field['pseudofield'])) {
      $alias = "{$tableName}_$fieldName";
      $this->_columnHeaders["{$tableName}_$fieldName"]['type'] = CRM_Utils_Array::value('type', $field);
      $this->_columnHeaders["{$tableName}_$fieldName"]['dbAlias'] = CRM_Utils_Array::value('dbAlias', $field);
      return ' 1 as  ' . $alias;
    }
    return FALSE;
  }

  /**
   * Function extracts the custom fields array where it is preceded by a table prefix
   * This allows us to include custom fields from multiple contacts (for example) in one report
   *
   * @param array $customFields
   * @param string $context
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function extractCustomFields(array $customFields, string $context = 'select'): array {
    $myColumns = [];
    $metadata = $this->getMetadataByType('metadata');
    $selectedFields = array_intersect_key($metadata, $customFields);
    foreach ($selectedFields as $fieldName => $selectedField) {
      $tableName = $selectedField['table_name'];
      $customFieldsToTables[$fieldName] = $tableName;
      $fieldAlias = $selectedField['alias'];
      $tableAlias = $tableName;
      // these should be in separate functions
      if ($context === 'select' && (!$this->_preConstrain || $this->_preConstrained)) {
        $this->_select .= ", $tableAlias.{$metadata[$fieldName]['name']} as $fieldAlias ";
      }
      if ($context === 'row_header') {
        $this->addRowHeader($tableAlias, $selectedField, $fieldAlias);
      }
      if ($context === 'column_header') {
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
  protected function addNullToFilterOptions(string $table, string $fieldName, string $label = '--does not exist--'): void {
    $this->_columns[$table]['filters'][$fieldName]['options'] = ['' => $label] + $this->_columns[$table]['filters'][$fieldName]['options'];
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
  private function addRowHeader(string $tableAlias, array $selectedField, string $fieldAlias, string $title = ''): void {
    if (empty($tableAlias)) {
      $this->_select = 'SELECT 1 ';
      // add a fake value just to save lots of code to calculate whether a comma is required later
      $this->_rollup = NULL;
      $this->_noGroupBY = TRUE;
      return;
    }
    $this->_select = "SELECT {$selectedField['dbAlias']} as $fieldAlias ";
    if (!in_array($fieldAlias, $this->_groupByArray, TRUE)) {
      $this->_groupByArray[] = $fieldAlias;
    }
    $this->_groupBy = "GROUP BY $fieldAlias " . $this->_rollup;
    $this->_columnHeaders[$fieldAlias] = ['title' => $title];
    $key = array_search($fieldAlias, $this->_noDisplay);
    if (is_int($key)) {
      unset($this->_noDisplay[$key]);
    }
  }

  /**
   * @param $rows
   */
  public function alterDisplay(&$rows): void {
    if (!empty($this->_defaults['report_id']) && $this->_defaults['report_id'] === reset($this->_drilldownReport)) {
      $this->linkedReportID = $this->_id;
    }

    if (isset($this->_params['delete_null']) && $this->_params['delete_null'] == '1') {
      foreach ($this->rollupRow as $rowName => $rowValue) {
        if ($rowValue !== '' && is_numeric($rowValue) && $rowValue == 0) {
          unset ($this->_columnHeaders[$rowName]);
        }
      }
    }
    //THis is all generic functionality which can hopefully go into the parent class
    // it introduces the option of defining an alter display function as part of the column definition
    // @todo tidy up the iteration so it happens in this function

    if (empty($rows)) {
      return;
    }
    [$firstRow] = $rows;
    // no result to alter
    if (empty($firstRow)) {
      return;
    }

    $selectedFields = array_keys($firstRow);
    $alterFunctions = $alterMap = $alterSpecs = [];

    foreach ($this->getSelectedAggregateRows() as $pivotRowField => $pivotRowFieldSpec) {
      $pivotRowIsCustomField = strpos($pivotRowField, 'custom_') === 0;
      if ($pivotRowIsCustomField && $pivotRowFieldSpec['html_type'] !== 'Text') {
        $alias = $pivotRowFieldSpec['alias'];
        $alterFunctions[$alias] = 'alterFromOptions';
        $alterMap[$alias] = $pivotRowField;
        $alterSpecs[$alias] = $pivotRowFieldSpec;
      }
    }

    $fieldData = $this->getMetadataByAlias('metadata');
    $chosen = array_intersect_key($fieldData, $firstRow);
    foreach ($fieldData as $fieldAlias => $specs) {
      if (!empty($this->_groupByArray) && isset($specs['statistics']['cumulative']) && in_array($fieldAlias . '_sum', $selectedFields, TRUE)) {
        $this->_columnHeaders[$fieldAlias . '_cumulative']['title'] = $specs['statistics']['cumulative'];
        $this->_columnHeaders[$fieldAlias . '_cumulative']['type'] = $specs['type'];
        $this->_columnHeaders[$fieldAlias . '_cumulative']['colspan'] = $specs['colspan'] ?? FALSE;
        $alterFunctions[$fieldAlias . '_sum'] = 'alterCumulative';
        $alterMap[$fieldAlias . '_sum'] = $fieldAlias;
        $alterSpecs[$fieldAlias . '_sum'] = $specs['name'];
      }
      if ($this->_editableFields && array_key_exists('crm_editable', $specs) && !empty($this->_aliases[$specs['crm_editable']['id_table']])) {
        //id key array is what the array would look like if the ONLY group by field is our id field
        // in which case it should be editable - in any other group by scenario it shouldn't be
        $idKeyArray = [$this->_aliases[$specs['crm_editable']['id_table']] . "." . $specs['crm_editable']['id_field']];
        if (empty($this->_groupByArray) || $this->_groupByArray == $idKeyArray) {
          $alterFunctions[$fieldAlias] = 'alterCrmEditable';
          $alterMap[$fieldAlias] = $fieldAlias;
          $alterSpecs[$fieldAlias] = $specs;
          $alterSpecs[$fieldAlias]['field_name'] = $specs['name'];
        }
      }
    }
    if (!empty($alterFunctions)) {
      foreach ($rows as $index => &$row) {
        foreach ($row as $selectedField => $value) {
          if (array_key_exists($selectedField, $alterFunctions) && $value !== '') {
            $rows[$index][$selectedField] = $this->{$alterFunctions[$selectedField]}($value, $row, $selectedField, $alterMap[$selectedField], $alterSpecs[$selectedField]);
          }
        }
      }
    }

    parent::alterDisplay($rows);
    if ($this->_rollup) {
      //we want to be able to unset rows so here
      $this->alterRollupRows($rows);
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
   *
   * @throws \CRM_Core_Exception
   */
  public function formatDisplay(&$rows, $pager = TRUE): void {
    // Check aggregate column header.
    if (isset($this->_params['aggregate_column_headers']) && ($this->_params['aggregate_column_headers'] === 'contribution_total_amount_year' || $this->_params['aggregate_column_headers'] === 'contribution_total_amount_month') && !empty($rows)) {
      $this->wrangleColumnHeadersForContributionPivotWithReceiveDateAggregate();
      $this->formatTotalAmountAggregateRows($rows);
      // format result set.
      $pager = FALSE;
    }
    parent::formatDisplay($rows, $pager);
  }

  /**
   * Calculate section totals.
   *
   * When "order by" fields are marked as sections, this assigns to the template
   * an array of total counts for each section. This data is used by the Smarty
   * plugin {sectionTotal}.
   *
   * @throws \CRM_Core_Exception
   */
  public function sectionTotals(): void {

    // Reports using order_bys with sections must populate $this->_selectAliases in select() method.
    if (empty($this->_selectAliases)) {
      return;
    }

    if (!empty($this->_sections)) {
      // build the query with no LIMIT clause
      $select = str_ireplace('SELECT SQL_CALC_FOUND_ROWS ', 'SELECT ', $this->_select);
      $sql = "$select $this->_from $this->_where $this->_groupBy $this->_having $this->_orderBy";

      // pull section aliases out of $this->_sections
      $sectionAliases = array_keys($this->_sections);

      $ifnulls = [];
      $statFields = ['ct' => ['title' => '']];
      foreach (array_keys($this->_selectAliases) as $alias) {
        if (!empty($this->_selectAliases[$alias]['stat'])) {
          $statFields[$alias] = ['title' => $this->_selectAliases[$alias]['title']];
          $stat = $this->_selectAliases[$alias]['stat'];
          if ($stat === 'count' && isset($statFields['ct'])) {
            unset($statFields['ct']);
          }
          $ifnulls[] = $this->getStatOp($stat) . "( $alias ) as $alias";
        }
      }

      foreach ($sectionAliases as $alias) {
        $ifnulls[] = "COALESCE($alias, '') as $alias";
      }
      $outerSelect = "SELECT " . implode(", ", $ifnulls);

      // Group (un-limited) report by all aliases and get counts. This might
      // be done more efficiently when the contents of $sql are known, ie. by
      // overriding this method in the report class.

      $query = $outerSelect .
        ", count(*) as ct from ($sql) as subquery group by " .
        implode(", ", $sectionAliases);

      // initialize array of total counts
      $dao = $this->executeReportQuery($query);
      $totals = $totalsArray = [];
      while ($dao->fetch()) {
        $values = [];
        // let $this->_alterDisplay translate any integer ids to human-readable values.
        $rows[0] = $dao->toArray();
        $this->alterDisplay($rows);
        $this->alterCustomDataDisplay($rows);
        $row = $rows[0];

        foreach ($sectionAliases as $alias) {
          $values[] = $row[$alias];
          $key = implode(CRM_Core_DAO::VALUE_SEPARATOR, $values);
          foreach ($statFields as $statField => $spec) {
            $totalsArray[$key][$statField]['title'] = $spec['title'];
            if (!isset($totalsArray[$key][$statField]['value'])) {
              $totalsArray[$key][$statField]['value'] = 0;
            }
            $totalsArray[$key][$statField]['value'] += $dao->$statField;
          }
        }
      }
      foreach ($totalsArray as $fieldKey => $value) {
        foreach ($value as $spec) {
          $totals[$fieldKey][] = $spec['title'] . ' : ' . $spec['value'];
        }
        $totals[$fieldKey] = implode(' , ', $totals[$fieldKey]);
      }

      $this->assign('sectionTotals', $totals);
    }
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
   * @param array $rows
   */
  protected function alterRollupRows(array &$rows): void {
    if (count($rows) === 1) {
      // If the report only returns one row there is no rollup.
      return;
    }
    $groupBys = array_reverse(array_fill_keys(array_keys($this->_groupByArray), NULL));
    $firstRow = reset($rows);
    foreach ($groupBys as $field => $groupBy) {
      $fieldKey = isset($firstRow[$field]) ? $field : str_replace([
        '_YEAR',
        '_MONTH',
      ], '_start', $field);
      if (isset($firstRow[$fieldKey])) {
        unset($groupBys[$field]);
        $groupBys[$fieldKey] = $firstRow[$fieldKey];
      }
    }
    $groupByLabels = array_keys($groupBys);

    $altered = [];
    $fieldsToUnSetForSubtotalLines = [];
    //on this first round we'll get a list of keys that are not groupbys or stats
    if (!$this->isPivot) {
      foreach (array_keys($firstRow) as $rowField) {
        if (!array_key_exists($rowField, $groupBys) && substr($rowField, -4) !== '_sum' && !substr($rowField, -7) !== '_count') {
          $fieldsToUnSetForSubtotalLines[] = $rowField;
        }
      }
    }

    $statLayers = count($this->_groupByArray);

    //I don't know that this precaution is required?          $this->fixSubTotalDisplay($rows[$rowNum], $this->_statFields);
    if (count($this->_statFields) === 0) {
      return;
    }

    foreach (array_keys($rows) as $rowNumber) {
      $nextRow = CRM_Utils_Array::value($rowNumber + 1, $rows);
      if ($nextRow === NULL && empty($this->rollupRow)) {
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
   * @param string|null $value
   * @param array $row
   * @param string $selectedField
   * @param string $criteriaFieldName
   * @param array $specs
   *
   * @return string
   * @throws \CRM_Core_Exception
   * @noinspection PhpUnusedParameterInspection
   */
  protected function alterFromOptions(?string $value, array $row, string $selectedField, string $criteriaFieldName, array $specs): string {
    if ($specs['data_type'] === 'ContactReference') {
      if (!empty($row[$selectedField])) {
        return CRM_Contact_BAO_Contact::displayName($row[$selectedField]);
      }
      return '';
    }
    if (empty($value)) {
      return '';
    }
    // Convert $value, which is a string, to an array, passing in the VALUE_SEPARATOR.
    $selectedValues = explode(CRM_Core_DAO::VALUE_SEPARATOR, trim($value, CRM_Core_DAO::VALUE_SEPARATOR));
    $options = $this->getCustomFieldOptions($specs);
    // Convert the array of options into a comma separated string.
    return implode(', ', array_intersect_key($options, array_flip($selectedValues)));
  }

  /**
   * Was hoping to avoid over-riding this - but it doesn't pass enough data to formatCustomValues by default
   * Am using it in a pretty hacky way to also cover the select box custom fields
   *
   * @param array $rows
   *
   * @throws \CRM_Core_Exception
   */
  public function alterCustomDataDisplay(&$rows): void {

    // custom code to alter rows having custom values
    if (empty($this->_customGroupExtends) && empty($this->_customGroupExtended)) {
      return;
    }
    $extends = $this->_customGroupExtends;
    foreach ($this->_customGroupExtended as $spec) {
      $extends = array_merge($extends, $spec['extends']);
    }

    $customFieldIds = [];
    if (!isset($this->_params['fields']) || !is_array($this->_params['fields'])) {
      $this->_params['fields'] = [];
    }
    foreach ($this->_params['fields'] as $fieldAlias => $value) {
      $prefix = $this->metaData['fields'][$fieldAlias]['prefix'] ?? "";
      if ($prefix) {
        $fieldAlias = str_replace('_' . $prefix, '', $fieldAlias);
      }
      $fieldId = CRM_Core_BAO_CustomField::getKeyID($fieldAlias);
      if ($fieldId) {
        $customFieldIds[$fieldAlias] = $fieldId;
      }
    }
    if (!empty($this->_params['custom_fields']) && is_array($this->_params['custom_fields'])) {
      foreach ($this->_params['custom_fields'] as $value) {
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

    $customFields = [];
    $customFieldCols = [
      'column_name',
      'data_type',
      'html_type',
      'option_group_id',
      'id',
      'serialize',
    ];

    // skip for type date and ContactReference since date format is already handled
    $query = '
SELECT cg.table_name, cg.extends, cf.' . implode(", cf.", $customFieldCols) . "
FROM  civicrm_custom_field cf
INNER JOIN civicrm_custom_group cg ON cg.id = cf.custom_group_id
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
    }
    $entryFound = FALSE;
    $columnKeys = array_keys($this->_columns);
    foreach ($rows as $rowNum => $row) {
      foreach ($row as $tableCol => $val) {
        $customField = NULL;
        $columnMatchedKeys = array_filter($columnKeys, static function($key) use ($tableCol) {
          return strpos($tableCol, $key . '_') !== FALSE;
        });
        $columnMatchedKey = array_pop($columnMatchedKeys);
        if (!empty($columnMatchedKey)) {
          $alias = $this->_columns[$columnMatchedKey]['alias'] ?? "";
          $name = $this->_columns[$columnMatchedKey]['name'] ?? "";
          $customFieldsIndex = str_replace($alias, $name, $tableCol);
          $customField = $customFields[$customFieldsIndex] ?? NULL;
        }
        if ($customField) {
          if ($customField['data_type'] === 'Money') {
            $rows[$rowNum][$tableCol] = $val;
          }
          else if ($customField['data_type'] === 'Boolean') {
            $rows[$rowNum][$tableCol] = $val;
          }
          else {
            $rows[$rowNum][$tableCol] = CRM_Core_BAO_CustomField::displayValue($val, $customField['id']);
          }

          if (!empty($this->_drilldownReport)) {
            $baseUrl = array_key_first($this->_drilldownReport);
            $label = $this->_drilldownReport[$baseUrl];
            $fieldName = 'custom_' . $customField['id'];
            $criteriaQueryParams = CRM_Report_Utils_Report::getPreviewCriteriaQueryParams($this->_defaults, $this->_params);
            $groupByCriteria = $this->getGroupByCriteria($tableCol, $row);

            $val = ($val == NULL) ? '' : $val;
            $url = CRM_Report_Utils_Report::getNextUrl($baseUrl,
              "reset=1&force=1&$criteriaQueryParams&" .
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
   * @param ?string $value
   *
   * @return string
   */
  protected function commaSeparateCustomValues(?string $value): string {
    if (empty($value)) {
      return '';
    }

    if (strpos($value, CRM_Core_DAO::VALUE_SEPARATOR) === 0) {
      $value = substr($value, 1);
    }
    if ($value[strlen($value) - 1] === CRM_Core_DAO::VALUE_SEPARATOR) {
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
   * @return float|string
   */
  protected function formatCustomValues($value, $customField, $fieldValueMap, array $row = []) {
    // @todo this might hopefully be already done by metadata - for booleans it is.
    if (!empty($this->_customGroupExtends) && count($this->_customGroupExtends) === 1) {
      //lets only extend apply edit-ability where only one entity extended
      // we can easily extend to contact combos
      [$entity] = $this->_customGroupExtends;
      $entity_table = $this->_aliases[strtolower('civicrm_' . $entity)];
      $idKeyArray = [$entity_table . '.id'];
      if (empty($this->_groupByArray) || $this->_groupByArray === $idKeyArray) {
        foreach ($this->getMetadataByType('fields') as $fieldName => $field) {
          if ($field['name'] === 'id') {
            $entity_field = $fieldName;
            $alias = $field['alias'];
            break;
          }
        }
        $entityID = $row[$alias];
      }
    }
    if (CRM_Utils_System::isNull($value) && !in_array($customField['data_type'], [
        'String',
        'Int',
      ])
    ) {
      // we will return unless it is potentially an editable field
      return '';
    }

    $htmlType = $customField['html_type'];

    switch ($customField['data_type']) {
      case 'Boolean':
        // Already handled.
        $retValue = $value;
        break;

      case 'Memo':
      case 'File':
        $retValue = $value;
        break;

      case 'Float':
        if ($htmlType === 'Text') {
          $retValue = (float) $value;
          break;
        }
      case 'Money':
        if ($htmlType === 'Text') {
          $retValue = $value;
          break;
        }
      case 'String':
      case 'Int':
        if (in_array($htmlType, [
          'Text',
          'TextArea',
          'Select',
          'Radio',
        ])
        ) {
          if ($htmlType === 'Select' || $htmlType === 'Radio') {
            $retValue = CRM_Utils_Array::value($value, $fieldValueMap[$customField['option_group_id']]);
          }
          else {
            $retValue = $value;
          }
          $extra = '';
          if (($htmlType === 'Select' || $htmlType === 'Radio') && !empty($entity)) {
            $options = civicrm_api($entity, 'getoptions', [
              'version' => 3,
              'field' => 'custom_' . $customField['id'],
            ]);
            $options = $options['values'];
            $options['selected'] = $value;
            $extra = "data-type='select' data-options='" . json_encode($options, JSON_HEX_APOS) . "'";
            $value = $options[$value];
          }
          if (!empty($entity_field)) {
            $retValue = "<div id=$entity-$entityID class='crm-entity'>" .
              "<span class='crm-editable crmf-custom_{$customField['id']} crm-editable' data-action='create' $extra >" . $value . "</span></div>";
          }
          break;
        }
      case 'StateProvince':
      case 'Country':

        switch ($htmlType) {
          case 'Multi-Select Country':
            $value = explode(CRM_Core_DAO::VALUE_SEPARATOR, $value);
            $customData = [];
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
            $customData = [];
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
          case 'Multi-Select':
            $value = explode(CRM_Core_DAO::VALUE_SEPARATOR, $value);
            $customData = [];
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
   * check if a table exists
   *
   * @param string $tableName Name of table
   *
   * @return bool
   * @throws \Civi\Core\Exception\DBQueryException
   */
  protected function tableExists(string $tableName): bool {
    $sql = "SHOW TABLES LIKE '$tableName'";
    $result = CRM_Core_DAO::executeQuery($sql);
    $result->fetch();
    return (bool) $result->N;
  }

  /**
   * Function to add columns because I wasn't enjoying adding filters to each fn.
   *
   * @param string $type
   * @param array $options
   *
   * @return array
   */
  protected function getColumns($type, $options = []): array {
    $defaultOptions = [
      'prefix' => '',
      'prefix_label' => '',
      'fields' => TRUE,
      'group_by' => FALSE,
      'order_by' => TRUE,
      'filters' => TRUE,
      'join_filters' => FALSE,
      'join_fields' => FALSE,
      'is_extendable' => TRUE,
      'fields_defaults' => [],
      'filters_defaults' => [],
      'group_bys_defaults' => [],
      'order_by_defaults' => [],
    ];
    $options = array_merge($defaultOptions, $options);

    $fn = 'get' . $type . 'Columns';
    $columns = $this->$fn($options);

    foreach ([
     'filters',
     'group_by',
     'order_by',
     'join_filters',
   ] as $metadataType) {
      if (!$options[$metadataType]) {
        foreach ($columns as &$table) {
          if (isset($table[$metadataType])) {
            $table[$metadataType] = [];
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
    if ($options['is_extendable'] &&
      !empty(CRM_Core_SelectValues::customGroupExtends()[$type])) {
      $this->_customGroupExtends[] = $type;
    }
    return $columns;
  }

  /**
   * Build the columns.
   *
   * The normal report class needs you to remember to do a few things that are
   * often erratic
   * 1) use a unique key for any field that might not be unique (e.g. start
   * date, label)
   * - this class will always prepend an alias to the key & set the 'name' if
   * you don't set it yourself.
   * - note that it assumes the value being passed in is the actual table
   * fieldname
   *
   * 2) set the field & set it to no display if you don't want the field but
   * you might want to use the field in other contexts - the code looks up the
   * fields array for data - so it both defines the field spec & the fields you
   * want to show
   *
   * @param array $specs
   * @param string $tableName
   * @param null $daoName
   * @param null $tableAlias
   * @param array $defaults
   * @param array $options Options
   *    - group_title
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function buildColumns($specs, $tableName, $daoName = NULL, $tableAlias = NULL, $defaults = [], $options = []): array {

    if (!$tableAlias) {
      $tableAlias = str_replace('civicrm_', '', $tableName);
    }
    $types = ['filters', 'group_bys', 'order_bys', 'join_filters', 'aggregate_columns', 'aggregate_rows'];
    $columns = [$tableName => array_fill_keys($types, [])];
    if (!empty($daoName)) {
      $columns[$tableName]['bao'] = $daoName;
    }
    $columns[$tableName]['alias'] = $tableAlias;
    $exportableFields = $this->getMetadataForFields(['dao' => $daoName]);

    foreach ($specs as $specName => $spec) {
      $spec['table_key'] = $tableName;
      unset($spec['default']);
      if (empty($spec['name'])) {
        $spec['name'] = $specName;
      }
      if (empty($spec['dbAlias'])) {
        $spec['dbAlias'] = $tableAlias . '.' . $spec['name'];
      }
      $daoSpec = (array) CRM_Utils_Array::value($spec['name'], $exportableFields, ($exportableFields[$tableAlias . '_' . $spec['name']] ?? []));
      $spec = array_merge($daoSpec, $spec);
      if (!isset($columns[$tableName]['table_name']) && isset($spec['table_name'])) {
        $columns[$tableName]['table_name'] = $spec['table_name'];
      }

      if (!isset($spec['operatorType'])) {
        $spec['operatorType'] = $this->getOperatorType($spec['type'], $spec);
      }
      foreach (array_merge($types, ['fields']) as $type) {
        $isKey = 'is_' . $type;
        if (isset($options[$type]) && !empty($spec[$isKey])) {
          // Options can change TRUE to FALSE for a field, but not vice versa.
          $spec[$isKey] = $options[$type];
        }
        if (!isset($spec[$isKey])) {
          $spec[$isKey] = FALSE;
        }
      }

      $fieldAlias = (empty($options['no_field_disambiguation']) ? $tableAlias . '_' : '') . $specName;
      $spec['alias'] = $tableName . '_' . $fieldAlias;
      if ($this->isPivot && !empty($spec['options'])) {
        $spec['is_aggregate_columns'] = TRUE;
        $spec['is_aggregate_rows'] = TRUE;
      }
      $columns[$tableName]['metadata'][$fieldAlias] = $spec;
      $columns[$tableName]['fields'][$fieldAlias] = $spec;
      if (isset($defaults['fields_defaults']) && in_array($spec['name'], $defaults['fields_defaults'], TRUE)) {
        $columns[$tableName]['metadata'][$fieldAlias]['is_fields_default'] = TRUE;
      }

      if (empty($spec['is_fields']) || (isset($options['fields_excluded']) && in_array($specName, $options['fields_excluded']))) {
        $columns[$tableName]['fields'][$fieldAlias]['no_display'] = TRUE;
      }

      if (!empty($spec['is_filters']) && !empty($spec['statistics']) && !empty($options) && !empty($options['group_by'])) {
        foreach ($spec['statistics'] as $statisticName => $statisticLabel) {
          $columns[$tableName]['filters'][$fieldAlias . '_' . $statisticName] = array_merge($spec, [
            'title' => E::ts('Aggregate filter : ') . $statisticLabel,
            'having' => TRUE,
            'dbAlias' => $tableName . '_' . $fieldAlias . '_' . $statisticName,
            'selectAlias' => "$statisticName($tableAlias.{$spec['name']})",
            'is_fields' => FALSE,
            'is_aggregate_field_for' => $fieldAlias,
          ]);
          $columns[$tableName]['metadata'][$fieldAlias . '_' . $statisticName] = $columns[$tableName]['filters'][$fieldAlias . '_' . $statisticName];
        }
      }

      foreach ($types as $type) {
        if (!empty($spec['is_' . $type])) {
          if ($type === 'join_filters') {
            $fieldAlias = 'join__' . $fieldAlias;
          }
          $columns[$tableName][$type][$fieldAlias] = $spec;
          $defaultKey = $type . '_defaults';
          if (isset($defaults[$defaultKey][$spec['name']])) {
            if ($type === 'filters' || $type === 'join_filters') {
              $columns[$tableName]['metadata'][$fieldAlias]['default'] = $defaults[$defaultKey][$spec['name']];
            }
            elseif ($type === 'group_bys') {
              $columns[$tableName]['metadata'][$fieldAlias]['is_group_bys_default'] = $defaults[$defaultKey][$spec['name']];
            }
          }
        }
      }
    }
    $columns[$tableName]['prefix'] = $options['prefix'] ?? '';
    $columns[$tableName]['prefix_label'] = $options['prefix_label'] ?? '';
    $columns[$tableName]['is_required_for_acls'] = $options['is_required_for_acls'] ?? FALSE;
    if (isset($options['group_title'])) {
      $columns[$tableName]['group_title'] = $options['group_title'];
      $columns[$tableName]['grouping'] = $options['grouping'];
    }
    else {
      // We can make one up but it won't be translated....
      $columns[$tableName]['group_title'] = ucfirst(str_replace('_', ' ', str_replace('civicrm_', '', $tableName)));
    }

    return $columns;
  }

  /**
   * Get the columns for the line items table.
   *
   * @param array $options
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getLineItemColumns(array $options): array {
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
    $defaults = $this->getDefaultsFromOptions($options);

    $specs = [
      'financial_type_id' => [
        'title' => ts('Line Item Financial Type'),
        'type' => CRM_Utils_Type::T_INT,
        'alter_display' => 'alterFinancialType',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contribute_BAO_Contribution::buildOptions('financial_type_id', 'get'),
      ],
      'id' => [
        'title' => ts('Individual Line Item'),
        'type' => CRM_Utils_Type::T_INT,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
      ],
      'participant_count' => [
        'title' => ts('Participant Count'),
        'type' => CRM_Utils_Type::T_INT,
        'statistics' => [
          'sum' => ts('Total Participants'),
        ],
        'is_fields' => TRUE,
      ],
      'price_field_id' => [
        'title' => ts('Price Field (line item)'),
        'type' => CRM_Utils_Type::T_INT,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
      ],
      'price_field_value_id' => [
        'title' => ts('Price Field Option (line item)'),
        'type' => CRM_Utils_Type::T_INT,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
      ],
      'qty' => [
        'title' => ts('Quantity'),
        'type' => CRM_Utils_Type::T_INT,
        'operator' => CRM_Report_Form::OP_INT,
        'statistics' => [
          'sum' => ts('Total Quantity Selected'),
        ],
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'unit_price' => [
        'title' => ts('Unit Price'),
        'type' => CRM_Utils_Type::T_MONEY,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'contribution_id' => [
        'title' => ts('Contribution Count'),
        'type' => CRM_Utils_Type::T_INT,
        'statistics' => [
          'count' => ts('Count of Contributions'),
        ],
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'line_total' => [
        'title' => ts('Line Total'),
        'type' => CRM_Utils_Type::T_MONEY,
        'statistics' => [
          'sum' => ts('Total of Line Items'),
        ],
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'label' => [
        'title' => ts('Line Label'),
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'tax_amount' => [
        'title' => ts('Tax Amount'),
        'type' => CRM_Utils_Type::T_MONEY,
        'statistics' => [
          'sum' => ts('Tax Total of Line Items'),
        ],
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'non_deductible_amount' => [
        'title' => ts('Non Deductible Amount'),
        'type' => CRM_Utils_Type::T_MONEY,
        'statistics' => [
          'sum' => ts('Non Deductible Total of Line Items'),
        ],
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
    ];

    return $this->buildColumns($specs, 'civicrm_line_item', 'CRM_Price_BAO_LineItem', NULL, $defaults);
  }

  /**
   *
   * @return array
   *
   * @throws \CRM_Core_Exception
   */
  protected function getPriceFieldValueColumns(): array {
    $pseudoMethod = $this->financialTypePseudoConstant;
    $specs = [
      'label' => [
        'title' => ts('Price Field Value Label'),
        'type' => CRM_Utils_Type::T_STRING,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_fields' => TRUE,
        'is_group_bys' => TRUE,
      ],
      'max_value' => [
        'title' => 'Price Option Maximum',
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
      ],
      'financial_type_id' => [
        'title' => 'Price Option Financial Type',
        'type' => CRM_Utils_Type::T_INT,
        'alter_display' => 'alterFinancialType',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'is_fields' => TRUE,
        'options' => CRM_Contribute_PseudoConstant::$pseudoMethod(),
      ],
    ];
    return $this->buildColumns($specs, 'civicrm_price_field_value', 'CRM_Price_BAO_PriceFieldValue');
  }

  /**
   * Get column specs for civicrm_price_fields.
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getPriceFieldColumns(): array {
    $specs = [
      'price_field_label' => [
        'title' => ts('Price Field Label'),
        'type' => CRM_Utils_Type::T_STRING,
        'name' => 'label',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
        'operator' => 'like',
      ],
    ];
    return $this->buildColumns($specs, 'civicrm_price_field', 'CRM_Price_BAO_PriceField');
  }

  /**
   * @param array $options
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getParticipantColumns(array $options = []): array {
    static $_events = [];
    if (!isset($_events['all'])) {
      CRM_Core_PseudoConstant::populate($_events['all'], 'CRM_Event_DAO_Event', FALSE, 'title', 'is_active', "is_template IS NULL OR is_template = 0", 'title');
    }
    $specs = [
      'id' => [
        'title' => 'Participant ID',
        'is_fields' => TRUE,
        'name' => 'id',
        'type' => CRM_Utils_Type::T_INT,
      ],
      'participant_registered_by_id' => [
        'title' => 'Registered by ID',
        'is_fields' => TRUE,
        'name' => 'registered_by_id',
      ],
      'participant_registered_by_name' => [
        'title' => 'Registered by Name',
        'is_fields' => TRUE,
        'name' => 'registered_by_id',
        'alter_display' => 'alterRegisteredName',
      ],
      'participant_event_id' => [
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
      ],
      'participant_status_id' => [
        'name' => 'status_id',
        'title' => ts('Event Participant Status'),
        'alter_display' => 'alterParticipantStatus',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Event_PseudoConstant::participantStatus(NULL, NULL, 'label'),
        'type' => CRM_Utils_Type::T_INT,
      ],
      'participant_role_id' => [
        'name' => 'role_id',
        'title' => ts('Participant Role'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT_SEPARATOR,
        'options' => CRM_Event_PseudoConstant::participantRole(),
        'alter_display' => 'alterParticipantRole',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'participant_fee_level' => [
        'name' => 'fee_level',
        'type' => CRM_Utils_Type::T_STRING,
        'operator' => 'like',
        'title' => ts('Participant Fee Level'),
        'is_fields' => TRUE,
      ],
      'participant_fee_amount' => NULL,
      'register_date' => [
        'title' => 'Registration Date',
        'operatorType' => CRM_Report_Form::OP_DATE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'is_test' => [
        'is_fields' => FALSE,
        'is_filters' => TRUE,
        'is_order_bys' => FALSE,
        'title' => 'Is a test registration?',
        'operatorType' => CRM_Report_Form::OP_SELECT,
        'options' => ['' => '--select--'] + CRM_Event_BAO_Participant::buildOptions('is_test'),
        'default' => 0,
        'type' => CRM_Utils_Type::T_STRING,
      ],
    ];

    return $this->buildColumns($specs, 'civicrm_participant', 'CRM_Event_BAO_Participant');
  }

  /**
   * @param array $options
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getMembershipColumns(array $options): array {
    $specs = [
      'membership_type_id' => [
        'title' => 'Membership Type',
        'alter_display' => 'alterMembershipTypeID',
        'options' => $this->_getOptions('membership', 'membership_type_id'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'name' => 'membership_type_id',
        'type' => CRM_Utils_Type::T_INT,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
      ],
      'membership_status_id' => [
        'name' => 'status_id',
        'title' => 'Membership Status',
        'alter_display' => 'alterMembershipStatusID',
        'options' => $this->_getOptions('membership', 'status_id', 'get'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'type' => CRM_Utils_Type::T_INT,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
      ],
      'join_date' => [
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'type' => CRM_Utils_Type::T_DATE,
        'operatorType' => CRM_Report_Form::OP_DATE,
      ],
      'start_date' => [
        'name' => 'start_date',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'title' => ts('Current Cycle Start Date'),
        'type' => CRM_Utils_Type::T_DATE,
        'operatorType' => CRM_Report_Form::OP_DATE,
      ],
      'end_date' => [
        'name' => 'end_date',
        'is_fields' => TRUE,
        'title' => ts('Current Membership Cycle End Date'),
        'include_null' => TRUE,
        'is_group_bys' => TRUE,
        'type' => CRM_Utils_Type::T_DATE,
        'operatorType' => CRM_Report_Form::OP_DATE,
      ],
      'id' => [
        'title' => 'Membership ID / Count',
        'name' => 'id',
        'statistics' => ['count' => ts('Number of Memberships')],
      ],
      'contact_id' => [
        'title' => 'Membership Contact ID',
        'name' => 'contact_id',
        'is_filters' => TRUE,
      ],
      'owner_membership_id' => [
        'title' => ts('Primary Membership'),
        'operatorType' => CRM_Report_Form::OP_INT,
        'is_filters' => TRUE,
      ],
    ];
    return $this->buildColumns($specs, $options['prefix'] . 'civicrm_membership', 'CRM_Member_DAO_Membership');
  }

  /**
   * Get columns from the membership log table.
   *
   * @param array $options
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getMembershipLogColumns(array $options = []): array {
    $columns = [
      'civicrm_membership_log' => [
        'grouping' => 'member-fields',
        'fields' => [
          'membership_type_id' => [
            'title' => ts($options['prefix_label'] . 'Membership Type'),
            'alter_display' => 'alterMembershipTypeID',
            'options' => $this->_getOptions('membership', 'membership_type_id'),
            'is_fields' => TRUE,
            'is_filters' => TRUE,
            'is_group_bys' => TRUE,
            'name' => 'membership_type_id',
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          ],
          'membership_status_id' => [
            'name' => 'status_id',
            'title' => ts($options['prefix_label'] . 'Membership Status'),
            'alter_display' => 'alterMembershipStatusID',
            'options' => $this->_getOptions('membership', 'status_id', $action = 'get'),
            'is_fields' => TRUE,
            'is_filters' => TRUE,
            'is_group_bys' => TRUE,
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          ],
          'start_date' => [
            'name' => 'start_date',
            'is_fields' => TRUE,
            'is_filters' => TRUE,
            'title' => ts($options['prefix_label'] . ' Start Date'),
            'type' => CRM_Utils_Type::T_DATE,
            'operatorType' => CRM_Report_Form::OP_DATE,
          ],
          'end_date' => [
            'name' => 'end_date',
            'is_fields' => TRUE,
            'title' => ts($options['prefix_label'] . ' Membership Cycle End Date'),
            'include_null' => TRUE,
            'is_group_bys' => TRUE,
            'is_filters' => TRUE,
            'type' => CRM_Utils_Type::T_DATE,
            'operatorType' => CRM_Report_Form::OP_DATE,
          ],
        ],
      ],
    ];
    return $this->buildColumns($columns['civicrm_membership_log']['fields'], $options['prefix'] . 'civicrm_membership_log', 'CRM_Member_DAO_MembershipLog', [], $options);
  }

  /**
   * @param array $options
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getFinancialAccountColumns(array $options = []): array {
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
    $defaults = $this->getDefaultsFromOptions($options);
    $spec = [
      'accounting_code' => [
        'title' => ts($options['prefix_label'] . 'Financial Account Code'),
        'name' => 'accounting_code',
        'type' => CRM_Utils_Type::T_STRING,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contribute_PseudoConstant::financialAccount(NULL, NULL, 'accounting_code', 'accounting_code'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'name' => [
        'title' => ts($options['prefix_label'] . 'Financial Account Name'),
        'name' => 'name',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contribute_PseudoConstant::financialAccount(),
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
    ];
    return $this->buildColumns($spec, $options['prefix'] . 'civicrm_financial_account', 'CRM_Financial_DAO_FinancialAccount', NULL, $defaults);
  }

  /**
   * @return array
   *
   * @throws \CRM_Core_Exception
   */
  protected function getFinancialTrxnColumns(): array {
    $specs = [
      'check_number' => [
        'title' => ts('Cheque #'),
        'default' => TRUE,
        'type' => CRM_Utils_Type::T_STRING,
      ],
      'payment_processor_id' => [
        'title' => ts('Payment Processor'),
        'alter_display' => 'alterPaymentProcessor',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_PseudoConstant::paymentProcessor(TRUE),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
      ],
      'payment_instrument_id' => [
        'title' => ts('Payment Instrument'),
        'default' => TRUE,
        'alter_display' => 'alterPaymentType',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contribute_BAO_Contribution::buildOptions('payment_instrument_id', 'get'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'currency' => [
        'required' => TRUE,
        'no_display' => FALSE,
        'type' => CRM_Utils_Type::T_STRING,
        'title' => ts('Currency'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_OptionGroup::values('currencies_enabled'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'trxn_date' => [
        'title' => ts('Transaction Date'),
        'default' => TRUE,
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
        'operatorType' => CRM_Report_Form::OP_DATE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'trxn_id' => [
        'title' => ts('Transaction #'),
        'default' => TRUE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'type' => CRM_Utils_Type::T_STRING,
      ],
      'financial_trxn_status_id' => [
        'name' => 'status_id',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'title' => ts('Transaction Status'),
        'filters_default' => [1],
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'type' => CRM_Utils_Type::T_INT,
        'options' => CRM_Contribute_PseudoConstant::contributionStatus(),
      ],
    ];
    return $this->buildColumns($specs, 'civicrm_financial_trxn', 'CRM_Core_BAO_FinancialTrxn');
  }

  /**
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getFinancialTypeColumns(): array {
    $specs = [
      'name' => [
        'title' => ts('Financial_type'),
        'type' => CRM_Utils_Type::T_STRING,
      ],
      'accounting_code' => [
        'title' => ts('Accounting Code'),
        'type' => CRM_Utils_Type::T_STRING,
      ],
      'is_deductible' => [
        'title' => ts('Tax Deductible'),
        'type' => CRM_Utils_Type::T_BOOLEAN,
      ],
    ];
    return $this->buildColumns($specs, 'civicrm_financial_type', 'CRM_Financial_DAO_FinancialType');
  }

  /**
   * Get the columns for the pledge payment.
   *
   * @param array $options
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getPledgePaymentColumns(array $options): array {
    $specs = [
      $options['prefix'] . 'actual_amount' => [
        'title' => ts($options['prefix'] . 'Amount Paid'),
        'type' => CRM_Utils_Type::T_MONEY,
        'statistics' => ['sum' => ts('Total Amount Paid')],
        'is_fields' => TRUE,
      ],
      $options['prefix'] . 'scheduled_date' => [
        'type' => CRM_Utils_Type::T_DATE,
        'title' => ts($options['prefix'] . 'Scheduled Payment Due'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
        'frequency' => TRUE,
      ],
      $options['prefix'] . 'scheduled_amount' => [
        'type' => CRM_Utils_Type::T_MONEY,
        'title' => ts($options['prefix_label'] . 'Amount to be paid'),
        'is_fields' => TRUE,
        'statistics' => [
          'sum' => ts('Amount to be paid'),
          'cumulative' => ts('Cumulative to be paid'),
        ],
      ],
      $options['prefix'] . 'status_id' => [
        'type' => CRM_Utils_Type::T_INT,
        'title' => ts($options['prefix_label'] . 'Payment Status'),
        'is_fields' => FALSE,
        'is_filters' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contribute_PseudoConstant::contributionStatus(),
        'alter_display' => 'alterContributionStatus',
      ],
    ];
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
   * @throws \CRM_Core_Exception
   */
  protected function getNextPledgePaymentColumns(array $options): array {
    $specs = [
      $options['prefix'] . 'scheduled_date' => [
        'type' => CRM_Utils_Type::T_DATE,
        'title' => ts('Next Payment Due'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
        'frequency' => TRUE,
      ],
      $options['prefix'] . 'scheduled_amount' => [
        'type' => CRM_Utils_Type::T_MONEY,
        'title' => ts('Next payment Amount'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
    ];
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
  protected function getPledgePaymentActions(): array {
    return [
      'add_payment' => [
        'type' => CRM_Utils_Type::T_INT,
        'title' => ts('Payment Link'),
        'name' => 'id',
        'alter_display' => 'alterPledgePaymentLink',
        // Otherwise it will be suppressed. We retrieve & alter.
        'is_fields' => TRUE,
      ],
    ];
  }

  /**
   * @param array $options
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getMembershipTypeColumns(array $options): array {
    $spec = [
      'membership_type_id' => [
        'name' => 'id',
        'title' => ts('Membership Types'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'type' => CRM_Utils_Type::T_INT,
        'options' => CRM_Member_PseudoConstant::membershipType(),
        'is_fields' => TRUE,
      ],
    ];
    return $this->buildColumns($spec, $options['prefix'] . 'civicrm_membership_type', 'CRM_Member_DAO_MembershipType');

  }

  /**
   * Get a standardized array of <select> options for "Event Title"
   * - taken from core event class.
   *
   * @return array
   * @throws \Civi\Core\Exception\DBQueryException
   */
  protected function getEventFilterOptions(): array {
    $events = [];
    $query = "
      select id, start_date, title from civicrm_event
      where (is_template IS NULL OR is_template = 0) AND is_active
      order by title ASC, start_date
    ";
    $dao = CRM_Core_DAO::executeQuery($query);
    while ($dao->fetch()) {
      $events[$dao->id] = "$dao->title - " . CRM_Utils_Date::customFormat(substr($dao->start_date, 0, 10)) . " (ID $dao->id)";
    }
    return $events;
  }

  /**
   * Get columns for event table.
   *
   * This is called by getColumns.
   *
   * @param array $options
   *
   * @return array
   *
   * @throws \CRM_Core_Exception
   */
  protected function getEventColumns(array $options = []): array {
    $specs = [
      'event_id' => [
        'name' => 'id',
        'is_fields' => TRUE,
        'title' => ts('Event ID'),
        'type' => CRM_Utils_Type::T_INT,
      ],
      'title' => [
        'title' => ts('Event Title'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'crm_editable' => [
          'id_table' => 'civicrm_event',
          'id_field' => 'id',
          'entity' => 'event',
        ],
        'type' => CRM_Utils_Type::T_STRING,
        'name' => 'title',
        'operatorType' => CRM_Report_Form::OP_STRING,
      ],
      'event_type_id' => [
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

      ],
      'fee_label' => [
        'title' => ts('Fee Label'),
        'is_fields' => TRUE,
      ],
      'event_start_date' => [
        'title' => ts('Event Start Date'),
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
        'operatorType' => CRM_Report_Form::OP_DATE,
        'name' => 'start_date',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'event_end_date' => [
        'title' => ts('Event End Date'),
        'is_fields' => TRUE,
        'name' => 'end_date',
      ],
      'max_participants' => [
        'title' => ts('Capacity'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'crm_editable' => [
          'id_table' => 'civicrm_event',
          'id_field' => 'id',
          'entity' => 'event',
        ],
      ],
      'is_active' => [
        'title' => ts('Is Active'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'type' => CRM_Utils_Type::T_BOOLEAN,
        'operatorType' => CRM_Report_Form::OP_SELECT,
        'options' => $this->getBooleanOptions(),
        'crm_editable' => [
          'id_table' => 'civicrm_event',
          'id_field' => 'id',
          'entity' => 'event',
          'options' => ['0' => 'No', '1' => 'Yes'],
        ],
      ],
      'is_public' => [
        'title' => ts('Is Publicly Visible'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'crm_editable' => [
          'id_table' => 'civicrm_event',
          'id_field' => 'id',
          'entity' => 'event',
        ],
      ],
    ];
    return $this->buildColumns($specs, 'civicrm_event', 'CRM_Event_DAO_Event', NULL, $this->getDefaultsFromOptions($options));
  }

  /**
   * Get Columns for Event totals Summary.
   *
   * This is called by getColumns.
   *
   * @param array $options
   *
   * @return array
   *
   * @throws \CRM_Core_Exception
   */
  protected function getEventSummaryColumns(array $options = []): array {
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
    // $fields['civicrm_event_summary' . $options['prefix']]['fields'] =
    $specs = [
      'registered_amount' . $options['prefix'] => [
        'title' => $options['prefix_label'] . ts('Total Income'),
        'default' => TRUE,
        'type' => CRM_Utils_Type::T_MONEY,
        'statistics' => ['sum' => ts('Total Income')],
        'is_fields' => TRUE,
      ],
      'paid_amount' . $options['prefix'] => [
        'title' => $options['prefix_label'] . ts('Paid Up Income'),
        'default' => TRUE,
        'type' => CRM_Utils_Type::T_MONEY,
        'statistics' => ['sum' => ts('Total Paid Up Income')],
        'is_fields' => TRUE,
      ],
      'pending_amount' . $options['prefix'] => [
        'title' => $options['prefix_label'] . ts('Pending Income'),
        'default' => TRUE,
        'type' => CRM_Utils_Type::T_MONEY,
        'statistics' => ['sum' => ts('Total Pending Income')],
        'is_fields' => TRUE,
      ],
      'registered_count' . $options['prefix'] => [
        'title' => $options['prefix_label'] . ts('No. Participants'),
        'default' => TRUE,
        'type' => CRM_Utils_Type::T_INT,
        'statistics' => ['sum' => ts('Total No. Participants')],
        'is_fields' => TRUE,
      ],
      'paid_count' . $options['prefix'] => [
        'title' => $options['prefix_label'] . ts('Paid Up Participants'),
        'default' => TRUE,
        'type' => CRM_Utils_Type::T_INT,
        'statistics' => ['sum' => ts('Total No,. Paid Up Participants')],
        'is_fields' => TRUE,
      ],
      'pending_count' . $options['prefix'] => [
        'title' => $options['prefix_label'] . ts('Pending Participants'),
        'default' => TRUE,
        'type' => CRM_Utils_Type::T_INT,
        'statistics' => ['sum' => ts('Total Pending Participants')],
        'is_fields' => TRUE,
      ],
    ];
    return $this->buildColumns($specs, 'civicrm_event_summary' . $options['prefix'], NULL, NULL, $this->getDefaultsFromOptions($options));
  }

  /**
   * Get campaign columns.
   *
   * This is called by getColumns.
   *
   * @return array
   *
   * @throws \CRM_Core_Exception
   */
  protected function getCampaignColumns(): array {

    if (!CRM_Campaign_BAO_Campaign::isComponentEnabled()) {
      return ['civicrm_campaign' => ['fields' => [], 'metadata' => []]];
    }
    $specs = [
      'campaign_type_id' => [
        'title' => ts('Campaign Type'),
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_fields' => TRUE,
        'is_group_bys' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Campaign_BAO_Campaign::buildOptions('campaign_type_id'),
        'alter_display' => 'alterCampaignType',
      ],
      'id' => [
        'title' => ts('Campaign'),
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_fields' => TRUE,
        'is_group_bys' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Campaign_BAO_Campaign::getCampaigns(),
        'alter_display' => 'alterCampaign',
      ],
      'goal_revenue' => [
        'title' => ts('Revenue goal'),
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_MONEY,
      ],
    ];
    return $this->buildColumns($specs, 'civicrm_campaign', 'CRM_Campaign_BAO_Campaign');
  }

  /**
   * Get Contribution Columns.
   *
   * This is called by getColumns.
   *
   * @param array $options
   *
   * @return array
   *
   * @throws \CRM_Core_Exception
   */
  protected function getContributionColumns($options): array {

    $options = array_merge(['group_title' => E::ts('Contributions'), 'grouping' => 'contribution'], $options);
    $specs = [
      'id' => [
        'title' => ts('Contribution ID'),
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_fields' => TRUE,
        'is_group_bys' => TRUE,
      ],
      'financial_type_id' => [
        'title' => ts('Contribution Type (Financial)'),
        'type' => CRM_Utils_Type::T_INT,
        'alter_display' => 'alterFinancialType',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contribute_BAO_Contribution::buildOptions('financial_type_id', 'get'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
      ],
      'payment_instrument_id' => [
        'title' => ts('Payment Instrument'),
        'type' => CRM_Utils_Type::T_INT,
        'alter_display' => 'alterPaymentType',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contribute_BAO_Contribution::buildOptions('payment_instrument_id', 'get'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
      ],
      'contribution_status_id' => [
        'title' => ts('Contribution Status'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contribute_PseudoConstant::contributionStatus(),
        'alter_display' => 'alterContributionStatus',
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
      ],
      'campaign_id' => [
        'title' => ts('Campaign'),
        'type' => CRM_Utils_Type::T_INT,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Campaign_BAO_Campaign::getCampaigns(NULL, NULL, TRUE, FALSE),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
        'alter_display' => 'alterCampaign',
      ],
      'source' => [
        'title' => 'Contribution Source',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'trxn_id' => ['is_fields' => TRUE, 'is_order_bys' => TRUE,],
      'receive_date' => [
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
        'operatorType' => CRM_Report_Form::OP_DATETIME,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
      ],
      'receipt_date' => ['is_fields' => TRUE, 'is_order_bys' => TRUE,],
      'thankyou_date' => ['is_fields' => TRUE, 'is_order_bys' => TRUE, 'is_filters' => TRUE],
      'total_amount' => [
        'title' => ts('Contribution Amount'),
        'statistics' => [
          'count' => ts('No. Contributions'),
          'sum' => ts('Total Amount'),
        ],
        'type' => CRM_Utils_Type::T_MONEY,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
      ],
      'fee_amount' => ['is_fields' => TRUE],
      'net_amount' => ['is_fields' => TRUE],
      'check_number' => ['is_fields' => TRUE, 'is_order_bys' => TRUE,],
      'contribution_page_id' => [
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'title' => ts('Contribution Page'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => $this->_getOptions('Contribution', 'contribution_page_id'),
        'type' => CRM_Utils_Type::T_INT,
        'alter_display' => 'alterContributionPage',
      ],
      'currency' => [
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'title' => 'Currency',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_OptionGroup::values('currencies_enabled'),
        'default' => NULL,
        'type' => CRM_Utils_Type::T_STRING,
      ],
      'contribution_recur_id' => [
        'title' => ts('Recurring Contribution ID'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'type' => CRM_Utils_Type::T_INT,
      ],
      'contribution_recur_id_exists' => [
        'title' => ts('Recurring Contribution?'),
        'pseudofield' => TRUE,
        'is_fields' => TRUE,
        'is_order_bys' => TRUE,
        'alter_display' => 'alterRecurringContributionId',
        'requires' => [
          "{$options['prefix']}contribution_{$options['prefix']}contribution_recur_id",
        ],
        'type' => CRM_Utils_Type::T_STRING,
      ],
      'is_test' => [
        'is_fields' => FALSE,
        'is_filters' => TRUE,
        'is_order_bys' => FALSE,
        'title' => 'Is a test transaction?',
        'operatorType' => CRM_Report_Form::OP_SELECT,
        'options' => ['' => '--select--'] + CRM_Contribute_BAO_Contribution::buildOptions('is_test'),
        'default' => NULL,
        'type' => CRM_Utils_Type::T_STRING,
      ],
      'is_pay_later' => [
        'is_fields' => FALSE,
        'is_filters' => TRUE,
        'is_order_bys' => FALSE,
        'title' => 'Is Pay Later?',
        'operatorType' => CRM_Report_Form::OP_SELECT,
        'options' => ['' => '--select--'] + CRM_Contribute_BAO_Contribution::buildOptions('is_pay_later'),
        'default' => NULL,
        'type' => CRM_Utils_Type::T_STRING,
      ],
      'contact_id' => [
        'title' => ts('Contribution Contact ID'),
        'name' => 'contact_id',
        'is_filters' => TRUE,
      ],
      'invoice_number' => (\Civi::settings()->get('invoicing') ? [
        'title' => ts('Invoice Number'),
        'name' => 'invoice_number',
        'is_filters' => TRUE,
        'is_fields' => TRUE,
      ] : []),
    ];
    return $this->buildColumns($specs, 'civicrm_contribution', 'CRM_Contribute_BAO_Contribution', NULL, $this->getDefaultsFromOptions($options), $options);
  }

  /**
   * Get Columns for Contact Contribution Summary
   *
   * @param array $options
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getContributionSummaryColumns($options = []): array {
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

    $spec =
      [
        'contributionsummary' . $options['prefix'] => [
          'title' => $options['prefix_label'] . ts('Contribution Details'),
          'default' => TRUE,
          'required' => TRUE,
          'alter_display' => 'alterDisplaytable2csv',
        ],

      ];

    return $this->buildColumns($spec, $options['prefix'] . 'civicrm_contribution_summary', 'CRM_Contribute_DAO_Contribution', NULL, $defaults);
  }

  /**
   * Get columns for the batch.
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  public function getBatchColumns(): array {
    if (!$this->includeBatches()) {
      return [];
    }
    $specs = [
      'title' => [
        'title' => ts('Batch Title'),
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_fields' => TRUE,
        'is_group_bys' => TRUE,
        // keep free form text... there could be lots of batches after a while
        // make selection unwieldy.
        'type' => CRM_Utils_Type::T_STRING,
      ],
      'status_id' => [
        'title' => ts('Batch Status'),
        'is_filters' => TRUE,
        'is_order_bys' => FALSE,
        'is_fields' => TRUE,
        'is_group_bys' => FALSE,
        // keep free form text... there could be lots of batches after a while
        // make selection unwieldy.
        'alter_display' => 'alterBatchStatus',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Batch_BAO_Batch::buildOptions('status_id'),
        'type' => CRM_Utils_Type::T_INT,
      ],
    ];
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
   * @throws \CRM_Core_Exception
   */
  public function getPhoneColumns($options = []): array {
    $defaultOptions = [
      'prefix' => '',
      'prefix_label' => '',
      'group_by' => FALSE,
      'order_by' => TRUE,
      'filters' => TRUE,
      'defaults' => [],
      'subquery' => TRUE,
      'fields_defaults' => [],
      'filters_defaults' => [],
      'group_bys_defaults' => [],
      'order_by_defaults' => [],
    ];

    $options = array_merge($defaultOptions, $options);
    $defaults = $this->getDefaultsFromOptions($options);

    $spec = [
      $options['prefix'] . 'phone' => [
        'title' => ts($options['prefix_label'] . 'Phone'),
        'name' => 'phone',
        'is_fields' => TRUE,
      ],
      $options['prefix'] . 'phone_numeric' => [
        'title' => $options['prefix_label'] . ' ' . E::ts('Phone (numbers only)'),
        'name' => 'phone_numeric',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
    ];

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
   * @throws \CRM_Core_Exception
   */
  protected function getPledgeColumns(array $options = []): array {
    $spec = [
      'id' => [
        'no_display' => TRUE,
        'required' => TRUE,
      ],
      'contact_id' => [
        'no_display' => TRUE,
        'required' => TRUE,
      ],
      'amount' => [
        'title' => ts('Pledged Amount'),
        'statistics' => ['sum' => ts('Total Pledge Amount')],
        'type' => CRM_Utils_Type::T_MONEY,
        'name' => 'amount',
        'operatorType' => CRM_Report_Form::OP_INT,
        'is_fields' => TRUE,
      ],
      'financial_type_id' => [
        'title' => ts('Financial Type'),
        'type' => CRM_Utils_Type::T_INT,
        'alter_display' => 'alterFinancialType',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contribute_BAO_Contribution::buildOptions('financial_type_id', 'get'),
      ],
      'frequency_unit' => [
        'title' => ts('Frequency Unit'),
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_STRING,
      ],
      'installments' => [
        'title' => ts('Installments'),
        'is_fields' => TRUE,
      ],
      'create_date' => [
        'title' => ts('Pledge Made Date'),
        'operatorType' => CRM_Report_Form::OP_DATE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'start_date' => [
        'title' => ts('Pledge Start Date'),
        'type' => CRM_Utils_Type::T_DATE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'end_date' => [
        'title' => ts('Pledge End Date'),
        'type' => CRM_Utils_Type::T_DATE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      'status_id' => [
        'name' => 'status_id',
        'title' => ts('Pledge Status'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_OptionGroup::values('contribution_status'),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'type' => CRM_Utils_Type::T_INT,
      ],
      'campaign_id' => [
        'title' => ts('Campaign'),
        'type' => CRM_Utils_Type::T_INT,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Campaign_BAO_Campaign::getCampaigns(),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_order_bys' => TRUE,
        'is_group_bys' => TRUE,
        'alter_display' => 'alterCampaign',
      ],
    ];

    return $this->buildColumns($spec, 'civicrm_pledge', 'CRM_Pledge_DAO_Pledge');
  }

  /**
   * Get email columns.
   *
   * @param array $options
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getEmailColumns(array $options = []): array {
    $defaultOptions = [
      'prefix' => '',
      'prefix_label' => '',
      'group_by' => FALSE,
      'order_by' => TRUE,
      'filters' => TRUE,
      'join_filters' => TRUE,
      'fields_defaults' => ['display_name', 'id'],
      'filters_defaults' => [],
      'group_bys_defaults' => [],
      'order_by_defaults' => ['sort_name ASC'],
    ];
    $options = array_merge($defaultOptions, $options);
    $defaults = $this->getDefaultsFromOptions($options);

    $fields = [
      'email' => [
        'title' => ts($options['prefix_label'] . 'Email'),
        'name' => 'email',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
        'type' => CRM_Utils_Type::T_STRING,
        'operatorType' => CRM_Report_Form::OP_STRING,
      ],
      'on_hold' => [
        'title' => ts($options['prefix_label'] . 'On Hold'),
        'name' => 'on_hold',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
        'type' => CRM_Utils_Type::T_BOOLEAN,
        'options' => $this->getBooleanOptions(),
        'operatorType' => CRM_Report_Form::OP_SELECT,
      ],
    ];
    return $this->buildColumns($fields, $options['prefix'] . 'civicrm_email', 'CRM_Core_DAO_Email', NULL, $defaults, $options);
  }

  /**
   * Get email columns.
   *
   * @param array $options
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getWebsiteColumns(array $options = []): array {
    $defaultOptions = [
      'prefix' => '',
      'prefix_label' => '',
      'group_by' => FALSE,
      'order_by' => TRUE,
      'filters' => TRUE,
      'fields_defaults' => ['display_name', 'id'],
      'filters_defaults' => [],
      'group_bys_defaults' => [],
      'order_by_defaults' => ['sort_name ASC'],
    ];
    $options = array_merge($defaultOptions, $options);
    $defaults = $this->getDefaultsFromOptions($options);

    $fields = [
      'url' => [
        'title' => $options['prefix_label'] . ts('Website'),
        'name' => 'url',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
        'type' => CRM_Utils_Type::T_STRING,
        'operatorType' => CRM_Report_Form::OP_STRING,
      ],
      'website_type_id' => [
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
      ],
    ];
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
   * @throws \CRM_Core_Exception
   */
  protected function getNoteColumns(array $options = []): array {
    $defaultOptions = [
      'prefix' => '',
      'prefix_label' => '',
      'group_by' => FALSE,
      'order_by' => TRUE,
      'filters' => TRUE,
    ];
    $options = array_merge($defaultOptions, $options);

    $fields = [
      'note' => [
        'title' => ts($options['prefix_label'] . 'Note'),
        'name' => 'note',
        'is_fields' => TRUE,
      ],
    ];
    return $this->buildColumns($fields, $options['prefix'] . 'civicrm_note', 'CRM_Core_DAO_Note');
  }

  /**
   * Get columns for relationship fields.
   *
   * @param array $options
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getRelationshipColumns(array $options = []): array {
    $defaultOptions = [
      'prefix' => '',
      'prefix_label' => '',
      'group_by' => FALSE,
      'order_by' => TRUE,
      'filters' => TRUE,
      'fields_defaults' => ['display_name', 'id'],
      'filters_defaults' => [],
      'group_bys_defaults' => [],
      'order_by_defaults' => ['sort_name ASC'],
    ];

    $options = array_merge($defaultOptions, $options);
    $defaults = $this->getDefaultsFromOptions($options);
    $prefix = $options['prefix'];
    $specs = [
      $prefix . 'id' => [
        'name' => 'id',
        'title' => ts('Relationship ID'),
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_STRING,
      ],
      $prefix . 'relationship_start_date' => [
        'title' => ts('Relationship Start Date'),
        'name' => 'start_date',
        'type' => CRM_Utils_Type::T_DATE,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_join_filters' => TRUE,
      ],
      $prefix . 'relationship_end_date' => [
        'title' => ts('Relationship End Date'),
        'name' => 'end_date',
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_DATE,
        'is_filters' => TRUE,
        'is_join_filters' => TRUE,
      ],
      $prefix . 'relationship_description' => [
        'title' => ts('Description'),
        'name' => 'description',
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ],
      $prefix . 'relationship_is_active' => [
        'title' => ts('Relationship Status'),
        'name' => 'is_active',
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => [
          '' => '- Any -',
          1 => 'Active',
          0 => 'Inactive',
        ],
        'is_filters' => TRUE,
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_INT,
        'is_join_filters' => TRUE,
      ],
      $prefix . 'relationship_type_id' => [
        'name' => 'relationship_type_id',
        'title' => ts('Relationship Type'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => [
            '' => '- any relationship type -',
          ] +
          CRM_Contact_BAO_Relationship::getContactRelationshipType(NULL, 'null', NULL, NULL, TRUE),
        'type' => CRM_Utils_Type::T_INT,
        'is_filters' => TRUE,
        'is_join_filters' => FALSE,
      ],
      // For the join filters we will use a one-way option list to make our life easier.
      $prefix . 'join_relationship_type_id' => [
        'name' => 'relationship_type_id',
        'title' => ts('Relationship Type'),
        'operatorType' => CRM_Report_Form::OP_SELECT,
        'options' => [
            '' => '- any relationship type -',
          ] +
          $this->getRelationshipABOptions(),
        'type' => CRM_Utils_Type::T_INT,
        'is_join_filters' => TRUE,
      ],


    ];

    return $this->buildColumns($specs, 'civicrm_relationship', 'CRM_Contact_BAO_Relationship', NULL, $defaults);
  }

  /**
   * @throws \CRM_Core_Exception
   */
  protected function getRelationshipTypeColumns($options = []): array {
    $defaultOptions = [
      'prefix' => '',
      'prefix_label' => '',
      'group_by' => FALSE,
      'order_by' => TRUE,
      'filters' => TRUE,
      'fields_defaults' => ['display_name', 'id'],
      'filters_defaults' => [],
      'group_bys_defaults' => [],
      'order_by_defaults' => ['sort_name ASC'],
    ];

    $options = array_merge($defaultOptions, $options);
    $defaults = $this->getDefaultsFromOptions($options);
    $specs = [
      'label_a_b' => [
        'title' => ts('Relationship A-B '),
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => 1,
        'is_filters' => 1,
      ],
      'label_b_a' => [
        'title' => ts('Relationship B-A '),
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => 1,
        'is_filters' => 1,
      ],
      'contact_type_a' => [
        'title' => ts('Contact Type  A'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contact_BAO_Contact::buildOptions('contact_type'),
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => 0,
        'is_filters' => 1,
      ],
      'contact_type_b' => [
        'title' => ts('Contact Type  B'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Contact_BAO_Contact::buildOptions('contact_type'),
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => 0,
        'is_filters' => 1,
      ],
    ];
    return $this->buildColumns($specs, 'civicrm_relationship_type', 'CRM_Contact_BAO_RelationshipType', NULL, $defaults);
  }

  /**
   * Get an array of relationships in an mono a-b direction
   *
   * @return array
   *   Options in a format id => label
   * @throws \CRM_Core_Exception
   */
  protected function getRelationshipABOptions(): array {
    $relationshipTypes = civicrm_api3('relationship_type', 'get', [
      'contact_type_a' => 'Individual',
      'is_active' => TRUE,
    ]);
    $options = [];
    foreach ($relationshipTypes['values'] as $values) {
      $options[$values['id']] = $values['label_a_b'];
    }

    return $options;
  }

  /**
   * Add tab to report allowing a relationship to be chosen for extension.
   */
  protected function addJoinFiltersTab(): void {
    $this->tabs['Relationships'] = [
      'title' => ts('Join Filters'),
      'tpl' => 'Relationships',
      'div_label' => 'set-relationships',
    ];
  }

  /**
   * Filter statistics.
   *
   * @param array $statistics
   *
   * @throws \CRM_Core_Exception
   */
  public function filterStat(&$statistics): void {
    foreach ($this->getSelectedFilters() as $fieldName => $field) {
      $statistics['filters'][] = $this->getQillForField($field, $fieldName);
    }
    foreach ($this->getSelectedJoinFilters() as $fieldName => $field) {
      $join = $this->getQillForField($field, $fieldName, 'join_filter_');
      $join['title'] = E::ts('%1 only included based on filter ', [$field['entity']]) . $join['title'];
      $statistics['filters'][] = $join;
    }
    // Prevents an e-notice in Statistics.tpl
    if (!isset($statistics['filters'])) {
      $statistics['filters'] = [];
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
   * @throws \CRM_Core_Exception
   */
  protected function getAddressColumns($options = []): array {
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
      $options['prefix'] . 'name' => [
        'title' => ts($options['prefix_label'] . 'Address Name'),
        'name' => 'name',
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_STRING,
      ],
      $options['prefix'] . 'display_address' => [
        'title' => ts($options['prefix_label'] . 'Display Address'),
        'pseudofield' => TRUE,
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_STRING,
        'requires' => [
          "{$options['prefix']}address_{$options['prefix']}name",
          "{$options['prefix']}address_{$options['prefix']}supplemental_address_1",
          "{$options['prefix']}address_{$options['prefix']}supplemental_address_2",
          "{$options['prefix']}address_{$options['prefix']}supplemental_address_3",
          "{$options['prefix']}address_{$options['prefix']}street_address",
          "{$options['prefix']}address_{$options['prefix']}city",
          "{$options['prefix']}address_{$options['prefix']}postal_code",
          "{$options['prefix']}address_{$options['prefix']}postal_code_suffix",
          "{$options['prefix']}address_{$options['prefix']}county_id",
          "{$options['prefix']}address_{$options['prefix']}country_id",
          "{$options['prefix']}address_{$options['prefix']}state_province_id",
          "{$options['prefix']}address_{$options['prefix']}is_primary",
          "{$options['prefix']}address_{$options['prefix']}location_type_id",
        ],
        'alter_display' => 'alterDisplayAddress',
      ],
      $options['prefix'] . 'street_number' => [
        'name' => 'street_number',
        'title' => ts($options['prefix_label'] . 'Street Number'),
        'type' => 1,
        'crm_editable' => [
          'id_table' => 'civicrm_address',
          'id_field' => 'id',
          'entity' => 'address',
        ],
        'is_fields' => TRUE,
      ],
      $options['prefix'] . 'street_name' => [
        'name' => 'street_name',
        'title' => ts($options['prefix_label'] . 'Street Name'),
        'type' => 1,
        'crm_editable' => [
          'id_table' => 'civicrm_address',
          'id_field' => 'id',
          'entity' => 'address',
        ],
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'operator' => 'like',
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'street_address' => [
        'title' => ts($options['prefix_label'] . 'Street Address'),
        'name' => 'street_address',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,

      ],
      $options['prefix'] . 'supplemental_address_1' => [
        'title' => ts($options['prefix_label'] . 'Supplementary Address Field 1'),
        'name' => 'supplemental_address_1',
        'crm_editable' => [
          'id_table' => 'civicrm_address',
          'id_field' => 'id',
          'entity' => 'address',
        ],
        'is_fields' => TRUE,
      ],
      $options['prefix'] . 'supplemental_address_2' => [
        'title' => ts($options['prefix_label'] . 'Supplementary Address Field 2'),
        'name' => 'supplemental_address_2',
        'crm_editable' => [
          'id_table' => 'civicrm_address',
          'id_field' => 'id',
          'entity' => 'address',
        ],
        'is_fields' => TRUE,
      ],
      $options['prefix'] . 'supplemental_address_3' => [
        'title' => ts($options['prefix_label'] . 'Supplementary Address Field 3'),
        'name' => 'supplemental_address_3',
        'crm_editable' => [
          'id_table' => 'civicrm_address',
          'id_field' => 'id',
          'entity' => 'address',
        ],
        'is_fields' => TRUE,
      ],
      $options['prefix'] . 'street_number' => [
        'name' => 'street_number',
        'title' => ts($options['prefix_label'] . 'Street Number'),
        'type' => 1,
        'is_order_bys' => TRUE,
        'is_filters' => TRUE,
        'is_fields' => TRUE,
      ],
      $options['prefix'] . 'street_name' => [
        'name' => 'street_name',
        'title' => ts($options['prefix_label'] . 'Street Name'),
        'type' => 1,
        'is_fields' => TRUE,
      ],
      $options['prefix'] . 'street_unit' => [
        'name' => 'street_unit',
        'title' => ts($options['prefix_label'] . 'Street Unit'),
        'type' => 1,
        'is_fields' => TRUE,
      ],
      $options['prefix'] . 'city' => [
        'title' => ts($options['prefix_label'] . 'City'),
        'name' => 'city',
        'operator' => 'like',
        'crm_editable' => [
          'id_table' => 'civicrm_address',
          'id_field' => 'id',
          'entity' => 'address',
        ],
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'postal_code' => [
        'title' => ts($options['prefix_label'] . 'Postal Code'),
        'name' => 'postal_code',
        'type' => 1,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'postal_code_suffix' => [
        'title' => ts($options['prefix_label'] . 'Postal Code Suffix'),
        'name' => 'postal_code',
        'type' => 1,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => TRUE,
      ],
      $options['prefix'] . 'county_id' => [
        'title' => ts($options['prefix_label'] . 'County'),
        'alter_display' => 'alterCountyID',
        'name' => 'county_id',
        'type' => CRM_Utils_Type::T_INT,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_PseudoConstant::county(),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
      ],
      $options['prefix'] . 'state_province_id' => [
        'title' => ts($options['prefix_label'] . 'State/Province'),
        'alter_display' => 'alterStateProvinceID',
        'name' => 'state_province_id',
        'type' => CRM_Utils_Type::T_INT,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_PseudoConstant::stateProvince(),
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
      ],
      $options['prefix'] . 'country_id' => [
        'title' => ts($options['prefix_label'] . 'Country'),
        'alter_display' => 'alterCountryID',
        'name' => 'country_id',
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'type' => CRM_Utils_Type::T_INT,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => CRM_Core_PseudoConstant::country(),
      ],
      $options['prefix'] . 'location_type_id' => [
        'name' => 'is_primary',
        'title' => ts($options['prefix_label'] . 'Location Type'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'is_join_filters' => TRUE,
        'alter_display' => 'alterLocationTypeID',
        'options' => CRM_Core_BAO_Address::buildOptions('location_type_id'),
      ],
      $options['prefix'] . 'id' => [
        'title' => ts($options['prefix_label'] . ' Address ID'),
        'name' => 'id',
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_INT,
      ],
      $options['prefix'] . 'is_primary' => [
        'name' => 'is_primary',
        'title' => ts($options['prefix_label'] . 'Primary Address?'),
        'type' => CRM_Utils_Type::T_BOOLEAN,
        'is_fields' => TRUE,
      ],
    ];
    return $this->buildColumns($spec, $options['prefix'] . 'civicrm_address', 'CRM_Core_DAO_Address', NULL, $defaults, $options);
  }

  /**
   * Get billing address columns to add to array.
   *
   * @param array $options
   *
   * @return array billing address columns definition
   * @throws \CRM_Core_Exception
   */
  protected function getBillingAddressColumns($options = []): array {
    $options['prefix'] = 'billing';
    $spec = [
      $options['prefix'] . 'name' => [
        'title' => ts($options['prefix_label'] . 'Billing Name'),
        'name' => 'name',
        'is_fields' => TRUE,
        'alter_display' => 'alterBillingName',
      ],
    ];
    //FIX THIS
    return $this->buildColumns($spec, $options['prefix'] . 'civicrm_address', 'CRM_Core_DAO_Address', NULL);
  }

  /**
   * @param $value
   *
   * @return string
   */
  protected function alterBillingName($value): string {
    if (empty($value)) {
      return '';
    }

    return str_replace(CRM_Core_DAO::VALUE_SEPARATOR, ' ', $value);
  }

  /**
   * Get Specification
   * for tag columns.
   *
   * @param array $options
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getTagColumns(array $options = []): array {
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
      'tag_name' => [
        'name' => 'name',
        'title' => 'Tags associated with this person',
        'is_fields' => TRUE,
        'type' => CRM_Utils_Type::T_STRING,
      ],
    ];

    return $this->buildColumns($spec, $options['prefix'] . 'civicrm_tag', 'CRM_Core_DAO_EntityTag', NULL, $defaults);
  }

  /**
   * Get Information about advertised Joins.
   *
   * @return array
   */
  protected function getAvailableJoins(): array {
    return [
      'batch_from_financialTrxn' => [
        'leftTable' => 'civicrm_financial_trxn',
        'rightTable' => 'civicrm_batch',
        'callback' => 'joinBatchFromFinancialTrxn',
      ],
      'batch_from_contribution' => [
        'leftTable' => 'contribution',
        'rightTable' => 'civicrm_batch',
        'callback' => 'joinBatchFromContribution',
      ],
      'campaign_fromPledge' => [
        'leftTable' => 'civicrm_pledge',
        'rightTable' => 'civicrm_campaign',
        'callback' => 'joinCampaignFromPledge',
      ],
      'pledge_from_pledge_payment' => [
        'leftTable' => 'civicrm_pledge_payment',
        'rightTable' => 'civicrm_pledge',
        'callback' => 'joinPledgeFromPledgePayment',
      ],
      'priceFieldValue_from_lineItem' => [
        'leftTable' => 'civicrm_line_item',
        'rightTable' => 'civicrm_price_field_value',
        'callback' => 'joinPriceFieldValueFromLineItem',
      ],
      'priceField_from_lineItem' => [
        'leftTable' => 'civicrm_line_item',
        'rightTable' => 'civicrm_price_field',
        'callback' => 'joinPriceFieldFromLineItem',
      ],
      'participant_from_lineItem' => [
        'leftTable' => 'civicrm_line_item',
        'rightTable' => 'civicrm_participant',
        'callback' => 'joinParticipantFromLineItem',
      ],
      'contribution_from_lineItem' => [
        'leftTable' => 'civicrm_line_item',
        'rightTable' => 'civicrm_contribution',
        'callback' => 'joinContributionFromLineItem',
      ],
      'membership_from_lineItem' => [
        'leftTable' => 'civicrm_line_item',
        'rightTable' => 'civicrm_membership',
        'callback' => 'joinMembershipFromLineItem',
      ],
      'contribution_from_contact' => [
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_contribution',
        'callback' => 'joinContributionFromContact',
      ],
      'contribution_from_participant' => [
        'leftTable' => 'civicrm_participant',
        'rightTable' => 'civicrm_contribution',
        'callback' => 'joinContributionFromParticipant',
      ],
      'contribution_from_membership' => [
        'leftTable' => 'civicrm_membership',
        'rightTable' => 'civicrm_contribution',
        'callback' => 'joinContributionFromMembership',
      ],
      'financial_trxn_from_contribution' => [
        'leftTable' => 'civicrm_contribution',
        'rightTable' => 'civicrm_financial_trxn',
        'callback' => 'joinFinancialTrxnFromContribution',
      ],
      'contribution_recur_from_contribution' => [
        'leftTable' => 'civicrm_contribution',
        'rightTable' => 'civicrm_contribution_recur',
        'callback' => 'joinContributionRecurFromContribution',
      ],
      'product_from_contribution' => [
        'leftTable' => 'civicrm_contribution',
        'rightTable' => 'civicrm_product',
        'callback' => 'joinProductFromContribution',
      ],
      'membership_from_contribution' => [
        'leftTable' => 'civicrm_contribution',
        'rightTable' => 'civicrm_membership',
        'callback' => 'joinMembershipFromContribution',
      ],
      'membershipType_from_membership' => [
        'leftTable' => 'civicrm_membership',
        'rightTable' => 'civicrm_membership_type',
        'callback' => 'joinMembershipTypeFromMembership',
      ],
      'membershipStatus_from_membership' => [
        'leftTable' => 'civicrm_membership',
        'rightTable' => 'civicrm_membership_status',
        'callback' => 'joinMembershipStatusFromMembership',
      ],
      'lineItem_from_contribution' => [
        'leftTable' => 'civicrm_contribution',
        'rightTable' => 'civicrm_line_item',
        'callback' => 'joinLineItemFromContribution',
      ],
      'lineItem_from_financialTrxn' => [
        'leftTable' => 'civicrm_financial_trxn',
        'rightTable' => 'civicrm_contribution',
        'callback' => 'joinLineItemFromFinancialTrxn',
      ],
      'contact_from_participant' => [
        'leftTable' => 'civicrm_participant',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinContactFromParticipant',
      ],
      'contact_from_membership' => [
        'leftTable' => 'civicrm_membership',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinContactFromMembership',
      ],
      'contact_from_contribution' => [
        'leftTable' => 'civicrm_contribution',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinContactFromContribution',
      ],
      'contact_from_contribution_recur' => [
        'leftTable' => 'civicrm_contribution_recur',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinContactFromContributionRecur',
      ],
      'contribution_from_contribution_soft' => [
        'leftTable' => 'civicrm_contribution_soft',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinContributionFromContributionSoft',
      ],
      'contact_from_pledge' => [
        'leftTable' => 'civicrm_pledge',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinContactFromPledge',
      ],
      'next_payment_from_pledge' => [
        'leftTable' => 'civicrm_pledge',
        'rightTable' => 'civicrm_pledge_payment',
        'callback' => 'joinNextPaymentFromPledge',
      ],
      'event_from_participant' => [
        'leftTable' => 'civicrm_participant',
        'rightTable' => 'civicrm_event',
        'callback' => 'joinEventFromParticipant',
      ],
      'eventsummary_from_event' => [
        'leftTable' => 'civicrm_event',
        'rightTable' => 'civicrm_event_summary',
        'callback' => 'joinEventSummaryFromEvent',
      ],
      'address_from_contact' => [
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_address',
        'callback' => 'joinAddressFromContact',
      ],
      'address_from_contribution' => [
        'leftTable' => 'civicrm_contribution',
        'rightTable' => 'civicrm_address',
        'callback' => 'joinAddressFromContribution',
      ],
      'address_from_event' => [
        'leftTable' => 'civicrm_event',
        'rightTable' => 'civicrm_address',
        'callback' => 'joinAddressFromEvent',
      ],
      'contact_from_address' => [
        'leftTable' => 'civicrm_address',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinContactFromAddress',
      ],
      'email_from_contact' => [
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_email',
        'callback' => 'joinEmailFromContact',
      ],
      'website_from_contact' => [
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_website',
        'callback' => 'joinWebsiteFromContact',
      ],
      'primary_phone_from_contact' => [
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_phone',
        'callback' => 'joinPrimaryPhoneFromContact',
      ],
      'phone_from_contact' => [
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_phone',
        'callback' => 'joinPhoneFromContact',
      ],
      'latestactivity_from_contact' => [
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_email',
        'callback' => 'joinLatestActivityFromContact',
      ],
      'entitytag_from_contact' => [
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_tag',
        'callback' => 'joinEntityTagFromContact',
      ],
      'contribution_summary_table_from_contact' => [
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_contribution_summary',
        'callback' => 'joinContributionSummaryTableFromContact',
      ],
      'contact_from_case' => [
        'leftTable' => 'civicrm_case',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinContactFromCase',
      ],
      'case_from_activity' => [
        'leftTable' => 'civicrm_activity',
        'rightTable' => 'civicrm_case',
        'callback' => 'joinCaseFromActivity',
      ],
      'case_activities_from_case' => [
        'callback' => 'joinCaseActivitiesFromCase',
      ],
      'single_contribution_comparison_from_contact' => [
        'callback' => 'joinContributionSinglePeriod',
      ],
      'activity_from_case' => [
        'leftTable' => 'civicrm_case',
        'rightTable' => 'civicrm_activity',
        'callback' => 'joinActivityFromCase',
      ],
      'activity_target_from_activity' => [
        'leftTable' => 'civicrm_activity',
        'rightTable' => 'civicrm_activity_contact',
        'callback' => 'joinActivityTargetFromActivity',
      ],
      'activity_assignee_from_activity' => [
        'leftTable' => 'civicrm_activity',
        'rightTable' => 'civicrm_activity_contact',
        'callback' => 'joinActivityAssigneeFromActivity',
      ],
      'related_contact_from_participant' => [
        'leftTable' => 'civicrm_participant',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinRelatedContactFromParticipant',
      ],
      'note_from_participant' => [
        'leftTable' => 'civicrm_participant',
        'rightTable' => 'civicrm_note',
        'callback' => 'joinNoteFromParticipant',
      ],
      'note_from_contribution' => [
        'leftTable' => 'civicrm_contribution',
        'rightTable' => 'civicrm_note',
        'callback' => 'joinNoteFromContribution',
      ],
      'note_from_contact' => [
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_note',
        'callback' => 'joinNoteFromContact',
      ],
      'contact_from_grant' => [
        'leftTable' => 'civicrm_grant',
        'rightTable' => 'civicrm_contact',
        'callback' => 'joinContactFromGrant',
      ],
    ];
  }

  /**
   * Define join from Activity to Activity Target
   */
  protected function joinActivityTargetFromActivity(): void {
    $activityContacts = CRM_Core_OptionGroup::values('activity_contacts', FALSE, FALSE, FALSE, NULL, 'name');
    $targetID = CRM_Utils_Array::key('Activity Targets', $activityContacts);
    $this->_from .= "
      LEFT JOIN civicrm_activity_contact civicrm_activity_target
        ON {$this->_aliases['civicrm_activity']}.id = civicrm_activity_target.activity_id
        AND civicrm_activity_target.record_type_id = $targetID
      LEFT JOIN civicrm_contact {$this->_aliases['target_civicrm_contact']}
        ON civicrm_activity_target.contact_id = {$this->_aliases['target_civicrm_contact']}.id
        AND {$this->_aliases['target_civicrm_contact']}.is_deleted = 0
      ";
  }

  /**
   * Define join from Activity to Activity Assignee
   */
  protected function joinActivityAssigneeFromActivity(): void {
    $activityContacts = CRM_Core_OptionGroup::values('activity_contacts', FALSE, FALSE, FALSE, NULL, 'name');
    $assigneeID = CRM_Utils_Array::key('Activity Assignees', $activityContacts);
    $this->_from .= "
      LEFT JOIN civicrm_activity_contact civicrm_activity_assignment
        ON {$this->_aliases['civicrm_activity']}.id = civicrm_activity_assignment.activity_id
        AND civicrm_activity_assignment.record_type_id = $assigneeID
      LEFT JOIN civicrm_contact {$this->_aliases['assignee_civicrm_contact']}
        ON civicrm_activity_assignment.contact_id = {$this->_aliases['assignee_civicrm_contact']}.id
     ";
  }

  /**
   * Define join from Activity to Activity Source.
   */
  protected function joinActivitySourceFromActivity(): void {
    $activityContacts = CRM_Core_OptionGroup::values('activity_contacts', FALSE, FALSE, FALSE, NULL, 'name');
    $sourceID = CRM_Utils_Array::key('Activity Source', $activityContacts);
    $this->_from .= "
      LEFT JOIN civicrm_activity_contact civicrm_activity_source
      ON {$this->_aliases['civicrm_activity']}.id = civicrm_activity_source.activity_id
      AND civicrm_activity_source.record_type_id = $sourceID
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
  protected function joinAddressFromContact($prefix = '', $extra = []): bool {

    if ($this->isTableSelected($prefix . 'civicrm_address')) {
      $this->_from .= " LEFT JOIN civicrm_address {$this->_aliases[$prefix . 'civicrm_address']}
    ON {$this->_aliases[$prefix . 'civicrm_address']}.contact_id = {$this->_aliases[$prefix . 'civicrm_contact']}.id
    AND {$this->_aliases[$prefix . 'civicrm_address']}.is_primary = 1
    ";
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Add join from contact table to address.
   *
   * Prefix will be added to both tables as it's assumed you are using it to get address of a secondary contact
   *
   * @param string $prefix prefix to add to table names
   *
   * @return bool true or false to denote whether extra filters can be appended to join
   */
  protected function joinAddressFromEvent(string $prefix = ''): bool {
    if ($this->isTableSelected($prefix . 'civicrm_address')) {
      $this->_from .= "
        LEFT JOIN civicrm_loc_block elb ON elb.id = {$this->_aliases[$prefix . 'civicrm_event']}.loc_block_id
        LEFT JOIN  civicrm_address {$this->_aliases[$prefix . 'civicrm_address']}
        ON {$this->_aliases[$prefix . 'civicrm_address']}.id = elb.address_id
    ";
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Add join from contribution table to address.
   *
   * Prefix will be added to both tables as it's assumed you are using it to get address of a secondary contact
   *
   * @param string $prefix prefix to add to table names
   *
   * @return bool true or false to denote whether extra filters can be appended to join
   */
  protected function joinAddressFromContribution(string $prefix = 'billingaddress'): bool {
    $this->_from .= " LEFT JOIN civicrm_address billingaddress
    ON billingaddress.id = {$this->_aliases[$prefix . 'civicrm_contribution']}.address_id
    AND billingaddress.is_billing = 1
    ";

    return TRUE;
  }

  /**
   * Add join from address table to contact.
   *
   * @param string $prefix prefix to add to table names
   *
   * @return bool true or false to denote whether extra filters can be appended to join
   */
  protected function joinContactFromAddress(string $prefix = ''): bool {

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
  protected function joinEmailFromContact($prefix = '', $extra = []): void {
    if ($this->isTableSelected($prefix . 'civicrm_email')) {
      $this->_from .= " LEFT JOIN civicrm_email {$this->_aliases[$prefix . 'civicrm_email']}
     ON {$this->_aliases[$prefix . 'civicrm_email']}.contact_id = {$this->_aliases[$prefix . 'civicrm_contact']}.id
     AND {$this->_aliases[$prefix . 'civicrm_email']}.is_primary = 1";
    }
  }

  /**
   * Add join from contact table to email.
   *
   * Prefix will be added to both tables as it's assumed you are using it to get address of a secondary contact.
   *
   * @param string $prefix
   */
  protected function joinWebsiteFromContact(string $prefix = ''): void {
    if ($this->isTableSelected($prefix . 'civicrm_website')) {
      $this->_from .= " LEFT JOIN civicrm_website {$this->_aliases[$prefix . 'civicrm_website']}
   ON {$this->_aliases[$prefix . 'civicrm_website']}.contact_id = {$this->_aliases[$prefix . 'civicrm_contact']}.id
";
      if (!empty($this->joinClauses['civicrm_website'])) {
        $this->_from .= ' AND ' . implode(',', $this->joinClauses['civicrm_website']);
      }
    }
  }

  protected function joinCampaignFromPledge($prefix = ''): void {
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
  protected function joinPhoneFromContact($prefix = '', $extra = []): void {
    if ($this->isTableSelected($prefix . 'civicrm_phone')) {
      $this->_from .= " LEFT JOIN civicrm_phone {$this->_aliases[$prefix . 'civicrm_phone']}
      ON {$this->_aliases[$prefix . 'civicrm_phone']}.contact_id = {$this->_aliases[$prefix . 'civicrm_contact']}.id
      ";
    }
  }

  /**
   * Add join from contact table to primary phone.
   *
   * @param string $prefix
   */
  protected function joinPrimaryPhoneFromContact(string $prefix = ''): void {
    if ($this->isTableSelected($prefix . 'civicrm_phone')) {
      $this->_from .= " LEFT JOIN civicrm_phone {$this->_aliases[$prefix . 'civicrm_phone']}
      ON {$this->_aliases[$prefix . 'civicrm_phone']}.contact_id = {$this->_aliases[$prefix . 'civicrm_contact']}.id
      AND {$this->_aliases[$prefix . 'civicrm_phone']}.is_primary = 1
      ";
    }
  }

  /**
   * Add join to entity tag from contact.
   *
   * @param string $prefix
   * @param array $extra [optional]
   */
  protected function joinEntityTagFromContact(string $prefix = '', array $extra = []): void {
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
   *  @noinspection PhpUnhandledExceptionInspection
   */
  protected function joinLatestActivityFromContact(): void {
    if (!$this->isTableSelected('civicrm_activity')) {
      return;
    }
    static $tmpTableName = NULL;
    if (empty($tmpTableName)) {

      $tmpTableName = 'civicrm_report_temp_latest_activity' . date('his') . random_int(1, 1000);
      $sql = "CREATE $this->_temporary TABLE $tmpTableName
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

  protected function joinPriceFieldValueFromLineItem(): void {
    $this->_from .= " LEFT JOIN civicrm_price_field_value {$this->_aliases['civicrm_price_field_value']}
ON {$this->_aliases['civicrm_line_item']}.price_field_value_id = {$this->_aliases['civicrm_price_field_value']}.id";
  }

  protected function joinPriceFieldFromLineItem(): void {
    $this->_from .= "
LEFT JOIN civicrm_price_field {$this->_aliases['civicrm_price_field']}
ON {$this->_aliases['civicrm_line_item']}.price_field_id = {$this->_aliases['civicrm_price_field']}.id
";
  }

  /**
   * Define join from line item table to participant table.
   */
  protected function joinParticipantFromLineItem(): void {
    $this->_from .= " LEFT JOIN civicrm_participant {$this->_aliases['civicrm_participant']}
ON ( {$this->_aliases['civicrm_line_item']}.entity_id = {$this->_aliases['civicrm_participant']}.id
AND {$this->_aliases['civicrm_line_item']}.entity_table = 'civicrm_participant')
";
  }

  /**
   * Define join from pledge payment table to pledge table..
   */
  protected function joinPledgeFromPledgePayment(): void {
    $this->_from .= "
     LEFT JOIN civicrm_pledge {$this->_aliases['civicrm_pledge']}
     ON {$this->_aliases['civicrm_pledge_payment']}.pledge_id = {$this->_aliases['civicrm_pledge']}.id";
  }

  /**
   * Define join from pledge table to pledge payment table.
   *
   * @throws \CRM_Core_Exception
   */
  protected function joinPledgePaymentFromPledge(): void {
    $until = CRM_Utils_Array::value('effective_date_value', $this->_params);
    $pledgePaymentStatuses = civicrm_api3('PledgePayment', 'getoptions', ['field' => 'status_id']);
    $toPayIDs = [
      array_search('Pending', $pledgePaymentStatuses['values']),
      array_search('Overdue', $pledgePaymentStatuses['values']),
    ];
    $this->_from .= "
      LEFT JOIN
      (SELECT p.*, p2.id, p2.scheduled_amount as next_scheduled_amount
      FROM (
        SELECT pledge_id, sum(if(status_id = 1, actual_amount, 0)) as actual_amount,
          IF(
          #Get the next scheduled payment date, if any.
            MIN(if(status_id IN (" . implode(',', $toPayIDs) . "), scheduled_date, '2200-01-01')) <> '2200-01-01',
            MIN(if(status_id IN (" . implode(',', $toPayIDs) . "), scheduled_date, '2200-01-01')),
          '') as scheduled_date,
          SUM(scheduled_amount) as scheduled_amount
        FROM civicrm_pledge_payment";
    if ($until) {
      $this->_from .=
        ' INNER JOIN civicrm_contribution c ON c.id = contribution_id  AND c.receive_date <="'
        . CRM_Utils_Type::validate(CRM_Utils_Date::processDate($until, 235959), 'Integer') . '"';
    }
    $this->_from .= "
       GROUP BY pledge_id) as p
      LEFT JOIN civicrm_pledge_payment p2
       ON p.pledge_id = p2.pledge_id AND p.scheduled_date = p2.scheduled_date
       AND p2.status_id IN (" . implode(',', $toPayIDs) . ")
      ";
    $this->_from .= "
     ) as {$this->_aliases['civicrm_pledge_payment']}
     ON {$this->_aliases['civicrm_pledge_payment']}.pledge_id = {$this->_aliases['civicrm_pledge']}.id";
  }

  /**
   * Join the pledge to the next payment due.
   */
  protected function joinNextPaymentFromPledge(): void {
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
  protected function joinRelatedContactFromParticipant(): void {
    if (!empty($this->joinClauses)
      || $this->isTableSelected($this->_aliases['related_civicrm_contact'])
      || $this->isTableSelected($this->_aliases['related_civicrm_phone'])
      || $this->isTableSelected($this->_aliases['related_civicrm_email'])
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
  protected function joinRelationshipTypeFromRelationship(): void {
    $this->_from .= "
      INNER JOIN civicrm_relationship_type {$this->_aliases['civicrm_relationship_type']}
      ON ( {$this->_aliases['civicrm_relationship']}.relationship_type_id  =
      {$this->_aliases['civicrm_relationship_type']}.id  )
    ";
  }

  /**
   * Define join from line item table to Membership table.
   */
  protected function joinMembershipFromLineItem(): void {
    $this->_from .= "
      LEFT JOIN civicrm_membership {$this->_aliases['civicrm_membership']}
      ON {$this->_aliases['civicrm_line_item']}.entity_id = {$this->_aliases['civicrm_membership']}.id
      AND {$this->_aliases['civicrm_line_item']}.entity_table = 'civicrm_membership'
    ";
  }

  /**
   * Define join from Contact to Contribution table
   */
  protected function joinContributionFromContact(): void {
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
  protected function joinContributionFromParticipant(): void {
    if ($this->isTableSelected('civicrm_contribution')) {
      $this->_from .= " LEFT JOIN civicrm_participant_payment pp
ON {$this->_aliases['civicrm_participant']}.id = pp.participant_id
LEFT JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
ON pp.contribution_id = {$this->_aliases['civicrm_contribution']}.id
";
    }
  }

  /**
   * Define join from Membership to Contribution table
   */
  protected function joinContributionFromMembership(): void {
    $this->_from .= "
      LEFT JOIN civicrm_membership_payment pp
      ON {$this->_aliases['civicrm_membership']}.id = pp.membership_id
  LEFT JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
  ON pp.contribution_id = {$this->_aliases['civicrm_contribution']}.id
";
  }

  /**
   * Join the recurring contribution table from the contribution table.
   */
  public function joinContributionRecurFromContribution(): void {
    if ($this->isTableSelected('civicrm_contribution_recur')) {
      $this->_from .= " LEFT JOIN civicrm_contribution_recur {$this->_aliases['civicrm_contribution_recur']}
      ON {$this->_aliases['civicrm_contribution_recur']}.id = {$this->_aliases['civicrm_contribution']}.contribution_recur_id";
    }
  }

  /**
   * Join the product (premium) table from the contribution table.
   */
  public function joinProductFromContribution(): void {
    if ($this->isTableSelected('civicrm_product') || $this->isTableSelected('civicrm_contribution_product')) {
      $this->_from .= "
       LEFT JOIN  civicrm_contribution_product {$this->_aliases['civicrm_contribution_product']}
         ON ({$this->_aliases['civicrm_contribution_product']}.contribution_id = {$this->_aliases['civicrm_contribution']}.id)
       LEFT JOIN  civicrm_product {$this->_aliases['civicrm_product']}
       ON ({$this->_aliases['civicrm_product']}.id = {$this->_aliases['civicrm_contribution_product']}.product_id)";
    }
  }

  /**
   * Join the participant table from the contribution table.
   */
  public function joinParticipantFromContribution(): void {
    $this->_from .= " LEFT JOIN civicrm_participant_payment pp
    ON {$this->_aliases['civicrm_contribution']}.id = pp.contribution_id
    LEFT JOIN civicrm_participant {$this->_aliases['civicrm_participant']}
    ON pp.participant_id = {$this->_aliases['civicrm_participant']}.id";
  }

  /**
   * Join the membership table from the contribution table.
   */
  public function joinMembershipFromContribution(): void {
    $this->_from .= "
LEFT JOIN civicrm_membership_payment pp
ON {$this->_aliases['civicrm_contribution']}.id = pp.contribution_id
LEFT JOIN civicrm_membership {$this->_aliases['civicrm_membership']}
ON pp.membership_id = {$this->_aliases['civicrm_membership']}.id";
  }

  public function joinMembershipTypeFromMembership():void {
    $this->_from .= "
LEFT JOIN civicrm_membership_type {$this->_aliases['civicrm_membership_type']}
ON {$this->_aliases['civicrm_membership']}.membership_type_id = {$this->_aliases['civicrm_membership_type']}.id
";
  }

  /**
   *
   */
  protected function joinMembershipStatusFromMembership(): void {
    $this->_from .= "
    LEFT JOIN civicrm_membership_status {$this->_aliases['civicrm_membership_status']}
    ON {$this->_aliases['civicrm_membership']}.status_id = {$this->_aliases['civicrm_membership_status']}.id
    ";
  }

  /**
   * Join contribution table from line item.
   */
  protected function joinContributionFromLineItem(): void {
    $this->_from .= "
      LEFT JOIN civicrm_contribution as {$this->_aliases['civicrm_contribution']}
      ON {$this->_aliases['civicrm_line_item']}.contribution_id = {$this->_aliases['civicrm_contribution']}.id
    ";
  }

  /**
   * Join line item table from contribution.
   */
  protected function joinLineItemFromContribution(): void {
    $this->_from .= "
      LEFT JOIN civicrm_line_item as {$this->_aliases['civicrm_line_item']}
      ON {$this->_aliases['civicrm_line_item']}.contribution_id = {$this->_aliases['civicrm_contribution']}.id
    ";
  }

  protected function joinLineItemFromFinancialTrxn(): void {
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
  protected function joinFinancialTrxnFromContribution(): void {
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
  protected function joinBatchFromFinancialTrxn(): void {
    if (!$this->isTableSelected('civicrm_batch')) {
      return;
    }
    $this->_from .= "
      LEFT  JOIN civicrm_entity_batch entity_batch
        ON entity_batch.entity_id = {$this->_aliases['civicrm_financial_trxn']}.id
        AND entity_batch.entity_table = 'civicrm_financial_trxn'
      LEFT  JOIN civicrm_batch {$this->_aliases['civicrm_batch']}
        ON {$this->_aliases['civicrm_batch']}.id = entity_batch.batch_id";
  }

  /**
   * Join batch table from Financial Trxn.
   */
  protected function joinBatchFromContribution(): void {
    if (!$this->isTableSelected('civicrm_batch')) {
      return;
    }
    $this->_from .= "
      LEFT JOIN civicrm_entity_financial_trxn eft
        ON eft.entity_id = {$this->_aliases['civicrm_contribution']}.id AND
          eft.entity_table = 'civicrm_contribution'
      LEFT JOIN civicrm_entity_batch entity_batch
        ON (entity_batch.entity_id = eft.financial_trxn_id
        AND entity_batch.entity_table = 'civicrm_financial_trxn')
      LEFT JOIN civicrm_batch {$this->_aliases['civicrm_batch']}
      ON {$this->_aliases['civicrm_batch']}.id = entity_batch.batch_id
    ";
  }

  protected function joinContactFromParticipant(): void {
    $this->_from .= "
      LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
ON {$this->_aliases['civicrm_participant']}.contact_id = {$this->_aliases['civicrm_contact']}.id
    ";

  }

  /**
   * Join in participant notes
   */
  protected function joinNoteFromParticipant(): void {
    $this->_from .= " LEFT JOIN civicrm_note {$this->_aliases['civicrm_note']}
ON ( {$this->_aliases['civicrm_participant']}.id = {$this->_aliases['civicrm_note']}.entity_id
AND {$this->_aliases['civicrm_note']}.entity_table = 'civicrm_participant') ";
  }

  /**
   * Join in contribution notes
   */
  protected function joinNoteFromContribution(): void {
    $this->joinNoteForEntity('contribution');
  }

  /**
   * Join in contact notes
   */
  protected function joinNoteFromContact(): void {
    $this->joinNoteForEntity('contact');
  }

  protected function joinContactFromGrant(): void {
    $this->_from .= "LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_grant']}.contact_id";
  }

  protected function joinContactFromMembership(): void {
    $this->_from .= " LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
ON {$this->_aliases['civicrm_membership']}.contact_id = {$this->_aliases['civicrm_contact']}.id";
  }

  protected function joinContactFromContribution(): void {
    $this->_from .= " LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
ON {$this->_aliases['civicrm_contribution']}.contact_id = {$this->_aliases['civicrm_contact']}.id";
  }

  /**
   * Join contact in from civicrm_contribution_recur table.
   */
  protected function joinContactFromContributionRecur(): void {
    $this->_from .= " LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
ON {$this->_aliases['civicrm_contribution_recur']}.contact_id = {$this->_aliases['civicrm_contact']}.id";
  }

  /**
   * Join contribution in from civicrm_contribution_soft table.
   */
  protected function joinContributionFromContributionSoft(): void {
    $this->_from .= " LEFT JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
ON {$this->_aliases['civicrm_contribution_soft']}.contribution_id = {$this->_aliases['civicrm_contribution']}.id";
  }

  /**
   * Define join from pledge table to contact table.
   */
  protected function joinContactFromPledge(): void {
    $this->_from .= "
      LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
      ON {$this->_aliases['civicrm_pledge']}.contact_id = {$this->_aliases['civicrm_contact']}.id
    ";
  }

  /**
   * Define join from participant table to event table.
   */
  protected function joinEventFromParticipant(): void {
    $this->_from .= " LEFT JOIN civicrm_event {$this->_aliases['civicrm_event']}
ON ({$this->_aliases['civicrm_event']}.id = {$this->_aliases['civicrm_participant']}.event_id ) AND
({$this->_aliases['civicrm_event']}.is_template IS NULL OR
{$this->_aliases['civicrm_event']}.is_template = 0)";
  }

  /**
   * @param string $prefix
   *
   * @throws \Exception
   */
  protected function joinEventSummaryFromEvent(string $prefix): void {
    $tempTable = 'civicrm_report_temp_contsumm' . $prefix . date('d_H_I') . random_int(1, 10000);
    $registeredStatuses = CRM_Event_PseudoConstant::participantStatus(NULL, "class = 'Positive'");
    $registeredStatuses = implode(', ', array_keys($registeredStatuses));
    $pendingStatuses = CRM_Event_PseudoConstant::participantStatus(NULL, "class = 'Pending'");
    $pendingStatuses = implode(', ', array_keys($pendingStatuses));

    //@todo currently statuses are hard-coded as 1 for complete & 5-6 for pending
    $createSQL = "
    CREATE $this->temporary table  $tempTable (
      `event_id` INT(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'FK to Event ID',
      `paid_amount` DECIMAL(42,2) NULL DEFAULT 0,
      `registered_amount` DECIMAL(48,6) NULL DEFAULT 0,
      `pending_amount` DECIMAL(48,6) NOT NULL DEFAULT '0',
      `paid_count` INT(10) UNSIGNED NULL DEFAULT '0',
      `registered_count` INT(10) UNSIGNED NULL DEFAULT '0',
      `pending_count` INT(10) UNSIGNED NULL DEFAULT '0',
      PRIMARY KEY (`event_id`)
    )";
    CRM_Core_DAO::executeQuery($createSQL);
    CRM_Core_DAO::executeQuery(
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
  public function joinContributionSummaryTableFromContact(string $prefix, array $extra): void {
    $criteria = " is_test = 0 ";
    if (!empty($extra['criteria'])) {
      $criteria .= " AND " . implode(' AND ', $extra['criteria']);
    }
    $tempTable = $this->createTemporaryTable('contribution_summary',
      "`contact_id` INT(10) UNSIGNED NOT NULL COMMENT 'Foreign key to civicrm_contact.id .',
      `contributionsummary$prefix` longtext NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
      INDEX `contact_id` (`contact_id`)
      )", TRUE
    );
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
      ,'</tr></table>') as contributions$prefix
      FROM (SELECT contact_id, receive_date, total_amount, name as financial_type_name
        FROM civicrm_contribution {$this->_aliases['civicrm_contribution']}
        LEFT JOIN civicrm_financial_type financial_type
        ON financial_type.id = {$this->_aliases['civicrm_contribution']}.financial_type_id
      WHERE $criteria
        ORDER BY receive_date DESC ) as conts
      GROUP BY contact_id
      ORDER BY NULL
     ";

    CRM_Core_DAO::executeQuery($insertSql);
    $this->_from .= " LEFT JOIN $tempTable {$this->_aliases['civicrm_contribution_summary' . $prefix]}
      ON {$this->_aliases['civicrm_contribution_summary' . $prefix]}.contact_id = {$this->_aliases['civicrm_contact']}.id";
  }

  /**
   *
   */
  protected function joinCaseFromContact(): void {
    $this->_from .= " LEFT JOIN civicrm_case_contact casecontact ON casecontact.contact_id = {$this->_aliases['civicrm_contact']}.id
    LEFT JOIN civicrm_case {$this->_aliases['civicrm_case']} ON {$this->_aliases['civicrm_case']}.id = casecontact.case_id ";
  }

  /**
   *
   */
  protected function joinActivityFromCase(): void {
    $this->_from .= "
      LEFT JOIN $this->_caseActivityTable cca ON cca.case_id = {$this->_aliases['civicrm_case']}.id
      LEFT JOIN civicrm_activity {$this->_aliases['civicrm_activity']} ON {$this->_aliases['civicrm_activity']}.id = cca.activity_id";
  }

  /**
   *
   */
  protected function joinCaseFromActivity(): void {
    $this->_from .= "
      LEFT JOIN civicrm_case_activity cca ON {$this->_aliases['civicrm_activity']}.id = cca.activity_id
      LEFT JOIN civicrm_case {$this->_aliases['civicrm_case']} ON cca.case_id = {$this->_aliases['civicrm_case']}.id
    ";
  }

  /**
   *
   */
  protected function joinContactFromCase(): void {
    $this->_from .= "
    LEFT JOIN civicrm_case_contact ccc ON ccc.case_id = {$this->_aliases['civicrm_case']}.id
    LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']} ON {$this->_aliases['civicrm_contact']}.id = ccc.contact_id ";
  }

  /**
   * Get URL string of criteria to potentially pass to subreport - obtains
   * potential criteria from $this->_potenial criteria
   *
   * @return string url string
   */
  protected function getCriteriaString(): string {
    $queryURL = "reset=1&force=1";
    if (!empty($this->_potentialCriteria) && is_array($this->_potentialCriteria)) {
      foreach ($this->_potentialCriteria as $criterion) {
        $name = $criterion . '_value';
        $op = $criterion . '_op';
        if (empty($this->_params[$name])) {
          continue;
        }
        $criterionValue = is_array($this->_params[$name]) ? implode(',', $this->_params[$name]) : $this->_params[$name];
        $queryURL .= "&$name=" . $criterionValue . "&$op=" . $this->_params[$op];
      }
    }
    return $queryURL;
  }

  /**
   * @param string|null $value
   * @param array $row
   * @param string $selectedField
   * @param string $criteriaFieldName
   * @param array $fullSpec
   *
   * @return string
   *
   * @noinspection PhpUnusedParameterInspection
   */
  protected function alterCrmEditable(?string $value,  array $row, string $selectedField, string $criteriaFieldName, array $fullSpec): string {
    $specs = $fullSpec['crm_editable'];
    $id_field = $specs['id_table'] . '_' . $specs['id_field'];
    if (empty($row[$id_field])) {
      // Check one more possible field...
      $id_field = $specs['id_table'] . '_' . $specs['entity'] . '_' . $specs['id_field'];
      if (empty($row[$id_field])) {
        //FIXME For some reason, the event id is returned with the entity repeated twice.
        //which means we need a tertiary check. This just a temporary fix
        $id_field = $specs['id_table'] . '_' . $specs['entity'] . '_' . $specs['entity'] . '_' . $specs['id_field'];
        if (empty($row[$id_field])) {
          // If the relevant id has not been set on the report the field cannot be editable.
          return (string) $value;
        }
      }
    }
    $entityID = $row[$id_field];
    $entity = $specs['entity'];
    $extra = $class = '';
    if (!empty($specs['options'])) {
      $specs['options']['selected'] = $value;
      $extra = "data-type='select'";
      $value = $specs['options'][$value] ?? $value;
      $class = 'editable_select';
    }
    elseif (!empty($specs['data-type'])) {
      $extra = "data-type='{$specs['data-type']}'";
    }

    //nodeName == "INPUT" && this.type=="checkbox"
    return "<div data-id='$entityID' data-entity='$entity' class='crm-entity'>" .
      "<span class='crm-editable crmf-{$fullSpec['field_name']} $class' data-action='create' $extra>" . $value . "</span></div>";
  }

  /**
   * @param $value
   * @param $row
   *
   * @return string
   */
  protected function alterNickName($value, $row): string {
    if (empty($row['civicrm_contact_id'])) {
      return '';
    }
    $contactID = $row['civicrm_contact_id'];
    return "<div id=contact-$contactID class='crm-entity'><span class='crm-editable crmf-nick_name crm-editable-enabled' data-action='create'>" . $value . "</span></div>";
  }

  /**
   * Retrieve text for contribution type from pseudoconstant.
   *
   * @param int|string|null $value Note a string would indicate a group by.
   * @param array $row
   *
   * @param string $selectedField
   * @param string $criteriaFieldName
   * @param array|null $specs
   *
   * @return string
   * @noinspection PhpUnusedParameterInspection
   */
  protected function alterFinancialType($value, array &$row, string $selectedField, string $criteriaFieldName, ?array $specs): string {
    if ($this->_drilldownReport) {
      // Issue #308 - drilldown report URLs should use original field name, not alias.
      $criteriaFieldName = $specs['fieldName'] ?? $selectedField;
      $criteriaQueryParams = CRM_Report_Utils_Report::getPreviewCriteriaQueryParams($this->_defaults, $this->_params);
      $url = CRM_Report_Utils_Report::getNextUrl(key($this->_drilldownReport),
        "reset=1&force=1&$criteriaQueryParams&" .
        "{$criteriaFieldName}_op=in&{$criteriaFieldName}_value=$value",
        $this->_absoluteUrl, $this->_id, $this->_drilldownReport
      );
      $row[$selectedField . '_link'] = $url;
    }
    $row[$selectedField . '_raw'] = $value;
    $financialTypes = explode(',', $value);
    $display = [];
    foreach ($financialTypes as $financialType) {
      $displayType = is_string(CRM_Contribute_PseudoConstant::financialType($financialType)) ? CRM_Contribute_PseudoConstant::financialType($financialType) : '';
      // Index the array in order to display each type only once.
      $display[$displayType] = $displayType;
    }
    return implode('|', $display);
  }

  /**
   * Retrieve text for contribution status from pseudoconstant
   *
   * @param $value
   *
   * @return string
   */
  protected function alterContributionStatus($value): string {
    return (string) CRM_Core_PseudoConstant::getLabel('CRM_Contribute_BAO_Contribution', 'contribution_status_id', $value);
  }

  /**
   * Retrieve text for contribution status from pseudoconstant
   *
   * @param $value
   * @param $row
   *
   * @param $selectedField
   * @param $criteriaFieldName
   * @param $spec
   *
   * @return string
   * @noinspection PhpUnusedParameterInspection
   */
  protected function alterByOptions($value, $row, $selectedField, $criteriaFieldName, $spec): string {
    return CRM_Core_PseudoConstant::getLabel($spec['bao'], $spec['name'], $value) ?? '';
  }

  /**
   * @param int|string|null $value
   * @return string
   */
  protected function alterCampaign($value): string {
    return CRM_Campaign_BAO_Campaign::getCampaigns(NULL, NULL, FALSE, FALSE, FALSE, TRUE)[$value] ?? '';
  }

  /**
   * @param $value
   *
   * @return string
   */
  protected function alterContributionPage($value): string {
    return $value === NULL ? '' : (CRM_Contribute_PseudoConstant::contributionPage($value) ?? '');
  }

  /**
   * @param int $value
   *
   * @return string
   */
  protected function alterCampaignType($value): string {
    $values = CRM_Campaign_BAO_Campaign::buildOptions('campaign_type_id');
    return (string) $values[$value];
  }

  /**
   * @param int|null $value
   *
   * @return string
   *   Event label.
   */
  protected function alterEventType($value): string {
    // Check if $value is comma separated.
    $separator = ',';
    if (strpos($value, $separator) !== FALSE) {
      // If yes, convert to array.
      $valueArr = explode($separator, $value);
      // Iterate through the array.
      foreach ($valueArr as $value) {
        // Look up the event type for each $value.
        $eventTypeArr[] = CRM_Event_PseudoConstant::eventType($value);
        // Then set the $value to a comma-space separated string of event types.
        $value = implode(', ', $eventTypeArr);
      }
      return $value;
    }
    // If not comma separated, just look up single event type.
    return $value ? CRM_Event_PseudoConstant::eventType($value) : '';
  }

  /**
   * @param int|null $value
   * @param array $row
   * @param string $selectedField
   *
   * @return string
   *   Name of primary participant.
   * @throws \CRM_Core_Exception
   */
  protected function alterRegisteredName($value, &$row, string $selectedField): string {
    if (!$value) {
      return '';
    }

    $registeredByContactId = CRM_Core_DAO::getFieldValue('CRM_Event_DAO_Participant', $value, 'contact_id', 'id');
    $row[$selectedField . '_link'] = CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid=' . $registeredByContactId);
    $row[$selectedField . '_hover'] = ts('View Contact Summary for Contact that registered the participant.');

    return CRM_Contact_BAO_Contact::displayName($registeredByContactId);
  }

  /**
   * replace event id with name & link to drilldown report
   *
   * @param string $value
   * @param array $row
   * @param string $selectedField
   *
   * @return string
   */
  protected function alterEventID($value, &$row, $selectedField): string {
    if (isset($this->_drilldownReport)) {
      $criteriaString = $this->getCriteriaString();
      $url = CRM_Report_Utils_Report::getNextUrl(implode(',', array_keys($this->_drilldownReport)),
        $criteriaString . '&event_id_op=in&event_id_value=' . $value,
        $this->_absoluteUrl, $this->_id, $this->_drilldownReport
      );
      $row[$selectedField . '_link'] = $url;
      $row[$selectedField . '_hover'] = ts(implode(',', $this->_drilldownReport));
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
  function alterCaseID($value, array &$row, string $selectedField, $criteriaFieldName) {
  }


  /**
   * @param $value
   *
   * @return array|string
   */
  protected function alterMembershipTypeID($value) {
    return is_string(CRM_Member_PseudoConstant::membershipType($value, FALSE)) ? CRM_Member_PseudoConstant::membershipType($value, FALSE) : '';
  }

  /**
   * @param int $value
   *
   * @return string
   */
  protected function alterMembershipStatusID($value): string {
    return is_string(CRM_Member_PseudoConstant::membershipStatus($value, FALSE)) ? CRM_Member_PseudoConstant::membershipStatus($value, FALSE) : '';
  }

  /**
   * @param int|float $value
   * @param array $row
   * @param string $selectedField
   *
   * @return int|float
   */
  protected function alterCumulative($value, array &$row, string $selectedField) {
    $cacheKey = $selectedField . 'cumulative';
    if (!isset(Civi::$statics[__CLASS__][$cacheKey])) {
      Civi::$statics[__CLASS__][$selectedField . 'cumulative'] = 0;
    }

    if (empty($row['is_rollup'])) {
      Civi::$statics[__CLASS__][$cacheKey] += $value;
    }
    $row[str_replace('_sum', '_cumulative', $selectedField)] = Civi::$statics[__CLASS__][$cacheKey];
    return $value;
  }

  /**
   * @param string|null $value
   *
   * @return string
   */
  protected function alterGenderID(?string $value): string {
    $genders = CRM_Contact_BAO_Contact::buildOptions('gender_id');

    if (CRM_Utils_Type::validate($value, 'CommaSeparatedIntegers', FALSE)) {
      $value = explode(',', $value);
    }

    foreach ((array) $value as $key => $genderID) {
      $value[$key] = $genders[trim($genderID)] ?? '';
    }

    return implode(', ', $value);
  }

  /**
   * Alter Employer ID value for display.
   *
   * @param int|string|null $value
   * @param array $row
   * @param string $fieldName
   *
   * @return string|null
   * @throws \CRM_Core_Exception
   */
  public function alterEmployerID($value, array &$row, string $fieldName): ?string {
    if ($value) {
      $values = explode(',', $value);
      try {
        $displayNames = [];
        foreach ($values as $id) {
          $displayNames[] = Contact::get()
            ->addWhere('id', '=', $id)
            ->addSelect('display_name')->execute()->first()['display_name'];
        }
        $row[$fieldName] = implode(', ', $displayNames);
        // Ho hum - only link to the first one.
        $row[$fieldName . '_link'] = CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid=' . $values[0], $this->_absoluteUrl);
        $row[$fieldName . '_hover'] = E::ts('View Contact Summary for Employer.');

        return $row[$fieldName];
      }
      catch (UnauthorizedException $e ) {
        // Let's just not show anything if they have no permission to view the employer.
        return NULL;
      }
    }
    return NULL;
  }

  /**
   * @param $value
   *
   * @return array|string|void
   */
  protected function alterParticipantStatus($value) {
    if (empty($value)) {
      return;
    }
    return CRM_Event_PseudoConstant::participantStatus($value, FALSE, 'label');
  }

  /**
   * @param $value
   *
   * @return string|null
   */
  protected function alterParticipantRole($value): ?string {
    if (empty($value)) {
      return NULL;
    }
    $roles = explode(CRM_Core_DAO::VALUE_SEPARATOR, $value);
    $value = [];
    foreach ($roles as $role) {
      $value[$role] = CRM_Event_PseudoConstant::participantRole($role, FALSE);
    }
    return implode(', ', $value);
  }

  /**
   * @param int|null $value
   *
   * @return string
   */
  protected function alterPaymentType(?string $value): string {
    $paymentInstruments = CRM_Contribute_BAO_Contribution::buildOptions('payment_instrument_id', 'get');
    $values = explode(',', $value);
    $labels = [];
    foreach ($values as $value) {
      $label = $paymentInstruments[$value] ?? '';
      if ($label) {
        $labels[] = $label;
      }
    }

    return (string) implode(',', $labels);
  }

  /**
   * @param int|null $value
   *
   * @return string
   */
  protected function alterPaymentProcessor(?int $value): string {
    $paymentProcessors = CRM_Contribute_PseudoConstant::paymentProcessor(TRUE);
    return CRM_Utils_Array::value($value, $paymentProcessors) ?? '';
  }

  /**
   * Convert the pledge payment id to a link if grouped by only pledge payment id.
   *
   * @param int|null|string $value
   *
   * @param array $row
   * @param string $selectedField
   *
   * @return string
   * @noinspection PhpUnused
   */
  protected function alterPledgePaymentLink($value, array &$row, string $selectedField): string {
    if ($this->_groupByArray !== ['civicrm_pledge_payment_id' => 'pledge_payment.id']
      && $this->_groupByArray !== ['civicrm_pledge_payment_id' => 'civicrm_pledge_payment.id']
    ) {
      CRM_Core_Session::setStatus(ts('Pledge payment link not added'), ts('The pledge payment link cannot be added if the grouping options on the report make it ambiguous'));
      return '';
    }
    if (empty($value) || !is_numeric($value)) {
      return $value;
    }
    $contactID = $row['civicrm_pledge_pledge_contact_id'] ?? CRM_Core_DAO::singleValueQuery(
        'SELECT contact_id FROM civicrm_pledge_payment pp
         LEFT JOIN civicrm_pledge p ON pp.pledge_id = p.id
         WHERE pp.id = ' . $value
      );
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
  protected function alterActivityType($value) {
    $activityTypes = CRM_Core_PseudoConstant::activityType(TRUE, TRUE, FALSE, 'label', TRUE);
    return CRM_Utils_Array::value($value, $activityTypes);
  }

  /**
   * @param $value
   *
   * @return mixed
   */
  protected function alterBatchStatus($value) {
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
  protected function alterBoolean($value) {
    $options = $this->getBooleanOptions();
    if (isset($options[$value])) {
      return $options[$value];
    }
    return $value;
  }

  /**
   * Return "Yes" if a contribution_recur_id exists, "No" otherwise.
   *
   * @param int|null $value
   *
   * @param $row
   * @param $selectedField
   *
   * @return string
   */
  protected function alterRecurringContributionId(?int $value, $row, $selectedField): string {
    $recurringIdField = str_replace('_exists', '', $selectedField);
    return $row[$recurringIdField] ? ts('Yes') : ts('No');
  }

  /**
   * @param $value
   * @param $row
   * @param $selectedField
   * @param $criteriaFieldName
   * @param $spec
   *
   * @return bool|null|string
   * @internal param $bao
   * @internal param $fieldName
   */
  protected function alterPseudoConstant($value, &$row, $selectedField, $criteriaFieldName, $spec) {
    return CRM_Core_PseudoConstant::getLabel($spec['bao'], $spec['name'], $value);
  }

  /**
   * @param $value
   *
   * @return bool|string|null
   */
  protected function alterWebsiteTypeId($value) {
    return CRM_Core_PseudoConstant::getLabel('CRM_Core_BAO_Website', 'website_type_id', $value);
  }

  /**
   * We are going to convert phones to an array
   *
   * @param $value
   *
   * @return string
   * @throws \CRM_Core_Exception
   */
  public function alterPhoneGroup($value): string {
    $locationTypes = $this->getLocationTypeOptions();
    $phoneTypes = $this->_getOptions('phone', 'phone_type_id');
    $phones = explode(',', ($value ?? ''));
    $return = [];
    $html = '<table>';
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

    if ($this->_outputMode === 'print') {
      return implode('<br>', $return);
    }

    $html .= "</table>";
    return $html;
  }

  /**
   * If in csv mode we will output line breaks
   *
   * @param string $value
   *
   * @return array|string|string[]|null
   */
  protected function alterDisplaycsvbr2nt($value) {
    if ($this->_outputMode === 'csv') {
      return preg_replace('/<br\\s*?\/??>/i', "\n", $value);
    }
    return $value;
  }

  /**
   * If in csv mode we will output line breaks in the table
   *
   * @param string $value
   *
   * @return array|string|string[]|null
   */
  function alterDisplaytable2csv($value) {
    if ($this->_outputMode === 'csv') {
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
  protected function alterDisplayAddress($value, array $row, $selectedField, string $criteriaFieldName): string {
    $address = [];
    $tablePrefix = str_replace('display_address', '', $selectedField);

    $format = Civi::settings()->get('address_format');
    $tokens = $this->extractTokens($format);

    foreach ($tokens as $token) {
      // ug token names not very standardised.
      $keyName = $tablePrefix . str_replace('address_', '', $token);
      $keyName = str_replace('supplemental_', 'supplemental_address_', $keyName);
      if (array_key_exists($keyName, $row)) {
        $address[$token] = $row[$keyName];
      }
      elseif (!empty($row[$keyName . '_id'])) {
        if ($token === 'country') {
          $address[$token] = CRM_Core_PseudoConstant::country($row[$keyName . '_id']);
        }
        elseif ($token === 'state_province') {
          $address[$token] = CRM_Core_PseudoConstant::stateProvince($row[$keyName . '_id']);
        }
        elseif ($token === 'county') {
          $address[$token] = CRM_Core_PseudoConstant::county($row[$keyName . '_id']);
        }
      }
    }
    $addressOutput = CRM_Utils_Address::format($address);
    if ($this->_outputMode !== 'csv') {
      return nl2br($addressOutput);
    }
    return $addressOutput;

  }

  /**
   * Regex the (contact) tokens out of a string.
   *
   * @param string $string
   *
   * @return array
   */
  protected function extractTokens(string $string): array {
    $tokens = [];
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
  protected function setTableAlias($table, $tableName): string {
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
   * @param string $table
   * @param int $count
   * @param string $prefix
   *
   * @throws \CRM_Core_Exception
   */
  protected function addFilterFieldsToReport(array $field, string $fieldName, string $table, int $count, string $prefix): void {
    $operations = CRM_Utils_Array::value('operations', $field);
    if (empty($operations)) {
      $operations = $this->getOperationPair(
        CRM_Utils_Array::value('operatorType', $field));
    }

    if ($fieldName === 'membership_owner_membership_id') {
      $operations = [
        '' => 'Both',
        'nll' => ts('Primary'),
        'nnll' => ts('Inherited'),
      ];
    }

    switch (CRM_Utils_Array::value('operatorType', $field)) {
      case CRM_Report_Form::OP_MONTH:
        if (!array_key_exists('options', $field) ||
          !is_array($field['options']) || empty($field['options'])
        ) {
          // If there's no option list for this filter, define one.
          $field['options'] = [
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
          ];
          // Add this option list to this column _columns. This is
          // required so that filter statistics show properly.
          $this->_columns[$table]['filters'][$fieldName]['options'] = $field['options'];
        }
      case CRM_Report_Form::OP_MULTISELECT:
      case CRM_Report_Form::OP_MULTISELECT_SEPARATOR:
        // assume a multi-select field
        if (!empty($field['options']) ||
          $fieldName === 'state_province_id' || $fieldName === 'county_id'
        ) {
          $element = $this->addElement('select', "$prefix{$fieldName}_op", ts('Operator:'), $operations);
          if (count($operations) <= 1) {
            $element->freeze();
          }
          if ($fieldName === 'state_province_id' ||
            $fieldName === 'county_id'
          ) {
            $this->addChainSelect($prefix . $fieldName . '_value', [
              'multiple' => TRUE,
              'label' => NULL,
              'class' => 'huge',
            ]);
          }
          else {
            $this->addElement('select', "$prefix{$fieldName}_value", NULL, $field['options'], [
              'style' => 'min-width:250px',
              'class' => 'crm-select2 huge',
              'multiple' => TRUE,
              'placeholder' => ts('- select -'),
            ]);
          }
        }
        break;

      case CRM_Report_Form::OP_SELECT:
        // assume a select field
        $this->addElement('select', "$prefix{$fieldName}_op", ts('Operator:'), $operations);
        if (!empty($field['options'])) {
          $this->addElement('select', "$prefix{$fieldName}_value", NULL, $field['options']);
        }
        break;

      case 256:
        $this->addElement('select', "$prefix{$fieldName}_op", ts('Operator:'), $operations);
        $this->setEntityRefDefaults($field, $table);
        $this->addEntityRef("$prefix{$fieldName}_value", NULL, $field['attributes']);
        break;

      case CRM_Report_Form::OP_DATE:
        // build datetime fields
        $this->addDatePickerRange($prefix . $fieldName, $field['title'], FALSE, FALSE, 'From', 'To', $operations, '_to', '_from');
        break;

      case CRM_Report_Form::OP_DATETIME:
        // build datetime fields
        $this->addDatePickerRange($prefix . $fieldName, $field['title'], TRUE, FALSE, 'From', 'To', $operations, '_to', '_from');
        break;
      case self::OP_SINGLEDATE:
        // build single datetime field
        $this->addElement('select', "$prefix{$fieldName}_op", ts('Operator:'), $operations);
        $this->add('datepicker', "$prefix{$fieldName}_value", ts(''), FALSE, FALSE, ['time' => FALSE]);
        break;
      case CRM_Report_Form::OP_INT:
      case CRM_Report_Form::OP_FLOAT:
        // and a min value input box
        $this->add('text', "$prefix{$fieldName}_min", ts('Min'));
        // and a max value input box
        $this->add('text', "$prefix{$fieldName}_max", ts('Max'));
      default:
        // default type is string
        $this->addElement('select', "$prefix{$fieldName}_op", ts('Operator:'), $operations,
          ['onchange' => "return showHideMaxMinVal( '" . $prefix . $fieldName . "', this.value );"]
        );
        // we need text box for value input
        $this->add('text', "$prefix{$fieldName}_value", NULL, ['class' => 'huge']);
        break;
    }
  }

  /**
   * Generate clause for the selected filter.
   *
   * @param array $field
   * @param string $fieldName
   *
   * @param string $prefix
   *
   * @return string Relevant where clause.
   * Relevant where clause.
   */
  protected function generateFilterClause($field, $fieldName, string $prefix = ''): string {
    $type = $field['type'];
    $clause = '';
    if ($type & CRM_Utils_Type::T_DATE) {
      if ($field['operatorType'] === CRM_Report_Form::OP_MONTH) {
        $op = CRM_Utils_Array::value("$prefix{$fieldName}_op", $this->_params);
        $value = CRM_Utils_Array::value("$prefix{$fieldName}_value", $this->_params);
        if (is_array($value) && !empty($value)) {
          $clause = "(month({$field['dbAlias']}) $op (" . implode(', ', $value) . '))';
        }
        return $clause;
      }

      $relative = $this->_params["$prefix{$fieldName}_relative"] ?? NULL;
      $from = $this->_params["$prefix{$fieldName}_from"] ?? NULL;
      $to = $this->_params["$prefix{$fieldName}_to"] ?? NULL;
      // next line is the changed one
      if (!empty($field['clause'])) {
        eval("\$clause = \"{$field['clause']}\";");
        $clauses[] = $clause;
        if (!empty($clauses)) {
          return implode(' AND ', $clauses);
        }
        return '';
      }
      return $this->dateClause($field['dbAlias'], $relative, $from, $to, $field['type']);
    }

    $op = CRM_Utils_Array::value("$prefix{$fieldName}_op", $this->_params);
    if ($op) {
      return $this->whereClause($field,
        $op,
        CRM_Utils_Array::value("$prefix{$fieldName}_value", $this->_params),
        CRM_Utils_Array::value("$prefix{$fieldName}_min", $this->_params),
        CRM_Utils_Array::value("$prefix{$fieldName}_max", $this->_params)
      );
    }
    return '';
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
  protected function getAggregateField(string $type): array {
    if (empty($this->_params['aggregate_' . $type . '_headers'])) {
      return [];
    }
    $columnHeader = $this->_params['aggregate_' . $type . '_headers'];

    $fieldSpec = [];
    if (array_key_exists($columnHeader, $this->getMetadataByType('aggregate_columns'))) {
      $fieldSpec = $this->getMetadataByType('aggregate_columns')[$columnHeader];
    }
    if ($columnHeader === 'contribution_total_amount_year' || $columnHeader === 'contribution_total_amount_month') {
      $fieldSpec = [
        'table_name' => 'civicrm_contribution',
        'name' => 'total_amount',
      ];
    }
    return [
      $fieldSpec['table_name'] => [$fieldSpec['name']],
    ];
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
   * ie field name => table alias
   */
  protected function getAggregateFieldSpec(string $type): array {
    if (empty($this->_params['aggregate_' . $type . '_headers'])) {
      return [];
    }
    $columnHeader = $this->_params['aggregate_' . $type . '_headers'];
    return [$this->getMetadataByType('metadata')[$columnHeader]];
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
  protected function getFieldBreakdownForAggregates($type):array {
    if (empty($this->_params['aggregate_' . $type . '_headers'])) {
      return [];
    }
    $columnHeader = $this->_params['aggregate_' . $type . '_headers'];
    $fieldArr = explode(':', $columnHeader);
    return [$fieldArr[1] => [$fieldArr[0]]];
  }

  /**
   * Add the fields to select the aggregate fields to the report.
   *
   * @throws \CRM_Core_Exception
   */
  protected function addAggregateSelectorsToForm(): void {
    if (!$this->isPivot) {
      return;
    }
    $aggregateColumnHeaderFields = $this->getAggregateColumnFields();
    $aggregateRowHeaderFields = $this->getAggregateRowFields();

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
          $this->_columns[$tableKey]['metadata'][$fieldName]['extends_table'] = $this->_columns[$tableKey]['extends_table'];
          $this->_columns[$tableKey]['filters'][$fieldName] = $this->_columns[$tableKey]['metadata'][$fieldName];
        }
        foreach ($this->_columns[$tableKey]['metadata'][$fieldName] as $fieldKey => $value) {
          $this->setMetadataValue($fieldName, $fieldKey, $value);
        }
        $this->setMetadataValue($fieldName, 'is_aggregate_columns', TRUE);
        $this->setMetadataValue($fieldName, 'table_alias', $this->_columns[$tableKey]['alias']);
        $this->metaData['aggregate_columns'][$fieldName] = $this->metaData['metadata'][$fieldName];
        $aggregateRowHeaderFields[$prefix . 'custom_' . $customField['id']] = $customField['prefix_label'] . $customField['label'];
        if (in_array($customField['html_type'], ['Select', 'CheckBox'])) {
          $aggregateColumnHeaderFields[$prefix . 'custom_' . $customField['id']] = $customField['prefix_label'] . $customField['label'];
        }
      }

    }
    $this->add('select', 'aggregate_column_headers', ts('Aggregate Report Column Headers'), $aggregateColumnHeaderFields, FALSE,
      ['id' => 'aggregate_column_headers', 'class' => 'crm-select2', 'title' => ts('- select -')]
    );
    $this->add('select', 'aggregate_row_headers', ts('Row Fields'), $aggregateRowHeaderFields, FALSE,
      ['id' => 'aggregate_row_headers', 'class' => 'crm-select2', 'title' => ts('- select -')]
    );
    $this->add('advcheckbox', 'delete_null', ts('Hide columns with zero count'));

    $this->_columns[$this->_baseTable]['fields']['include_null'] = [
      'title' => 'Show column for unknown',
      'pseudofield' => TRUE,
      'default' => TRUE,
    ];
    $this->tabs['Aggregate'] = [
      'title' => ts('Pivot table'),
      'tpl' => 'Aggregate',
      'div_label' => 'set-aggregate',
    ];

    $this->assignTabs();
  }

  /**
   * Get the name of the field selected for the pivot table row.
   *
   * @return string
   */
  protected function getPivotRowFieldName(): string {
    if (!empty($this->_params['aggregate_row_headers'])) {
      $aggregateField = explode(':', $this->_params['aggregate_row_headers']);
      return $aggregateField[1];
    }
    return '';
  }

  /**
   * Get the name of the field selected for the pivot table row.
   *
   * @return string
   */
  protected function getPivotRowTableAlias(): string {
    if (!empty($this->_params['aggregate_row_headers'])) {
      $aggregateField = explode(':', $this->_params['aggregate_row_headers']);
      return $aggregateField[0];
    }
    return '';
  }

  /**
   * @param $tableCol
   * @param array $row
   *
   * @return string
   */
  protected function getGroupByCriteria($tableCol, array $row): string {
    $otherGroupedFields = array_diff(array_keys($this->_groupByArray), [$tableCol]);
    $groupByCriteria = '';
    foreach ($otherGroupedFields as $field) {
      $fieldParts = explode('_', $field);
      // argh can't be bothered doing this properly right now.
      $tableName = $fieldParts[0] . '_' . $fieldParts[1];
      unset($fieldParts[0], $fieldParts[1]);
      if (!isset($this->_columns[$tableName])) {
        $tableName .= '_' . $fieldParts[2];
        unset($fieldParts[2]);
      }
      $presumedName = implode('_', $fieldParts);
      if (isset($this->_columns[$tableName]) && isset($this->_columns[$tableName]['metadata'][$presumedName])) {
        $value = $row["{$field}_raw"] ?? $row[$field];
        $groupByCriteria .= "&{$presumedName}_op=in&{$presumedName}_value=" . $value;
      }
    }

    return $groupByCriteria;
  }

  /**
   * @param array $options
   *
   * @return array
   */
  protected function getDefaultsFromOptions($options):array {
    return [
      'fields_defaults' => $options['fields_defaults'],
      'filters_defaults' => $options['filters_defaults'],
      'group_bys_defaults' => $options['group_bys_defaults'],
      'order_by_defaults' => $options['order_by_defaults'],
    ];
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
   * @return void
   */
  private function alterRowForRollup(&$row, $nextRow, &$groupBys, $rowNumber, $statLayers, $groupByLabels, $altered, $fieldsToUnSetForSubtotalLines): void {
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
   * @return void
   */
  protected function updateRollupRow(&$row, $fieldsToUnSetForSubtotalLines): void {
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
   * @param string $stat
   *
   * @return string
   */
  protected function getStatisticsSelectClause(array $field, string $stat): string {
    $statOp = $this->getStatOp($stat);
    switch (strtolower($stat)) {
      case 'max':
      case 'sum':
      case 'cumulative':
      case 'count':
      case 'display':
        return "$statOp({$field['dbAlias']})";

      case 'count_distinct':
        return "COUNT(DISTINCT {$field['dbAlias']})";

      case 'avg':
        return "ROUND(AVG({$field['dbAlias']}),2)";
    }
    return '';
  }

  /**
   * Get op string for a stat.
   *
   * @param $stat
   *
   * @return string
   */
  public function getStatOp($stat): string {
    switch (strtolower($stat)) {
      case 'max':
        return 'MAX';

      case 'sum':
      case 'cumulative':
        return 'SUM';

      case 'count':
        return "COUNT";

      case 'display':
      default:
        return '';
    }
  }

  /**
   * Get SQL operator from form text version.
   *
   * @param string $operator
   *
   * @return string
   */
  public function getSQLOperator($operator = 'like'): string {
    if ($operator === 'rlike') {
      return 'RLIKE';
    }
    return parent::getSQLOperator($operator);
  }

  /**
   * @return bool
   */
  protected function isInProcessOfPreconstraining(): bool {
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
   * @throws \CRM_Core_Exception
   */
  protected function getContactIdFilter(): ?int {
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
   * @param array $table
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getMetadataForFields(array $table): array {
    // higher preference to bao object
    $daoOrBaoName = CRM_Utils_Array::value('bao', $table, CRM_Utils_Array::value('dao', $table));
    if (!$daoOrBaoName) {
      return [];
    }

    $entity = CRM_Core_DAO_AllCoreTables::getEntityNameForClass(str_replace('BAO', 'DAO', $daoOrBaoName));
    if ($entity) {
      $expFields = civicrm_api3($entity, 'getfields', [])['values'];
    }


    if (empty($expFields)) {
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
        $expFields[$fieldName]['operatorType'] = $this->getOperatorType($field['type']);
      }
      // Double index any unique fields to ensure we find a match
      // later on. For example the metadata keys
      // contribution_campaign_id rather than campaign_id
      // this is not super predictable so we ensure that they are keyed by
      // both possibilities
      if (!empty($field['name']) && $field['name'] !== $fieldName) {
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
  protected function ensureBaoIsSetIfPossible(): void {
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
  public function storeWhereHavingClauseArray(): void {
    $filters = $this->getSelectedFilters();
    foreach ($filters as $filterName => $field) {
      if (!empty($field['pseudofield'])) {
        continue;
      }

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
  protected function addAdditionalRequiredFields(array $specs, string $table): void {
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
   *
   * @return array
   */
  protected function getContactFilterFieldOptions(): array {
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
  protected function getContactFilterFields(): array {
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
  protected function getSelectedFields(): array {
    $fields = $this->getMetadataByType('fields');
    $selectedFields = array_intersect_key($fields, $this->getConfiguredFieldsFlatArray());
    $requiredFields = array_merge($this->getFieldsRequiredToSupportHavingFilters(), $this->getExtraFieldsRequiredForSelectedFields($selectedFields));

    foreach (array_diff_key($requiredFields, $selectedFields) as $fieldKey => $fieldSpec) {
      $selectedFields[$fieldKey] = $fieldSpec;
      $selectedFields[$fieldKey]['no_display'] = TRUE;
      $this->setFieldAsNoDisplay($fieldKey);
    }

    $orderBys = $this->getSelectedOrderBys();
    foreach ($fields as $fieldName => $field) {
      if (!empty($field['required']) || !empty($field['required_sql'])) {
        $selectedFields[$fieldName] = $field;
      }

      // The field is being selected for select & order by. Reconcile any fallbacks.
      // Perhaps later we should find a way to support them not being the same but for now....
      if (isset($orderBys[$fieldName], $selectedFields[$fieldName]) && CRM_Utils_Array::value('field_on_null', $orderBys[$fieldName], [])
        !== CRM_Utils_Array::value('field_on_null', $selectedFields[$fieldName], [])) {
        CRM_Core_Session::setStatus(E::ts('Selected field fallback altered to match order by fallback. Currently different configurations are not supported if both are selected'));
        $selectedFields[$fieldName]['field_on_null'] = CRM_Utils_Array::value('field_on_null', $orderBys[$fieldName], []);
        if (!empty($this->_formValues['extended_fields'])) {
          // If we have fields stored this way then add this one in.
          $this->_formValues['extended_fields'][$fieldName] = ['name' => $fieldName, 'title' => $field['title']];
        }
      }

    }
    return $selectedFields;
  }

  /**
   * @return array
   */
  protected function getSelectedGroupBys(): array {
    $groupBys = $this->getMetadataByType('group_bys');
    return array_intersect_key($groupBys, CRM_Utils_Array::value('group_bys', $this->_params, []));
  }

  /**
   * @return array
   */
  protected function getSelectedOrderBys(): array {
    $orderBys = $this->getMetadataByType('order_bys');
    $result = [];
    foreach ($this->_params['order_bys'] as $order_by) {
      if (isset($orderBys[$order_by['column']])) {
        $order_by = array_merge(
          $order_by, $orderBys[$order_by['column']]
        );
        if (!isset($order_by['order'])) {
          $order_by['order'] = 'ASC';
        }
        $result[$order_by['column']] = $order_by;
      }
    }
    return $result;
  }

  /**
   * Get any order bys that are not already in the selected fields.
   *
   * @return array
   */
  public function getOrderBysNotInSelectedFields(): array {
    return array_diff_key($this->getSelectedOrderBys(), $this->getSelectedFields());
  }

  /**
   * @return array
   */
  protected function getSelectedFilters(): array {
    $selectedFilters = [];
    $filters = $this->getMetadataByType('filters');
    foreach ($this->_params as $key => $value) {
      $field = '';
      if ($key === 'membership_owner_membership_id_op') {
        $key = 'membership_owner_membership_id_value';
      }
      if (substr($key, -6, 6) === '_value' && ($value !== '' && $value !== NULL && $value !== "NULL" && $value !== [])) {
        $field = substr($key, 0, -6);
      }
      if (substr($key, -3, 3) === '_op' && in_array($value, ['nll', 'nnll'], TRUE)) {
        $field = substr($key, 0, -3);
      }
      $validSuffixes = ['relative', 'from', 'to', 'max', 'min'];
      foreach ($validSuffixes as $suffix) {
        $suffixLength = strlen($suffix) + 1;
        if (substr($key, -$suffixLength, $suffixLength) === '_' . $suffix && (!empty($value) || is_numeric($value))) {
          $field = substr($key, 0, strlen($key) - $suffixLength);
        }
      }
      if (!empty($field)) {
        $selectedFilters[$field] = $field;
      }

    }
    return array_intersect_key($filters, array_flip($selectedFilters));
  }

  /**
   * Get any selected join filters.
   *
   * @return array
   */
  protected function getSelectedJoinFilters(): array {
    $selectedFilters = [];
    $filters = $this->getMetadataByType('join_filters');
    foreach ($this->_params as $key => $value) {
      if (strpos($key, 'join_filter') === 0) {
        if (substr($key, -6, 6) === '_value' && ($value !== '' && $value !== NULL && $value !== "NULL" && $value !== [])) {
          $selectedFilters[] = str_replace('join_filter_', '', substr($key, 0, -6));
        }
        if (substr($key, -9, 9) === '_relative' && !empty($value)) {
          $selectedFilters[] = str_replace('join_filter_', '', substr($key, 0, -9));
        }
      }
    }
    return array_intersect_key($filters, array_flip($selectedFilters));
  }

  /**
   * @return array
   */
  protected function getSelectedHavings(): array {
    $havings = $this->getMetadataByType('having');
    $selectedHavings = [];
    foreach ($havings as $field => $spec) {
      $fieldValue = $field . '_value';
      if (isset($this->_params[$fieldValue]) && (
          $this->_params[$fieldValue] === 0 || !empty($this->_params[$fieldValue])
        )) {
        $selectedHavings[$field] = $spec;
      }
    }
    return $selectedHavings;
  }

  /**
   * @return array
   */
  protected function getSelectedAggregateColumns(): array {
    $metadata = $this->getMetadataByType('aggregate_columns');
    if (empty($this->_params['aggregate_column_headers']) || !isset($metadata[$this->_params['aggregate_column_headers']])) {
      return [];
    }
    return [$this->_params['aggregate_column_headers'] => $metadata[$this->_params['aggregate_column_headers']]];
  }

  /**
   * @return array
   */
  protected function getSelectedAggregateRows(): array {
    $metadata = $this->getMetadataByType('metadata');
    if (empty($this->_params['aggregate_row_headers']) || !isset($metadata[$this->_params['aggregate_row_headers']])) {
      return [];
    }
    return [$this->_params['aggregate_row_headers'] => $metadata[$this->_params['aggregate_row_headers']]];
  }

  /**
   * @param array $field
   * @param string $currentTable
   * @param string $prefix
   * @param string $prefixLabel
   */
  protected function addCustomDataTable(array $field, string $currentTable, string $prefix, string $prefixLabel): void {
    $tableKey = $prefix . $currentTable;

    $fieldName = 'custom_' . ($prefix ? $prefix . '_' : '') . $field['id'];

    $field['is_filters'] = $this->_customGroupFilters;
    $field['is_join_filters'] = $this->_customGroupFilters;
    $field['is_group_bys'] = $this->_customGroupGroupBy;
    $field['is_order_bys'] = $this->_customGroupOrderBy;
    $field['is_aggregate_columns'] = ($this->isPivot && !empty($field['options']));
    if ($this->_customGroupFilters) {
      $field = $this->addCustomDataFilters($field, $fieldName);
    }
    $this->appendFieldToMetadata($field, $tableKey, $fieldName);

    // Add totals field
    $this->appendFieldToMetadata(array_merge(
      $field, [
        'title' => "$prefixLabel{$field['label']} Count of Selected",
        'statistics' => ['count' => "$prefixLabel{$field['label']} Count of Selected"],
        'is_group_bys' => FALSE,
        'is_order_bys' => FALSE,
        'is_filters' => FALSE,
        'is_join_filters' => FALSE,
      ]
    ), $tableKey, $fieldName . '_qty');

    if ($field['type'] === CRM_Utils_Type::T_INT) {
      $this->appendFieldToMetadata(array_merge(
        $field, [
          'title' => "$prefixLabel{$field['label']} Selected Quantity",
          'statistics' => ['sum' => "$prefixLabel{$field['label']} Selected Quantity"],
          'is_group_bys' => FALSE,
          'is_order_bys' => FALSE,
          'is_filters' => FALSE,
          'is_join_filters' => FALSE,
        ]
      ), $tableKey, $fieldName . '_sum');
    }
  }

  /**
   * @param array $field
   * @param string $fieldName
   *
   * @return array
   */
  protected function addCustomDataFilters(array $field, string $fieldName): array {

    switch ($field['data_type']) {

      case 'StateProvince':
        if ($field['html_type'] === 'Multi-Select State/Province'
        ) {
          $field['operatorType'] = CRM_Report_Form::OP_MULTISELECT_SEPARATOR;
        }
        else {
          $field['operatorType'] = CRM_Report_Form::OP_MULTISELECT;
        }
        $field['options'] = CRM_Core_PseudoConstant::stateProvince();
        break;

      case 'Country':
        if ($field['html_type'] === 'Multi-Select Country'
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
        $field['dbAlias'] = "contact_{$fieldName}_civireport.display_name";
        break;
    }
    return $field;
  }

  /**
   * @param array $field
   *
   * @return int
   */
  protected function getFieldType(array $field): int {
    switch ($field['data_type']) {
      case 'Date':
        if ($field['time_format']) {
          return CRM_Utils_Type::T_TIMESTAMP;
        }
        return CRM_Utils_Type::T_DATE;

      case 'Boolean':
        return CRM_Utils_Type::T_BOOLEAN;

      case 'Int':
        return CRM_Utils_Type::T_INT;

      case 'Money':
        return CRM_Utils_Type::T_MONEY;

      case 'Float':
        return CRM_Utils_Type::T_FLOAT;

      case 'String':
      case 'StateProvince':
      case 'Country':
      case 'ContactReference':
      default:
        return CRM_Utils_Type::T_STRING;

    }
  }

  /**
   * @param array $extends
   *
   * @throws \CRM_Core_Exception
   */
  protected function addCustomDataForEntities(array $extends): void {
    $fields = $this->getCustomDataDAOs($extends);
    foreach ($fields as $field) {
      $prefixLabel = trim(CRM_Utils_Array::value('prefix_label', $field));
      $prefix = trim(CRM_Utils_Array::value('prefix', $field));
      if ($prefixLabel) {
        $prefixLabel .= ' ';
      }
      $field = $this->getCustomFieldMetadata($field, $prefixLabel, $prefix);
      $this->addCustomTableToColumns($field, $field['table_name'], $prefix, $prefixLabel, $prefix . $field['table_name']);
      $field['extends_table'] = $this->_columns[$prefix . $field['table_name']]['extends_table'];
      $this->addCustomDataTable($field, $field['table_name'], $prefix, $prefixLabel);
    }
  }

  /**
   * @param array $extends
   *
   * @return array
   */
  protected function getCustomDataDAOs(array $extends): array {
    $extendsKey = implode(',', $extends);
    if (isset($this->customDataDAOs[$extendsKey])) {
      return $this->customDataDAOs[$extendsKey];
    }
    $customGroupWhere = '';
    if (!$this->userHasAllCustomGroupAccess()) {
      $permissionedCustomGroupIDs = CRM_ACL_API::group(CRM_Core_Permission::VIEW, NULL, 'civicrm_custom_group');
      if (empty($permissionedCustomGroupIDs)) {
        return [];
      }
      $customGroupWhere = 'cg.id IN (' . implode(',', $permissionedCustomGroupIDs) . ') AND';
    }
    $extendsMap = [];
    $extendsEntities = array_fill_keys($extends, TRUE);
    foreach (array_keys($extendsEntities) as $extendsEntity) {
      if (in_array($extendsEntity, [
        'Individual',
        'Household',
        'Organization',
      ])) {
        $extendsEntities['Contact'] = TRUE;
        unset($extendsEntities[$extendsEntity]);
      }
    }
    foreach ($this->_columns as $spec) {
      $entityName = (isset($spec['bao']) ? CRM_Core_DAO_AllCoreTables::getEntityNameForClass(str_replace('BAO', 'DAO', $spec['bao'])) : '');
      if ($entityName && !empty($extendsEntities[$entityName])) {
        $extendsMap[$entityName][$spec['prefix']] = $spec['prefix_label'];
      }
    }
    $extendsString = implode("','", $extends);
    $sql = "
SELECT cg.table_name, cg.title, cg.extends, cf.id as cf_id, cf.label,
       cf.column_name, cf.data_type, cf.html_type, cf.option_group_id,
       cf.time_format, cf.serialize
FROM   civicrm_custom_group cg
INNER  JOIN civicrm_custom_field cf ON cg.id = cf.custom_group_id
WHERE cg.extends IN ('" . $extendsString . "') AND
  $customGroupWhere
  cg.is_active = 1 AND
  cf.is_active = 1 AND
  cf.is_searchable = 1
  ORDER BY cg.weight, cf.weight";
    $customDAO = CRM_Core_DAO::executeQuery($sql);

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
          'table_key' => $prefix . $customDAO->table_name,
          'prefix_label' => $label,
          'table_name' => $customDAO->table_name,
          'serialize' => $customDAO->serialize,
        ];
        $fields[$prefix . $customDAO->column_name]['type'] = $this->getFieldType($fields[$prefix . $customDAO->column_name]);
      }
    }
    $this->customDataDAOs[$extendsKey] = $fields;
    return $fields;
  }

  /**
   * @param array $field
   * @param string $currentTable
   * @param $prefix
   * @param $prefixLabel
   * @param $tableKey
   */
  protected function addCustomTableToColumns(array $field, string $currentTable, $prefix, $prefixLabel, $tableKey): void {
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
      $this->_columns[$tableKey]['extends_table'] = $prefix . CRM_Core_DAO_AllCoreTables::getTableForClass(CRM_Core_DAO_AllCoreTables::getDAONameForEntity($entity));
    }
  }

  /**
   * @param array $field
   * @param string $prefixLabel
   * @param string $prefix
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getCustomFieldMetadata(array $field, string $prefixLabel, string $prefix = ''): array {
    $field = array_merge($field, [
      'name' => $field['column_name'],
      'title' => $prefixLabel . $field['label'],
      'dataType' => $field['data_type'],
      'htmlType' => $field['html_type'],
      'operatorType' => $this->getOperatorType($this->getFieldType($field)),
      'is_fields' => TRUE,
      'is_filters' => TRUE,
      'is_group_bys' => FALSE,
      'is_order_bys' => FALSE,
      'is_join_filters' => FALSE,
      'type' => $this->getFieldType($field),
      'dbAlias' => $prefix . $field['table_name'] . '.' . $field['column_name'],
      'alias' => $prefix . $field['table_name'] . '_' . 'custom_' . $field['id'],
    ]);
    $field['is_aggregate_columns'] = in_array($field['html_type'], ['Select', 'Radio']);

    if (!empty($field['option_group_id'])) {
      $field['operatorType'] = CRM_Core_BAO_CustomField::isSerialized($field) ? CRM_Report_Form::OP_MULTISELECT_SEPARATOR : CRM_Report_Form::OP_MULTISELECT;
      $field['options'] = civicrm_api3($field['extends'], 'getoptions', ['field' => 'custom_' . $field['id']])['values'];
    }

    if ($field['type'] === CRM_Utils_Type::T_BOOLEAN) {
      $field['options'] = [
        '' => ts('- select -'),
        1 => ts('Yes'),
        0 => ts('No'),
      ];
    }
    return $field;
  }

  /**
   * @param array $spec
   *
   * @return array
   *
   * @throws CRM_Core_Exception
   */
  protected function getCustomFieldOptions(array $spec): array {
    $options = [];
    if (!empty($spec['options'])) {
      return $spec['options'];
    }

    // Data type is set for custom fields but not core fields.
    if (($spec['data_type'] ?? '') === 'Boolean') {
      $options = [
        'values' => [
          0 => ['label' => E::ts('No'), 'value' => 0],
          1 => ['label' => E::ts('Yes'), 'value' => 1],
        ],
      ];
    }
    elseif (!empty($spec['options'])) {
      foreach ($spec['options'] as $option => $label) {
        $options['values'][$option] = [
          'label' => $label,
          'value' => $option,
        ];
      }
    }
    else {
      if (empty($spec['option_group_id'])) {
        throw new CRM_Core_Exception('currently column headers need to be radio or select');
      }
      $options = civicrm_api('option_value', 'get', [
        'version' => 3,
        'options' => ['limit' => 50,],
        'option_group_id' => $spec['option_group_id'],
      ]);
    }
    return $options['values'];
  }

  /**
   * @param string $identifier
   * @param string $columns - eg '`contact_id` INT(10) NOT NULL, `name` varchar(255) NULL'
   *
   * @return string
   */
  protected function createTemporaryTableFromColumns(string $identifier, string $columns): string {
    $isNotTrueTemporary = !$this->_temporary;
    $tmpTableName = CRM_Utils_SQL_TempTable::build()
      ->setId($identifier)
      ->createWithColumns($columns)
      ->getName();

    $this->temporaryTables[$identifier] = [
      'temporary' => !$isNotTrueTemporary,
      'name' => $tmpTableName,
    ];

    $sql = 'CREATE ' . ($isNotTrueTemporary ? '' : 'TEMPORARY ') . "TABLE $tmpTableName $columns  DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ";
    $this->addToDeveloperTab($sql);
    return $tmpTableName;
  }

  /**
   * @param string $type
   *
   * @return int
   *
   */
  protected function getOperatorType($type): int {
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
  }

  /**
   * @param $field
   * @param $fieldName
   * @param string $prefix
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getQillForField($field, $fieldName, string $prefix = ''): array {
    if ((CRM_Utils_Array::value('type', $field) & CRM_Utils_Type::T_DATE ||
        CRM_Utils_Array::value('type', $field) & CRM_Utils_Type::T_TIME) &&
      CRM_Utils_Array::value('operatorType', $field) !=
      CRM_Report_Form::OP_MONTH
    ) {

      $from = $this->_params["$prefix{$fieldName}_from"] ?? NULL;
      $to = $this->_params["$prefix{$fieldName}_to"] ?? NULL;
      if (!empty($this->_params["$prefix{$fieldName}_relative"])) {
        [$from, $to] = CRM_Utils_Date::getFromTo($this->_params["$prefix{$fieldName}_relative"]);
      }
      if (strlen($to) === 10) {
        // If we just have the date we assume the end of that day.
        $to .= ' 23:59:59';
      }

      if ($from || $to) {
        if ($from) {
          $from = date('l j F Y, g:iA', strtotime($from));
        }
        if ($to) {
          $to = date('l j F Y, g:iA', strtotime($to));
        }
        return [
          'title' => $field['title'],
          'value' => ts("Between %1 and %2", [1 => $from, 2 => $to]),
        ];
      }

      if (in_array($rel = CRM_Utils_Array::value("$prefix{$fieldName}_relative", $this->_params),
        array_keys($this->getOperationPair(CRM_Report_Form::OP_DATE))
      )) {
        $pair = $this->getOperationPair(CRM_Report_Form::OP_DATE);
        return [
          'title' => $field['title'],
          'value' => $pair[$rel],
        ];
      }
    }
    else {
      $op = CRM_Utils_Array::value("$prefix{$fieldName}_op", $this->_params);
      $value = NULL;
      if ($op) {
        $pair = $this->getOperationPair(
          CRM_Utils_Array::value('operatorType', $field),
          $fieldName
        );
        $min = CRM_Utils_Array::value("$prefix{$fieldName}_min", $this->_params);
        $max = CRM_Utils_Array::value("$prefix{$fieldName}_max", $this->_params);
        $val = CRM_Utils_Array::value("$prefix{$fieldName}_value", $this->_params);
        if (in_array($op, ['bw', 'nbw']) && ($min || $max)) {
          $value = "$pair[$op] $min " . ts('and') . " $max";
        }
        elseif ($val && CRM_Utils_Array::value('operatorType', $field) & self::OP_ENTITYREF) {
          $this->setEntityRefDefaults($field, $field['table_name']);
          $result = civicrm_api3($field['attributes']['entity'], 'getlist',
            ['id' => $val] +
            CRM_Utils_Array::value('api', $field['attributes'], []));
          $values = [];
          foreach ($result['values'] as $v) {
            $values[] = $v['label'];
          }
          $value = "$pair[$op] " . implode(', ', $values);
        }
        elseif ($op === 'nll' || $op === 'nnll') {
          $value = $pair[$op];
        }
        elseif (is_array($val) && (!empty($val))) {
          $options = CRM_Utils_Array::value('options', $field, []);
          foreach ($val as $key => $valIds) {
            if (isset($options[$valIds])) {
              $val[$key] = $options[$valIds];
            }
          }
          $pair[$op] = (count($val) === 1) ? (($op === 'notin' || $op ===
            'mnot') ? ts('Is Not') : ts('Is')) : CRM_Utils_Array::value($op, $pair);
          $val = implode(', ', $val);
          $value = "$pair[$op] " . $val;
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
        return [
          'title' => CRM_Utils_Array::value('title', $field),
          'value' => $value,
        ];
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
  protected function isValidTitle(string $string):bool {
    return CRM_Utils_Rule::alphanumeric(str_replace(' ', '', $string));
  }

  /**
   * Get the configured fields as a flat array
   *
   * This is in the same format as 'fields' so is easy to compare.
   *
   * @return array
   */
  protected function getConfiguredFieldsFlatArray():array {
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
  protected function mergeExtendedConfigurationIntoReportData(): void {
    $this->mergeExtendedFieldConfiguration();
    $this->mergeExtendedOrderByConfiguration();
  }

  /**
   * Merge 'extended_order_bys' into fields
   */
  protected function mergeExtendedOrderByConfiguration(): void {
    $orderBys = CRM_Utils_Array::value('order_bys', $this->_formValues);
    $extendedOrderBys = CRM_Utils_Array::value('extended_order_bys', $this->_formValues);
    $metadata = $this->getMetadataByType('order_bys');
    $count = 1;
    if (!empty($extendedOrderBys)) {
      foreach ($extendedOrderBys as $extendedOrderBy) {
        $extendedOrderBy['column'] = $extendedOrderBy['name'] = CRM_Utils_Array::value('name', $extendedOrderBy, CRM_Utils_Array::value('column', $extendedOrderBy));
        $extendedOrderBy['title'] = CRM_Utils_Array::value('title', $extendedOrderBy, $metadata[$extendedOrderBy['name']]['title']);
        $orderBys[$count] = [
          'column' => CRM_Utils_Array::value('name', $extendedOrderBy, $extendedOrderBy['column']),
        ];
        $count++;
        if ($this->isValidTitle($extendedOrderBy['title'])) {
          $this->metaData['order_bys'][$extendedOrderBy['name']]['title'] = $extendedOrderBy['title'];
        }
        if (!empty($extendedOrderBy['field_on_null'])) {
          $nullString = [];
          foreach ($extendedOrderBy['field_on_null'] as $fallbackField) {
            if ($this->isValidTitle($fallbackField['title'])) {
              $nullString[] = $fallbackField['title'];
            }
          }
          $this->metaData['order_bys'][$extendedOrderBy['name']]['title'] .= '(' . implode(', ', $nullString) . ')';
          $this->metaData['order_bys'][$extendedOrderBy['name']]['field_on_null'] = $extendedOrderBy['field_on_null'];
          $this->metaData['order_bys'][$extendedOrderBy['name']]['field_on_null_usage'] = CRM_Utils_Array::value('field_on_null_usage', $extendedOrderBy, 'on_null');
        }
      }
    }
    $this->_formValues['order_bys'] = $orderBys;
  }

  /**
   * Merge 'extended_fields' into fields
   */
  protected function mergeExtendedFieldConfiguration(): void {
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
          $this->metaData['fields'][$fieldName]['field_on_null_usage'] = CRM_Utils_Array::value('field_on_null_usage', $configuredExtendedField, 'on_null');

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
    return $this->_formValues['extended_fields'] ?? ($this->_params['extended_fields'] ?? []);
  }

  /**
   * Get configured order by options by combining extended options with normal options.
   *
   * In this scenario we are in qf configuration mode so that config wins out where there is
   * conflict - but extended config is merged in.
   *
   * @param array $params
   *
   * @return array
   */
  protected function getConfiguredOrderBys(array $params): array {
    $orderBys = [];
    $quickFormOrderBys = isset($params['order_bys']) ? (array) $params['order_bys'] : [];
    foreach ($quickFormOrderBys as $index => $quickFormOrderBy) {
      if ($quickFormOrderBy === ['column' => '-']) {
        unset($params['order_bys'][$index], $quickFormOrderBys['index']);
        continue;
      }
      $orderBys[$quickFormOrderBy['column']] = $quickFormOrderBy;
      $orderBys[$quickFormOrderBy['column']]['title'] = $this->getMetadataByType('order_bys')[$quickFormOrderBy['column']]['title'];
      $orderBys[$quickFormOrderBy['column']]['name'] = $quickFormOrderBy['column'];
    }
    $extendedOrderBys = $this->getExtendedOrderBysSelection($params);
    foreach ($extendedOrderBys as $index => $extendedOrderBy) {
      $orderByName = CRM_Utils_Array::value('name', $extendedOrderBy, CRM_Utils_Array::value('column', $extendedOrderBy));
      // If order_bys have been passed in then we filter out anything not set in them
      if (!isset($orderBys[$orderByName]) && isset($params['order_bys'])) {
        unset($extendedOrderBys[$index]);
      }
      else {
        $orderBys[$orderByName] = array_merge($extendedOrderBy, CRM_Utils_Array::value($orderByName, $orderBys, []));
      }
    }

    $reindexedArray = [];
    $count = 1;
    foreach ($orderBys as $orderBy) {
      $reindexedArray[$count] = $orderBy;
      $count++;
    }
    return $reindexedArray;
  }

  /**
   * Get configured extended order by fields.
   *
   * @param array $params
   *
   * @return array
   */
  protected function getExtendedOrderBysSelection(array $params): array {
    return CRM_Utils_Array::value('extended_order_bys', $params, CRM_Utils_Array::value('extended_order_bys', $this->_formValues, CRM_Utils_Array::value('extended_order_bys', $this->_params, [])));
  }

  /**
   * @param array $field
   * @param string $alias
   *
   * @return string
   */
  protected function getBasicFieldSelectClause(array $field, string $alias): string {
    $fieldString = $field['dbAlias'];
    if (!empty($field['field_on_null'])) {
      $fallbacks = [];
      foreach ($field['field_on_null'] as $fallback) {
        $fallbacks[] = $this->getMetadataByType('fields')[$fallback['name']]['dbAlias'];
      }
      if (CRM_Utils_Array::value('field_on_null_usage', $field, 'on_null') === 'on_null') {
        $fieldString = 'COALESCE(' . $fieldString . ',' . implode(',', $fallbacks) . ')';
      }
      else {
        $fieldString = 'COALESCE(NULLIF(' . $fieldString . ', ""),' . implode(',', $fallbacks) . ')';
      }
    }
    if ($this->isGroupByMode() && (empty($field['statistics']) || in_array('GROUP_CONCAT', $field['statistics'], TRUE))) {

      if (empty($this->_groupByArray[$alias])) {
        return "GROUP_CONCAT(DISTINCT ($fieldString))";
      }
      return "($fieldString) ";
    }
    return "$fieldString ";
  }

  /**
   * Get the aias for a stat field.
   *
   * @param string $tableName
   * @param string $fieldName
   * @param string $stat
   *
   * @return string
   */
  protected function getStatisticsAlias(string $tableName, string $fieldName, string $stat): string {
    if ($stat === 'cumulative') {
      $stat = 'sum';
    }
    return "{$tableName}_{$fieldName}_$stat";
  }

  /**
   * Get array of statistics to display if appropriate.
   *
   * @param array $field
   *
   * @return array
   */
  protected function getFieldStatistics(array $field): array {
    return empty($this->_groupByArray) ? [] : CRM_Utils_Array::value('statistics', $field, []);
  }

  /**
   * Is the report in group by mode - either by being forced or by group by conditions being present.
   *
   * @return bool
   */
  protected function isGroupByMode(): bool {
    return (!empty($this->_groupByArray) || $this->isForceGroupBy);
  }

  /**
   * Get fields used to provide column headers on a pivot report.
   *
   * @return array
   */
  protected function getAggregateColumnFields(): array {
    $fields = $this->getMetadataByType('aggregate_columns');
    $aggregateColumns = ['' => ts('--Select--')];
    foreach ($fields as $key => $spec) {
      $tooCrazyManyOptionsForHeaders = ['county_id', 'country_id', 'state_province_id'];
      if (in_array($spec['name'], $tooCrazyManyOptionsForHeaders)) {
        continue;
      }
      $aggregateColumns[$key] = $spec['title'];
    }

    if ($this->_attributes['name'] === 'ContributionPivot') {
      $breakDownByMonth = [
        'contribution_total_amount_month' => E::ts('Breakdown By Month'),
        'contribution_total_amount_year' => E::ts('Breakdown By Year'),
      ];
      $aggregateColumns = $aggregateColumns + $breakDownByMonth;
    }
    return $aggregateColumns;
  }

  /**
   * Get fields used to provide column headers on a pivot report.
   *
   * @return array
   */
  protected function getAggregateRowFields(): array {
    // what fields are suitable. Columns need some limits on variants or
    // else it gets REALLLLLYYYYY WIDE - but for rows not sure what limits if
    // any there should be
    $fields = $this->getMetadataByType('metadata');
    $aggregateRows = ['' => ts('--Select--')];
    foreach ($fields as $key => $spec) {
      $aggregateRows[$key] = $spec['title'];
    }
    return $aggregateRows;
  }

  /**
   * Get metadata for any fields we need to add to select to support having filters.
   *
   * @return array
   */
  protected function getFieldsRequiredToSupportHavingFilters(): array {
    $filters = $this->getSelectedFilters();
    $fieldsRequireToSupportAggregateFilters = [];
    foreach ($filters as $filter) {
      if (!empty($filter['is_aggregate_field_for'])) {
        $fieldsRequireToSupportAggregateFilters[$filter['is_aggregate_field_for']] = $this->getMetadataByType('fields')[$filter['is_aggregate_field_for']];
      }
    }
    return $fieldsRequireToSupportAggregateFilters;
  }

  /**
   * If any fields have the "requires" element, return an array of additional fields required in the SELECT clause.
   * @param $selectedFields
   *
   * @return array
   */
  protected function getExtraFieldsRequiredForSelectedFields($selectedFields): array {
    $fields = $this->getMetadataByType('fields');
    $requiredFields = [];
    foreach ($selectedFields as $selectedField) {
      if (!empty($selectedField['requires'])) {
        $requiredFields = array_merge($requiredFields, array_intersect_key($fields, array_fill_keys($selectedField['requires'], 1)));
      }
    }
    return $requiredFields;
  }

  /**
   * @param $fieldKey
   */
  protected function setFieldAsNoDisplay($fieldKey): void {
    $this->metaData['fields'][$fieldKey]['no_display'] = TRUE;
  }

  /**
   * @param $field
   * @param $alias
   */
  protected function addFieldToColumnHeaders($field, $alias): void {
    if (!isset($field['no_display'])) {
      $this->_columnHeaders[$alias]['title'] = $field['title'];
      $this->_columnHeaders[$alias]['type'] = $field['type'];
    }
  }

  /**
   * Add group by statistics.
   *
   * @param array $statistics
   */
  public function groupByStat(&$statistics): void {
    $combinations = [];
    foreach ($this->getSelectedGroupBys() as $field) {
      $combinations[] = $field['title'];
    }
    $statistics['groups'][] = [
      'title' => ts('Grouping(s)'),
      'value' => implode(' & ', $combinations),
    ];
  }

  /**
   * Fetch array of DAO tables having columns included in SELECT or ORDER BY clause.
   *
   * If the array is unset it will be built.
   *
   * @return array
   *   selectedTables
   */
  public function selectedTables(): array {
    if (!$this->_selectedTables) {
      foreach ($this->getAllUsedFields() as $fieldSpec) {
        $this->_selectedTables[$fieldSpec['table_key'] ?? $fieldSpec['table_name']] = $fieldSpec['table_key'] ?? $fieldSpec['table_name'];
        if (!empty($fieldSpec['extends_table'])) {
          $this->_selectedTables[$fieldSpec['extends_table']] = $fieldSpec['extends_table'];
        }
      }
    }
    return array_merge($this->_selectedTables, $this->aclTables);
  }


  /**
   * Build the permision clause for all entities in this report.
   *
   * Override this as it does not support table name prefixing & fails to determine the BAO.
   */
  public function buildPermissionClause(): void {
    $ret = [];
    foreach ($this->selectedTables() as $tableName) {
      $baoName = str_replace('_DAO_', '_BAO_', $this->_columns[$tableName]['bao'] ?? '');
      if ($baoName
        // This clause seems pretty tricksy & expensive for likely no value. Let's looks at in core.
        && $baoName !== 'CRM_Core_BAO_EntityTag'
        && !empty($this->_columns[$tableName]['alias'])
        && class_exists($baoName)) {

        $tableAlias = $this->_columns[$tableName]['alias'];
        $clauses = array_filter($baoName::getSelectWhereClause($tableAlias));
        foreach ($clauses as $field => $clause) {
          // Skip contact_id field if redundant
          if ($field !== 'contact_id' || !in_array('civicrm_contact', $this->selectedTables())) {
            $ret["$tableName.$field"] = $clause;
          }
        }
      }
    }
    // Override output from buildACLClause
    $this->_aclFrom = NULL;
    $this->_aclWhere = implode(' AND ', $ret);
  }

  /**
   * Get an array of all fields used to make up the query (in any capacity).
   *
   * @return array
   */
  protected function getAllUsedFields(): array {
    return array_merge(
      $this->getSelectedFilters(),
      $this->getSelectedFields(),
      $this->getSelectedOrderBys(),
      $this->getSelectedAggregateRows(),
      $this->getSelectedAggregateColumns(),
      $this->getSelectedGroupBys()
    );
  }

  /**
   * @param array $fieldSpec
   * @param string $tableKey
   * @param string $fieldName
   */
  protected function appendFieldToMetadata(array $fieldSpec, string $tableKey, string $fieldName): void {
    $this->_columns[$tableKey]['metadata'][$fieldName] = $fieldSpec;
    // Empty metadata so it gets rebuild based off columns when next requested.
    $this->metaData = [];
  }

  /**
   * @param array $fieldSpec
   * @param string $tableKey
   * @param string $fieldName
   */
  protected function addFieldToMetadata(array $fieldSpec, string $tableKey, string $fieldName): void {
    $definitionTypes = [
      'fields',
      'filters',
      'join_filters',
      'group_bys',
      'order_bys',
      'aggregate_columns',
    ];
    $this->_columns[$tableKey]['metadata'][] = $fieldSpec;
    $this->metaData['metadata'][$fieldName] = $fieldSpec;
    foreach ($definitionTypes as $type) {
      if (!isset($this->metaData[$type])) {
        $this->metaData[$type] = [];
      }
      if ($fieldSpec['is_' . $type]) {
        $this->metaData[$type][$fieldName] = $fieldSpec;
      }
      if ($type === 'filters' && !empty($fieldSpec['having'])) {
        $this->metaData['having'][$fieldName] = $fieldSpec;
      }
    }
    if (!isset($this->metaData['having'])) {
      $this->metaData['having'] = [];
    }
  }

  /**
   * Rebuild the metadata array.
   */
  protected function rebuildMetadata(): void {
    $definitionTypes = [
      'fields',
      'filters',
      'join_filters',
      'group_bys',
      'order_bys',
      'aggregate_columns',
    ];
    $this->metaData = array_fill_keys($definitionTypes, []);
    $this->metaData['having'] = [];

    foreach ($this->_columns as $table => $tableSpec) {
      if (!empty($tableSpec['is_required_for_acls'])) {
        $this->aclTables[$table] = $table;
      }
      foreach ($tableSpec['metadata'] as $fieldName => $fieldSpec) {
        $fieldSpec = array_merge([
          'table_name' => $table,
          'group_title' => $tableSpec['group_title'],
          'prefix' => $tableSpec['prefix'] ?? '',
          'prefix_label' => $tableSpec['prefix_label'] ?? '',
        ], $fieldSpec);
        $this->addFieldToMetadata($fieldSpec, $table, $fieldName);
      }
    }
    // If set then unset it as metadata has changed so this might too.
    $this->_selectedTables = [];
  }

  /**
   * Set a metadata value.
   *
   * Currently this adds to a build array but not to an un-built array
   * (as that should be added when built and we don't want to block it building).
   *
   * @param string $fieldName
   * @param $key
   * @param $value
   */
  protected function setMetadataValue(string $fieldName, $key, $value): void {
    if (!empty($this->metaData['metadata'])) {
      $this->metaData['metadata'][$fieldName][$key] = $value;
      foreach ($this->metaData as $type => $fields) {
        if (isset($fields[$fieldName])) {
          $this->metaData[$type][$fieldName][$key] = $value;
        }
      }
    }
  }

  /**
   * Get the minimum year for the date box.
   *
   * @param int $yearsInPast
   * @param int $yearsInFuture
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getYearOptions(int $yearsInPast = 8, int $yearsInFuture = 2): array {
    $optionYear = [];
    $date = CRM_Core_SelectValues::date('custom', NULL, $yearsInPast, $yearsInFuture);
    $count = $date['maxYear'];
    while ($date['minYear'] <= $count) {
      $optionYear[$date['minYear']] = $date['minYear'];
      $date['minYear']++;
    }
    return $optionYear;
  }

  /**
   * Should batches be included.
   *
   * @return bool
   */
  protected function includeBatches(): bool {
    if (!isset($this->tableStatuses['batch'])) {
      $this->tableStatuses['batch'] = (bool) CRM_Batch_BAO_Batch::singleValueQuery('SELECT COUNT(*) FROM civicrm_batch');
    }
    return $this->tableStatuses['batch'];
  }

  /**
   * Join in the note table for the given entity, using group_concat to prevent multiple rows.
   *
   * This is a many to one relationship, although for many tables that might not be UI-exposed.
   * If there are many it could mess with totals so we use an temp table, as is done
   * for entity tags. There is some risk of performance issues but they don't seem to have been reported
   * in other similar places - we could go down the preconstrain path if we had to.
   *
   * @param string $entity
   */
  protected function joinNoteForEntity(string $entity): void {
    $tableAlias = $entity . '_civicrm_note';
    if ($this->isTableSelected($tableAlias)) {
      if (!isset($this->temporaryTables[$tableAlias])) {
        $tmpTableName = $this->createTemporaryTableFromColumns($tableAlias, '`entity_id` INT(10) NOT NULL, `id` INT(10) NOT NULL, `entity_table` varchar(64) NULL, `note` longtext NULL, index (entity_id), index (id)');
        $sql = " INSERT INTO $tmpTableName
          # we need id & entity_table to 'fool' the acl clause but id just has to be not null.
          SELECT entity_id, MAX(id), 'civicrm_$entity' as entity_table, GROUP_CONCAT(note SEPARATOR ', ') as note
          FROM civicrm_note note
          WHERE entity_table = 'civicrm_$entity'
          GROUP BY note.entity_id
        ";

        $this->executeReportQuery($sql);
      }
      $this->_from .= "
        LEFT JOIN {$this->temporaryTables[$tableAlias]['name']} {$this->_aliases[$tableAlias]}
        ON ( {$this->_aliases['civicrm_' . $entity]}.id = {$this->_aliases[$tableAlias]}.entity_id)";
    }
  }

  /**
   * @return bool
   */
  protected function isCampaignEnabled(): bool {
    // Check if CiviCampaign is a) enabled and b) has active campaigns
    if (!CRM_Core_Component::isEnabled('CiviCampaign')) {
      return FALSE;
    }
    $getCampaigns = CRM_Campaign_BAO_Campaign::getPermissionedCampaigns(NULL, NULL, TRUE, FALSE, TRUE);
    $this->activeCampaigns = $getCampaigns['campaigns'];
    asort($this->activeCampaigns);
    return TRUE;
  }

  /**
   * Format rows when aggregating by total amount.
   *
   * @param array $rows
   *
   * @throws \CRM_Core_Exception
   * @throws \CRM_Core_Exception
   */
  protected function formatTotalAmountAggregateRows(array &$rows): void {
    $columnType = explode('_', $this->_params['aggregate_column_headers']);
    $columnType = end($columnType);

    // Get the row field data for adding conditions.
    foreach ($rows as $rowsKey => $rowsData) {
      $rowFieldId = $rowsData[$this->getAggregateRowFieldAlias()] ?? '';
      $rows[$rowsKey] = $this->buildContributionTotalAmountByBreakdown($rowFieldId, $columnType, $this->_params['aggregate_column_headers']);
    }

    $row = [];
    foreach ($rows as $key => $row) {
      foreach ($row as $columnName => $amount) {
        if ($columnName !== $this->getAggregateRowFieldAlias()) {
          $rows[$key][$columnName] = $amount;
        }
      }
    }
    // If there is a rollup it will be the last one - so that one will have 'all' the
    // columns.
    $this->_statFields = array_keys($row);
  }

  /**
   * Get the alias for the aggregate row field.
   *
   * @return string
   */
  protected function getAggregateRowFieldAlias(): string {
    $rowFields = $this->getAggregateFieldSpec('row')[0] ?? [];
    return $rowFields['alias'] ?? '';
  }

  /**
   * Get the title for the aggregate row field.
   *
   * @return string
   */
  protected function getAggregateRowFieldTitle(): string {
    $rowFields = $this->getAggregateFieldSpec('row')[0] ?? [];
    return $rowFields['title'] ?? '';
  }

  /**
   * @throws \CRM_Core_Exception
   */
  protected function wrangleColumnHeadersForContributionPivotWithReceiveDateAggregate(): void {
    // Change column header.
    if (isset($this->_params['aggregate_column_headers']) && ($this->_params['aggregate_column_headers'] === 'contribution_total_amount_year' || $this->_params['aggregate_column_headers'] === 'contribution_total_amount_month')) {
      $columnType = explode('_', $this->_params['aggregate_column_headers']);
      $columnType = end($columnType);
      $result = $this->buildContributionTotalAmountByBreakdown('HEADER', $columnType, $this->_params['aggregate_column_headers']);

      $header = array_keys($result);

      foreach ($header as $value) {
        if ($value === $this->getAggregateRowFieldAlias()) {
          $amountYearLabel[$value]['title'] = $this->getAggregateRowFieldTitle();
        }
        if ($value !== 'total_amount_total' && strpos($value, 'total_amount_') !== FALSE) {
          $title = preg_replace('/\D/', '', $value);
          if ($columnType === 'month') {
            if (strlen($title) > 2) {
              $year = substr($title, -4);
              if (!empty($year)) {
                $month = str_replace($year, '', $title);
                $title = date("M", mktime(0, 0, 0, (int) $month, 10)) . ' ' . $year;
                $headerWeight[$year][$value] = $title;
              }
            }
            else {
              $title = date("M", mktime(0, 0, 0, (int) $title, 10));
            }
          }
          $amountYearLabel[$value]['title'] = $title;
        }
      }
      $amountYearLabel['total_amount_total']['title'] = E::ts('Total');
      if (!empty($headerWeight)) {
        $amountYearLabel = [];
        $amountYearLabel[$this->getAggregateRowFieldAlias()]['title'] = $this->_columnHeaders[$this->getAggregateRowFieldAlias()]['title'];
        ksort($headerWeight);
        foreach ($headerWeight as $headerWeightvalue) {
          foreach ($headerWeightvalue as $headerKey => $headerTitle) {
            $amountYearLabel[$headerKey]['title'] = $headerTitle;
          }
        }
        $amountYearLabel['total_amount_total']['title'] = E::ts('Total');
      }

      $this->_columnHeaders = $amountYearLabel;
    }
  }

  /**
   * @return array
   */
  protected function getBooleanOptions(): array {
    return [
      '' => E::ts('- select -'),
      1 => E::ts('Yes'),
      0 => E::ts('No'),
    ];
  }

}
