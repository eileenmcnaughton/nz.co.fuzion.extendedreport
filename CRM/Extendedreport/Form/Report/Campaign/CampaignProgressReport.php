<?php

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */

class CRM_Extendedreport_Form_Report_Campaign_CampaignProgressReport extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_customGroupExtends = ['Campaign'];

  protected $_baseTable = 'civicrm_campaign';

  protected $_customGroupGroupBy = TRUE;

  /**
   * Class constructor.
   *
   * @throws \CRM_Core_Exception
   */
  public function __construct() {
    $progressSpec = [
      'financial_type_id' => [
        'title' => ts('Financial type'),
        'alter_display' => 'alterFinancialType',
        'type' => CRM_Utils_Type::T_INT,
        'operatorType' => self::OP_MULTISELECT,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_group_bys' => TRUE,
        'is_order_bys' => FALSE,
        'is_join_filters' => FALSE,
        'is_aggregate_columns' => FALSE,
        'is_aggregate_rows' => FALSE,
        'options' => $this->_getOptions('Contribution', 'financial_type_id'),
      ],
      'total_amount' => [
        'title' => ts('Raised'),
        'type' => CRM_Utils_Type::T_MONEY,
        'operatorType' => CRM_Report_Form::OP_FLOAT,
        'statistics' => ['sum' => ts('Total Raised')],
        'is_fields' => TRUE,
        'is_filters' => FALSE,
        'is_group_bys' => FALSE,
        'is_order_bys' => FALSE,
        'is_join_filters' => FALSE,
        'is_aggregate_columns' => FALSE,
        'is_aggregate_rows' => FALSE,
      ],
      'paid_amount' => [
        'title' => ts('Amount received'),
        'type' => CRM_Utils_Type::T_MONEY,
        'operatorType' => CRM_Report_Form::OP_FLOAT,
        'statistics' => ['sum' => ts('Total Received')],
        'is_fields' => TRUE,
        'is_filters' => FALSE,
        'is_group_bys' => FALSE,
        'is_order_bys' => FALSE,
        'is_join_filters' => FALSE,
        'is_aggregate_columns' => FALSE,
        'is_aggregate_rows' => FALSE,
      ],
      'balance_amount' => [
        'title' => ts('Amount outstanding'),
        'type' => CRM_Utils_Type::T_MONEY,
        'operatorType' => CRM_Report_Form::OP_FLOAT,
        'statistics' => ['sum' => ts('Pledges Outstanding')],
        'is_fields' => TRUE,
        'is_filters' => FALSE,
        'is_group_bys' => FALSE,
        'is_order_bys' => FALSE,
        'is_join_filters' => FALSE,
        'is_aggregate_columns' => FALSE,
        'is_aggregate_rows' => FALSE,
      ],
      'is_pledge' => [
        'title' => ts('Type'),
        'type' => CRM_Utils_Type::T_BOOLEAN,
        'operatorType' => CRM_Report_Form::OP_SELECT,
        'options' => [0 => ts('Payment'), 1 => ts('Pledge')],
        'alter_display' => 'alterIsPledge',
        'is_fields' => TRUE,
        'is_filters' => FALSE,
        'is_group_bys' => TRUE,
        'is_order_bys' => FALSE,
        'is_join_filters' => FALSE,
      ],
      'still_to_raise' => [
        'title' => ts('Balance to raise'),
        'type' => CRM_Utils_Type::T_MONEY,
        'operatorType' => CRM_Report_Form::OP_FLOAT,
        'is_fields' => TRUE,
        'is_filters' => FALSE,
        'is_group_bys' => FALSE,
        'is_order_bys' => FALSE,
        'is_join_filters' => FALSE,
        'is_aggregate_columns' => FALSE,
        'is_aggregate_rows' => FALSE,
      ],
      'effective_date' => [
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
        'title' => ts('Date range'),
        'operatorType' => self::OP_SINGLEDATE,
        'pseudofield' => TRUE,
        'is_fields' => FALSE,
        'is_filters' => TRUE,
        'is_group_bys' => FALSE,
        'is_order_bys' => FALSE,
        'is_join_filters' => FALSE,
        'is_aggregate_columns' => FALSE,
        'is_aggregate_rows' => FALSE,
      ],
    ];

    $this->_columns = $this->getColumns('Campaign') + $this->buildColumns($progressSpec, 'progress');

    parent::__construct();
    CRM_Core_DAO::disableFullGroupByMode();
  }

  /**
   * Add from clause
   *
   * @throws \CRM_Core_Exception
   */
  public function from(): void {
    $this->_from = "
      FROM civicrm_campaign {$this->_aliases['civicrm_campaign']}";

    $this->joinProgressTable();
    $this->_aliases['civicrm_contact'] = 'civicrm_contact';

    $this->_from .= "
      LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
      ON ({$this->_aliases['civicrm_contact']}.id = progress.contact_id )
      $this->_aclFrom
    ";
  }

  /**
   * Join on a progress summary.
   *
   * @throws \CRM_Core_Exception
   */
  protected function joinProgressTable(): void {
    $until = CRM_Utils_Array::value('effective_date_value', $this->_params);
    $untilClause = '';
    if ($until) {
      $untilClause = ' AND c.receive_date <="' . CRM_Utils_Type::validate(CRM_Utils_Date::processDate($until, 235959), 'Integer') . '"';
    }
    $this->_from .= " LEFT JOIN

    (
    SELECT CONCAT('p', p.id) as id, contact_id, campaign_id, financial_type_id,
    COALESCE(amount, 0) - COALESCE(cancelled_amount, 0) as total_amount,
    currency,
    COALESCE(paid_amount, 0) as paid_amount,
    COALESCE(amount - paid_amount, 0) - COALESCE(cancelled_amount, 0) as balance_amount,
    1 as is_pledge

FROM civicrm_pledge p
LEFT JOIN
    (SELECT pledge_id,
    sum(if(status_id = 1 $untilClause , actual_amount, 0)) as paid_amount,
    sum(if(status_id = 3 $untilClause , scheduled_amount, 0)) as cancelled_amount

      FROM civicrm_pledge_payment
      LEFT JOIN civicrm_contribution c ON c.id = contribution_id
      GROUP BY pledge_id
     ) as pp
     ON pp.pledge_id = p.id
     WHERE p.is_test = 0
     ";
    if ($until) {
      $this->_from .= ' AND p.create_date <="' . CRM_Utils_Type::validate(CRM_Utils_Date::processDate($until, 235959), 'Integer') . '"';
    }

    $this->_from .= " UNION

 SELECT CONCAT('c', c.id) as id, contact_id, campaign_id, financial_type_id,
 COALESCE(total_amount, 0) as total_amount, c.currency,
 COALESCE(total_amount, 0) as paid_amount,
 0 as balance_amount,
 0 as is_pledge
 FROM civicrm_contribution c
 LEFT JOIN civicrm_pledge_payment pp ON pp.contribution_id = c.id
 WHERE c.contribution_status_id = 1
 AND pp.id IS NULL ";
    if ($until) {
      $this->_from .= ' AND c.receive_date <= "' . CRM_Utils_Type::validate(CRM_Utils_Date::processDate($until, 235959), 'Integer') . '"';
    }

    $this->_from .= ") as progress  ON progress.campaign_id = {$this->_aliases['civicrm_campaign']}.id
    ";
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
    if ($fieldName === 'progress_still_to_raise') {
      $alias = "{$tableName}_$fieldName";
      $this->_columnHeaders[$alias]['title'] = CRM_Utils_Array::value('title', $field);
      $this->_columnHeaders[$alias]['type'] = CRM_Utils_Array::value('type', $field);
      $this->_columnHeaders[$alias]['dbAlias'] = CRM_Utils_Array::value('dbAlias', $field);
      $this->_selectAliases[$alias] = $alias;
      return " COALESCE({$this->_aliases['civicrm_campaign']}.goal_revenue, 0) - SUM(COALESCE(progress.total_amount, 0)) as $alias ";
    }
    return parent::selectClause($tableName, $tableKey, $fieldName, $field);
  }

  /**
   * Block parent re-ordering of headers.
   */
  public function reOrderColumnHeaders(): void {

  }

  /**
   * Alter is pledge output.
   *
   * @param bool $value
   *
   * @return string
   */
  public function alterIsPledge(bool $value): string {
    return str_replace([0, 1], [ts('Payment without pledge'), ts('Pledge')], $value);
  }

  /**
   * @param array $rows
   */
  public function alterDisplay(&$rows): void {
    parent::alterDisplay($rows);
    $this->unsetUnreliableColumnsIfNotCampaignGrouped();
    if (isset($this->_columnHeaders['progress_progress_still_to_raise'])) {
      $move = $this->_columnHeaders['progress_progress_still_to_raise'];
      unset($this->_columnHeaders['progress_progress_still_to_raise']);
      $this->_columnHeaders['progress_progress_still_to_raise'] = $move;
    }

    $runningTotalGoal = $runningTotalLeft = $runningTotalReceived = 0;
    $grandTotalGoal = $grandTotalLeft = $grandTotalReceived = 0;
    foreach ($rows as $index => $row) {
      if (isset($row['civicrm_campaign_campaign_goal_revenue']) && is_numeric($row['civicrm_campaign_campaign_goal_revenue'])) {
        $runningTotalGoal += $row['civicrm_campaign_campaign_goal_revenue'];
        if (isset($row['progress_progress_still_to_raise']) && is_numeric($row['progress_progress_still_to_raise'])) {
          $runningTotalLeft += $row['progress_progress_still_to_raise'];
        }
        if (isset($row['progress_progress_paid_amount_sum']) && is_numeric($row['progress_progress_paid_amount_sum'])) {
          $runningTotalReceived += $row['progress_progress_paid_amount_sum'];
        }
      }
      else {
        $rows[$index]['civicrm_campaign_campaign_goal_revenue'] = $runningTotalGoal;
        $rows[$index]['progress_progress_still_to_raise'] = $runningTotalLeft;
        $rows[$index]['progress_progress_paid_amount_sum'] = $runningTotalReceived;
        $grandTotalLeft += $runningTotalLeft;
        $grandTotalGoal += $runningTotalGoal;
        $grandTotalReceived += $runningTotalReceived;
        $runningTotalGoal = $runningTotalLeft = 0;
        foreach ($rows[$index] as $field => $value) {
          if (is_numeric($value)) {
            $rows[$index][$field] = $value;
          }
        }
      }

    }
    $grandTotalLeft += $runningTotalLeft;
    $grandTotalGoal += $runningTotalGoal;
    $grandTotalReceived += $runningTotalReceived;
    $this->rollupRow['civicrm_campaign_campaign_goal_revenue'] = $grandTotalGoal;
    $this->rollupRow['progress_progress_still_to_raise'] = $grandTotalLeft;
    $this->rollupRow['progress_progress_paid_amount_sum'] = $grandTotalReceived;
    $this->assign('grandStat', $this->rollupRow);
  }

  /**
   * Do we have a group by array that does not include campaign/
   */
  protected function groupByCampaignTypeNotCampaign(): bool {
    return !empty($this->_groupByArray) && !in_array('campaign.id', $this->_groupByArray, TRUE);
  }

  /**
   *  Note: $fieldName param allows inheriting class to build operationPairs
   * specific to a field.
   *
   * @param string $type
   * @param null $fieldName
   *
   * @return array
   */
  public function getOperationPair($type = "string", $fieldName = NULL): array {
    if ($type === (string) self::OP_SINGLEDATE) {
      return [
        'to' => ts('Until Date'),
      ];
    }
    return parent::getOperationPair($type, $fieldName);
  }

  protected function unsetUnreliableColumnsIfNotCampaignGrouped(): void {
    if ($this->groupByCampaignTypeNotCampaign()) {
      if (isset($this->_columnHeaders['progress_still_to_raise'])) {
        unset($this->_columnHeaders['progress_still_to_raise']);
        CRM_Core_Session::setStatus(ts('Currently campaign revenue cannot be calculated against the goal if grouping does not include campaign'));
      }
      if (isset($this->_columnHeaders['civicrm_campaign_campaign_goal_revenue'])) {
        unset($this->_columnHeaders['civicrm_campaign_campaign_goal_revenue']);
        CRM_Core_Session::setStatus(ts('Currently campaign revenue cannot be calculated against the goal if grouping does not include campaign'));
      }
    }
  }

}
