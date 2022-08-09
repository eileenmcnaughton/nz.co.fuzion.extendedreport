<?php

/**
 * Class CRM_Extendedreport_Form_Report_Price_Lineitemmembership
 */
class CRM_Extendedreport_Form_Report_Price_Lineitemmembership extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_customGroupExtends = ['Membership', 'Individual', 'Contact'];

  protected $_baseTable = 'civicrm_line_item';

  protected $_aclTable = 'civicrm_contact';

  protected $isSupportsContactTab = TRUE;

  protected $joinFiltersTab = TRUE;

  /**
   * Support contact tabs by specifying which filter to map the contact id field to.
   *
   * @var string
   */
  protected $contactIDField = 'membership_contact_id';

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_columns = $this->getColumns('Contact') +
      $this->getColumns('Membership') +
      $this->getColumns('Contribution') +
      $this->getColumns('Batch', ['order_by' => TRUE]) +
      $this->getColumns('PriceField') +
      $this->getColumns('PriceFieldValue') +
      $this->getColumns('LineItem') +
      $this->getColumns('Address', ['join_filters' => TRUE]);

    parent::__construct();
  }

  /**
   * Select from clauses to use.
   *
   * (from those advertised using $this->getAvailableJoins())
   *
   * @return array
   */
  public function fromClauses():array {
    return [
      'priceFieldValue_from_lineItem',
      'priceField_from_lineItem',
      'membership_from_lineItem',
      'contact_from_membership',
      'address_from_contact',
      'contribution_from_lineItem',
      'batch_from_contribution',
    ];
  }

}
