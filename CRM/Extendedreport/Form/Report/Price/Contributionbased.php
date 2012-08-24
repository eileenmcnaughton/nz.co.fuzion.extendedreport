<?php

require_once 'CRM/Report/Form.php';

class CRM_Extendedreport_Form_Report_Price_Contributionbased extends CRM_Report_Form_Extendedreport {

    protected $_baseTable = 'civicrm_contribution' ;

    function __construct( ) {

        $this->_columns = $this->getContactColumns()
                        + $this->getContributionColumns()
                        + $this->getLineItemColumns()

                        ;
       parent::__construct( );
    }

    function fromClauses( ) {
      return array(
        'lineItem_from_contribution',
        'contact_from_contribution',
      );
    }
}