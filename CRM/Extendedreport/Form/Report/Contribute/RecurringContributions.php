<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2015                                |
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
class CRM_Extendedreport_Form_Report_Contribute_RecurringContributions extends CRM_Extendedreport_Form_Report_ExtendedReport {

  /**
   * Add group bys for custom fields.
   *
   * @var bool
   */
  protected $_customGroupGroupBy = TRUE;

  /**
   * @var array
   */
  protected $_customGroupExtends = [
    'ContributionRecur',
    'Contact',
    'Individual',
    'Organization',
    'Household',
  ];

  protected $_baseTable = 'civicrm_contribution_recur';

  /**
   * Class constructor.
   *
   * @throws \CRM_Core_Exception
   */
  public function __construct() {
    $this->_columns = $this->getColumns('Contact', ['group_by' => TRUE])
      + $this->getColumns('ContributionRecur', ['group_by' => TRUE])
      + $this->getColumns('Email', ['group_by' => TRUE])
      + $this->getColumns('Address', ['group_by' => TRUE])
      + $this->getColumns('Phone', ['group_by' => TRUE])
      + $this->getColumns('Website', [
        'group_by' => TRUE,
        'join_filters' => TRUE,
      ]);
    parent::__construct();
  }

  /**
   * @return array
   */
  public function fromClauses(): array {
    return [
      'contact_from_contribution_recur',
      'email_from_contact',
      'primary_phone_from_contact',
      'address_from_contact',
      'website_from_contact',
    ];
  }

}
