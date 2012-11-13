 <?php

class CRM_Extendedreport_Form_Report_ExtendedReport extends CRM_Report_Form {
  protected $_addressField = FALSE;

  protected $_emailField = FALSE;

  protected $_summary = NULL;
  protected $_exposeContactID = FALSE;
  protected $_customGroupExtends = array();
  protected $_baseTable = 'civicrm_contact';
  protected $_editableFields = TRUE;

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

  function __construct() {
    parent::__construct();
    $this->addSelectableCustomFields();
    $this->addTemplateSelector();
    CRM_Core_Resources::singleton()->addScriptFile('nz.co.fuzion.extendedreport', 'js/jquery.multiselect.filter.js');
  }

  function preProcess() {
    parent::preProcess();
  }

  function select() {
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
      $this->_extrafrom .= "
      LEFT JOIN {$properties['name']} $selectedTable ON {$selectedTable}.entity_id = {$this->_aliases[$extendsTable]}.id";
    }
  }

  function addColumnAggregateSelect($fieldName, $tableAlias, $spec){
    $options = civicrm_api('option_value', 'get', array('version' => 3, 'option_group_id' => $spec['option_group_id']));

    foreach ($options['values'] as $option){
      $this->_select .= " , SUM( CASE {$tableAlias}.{$fieldName} WHEN '{$option['value']}' THEN 1 ELSE 0 END ) AS {$fieldName}_{$option['value']} ";
      $this->_columnHeaders["{$fieldName}_{$option['value']}"] = array('title' => $spec['title'] . " - " . $option['label']);
    }
    $this->_select .= " , SUM( CASE {$tableAlias}.{$fieldName} WHEN '{$option['value']}' THEN 1 ELSE 0 END ) AS {$fieldName}_{$option['value']} ";
  }
  /*
* From clause build where baseTable & fromClauses are defined
*/
  function from() {
    if (!empty($this->_baseTable)) {
      if(!empty($this->_aliases['civicrm_contact'])){
        $this->buildACLClause($this->_aliases['civicrm_contact']);
      }
      $this->_from = "FROM {$this->_baseTable} {$this->_aliases[$this->_baseTable]}";
      $availableClauses = $this->getAvailableJoins();
      foreach ($this->fromClauses() as $fromClause) {
        $fn = $availableClauses[$fromClause]['callback'];
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
      if (strstr($this->_from, 'civicrm_contact')) {
        $this->_from .= $this->_aclFrom;
      }
      $this->_from .= $this->_extrafrom;
    }
    $this->selectableCustomDataFrom();
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
    if(empty($this->_groupByArray)){
      foreach($this->_columns[$this->_baseTable]['fields'] as $fieldname => $field){
        if(isset( $field['statistics'])){
          unset($this->_columns[$this->_baseTable]['fields'][$fieldname]['statistics']);
        }
      }
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
    if($this->_params['templates'] ==1){
     //
    }
    return $defaultTpl;
  }
/*
  function compileContent(){
    if(in_array( $this->_outputMode, array( 'print', 'pdf' ))){
      $templateFile = $this->getTemplateFileName();
      echo $this->_formValues['report_header'] . CRM_Core_Form::$_template->fetch($templateFile) . $this->_formValues['report_footer'];
    die;
    }
    else{
      parent::compileContent();
    }
  }
  */

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

    $extends = array();
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
    if(!empty($params['custom_fields']) && is_array($params['custom_fields'])){
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

  function getContributionColumns() {
    return array(
      'civicrm_contribution' =>
      array(
        'dao' => 'CRM_Contribute_DAO_Contribution',
        'fields' =>
        array(
          'contribution_id' => array(
            'title' => ts('Contribution ID'),
            'name' => 'id',
          ),
          'contribution_type_id' => array('title' => ts('Contribution Type'),
            'default' => TRUE,
            'alter_display' => 'alterContributionType',
          ),
          'payment_instrument_id' => array('title' => ts('Payment Instrument'),
            'alter_display' => 'alterPaymentType',
          ),
          'source' => array('title' => 'Contribution Source'),
          'trxn_id' => NULL,
          'receive_date' => array('default' => TRUE),
          'receipt_date' => NULL,
          'fee_amount' => NULL,
          'net_amount' => NULL,
          'total_amount' => array('title' => ts('Amount'),
          'statistics' =>
            array('sum' => ts('Total Amount')),
            'type' => CRM_Utils_Type::T_MONEY,
          ),
        ),
        'filters' =>
        array(
          'receive_date' =>
          array('operatorType' => CRM_Report_Form::OP_DATE),
          'contribution_type_id' =>
          array('title' => ts('Contribution Type'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Contribute_PseudoConstant::contributionType(),
          ),
          'payment_instrument_id' =>
          array('title' => ts('Payment Type'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Contribute_PseudoConstant::paymentInstrument(),
          ),
          'contribution_status_id' =>
          array('title' => ts('Contribution Status'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Contribute_PseudoConstant::contributionStatus(),
          ),
          'contribution_is_test' =>  array(
            'type' => CRM_Report_Form::OP_INT,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'title' => ts("Contribution Mode"),
            'default' => 0,
            'name' => 'is_test',
            'options' => array('0' => 'Live', '1' => 'Test'),
          ),
          'total_amount' =>
          array('title' => ts('Contribution Amount')),
        ),
        'order_bys' =>
        array(
          'payment_instrument_id' =>
          array('title' => ts('Payment Instrument'),
          ),
         'contribution_type_id' =>
          array('title' => ts('Contribution Type'),
          ),
        ),
        'group_bys' =>
        array(
          'contribution_type_id' =>
          array('title' => ts('Contribution Type')),
          'payment_instrument_id' =>
          array('title' => ts('Payment Instrument')),
          'contribution_id' =>
          array('title' => ts('Individual Contribution'),
            'name' => 'id',
          ),
          'source' => array('title' => 'Contribution Source'),
        ),
        'grouping' => 'contribution-fields',
      ),
    );
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
          'id' => array(
            'title' => ts($options['prefix_label'] . 'Contact ID'),
          )
          ,
          'sort_name' => array(
            'title' => ts($options['prefix_label'] . 'Contact Name'),
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
          'id' => array(
            'title' => ts('Case ID'),
            'required' => false
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
            'default' => 0,
            'name' => 'is_deleted'
          )
        )
      )
    );
  }
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
                      'operatorType' => CRM_Report_Form::OP_SELECT,
                      'options'      =>
                      array( ''  => '- Any -',
                          1   => 'Active',
                          0   => 'Inactive',
                      ),
                      'type'     => CRM_Utils_Type::T_INT ),
                  'relationship_type_id' =>
                  array( 'title'        => ts( 'Relationship' ),
                      'operatorType' => CRM_Report_Form::OP_SELECT,
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
  function getActivityColumns(){
    return array('civicrm_activity' =>
    array(
        'dao' => 'CRM_Activity_DAO_Activity',
        'fields' =>
        array(
            'id' =>
            array(
                'no_display' => TRUE,
                'required' => TRUE,
            ),
            'source_record_id' =>
            array(
                'no_display' => TRUE,
                'required' => TRUE,
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
                'required' => TRUE,
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
        ),
        'filters' =>
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
            ),
        ),
        'order_bys' =>
        array(
          'activity_date_time' => array(
             'title' => ts('Activity Date')),
            'activity_type_id' =>
            array('title' => ts('Activity Type')),
        ),
        'grouping' => 'activity-fields',
        'alias' => 'activity',
      )
    );
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
      'contribution_from_participant' => array(
        'leftTable' => 'civicrm_participant',
        'rightTable' => 'civicrm_contribution',
        'callback' => 'joinContribution:git FromParticipant',
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
    );
  }

  /*
* Add join from contact table to address. Prefix will be added to both tables
* as it's assumed you are using it to get address of a secondary contact
*/
  function joinAddressFromContact( $prefix = '') {
    $this->_from .= " LEFT JOIN civicrm_address {$this->_aliases[$prefix . 'civicrm_address']}
ON {$this->_aliases[$prefix . 'civicrm_address']}.contact_id = {$this->_aliases[$prefix . 'civicrm_contact']}.id";
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
  /*
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
    $tempTable = 'civicrm_temp_report_line_items' . rand(1, 10000);
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
    $tempTable = 'civicrm_temp_report_line_item_map' . rand(1, 10000);
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
    return "<div id=contact-{$contactID} class='crm-entity'>
<span class='crm-editable crmf-nick_name crm-editable-enabled' data-action='create'>" . $value . "</span></div>";
  }

  /*
* Retrieve text for contribution type from pseudoconstant
*/
  function alterContributionType($value, &$row) {
    return is_string(CRM_Contribute_PseudoConstant::contributionType($value, FALSE)) ? CRM_Contribute_PseudoConstant::contributionType($value, FALSE) : '';
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
    $activityStatuses = $activityType   = CRM_Core_PseudoConstant::activityStatus();
    return $activityStatuses[$value];
  }
}
