<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.2                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2010                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */
class CRM_Extendedreport_Form_Report_Pledge_Sybunt extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_add2groupSupported = FALSE;

  protected $_customGroupExtends = ['Pledge'];

  /**
   *
   * @throws \CRM_Core_Exception
   */
  public function __construct() {
    $yearsInPast = 8;
    $yearsInFuture = 2;
    $optionYear = NULL;
    $date = CRM_Core_SelectValues::date('custom', NULL, $yearsInPast, $yearsInFuture);
    $count = $date['maxYear'];
    while ($date['minYear'] <= $count) {
      $optionYear[$date['minYear']] = $date['minYear'];
      $date['minYear']++;
    }

    $this->_columns = $this->getColumns('Contact')
      + $this->getColumns('Email')
      + $this->getColumns('Phone')
      + $this->getColumns('Pledge');

    $this->_columns['civicrm_pledge']['filters']['yid'] =
    $this->_columns['civicrm_pledge']['metadata']['yid'] = [
      'name' => 'start_date',
      'title' => ts('This Year'),
      'operatorType' => CRM_Report_Form::OP_SELECT,
      'type' => CRM_Utils_Type::T_INT,
      'options' => $optionYear,
      'default' => date('Y'),
      'is_filters' => TRUE,
      'is_join_filters' => TRUE,
      'is_aggregate_columns' => FALSE,
      'is_aggregate_rows' => FALSE,
      'is_fields' => FALSE,
      'is_group_bys' => FALSE,
      'is_order_bys' => FALSE,
      'table_key' => 'civicrm_pledge',
    ];

    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;
    parent::__construct();
    CRM_Core_DAO::disableFullGroupByMode();
  }

  public function select(): void {
    $select = [];
    $this->_columnHeaders = [];
    $current_year = $this->_params['yid_value'];
    $previous_year = $current_year - 1;
    $previous_pyear = $current_year - 2;
    $previous_ppyear = $current_year - 3;
    $upTo_year = $current_year - 4;

    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {

          if (CRM_Utils_Array::value('required', $field) || CRM_Utils_Array::value($fieldName, $this->_params['fields'])) {
            if ($fieldName === 'amount') {
              $select[] = "SUM({$field['dbAlias']}) as {$tableName}_$fieldName";

              $this->_columnHeaders["civicrm_upto_$upTo_year"]['type'] = $field['type'];
              $this->_columnHeaders["civicrm_upto_$upTo_year"]['title'] = "Up To $upTo_year";

              $this->_columnHeaders[$previous_ppyear]['type'] = $field['type'];
              $this->_columnHeaders[$previous_ppyear]['title'] = $previous_ppyear;

              $this->_columnHeaders[$previous_pyear]['type'] = $field['type'];
              $this->_columnHeaders[$previous_pyear]['title'] = $previous_pyear;

              $this->_columnHeaders[$previous_year]['type'] = $field['type'];
              $this->_columnHeaders[$previous_year]['title'] = $previous_year;

              $this->_columnHeaders["civicrm_life_time_total"]['type'] = $field['type'];
              $this->_columnHeaders["civicrm_life_time_total"]['title'] = 'LifeTime';
            }
            else if ($fieldName === 'start_date') {
              $select[] = "Year({$field['dbAlias']} ) as {$tableName}_$fieldName";
            }
            else {
              $select[] = "{$field['dbAlias']} as {$tableName}_$fieldName";
              $this->_columnHeaders["{$tableName}_$fieldName"]['type'] = CRM_Utils_Array::value('type', $field);
              $this->_columnHeaders["{$tableName}_$fieldName"]['title'] = $field['title'];
            }
            if (CRM_Utils_Array::value('no_display', $field)) {
              $this->_columnHeaders["{$tableName}_$fieldName"]['no_display'] = TRUE;
            }
          }
        }
      }
    }
    $this->_selectClauses = $select;
    $this->_select = "SELECT " . implode(', ', $select) . " ";
  }

  public function from(): void {
    $this->_from = "
        FROM  civicrm_pledge  {$this->_aliases['civicrm_pledge']}
             INNER JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
                         ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_pledge']}.contact_id
             $this->_aclFrom
             LEFT  JOIN civicrm_email  {$this->_aliases['civicrm_email']}
                         ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_email']}.contact_id
                         AND {$this->_aliases['civicrm_email']}.is_primary = 1
             LEFT  JOIN civicrm_phone  {$this->_aliases['civicrm_phone']}
                         ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_phone']}.contact_id AND
                            {$this->_aliases['civicrm_phone']}.is_primary = 1 ";
  }

  /**
   * Specific where clause override.
   *
   * @param array $field
   * @param string $op
   * @param mixed $value
   * @param float $min
   * @param float $max
   *
   * @return string
   * @throws \CRM_Core_Exception
   */
  public function whereClause(&$field, $op, $value, $min, $max): string {
    if ($field['name'] === 'start_date') {
      $value = CRM_Utils_Type::escape($value, 'Int');
      return "
        {$this->_aliases['civicrm_pledge']}.contact_id NOT IN (
          SELECT distinct pledge.contact_id
          FROM civicrm_pledge pledge
          WHERE YEAR(pledge.start_date) = $value
          AND pledge.is_test = 0
        )";
    }
    return parent::whereClause($field, $op, $value, $min, $max);
  }

  public function groupBy() : void{
    $this->_groupByArray = [$this->_aliases['civicrm_pledge'] . '.contact_id'];
    $this->_groupBy = CRM_Contact_BAO_Query::getGroupByFromSelectColumns($this->_selectClauses, $this->_groupByArray) . ", Year({$this->_aliases['civicrm_pledge']}.start_date) WITH ROLLUP ";
  }

  /**
   * @param $rows
   *
   * @return array
   */
  public function statistics(&$rows): array {
    $statistics = parent::statistics($rows);

    if (!empty($rows)) {
      $select = "
                   SELECT
                        SUM({$this->_aliases['civicrm_pledge']}.amount ) as amount ";

      $sql = "{$select} {$this->_from } {$this->_where}";
      $dao = CRM_Core_DAO::executeQuery($sql);
      if ($dao->fetch()) {
        $statistics['counts']['amount'] = [
          'value' => $dao->amount,
          'title' => 'Total LifeTime',
          'type' => CRM_Utils_Type::T_MONEY,
        ];
      }
    }
    return $statistics;
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function postProcess(): void {
    // get ready with post process params
    $this->beginPostProcess();

    $this->buildPermissionClause();
    $this->select();
    $this->from();
    $this->extendedCustomDataFrom();
    $this->where();
    $this->groupBy();

    $rows = $contactIds = [];
    if (!CRM_Utils_Array::value('charts', $this->_params)) {
      $this->limit();
      $getContacts = "SELECT {$this->_aliases['civicrm_contact']}.id as cid {$this->_from} {$this->_where} GROUP BY {$this->_aliases['civicrm_contact']}.id {$this->_limit}";

      $dao = CRM_Core_DAO::executeQuery($getContacts);

      while ($dao->fetch()) {
        $contactIds[] = $dao->cid;
      }
      $this->setPager();
    }

    if (!empty($contactIds) || CRM_Utils_Array::value('charts', $this->_params)) {
      if (CRM_Utils_Array::value('charts', $this->_params)) {
        $sql = "{$this->_select} {$this->_from} {$this->_where} {$this->_groupBy}";
      }
      else {
        $sql = "{$this->_select} {$this->_from} WHERE {$this->_aliases['civicrm_contact']}.id IN (" . implode(',', $contactIds) . ") AND {$this->_aliases['civicrm_pledge']}.is_test = 0 {$this->_groupBy} ";
      }

      $current_year = $this->_params['yid_value'];
      $upTo_year = $current_year - 4;

      $rows = [];
      $dao = CRM_Core_DAO::executeQuery($sql);
      $contributionSum = 0;

      while ($dao->fetch()) {
        if (!$dao->civicrm_pledge_pledge_contact_id) {
          continue;
        }
        foreach ($this->_columnHeaders as $key => $value) {
          if (property_exists($dao, $key)) {
            $rows[$dao->civicrm_pledge_pledge_contact_id][$key] = $dao->$key;
          }
        }
        if ($dao->civicrm_pledge_pledge_start_date) {
          if ($dao->civicrm_pledge_pledge_start_date > $upTo_year) {
            $contributionSum += $dao->civicrm_pledge_amount;
            $rows[$dao->civicrm_pledge_pledge_contact_id][$dao->civicrm_pledge_pledge_start_date] = $dao->civicrm_pledge_amount;
          }
        }
        else {
          $rows[$dao->civicrm_pledge_pledge_contact_id]['civicrm_life_time_total'] = $dao->civicrm_pledge_amount;
          if (($dao->civicrm_pledge_amount - $contributionSum) > 0) {
            $rows[$dao->civicrm_pledge_pledge_contact_id]["civicrm_upto_{$upTo_year}"] = $dao->civicrm_pledge_amount - $contributionSum;
          }
          $contributionSum = 0;
        }
      }
    }
    // format result set.
    $this->formatDisplay($rows, FALSE);

    // assign variables to templates
    $this->doTemplateAssignment($rows);

    // do print / pdf / instance stuff if needed
    $this->endPostProcess($rows);
  }

  /**
   * @param array $rows
   */
  public function alterDisplay(&$rows): void {
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
