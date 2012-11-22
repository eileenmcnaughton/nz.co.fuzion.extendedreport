<?php



class CRM_Extendedreport_Form_Report_Price_Contributionbased extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_contribution';
  function __construct() {
    $this->_columns = $this->getContactColumns()
    + $this->getContributionColumns()
    + $this->getPriceFieldColumns()
    + $this->getPriceFieldValueColumns()
    + $this->getLineItemColumns();


    parent::__construct();
  }
  function fromClauses() {
    return array(
        'lineItem_from_contribution',
        'contact_from_contribution',
        'priceFieldValue_from_lineItem',
        'priceField_from_lineItem'
    );
  }
}
