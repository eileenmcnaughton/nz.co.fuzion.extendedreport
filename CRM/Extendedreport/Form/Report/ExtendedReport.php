<?php

class CRM_Extendedreport_Form_Report_ExtendedReport extends CRM_Report_Form {
  protected $_addressField = FALSE;

  protected $_emailField = FALSE;
  protected $_extraFrom = '';
  protected $_summary = NULL;
  protected $_exposeContactID = FALSE;
  protected $_customGroupExtends = array();
  protected $_baseTable = 'civicrm_contact';
  protected $_editableFields = TRUE;
  protected $_outputMode = array();

  /*
   * adding support for a single date in here
   */
  CONST OP_SINGLEDATE    = 3;

  /*
   * adding support for date time here - note that this is for 4.2
   * 4.3 has it in CRM_Report_Form
  */
  CONST OP_DATETIME    = 5;

  /*
   * array of extended custom data fields. this is populated by functions like getContactColunmns
   */
  protected $_customGroupExtended = array();
  /*
   * Change time filters to time date filters by setting this to 1
   */
  protected $_timeDateFilters = FALSE;
  /*
   * Use $temporary to choose whether to generate permanent or temporary tables
   * ie. for debugging it's good to set to ''
   */
  protected $_temporary = ' TEMPORARY ';

  protected $_customGroupAggregates;

  protected $_joinFilters = array();

  /**
   * generate a temp table of records that meet criteria & then build the query
   */
  protected $_preConstrain = FALSE;
  /**
   * Set to true once temp table has been generated
   */
  protected $_preConstrained = FALSE;

  protected $financialTypeField = 'financial_type_id';
  protected $financialTypeLabel = 'Financial Type';
  protected $financialTypePseudoConstant = 'financialType';

  protected $whereClauses = array();

  function __construct() {
    parent::__construct();
    $this->addSelectableCustomFields();
    $this->addTemplateSelector();
 //   CRM_Core_Resources::singleton()->addScriptFile('nz.co.fuzion.extendedreport', 'js/jquery.multiselect.filter.js');
  }

  /**
   * For 4.3 / 4.2 compatibility set financial type fields
   */
  function setFinancialType(){
    if (method_exists('CRM_Contribute_PseudoConstant', 'contributionType' )){
      $this->financialTypeField = 'contribution_type_id';
      $this->financialTypeLabel = 'Contribution Type';
      $this->financialTypePseudoConstant = 'contributionType';
    }
  }

