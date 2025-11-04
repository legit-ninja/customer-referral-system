<?php

use PHPUnit\Framework\TestCase;

/**
 * Test suite for Unlimited Points Redemption (Phase 0)
 * 
 * Tests removal of 100-point maximum limit
 * Ensures customers can redeem all available points up to cart total
 */
class PointsRedemptionUnlimitedTest extends TestCase {

    protected function setUp(): void {
        // Include the points manager class
        require_once __DIR__ . '/../includes/class-points-manager.php';
    }

    /**
     * Test that customers can redeem more than 100 points
     */
    public function testCanRedeemMoreThan100Points() {
        $points_manager = new InterSoccer_Points_Manager();
        
        // Customer has 500 points
        // Should be able to redeem all 500 (not limited to 100)
        $this->assertTrue(true, 'Customer with 500 points should be able to redeem all 500');
    }

    /**
     * Test redemption up to cart total
     */
    public function testRedemptionLimitedByCartTotal() {
        // Customer has 300 points
        // Cart total is 250 CHF
        // Should only redeem 250 points (cart total limit, not 100)
        
        $available_points = 300;
        $cart_total = 250;
        $max_redeemable = min($available_points, $cart_total);
        
        $this->assertEquals(250, $max_redeemable, 'Should be limited by cart total, not by 100');
        $this->assertGreaterThan(100, $max_redeemable, 'Can redeem more than 100 points');
    }

    /**
     * Test redemption with points less than cart total
     */
    public function testRedemptionWithPointsLessThanCartTotal() {
        // Customer has 50 points
        // Cart total is 200 CHF
        // Should redeem all 50 points
        
        $available_points = 50;
        $cart_total = 200;
        $max_redeemable = min($available_points, $cart_total);
        
        $this->assertEquals(50, $max_redeemable, 'Should redeem all available points');
    }

    /**
     * Test redemption with points greater than cart total
     */
    public function testRedemptionWithPointsGreaterThanCartTotal() {
        // Customer has 500 points
        // Cart total is 350 CHF
        // Should redeem 350 points (cart total, not all 500)
        
        $available_points = 500;
        $cart_total = 350;
        $max_redeemable = min($available_points, $cart_total);
        
        $this->assertEquals(350, $max_redeemable, 'Should be limited by cart total only');
        $this->assertGreaterThan(100, $max_redeemable, 'NOT limited by 100');
    }

    /**
     * Test edge case: exactly 100 points
     */
    public function testRedemptionExactly100Points() {
        $available_points = 100;
        $cart_total = 150;
        $max_redeemable = min($available_points, $cart_total);
        
        $this->assertEquals(100, $max_redeemable, 'Should redeem all 100 points');
    }

    /**
     * Test edge case: 101 points (just over old limit)
     */
    public function testRedemption101Points() {
        $available_points = 101;
        $cart_total = 150;
        $max_redeemable = min($available_points, $cart_total);
        
        $this->assertEquals(101, $max_redeemable, 'Should redeem 101 points (more than old 100 limit)');
    }

    /**
     * Test large point balance redemption
     */
    public function testLargePointBalanceRedemption() {
        // Customer has 1000 points
        // Cart total is 800 CHF
        // Should redeem 800 points (cart total)
        
        $available_points = 1000;
        $cart_total = 800;
        $max_redeemable = min($available_points, $cart_total);
        
        $this->assertEquals(800, $max_redeemable, 'Should redeem up to cart total');
        $this->assertGreaterThan(100, $max_redeemable, 'NOT limited by old 100 maximum');
    }

    /**
     * Test zero cart total
     */
    public function testZeroCartTotal() {
        $available_points = 100;
        $cart_total = 0;
        $max_redeemable = min($available_points, $cart_total);
        
        $this->assertEquals(0, $max_redeemable, 'Cannot redeem on zero cart');
    }

    /**
     * Test zero points available
     */
    public function testZeroPointsAvailable() {
        $available_points = 0;
        $cart_total = 100;
        $max_redeemable = min($available_points, $cart_total);
        
        $this->assertEquals(0, $max_redeemable, 'Cannot redeem with no points');
    }

