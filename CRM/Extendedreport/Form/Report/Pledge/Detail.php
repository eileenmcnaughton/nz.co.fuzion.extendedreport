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

  function __construct() {
    $this->_columns = $this->getColumns('Contact', array(
          'fields' => TRUE,
          'order_by' => TRUE,
        )
      ) + $this->getColumns('Contact')
      + $this->getColumns('Email')
      + $this->getColumns('Pledge', array('group_bys' => FALSE))
      + $this->getColumns('PledgePayment')
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
      'civicrm_pledge_payment' => array('actual_amount', 'balance_amount'),
    );
    foreach ($defaults as $entity => $fields) {
      foreach ($fields as $field) {
        $this->_columns[$entity]['fields'][$field]['default'] = 1;
      }
    }
    $this->_columns['civicrm_pledge_payment']['fields']['scheduled_date']['pseudofield'] = TRUE;
    $this->_columns['civicrm_pledge_payment']['fields']['scheduled_amount']['pseudofield'] = TRUE;
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

    // include address field if address column is to be included
    if ($this->isTableSelected('civicrm_address')) {
      $this->_from .= "
                 LEFT JOIN civicrm_address {$this->_aliases['civicrm_address']}
                           ON ({$this->_aliases['civicrm_contact']}.id =
                               {$this->_aliases['civicrm_address']}.contact_id) AND
                               {$this->_aliases['civicrm_address']}.is_primary = 1\n";
    }

    // include email field if email column is to be included
    if ($this->isTableSelected('civicrm_email')) {
      $this->_from .= "
                 LEFT JOIN civicrm_email {$this->_aliases['civicrm_email']}
                           ON ({$this->_aliases['civicrm_contact']}.id =
                               {$this->_aliases['civicrm_email']}.contact_id) AND
                               {$this->_aliases['civicrm_email']}.is_primary = 1\n";
    }

  }

  function postProcess() {
    $this->beginPostProcess();

    // get the acl clauses built before we assemble the query
    $this->buildACLClause($this->_aliases['civicrm_contact']);
    $sql = $this->buildQuery();

    $this->addDeveloperTab($sql);
    $rows = $payment = array();
    $this->buildRows($sql, $rows);

    $dao = CRM_Core_DAO::executeQuery($sql);

    // Set pager for the Main Query only which displays basic information
    $this->setPager();
    $this->assign('columnHeaders', $this->_columnHeaders);

    while ($dao->fetch()) {
      $pledgeID = $dao->civicrm_pledge_pledge_id;
      foreach ($this->_columnHeaders as $columnHeadersKey => $columnHeadersValue) {
        if (property_exists($dao, $columnHeadersKey)) {
          $display[$pledgeID][$columnHeadersKey] = $dao->$columnHeadersKey;
        }
      }
      $pledgeIDArray[] = $pledgeID;
    }

    // To Display Payment Details of pledged amount
    // for pledge payments In Progress
      $sqlPayment = "
                 SELECT min(payment.scheduled_date) as scheduled_date,
                        payment.pledge_id,
                        payment.scheduled_amount,
                        pledge.contact_id

                  FROM civicrm_pledge_payment payment
                       LEFT JOIN civicrm_pledge pledge
                                 ON pledge.id = payment.pledge_id
                  LEFT JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']} ON {$this->_aliases['civicrm_contribution']}.id = payment.contribution_id

                  WHERE payment.status_id IN(2, 6)
                  GROUP BY payment.pledge_id";

      $daoPayment = CRM_Core_DAO::executeQuery($sqlPayment);

      while ($daoPayment->fetch()) {
        foreach ($pledgeIDArray as $key => $val) {
          if ($val == $daoPayment->pledge_id) {

            $display[$daoPayment->pledge_id]['scheduled_date'] = $daoPayment->scheduled_date;

            $display[$daoPayment->pledge_id]['scheduled_amount'] = $daoPayment->scheduled_amount;
          }
        }
      }
    foreach ($rows as $index => $row) {
      if (!empty($display[$row['civicrm_pledge_pledge_id']])) {
        $rows[$index]['civicrm_pledge_payment_scheduled_date'] = $display[$row['civicrm_pledge_pledge_id']]['scheduled_date'];
        $rows[$index]['civicrm_pledge_payment_scheduled_amount'] = $display[$row['civicrm_pledge_pledge_id']]['scheduled_amount'];
      }
    }

    $this->formatDisplay($rows, FALSE);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
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
    $alias = "{$tableName}_{$fieldName}_sum";
    if ($fieldName == 'balance_amount') {
      $alias = $this->selectStatSum($tableName, $fieldName, $field);
      return " SUM(COALESCE(IF((pledge.status_id =3), pledge_payment_civireport.actual_amount, pledge.amount), 0)) - COALESCE(sum(pledge_payment_civireport.actual_amount), 0) as $alias ";
    }
    if ($fieldName == 'pledge_amount') {
      $alias = $this->selectStatSum($tableName, $fieldName, $field);
      return " SUM(COALESCE(IF((pledge.status_id =3), pledge_payment_civireport.actual_amount, pledge.amount), 0)) as $alias ";
    }
    return parent::selectClause($tableName, $tableKey, $fieldName, $field);

  }

}
