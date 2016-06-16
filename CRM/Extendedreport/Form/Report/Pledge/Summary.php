<?php

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2010
 *            $Id$
 *
 */

class CRM_Extendedreport_Form_Report_Pledge_Summary extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_summary = NULL;
  protected $_totalPaid = FALSE;
  protected $_customGroupExtends = array(
    'Pledge',
  );
  public $_drilldownReport = array('pledge/details' => 'Pledge Details');
  protected $_customGroupGroupBy = TRUE;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_columns =
      $this->getColumns('Campaign')
      + $this->getColumns('Contact', array(
          'fields' => TRUE,
          'order_by' => TRUE,
        )
      ) + $this->getColumns('Contact')
      + $this->getColumns('Email')
      + $this->getColumns('FinancialType')
      + $this->getColumns('Pledge', array('fields' => TRUE))
      + $this->getColumns('PledgePayment');
    $this->_columns['civicrm_pledge']['fields']['balance_amount'] = array(
      'title' => 'Balance to Pay',
      'statistics' => array('sum' => ts('Balance')),
      'type' => CRM_Utils_Type::T_MONEY,
    );

    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;
    parent::__construct();
  }

  function from() {
    $this->_from = "
            FROM civicrm_pledge {$this->_aliases['civicrm_pledge']}";
    $this->joinCampaignFromPledge();
    $this->joinPledgePaymentFromPledge();
    $this->_from .= "
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
      return " SUM(COALESCE(IF((pledge.status_id =3), pledge_payment_civireport.actual_amount, pledge.amount), 0))
        - COALESCE(sum(pledge_payment_civireport.actual_amount), 0) as $alias ";
    }
    if ($fieldName == 'pledge_amount') {
      $alias = $this->selectStatSum($tableName, $fieldName, $field);
      return " SUM(COALESCE(IF((pledge.status_id =3), pledge_payment_civireport.actual_amount, pledge.amount), 0)) as $alias ";
    }
  }

  /**
   * Block parent re-ordering of headers.
   */
  function reOrderColumnHeaders() {

  }

}
