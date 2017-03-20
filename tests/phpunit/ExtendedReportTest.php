<?php

require_once __DIR__ . '/BaseTestClass.php';

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * FIXME - Add test description.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class ExtendedReportTest extends BaseTestClass implements HeadlessInterface, HookInterface, TransactionalInterface {

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    parent::setUp();
    $components = array();
    $dao = new CRM_Core_DAO_Component();
    while ($dao->fetch()) {
      $components[$dao->id] = $dao->name;
    }
    civicrm_api3('Setting', 'create', array('enable_components' => $components));
  }

  public function tearDown() {
    parent::tearDown();
  }

  /**
   * Example: Test that a version is returned.
   */
  public function testReportsRun() {
    $reports = array();
    extendedreport_civicrm_managed($reports);
    foreach ($reports as $report) {
      try {
        $this->callAPISuccess('ReportTemplate', 'getrows', array(
          'report_id' => $report['params']['report_url'],
        ));
      }
      catch (CiviCRM_API3_Exception $e) {
        $extra = $e->getExtraParams();
        $this->fail($report['params']['report_url'] . " " . $e->getMessage() . " \n" . CRM_Utils_Array::value('sql', $extra) . "\n" . $extra['trace']);
      }

    }

  }

  /**
   * Test the future income report with some data.
   */
  public function testPledgeIncomeReport() {
    $this->setUpPledgeData();
    $params = array(
      'report_id' => 'pledge/income',
    );
    $rows = $this->getRows($params);
    // 12 exist, 10 are unpaid.
    $this->assertEquals(10, count($rows));
    $this->assertEquals(10000, $rows[0]['civicrm_pledge_payment_pledge_payment_scheduled_amount_sum']);
    $this->assertEquals(10000, $rows[0]['civicrm_pledge_payment_pledge_payment_scheduled_amount_cumulative']);
    $this->assertEquals(20000, $rows[1]['civicrm_pledge_payment_pledge_payment_scheduled_amount_cumulative']);
  }

  /**
   * Test the future income report with some data.
   */
  public function testPledgeIncomeReportGroupByContact() {
    $this->setUpPledgeData();
    $params = array(
      'report_id' => 'pledge/income',
      'group_bys' => array('civicrm_contact_contact_id' => '1'),
    );
    $rows = $this->getRows($params);
    $this->assertEquals(3, count($rows));
    $this->assertEquals(20000, $rows[0]['civicrm_pledge_payment_pledge_payment_scheduled_amount_sum']);
    $this->assertEquals(20000, $rows[0]['civicrm_pledge_payment_pledge_payment_scheduled_amount_cumulative']);
    $this->assertEquals(80000, $rows[1]['civicrm_pledge_payment_pledge_payment_scheduled_amount_sum']);
    $this->assertEquals(100000, $rows[1]['civicrm_pledge_payment_pledge_payment_scheduled_amount_cumulative']);
    $this->assertEquals(100000, $rows[2]['civicrm_pledge_payment_pledge_payment_scheduled_amount_sum']);
    $this->assertEquals(200000, $rows[2]['civicrm_pledge_payment_pledge_payment_scheduled_amount_cumulative']);
  }

  public function setUpPledgeData() {
    $contacts = array(
      array(
        'first_name' => 'Wonder',
        'last_name' => 'Woman',
        'contact_type' => 'Individual',
        'api.pledge.create' => array(
          'installments' => 4,
          'financial_type_id' => 'Donation',
          'amount' => 40000,
          'start_date' => '1 year ago',
          'create_date' => '1 year ago',
          'original_installment_amount' => 10000,
          'frequency_unit' => 'month',
          'frequency_interval' => 3,
        ),
        'api.contribution.create' => array(
          array(
            'financial_type_id' => 'Donation',
            'total_amount' => 10000,
            'receive_date' => '1 year ago',
          ),
          array(
            'financial_type_id' => 'Donation',
            'total_amount' => 10000,
            'receive_date' => '6 months ago',
          ),
        ),
      ),
      array(
        'first_name' => 'Cat',
        'last_name' => 'Woman',
        'contact_type' => 'Individual',
        'api.pledge.create' => array(
          'installments' => 1,
          'financial_type_id' => 'Donation',
          'amount' => 80000,
          'start_date' => 'now',
          'create_date' => 'now',
          'original_installment_amount' => 80000,
        ),
      ),
      array(
        'organization_name' => 'Heros Inc.',
        'contact_type' => 'Organization',
        'api.pledge.create' => array(
          'installments' => 7,
          'financial_type_id' => 'Donation',
          'start_date' => '1 month ago',
          'create_date' => '1 month ago',
          'original_installment_amount' => 14285.71,
          'amount' => 100000,
        ),
      ),
    );
    foreach ($contacts as $params) {
      $contact = $this->callAPISuccess('Contact', 'create', $params);
      $contributions = $this->callAPISuccess('Contribution', 'get', array('contact_id' => $contact['id']));
      $pledges = $this->callAPISuccess('Pledge', 'get', array('contact_id' => $contact['id']));
      foreach ($contributions['values'] as $contribution) {
        $this->callAPISuccess('PledgePayment', 'create', array(
          'contribution_id' => $contribution['id'],
          'pledge_id' => $pledges['id'],
          'status_id' => 'Completed',
        ));
      }
      if (CRM_Utils_Array::value('organization_name', $params) == 'Heros Inc.') {
        $this->callAPISuccess('PledgePayment', 'get', array(
          'pledge_id' => $pledges['id'],
          'options' => array('limit' => 1, 'sort' => 'scheduled_date DESC'),
          'api.PledgePayment.create' => array('scheduled_amount' => 14285.74, 'scheduled_date' => '2 years ago'),
        ));
      }
    }

  }

  /**
   * @param $params
   * @return array|int
   */
  protected function getRows($params) {
    $params['options']['metadata'] = array('title', 'label', 'sql');
    $rows = $this->callAPISuccess('ReportTemplate', 'getrows', $params);
    $rows = $rows['values'];
    return $rows;
  }

}
