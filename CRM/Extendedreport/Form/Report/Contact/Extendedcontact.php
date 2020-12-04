<?php

/**
 * Class CRM_Extendedreport_Form_Report_Contact_Extendedcontact.
 *
 * This class generates a pivot report - due to _customGroupAggregates being set
 * to true based on the civicrm_contact table.
 */
class CRM_Extendedreport_Form_Report_Contact_Extendedcontact extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_baseTable = 'civicrm_contact';

  protected $skipACL = TRUE;

  protected $_customGroupAggregates = TRUE;

  protected $isPivot = TRUE;

  protected $_noFields = TRUE;

  protected $_customGroupExtends = ['Contact', 'Individual', 'Household', 'Organization'];

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_columns = $this->getColumns('Contact', [
        'fields' => FALSE,
        'order_by' => FALSE,
      ]
    );
    $this->_columns['civicrm_contact']['fields']['id']['required'] = TRUE;
    parent::__construct();
  }

  /**
   * Remove is_primary from contact table to phone join.
   *
   * @param string $prefix
   * @param array $extra
   */
  protected function joinPhoneFromContact($prefix = '', $extra = []) {
    if ($this->isTableSelected($prefix . 'civicrm_phone')) {
      $this->_from .= " LEFT JOIN civicrm_phone {$this->_aliases[$prefix . 'civicrm_phone']}
      ON {$this->_aliases[$prefix . 'civicrm_phone']}.contact_id = {$this->_aliases[$prefix . 'civicrm_contact']}.id";
    }
  }

}
