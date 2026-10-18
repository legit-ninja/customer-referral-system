<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for CHF-based ROI calculation in admin dashboard.
 *
 * New architecture uses ROI Calculator:
 * - Cost: Points EARNED (loyalty + referral + goodwill) + Coach commissions
 * - Revenue: Referred customer orders + loyalty holder extra spend
 * - ROI: Range-based (25%-100% incrementality assumptions)
 */
class ROICalculationTest extends TestCase {
    private $saved = [];
    private $saved_options = [];

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-admin-dashboard-main.php';

        global $mock_points_log_rows, $mock_user_meta, $mock_points_balances;
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_get_orders, $mock_transients;

        $this->saved = [
            'rows' => $mock_points_log_rows ?? [],
            'meta' => $mock_user_meta ?? [],
            'balances' => $mock_points_balances ?? [],
            'var_results' => $mock_wpdb_get_var_results ?? [],
            'results' => $mock_wpdb_get_results ?? [],
            'wc_orders' => $mock_wc_get_orders ?? null,
            'transients' => $mock_transients ?? [],
        ];
        
        $this->saved_options = [
            'intersoccer_roi_camp_margin_percent' => get_option('intersoccer_roi_camp_margin_percent'),
        ];
        
        $mock_points_log_rows = [];
        $mock_points_balances = [];
        $mock_wpdb_get_var_results = [];
        $mock_wpdb_get_results = [];
        $mock_wc_get_orders = function() { return []; };
        $mock_transients = [];
        parent::setUp();
    }

    protected function tearDown(): void {
        global $mock_points_log_rows, $mock_user_meta, $mock_points_balances;
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_get_orders, $mock_transients;

        $mock_points_log_rows = $this->saved['rows'];
        $mock_user_meta = $this->saved['meta'];
        $mock_points_balances = $this->saved['balances'];
        $mock_wpdb_get_var_results = $this->saved['var_results'];
        $mock_wpdb_get_results = $this->saved['results'];
        $mock_wc_get_orders = $this->saved['wc_orders'];
        $mock_transients = $this->saved['transients'];
        
        foreach ($this->saved_options as $key => $value) {
            if ($value !== false) {
                update_option($key, $value);
            } else {
                delete_option($key);
            }
        }
        parent::tearDown();
    }

    /**
     * Test ROI calculation with normal data and margin set.
     * Cost comes from points_log (earned points + commissions).
     * With margin set and data present, has_data should be true.
     */
    public function testROI_WithNormalDataAndMargin() {
        // Set camp margin for ROI calculation
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        // Seed points log with loyalty activity (100) and commissions (50)
        $this->addPointsEntry(1, 'order_purchase', 100, 'Purchase');
        $this->mockCommissions(50.00);

        $dashboard = new InterSoccer_Admin_Dashboard_Main();
        $roi_data = $this->invoke($dashboard, 'calculate_chf_roi');

        // Cost = loyalty points earned (100) + commissions (50)
        $this->assertEquals(150.00, $roi_data['cost_chf']);
        // Has data because we have cost > 0
        $this->assertTrue($roi_data['has_data']);
    }

    /**
     * Test ROI with zero cost shows "Not enough data" in roi_display.
     */
    public function testROI_WithZeroCost() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        // No points, no commissions = zero cost
        $dashboard = new InterSoccer_Admin_Dashboard_Main();
        $roi_data = $this->invoke($dashboard, 'calculate_chf_roi');

        $this->assertEquals(0.00, $roi_data['cost_chf']);
        $this->assertFalse($roi_data['has_data']);
        $this->assertNull($roi_data['roi_percentage']);
        // With zero cost, should show "Not enough data"
        $this->assertStringContainsString('Not enough data', $roi_data['roi_display']);
    }

    /**
     * Test ROI with no data (zero cost, zero revenue).
     */
    public function testROI_WithNoData() {
        $dashboard = new InterSoccer_Admin_Dashboard_Main();
        $roi_data = $this->invoke($dashboard, 'calculate_chf_roi');

        $this->assertEquals(0.00, $roi_data['cost_chf']);
        $this->assertEquals(0.00, $roi_data['revenue_chf']);
        $this->assertFalse($roi_data['has_data']);
        $this->assertNull($roi_data['roi_percentage']);
    }

    /**
     * Test that high cost relative to revenue produces correct cost tracking.
     */
    public function testROI_HighCost() {
        // High cost: 500 loyalty + 100 commission = 600
        $this->addPointsEntry(1, 'order_purchase', 500, 'Purchase');
        $this->mockCommissions(100.00);

        $dashboard = new InterSoccer_Admin_Dashboard_Main();
        $roi_data = $this->invoke($dashboard, 'calculate_chf_roi');

        $this->assertEquals(600.00, $roi_data['cost_chf']);
        $this->assertTrue($roi_data['has_data']);
    }

    /**
     * Test that loyalty_cost_chf is tracked separately.
     */
    public function testROI_LoyaltyCostTracked() {
        $this->addPointsEntry(1, 'order_purchase', 200, 'Purchase');

        $dashboard = new InterSoccer_Admin_Dashboard_Main();
        $roi_data = $this->invoke($dashboard, 'calculate_chf_roi');

        $this->assertEquals(200.00, $roi_data['points_discount_chf']);
    }

    /**
     * Test that commissions are tracked separately and add to cost.
     */
    public function testROI_CostIncludesCommissions() {
        $this->addPointsEntry(1, 'order_purchase', 300, 'Purchase');
        $this->mockCommissions(75.00);

        $dashboard = new InterSoccer_Admin_Dashboard_Main();
        $roi_data = $this->invoke($dashboard, 'calculate_chf_roi');

        $this->assertEquals(300.00, $roi_data['points_discount_chf']);
        $this->assertEquals(75.00, $roi_data['coach_commission_chf']);
        $this->assertEquals(375.00, $roi_data['cost_chf']);
    }

    /**
     * Test that Referral ROI card displays "Set camp margin" when no margin is set.
     * This tests the dashboard card logic, not just the calculator.
     */
    public function testReferralROICard_BlankWhenNoMargin() {
        delete_option('intersoccer_roi_camp_margin_percent');
        
        $dashboard = new InterSoccer_Admin_Dashboard_Main();
        
        // Verify the calculator reports no margin
        $this->assertFalse($dashboard->roi_calculator->has_camp_margin());
        
        // The referral ROI card should show "Set camp margin" when no margin configured
        // We test this by verifying has_camp_margin() and the expected behavior
        $roi_data = $dashboard->roi_calculator->calculate_referral_roi();
        $this->assertEquals('—', $roi_data['roi_display']);
        $this->assertTrue($roi_data['show_margin_prompt']);
    }

    public function testPointsActivity_UsesPointsLog() {
        global $mock_points_log_rows, $mock_points_balances;
        $mock_points_log_rows = [];
        $mock_points_balances = [];

        $this_month = date('Y-m-15');
        $this->addPointsEntry(1, 'order_purchase', 5000, 'Purchase', $this_month);
        $this->addPointsEntry(1, 'points_redemption', -1000, 'Redeemed', $this_month);

        $dashboard = new InterSoccer_Admin_Dashboard_Main();
        $trends = $this->invoke($dashboard, 'get_credit_trends_data');

        $this->assertArrayHasKey('revenue', $trends);
        $this->assertArrayHasKey('costs', $trends);

        $latest_earned = end($trends['revenue']);
        $latest_cost = end($trends['costs']);
        $this->assertEquals(5000, $latest_earned);
        $this->assertEquals(1000, $latest_cost);
    }

    public function testDistribution_BySource() {
        global $mock_points_log_rows, $mock_points_balances;
        $mock_points_log_rows = [];
        $mock_points_balances = [];

        $this->addPointsEntry(1, 'order_purchase', 10000, 'Purchase');
        $this->addPointsEntry(1, 'admin_adjustment', 2000, 'Admin');
        $this->addPointsEntry(1, 'customer_referral_reward', 500, 'Referral');
        $this->addPointsEntry(1, 'points_redemption', -1000, 'Redeemed');

        $dashboard = new InterSoccer_Admin_Dashboard_Main();
        $distribution = $this->invoke($dashboard, 'get_program_distribution_data');

        $this->assertContains('Purchase Points', $distribution['labels']);
        $this->assertContains('Admin Adjustments', $distribution['labels']);
        $this->assertContains('Referral Rewards', $distribution['labels']);
        $this->assertContains('Points Redeemed', $distribution['labels']);
    }

    private function mockCommissions(float $amount) {
        global $mock_wpdb_get_var_results;
        $mock_wpdb_get_var_results['SUM(commission_amount)'] = $amount;
    }

    private function addPointsEntry($user_id, $type, $amount, $description, $date = null) {
        global $mock_points_log_rows;
        $mock_points_log_rows[] = [
            'id' => count($mock_points_log_rows) + 1,
            'user_id' => $user_id,
            'transaction_type' => $type,
            'points_amount' => $amount,
            'description' => $description,
            'created_at' => $date ?? date('Y-m-d H:i:s'),
        ];
    }

    private function invoke($object, $method) {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);
        return $reflection->invoke($object);
    }
}
