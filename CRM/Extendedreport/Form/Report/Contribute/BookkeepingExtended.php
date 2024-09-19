<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2017                                |
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

use CRM_Extendedreport_ExtensionUtil as E;

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */
class CRM_Extendedreport_Form_Report_Contribute_BookkeepingExtended extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_baseTable = 'civicrm_contribution';

  protected $_rollup;

  /**
   * Class constructor.
   *
   * @throws \CRM_Core_Exception
   */
  public function __construct() {
    $this->_columns = $this->getColumns('Contact')
      + $this->getColumns('Address')
      + $this->getColumns('Phone', ['subquery' => FALSE])
      + $this->getColumns('Email')
      + $this->getColumns('Membership')
      + $this->getColumns('MembershipLog', ['prefix_label' => 'Historical '])
      + $this->getColumns('FinancialAccount', [
        'prefix' => 'credit_',
        'group_by' => TRUE,
        'prefix_label' => ts('Credit '),
        'filters' => TRUE,
      ])
      + $this->getColumns('FinancialAccount', [
        'prefix' => 'debit_',
        'group_by' => TRUE,
        'prefix_label' => ts('Debit '),
        'filters' => FALSE,
      ])
      + $this->getColumns('LineItem')
      + $this->getColumns('Contribution', [
        'fields_defaults' => ['receive_date'],
        'filters_defaults' => [
          'contribution_status_id' => [1],
        ],
      ])
      + $this->getColumns('ContributionRecur', ['group_by' => TRUE])
      + $this->getColumns('Product', ['group_by' => TRUE])
      + $this->getColumns('ContributionProduct', ['group_by' => TRUE])
      + $this->getColumns('Note', ['prefix' => 'contribution_', 'prefix_label' => ' ' . E::ts('Contribution')])
      + $this->getColumns('FinancialTrxn', [
        'filters_defaults' => [
          'status_id' => ['IN' => [1]],
        ],
      ])
      + $this->buildColumns(
        [
          'amount' => [
            'title' => ts('Amount'),
            'default' => TRUE,
            'type' => CRM_Utils_Type::T_MONEY,
            'operatorType' => CRM_Report_Form::OP_FLOAT,
            'statistics' => ['sum' => ts('Total amount')],
            'is_fields' => TRUE,
            'is_filters' => TRUE,
            'is_group_bys' => FALSE,
            'is_order_bys' => FALSE,
            'is_join_filters' => FALSE,
            'table_name' => 'civicrm_entity_financial_trxn',
          ],
        ], 'civicrm_entity_financial_trxn', 'CRM_Financial_DAO_EntityFinancialTrxn')
      + $this->getColumns('Batch', [
        'group_by' => TRUE,
        'prefix_label' => ts('Batch '),
        'filters' => TRUE,
      ]);

    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;
    parent::__construct();
    CRM_Core_DAO::disableFullGroupByMode();

  }

  /**
   * Here we can define select clauses for any particular row.
   *
   * @param string $tableName
   * @param string $tableKey
   * @param string $fieldName
   * @param array $field
   *
   * @return bool|string
   */
  public function selectClause(&$tableName, $tableKey, &$fieldName, &$field) {
    $alias = "{$tableName}_$fieldName";
    if ($fieldName === 'credit_financial_account_accounting_code') {
      $this->setHeaders($tableName, $fieldName, $field, $alias);
      return "
        CASE
        WHEN {$this->_aliases['civicrm_financial_trxn']}.from_financial_account_id IS NOT NULL
        THEN {$this->_aliases['credit_civicrm_financial_account']}.accounting_code
        ELSE credit_financial_item_financial_account.accounting_code
        END AS $alias ";
    }

    if ($fieldName === 'credit_financial_account_name') {
      $this->setHeaders($tableName, $fieldName, $field, $alias);
      return "
        CASE
        WHEN {$this->_aliases['civicrm_financial_trxn']}.from_financial_account_id IS NOT NULL
        THEN {$this->_aliases['credit_civicrm_financial_account']}.name
        ELSE credit_financial_item_financial_account.name
        END AS $alias ";
    }

    if ($fieldName === 'debit_financial_account_accounting_code') {
      $this->setHeaders($tableName, $fieldName, $field, $alias);
      return "
        CASE
        WHEN {$this->_aliases['civicrm_financial_trxn']}.from_financial_account_id IS NOT NULL
        THEN  {$this->_aliases['debit_civicrm_financial_account']}.accounting_code
        ELSE  {$this->_aliases['debit_civicrm_financial_account']}.accounting_code
        END AS $alias ";
    }


    if ($fieldName === 'debit_financial_account_name') {
      $this->setHeaders($tableName, $fieldName, $field, $alias);
      return "
        CASE
        WHEN {$this->_aliases['civicrm_financial_trxn']}.from_financial_account_id IS NOT NULL
        THEN  {$this->_aliases['debit_civicrm_financial_account']}.name
        ELSE  {$this->_aliases['debit_civicrm_financial_account']}.name
        END AS $alias ";
    }

    if ($fieldName === 'amount') {
      $field['dbAlias'] = '';
      $this->setHeaders($tableName, $fieldName, $field, $alias);
      $clause = "(
        CASE
        WHEN  {$this->_aliases['civicrm_entity_financial_trxn']}_item.entity_id IS NOT NULL
        THEN {$this->_aliases['civicrm_entity_financial_trxn']}_item.amount
        ELSE {$this->_aliases['civicrm_entity_financial_trxn']}.amount
        END) AS civicrm_entity_financial_trxn_amount ";
      if (!empty($this->_groupByArray) || $this->isForceGroupBy) {
        return " SUM" . $clause;
      }
      return $clause;
    }

    return parent::selectClause($tableName, $tableKey, $fieldName, $field);
  }

  public function from(): void {
    parent::from();
    // @todo break these out to be like the other ones.
    $this->_from .=
      "
              LEFT JOIN civicrm_financial_account {$this->_aliases['debit_civicrm_financial_account']}
                    ON {$this->_aliases['civicrm_financial_trxn']}.to_financial_account_id =
                    {$this->_aliases['debit_civicrm_financial_account']}.id

              LEFT JOIN civicrm_financial_account {$this->_aliases['credit_civicrm_financial_account']}
                    ON {$this->_aliases['civicrm_financial_trxn']}.from_financial_account_id = {$this->_aliases['credit_civicrm_financial_account']}.id";
    if ($this->isTableSelected('civicrm_membership_log')) {
      $this->_from .= "
      LEFT JOIN civicrm_membership_log {$this->_aliases['civicrm_membership_log']}
      ON {$this->_aliases['civicrm_membership']}.id = {$this->_aliases['civicrm_membership_log']}.membership_id
      ";
    }
  }

  /**
   * @return array
   */
  public function fromClauses(): array {
    return [
      'contact_from_contribution',
      'contribution_recur_from_contribution',
      'note_from_contribution',
      'product_from_contribution',
      'financial_trxn_from_contribution',
      'lineItem_from_financialTrxn',
      'batch_from_financialTrxn',
      'primary_phone_from_contact',
      'address_from_contact',
      'email_from_contact',
      'membership_from_lineItem',
    ];
  }

  public function orderBy(): void {
    parent::orderBy();

    // please note this will just add the order-by columns to select query, and not display in column-headers.
    // This is a solution to not throw fatal errors when there is a column in order-by, not present in select/display columns.
    foreach ($this->_orderByFields as $orderBy) {
      if (!array_key_exists($orderBy['name'], $this->_params['fields']) &&
        empty($orderBy['section'])
      ) {
        $this->_select .= ", {$orderBy['dbAlias']} as {$orderBy['tplField']}";
      }
    }
  }

  public function where(): void {
    parent::where();
    if ($this->isTableSelected('civicrm_membership_log')) {
      $this->_where .= "AND {$this->_aliases['civicrm_membership_log']}.modified_date = DATE({$this->_aliases['civicrm_financial_trxn']}.trxn_date)";
    }
  }

  /**
   * Generate where clause.
   *
   * This can be overridden in reports for special treatment of a field
   *
   * @param array $field Field specifications
   * @param string $op Query operator (not an exact match to sql)
   * @param mixed $value
   * @param float $min
   * @param float $max
   *
   * @return null|string
   */
  public function whereClause(&$field, $op, $value, $min, $max): ?string {
    if ($field['dbAlias'] === "{$this->_aliases['credit_civicrm_financial_account']}.accounting_code") {
      $field['dbAlias'] = "CASE
              WHEN financial_trxn_civireport.from_financial_account_id IS NOT NULL
              THEN  {$this->_aliases['credit_civicrm_financial_account']}.accounting_code
              ELSE  credit_financial_item_financial_account.accounting_code
              END";
    }
    if ($field['dbAlias'] === 'credit_financial_account.name') {
      $field['dbAlias'] = "CASE
              WHEN financial_trxn_civireport.from_financial_account_id IS NOT NULL
              THEN {$this->_aliases['credit_civicrm_financial_account']}.id
              ELSE  credit_financial_item_financial_account.id
              END";

    }
    return parent::whereClause($field, $op, $value, $min, $max);
  }

  /**
   * @param $rows
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  public function statistics(&$rows): array {
    $statistics = parent::statistics($rows);
    $avg = [];

    $select = " SELECT COUNT({$this->_aliases['civicrm_financial_trxn']}.id ) as count,
                {$this->_aliases['civicrm_contribution']}.currency,
                SUM(CASE
                  WHEN {$this->_aliases['civicrm_entity_financial_trxn']}_item.entity_id IS NOT NULL
                  THEN {$this->_aliases['civicrm_entity_financial_trxn']}_item.amount
                  ELSE {$this->_aliases['civicrm_entity_financial_trxn']}.amount
                END) as amount
";

    $sql = "{$select} {$this->_from} {$this->_where}
            GROUP BY {$this->_aliases['civicrm_contribution']}.currency
";

    $dao = CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      $amount[] = Civi::format()->money($dao->amount, $dao->currency);
      $avg[] = Civi::format()->money(round(($dao->amount /
        $dao->count), 2), $dao->currency);
    }
    if (empty($amount)) {
      return $statistics;
    }
    $statistics['counts']['amount'] = [
      'value' => implode(', ', $amount),
      'title' => 'Total Amount',
      'type' => CRM_Utils_Type::T_STRING,
    ];
    $statistics['counts']['avg'] = [
      'value' => implode(', ', $avg),
      'title' => 'Average',
      'type' => CRM_Utils_Type::T_STRING,
    ];
    return $statistics;
  }

  /**
   * @param array $rows
   *
   * @throws \CRM_Core_Exception
   */
  public function alterDisplay(&$rows): void {
    foreach ($rows as $rowNum => $row) {
      // convert display name to links
      if (array_key_exists('civicrm_contact_sort_name', $row) &&
        !empty($row['civicrm_contact_sort_name']) &&
        array_key_exists('civicrm_contact_id', $row)
      ) {
        $url = CRM_Utils_System::url('civicrm/contact/view',
          'reset=1&cid=' . $row['civicrm_contact_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_contact_sort_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_sort_name_hover'] = ts('View Contact Summary for this Contact.');
      }

      // handle contribution status id
      if ($value = CRM_Utils_Array::value('civicrm_contribution_contribution_status_id', $row)) {
        $rows[$rowNum]['civicrm_contribution_contribution_status_id'] = CRM_Core_PseudoConstant::getLabel('CRM_Contribute_BAO_Contribution', 'contribution_status_id', $value);
      }
      if ($value = CRM_Utils_Array::value('civicrm_financial_trxn_financial_trxn_financial_trxn_status_id', $row)) {
        $rows[$rowNum]['civicrm_financial_trxn_financial_trxn_financial_trxn_status_id'] = CRM_Core_PseudoConstant::getLabel('CRM_Contribute_BAO_Contribution', 'contribution_status_id', $value);
      }

      // handle financial type id
      if ($value = CRM_Utils_Array::value('civicrm_line_item_financial_type_id', $row)) {
        $rows[$rowNum]['civicrm_line_item_financial_type_id'] = CRM_Core_PseudoConstant::getLabel('CRM_Contribute_BAO_Contribution', 'financial_type_id', $value);
      }
      if (CRM_Utils_Array::value('civicrm_entity_financial_trxn_amount', $row)) {
        $rows[$rowNum]['civicrm_entity_financial_trxn_amount'] = CRM_Utils_Money::format($rows[$rowNum]['civicrm_entity_financial_trxn_amount'], $rows[$rowNum]['civicrm_financial_trxn_financial_trxn_currency']);
      }
    }
    parent::alterDisplay($rows);
  }

  /**
   * @param $tableName
   * @param $fieldName
   * @param $field
   * @param $alias
   */
  protected function setHeaders($tableName, $fieldName, $field, $alias): void {
    $this->_columnHeaders["{$tableName}_$fieldName"]['title'] = CRM_Utils_Array::value('title', $field);
    $this->_columnHeaders["{$tableName}_$fieldName"]['type'] = CRM_Utils_Array::value('type', $field);
    $this->_columnHeaders["{$tableName}_$fieldName"]['dbAlias'] = CRM_Utils_Array::value('dbAlias', $field);
    $this->_selectAliases[$alias] = $alias;
  }

  public function storeGroupByArray(): void {
    parent::storeGroupByArray();
    if (empty($this->_groupByArray)) {
      $this->_groupByArray = [
        "{$this->_aliases['civicrm_entity_financial_trxn']}.id",
        "{$this->_aliases['civicrm_line_item']}.id",
      ];
      $this->_rollup = FALSE;
    }
  }

}
