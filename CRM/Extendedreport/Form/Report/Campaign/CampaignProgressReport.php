<?php

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2010
 *            $Id$
 *
 */

class CRM_Extendedreport_Form_Report_Campaign_CampaignProgressReport extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_summary = NULL;
  protected $_totalPaid = FALSE;
  protected $_customGroupExtends = array(
    'Campaign',
  );
  protected $_baseTable = 'civicrm_campaign';
  protected $_customGroupGroupBy = TRUE;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_columns
      = $this->getColumns('Campaign') +
      array(
        'progress' => array(
          'alias' => 'progress',
          'fields' => array(
            'financial_type_id' => array(
              'title' => ts('Financial type'),
              'alter_display' => 'alterFinancialType',
              'statistics' => array('GROUP_CONCAT'),
            ),
            'total_amount' => array(
              'title' => ts('Raised'),
              'type' => CRM_Utils_Type::T_MONEY,
              'statistics' => array('sum' => ts('Total Raised')),
            ),
            'paid_amount' => array(
              'title' => ts('Amount received'),
              'type' => CRM_Utils_Type::T_MONEY,
              'statistics' => array('sum' => ts('Total Received')),
            ),
            'balance_amount' => array(
              'title' => ts('Amount outstanding'),
              'type' => CRM_Utils_Type::T_MONEY,
              'statistics' => array('sum' => ts('Pledges Outstanding')),
            ),
            'is_pledge' => array(
              'title' => ts('Type'),
              'type' => CRM_Utils_Type::T_BOOLEAN,
              'options' => array(0 => ts('Payment'), 1 => ts('Pledge')),
              'alter_display' => 'alterIsPledge',
              'statistics' => array('GROUP_CONCAT'),
            ),
            'still_to_raise' => array(
              'title' => ts('Balance to raise'),
              'type' => CRM_Utils_Type::T_MONEY,
            ),
          ),
          'filters' => array(
            'effective_date' => array(
              'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
              'title' => ts('Date range'),
              'operatorType' => self::OP_SINGLEDATE,
              'pseudofield' => TRUE,
            ),
            'financial_type_id' => array(
              'title' => ts('Financial type'),
              'alter_display' => 'alterFinancialType',
              'type' => CRM_Utils_Type::T_INT,
              'operatorType' => self::OP_MULTISELECT,
              'options' => $this->_getOptions('Contribution', 'financial_type_id'),
            ),
          ),
          'group_bys' => array(
            'financial_type_id' => array(
              'title' => ts('Financial type'),
              'alter_display' => 'alterFinancialType',
              'type' => CRM_Utils_Type::T_INT,
              'operatorType' => self::OP_MULTISELECT,
              'options' => $this->_getOptions('Contribution', 'financial_type_id'),
            ),
            'is_pledge' => array(
              'title' => ts('Type'),
              'type' => CRM_Utils_Type::T_BOOLEAN,
              'options' => array(0 => ts('Payment'), 1 => ts('Pledge')),
              'alter_display' => 'alterIsPledge',
              'statistics' => array('GROUP_CONCAT'),
            ),
          ),
        ),
      );

    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;
    parent::__construct();
  }

  function from() {
    $this->_from = "
      FROM civicrm_campaign {$this->_aliases['civicrm_campaign']}";

    $this->joinProgressTable();
    $this->_aliases['civicrm_contact'] = 'civicrm_contact';

    $this->_from .= "
      LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
      ON ({$this->_aliases['civicrm_contact']}.id = progress.contact_id )
      {$this->_aclFrom}
    ";
  }

  /**
   * Join on a progress summary.
   */
  protected function joinProgressTable() {
    $until = CRM_Utils_Array::value('effective_date_value', $this->_params);
    $this->_from .= " LEFT JOIN

    (
    SELECT CONCAT('p', p.id) as id, contact_id, campaign_id, financial_type_id,
    COALESCE(amount, 0) as total_amount,
    currency,
    COALESCE(paid_amount, 0) as paid_amount,
    COALESCE(amount - paid_amount, 0) as balance_amount,
    1 as is_pledge

FROM civicrm_pledge p
LEFT JOIN
    (SELECT pledge_id, sum(if(status_id = 1";
    if ($until) {
      $this->_from .= ' AND c.receive_date <="' . CRM_Utils_Type::validate(CRM_Utils_Date::processDate($until, 235959), 'Integer') . '"';
    }
    $this->_from .= ", actual_amount, 0)) as paid_amount
      FROM civicrm_pledge_payment
      LEFT JOIN civicrm_contribution c ON c.id = contribution_id
      GROUP BY pledge_id
     ) as pp
     ON pp.pledge_id = p.id
     WHERE p.is_test = 0";
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
  function selectClause(&$tableName, $tableKey, &$fieldName, &$field) {
    if ($fieldName == 'still_to_raise') {
      $alias = "{$tableName}_{$fieldName}";
      $this->_columnHeaders[$alias]['title'] = CRM_Utils_Array::value('title', $field);
      $this->_columnHeaders[$alias]['type'] = CRM_Utils_Array::value('type', $field);
      $this->_columnHeaders[$alias]['dbAlias'] = CRM_Utils_Array::value('dbAlias', $field);
      $this->_selectAliases[] = $alias;
      return " COALESCE(campaign.goal_revenue, 0) - SUM(COALESCE(progress.total_amount, 0)) as $alias ";
    }
    return parent::selectClause($tableName, $tableKey, $fieldName, $field);
  }

  /**
   * Block parent re-ordering of headers.
   */
  function reOrderColumnHeaders() {

  }

  /**
   * Alter is pledge output.
   *
   * @param bool $value
   *
   * @return string
   */
  function alterIsPledge($value) {
    return str_replace(array(0, 1), array(ts('Payment without pledge'),ts('Pledge')), $value);
  }

  /**
   * @param $rows
   */
  function alterDisplay(&$rows) {
    parent::alterDisplay($rows);
    $this->unsetUnreliableColumnsIfNotCampaignGrouped();
    if (isset($this->_columnHeaders['progress_still_to_raise'])) {
      $move = $this->_columnHeaders['progress_still_to_raise'];
      unset($this->_columnHeaders['progress_still_to_raise']);
      $this->_columnHeaders['progress_still_to_raise'] = $move;
    }

    $runningTotalRaised = $runningTotalLeft = 0;
    $grandTotalRaised = $grandTotalLeft = 0;
    foreach ($rows as $index => $row) {
      if (isset($row['civicrm_campaign_campaign_goal_revenue']) && is_numeric($row['civicrm_campaign_campaign_goal_revenue'])) {
        $runningTotalRaised += $row['civicrm_campaign_campaign_goal_revenue'];
        $runningTotalLeft += $row['progress_still_to_raise'];
      }
      else {
        $rows[$index]['civicrm_campaign_campaign_goal_revenue'] = $runningTotalRaised;
        $rows[$index]['progress_still_to_raise'] = $runningTotalLeft;
        $grandTotalLeft += $runningTotalLeft;
        $grandTotalRaised += $runningTotalRaised;
        $runningTotalRaised = $runningTotalLeft = 0;
        foreach ($rows[$index] as $field => $value) {
          if (is_numeric($value)) {
            $rows[$index][$field] = '<span class="report-label">' . str_replace('$', '', CRM_Utils_Money::format($value)) . '</span>';
          }
        }
      }

    }
    $this->rollupRow['civicrm_campaign_campaign_goal_revenue'] = $runningTotalRaised;
    $this->rollupRow['progress_still_to_raise'] = $runningTotalLeft;
    $this->assign('grandStat', $this->rollupRow);
  }

  /**
   * Do we have a group by array that does not include campaign/
   */
  protected function groupByCampaignTypeNotCampaign() {
    if (!empty($this->_groupByArray)) {
      if (!in_array('campaign.id', $this->_groupByArray)) {
        return TRUE;
      }
    }
    return FALSE;
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
  function getOperationPair($type = "string", $fieldName = NULL) {
    if ($type == self::OP_SINGLEDATE) {
      return array(
        'to' => ts('Until Date'),
      );
    }
    return parent::getOperationPair($type, $fieldName);
  }

  protected function unsetUnreliableColumnsIfNotCampaignGrouped() {
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
