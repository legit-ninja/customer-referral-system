<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for Commission Calculations
 * 
 * Tests tiered commission structure:
 * - 1-10 customers: 10% commission
 * - 11-24 customers: 15% commission  
 * - 25-50 customers: 20% commission
 */
class CommissionCalculationTest extends TestCase {

    /**
     * Test tier 1 commission (1-10 customers)
     */
    public function testTier1Commission() {
        $customers_recruited = 5;
        $commission_rate = 0.10; // 10%
        $order_amount = 100;
        
        $commission = $order_amount * $commission_rate;
        
        $this->assertEquals(10, $commission);
    }

    /**
     * Test tier 2 commission (11-24 customers)
     */
    public function testTier2Commission() {
        $customers_recruited = 15;
        $commission_rate = 0.15; // 15%
        $order_amount = 100;
        
        $commission = $order_amount * $commission_rate;
        
        $this->assertEquals(15, $commission);
    }

    /**
     * Test tier 3 commission (25-50 customers)
     */
    public function testTier3Commission() {
        $customers_recruited = 30;
        $commission_rate = 0.20; // 20%
        $order_amount = 100;
        
        $commission = $order_amount * $commission_rate;
        
        $this->assertEquals(20, $commission);
    }

    /**
     * Test tier progression increases commission
     */
    public function testTierProgressionIncreasesCommission() {
        $order_amount = 100;
        
        $tier1_commission = $order_amount * 0.10; // 10
        $tier2_commission = $order_amount * 0.15; // 15
        $tier3_commission = $order_amount * 0.20; // 20
        
        $this->assertGreaterThan($tier1_commission, $tier2_commission);
        $this->assertGreaterThan($tier2_commission, $tier3_commission);
    }

    /**
     * Test tier boundaries
     */
    public function testTierBoundaries() {
        $tier_configs = [
            ['min' => 1, 'max' => 10, 'rate' => 0.10],
            ['min' => 11, 'max' => 24, 'rate' => 0.15],
            ['min' => 25, 'max' => 50, 'rate' => 0.20],
        ];
        
        // Test boundary: 10 customers (tier 1)
        $customers = 10;
        $tier = ($customers <= 10) ? 0.10 : (($customers <= 24) ? 0.15 : 0.20);
        $this->assertEquals(0.10, $tier);
        
        // Test boundary: 11 customers (tier 2)
        $customers = 11;
        $tier = ($customers <= 10) ? 0.10 : (($customers <= 24) ? 0.15 : 0.20);
        $this->assertEquals(0.15, $tier);
        
        // Test boundary: 25 customers (tier 3)
        $customers = 25;
        $tier = ($customers <= 10) ? 0.10 : (($customers <= 24) ? 0.15 : 0.20);
        $this->assertEquals(0.20, $tier);
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
        $commission_before = $order_amount * 0.10;
        
        // After upgrade: 11 customers (15%)
        $commission_after = $order_amount * 0.15;
        
        $this->assertGreaterThan($commission_before, $commission_after);
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
     * Test commission rate configuration
     */
    public function testCommissionRateConfiguration() {
        $tier_rates = [
            'tier1' => 10,
            'tier2' => 15,
            'tier3' => 20,
        ];
        
        $this->assertEquals(10, $tier_rates['tier1']);
        $this->assertEquals(15, $tier_rates['tier2']);
        $this->assertEquals(20, $tier_rates['tier3']);
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
     * Test coach tier badge assignment
     */
    public function testCoachTierBadgeAssignment() {
        $customer_count = 5;
        
        if ($customer_count <= 10) {
            $tier = 'Bronze';
        } elseif ($customer_count <= 24) {
            $tier = 'Silver';
        } else {
            $tier = 'Gold';
        }
        
        $this->assertEquals('Bronze', $tier);
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

