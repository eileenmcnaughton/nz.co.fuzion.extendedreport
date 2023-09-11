<?php

namespace Civi\Extendedreport;

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
class ExtendedReportTest extends BaseTestClass {

  public function setUp(): void {
    parent::setUp();
    $this->enableAllComponents();
  }

  /**
   * @throws \Civi\Core\Exception\DBQueryException
   */
  public function tearDown(): void {
    \CRM_Core_DAO::executeQuery('DELETE FROM civicrm_pledge');
    parent::tearDown();
    \CRM_Core_DAO::reenableFullGroupByMode();
  }

  /**
   * Example: Test that a version is returned.
   *
   * @throws \Civi\Core\Exception\DBQueryException
   */
  public function testReportsRun(): void {
    foreach ($this->getAllReports() as $report) {
      if (!empty($report['is_require_logging'])) {
        // Hack alert - there is a bug whereby the table is deleted but the row isn't after ActivityExtendedTest.
        // So far I've failed to solve this properly - probably transaction rollback in some way.
        \CRM_Core_DAO::executeQuery("DELETE FROM civicrm_custom_group WHERE name = 'Contact'");
        $this->callAPISuccess('Setting', 'create', ['logging' => TRUE]);
      }
      $this->callAPISuccess('ReportTemplate', 'getrows', [
        'report_id' => $report['params']['report_url'],
      ]);
      if (!empty($report['is_require_logging'])) {
        $this->callAPISuccess('Setting', 'create', ['logging' => FALSE]);
      }
    }
  }

  /**
   * @dataProvider getAllNonLoggingReports
   *
   * @param string $reportID
   */
  public function testReportsRunAllFields(string $reportID): void {
    $metadata = $this->callAPISuccess('ReportTemplate', 'getmetadata', ['report_id' => $reportID])['values'];
    $params = [
      'report_id' => $reportID,
      'fields' => array_fill_keys(array_keys($metadata['fields']), 1),
    ];
    $this->getRows($params);
  }

  /**
   * Test extended fields (as configured in angular form) merge the flat fields array.
   *
   * We are checking we can re-order fields, change titles & add fallbacks.
   *
   * @dataProvider getDataForExtendedFields
   *
   * @param array $group_bys
   */
  public function testExtendedFields(array $group_bys = []): void {
    $contact = $this->callAPISuccess('Contact', 'create', ['contact_type' => 'Individual', 'first_name' => 'first', 'last_name' => 'last']);
    $this->ids['Contact'][] = $contact['id'];
    $this->callAPISuccess('Contribution', 'create', ['financial_type_id' => 'Donation', 'total_amount' => 10, 'contact_id' => $contact['id']]);
    $rows = $this->getRows([
      'report_id' => 'contribution/contributions',
      'extended_fields' => [
        [
          'title' => 'special name',
          'name' => 'civicrm_contact_middle_name',
          'field_on_null' => [
            [
              'title' => 'First name',
              'name' => 'civicrm_contact_first_name',
            ],
          ],
        ],
        [
          'title' => 'boring name',
          'name' => 'civicrm_contact_last_name',
        ],
      ],
      'fields' => ['civicrm_contact_first_name' => 1, 'civicrm_contact_middle_name' => 1, 'class' => ''],
      'group_bys' => $group_bys,
      'class' => '',
    ]);
    $this->assertEquals(['civicrm_contact_civicrm_contact_middle_name', 'civicrm_contact_civicrm_contact_last_name', 'class'], array_keys($rows[0]));
    $this->assertEquals('special name(First name)', $this->labels['civicrm_contact_civicrm_contact_middle_name']);
    $this->assertEquals('boring name', $this->labels['civicrm_contact_civicrm_contact_last_name']);
    $this->assertEquals('first', $rows[0]['civicrm_contact_civicrm_contact_middle_name']);
    $this->assertEquals('last', $rows[0]['civicrm_contact_civicrm_contact_last_name']);
  }

  /**
   * Get fields data
   *
   * @return array
   */
  public function getDataForExtendedFields(): array {
    return [
      [[]],
      [['civicrm_contact_contact_id' => '1']],
    ];
  }