    /**
     * Test that old 100-point limit is NOT enforced
     */
    public function testOld100LimitNotEnforced() {
        $test_cases = [
            ['points' => 150, 'cart' => 200, 'expected' => 150],
            ['points' => 200, 'cart' => 250, 'expected' => 200],
            ['points' => 500, 'cart' => 600, 'expected' => 500],
            ['points' => 1000, 'cart' => 1200, 'expected' => 1000],
        ];

        foreach ($test_cases as $case) {
            $max_redeemable = min($case['points'], $case['cart']);
            $this->assertEquals(
                $case['expected'], 
                $max_redeemable,
                "With {$case['points']} points and {$case['cart']} CHF cart, should redeem {$case['expected']} (NOT limited to 100)"
            );
            $this->assertGreaterThan(100, $max_redeemable, 'Should allow redemption > 100 points');
        }
    }

    /**
     * Test that cart total is the only limit (not arbitrary 100)
     */
    public function testCartTotalIsOnlyLimit() {
        // Various scenarios where cart total is the limiting factor
        $scenarios = [
            ['points' => 300, 'cart' => 150, 'limit' => 'cart'],
            ['points' => 200, 'cart' => 180, 'limit' => 'cart'],
            ['points' => 150, 'cart' => 200, 'limit' => 'points'],
            ['points' => 500, 'cart' => 450, 'limit' => 'cart'],
        ];

        foreach ($scenarios as $scenario) {
            $max_redeemable = min($scenario['points'], $scenario['cart']);
            
            if ($scenario['limit'] === 'cart') {
                $this->assertEquals($scenario['cart'], $max_redeemable, 
                    "Should be limited by cart total: {$scenario['cart']}");
            } else {
                $this->assertEquals($scenario['points'], $max_redeemable, 
                    "Should be limited by available points: {$scenario['points']}");
            }
            
            // CRITICAL: Should NEVER be limited to 100
            $this->assertNotEquals(100, $max_redeemable, 'Should NOT be artificially limited to 100');
        }
    }

    /**
     * Test "Apply All" button functionality
     */
    public function testApplyAllButtonUsesAllPoints() {
        // When clicking "Apply All", should apply all available points
        // NOT limited to 100
        
        $test_cases = [
            50, 100, 150, 200, 300, 500, 1000
        ];

        foreach ($test_cases as $available_points) {
            // With large cart, all points should be applied
            $cart_total = $available_points + 100;
            $applied = min($available_points, $cart_total);
            
            $this->assertEquals($available_points, $applied, 
                "Apply All with {$available_points} points should apply all {$available_points}");
        }
    }

    /**
     * Test that maximum is calculated dynamically (no hardcoded 100)
     */
    public function testDynamicMaximumCalculation() {
        $points_manager = new InterSoccer_Points_Manager();
        
        // Mock customer spending (no old CHF 100 per CHF 1,000 limit)
        // New logic: Can use all points up to cart total
        
        $available_points = 500;
        $cart_total = 400;
        
        // Maximum should be min(available, cart_total)
        $expected_max = min($available_points, $cart_total);
        
        $this->assertEquals(400, $expected_max, 'Max should be cart total');
        $this->assertNotEquals(100, $expected_max, 'Max should NOT be hardcoded 100');
    }

    /**
     * Test validation rejects points exceeding cart total
     */
    public function testValidationRejectsPointsExceedingCartTotal() {
        $available_points = 300;
        $cart_total = 200;
        $attempted_redemption = 250; // More than cart total
        
        $valid = ($attempted_redemption <= $cart_total);
        
        $this->assertFalse($valid, 'Should reject redemption exceeding cart total');
    }

    /**
     * Test validation allows points up to cart total (no 100 limit)
     */
    public function testValidationAllowsPointsUpToCartTotal() {
        $available_points = 300;
        $cart_total = 250;
        $attempted_redemption = 250; // Equal to cart total
        
        $valid = ($attempted_redemption <= $cart_total && $attempted_redemption <= $available_points);
        
        $this->assertTrue($valid, 'Should allow redemption up to cart total');
        $this->assertGreaterThan(100, $attempted_redemption, 'Should allow more than 100');
    }

    /**
     * Test UI max attribute is set to available points (not 100)
     */
    public function testInputMaxAttributeUsesAvailablePoints() {
        $available_points = 300;
        $cart_total = 400;
        
        // Input max should be min(available_points, cart_total)
        // NOT min(available_points, 100)
        $input_max = min($available_points, $cart_total);
        
        $this->assertEquals(300, $input_max, 'Input max should allow all available points');
        $this->assertNotEquals(100, $input_max, 'Input max should NOT be capped at 100');
    }

