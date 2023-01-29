<?php

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */

class CRM_Extendedreport_Form_Report_Pledge_PaidAndCommitted extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_customGroupExtends = ['Pledge'];

  public $_drilldownReport = ['pledge/detail' => 'Pledge Details'];

  protected $_customGroupGroupBy = TRUE;

  /**
   * Class constructor.
   *
   * @throws \CRM_Core_Exception
   */
  public function __construct() {
    $this->_columns = $this->getColumns('Contact', [
          'fields' => TRUE,
          'order_by' => TRUE,
        ]
      ) + $this->getColumns('Contact')
      + $this->getColumns('FinancialType')
      + $this->buildColumns([
        'actual_amount' => [
          'title' => ts('Amount Paid'),
          'statistics' => ['sum' => ts('Amount Paid')],
          'type' => CRM_Utils_Type::T_MONEY,
          'is_fields' => TRUE,
          'is_filters' => FALSE,
          'is_join_filters' => FALSE,
          'is_group_bys' => FALSE,
          'is_order_bys' => FALSE,
          'is_aggregate_columns' => FALSE,
          'is_aggregate_rows' => FALSE,
        ],
      ],
        'civicrm_pledge_payment');

    $this->_columns += $this->getColumns('Pledge', ['fields' => TRUE]);

    $this->_columns['civicrm_pledge']['metadata']['balance_amount'] = [
      'title' => 'Balance to Pay',
      'type' => CRM_Utils_Type::T_MONEY,
      'dbAlias' => "(COALESCE(sum(pledge.amount), 0) - COALESCE(sum(pledge_payment_civireport.actual_amount), 0))",
      'is_fields' => TRUE,
      'is_filters' => FALSE,
      'is_join_filters' => FALSE,
      'is_group_bys' => TRUE,
      'is_order_bys' => FALSE,
      'is_aggregate_columns' => FALSE,
      'is_aggregate_rows' => FALSE,
    ];

    $this->_columns['civicrm_pledge']['fields']['balance_amount'] = [
      'title' => 'Balance to Pay',
      'statistics' => ['sum' => ts('Balance')],
      'type' => CRM_Utils_Type::T_MONEY,
    ];

    $this->_columns['civicrm_pledge']['order_bys']['balance_amount'] = [
      'title' => 'Balance to Pay',
      'type' => CRM_Utils_Type::T_MONEY,
    ];

    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;
    parent::__construct();
    CRM_Core_DAO::disableFullGroupByMode();
  }

  public function from(): void {
    $this->_from = "
            FROM civicrm_pledge {$this->_aliases['civicrm_pledge']}
            LEFT JOIN
            (SELECT pledge_id, sum(actual_amount) as actual_amount FROM
              civicrm_pledge_payment
              GROUP BY pledge_id
            ) as {$this->_aliases['civicrm_pledge_payment']} ON {$this->_aliases['civicrm_pledge_payment']}.pledge_id = {$this->_aliases['civicrm_pledge']}.id
            LEFT JOIN civicrm_financial_type {$this->_aliases['civicrm_financial_type']}
                      ON  ({$this->_aliases['civicrm_pledge']}.financial_type_id =
                          {$this->_aliases['civicrm_financial_type']}.id)
                 LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
                      ON ({$this->_aliases['civicrm_contact']}.id =
                          {$this->_aliases['civicrm_pledge']}.contact_id )
                 $this->_aclFrom ";

    if ($this->isTableSelected('civicrm_address')) {
      $this->joinAddressFromContact();
    }

    if ($this->isTableSelected('civicrm_email')) {
      $this->joinEmailFromContact();
    }
  }

  /**
   * Add balance amount calculation.
   *
   * @param string $tableName
   * @param string $tableKey
   * @param string $fieldName
   * @param array $field
   *
   * @return string
   */
  public function selectClause(&$tableName, $tableKey, &$fieldName, &$field): string {
    if ($fieldName === 'balance_amount') {
      $this->_columnHeaders["{$tableName}_$fieldName"]['title'] = CRM_Utils_Array::value('title', $field);
      $this->_columnHeaders["{$tableName}_$fieldName"]['type'] = CRM_Utils_Array::value('type', $field);
      $this->_statFields['Balance to Pay'] = "{$tableName}_$fieldName";
      return " COALESCE(sum(pledge.amount), 0) - COALESCE(sum({$this->_aliases['civicrm_pledge_payment']}.actual_amount), 0) as civicrm_pledge_balance_amount ";
    }
    return '';
  }

  /**
   * Block parent re-ordering of headers.
   */
  public function reOrderColumnHeaders(): void {
  }

}
