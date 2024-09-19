<?php

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */

use CRM_Extendedreport_ExtensionUtil as E;

class CRM_Extendedreport_Form_Report_Pledge_Detail extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_customGroupExtends = ['Pledge'];

  protected $_customGroupGroupBy = TRUE;

  protected $_baseTable = 'civicrm_pledge';

  /**
   * CRM_Extendedreport_Form_Report_Pledge_Detail constructor.
   *
   * @throws \CRM_Core_Exception
   */
  public function __construct() {
    $this->_columns = $this->getColumns('Contact', [
          'fields' => TRUE,
          'order_by' => TRUE,
        ]
      ) + $this->getColumns('Contact')
      + $this->getColumns('Email')
      + $this->getColumns('Pledge', ['group_bys' => FALSE])
      + $this->getColumns('PledgePayment', ['fields_defaults' => ['actual_amount']])
      + $this->getColumns('FinancialType');
    unset($this->_columns['civicrm_pledge_payment']['fields']['status_id']);
    $this->_columns['civicrm_pledge_payment']['metadata']['balance_amount'] = [
      'title' => ts('Balance to Pay'),
      'statistics' => ['sum' => ts('Balance')],
      'type' => CRM_Utils_Type::T_MONEY,
      'is_fields' => TRUE,
      'is_filters' => FALSE,
      'is_order_bys' => FALSE,
      'is_group_bys' => FALSE,
      'is_join_filters' => FALSE,
      'is_aggregate_columns' => FALSE,
      'is_aggregate_rows' => FALSE,
      'alias' => 'pledge_balance_amount',
    ];
    $this->_columns['civicrm_contribution']['group_title'] = E::ts('Report Date');
    $this->_columns['civicrm_contribution']['metadata']['effective_date'] = [
      'type' => CRM_Utils_Type::T_DATE,
      'title' => ts('Do not consider payments or pledges after...'),
      'operatorType' => self::OP_SINGLEDATE,
      'pseudofield' => TRUE,
      'is_fields' => FALSE,
      'is_filters' => TRUE,
      'is_group_bys' => FALSE,
      'is_order_bys' => FALSE,
      'is_join_filters' => FALSE,
      'is_aggregate_columns' => FALSE,
      'is_aggregate_rows' => FALSE,
      'operations' => ['to' => E::ts('Date')],
      'alias' => 'contribution_effective_date',
    ];

    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;
    $defaults = [
      'civicrm_contact' => ['civicrm_contact_display_name', 'civicrm_contact_contact_id'],
      'civicrm_pledge' => ['pledge_amount'],
      'civicrm_pledge_payment' => ['balance_amount'],
    ];
    foreach ($defaults as $entity => $fields) {
      foreach ($fields as $field) {
        $this->_columns[$entity]['fields'][$field]['default'] = 1;
      }
    }
    parent::__construct();
  }

  /**
   *  From function.
   */
  public function from(): void {
    $this->_from = "
            FROM civicrm_pledge {$this->_aliases['civicrm_pledge']}";
    $this->joinPledgePaymentFromPledge();
    $this->_from .= " LEFT JOIN civicrm_financial_type {$this->_aliases['civicrm_financial_type']}
                      ON  ({$this->_aliases['civicrm_pledge']}.financial_type_id =
                          {$this->_aliases['civicrm_financial_type']}.id)
                 LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
                      ON ({$this->_aliases['civicrm_contact']}.id =
                          {$this->_aliases['civicrm_pledge']}.contact_id )
                 $this->_aclFrom ";

    $this->joinEmailFromContact();
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
      $alias = $this->selectStatSum($tableName, $fieldName, $field);
      return " SUM(COALESCE(IF((pledge.status_id =3), {$this->_aliases['civicrm_pledge_payment']}.actual_amount, pledge.amount), 0))
       - COALESCE(sum({$this->_aliases['civicrm_pledge_payment']}.actual_amount), 0) as $alias ";
    }
    if ($fieldName === 'pledge_amount') {
      $alias = $this->selectStatSum($tableName, $fieldName, $field);
      return " SUM(COALESCE(IF((pledge.status_id =3), {$this->_aliases['civicrm_pledge_payment']}.actual_amount, pledge.amount), 0)) as $alias ";
    }

    if ($fieldName === 'next_scheduled_amount') {
      $alias = $this->selectStatSum($tableName, $fieldName, $field);
      return " SUM(COALESCE(IF((pledge.status_id =3), {$this->_aliases['civicrm_pledge_payment']}.actual_amount, pledge.amount), 0)) as $alias ";
    }
    return parent::selectClause($tableName, $tableKey, $fieldName, $field);

  }

}
