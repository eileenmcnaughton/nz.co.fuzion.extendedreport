<?php

require_once 'extendedreport.civix.php';

use CRM_Extendedreport_ExtensionUtil as E;

/**
 * Implementation of hook_civicrm_config
 */
function extendedreport_civicrm_config(&$config) {
  _extendedreport_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_install
 */
function extendedreport_civicrm_install() {
  return _extendedreport_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_enable
 */
function extendedreport_civicrm_enable() {
  return _extendedreport_civix_civicrm_enable();
}

/**
 * Check version is at least as high as the one passed.
 *
 * @param string $version
 *
 * @return bool
 */
function extendedreport_version_at_least($version) {
  $codeVersion = explode('.', CRM_Utils_System::version());
  if (version_compare($codeVersion[0] . '.' . $codeVersion[1], $version) >= 0) {
    return TRUE;
  }
  return FALSE;
}

function extendedreport_civicrm_tabset($tabsetName, &$tabs, $context) {
  if ($tabsetName !== 'civicrm/contact/view') {
    return;
  }
  $reports = civicrm_api3('ReportInstance', 'get', ['form_values' => ['LIKE' => '%contact_dashboard_tab";s:1:"1";%']]);

  foreach ($reports['values'] as $report) {
    $tabs['report_' . $report['id']] = [
      'title' => ts($report['title']),
      'id' => 'report_' . $report['id'],
      'icon' => 'crm-i fa-table',
      'url' => CRM_Utils_System::url('civicrm/report/instance/' . $report['id'], [
          'log_civicrm_address_op' => 'in',
          'contact_id_value' => $context['contact_id'],
          'contact_id' => $context['contact_id'],
          'output' => 'html',
          'force' => 1,
          'section' => 2,
        ]
      ),
      'weight' => 70,
    ];
  }
}

/**
 * @param $op
 * @param $objectName
 * @param $objectId
 * @param $objectRef
 */
function extendedreport_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  if ($objectName === 'ReportInstance') {
    CRM_Extendedreport_Page_Inline_ExtendedReportlets::flushReports();
  }
}

/**
 * @param string $formName
 * @param CRM_Extendedreport_Form_Report_ExtendedReport $form
 */
function extendedreport_civicrm_preProcess($formName, &$form) {
  if (is_subclass_of($form, 'CRM_Extendedreport_Form_Report_ExtendedReport') && $form->getInstanceID()) {
    CRM_Core_Resources::singleton()->addScript("cj('.crm-report-criteria').append(
      '<p><a href=\"" . CRM_Utils_System::url('civicrm/a/#/exreport/report/' . $form->getInstanceID()) . "\">Advanced Report configuration</a> provides options to re-order columnns, change titles & fallback to another field on empty.</p>')");
  }
}

/**
 * Implements hook_civicrm_contactSummaryBlocks().
 *
 * @link https://github.com/civicrm/org.civicrm.contactlayout
 */
function extendedreport_civicrm_contactSummaryBlocks(&$blocks) {

  $reports = CRM_Extendedreport_Page_Inline_ExtendedReportlets::getReportsToDisplay();

  if (empty($reports)) {
    return;
  }
  // Provide our own group for this block to visually distinguish it on the contact summary editor palette.
  $blocks += [
    'extendedreports' => [
      'title' => ts('Extended report'),
      'icon' => 'fa-table',
      'blocks' => [],
    ],
  ];
  foreach ($reports as $report) {
    $blocks['extendedreports']['blocks']['report_' . $report['id']] = [
      'id' => 'report_' . $report['id'],
      'icon' => 'crm-i fa-bar-chart',
      'title' => $report['title'],
      'tpl_file' => 'CRM/Extendedreport/Page/Inline/ExtendedReport.tpl',
      'edit' => FALSE,
      'report_id' => $report['id'],
      'collapsible' => TRUE,
    ];
  }

}
