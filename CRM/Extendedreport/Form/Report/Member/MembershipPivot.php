<?php

/**
 * Class CRM_Extendedreport_Form_Report_Contact_Extendedcontact
 */
class CRM_Extendedreport_Form_Report_Member_MembershipPivot extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_baseTable = 'civicrm_membership';

  protected $_customGroupAggregates = TRUE;

  protected $_aggregatesIncludeNULL = TRUE;

  protected $_aggregatesAddTotal = TRUE;

  protected $_rollup = 'WITH ROLLUP';

  protected $isPivot = TRUE;

  public $_drilldownReport = ['membership/membershipdetail' => 'Link to Participants'];

  protected $_noFields = TRUE;

  protected $_customGroupExtends = ['Membership', 'Contact', 'Individual', 'Household', 'Organization'];
  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_columns = $this->getColumns('membership', [
          'fields' => FALSE,
          'order_by' => FALSE,
        ]
      ) + $this->getColumns('contact', [
          'fields' => FALSE,
          'order_by' => FALSE,
        ]
      );
    $this->_columns['civicrm_membership']['fields']['id']['required'] = TRUE;
    parent::__construct();
  }

  /**
   * @return array
   */
  public function fromClauses() {
    return [
      'contact_from_membership',
    ];
  }
}
