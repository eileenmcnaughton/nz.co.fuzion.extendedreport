<?php

require_once 'extendedreport.civix.php';

/**
 * Implementation of hook_civicrm_config
 */
function extendedreport_civicrm_config(&$config) {
  _extendedreport_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function extendedreport_civicrm_xmlMenu(&$files) {
  _extendedreport_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function extendedreport_civicrm_install() {
  return _extendedreport_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function extendedreport_civicrm_uninstall() {
  return _extendedreport_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function extendedreport_civicrm_enable() {
  return _extendedreport_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function extendedreport_civicrm_disable() {
  return _extendedreport_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function extendedreport_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _extendedreport_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function extendedreport_civicrm_managed(&$entities) {
  return _extendedreport_civix_civicrm_managed($entities);
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
