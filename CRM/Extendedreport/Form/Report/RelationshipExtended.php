<?php

/**
 * Class CRM_Extendedreport_Form_Report_RelationshipExtended
 */
class CRM_Extendedreport_Form_Report_RelationshipExtended extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_baseTable = 'civicrm_relationship';

  protected $_primaryContactPrefix = 'contact_a_';

  protected $_customGroupExtends = ['Relationship', 'Contact', 'Individual', 'Household', 'Organization'];

  public $_tagFilterTable = 'contact_a_civicrm_contact';

  private $relationType;

  /**
   * Class constructor.
   *
   * @throws \CRM_Core_Exception
   */
  public function __construct() {
    $this->_tagFilter = TRUE;
    $this->_groupFilter = TRUE;
    $this->_customGroupExtended['civicrm_relationship'] = [
      'extends' => ['Relationship'],
      'title' => ts('Relationship'),
      'filters' => TRUE,
    ];

    $this->_columns = $this->getColumns('Contact', [
          'prefix' => 'contact_a_',
          'prefix_label' => 'Contact A ::',
        ]
      ) + $this->getColumns('Address', [
          'prefix' => 'contact_a_',
          'prefix_label' => 'Contact A ::',
        ]
      ) + $this->getColumns('Email', [
          'prefix' => 'contact_a_',
          'prefix_label' => 'Contact A ::',
        ]
      ) + $this->getColumns('Phone', [
          'prefix' => 'contact_a_',
          'prefix_label' => 'Contact A ::',
          'subquery' => FALSE,
        ]
      ) + $this->getColumns('Contact', [
        'prefix' => 'contact_b_',
        'prefix_label' => 'Contact B ::',
      ]) + $this->getColumns('Address', [
          'prefix' => 'contact_b_',
          'prefix_label' => 'Contact B ::',
        ]
      ) + $this->getColumns('Email', [
        'prefix' => 'contact_b_',
        'prefix_label' => 'Contact B ::',
      ])

      + $this->getColumns('Phone', [
        'prefix' => 'contact_b_',
        'prefix_label' => 'Contact B ::',
        'subquery' => FALSE,
      ])
      + $this->getColumns('Relationship')
      + $this->getColumns('RelationshipType')
      + $this->getColumns('Case', ['filters_defaults' => []]);
    parent::__construct();
  }

   /**
     * Build where clause for tags.
     *
     * @param string $field
     * @param mixed $value
     * @param string $op
     *
     * @return string
     */
    public function whereTagClause($field, $value, $op): string {
      // not using left join in query because if any contact
      // belongs to more than one tag, results duplicate
      // entries.
      $sqlOp = $this->getSQLOperator($op);
      if (!is_array($value)) {
        $value = [$value];
      }
      $clause = "{$field['dbAlias']} IN (" . implode(', ', $value) . ")";
      $entity_table = $this->_tagFilterTable;
      return " {$this->_aliases[$entity_table]}.id {$sqlOp} (
                            SELECT DISTINCT {$this->_aliases['civicrm_tag']}.entity_id
                            FROM civicrm_entity_tag {$this->_aliases['civicrm_tag']}
                            WHERE entity_table = 'civicrm_contact' AND {$clause} ) ";
  }

  public function from(): void {
    $this->setFromBase('civicrm_contact', 'id', $this->_aliases['contact_a_civicrm_contact']);
    $this->_from .= "
      INNER JOIN civicrm_relationship {$this->_aliases['civicrm_relationship']}
          ON ( {$this->_aliases['civicrm_relationship']}.contact_id_a =
          {$this->_aliases['contact_a_civicrm_contact']}.id )
          && ({$this->_aliases['contact_a_civicrm_contact']}.is_deleted = 0)

          INNER JOIN civicrm_contact {$this->_aliases['contact_b_civicrm_contact']}
          ON ( {$this->_aliases['civicrm_relationship']}.contact_id_b =
          {$this->_aliases['contact_b_civicrm_contact']}.id )
          && ({$this->_aliases['contact_b_civicrm_contact']}.is_deleted = 0)

          {$this->_aclFrom}
          LEFT JOIN civicrm_relationship rc ON ({$this->_aliases['contact_b_civicrm_contact']}.id = rc.contact_id_a AND rc.relationship_type_id = 15)
          LEFT JOIN civicrm_relationship rccoordinator ON ({$this->_aliases['contact_b_civicrm_contact']}.id = rccoordinator.contact_id_a AND rccoordinator.relationship_type_id = 8)
          LEFT JOIN civicrm_case case_civireport ON rccoordinator .case_id = case_civireport.id";
    $this->joinRelationshipTypeFromRelationship();

    // include Email Field
    if ($this->isTableSelected('contact_a_civicrm_email')) {
      $this->_from .= "
        LEFT JOIN civicrm_email {$this->_aliases['contact_a_civicrm_email']}
        ON ( {$this->_aliases['contact_a_civicrm_contact']}.id =
          {$this->_aliases['contact_a_civicrm_email']}.contact_id
          AND {$this->_aliases['contact_a_civicrm_email']}.is_primary = 1 )";
    }
    if ($this->isTableSelected('contact_b_civicrm_email')) {
      $this->_from .= "
        LEFT JOIN civicrm_email {$this->_aliases['contact_b_civicrm_email']}
        ON ( {$this->_aliases['contact_b_civicrm_contact']}.id =
          {$this->_aliases['contact_b_civicrm_email']}.contact_id
          AND {$this->_aliases['contact_b_civicrm_email']}.is_primary = 1 )";
    }
    // include phone Field
    if ($this->isTableSelected('contact_a_civicrm_phone')) {
      $this->_from .= "
      LEFT JOIN civicrm_phone {$this->_aliases['contact_a_civicrm_phone']}
        ON ( {$this->_aliases['contact_a_civicrm_contact']}.id =
        {$this->_aliases['contact_a_civicrm_phone']}.contact_id
          AND {$this->_aliases['contact_a_civicrm_phone']}.is_primary = 1 )";
    }
    if ($this->isTableSelected('contact_b_civicrm_phone')) {
      $this->_from .= "
        LEFT JOIN civicrm_phone {$this->_aliases['contact_b_civicrm_phone']}
        ON ( {$this->_aliases['contact_b_civicrm_contact']}.id =
        {$this->_aliases['contact_b_civicrm_phone']}.contact_id
        AND {$this->_aliases['contact_b_civicrm_phone']}.is_primary = 1 )";
    }

    if ($this->isTableSelected('contact_a_civicrm_address')) {
      $this->_from .= "
      LEFT JOIN civicrm_address {$this->_aliases['contact_a_civicrm_address']}
        ON ( {$this->_aliases['contact_a_civicrm_contact']}.id =
        {$this->_aliases['contact_a_civicrm_address']}.contact_id
        AND {$this->_aliases['contact_a_civicrm_address']}.is_primary = 1 )";
    }
    if ($this->isTableSelected('contact_b_civicrm_address')) {
      $this->_from .= "
          LEFT JOIN civicrm_address {$this->_aliases['contact_b_civicrm_address']}
          ON ( {$this->_aliases['contact_b_civicrm_contact']}.id =
        {$this->_aliases['contact_b_civicrm_address']}.contact_id
        AND {$this->_aliases['contact_b_civicrm_address']}.is_primary = 1 )";
    }
  }

  /**
   * @param $rows
   *
   * @return array
   */
  public function statistics(&$rows): array {
    $statistics = parent::statistics($rows);

    $isStatusFilter = FALSE;
    $relStatus = NULL;
    $isActive = $this->_params['is_active_value'] ?? NULL;
    if ($isActive) {
      $relStatus = 'Is equal to Active';
    }
    elseif ($isActive !== NULL) {
      $relStatus = 'Is equal to Inactive';
    }
    if (CRM_Utils_Array::value('filters', $statistics)) {
      foreach ($statistics['filters'] as $id => $value) {
        //for displaying relationship type filter
        if ($value['title'] === 'Relationship') {
          $relTypes = CRM_Core_PseudoConstant::relationshipType();
          $statistics['filters'][$id]['value'] = 'Is equal to ' . $relTypes[$this->_params['relationship_type_id_value']]['label_' . $this->relationType];
        }

        //for displaying relationship status
        if ($value['title'] === 'Relationship Status') {
          $isStatusFilter = TRUE;
          $statistics['filters'][$id]['value'] = $relStatus;
        }
      }
    }
    //for displaying relationship status
    if (!$isStatusFilter && $relStatus) {
      $statistics['filters'][] = [
        'title' => 'Relationship Status',
        'value' => $relStatus,
      ];
    }
    return $statistics;
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function postProcess(): void {
    $this->beginPostProcess();
    $this->relationType = NULL;
    $originalRelationshipTypes = [];

    $relationships = [];
    if (empty($this->_params['relationship_relationship_type_id_value'])) {
      $originalRelationshipTypes = $this->_params['relationship_relationship_type_id_value'];
      foreach ($this->_params['relationship_relationship_type_id_value'] as $relString) {
        $relType = explode('_', $relString);
        $this->relationType[] = $relType[1] . '_' . $relType[2];
        $relationships[] = (int) $relType[0];
      }
    }
    $this->_params['relationship_relationship_type_id_value'] = $relationships;
    $sql = $this->buildQuery();
    $this->addToDeveloperTab($sql);
    $rows = [];
    $this->buildRows($sql, $rows);
    $this->_params['relationship_type_id_value'] = $originalRelationshipTypes;
    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }

  /**
   * @param $rows
   */
  public function alterDisplay(&$rows): void {
    parent::alterDisplay($rows);
    // custom code to alter rows
    $entryFound = TRUE;

    foreach ($rows as $rowNum => $row) {

      if (array_key_exists('civicrm_case_status_id', $row)) {
        if ($value = $row['civicrm_case_status_id']) {
          $caseStatuses = CRM_Case_PseudoConstant::caseStatus();
          $rows[$rowNum]['civicrm_case_status_id'] = $caseStatuses[$value];
          $entryFound = TRUE;
        }
      }
      if (array_key_exists('civicrm_contact_sort_name_a', $row) && array_key_exists('civicrm_contact_id', $row)) {
        $url = CRM_Report_Utils_Report::getNextUrl('contact/detail', 'reset=1&force=1&id_op=eq&id_value=' . $row['civicrm_contact_id'], $this->_absoluteUrl, $this->_id);
        $rows[$rowNum]['civicrm_contact_sort_name_a_link'] = $url;
        $rows[$rowNum]['civicrm_contact_sort_name_a_hover'] = ts("View Contact details for this contact.");
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_contact_b_sort_name_b', $row) && array_key_exists('civicrm_contact_b_id', $row)) {
        $url = CRM_Report_Utils_Report::getNextUrl('contact/detail', 'reset=1&force=1&id_op=eq&id_value=' . $row['civicrm_contact_b_id'], $this->_absoluteUrl, $this->_id);
        $rows[$rowNum]['civicrm_contact_b_sort_name_b_link'] = $url;
        $rows[$rowNum]['civicrm_contact_b_sort_name_b_hover'] = ts("View Contact details for this contact.");
        $entryFound = TRUE;
      }

      // skip looking further in rows, if first row itself doesn't
      // have the column we need
      if (!$entryFound) {
        break;
      }
    }
  }
}

