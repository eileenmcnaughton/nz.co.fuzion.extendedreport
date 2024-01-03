<?php

namespace Civi\Extendedreport\Contribute;

use Civi\Extendedreport\BaseTestClass;

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
class ContributionContributionsTest extends BaseTestClass {

  protected $contacts = [];

  /**
   * Test metadata retrieval.
   *
   */
  public function testGetMetadata(): void {
    $metadata = $this->callAPISuccess('ReportTemplate', 'getmetadata', ['report_id' => 'contribution/contributions'])['values'];
    $this->assertEquals('Contribution ID', $metadata['fields']['contribution_id']['title']);
    $this->assertIsArray($metadata['fields']['contribution_id']);
  }

  /**
   * Test rows retrieval.
   *
   * @param array $overrides
   *   array to override function parameters
   *
   * @dataProvider getRowVariants
   */
  public function testGetRows(array $overrides): void {
    $params = array_merge([
      'report_id' => 'contribution/contributions',
      'fields' => [
        'contribution_id' => '1',
        'contribution_financial_type_id' => '1',
        'contribution_campaign_id' => '1',
        'contribution_source' => '1',
      ],
    ], $overrides);
    $this->callAPISuccess('ReportTemplate', 'getrows', $params)['values'];
  }

  /**
   * Get variations of data to test against the report.
   *
   * @return array
   */
  public function getRowVariants(): array {
    return [
      [
        [
          'order_bys' => [['column' => 'contribution_check_number', 'order' => 'ASC']],
          'group_bys' => ['contribution_campaign_id' => '1'],
        ],
      ],
      [
        [
          'order_bys' => [['column' => 'contribution_check_number', 'order' => 'DESC']],
          'group_bys' => ['contribution_campaign_id' => '1'],
        ],
      ],
      [
        [
          'order_bys' => [['column' => 'contribution_check_number', 'order' => 'DESC', 'section' => 1]],
          'group_bys' => ['contribution_campaign_id' => '1'],
        ],
      ],
      [
        [
          'order_bys' => [['column' => 'contribution_check_number', 'order' => 'DESC', 'section' => 1, 'pageBreak' => 1]],
          'group_bys' => ['contribution_campaign_id' => '1'],
        ],
      ],
      [
        [
          'order_bys' => [['column' => 'contribution_check_number', 'order' => 'ASC']],
        ],
      ],
      [
        [
          'order_bys' => [['column' => 'contribution_check_number', 'order' => 'DESC']],
        ],
      ],
      [
        [
          'order_bys' => [['column' => 'contribution_check_number', 'order' => 'DESC', 'section' => 1]],
        ],
      ],
      [
        [
          'order_bys' => [['column' => 'contribution_check_number', 'order' => 'DESC', 'section' => 1, 'pageBreak' => 1]],
        ],
      ],
      [
        [
          'extended_order_bys' => [['column' => 'contribution_check_number', 'order' => 'DESC', 'title' => 'special']],
        ],
      ],
      [
        [
          'fields' => [
            'contribution_financial_type_id' => '1',
            'contribution_receive_date' => '1',
            'contribution_total_amount' => '1',
            'civicrm_contact_display_name' => '1',
          ],
          'order_bys' => [1 => ['column' => 'civicrm_contact_sort_name', 'order' => 'DESC', 'section' => 1]],
        ],
      ],
    ];
  }

  /**
   * Test that is doesn't matter if the having filter is selected.
   *
   */
  public function testGetRowsHavingFilterNotSelected(): void {
    $params = [
      'report_id' => 'contribution/contributions',
      'contribution_total_amount_sum_op' => 'lte',
      'contribution_total_amount_sum_value' => '1000',
      'group_bys' => [
        'contribution_financial_type_id' => '1',
        'contribution_campaign_id' => '1',
      ],
      'order_bys' => [['column' => 'contribution_source', 'order' => 'ASC']],
    ];
    $this->callAPISuccess('ReportTemplate', 'getrows', $params)['values'];
  }

  /**
   * Test that between filter is respected.
   *
   */
  public function testGetRowsHavingFilterBetween(): void {
    // Create 2 contacts - one with total of $50 & one with total of $100.
    $this->createContacts(2);
    $this->callAPISuccess('Contribution', 'create', [
      'financial_type_id' => 'Donation',
      'total_amount' => 50,
      'contact_id' => $this->ids['Contact'][0],
    ]);
    $this->callAPISuccess('Contribution', 'create', [
      'financial_type_id' => 'Donation',
      'total_amount' => 50,
      'contact_id' => $this->ids['Contact'][1],
    ]);
    $this->callAPISuccess('Contribution', 'create', [
      'financial_type_id' => 'Donation',
      'total_amount' => 50,
      'contact_id' => $this->ids['Contact'][1],
    ]);

    $params = [
      'report_id' => 'contribution/contributions',
      'contribution_total_amount_sum_op' => 'bw',
      'contribution_total_amount_sum_min' => '51',
      'contribution_total_amount_sum_max' => '100',
      'group_bys' => [
        'civicrm_contact_contact_id' => '1',
      ],
      'fields' => array_fill_keys(['contribution_financial_type_id', 'contribution_total_amount', 'civicrm_contact_contact_id'], 1),
    ];
    $rows = $this->callAPISuccess('ReportTemplate', 'getrows', $params)['values'];
    $this->assertCount(1, $rows);
    $this->assertEquals(100, $rows[0]['civicrm_contribution_contribution_total_amount_sum']);
    $this->assertEquals(2, $rows[0]['civicrm_contribution_contribution_total_amount_count']);
    // Make sure total amount is optional
    unset($params['fields']['contribution_total_amount']);
    $params['options']['metadata'] = ['labels'];
    $rows = $this->callAPISuccess('ReportTemplate', 'getrows', $params);
    $this->assertEquals(1, $rows['count']);
    $this->assertEquals([
      'civicrm_contact_civicrm_contact_contact_id' => 'Contact ID',
      'civicrm_contribution_contribution_financial_type_id' => 'Contribution Type (Financial)',
    ], $rows['metadata']['labels']);
  }

