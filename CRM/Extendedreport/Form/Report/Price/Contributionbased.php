<?php


/**
 * Class CRM_Extendedreport_Form_Report_Price_Contributionbased
 */
class CRM_Extendedreport_Form_Report_Price_Contributionbased extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_contribution';

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_columns
      = $this->getColumns('Contact')
      + $this->getColumns('Contribution')
      + $this->getColumns('PriceField')
      + $this->getColumns('PriceFieldValue')
      + $this->getColumns('LineItem')
      + $this->getColumns('Address');
    parent::__construct();
  }

  /**
   * @return array
   */
  function fromClauses() {
    return array(
      'lineItem_from_contribution',
      'contact_from_contribution',
      'priceFieldValue_from_lineItem',
      'priceField_from_lineItem',
      'address_from_contact',
    );
  }
}
