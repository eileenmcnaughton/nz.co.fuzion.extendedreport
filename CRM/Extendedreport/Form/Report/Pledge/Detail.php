<?php

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2010
 *            $Id$
 *
 */

class CRM_Extendedreport_Form_Report_Pledge_Detail extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_summary = NULL;
  protected $_totalPaid = FALSE;
  protected $_customGroupExtends = array(
    'Pledge',
  );
  protected $_customGroupGroupBy = TRUE;
  protected $_baseTable = 'civicrm_pledge';

  function __construct() {
    $this->_columns = $this->getColumns('Contact', array(
          'fields' => TRUE,
          'order_by' => TRUE,
        )
      ) + $this->getColumns('Contact')
      + $this->getColumns('Email')
      + $this->getColumns('Pledge', array('group_bys' => FALSE))
      + $this->getColumns('PledgePayment', array('fields_defaults' => array('actual_amount')))
      + $this->getColumns('FinancialType');
    $this->_columns['civicrm_pledge_payment']['fields']['balance_amount'] = array(
      'title' => ts('Balance to Pay'),
      'statistics' => array('sum' => ts('Balance')),
      'type' => CRM_Utils_Type::T_MONEY,
    );
    $this->_columns['civicrm_contribution']['filters']['effective_date'] = array(
      'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
      'title' => ts('Do not consider payments after...'),
      'operatorType' => self::OP_SINGLEDATE,
      'pseudofield' => TRUE,
    );
    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;
    $defaults = array(
      'civicrm_contact' => array('civicrm_contact_display_name', 'civicrm_contact_contact_id'),
      'civicrm_pledge' => array('pledge_amount'),
      'civicrm_pledge_payment' => array('balance_amount'),
    );
    foreach ($defaults as $entity => $fields) {
      foreach ($fields as $field) {
        $this->_columns[$entity]['fields'][$field]['default'] = 1;
      }
    }
    parent::__construct();
  }

  function from() {
    $this->_from = "
            FROM civicrm_pledge {$this->_aliases['civicrm_pledge']}";
    $this->joinPledgePaymentFromPledge();
    $this->_from .= " LEFT JOIN civicrm_financial_type {$this->_aliases['civicrm_financial_type']}
                      ON  ({$this->_aliases['civicrm_pledge']}.financial_type_id =
                          {$this->_aliases['civicrm_financial_type']}.id)
                 LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
                      ON ({$this->_aliases['civicrm_contact']}.id =
                          {$this->_aliases['civicrm_pledge']}.contact_id )
                 {$this->_aclFrom} ";

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
  function selectClause(&$tableName, $tableKey, &$fieldName, &$field) {
    if ($fieldName == 'balance_amount') {
      $alias = $this->selectStatSum($tableName, $fieldName, $field);
      return " SUM(COALESCE(IF((pledge.status_id =3), {$this->_aliases['civicrm_pledge_payment']}.actual_amount, pledge.amount), 0))
       - COALESCE(sum({$this->_aliases['civicrm_pledge_payment']}.actual_amount), 0) as $alias ";
    }
    if ($fieldName == 'pledge_amount') {
      $alias = $this->selectStatSum($tableName, $fieldName, $field);
      return " SUM(COALESCE(IF((pledge.status_id =3), {$this->_aliases['civicrm_pledge_payment']}.actual_amount, pledge.amount), 0)) as $alias ";
    }

    if ($fieldName == 'next_scheduled_amount') {
      $alias = $this->selectStatSum($tableName, $fieldName, $field);
      return " SUM(COALESCE(IF((pledge.status_id =3), {$this->_aliases['civicrm_pledge_payment']}.actual_amount, pledge.amount), 0)) as $alias ";
    }
    return parent::selectClause($tableName, $tableKey, $fieldName, $field);

  }

}
