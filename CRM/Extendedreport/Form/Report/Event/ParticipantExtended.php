<?php

/**
 * Class CRM_Extendedreport_Form_Report_Event_ParticipantExtended
 */
class CRM_Extendedreport_Form_Report_Event_ParticipantExtended extends CRM_Extendedreport_Form_Report_ExtendedReport {
//todo move def to getActivityColumns
  /**
   * @var array
   */
  protected $_customGroupExtended = array(
    'civicrm_activity' => array(
      'extends' => array('Participant'),
      'title'  => 'Participant',
      'filters' => TRUE,
    ),
  );
  /**
   * @var bool
   */
  protected $_addressField = FALSE;
  /**
   * @var bool
   */
  protected $_emailField = FALSE;
  /**
   * @var null
   */
  protected $_summary = NULL;
  /**
   * @var bool
   */
  protected $_exposeContactID = FALSE;
  /**
   * @var bool
   */
  protected $_customGroupGroupBy = FALSE;
  /**
   * @var string
   */
  protected $_baseTable = 'civicrm_participant';

  protected $_customGroupExtends = array('Participant', 'Contact', 'Individual', 'Event');

  public $_drilldownReport = array('event/income' => 'Link to Detail Report');

  function __construct() {
    $this->_autoIncludeIndexedFieldsAsOrderBys = 1;

    // Check if CiviCampaign is a) enabled and b) has active campaigns
    $config = CRM_Core_Config::singleton();
    $campaignEnabled = in_array("CiviCampaign", $config->enableComponents);
    if ($campaignEnabled) {
      $getCampaigns = CRM_Campaign_BAO_Campaign::getPermissionedCampaigns(NULL, NULL, TRUE, FALSE, TRUE);
      $this->activeCampaigns = $getCampaigns['campaigns'];
      asort($this->activeCampaigns);
    }

    $this->_columns = $this->getColumns('Contact')
    + $this->getColumns('Email')
    + $this->getColumns('Address')
    + $this->getColumns('Participant')
    + $this->getColumns('Phone')
    + $this->getColumns('Event');

    // If we have active campaigns add those elements to both the fields and filters
    if ($campaignEnabled && !empty($this->activeCampaigns)) {
      $this->_columns['civicrm_participant']['fields']['campaign_id'] =
        array(
          'title' => ts('Campaign'),
          'default' => 'false',
        );
      $this->_columns['civicrm_participant']['filters']['campaign_id'] =
        array(
          'title' => ts('Campaign'),
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'options' => $this->activeCampaigns,
        );
      $this->_columns['civicrm_participant']['order_bys']['campaign_id'] =
        array('title' => ts('Campaign'));

    }

    $this->_currencyColumn = 'civicrm_participant_fee_currency';
    dpm($this->_columns);
    parent::__construct();
  }

  function getPriceLevels() {
    $query = "
SELECT     DISTINCT cv.label, cv.id
FROM      civicrm_price_field_value cv
LEFT JOIN civicrm_price_field cf ON cv.price_field_id = cf.id
LEFT JOIN civicrm_price_set_entity ce ON ce.price_set_id = cf.price_set_id
WHERE     ce.entity_table = 'civicrm_event'
GROUP BY  cv.label
";
    $dao = CRM_Core_DAO::executeQuery($query);
    $elements = array();
    while ($dao->fetch()) {
      $elements[$dao->id] = "$dao->label\n";
    }

    return $elements;
  } //searches database for priceset values


  function preProcess() {
    parent::preProcess();
  }


  function from() {
    $this->_from = "
        FROM civicrm_participant {$this->_aliases['civicrm_participant']}
             LEFT JOIN civicrm_event {$this->_aliases['civicrm_event']}
                    ON ({$this->_aliases['civicrm_event']}.id = {$this->_aliases['civicrm_participant']}.event_id  AND
                    {$this->_aliases['civicrm_event']}.is_template = 0)
             LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
                    ON ({$this->_aliases['civicrm_participant']}.contact_id  = {$this->_aliases['civicrm_contact']}.id  )
             {$this->_aclFrom}
             LEFT JOIN  civicrm_email {$this->_aliases['civicrm_email']}
                    ON ({$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_email']}.contact_id AND
                       {$this->_aliases['civicrm_email']}.is_primary = 1)
      ";
    + $this->joinPhoneFromContact();
    if ($this->_contribField) {
      $this->_from .= "
             LEFT JOIN civicrm_participant_payment pp
                    ON ({$this->_aliases['civicrm_participant']}.id  = pp.participant_id)
             LEFT JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
                    ON (pp.contribution_id  = {$this->_aliases['civicrm_contribution']}.id)
      ";
    }
    if ($this->_lineitemField){
      $this->_from .= "
            LEFT JOIN civicrm_line_item line_item_civireport
            ON line_item_civireport.entity_table = 'civicrm_participant' AND  line_item_civireport.entity_id = {$this->_aliases['civicrm_participant']}.id
      ";
    }
    $this->addAddressFromClause();
  }

  function groupBy(){
    $this->_groupBy = "GROUP BY {$this->_aliases['civicrm_participant']}.id";
  }
}