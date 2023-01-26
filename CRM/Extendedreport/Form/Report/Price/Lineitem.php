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
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */
class CRM_Extendedreport_Form_Report_Price_Lineitem extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_customGroupExtends = ['Contribution'];

  protected $_baseTable = 'civicrm_line_item';

  protected $_aclTable = 'civicrm_contact';

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_columns
      = $this->getColumns('Contact', ['order_by' => TRUE])
      + $this->getColumns('Email', [
          'fields' => TRUE,
          'order_by' => FALSE,
        ]
      )
      + $this->getColumns('Phone', [
        'fields' => TRUE,
        'order_by' => FALSE,
      ])
      + $this->getColumns('Event')
      + $this->getColumns('Participant')
      + $this->getColumns('Contribution', ['order_by' => TRUE])
      + $this->getColumns('Batch', ['order_by' => TRUE])
      + $this->getColumns('PriceField', ['order_by' => TRUE])
      + $this->getColumns('PriceFieldValue', ['order_by' => TRUE])
      + $this->getColumns('LineItem', ['order_by' => TRUE, 'fields_defaults' => ['financial_type_id', 'line_total']]) +
      $this->getColumns('BillingAddress') +
      $this->getColumns('Address');
    parent::__construct();
  }

  /**
   * Select from clauses to use.
   *
   * (from those advertised using $this->getAvailableJoins()).
   *
   * @return array
   */
  public function fromClauses(): array {
    return [
      'priceFieldValue_from_lineItem',
      'priceField_from_lineItem',
      'participant_from_lineItem',
      'contribution_from_lineItem',
      'contact_from_contribution',
      'batch_from_contribution',
      'event_from_participant',
      'address_from_contact',
      'address_from_contribution',
      'email_from_contact',
      'primary_phone_from_contact',
    ];

  }

}
