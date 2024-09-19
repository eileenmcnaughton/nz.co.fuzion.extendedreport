<?php

/**
 * Class CRM_Extendedreport_Form_Report_Contact_Basiccontact
 */
class CRM_Extendedreport_Form_Report_Contact_Basiccontact extends CRM_Extendedreport_Form_Report_ExtendedReport {

  protected $_joinFilters = ['address_from_contact' => ['civicrm_address' => 'is_primary = 1 ']];

  protected $_customGroupExtends = ['Contact', 'Individual', 'Household', 'Organization'];

  /**
   * Class constructor.
   *
   * @throws \CRM_Core_Exception
   */
  public function __construct() {
    $this->_templates = [
      'default' => 'default template',
      'PhoneBank' => 'Phone Bank template - Phone.tpl',
    ];
    $this->_columns = $this->getColumns('Contact', [
          'fields' => TRUE,
          'order_by' => FALSE,
        ]
      ) +
      $this->getColumns('Address', [
          'fields' => TRUE,
          'order_by' => FALSE,
        ]
      ) +
      $this->getColumns('Email', [
          'fields' => TRUE,
          'order_by' => FALSE,
        ]
      ) +
      $this->getColumns('LatestActivity', [
        'filters' => FALSE,
        'fields' => ['activity_type' => ['title' => 'Latest Activity']],
      ]) +
      $this->getColumns('Tag') +
      $this->getColumns('Phone');
    $this->_columns['civicrm_contact']['fields']['id']['required'] = TRUE;
    $this->addTemplateSelector();
    $this->_groupFilter = TRUE;
    parent::__construct();
  }

  /**
   * @return array
   */
  public function fromClauses(): array {
    return [
      'address_from_contact',
      'email_from_contact',
      'phone_from_contact',
      'latestactivity_from_contact',
      'entitytag_from_contact',
    ];
  }

  public function groupBy(): void {
    $this->_groupBy = "GROUP BY {$this->_aliases['civicrm_contact']}.id";
  }
}
