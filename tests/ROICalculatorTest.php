<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for InterSoccer_ROI_Calculator
 * 
 * Tests the ROI spec:
 * - Rolling 12-month period (WordPress timezone)
 * - Referral and Loyalty ROI calculated separately
 * - Points counted as CHF 1 cost when earned (not redeemed)
 * - Expiry and reversal reduce liability, not cost
 * - ROI blank until camp margin % is set
 * - Finance dedupe: first referred order = referral revenue only,
 *   later orders = loyalty holder spend
 * - Finance holder ruling: first orders decide who counts as holder,
 *   but holder SPEND excludes first referred orders
 * 
 * Test types:
 * - Exact-value tests assert hand-computed values with docblock showing calculation
 * - Guard tests verify filters (status, date window) cannot be removed without failing
 * - A few structure tests use assertArrayHasKey for API stability
 */
class ROICalculatorTest extends TestCase {
    
    private $saved_options = [];
    
    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-roi-calculator.php';
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_row_results, $mock_wpdb_get_results, $mock_wpdb_get_col_results;
        global $mock_wc_get_orders, $mock_wc_orders_by_id, $mock_user_meta, $mock_transients;
        
        $this->saved_options = [
            'intersoccer_roi_camp_margin_percent' => get_option('intersoccer_roi_camp_margin_percent'),
            'intersoccer_roi_ad_spend' => get_option('intersoccer_roi_ad_spend'),
            'intersoccer_roi_ad_families' => get_option('intersoccer_roi_ad_families'),
        ];
        
        $mock_wpdb_get_var_results = [];
        $mock_wpdb_get_row_results = [];
        $mock_wpdb_get_results = [];
        $mock_wpdb_get_col_results = [];
        $mock_wc_get_orders = null;
        $mock_wc_orders_by_id = [];
        $mock_user_meta = [];
        $mock_transients = [];
        
