<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
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
 * @copyright CiviCRM LLC (c) 2004-2013
 *
 * Like contribution detail but with more custom fields.
 */
class CRM_Extendedreport_Form_Report_Contribute_DetailExtended extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_summary = NULL;
  protected $_allBatches = NULL;

  protected $groupConcatTested = TRUE;

  protected $_customGroupExtends = array(
    'Contribution',
    'Individual',
    'Contact',
    'Organization',
    'Household',
  );

  protected $isTempTableBuilt = FALSE;

  protected $_baseTable = 'civicrm_contribution';

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
    $this->_columns = $this->getColumns('Contact',  array(
      'fields_defaults' => array('display_name', 'id'),
    ))
    + $this->getColumns('Email')
    + $this->getColumns('Phone')
    + $this->getColumns('Contribution', array(
      'fields_defaults' => array('receive_date', 'id', 'total_amount'),
      'filters_defaults' => array('contribution_status_id' => array(1), 'is_test' => 0),
      'group_bys_defaults' => ['id' => TRUE],
    ));

    $this->_columns['civicrm_contribution']['fields']['id']['required'] = TRUE;
    $this->_columns['civicrm_contribution']['fields']['currency']['required'] = TRUE;
    $this->_columns['civicrm_contribution']['fields']['currency']['no_display'] = TRUE;

    $this->_columns['civicrm_contribution_ordinality'] = array(
      'dao' => 'CRM_Contribute_DAO_Contribution',
      'alias' => 'cordinality',
      'metadata' => ['ordinality' => [
        'is_filters' => TRUE,
        'is_join_filters' => FALSE,
        'is_fields' => FALSE,
        'is_group_bys' => FALSE,
        'is_order_bys' => FALSE,
        'type' => CRM_Utils_Type::T_INT,
        'alias' => 'cordinality_cordinality'
      ]],
      'group_title' => ts('Contribution Ordinality'),

      'filters' => [
        'ordinality' => [
          'title' => ts('Contribution Ordinality'),
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'options' => array(
            0 => 'First by Contributor',
            1 => 'Second or Later by Contributor',
          ),
          'type' => CRM_Utils_Type::T_INT,
        ],
      ],
    );
    $this->_columns += $this->getColumns('Address');
    $this->_columns += $this->getColumns('Note');

    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;

    // Don't show Batch display column and filter unless batches are being used
    $this->_allBatches = CRM_Batch_BAO_Batch::getBatches();
    if (!empty($this->_allBatches)) {
      $this->_columns['civicrm_batch']['dao'] = 'CRM_Batch_DAO_Batch';
      $this->_columns['civicrm_batch']['fields']['batch_id'] = array(
        'name' => 'id',
        'title' => ts('Batch Name'),
      );
      $this->_columns['civicrm_batch']['filters']['bid'] = array(
        'name' => 'id',
        'title' => ts('Batch Name'),
        'type' => CRM_Utils_Type::T_INT,
        'operatorType' => CRM_Report_Form::OP_MULTISELECT,
        'options' => $this->_allBatches,
      );
      $this->_columns['civicrm_entity_batch']['dao'] = 'CRM_Batch_DAO_EntityBatch';
      $this->_columns['civicrm_entity_batch']['fields']['entity_batch_id'] = array(
        'name' => 'batch_id',
        'default' => TRUE,
        'no_display' => TRUE,
      );
    }

    $this->_currencyColumn = 'civicrm_contribution_currency';
    parent::__construct();
  }

  function from() {
    $this->_from = "
        FROM  civicrm_contact      {$this->_aliases['civicrm_contact']} {$this->_aclFrom}
              INNER JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
                      ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_contribution']}.contact_id AND {$this->_aliases['civicrm_contribution']}.is_test = 0";

    if (!empty($this->_params['ordinality_value'])) {
      $this->_from .= "
              INNER JOIN (SELECT c.id, IF(COUNT(oc.id) = 0, 0, 1) AS ordinality FROM civicrm_contribution c LEFT JOIN civicrm_contribution oc ON c.contact_id = oc.contact_id AND oc.receive_date < c.receive_date GROUP BY c.id) {$this->_aliases['civicrm_contribution_ordinality']}
                      ON {$this->_aliases['civicrm_contribution_ordinality']}.id = {$this->_aliases['civicrm_contribution']}.id";
    }

    $this->joinPhoneFromContact();
    $this->joinAddressFromContact();
    $this->joinEmailFromContact();

    // include contribution note
    if ($this->isTableSelected('civicrm_note')) {
      $this->_from .= "
            LEFT JOIN civicrm_note {$this->_aliases['civicrm_note']}
                      ON ( {$this->_aliases['civicrm_note']}.entity_table = 'civicrm_contribution' AND
                           {$this->_aliases['civicrm_contribution']}.id = {$this->_aliases['civicrm_note']}.entity_id )";
    }
    //for contribution batches
    if ($this->_allBatches &&
      (CRM_Utils_Array::value('batch_id', $this->_params['fields']) || !empty($this->_params['bid_value']))
    ) {
      $this->_from .= "
                LEFT JOIN civicrm_entity_financial_trxn tx ON (tx.entity_id = {$this->_aliases['civicrm_contribution']}.id AND
                   tx.entity_table = 'civicrm_contribution')
                 LEFT JOIN  civicrm_entity_batch {$this->_aliases['civicrm_entity_batch']}
                        ON ({$this->_aliases['civicrm_entity_batch']}.entity_id = tx.financial_trxn_id AND
                        {$this->_aliases['civicrm_entity_batch']}.entity_table = 'civicrm_financial_trxn')
                 LEFT JOIN civicrm_batch {$this->_aliases['civicrm_batch']}
                        ON {$this->_aliases['civicrm_batch']}.id = {$this->_aliases['civicrm_entity_batch']}.batch_id";
    }

  }

  function statistics(&$rows) {
    $statistics = parent::statistics($rows);

    $totalAmount = $average = array();
    $count = 0;
    $select = "
        SELECT COUNT({$this->_aliases['civicrm_contribution']}.total_amount ) as count,
               SUM( {$this->_aliases['civicrm_contribution']}.total_amount ) as amount,
               ROUND(AVG({$this->_aliases['civicrm_contribution']}.total_amount), 2) as avg,
               {$this->_aliases['civicrm_contribution']}.currency as currency
        ";

    $group = "\nGROUP BY {$this->_aliases['civicrm_contribution']}.currency";
    $sql = "{$select} {$this->_from} {$this->_where} {$group}";
    $dao = CRM_Core_DAO::executeQuery($sql);

    while ($dao->fetch()) {
      $totalAmount[] = CRM_Utils_Money::format($dao->amount, $dao->currency) . " (" . $dao->count . ")";
      $average[] = CRM_Utils_Money::format($dao->avg, $dao->currency);
      $count += $dao->count;
    }
    $statistics['counts']['amount'] = array(
      'title' => ts('Total Amount (Donations)'),
      'value' => implode(',  ', $totalAmount),
      'type' => CRM_Utils_Type::T_STRING,
    );
    $statistics['counts']['count'] = array(
      'title' => ts('Total Donations'),
      'value' => $count,
    );
    $statistics['counts']['avg'] = array(
      'title' => ts('Average'),
      'value' => implode(',  ', $average),
      'type' => CRM_Utils_Type::T_STRING,
    );

    // Stats for soft credits
    if ($this->_softFrom && CRM_Utils_Array::value('contribution_or_soft_value', $this->_params) != 'contributions_only') {
      $totalAmount = $average = array();
      $count = 0;
      $select = "
SELECT COUNT(contribution_soft_civireport.amount ) as count,
       SUM(contribution_soft_civireport.amount ) as amount,
       ROUND(AVG(contribution_soft_civireport.amount), 2) as avg,
       {$this->_aliases['civicrm_contribution']}.currency as currency";
      $sql = "
{$select}
{$this->_softFrom}
GROUP BY {$this->_aliases['civicrm_contribution']}.currency";
      $dao = CRM_Core_DAO::executeQuery($sql);
      while ($dao->fetch()) {
        $totalAmount[] = CRM_Utils_Money::format($dao->amount, $dao->currency) . " (" . $dao->count . ")";
        $average[] = CRM_Utils_Money::format($dao->avg, $dao->currency);
        $count += $dao->count;
      }
      $statistics['counts']['softamount'] = array(
        'title' => ts('Total Amount (Soft Credits)'),
        'value' => implode(',  ', $totalAmount),
        'type' => CRM_Utils_Type::T_STRING,
      );
      $statistics['counts']['softcount'] = array(
        'title' => ts('Total Soft Credits'),
        'value' => $count,
      );
      $statistics['counts']['softavg'] = array(
        'title' => ts('Average (Soft Credits)'),
        'value' => implode(',  ', $average),
        'type' => CRM_Utils_Type::T_STRING,
      );
    }

    return $statistics;
  }

  function alterDisplay(&$rows) {
    $entryFound = FALSE;
    $display_flag = $prev_cid = $cid = 0;
    $contributionStatus = CRM_Contribute_PseudoConstant::contributionStatus();
    $contributionPages = CRM_Contribute_PseudoConstant::contributionPage();
    $honorTypes = CRM_Core_OptionGroup::values('honor_type', FALSE, FALSE, FALSE, NULL, 'label');


    foreach ($rows as $rowNum => $row) {
      if (!empty($this->_noRepeats) && $this->_outputMode != 'csv') {
        // don't repeat contact details if its same as the previous row
        if (array_key_exists('civicrm_contact_id', $row)) {
          if ($cid = $row['civicrm_contact_id']) {
            if ($rowNum == 0) {
              $prev_cid = $cid;
            }
            else {
              if ($prev_cid == $cid) {
                $display_flag = 1;
                $prev_cid = $cid;
              }
              else {
                $display_flag = 0;
                $prev_cid = $cid;
              }
            }

            if ($display_flag) {
              foreach ($row as $colName => $colVal) {
                // Hide repeats in no-repeat columns, but not if the field's a section header
                if (in_array($colName, $this->_noRepeats) && !array_key_exists($colName, $this->_sections)) {
                  unset($rows[$rowNum][$colName]);
                }
              }
            }
            $entryFound = TRUE;
          }
        }
      }


      // convert donor sort name to link
      if (array_key_exists('civicrm_contact_sort_name', $row) &&
        CRM_Utils_Array::value('civicrm_contact_sort_name', $rows[$rowNum]) &&
        array_key_exists('civicrm_contact_id', $row)
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view",
          'reset=1&cid=' . $row['civicrm_contact_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_contact_sort_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_sort_name_hover'] = ts("View Contact Summary for this Contact.");
      }

      // convert honoree sort name to link
      if (array_key_exists('civicrm_contact_honor_sort_name_honor', $row) &&
        CRM_Utils_Array::value('civicrm_contact_honor_sort_name_honor', $rows[$rowNum]) &&
        array_key_exists('civicrm_contact_honor_id_honor', $row)
      ) {

        $url = CRM_Utils_System::url("civicrm/contact/view",
          'reset=1&cid=' . $row['civicrm_contact_honor_id_honor'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_contact_honor_sort_name_honor_link'] = $url;
        $rows[$rowNum]['civicrm_contact_honor_sort_name_honor_hover'] = ts("View Contact Summary for Honoree.");
      }

      if ($value = CRM_Utils_Array::value('civicrm_contribution_contribution_status_id', $row)) {
        $rows[$rowNum]['civicrm_contribution_contribution_status_id'] = $contributionStatus[$value];
        $entryFound = TRUE;
      }
      if ($value = CRM_Utils_Array::value('civicrm_contribution_contribution_page_id', $row)) {
        $rows[$rowNum]['civicrm_contribution_contribution_page_id'] = $contributionPages[$value];
        $entryFound = TRUE;
      }

      if ($value = CRM_Utils_Array::value('civicrm_contribution_honor_type_id', $row)) {
        $rows[$rowNum]['civicrm_contribution_honor_type_id'] = $honorTypes[$value];
        $entryFound = TRUE;
      }
      if (array_key_exists('civicrm_batch_batch_id', $row)) {
        if ($value = $row['civicrm_batch_batch_id']) {
          $rows[$rowNum]['civicrm_batch_batch_id'] = CRM_Core_DAO::getFieldValue('CRM_Batch_DAO_Batch', $value, 'title');
        }
        $entryFound = TRUE;
      }

      // Contribution amount links to viewing contribution
      if (($value = CRM_Utils_Array::value('civicrm_contribution_total_amount_sum', $row)) &&
        CRM_Core_Permission::check('access CiviContribute')
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view/contribution",
          "reset=1&id=" . $row['civicrm_contribution_contribution_id'] . "&cid=" . $row['civicrm_contact_id'] . "&action=view&context=contribution&selectedChild=contribute",
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_contribution_total_amount_sum_link'] = $url;
        $rows[$rowNum]['civicrm_contribution_total_amount_sum_hover'] = ts("View Details of this Contribution.");
        $entryFound = TRUE;
      }

      // convert campaign_id to campaign title
      if (array_key_exists('civicrm_contribution_campaign_id', $row)) {
        if ($value = $row['civicrm_contribution_campaign_id']) {
          $rows[$rowNum]['civicrm_contribution_campaign_id'] = $this->activeCampaigns[$value];
          $entryFound = TRUE;
        }
      }

      // soft credits
      if (array_key_exists('civicrm_contribution_soft_credits', $row) &&
        'Contribution' == CRM_Utils_Array::value('civicrm_contribution_contribution_or_soft', $rows[$rowNum]) &&
        array_key_exists('civicrm_contribution_contribution_id', $row)
      ) {
        $query = "
SELECT civicrm_contact_id, civicrm_contact_sort_name, civicrm_contribution_total_amount_sum, civicrm_contribution_currency
FROM   civireport_contribution_detail_temp2
WHERE  civicrm_contribution_contribution_id={$row['civicrm_contribution_contribution_id']}";
        $dao = CRM_Core_DAO::executeQuery($query);
        $string = '';
        $separator = ($this->_outputMode !== 'csv') ? "<br/>" : ' ';
        while ($dao->fetch()) {
          $url = CRM_Utils_System::url("civicrm/contact/view", 'reset=1&cid=' . $dao->civicrm_contact_id);
          $string = $string . ($string ? $separator : '') . "<a href='{$url}'>{$dao->civicrm_contact_sort_name}</a> " .
            CRM_Utils_Money::format($dao->civicrm_contribution_total_amount_sum, $dao->civicrm_contribution_currency);
        }
        $rows[$rowNum]['civicrm_contribution_soft_credits'] = $string;
      }

      if (array_key_exists('civicrm_contribution_soft_credit_for', $row) &&
        'Soft Credit' == CRM_Utils_Array::value('civicrm_contribution_contribution_or_soft', $rows[$rowNum]) &&
        array_key_exists('civicrm_contribution_contribution_id', $row)
      ) {
        $query = "
SELECT civicrm_contact_id, civicrm_contact_sort_name
FROM   civireport_contribution_detail_temp1
WHERE  civicrm_contribution_contribution_id={$row['civicrm_contribution_contribution_id']}";
        $dao = CRM_Core_DAO::executeQuery($query);
        $string = '';
        while ($dao->fetch()) {
          $url = CRM_Utils_System::url("civicrm/contact/view", 'reset=1&cid=' . $dao->civicrm_contact_id);
          $string = $string . "\n<a href='{$url}'>{$dao->civicrm_contact_sort_name}</a>";
        }
        $rows[$rowNum]['civicrm_contribution_soft_credit_for'] = $string;
      }

      $entryFound = $this->alterDisplayAddressFields($row, $rows, $rowNum, 'contribute/detail', 'List all contribution(s) for this ') ? TRUE : $entryFound;

      // skip looking further in rows, if first row itself doesn't
      // have the column we need
      if (!$entryFound) {
        break;
      }
    }
    parent::alterDisplay($rows);
  }

  function sectionTotals() {

    // Reports using order_bys with sections must populate $this->_selectAliases in select() method.
    if (empty($this->_selectAliases)) {
      return;
    }

    if (!empty($this->_sections)) {
      // build the query with no LIMIT clause
      $select = str_ireplace('SELECT SQL_CALC_FOUND_ROWS ', 'SELECT ', $this->_select);
      $sql = "{$select} {$this->_from} {$this->_where} {$this->_groupBy} {$this->_having} {$this->_orderBy}";

      // pull section aliases out of $this->_sections
      $sectionAliases = array_keys($this->_sections);

      $ifnulls = array();
      foreach (array_merge($sectionAliases, $this->_selectAliases) as $alias) {
        $ifnulls[] = "ifnull($alias, '') as $alias";
      }

      /* Group (un-limited) report by all aliases and get counts. This might
      * be done more efficiently when the contents of $sql are known, ie. by
      * overriding this method in the report class.
      */

      $addtotals = '';

      if (array_search("civicrm_contribution_total_amount_sum", $this->_selectAliases) !== FALSE) {
        $addtotals = ", sum(civicrm_contribution_total_amount_sum) as sumcontribs";
        $showsumcontribs = TRUE;
      }

      $query = "select "
        . implode(", ", $ifnulls)
        . "$addtotals, count(*) as ct from civireport_contribution_detail_temp3 group by " . implode(", ", $sectionAliases);
      // initialize array of total counts
      $sumcontribs = $totals = array();
      $dao = CRM_Core_DAO::executeQuery($query);
      while ($dao->fetch()) {

        // let $this->_alterDisplay translate any integer ids to human-readable values.
        $rows[0] = $dao->toArray();
        $this->alterDisplay($rows);
        $row = $rows[0];

        // add totals for all permutations of section values
        $values = array();
        $i = 1;
        $aliasCount = count($sectionAliases);
        foreach ($sectionAliases as $alias) {
          $values[] = $row[$alias];
          $key = implode(CRM_Core_DAO::VALUE_SEPARATOR, $values);
          if ($i == $aliasCount) {
            // the last alias is the lowest-level section header; use count as-is
            $totals[$key] = $dao->ct;
            if ($showsumcontribs) {
              $sumcontribs[$key] = $dao->sumcontribs;
            }
          }
          else {
            // other aliases are higher level; roll count into their total
            $totals[$key] = (array_key_exists($key, $totals)) ? $totals[$key] + $dao->ct : $dao->ct;
            if ($showsumcontribs) {
              $sumcontribs[$key] = array_key_exists($key, $sumcontribs) ? $sumcontribs[$key] + $dao->sumcontribs : $dao->sumcontribs;
            }
          }
        }
      }
      if ($showsumcontribs) {
        $totalandsum = array();
        // ts exception to avoid having ts("%1 %2: %3")
        $title = '%1 contributions / soft-credits: %2';

        if (CRM_Utils_Array::value('contribution_or_soft_value', $this->_params) == 'contributions_only') {
          $title = '%1 contributions: %2';
        }
        else {
          if (CRM_Utils_Array::value('contribution_or_soft_value', $this->_params) == 'soft_credits_only') {
            $title = '%1 soft-credits: %2';
          }
        }
        foreach ($totals as $key => $total) {
          $totalandsum[$key] = ts($title, array(
            1 => $total,
            2 => CRM_Utils_Money::format($sumcontribs[$key])
          ));
        }
        $this->assign('sectionTotals', $totalandsum);
      }
      else {
        $this->assign('sectionTotals', $totals);
      }
    }
  }
}