  /**
   * Test extended order bys (as configured by angular form) result in re-ordered fields and order by fallbacks.
   *
   * We are testing that the order is
   * - nick-name fall back on first name
   * - last name desc
   *
   * This means we have
   * contact 0 is b,a (with fall back)
   * contact 1 is a,b (with fall back)
   * contact 2 is a,c (no fallback)
   * contact 3 is b,z (no fallback)
   *
   * First is contact 2 - this demonstrates that nick name is preferred
   * Second is contact 1 - this demonstrates fallback on first name and is after 1 due to sort order
   * Third is contact 3 this demonstrates that the fallback option has 'slotted' between the 2
   *  nick names
   * Forth is contact 0 - this demonstrates desc sort order on last name.
   *
   * @dataProvider getFieldsForExtendedOrderBys
   *
   * @param array $fields
   */
  public function testExtendedOrderBys(array $fields): void {
    $this->ids['Contact'][0] = $this->callAPISuccess('Contact', 'create', ['contact_type' => 'Individual', 'first_name' => 'b', 'last_name' => 'a'])['id'];
    $this->ids['Contact'][1] = $this->callAPISuccess('Contact', 'create', ['contact_type' => 'Individual', 'first_name' => 'a', 'last_name' => 'b'])['id'];
    $this->ids['Contact'][2] = $this->callAPISuccess('Contact', 'create', ['contact_type' => 'Individual', 'first_name' => 'r', 'nick_name' => 'a', 'last_name' => 'c'])['id'];
    $this->ids['Contact'][3] = $this->callAPISuccess('Contact', 'create', ['contact_type' => 'Individual', 'first_name' => 'z', 'nick_name' => 'b', 'last_name' => 'd'])['id'];
    foreach ($this->ids['Contact'] as $contactID) {
      $this->callAPISuccess('Contribution', 'create', ['financial_type_id' => 'Donation', 'total_amount' => 10, 'contact_id' => $contactID]);
    }

    $rows = $this->getRows([
      'report_id' => 'contribution/contributions',
      'extended_order_bys' => [
        [
          'title' => 'Nick Name',
          'column' => 'civicrm_contact_nick_name',
          'field_on_null' => [
            [
              'title' => 'First name',
              'name' => 'civicrm_contact_first_name',
            ],
          ],
        ],
        [
          'title' => 'Last Name',
          'column' => 'civicrm_contact_last_name',
          'order' => 'DESC',
        ],
      ],
      'fields' => $fields,
    ]);

    $this->assertEquals('r', $rows[0]['civicrm_contact_civicrm_contact_first_name']);
    $this->assertEquals('a', $rows[1]['civicrm_contact_civicrm_contact_first_name']);
    $this->assertEquals('z', $rows[2]['civicrm_contact_civicrm_contact_first_name']);
    $this->assertEquals('b', $rows[3]['civicrm_contact_civicrm_contact_first_name']);
  }

  /**
   * Get fields data
   *
   * @return array
   */
  public function getFieldsForExtendedOrderBys(): array {
    return [
      [['civicrm_contact_first_name' => 1, 'civicrm_contact_last_name' => 1]],
      [['civicrm_contact_first_name' => 1, 'civicrm_contact_nick_name' => 1]],
    ];
  }

  /**
   * Test the group filter does not cause an sql error.
   *
   * @param string $reportID
   *
   * @dataProvider getAllNonLoggingReports
   */
  public function testReportsGroupFilter(string $reportID): void {
    $group = $this->callAPISuccess('Group', 'create', ['title' => 'my group']);
    $params = [
      'report_id' => $reportID,
      'fields' => ['contribution_id' => 1],
      'gid_op' => 'in',
      'gid_value' => [$group['id']],
    ];
    $this->getRows($params);
    $this->callAPISuccess('Group', 'delete', ['id' => $group['id']]);
  }