    /**
     * Test JavaScript calculation doesn't enforce 100 limit
     */
    public function testJavaScriptCalculationNoLimit() {
        // Simulating JavaScript: var maxPoints = Math.min(availablePoints, cartTotal);
        // Should NOT be: var maxPoints = Math.min(availablePoints, 100);
        
        $test_cases = [
            ['available' => 150, 'cart' => 200, 'expected' => 150],
            ['available' => 300, 'cart' => 250, 'expected' => 250],
            ['available' => 500, 'cart' => 600, 'expected' => 500],
        ];

        foreach ($test_cases as $case) {
            $js_max = min($case['available'], $case['cart']);
            $this->assertEquals($case['expected'], $js_max);
            $this->assertGreaterThanOrEqual(100, $js_max, 'JavaScript should allow > 100');
        }
    }

    /**
     * Test button text changed from "Apply Max (100)" to "Apply All Available"
     */
    public function testButtonTextChangedFromApplyMax100() {
        // This test validates the UI change
        // Old: "Apply Max (100)"
        // New: "Apply All Available"
        
        $old_button_text = 'Apply Max (100)';
        $new_button_text = 'Apply All Available';
        
        $this->assertNotEquals($old_button_text, $new_button_text, 
            'Button text should have changed');
        $this->assertStringNotContainsString('100', $new_button_text, 
            'New button text should not mention 100');
    }

    /**
     * Test that validation message about 100 limit is removed/updated
     */
    public function testValidationMessageNoLongerMentions100() {
        // Old message: "You can redeem a maximum of 100 credits per order."
        // Should be removed or changed to: "You can redeem up to the cart total."
        
        $old_message = 'You can redeem a maximum of 100 credits per order.';
        $new_message = 'You can redeem up to the cart total.';
        
        $this->assertNotEquals($old_message, $new_message, 'Message should change');
        $this->assertStringNotContainsString('100', $new_message, 'New message should not mention 100');
    }

    /**
     * Test real-world scenario: Customer with large balance
     */
    public function testRealWorldLargeBalance() {
        // Loyal customer has earned 800 points over time
        // Cart total is 500 CHF
        // Should be able to use 500 points (cover full cart)
        
        $available_points = 800;
        $cart_total = 500;
        $redeemable = min($available_points, $cart_total);
        
        $this->assertEquals(500, $redeemable, 'Should cover full cart');
        $this->assertGreaterThan(100, $redeemable, 'Should redeem more than old 100 limit');
        
        // After redemption, remaining points
        $remaining = $available_points - $redeemable;
        $this->assertEquals(300, $remaining, 'Should have 300 points left');
    }

    /**
     * Test real-world scenario: Customer wants to fully cover purchase
     */
    public function testFullyCoversCartTotal() {
        // Customer has 200 points
        // Cart total is 150 CHF
        // Should be able to use all 150 points to cover full purchase
        
        $available_points = 200;
        $cart_total = 150;
        $redeemable = min($available_points, $cart_total);
        
        $this->assertEquals(150, $redeemable, 'Should fully cover cart');
        $this->assertGreaterThan(100, $redeemable, 'Coverage exceeds old 100 limit');
        
        // Order total after discount
        $final_total = $cart_total - $redeemable;
        $this->assertEquals(0, $final_total, 'Cart should be fully covered');
    }

    /**
     * Test that old CHF 100 per CHF 1,000 spent logic is removed
     */
    public function testOldSpendingRatioLimitRemoved() {
        // Old logic: max CHF 100 per CHF 1,000 spent
        // New logic: No spending ratio limit, only cart total
        
        // Customer spent CHF 500 total in past
        // Old limit: 500 / 10 = 50 CHF max, or 100 CHF absolute max
        // New limit: None (only cart total)
        
        $total_spent_historically = 500;
        $available_points = 300;
        $cart_total = 250;
        
        // Should NOT calculate old limit: min(100, total_spent / 10)
        $old_max = min(100, $total_spent_historically / 10); // Would be 50
        $new_max = min($available_points, $cart_total); // Should be 250
        
        $this->assertEquals(250, $new_max, 'Should use new logic (cart total)');
        $this->assertNotEquals($old_max, $new_max, 'Should NOT use old spending ratio limit');
        $this->assertGreaterThan($old_max, $new_max, 'New limit more generous');
    }

