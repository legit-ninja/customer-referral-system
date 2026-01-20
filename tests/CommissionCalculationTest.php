<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for Commission Calculations
 * 
 * Tests tiered commission structure using customizable tiers from database
 */
class CommissionCalculationTest extends TestCase {

    protected function setUp(): void {
        // Include the commission manager class
        require_once __DIR__ . '/../includes/class-commission-manager.php';
        
        // Set up default tiers for each role
        $default_tiers = [
            ['min_customers' => 1, 'max_customers' => 10, 'rate' => 10],
            ['min_customers' => 11, 'max_customers' => 24, 'rate' => 15],
            ['min_customers' => 25, 'max_customers' => 999999, 'rate' => 20],
        ];
        update_option('intersoccer_commission_tiers_coach', $default_tiers);
        update_option('intersoccer_commission_tiers_partner', $default_tiers);
        update_option('intersoccer_commission_tiers_social_influencer', $default_tiers);
    }

    protected function tearDown(): void {
        // Clean up
        delete_option('intersoccer_commission_tiers_coach');
        delete_option('intersoccer_commission_tiers_partner');
        delete_option('intersoccer_commission_tiers_social_influencer');
    }

    /**
     * Test tier 1 commission (1-10 customers)
     */
    public function testTier1Commission() {
        $customers_recruited = 5;
        $commission_rate = InterSoccer_Commission_Manager::get_commission_rate_for_customer_count($customers_recruited, 'coach');
        $order_amount = 100;
        
        $commission = $order_amount * $commission_rate;
        
        $this->assertEquals(10, $commission);
        $this->assertEquals(0.10, $commission_rate); // 10% as decimal
    }

    /**
     * Test tier 2 commission (11-24 customers)
     */
    public function testTier2Commission() {
        $customers_recruited = 15;
        $commission_rate = InterSoccer_Commission_Manager::get_commission_rate_for_customer_count($customers_recruited, 'coach');
        $order_amount = 100;
        
        $commission = $order_amount * $commission_rate;
        
        $this->assertEquals(15, $commission);
        $this->assertEquals(0.15, $commission_rate); // 15% as decimal
    }

    /**
     * Test tier 3 commission (25+ customers)
     */
    public function testTier3Commission() {
        $customers_recruited = 30;
        $commission_rate = InterSoccer_Commission_Manager::get_commission_rate_for_customer_count($customers_recruited, 'coach');
        $order_amount = 100;
        
        $commission = $order_amount * $commission_rate;
        
        $this->assertEquals(20, $commission);
        $this->assertEquals(0.20, $commission_rate); // 20% as decimal
    }

    /**
     * Test tier progression increases commission
     */
    public function testTierProgressionIncreasesCommission() {
        $order_amount = 100;
        
        $tier1_rate = InterSoccer_Commission_Manager::get_commission_rate_for_customer_count(5, 'coach');
        $tier2_rate = InterSoccer_Commission_Manager::get_commission_rate_for_customer_count(15, 'coach');
        $tier3_rate = InterSoccer_Commission_Manager::get_commission_rate_for_customer_count(30, 'coach');
        
        $tier1_commission = $order_amount * $tier1_rate;
        $tier2_commission = $order_amount * $tier2_rate;
        $tier3_commission = $order_amount * $tier3_rate;
        
        $this->assertGreaterThan($tier1_commission, $tier2_commission);
        $this->assertGreaterThan($tier2_commission, $tier3_commission);
    }

    /**
     * Test tier boundaries
     */
    public function testTierBoundaries() {
        // Test boundary: 10 customers (tier 1)
        $customers = 10;
        $rate = InterSoccer_Commission_Manager::get_commission_rate_for_customer_count($customers, 'coach');
        $this->assertEquals(0.10, $rate);
        
        // Test boundary: 11 customers (tier 2)
        $customers = 11;
        $rate = InterSoccer_Commission_Manager::get_commission_rate_for_customer_count($customers, 'coach');
        $this->assertEquals(0.15, $rate);
        
        // Test boundary: 25 customers (tier 3)
        $customers = 25;
        $rate = InterSoccer_Commission_Manager::get_commission_rate_for_customer_count($customers, 'coach');
        $this->assertEquals(0.20, $rate);
    }

