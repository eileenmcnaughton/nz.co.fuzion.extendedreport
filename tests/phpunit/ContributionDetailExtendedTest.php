<?php

require_once __DIR__ . '/BaseTestClass.php';

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Test contribution DetailExtended class.
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
class ContributionDetailExtendedTest extends BaseTestClass implements HeadlessInterface, HookInterface, TransactionalInterface {

  protected $contacts = array();

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    parent::setUp();
    $this->enableAllComponents();
    $contacts = $this->createContacts();
    $this->contacts[] = reset($contacts)['id'];
  }

  public function tearDown() {
    parent::tearDown();
  }

  /**
   * Test the ContributionDetailExtended report with order by.
   */
  public function testContributionExtendedReport() {
    $this->setupData();
    $params = array(
      'report_id' => 'contribution/detailextended',
      'fields' => array (
        'civicrm_contact_display_name' => '1',
        'contribution_currency' => '1',
      ),
      'order_bys' => array(
        1 => ['column' => 'contribution_financial_type_id', 'order' => 'ASC'],
        2 => ['column' => 'contribution_total_amount', 'order' => 'DESC'],
      ),
    );
    $rows = $this->getRows($params);
    $this->assertEquals('USD', $rows[0]['civicrm_contribution_contribution_currency']);
  }

  public function testDetailExtendedGroupByContact() {
    $this->setupData();
    $params = [
      'report_id' => 'contribution/detailextended',
      'fields' => [
        'civicrm_contact_display_name' => '1',
        'civicrm_contact_contact_id' => '1',
        'contribution_id' => '1',
        'contribution_receive_date' => '1',
        'contribution_total_amount' => '1',
        'id' => '1',
      ],
      'group_bys' =>['civicrm_contact_contact_id' => '1'],
      'order_bys' => [['column' => '-'],
      ],
    ];
    $this->getRows($params);
  }

  protected function setupData() {
    $this->callAPISuccess('Order', 'create', [
      'contact_id' => $this->contacts[0],
      'total_amount' => 5,
      'financial_type_id' => 2
    ]);
  }

  public function testReportWithMoreThanTwentyFiveContributions() {
    $this->createMoreThanTwentyFiveContributions();
    $params = [
      'report_id' => 'contribution/detailextended',
      'fields' => [
        'civicrm_contact_display_name' => '1',
        'civicrm_contact_contact_id' => '1',
        'contribution_id' => '1',
        'contribution_receive_date' => '1',
        'contribution_total_amount' => '1',
        'id' => '1',
      ],
      'group_bys' => [
        'contribution_id' => '1',
      ],
    ];
    $rows = $this->getRows($params);
    $this->assertEquals(61, count($rows));
    $rollupRow = $rows[60];
    $this->assertEquals('', $rollupRow['civicrm_contact_civicrm_contact_display_name']);
    unset($rows[60]);
    $total = 0;
    foreach ($rows as $row) {
      $total += $row['civicrm_contribution_contribution_total_amount_sum'];
    }
    $this->assertEquals(9150, $total);
    $stats = $this->callAPISuccess('ReportTemplate', 'getstatistics', $params)['values'];
    $this->assertEquals('$ 9,150.00 (60)', $stats['counts']['amount']['value']);
  }

  public function createMoreThanTwentyFiveContributions() {
    $amount = 5;
    $contactData = array_merge(
      $this->getContactData('Organization', 10),
      $this->getContactData('Individual', 10),
      $this->getContactData('Household', 10)
    );
    foreach ($contactData as $contactParams) {
      $contact = $this->callAPISuccess('Contact', 'create', $contactParams);
      $this->callAPISuccess('Contribution', 'create', [
        'contact_id' => $contact['id'],
        'total_amount' => $amount,
        'financial_type_id' => 'Donation',
        'receive_date' => '2018-12-09',
      ]);
      $this->contacts[] = $contact;
      $amount = $amount + 5;
      $this->callAPISuccess('Contribution', 'create', [
        'contact_id' => $contact['id'],
        'total_amount' => $amount,
        'financial_type_id' => 'Donation',
        'receive_date' => '2018-11-09',
      ]);
      $amount = $amount + 5;
    }
  }

}
