<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.0                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2011                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2011
 */
class CRM_Extendedreport_Form_Report_Price_Lineitem extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_customGroupExtends = array('Contribution');

  protected $_baseTable = 'civicrm_line_item';

  protected $_aclTable = 'civicrm_contact';

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_columns
      = $this->getColumns('Contact', array('order_by' => TRUE))
      + $this->getColumns('Email', [
          'fields' => TRUE,
          'order_by' => FALSE,
        ]
      )
      + $this->getColumns('Event')
      + $this->getColumns('Participant')
      + $this->getColumns('Contribution', array('order_by' => TRUE))
      + $this->getColumns('PriceField', array('order_by' => TRUE))
      + $this->getColumns('PriceFieldValue' , array('order_by' => TRUE))
      + $this->getColumns('LineItem', array('order_by' => TRUE, 'fields_defaults' => array('financial_type_id', 'line_total'))) +
      $this->getColumns('BillingAddress') +
      $this->getColumns('Address');
    parent::__construct();
  }

  function preProcess() {
    parent::preProcess();
  }

  function select() {
    parent::select();
  }

  /**
   * Select from clauses to use.
   *
   * (from those advertised using $this->getAvailableJoins()).
   *
   * @return array
   */
  public function fromClauses() {
    return array(
      'priceFieldValue_from_lineItem',
      'priceField_from_lineItem',
      'participant_from_lineItem',
      'contribution_from_lineItem',
      'contact_from_contribution',
      'event_from_participant',
      'address_from_contact',
      'address_from_contribution',
	    'email_from_contact',
    );

  }

  function groupBy() {
    parent::groupBy();

  }

  function orderBy() {
    parent::orderBy();
  }

  /**
   * @param $rows
   *
   * @return mixed
   */
  function statistics(&$rows) {
    return parent::statistics($rows);
  }

  function postProcess() {
    parent::postProcess();
  }

  /**
   * Alter rows display.
   *
   * @param $rows
   */
  public function alterDisplay(&$rows) {
    parent::alterDisplay($rows);

  }
}