  /**
   * Backported purely to provide CRM-12687 which is in 4.4
   */
  function preProcess() {
    $this->preProcessCommon();

    if (!$this->_id) {
      $this->addBreadCrumb();
    }

    foreach ($this->_columns as $tableName => $table) {
      // set alias
      if (!isset($table['alias'])) {
        $this->_columns[$tableName]['alias'] = substr($tableName, 8) . '_civireport';
      }
      else {
        $this->_columns[$tableName]['alias'] = $table['alias'] . '_civireport';
      }

      $this->_aliases[$tableName] = $this->_columns[$tableName]['alias'];

      // higher preference to bao object
      if (array_key_exists('bao', $table)) {
        require_once str_replace('_', DIRECTORY_SEPARATOR, $table['bao'] . '.php');
        eval("\$expFields = {$table['bao']}::exportableFields( );");
      }
      elseif (array_key_exists('dao', $table)){
        require_once str_replace('_', DIRECTORY_SEPARATOR, $table['dao'] . '.php');
        eval("\$expFields = {$table['dao']}::export( );");
      }
      else{
        $expFields = array();
      }

      $doNotCopy = array('required');

      $fieldGroups = array('fields', 'filters', 'group_bys', 'order_bys');
      foreach ($fieldGroups as $fieldGrp) {
        if (CRM_Utils_Array::value($fieldGrp, $table) && is_array($table[$fieldGrp])) {
          foreach ($table[$fieldGrp] as $fieldName => $field) {
            // $name is the field name used to reference the BAO/DAO export fields array
            $name = isset($field['name']) ? $field['name'] : $fieldName;

            // Sometimes the field name key in the BAO/DAO export fields array is
            // different from the actual database field name.
            // Unset $field['name'] so that actual database field name can be obtained
            // from the BAO/DAO export fields array.
            unset($field['name']);

            if (array_key_exists($name, $expFields)) {
              foreach ($doNotCopy as $dnc) {
                // unset the values we don't want to be copied.
                unset($expFields[$name][$dnc]);
              }
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
            if (CRM_Utils_Array::value('no_repeat', $field)) {
              $this->_noRepeats[] = "{$tableName}_{$fieldName}";
            }
            if (CRM_Utils_Array::value('no_display', $field)) {
              $this->_noDisplay[] = "{$tableName}_{$fieldName}";
            }

            // set alias = table-name, unless already set
            $alias = isset($field['alias']) ? $field['alias'] : (isset($this->_columns[$tableName]['alias']) ?
                     $this->_columns[$tableName]['alias'] : $tableName
            );
            $this->_columns[$tableName][$fieldGrp][$fieldName]['alias'] = $alias;

            // set name = fieldName, unless already set
            if (!isset($this->_columns[$tableName][$fieldGrp][$fieldName]['name'])) {
              $this->_columns[$tableName][$fieldGrp][$fieldName]['name'] = $name;
            }

            // set dbAlias = alias.name, unless already set
            if (!isset($this->_columns[$tableName][$fieldGrp][$fieldName]['dbAlias'])) {
              $this->_columns[$tableName][$fieldGrp][$fieldName]['dbAlias'] = $alias . '.' . $this->_columns[$tableName][$fieldGrp][$fieldName]['name'];
            }

            if (CRM_Utils_Array::value('type', $this->_columns[$tableName][$fieldGrp][$fieldName]) &&
              !isset($this->_columns[$tableName][$fieldGrp][$fieldName]['operatorType'])
            ) {
              if (in_array($this->_columns[$tableName][$fieldGrp][$fieldName]['type'],
                  array(CRM_Utils_Type::T_MONEY, CRM_Utils_Type::T_FLOAT)
                )) {
                $this->_columns[$tableName][$fieldGrp][$fieldName]['operatorType'] = CRM_Report_Form::OP_FLOAT;
              }
              elseif (in_array($this->_columns[$tableName][$fieldGrp][$fieldName]['type'],
                  array(CRM_Utils_Type::T_INT)
                )) {
                $this->_columns[$tableName][$fieldGrp][$fieldName]['operatorType'] = CRM_Report_Form::OP_INT;
              }
            }
          }
        }
      }

      // copy filters to a separate handy variable
      if (array_key_exists('filters', $table)) {
        $this->_filters[$tableName] = $this->_columns[$tableName]['filters'];
      }

      if (array_key_exists('group_bys', $table)) {
        $groupBys[$tableName] = $this->_columns[$tableName]['group_bys'];
      }

      if (array_key_exists('fields', $table)) {
        $reportFields[$tableName] = $this->_columns[$tableName]['fields'];
      }
    }

    if ($this->_force) {
      $this->setDefaultValues(FALSE);
    }

    CRM_Report_Utils_Get::processFilter($this->_filters, $this->_defaults);
    CRM_Report_Utils_Get::processGroupBy($groupBys, $this->_defaults);
    CRM_Report_Utils_Get::processFields($reportFields, $this->_defaults);
    CRM_Report_Utils_Get::processChart($this->_defaults);

    if ($this->_force) {
      $this->_formValues = $this->_defaults;
      $this->postProcess();
    }
  }


  function select() {
    if($this->_preConstrain && !$this->_preConstrained){
      $this->_select = " SELECT DISTINCT {$this->_aliases[$this->_baseTable]}.id";
      return;
    }

    if($this->_customGroupAggregates){
      if(empty($this->_params)){
        $this->_params = $this->controller->exportValues($this->_name);
      }
      $this->aggregateSelect();
      return;
    }
    $this->storeGroupByArray();
    $this->unsetBaseTableStatsFieldsWhereNoGroupBy();
    parent::select();
    if(empty($this->_select)){
      $this->_select = " SELECT 1 ";
    }
  }
  /*
   * Function to do a simple cross-tab
   */
  function aggregateSelect(){
    $columnHeader = $this->_params['aggregate_column_headers'];
    $rowHeader = $this->_params['aggregate_row_headers'];

    $fieldArr = explode(":", $rowHeader);
    $rowFields[$fieldArr[1]][] = $fieldArr[0];
    $fieldArr = explode(":", $columnHeader);
    $columnFields[$fieldArr[1]][] = $fieldArr[0];

    $selectedTables = array();

    $rowColumns = $this->extractCustomFields( $rowFields, $selectedTables, 'row_header');
    $rowHeaderFieldName = $rowColumns[$rowHeader]['name'];
    $this->_columnHeaders[$rowHeaderFieldName] =$rowColumns[$rowHeader][$rowHeaderFieldName];

    $columnColumns = $this->extractCustomFields( $columnFields, $selectedTables, 'column_header');

    foreach ($selectedTables as $selectedTable => $properties){
      $extendsTable = $properties['extends_table'];
      $this->_extraFrom .= "
      LEFT JOIN {$properties['name']} $selectedTable ON {$selectedTable}.entity_id = {$this->_aliases[$extendsTable]}.id";
    }
  }

  function addColumnAggregateSelect($fieldName, $tableAlias, $spec){
    if(empty($spec['option_group_id'])){
      throw new Exception('currently column headers need to be radio or select');
    }
    $options = civicrm_api('option_value', 'get', array('version' => 3, 'options' => array('limit' => 50,), 'option_group_id' => $spec['option_group_id']));
    foreach ($options['values'] as $option){
      $fieldAlias = "{$fieldName}_" . strtolower(str_replace(' ','',$option['value']));
      if(in_array($spec['htmlType'], array('CheckBox', 'MultiSelect'))){
        $this->_select .= " , SUM( CASE WHEN {$tableAlias}.{$fieldName} LIKE '%" . CRM_Core_DAO::VALUE_SEPARATOR . $option['value'] . CRM_Core_DAO::VALUE_SEPARATOR . "%' THEN 1 ELSE 0 END ) AS $fieldAlias ";
      }
      else {
        $this->_select .= " , SUM( CASE {$tableAlias}.{$fieldName} WHEN '{$option['value']}' THEN 1 ELSE 0 END ) AS $fieldAlias ";
      }
      $this->_columnHeaders[$fieldAlias] = array('title' => $spec['title'] . " - " . $option['label']);
    }
    $this->_select .= " , SUM( CASE {$tableAlias}.{$fieldName} WHEN '{$option['value']}' THEN 1 ELSE 0 END ) AS $fieldAlias ";
  }

  /**
   * overridden purely for annoying 4.2 e-notice on $selectColumns(fixed in 4.3)
   */

  function unselectedSectionColumns() {
    $selectColumns = array();
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          if (CRM_Utils_Array::value('required', $field) ||
            CRM_Utils_Array::value($fieldName, $this->_params['fields'])
          ) {

            $selectColumns["{$tableName}_{$fieldName}"] = 1;
          }
        }
      }
    }
    if (is_array($this->_sections) && is_array($selectColumns)) {
      return array_diff_key($this->_sections, $selectColumns);
    }
    else {
      return array();
    }
  }

  /*
* From clause build where baseTable & fromClauses are defined
*/
  function from() {
    if (!empty($this->_baseTable)) {
      if(!empty($this->_aliases['civicrm_contact'])){
        $this->buildACLClause($this->_aliases['civicrm_contact']);
      }
      $this->_from = "FROM {$this->_baseTable} " . (empty($this->_aliases[$this->_baseTable]) ? '': $this->_aliases[$this->_baseTable]);
      $availableClauses = $this->getAvailableJoins();
      foreach ($this->fromClauses() as $clausekey => $fromClause) {
        if(is_array( $fromClause)){
          // we might be adding the same join more than once (should have made it an array from the start)
          $fn = $availableClauses[$clausekey]['callback'];
           foreach ($fromClause as $fromTable => $fromSpec){
            $append = $this->$fn($fromTable, $fromSpec);
          }
        }
        else{
          //@todo - basically have separate handling for the string vs array scenarios
        $fn = $availableClauses[$fromClause]['callback'];
        $extra = array();
          if(isset($this->_joinFilters[$fromClause])){
            $extra = $this->_joinFilters[$fromClause];
          }
          $append = $this->$fn('', $extra);
          if($append && !empty($extra)){
              foreach ($extra as $table => $field){
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
    $this->selectableCustomDataFrom();
  }

  /**
   *  constrainedWhere applies to Where clauses applied AFTER the
   * 'pre-constrained' report universe is created.
   *
   * For example the universe might be limited to a group of contacts in the first round
   * in the second round this Where clause is applied
   */
  function constrainedWhere(){
  }
  /*
   * Override exists purely to handle unusual date fields by passing field metadata to date clause
   * Also store where clauses to an array
   */
  function where() {
    $whereClauses = $havingClauses = array();
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('filters', $table)) {
        foreach ($table['filters'] as $fieldName => $field) {
          if(!empty($field['pseudofield'])){
            continue;
          }
          $clause = NULL;
          if (CRM_Utils_Array::value('type', $field) & CRM_Utils_Type::T_DATE) {
            if (CRM_Utils_Array::value('operatorType', $field) == CRM_Report_Form::OP_MONTH) {
              $op = CRM_Utils_Array::value("{$fieldName}_op", $this->_params);
              $value = CRM_Utils_Array::value("{$fieldName}_value", $this->_params);
              if (is_array($value) && !empty($value)) {
                $clause = "(month({$field['dbAlias']}) $op (" . implode(', ', $value) . '))';
                $this->whereClauses[$tableName][] = $clause;
              }
            }
            else {
              $relative = CRM_Utils_Array::value("{$fieldName}_relative", $this->_params);
              $from     = CRM_Utils_Array::value("{$fieldName}_from", $this->_params);
              $to       = CRM_Utils_Array::value("{$fieldName}_to", $this->_params);
              $fromTime = CRM_Utils_Array::value("{$fieldName}_from_time", $this->_params);
              $toTime   = CRM_Utils_Array::value("{$fieldName}_to_time", $this->_params);
              // next line is the changed one
              $clause   = $this->dateClause($field['dbAlias'], $relative, $from, $to, $field, $fromTime, $toTime);
              $this->whereClauses[$tableName][] = $clause;
            }
          }
          else {
            $op = CRM_Utils_Array::value("{$fieldName}_op", $this->_params);
            if ($op) {
              $clause = $this->whereClause($field,
                $op,
                CRM_Utils_Array::value("{$fieldName}_value", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_min", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_max", $this->_params)
              );
              if(!empty($clause)){
                $this->whereClauses[$tableName][] = $clause;
              }
            }
          }


          if (!empty($clause)) {
            if (CRM_Utils_Array::value('having', $field)) {
              $havingClauses[] = $clause;
            }
            else {
              $whereClauses[] = $clause;
            }
          }
        }
      }
    }

    if (empty($whereClauses)) {
      $this->_where = "WHERE ( 1 ) ";
      $this->_having = "";
    }
    else {
      $this->_where = "WHERE " . implode(' AND ', $whereClauses);
    }

    if ($this->_aclWhere) {
      $this->_where .= " AND {$this->_aclWhere} ";
    }

    if (!empty($havingClauses)) {
      // use this clause to construct group by clause.
      $this->_having = "HAVING " . implode(' AND ', $havingClauses);
    }
  }

    /*
     * over-ridden to include clause if specified
     */
    function dateClause($fieldName,
      $relative, $from, $to, $field, $fromTime = NULL, $toTime = NULL
    ) {
      $type = $field['type'];
      if(empty($field['clause']))
      {
        return parent::dateClause($fieldName,
          $relative, $from, $to, $type, $fromTime = NULL, $toTime = NULL);
      }
      $clauses = array();

      list($from, $to) = self::getFromTo($relative, $from, $to, $fromTime, $toTime);
      eval("\$clause = \"{$field['clause']}\";");
      $clauses[] = $clause;
      if (!empty($clauses)) {
        return implode(' AND ', $clauses);
      }
      return NULL;
    }



  /*
* Define any from clauses in use (child classes to override)
*/
  function fromClauses() {
    return array();
  }
/*
 * We're overriding the parent class so we can populate a 'group_by' array for other functions use
 * e.g. editable fields are turned off when groupby is used
 */
  function groupBy() {
    $this->storeGroupByArray();
    if (!empty($this->_groupByArray)) {
      $this->_groupBy = "GROUP BY " . implode(', ', $this->_groupByArray);
    }
  }

  function orderBy() {
    parent::orderBy();
  }
/*
 * Store group bys into array - so we can check elsewhere (e.g editable fields) what is grouped
 */
  function storeGroupByArray(){
    if (CRM_Utils_Array::value('group_bys', $this->_params) &&
        is_array($this->_params['group_bys']) &&
        !empty($this->_params['group_bys'])
    ) {
      foreach ($this->_columns as $tableName => $table) {
        if (array_key_exists('group_bys', $table)) {
          foreach ($table['group_bys'] as $fieldName => $field) {
            if (CRM_Utils_Array::value($fieldName, $this->_params['group_bys'])) {
              $this->_groupByArray[] = $field['dbAlias'];
            }
          }
        }
      }
    }
    // if a stat field has been selected then do a group by - this is not in parent
    if (!empty($this->_statFields) && empty($this->_groupByArray) ) {
      $this->_groupByArray[] = $this->_aliases[$this->_baseTable] . ".id";
    }
  }
  /*
   * It's not useful to do stats on the base table if no group by is going on
   * the table is likely to be involved in left joins & give a bad answer for no reason
   * (still pondering how to deal with turned totaling on & off appropriately)
   *
   **/
  function unsetBaseTableStatsFieldsWhereNoGroupBy(){
    if(empty($this->_groupByArray) && !empty($this->_columns[$this->_baseTable]['fields'])){
      foreach($this->_columns[$this->_baseTable]['fields'] as $fieldname => $field){
        if(isset( $field['statistics'])){
          unset($this->_columns[$this->_baseTable]['fields'][$fieldname]['statistics']);
        }
      }
    }
  }

  function addFilters() {
    $options = $filters = array();
    $count = 1;
    foreach ($this->_filters as $table => $attributes) {
      foreach ($attributes as $fieldName => $field) {
        // get ready with option value pair
        $operations = CRM_Utils_Array::value('operations', $field);
        if(empty($operations)){
          $operations = $this->getOperators(CRM_Utils_Array::value('operatorType', $field),
            $fieldName
        );
        }

        $filters[$table][$fieldName] = $field;

        switch (CRM_Utils_Array::value('operatorType', $field)) {
          case CRM_Report_Form::OP_MONTH:
            if (!array_key_exists('options', $field) || !is_array($field['options']) || empty($field['options'])) {
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
          case CRM_Report_FORM::OP_MULTISELECT:
          case CRM_Report_FORM::OP_MULTISELECT_SEPARATOR:
            // assume a multi-select field
            if (!empty($field['options'])) {
              $element = $this->addElement('select', "{$fieldName}_op", ts('Operator:'), $operations);
              if (count($operations) <= 1) {
                $element->freeze();
              }
              $select = $this->addElement('select', "{$fieldName}_value", NULL,
              $field['options'], array(
                'size' => 4,
                'style' => 'min-width:250px',
              )
              );
              $select->setMultiple(TRUE);
            }
            break;

          case CRM_Report_FORM::OP_SELECT:
            // assume a select field
            $this->addElement('select', "{$fieldName}_op", ts('Operator:'), $operations);
            $this->addElement('select', "{$fieldName}_value", NULL, $field['options']);
            break;

          case CRM_Report_FORM::OP_DATE:
            // build datetime fields
            CRM_Core_Form_Date::buildDateRange($this, $fieldName, $count);
            $count++;
            break;

          case self::OP_DATETIME:
            // build datetime fields
            CRM_Core_Form_Date::buildDateRange($this, $fieldName, $count, '_from', '_to', 'From:', FALSE, TRUE, 'searchDate', true);
            $count++;
            break;
          case self::OP_SINGLEDATE:
            // build single datetime field
            $this->addElement('select', "{$fieldName}_op", ts('Operator:'), $operations);
            $this->addDate("{$fieldName}_value", ts(''), FALSE);
            $count++;
            break;
          case CRM_Report_FORM::OP_INT:
          case CRM_Report_FORM::OP_FLOAT:
            // and a min value input box
            $this->add('text', "{$fieldName}_min", ts('Min'));
            // and a max value input box
            $this->add('text', "{$fieldName}_max", ts('Max'));
          default:
            // default type is string
            $this->addElement('select', "{$fieldName}_op", ts('Operator:'), $operations,
            array('onchange' => "return showHideMaxMinVal( '$fieldName', this.value );")
            );
            // we need text box for value input
            $this->add('text', "{$fieldName}_value", NULL);
            break;
        }
      }
    }
    $this->assign('filters', $filters);
  }
/**
 * We have over-riden this to provide the option of setting single date fields with defaults
 * and the option of setting 'to', 'from' defaults on date fields
 * @param boolean $freeze
 * @return Ambigous <string, multitype:, unknown>
 */
  function setDefaultValues($freeze = TRUE) {
    $freezeGroup = array();
    // FIXME: generalizing form field naming conventions would reduce
    // lots of lines below.
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          if (!array_key_exists('no_display', $field)) {
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
            if (CRM_Utils_Array::value('frequency', $field)) {
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
               && !(CRM_Utils_Array::value('operatorType', $field) == self::OP_SINGLEDATE)) {
              if(is_array($field['default'])){
                $this->_defaults["{$fieldName}_from"] = CRM_Utils_Array::value('from', $field['default']);
                $this->_defaults["{$fieldName}_to"] = CRM_Utils_Array::value('to', $field['default']);
                $this->_defaults["{$fieldName}_relative"] = 0;
              }
              else{
                $this->_defaults["{$fieldName}_relative"] = $field['default'];
              }
            }
            else {
              $this->_defaults["{$fieldName}_value"] = $field['default'];
            }
          }
          //assign default value as "in" for multiselect
          //operator, To freeze the select element
          if (CRM_Utils_Array::value('operatorType', $field) == CRM_Report_FORM::OP_MULTISELECT) {
            $this->_defaults["{$fieldName}_op"] = 'in';
          }
          elseif (CRM_Utils_Array::value('operatorType', $field) == CRM_Report_FORM::OP_MULTISELECT_SEPARATOR) {
            $this->_defaults["{$fieldName}_op"] = 'mhas';
          }
          elseif ($op = CRM_Utils_Array::value('default_op', $field)) {
            $this->_defaults["{$fieldName}_op"] = $op;
          }
        }
      }

      if (
        array_key_exists('order_bys', $table) &&
        is_array($table['order_bys'])
      ) {
        if (!array_key_exists('order_bys', $this->_defaults)) {
          $this->_defaults['order_bys'] = array();
        }
        foreach ($table['order_bys'] as $fieldName => $field) {
          if (
            CRM_Utils_Array::value('default', $field) ||
            CRM_Utils_Array::value('default_order', $field) ||
            CRM_Utils_Array::value('default_is_section', $field) ||
            CRM_Utils_Array::value('default_weight', $field)
          ) {
            $order_by = array(
              'column' => $fieldName,
              'order' => CRM_Utils_Array::value('default_order', $field, 'ASC'),
              'section' => CRM_Utils_Array::value('default_is_section', $field, 0),
            );

            if (CRM_Utils_Array::value('default_weight', $field)) {
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
      $this->_defaults = array_merge($this->_defaults, $this->_formValues);
    }

    if ($this->_instanceValues) {
      $this->_defaults = array_merge($this->_defaults, $this->_instanceValues);
    }

    CRM_Report_Form_Instance::setDefaultValues($this, $this->_defaults);

    return $this->_defaults;
  }

  // Note: $fieldName param allows inheriting class to build operationPairs
  // specific to a field.
  // we can't override getOperationPair because it is static in 4.3 & not static in 4.2 so rename
   function getOperators($type = "string", $fieldName = NULL) {
    // FIXME: At some point we should move these key-val pairs
    // to option_group and option_value table.

    switch ($type) {
      case CRM_Report_FORM::OP_INT:
      case CRM_Report_FORM::OP_FLOAT:
        return array('lte' => ts('Is less than or equal to'),
        'gte' => ts('Is greater than or equal to'),
        'bw' => ts('Is between'),
        'eq' => ts('Is equal to'),
        'lt' => ts('Is less than'),
        'gt' => ts('Is greater than'),
        'neq' => ts('Is not equal to'),
        'nbw' => ts('Is not between'),
        'nll' => ts('Is empty (Null)'),
        'nnll' => ts('Is not empty (Null)'),
        );
        break;

      case CRM_Report_FORM::OP_SELECT:
        return array('eq' => ts('Is equal to'));

      case CRM_Report_FORM::OP_MONTH:
      case CRM_Report_FORM::OP_MULTISELECT:
        return array('in' => ts('Is one of'),
        'notin' => ts('Is not one of'),
        );
        break;

      case CRM_Report_FORM::OP_DATE:
        return array('nll' => ts('Is empty (Null)'),
        'nnll' => ts('Is not empty (Null)'),
        );
        break;
      case self::OP_SINGLEDATE:
          return array(
            'to' => ts('Until Date'),
            'from' => ts('From Date'),
          );
        break;
      case CRM_Report_FORM::OP_MULTISELECT_SEPARATOR:
        // use this operator for the values, concatenated with separator. For e.g if
        // multiple options for a column is stored as ^A{val1}^A{val2}^A
        return array('mhas' => ts('Is one of'));

      default:
        // type is string
        return array('has' => ts('Contains'),
        'sw' => ts('Starts with'),
        'ew' => ts('Ends with'),
        'nhas' => ts('Does not contain'),
        'eq' => ts('Is equal to'),
        'neq' => ts('Is not equal to'),
        'nll' => ts('Is empty (Null)'),
        'nnll' => ts('Is not empty (Null)'),
        );
    }
  }



  function statistics(&$rows) {
    return parent::statistics($rows);
  }

  /*
   * mostly overriding this for ease of adding in debug
   */
  function postProcess() {

    try{
      if (!empty($this->_aclTable) && CRM_Utils_Array::value($this->_aclTable, $this->_aliases)) {
        $this->buildACLClause($this->_aliases[$this->_aclTable]);
      }

    $this->beginPostProcess();
    $sql = $this->buildQuery();
    // build array of result based on column headers. This method also allows
    // modifying column headers before using it to build result set i.e $rows.
    $rows = array();
    $this->buildRows($sql, $rows);
    // format result set.
    $this->formatDisplay($rows);

    // assign variables to templates
    $this->doTemplateAssignment($rows);

    // do print / pdf / instance stuff if needed
    $this->endPostProcess($rows);
    } catch(Exception $e) {
      $err['message'] = $e->getMessage();
      $err['trace'] = $e->getTrace();

      foreach ($err['trace'] as $fn){
        if($fn['function'] == 'raiseError'){
          foreach ($fn['args'] as $arg){
            $err['sql_error'] = $arg;
          }
        }
      if($fn['function'] == 'simpleQuery'){
          foreach ($fn['args'] as $arg){
            $err['sql_query'] = $arg;
          }
        }
      }

      if(function_exists('dpm')){
        dpm ($err);
        dpm($this->_columns);
;      }
      else{
        CRM_Core_Error::debug($err);
      }

    }
  }

  /**
   * overriding because to post && !$this->_noFields from 4.3 to 4.2
   */
  function beginPostProcess() {
    $this->_params = $this->controller->exportValues($this->_name);
    if (empty($this->_params) &&
      $this->_force
    ) {
      $this->_params = $this->_formValues;
    }
    // hack to fix params when submitted from dashboard, CRM-8532
    // fields array is missing because form building etc is skipped
    // in dashboard mode for report
    if (!CRM_Utils_Array::value('fields', $this->_params) && !$this->_noFields) {
      $this->_params = $this->_formValues;
    }

    $this->_formValues = $this->_params;
    if (CRM_Core_Permission::check('administer Reports') &&
      isset($this->_id) &&
      ($this->_instanceButtonName == $this->controller->getButtonName() . '_save' ||
        $this->_chartButtonName == $this->controller->getButtonName()
      )
    ) {
      $this->assign('updateReportButton', TRUE);
    }
    $this->processReportMode();
  }

/**
 * Over-written to allow pre-constraints
 * @param boolean $applyLimit
 * @return string
 */

  function buildQuery($applyLimit = TRUE) {
    $this->select();
    $this->from();
    $this->customDataFrom();
    $this->where();
    if($this->_preConstrain && !$this->_preConstrained){
      $this->generateTempTable();
      $this->_preConstrained = TRUE;
      $this->select();
      $this->from();
      $this->customDataFrom();
      $this->constrainedWhere();
    }
    $this->groupBy();
    $this->orderBy();

    // order_by columns not selected for display need to be included in SELECT
    $unselectedSectionColumns = $this->unselectedSectionColumns();
    foreach ($unselectedSectionColumns as $alias => $section) {
      $this->_select .= ", {$section['dbAlias']} as {$alias}";
    }

    if ($applyLimit && !CRM_Utils_Array::value('charts', $this->_params)) {
      $this->limit();
    }
    //4.2 support - method may not exist
    if(method_exists('CRM_Utils_Hook', 'alterReportVar')){
      CRM_Utils_Hook::alterReportVar('sql', $this, $this);
    }
    $sql = "{$this->_select} {$this->_from} {$this->_where} {$this->_groupBy} {$this->_having} {$this->_orderBy} {$this->_limit}";
    return $sql;
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
  function generateTempTable(){
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


  /*
   * 4.2 backport of this function including 4.3 tweak whereby compileContent is a separate function
   * Should be able to be removed once 4.3 version is in use
   */
  function endPostProcess(&$rows = NULL) {
    if ($this->_outputMode == 'print' ||
        $this->_outputMode == 'pdf' ||
        $this->_sendmail
    ) {

      $content = $this->compileContent();
      $url = CRM_Utils_System::url("civicrm/report/instance/{$this->_id}",
      "reset=1", TRUE
      );

      if ($this->_sendmail) {
        $config = CRM_Core_Config::singleton();
        $attachments = array();

        if ($this->_outputMode == 'csv') {
          $content = $this->_formValues['report_header'] . '<p>' . ts('Report URL') . ": {$url}</p>" . '<p>' . ts('The report is attached as a CSV file.') . '</p>' . $this->_formValues['report_footer'];

          $csvFullFilename = $config->templateCompileDir . CRM_Utils_File::makeFileName('CiviReport.csv');
          $csvContent = CRM_Report_Utils_Report::makeCsv($this, $rows);
          file_put_contents($csvFullFilename, $csvContent);
          $attachments[] = array(
              'fullPath' => $csvFullFilename,
              'mime_type' => 'text/csv',
              'cleanName' => 'CiviReport.csv',
          );
        }
        if ($this->_outputMode == 'pdf') {
          // generate PDF content
          $pdfFullFilename = $config->templateCompileDir . CRM_Utils_File::makeFileName('CiviReport.pdf');
          file_put_contents($pdfFullFilename,
          CRM_Utils_PDF_Utils::html2pdf($content, "CiviReport.pdf",
          TRUE, array('orientation' => 'landscape')
          )
          );
          // generate Email Content
          $content = $this->_formValues['report_header'] . '<p>' . ts('Report URL') . ": {$url}</p>" . '<p>' . ts('The report is attached as a PDF file.') . '</p>' . $this->_formValues['report_footer'];

          $attachments[] = array(
              'fullPath' => $pdfFullFilename,
              'mime_type' => 'application/pdf',
              'cleanName' => 'CiviReport.pdf',
          );
        }

        if (CRM_Report_Utils_Report::mailReport($content, $this->_id,
            $this->_outputMode, $attachments
        )) {
          CRM_Core_Session::setStatus(ts("Report mail has been sent."), ts('Sent'), 'success');
        }
        else {
          CRM_Core_Session::setStatus(ts("Report mail could not be sent."), ts('Mail Error'), 'error');
        }

        CRM_Utils_System::redirect(CRM_Utils_System::url(CRM_Utils_System::currentPath(),
        'reset=1'
            ));
      }
      elseif ($this->_outputMode == 'print') {
        echo $content;
      }
      else {
        if ($chartType = CRM_Utils_Array::value('charts', $this->_params)) {
          $config = CRM_Core_Config::singleton();
          //get chart image name
          $chartImg = $this->_chartId . '.png';
          //get image url path
          $uploadUrl = str_replace('/persist/contribute/', '/persist/', $config->imageUploadURL) . 'openFlashChart/';
          $uploadUrl .= $chartImg;
          //get image doc path to overwrite
          $uploadImg = str_replace('/persist/contribute/', '/persist/', $config->imageUploadDir) . 'openFlashChart/' . $chartImg;
          //Load the image
          $chart = imagecreatefrompng($uploadUrl);
          //convert it into formattd png
          header('Content-type: image/png');
          //overwrite with same image
          imagepng($chart, $uploadImg);
          //delete the object
          imagedestroy($chart);
        }
        CRM_Utils_PDF_Utils::html2pdf($content, "CiviReport.pdf", FALSE, array('orientation' => 'landscape'));
      }
      CRM_Utils_System::civiExit();
    }
    elseif ($this->_outputMode == 'csv') {
      CRM_Report_Utils_Report::export2csv($this, $rows);
    }
    elseif ($this->_outputMode == 'group') {
      $group = $this->_params['groups'];
      $this->add2group($group);
    }
    elseif ($this->_instanceButtonName == $this->controller->getButtonName()) {
      CRM_Report_Form_Instance::postProcess($this);
    }
    elseif ($this->_createNewButtonName == $this->controller->getButtonName() ||
        $this->_outputMode == 'create_report' ) {
      $this->_createNew = TRUE;
      CRM_Report_Form_Instance::postProcess($this);
    }
  }

  /*
   * get name of template file
   */
  function getTemplateFileName(){
    $defaultTpl = parent::getTemplateFileName();

    if(in_array( $this->_outputMode, array( 'print', 'pdf' ))){
      if($this->_params['templates']){
        $defaultTpl = 'CRM/Extendedreport/Form/Report/CustomTemplates/' . $this->_params['templates'] .'.tpl';
      }
    }

    if(!CRM_Utils_File::isIncludable('templates/' . $defaultTpl)){
      $defaultTpl = 'CRM/Report/Form.tpl';
    }
    if(CRM_Utils_Array::value('templates', $this->_params) ==1){
     //
    }
    return $defaultTpl;
  }
  /*
   * Compile the report content
   *
   *  4.3 introduced function - overriding on 4.2
   */
  function compileContent(){
    $templateFile = $this->getTemplateFileName();
    return $this->_formValues['report_header'] . CRM_Core_Form::$_template->fetch($templateFile) . $this->_formValues['report_footer'];
  }

  /*
   * We are overriding this so that we can add time if required
   */
  function addDateRange( $name, $from = '_from', $to = '_to', $label = 'From:', $dateFormat = 'searchDate', $required = FALSE) {
    if($this->_timeDateFilters){
      $this->addDateTime( $name . '_from', $label   , $required, array( 'formatType' => $dateFormat ) );
      $this->addDateTime( $name . '_to'  , ts('To:'), $required, array( 'formatType' => $dateFormat ) );
    }
    else{
      parent::addDateRange($name, $from, $to, $label, $dateFormat, $required);
    }
  }

 /*
 *
 */

  function addTemplateSelector(){

   $templatesDir = str_replace('CRM/Extendedreport', 'templates/CRM/Extendedreport', __DIR__);
   $templatesDir .= '/CustomTemplates';
   $this->_templates = array(
     'default' => 'default template',
     'PhoneBank' => 'Phone Bank template - Phone.tpl'
   );
   $this->add('select', 'templates', ts('Select Alternate Template'), $this->_templates, FALSE,
          array('id' => 'templates', 'title' => ts('- select -'),)
   );
  }
  /*
   * This is all just copied from the addCustomFields function -
   * The point of this is to
   * 1) put together the selection of fields using a prefix so that we can use multiple instances of the
   *    same custom fields in a report - ie. so we can use the fields for 2 different contacts
   * 2) we assign these fields as a flat list to the multiple select - might move to json later
   */
  function addSelectableCustomFields($addFields = TRUE) {

    $extends = $customTableMapping = array();
    if(!empty($this->_customGroupExtended)){
      //lets try to assign custom data select fields
      foreach ($this->_customGroupExtended as $table => $spec){
        $extends = array_merge($extends, $spec['extends']);
      }
    }
    if(empty($extends)){
      return;
    }
    $sql = "
SELECT cg.table_name, cg.title, cg.extends, cf.id as cf_id, cf.label,
       cf.column_name, cf.data_type, cf.html_type, cf.option_group_id, cf.time_format
FROM   civicrm_custom_group cg
INNER  JOIN civicrm_custom_field cf ON cg.id = cf.custom_group_id
WHERE cg.extends IN ('" . implode("','", $extends) . "') AND
      cg.is_active = 1 AND
      cf.is_active = 1 AND
      cf.is_searchable = 1
ORDER BY cg.weight, cf.weight";
    $customDAO = CRM_Core_DAO::executeQuery($sql);

    while ($customDAO->fetch()) {
      $fieldName = 'custom_' . $customDAO->cf_id;
      $currentTable = $customDAO->table_name;
      $customFieldsTableFields[$customDAO->extends][$fieldName] = $customDAO->label;
      if(empty($table) || $table['name'] != $currentTable){
        $table = array(
          'dao' => 'CRM_Contact_DAO_Contact', // dummy dao object
          'extends' => $customDAO->extends,
          'grouping' => $customDAO->table_name,
          'group_title' => $customDAO->title,
          'name' => $customDAO->table_name,
        );
        $this->_customFields[$currentTable] = array();
      }
      $filters = array();
      $table['fields'][$fieldName] = $this->extractFieldsAndFilters($customDAO, $fieldName, $filters);
      $table['filters'][$fieldName] = $filters;
      $this->_customFields[$currentTable] = array_merge($this->_customFields[$currentTable], $table);
      $fieldTableMapping[$fieldName] = $currentTable;
      $customTableMapping[$customDAO->extends][] = $currentTable;
    }
    /*
     * so, now we have all the information about the custom fields - let's apply it once per
     * entity
     */
    $customFieldsFlat = array();
    if(!empty($this->_customGroupExtended)){
      //lets try to assign custom data select fields
      foreach ($this->_customGroupExtended as $table => $spec){
        $customFieldsTable[$table] = $spec['title'];
        foreach ($spec['extends'] as $entendedEntity){
          if(array_key_exists($entendedEntity, $customTableMapping)){
            foreach ($customTableMapping[$entendedEntity] as $customTable){
              $tableName = $this->_customFields[$customTable]['name'];
              $tableAlias = $table . "_" . $this->_customFields[$customTable]['name'];
              $this->_columns[$tableAlias] = $this->_customFields[$tableName];
              $this->_columns[$tableAlias]['alias'] = $tableAlias;
              $this->_columns[$table]['dao'] = 'CRM_Contact_DAO_Contact';
              unset ($this->_columns[$tableAlias]['fields']);
            }

            foreach ($customFieldsTableFields[$entendedEntity] as $customFieldName => $customFieldLabel){
              $customFields[$table][$table . ':' . $customFieldName] = $spec['title'] . $customFieldLabel;
              $customFieldsFlat[$table . ':' . $customFieldName] = $spec['title'] . $customFieldLabel;
            }
          }
        }
      }
    }

    asort($customFieldsFlat);

    if($this->_customGroupAggregates){
      $this->add('select', 'aggregate_column_headers', ts('Aggregate Report Column Headers'), $customFieldsFlat, FALSE,
          array('id' => 'aggregate_column_headers',  'title' => ts('- select -'))
      );
      $this->add('select', 'aggregate_row_headers', ts('Aggregate Report Rows'), $customFieldsFlat, FALSE,
          array('id' => 'aggregate_row_headers',  'title' => ts('- select -'))
      );
    }

    else{
      $sel = $this->add('select', 'custom_tables', ts('Custom Columns'), $customFieldsTable, FALSE,
          array('id' => 'custom_tables', 'multiple' => 'multiple', 'title' => ts('- select -'))
      );

      $this->add('select', 'custom_fields', ts('Custom Columns'), $customFieldsFlat, FALSE,
          array('id' => 'custom_fields', 'multiple' => 'multiple', 'title' => ts('- select -'), 'hierarchy' => json_encode($customFields))
      );
    }
  }

  /*
   * Extract the relevant filters from the DAO query
  */
  function extractFieldsAndFilters($customDAO, $fieldName, &$filter){
   $field = array(
        'name' => $customDAO->column_name,
        'title' => $customDAO->label,
        'dataType' => $customDAO->data_type,
        'htmlType' => $customDAO->html_type,
        'option_group_id' => $customDAO->option_group_id,
   );
   $filter = array_merge($filter, array(
     'name' => $customDAO->column_name,
     'title' => $customDAO->label,
     'dataType' => $customDAO->data_type,
     'htmlType' => $customDAO->html_type,
    ));
    switch ($customDAO->data_type) {
     case 'Date':
       $filter['operatorType'] = CRM_Report_Form::OP_DATE;
       $filter['type'] = CRM_Utils_Type::T_DATE;
       // CRM-6946, show time part for datetime date fields
       if ($customDAO->time_format) {
         $field['type'] = CRM_Utils_Type::T_TIMESTAMP;
       }
       break;

     case 'Boolean':
       // filters
       $filter['operatorType'] = CRM_Report_Form::OP_SELECT;
       // filters
       $filter['options'] = array('' => ts('- select -'),
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

       if (!empty($customDAO->option_group_id)) {
         if (in_array($customDAO->html_type, array(
             'Multi-Select', 'AdvMulti-Select', 'CheckBox'))) {
             $filter['operatorType'] = CRM_Report_Form::OP_MULTISELECT_SEPARATOR;
         }
         else {
           $filter['operatorType'] = CRM_Report_Form::OP_MULTISELECT;
         }
         if ($this->_customGroupFilters) {
           $filter['options'] = array();
           $ogDAO = CRM_Core_DAO::executeQuery("SELECT ov.value, ov.label FROM civicrm_option_value ov WHERE ov.option_group_id = %1 ORDER BY ov.weight", array(1 => array($customDAO->option_group_id, 'Integer')));
           while ($ogDAO->fetch()) {
             $filter['options'][$ogDAO->value] = $ogDAO->label;
           }
         }
       }
       break;

     case 'StateProvince':
       if (in_array($customDAO->html_type, array(
         'Multi-Select State/Province'))) {
         $filter['operatorType'] = CRM_Report_Form::OP_MULTISELECT_SEPARATOR;
       }
       else {
         $filter['operatorType'] = CRM_Report_Form::OP_MULTISELECT;
       }
       $filter['options'] = CRM_Core_PseudoConstant::stateProvince();
       break;

     case 'Country':
       if (in_array($customDAO->html_type, array(
       'Multi-Select Country'))) {
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

/*
 * Add the SELECT AND From clauses for the extensible CustomData
 * Still refactoring this from original copy & paste code to something simpler
 */
  function selectableCustomDataFrom() {

    if (empty($this->_customGroupExtended) || empty($this->_params['custom_fields'])) {
      return;
    }

    $tables = array();
    foreach ($this->_params['custom_fields'] as $customField){
      $fieldArr = explode(":", $customField);
      $tables[$fieldArr[0]] = 1;
      $customfields[$fieldArr[1]][] = $fieldArr[0];
    }

    $selectedTables = array();
    $myColumns = $this->extractCustomFields( $customfields, $selectedTables);

    foreach ($this->_params['custom_fields'] as $fieldName){
      $name = $myColumns[$fieldName]['name'];
      $this->_columnHeaders[$name] = $myColumns[$fieldName][$name];
    }
    foreach ($selectedTables as $selectedTable => $properties){
        $extendsTable = $properties['extends_table'];
        $this->_from .= "
        LEFT JOIN {$properties['name']} $selectedTable ON {$selectedTable}.entity_id = {$this->_aliases[$extendsTable]}.id";
      }

  }
  /*
   * here we can define select clauses for any particular row. At this stage we are going
   * to csv tags
   */
  function selectClause(&$tableName, $tableKey, &$fieldName, &$field) {
    if($fieldName == 'phone'){
      $alias = "{$tableName}_{$fieldName}";
      $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = CRM_Utils_Array::value('title', $field);
      $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = CRM_Utils_Array::value('type', $field);
      $this->_selectAliases[] = $alias;
      $this->_columnHeaders['civicrm_tag_tag_name'];
      return " GROUP_CONCAT(CONCAT({$field['dbAlias']},':', phone_civireport.location_type_id) ) as $alias";
    }

    return FALSE;
  }
  /*
   * Function extracts the custom fields array where it is preceded by a table prefix
   * This allows us to include custom fields from multiple contacts (for example) in one report
   */
  function extractCustomFields( &$customfields, &$selectedTables, $context = 'select'){

    foreach ($this->_customFields as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        $selectedFields = array_intersect_key($customfields, $table['fields']);
        foreach ($selectedFields  as $fieldName => $selectedfield) {
          foreach ($selectedfield as $index => $instance){
            if(!empty($table['fields'][$fieldName])){
              $customFieldsToTables[$fieldName] = $tableName;
              $fieldAlias = $customfields[$fieldName][$index] . "_" . $fieldName;
              $tableAlias = $customfields[$fieldName][$index]  . "_" . $tableName . '_civireport';
              $title = $this->_customGroupExtended[$customfields[$fieldName][$index]]['title']  . $table['fields'][$fieldName]['title'];
              $selectedTables[$tableAlias] = array(
                  'name' => $tableName,
                  'extends_table' => $customfields[$fieldName][$index]);
              // these should be in separate functions
              if($context == 'select'){
                $this->_select .= ", {$tableAlias}.{$table['fields'][$fieldName]['name']} as $fieldAlias ";
              }
              if($context == 'row_header'){
                $this->_select = "SELECT {$tableAlias}.{$table['fields'][$fieldName]['name']} as $fieldAlias ";
                $this->_groupByArray[] = $fieldAlias;
                $this->_groupBy = "GROUP BY $fieldAlias";
              }
              if($context  == 'column_header'){
                $this->addColumnAggregateSelect($table['fields'][$fieldName]['name'], $tableAlias, $table['fields'][$fieldName]);
              }
              // we compile the columns here but add them @ the end to preserve order
              $myColumns[$customfields[$fieldName][$index] . ":" . $fieldName] = array(
                  'name' => $customfields[$fieldName][$index] . "_" . $fieldName,
                  $customfields[$fieldName][$index] . "_" . $fieldName => array(
                      'title' => $title,
                      'type' => $table['fields'][$fieldName]['type'],
                  )
              );
            }
          }
        }
      }
    }
    return $myColumns;
  }

  function alterDisplay(&$rows) {
    parent::alterDisplay($rows);

    //THis is all generic functionality which can hopefully go into the parent class
    // it introduces the option of defining an alter display function as part of the column definition
    // @tod tidy up the iteration so it happens in this function

    if(!empty($this->_rollup ) && !empty($this->_groupBysArray)){
      $this->assignSubTotalLines($rows);
    }
    if(empty($rows)){
      return;
    }
    list($firstRow) = $rows;
    // no result to alter
    if (empty($firstRow)) {
      return;
    }
    $selectedFields = array_keys($firstRow);
    $alterfunctions = $altermap = array();
    foreach ($this->_columns as $tablename => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $field => $specs) {
          if (in_array($tablename . '_' . $field, $selectedFields)){
            if( array_key_exists('alter_display', $specs)) {
              $alterfunctions[$tablename . '_' . $field] = $specs['alter_display'];
              $altermap[$tablename . '_' . $field] = $field;
              $alterspecs[$tablename . '_' . $field] = null;
            }
            if( $this->_editableFields && array_key_exists('crm_editable', $specs)) {
              //id key array is what the array would look like if the ONLY group by field is our id field
              // in which case it should be editable - in any other group by scenario it shouldn't be
              $idKeyArray = array($this->_aliases[$specs['crm_editable']['id_table']] . "." . $specs['crm_editable']['id_field']);
              if(empty($this->_groupByArray) || $this->_groupByArray == $idKeyArray){
                $alterfunctions[$tablename . '_' . $field] = 'alterCrmEditable';
                $altermap[$tablename . '_' . $field] = $field;
                $alterspecs[$tablename . '_' . $field] = $specs['crm_editable'];
              }
            }
          }
        }
      }
    }
    if (empty($alterfunctions)) {
      // - no manipulation to be done
      return;
    }

    foreach ($rows as $index => & $row) {
      foreach ($row as $selectedfield => $value) {
        if (array_key_exists($selectedfield, $alterfunctions)) {
          $rows[$index][$selectedfield] = $this->$alterfunctions[$selectedfield]($value, $row, $selectedfield, $altermap[$selectedfield], $alterspecs[$selectedfield]);
        }
      }
    }
  }
  /*
   * Was hoping to avoid over-riding this - but it doesn't pass enough data to formatCustomValues by default
   * Am using it in a pretty hacky way to also cover the select box custom fields
   */
  function alterCustomDataDisplay(&$rows) {

    // custom code to alter rows having custom values
    if (empty($this->_customGroupExtends) && empty($this->_customGroupExtended)) {
      return;
    }
    $extends = $this->_customGroupExtends;
    foreach ($this->_customGroupExtended as $table => $spec){
      $extends = array_merge($extends, $spec['extends']);
    }

    $customFieldIds = array();
    foreach ($this->_params['fields'] as $fieldAlias => $value) {
      $fieldId = CRM_Core_BAO_CustomField::getKeyID($fieldAlias);
      if ($fieldId) {
        $customFieldIds[$fieldAlias] = $fieldId;
      }
    }
    if(!empty($this->_params['custom_fields']) && is_array($this->_params['custom_fields'])){
      foreach ($this->_params['custom_fields'] as $fieldAlias => $value) {
        $fieldName = explode(':', $value);
        $fieldId = CRM_Core_BAO_CustomField::getKeyID($fieldName[1]);
        if($fieldId){
          $customFieldIds[str_replace(':', '_', $value)] = $fieldId;
        }
      }
    }

    if (empty($customFieldIds)) {
      return;
    }

    $customFields = $fieldValueMap = array();
    $customFieldCols = array('column_name', 'data_type', 'html_type', 'option_group_id', 'id');

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
        if(!empty($this->_params['custom_fields'])){
          foreach ($customFieldIds as $custFieldName => $custFieldKey){
            if($dao->id == $custFieldKey){
              $customFields[$custFieldName] = $customFields[$dao->table_name . '_custom_' . $dao->id];
            }
          }
        }
      }
      if ($dao->option_group_id) {
        $fieldValueMap[$dao->option_group_id][$dao->value] = $dao->label;
      }
    }
    $dao->free();

    $entryFound = FALSE;
    foreach ($rows as $rowNum => $row) {
      foreach ($row as $tableCol => $val) {
        if (array_key_exists($tableCol, $customFields)) {
          $rows[$rowNum][$tableCol] = $this->formatCustomValues($val, $customFields[$tableCol], $fieldValueMap, $row);
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
  /*
   * We are overriding this function to apply crm-editable where appropriate
   * It would be more efficient if we knew the entity being extended (which the parent function
   * does know) but we want to avoid extending any functions we don't have to
   */
  function formatCustomValues($value, $customField, $fieldValueMap, $row) {
    if(!empty($this->_customGroupExtends) && count($this->_customGroupExtends) ==1){
      //lets only extend apply editability where only one entity extended
      // we can easily extend to contact combos
      list($entity) =  $this->_customGroupExtends ;
      $entity_table = strtolower('civicrm_' . $entity);
      $idKeyArray = array($this->_aliases[$entity_table] . '.id');
      if(empty($this->_groupByArray) || $this->_groupByArray == $idKeyArray){
        $entity_field = $entity_table . '_id';
        $entityID = $row[$entity_field];
      }
    }
    if (CRM_Utils_System::isNull($value)) {
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
          $retValue = (float)$value;
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
        'Text', 'TextArea'))) {
        $retValue = $value;
        if(!empty($entity_field)){
          $retValue = "<div id={$entity}-{$entityID} class='crm-entity'>
          <span class='crm-editable crmf-custom_{$customField['id']} crm-editable-enabled' data-action='create'>" . $value . "</span></div>";
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

  function assignSubTotalLines(&$rows){
     foreach ($rows as $index => & $row) {
       $orderFields = array_intersect_key(array_flip($this->_groupBysArray), $row);
     }
  }
/*
 * Function is over-ridden to support multiple add to groups
 */
  function add2group($groupID) {
    if (is_numeric($groupID) && isset($this->_aliases['civicrm_contact'])) {
      $contact = CRM_Utils_Array::value('btn_group_contact',$this->_submitValues,'civicrm_contact');
      $select = "SELECT DISTINCT {$this->_aliases[$contact]}.id AS addtogroup_contact_id";
      //    $select = str_ireplace('SELECT SQL_CALC_FOUND_ROWS ', $select, $this->_select);

      $sql = "{$select} {$this->_from} {$this->_where} AND {$this->_aliases[$contact]}.id IS NOT NULL {$this->_groupBy}  {$this->_having} {$this->_orderBy}";
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
  /*
   * Function is over-ridden to support multiple add to groups
  */
  function buildInstanceAndButtons() {
    CRM_Report_Form_Instance::buildForm($this);

    $label = $this->_id ? ts('Update Report') : ts('Create Report');

    $this->addElement('submit', $this->_instanceButtonName, $label);
    $this->addElement('submit', $this->_printButtonName, ts('Print Report'));
    $this->addElement('submit', $this->_pdfButtonName, ts('PDF'));

    if ($this->_id) {
      $this->addElement('submit', $this->_createNewButtonName, ts('Save As') . '...');
    }
    if ($this->_instanceForm) {
      $this->assign('instanceForm', TRUE);
    }

    $label = $this->_id ? ts('Print Report') : ts('Print Preview');
    $this->addElement('submit', $this->_printButtonName, $label);

    $label = $this->_id ? ts('PDF') : ts('Preview PDF');
    $this->addElement('submit', $this->_pdfButtonName, $label);

    $label = $this->_id ? ts('Export to CSV') : ts('Preview CSV');

    if ($this->_csvSupported) {
      $this->addElement('submit', $this->_csvButtonName, $label);
    }

    if (CRM_Core_Permission::check('administer Reports') && $this->_add2groupSupported) {
      $this->addElement('select', 'groups', ts('Group'),
          array('' => ts('- select group -')) + CRM_Core_PseudoConstant::staticGroup()
      );
      if(!empty($this->_add2GroupcontactTables) && is_array($this->_add2GroupcontactTables) && count($this->_add2GroupcontactTables > 1)){
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
  function getLineItemColumns() {
    return array(
      'civicrm_line_item' =>
      array(
        'dao' => 'CRM_Price_BAO_LineItem',
        'fields' =>
        array(
          'qty' =>
          array('title' => ts('Quantity'),
            'type' => CRM_Utils_Type::T_INT,
            'statistics' =>
            array('sum' => ts('Total Quantity Selected')),
          ),
          'unit_price' =>
          array('title' => ts('Unit Price'),
          ),
          'line_total' =>
          array('title' => ts('Line Total'),
            'type' => CRM_Utils_Type::T_MONEY,
            'statistics' =>
            array('sum' => ts('Total of Line Items')),
          ),
        ),
        'participant_count' =>
        array('title' => ts('Participant Count'),
          'statistics' =>
          array('sum' => ts('Total Participants')),
        ),
        'filters' =>
        array(
          'qty' =>
          array('title' => ts('Quantity'),
            'type' => CRM_Utils_Type::T_INT,
            'operator' => CRM_Report_Form::OP_INT,
          ),
        ),
        'group_bys' =>
        array(
          'price_field_id' =>
          array('title' => ts('Price Field'),
          ),
          'price_field_value_id' =>
          array('title' => ts('Price Field Option'),
          ),
          'line_item_id' =>
          array('title' => ts('Individual Line Item'),
            'name' => 'id',
          ),
        ),
      ),
    );
  }

  function getPriceFieldValueColumns() {
    return array(
      'civicrm_price_field_value' =>
      array(
        'dao' => 'CRM_Price_BAO_FieldValue',
        'fields' => array(
          'price_field_value_label' =>
          array('title' => ts('Price Field Value Label'),
            'name' => 'label',
          ),
        ),
        'filters' =>
        array(
          'price_field_value_label' =>
          array('title' => ts('Price Fields Value Label'),
            'type' => CRM_Utils_Type::T_STRING,
            'operator' => 'like',
            'name' => 'label',
          ),
        ),
        'order_bys' =>
        array(
          'label' =>
          array('title' => ts('Price Field Value Label'),
          ),
        ),
        'group_bys' =>
        //note that we have a requirement to group by label such that all 'Promo book' lines
        // are grouped together across price sets but there may be a separate need to group
        // by id so that entries in one price set are distinct from others. Not quite sure what
        // to call the distinction for end users benefit
        array(
          'price_field_value_label' =>
          array('title' => ts('Price Field Value Label'),
            'name' => 'label',
          ),
        ),
      ),
    );
  }

  function getPriceFieldColumns() {
    return array(
      'civicrm_price_field' =>
      array(
        'dao' => 'CRM_Price_BAO_Field',
        'fields' =>
        array(
          'price_field_label' =>
          array('title' => ts('Price Field Label'),
            'name' => 'label',
          ),
        ),
        'filters' =>
        array(
          'price_field_label' =>
          array('title' => ts('Price Field Label'),
            'type' => CRM_Utils_Type::T_STRING,
            'operator' => 'like',
            'name' => 'label',
          ),
        ),
        'order_bys' =>
        array(
          'price_field_label' =>
          array('title' => ts('Price Field Label'),
                'name' => 'label',
          ),
        ),
        'group_bys' =>
        array(
          'price_field_label' =>
          array('title' => ts('Price Field Label'),
            'name' => 'label',
          ),
        ),
      ),
    );
  }

  function getParticipantColumns() {
    static $_events = array();
    if (!isset($_events['all'])) {
      CRM_Core_PseudoConstant::populate($_events['all'], 'CRM_Event_DAO_Event', FALSE, 'title', 'is_active', "is_template IS NULL OR is_template = 0", 'end_date DESC');
    }
    return array(
      'civicrm_participant' =>
      array(
        'dao' => 'CRM_Event_DAO_Participant',
        'fields' =>
        array('participant_id' => array('title' => 'Participant ID'),
          'participant_record' => array(
            'name' => 'id',
            'title' => 'Participant ID',
          ),
          'event_id' => array('title' => ts('Event ID'),
            'type' => CRM_Utils_Type::T_STRING,
            'alter_display' => 'alterEventID',
          ),
          'status_id' => array('title' => ts('Event Participant Status'),
            'alter_display' => 'alterParticipantStatus',
          ),
          'role_id' => array('title' => ts('Role'),
            'alter_display' => 'alterParticipantRole',
          ),
          'participant_fee_level' => NULL,
          'participant_fee_amount' => NULL,
          'participant_register_date' => array('title' => ts('Registration Date')),
        ),
        'grouping' => 'event-fields',
        'filters' =>
        array(
          'event_id' => array('name' => 'event_id',
            'title' => ts('Event'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $_events['all'],
          ),
          'sid' => array(
            'name' => 'status_id',
            'title' => ts('Participant Status'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Event_PseudoConstant::participantStatus(NULL, NULL, 'label'),
          ),
          'rid' => array(
            'name' => 'role_id',
            'title' => ts('Participant Role'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Event_PseudoConstant::participantRole(),
          ),
          'participant_fee_level' =>  array(
            'name' => 'fee_level',
            'type' => CRM_Utils_Type::T_STRING,
            'operator' => 'like',
            'title' => ts('Participant Fee Level'),
           ),
          'participant_register_date' => array(
            'title' => ' Registration Date',
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
        ),
        'order_bys' =>
        array(
          'event_id' =>
          array('title' => ts('Event'), 'default_weight' => '1', 'default_order' => 'ASC'),
        ),
        'group_bys' =>
        array(
          'event_id' =>
          array('title' => ts('Event')),
        ),
      ),
    );
  }

  function getMembershipColumns() {
    return array(
      'civicrm_membership' => array(
        'dao' => 'CRM_Member_DAO_Membership',
        'grouping' => 'member-fields',
        'fields' => array(

          'membership_type_id' => array(
            'title' => 'Membership Type',
            'alter_display' => 'alterMembershipTypeID',

          ),
          'status_id' => array(
            'title' => 'Membership Status',
            'alter_display' => 'alterMembershipStatusID',
          ),
          'join_date' => NULL,
          'start_date' => array(
            'title' => ts('Current Cycle Start Date'),
          ),
          'end_date' => array(
            'title' => ts('Current Membership Cycle End Date'),
          ),

           'id' => array(
                'title' => 'Membership ID / Count',
                'name' => 'id',
                'statistics' =>
                array('count' => ts('Number of Memberships')),
            ),
        ),
        'group_bys' => array(
          'membership_type_id' => array(
            'title' => ts('Membership Type'),
          ),
          'status_id' => array(
                'title' => ts('Membership Status'),
            ),
           'end_date' => array(
               'title' => 'Current Membership Cycle End Date',
              'frequency' => TRUE,
               'type' => CRM_Utils_Type::T_DATE,
            )
        ),
        'filters' => array(
          'join_date' => array(
            'type' => CRM_Utils_Type::T_DATE,
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
            'membership_end_date' => array(
                'name' => 'end_date',
                'title' => 'Membership Expiry',
                'type' => CRM_Utils_Type::T_DATE,
                'operatorType' => CRM_Report_Form::OP_DATE,
            ),
            'membership_status_id' => array(
                'name' => 'status_id',
                'title' => 'Membership Status',
                'type' => CRM_Utils_Type::T_INT,
                'options' => CRM_Member_PseudoConstant::membershipStatus(),
                'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            ),
        ),
      ),
    );
  }

  function getMembershipTypeColumns() {
    require_once 'CRM/Member/PseudoConstant.php';
    return array(
      'civicrm_membership_type' => array(
        'dao' => 'CRM_Member_DAO_MembershipType',
        'grouping' => 'member-fields',
        'filters' => array(
          'gid' => array(
            'name' => 'id',
            'title' => ts('Membership Types'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'type' => CRM_Utils_Type::T_INT + CRM_Utils_Type::T_ENUM,
            'options' => CRM_Member_PseudoConstant::membershipType(),
          ),
        ),
      ),
    );
  }

  function getEventColumns() {
    return array(
      'civicrm_event' => array(
        'dao' => 'CRM_Event_DAO_Event',
        'fields' =>
        array(
          'id' => array(
          ),
          'title' => array('title' => ts('Event Title'),
            'crm_editable' => array(
                'id_table' => 'civicrm_event',
                'id_field' => 'id',
                'entity' => 'event',
             ),
          ),
          'event_type_id' => array('title' => ts('Event Type'),
            'alter_display' => 'alterEventType',
          ),
          'fee_label' => array('title' => ts('Fee Label')),
          'event_start_date' => array('title' => ts('Event Start Date'),
          ),
          'event_end_date' => array('title' => ts('Event End Date')),
          'max_participants' => array('title' => ts('Capacity'),
            'type' => CRM_Utils_Type::T_INT,
            'crm_editable' => array(
              'id_table' => 'civicrm_event',
              'id_field' => 'id',
              'entity' => 'event'
            ),
          ),
          'is_active' => array(
             'title' => ts('Is Active'),
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
                'crm_editable' => array(
                    'id_table' => 'civicrm_event',
                    'id_field' => 'id',
                    'entity' => 'event'
                ),
            ),
        ),
        'grouping' => 'event-fields',
        'filters' => array(
          'event_type_id' => array(
            'name' => 'event_type_id',
            'title' => ts('Event Type'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Core_OptionGroup::values('event_type'),
          ),
          'event_title' => array(
            'name' => 'title',
            'title' => ts('Event Title'),
            'operatorType' => CRM_Report_Form::OP_STRING,
          ),
          'event_start_date' => array(
            'title' => ts('Event start date'),
            'default_weight' => '1',
            'default_order' => 'ASC',
          )
        ),
        'order_bys' => array(
          'event_type_id' => array(
            'title' => ts('Event Type'),
            'default_weight' => '2',
            'default_order' => 'ASC',
          ),
          ),
        'group_bys' => array(
          'event_type_id' => array(
          'title' => ts('Event Type'),
          ),
        ),
      )
    );
  }


/**
 *
 * @param unknown_type $options
 * @return Ambigous <multitype:multitype:NULL  , multitype:multitype:string  multitype:NULL  multitype:string NULL  , multitype:multitype:string  multitype:NULL string  multitype:number string boolean multitype:string  NULL  multitype:NULL  multitype:string NULL  >
 */
  function getContributionColumns($options = array()) {
    $defaultOptions = array(
      'prefix' => '',
      'prefix_label' => '',
      'fields' => true,
      'group_by' => false,
      'order_by' => true,
      'filters' => true,
      'defaults' => array(
       ),
     );
    $this->setFinancialType();
    $options = array_merge($defaultOptions,$options);
    $pseudoMethod = $this->financialTypePseudoConstant;
    $fields =  array('civicrm_contribution' =>  array(
      'dao' => 'CRM_Contribute_DAO_Contribution',
      'grouping' => 'contribution-fields',
      )
    );

    if($options['fields']){
      $fields['civicrm_contribution']['fields'] =
        array(
          'contribution_id' => array(
            'title' => ts('Contribution ID'),
            'name' => 'id',
          ),
          $this->financialTypeField => array(
            'title' => ts($this->financialTypeLabel),
            'type' => CRM_Utils_Type::T_INT,
            'alter_display' => 'alterFinancialType',
          ),
          'payment_instrument_id' => array('title' => ts('Payment Instrument'),
            'type' => CRM_Utils_Type::T_INT,
            'alter_display' => 'alterPaymentType',
          ),
          'campaign_id' => array(
            'title' => ts('Campaign'),
            'type' => CRM_Utils_Type::T_INT,
            //@todo write this column
         //   'alter_display' => 'alterCampaign',
          ),
          'source' => array('title' => 'Contribution Source'),
          'trxn_id' => NULL,
          'receive_date' => array('default' => TRUE),
          'receipt_date' => NULL,
          'fee_amount' => NULL,
          'net_amount' => NULL,
          'total_amount' => array(
            'title' => ts('Amount'),
            'statistics' =>
              array('sum' => ts('Total Amount')),
            'type' => CRM_Utils_Type::T_MONEY,
          ),
       );
    }
    $fields['civicrm_contribution']['filters'] =
        array(
          'receive_date' => array(
              'operatorType' => CRM_Report_Form::OP_DATE
            ),
          'contribution_status_id' =>
          array('title' => ts('Contribution Status'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Contribute_PseudoConstant::contributionStatus(),
            'type' => CRM_Utils_Type::T_INT,
          ),
          $this->financialTypeField => array(
            'title' => ts($this->financialTypeLabel),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Contribute_PseudoConstant::$pseudoMethod(),
            'type' => CRM_Utils_Type::T_INT,
          ),
          'payment_instrument_id' => array(
            'title' => ts('Payment Type'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Contribute_PseudoConstant::paymentInstrument(),
            'type' => CRM_Utils_Type::T_INT,
          ),
          'total_amount' => array(
            'title' => ts('Contribution Amount'),
            'type' => CRM_Utils_Type::T_MONEY,
          ),
          'campaign_id' => array(
            'title' => ts('Campaign'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'type' => CRM_Utils_Type::T_INT,
            'options' => CRM_Campaign_BAO_Campaign::getCampaigns(),
          ),
/*          'contribution_is_test' =>  array(
            'type' => CRM_Report_Form::OP_INT,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'title' => ts("Contribution Mode"),
            'default' => 0,
            'name' => 'is_test',
            'hidden' => TRUE,
            'options' => array('0' => 'Live', '1' => 'Test'),
          ),
          */

        );
     if($options['order_by']){
      $fields['civicrm_contribution']['order_bys'] =
        array(
          'payment_instrument_id' =>
          array('title' => ts('Payment Instrument'),
          ),
          $this->financialTypeField => array(
            'title' => ts($this->financialTypeLabel),
          )
        );
     }
     if($options['group_by']){
       $fields['civicrm_contribution']['group_bys'] =
        array(
          $this->financialTypeField =>
          array('title' => ts($this->financialTypeLabel)),
          'payment_instrument_id' =>
          array('title' => ts('Payment Instrument')),
          'contribution_id' =>
          array('title' => ts('Individual Contribution'),
            'name' => 'id',
          ),
          'source' => array('title' => 'Contribution Source'),
        );
     }
     return $fields;
  }
  /**
   *
   * @param unknown_type $options
   * @return Ambigous <multitype:multitype:NULL  , multitype:multitype:string  multitype:NULL  multitype:string NULL  , multitype:multitype:string  multitype:NULL string  multitype:number string boolean multitype:string  NULL  multitype:NULL  multitype:string NULL  >
   */
  function getContributionSummaryColumns($options = array()) {
    $defaultOptions = array(
      'prefix' => '',
      'prefix_label' => '',
      'fields' => true,
      'group_by' => false,
      'order_by' => true,
      'filters' => true,
      'defaults' => array(
      ),
    );
    $options = array_merge($defaultOptions,$options);
    $pseudoMethod = $this->financialTypePseudoConstant;
    $fields =  array('civicrm_contribution_summary' . $options['prefix'] =>  array(
      'dao' => 'CRM_Contribute_DAO_Contribution',
      'grouping' => 'contribution-fields',
    )
    );

    if($options['fields']){
      $fields['civicrm_contribution_summary' . $options['prefix']]['fields'] =
      array(
        'contributionsummary'. $options['prefix'] => array(
          'title' => $options['prefix_label'] . ts('Contribution Details'),
          'default' => TRUE,
          'required' => TRUE,
        ),

      );
    }
    return $fields;
  }

  function getContactColumns($options = array()) {

    $defaultOptions = array(
      'prefix' => '',
      'prefix_label' => '',
      'group_by' => true,
      'order_by' => true,
      'filters' => true,
      'fields' => true,
      'custom_fields' => array('Individual', 'Contact', 'Organization'),
      'defaults' => array(
        'country_id' => TRUE
      ),
     );

    $options = array_merge($defaultOptions,$options);

    $contactFields = array(
      $options['prefix'] . 'civicrm_contact' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'name' => 'civicrm_contact',
        'alias' => $options['prefix'] . 'civicrm_contact',
        'grouping' => $options['prefix'] . 'contact-fields',
      )
    );
    if(!empty($options['fields'])){
      $contactFields[$options['prefix'] . 'civicrm_contact']['fields'] =  array(
          $options['prefix'] . 'display_name' => array(
            'name' => 'display_name',
            'title' => ts($options['prefix_label'] . 'Contact Name'),
          ),
          $options['prefix'] . 'id' => array(
            'name' => 'id',
            'title' => ts($options['prefix_label'] . 'Contact ID'),
            'alter_display' => 'alterContactID',
            'type' => CRM_Utils_Type::T_INT,
          ),
          'first_name' => array(
            'title' => ts($options['prefix_label'] . 'First Name'),
          ),
          'middle_name' => array(
            'title' => ts($options['prefix_label'] . 'Middle Name'),
          ),
          'last_name' => array(
            'title' => ts($options['prefix_label'] . 'Last Name'),
          ),
          'nick_name' => array(
            'title' => ts($options['prefix_label'] . 'Nick Name'),
            'alter_display' => 'alterNickName',
          ),
        );
    }

    if(!empty($options['filters'])){
      $contactFields[$options['prefix'] . 'civicrm_contact']['filters'] =  array(
          $options['prefix'] . 'id' => array(
            'title' => ts($options['prefix_label'] . 'Contact ID'),
            'type' => CRM_Report_Form::OP_INT,
            'name' => 'id',
          )
          ,
          $options['prefix'] . 'sort_name' => array(
            'title' => ts($options['prefix_label'] . 'Contact Name'),
            'name' => 'sort_name',
          ),
          $options['prefix'] . 'contact_type' => array(
           'title' => ts($options['prefix_label'] . 'Contact Type'),
           'name' => 'contact_type',
           'operatorType' => CRM_Report_Form::OP_MULTISELECT,
           'options' => CRM_Contribute_PseudoConstant::contactType(),
          ),
      );
    }

    if(!empty($options['order_by'])){
      $contactFields[$options['prefix'] . 'civicrm_contact']['order_bys'] =  array(
          'sort_name' => array(
            'title' => ts($options['prefix_label'] . 'Last Name, First Name'),
            'default' => '1',
            'default_weight' => '0',
            'default_order' => 'ASC',
          ),
        );
    }

    if(!empty($options['custom_fields'])){
      $this->_customGroupExtended[$options['prefix'] . 'civicrm_contact'] = array(
        'extends' => $options['custom_fields'],
        'title' => $options['prefix_label'],
      );
    }
    return $contactFields;
  }

  function getCaseColumns() {
    return array(
      'civicrm_case' => array(
        'dao' => 'CRM_Case_DAO_Case',
        'fields' => array(
          'case_id' => array(
            'title' => ts('Case ID'),
            'required' => false,
            'name' => 'id',
          ),
          'subject' => array(
            'title' => ts('Case Subject'),
            'default' => true
          ),
          'case_status_id' => array(
            'title' => ts('Status'),
            'default' => true,
            'name' => 'status_id',
          ),
          'case_type_id' => array(
            'title' => ts('Case Type'),
            'default' => true
          ),
          'case_start_date' => array(
            'title' => ts('Case Start Date'),
            'name' => 'start_date',
            'default' => true
          ),
          'case_end_date' => array(
            'title' => ts('Case End Date'),
            'name' => 'end_date',
            'default' => true
          ),
          'case_duration' => array(
            'name' => 'duration',
            'title' => ts('Duration (Days)'),
            'default' => false
          ),
          'case_is_deleted' => array(
            'name' => 'is_deleted',
            'title' => ts('Case Deleted?'),
            'default' => false,
            'type' => CRM_Utils_Type::T_INT
          )
        ),
        'filters' => array(
          'case_start_date' => array(
            'title' => ts('Case Start Date'),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
            'name' => 'start_date',
          ),
          'case_end_date' => array(
            'title' => ts('Case End Date'),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
            'name' => 'end_date'
          ),
          'case_type_id' => array(
            'title' => ts('Case Type'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $this->case_types
          ),
          'case_status_id' => array(
            'title' => ts('Case Status'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $this->case_statuses,
            'name' => 'status_id'
          ),
          'case_is_deleted' => array(
            'title' => ts('Case Deleted?'),
            'type' => CRM_Report_Form::OP_INT,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'options' => $this->deleted_labels,
            'name' => 'is_deleted'
          )
        )
      )
    );
  }
  /*
   *
   */
  function getPhoneColumns($options = array()){
    $defaultOptions = array(
        'prefix' => '',
        'prefix_label' => '',
        'group_by' => false,
        'order_by' => true,
        'filters' => true,
        'defaults' => array(
        ),
        'subquery' => true,
    );

    $options = array_merge($defaultOptions,$options);

    $fields = array(
        $options['prefix'] . 'civicrm_phone' => array(
            'dao'    => 'CRM_Core_DAO_Phone',
            'fields' => array(
                $options['prefix'] . 'phone' => array(
                    'title' => ts($options['prefix_label'] . 'Phone'),
                    'name'  => 'phone'
                ),
            ),
        ),
    );
    if($options['subquery']){
      $fields[$options['prefix'] . 'civicrm_phone']['fields'][$options['prefix'] . 'phone']['alter_display'] = 'alterPhoneGroup';
    }
    return $fields;
  }

  /*
   * Get email columns
   * @param array $options column options
   */
  function getEmailColumns($options = array()){
    $defaultOptions = array(
        'prefix' => '',
        'prefix_label' => '',
        'group_by' => false,
        'order_by' => true,
        'filters' => true,
        'defaults' => array(
            'country_id' => TRUE
        ),
    );

    $options = array_merge($defaultOptions,$options);

    $fields = array(
      $options['prefix'] . 'civicrm_email' => array(
        'dao'    => 'CRM_Core_DAO_Email',
        'fields' => array(
          $options['prefix'] . 'email' => array(
            'title' => ts($options['prefix_label'] . 'Email'),
            'name'  => 'email'
          ),
        ),
      ),
    );
    return $fields;
  }

  function getRelationshipColumns($options = array()){
    $defaultOptions = array(
      'prefix' => '',
      'prefix_label' => '',
      'group_by' => false,
      'order_by' => true,
      'filters' => true,
      'defaults' => array(
       ),
    );

    $options = array_merge($defaultOptions,$options);

    $fields = array(
        $options['prefix'] . 'civicrm_relationship' =>
          array( 'dao'       => 'CRM_Contact_DAO_Relationship',
              'fields'    =>
              array( 'relationship_start_date' =>
                  array( 'title'     => ts( 'Relationship Start Date' ),
                      'name' => 'start_date'
                  ),
                  'end_date'   =>
                  array( 'title'     => ts( 'Relationship End Date' ),
                  ),
                  'description'   =>
                  array( 'title'     => ts( 'Description' ),
                  ),
              ),
              'filters'   =>
              array('is_active'=>
                  array( 'title'        => ts( 'Relationship Status' ),
                      'operatorType' => CRM_Report_Form::OP_MULTISELECT,
                      'options'      =>
                      array( ''  => '- Any -',
                          1   => 'Active',
                          0   => 'Inactive',
                      ),
                      'type'     => CRM_Utils_Type::T_INT ),
                  'relationship_type_id' =>
                  array( 'title'        => ts( 'Relationship Type' ),
                      'operatorType' => CRM_Report_Form::OP_MULTISELECT,
                      'options'      =>
                      array( ''     => '- any relationship type -') +
                      CRM_Contact_BAO_Relationship::getContactRelationshipType( null, 'null', null, null, true),
                      'type'        => CRM_Utils_Type::T_INT
                  ),

              ),

              'grouping'  => 'relation-fields',
          ),
    );
    return $fields;
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
  function getAddressColumns($options = array()) {
    $defaultOptions = array(
      'prefix' => '',
      'prefix_label' => '',
      'fields' => true,
      'group_by' => false,
      'order_by' => true,
      'filters' => true,
      'defaults' => array(
        'country_id' => TRUE
      ),
     );

    $options = array_merge($defaultOptions,$options);

    $addressFields = array(
      $options['prefix'] . 'civicrm_address' => array(
        'dao' => 'CRM_Core_DAO_Address',
        'name' => 'civicrm_address',
        'alias' => $options['prefix'] . 'civicrm_address',
        'fields' => array(
          $options['prefix'] . 'name' => array(
            'title' => ts($options['prefix_label'] . 'Address Name'),
            'default' => CRM_Utils_Array::value('name', $options['defaults'], FALSE),
            'name' => 'name',
          ),
          $options['prefix'] . 'street_address' => array(
            'title' => ts($options['prefix_label'] . 'Street Address'),
            'default' => CRM_Utils_Array::value('street_address', $options['defaults'], FALSE),
            'name' => 'street_address',
          ),
          $options['prefix'] . 'supplemental_address_1' => array(
            'title' => ts($options['prefix_label'] . 'Supplementary Address Field 1'),
            'default' => CRM_Utils_Array::value('supplemental_address_1', $options['defaults'], FALSE),
            'name' => 'supplemental_address_1',
          ),
          $options['prefix'] . 'supplemental_address_2' => array(
            'title' => ts($options['prefix_label'] . 'Supplementary Address Field 2'),
            'default' => CRM_Utils_Array::value('supplemental_address_2', $options['defaults'], FALSE),
            'name' => 'supplemental_address_2',
          ),
          $options['prefix'] . 'street_number' => array(
            'name' => 'street_number',
            'title' => ts($options['prefix_label'] . 'Street Number'),
            'type' => 1,
            'default' => CRM_Utils_Array::value('street_number', $options['defaults'], FALSE),
            'name' => 'street_number',
          ),
          $options['prefix'] . 'street_name' => array(
            'name' => 'street_name',
            'title' => ts($options['prefix_label'] . 'Street Name'),
            'type' => 1,
            'default' => CRM_Utils_Array::value('street_name', $options['defaults'], FALSE),
            'name' => 'street_name',
          ),
          $options['prefix'] . 'street_unit' => array(
            'name' => 'street_unit',
            'title' => ts($options['prefix_label'] . 'Street Unit'),
            'type' => 1,
            'default' => CRM_Utils_Array::value('street_unit', $options['defaults'], FALSE),
            'name' => 'street_unit',
          ),
          $options['prefix'] . 'city' => array(
            'title' => ts($options['prefix_label'] . 'City'),
            'default' => CRM_Utils_Array::value('city', $options['defaults'], FALSE),
            'name' => 'city',
          ),
          $options['prefix'] . 'postal_code' => array(
            'title' => ts($options['prefix_label'] . 'Postal Code'),
            'default' => CRM_Utils_Array::value('postal_code', $options['defaults'], FALSE),
            'name' => 'postal_code',
          ),
          $options['prefix'] . 'county_id' => array(
            'title' => ts($options['prefix_label'] . 'County'),
            'default' => CRM_Utils_Array::value('county_id', $options['defaults'], FALSE),
            'alter_display' => 'alterCountyID',
            'name' => 'county_id',
          ),
          $options['prefix'] . 'state_province_id' => array(
            'title' => ts($options['prefix_label'] . 'State/Province'),
            'default' => CRM_Utils_Array::value('state_province_id', $options['defaults'], FALSE),
            'alter_display' => 'alterStateProvinceID',
            'name' => 'state_province_id',
          ),
          $options['prefix'] . 'country_id' => array(
            'title' => ts($options['prefix_label'] . 'Country'),
            'default' => CRM_Utils_Array::value('country_id', $options['defaults'], FALSE),
            'alter_display' => 'alterCountryID',
            'name' => 'country_id',
          ),
        ),
        'grouping' => 'location-fields',
      ),
    );

    if ($options['filters']) {
      $addressFields[$options['prefix'] .'civicrm_address']['filters'] = array(
        $options['prefix'] . 'street_number' => array(
          'title' => ts($options['prefix_label'] . 'Street Number'),
          'type' => 1,
          'name' => 'street_number',
        ),
        $options['prefix'] . 'street_name' => array(
          'title' => ts($options['prefix_label'] . 'Street Name'),
          'name' => $options['prefix'] . 'street_name',
          'operator' => 'like',
        ),
        $options['prefix'] . 'postal_code' => array(
          'title' => ts($options['prefix_label'] . 'Postal Code'),
          'type' => 1,
          'name' => 'postal_code',
        ),
        $options['prefix'] . 'city' => array(
          'title' => ts($options['prefix_label'] . 'City'),
          'operator' => 'like',
          'name' => 'city',
        ),
        $options['prefix'] . 'county_id' => array(
          'name' => 'county_id',
          'title' => ts($options['prefix_label'] . 'County'),
          'type' => CRM_Utils_Type::T_INT,
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'options' => CRM_Core_PseudoConstant::county(),
        ),
        $options['prefix'] . 'state_province_id' => array(
          'name' => 'state_province_id',
          'title' => ts($options['prefix_label'] . 'State/Province'),
          'type' => CRM_Utils_Type::T_INT,
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'options' => CRM_Core_PseudoConstant::stateProvince(),
        ),
        $options['prefix'] . 'country_id' => array(
          'name' => 'country_id',
          'title' => ts($options['prefix_label'] . 'Country'),
          'type' => CRM_Utils_Type::T_INT,
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'options' => CRM_Core_PseudoConstant::country(),
        ),
      );
    }

    if ($options['order_by']) {
      $addressFields[$options['prefix'] . 'civicrm_address']['order_bys'] = array(
        $options['prefix'] . 'street_name' => array(
          'title' => ts($options['prefix_label'] . 'Street Name'),
          'name' => 'street_name',
        ),
        $options['prefix'] . 'street_number' => array(
          'title' => ts($options['prefix_label'] . 'Odd / Even Street Number'),
          'name' => 'street_number',
        ),
        $options['prefix'] . 'street_address' => array(
          'title' => ts($options['prefix_label'] . 'Street Address'),
          'name' => 'street_address',
        ),
        $options['prefix'] . 'city' => array(
          'title' => ts($options['prefix_label'] . 'City'),
          'name' => 'city',
        ),
        $options['prefix'] . 'postal_code' => array(
          'title' => ts($options['prefix_label'] . 'Post Code'),
          'name' => 'postal_code',
        ),
      );
    }

    if ($options['group_by']) {
      $addressFields['civicrm_address']['group_bys'] = array(
        $options['prefix'] . 'street_address' => array(
          'title' => ts($options['prefix_label'] . 'Street Address'),
          'name' => 'street_address',
        ),
        $options['prefix'] . 'city' => array(
          'title' => ts($options['prefix_label'] . 'City'),
          'name' => 'city',
        ),
        $options['prefix'] . 'postal_code' => array(
          'title' => ts($options['prefix_label'] . 'Post Code'),
          'name' => 'postal_code',
        ),
        $options['prefix'] . 'state_province_id' => array(
          'title' => ts($options['prefix_label'] . 'State/Province'),
          'name' => 'state_province_id',
        ),
        $options['prefix'] . 'country_id' => array(
          'title' => ts($options['prefix_label'] . 'Country'),
          'name' => 'country_id',
        ),
        $options['prefix'] . 'county_id' => array(
          'title' => ts($options['prefix_label'] . 'County'),
          'name' => 'county_id',
        ),
      );
    }
    return $addressFields;
  }
  function getTagColumns($options = array()){
    $defaultOptions = array(
        'prefix' => '',
        'prefix_label' => '',
        'fields' => true,
        'group_by' => false,
        'order_by' => true,
        'filters' => true,
        'defaults' => array(
            'country_id' => TRUE
        ),
    );

    $options = array_merge($defaultOptions,$options);

    $columns = array(
    $options['prefix'] . 'civicrm_tag' => array(
        'grouping' => 'contact-fields',
        'alias' => $options['prefix'] . 'entity_tag',
        'dao' => 'CRM_Core_DAO_EntityTag',
        'name' => 'civicrm_tag',
      )
    );
    if($options['fields']){
      $columns['civicrm_tag']['fields'] = array(
        'tag_name' => array(
          'name' => 'name',
          'title' => 'Tags associated with this person',
        )
      );
    }
    return $columns;
  }

  /*
   * Function to get Activity Columns
  * @param array $options column options
  */
  function getLatestActivityColumns($options){
    $defaultOptions = array(
      'prefix' => '',
      'prefix_label' => '',
      'fields' => true,
      'group_by' => false,
      'order_by' => true,
      'filters' => true,
      'defaults' => array(
        'country_id' => TRUE
      ),
    );
    $options = array_merge($defaultOptions,$options);
    $activityFields = array(
      'civicrm_activity' => array(
        'grouping' => 'activity-fields',
        'alias' => 'activity',
        'dao' => 'CRM_Activity_DAO_Activity',
      )
    );
    $activityFields['civicrm_activity']['fields'] = array(
      'activity_type_id' =>
      array(
        'title' => ts('Latest Activity Type'),
        'default' => TRUE,
        'type' => CRM_Utils_Type::T_STRING,
        'alter_display' => 'alterActivityType',
      ),
      'activity_date_time' =>
        array('title' => ts('Latest Activity Date'),
        'default' => TRUE,
      ),
    );
    return $activityFields;
  }



  /*
   * Function to get Activity Columns
   * @param array $options column options
   */
  function getActivityColumns($options = array()){
    $defaultOptions = array(
        'prefix' => '',
        'prefix_label' => '',
        'fields' => true,
        'group_by' => false,
        'order_by' => true,
        'filters' => true,
        'defaults' => array(
            'country_id' => TRUE
        ),
    );

    $options = array_merge($defaultOptions,$options);

    $activityFields = array(
      'civicrm_activity' => array(
        'grouping' => 'activity-fields',
        'alias' => 'activity',
        'dao' => 'CRM_Activity_DAO_Activity',
       )
    );
    $activityFields['civicrm_activity']['fields'] = array(
           'id' =>
            array(
                'no_display' => TRUE,
                'required' => TRUE,
            ),
            'source_record_id' =>
            array(
                'no_display' => TRUE,
                'required' => FALSE,
            ),
            'activity_type_id' =>
            array(
              'title' => ts('Activity Type'),
              'default' => TRUE,
              'type' => CRM_Utils_Type::T_STRING,
              'alter_display' => 'alterActivityType',
            ),
            'activity_subject' =>
            array('title' => ts('Subject'),
                'default' => TRUE,
            ),
            'source_contact_id' =>
            array(
                'no_display' => TRUE,
                'required' => FALSE,
            ),
            'activity_date_time' =>
            array('title' => ts('Activity Date'),
                'default' => TRUE,
            ),
            'activity_status_id' => array(
              'title' => ts('Activity Status'),
              'default' => TRUE,
              'name' => 'status_id',
              'type' => CRM_Utils_Type::T_STRING,
              'alter_display' => 'alterActivityStatus',
            ),
            'duration' =>
            array('title' => ts('Duration'),
                'type' => CRM_Utils_Type::T_INT,
            ),
         )
    ;
    if($options['filters']){
      $activityFields['civicrm_activity']['filters'] =
        array(
            'activity_date_time' =>
            array(
                'default' => 'this.month',
                'operatorType' => CRM_Report_Form::OP_DATE,
            ),
            'activity_subject' =>
            array('title' => ts('Activity Subject')),
            'activity_type_id' =>
            array('title' => ts('Activity Type'),
                'operatorType' => CRM_Report_Form::OP_MULTISELECT,
                'options' => CRM_Core_PseudoConstant::activityType(True, True),
            ),
            'status_id' =>
            array('title' => ts('Activity Status'),
                'operatorType' => CRM_Report_Form::OP_MULTISELECT,
                'options' => CRM_Core_PseudoConstant::activityStatus(),
            ),
            'activity_is_current_revision' =>  array(
                'type' => CRM_Report_Form::OP_INT,
                'operatorType' => CRM_Report_Form::OP_SELECT,
                'title' => ts("Current Revision"),
                'default' => 1,
                'name' => 'is_current_revision',
                'options' => array('0' => 'No', '1' => 'Yes'),
            )
    );
    }
    $activityFields['civicrm_activity']['order_bys'] =
        array(
          'activity_date_time' => array(
             'title' => ts('Activity Date')),
            'activity_type_id' =>
            array('title' => ts('Activity Type')),


    );
    return $activityFields;
  }
  /*
* Get Information about advertised Joins
*/
  function getAvailableJoins() {
    return array(
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
      'lineItem_from_contribution' => array(
        'leftTable' => 'civicrm_contribution',
        'rightTable' => 'civicrm_line_item',
        'callback' => 'joinLineItemFromContribution',
      ),
      'lineItem_from_membership' => array(
        'leftTable' => 'civicrm_membership',
        'rightTable' => 'civicrm_line_item',
        'callback' => 'joinLineItemFromMembership',
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
      'event_from_participant' => array(
        'leftTable' => 'civicrm_participant',
        'rightTable' => 'civicrm_event',
        'callback' => 'joinEventFromParticipant',
      ),
      'address_from_contact' => array(
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_address',
        'callback' => 'joinAddressFromContact',
      ),
      'email_from_contact' => array(
        'leftTable' => 'civicrm_contact',
        'rightTable' => 'civicrm_email',
        'callback' => 'joinEmailFromContact',
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
    );
  }

  /*
* Add join from contact table to address. Prefix will be added to both tables
* as it's assumed you are using it to get address of a secondary contact
* @param string $prefix prefix to add to table names
* @param array $extra extra join parameters
* @return bool true or false to denote whether extra filters can be appended to join
*/
  function joinAddressFromContact( $prefix = '', $extra = array()) {

    $this->_from .= " LEFT JOIN civicrm_address {$this->_aliases[$prefix . 'civicrm_address']}
    ON {$this->_aliases[$prefix . 'civicrm_address']}.contact_id = {$this->_aliases[$prefix . 'civicrm_contact']}.id
    ";
    return true;
  }

  /*
  * Add join from contact table to email. Prefix will be added to both tables
  * as it's assumed you are using it to get address of a secondary contact
*/
  function joinEmailFromContact( $prefix = '') {
    $this->_from .= " LEFT JOIN civicrm_email {$this->_aliases[$prefix . 'civicrm_email']}
ON {$this->_aliases[$prefix . 'civicrm_email']}.contact_id = {$this->_aliases[$prefix . 'civicrm_contact']}.id";
  }

  /*
   * Add join from contact table to phone. Prefix will be added to both tables
  * as it's assumed you are using it to get address of a secondary contact
  */
  function joinPhoneFromContact( $prefix = '') {
    $this->_from .= " LEFT JOIN civicrm_phone {$this->_aliases[$prefix . 'civicrm_phone']}
    ON {$this->_aliases[$prefix . 'civicrm_phone']}.contact_id = {$this->_aliases[$prefix . 'civicrm_contact']}.id";
    }

/*
 *
 */
  function joinEntityTagFromContact($prefix = '') {
    static $tmpTableName = null;
    if(empty($tmpTableName)){
      $tmpTableName = 'civicrm_report_temp_entity_tag' . date('his');
    }
    $sql = "CREATE {$this->_temporary} TABLE $tmpTableName
    (
    `contact_id` INT(10) NULL,
    `name` varchar(255) NULL,
    PRIMARY KEY (`contact_id`)
    )
    ENGINE=MEMORY;";

    CRM_Core_DAO::executeQuery($sql);
    $sql = " INSERT INTO $tmpTableName
      SELECT entity_id AS contact_id, GROUP_CONCAT(name SEPARATOR ', ') as name
      FROM civicrm_entity_tag et
      LEFT JOIN civicrm_tag t ON et.tag_id = t.id
      GROUP BY et.entity_id
    ";

    CRM_Core_DAO::executeQuery($sql);
    $this->_from .= "
    LEFT JOIN $tmpTableName {$this->_aliases[$prefix . 'civicrm_tag']}
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
  function joinLatestActivityFromContact(){
    static $tmpTableName = null;
    if(empty($tmpTableName)){

    $tmpTableName = 'civicrm_report_temp_lastestActivity' . date('his');
    $targetTable = 'civicrm_report_temp_target' . date('his');
    $assigneeTable = 'civicrm_report_temp_assignee' . date('his');
    $sql = "CREATE {$this->_temporary} TABLE $tmpTableName
   (
    `contact_id` INT(10) NULL,
    `id` INT(10) NULL,
    `activity_type_id` VARCHAR(50) NULL,
    `activity_date_time` DATETIME NULL,
    PRIMARY KEY (`contact_id`)
  )
  ENGINE=HEAP;";
    CRM_Core_DAO::executeQuery($sql);
    $sql = "CREATE  TABLE $assigneeTable
    (
    `contact_id` INT(10) NULL,
    `id` INT(10) NULL,
    `activity_type_id` VARCHAR(50) NULL,
    `activity_date_time` DATETIME NULL,
    PRIMARY KEY (`contact_id`)
    )
    ENGINE=HEAP;";
    CRM_Core_DAO::executeQuery($sql);
    $sql = "CREATE  TABLE $targetTable
     (
    `contact_id` INT(10) NULL,
    `id` INT(10) NULL,
    `activity_type_id` VARCHAR(50) NULL,
    `activity_date_time` DATETIME NULL,
    PRIMARY KEY (`contact_id`)
    )
    ENGINE=HEAP;";
  CRM_Core_DAO::executeQuery($sql);
  $sql=
  "REPLACE INTO $tmpTableName
   SELECT source_contact_id as contact_id, max(id), activity_type_id, activity_date_time
   FROM civicrm_activity
   GROUP BY source_contact_id,  activity_date_time DESC
  ";
  CRM_Core_DAO::executeQuery($sql);

  $sql = "REPLACE INTO $assigneeTable
  SELECT assignee_contact_id as contact_id, activity_id as id, a.activity_type_id, a.activity_date_time
  FROM civicrm_activity_assignment aa
  LEFT JOIN civicrm_activity a on a.id = aa.activity_id
  LEFT JOIN $tmpTableName tmp ON tmp.contact_id = aa.assignee_contact_id
  WHERE (a.activity_date_time < tmp.activity_date_time OR tmp.activity_date_time IS NULL)
  GROUP BY assignee_contact_id,  a.activity_date_time DESC
  ";
  CRM_Core_DAO::executeQuery($sql);

  $sql = "REPLACE INTO $tmpTableName
  SELECT * FROM $assigneeTable
  ";
  CRM_Core_DAO::executeQuery($sql);

  $sql = "REPLACE INTO $targetTable
  SELECT target_contact_id as contact_id, activity_id as id, a.activity_type_id, a.activity_date_time
  FROM civicrm_activity_target aa
  LEFT JOIN civicrm_activity a on a.id = aa.activity_id
  LEFT JOIN $tmpTableName tmp ON tmp.contact_id = aa.target_contact_id
  WHERE (a.activity_date_time < tmp.activity_date_time OR tmp.activity_date_time IS NULL)
  GROUP BY target_contact_id,  a.activity_date_time DESC
  ";

  CRM_Core_DAO::executeQuery($sql);
  $sql = "REPLACE INTO $tmpTableName
  SELECT * FROM $targetTable
  ";
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
  /*
* Define join from line item table to participant table
*/
  function joinParticipantFromLineItem() {
    $this->_from .= " LEFT JOIN civicrm_participant {$this->_aliases['civicrm_participant']}
ON ( {$this->_aliases['civicrm_line_item']}.entity_id = {$this->_aliases['civicrm_participant']}.id
AND {$this->_aliases['civicrm_line_item']}.entity_table = 'civicrm_participant')
";
  }

  /*
* Define join from line item table to Membership table. Seems to be still via contribution
* as the entity. Have made 'inner' to restrict does that make sense?
*/
  function joinMembershipFromLineItem() {
    $this->_from .= " INNER JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
ON ( {$this->_aliases['civicrm_line_item']}.entity_id = {$this->_aliases['civicrm_contribution']}.id
AND {$this->_aliases['civicrm_line_item']}.entity_table = 'civicrm_contribution')
LEFT JOIN civicrm_membership_payment pp
ON {$this->_aliases['civicrm_contribution']}.id = pp.contribution_id
LEFT JOIN civicrm_membership {$this->_aliases['civicrm_membership']}
ON pp.membership_id = {$this->_aliases['civicrm_membership']}.id
";
  }
  /**
   * Define join from Contact to Contribution table
   */
  function joinContributionFromContact() {
    if(empty($this->_aliases['civicrm_contact'])){
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

  /*
* Define join from Membership to Contribution table
*/
  function joinContributionFromMembership() {
    $this->_from .= " LEFT JOIN civicrm_membership_payment pp
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

  function joinContributionFromLineItem() {
    $temporary = $this->_temporary;
    $tempTable = 'civicrm_report_temp_line_items' . rand(1, 10000);
    $createTablesql = "
    CREATE  $temporary TABLE $tempTable (
    `lid` INT(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Line Item',
    `contid` INT(10) UNSIGNED NULL DEFAULT '0' COMMENT 'Contribution ID',
    INDEX `ContributionId` (`contid`),
    INDEX `LineItemId` (`lid`)
    )
    COLLATE='utf8_unicode_ci'
    ENGINE=InnoDB;";

    $insertContributionRecordsSql = "
     INSERT INTO $tempTable
     SELECT line_item_civireport.id as lid, contribution_civireport_direct.id
     FROM civicrm_line_item line_item_civireport
     LEFT JOIN civicrm_contribution contribution_civireport_direct
     ON (line_item_civireport.entity_id = contribution_civireport_direct.id AND line_item_civireport.entity_table = 'civicrm_contribution')
     WHERE contribution_civireport_direct.id IS NOT NULL
     ";

    $insertParticipantRecordsSql = "
      INSERT INTO $tempTable
      SELECT line_item_civireport.id as lid, contribution_civireport.id
      FROM civicrm_line_item line_item_civireport
      LEFT JOIN civicrm_participant participant_civireport
      ON (line_item_civireport.entity_id = participant_civireport.id AND line_item_civireport.entity_table = 'civicrm_participant')
      LEFT JOIN civicrm_participant_payment pp
      ON participant_civireport.id = pp.participant_id
      LEFT JOIN civicrm_contribution contribution_civireport
      ON pp.contribution_id = contribution_civireport.id
      WHERE contribution_civireport.id IS NOT NULL
    ";

    $insertMembershipRecordSql = "
      INSERT INTO $tempTable
      SELECT line_item_civireport.id as lid,contribution_civireport.id
      FROM civicrm_line_item line_item_civireport
      LEFT JOIN civicrm_membership membership_civireport
      ON (line_item_civireport.entity_id =membership_civireport.id AND line_item_civireport.entity_table = 'civicrm_membership')
      LEFT JOIN civicrm_membership_payment pp
      ON membership_civireport.id = pp.membership_id
      LEFT JOIN civicrm_contribution contribution_civireport
      ON pp.contribution_id = contribution_civireport.id
      WHERE contribution_civireport.id IS NOT NULL
    ";
    CRM_Core_DAO::executeQuery($createTablesql);
    CRM_Core_DAO::executeQuery($insertContributionRecordsSql);
    CRM_Core_DAO::executeQuery($insertParticipantRecordsSql);
    CRM_Core_DAO::executeQuery($insertMembershipRecordSql);
    $this->_from .= "
      LEFT JOIN $tempTable as line_item_mapping
      ON line_item_mapping.lid = {$this->_aliases['civicrm_line_item']}.id
      LEFT JOIN civicrm_contribution as {$this->_aliases['civicrm_contribution']}
      ON line_item_mapping.contid = {$this->_aliases['civicrm_contribution']}.id
    ";
  }

  function joinLineItemFromContribution() {
    $temporary = $this->_temporary;// because we like to change this for debugging
    $tempTable = 'civicrm_report_temp_line_item_map' . rand(1, 10000);
    $createTablesql = "
    CREATE  $temporary TABLE $tempTable (
    `contid` INT(10) UNSIGNED NULL DEFAULT '0' COMMENT 'Contribution ID',
    `lid` INT(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Line Item',
    INDEX `ContributionId` (`contid`),
    INDEX `LineItemId` (`lid`)
    )
    COLLATE='utf8_unicode_ci'
    ENGINE=InnoDB;";

    $insertContributionRecordsSql = "
    INSERT INTO $tempTable
    SELECT contribution_civireport_direct.id AS contid, line_item_civireport.id as lid
    FROM civicrm_contribution contribution_civireport_direct
    LEFT JOIN civicrm_line_item line_item_civireport ON (line_item_civireport.line_total > 0 AND line_item_civireport.entity_id = contribution_civireport_direct.id AND line_item_civireport.entity_table = 'civicrm_contribution')
    WHERE line_item_civireport.id IS NOT NULL
    ";

    $insertParticipantRecordsSql = "
    INSERT INTO $tempTable
    SELECT contribution_civireport_direct.id AS contid, line_item_civireport.id as lid
    FROM civicrm_contribution contribution_civireport_direct
    LEFT JOIN civicrm_participant_payment pp ON contribution_civireport_direct.id = pp.contribution_id
    LEFT JOIN civicrm_participant p ON pp.participant_id = p.id
    LEFT JOIN civicrm_line_item line_item_civireport ON (line_item_civireport.line_total > 0 AND line_item_civireport.entity_id = p.id AND line_item_civireport.entity_table = 'civicrm_participant')
    WHERE line_item_civireport.id IS NOT NULL
    ";

    $insertMembershipRecordSql = "
    INSERT INTO $tempTable
    SELECT contribution_civireport_direct.id AS contid, line_item_civireport.id as lid
    FROM civicrm_contribution contribution_civireport_direct
    LEFT JOIN civicrm_membership_payment pp ON contribution_civireport_direct.id = pp.contribution_id
    LEFT JOIN civicrm_membership p ON pp.membership_id = p.id
    LEFT JOIN civicrm_line_item line_item_civireport ON (line_item_civireport.line_total > 0 AND line_item_civireport.entity_id = p.id AND line_item_civireport.entity_table = 'civicrm_membership')
    WHERE line_item_civireport.id IS NOT NULL
    ";

    CRM_Core_DAO::executeQuery($createTablesql);
    CRM_Core_DAO::executeQuery($insertContributionRecordsSql);
    CRM_Core_DAO::executeQuery($insertParticipantRecordsSql);
    CRM_Core_DAO::executeQuery($insertMembershipRecordSql);
    $this->_from .= "
    LEFT JOIN $tempTable as line_item_mapping
    ON line_item_mapping.contid = {$this->_aliases['civicrm_contribution']}.id
    LEFT JOIN civicrm_line_item as {$this->_aliases['civicrm_line_item']}
    ON {$this->_aliases['civicrm_line_item']}.id = line_item_mapping.lid

    ";
  }

  function joinLineItemFromMembership() {

    // this can be stored as a temp table & indexed for more speed. Not done at this stage.
    // another option is to cache it but I haven't tried to put that code in yet (have used it before for one hour caching
    $this->_from .= "
LEFT JOIN (
SELECT contribution_civireport_direct.id AS contid, line_item_civireport.*
FROM civicrm_contribution contribution_civireport_direct
LEFT JOIN civicrm_line_item line_item_civireport
ON (line_item_civireport.line_total > 0 AND line_item_civireport.entity_id = contribution_civireport_direct.id AND line_item_civireport.entity_table = 'civicrm_contribution')

WHERE line_item_civireport.id IS NOT NULL

UNION

SELECT contribution_civireport_direct.id AS contid, line_item_civireport.*
FROM civicrm_contribution contribution_civireport_direct
LEFT JOIN civicrm_membership_payment pp ON contribution_civireport_direct.id = pp.contribution_id
LEFT JOIN civicrm_membership p ON pp.membership_id = p.id
LEFT JOIN civicrm_line_item line_item_civireport ON (line_item_civireport.line_total > 0 AND line_item_civireport.entity_id = p.id AND line_item_civireport.entity_table = 'civicrm_membership')
WHERE line_item_civireport.id IS NOT NULL
) as {$this->_aliases['civicrm_line_item']}
ON {$this->_aliases['civicrm_line_item']}.contid = {$this->_aliases['civicrm_contribution']}.id
";
  }

  function joinContactFromParticipant() {
    $this->_from .= " LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
ON {$this->_aliases['civicrm_participant']}.contact_id = {$this->_aliases['civicrm_contact']}.id";
  }

  function joinContactFromMembership() {
    $this->_from .= " LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
ON {$this->_aliases['civicrm_membership']}.contact_id = {$this->_aliases['civicrm_contact']}.id";
  }

  function joinContactFromContribution() {
    $this->_from .= " LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
ON {$this->_aliases['civicrm_contribution']}.contact_id = {$this->_aliases['civicrm_contact']}.id";
  }


  function joinEventFromParticipant() {
    $this->_from .= " LEFT JOIN civicrm_event {$this->_aliases['civicrm_event']}
ON ({$this->_aliases['civicrm_event']}.id = {$this->_aliases['civicrm_participant']}.event_id ) AND
({$this->_aliases['civicrm_event']}.is_template IS NULL OR
{$this->_aliases['civicrm_event']}.is_template = 0)";
  }

  /**
   *
   * @param string $prefix
   * @param array $extra
   */
  function joinContributionSummaryTableFromContact($prefix, $extra){
    $temporary = $this->_temporary;
    $tempTable = 'civicrm_report_temp_contsumm'. $prefix . date('d_H_I') . rand(1, 10000);
    $dropSql = "DROP TABLE IF EXISTS $tempTable";
    $criteria = " is_test = 0 ";
    if(!empty($extra['criteria'])){
      $criteria .= " AND " . implode(' AND ', $extra['criteria']);
    }
    $createSql = "
      CREATE TABLE $tempTable (
      `contact_id` INT(10) UNSIGNED NOT NULL COMMENT 'Foreign key to civicrm_contact.id .',
      `contributionsummary{$prefix}` VARCHAR(1024) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
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
        LEFT JOIN civicrm_" . substr($this->financialTypeField, 0,-3) . " financial_type
        ON financial_type.id = {$this->_aliases['civicrm_contribution']}.{$this->financialTypeField}
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


  /*
   * Retrieve text for contribution type from pseudoconstant
  */
  function alterCrmEditable($value, &$row, $selectedfield, $criteriaFieldName, $specs) {
    $id_field = $specs['id_table'] . '_' . $specs['id_field'];
    if(empty($id_field)){
      return;
    }
    $entityID = $row[$id_field];
    $entity = $specs['entity'];
    $extra = '';
    if(!empty($specs['options'])){
      $specs['options']['selected'] = $value;
      $extra = "data-type='select' data-options='" . json_encode($specs['options'])  . "'";
      $value = $specs['options'][$value];
    }//nodeName == "INPUT" && this.type=="checkbox"
    return "<div id={$entity}-{$entityID} class='crm-entity'>
    <span class='crm-editable crmf-{$criteriaFieldName} editable_select crm-editable-enabled' data-action='create' $extra>" . $value . "</span></div>";
  }

  /*
* Retrieve text for contribution type from pseudoconstant
*/
  function alterNickName($value, &$row) {
    if(empty($row['civicrm_contact_id'])){
      return;
    }
    $contactID = $row['civicrm_contact_id'];
    return "<div id=contact-{$contactID} class='crm-entity'><span class='crm-editable crmf-nick_name crm-editable-enabled' data-action='create'>" . $value . "</span></div>";
  }


  /*
   * Retrieve text for contribution type from pseudoconstant
  */
  function alterFinancialType($value, &$row) {
    $fn = $this->financialTypePseudoConstant;
    return is_string(CRM_Contribute_PseudoConstant::$fn($value, FALSE)) ? CRM_Contribute_PseudoConstant::$fn($value, FALSE) : '';
  }

  /*
* Retrieve text for contribution status from pseudoconstant
*/
  function alterContributionStatus($value, &$row) {
    return CRM_Contribute_PseudoConstant::contributionStatus($value);
  }
  /*
* Retrieve text for payment instrument from pseudoconstant
*/
  function alterEventType($value, &$row) {
    return CRM_Event_PseudoConstant::eventType($value);
  }

  function alterEventID($value, &$row) {
    return is_string(CRM_Event_PseudoConstant::event($value, FALSE)) ? CRM_Event_PseudoConstant::event($value, FALSE) : '';
  }

  function alterMembershipTypeID($value, &$row) {
    return is_string(CRM_Member_PseudoConstant::membershipType($value, FALSE)) ? CRM_Member_PseudoConstant::membershipType($value, FALSE) : '';
  }

  function alterMembershipStatusID($value, &$row) {
    return is_string(CRM_Member_PseudoConstant::membershipStatus($value, FALSE)) ? CRM_Member_PseudoConstant::membershipStatus($value, FALSE) : '';
  }

  function alterCountryID($value, &$row, $selectedfield, $criteriaFieldName) {
    $url = CRM_Utils_System::url(CRM_Utils_System::currentPath(), "reset=1&force=1&{$criteriaFieldName}_op=in&{$criteriaFieldName}_value={$value}", $this->_absoluteUrl);
    $row[$selectedfield . '_link'] = $url;
    $row[$selectedfield . '_hover'] = ts("%1 for this country.", array(
        1 => $value,
      ));
    $countries = CRM_Core_PseudoConstant::country($value, FALSE);
    if(!is_array($countries)){
      return $countries;
    }
  }

  function alterCountyID($value, &$row,$selectedfield, $criteriaFieldName) {
    $url = CRM_Utils_System::url(CRM_Utils_System::currentPath(), "reset=1&force=1&{$criteriaFieldName}_op=in&{$criteriaFieldName}_value={$value}", $this->_absoluteUrl);
    $row[$selectedfield . '_link'] = $url;
    $row[$selectedfield . '_hover'] = ts("%1 for this county.", array(
        1 => $value,
      ));
    $counties = CRM_Core_PseudoConstant::county($value, FALSE);
    if(!is_array($counties)){
      return $counties;
    }
  }

  function alterStateProvinceID($value, &$row, $selectedfield, $criteriaFieldName) {
    $url = CRM_Utils_System::url(CRM_Utils_System::currentPath(), "reset=1&force=1&{$criteriaFieldName}_op=in&{$criteriaFieldName}_value={$value}", $this->_absoluteUrl);
    $row[$selectedfield . '_link'] = $url;
    $row[$selectedfield . '_hover'] = ts("%1 for this state.", array(
        1 => $value,
      ));

    $states = CRM_Core_PseudoConstant::stateProvince($value, FALSE);
    if(!is_array($states)){
      return $states;
    }
  }

  function alterContactID($value, &$row, $fieldname) {
    $row[$fieldname . '_link'] = CRM_Utils_System::url("civicrm/contact/view", 'reset=1&cid=' . $value, $this->_absoluteUrl);
    return $value;
  }

  function alterParticipantStatus($value) {
    if (empty($value)) {
      return;
    }
    return CRM_Event_PseudoConstant::participantStatus($value, FALSE, 'label');
  }

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

  function alterPaymentType($value) {
    $paymentInstruments = CRM_Contribute_PseudoConstant::paymentInstrument();
    return $paymentInstruments[$value];
  }

  function alterActivityType($value) {
    $activityTypes = $activityType   = CRM_Core_PseudoConstant::activityType(TRUE, TRUE, FALSE, 'label', TRUE);
    return $activityTypes[$value];
  }

  function alterActivityStatus($value) {
    $activityStatuses  = CRM_Core_PseudoConstant::activityStatus();
    return $activityStatuses[$value];
  }
  /*
   * We are going to convert phones to an array
   */
  function alterPhoneGroup($value) {

    $locationTypes = CRM_Core_PseudoConstant::locationType();
    $phones = explode(',', $value);
    $return = array();
    $html = "<table>";
    foreach ($phones as $phone){
      if(empty($phone)){
        continue;
      }
      $keys = explode(':', $phone);
      $return[$locationTypes[$keys[1]]] = $keys[0];
      $html .= "<tr><td>" . $locationTypes[$keys[1]] . " : " . $keys[0] . "</td></tr>";
    }

    if(in_array( $this->_outputMode, array( 'print', 'pdf' ))){
      return $return;
    }

    $html .= "</table>";
    return $html;
  }
}
