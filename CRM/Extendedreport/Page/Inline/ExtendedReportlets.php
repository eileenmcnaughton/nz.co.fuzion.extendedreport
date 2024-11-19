<?php

/**
 * Created by IntelliJ IDEA.
 * User: emcnaughton
 * Date: 8/22/18
 * Time: 4:43 PM
 */
class CRM_Extendedreport_Page_Inline_ExtendedReportlets {

  public function run() {

  }

  /**
   * Get reports configured for display
   */
  public static function getReportsToDisplay() {
    $reportlets = Civi::cache()->get(__CLASS__ . 'contact_summary_reportlets');
    if ($reportlets === NULL) {
      $reportlets = civicrm_api3('ReportInstance', 'get', [
        'form_values' => ['LIKE' => '%contact_reportlet";s:1:"1";%'],
        'options' => ['limit' => 0],
      ])['values'];
      Civi::cache()->set(__CLASS__ . 'contact_summary_reportlets', $reportlets);
    }
    return $reportlets;
  }

  /**
   * Clear report cache out.
   */
  public static function flushReports() {
    Civi::cache()->set(__CLASS__ . 'contact_summary_reportlets', NULL);
  }
}