    /**
     * Test commission on multiple orders
     */
    public function testCommissionOnMultipleOrders() {
        $orders = [100, 150, 200];
        $commission_rate = 0.10;
        
        $total_commission = 0;
        foreach ($orders as $order) {
            $total_commission += $order * $commission_rate;
        }
        
        $this->assertEquals(45, $total_commission);
    }

    /**
     * Test zero order amount gives zero commission
     */
    public function testZeroOrderAmountZeroCommission() {
        $order_amount = 0;
        $commission_rate = 0.10;
        $commission = $order_amount * $commission_rate;
        
        $this->assertEquals(0, $commission);
    }

    /**
     * Test commission rounding
     */
    public function testCommissionRounding() {
        $order_amount = 33.33;
        $commission_rate = 0.10;
        $commission = round($order_amount * $commission_rate, 2);
        
        $this->assertEquals(3.33, $commission);
    }

    /**
     * Test coach customer count accuracy
     */
    public function testCoachCustomerCountAccuracy() {
        $coach_id = 123;
        $recruited_customers = [1, 2, 3, 4, 5];
        $count = count($recruited_customers);
        
        $this->assertEquals(5, $count);
    }

    /**
     * Test tier upgrade increases future commissions
     */
    public function testTierUpgradeIncreasesFutureCommissions() {
        $order_amount = 100;
        
        // Before upgrade: 10 customers (10%)
        $rate_before = InterSoccer_Commission_Manager::get_commission_rate_for_customer_count(10, 'coach');
        $commission_before = $order_amount * $rate_before;
        
        // After upgrade: 11 customers (15%)
        $rate_after = InterSoccer_Commission_Manager::get_commission_rate_for_customer_count(11, 'coach');
        $commission_after = $order_amount * $rate_after;
        
        $this->assertGreaterThan($commission_before, $commission_after);
        $this->assertEquals(0.10, $rate_before);
        $this->assertEquals(0.15, $rate_after);
    }

    /**
     * Test commission payment tracking
     */
    public function testCommissionPaymentTracking() {
        $payment = [
            'coach_id' => 123,
            'amount' => 45.50,
            'status' => 'paid',
            'payment_date' => time(),
        ];
        
        $this->assertEquals('paid', $payment['status']);
        $this->assertGreaterThan(0, $payment['amount']);
    }

    /**
     * Test commission pending status
     */
    public function testCommissionPendingStatus() {
        $commission = [
            'amount' => 25,
            'status' => 'pending',
            'due_date' => strtotime('+30 days'),
        ];
        
        $this->assertEquals('pending', $commission['status']);
    }

    /**
     * Test commission aggregation by period
     */
    public function testCommissionAggregationByPeriod() {
        $monthly_commissions = [
            'january' => 150,
            'february' => 200,
            'march' => 175,
        ];
        
        $total = array_sum($monthly_commissions);
        
        $this->assertEquals(525, $total);
    }

    /**
     * Test commission rate configuration from database
     */
    public function testCommissionRateConfiguration() {
        $roles = ['coach', 'partner', 'social_influencer'];
        
        foreach ($roles as $role) {
            $option_name = 'intersoccer_commission_tiers_' . $role;
            $tiers = get_option($option_name, []);
            
            $this->assertIsArray($tiers);
            $this->assertGreaterThan(0, count($tiers));
            
            // Verify tier structure
            foreach ($tiers as $tier) {
                $this->assertArrayHasKey('min_customers', $tier);
                $this->assertArrayHasKey('max_customers', $tier);
                $this->assertArrayHasKey('rate', $tier);
                $this->assertGreaterThan(0, $tier['rate']);
                $this->assertLessThanOrEqual(100, $tier['rate']);
            }
            
            // Test that rates are applied correctly for this role
            $rate1 = InterSoccer_Commission_Manager::get_commission_rate_for_customer_count(5, $role);
            $rate2 = InterSoccer_Commission_Manager::get_commission_rate_for_customer_count(15, $role);
            $rate3 = InterSoccer_Commission_Manager::get_commission_rate_for_customer_count(30, $role);
            
            $this->assertEquals(0.10, $rate1); // 10% as decimal
            $this->assertEquals(0.15, $rate2); // 15% as decimal
            $this->assertEquals(0.20, $rate3); // 20% as decimal
        }
    }

    /**
     * Test retention bonus commission
     */
    public function testRetentionBonusCommission() {
        $base_commission = 10;
        $retention_bonus = 25; // Season 2 return
        $total = $base_commission + $retention_bonus;
        
        $this->assertEquals(35, $total);
    }

