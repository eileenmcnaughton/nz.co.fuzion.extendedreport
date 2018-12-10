<?php

/**
 * Class CRM_Extendedreport_Form_Report_Event_EventPivot
 */
class CRM_Extendedreport_Form_Report_Event_EventPivot extends CRM_Extendedreport_Form_Report_ExtendedReport {
  protected $_baseTable = 'civicrm_participant';
  protected $skipACL = TRUE;
  protected $_customGroupAggregates = TRUE;
  protected $_aggregatesIncludeNULL = TRUE;
  protected $_aggregatesAddTotal = TRUE;
  protected $isPivot = TRUE;
  protected $_rollup = 'WITH ROLLUP';
  public $_drilldownReport = array('event/participantlist' => 'Link to Participants');
  protected $_participantTable = 'civicrm_participant';
  protected $_noFields = TRUE;
  protected $_potentialCriteria = array(
    'rid',
    'sid',
  );

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_customGroupExtended['civicrm_event'] = array(
      'extends' => array('Event'),
      'filters' => TRUE,
      'title' => ts('Event'),
    );
    $this->_customGroupExtended['civicrm_participant'] = array(
      'extends' => array('Participant'),
      'filters' => TRUE,
      'title' => ts('Participant'),
    );

    $this->_columns = $this->getColumns('Event', array(
          'fields' => FALSE,
        )
      )
      + $this->getColumns('Participant', array('fields' => FALSE,))
      + $this->getColumns('Contact', array('fields' => FALSE,));
    $this->_columns['civicrm_event']['fields']['id']['required'] = TRUE;
    $this->_columns['civicrm_event']['fields']['id']['alter_display'] = 'alterEventID';
    $this->_columns['civicrm_event']['fields']['id']['title'] = 'Event';

    $this->_aggregateRowFields = array(
      'event:id' => 'Event',
    );

    $this->_aggregateColumnHeaderFields = array(
      'participant:status_id' => ts('Participant Status'),
    );

    $this->_groupFilter = TRUE;
    parent::__construct();
    $this->_options = array(
      'delete_null' => array(
        'title' => ts('Hide columns with zero count'),
        'type' => 'checkbox',
      ),
    );
  }  
  
  /**
   * Declare from clauses used in the from clause for this report.
   *
   * @return array
   */
  public function fromClauses() {
    return array(
      'event_from_participant',
      'contact_from_participant',
    );
  }
    
  function alterDisplay(&$rows) {
    parent::alterDisplay($rows);

    if (isset ($_POST['delete_null'])) {
      $hiddenColumnsLabels = array();
      $columnFields = $this->getFieldBreakdownForAggregates('column');
      $selectedTables = array();
      $columnColumns = $this->extractCustomFields($columnFields, $selectedTables, 'column_header');
      
      if (empty($columnColumns)) {
        $participantStatuses = CRM_Event_PseudoConstant::participantStatus(NULL, NULL, 'label');
        foreach ($participantStatuses as $key => $opt) {
          $hiddenColumnsLabels[] = 'status_id_'.$key;
        }
      } else {
        $customfieldId = intval(preg_replace('/[^0-9]+/', '', key($columnColumns)), 10);
        $CustomField = civicrm_api3('CustomField', 'get', [
          'sequential' => 1,
          'id' => $customfieldId,
          'options' => ['limit' => 0],
        ]);
        $title = $CustomField['values'][0]['column_name'];
        $group = $CustomField['values'][0]['option_group_id'];
        $OptionValue = civicrm_api3('OptionValue', 'get', [
          'sequential' => 1,
          'option_group_id' => "$group",
          'options' => ['limit' => 0],
        ]);
        foreach ($OptionValue['values'] as $key => $opt) {
          $key++;
          $hiddenColumnsLabels[] = $title.'_'.$key;
        }
      }
      
      $colVoucher = array();
      foreach ($hiddenColumnsLabels as $v) {
        $colVoucher[$v] = 0; 
      }
      
      foreach ($rows as $rowNum => $row) {
        foreach ($row as $colNum => $col){
          if (in_array ($colNum,$hiddenColumnsLabels)) {
            if ($col != 0 ) {
              $colVoucher[$colNum]++;
            }
          }
        }
      }
      
      foreach ($colVoucher as $collabel => $colcontent) {
        if ($colVoucher[$collabel] == 0){              
          unset ($this->_columnHeaders[$collabel]);
        }
      }        
    }
  }
}