  /**
   * Test that is doesn't matter if the having filter is selected.
   */
  public function testGetRowsFilterCustomData(): void {
    $this->enableAllComponents();
    $ids = $this->createCustomGroupWithField([]);
    $contribution = $this->createTwoContactsWithContributions($ids);

    $rows = $this->getRowsFilteredByCustomField($ids['custom_field_id'], $contribution['id']);
    $this->assertCount(1, $rows);
  }

  /**
   * Test that is doesn't matter if the having filter is selected.
   */
  public function testGetRowsWithNotes(): void {
    $ids = $this->createTwoContactsWithContributions();
    $this->callAPISuccess('Contribution', 'create', ['id' => $ids['id'], 'contribution_note' => 'first note', 'contact_id' => $this->ids['Contact'][1]]);
    $this->callAPISuccess('Contribution', 'create', ['id' => $ids['id'], 'contribution_note' => 'second note', 'contact_id' => $this->ids['Contact'][1]]);
    $this->callAPISuccess('Contact', 'create', ['note' => 'first contact note', 'id' => $this->ids['Contact'][1]]);
    $this->callAPISuccess('Contact', 'create', ['note' => 'second contact note', 'id' => $this->ids['Contact'][1]]);
    $rows = $this->getRows([
      'report_id' => 'contribution/contributions',
      'order_bys' => [['column' => 'contribution_id', 'order' => 'DESC']],
      'fields' => [
        'product_name' => '1',
        'product_description' => '1',
        'product_sku' => '1',
        'contribution_product_product_option' => '1',
        'contribution_product_fulfilled_date' => '1',
        'contribution_note_note' => '1',
        'civicrm_contact_suffix_id' => '1',
        'contact_note_note' => '1',
      ]
    ]);
    $this->assertCount(2, $rows, print_r($this->sql, TRUE) . "\n" . print_r($rows, TRUE));
    $this->assertEquals('first note, second note', $rows[0]['contribution_civicrm_note_contribution_note_note']);
    $this->assertEquals('first contact note, second contact note', $rows[0]['contact_civicrm_note_contact_note_note']);

    $this->assertEquals(NULL, $rows[1]['contribution_civicrm_note_contribution_note_note']);
    $this->assertEquals(NULL, $rows[1]['contact_civicrm_note_contact_note_note']);
  }

  /**
   * Test we don't get a failed join pulling in address custom data but not the address.
   *
   * @throws \CRM_Core_Exception
   */
  public function testGetRowFilterAddressCustomData(): void {
    $this->enableAllComponents();
    $ids = $this->createCustomGroupWithField([], 'Address');
    $contribution = $this->createTwoContactsWithContributions();

    $this->callAPISuccess('Address', 'create', [
      'contact_id' => $this->ids['Contact'][1],
      'custom_' . $ids['custom_field_id'] => $contribution['id'],
    ]);
    $rows = $this->getRowsFilteredByCustomField($ids['custom_field_id'], $contribution['id']);
    $this->assertCount(1, $rows);
  }

  /**
   * @param array $ids
   *
   * @return array
   */
  protected function createTwoContactsWithContributions(array $ids = []): array {
    $contribution = [];
    $contacts = $this->createContacts(2);
    foreach ($contacts as $contact) {
      $contribution = (array) $this->callAPISuccess('Contribution', 'create', [
        'total_amount' => 4,
        'financial_type_id' => 'Donation',
        'contact_id' => $contact['id'],
      ]);
      if (!empty($ids)) {
        $contactParams = ['id' => $contact['id'], 'custom_' . $ids['custom_field_id'] => $contribution['id']];
        $this->callAPISuccess('Contact', 'create', $contactParams);
      }
      $this->ids['Contribution'][] = $contribution['id'];
    }
    return $contribution;
  }

  /**
   * Get rows filtered by the custom field having the given value.
   *
   * @param int $id
   * @param string|int $value
   *
   * @return array
   */
  protected function getRowsFilteredByCustomField(int $id, $value): array {
    $params = [
      'report_id' => 'contribution/contributions',
      'fields' => ['contribution_id'],
      'custom_' . $id . '_op' => 'eq',
      'custom_' . $id . '_value' => $value,
    ];
    return $this->callAPISuccess('ReportTemplate', 'getrows', $params)['values'];
  }

}
