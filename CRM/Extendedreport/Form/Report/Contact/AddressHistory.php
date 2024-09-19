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
  protected $mergedContacts = [];

  /**
   * CRM_Extendedreport_Form_Report_Contact_AddressHistory constructor.
   *
   * @throws \CRM_Core_Exception
   */
  public function __construct() {
    $addressColumns = $this->getColumns('Address', [
        'fields' => TRUE,
        'order_bys' => FALSE,
        //'fields_defaults' => array('address_name', 'street_address', 'supplemental_address_1', 'supplemental_address_2', 'city', 'state_province_id', 'country_id', 'postal_code', 'county_id'),
        'fields_defaults' => ['display_address'],
        // More work to be done to figure out how retrieving custom data for this report would look
        'is_extendable' => FALSE,
      ]
    )['civicrm_address']['metadata'];

    $logMetaData = array_merge($addressColumns, [
      'id' => [
        'name' => 'id',
        'title' => ts('Address ID'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'no_display' => TRUE,
      ],
      'contact_id' => [
        'name' => 'contact_id',
        'title' => ts('Contact ID'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'is_filters' => TRUE,
        'is_contact_filter' => TRUE,
      ],
      'log_date' => [
        'name' => 'log_date',
        'title' => ts('Change Date'),
        'type' => CRM_Utils_Type::T_TIMESTAMP,
        'is_fields' => TRUE,
      ],
      'log_conn_id' => [
        'name' => 'log_conn_id',
        'title' => ts('Connection'),
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => TRUE,
      ],
      'log_user_id' => [
        'name' => 'log_user_id',
        'title' => ts('Changed By'),
        'type' => CRM_Utils_Type::T_INT,
        'is_fields' => TRUE,
        'is_contact_filter' => TRUE,
      ],
      'log_action' => [
        'name' => 'log_action',
        'title' => ts('Change action'),
        'type' => CRM_Utils_Type::T_STRING,
        'is_fields' => TRUE,
      ],
    ]);

    $this->_columns = $this->buildColumns($logMetaData, 'log_civicrm_address', NULL, 'address',
      ['fields_defaults' => ['address_display', 'log_action', 'log_user_id', 'log_conn_id', 'log_date']],
      ['order_by' => FALSE, 'no_field_disambiguation' => TRUE]
    );

    $activityTypes = civicrm_api3('Activity', 'getoptions', ['field' => 'activity_type_id']);
    $this->activityTypeID = array_search('Contact Deleted by Merge', $activityTypes['values']);
    parent::__construct();

  }

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
   * @throws \CRM_Core_Exception
   */
  public function whereClause(&$field, $op, $value, $min, $max): ?string {
    if ($field['name'] === 'contact_id' && $value) {
      $this->contactID = (int) $value;
      $mergedContactIDs = $this->getContactsMergedIntoThisOne($this->contactID);
      return parent::whereClause($field, 'in', array_merge([$this->contactID], $mergedContactIDs), $min, $max);
    }
    return '';
  }

  /**
   * Get all the contacts previously merged into the selected contact.
   *
   * @param int $contactID
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function getContactsMergedIntoThisOne(int $contactID): array {
    // @todo get api joins working properly.
    $result = civicrm_api3('Activity', 'get', [
      'assignee_contact_id' => $contactID,
      'return' => 'id',
      'activity_type_id' => $this->activityTypeID,
      'api.ActivityContact.get' => [
        'record_type_id' => 'Activity Targets',
        'return' => 'contact_id',
      ],
    ]);
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
