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
    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;
    parent::__construct();
  }

  function from() {
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

    $dao = CRM_Core_DAO::executeQuery($sql);

    // Set pager for the Main Query only which displays basic information
    $this->setPager();
    $this->assign('columnHeaders', $this->_columnHeaders);

    while ($dao->fetch()) {
      $pledgeID = $dao->civicrm_pledge_id;
      foreach ($this->_columnHeaders as $columnHeadersKey => $columnHeadersValue) {
        if (property_exists($dao, $columnHeadersKey)) {
          $display[$pledgeID][$columnHeadersKey] = $dao->$columnHeadersKey;
        }
      }
      $pledgeIDArray[] = $pledgeID;
    }

    // Pledge- Payment Detail Headers
    $tableHeader = array(
      'scheduled_date' => array(
        'type' => CRM_Utils_Type::T_DATE,
        'title' => 'Next Payment Due'
      ),
      'scheduled_amount' => array(
        'type' => CRM_Utils_Type::T_MONEY,
        'title' => 'Next Payment Amount'
      ),
      'balance_due' => array(
        'type' => CRM_Utils_Type::T_MONEY,
        'title' => 'Balance Due'
      ),
      'status_id' => NULL
    );
    foreach ($tableHeader as $k => $val) {
      $this->_columnHeaders[$k] = $val;
    }

    // To Display Payment Details of pledged amount
    // for pledge payments In Progress
    if (!empty($display)) {
      $sqlPayment = "
                 SELECT min(payment.scheduled_date) as scheduled_date,
                        payment.pledge_id,
                        payment.scheduled_amount,
                        pledge.contact_id

                  FROM civicrm_pledge_payment payment
                       LEFT JOIN civicrm_pledge pledge
                                 ON pledge.id = payment.pledge_id

                  WHERE payment.status_id = 2

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

      // Do calculations for Total amount paid AND
      // Balance Due, based on Pledge Status either
      // In Progress, Pending or Completed
      foreach ($display as $pledgeID => $data) {
        $count = $due = $paid = 0;

        // Get Sum of all the payments made
        $payDetailsSQL = "
                    SELECT SUM( payment.actual_amount ) as total_amount
                       FROM civicrm_pledge_payment payment
                       WHERE payment.pledge_id = {$pledgeID} AND
                             payment.status_id = 1";

        $totalPaidAmt = CRM_Core_DAO::singleValueQuery($payDetailsSQL);

        if (CRM_Utils_Array::value('civicrm_pledge_status_id', $data) == 5) {
          $due = $data['civicrm_pledge_amount'] - $totalPaidAmt;
          $paid = $totalPaidAmt;
          $count++;
        }
        else {
          if (CRM_Utils_Array::value('civicrm_pledge_status_id', $data) == 2) {
            $due = $data['civicrm_pledge_amount'];
            $paid = 0;
          }
          else {
            if (CRM_Utils_Array::value('civicrm_pledge_status_id', $data) == 1) {
              $due = 0;
              $paid = $paid + $data['civicrm_pledge_amount'];
            }
          }
        }

        $display[$pledgeID]['total_paid'] = $paid;
        $display[$pledgeID]['balance_due'] = $due;
      }
    }

    // Displaying entire data on the form
    if (!empty($display)) {
      foreach ($display as $key => $value) {
        $row = array();
        foreach ($this->_columnHeaders as $columnKey => $columnValue) {
          if (CRM_Utils_Array::value($columnKey, $value)) {
            $row[$columnKey] = $value[$columnKey];
          }
        }
        $rows[] = $row;
      }
    }
    $this->formatDisplay($rows, FALSE);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }

}
