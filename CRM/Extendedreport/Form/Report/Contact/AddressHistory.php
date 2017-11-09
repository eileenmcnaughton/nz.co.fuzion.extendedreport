<?php

/**
 * Class CRM_Extendedreport_Form_Report_Contact_AddressHistory
 */
class CRM_Extendedreport_Form_Report_Contact_AddressHistory extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_baseTable = 'log_civicrm_address';
  protected $isSupportsContactTab = TRUE;

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
    $this->_columns['log_civicrm_address']['metadata'] += $logMetaData;
    $this->_columns['log_civicrm_address']['fields'] += $logMetaData;
    $this->_columns['log_civicrm_address']['filters']['contact_id'] = $logMetaData['contact_id'];

    parent::__construct();
  }

  public function alterDisplay(&$rows) {
    parent::alterDisplay($rows);
    // Process rows in reverse order.
    $oldestUnprocessedRowIndex = (count($rows) - 1);
    while ($oldestUnprocessedRowIndex > -1) {
      $row = &$rows[$oldestUnprocessedRowIndex];
      $oldestUnprocessedRowIndex--;
    }
    return $rows;
  }

  /**
   * Build order by clause.
   */
  public function orderBy() {
    parent::orderBy();
    $this->_orderBy = "ORDER BY address.log_date DESC";
  }

}
