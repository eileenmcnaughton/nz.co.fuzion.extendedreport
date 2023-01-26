<?php

/**
 * Class CRM_Extendedreport_Form_Report_Contact_Extendedcontact.
 *
 * This class generates a pivot report - due to _customGroupAggregates being set
 * to true based on the civicrm_contact table.
 */
class CRM_Extendedreport_Form_Report_Contact_Extendedcontact extends CRM_Extendedreport_Form_Report_ExtendedReport {

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
}
