<?php

/**
 * Class CRM_Extendedreport_Form_Report_RelationshipExtended
 */
class CRM_Extendedreport_Form_Report_RelationshipExtended extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_summary = NULL;
  protected $_emailField_a = FALSE;
  protected $_emailField_b = FALSE;
  protected $_baseTable = 'civicrm_relationship';
  protected $_primaryContactPrefix = 'contact_a_';

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_tagFilter = TRUE;
    $this->_customGroupExtended['civicrm_relationship'] = array(
      'extends' => array('Relationship'),
      'title' => ts('Relationship'),
      'filters' => TRUE,
    );

    $this->_columns = $this->getColumns('Contact', array(
          'prefix' => 'contact_a_',
          'prefix_label' => 'Contact A ::',
        )
      ) + $this->getColumns('Address', array(
          'prefix' => 'contact_a_',
          'prefix_label' => 'Contact A ::',
        )
      ) + $this->getColumns('Email', array(
          'prefix' => 'contact_a_',
          'prefix_label' => 'Contact A ::',
        )
      ) + $this->getColumns('Phone', array(
          'prefix' => 'contact_a_',
          'prefix_label' => 'Contact A ::',
          'subquery' => FALSE,
        )
      ) + $this->getColumns('Contact', array(
        'prefix' => 'contact_b_',
        'prefix_label' => 'Contact B ::',
      )) + $this->getColumns('Address', array(
          'prefix' => 'contact_b_',
          'prefix_label' => 'Contact B ::',
        )
      ) + $this->getColumns('Email', array(
        'prefix' => 'contact_b_',
        'prefix_label' => 'Contact B ::',
      ))

      + $this->getColumns('Phone', array(
        'prefix' => 'contact_b_',
        'prefix_label' => 'Contact B ::',
        'subquery' => FALSE,
      ))
      + $this->getColumns('Relationship') + array(
        'civicrm_relationship_type' => array(
          'dao' => 'CRM_Contact_DAO_RelationshipType',
          'fields' => array(
            'label_a_b' => array(
              'title' => ts('Relationship A-B '),
              'default' => TRUE,
            ),
            'label_b_a' => array(
              'title' => ts('Relationship B-A '),
              'default' => TRUE
            )
          ),
          'filters' => array(
            'contact_type_a' => array(
              'title' => ts('Contact Type  A'),
              'operatorType' => CRM_Report_Form::OP_MULTISELECT,
              'options' => CRM_Contact_BAO_Contact::buildOptions('contact_type'),
              'type' => CRM_Utils_Type::T_STRING
            ),
            'contact_type_b' => array(
              'title' => ts('Contact Type  B'),
              'operatorType' => CRM_Report_Form::OP_MULTISELECT,
              'options' => CRM_Contact_BAO_Contact::buildOptions('contact_type'),
              'type' => CRM_Utils_Type::T_STRING
            ),
          ),
          'grouping' => 'relation-fields',
        ),
        'civicrm_group' => array(
          'dao' => 'CRM_Contact_DAO_Group',
          'alias' => 'cgroup',
          'filters' => array(
            'gid' => array(
              'name' => 'group_id',
              'title' => ts('Group'),
              'operatorType' => CRM_Report_Form::OP_MULTISELECT,
              'group' => TRUE,
              'type' => CRM_Utils_Type::T_INT,
              'options' => CRM_Core_PseudoConstant::group()
            ),
          ),
        ),
      )
      + $this->getColumns('Case');
    parent::__construct();
  }

  function from() {
    $this->buildACLClause($this->_aliases['contact_a_civicrm_contact']);
    $this->setFromBase('civicrm_contact', 'id', $this->_aliases['contact_a_civicrm_contact']);
    $this->_from .= "
      INNER JOIN civicrm_relationship {$this->_aliases['civicrm_relationship']}
          ON ( {$this->_aliases['civicrm_relationship']}.contact_id_a =
          {$this->_aliases['contact_a_civicrm_contact']}.id )

          INNER JOIN civicrm_contact {$this->_aliases['contact_b_civicrm_contact']}
          ON ( {$this->_aliases['civicrm_relationship']}.contact_id_b =
          {$this->_aliases['contact_b_civicrm_contact']}.id )

          {$this->_aclFrom}
          LEFT JOIN civicrm_relationship rc ON ({$this->_aliases['contact_b_civicrm_contact']}.id = rc.contact_id_a AND rc.relationship_type_id = 15)
          LEFT JOIN civicrm_relationship rccoordinator ON ({$this->_aliases['contact_b_civicrm_contact']}.id = rccoordinator.contact_id_a AND rccoordinator.relationship_type_id = 8)
          LEFT JOIN civicrm_case case_civireport ON rccoordinator .case_id = case_civireport.id";

    $this->_from .= "
          INNER JOIN civicrm_relationship_type {$this->_aliases['civicrm_relationship_type']}
          ON ( {$this->_aliases['civicrm_relationship']}.relationship_type_id  =
          {$this->_aliases['civicrm_relationship_type']}.id  ) ";

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

    $this->selectableCustomDataFrom();
  }

  /**
   * @param $rows
   *
   * @return mixed
   */
  function statistics(&$rows) {
    $statistics = parent::statistics($rows);

    $isStatusFilter = FALSE;
    $relStatus = NULL;
    if (CRM_Utils_Array::value('is_active_value', $this->_params) == '1') {
      $relStatus = 'Is equal to Active';
    }
    elseif (CRM_Utils_Array::value('is_active_value', $this->_params) == '0') {
      $relStatus = 'Is equal to Inactive';
    }
    if (CRM_Utils_Array::value('filters', $statistics)) {
      foreach ($statistics['filters'] as $id => $value) {
        //for displaying relationship type filter
        if ($value['title'] == 'Relationship') {
          $relTypes = CRM_Core_PseudoConstant::relationshipType();
          $statistics['filters'][$id]['value'] = 'Is equal to ' . $relTypes[$this->_params['relationship_type_id_value']]['label_' . $this->relationType];
        }

        //for displaying relationship status
        if ($value['title'] == 'Relationship Status') {
          $isStatusFilter = TRUE;
          $statistics['filters'][$id]['value'] = $relStatus;
        }
      }
    }
    //for displaying relationship status
    if (!$isStatusFilter && $relStatus) {
      $statistics['filters'][] = array(
        'title' => 'Relationship Status',
        'value' => $relStatus
      );
    }
    return $statistics;
  }

  function postProcess() {
    $this->beginPostProcess();
    $this->relationType = NULL;
    $originalRelationshipTypes = array();

    $relationships = array();
    if (CRM_Utils_Array::value('relationship_relationship_type_id_value', $this->_params) && is_array($this->_params['relationship_relationship_type_id_value'])) {
      $originalRelationshipTypes = $this->_params['relationship_relationship_type_id_value'];
      foreach ($this->_params['relationship_relationship_type_id_value'] as $relString) {
        $relType = explode('_', $relString);
        $this->relationType[] = $relType[1] . '_' . $relType[2];
        $relationships[] = intval($relType[0]);
      }
    }
    $this->_params['relationship_relationship_type_id_value'] = $relationships;
    $this->buildACLClause(array(
      $this->_aliases['contact_a_civicrm_contact'],
      $this->_aliases['contact_b_civicrm_contact']
    ));
    $sql = $this->buildQuery();
    $rows = array();
    $this->buildRows($sql, $rows);
    $this->_params['relationship_type_id_value'] = $originalRelationshipTypes;
    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }

  /**
   * @param $rows
   */
  function alterDisplay(&$rows) {
    parent::alterDisplay($rows);
    // custom code to alter rows
    $entryFound = TRUE;

    foreach ($rows as $rowNum => $row) {

      if (array_key_exists('civicrm_case_status_id', $row)) {
        if ($value = $row['civicrm_case_status_id']) {
          $this->case_statuses = CRM_Case_PseudoConstant::caseStatus();
          $rows[$rowNum]['civicrm_case_status_id'] = $this->case_statuses[$value];
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

