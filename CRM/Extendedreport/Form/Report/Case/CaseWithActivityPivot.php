<?php

/**
 * Class CRM_Extendedreport_Form_Report_Case_CaseWithActivityPivot
 */
class CRM_Extendedreport_Form_Report_Case_CaseWithActivityPivot extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_baseTable = 'civicrm_case';

  protected $_customGroupAggregates = TRUE;

  protected $_rollup = 'WITH ROLLUP';

  protected $_aggregatesAddPercentage = TRUE;

  protected $isPivot = TRUE;

  protected $_noFields = TRUE;

  protected $_customGroupExtends = ['Case', 'Activity'];

  /**
   * PreConstrain means the query gets run twice - the first time for generating temp tables
   * which go in the from the second time around
   *
   * @var bool
   */
  protected $_preConstrain = TRUE;
  protected $_potentialCriteria = [];

  /**
   *
   * @throws \CRM_Core_Exception
   */
  public function __construct() {
    $this->_columns = $this->getColumns('Case', [
          'fields' => FALSE,
        ]
      )
      + $this->getColumns('Contact', [])
      + $this->_columns = $this->getColumns('Activity', [
          'fields' => FALSE,
        ]
      );
    $this->_columns['civicrm_case']['fields']['id']['required'] = TRUE;
    $this->_columns['civicrm_contact']['fields']['id']['required'] = TRUE;
    //  $this->_columns['civicrm_case']['fields']['id']['alter_display'] = 'alterCaseID';
    $this->_columns['civicrm_case']['fields']['id']['title'] = 'Case';
    $this->_columns['civicrm_contact']['fields']['gender_id']['no_display'] = TRUE;
    $this->_columns['civicrm_contact']['fields']['gender_id']['title'] = 'Gender';

    $this->_tagFilter = TRUE;
    $this->_groupFilter = TRUE;
    parent::__construct();
  }

  /**
   * Generate a temp table to reflect the pre-constrained report group
   * This could be a group of contacts on whom we are going to do a series of
   * contribution comparisons.
   *
   * We apply where criteria from the form to generate this
   *
   * We create a temp table of their ids in the first instance
   * and use this as the base
   *
   * @throws \Exception
   */
  protected function generateTempTable(): void {
    $tempTable = 'civicrm_report_temp_activities' . date('d_H_I') . random_int(1, 10000);
    $sql = "CREATE $this->_temporary TABLE $tempTable
    (`case_id` INT(10) UNSIGNED NULL DEFAULT '0',
    `activity_id` INT(10) UNSIGNED NULL DEFAULT '0',
    INDEX `case_id` (`case_id`),
    INDEX `activity_id` (`activity_id`)
    )
    COLLATE='utf8_unicode_ci'
    ENGINE=HEAP;";
    CRM_Core_DAO::executeQuery($sql);
    $sql = "
      INSERT INTO $tempTable
      SELECT ccase.id, activity_id
      FROM civicrm_case ccase
      LEFT JOIN
        (SELECT
          {$this->_aliases['civicrm_case']}.id as case_id, activity_id
          $this->_from $this->_where
        ) as case_activities ON case_activities.case_id = ccase.id";
    CRM_Core_DAO::executeQuery($sql);
    $this->_caseActivityTable = $tempTable;
  }

  /**
   * @return array
   */
  protected function fromClauses(): array {
    return [
      'contact_from_case',
      'activity_from_case',
    ];
  }

  /**
   * constrainedWhere applies to Where clauses applied AFTER the
   * 'pre-constrained' report temp tables are created. Here we only keep the clauses relating to
   * civicrm_case
   */
  protected function constrainedWhere(): void {
    $this->_where = " WHERE 1";
    foreach ($this->whereClauses as $table => $clauses) {
      if (strpos($table, 'civicrm_case') === 0) {
        foreach ($clauses as $clause) {
          if (!empty($clause)) {
            $this->_where .= " AND " . $clause;
          }
        }
      }
    }
  }
}
