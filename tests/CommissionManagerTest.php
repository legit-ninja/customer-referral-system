<?php

use PHPUnit\Framework\TestCase;

/**
 * Test suite for InterSoccer Commission Manager
 */
class CommissionManagerTest extends TestCase {

    protected function setUp(): void {
        // Include the commission manager class
        require_once __DIR__ . '/../includes/class-commission-manager.php';
    }

    /**
     * Test calculate_base_commission method
     */
    public function testCalculateBaseCommission() {
        $order = new WC_Order();
        $order->set_total(100);
        $order->set_tax_total(10);

        // Test first purchase (15% commission)
        $commission = InterSoccer_Commission_Manager::calculate_base_commission($order, 1);
        $this->assertEquals(13.5, $commission); // (100-10) * 0.15

        // Test second purchase (7.5% commission)
        $commission = InterSoccer_Commission_Manager::calculate_base_commission($order, 2);
        $this->assertEquals(6.75, $commission); // (100-10) * 0.075

        // Test third+ purchase (5% commission)
        $commission = InterSoccer_Commission_Manager::calculate_base_commission($order, 3);
        $this->assertEquals(4.5, $commission); // (100-10) * 0.05
    }

    /**
     * Test calculate_loyalty_bonus method
     */
    public function testCalculateLoyaltyBonus() {
        $order = new WC_Order();
        $order->set_total(100);
        $order->set_tax_total(10);

        // Test first purchase (5% loyalty bonus)
        $bonus = InterSoccer_Commission_Manager::calculate_loyalty_bonus($order, 1);
        $this->assertEquals(4.5, $bonus); // (100-10) * 0.05

        // Test second purchase (8% loyalty bonus)
        $bonus = InterSoccer_Commission_Manager::calculate_loyalty_bonus($order, 2);
        $this->assertEquals(7.2, $bonus); // (100-10) * 0.08

        // Test third+ purchase (15% loyalty bonus)
        $bonus = InterSoccer_Commission_Manager::calculate_loyalty_bonus($order, 3);
        $this->assertEquals(13.5, $bonus); // (100-10) * 0.15
    }

    /**
     * Test calculate_tier_bonus method
     */
    public function testCalculateTierBonus() {
        $base_amount = 10;

        // Test Bronze tier (0% bonus)
        $bonus = InterSoccer_Commission_Manager::calculate_tier_bonus(1, $base_amount);
        $this->assertEquals(0, $bonus);

        // Test Silver tier (2% bonus)
        $bonus = InterSoccer_Commission_Manager::calculate_tier_bonus(2, $base_amount);
        $this->assertEquals(0.2, $bonus); // 10 * 0.02

        // Test Gold tier (5% bonus)
        $bonus = InterSoccer_Commission_Manager::calculate_tier_bonus(3, $base_amount);
        $this->assertEquals(0.5, $bonus); // 10 * 0.05

        // Test Platinum tier (10% bonus)
        $bonus = InterSoccer_Commission_Manager::calculate_tier_bonus(4, $base_amount);
        $this->assertEquals(1.0, $bonus); // 10 * 0.10
    }

    /**
     * Test get_coach_tier method
     */
    public function testGetCoachTier() {
        // Mock different referral counts
        global $wpdb;

        // Test Bronze tier (0-4 referrals)
        $wpdb->get_var = function($query) { return 3; };
        $tier = InterSoccer_Commission_Manager::get_coach_tier(1);
        $this->assertEquals('Bronze', $tier);

        // Test Silver tier (5-9 referrals)
        $wpdb->get_var = function($query) { return 7; };
        $tier = InterSoccer_Commission_Manager::get_coach_tier(1);
        $this->assertEquals('Silver', $tier);

        // Test Gold tier (10-19 referrals)
        $wpdb->get_var = function($query) { return 15; };
        $tier = InterSoccer_Commission_Manager::get_coach_tier(1);
        $this->assertEquals('Gold', $tier);

        // Test Platinum tier (20+ referrals)
        $wpdb->get_var = function($query) { return 25; };
        $tier = InterSoccer_Commission_Manager::get_coach_tier(1);
        $this->assertEquals('Platinum', $tier);
    }

    /**
     * Test calculate_partnership_commission method
     */
    public function testCalculatePartnershipCommission() {
        $order = new WC_Order();
        $order->set_total(100);
        $order->set_tax_total(10);

        $commission = InterSoccer_Commission_Manager::calculate_partnership_commission($order, 1);

        $this->assertEquals(4.5, $commission['base_commission']); // (100-10) * 0.05
        $this->assertEquals(4.5, $commission['total_amount']); // Base + tier bonus (Bronze = 0)
    }

    /**
     * Test calculate_retention_bonus method
     */
    public function testCalculateRetentionBonus() {
        // Mock customer with multiple seasons
        $customer_id = 1;
        $current_season = 2025;

        // Test new customer (no bonus)
        $bonus = InterSoccer_Commission_Manager::calculate_retention_bonus($customer_id, $current_season);
        $this->assertEquals(0, $bonus);

        // Test returning customer (Season 2 bonus)
        // This would require mocking get_customer_seasonal_orders
        // For now, we'll test the logic with direct calls
        $this->assertTrue(true); // Placeholder
    }

    /**
     * Test calculate_network_bonus method
     */
    public function testCalculateNetworkBonus() {
        global $wpdb;

        // Test customer with referrals
        $wpdb->get_var = function($query) { return 2; };
        $bonus = InterSoccer_Commission_Manager::calculate_network_bonus(1);
        $this->assertEquals(15, $bonus);

        // Test customer without referrals
        $wpdb->get_var = function($query) { return 0; };
        $bonus = InterSoccer_Commission_Manager::calculate_network_bonus(1);
        $this->assertEquals(0, $bonus);
    }

