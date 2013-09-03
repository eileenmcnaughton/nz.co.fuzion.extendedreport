<?php

class CRM_Extendedreport_Form_Report_RelationshipExtended extends CRM_Extendedreport_Form_Report_ExtendedReport {

    protected $_summary       = null;
    protected $_emailField_a  = false;
    protected $_emailField_b  = false;
    protected $_baseTable = 'civicrm_relationship';

    function __construct( ) {
      $this->_tagFilter = True;

      $this->_columns =
        $this->getContactColumns(array('prefix' => 'contact_a_', 'prefix_label' => 'Contact A ::'))
      + $this->getContactColumns(array('prefix' => 'contact_b_', 'prefix_label' => 'Contact B ::'))
      + $this->getEmailColumns(array('prefix' => 'contact_a_', 'prefix_label' => 'Contact A ::'))
      + $this->getEmailColumns(array('prefix' => 'contact_b_', 'prefix_label' => 'Contact B ::'))
      + $this->getRelationshipColumns()
      + array(
        'civicrm_relationship_type' =>
          array( 'dao'       => 'CRM_Contact_DAO_RelationshipType',
              'fields'    =>
              array( 'label_a_b' =>
                  array( 'title'   => ts( 'Relationship A-B ' ),
                      'default' => true,),

                  'label_b_a' =>
                  array( 'title' => ts( 'Relationship B-A ' ),
                      'default' => true, ),
              ),
              'filters'   =>
              array( 'contact_type_a' =>
                  array( 'title'        => ts( 'Contact Type  A' ),
                      'operatorType' => CRM_Report_Form::OP_MULTISELECT,
                      'options'      => $this->getContactTypeOptions(),
                      'type'         => CRM_Utils_Type::T_STRING,
                  ),
                  'contact_type_b' =>
                  array( 'title'        => ts( 'Contact Type  B' ),
                      'operatorType' => CRM_Report_Form::OP_MULTISELECT,
                      'options'      => $this->getContactTypeOptions(),
                      'type'         => CRM_Utils_Type::T_STRING,
                  ), ),
              'grouping'  => 'relation-fields',
          ),

          'civicrm_group' =>
          array( 'dao'    => 'CRM_Contact_DAO_Group',
              'alias'  => 'cgroup',
              'filters'=>
              array( 'gid' =>
                  array( 'name'         => 'group_id',
                      'title'        => ts( 'Group' ),
                      'operatorType' => CRM_Report_Form::OP_MULTISELECT,
                      'group'        => true,
                      'options'      => CRM_Core_PseudoConstant::group( )
                  ),
              ),
          ),
      ) + $this->getCaseColumns()
      + $this->getAddressColumns();
      parent::__construct( );
    }


    function from( ) {
      $this->buildACLClause($this->_aliases['contact_a_civicrm_contact']);

      $this->_from = "
      FROM civicrm_relationship {$this->_aliases['civicrm_relationship']}

      INNER JOIN civicrm_contact {$this->_aliases['contact_a_civicrm_contact']}
          ON ( {$this->_aliases['civicrm_relationship']}.contact_id_a =
          {$this->_aliases['contact_a_civicrm_contact']}.id )

          INNER JOIN civicrm_contact {$this->_aliases['contact_b_civicrm_contact']}
          ON ( {$this->_aliases['civicrm_relationship']}.contact_id_b =
          {$this->_aliases['contact_b_civicrm_contact']}.id )

          {$this->_aclFrom}
          LEFT JOIN civicrm_relationship rc ON ({$this->_aliases['contact_b_civicrm_contact']}.id = rc.contact_id_a AND rc.relationship_type_id = 15)
          LEFT JOIN civicrm_relationship rccoordinator ON ({$this->_aliases['contact_b_civicrm_contact']}.id = rccoordinator.contact_id_a AND rccoordinator.relationship_type_id = 8)
          LEFT JOIN civicrm_case case_civireport ON rccoordinator .case_id = case_civireport.id";


          $this->_from .= "
          LEFT JOIN civicrm_address {$this->_aliases['civicrm_address']}
          ON (( {$this->_aliases['civicrm_address']}.contact_id =
          {$this->_aliases['contact_a_civicrm_contact']}.id) AND
          {$this->_aliases['civicrm_address']}.is_primary = 1 ) ";


          $this->_from .= "
          INNER JOIN civicrm_relationship_type {$this->_aliases['civicrm_relationship_type']}
          ON ( {$this->_aliases['civicrm_relationship']}.relationship_type_id  =
          {$this->_aliases['civicrm_relationship_type']}.id  ) ";

          // include Email Field
        if ( $this->_emailField_a ) {
          $this->_from .= "
          LEFT JOIN civicrm_email {$this->_aliases['civicrm_email']}
          ON ( {$this->_aliases['contact_a_civicrm_contact']}.id =
          {$this->_aliases['civicrm_email']}.contact_id AND
          {$this->_aliases['civicrm_email']}.is_primary = 1 )";
          }
          if ( $this->_emailField_b ) {
              $this->_from .= "
              LEFT JOIN civicrm_email {$this->_aliases['civicrm_email_b']}
              ON ( {$this->_aliases['civicrm_contact']}.id =
              {$this->_aliases['civicrm_email_b']}.contact_id AND
                {$this->_aliases['civicrm_email_b']}.is_primary = 1 )";
                }
      $this->selectableCustomDataFrom();
    }

