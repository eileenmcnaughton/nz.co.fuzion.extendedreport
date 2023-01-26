<?php


/**
 * Class CRM_Extendedreport_Form_Report_Price_Contributionbased
 */
class CRM_Extendedreport_Form_Report_Price_Contributionbased extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_baseTable = 'civicrm_contribution';

  /**
   * Class constructor.
   *
   * @throws \CRM_Core_Exception
   */
  public function __construct() {
    $this->_columns
      = $this->getColumns('Contact')
      + $this->getColumns('Contribution', ['filters_defaults' => ['is_test' => 0]])
      + $this->getColumns('Batch', ['order_by' => TRUE])
      + $this->getColumns('PriceField')
      + $this->getColumns('PriceFieldValue')
      + $this->getColumns('LineItem')
      + $this->getColumns('Address');
    parent::__construct();
  }

  /**
   * @return array
   */
  public function fromClauses(): array {
    return [
      'lineItem_from_contribution',
      'batch_from_contribution',
      'contact_from_contribution',
      'priceFieldValue_from_lineItem',
      'priceField_from_lineItem',
      'address_from_contact',
    ];
  }
}