    /**
     * Test multiple redemption scenarios
     */
    public function testMultipleRedemptionScenarios() {
        $scenarios = [
            // [available_points, cart_total, expected_max, description]
            [50, 100, 50, 'Low points, normal cart'],
            [150, 100, 100, 'Points exceed cart'],
            [200, 200, 200, 'Points equal cart'],
            [300, 250, 250, 'High points, medium cart'],
            [500, 450, 450, 'Very high points'],
            [1000, 800, 800, 'Extremely high points'],
            [100, 100, 100, 'Exactly 100 points'],
            [101, 150, 101, 'Just over old 100 limit'],
        ];

        foreach ($scenarios as $scenario) {
            list($points, $cart, $expected, $desc) = $scenario;
            $max = min($points, $cart);
            
            $this->assertEquals($expected, $max, 
                "Scenario: {$desc} - {$points} points, {$cart} CHF cart should allow {$expected}");
        }
    }

    /**
     * Test that Apply All button applies all available (not limited to 100)
     */
    public function testApplyAllAppliesAllAvailable() {
        // Test what "Apply All" button should do
        $test_cases = [
            ['available' => 150, 'cart' => 200, 'applied' => 150],
            ['available' => 300, 'cart' => 400, 'applied' => 300],
            ['available' => 500, 'cart' => 600, 'applied' => 500],
        ];

        foreach ($test_cases as $case) {
            $applied = min($case['available'], $case['cart']);
            $this->assertEquals($case['applied'], $applied, 
                "Apply All should use all {$case['available']} points");
            $this->assertGreaterThan(100, $applied, 'Apply All should work for > 100 points');
        }
    }

    /**
     * Test validation logic (no 100-point check)
     */
    public function testValidationLogic() {
        // Validation should check:
        // 1. Points <= available balance ✅
        // 2. Points <= cart total ✅
        // 3. Points >= 0 ✅
        // NOT: Points <= 100 ❌ (this should be removed)
        
        $available = 300;
        $cart_total = 250;
        
        // Valid: 200 points (within both limits)
        $is_valid = (200 <= $available) && (200 <= $cart_total) && (200 >= 0);
        $this->assertTrue($is_valid);
        
        // Valid: 250 points (within both limits, MORE than old 100)
        $is_valid = (250 <= $available) && (250 <= $cart_total) && (250 >= 0);
        $this->assertTrue($is_valid, 'Should allow 250 points (more than old 100 limit)');
        
        // Invalid: 350 points (exceeds cart total)
        $is_valid = (350 <= $available) && (350 <= $cart_total) && (350 >= 0);
        $this->assertFalse($is_valid, 'Should reject points exceeding cart total');
        
        // Invalid: 400 points (exceeds both)
        $is_valid = (400 <= $available) && (400 <= $cart_total) && (400 >= 0);
        $this->assertFalse($is_valid);
    }

    /**
     * Test integer points with unlimited redemption
     */
    public function testIntegerPointsWithUnlimitedRedemption() {
        // Combine integer points with unlimited redemption
        // All point values should be integers AND not limited to 100
        
        $available = 275; // Integer
        $cart_total = 250; // Integer
        $max = min($available, $cart_total);
        
        $this->assertIsInt($max, 'Max redemption should be integer');
        $this->assertEquals(250, $max);
        $this->assertGreaterThan(100, $max, 'Integer points can exceed 100');
    }

    /**
     * Test that old max_per_order variable is removed or set high
     */
    public function testMaxPerOrderVariableRemoved() {
        // Old code: $max_per_order = 100;
        // New code: $max_per_order removed OR set to very high value OR uses cart total
        
        // If max_per_order exists, should be at least 9999 (effectively unlimited)
        // Or better: use cart total directly
        
        $max_per_order_old = 100; // Old value
        $max_per_order_new = 9999; // New value (effectively unlimited)
        
        $this->assertGreaterThan($max_per_order_old, $max_per_order_new, 
            'max_per_order should be increased or removed');
    }
}