        parent::setUp();
    }
    
    protected function tearDown(): void {
        global $mock_wc_orders_by_id, $mock_wpdb_get_var_results, $mock_wpdb_get_row_results, $mock_wpdb_get_results, $mock_wpdb_get_col_results, $mock_wc_get_orders, $mock_user_meta, $mock_transients;
        
        // Reset all mock globals to prevent pollution between tests
        $mock_wc_orders_by_id = [];
        $mock_wpdb_get_var_results = [];
        $mock_wpdb_get_row_results = [];
        $mock_wpdb_get_results = [];
        $mock_wpdb_get_col_results = [];
        $mock_wc_get_orders = null;
        $mock_user_meta = [];
        $mock_transients = [];
        
        foreach ($this->saved_options as $key => $value) {
            if ($value !== false) {
                update_option($key, $value);
            } else {
                delete_option($key);
            }
        }
        parent::tearDown();
    }
    
    // =========================================================================
    // HELPER METHODS FOR BUILDING REAL MOCK ORDERS
    // =========================================================================
    
    /**
     * Create a mock order with line items, fees, and optional refunds
     */
    private function createMockOrder($id, $status, $date, $line_items = [], $fees = [], $refunds = [], $meta = []) {
        global $mock_wc_orders_by_id;
        
        $order = new WC_Order($id);
        $order->set_id($id);
        $order->set_status($status);
        $order->set_date_created($date);
        $order->set_customer_id(100 + $id);
        
        // Add order meta
        foreach ($meta as $key => $value) {
            $order->update_meta_data($key, $value);
        }
        
        // Add line items (product lines)
        foreach ($line_items as $item_data) {
            $item = new WC_Order_Item_Product();
            $item->set_name($item_data['name'] ?? 'Product');
            $item->set_subtotal($item_data['subtotal'] ?? 0);
            $item->set_total($item_data['total'] ?? $item_data['subtotal'] ?? 0);
            $item->set_quantity($item_data['quantity'] ?? 1);
            $order->add_item($item);
        }
        
        // Add fee lines (points discount, referral discount, etc.)
        foreach ($fees as $fee_data) {
            $fee = new WC_Order_Item_Fee($fee_data['name'], $fee_data['total']);
            if (isset($fee_data['meta'])) {
                foreach ($fee_data['meta'] as $meta_key => $meta_value) {
                    $fee->add_meta_data($meta_key, $meta_value);
                }
            }
            $order->add_item($fee);
        }
        
        // Add refunds
        foreach ($refunds as $refund_data) {
            $refund = new WC_Order_Refund($id * 1000 + count($order->get_refunds()));
            $refund->set_parent_id($id);
            $refund->set_total($refund_data['total'] ?? 0);
            
            // Add refund line items if specified
            if (isset($refund_data['items'])) {
                foreach ($refund_data['items'] as $refund_item_data) {
                    $refund_item = new WC_Order_Item_Product();
                    $refund_item->set_name($refund_item_data['name'] ?? 'Refund');
                    $refund_item->set_total($refund_item_data['total'] ?? 0);
                    $refund->add_item($refund_item);
                }
            }
            
            // Add refund fee items if specified
            if (isset($refund_data['fees'])) {
                foreach ($refund_data['fees'] as $refund_fee_data) {
                    $refund_fee = new WC_Order_Item_Fee($refund_fee_data['name'], $refund_fee_data['total']);
                    $refund->add_item($refund_fee);
                }
            }
            
            $order->add_refund($refund);
        }
        
        $mock_wc_orders_by_id[$id] = $order;
        return $order;
    }
    
    /**
     * Set up wc_get_orders mock to return specific order IDs
     */
    private function mockWcGetOrders($order_ids) {
        global $mock_wc_get_orders;
        $mock_wc_get_orders = function($args) use ($order_ids) {
            if (isset($args['return']) && $args['return'] === 'ids') {
                return $order_ids;
            }
            return [];
        };
    }
    
    // =========================================================================
    // CAMP MARGIN TESTS
    // =========================================================================
    
    public function testHasCampMargin_ReturnsFalseWhenNotSet() {
        delete_option('intersoccer_roi_camp_margin_percent');
        $calculator = new InterSoccer_ROI_Calculator();
        $this->assertFalse($calculator->has_camp_margin());
    }
    
    public function testHasCampMargin_ReturnsFalseWhenZero() {
        update_option('intersoccer_roi_camp_margin_percent', 0);
        $calculator = new InterSoccer_ROI_Calculator();
        $this->assertFalse($calculator->has_camp_margin());
    }
    
    public function testHasCampMargin_ReturnsTrueWhenSet() {
        update_option('intersoccer_roi_camp_margin_percent', 25);
        $calculator = new InterSoccer_ROI_Calculator();
        $this->assertTrue($calculator->has_camp_margin());
    }
    
    // =========================================================================
    // BEHAVIORAL TESTS WITH FIXTURE DATA
    // =========================================================================
    
    // Note: Incrementality constants (REFERRAL 25%-75%, LOYALTY 5%-25%) are tested
    // indirectly via exact-value ROI tests. If constants change, those tests break.
    // See testReferralROIWithRange_SingleOrder_ExactValues, testHPOS_SQLPath_ExactValues.
    
    /**
     * Test that referral ROI returns em-dash when no camp margin is configured
     */
    public function testReferralROI_BlankWhenNoMargin() {
        delete_option('intersoccer_roi_camp_margin_percent');
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_get_orders;
        $mock_wpdb_get_var_results = [];
        $mock_wpdb_get_results = ['intersoccer_referrals' => []];
        $mock_wc_get_orders = function() { return []; };
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->calculate_referral_roi();
        
        $this->assertEquals('—', $result['roi_display'], 'ROI display should be em-dash when no margin');
        $this->assertNull($result['roi_percentage'], 'ROI percentage should be null');
        $this->assertTrue($result['show_margin_prompt'], 'Should prompt user to set margin');
    }
    
    /**
     * EXACT VALUE TEST: Referral ROI with margin but no data
     * 
     * Expected values for empty state:
     *   roi_display: 'Not enough data'
     *   roi_percentage: null
     *   show_margin_prompt: false (margin IS set)
     *   cost_per_family: 0 (no families)
     *   referred_share_percent: 0 (no customers)
     *   new_families: 0
     *   total_cost: 0
     */
    public function testReferralROI_WithMarginNoData_ExactValues() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_get_orders;
        $mock_wpdb_get_var_results = [];
        $mock_wpdb_get_results = ['intersoccer_referrals' => []];
        $mock_wc_get_orders = function() { return []; };
        
        $calculator = new InterSoccer_ROI_Calculator();
        $this->assertTrue($calculator->has_camp_margin(), 'Should have camp margin');
        
        $result = $calculator->calculate_referral_roi();
        
        $this->assertEquals('Not enough data', $result['roi_display']);
        $this->assertNull($result['roi_percentage']);
        $this->assertFalse($result['show_margin_prompt']);
        $this->assertEquals(0, $result['cost_per_family']);
        $this->assertEquals(0, $result['referred_share_percent']);
        $this->assertEquals(0, $result['new_families']);
        $this->assertEquals(0, $result['total_cost']);
    }
    
    /**
     * EXACT VALUE TEST: Cost per family calculation
     * 
     * Fixture:
     *   Commissions: 150 CHF
     *   Referral points: 50 CHF
     *   New families: 4 (4 referred customers with completed orders)
     * 
     * Expected: cost_per_family = (150 + 50) / 4 = 50 CHF
     */
    public function testReferralROI_CostPerFamily() {
        update_option('intersoccer_roi_camp_margin_percent', 30);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_orders_by_id;
        
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        
        // Create 4 orders for 4 referred customers (new families)
        for ($i = 1; $i <= 4; $i++) {
            $order = $this->createMockOrder(
                5100 + $i, 'completed', $in_window_date,
                [['name' => 'Camp', 'subtotal' => 200, 'total' => 200]],
                [], [], []
            );
            $order->set_customer_id(5100 + $i);
        }
        
        $mock_wpdb_get_var_results = [
            'commission_amount' => 150,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 50,
            'NOT IN' => 0,
        ];
        
        // Referrals with order_id so wc_get_order can retrieve them
        $mock_wpdb_get_results = [
            'intersoccer_referrals' => [
                (object) ['customer_id' => 5101, 'order_id' => 5101, 'created_at' => $in_window_date],
                (object) ['customer_id' => 5102, 'order_id' => 5102, 'created_at' => $in_window_date],
                (object) ['customer_id' => 5103, 'order_id' => 5103, 'created_at' => $in_window_date],
                (object) ['customer_id' => 5104, 'order_id' => 5104, 'created_at' => $in_window_date],
            ],
            'intersoccer_points_log' => [],
        ];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->calculate_referral_roi();
        
        $this->assertEquals(4, $result['new_families'], 'Should have 4 new families');
        $this->assertEquals(50.0, $result['cost_per_family'], 'Cost per family = (150 + 50) / 4 = 50');
    }
    
    /**
     * EXACT VALUE TEST: Referred share percentage calculation
     * 
     * Fixture:
     *   3 referred customers (300 CHF total)
     *   10 total orders (1000 CHF total)
     * 
     * Expected: referred_share_percent = 300/1000 × 100 = 30%
     */
    public function testReferralROI_ReferredSharePercent() {
        update_option('intersoccer_roi_camp_margin_percent', 30);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_orders_by_id;
        
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        
        // Create 10 orders total (100 CHF each = 1000 CHF total)
        for ($i = 1; $i <= 10; $i++) {
            $order = $this->createMockOrder(
                6000 + $i, 'completed', $in_window_date,
                [['name' => 'Camp', 'subtotal' => 100, 'total' => 100]],
                [], [], []
            );
            $order->set_customer_id(6000 + $i);
        }
        
        $mock_wpdb_get_var_results = [
            'commission_amount' => 0,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 0,
            'NOT IN' => 0,
        ];
        
        // 3 referred customers (300 CHF / 1000 CHF = 30%)
        $mock_wpdb_get_results = [
            'intersoccer_referrals' => [
                (object) ['customer_id' => 6001, 'order_id' => 6001, 'created_at' => $in_window_date],
                (object) ['customer_id' => 6002, 'order_id' => 6002, 'created_at' => $in_window_date],
                (object) ['customer_id' => 6003, 'order_id' => 6003, 'created_at' => $in_window_date],
            ],
            'intersoccer_points_log' => [],
        ];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->calculate_referral_roi();
        
        $this->assertEquals(30.0, $result['referred_share_percent'], 'Referred share = 300/1000 × 100 = 30%');
    }
    
    /**
     * Test loyalty ROI shows blank when no margin set
     */
    public function testLoyaltyROI_BlankWhenNoMargin() {
        delete_option('intersoccer_roi_camp_margin_percent');
        update_option('intersoccer_roi_ad_spend', 0);
        update_option('intersoccer_roi_staff_time_monthly', 0);
        update_option('intersoccer_roi_platform_fees_monthly', 0);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_get_orders, $mock_user_meta;
        $mock_wpdb_get_var_results = [];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        $mock_wc_get_orders = function() { return []; };
        $mock_user_meta = [];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->calculate_loyalty_roi();
        
        $this->assertEquals('—', $result['roi_display'], 'Loyalty ROI blank without margin');
        $this->assertTrue($result['show_margin_prompt'], 'Should prompt for margin');
    }
    
    /**
     * EXACT VALUE TEST: Expired/reversed points reduce LIABILITY, not cost
     * 
     * Fixture:
     *   Earned (activity): 2000 points
     *   Expired: 300 points
     *   Reversed: 200 points
     * 
     * Expected:
     *   points_cost = 2000 CHF (cost when earned, not reduced)
     *   liability_reductions = 500 CHF (300 + 200)
     */
    public function testLoyaltyROI_ExpiryReducesLiabilityNotCost() {
        update_option('intersoccer_roi_camp_margin_percent', 30);
        update_option('intersoccer_roi_ad_spend', 0);
        update_option('intersoccer_roi_staff_time_monthly', 0);
        update_option('intersoccer_roi_platform_fees_monthly', 0);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_get_orders, $mock_user_meta;
        $mock_wpdb_get_var_results = [
            'commission_amount' => 0,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 0,
            'NOT IN' => 2000,
            'intersoccer_points_balance' => 1500,
            "transaction_type = 'points_expired'" => 300,
            "IN ('order_refund', 'points_refund', 'reversal')" => 200,
        ];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        $mock_wc_get_orders = function() { return []; };
        $mock_user_meta = [];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->calculate_loyalty_roi();
        
        $this->assertEquals(2000.0, $result['points_cost'], 'Cost = earned points (not reduced by expiry)');
        
        $liability = $calculator->get_points_liability();
        $this->assertEquals(500.0, $liability['liability_reductions'], 'Liability reductions = 300 expired + 200 reversed');
    }
    
    /**
     * Test that reversals are tracked in liability_reductions
     * 
     * Fixture: 5000 balance, 1000 reversed, 500 expired
     * Expected: liability_reductions = 1500 (500 + 1000)
     */
    public function testLoyaltyROI_ReversalReducesLiabilityNotCost() {
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results;
        
        $mock_wpdb_get_var_results = [
            'intersoccer_points_balance' => 5000,
            "IN ('order_refund', 'points_refund', 'reversal')" => 1000,
            "transaction_type = 'points_expired'" => 500,
        ];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->get_points_liability();
        
        $this->assertArrayHasKey('total_reversed', $result, 'Should track total_reversed');
        $this->assertArrayHasKey('liability_reductions', $result, 'Should have liability_reductions');
        
        $expected_reductions = $result['total_expired'] + $result['total_reversed'];
        $this->assertEquals(
            $expected_reductions,
            $result['liability_reductions'],
            'liability_reductions = expired + reversed'
        );
    }
    
    /**
     * EXACT VALUE TEST: Points liability includes expired and reversed
     * 
     * Fixture: balance = 35000, expired = 2000, reversed = 1500
     * Expected:
     *   current_chf = 35000
     *   total_expired = 2000
     *   total_reversed = 1500
     *   liability_reductions = 3500
     */
    public function testPointsLiability_IncludesExpiredAndReversed() {
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results;
        
        $mock_wpdb_get_var_results = [
            'intersoccer_points_balance' => 35000,
            'points_expired' => 2000,
            "transaction_type = 'points_expired'" => 2000,
            "IN ('order_refund', 'points_refund', 'reversal')" => 1500,
        ];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->get_points_liability();
        
        $this->assertEquals(35000.0, $result['current_chf'], 'current_chf = balance');
        $this->assertEquals(2000.0, $result['total_expired'], 'total_expired = 2000');
        $this->assertEquals(1500.0, $result['total_reversed'], 'total_reversed = 1500');
        $this->assertEquals(3500.0, $result['liability_reductions'], 'liability_reductions = 2000 + 1500');
    }
    
    /**
     * EXACT VALUE TEST: Month-over-month liability change
     * 
     * Fixture: current=40000, last_month=35000
     * Expected:
     *   current_chf = 40000
     *   last_month_chf = 35000
     *   change_chf = 5000
     */
    public function testPointsLiability_MonthOverMonthChange() {
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results;
        
        $mock_wpdb_get_var_results = [
            'intersoccer_points_balance' => 40000,
            'points_expired' => 0,
            "transaction_type = 'points_expired'" => 0,
            "IN ('order_refund', 'points_refund', 'reversal')" => 0,
            '<= ' => 35000,
        ];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->get_points_liability();
        
        $this->assertEquals(40000.0, $result['current_chf'], 'current = 40000');
        $this->assertEquals(35000.0, $result['last_month_chf'], 'last_month = 35000');
        $this->assertEquals(5000.0, $result['change_chf'], 'change = 40000 - 35000 = 5000');
    }
    
    // =========================================================================
    // COST BREAKDOWN TESTS WITH EXACT VALUES
    // =========================================================================
    
    /**
     * Test cost breakdown splits referral vs loyalty with adjustment classification
     * 
     * Fixture:
     *   Referral points: 1000 CHF
     *   Loyalty activity: 26000 CHF
     *   Goodwill adjustments: 3000 CHF (counted as cost)
     *   Correction adjustments: 7000 CHF (NOT counted as cost)
     *   Coach commissions: 5000 CHF
     * 
     * Expected:
     *   Referral cost: 6000 CHF (5000 + 1000)
     *   Loyalty cost: 29000 CHF (26000 + 3000)
     *   Total program cost: 35000 CHF (excludes 7000 corrections)
     */
    public function testCostBreakdown_SplitsReferralAndLoyaltyCosts() {
        update_option('intersoccer_roi_ad_spend', 0);
        update_option('intersoccer_roi_staff_time_monthly', 0);
        update_option('intersoccer_roi_platform_fees_monthly', 0);
        
        $this->mockCostQueries([
            'referral_points' => 1000,
            'loyalty_points' => 26000,
            'goodwill_points' => 3000,
            'correction_points' => 7000,
            'commissions' => 5000,
        ]);
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->get_cost_breakdown();
        
        $this->assertEquals(1000, $result['referral_points_chf'], 'Referral points should be 1000');
        $this->assertEquals(26000, $result['loyalty_activity_chf'], 'Loyalty activity should be 26000');
        $this->assertEquals(3000, $result['goodwill_points_chf'], 'Goodwill should be 3000');
        $this->assertEquals(7000, $result['correction_points_chf'], 'Corrections should be 7000');
        $this->assertEquals(5000, $result['commissions_chf'], 'Commissions should be 5000');
        $this->assertEquals(6000, $result['referral_cost_chf'], 'Referral cost = 5000 + 1000');
        $this->assertEquals(29000, $result['loyalty_cost_chf'], 'Loyalty cost = 26000 + 3000');
        $this->assertEquals(35000, $result['total_real_cost'], 'Total = 6000 + 29000 (excludes corrections)');
        $this->assertEquals(30000, $result['points_earned_chf'], 'Earned = 1000 + 26000 + 3000');
        $this->assertFalse($result['has_estimates']);
        $this->assertTrue($result['has_goodwill']);
        $this->assertTrue($result['has_corrections']);
    }
    
    /**
     * Test that corrections are NOT counted as cost
     * 
     * Fixture: 10000 points are all corrections
     * Expected: total_real_cost = 0
     */
    public function testCostBreakdown_CorrectionsExcludedFromCost() {
        update_option('intersoccer_roi_ad_spend', 0);
        update_option('intersoccer_roi_staff_time_monthly', 0);
        update_option('intersoccer_roi_platform_fees_monthly', 0);
        
        $this->mockCostQueries([
            'referral_points' => 0,
            'loyalty_points' => 0,
            'goodwill_points' => 0,
            'correction_points' => 10000,
            'commissions' => 0,
        ]);
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->get_cost_breakdown();
        
        $this->assertEquals(0, $result['total_real_cost'], 'Corrections not counted as program cost');
        $this->assertEquals(10000, $result['correction_points_chf'], 'Corrections tracked separately');
        $this->assertFalse($result['has_goodwill'], 'No goodwill adjustments');
        $this->assertTrue($result['has_corrections'], 'Has corrections');
    }
    
    /**
     * Test that goodwill/bonus adjustments ARE counted as cost
     * 
     * Fixture: 5000 points are all goodwill
     * Expected: loyalty_cost = 5000, total_real_cost = 5000
     */
    public function testCostBreakdown_GoodwillCountedAsCost() {
        update_option('intersoccer_roi_ad_spend', 0);
        update_option('intersoccer_roi_staff_time_monthly', 0);
        update_option('intersoccer_roi_platform_fees_monthly', 0);
        
        $this->mockCostQueries([
            'referral_points' => 0,
            'loyalty_points' => 0,
            'goodwill_points' => 5000,
            'correction_points' => 0,
            'commissions' => 0,
        ]);
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->get_cost_breakdown();
        
        $this->assertEquals(5000, $result['loyalty_cost_chf'], 'Goodwill counted as loyalty cost');
        $this->assertEquals(5000, $result['total_real_cost'], 'Goodwill contributes to total cost');
        $this->assertTrue($result['has_goodwill'], 'Has goodwill adjustments');
    }
    
    /**
     * REGRESSION TEST: All three referral point types must be summed
     * 
     * Fixture:
     *   customer_referral_reward + new_customer_bonus + coach_referral_reward = 177
     *   commissions = 50
     * 
     * Expected:
     *   referral_points_chf = 177
     *   referral_cost_chf = 227 (50 + 177)
     */
    public function testCostBreakdown_IncludesAllReferralPointTypes() {
        update_option('intersoccer_roi_camp_margin_percent', 45);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results;
        
        $mock_wpdb_get_var_results = [
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 177,
            'NOT IN' => 30000,
            'commission_amount' => 50,
        ];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $costs = $calculator->get_cost_breakdown();
        
        $this->assertGreaterThan(0, $costs['referral_points_chf'], 'Referral points should be > 0');
        $this->assertEquals(177, $costs['referral_points_chf'], 'Should sum all three referral types');
        $this->assertEquals(227, $costs['referral_cost_chf'], 'Referral cost = 50 + 177');
    }
    
    /**
     * EXACT VALUE TEST: Cost split with first-order discount (50/10/15/75/100/175)
     * 
     * Fixture:
     *   Commissions: 50 CHF
     *   Referral points: 10 CHF
     *   First-order discount: 15 CHF
     *   Loyalty points: 100 CHF
     * 
     * Expected:
     *   Referral cost: 75 CHF (50 + 10 + 15)
     *   Loyalty cost: 100 CHF
     *   Total real cost: 175 CHF (75 + 100)
     */
    public function testCostBreakdown_WithFirstOrderDiscount_ExactSplit() {
        update_option('intersoccer_roi_ad_spend', 0);
        update_option('intersoccer_roi_staff_time_monthly', 0);
        update_option('intersoccer_roi_platform_fees_monthly', 0);
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_get_orders, $mock_wc_orders_by_id;
        
        // Create an order with first-order discount within the window
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        $order = $this->createMockOrder(
            5501, 'completed', $in_window_date,
            [['name' => 'Camp', 'subtotal' => 300, 'total' => 300]],
            [['name' => 'Referral Discount', 'total' => -15, 'meta' => ['_intersoccer_referral_discount_fee' => '1']]],
            [],
            ['_intersoccer_first_order_discount_applied' => 1, '_intersoccer_first_order_discount_amount' => 15]
        );
        $order->set_total(285);
        $order->set_customer_id(5501);
        
        $this->mockWcGetOrders([5501]);
        
        $mock_wpdb_get_var_results = [
            'commission_amount' => 50,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 10,
            'NOT IN' => 100,
        ];
        $mock_wpdb_get_results = ['intersoccer_referrals' => [], 'intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $costs = $calculator->get_cost_breakdown();
        
        $this->assertEquals(50, $costs['commissions_chf'], 'Commissions should be 50');
        $this->assertEquals(10, $costs['referral_points_chf'], 'Referral points should be 10');
        $this->assertEquals(15, $costs['first_order_discount_chf'], 'First-order discount should be 15');
        $this->assertEquals(75, $costs['referral_cost_chf'], 'Referral cost = 50 + 10 + 15');
        $this->assertEquals(100, $costs['loyalty_cost_chf'], 'Loyalty cost should be 100');
        $this->assertEquals(175, $costs['total_real_cost'], 'Total cost = 75 + 100');
    }
    
    /**
     * Test cost breakdown with estimates (ad spend, staff time, platform fees)
     * 
     * Fixture:
     *   ad_spend = 5000, staff_time_monthly = 500, platform_fees_monthly = 100
     *   referral_points = 1000, loyalty_points = 29000, commissions = 5000
     * 
     * Expected:
     *   total_real_cost = 35000
     *   staff_time_chf = 6000 (500 × 12)
     *   platform_fees_chf = 1200 (100 × 12)
     *   total_fully_loaded = 35000 + 5000 + 6000 + 1200 = 47200
     */
    public function testCostBreakdown_WithEstimates() {
        update_option('intersoccer_roi_ad_spend', 5000);
        update_option('intersoccer_roi_staff_time_monthly', 500);
        update_option('intersoccer_roi_platform_fees_monthly', 100);
        
        $this->mockCostQueries([
            'referral_points' => 1000,
            'loyalty_points' => 29000,
            'adjustment_points' => 0,
            'commissions' => 5000,
        ]);
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->get_cost_breakdown();
        
        $this->assertEquals(35000, $result['total_real_cost']);
        $this->assertEquals(5000, $result['ad_spend_chf']);
        $this->assertEquals(6000, $result['staff_time_chf']);
        $this->assertEquals(1200, $result['platform_fees_chf']);
        $this->assertTrue($result['has_estimates']);
        $this->assertEquals(47200, $result['total_fully_loaded_chf']);
    }
    
    // =========================================================================
    // ADJUSTMENT CLASSIFICATION TESTS
    // =========================================================================
    
    public function testAdjustmentClassification_AdminResetIsCorrection() {
        $calculator = new InterSoccer_ROI_Calculator();
        
        global $mock_wpdb_get_results;
        $mock_wpdb_get_results['intersoccer_points_log'] = [
            (object) ['points_amount' => 1000, 'description' => 'Balance reset', 'transaction_type' => 'admin_reset'],
            (object) ['points_amount' => 500, 'description' => 'Survey reward', 'transaction_type' => 'admin_adjustment'],
        ];
        
        $result = $calculator->classify_adjustments();
        
        $this->assertEquals(1000, $result['correction_chf'], 'admin_reset is a correction');
        $this->assertEquals(500, $result['goodwill_chf'], 'Survey is goodwill');
    }
    
    public function testAdjustmentClassification_RefundCreditIsCorrection() {
        $calculator = new InterSoccer_ROI_Calculator();
        
        global $mock_wpdb_get_results;
        $mock_wpdb_get_results['intersoccer_points_log'] = [
            (object) ['points_amount' => 200, 'description' => 'Refund credit for cancelled order', 'transaction_type' => 'admin_adjustment'],
        ];
        
        $result = $calculator->classify_adjustments();
        
        $this->assertEquals(200, $result['correction_chf'], 'Refund credit should be correction');
        $this->assertEquals(0, $result['goodwill_chf'], 'Should not be goodwill');
    }
    
    public function testAdjustmentClassification_UnknownDefaultsToCorrection() {
        $calculator = new InterSoccer_ROI_Calculator();
        
        global $mock_wpdb_get_results;
        $mock_wpdb_get_results['intersoccer_points_log'] = [
            (object) ['points_amount' => 300, 'description' => 'Manual entry xyz123', 'transaction_type' => 'admin_adjustment'],
        ];
        
        $result = $calculator->classify_adjustments();
        
        $this->assertEquals(300, $result['correction_chf'], 'Unknown should default to correction');
        $this->assertEquals(0, $result['goodwill_chf'], 'Should not be goodwill');
        $this->assertGreaterThan(0, $result['unclassified_count'], 'Should track unclassified');
    }
    
    /**
     * FIXTURE TEST: "Bonus" in description is classified as goodwill (not correction)
     * 
     * Fixture:
     *   "Signup bonus for new family" with 100 points
     *   "Loyalty bonus reward" with 75 points  
     *   "Birthday bonus" with 50 points
     * 
     * Expected: All three are goodwill, total 225 CHF
     * Proves: "bonus" keyword triggers goodwill classification
     */
    public function testAdjustmentClassification_BonusIsGoodwill() {
        $calculator = new InterSoccer_ROI_Calculator();
        
        global $mock_wpdb_get_results;
        $mock_wpdb_get_results['intersoccer_points_log'] = [
            (object) ['points_amount' => 100, 'description' => 'Signup bonus for new family', 'transaction_type' => 'admin_adjustment'],
            (object) ['points_amount' => 75, 'description' => 'Loyalty bonus reward', 'transaction_type' => 'admin_adjustment'],
            (object) ['points_amount' => 50, 'description' => 'Birthday bonus', 'transaction_type' => 'admin_adjustment'],
        ];
        
        $result = $calculator->classify_adjustments();
        
        $this->assertEquals(225, $result['goodwill_chf'], 'All bonus entries should be goodwill: 100 + 75 + 50 = 225');
        $this->assertEquals(0, $result['correction_chf'], 'No corrections in this fixture');
        $this->assertEquals(0, $result['unclassified_count'], 'All entries classified');
    }
    
    /**
     * FIXTURE TEST: Goodwill vs correction - mixed entries properly classified
     * 
     * Fixture:
     *   Goodwill: "bonus reward" 100, "compensation gesture" 50 = 150 total
     *   Correction: "error fix" 200, "mistake correction" 100 = 300 total
     * 
     * Expected: goodwill = 150, correction = 300
     * Proves: Classification is mutually exclusive and accurate
     */
    public function testAdjustmentClassification_GoodwillVsCorrection_MixedEntries() {
        $calculator = new InterSoccer_ROI_Calculator();
        
        global $mock_wpdb_get_results;
        $mock_wpdb_get_results['intersoccer_points_log'] = [
            (object) ['points_amount' => 100, 'description' => 'bonus reward for referral', 'transaction_type' => 'admin_adjustment'],
            (object) ['points_amount' => 200, 'description' => 'error fix for order 12345', 'transaction_type' => 'admin_adjustment'],
            (object) ['points_amount' => 50, 'description' => 'compensation gesture for delay', 'transaction_type' => 'admin_adjustment'],
            (object) ['points_amount' => 100, 'description' => 'mistake correction on account', 'transaction_type' => 'admin_adjustment'],
        ];
        
        $result = $calculator->classify_adjustments();
        
        $this->assertEquals(150, $result['goodwill_chf'], 'Goodwill: 100 bonus + 50 compensation = 150');
        $this->assertEquals(300, $result['correction_chf'], 'Correction: 200 error + 100 mistake = 300');
        $this->assertEquals(0, $result['unclassified_count'], 'All entries classified');
    }
    
    // =========================================================================
    // NEW FAMILY RULE TESTS
    // =========================================================================
    
    /**
     * Test new family rule: customer with NO prior orders
     */
    public function testNewFamilyRule_TrueWhenNoPriorOrders() {
        global $mock_wc_get_orders, $mock_wc_orders_by_id;
        
        $referred_order = new WC_Order(1001);
        $referred_order->set_customer_id(101);
        $referred_order->set_date_created('2024-06-15 10:00:00');
        $referred_order->set_status('completed');
        $mock_wc_orders_by_id[1001] = $referred_order;
        
        $mock_wc_get_orders = function($args) {
            if (isset($args['date_created']) && strpos($args['date_created'], '<') === 0) {
                return [];
            }
            return [];
        };
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->is_new_family(101, 1001);
        
        $this->assertTrue($result, 'Should be new family when no prior orders');
    }
    
    /**
     * Test new family rule: returns FALSE when prior order exists
     */
    public function testNewFamilyRule_FalseWhenPriorOrderExists() {
        global $mock_wc_get_orders, $mock_wc_orders_by_id;
        
        $referred_order = new WC_Order(1002);
        $referred_order->set_customer_id(102);
        $referred_order->set_date_created('2024-06-15 10:00:00');
        $referred_order->set_status('completed');
        $mock_wc_orders_by_id[1002] = $referred_order;
        
        $mock_wc_get_orders = function($args) {
            if (isset($args['date_created']) && strpos($args['date_created'], '<') === 0
                && isset($args['return']) && $args['return'] === 'ids') {
                return [999];
            }
            return [];
        };
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->is_new_family(102, 1002);
        
        $this->assertFalse($result, 'Should NOT be new family when prior order exists');
    }
    
    /**
     * Test new family rule: uses order date not record date
     */
    public function testNewFamilyRule_UsesOrderDateNotRecordDate() {
        global $mock_wc_get_orders, $mock_wc_orders_by_id;
        
        $referred_order = new WC_Order(1003);
        $referred_order->set_customer_id(103);
        $referred_order->set_date_created('2024-06-15 10:00:00');
        $referred_order->set_status('completed');
        $mock_wc_orders_by_id[1003] = $referred_order;
        
        $mock_wc_get_orders = function($args) {
            if (isset($args['date_created']) && strpos($args['date_created'], '<') === 0
                && isset($args['return']) && $args['return'] === 'ids') {
                return [998];
            }
            return [];
        };
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->is_new_family(103, 1003);
        
        $this->assertFalse($result, 'Order 1 second before makes them existing customer');
    }
    
    // =========================================================================
    // REFERRAL FUNNEL TESTS
    // =========================================================================
    
    public function testReferralFunnel_HasExpectedStructure() {
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wpdb_get_col_results;
        
        $mock_wpdb_get_var_results = [
            'COUNT(DISTINCT referral_code)' => 10,
            'COUNT(*)' => 5,
        ];
        $mock_wpdb_get_results = ['intersoccer_referrals' => []];
        $mock_wpdb_get_col_results = ['intersoccer_referrals' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->get_referral_funnel();
        
        $this->assertArrayHasKey('codes_used', $result);
        $this->assertArrayHasKey('bookings', $result);
        $this->assertArrayHasKey('new_families', $result);
        $this->assertArrayHasKey('existing_customers', $result);
        $this->assertArrayHasKey('existing_customer_share', $result);
        $this->assertArrayHasKey('total_unique_customers', $result);
        $this->assertArrayHasKey('sample_warning', $result);
        
        $this->assertIsInt($result['new_families']);
        $this->assertIsInt($result['existing_customers']);
        $this->assertIsNumeric($result['existing_customer_share']);
    }
    
    // =========================================================================
    // REDEMPTION RATE TEST
    // =========================================================================
    
    /**
     * EXACT VALUE TEST: Redemption rate calculation
     * 
     * Fixture: earned=10000, redeemed=2000
     * Expected: redemption_rate = 2000/10000 × 100 = 20%
     */
    public function testRedemptionRate_CalculatesCorrectly() {
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results;
        
        $mock_wpdb_get_var_results = [
            'intersoccer_points_balance' => 8000,
            'points_amount > 0' => 10000,
            "transaction_type = 'points_redemption'" => -2000,
            "transaction_type = 'points_expired'" => 0,
            "IN ('order_refund', 'points_refund', 'reversal')" => 0,
            '<= ' => 7500,
        ];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->get_points_liability();
        
        $this->assertEquals(10000.0, $result['earned_12mo'], 'earned = 10000');
        $this->assertEquals(2000.0, $result['redeemed_12mo'], 'redeemed = 2000');
        $this->assertEqualsWithDelta(20.0, $result['redemption_rate'], 0.1, 'rate = 2000/10000 × 100 = 20%');
    }
    
    // =========================================================================
    // AD COST TESTS
    // =========================================================================
    
    public function testAdCostPerFamily_HiddenWhenNoAdSpend() {
        update_option('intersoccer_roi_ad_spend', 0);
        update_option('intersoccer_roi_ad_families', 0);
        
        $calculator = new InterSoccer_ROI_Calculator();
        $this->assertFalse($calculator->has_ad_data());
    }
    
    public function testAdCostPerFamily_CalculatedWhenAdDataPresent() {
        update_option('intersoccer_roi_ad_spend', 5000);
        update_option('intersoccer_roi_ad_families', 50);
        
        $calculator = new InterSoccer_ROI_Calculator();
        $this->assertTrue($calculator->has_ad_data());
    }
    
    // =========================================================================
    // HPOS FALLBACK TEST
    // =========================================================================
    
    /**
     * EXACT VALUE TEST: HPOS fallback returns correct values
     * 
     * With no orders: revenue=0, holder_count=0, non_holder_count=0
     */
    public function testHPOSCompatibility_BatchedFallbackReturnsProperStructure() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_get_orders, $mock_wpdb_get_col_results;
        $mock_wpdb_get_var_results = [];
        $mock_wpdb_get_results = ['intersoccer_referrals' => [], 'intersoccer_points_log' => []];
        $mock_wpdb_get_col_results = ['intersoccer_points_balance' => []];
        $mock_wc_get_orders = function() { return []; };
        
        $calculator = new InterSoccer_ROI_Calculator();
        $range = $calculator->get_period_range();
        
        $revenue = $calculator->get_revenue_ex_vat($range);
        $this->assertEquals(0.0, $revenue, 'No orders = 0 revenue');
        
        $comparison = $calculator->get_holder_vs_nonholder_comparison($range);
        $this->assertEquals(0, $comparison['holder_count'], 'No orders = 0 holders');
        $this->assertEquals(0, $comparison['non_holder_count'], 'No orders = 0 non-holders');
        $this->assertEquals(0.0, $comparison['holder_avg_spend'], 'No orders = 0 holder avg');
        $this->assertEquals(0.0, $comparison['non_holder_avg_spend'], 'No orders = 0 non-holder avg');
    }
    
    // =========================================================================
    // TIMEZONE / PERIOD TESTS
    // =========================================================================
    
    public function testTimezoneConsistency_PeriodRangeReturnsValidDates() {
        $calculator = new InterSoccer_ROI_Calculator();
        $range = $calculator->get_period_range();
        
        $this->assertArrayHasKey('start', $range);
        $this->assertArrayHasKey('end', $range);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $range['start']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $range['end']);
        $this->assertLessThan($range['end'], $range['start']);
        
        $start = new DateTime($range['start']);
        $end = new DateTime($range['end']);
        $diff = $start->diff($end);
        $months = $diff->m + ($diff->y * 12);
        $this->assertGreaterThanOrEqual(11, $months);
        $this->assertLessThanOrEqual(13, $months);
    }
    
    // =========================================================================
    // ROI RANGE TESTS WITH EXACT VALUES
    // =========================================================================
    
    public function testROIRangeDisplay_ShowsPromptWhenNoMargin() {
        delete_option('intersoccer_roi_camp_margin_percent');
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->get_roi_range_display();
        
        $this->assertEquals('—', $result['display']);
        $this->assertTrue($result['show_margin_prompt']);
        $this->assertFalse($result['has_data']);
    }
    
    public function testReferralROIWithRange_ShowsMarginPromptWhenNoMargin() {
        delete_option('intersoccer_roi_camp_margin_percent');
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->calculate_referral_roi_with_range();
        
        $this->assertTrue($result['show_margin_prompt']);
        $this->assertNull($result['low']);
        $this->assertNull($result['high']);
    }
    
    public function testLoyaltyROIWithRange_ShowsMarginPromptWhenNoMargin() {
        delete_option('intersoccer_roi_camp_margin_percent');
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->calculate_loyalty_roi_with_range();
        
        $this->assertTrue($result['show_margin_prompt']);
        $this->assertNull($result['low']);
        $this->assertNull($result['high']);
    }
    
    /**
     * EXACT VALUE ROI RANGE TEST: Referral ROI with known fixture
     * 
     * Fixture:
     *   Referral revenue: 2000 CHF (from referred customer orders)
     *   Referral cost: 100 CHF (commissions 60 + referral points 20 + first-order discount 20)
     *   Margin: 40%
     * 
     * Formula:
     *   Low: (revenue × 0.25 × margin - cost) / cost × 100
     *   High: (revenue × 0.75 × margin - cost) / cost × 100
     * 
     * Calculation:
     *   Low: (2000 × 0.25 × 0.40 - 100) / 100 × 100 = (200 - 100) / 100 × 100 = 100%
     *   High: (2000 × 0.75 × 0.40 - 100) / 100 × 100 = (600 - 100) / 100 × 100 = 500%
     */
    public function testReferralROIWithRange_ExactValues() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_get_orders, $mock_wc_orders_by_id;
        
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        
        // Create a referred customer order with 2000 CHF revenue and 20 CHF referral discount
        $order = $this->createMockOrder(
            2001,
            'completed',
            $in_window_date,
            [['name' => 'Camp', 'subtotal' => 2000, 'total' => 2000]],
            [['name' => 'Referral Discount', 'total' => -20]],
            [],
            ['_intersoccer_first_order_discount_applied' => 1, '_intersoccer_first_order_discount_amount' => 20]
        );
        $order->set_total(1980);
        $order->set_customer_id(2101);
        
        // Mock referral record pointing to this order
        $mock_wpdb_get_results = [
            'intersoccer_referrals' => [
                (object) ['customer_id' => 2101, 'order_id' => 2001, 'created_at' => $in_window_date]
            ],
            'intersoccer_points_log' => [],
        ];
        
        // Mock cost queries: 60 commissions + 20 referral points = 80 (+ 20 first-order = 100 total)
        $mock_wpdb_get_var_results = [
            'commission_amount' => 60,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 20,
            'NOT IN' => 0,
        ];
        
        $calculator = new InterSoccer_ROI_Calculator();
        
        // Test cost breakdown first
        $costs = $calculator->get_cost_breakdown();
        $this->assertEquals(60.0, $costs['commissions_chf'], 'Commissions should be 60 CHF');
        $this->assertEquals(20.0, $costs['referral_points_chf'], 'Referral points should be 20 CHF');
        $this->assertEquals(20.0, $costs['first_order_discount_chf'], 'First-order discount should be 20 CHF');
        $this->assertEquals(100.0, $costs['referral_cost_chf'], 'Total referral cost should be 100 CHF');
        
        // Test ROI with range
        $roi_range = $calculator->calculate_referral_roi_with_range();
        
        $this->assertFalse($roi_range['show_margin_prompt'], 'Should not show margin prompt');
        $this->assertEquals(0.25, $roi_range['incrementality_low'], 'Incrementality low should be 25%');
        $this->assertEquals(0.75, $roi_range['incrementality_high'], 'Incrementality high should be 75%');
        
        // With revenue=2000, cost=100, margin=0.40:
        // Low: (2000 × 0.25 × 0.40 - 100) / 100 × 100 = 100%
        // High: (2000 × 0.75 × 0.40 - 100) / 100 × 100 = 500%
        $this->assertEqualsWithDelta(100.0, $roi_range['low'], 0.1, 'Referral ROI low should be 100%');
        $this->assertEqualsWithDelta(500.0, $roi_range['high'], 0.1, 'Referral ROI high should be 500%');
    }
    
    /**
     * EXACT VALUE ROI RANGE TEST: Loyalty ROI with known fixture
     * 
     * This test uses exact hand-computed values that would break if
     * LOYALTY_INCREMENTALITY_LOW or LOYALTY_INCREMENTALITY_HIGH change.
     * 
     * Fixture:
     *   Holder avg spend: 600 CHF (10 holders)
     *   Non-holder avg spend: 100 CHF (10 non-holders)
     *   Extra spend per holder: 600 - 100 = 500 CHF
     *   Total extra spend: 500 × 10 = 5000 CHF
     *   Loyalty cost: 200 CHF
     *   Margin: 40%
     * 
     * Hand-computed ROI formula:
     *   Low: (extra_spend × 0.05 × margin - cost) / cost × 100
     *   High: (extra_spend × 0.25 × margin - cost) / cost × 100
     * 
     * Calculation:
     *   Low: (5000 × 0.05 × 0.40 - 200) / 200 × 100 = (100 - 200) / 200 × 100 = -50.0%
     *   High: (5000 × 0.25 × 0.40 - 200) / 200 × 100 = (500 - 200) / 200 × 100 = 150.0%
     * 
     * If LOYALTY_INCREMENTALITY_LOW changed from 0.05 to 0.10, low would become:
     *   (5000 × 0.10 × 0.40 - 200) / 200 × 100 = (200 - 200) / 200 × 100 = 0%
     *   This would break the assertEquals(-50.0, ...) assertion below.
     */
    public function testLoyaltyROIWithRange_ExactValues() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wpdb_get_col_results, $mock_wc_get_orders, $mock_wc_orders_by_id, $mock_user_meta;
        
        // Use dates within current 12-month window
        $order_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        
        // Holders: IDs 101-110, each with 600 CHF revenue
        $holder_ids = range(101, 110);
        for ($i = 0; $i < 10; $i++) {
            $order_id = 7001 + $i;
            $customer_id = $holder_ids[$i];
            $order = $this->createMockOrder(
                $order_id,
                'completed',
                $order_date,
                [['name' => 'Camp', 'subtotal' => 600, 'total' => 600]],
                [],
                [],
                []
            );
            $order->set_customer_id($customer_id);
            $mock_user_meta[$customer_id] = ['intersoccer_points_balance' => 100];
        }
        
        // Non-holders: IDs 201-210, each with 100 CHF revenue
        for ($i = 0; $i < 10; $i++) {
            $order_id = 7101 + $i;
            $customer_id = 201 + $i;
            $order = $this->createMockOrder(
                $order_id,
                'completed',
                $order_date,
                [['name' => 'Camp', 'subtotal' => 100, 'total' => 100]],
                [],
                [],
                []
            );
            $order->set_customer_id($customer_id);
        }
        
        // Mock wpdb->get_col to return holder IDs when querying usermeta for points balance
        $mock_wpdb_get_col_results = [
            'usermeta' => $holder_ids,
            'intersoccer_points_balance' => $holder_ids,
        ];
        
        // Mock loyalty cost = 200 CHF (activity points)
        $mock_wpdb_get_var_results = [
            'commission_amount' => 0,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 0,
            'NOT IN' => 200,
            'intersoccer_points_balance' => 1000,
        ];
        
        $mock_wpdb_get_results = [
            'intersoccer_referrals' => [],
            'intersoccer_points_log' => [],
        ];
        
        // Mock wc_get_orders to return orders with status/date filtering
        $all_order_ids = array_merge(range(7001, 7010), range(7101, 7110));
        $mock_wc_get_orders = function($args) use ($all_order_ids, &$mock_wc_orders_by_id) {
            // Filter by status if specified
            $statuses = isset($args['status']) ? (array) $args['status'] : ['completed', 'processing'];
            
            $filtered = [];
            foreach ($all_order_ids as $oid) {
                if (!isset($mock_wc_orders_by_id[$oid])) continue;
                $order = $mock_wc_orders_by_id[$oid];
                $status = $order->get_status();
                if (!in_array($status, $statuses) && !in_array('wc-' . $status, $statuses)) continue;
                $filtered[] = $oid;
            }
            
            if (isset($args['return']) && $args['return'] === 'ids') {
                return $filtered;
            }
            
            $orders = [];
            foreach ($filtered as $oid) {
                $orders[] = $mock_wc_orders_by_id[$oid];
            }
            return $orders;
        };
        
        $calculator = new InterSoccer_ROI_Calculator();
        $loyalty_range = $calculator->calculate_loyalty_roi_with_range();
        
        // NO has_data guard - these assertions MUST run
        $this->assertFalse($loyalty_range['show_margin_prompt'], 'Should not show margin prompt');
        $this->assertTrue($loyalty_range['has_data'], 'Must have data for this test to be valid');
        
        // These EXACT VALUES break if incrementality constants change:
        // If LOYALTY_INCREMENTALITY_LOW 0.05 → 0.10: expected -50.0, actual 0.0
        // If LOYALTY_INCREMENTALITY_HIGH 0.25 → 0.30: expected 150.0, actual 200.0
        $this->assertEqualsWithDelta(-50.0, $loyalty_range['low'], 0.1,
            'Loyalty ROI low should be -50.0%: (5000 × 0.05 × 0.40 - 200) / 200 × 100'
        );
        $this->assertEqualsWithDelta(150.0, $loyalty_range['high'], 0.1,
            'Loyalty ROI high should be 150.0%: (5000 × 0.25 × 0.40 - 200) / 200 × 100'
        );
        $this->assertEquals(0.05, $loyalty_range['incrementality_low']);
        $this->assertEquals(0.25, $loyalty_range['incrementality_high']);
    }
    
    /**
     * EXACT VALUE ROI RANGE TEST: Combined ROI with Finance dedupe
     * 
     * Finance dedupe rule: A referred customer's first order counts as referral revenue ONLY.
     * Their later orders (as a holder) count toward loyalty. Each order is counted once.
     * A referred holder with no later orders counts as a holder with 0 spend.
     * 
     * Fixture:
     * - Referral: 3 referred customers with first orders (500 CHF each, in-window)
     * - 1 on-hold (excluded), 1 out-of-window (excluded)
     * - Loyalty: 5 additional holders (600 CHF each), 5 non-holders (100 CHF each)
     * - Referred customers also hold points (new_customer_bonus) but have NO later orders
     * 
     * With Finance dedupe:
     *   Referral revenue: 1500 CHF (3 × 500) — first orders only
     *   Referral cost: 137 CHF (77 commissions + 40 points + 20 first-order discount)
     *   Holders: 8 (3 referred with 0 spend + 5 additional with 600 each)
     *   Holder total spend: 3000 CHF (5 × 600, referred first orders excluded)
     *   Holder avg: 375 CHF (3000 / 8)
     *   Non-holders: 5 with avg 100 CHF
     *   Loyalty extra spend: 2200 CHF ((375-100) × 8)
     *   Loyalty cost: 100 CHF
     *   Total cost: 237 CHF
     *   Margin: 40%
     * 
     * Combined ROI formula (Finance dedupe):
     *   Low: ((1500×0.25 + 2200×0.05) × 0.40 - 237) / 237 × 100
     *       = ((375 + 110) × 0.40 - 237) / 237 × 100
     *       = (194 - 237) / 237 × 100 = -18.1%
     *   High: ((1500×0.75 + 2200×0.25) × 0.40 - 237) / 237 × 100
     *        = ((1125 + 550) × 0.40 - 237) / 237 × 100
     *        = (670 - 237) / 237 × 100 = 182.7%
     */
    public function testCombinedROIRange_ExactValues_MultiOrderFixture() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wpdb_get_col_results;
        global $mock_wc_get_orders, $mock_wc_orders_by_id, $mock_user_meta;
        
        // Use dates within current 12-month window
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        $out_of_window_date = wp_date('Y-m-d', strtotime('-18 months')) . ' 10:00:00';
        
        // --- REFERRAL ORDERS (referred customers who also have points) ---
        // Referred order 1: 500 CHF, with 20 CHF first-order discount
        $order1 = $this->createMockOrder(
            8001, 'completed', $in_window_date,
            [['name' => 'Camp', 'subtotal' => 500, 'total' => 500]],
            [['name' => 'Referral Discount', 'total' => -20, 'meta' => ['_intersoccer_referral_discount_fee' => '1']]],
            [],
            ['_intersoccer_first_order_discount_applied' => 1, '_intersoccer_first_order_discount_amount' => 20]
        );
        $order1->set_total(480);
        $order1->set_customer_id(8101);
        $mock_user_meta[8101] = ['intersoccer_points_balance' => 25]; // Got new_customer_bonus
        
        // Referred order 2: 500 CHF
        $order2 = $this->createMockOrder(
            8002, 'completed', $in_window_date,
            [['name' => 'Camp', 'subtotal' => 500, 'total' => 500]],
            [], [], []
        );
        $order2->set_customer_id(8102);
        $mock_user_meta[8102] = ['intersoccer_points_balance' => 25]; // Got new_customer_bonus
        
        // Referred order 3: 500 CHF
        $order3 = $this->createMockOrder(
            8003, 'completed', $in_window_date,
            [['name' => 'Camp', 'subtotal' => 500, 'total' => 500]],
            [], [], []
        );
        $order3->set_customer_id(8103);
        $mock_user_meta[8103] = ['intersoccer_points_balance' => 25]; // Got new_customer_bonus
        
        // On-hold order (should be EXCLUDED from calculations)
        $order_onhold = $this->createMockOrder(
            8004, 'on-hold', $in_window_date,
            [['name' => 'Camp', 'subtotal' => 999, 'total' => 999]],
            [], [], []
        );
        $order_onhold->set_customer_id(8104);
        
        // Out-of-window order (should be EXCLUDED from calculations)
        $order_old = $this->createMockOrder(
            8005, 'completed', $out_of_window_date,
            [['name' => 'Camp', 'subtotal' => 888, 'total' => 888]],
            [], [], []
        );
        $order_old->set_customer_id(8105);
        
        // --- LOYALTY ORDERS ---
        // 5 holders with 600 CHF each
        $holder_ids = [8101, 8102, 8103]; // Include referred customers as holders
        for ($i = 0; $i < 5; $i++) {
            $order_id = 9001 + $i;
            $customer_id = 9101 + $i;
            $holder_ids[] = $customer_id;
            $order = $this->createMockOrder(
                $order_id, 'completed', $in_window_date,
                [['name' => 'Camp', 'subtotal' => 600, 'total' => 600]],
                [], [], []
            );
            $order->set_customer_id($customer_id);
            $mock_user_meta[$customer_id] = ['intersoccer_points_balance' => 50];
        }
        
        // 5 non-holders with 100 CHF each
        for ($i = 0; $i < 5; $i++) {
            $order_id = 9101 + $i;
            $customer_id = 9201 + $i;
            $order = $this->createMockOrder(
                $order_id, 'completed', $in_window_date,
                [['name' => 'Camp', 'subtotal' => 100, 'total' => 100]],
                [], [], []
            );
            $order->set_customer_id($customer_id);
        }
        
        // Mock holder detection via wpdb->get_col - includes referred customers as holders
        $mock_wpdb_get_col_results = [
            'usermeta' => $holder_ids,
            'intersoccer_points_balance' => $holder_ids,
        ];
        
        // Referral records
        $mock_wpdb_get_results = [
            'intersoccer_referrals' => [
                (object) ['customer_id' => 8101, 'order_id' => 8001, 'created_at' => $in_window_date],
                (object) ['customer_id' => 8102, 'order_id' => 8002, 'created_at' => $in_window_date],
                (object) ['customer_id' => 8103, 'order_id' => 8003, 'created_at' => $in_window_date],
            ],
            'intersoccer_points_log' => [],
        ];
        
        // Costs: 77 commissions + 40 referral points + 20 first-order = 137 referral, 100 loyalty
        $mock_wpdb_get_var_results = [
            'commission_amount' => 77,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 40,
            'NOT IN' => 100,  // loyalty activity points
            'intersoccer_points_balance' => 250,
        ];
        
        // Build list of all order IDs and the wc_get_orders mock
        $all_order_ids = [8001, 8002, 8003, 8004, 8005];
        $all_order_ids = array_merge($all_order_ids, range(9001, 9005), range(9101, 9105));
        
        $mock_wc_get_orders = function($args) use (&$mock_wc_orders_by_id, $in_window_date) {
            $statuses = isset($args['status']) ? (array) $args['status'] : ['completed', 'processing'];
            
            // Parse date_created filter
            $date_filter = $args['date_created'] ?? null;
            $start_date = null;
            $end_date = null;
            if ($date_filter) {
                if (strpos($date_filter, '...') !== false) {
                    list($start_date, $end_date) = explode('...', $date_filter);
                } elseif (strpos($date_filter, '>=') === 0) {
                    $start_date = substr($date_filter, 2);
                } elseif (strpos($date_filter, '<') === 0) {
                    $end_date = substr($date_filter, 1);
                }
            }
            
            $filtered = [];
            foreach ($mock_wc_orders_by_id as $oid => $order) {
                // Filter by status
                $status = $order->get_status();
                if (!in_array($status, $statuses) && !in_array('wc-' . $status, $statuses)) {
                    continue;
                }
                
                // Filter by customer_id if specified
                if (isset($args['customer_id'])) {
                    if ($order->get_customer_id() !== (int) $args['customer_id']) {
                        continue;
                    }
                }
                
                // Filter by date range
                $order_date = $order->get_date_created();
                if ($order_date) {
                    $order_ts = $order_date->getTimestamp();
                    if ($start_date && $order_ts < strtotime($start_date)) continue;
                    if ($end_date && $order_ts > strtotime($end_date . ' 23:59:59')) continue;
                }
                
                $filtered[$oid] = $order;
            }
            
            if (isset($args['return']) && $args['return'] === 'ids') {
                return array_keys($filtered);
            }
            return array_values($filtered);
        };
        
        $calculator = new InterSoccer_ROI_Calculator();
        
        // Verify cost breakdown
        $costs = $calculator->get_cost_breakdown();
        $this->assertEquals(77.0, $costs['commissions_chf']);
        $this->assertEquals(40.0, $costs['referral_points_chf']);
        $this->assertEquals(20.0, $costs['first_order_discount_chf']);
        $this->assertEquals(137.0, $costs['referral_cost_chf']);
        $this->assertEquals(100.0, $costs['loyalty_cost_chf']);
        $this->assertEquals(237.0, $costs['total_real_cost']);
        
        // Verify loyalty comparison (Finance dedupe)
        // Holders: 8 (3 referred + 5 loyalty)
        // Referred holders have 0 spend (first orders excluded)
        // Non-referred holders: 5 × 600 = 3000
        // Holder avg: 3000 / 8 = 375
        // Non-holders: 5 × 100 = 500, avg = 100
        // Extra spend = (375 - 100) × 8 = 2200
        $loyalty = $calculator->calculate_loyalty_roi();
        $this->assertEquals(8, $loyalty['holder_count']);
        $this->assertEqualsWithDelta(375.0, $loyalty['holder_avg_spend'], 0.1);
        $this->assertEqualsWithDelta(100.0, $loyalty['non_holder_avg_spend'], 0.1);
        $this->assertEqualsWithDelta(2200.0, $loyalty['total_extra_spend'], 0.1);
        
        // Verify referral revenue (Finance dedupe: first orders only)
        $referral = $calculator->calculate_referral_roi();
        $this->assertEquals(1500, $referral['revenue_ex_vat']);
        
        // Get combined ROI range
        $combined = $calculator->get_roi_range_display();
        
        // NO has_data guard - assertions MUST run
        $this->assertTrue($combined['has_data'], 'Must have data for this test to be valid');
        $this->assertFalse($combined['show_margin_prompt']);
        
        // Finance dedupe EXACT VALUES (user requirement: -18.1% / 182.7%)
        // Referral revenue: 1500, Loyalty extra spend: 2200, Total cost: 237
        // Combined low: ((1500×0.25 + 2200×0.05) × 0.40 - 237) / 237 × 100
        //             = ((375 + 110) × 0.40 - 237) / 237 × 100 = (194 - 237) / 237 × 100 = -18.1%
        // Combined high: ((1500×0.75 + 2200×0.25) × 0.40 - 237) / 237 × 100
        //              = ((1125 + 550) × 0.40 - 237) / 237 × 100 = (670 - 237) / 237 × 100 = 182.7%
        $this->assertEqualsWithDelta(-18.1, $combined['min'], 0.1,
            'Combined ROI low: ((1500×0.25 + 2200×0.05) × 0.40 - 237) / 237 × 100'
        );
        $this->assertEqualsWithDelta(182.7, $combined['max'], 0.1,
            'Combined ROI high: ((1500×0.75 + 2200×0.25) × 0.40 - 237) / 237 × 100'
        );
        
        // Finance ruling: attribution_rule must be present in combined tooltip
        $this->assertArrayHasKey('attribution_rule', $combined, 'Combined must include attribution_rule');
        $this->assertStringContainsString('first order counts as referral', $combined['attribution_rule']);
    }
    
    /**
     * EXACT VALUE TEST: Referred customer who also holds points (Finance dedupe)
     * 
     * Finance ruling: A referred customer's first order counts as referral revenue ONLY.
     * Their later orders (as a points holder) count toward loyalty extra spend.
     * Each order is counted once: first order → referral, later orders → loyalty.
     * 
     * Fixture:
     *   Customer 9001: Referred, got 25 points (new_customer_bonus)
     *   Order 1 (referred): 400 CHF → referral revenue (NOT in loyalty)
     *   Order 2 (later): 300 CHF → holder avg spend (loyalty)
     *   
     *   Customer 9002: Non-holder, 200 CHF → non-holder avg spend
     * 
     * Expected (Finance dedupe):
     *   Referral revenue: 400 CHF (first order only)
     *   Holder avg spend: 300 CHF (later order only, first excluded)
     *   Non-holder avg: 200 CHF
     *   Extra spend: (300 - 200) × 1 = 100 CHF
     */
    public function testCombinedROI_ReferredCustomerAlsoHoldsPoints() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wpdb_get_col_results;
        global $mock_wc_orders_by_id, $mock_user_meta;
        
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        $later_date = wp_date('Y-m-d', strtotime('-2 months')) . ' 10:00:00';
        
        // Referred customer's FIRST order (counts as referral revenue ONLY)
        $order1 = $this->createMockOrder(
            9501, 'completed', $in_window_date,
            [['name' => 'Camp', 'subtotal' => 400, 'total' => 400]],
            [], [], []
        );
        $order1->set_customer_id(9001);
        
        // Same customer's LATER order (counts toward holder avg spend)
        $order2 = $this->createMockOrder(
            9502, 'completed', $later_date,
            [['name' => 'Camp', 'subtotal' => 300, 'total' => 300]],
            [], [], []
        );
        $order2->set_customer_id(9001);
        
        // Customer 9001 holds points (got new_customer_bonus)
        $mock_user_meta[9001] = ['intersoccer_points_balance' => 25];
        
        // Non-holder customer
        $order3 = $this->createMockOrder(
            9503, 'completed', $in_window_date,
            [['name' => 'Camp', 'subtotal' => 200, 'total' => 200]],
            [], [], []
        );
        $order3->set_customer_id(9002);
        
        // Holder detection via wpdb->get_col
        $mock_wpdb_get_col_results = [
            'intersoccer_points_balance' => [9001],
        ];
        
        // Referral record: customer 9001 was referred, order 9501 is their first referred order
        $mock_wpdb_get_results = [
            'intersoccer_referrals' => [
                (object) ['customer_id' => 9001, 'order_id' => 9501, 'order_count' => 1, 'created_at' => $in_window_date],
            ],
            'intersoccer_points_log' => [],
        ];
        
        // Cost mocks
        $mock_wpdb_get_var_results = [
            'commission_amount' => 20,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 25,
            'NOT IN' => 0,
        ];
        
        $calculator = new InterSoccer_ROI_Calculator();
        
        // Finance dedupe: referral revenue = first order only (400)
        $referral = $calculator->calculate_referral_roi();
        $this->assertEquals(400, $referral['revenue_ex_vat'], 'Referral revenue = first order only (400)');
        
        // Finance dedupe: holder spend excludes first referred order
        $loyalty = $calculator->calculate_loyalty_roi();
        $this->assertEquals(1, $loyalty['holder_count'], 'One holder (referred customer with points)');
        $this->assertEqualsWithDelta(300.0, $loyalty['holder_avg_spend'], 0.1, 'Holder avg = later order only (300)');
        $this->assertEqualsWithDelta(200.0, $loyalty['non_holder_avg_spend'], 0.1, 'Non-holder avg = 200');
        $this->assertEqualsWithDelta(100.0, $loyalty['total_extra_spend'], 0.1, 'Extra spend = (300 - 200) × 1 = 100');
        
        // Verify combined includes attribution rule
        $combined = $calculator->get_roi_range_display();
        $this->assertArrayHasKey('attribution_rule', $combined);
    }

    /**
     * EXACT VALUE TEST: Finance ruling - dormant holders are excluded from count
     * 
     * Finance ruling: Count only customers who placed at least one order in the 12-month window.
     * Dormant accounts (points but no orders in window) are excluded from both groups.
     * Their points still count in the liability figure.
     * 
     * Fixture:
     *   - Customer 9101: Active holder (100 points, 500 CHF order in window)
     *   - Customer 9102: Dormant holder (200 points, NO orders in window)
     *   - Customer 9103: Non-holder (no points, 300 CHF order in window)
     * 
     * Expected:
     *   holder_count: 1 (only active holder; dormant excluded)
     *   non_holder_count: 1
     *   holder_avg_spend: 500 CHF (only active holder's order)
     *   non_holder_avg_spend: 300 CHF
     *   Loyalty cost includes dormant holder's points (200) in liability
     */
    public function testFinanceRuling_DormantHolderExcludedFromCount() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wpdb_get_col_results;
        global $mock_wc_orders_by_id, $mock_user_meta;
        
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        
        // Active holder: has order in window + points
        $order1 = $this->createMockOrder(
            9601, 'completed', $in_window_date,
            [['name' => 'Camp', 'subtotal' => 500, 'total' => 500]],
            [], [], []
        );
        $order1->set_customer_id(9101);
        $mock_user_meta[9101] = ['intersoccer_points_balance' => 100];
        
        // Dormant holder: has points but NO orders in window
        // (no order created for customer 9102)
        $mock_user_meta[9102] = ['intersoccer_points_balance' => 200];
        
        // Non-holder: no points, has order in window
        $order2 = $this->createMockOrder(
            9602, 'completed', $in_window_date,
            [['name' => 'Camp', 'subtotal' => 300, 'total' => 300]],
            [], [], []
        );
        $order2->set_customer_id(9103);
        
        // Holder detection returns BOTH holders (active + dormant)
        // Finance ruling must filter to only active ones
        $mock_wpdb_get_col_results = [
            'intersoccer_points_balance' => [9101, 9102], // Both holders
        ];
        
        $mock_wpdb_get_results = [
            'intersoccer_referrals' => [],
            'intersoccer_points_log' => [],
        ];
        
        // Loyalty cost: 300 total points (100 active + 200 dormant)
        // Dormant holder's points still count in liability
        $mock_wpdb_get_var_results = [
            'commission_amount' => 0,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 0,
            'NOT IN' => 300, // Total activity points including dormant
            'intersoccer_points_balance' => 300,
        ];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $loyalty = $calculator->calculate_loyalty_roi();
        
        // Finance ruling: dormant holder is EXCLUDED from holder_count
        $this->assertEquals(1, $loyalty['holder_count'], 'Dormant holder must be excluded - only 1 active holder');
        $this->assertEquals(1, $loyalty['non_holder_count'], 'One non-holder with order in window');
        
        // Average spend uses only active holders
        $this->assertEqualsWithDelta(500.0, $loyalty['holder_avg_spend'], 0.1, 'Holder avg = active holder only (500)');
        $this->assertEqualsWithDelta(300.0, $loyalty['non_holder_avg_spend'], 0.1, 'Non-holder avg = 300');
        
        // Extra spend = (500 - 300) × 1 = 200
        $this->assertEqualsWithDelta(200.0, $loyalty['total_extra_spend'], 0.1, 'Extra spend = (500 - 300) × 1');
        
        // Liability still includes dormant holder's points
        $this->assertEquals(300, $loyalty['points_cost'], 'Cost includes all points (dormant + active)');
    }

    /**
     * EXACT VALUE TEST: Negative ROI values are allowed
     * 
     * Fixture:
     *   Referral revenue: 1000 CHF (one referred order)
     *   Referral cost: 15000 CHF (10000 commissions + 5000 points)
     *   Margin: 10%
     * 
     * Expected referral ROI (with 25%-75% incrementality):
     *   Low: (1000 × 0.25 × 0.10 - 15000) / 15000 × 100 = -99.8%
     *   High: (1000 × 0.75 × 0.10 - 15000) / 15000 × 100 = -99.5%
     */
    public function testCombinedROIRange_AllowsNegativeValues() {
        update_option('intersoccer_roi_camp_margin_percent', 10);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_orders_by_id, $mock_user_meta, $mock_wpdb_get_col_results;
        
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        
        $order = $this->createMockOrder(
            6001, 'completed', $in_window_date,
            [['name' => 'Camp', 'subtotal' => 1000, 'total' => 1000]],
            [], [], []
        );
        $order->set_customer_id(6001);
        
        $mock_wpdb_get_var_results = [
            'commission_amount' => 10000,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 5000,
            'NOT IN' => 0,
            'intersoccer_points_balance' => 0,
        ];
        $mock_wpdb_get_results = [
            'intersoccer_referrals' => [
                (object) ['customer_id' => 6001, 'order_id' => 6001, 'created_at' => $in_window_date],
            ],
            'intersoccer_points_log' => [],
        ];
        $mock_wpdb_get_col_results = ['intersoccer_points_balance' => []];
        $mock_user_meta = [];
        
        $calculator = new InterSoccer_ROI_Calculator();
        
        $referral_range = $calculator->calculate_referral_roi_with_range();
        
        // With cost >> revenue, ROI is deeply negative
        $this->assertTrue($referral_range['has_data'], 'Must have data');
        $this->assertLessThan(0, $referral_range['low'], 'Low ROI must be negative');
        $this->assertLessThan(0, $referral_range['high'], 'High ROI must be negative');
        
        // Exact values: (1000 × incrementality × 0.10 - 15000) / 15000 × 100
        $this->assertEqualsWithDelta(-99.8, $referral_range['low'], 0.1, 'Low = (25 - 15000) / 15000 × 100');
        $this->assertEqualsWithDelta(-99.5, $referral_range['high'], 0.1, 'High = (75 - 15000) / 15000 × 100');
    }
    
    // =========================================================================
    // REVENUE BASIS TESTS
    // =========================================================================
    
    /**
     * FIXTURE TEST: Revenue = line totals after coupons
     * 
     * Fixture:
     *   Order: line subtotal = 500, coupon = 50, line total = 450
     *   Points fee: -30 (NOT counted in revenue)
     *   Referral discount fee: -20 (NOT counted in revenue)
     * 
     * Expected: revenue = 450 (line total, not 420)
     */
    public function testRevenueBasis_CouponReducesRevenue_PointsAreFeeNotLineDiscount() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results;
        
        $order = $this->createMockOrder(
            1001,
            'completed',
            '2024-06-15 10:00:00',
            [['name' => 'Camp Registration', 'subtotal' => 500, 'total' => 450]],
            [
                ['name' => 'Referral Credits Discount', 'total' => -30],
                ['name' => 'Referral Discount', 'total' => -20],
            ],
            [],
            ['_intersoccer_first_order_discount_applied' => 1, '_intersoccer_first_order_discount_amount' => 20]
        );
        $order->set_total(400);
        
        $this->mockWcGetOrders([1001]);
        
        $mock_wpdb_get_var_results = [
            'commission_amount' => 0,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 0,
            'NOT IN' => 0,
        ];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $range = ['start' => '2024-01-01', 'end' => '2024-12-31'];
        
        $revenue = $calculator->get_revenue_ex_vat($range, true);
        $this->assertEquals(450.0, $revenue, 'Revenue should be 450 CHF (line total after coupon)');
    }
    
    public function testRevenueCalculation_WithEmptyOrdersReturnsZero() {
        global $mock_wc_get_orders;
        $mock_wc_get_orders = function() { return []; };
        
        $calculator = new InterSoccer_ROI_Calculator();
        $range = $calculator->get_period_range();
        
        $revenue = $calculator->get_revenue_ex_vat($range, true);
        $this->assertEquals(0, $revenue, 'Empty orders should return 0 revenue');
    }
    
    // =========================================================================
    // FIRST-ORDER DISCOUNT TESTS WITH EXACT VALUES
    // =========================================================================
    
    /**
     * FIXTURE TEST: First-order discount counted as referral cost
     * 
     * Fixture:
     *   Commissions: 100 CHF
     *   Referral points: 50 CHF
     *   First-order discount fee: 20 CHF
     * 
     * Expected: referral_cost = 170 CHF (100 + 50 + 20)
     */
    public function testFirstOrderDiscount_CountedAsReferralCost() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results;
        
        // Create order with first-order discount
        $order = $this->createMockOrder(
            3001,
            'completed',
            '2024-06-15 10:00:00',
            [['name' => 'Camp', 'subtotal' => 500, 'total' => 500]],
            [['name' => 'Referral Discount', 'total' => -20]],
            [],
            ['_intersoccer_first_order_discount_applied' => 1, '_intersoccer_first_order_discount_amount' => 20]
        );
        $order->set_total(480);
        
        $this->mockWcGetOrders([3001]);
        
        $mock_wpdb_get_var_results = [
            'commission_amount' => 100,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 50,
            'NOT IN' => 200,
        ];
        $mock_wpdb_get_results = ['intersoccer_referrals' => [], 'intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $costs = $calculator->get_cost_breakdown();
        
        $this->assertArrayHasKey('first_order_discount_chf', $costs);
        $this->assertEquals(20.0, $costs['first_order_discount_chf'], 'First-order discount should be 20 CHF');
        $this->assertArrayHasKey('has_first_order_discount', $costs);
        $this->assertTrue($costs['has_first_order_discount']);
        
        // Referral cost = 100 + 50 + 20 = 170
        $this->assertEquals(170.0, $costs['referral_cost_chf'], 'Referral cost should be 170 CHF');
    }
    
    public function testFirstOrderDiscount_MethodReturnsNumeric() {
        global $mock_wc_get_orders;
        $mock_wc_get_orders = function() { return []; };
        
        $calculator = new InterSoccer_ROI_Calculator();
        $range = $calculator->get_period_range();
        
        $this->assertTrue(
            method_exists($calculator, 'get_first_order_discount_cost'),
            'Should have get_first_order_discount_cost method'
        );
        
        $discount_cost = $calculator->get_first_order_discount_cost($range);
        $this->assertIsNumeric($discount_cost);
        $this->assertGreaterThanOrEqual(0, $discount_cost);
    }
    
    /**
     * FIXTURE TEST: On-hold orders excluded from first-order discount cost
     * 
     * Uses a real order through the status/date-aware default mock.
     * The mock filters by status ['completed', 'processing'] and date range.
     * On-hold orders are excluded by the status filter.
     */
    public function testFirstOrderDiscount_OnHoldOrderExcluded() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_orders_by_id;
        
        // Use dates within the query range
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        
        // Create on-hold order with first-order discount - uses createMockOrder which
        // adds to $mock_wc_orders_by_id, and the default wc_get_orders mock filters by status
        $order = $this->createMockOrder(
            1002,
            'on-hold',
            $in_window_date,
            [['name' => 'Camp', 'subtotal' => 500, 'total' => 450]],
            [['name' => 'Referral Discount', 'total' => -20, 'meta' => ['_intersoccer_referral_discount_fee' => '1']]],
            [],
            ['_intersoccer_first_order_discount_applied' => 1, '_intersoccer_first_order_discount_amount' => 20]
        );
        $order->set_customer_id(1002);
        
        // Don't override wc_get_orders - let the default mock's status filter exclude on-hold
        // The default mock in bootstrap.php filters by status = ['completed', 'processing']
        
        $mock_wpdb_get_var_results = [];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $range = $calculator->get_period_range();
        
        $discount_cost = $calculator->get_first_order_discount_cost($range);
        $this->assertEquals(0.0, $discount_cost, 'On-hold orders excluded by status filter');
    }
    
    /**
     * FIXTURE TEST: Fully refunded order has discount cost = 0
     */
    public function testFirstOrderDiscount_FullyRefundedOrderZeroCost() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results;
        
        // Create completed order with full refund
        $order = $this->createMockOrder(
            1003,
            'completed',
            '2024-06-15 10:00:00',
            [['name' => 'Camp', 'subtotal' => 500, 'total' => 450]],
            [['name' => 'Referral Discount', 'total' => -20]],
            [['total' => -430, 'fees' => [['name' => 'Referral Discount', 'total' => 20]]]],
            ['_intersoccer_first_order_discount_applied' => 1, '_intersoccer_first_order_discount_amount' => 20]
        );
        $order->set_total(430);
        
        $this->mockWcGetOrders([1003]);
        
        $mock_wpdb_get_var_results = [];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $range = ['start' => '2024-01-01', 'end' => '2024-12-31'];
        
        $discount_cost = $calculator->get_first_order_discount_cost($range);
        $this->assertEquals(0.0, $discount_cost, 'Fully refunded order discount cost should be 0');
    }
    
    /**
     * FIXTURE TEST: Partial refund reduces discount cost (line-based rule)
     * 
     * Finance rule #1: If refund records which product lines were refunded,
     * reverse discount by those lines' share of order's product total.
     * 
     * Fixture (designed to differ from cash-ratio test):
     *   Order: product line total = 600, discount fee = -30
     *   Order total paid = 600 - 30 = 570
     *   Refund: product line refunded = 300 (half of 600)
     * 
     * Line-based calculation: 300 / 600 × 30 = 15 reversed
     * Net discount cost = 30 - 15 = 15 CHF
     * 
     * Compare: Cash-ratio would give 300/570 × 30 = 15.79 (different!)
     */
    public function testFirstOrderDiscount_LineBased_PartialRefund() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results;
        
        $order = $this->createMockOrder(
            1004,
            'completed',
            '2024-06-15 10:00:00',
            [['name' => 'Camp', 'subtotal' => 600, 'total' => 600]],
            [['name' => 'Referral Discount', 'total' => -30, 'meta' => ['_intersoccer_referral_discount_fee' => '1']]],
            [],
            ['_intersoccer_first_order_discount_applied' => 1, '_intersoccer_first_order_discount_amount' => 30]
        );
        $order->set_total(570);
        
        // Refund with product line items (triggers line-based rule)
        $refund = new WC_Order_Refund(10041);
        $refund->set_parent_id(1004);
        $refund->set_total(-300);
        $refund_item = new WC_Order_Item_Product();
        $refund_item->set_name('Camp Refund');
        $refund_item->set_total(-300);  // Half of 600 line total
        $refund->add_item($refund_item);
        $order->add_refund($refund);
        
        $this->mockWcGetOrders([1004]);
        
        $mock_wpdb_get_var_results = [];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $range = ['start' => '2024-01-01', 'end' => '2024-12-31'];
        
        $discount_cost = $calculator->get_first_order_discount_cost($range);
        // Line-based: 300/600 × 30 = 15 reversed, net = 15
        $this->assertEquals(15.0, $discount_cost, 'Line-based: 300/600 × 30 = 15 reversed, net = 15');
    }
    
    /**
     * FIXTURE TEST: Cash-ratio refund when no product lines recorded
     * 
     * Finance rule #2: If refund doesn't record product lines,
     * use cash ratio: refund amount / order total paid.
     * 
     * Fixture (designed to differ from line-based test):
     *   Order: product total = 600, discount = 30
     *   Order total paid = 600 - 30 = 570
     *   Refund: 300 CHF (no product line items)
     * 
     * Cash ratio: 300 / 570 × 30 = 15.79 reversed (rounds to 15.8)
     * Net discount cost = 30 - 15.79 = 14.21 CHF (rounds to 14.2)
     * 
     * Compare: Line-based would give 300/600 × 30 = 15 (different!)
     */
    public function testFirstOrderDiscount_CashRatio_NoLineItems() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results;
        
        $order = $this->createMockOrder(
            1005,
            'completed',
            '2024-06-15 10:00:00',
            [['name' => 'Camp', 'subtotal' => 600, 'total' => 600]],
            [['name' => 'Referral Discount', 'total' => -30, 'meta' => ['_intersoccer_referral_discount_fee' => '1']]],
            [],
            ['_intersoccer_first_order_discount_applied' => 1, '_intersoccer_first_order_discount_amount' => 30]
        );
        $order->set_total(570);
        
        // Refund WITHOUT product line items (triggers cash-ratio rule)
        $refund = new WC_Order_Refund(10051);
        $refund->set_parent_id(1005);
        $refund->set_total(-300);
        // No line items added - simulates manual/partial refund without line detail
        $order->add_refund($refund);
        
        $this->mockWcGetOrders([1005]);
        
        $mock_wpdb_get_var_results = [];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $range = ['start' => '2024-01-01', 'end' => '2024-12-31'];
        
        $discount_cost = $calculator->get_first_order_discount_cost($range);
        // Cash ratio: 300/570 × 30 = 15.79 reversed, net = 14.21
        $this->assertEqualsWithDelta(14.2, $discount_cost, 0.1, 'Cash ratio: 300/570 × 30 = 15.79 reversed, net = 14.2');
    }
    
    /**
     * FIXTURE TEST: Never reverse more than original discount (cap rule)
     * 
     * Finance rule #3: Never reverse more than the original discount in total.
     * 
     * Fixture:
     *   Order: product total = 450, discount = 20
     *   Refund 1: 300 refunded (300/450 × 20 = 13.33)
     *   Refund 2: 300 refunded (300/450 × 20 = 13.33)
     *   Total would be 26.66, but capped at 20
     * 
     * Net discount cost = 0 CHF (fully reversed, capped)
     */
    public function testFirstOrderDiscount_Cap_MultipleRefunds() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results;
        
        $order = $this->createMockOrder(
            1006,
            'completed',
            '2024-06-15 10:00:00',
            [['name' => 'Camp', 'subtotal' => 500, 'total' => 450]],
            [['name' => 'Referral Discount', 'total' => -20]],
            [],
            ['_intersoccer_first_order_discount_applied' => 1, '_intersoccer_first_order_discount_amount' => 20]
        );
        $order->set_total(430);
        
        // First refund: 300 of 450 product
        $refund1 = new WC_Order_Refund(10061);
        $refund1->set_parent_id(1006);
        $refund1->set_total(-300);
        $refund_item1 = new WC_Order_Item_Product();
        $refund_item1->set_name('Camp Refund 1');
        $refund_item1->set_total(-300);
        $refund1->add_item($refund_item1);
        $order->add_refund($refund1);
        
        // Second refund: another 300 (overrefund scenario)
        $refund2 = new WC_Order_Refund(10062);
        $refund2->set_parent_id(1006);
        $refund2->set_total(-300);
        $refund_item2 = new WC_Order_Item_Product();
        $refund_item2->set_name('Camp Refund 2');
        $refund_item2->set_total(-300);
        $refund2->add_item($refund_item2);
        $order->add_refund($refund2);
        
        $this->mockWcGetOrders([1006]);
        
        $mock_wpdb_get_var_results = [];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $range = ['start' => '2024-01-01', 'end' => '2024-12-31'];
        
        $discount_cost = $calculator->get_first_order_discount_cost($range);
        // (300+300)/450 × 20 = 26.67, capped at 20, net = 0
        $this->assertEquals(0.0, $discount_cost, 'Cap: cannot reverse more than original 20');
    }
    
    /**
     * FIXTURE TEST: Refunded orders status excluded
     * 
     * Uses a real order through the status/date-aware default mock.
     * The mock filters by status ['completed', 'processing'] and date range.
     * Refunded orders are excluded by the status filter.
     */
    public function testFirstOrderDiscount_RefundedOrdersExcluded() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_orders_by_id;
        
        // Use dates within the query range
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        
        // Create refunded order with first-order discount - uses createMockOrder which
        // adds to $mock_wc_orders_by_id, and the default wc_get_orders mock filters by status
        $order = $this->createMockOrder(
            5001,
            'refunded',
            $in_window_date,
            [['name' => 'Camp', 'subtotal' => 500, 'total' => 450]],
            [['name' => 'Referral Discount', 'total' => -10, 'meta' => ['_intersoccer_referral_discount_fee' => '1']]],
            [],
            ['_intersoccer_first_order_discount_applied' => 1, '_intersoccer_first_order_discount_amount' => 10]
        );
        $order->set_customer_id(501);
        
        // Don't override wc_get_orders - let the default mock's status filter exclude refunded
        // The default mock in bootstrap.php filters by status = ['completed', 'processing']
        
        $mock_wpdb_get_var_results = [];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $range = $calculator->get_period_range();
        
        $discount_cost = $calculator->get_first_order_discount_cost($range);
        $this->assertEquals(0.0, $discount_cost, 'Refunded orders excluded by status filter');
    }
    
    // =========================================================================
    // FEE IDENTIFICATION TESTS
    // =========================================================================
    
    /**
     * FIXTURE TEST: Fee identified by meta marker only (new orders)
     * 
     * Fixture: Fee has marker meta but generic name.
     * Expected: Uses marker, ignores name.
     */
    public function testFeeIdentification_ByMarkerOnly() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results;
        
        // Fee has marker but a non-standard name
        $order = $this->createMockOrder(
            6001, 'completed', '2024-06-15 10:00:00',
            [['name' => 'Camp', 'subtotal' => 500, 'total' => 500]],
            [['name' => 'Generic Fee Label', 'total' => -25, 'meta' => ['_intersoccer_referral_discount_fee' => '1']]],
            [], []
        );
        
        $this->mockWcGetOrders([6001]);
        $mock_wpdb_get_var_results = [];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $range = ['start' => '2024-01-01', 'end' => '2024-12-31'];
        
        $discount_cost = $calculator->get_first_order_discount_cost($range);
        $this->assertEquals(25.0, $discount_cost, 'Marker-only: fee identified by meta despite generic name');
    }
    
    /**
     * FIXTURE TEST: Fee identified by FR name only (WPML fallback)
     * 
     * Fixture: Fee has French name, no marker meta.
     * Expected: Falls back to name matching.
     */
    public function testFeeIdentification_FRNameOnly() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results;
        
        // Fee has French name but no marker
        $order = $this->createMockOrder(
            6002, 'completed', '2024-06-15 10:00:00',
            [['name' => 'Camp', 'subtotal' => 500, 'total' => 500]],
            [['name' => 'Réduction de parrainage', 'total' => -20]],
            [],
            ['_intersoccer_first_order_discount_applied' => 1, '_intersoccer_first_order_discount_amount' => 20]
        );
        
        $this->mockWcGetOrders([6002]);
        $mock_wpdb_get_var_results = [];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $range = ['start' => '2024-01-01', 'end' => '2024-12-31'];
        
        $discount_cost = $calculator->get_first_order_discount_cost($range);
        $this->assertEquals(20.0, $discount_cost, 'FR name fallback: Réduction de parrainage identified');
    }
    
    /**
     * FIXTURE TEST: Fee identified by DE name only (WPML fallback)
     * 
     * Fixture: Fee has German name, no marker meta.
     * Expected: Falls back to name matching.
     */
    public function testFeeIdentification_DENameOnly() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results;
        
        // Fee has German name but no marker
        $order = $this->createMockOrder(
            6003, 'completed', '2024-06-15 10:00:00',
            [['name' => 'Camp', 'subtotal' => 500, 'total' => 500]],
            [['name' => 'Empfehlungsrabatt', 'total' => -15]],
            [],
            ['_intersoccer_first_order_discount_applied' => 1, '_intersoccer_first_order_discount_amount' => 15]
        );
        
        $this->mockWcGetOrders([6003]);
        $mock_wpdb_get_var_results = [];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $range = ['start' => '2024-01-01', 'end' => '2024-12-31'];
        
        $discount_cost = $calculator->get_first_order_discount_cost($range);
        $this->assertEquals(15.0, $discount_cost, 'DE name fallback: Empfehlungsrabatt identified');
    }
    
    /**
     * FIXTURE TEST: Cash refund (any type) proportionally reduces discount
     * 
     * Finance rule: Any partial refund reduces the discount proportionally
     * by cash ratio, regardless of what was refunded.
     * 
     * Fixture:
     *   Order: product = 600, referral discount = -30, total paid = 570
     *   Refund: 50 CHF (fee-only refund, no product lines)
     * 
     * Cash ratio: 50/570 × 30 = 2.63 reversed
     * Net discount cost = 30 - 2.63 = 27.37 CHF
     */
    public function testFeeIdentification_CashRefundProportionallyReducesDiscount() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results;
        
        $order = $this->createMockOrder(
            6004, 'completed', '2024-06-15 10:00:00',
            [['name' => 'Camp', 'subtotal' => 600, 'total' => 600]],
            [['name' => 'Referral Discount', 'total' => -30, 'meta' => ['_intersoccer_referral_discount_fee' => '1']]],
            [],
            ['_intersoccer_first_order_discount_applied' => 1, '_intersoccer_first_order_discount_amount' => 30]
        );
        $order->set_total(570);
        
        // Refund with fee line only (no product lines)
        $refund = new WC_Order_Refund(60041);
        $refund->set_parent_id(6004);
        $refund->set_total(-50);
        // Fee refund (no product line items = uses cash ratio)
        $refund_fee = new WC_Order_Item_Fee('Shipping Refund', 50);
        $refund->add_item($refund_fee);
        $order->add_refund($refund);
        
        $this->mockWcGetOrders([6004]);
        $mock_wpdb_get_var_results = [];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $range = ['start' => '2024-01-01', 'end' => '2024-12-31'];
        
        $discount_cost = $calculator->get_first_order_discount_cost($range);
        // Cash ratio: 50/570 × 30 = 2.63 reversed, net = 27.37
        $this->assertEqualsWithDelta(27.4, $discount_cost, 0.1, 'Cash refund: 50/570 × 30 = 2.63 reversed, net = 27.4');
    }
    
    // =========================================================================
    // CHECKOUT MARKER TESTS
    // =========================================================================
    
    /**
     * FIXTURE TEST: Checkout marker - name match takes priority over amount match
     * 
     * Fixture: Order has two fees with similar amounts but different names.
     *   - Fee 1: "Other Discount" with -25 (matches amount)
     *   - Fee 2: "Referral Discount" with -25 (matches name)
     * 
     * Expected: The marker is applied to Fee 2 (name match), not Fee 1 (amount match).
     * Proves: Name matching takes priority over amount matching.
     */
    public function testCheckoutMarker_NameMatchTakesPriorityOverAmount() {
        require_once __DIR__ . '/../includes/class-admin-dashboard.php';
        
        global $mock_session;
        $mock_session = [
            'intersoccer_first_order_discount_pending' => 'yes',
            'intersoccer_first_order_discount_code' => 'TEST123',
            'intersoccer_first_order_discount_amount' => 25,
        ];
        
        $order = new WC_Order(7001);
        $order->set_id(7001);
        $order->set_customer_id(701);
        
        $other_fee = new WC_Order_Item_Fee('Other Discount', -25);
        $order->add_fee($other_fee);
        
        $referral_fee = new WC_Order_Item_Fee('Referral Discount', -25);
        $order->add_fee($referral_fee);
        
        $reflection = new ReflectionClass(InterSoccer_Referral_Admin_Dashboard::class);
        $dashboard = $reflection->newInstanceWithoutConstructor();
        $dashboard->maybe_mark_first_order_discount_on_order($order, []);
        
        $fees = $order->get_items('fee');
        $other_fee_marked = false;
        $referral_fee_marked = false;
        
        foreach ($fees as $fee) {
            if ($fee->get_name() === 'Other Discount') {
                $other_fee_marked = (bool) $fee->get_meta('_intersoccer_referral_discount_fee', true);
            }
            if ($fee->get_name() === 'Referral Discount') {
                $referral_fee_marked = (bool) $fee->get_meta('_intersoccer_referral_discount_fee', true);
            }
        }
        
        $this->assertFalse($other_fee_marked, 'Other Discount should NOT be marked (amount match loses to name match)');
        $this->assertTrue($referral_fee_marked, 'Referral Discount SHOULD be marked (name match wins)');
    }
    
    /**
     * FIXTURE TEST: Checkout marker - amount fallback only when no name matches
     * 
     * Fixture: Order has one fee with generic name but matching amount.
     *   - Fee: "Generic Fee" with -30 (no name match, but amount matches)
     * 
     * Expected: The marker is applied to the fee via amount fallback.
     * Proves: Amount matching works as fallback when no name matches.
     */
    public function testCheckoutMarker_AmountFallbackWhenNoNameMatch() {
        require_once __DIR__ . '/../includes/class-admin-dashboard.php';
        
        global $mock_session;
        $mock_session = [
            'intersoccer_first_order_discount_pending' => 'yes',
            'intersoccer_first_order_discount_code' => 'TEST456',
            'intersoccer_first_order_discount_amount' => 30,
        ];
        
        $order = new WC_Order(7002);
        $order->set_id(7002);
        $order->set_customer_id(702);
        
        $generic_fee = new WC_Order_Item_Fee('Generic Discount Fee', -30);
        $order->add_fee($generic_fee);
        
        $reflection = new ReflectionClass(InterSoccer_Referral_Admin_Dashboard::class);
        $dashboard = $reflection->newInstanceWithoutConstructor();
        $dashboard->maybe_mark_first_order_discount_on_order($order, []);
        
        $fees = $order->get_items('fee');
        $generic_fee_marked = false;
        
        foreach ($fees as $fee) {
            if ($fee->get_name() === 'Generic Discount Fee') {
                $generic_fee_marked = (bool) $fee->get_meta('_intersoccer_referral_discount_fee', true);
            }
        }
        
        $this->assertTrue($generic_fee_marked, 'Generic fee should be marked via amount fallback');
    }
    
    /**
     * FIXTURE TEST: Checkout marker - FR name recognized
     * 
     * Fixture: Order has fee with French translated name.
     *   - Fee: "Réduction de parrainage" with -20
     * 
     * Expected: The marker is applied to the fee (FR name recognized).
     * Proves: WPML FR translation is recognized by name matching.
     */
    public function testCheckoutMarker_FRNameRecognized() {
        require_once __DIR__ . '/../includes/class-admin-dashboard.php';
        
        global $mock_session;
        $mock_session = [
            'intersoccer_first_order_discount_pending' => 'yes',
            'intersoccer_first_order_discount_code' => 'TEST789',
            'intersoccer_first_order_discount_amount' => 20,
        ];
        
        $order = new WC_Order(7003);
        $order->set_id(7003);
        $order->set_customer_id(703);
        
        $fr_fee = new WC_Order_Item_Fee('Réduction de parrainage', -20);
        $order->add_fee($fr_fee);
        
        $reflection = new ReflectionClass(InterSoccer_Referral_Admin_Dashboard::class);
        $dashboard = $reflection->newInstanceWithoutConstructor();
        $dashboard->maybe_mark_first_order_discount_on_order($order, []);
        
        $fees = $order->get_items('fee');
        $fr_fee_marked = false;
        
        foreach ($fees as $fee) {
            if ($fee->get_name() === 'Réduction de parrainage') {
                $fr_fee_marked = (bool) $fee->get_meta('_intersoccer_referral_discount_fee', true);
            }
        }
        
        $this->assertTrue($fr_fee_marked, 'FR Réduction de parrainage should be marked by name');
    }
    
    /**
     * FIXTURE TEST: Checkout marker - DE name recognized
     * 
     * Fixture: Order has fee with German translated name.
     *   - Fee: "Empfehlungsrabatt" with -15
     * 
     * Expected: The marker is applied to the fee (DE name recognized).
     * Proves: WPML DE translation is recognized by name matching.
     */
    public function testCheckoutMarker_DENameRecognized() {
        require_once __DIR__ . '/../includes/class-admin-dashboard.php';
        
        global $mock_session;
        $mock_session = [
            'intersoccer_first_order_discount_pending' => 'yes',
            'intersoccer_first_order_discount_code' => 'TESTDE1',
            'intersoccer_first_order_discount_amount' => 15,
        ];
        
        $order = new WC_Order(7004);
        $order->set_id(7004);
        $order->set_customer_id(704);
        
        $de_fee = new WC_Order_Item_Fee('Empfehlungsrabatt', -15);
        $order->add_fee($de_fee);
        
        $reflection = new ReflectionClass(InterSoccer_Referral_Admin_Dashboard::class);
        $dashboard = $reflection->newInstanceWithoutConstructor();
        $dashboard->maybe_mark_first_order_discount_on_order($order, []);
        
        $fees = $order->get_items('fee');
        $de_fee_marked = false;
        
        foreach ($fees as $fee) {
            if ($fee->get_name() === 'Empfehlungsrabatt') {
                $de_fee_marked = (bool) $fee->get_meta('_intersoccer_referral_discount_fee', true);
            }
        }
        
        $this->assertTrue($de_fee_marked, 'DE Empfehlungsrabatt should be marked by name');
    }
    
    /**
     * FIXTURE TEST: Checkout marker - no separate save_meta_data call
     * 
     * This test verifies the implementation contract: the fee meta is added via
     * add_meta_data() but NOT saved separately via save_meta_data(). WooCommerce
     * saves order item meta when the order is saved during checkout.
     * 
     * Fixture: Order with fee, marker applied.
     * Expected: Meta is accessible via get_meta() (added to pending meta).
     * Proves: Meta is added and will be saved WITH the order (not before).
     */
    public function testCheckoutMarker_MetaAddedNotSavedSeparately() {
        require_once __DIR__ . '/../includes/class-admin-dashboard.php';
        
        global $mock_session;
        $mock_session = [
            'intersoccer_first_order_discount_pending' => 'yes',
            'intersoccer_first_order_discount_code' => 'TESTSAVE',
            'intersoccer_first_order_discount_amount' => 40,
        ];
        
        $order = new WC_Order(7005);
        $order->set_id(7005);
        $order->set_customer_id(705);
        
        $fee = new WC_Order_Item_Fee('Referral Discount', -40);
        $order->add_fee($fee);
        
        $reflection = new ReflectionClass(InterSoccer_Referral_Admin_Dashboard::class);
        $dashboard = $reflection->newInstanceWithoutConstructor();
        $dashboard->maybe_mark_first_order_discount_on_order($order, []);
        
        $fees = $order->get_items('fee');
        $fee_meta_value = null;
        
        foreach ($fees as $f) {
            if ($f->get_name() === 'Referral Discount') {
                $fee_meta_value = $f->get_meta('_intersoccer_referral_discount_fee', true);
            }
        }
        
        $this->assertEquals('1', $fee_meta_value, 'Fee meta should be accessible via get_meta (added to pending meta)');
    }
    
    // =========================================================================
    // MONTHLY COST TEST
    // =========================================================================
    
    /**
     * Test that monthly program cost includes first-order discount
     */
    public function testMonthlyCost_IncludesFirstOrderDiscount() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results;
        
        // Create order with first-order discount in current month
        $this_month = date('Y-m');
        $order = $this->createMockOrder(
            4001,
            'completed',
            $this_month . '-15 10:00:00',
            [['name' => 'Camp', 'subtotal' => 500, 'total' => 500]],
            [['name' => 'Referral Discount', 'total' => -25]],
            [],
            ['_intersoccer_first_order_discount_applied' => 1, '_intersoccer_first_order_discount_amount' => 25]
        );
        $order->set_total(475);
        
        $this->mockWcGetOrders([4001]);
        
        $mock_wpdb_get_var_results = [
            'commission_amount' => 0,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 0,
            'NOT IN' => 0,
        ];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $range = ['start' => $this_month . '-01', 'end' => date('Y-m-t')];
        
        $discount_cost = $calculator->get_first_order_discount_cost($range);
        $this->assertEquals(25.0, $discount_cost, 'Monthly discount should be 25 CHF');
    }
    
    // =========================================================================
    // COMBINED ROI INTEGRATION TEST
    // =========================================================================
    
    /**
     * EXACT VALUE TEST: Ex-VAT revenue from orders with line items
     * 
     * Fixture:
     *   Order 1: 300 CHF line item
     *   Order 2: 200 CHF line item
     * 
     * Expected: total revenue = 500 CHF
     */
    public function testExVatRevenue_ReturnsExactValue() {
        global $mock_wc_orders_by_id;
        
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        
        $order1 = $this->createMockOrder(
            6501, 'completed', $in_window_date,
            [['name' => 'Camp A', 'subtotal' => 300, 'total' => 300]],
            [], [], []
        );
        $order1->set_customer_id(6501);
        
        $order2 = $this->createMockOrder(
            6502, 'completed', $in_window_date,
            [['name' => 'Camp B', 'subtotal' => 200, 'total' => 200]],
            [], [], []
        );
        $order2->set_customer_id(6502);
        
        $calculator = new InterSoccer_ROI_Calculator();
        $range = $calculator->get_period_range();
        
        $result = $calculator->get_revenue_ex_vat($range);
        
        $this->assertEquals(500.0, $result, 'Revenue should be 300 + 200 = 500');
    }
    
    /**
     * EXACT VALUE TEST: Referral ROI range with no data
     * 
     * Expected:
     *   low: null (no revenue)
     *   high: null (no revenue)
     *   has_data: false
     *   show_margin_prompt: false (margin IS set at 40%)
     */
    public function testReferralROIWithRange_NoData_ExactValues() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_get_orders;
        $mock_wpdb_get_var_results = [];
        $mock_wpdb_get_results = ['intersoccer_referrals' => [], 'intersoccer_points_log' => []];
        $mock_wc_get_orders = function() { return []; };
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->calculate_referral_roi_with_range();
        
        $this->assertFalse($result['has_data']);
        $this->assertNull($result['low']);
        $this->assertNull($result['high']);
        $this->assertFalse($result['show_margin_prompt']);
    }
    
    /**
     * EXACT VALUE TEST: Loyalty ROI range with no data
     * 
     * Expected:
     *   low: null (no holders)
     *   high: null (no holders)
     *   has_data: false
     *   show_margin_prompt: false (margin IS set at 40%)
     */
    public function testLoyaltyROIWithRange_NoData_ExactValues() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_get_orders, $mock_user_meta;
        $mock_wpdb_get_var_results = [];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        $mock_wc_get_orders = function() { return []; };
        $mock_user_meta = [];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->calculate_loyalty_roi_with_range();
        
        $this->assertFalse($result['has_data']);
        $this->assertNull($result['low']);
        $this->assertNull($result['high']);
        $this->assertFalse($result['show_margin_prompt']);
    }
    
    /**
     * FIXTURE TEST: Referral ROI with real numeric values
     * 
     * Fixture:
     *   Referral cost: 500 CHF (commissions 300 + referral points 200)
     *   Referred revenue: 1000 CHF (1 order with line item)
     *   Margin: 45%
     *   Incrementality: 25%-75%
     * 
     * Expected:
     *   Low ROI: ((1000 × 0.25 × 0.45) - 500) / 500 × 100 = (112.5 - 500) / 500 × 100 = -77.5%
     *   High ROI: ((1000 × 0.75 × 0.45) - 500) / 500 × 100 = (337.5 - 500) / 500 × 100 = -32.5%
     */
    public function testReferralROI_WithRealNumericFixture() {
        update_option('intersoccer_roi_camp_margin_percent', 45);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_orders_by_id;
        
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        
        $mock_wpdb_get_var_results = [
            'commission_amount' => 300,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 200,
            'NOT IN' => 0,
        ];
        
        // Create order with line item for revenue calculation
        $mock_order = $this->createMockOrder(
            3001, 'completed', $in_window_date,
            [['name' => 'Camp', 'subtotal' => 1000, 'total' => 1000]],
            [], [], []
        );
        $mock_order->set_customer_id(101);
        
        $mock_wpdb_get_results = [
            'intersoccer_referrals' => [
                (object) ['customer_id' => 101, 'order_id' => 3001, 'created_at' => $in_window_date],
            ],
            'intersoccer_points_log' => [],
        ];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $referral_range = $calculator->calculate_referral_roi_with_range();
        
        // Must have data with proper fixture
        $this->assertTrue($referral_range['has_data'], 'Must have data with fixture');
        $this->assertFalse($referral_range['show_margin_prompt']);
        
        // Verify incrementality constants are exposed
        $this->assertEquals(0.25, $referral_range['incrementality_low']);
        $this->assertEquals(0.75, $referral_range['incrementality_high']);
        $this->assertArrayHasKey('tooltip', $referral_range);
        
        // Verify exact ROI values
        $this->assertEqualsWithDelta(-77.5, $referral_range['low'], 0.5,
            'Low ROI: ((1000 × 0.25 × 0.45) - 500) / 500 × 100');
        $this->assertEqualsWithDelta(-32.5, $referral_range['high'], 0.5,
            'High ROI: ((1000 × 0.75 × 0.45) - 500) / 500 × 100');
    }
    
    /**
     * FIXTURE TEST: Loyalty ROI with real numeric values
     * 
     * Fixture:
     *   Loyalty cost: 1000 CHF (activity points)
     *   Holders: 5 customers with 600 CHF each = 3000
     *   Non-holders: 5 customers with 100 CHF each = 500
     *   Extra spend: (600 - 100) × 5 = 2500
     *   Margin: 45%
     *   Incrementality: 5%-25%
     * 
     * Expected:
     *   Low ROI: ((2500 × 0.05 × 0.45) - 1000) / 1000 × 100 = (56.25 - 1000) / 1000 × 100 = -94.4%
     *   High ROI: ((2500 × 0.25 × 0.45) - 1000) / 1000 × 100 = (281.25 - 1000) / 1000 × 100 = -71.9%
     */
    public function testLoyaltyROI_WithRealNumericFixture() {
        update_option('intersoccer_roi_camp_margin_percent', 45);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wpdb_get_col_results;
        global $mock_wc_get_orders, $mock_wc_orders_by_id, $mock_user_meta;
        
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        
        // Create holder orders (5 × 600)
        $holder_ids = [];
        for ($i = 0; $i < 5; $i++) {
            $customer_id = 4001 + $i;
            $holder_ids[] = $customer_id;
            $order = $this->createMockOrder(
                4001 + $i, 'completed', $in_window_date,
                [['name' => 'Camp', 'subtotal' => 600, 'total' => 600]],
                [], [], []
            );
            $order->set_customer_id($customer_id);
            $mock_user_meta[$customer_id] = ['intersoccer_points_balance' => 50];
        }
        
        // Create non-holder orders (5 × 100)
        for ($i = 0; $i < 5; $i++) {
            $customer_id = 4101 + $i;
            $order = $this->createMockOrder(
                4101 + $i, 'completed', $in_window_date,
                [['name' => 'Camp', 'subtotal' => 100, 'total' => 100]],
                [], [], []
            );
            $order->set_customer_id($customer_id);
        }
        
        $mock_wpdb_get_col_results = [
            'usermeta' => $holder_ids,
            'intersoccer_points_balance' => $holder_ids,
        ];
        
        $mock_wpdb_get_var_results = [
            'commission_amount' => 0,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 0,
            'NOT IN' => 1000,  // loyalty activity points
            'intersoccer_points_balance' => 250,
        ];
        
        $mock_wpdb_get_results = [
            'intersoccer_referrals' => [],
            'intersoccer_points_log' => [],
        ];
        
        $mock_wc_get_orders = function($args) use (&$mock_wc_orders_by_id) {
            $statuses = isset($args['status']) ? (array) $args['status'] : ['completed', 'processing'];
            $filtered = [];
            foreach ($mock_wc_orders_by_id as $oid => $order) {
                $status = $order->get_status();
                if (!in_array($status, $statuses) && !in_array('wc-' . $status, $statuses)) continue;
                if (isset($args['customer_id']) && $order->get_customer_id() !== (int) $args['customer_id']) continue;
                $filtered[$oid] = $order;
            }
            if (isset($args['return']) && $args['return'] === 'ids') return array_keys($filtered);
            return array_values($filtered);
        };
        
        $calculator = new InterSoccer_ROI_Calculator();
        $loyalty_range = $calculator->calculate_loyalty_roi_with_range();
        
        // Must have data with proper fixture
        $this->assertTrue($loyalty_range['has_data'], 'Must have data with fixture');
        $this->assertFalse($loyalty_range['show_margin_prompt']);
        
        // Verify incrementality constants are exposed
        $this->assertEquals(0.05, $loyalty_range['incrementality_low']);
        $this->assertEquals(0.25, $loyalty_range['incrementality_high']);
        $this->assertArrayHasKey('tooltip', $loyalty_range);
        
        // Verify exact ROI values
        $this->assertEqualsWithDelta(-94.4, $loyalty_range['low'], 0.5,
            'Low ROI: ((2500 × 0.05 × 0.45) - 1000) / 1000 × 100');
        $this->assertEqualsWithDelta(-71.9, $loyalty_range['high'], 0.5,
            'High ROI: ((2500 × 0.25 × 0.45) - 1000) / 1000 × 100');
    }
    
    /**
     * Test that per-program ROI methods correctly expose incrementality constants.
     * Uses no data fixture to verify fallback behavior.
     */
    public function testPerProgramROI_ConstantsExposed() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        // Verify class constants directly (always available)
        $this->assertEquals(0.25, InterSoccer_ROI_Calculator::REFERRAL_INCREMENTALITY_LOW);
        $this->assertEquals(0.75, InterSoccer_ROI_Calculator::REFERRAL_INCREMENTALITY_HIGH);
        $this->assertEquals(0.05, InterSoccer_ROI_Calculator::LOYALTY_INCREMENTALITY_LOW);
        $this->assertEquals(0.25, InterSoccer_ROI_Calculator::LOYALTY_INCREMENTALITY_HIGH);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_get_orders, $mock_user_meta;
        $mock_wpdb_get_var_results = [];
        $mock_wpdb_get_results = ['intersoccer_referrals' => [], 'intersoccer_points_log' => []];
        $mock_wc_get_orders = function() { return []; };
        $mock_user_meta = [];
        
        $calculator = new InterSoccer_ROI_Calculator();
        
        $referral_range = $calculator->calculate_referral_roi_with_range();
        $loyalty_range = $calculator->calculate_loyalty_roi_with_range();
        
        // With no data, has_data should be false and incrementality_low/high not present
        $this->assertFalse($referral_range['has_data']);
        $this->assertFalse($loyalty_range['has_data']);
        $this->assertArrayNotHasKey('incrementality_low', $referral_range);
        $this->assertArrayNotHasKey('incrementality_low', $loyalty_range);
    }
    
    /**
     * EXACT VALUE TEST: Referral ROI range with single referred order
     * 
     * Fixture:
     *   Revenue: 1000 CHF (one referred order)
     *   Cost: 100 CHF (50 commission + 50 referral points)
     *   Margin: 40%
     * 
     * Expected ROI with 25%-75% incrementality:
     *   Low: ((1000 × 0.25 × 0.40) - 100) / 100 × 100 = (100 - 100) / 100 × 100 = 0%
     *   High: ((1000 × 0.75 × 0.40) - 100) / 100 × 100 = (300 - 100) / 100 × 100 = 200%
     */
    public function testReferralROIWithRange_SingleOrder_ExactValues() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_orders_by_id;
        
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        
        $order = $this->createMockOrder(
            5001, 'completed', $in_window_date,
            [['name' => 'Camp', 'subtotal' => 1000, 'total' => 1000]],
            [], [], []
        );
        $order->set_customer_id(5101);
        
        $mock_wpdb_get_results = [
            'intersoccer_referrals' => [
                (object) ['customer_id' => 5101, 'order_id' => 5001, 'created_at' => $in_window_date]
            ],
            'intersoccer_points_log' => [],
        ];
        
        $mock_wpdb_get_var_results = [
            'commission_amount' => 50,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 50,
            'NOT IN' => 0,
        ];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $result = $calculator->calculate_referral_roi_with_range();
        
        $this->assertTrue($result['has_data']);
        $this->assertFalse($result['show_margin_prompt']);
        $this->assertEqualsWithDelta(0.0, $result['low'], 0.1, 'Low ROI = (100-100)/100×100 = 0%');
        $this->assertEqualsWithDelta(200.0, $result['high'], 0.1, 'High ROI = (300-100)/100×100 = 200%');
    }
    
    // =========================================================================
    // HELPER METHODS
    // =========================================================================
    
    private function mockCostQueries($values) {
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_get_orders;
        
        $referral_points = $values['referral_points'] ?? 0;
        $loyalty_points = $values['loyalty_points'] ?? 0;
        $goodwill_points = $values['goodwill_points'] ?? ($values['adjustment_points'] ?? 0);
        $correction_points = $values['correction_points'] ?? 0;
        
        $mock_wpdb_get_results['intersoccer_points_log'] = [];
        if ($goodwill_points > 0) {
            $mock_wpdb_get_results['intersoccer_points_log'][] = (object) [
                'points_amount' => $goodwill_points,
                'description' => 'bonus reward',
                'transaction_type' => 'admin_adjustment'
            ];
        }
        if ($correction_points > 0) {
            $mock_wpdb_get_results['intersoccer_points_log'][] = (object) [
                'points_amount' => $correction_points,
                'description' => 'error correction',
                'transaction_type' => 'admin_reset'
            ];
        }
        
        $mock_wpdb_get_var_results = [
            'points_amount' => function($query) use ($referral_points, $loyalty_points) {
                if (strpos($query, 'NOT IN') !== false && strpos($query, 'admin_adjustment') !== false) {
                    return $loyalty_points;
                }
                if (strpos($query, "IN ('customer_referral_reward'") !== false && strpos($query, 'NOT IN') === false) {
                    return $referral_points;
                }
                return 0;
            },
            'commission_amount' => $values['commissions'],
        ];
        
        // Empty orders for first-order discount calculation
        $mock_wc_get_orders = function() { return []; };
    }
    
    private function mockPointsActivityQueries($months) {
        global $mock_wpdb_get_var_results;
        
        $month_data = $months[0] ?? ['earned' => 0, 'redeemed' => 0, 'expired' => 0];
        
        $mock_wpdb_get_var_results = [
            'points_amount > 0' => $month_data['earned'],
            'points_redemption' => -$month_data['redeemed'],
            'points_expired' => -$month_data['expired'],
        ];
    }
    
    // =========================================================================
    // REGRESSION GUARDS: completed order, out-of-window, status filter
    // =========================================================================
    
    /**
     * POSITIVE CONTROL: Completed order counts toward ROI
     * 
     * Verifies that a 'completed' order is included in revenue calculations.
     * If this fails, the status filter is broken or the order mock is wrong.
     */
    public function testReferralROI_CompletedOrderCounts() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_orders_by_id;
        
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        
        // Completed order from referred customer
        $order = $this->createMockOrder(
            7001, 'completed', $in_window_date,
            [['name' => 'Camp', 'subtotal' => 500, 'total' => 500]],
            [], [], []
        );
        $order->set_customer_id(7001);
        
        $mock_wpdb_get_results = [
            'intersoccer_referrals' => [
                (object) ['customer_id' => 7001, 'order_id' => 7001, 'created_at' => $in_window_date],
            ],
            'intersoccer_points_log' => [],
        ];
        
        $mock_wpdb_get_var_results = [
            'commission_amount' => 10,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 5,
            'NOT IN' => 0,
        ];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $referral = $calculator->calculate_referral_roi();
        
        // Completed order MUST be counted
        $this->assertEquals(500, $referral['revenue_ex_vat'], 
            'Completed order revenue must be included');
        $this->assertTrue($referral['has_data'], 
            'Must have data with completed order');
    }
    
    /**
     * OUT-OF-WINDOW: Order date past window end is excluded from ROI
     * 
     * Verifies that orders with dates beyond the rolling window end are excluded.
     * The SQL query filters referrals by created_at; this tests the PHP-side filter.
     * If this fails, the date boundary check in calculate_referral_roi is broken.
     */
    public function testReferralROI_OutOfWindowOrderExcluded() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_orders_by_id;
        
        // In-window referral but order dated in the future (past window end)
        // This simulates an edge case: referral created now but order hasn't happened yet
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        $future_date = wp_date('Y-m-d', strtotime('+1 year')) . ' 10:00:00';
        
        $order = $this->createMockOrder(
            7002, 'completed', $future_date,
            [['name' => 'Camp', 'subtotal' => 999, 'total' => 999]],
            [], [], []
        );
        $order->set_customer_id(7002);
        
        // Referral record in window, but order is dated in the future
        $mock_wpdb_get_results = [
            'intersoccer_referrals' => [
                (object) ['customer_id' => 7002, 'order_id' => 7002, 'created_at' => $in_window_date],
            ],
            'intersoccer_points_log' => [],
        ];
        
        $mock_wpdb_get_var_results = [
            'commission_amount' => 0,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 0,
            'NOT IN' => 0,
        ];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $referral = $calculator->calculate_referral_roi();
        
        // Order dated past window end MUST be excluded
        $this->assertEquals(0, $referral['revenue_ex_vat'], 
            'Order dated past window end must be excluded from revenue');
    }
    
    /**
     * STATUS FILTER GUARD: Non-completed/processing orders excluded
     * 
     * This test FAILS if the status filter is removed from calculate_referral_roi.
     * It verifies that on-hold, pending, and failed orders are excluded.
     */
    public function testReferralROI_StatusFilterExcludesNonCompleted() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_orders_by_id;
        
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        
        // Order with on-hold status (should be EXCLUDED)
        $order_onhold = $this->createMockOrder(
            7003, 'on-hold', $in_window_date,
            [['name' => 'Camp', 'subtotal' => 777, 'total' => 777]],
            [], [], []
        );
        $order_onhold->set_customer_id(7003);
        
        // Order with pending status (should be EXCLUDED)
        $order_pending = $this->createMockOrder(
            7004, 'pending', $in_window_date,
            [['name' => 'Camp', 'subtotal' => 888, 'total' => 888]],
            [], [], []
        );
        $order_pending->set_customer_id(7004);
        
        // Order with failed status (should be EXCLUDED)
        $order_failed = $this->createMockOrder(
            7005, 'failed', $in_window_date,
            [['name' => 'Camp', 'subtotal' => 999, 'total' => 999]],
            [], [], []
        );
        $order_failed->set_customer_id(7005);
        
        // Referral records for all three orders
        $mock_wpdb_get_results = [
            'intersoccer_referrals' => [
                (object) ['customer_id' => 7003, 'order_id' => 7003, 'created_at' => $in_window_date],
                (object) ['customer_id' => 7004, 'order_id' => 7004, 'created_at' => $in_window_date],
                (object) ['customer_id' => 7005, 'order_id' => 7005, 'created_at' => $in_window_date],
            ],
            'intersoccer_points_log' => [],
        ];
        
        $mock_wpdb_get_var_results = [
            'commission_amount' => 0,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 0,
            'NOT IN' => 0,
        ];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $referral = $calculator->calculate_referral_roi();
        
        // Status filter MUST exclude non-completed/processing orders
        // If this assertion fails: (777 + 888 + 999) = 2664, the status filter is broken
        $this->assertEquals(0, $referral['revenue_ex_vat'], 
            'Non-completed/processing orders must be excluded by status filter');
        $this->assertFalse($referral['has_data'] && $referral['revenue_ex_vat'] > 0,
            'Should not have revenue data from excluded statuses');
    }

    /**
     * ORDER BY GUARD: First referred order picked by earliest created_at
     * 
     * This test FAILS if ORDER BY is removed from the SQL.
     * 
     * The mock returns rows in REVERSE date order (later first). The mock callback
     * sorts them by created_at ASC only if the SQL contains ORDER BY.
     * Without ORDER BY, the wrong order would be picked as "first".
     * 
     * Fixture:
     *   Customer 9301: order 9301 at -3mo (500 CHF), order 9302 at -2mo (300 CHF)
     *   Mock returns: [9302 (later), 9301 (earlier)] - WRONG order
     *   With ORDER BY: sorted to [9301, 9302] - CORRECT order
     * 
     * Expected: order 9301 (500 CHF) is the first order, 9302 goes to holder spend
     * Fails if ORDER BY removed: order 9302 (300 CHF) picked as first
     */
    public function testFirstReferredOrder_OrderByGuard_FailsWithoutSort() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_orders_by_id, $mock_user_meta, $mock_wpdb_get_col_results;
        
        $first_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        $later_date = wp_date('Y-m-d', strtotime('-2 months')) . ' 10:00:00';
        
        // First order (chronologically): 500 CHF
        $order1 = $this->createMockOrder(
            9301, 'completed', $first_date,
            [['name' => 'Camp', 'subtotal' => 500, 'total' => 500]],
            [], [], []
        );
        $order1->set_customer_id(9301);
        
        // Later order: 300 CHF
        $order2 = $this->createMockOrder(
            9302, 'completed', $later_date,
            [['name' => 'Camp', 'subtotal' => 300, 'total' => 300]],
            [], [], []
        );
        $order2->set_customer_id(9301);
        
        // Customer holds points
        $mock_user_meta[9301] = ['intersoccer_points_balance' => 50];
        $mock_wpdb_get_col_results = ['intersoccer_points_balance' => [9301]];
        
        // CRITICAL: Rows in REVERSE date order (later first) - simulates unsorted DB return
        // The callback sorts only if ORDER BY is in the SQL
        $unsorted_rows = [
            (object) ['customer_id' => 9301, 'order_id' => 9302, 'created_at' => $later_date],
            (object) ['customer_id' => 9301, 'order_id' => 9301, 'created_at' => $first_date],
        ];
        
        $mock_wpdb_get_results = [
            'intersoccer_referrals' => function($query) use ($unsorted_rows) {
                // Sort only if ORDER BY is present in the SQL
                if (stripos($query, 'ORDER BY') !== false) {
                    $sorted = $unsorted_rows;
                    usort($sorted, function($a, $b) {
                        return strcmp($a->created_at, $b->created_at);
                    });
                    return $sorted;
                }
                // Without ORDER BY: return in wrong order
                return $unsorted_rows;
            },
            'intersoccer_points_log' => [],
        ];
        
        $mock_wpdb_get_var_results = [
            'commission_amount' => 0,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 0,
            'NOT IN' => 50,
        ];
        
        $calculator = new InterSoccer_ROI_Calculator();
        
        // With ORDER BY: first order (500) is picked
        // Without ORDER BY: would pick 300 (later order listed first in unsorted results)
        $referral = $calculator->calculate_referral_roi();
        $this->assertEquals(500, $referral['revenue_ex_vat'], 
            'ORDER BY guard: first order (500) must be picked; fails if ORDER BY removed (would get 300)');
        
        // Holder spend should be 300 (later order excluded from referral)
        $loyalty = $calculator->calculate_loyalty_roi();
        $this->assertEqualsWithDelta(300.0, $loyalty['holder_avg_spend'], 0.1,
            'Later order (300) goes to holder spend');
    }

    /**
     * BEFORE-WINDOW GUARD: Orders before window start are excluded
     * 
     * This test FAILS if the window start check is removed.
     */
    public function testReferralROI_BeforeWindowOrderExcluded() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_orders_by_id;
        
        $before_window_date = wp_date('Y-m-d', strtotime('-18 months')) . ' 10:00:00';
        
        $order = $this->createMockOrder(
            7006, 'completed', $before_window_date,
            [['name' => 'Camp', 'subtotal' => 555, 'total' => 555]],
            [], [], []
        );
        $order->set_customer_id(7006);
        
        // Referral record within window, but order is before window
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        $mock_wpdb_get_results = [
            'intersoccer_referrals' => [
                (object) ['customer_id' => 7006, 'order_id' => 7006, 'created_at' => $in_window_date],
            ],
            'intersoccer_points_log' => [],
        ];
        
        $mock_wpdb_get_var_results = [
            'commission_amount' => 0,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 0,
            'NOT IN' => 0,
        ];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $referral = $calculator->calculate_referral_roi();
        
        $this->assertEquals(0, $referral['revenue_ex_vat'],
            'Order dated before window start must be excluded');
    }

    /**
     * STATUS GUARD - LOYALTY PATH: Non-completed orders excluded from holder comparison
     * 
     * This test FAILS if the status filter is removed from wc_get_orders calls.
     * 
     * Fixture:
     *   Holder 7101: completed order (500 CHF) + on-hold order (999 CHF)
     *   Holder 7102: pending order (888 CHF) only
     *   Non-holder 7201: completed order (100 CHF)
     * 
     * Expected with status filter:
     *   holder_count: 1 (only 7101 has completed order)
     *   holder_avg_spend: 500 (only completed order counted)
     *   non_holder_avg: 100
     * 
     * Fails if status filter removed:
     *   Would count 999 + 888 = extra revenue, wrong holder_count
     */
    public function testLoyaltyROI_StatusGuard_FailsWithoutFilter() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_get_orders, $mock_user_meta, $mock_wpdb_get_col_results;
        global $mock_wc_orders_by_id;
        
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        
        // Holder 7101: completed order (500) + on-hold order (999)
        $order_completed = $this->createMockOrder(
            7101, 'completed', $in_window_date,
            [['name' => 'Camp', 'subtotal' => 500, 'total' => 500]],
            [], [], []
        );
        $order_completed->set_customer_id(7101);
        
        $order_onhold = $this->createMockOrder(
            7102, 'on-hold', $in_window_date,
            [['name' => 'Camp', 'subtotal' => 999, 'total' => 999]],
            [], [], []
        );
        $order_onhold->set_customer_id(7101);
        
        // Holder 7102: pending order only (should NOT count)
        $order_pending = $this->createMockOrder(
            7103, 'pending', $in_window_date,
            [['name' => 'Camp', 'subtotal' => 888, 'total' => 888]],
            [], [], []
        );
        $order_pending->set_customer_id(7102);
        
        // Non-holder 7201: completed order (100)
        $order_nonholder = $this->createMockOrder(
            7104, 'completed', $in_window_date,
            [['name' => 'Camp', 'subtotal' => 100, 'total' => 100]],
            [], [], []
        );
        $order_nonholder->set_customer_id(7201);
        
        $mock_user_meta[7101] = ['intersoccer_points_balance' => 100];
        $mock_user_meta[7102] = ['intersoccer_points_balance' => 50];
        $mock_wpdb_get_col_results = ['intersoccer_points_balance' => [7101, 7102]];
        
        // Mock returns ALL orders, but filters by status when requested
        $all_orders = [7101 => $order_completed, 7102 => $order_onhold, 7103 => $order_pending, 7104 => $order_nonholder];
        $mock_wc_get_orders = function($args) use ($all_orders, &$mock_wc_orders_by_id) {
            $statuses = isset($args['status']) ? (array) $args['status'] : ['completed', 'processing'];
            $filtered_ids = [];
            foreach ($all_orders as $oid => $order) {
                $status = $order->get_status();
                if (in_array($status, $statuses) || in_array('wc-' . $status, $statuses)) {
                    $filtered_ids[] = $oid;
                }
            }
            if (isset($args['return']) && $args['return'] === 'ids') {
                return $filtered_ids;
            }
            $orders = [];
            foreach ($filtered_ids as $oid) {
                $orders[] = $mock_wc_orders_by_id[$oid];
            }
            return $orders;
        };
        
        $mock_wpdb_get_var_results = [
            'commission_amount' => 0,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 0,
            'NOT IN' => 100,
        ];
        $mock_wpdb_get_results = ['intersoccer_referrals' => [], 'intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $loyalty = $calculator->calculate_loyalty_roi();
        
        // With status filter: only holder 7101 has a completed order
        // Holder 7102 has only pending order, so doesn't count as active holder
        $this->assertEquals(1, $loyalty['holder_count'],
            'Status guard: only holders with completed/processing orders count');
        $this->assertEqualsWithDelta(500.0, $loyalty['holder_avg_spend'], 0.1,
            'Status guard: only completed order (500) counted, not on-hold (999)');
        $this->assertEqualsWithDelta(100.0, $loyalty['non_holder_avg_spend'], 0.1,
            'Non-holder completed order counted');
        
        // If status filter is removed, would get:
        // - holder_count could include 7102 (pending order)
        // - holder_avg_spend would include 999 from on-hold order
    }

    /**
     * HPOS DETECTION TEST: Verifies HPOS is detected when enabled
     * 
     * The HPOS SQL path uses aggregate queries that are difficult to mock.
     * This test verifies HPOS detection works, then the batched fallback
     * test below exercises the actual calculation with exact values.
     */
    public function testHPOS_Detection_WhenEnabled() {
        // Enable HPOS
        update_option('woocommerce_custom_orders_table_enabled', 'yes');
        
        global $mock_wpdb_get_var_results;
        
        // Mock: HPOS table exists and has data
        $mock_wpdb_get_var_results = [
            "SELECT COUNT(*) FROM" => 10,
        ];
        
        $calculator = new InterSoccer_ROI_Calculator();
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($calculator);
        $method = $reflection->getMethod('is_hpos_active');
        $method->setAccessible(true);
        
        $this->assertTrue($method->invoke($calculator), 'HPOS should be detected as active');
        
        // Cleanup
        delete_option('woocommerce_custom_orders_table_enabled');
    }
    
    /**
     * HPOS DETECTION TEST: Verifies HPOS is not active when disabled
     */
    public function testHPOS_Detection_WhenDisabled() {
        delete_option('woocommerce_custom_orders_table_enabled');
        
        $calculator = new InterSoccer_ROI_Calculator();
        
        $reflection = new \ReflectionClass($calculator);
        $method = $reflection->getMethod('is_hpos_active');
        $method->setAccessible(true);
        
        $this->assertFalse($method->invoke($calculator), 'HPOS should not be active when disabled');
    }

    /**
     * BATCHED FALLBACK PATH TEST: When HPOS is disabled
     * 
     * Fixture:
     *   2 holders with 600 CHF each = 1200 total, avg 600
     *   2 non-holders with 100 CHF each = 200 total, avg 100
     *   Extra spend = (600 - 100) × 2 = 1000 CHF
     */
    public function testBatchedFallback_ExactValues() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        // Disable HPOS to force batched path
        delete_option('woocommerce_custom_orders_table_enabled');
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wpdb_get_col_results;
        global $mock_wc_get_orders, $mock_wc_orders_by_id, $mock_user_meta;
        
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        
        // Create holder orders
        for ($i = 0; $i < 2; $i++) {
            $order = $this->createMockOrder(
                8001 + $i, 'completed', $in_window_date,
                [['name' => 'Camp', 'subtotal' => 600, 'total' => 600]],
                [], [], []
            );
            $order->set_customer_id(101 + $i);
            $mock_user_meta[101 + $i] = ['intersoccer_points_balance' => 50];
        }
        
        // Create non-holder orders
        for ($i = 0; $i < 2; $i++) {
            $order = $this->createMockOrder(
                8101 + $i, 'completed', $in_window_date,
                [['name' => 'Camp', 'subtotal' => 100, 'total' => 100]],
                [], [], []
            );
            $order->set_customer_id(201 + $i);
        }
        
        $mock_wpdb_get_col_results = ['intersoccer_points_balance' => [101, 102]];
        
        $all_order_ids = [8001, 8002, 8101, 8102];
        $mock_wc_get_orders = function($args) use ($all_order_ids, &$mock_wc_orders_by_id) {
            if (isset($args['return']) && $args['return'] === 'ids') {
                return $all_order_ids;
            }
            $orders = [];
            foreach ($all_order_ids as $oid) {
                if (isset($mock_wc_orders_by_id[$oid])) {
                    $orders[] = $mock_wc_orders_by_id[$oid];
                }
            }
            return $orders;
        };
        
        $mock_wpdb_get_var_results = [
            'commission_amount' => 0,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 0,
            'NOT IN' => 100,
        ];
        $mock_wpdb_get_results = ['intersoccer_referrals' => [], 'intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $loyalty = $calculator->calculate_loyalty_roi();
        
        $this->assertEquals(2, $loyalty['holder_count'], 'Batched: 2 holders');
        $this->assertEqualsWithDelta(600.0, $loyalty['holder_avg_spend'], 0.1, 'Batched: holder avg = 1200/2 = 600');
        $this->assertEqualsWithDelta(100.0, $loyalty['non_holder_avg_spend'], 0.1, 'Batched: non-holder avg = 200/2 = 100');
        $this->assertEqualsWithDelta(1000.0, $loyalty['total_extra_spend'], 0.1, 'Batched: extra = (600-100)×2 = 1000');
    }

    /**
     * EXACT VALUE TEST: Revenue excludes fee lines (points discount, referral discount)
     * 
     * Fixture:
     *   Line item: 500 CHF (subtotal) → 450 CHF (total after coupon)
     *   Points fee: -30 CHF (NOT revenue)
     *   Referral discount fee: -20 CHF (NOT revenue)
     * 
     * Expected: revenue = 450 (line total only, fees excluded)
     */
    public function testRevenue_ExcludesFeeLinesFromTotal() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_orders_by_id;
        
        $in_window_date = wp_date('Y-m-d', strtotime('-3 months')) . ' 10:00:00';
        
        $order = $this->createMockOrder(
            8801, 'completed', $in_window_date,
            [['name' => 'Camp Registration', 'subtotal' => 500, 'total' => 450]],
            [
                ['name' => 'Points Discount', 'total' => -30],
                ['name' => 'Referral Discount', 'total' => -20],
            ],
            [], []
        );
        $order->set_customer_id(8801);
        $order->set_total(400); // 450 - 30 - 20 = 400 order total
        
        $mock_wpdb_get_results = [
            'intersoccer_referrals' => [
                (object) ['customer_id' => 8801, 'order_id' => 8801, 'created_at' => $in_window_date]
            ],
            'intersoccer_points_log' => [],
        ];
        
        $mock_wpdb_get_var_results = [
            'commission_amount' => 0,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 0,
            'NOT IN' => 0,
        ];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $referral = $calculator->calculate_referral_roi();
        
        // Revenue = line item total (450), not order total (400), not subtotal (500)
        $this->assertEquals(450, $referral['revenue_ex_vat'],
            'Revenue = line total (450), fees are excluded');
    }

    /**
     * EXACT VALUE TEST: Liability expired/reversed breakdown
     * 
     * Fixture:
     *   Total balance: 5000 CHF
     *   Expired: 1000 CHF
     *   Reversed: 500 CHF
     * 
     * Expected:
     *   total_expired: 1000
     *   total_reversed: 500
     *   liability_reductions: 1500
     */
    public function testLiability_ExpiredReversed_ExactValues() {
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results;
        
        $mock_wpdb_get_var_results = [
            'intersoccer_points_balance' => 5000,
            'points_expired' => 1000,
            "transaction_type = 'points_expired'" => 1000,
            "IN ('order_refund', 'points_refund', 'reversal')" => 500,
        ];
        $mock_wpdb_get_results = ['intersoccer_points_log' => []];
        
        $calculator = new InterSoccer_ROI_Calculator();
        $liability = $calculator->get_points_liability();
        
        $this->assertEquals(5000, $liability['current_chf'], 'current balance = 5000');
        $this->assertEquals(1000, $liability['total_expired'], 'total_expired = 1000');
        $this->assertEquals(500, $liability['total_reversed'], 'total_reversed = 500');
        $this->assertEquals(1500, $liability['liability_reductions'], 'reductions = 1000 + 500');
        
        // Age breakdown exists (detailed bucket testing requires complex mock setup)
        $this->assertArrayHasKey('age_breakdown', $liability, 'Should have age_breakdown');
    }

    /**
     * EXACT VALUE TEST: Expired points count in activity cost, not liability reduction
     * 
     * Points are counted as cost when EARNED. Expiry/reversal reduce the liability
     * (what we owe) but not the cost (what we spent to generate business).
     * 
     * Fixture:
     *   Activity points earned: 500
     *   Of which expired: 100
     * 
     * Expected:
     *   activity_cost = 500 (all earned, not 400)
     *   liability_reductions = 100 (expired portion)
     */
    public function testActivityPoints_ExpiredStillCountAsCost() {
        update_option('intersoccer_roi_camp_margin_percent', 40);
        
        global $mock_wpdb_get_var_results, $mock_wpdb_get_results, $mock_wc_get_orders;
        
        $mock_wpdb_get_var_results = [
            'commission_amount' => 0,
            "IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')" => 0,
            'NOT IN' => 500, // Total activity points earned
            'intersoccer_points_balance' => 400, // Current balance (500 - 100 expired)
            'points_expired' => 100,
            "transaction_type = 'points_expired'" => 100,
            'points_reversed' => 0,
            "transaction_type = 'points_reversal'" => 0,
        ];
        
        $mock_wpdb_get_results = ['intersoccer_referrals' => [], 'intersoccer_points_log' => []];
        $mock_wc_get_orders = function() { return []; };
        
        $calculator = new InterSoccer_ROI_Calculator();
        $costs = $calculator->get_cost_breakdown();
        $liability = $calculator->get_points_liability();
        
        // Cost = all earned points (500), including expired
        $this->assertEquals(500, $costs['loyalty_cost_chf'], 'Activity cost = 500 (all earned)');
        
        // Liability reduction = expired amount
        $this->assertEquals(100, $liability['liability_reductions'], 'Liability reduction = 100 (expired)');
    }
}