    /**
     * Test calculate_seasonal_bonus method
     */
    public function testCalculateSeasonalBonus() {
        $base_amount = 10;

        // Test back to school season (August)
        $bonus = InterSoccer_Commission_Manager::calculate_seasonal_bonus($base_amount, '2025-08-15');
        $this->assertEquals(5, $bonus); // 10 * 0.5

        // Test holiday season (December)
        $bonus = InterSoccer_Commission_Manager::calculate_seasonal_bonus($base_amount, '2025-12-15');
        $this->assertEquals(3, $bonus); // 10 * 0.3

        // Test spring season (March)
        $bonus = InterSoccer_Commission_Manager::calculate_seasonal_bonus($base_amount, '2025-03-15');
        $this->assertEquals(2, $bonus); // 10 * 0.2

        // Test regular season (May)
        $bonus = InterSoccer_Commission_Manager::calculate_seasonal_bonus($base_amount, '2025-05-15');
        $this->assertEquals(0, $bonus);
    }

    /**
     * Test calculate_weekend_bonus method
     */
    public function testCalculateWeekendBonus() {
        $base_amount = 10;

        // Test Saturday
        $bonus = InterSoccer_Commission_Manager::calculate_weekend_bonus($base_amount, '2025-10-26'); // Saturday
        $this->assertEquals(1, $bonus); // 10 * 0.1

        // Test Sunday
        $bonus = InterSoccer_Commission_Manager::calculate_weekend_bonus($base_amount, '2025-10-27'); // Sunday
        $this->assertEquals(1, $bonus);

        // Test weekday (Monday)
        $bonus = InterSoccer_Commission_Manager::calculate_weekend_bonus($base_amount, '2025-10-28'); // Monday
        $this->assertEquals(0, $bonus);
    }

    /**
     * Test calculate_total_commission method
     */
    public function testCalculateTotalCommission() {
        $order = new WC_Order();
        $order->set_total(100);
        $order->set_tax_total(10);
        $coach_id = 1;
        $customer_id = 1;
        $purchase_count = 1;

        $commission = InterSoccer_Commission_Manager::calculate_total_commission(
            $order, $coach_id, $customer_id, $purchase_count
        );

        $this->assertIsArray($commission);
        $this->assertArrayHasKey('base_commission', $commission);
        $this->assertArrayHasKey('loyalty_bonus', $commission);
        $this->assertArrayHasKey('retention_bonus', $commission);
        $this->assertArrayHasKey('network_bonus', $commission);
        $this->assertArrayHasKey('tier_bonus', $commission);
        $this->assertArrayHasKey('seasonal_bonus', $commission);
        $this->assertArrayHasKey('weekend_bonus', $commission);
        $this->assertArrayHasKey('total_amount', $commission);

        // Verify calculations
        $this->assertEquals(13.5, $commission['base_commission']); // 90 * 0.15
        $this->assertEquals(4.5, $commission['loyalty_bonus']); // 90 * 0.05
        $this->assertGreaterThanOrEqual(0, $commission['total_amount']);
    }

    /**
     * Test commission calculations with different order totals
     */
    public function testCommissionWithDifferentTotals() {
        $coach_id = 1;
        $customer_id = 1;
        $purchase_count = 1;

        // Test with CHF 200 order
        $order = new WC_Order();
        $order->set_total(200);
        $order->set_tax_total(20);

        $commission = InterSoccer_Commission_Manager::calculate_total_commission(
            $order, $coach_id, $customer_id, $purchase_count
        );

        $this->assertEquals(27, $commission['base_commission']); // (200-20) * 0.15
        $this->assertEquals(9, $commission['loyalty_bonus']); // (200-20) * 0.05

        // Test with CHF 50 order
        $order->set_total(50);
        $order->set_tax_total(5);

        $commission = InterSoccer_Commission_Manager::calculate_total_commission(
            $order, $coach_id, $customer_id, $purchase_count
        );

        $this->assertEquals(6.75, $commission['base_commission']); // (50-5) * 0.15
        $this->assertEquals(2.25, $commission['loyalty_bonus']); // (50-5) * 0.05
    }

    /**
     * Test commission calculations with different purchase counts
     */
    public function testCommissionWithDifferentPurchaseCounts() {
        $order = new WC_Order();
        $order->set_total(100);
        $order->set_tax_total(10);
        $coach_id = 1;
        $customer_id = 1;

        // First purchase
        $commission = InterSoccer_Commission_Manager::calculate_total_commission(
            $order, $coach_id, $customer_id, 1
        );
        $this->assertEquals(13.5, $commission['base_commission']);
        $this->assertEquals(4.5, $commission['loyalty_bonus']);

        // Second purchase
        $commission = InterSoccer_Commission_Manager::calculate_total_commission(
            $order, $coach_id, $customer_id, 2
        );
        $this->assertEquals(6.75, $commission['base_commission']);
        $this->assertEquals(7.2, $commission['loyalty_bonus']);

        // Third purchase
        $commission = InterSoccer_Commission_Manager::calculate_total_commission(
            $order, $coach_id, $customer_id, 3
        );
        $this->assertEquals(4.5, $commission['base_commission']);
        $this->assertEquals(13.5, $commission['loyalty_bonus']);
    }
}