  /**
   * Test the group filter does not cause an sql error.
   *
   * @param string $reportID
   *
   * @dataProvider getAllNonLoggingReports
   */
  public function testReportsTagFilter(string $reportID): void {
    $tag = $this->callAPISuccess('Tag', 'create', ['name' => 'my tag']);
    $params = [
      'report_id' => $reportID,
      'fields' => ['contribution_id' => 1],
      'tagid_op' => 'in',
      'tagid_value' => [$tag['id']],
    ];
    $this->getRows($params);
    $this->callAPISuccess('Tag', 'delete', ['id' => $tag['id']]);
  }

  /**
   * Test the group filter ... filters.
   *
   */
  public function testReportsGroupFilterWorks(): void {
    $group = $this->callAPISuccess('Group', 'create', ['title' => 'bob']);
    $badBob = $this->callAPISuccess('Contact', 'create', ['first_name' => 'bob', 'last_name' => 'bob', 'contact_type' => 'Individual']);
    $goodBob = $this->callAPISuccess('Contact', 'create', ['first_name' => 'bob', 'last_name' => 'bob', 'contact_type' => 'Individual']);

    $this->callAPISuccess('GroupContact', 'create', ['group_id' => $group['id'], 'contact_id' => $goodBob['id'], 'status' => 'Added']);
    $this->callAPISuccess('Contribution', 'create', ['financial_type_id' => 'Donation', 'total_amount' => 10, 'contact_id' => $badBob['id']]);
    $this->callAPISuccess('Contribution', 'create', ['financial_type_id' => 'Donation', 'total_amount' => 10, 'contact_id' => $goodBob['id']]);

    $params = [
      'report_id' => 'contribution/detailextended',
      'fields' => ['contribution_id' => 1],
      'gid_op' => 'in',
      'gid_value' => [$group['id']],
    ];
    $rows = $this->getRows($params);
    $this->assertCount(1, $rows);

    $this->callAPISuccess('Contribution', 'get', ['api.Contribution.delete' => 1]);
    $this->callAPISuccess('Contact', 'get', ['id' => ['IN' => [$goodBob['id'], $badBob['id']], 'api.Contact.delete' => 1]]);
    $this->callAPISuccess('Group', 'delete', ['id' => $group['id']]);
  }

  /**
   * Test the tag filter filters by tag
   *
   */
  public function testReportsTagFilterWorks(): void {
    $tag = $this->callAPISuccess('Tag', 'create', ['name' => 'bob']);
    $badBob = $this->callAPISuccess('Contact', 'create', ['first_name' => 'bob', 'last_name' => 'bob', 'contact_type' => 'Individual']);
    $goodBob = $this->callAPISuccess('Contact', 'create', ['first_name' => 'bob', 'last_name' => 'bob', 'contact_type' => 'Individual']);
    $this->callAPISuccess('EntityTag', 'create', ['tag_id' => $tag['id'], 'entity_id' => $goodBob['id']]);
    $this->callAPISuccess('Contribution', 'create', ['financial_type_id' => 'Donation', 'total_amount' => 10, 'contact_id' => $badBob['id']]);
    $this->callAPISuccess('Contribution', 'create', ['financial_type_id' => 'Donation', 'total_amount' => 10, 'contact_id' => $goodBob['id']]);
    $params = [
      'report_id' => 'contribution/detailextended',
      'fields' => ['contribution_id' => 1, 'contribution_contact_id' => 1],
      'tagid_op' => 'in',
      'tagid_value' => [$tag['id']],
    ];
    $rows = $this->getRows($params);
    $this->assertCount(1, $rows);

    $this->callAPISuccess('Tag', 'delete', ['id' => $tag['id']]);
    $this->callAPISuccess('Contribution', 'get', ['api.Contribution.delete' => 1]);
    $this->callAPISuccess('Contact', 'get', ['id' => ['IN' => [$goodBob['id'], $badBob['id']], 'api.Contact.delete' => 1]]);

  }

