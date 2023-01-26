<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2015                                |
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

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */
class CRM_Extendedreport_Form_Report_Contribute_Overview extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_charts = [
    '' => 'Tabular',
    'barChart' => 'Bar Chart',
    'pieChart' => 'Pie Chart',
  ];

  protected $_customGroupExtends = ['Contribution', 'Contact', 'Individual', 'Household', 'Organization'];

  protected $_customGroupGroupBy = TRUE;

  public $_drilldownReport = ['contribution/detailextended' => 'Link to Detail Report'];

  /**
   * To what frequency group-by a date column
   *
   * @var array
   */
  protected $_groupByDateFreq = [
    'MONTH' => 'Month',
    'YEARWEEK' => 'Week',
    'QUARTER' => 'Quarter',
    'YEAR' => 'Year',
    'FISCALYEAR' => 'Fiscal Year',
  ];

  /**
   * Class constructor.
   */
  public function __construct() {

    // Check if CiviCampaign is a) enabled and b) has active campaigns
    $config = CRM_Core_Config::singleton();
    $campaignEnabled = in_array("CiviCampaign", $config->enableComponents);
    if ($campaignEnabled) {
      $getCampaigns = CRM_Campaign_BAO_Campaign::getPermissionedCampaigns(NULL, NULL, TRUE, FALSE, TRUE);
      $this->activeCampaigns = $getCampaigns['campaigns'];
      asort($this->activeCampaigns);
    }

    $this->_columns = $this->getColumns('Contact', ['group_by' => TRUE])
      + $this->getColumns('Email', ['group_by' => TRUE])
      + $this->getColumns('Phone', ['group_by' => TRUE])
      + $this->getColumns('Contribution', ['group_by' => TRUE])
      + $this->getColumns('Batch', ['group_by' => TRUE])
      + $this->getColumns('Address');

    // If we have a campaign, build out the relevant elements
    if ($campaignEnabled && !empty($this->activeCampaigns)) {
      $this->_columns['civicrm_contribution']['fields']['campaign_id'] = [
        'title' => 'Campaign',
        'default' => 'false',
      ];
      $this->_columns['civicrm_contribution']['filters']['campaign_id'] = [
        'title' => ts('Campaign'),
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => $this->activeCampaigns,
      ];
      $this->_columns['civicrm_contribution']['group_bys']['campaign_id'] = ['title' => ts('Campaign')];
    }

    $this->_tagFilter = TRUE;
    $this->_groupFilter = TRUE;
    $this->_currencyColumn = 'civicrm_contribution_currency';
    parent::__construct();
  }

  /**
   * Set form rules.
   *
   * @param array $fields
   * @param array $files
   * @param CRM_Report_Form_Contribute_Summary $self
   *
   * @return array
   */
  public static function formRule($fields, $files, $self): array {
    // Check for searching combination of display columns and
    // grouping criteria
    $ignoreFields = ['total_amount', 'sort_name'];
    $errors = $self->customDataFormRule($fields, $ignoreFields);

    if (empty($fields['fields']['total_amount'])) {
      foreach ([
                 'total_count_value',
                 'total_sum_value',
                 'total_avg_value',
               ] as $val) {
        if (!empty($fields[$val])) {
          $errors[$val] = ts("Please select the Amount Statistics");
        }
      }
    }

    return $errors;
  }

  /**
   * Set from clause.
   *
   * @param string $entity
   */
  public function from($entity = NULL): void {

    $this->_from = "
        FROM civicrm_contact  {$this->_aliases['civicrm_contact']} {$this->_aclFrom}
             INNER JOIN civicrm_contribution   {$this->_aliases['civicrm_contribution']}
                     ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_contribution']}.contact_id AND
                        {$this->_aliases['civicrm_contribution']}.is_test = 0
             LEFT  JOIN civicrm_email {$this->_aliases['civicrm_email']}
                     ON ({$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_email']}.contact_id AND
                        {$this->_aliases['civicrm_email']}.is_primary = 1)

             LEFT  JOIN civicrm_phone {$this->_aliases['civicrm_phone']}
                     ON ({$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_phone']}.contact_id AND
                        {$this->_aliases['civicrm_phone']}.is_primary = 1)";

    if ($this->isTableSelected('civicrm_address')) {
      $this->_from .= "
                  LEFT JOIN civicrm_address {$this->_aliases['civicrm_address']}
                         ON {$this->_aliases['civicrm_contact']}.id =
                            {$this->_aliases['civicrm_address']}.contact_id AND
                            {$this->_aliases['civicrm_address']}.is_primary = 1\n";
    }
    $this->joinBatchFromContribution();
  }

  /**
   * Set statistics.
   *
   * @param array $rows
   *
   * @return array
   */
  public function statistics(&$rows): array {
    $statistics = parent::statistics($rows);

    $softCredit = CRM_Utils_Array::value('soft_amount', $this->_params['fields']);
    $onlySoftCredit = $softCredit && !CRM_Utils_Array::value('total_amount', $this->_params['fields']);
    $group = "\nGROUP BY {$this->_aliases['civicrm_contribution']}.currency";

    $this->from('contribution');
    $this->extendedCustomDataFrom();

    $contriQuery = "
COUNT({$this->_aliases['civicrm_contribution']}.total_amount )        as civicrm_contribution_total_amount_count,
SUM({$this->_aliases['civicrm_contribution']}.total_amount )          as civicrm_contribution_total_amount_sum,
ROUND(AVG({$this->_aliases['civicrm_contribution']}.total_amount), 2) as civicrm_contribution_total_amount_avg,
{$this->_aliases['civicrm_contribution']}.currency                    as currency
{$this->_from} {$this->_where}";

    if ($softCredit) {
      $this->from();
      $select = "
COUNT({$this->_aliases['civicrm_contribution_soft']}.amount )        as civicrm_contribution_soft_soft_amount_count,
SUM({$this->_aliases['civicrm_contribution_soft']}.amount )          as civicrm_contribution_soft_soft_amount_sum,
ROUND(AVG({$this->_aliases['civicrm_contribution_soft']}.amount), 2) as civicrm_contribution_soft_soft_amount_avg";
      $contriQuery = "{$select}, {$contriQuery}";
      $softSQL = "SELECT {$select}, {$this->_aliases['civicrm_contribution']}.currency as currency
      {$this->_from} {$this->_where} {$group} {$this->_having}";
    }

    $contriSQL = "SELECT {$contriQuery} {$group} {$this->_having}";
    $contriDAO = CRM_Core_DAO::executeQuery($contriSQL);

    $totalAmount = $average = $mode = $median = $softTotalAmount = $softAverage = [];
    $count = $softCount = 0;
    while ($contriDAO->fetch()) {
      $totalAmount[]
        = CRM_Utils_Money::format($contriDAO->civicrm_contribution_total_amount_sum, $contriDAO->currency) .
        " (" . $contriDAO->civicrm_contribution_total_amount_count . ")";
      $average[] = CRM_Utils_Money::format($contriDAO->civicrm_contribution_total_amount_avg, $contriDAO->currency);
      $count += $contriDAO->civicrm_contribution_total_amount_count;
    }

    $groupBy = "\n{$group}, {$this->_aliases['civicrm_contribution']}.total_amount";
    $orderBy = "\nORDER BY civicrm_contribution_total_amount_count DESC";
    $modeSQL = "SELECT civicrm_contribution_total_amount_count, amount, currency
    FROM (SELECT {$this->_aliases['civicrm_contribution']}.total_amount as amount,
    {$contriQuery} {$groupBy} {$orderBy}) as mode GROUP BY currency";

    if ($softCredit) {
      $softDAO = CRM_Core_DAO::executeQuery($softSQL);
      while ($softDAO->fetch()) {
        $softTotalAmount[]
          = CRM_Utils_Money::format($softDAO->civicrm_contribution_soft_soft_amount_sum, $softDAO->currency) .
          " (" . $softDAO->civicrm_contribution_soft_soft_amount_count . ")";
        $softAverage[] = CRM_Utils_Money::format($softDAO->civicrm_contribution_soft_soft_amount_avg, $softDAO->currency);
        $softCount += $softDAO->civicrm_contribution_soft_soft_amount_count;
      }
    }

    if (!$onlySoftCredit) {
      $statistics['counts']['amount'] = [
        'title' => ts('Total Amount'),
        'value' => implode(',  ', $totalAmount),
        'type' => CRM_Utils_Type::T_STRING,
      ];
      $statistics['counts']['count'] = [
        'title' => ts('Total Contributions'),
        'value' => $count,
      ];
      $statistics['counts']['avg'] = [
        'title' => ts('Average'),
        'value' => implode(',  ', $average),
        'type' => CRM_Utils_Type::T_STRING,
      ];
      $statistics['counts']['mode'] = [
        'title' => ts('Mode'),
        'value' => implode(',  ', $mode),
        'type' => CRM_Utils_Type::T_STRING,
      ];
      $statistics['counts']['median'] = [
        'title' => ts('Median'),
        'value' => implode(',  ', $median),
        'type' => CRM_Utils_Type::T_STRING,
      ];
    }
    if ($softCredit) {
      $statistics['counts']['soft_amount'] = [
        'title' => ts('Total Soft Credit Amount'),
        'value' => implode(',  ', $softTotalAmount),
        'type' => CRM_Utils_Type::T_STRING,
      ];
      $statistics['counts']['soft_count'] = [
        'title' => ts('Total Soft Credits'),
        'value' => $softCount,
      ];
      $statistics['counts']['soft_avg'] = [
        'title' => ts('Average Soft Credit'),
        'value' => implode(',  ', $softAverage),
        'type' => CRM_Utils_Type::T_STRING,
      ];
    }
    return $statistics;
  }

  /**
   * Build table rows for output.
   *
   * @param string $sql
   * @param array $rows
   */
  public function buildRows($sql, &$rows) {
    $dao = CRM_Core_DAO::executeQuery($sql);
    if (!is_array($rows)) {
      $rows = [];
    }

    // use this method to modify $this->_columnHeaders
    $this->modifyColumnHeaders();
    $contriRows = [];
    $unselectedSectionColumns = $this->unselectedSectionColumns();

    //CRM-16338 if both soft-credit and contribution are enabled then process the contribution's
    //total amount's average, count and sum separately and add it to the respective result list
    $softCredit = (!empty($this->_params['fields']['soft_amount']) && !empty($this->_params['fields']['total_amount'])) ? TRUE : FALSE;
    if ($softCredit) {
      $this->from('contribution');
      $contriSQL = "{$this->_select} {$this->_from} {$this->_where} {$this->_groupBy} {$this->_having} {$this->_orderBy} {$this->_limit}";
      $contriDAO = CRM_Core_DAO::executeQuery($contriSQL);
      $contriFields = [
        'civicrm_contribution_total_amount_sum',
        'civicrm_contribution_total_amount_avg',
        'civicrm_contribution_total_amount_count',
      ];
      $contriRows = [];
      while ($contriDAO->fetch()) {
        $contriRow = [];
        foreach ($contriFields as $column) {
          $contriRow[$column] = $contriDAO->$column;
        }
        $contriRows[] = $contriRow;
      }
    }

    $count = 0;
    while ($dao->fetch()) {
      $row = [];
      foreach ($this->_columnHeaders as $key => $value) {
        if ($softCredit && array_key_exists($key, $contriRows[$count])) {
          $row[$key] = $contriRows[$count][$key];
        }
        elseif (property_exists($dao, $key)) {
          $row[$key] = $dao->$key;
        }
      }

      // section headers not selected for display need to be added to row
      foreach ($unselectedSectionColumns as $key => $values) {
        if (property_exists($dao, $key)) {
          $row[$key] = $dao->$key;
        }
      }

      $count++;
      $rows[] = $row;
    }

  }

  /**
   * Build chart.
   *
   * @param array $rows
   */
  public function buildChart(&$rows) {
    $graphRows = [];

    if (!empty($this->_params['charts'])) {
      if (!empty($this->_params['group_bys']['receive_date'])) {

        $contrib = !empty($this->_params['fields']['total_amount']) ? TRUE : FALSE;
        $softContrib = !empty($this->_params['fields']['soft_amount']) ? TRUE : FALSE;

        foreach ($rows as $key => $row) {
          if ($row['civicrm_contribution_receive_date_subtotal']) {
            $graphRows['receive_date'][] = $row['civicrm_contribution_receive_date_start'];
            $graphRows[$this->_interval][] = $row['civicrm_contribution_receive_date_interval'];
            if ($softContrib && $contrib) {
              // both contri & soft contri stats are present
              $graphRows['multiValue'][0][] = $row['civicrm_contribution_total_amount_sum'];
              $graphRows['multiValue'][1][] = $row['civicrm_contribution_soft_soft_amount_sum'];
            }
            elseif ($softContrib) {
              // only soft contributions
              $graphRows['multiValue'][0][] = $row['civicrm_contribution_soft_soft_amount_sum'];
            }
            else {
              // only contributions
              $graphRows['multiValue'][0][] = $row['civicrm_contribution_total_amount_sum'];
            }
          }
        }

        if ($softContrib && $contrib) {
          $graphRows['barKeys'][0] = ts('Contributions');
          $graphRows['barKeys'][1] = ts('Soft Credits');
          $graphRows['legend'] = ts('Contributions and Soft Credits');
        }
        elseif ($softContrib) {
          $graphRows['legend'] = ts('Soft Credits');
        }

        // build the chart.
        $config = CRM_Core_Config::Singleton();
        $graphRows['xname'] = $this->_interval;
        $graphRows['yname'] = "Amount ({$config->defaultCurrency})";
        CRM_Utils_OpenFlashChart::chart($graphRows, $this->_params['charts'], $this->_interval);
        $this->assign('chartType', $this->_params['charts']);
      }
    }
  }

  /**
   * Alter display of rows.
   *
   * Iterate through the rows retrieved via SQL and make changes for display purposes,
   * such as rendering contacts as links.
   *
   * @param array $rows
   *   Rows generated by SQL, with an array for each row.
   */
  public function alterDisplay(&$rows): void {
    $entryFound = FALSE;
    $contributionStatus = CRM_Contribute_PseudoConstant::contributionStatus();

    foreach ($rows as $rowNum => $row) {
      // make count columns point to detail report
      if (!empty($this->_params['group_bys']['receive_date']) &&
        !empty($row['civicrm_contribution_receive_date_start']) &&
        CRM_Utils_Array::value('civicrm_contribution_receive_date_start', $row) &&
        !empty($row['civicrm_contribution_receive_date_subtotal'])
      ) {

        $dateStart = CRM_Utils_Date::customFormat($row['civicrm_contribution_receive_date_start'], '%Y%m%d');
        $endDate = new DateTime($dateStart);
        $dateEnd = [];

        list($dateEnd['Y'], $dateEnd['M'], $dateEnd['d']) = explode(':', $endDate->format('Y:m:d'));

        switch (strtolower($this->_params['group_bys_freq']['receive_date'])) {
          case 'month':
            $dateEnd = date("Ymd", mktime(0, 0, 0, $dateEnd['M'] + 1,
              $dateEnd['d'] - 1, $dateEnd['Y']
            ));
            break;

          case 'year':
            $dateEnd = date("Ymd", mktime(0, 0, 0, $dateEnd['M'],
              $dateEnd['d'] - 1, $dateEnd['Y'] + 1
            ));
            break;

          case 'fiscalyear':
            $dateEnd = date("Ymd", mktime(0, 0, 0, $dateEnd['M'],
              $dateEnd['d'] - 1, $dateEnd['Y'] + 1
            ));
            break;

          case 'yearweek':
            $dateEnd = date("Ymd", mktime(0, 0, 0, $dateEnd['M'],
              $dateEnd['d'] + 6, $dateEnd['Y']
            ));
            break;

          case 'quarter':
            $dateEnd = date("Ymd", mktime(0, 0, 0, $dateEnd['M'] + 3,
              $dateEnd['d'] - 1, $dateEnd['Y']
            ));
            break;
        }
        $url = CRM_Report_Utils_Report::getNextUrl('contribute/detail',
          "reset=1&force=1&receive_date_from={$dateStart}&receive_date_to={$dateEnd}",
          $this->_absoluteUrl,
          $this->_id,
          $this->_drilldownReport
        );
        $rows[$rowNum]['civicrm_contribution_receive_date_start_link'] = $url;
        $rows[$rowNum]['civicrm_contribution_receive_date_start_hover'] = ts('List all contribution(s) for this date unit.');
        $entryFound = TRUE;
      }

      // make subtotals look nicer
      if (array_key_exists('civicrm_contribution_receive_date_subtotal', $row) &&
        !$row['civicrm_contribution_receive_date_subtotal']
      ) {
        $this->fixSubTotalDisplay($rows[$rowNum], $this->_statFields);
        $entryFound = TRUE;
      }

      // convert display name to links
      if (array_key_exists('civicrm_contact_civicrm_contact_display_name', $row) &&
        array_key_exists('civicrm_contact_civicrm_contact_contact_id', $row)
      ) {
        $url = CRM_Report_Utils_Report::getNextUrl('contribution/detailextended', 'reset=1&force=1&civicrm_contact_contact_id_op=eq&civicrm_contact_contact_id_value=' . $row['civicrm_contact_civicrm_contact_contact_id'], $this->_absoluteUrl, $this->_id, $this->_drilldownReport
        );
        $rows[$rowNum]['civicrm_contact_civicrm_contact_display_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_civicrm_contact_display_name_hover'] = ts("Lists detailed contribution(s) for this record.");
        $entryFound = TRUE;
      }

      // convert contribution status id to status name
      if ($value = CRM_Utils_Array::value('civicrm_contribution_contribution_status_id', $row)) {
        $rows[$rowNum]['civicrm_contribution_contribution_status_id'] = $contributionStatus[$value];
        $entryFound = TRUE;
      }

      // If using campaigns, convert campaign_id to campaign title
      if (array_key_exists('civicrm_contribution_campaign_id', $row)) {
        if ($value = $row['civicrm_contribution_campaign_id']) {
          $rows[$rowNum]['civicrm_contribution_campaign_id'] = $this->activeCampaigns[$value];
        }
        $entryFound = TRUE;
      }

      $entryFound = $this->alterDisplayAddressFields($row, $rows, $rowNum, 'contribute/detail', 'List all contribution(s) for this ') ? TRUE : $entryFound;
      $entryFound = $this->alterDisplayContactFields($row, $rows, $rowNum, 'contribute/detail', 'List all contribution(s) for this ') ? TRUE : $entryFound;

      // skip looking further in rows, if first row itself doesn't
      // have the column we need
      if (!$entryFound) {
        break;
      }
    }
    parent::alterDisplay($rows);
  }

}
