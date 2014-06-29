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
 * $Id$
 *
 */
class CRM_Extendedreport_Form_Report_Price_Lineitem extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_addressField = FALSE;

  protected $_emailField = FALSE;

  protected $_summary = NULL;

  protected $_customGroupExtends = array('Contribution');

  protected $_baseTable = 'civicrm_line_item';

  protected $_aclTable = 'civicrm_contact';

  /**
   * @param int $child
   */
  function __construct($child = 0) {
    if (empty($child)) {
      // hack because we are currently using this as base for other report
      // plan is to move functions into Form.php instead & won't be required
      $this->_columns = $this->getColumns('Contact')
        + $this->getColumns('Event')
        + $this->getColumns('Participant')
        + $this->getColumns('Contribution', array('group_by' => TRUE))
        + $this->getColumns('PriceField')
        + $this->getColumns('PriceFieldValue')
        + $this->getColumns('LineItem');
    }
    parent::__construct();
  }

  function preProcess() {
    parent::preProcess();
  }

  function select() {
    parent::select();
  }
  /*
   * select from clauses to use (from those advertised using
   * $this->getAvailableJoins())
   */
  /**
   * @return array
   */
  function fromClauses() {
    return array(
      'priceFieldValue_from_lineItem',
      'priceField_from_lineItem',
      'participant_from_lineItem',
      'contribution_from_lineItem',
      'contact_from_contribution',
      'event_from_participant'
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
   * @param $rows
   */
  function alterDisplay(&$rows) {
    parent::alterDisplay($rows);

  }
}