  /**
   * Test the future income report with some data.
   *
   */
  public function testPledgeIncomeReport(): void {
    $this->setUpPledgeData();
    $params = [
      'report_id' => 'pledge/income',
      'order_bys' => [['column' => 'pledge_payment_scheduled_date', 'order' => 'ASC']],
    ];
    $rows = $this->getRows($params);
    // 12 exist, 10 are unpaid.
    $this->assertCount(10, $rows);
    $this->assertEquals(date('Y-m-d', strtotime('2 years ago')), date('Y-m-d', strtotime($rows[0]['civicrm_pledge_payment_pledge_payment_scheduled_date'])));
    $this->assertEquals(14285.74, $rows[0]['civicrm_pledge_payment_pledge_payment_scheduled_amount_sum']);
    $this->assertEquals(14285.74, $rows[0]['civicrm_pledge_payment_pledge_payment_scheduled_amount_cumulative']);
    $this->assertEquals(10000, $rows[1]['civicrm_pledge_payment_pledge_payment_scheduled_amount_sum']);
    $this->assertEquals(24285.74, round($rows[1]['civicrm_pledge_payment_pledge_payment_scheduled_amount_cumulative'], 2));
  }

  /**
   * Test the future income report with some data.
   *
   */
  public function testPledgeIncomeReportGroupByContact(): void {
    $this->setUpPledgeData();
    $params = [
      'report_id' => 'pledge/income',
      'group_bys' => ['civicrm_contact_contact_id' => '1'],
    ];
    $rows = $this->getRows($params);
    $this->assertCount(3, $rows);
    $this->assertEquals(20000, $rows[0]['civicrm_pledge_payment_pledge_payment_scheduled_amount_sum']);
    $this->assertEquals(20000, $rows[0]['civicrm_pledge_payment_pledge_payment_scheduled_amount_cumulative']);
    $this->assertEquals(80000, $rows[1]['civicrm_pledge_payment_pledge_payment_scheduled_amount_sum']);
    $this->assertEquals(100000, $rows[1]['civicrm_pledge_payment_pledge_payment_scheduled_amount_cumulative']);
    $this->assertEquals(100000, $rows[2]['civicrm_pledge_payment_pledge_payment_scheduled_amount_sum']);
    $this->assertEquals(200000, $rows[2]['civicrm_pledge_payment_pledge_payment_scheduled_amount_cumulative']);
  }

  /**
   * Test the future income report with some data.
   *
   */
  public function testPledgeIncomeReportGroupByMonth(): void {
    $this->setUpPledgeData();
    $params = [
      'report_id' => 'pledge/income',
      'group_bys' => ['pledge_payment_scheduled_date' => '1'],
      'group_bys_freq' => [
        'pledge_payment_scheduled_date' => 'MONTH',
        'next_civicrm_pledge_payment_next_scheduled_date' => 'MONTH',
      ],
      'fields' => [
        'pledge_payment_scheduled_date' => '1',
        'pledge_payment_scheduled_amount' => '1',
      ],
    ];
    $this->callAPISuccess('PledgePayment', 'get', []);
    $rows = $this->getRows($params);
    $this->assertCount(5, $rows);
    $this->assertEquals(14285.74, $rows[0]['civicrm_pledge_payment_pledge_payment_scheduled_amount_sum']);
    $this->assertEquals(14285.74, $rows[0]['civicrm_pledge_payment_pledge_payment_scheduled_amount_cumulative']);
    $this->assertEquals(10000, $rows[1]['civicrm_pledge_payment_pledge_payment_scheduled_amount_sum']);
    $this->assertEquals(24285.74, round($rows[1]['civicrm_pledge_payment_pledge_payment_scheduled_amount_cumulative'], 2));
    $this->assertEquals(10000, $rows[2]['civicrm_pledge_payment_pledge_payment_scheduled_amount_sum']);
    $this->assertEquals(34285.74, $rows[2]['civicrm_pledge_payment_pledge_payment_scheduled_amount_cumulative']);
    $this->assertEquals(24285.74, round($rows[1]['civicrm_pledge_payment_pledge_payment_scheduled_amount_cumulative'], 2));
    $this->assertEquals(85714.26, $rows[3]['civicrm_pledge_payment_pledge_payment_scheduled_amount_sum']);
  }

}
