<?php

use PHPUnit\Framework\TestCase;

/**
 * Duplicate intersoccer_points_balance usermeta rows (issue #108).
 *
 * Admin Adjust and checkout must read/write the same canonical row. Legacy
 * duplicates are collapsed by repair; change_points_balance always targets the
 * lowest umeta_id after ensuring a single row.
 */
class DuplicatePointsBalanceRepairTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/bootstrap.php';
        require_once __DIR__ . '/../includes/class-points-manager.php';
        require_once __DIR__ . '/../includes/class-admin-points.php';

        $this->resetState();
    }

    protected function tearDown(): void {
        $this->resetState();
        parent::tearDown();
    }

    private function resetState(): void {
        global $mock_user_meta, $mock_points_balance_rows, $mock_points_balances, $mock_points_log_rows, $mock_force_points_balance_read, $mock_wp_json_response, $mock_user_capabilities;

        if (class_exists('InterSoccer_Points_Manager')) {
            $reflection = new ReflectionClass('InterSoccer_Points_Manager');
            if ($reflection->hasProperty('instance')) {
                $prop = $reflection->getProperty('instance');
                $prop->setAccessible(true);
                $prop->setValue(null, null);
            }
        }

        $mock_user_meta = [];
        $mock_points_balance_rows = [];
        $mock_points_balances = [];
        $mock_points_log_rows = [];
        $mock_force_points_balance_read = [];
        $mock_wp_json_response = null;
        $mock_user_capabilities = [
            'manage_options' => true,
            'manage_woocommerce' => true,
        ];
        $_POST = [];
    }

    private function seedDuplicateBalance($user_id, $canonical_value, $stale_value, $canonical_umeta_id = null, $stale_umeta_id = null) {
        global $mock_user_meta, $mock_points_balance_rows;

        $user_id = (int) $user_id;
        $canonical_umeta_id = $canonical_umeta_id ?: (($user_id * 1000) + 1);
        $stale_umeta_id = $stale_umeta_id ?: (($user_id * 1000) + 2);

        $mock_points_balance_rows[$user_id] = [
            ['umeta_id' => $canonical_umeta_id, 'meta_value' => (int) $canonical_value],
            ['umeta_id' => $stale_umeta_id, 'meta_value' => (int) $stale_value],
        ];
        // get_user_meta without repair still looks like the stale checkout read.
        $mock_user_meta[$user_id] = [
            'intersoccer_points_balance' => (int) $stale_value,
        ];
    }

    public function testRepairCollapsesDuplicateRowsToLedgerBalance() {
        global $mock_user_meta, $mock_points_balance_rows, $mock_points_balances;

        $user_id = 10801;
        $this->seedDuplicateBalance($user_id, 50, 50);
        // Admin +15 landed on the higher umeta_id only (pre-fix divergence).
        $mock_points_balance_rows[$user_id][1]['meta_value'] = 65;
        $mock_points_balances[$user_id] = 65;

        $points = new InterSoccer_Points_Manager();
        $summary = $points->repair_duplicate_points_balance_rows($user_id);

        $this->assertSame(1, $summary['users_repaired']);
        $this->assertSame(1, $summary['rows_removed']);
        $this->assertCount(1, $mock_points_balance_rows[$user_id]);
        $this->assertSame(65, (int) $mock_user_meta[$user_id]['intersoccer_points_balance']);
        $this->assertSame(65, $points->get_points_balance($user_id));
        $this->assertSame(65, InterSoccer_Points_Manager::read_points_balance($user_id));
    }

    public function testRepairUsesMaxMetaWhenLedgerMissing() {
        global $mock_user_meta, $mock_points_balance_rows, $mock_points_balances;

        $user_id = 10802;
        $this->seedDuplicateBalance($user_id, 40, 55);
        unset($mock_points_balances[$user_id]);

        $points = new InterSoccer_Points_Manager();
        $result = $points->repair_user_points_balance_rows($user_id);

        $this->assertTrue($result['repaired']);
        $this->assertSame(55, $result['balance']);
        $this->assertCount(1, $mock_points_balance_rows[$user_id]);
        $this->assertSame(55, (int) $mock_user_meta[$user_id]['intersoccer_points_balance']);
    }

    public function testAdjustAndCheckoutSeeSameBalanceUnderDuplicates() {
        global $mock_user_meta, $mock_points_balance_rows, $mock_points_balances, $mock_wp_json_response;

        $user_id = 10803;
        // Two rows both at 50; get_user_meta still returns an arbitrary/stale 50.
        $this->seedDuplicateBalance($user_id, 50, 50);
        $mock_points_balances[$user_id] = 50;

        $points = new InterSoccer_Points_Manager();

        $_POST = [
            'nonce' => 'test',
            'user_id' => (string) $user_id,
            'adjustment_type' => 'add',
            'points_amount' => '15',
            'reason' => 'Goodwill credit for issue 108',
        ];
        (new InterSoccer_Admin_Points())->adjust_user_points_ajax();

        $this->assertTrue($mock_wp_json_response['success']);
        $this->assertSame(65, (int) $mock_user_meta[$user_id]['intersoccer_points_balance']);
        $this->assertCount(1, $mock_points_balance_rows[$user_id], 'Adjust must leave a single balance row');
        $this->assertSame(
            65,
            InterSoccer_Points_Manager::read_points_balance($user_id),
            'Checkout-style read must match the adjusted balance'
        );
        $this->assertSame(65, $points->get_points_balance($user_id));
    }

    public function testChangePointsBalanceRepairsThenUpdatesCanonicalRow() {
        global $mock_user_meta, $mock_points_balance_rows, $mock_points_balances;

        $user_id = 10804;
        $this->seedDuplicateBalance($user_id, 50, 20);
        $mock_points_balances[$user_id] = 50;

        $points = new InterSoccer_Points_Manager();
        $new_balance = $points->change_points_balance($user_id, 15, false);

        $this->assertSame(65, $new_balance);
        $this->assertCount(1, $mock_points_balance_rows[$user_id]);
        $this->assertSame(65, (int) $mock_points_balance_rows[$user_id][0]['meta_value']);
        $this->assertSame(65, (int) $mock_user_meta[$user_id]['intersoccer_points_balance']);
        $this->assertSame(65, InterSoccer_Points_Manager::read_points_balance($user_id));
    }

    public function testScanRepairFindsUsersWithDuplicateRows() {
        global $mock_points_balances;

        $this->seedDuplicateBalance(10805, 10, 10);
        $this->seedDuplicateBalance(10806, 7, 9);
        $mock_points_balances[10805] = 10;
        $mock_points_balances[10806] = 9;

        $points = new InterSoccer_Points_Manager();
        $summary = $points->repair_duplicate_points_balance_rows(null);

        $this->assertGreaterThanOrEqual(2, $summary['users_scanned']);
        $this->assertGreaterThanOrEqual(2, $summary['users_repaired']);
        $this->assertSame(10, InterSoccer_Points_Manager::read_points_balance(10805));
        $this->assertSame(9, InterSoccer_Points_Manager::read_points_balance(10806));
    }

    public function testGetPointsBalanceMatchesCheckoutAfterRepair() {
        global $mock_points_balances;

        $user_id = 10807;
        $this->seedDuplicateBalance($user_id, 12, 0);
        $mock_points_balances[$user_id] = 12;

        $points = new InterSoccer_Points_Manager();
        // Reading balance should repair and then match checkout helper.
        $this->assertSame(12, $points->get_points_balance($user_id));
        $this->assertSame(12, InterSoccer_Points_Manager::read_points_balance($user_id));
    }
}
