<?php

/**
 * Class CRM_Extendedreport_Form_Report_Pledge_Btbns
 */
class CRM_Extendedreport_Form_Report_Pledge_Btbns extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_charts = array(
    '' => 'Tabular',
    'barChart' => 'Bar Chart',
    'pieChart' => 'Pie Chart',
  );
  protected $_customGroupExtends = array(
    'Pledge',
  );
  protected $lifeTime_from = NULL;
  protected $lifeTime_where = NULL;

  /**
   * Class constructor.
   */
  public function __construct() {
    $yearsInPast = 8;
    $yearsInFuture = 2;
    $date = CRM_Core_SelectValues::date('custom', NULL, $yearsInPast, $yearsInFuture);
    $count = $date['maxYear'];
    while ($date['minYear'] <= $count) {
      $optionYear[$date['minYear']] = $date['minYear'];
      $date['minYear']++;
    }

    $this->_columns = array(
      'civicrm_contact' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'grouping' => 'contact-field',
        'fields' => array(
          'display_name' => array(
            'title' => ts('Donor Name'),
            'default' => TRUE,
            'required' => TRUE,
          ),
        ),
        'filters' => array(
          'sort_name' => array(
            'title' => ts('Donor Name'),
            'operator' => 'like',
          ),
        ),
      ),
      'civicrm_email' => array(
        'dao' => 'CRM_Core_DAO_Email',
        'grouping' => 'contact-field',
        'fields' => array(
          'email' => array(
            'title' => ts('Email'),
            'default' => TRUE,
          ),
        ),
      ),
      'civicrm_phone' => array(
        'dao' => 'CRM_Core_DAO_Phone',
        'grouping' => 'contact-field',
        'fields' => array(
          'phone' => array(
            'title' => ts('Phone No'),
            'default' => TRUE,
          ),
        ),
      ),
      'civicrm_pledge' => array(
        'dao' => 'CRM_Pledge_DAO_Pledge',
        'fields' => array(
          'contact_id' => array(
            'title' => ts('contactId'),
            'required' => TRUE,
            'no_repeat' => TRUE,
            'type' => CRM_Utils_Type::T_INT,
          ),
          'amount' => array(
            'title' => ts('Total Amount'),
            'required' => TRUE,
            'no_repeat' => TRUE,
            'type' => CRM_Utils_Type::T_MONEY,
          ),
          'start_date' => array(
            'title' => ts('Start Date (within range)'),
            'required' => TRUE,
            'no_repeat' => TRUE,
            'type' => CRM_Utils_Type::T_DATE,
          ),
        ),
        'filters' => array(
          'yid' => array(
            'name' => 'start_date',
            'title' => ts('Last Pledge Start Date'),
            'type' => CRM_Utils_Type::T_DATE,
            'operatorType' => CRM_Report_Form::OP_DATE,
            'clause' => "pledge_civireport.contact_id IN
              (SELECT distinct pledge.contact_id FROM civicrm_pledge pledge
               WHERE pledge.start_date  BETWEEN '\$from' AND '\$to' AND pledge.is_test = 0
            )
            AND pledge_civireport.contact_id NOT IN
            (SELECT distinct pledge.contact_id FROM civicrm_pledge pledge
             WHERE pledge.start_date >=  ('\$to') AND pledge.is_test = 0) ",
          ),
          'status_id' => array(
            'title' => 'Pledge Status',
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'type' => CRM_Utils_Type::T_INT,
            'options' => CRM_Contribute_PseudoConstant::contributionStatus(),
            'default' => array(
              '1',
            ),
          ),
        ),
      ),
      'civicrm_group' => array(
        'dao' => 'CRM_Contact_DAO_GroupContact',
        'alias' => 'cgroup',
        'filters' => array(
          'gid' => array(
            'name' => 'group_id',
            'title' => ts('Group'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'group' => TRUE,
            'type' => CRM_Utils_Type::T_INT,
            'options' => CRM_Core_PseudoConstant::group()
          )
        )
      )
    );

    $this->_tagFilter = TRUE;
    parent::__construct();
  }

  function select() {
    $this->_columnHeaders = $select = array();
    $current_year = isset($this->_params['yid_value']) ? $this->_params['yid_value'] : date('Y');
    $previous_year = $current_year - 1;


    foreach ($this->_columns as $tableName => $table) {

      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {

          if (CRM_Utils_Array::value('required', $field) || CRM_Utils_Array::value($fieldName, $this->_params['fields'])) {
            if ($fieldName == 'total_amount') {
              $select[] = "SUM({$field['dbAlias']}) as {$tableName}_{$fieldName}";

              $this->_columnHeaders["{$previous_year}"]['type'] = $field['type'];
              $this->_columnHeaders["{$previous_year}"]['title'] = $previous_year;

              $this->_columnHeaders["civicrm_life_time_total"]['type'] = $field['type'];
              $this->_columnHeaders["civicrm_life_time_total"]['title'] = 'LifeTime';;
            }
            else {
              if ($fieldName == 'receive_date') {
                $select[] = " Year ( {$field['dbAlias']} ) as {$tableName}_{$fieldName} ";
              }
              else {
                $select[] = "{$field['dbAlias']} as {$tableName }_{$fieldName} ";
                $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = $field['type'];
                $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = $field['title'];
              }
            }

            if (CRM_Utils_Array::value('no_display', $field)) {
              $this->_columnHeaders["{$tableName}_{$fieldName}"]['no_display'] = TRUE;
            }
          }
        }
      }
    }

    $this->_select = "SELECT  " . implode(', ', $select) . " ";
  }

  function from() {
    $this->_from = "
        FROM  civicrm_pledge  {$this->_aliases['civicrm_pledge']}
              INNER JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
                      ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_pledge']}.contact_id
              {$this->_aclFrom}
              LEFT  JOIN civicrm_email  {$this->_aliases['civicrm_email']}
                      ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_email']}.contact_id AND
                         {$this->_aliases['civicrm_email']}.is_primary = 1
              LEFT  JOIN civicrm_phone  {$this->_aliases['civicrm_phone']}
                      ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_phone']}.contact_id AND
                         {$this->_aliases['civicrm_phone']}.is_primary = 1 ";
  }

  function groupBy() {
    $this->_groupBy = "Group BY  {$this->_aliases['civicrm_pledge']}.contact_id, Year({$this->_aliases['civicrm_pledge']}.start_date) WITH ROLLUP";
    $this->assign('chartSupported', TRUE);
  }

  /**
   * @param $rows
   *
   * @return mixed
   */
  function statistics(&$rows) {
    $statistics = parent::statistics($rows);
    if (!empty($rows)) {
      $select = "
                      SELECT
                            SUM({$this->_aliases['civicrm_pledge']}.amount ) as amount ";

      $sql = "{$select} {$this->_from } {$this->_where}";
      $dao = CRM_Core_DAO::executeQuery($sql);
      if ($dao->fetch()) {
        $statistics['counts']['amount'] = array(
          'value' => $dao->amount,
          'title' => 'Total LifeTime',
          'type' => CRM_Utils_Type::T_MONEY
        );
      }
    }

    return $statistics;
  }

  function postProcess() {

    // get ready with post process params
    $this->beginPostProcess();

    // get the acl clauses built before we assemble the query
    $this->buildACLClause($this->_aliases['civicrm_contact']);
    $this->select();
    $this->from();
    $this->extendedCustomDataFrom();
    $this->where();
    $this->groupBy();
    $rows = $contactIds = array();
    if (!CRM_Utils_Array::value('charts', $this->_params)) {
      $this->limit();
      $getContacts = "SELECT SQL_CALC_FOUND_ROWS {$this->_aliases['civicrm_contact']}.id as cid {$this->_from} {$this->_where}  GROUP BY {$this->_aliases['civicrm_contact']}.id {$this->_limit}";
      $dao = CRM_Core_DAO::executeQuery($getContacts);
      while ($dao->fetch()) {
        $contactIds[] = $dao->cid;
      }
      $dao->free();
      $this->setPager();
    }
    if (!empty($contactIds) || CRM_Utils_Array::value('charts', $this->_params)) {
      if (CRM_Utils_Array::value('charts', $this->_params)) {
        $sql = "{$this->_select} {$this->_from} {$this->_where} {$this->_groupBy}";
      }
      else {
        $sql = "{$this->_select} {$this->_from} WHERE {$this->_aliases['civicrm_contact']}.id IN (" . implode(',', $contactIds) . ") AND {$this->_aliases['civicrm_pledge']}.is_test = 0 {$this->_groupBy} ";
      }
      $dao = CRM_Core_DAO::executeQuery($sql);
      $current_year = $this->_params['yid_value'];
      $previous_year = $current_year - 1;

      while ($dao->fetch()) {

        if (!$dao->civicrm_pledge_contact_id) {
          continue;
        }

        foreach ($this->_columnHeaders as $key => $value) {
          if (property_exists($dao, $key)) {
            $rows[$dao->civicrm_pledge_contact_id][$key] = $dao->$key;
          }
        }

        if (empty($dao->civicrm_contribution_receive_date)) {
          if (!empty($dao->civicrm_contribution_receive_date) && $dao->civicrm_contribution_receive_date == $previous_year) {
            $rows[$dao->civicrm_pledge_contact_id][$dao->civicrm_pledge_start_date] = $dao->civicrm_pledge_amount;
          }
        }
        else {
          $rows[$dao->civicrm_pledge_contact_id]['civicrm_life_time_total'] = $dao->civicrm_pledge_amount;
        }
      }
      $dao->free();
    }

    $this->formatDisplay($rows, FALSE);

    // assign variables to templates
    $this->doTemplateAssignment($rows);

    // do print / pdf / instance stuff if needed
    $this->endPostProcess($rows);
  }

  /**
   * @param $rows
   */
  function buildChart(&$rows) {
    $graphRows = array();
    $display = array();

    $current_year = $this->_params['yid_value'];
    $previous_year = $current_year - 1;
    $interval[$previous_year] = $previous_year;
    $interval['life_time'] = 'Life Time';

    foreach ($rows as $key => $row) {
      $display['life_time'] = CRM_Utils_Array::value('life_time', $display) + $row['civicrm_life_time_total'];
      $display[$previous_year] = CRM_Utils_Array::value($previous_year, $display) + $row[$previous_year];
    }

    $config = CRM_Core_Config::Singleton();
    $graphRows['value'] = $display;
    $chartInfo = array(
      'legend' => ts('Lybunt Report'),
      'xname' => ts('Year'),
      'yname' => ts('Amount (%1)', array(
        1 => $config->defaultCurrency
      ))
    );
    if ($this->_params['charts']) {
      // build chart.
      require_once 'CRM/Utils/OpenFlashChart.php';
      CRM_Utils_OpenFlashChart::reportChart($graphRows, $this->_params['charts'], $interval, $chartInfo);
      $this->assign('chartType', $this->_params['charts']);
    }
  }

  /**
   * @param $rows
   */
  function alterDisplay(&$rows) {
    foreach ($rows as $rowNum => $row) {
      //Convert Display name into link
      if (array_key_exists('civicrm_contact_display_name', $row) && array_key_exists('civicrm_pledge_contact_id', $row)) {
        $url = CRM_Utils_System::url("civicrm/contact/view", 'reset=1&cid=' . $row['civicrm_pledge_contact_id'], $this->_absoluteUrl);

        $rows[$rowNum]['civicrm_contact_display_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_display_name_hover'] = ts("View Contribution Details for this Contact.");
      }
    }
  }
}
