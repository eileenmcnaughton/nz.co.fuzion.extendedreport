<?php

/**
 * Class CRM_Extendedreport_Form_Report_Contact_AddressHistory
 */
class CRM_Extendedreport_Form_Report_Contact_AddressHistory extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_baseTable = 'log_civicrm_address';
  protected $isSupportsContactTab = TRUE;

  /**
   * Contact ID being filtered for.
   *
   * @var int
   */
  protected $contactID;

  /**
   * Contact ID being filtered for.
   *
   * @var int
   */
  protected $activityTypeID;

  /**
   * Contacts merged into tracked contacts.
   *
   * @var array
   */
  protected $mergedContacts = array();

  public function __construct() {
    $this->_columns = $this->getColumns('Address', array(
        'fields' => TRUE,
        'order_by' => FALSE,
        //'fields_defaults' => array('address_name', 'street_address', 'supplemental_address_1', 'supplemental_address_2', 'city', 'state_province_id', 'country_id', 'postal_code', 'county_id'),
        'fields_defaults' => array('display_address'),
      )
    );

    $this->_columns['log_civicrm_address'] = $this->_columns['civicrm_address'];
    unset($this->_columns['civicrm_address']);
    $logMetaData = array(
      'id' => array(
        'name' => 'id',
        'title' => ts('Address ID'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'no_display' => TRUE,
      ),
      'contact_id' => array(
        'name' => 'contact_id',
        'title' => ts('Contact ID'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
      ),
      'log_date' => array(
        'name' => 'log_date',
        'title' => ts('Change Date'),
        'type' => CRM_Utils_Type::T_TIMESTAMP,
        'is_fields' => TRUE,
        'default' => 1,
      ),
      'log_conn_id' => array(
        'name' => 'log_conn_id',
        'title' => ts('Connection'),
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => TRUE,
        'default' => 1,
      ),
      'log_user_id' => array(
        'name' => 'log_user_id',
        'title' => ts('Changed By'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'default' => 1,
      ),
      'log_action' => array(
        'name' => 'log_action',
        'title' => ts('Change action'),
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => TRUE,
        'default' => 1,
      ),
    );

    foreach ($logMetaData as $index => $field) {
      foreach (['filters', 'join_filters', 'group_bys', 'order_bys'] as $type) {
        $logMetaData[$index]['is_' . $type] = FALSE;
      }
      $logMetaData[$index]['dbAlias'] = $this->_columns['log_civicrm_address']['alias'] . '.' . $field['name'];
      $logMetaData[$index]['alias'] = $this->_columns['log_civicrm_address']['alias'] . '_' . $field['name'];
    }
    $this->_columns['log_civicrm_address']['metadata'] += $logMetaData;
    $this->_columns['log_civicrm_address']['fields'] += $logMetaData;
    $this->_columns['log_civicrm_address']['filters']['contact_id'] = $logMetaData['contact_id'];

    $activityTypes = civicrm_api3('Activity', 'getoptions', array('field' => 'activity_type_id'));
    $this->activityTypeID = array_search('Contact Deleted by Merge', $activityTypes['values']);
    parent::__construct();

  }

  /**
  public function alterDisplay(&$rows) {
    parent::alterDisplay($rows);
    // Process rows in reverse order.
    $oldestUnprocessedRowIndex = (count($rows) - 1);
    while ($oldestUnprocessedRowIndex > -1) {
      $row = &$rows[$oldestUnprocessedRowIndex];
      $oldestUnprocessedRowIndex--;
    }
    return $rows;
  }*/

  /**
   * Build order by clause.
   */
  public function orderBy() {
    parent::orderBy();
    $this->_orderBy = "ORDER BY address.log_date DESC";
  }

  /**
   * Generate where clause.
   *
   * This can be overridden in reports for special treatment of a field
   *
   * @param array $field Field specifications
   * @param string $op Query operator (not an exact match to sql)
   * @param mixed $value
   * @param float $min
   * @param float $max
   *
   * @return null|string
   */
  public function whereClause(&$field, $op, $value, $min, $max) {
     if ($field['name'] === 'contact_id' && $value) {
       $this->contactID = (int) $value;
       $mergedContactIDs = $this->getContactsMergedIntoThisOne($this->contactID);
       $clause = parent::whereClause($field, 'in', array_merge(array($this->contactID), $mergedContactIDs), $min, $max);
       return $clause;
     }
  }

  /**
   * @param int $contactID
   * @return int
   */
  protected function getContactsMergedIntoThisOne($contactID) {
    // @todo get api joins working properly.
    $result = civicrm_api3('Activity', 'get', array(
      'assignee_contact_id' => $contactID,
      'return' => 'id',
      'activity_type_id' => $this->activityTypeID,
      'api.ActivityContact.get' => array('record_type_id' => 'Activity Targets', 'return' => 'contact_id')
    ));
    if ($result['count']) {
      foreach ($result['values'] as $resultRow) {
        if (!empty($resultRow['api.ActivityContact.get'])) {
          foreach ($resultRow['api.ActivityContact.get']['values'] as $deletedContact) {
            if (!in_array($deletedContact['contact_id'], $this->mergedContacts)) {
              $this->mergedContacts[] = $deletedContact['contact_id'];
              $this->getContactsMergedIntoThisOne($deletedContact['contact_id']);
            }
          }
        }
      }
    }
    return $this->mergedContacts;
  }

}