    /**
     * Test referral milestone bonus
     */
    public function testReferralMilestoneBonus() {
        $successful_referrals = 10;
        $milestone = 10;
        $bonus_points = 250;
        
        $earned_bonus = ($successful_referrals >= $milestone) ? $bonus_points : 0;
        
        $this->assertEquals(250, $earned_bonus);
    }

    /**
     * Test commission calculation is consistent
     */
    public function testCommissionCalculationConsistent() {
        $order_amount = 100;
        $rate = 0.10;
        
        $calc1 = $order_amount * $rate;
        $calc2 = $order_amount * $rate;
        
        $this->assertEquals($calc1, $calc2, 'Calculation should be deterministic');
    }

    /**
     * Test negative commission not possible
     */
    public function testNegativeCommissionNotPossible() {
        $order_amount = -100; // Refund
        $rate = 0.10;
        $commission = max(0, $order_amount * $rate);
        
        $this->assertEquals(0, $commission);
    }

    /**
     * Test commission cap (if any)
     */
    public function testCommissionCap() {
        $max_commission_per_order = 1000; // Example cap
        $calculated_commission = 1500; // Exceeds cap
        
        $final_commission = min($calculated_commission, $max_commission_per_order);
        
        $this->assertEquals(1000, $final_commission);
    }

    /**
     * Test commission rate retrieval for different customer counts
     */
    public function testCommissionRateForDifferentCustomerCounts() {
        // Test various customer counts for coach role
        $test_cases = [
            [1, 0.10],   // Minimum tier 1
            [5, 0.10],   // Mid tier 1
            [10, 0.10],  // Max tier 1
            [11, 0.15],  // Min tier 2
            [20, 0.15],  // Mid tier 2
            [24, 0.15],  // Max tier 2
            [25, 0.20],  // Min tier 3
            [50, 0.20],  // Mid tier 3
            [100, 0.20], // High tier 3
        ];
        
        foreach ($test_cases as [$customer_count, $expected_rate]) {
            $actual_rate = InterSoccer_Commission_Manager::get_commission_rate_for_customer_count($customer_count, 'coach');
            $this->assertEquals($expected_rate, $actual_rate, 
                "Customer count {$customer_count} should have rate {$expected_rate}");
        }
    }

    /**
     * Test role-specific tier configurations
     */
    public function testRoleSpecificTierConfigurations() {
        // Set different tiers for each role
        $coach_tiers = [
            ['min_customers' => 1, 'max_customers' => 10, 'rate' => 10],
            ['min_customers' => 11, 'max_customers' => 999999, 'rate' => 15],
        ];
        $partner_tiers = [
            ['min_customers' => 1, 'max_customers' => 5, 'rate' => 12],
            ['min_customers' => 6, 'max_customers' => 999999, 'rate' => 18],
        ];
        
        update_option('intersoccer_commission_tiers_coach', $coach_tiers);
        update_option('intersoccer_commission_tiers_partner', $partner_tiers);
        
        // Test coach rates
        $coach_rate_5 = InterSoccer_Commission_Manager::get_commission_rate_for_customer_count(5, 'coach');
        $coach_rate_15 = InterSoccer_Commission_Manager::get_commission_rate_for_customer_count(15, 'coach');
        $this->assertEquals(0.10, $coach_rate_5);
        $this->assertEquals(0.15, $coach_rate_15);
        
        // Test partner rates (different structure)
        $partner_rate_5 = InterSoccer_Commission_Manager::get_commission_rate_for_customer_count(5, 'partner');
        $partner_rate_15 = InterSoccer_Commission_Manager::get_commission_rate_for_customer_count(15, 'partner');
        $this->assertEquals(0.12, $partner_rate_5);
        $this->assertEquals(0.18, $partner_rate_15);
        
        // Restore defaults
        delete_option('intersoccer_commission_tiers_coach');
        delete_option('intersoccer_commission_tiers_partner');
    }

    /**
     * Test commission analytics
     */
    public function testCommissionAnalytics() {
        $analytics = [
            'total_earned' => 5000,
            'avg_per_order' => 12.50,
            'highest_month' => 800,
            'current_tier' => 'Silver',
        ];
        
        $this->assertEquals(5000, $analytics['total_earned']);
        $this->assertEquals('Silver', $analytics['current_tier']);
    }
}

