<?php

/**
 * Class CRM_Extendedreport_Form_Report_Pledge_Income
 */
class CRM_Extendedreport_Form_Report_Pledge_Income extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_baseTable = 'civicrm_pledge_payment';
  protected $_customGroupGroupBy = TRUE;

  protected $_customGroupExtends = [
    'Pledge',
    'Contact',
    'Individual',
    'Organization',
    'Household',
  ];

  protected $_rollup = FALSE;

  /**
   * Class constructor.
   *
   * @throws \CRM_Core_Exception
   */
  public function __construct() {
    $paymentStatuses = array_flip(CRM_Pledge_BAO_PledgePayment::buildOptions('status_id'));
    $this->_columns =
      $this->getColumns('PledgePayment', [
        'fields_defaults' => ['scheduled_amount', 'scheduled_date'],
        'filters_defaults' => [
          'status_id' => [
            $paymentStatuses['Pending'],
            $paymentStatuses['Overdue'],
          ],
        ],
        'fields_excluded' => ['actual_amount'],
        'is_order_bys' => TRUE,
        'is_actions' => FALSE,
      ])
      + $this->getColumns('NextPledgePayment', [
        'prefix' => 'next_',
        'prefix_label' => 'Next ',
        'is_order_bys' => FALSE,
        'is_actions' => TRUE,
      ])
      + $this->getColumns('Contact')
      + $this->getColumns('Pledge');
    parent::__construct();
    CRM_Core_DAO::disableFullGroupByMode();
  }

  /**
   * Declare joins.
   *
   * @return array
   */
  public function fromClauses(): array {
    return [
      'pledge_from_pledge_payment',
      'next_payment_from_pledge',
      'contact_from_pledge',
    ];
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
   * @throws \CRM_Core_Exception
   */
  public function selectClause(&$tableName, $tableKey, &$fieldName, &$field): string {
    if ($fieldName === 'pledge_payment_scheduled_amount') {
      $pledgePaymentStatuses = civicrm_api3('PledgePayment', 'getoptions', ['field' => 'status_id']);
      $toPayIDs = [array_search('Pending', $pledgePaymentStatuses['values'], TRUE),
        array_search('Overdue', $pledgePaymentStatuses['values'], TRUE)
      ];
      $alias = $this->selectStatSum($tableName, $fieldName, $field);
      return " SUM(COALESCE(IF(pledge_payment.status_id IN (" . implode(',', $toPayIDs) . "), {$this->_aliases['civicrm_pledge_payment']}.scheduled_amount, 0))) as $alias ";
    }
    return parent::selectClause($tableName, $tableKey, $fieldName, $field);
  }

  /**
   * Modify column headers.
   */
  public function modifyColumnHeaders(): void {
    $columnsToRemove = [];
    $isGroupByScheduledDate = FALSE;
    if ($this->isSelfGrouped()) {
      $columnsToRemove[] = 'next_civicrm_pledge_payment_next_civicrm_pledge_payment_next_scheduled_date';
    }
    else {
      foreach ($this->_groupByArray as $groupByString) {
        if (stripos($groupByString, 'scheduled_date') !== FALSE) {
          $isGroupByScheduledDate = TRUE;
          $columnsToRemove[] = 'next_civicrm_pledge_payment_next_civicrm_pledge_payment_next_scheduled_date';
        }
      }
      if (!$isGroupByScheduledDate) {
        $columnsToRemove[] = 'civicrm_pledge_payment_civicrm_pledge_payment_scheduled_date';
      }
    }
    foreach ($columnsToRemove as $columnName) {
      unset($this->_columnHeaders[$columnName]);
    }

  }

}