    function statistics( &$rows ) {
      $statistics = parent::statistics( $rows );

                  $isStatusFilter = false;
                  $relStatus      = null;
                  if ( CRM_Utils_Array::value('is_active_value', $this->_params ) == '1' ) {
                  $relStatus = 'Is equal to Active';
              } elseif ( CRM_Utils_Array::value('is_active_value', $this->_params ) == '0' ) {
              $relStatus = 'Is equal to Inactive';
              }
              if ( CRM_Utils_Array::value( 'filters', $statistics ) ) {
              foreach( $statistics['filters'] as $id => $value ) {
              //for displaying relationship type filter
                  if( $value['title'] == 'Relationship' ) {
                  $relTypes = CRM_Core_PseudoConstant::relationshipType();
                      $statistics['filters'][$id]['value'] =
                      'Is equal to '.$relTypes[$this->_params['relationship_type_id_value']]['label_'.$this->relationType] ;
              }

                  //for displaying relationship status
                  if ( $value['title'] == 'Relationship Status' ) {
                  $isStatusFilter  = true;
                  $statistics['filters'][$id]['value'] = $relStatus;
                  }
                  }
                  }
                  //for displaying relationship status
                  if ( !$isStatusFilter && $relStatus ) {
            $statistics['filters'][] = array ( 'title' => 'Relationship Status',
                  'value' => $relStatus ) ;
                  }
                  return $statistics;
              }


  function postProcess( ) {
    $this->beginPostProcess( );
    $this->relationType = null;
    $relType = $originalRelationshipTypes = array( );

    if ( CRM_Utils_Array::value( 'relationship_type_id_value', $this->_params ) && is_array($this->_params['relationship_type_id_value']) ) {
      $originalRelationshipTypes = $this->_params['relationship_type_id_value'];
      foreach ($this->_params['relationship_type_id_value'] as $relString){
        $relType = explode('_',  $relString);
        $this->relationType[] = $relType[1].'_'.$relType[2];
        $relationships[] = intval( $relType[0] );
      }
    }
    $this->_params['relationship_type_id_value'] = $relationships;
    $this->buildACLClause( array( $this->_aliases['contact_a_civicrm_contact'] ,$this->_aliases['contact_b_civicrm_contact'] ) );
    $sql = $this->buildQuery( );
    $rows = array();
    $this->buildRows ( $sql, $rows );
    $this->_params['relationship_type_id_value'] = $originalRelationshipTypes;
    $this->formatDisplay( $rows );
    $this->doTemplateAssignment( $rows );
    $this->endPostProcess( $rows );
  }

  function alterDisplay( &$rows ) {
        // custom code to alter rows
          $entryFound = true;

          foreach ( $rows as $rowNum => $row ) {

          if ( array_key_exists('civicrm_case_status_id', $row ) ) {
            if ( $value = $row['civicrm_case_status_id'] ) {
              $this->case_statuses = CRM_Case_PseudoConstant::caseStatus();
              $rows[$rowNum]['civicrm_case_status_id'] = $this->case_statuses[$value];
              $entryFound = true;
                }
            }
              // handle country
              if ( array_key_exists('civicrm_address_country_id', $row) ) {
              if ( $value = $row['civicrm_address_country_id'] ) {
              $rows[$rowNum]['civicrm_address_country_id'] =
                  CRM_Core_PseudoConstant::country( $value, false );
              }
                $entryFound = true;
              }

              if ( array_key_exists('civicrm_address_state_province_id', $row) ) {
              if ( $value = $row['civicrm_address_state_province_id'] ) {
              $rows[$rowNum]['civicrm_address_state_province_id'] =
                        CRM_Core_PseudoConstant::stateProvince( $value, false );
              }
                $entryFound = true;
              }

              if ( array_key_exists('civicrm_contact_sort_name_a', $row) &&
              array_key_exists('civicrm_contact_id', $row) ) {
              $url = CRM_Report_Utils_Report::getNextUrl( 'contact/detail',
              'reset=1&force=1&id_op=eq&id_value=' . $row['civicrm_contact_id'],
              $this->_absoluteUrl, $this->_id );
              $rows[$rowNum]['civicrm_contact_sort_name_a_link' ] = $url;
                  $rows[$rowNum]['civicrm_contact_sort_name_a_hover'] = ts("View Contact details for this contact.");
                      $entryFound = true;
            }

            if ( array_key_exists('civicrm_contact_b_sort_name_b', $row) &&
            array_key_exists('civicrm_contact_b_id', $row) ) {
            $url = CRM_Report_Utils_Report::getNextUrl( 'contact/detail',
            'reset=1&force=1&id_op=eq&id_value=' . $row['civicrm_contact_b_id'],
            $this->_absoluteUrl, $this->_id );
            $rows[$rowNum]['civicrm_contact_b_sort_name_b_link' ] = $url;
            $rows[$rowNum]['civicrm_contact_b_sort_name_b_hover'] = ts("View Contact details for this contact.");
                $entryFound = true;
            }

                // skip looking further in rows, if first row itself doesn't
            // have the column we need
                if ( !$entryFound ) {
                break;
            }
            }
            }
}

