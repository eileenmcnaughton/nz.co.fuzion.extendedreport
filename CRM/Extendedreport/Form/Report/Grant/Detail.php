<?php

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2010
 *            $Id$
 *
 */

class CRM_Extendedreport_Form_Report_Grant_Detail extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_customGroupExtends = [
    'Contact',
    'Individual',
    'Household',
    'Organization',
    'Grant',
  ];

  protected $_baseTable = 'civicrm_grant';

  protected $_customGroupGroupBy = TRUE;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_columns = $this->getColumns('Contact') + $this->getColumns('Email') + $this->getColumns('Phone') + $this->getColumns('Grant');

    parent::__construct();
    CRM_Core_DAO::disableFullGroupByMode();
  }

  function fromClauses() {
    return [
      'contact_from_grant',
      'email_from_contact',
      'phone_from_contact',
    ];
  }

}
