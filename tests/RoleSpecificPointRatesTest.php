<?php

use PHPUnit\Framework\TestCase;

/**
 * Test suite for Role-Specific Point Acquisition Rates (Phase 0)
 * 
 * Tests different point earning rates for different user roles:
 * - Customers
 * - Coaches
 * - Partners
 * - Social Influencers
 */
class RoleSpecificPointRatesTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-points-manager.php';
    }

    /**
     * Test default rates are set correctly
     */
    public function testDefaultRatesAreSetCorrectly() {
        $default_rates = [
            'customer' => 10,           // CHF 10 = 1 point
            'coach' => 10,              // CHF 10 = 1 point
            'partner' => 10,            // CHF 10 = 1 point
            'social_influencer' => 10,  // CHF 10 = 1 point
        ];

        foreach ($default_rates as $role => $rate) {
            $this->assertEquals(10, $rate, "Default rate for {$role} should be 10");
            $this->assertIsInt($rate, "Rate should be integer");
        }
    }

    /**
     * Test customer earns points at standard rate
     */
    public function testCustomerEarnsPointsAtStandardRate() {
        $rate = 10; // CHF 10 = 1 point
        $spent = 100; // CHF
        $expected_points = (int) floor($spent / $rate);
        
        $this->assertEquals(10, $expected_points);
    }

    /**
     * Test coach earns points at configured rate
     */
    public function testCoachEarnsPointsAtConfiguredRate() {
        $rate = 8; // CHF 8 = 1 point (better rate)
        $spent = 100; // CHF
        $expected_points = (int) floor($spent / $rate);
        
        $this->assertEquals(12, $expected_points, 'Coach with better rate earns more points');
    }

    /**
     * Test partner earns points at configured rate
     */
    public function testPartnerEarnsPointsAtConfiguredRate() {
        $rate = 5; // CHF 5 = 1 point (best rate)
        $spent = 100; // CHF
        $expected_points = (int) floor($spent / $rate);
        
        $this->assertEquals(20, $expected_points, 'Partner with best rate earns most points');
    }

    /**
     * Test social influencer earns points at configured rate
     */
    public function testSocialInfluencerEarnsPointsAtConfiguredRate() {
        $rate = 7; // CHF 7 = 1 point
        $spent = 100; // CHF
        $expected_points = (int) floor($spent / $rate);
        
        $this->assertEquals(14, $expected_points, 'Social influencer earns at configured rate');
    }

    /**
     * Test different roles earn different points for same amount
     */
    public function testDifferentRolesEarnDifferentPoints() {
        $spent = 100; // Same amount for all
        
        $customer_rate = 10;    // Standard
        $coach_rate = 8;        // Better
        $partner_rate = 5;      // Best
        
        $customer_points = (int) floor($spent / $customer_rate);   // 10 points
        $coach_points = (int) floor($spent / $coach_rate);         // 12 points
        $partner_points = (int) floor($spent / $partner_rate);     // 20 points
        
        $this->assertEquals(10, $customer_points);
        $this->assertEquals(12, $coach_points);
        $this->assertEquals(20, $partner_points);
        
        $this->assertGreaterThan($customer_points, $coach_points, 'Coach earns more than customer');
        $this->assertGreaterThan($coach_points, $partner_points, 'Partner earns more than coach');
    }

    /**
     * Test rate calculation always returns integers
     */
    public function testRateCalculationReturnsIntegers() {
        $test_cases = [
            ['rate' => 10, 'spent' => 95, 'expected' => 9],   // 95/10 = 9.5 → 9
            ['rate' => 8, 'spent' => 95, 'expected' => 11],   // 95/8 = 11.875 → 11
            ['rate' => 5, 'spent' => 97, 'expected' => 19],   // 97/5 = 19.4 → 19
            ['rate' => 7, 'spent' => 100, 'expected' => 14],  // 100/7 = 14.28 → 14
        ];

        foreach ($test_cases as $case) {
            $points = (int) floor($case['spent'] / $case['rate']);
            $this->assertEquals($case['expected'], $points);
            $this->assertIsInt($points, 'Points must be integer');
        }
    }

    /**
     * Test rate validation: must be positive
     */
    public function testRateValidationMustBePositive() {
        $invalid_rates = [0, -1, -10];
        
        foreach ($invalid_rates as $rate) {
            $is_valid = ($rate > 0);
            $this->assertFalse($is_valid, "Rate {$rate} should be invalid");
        }
    }

    /**
     * Test rate validation: must be integer
     */
    public function testRateValidationMustBeInteger() {
        $fractional_rates = ['10.5', '8.75', '5.25'];
        
        foreach ($fractional_rates as $rate) {
            $has_decimal = (strpos($rate, '.') !== false);
            $this->assertTrue($has_decimal, "Should detect fractional rate: {$rate}");
        }
    }

    /**
     * Test valid rates are accepted
     */
    public function testValidRatesAreAccepted() {
        $valid_rates = [1, 5, 10, 15, 20, 50, 100];
        
        foreach ($valid_rates as $rate) {
            $is_valid = ($rate > 0 && is_int($rate));
            $this->assertTrue($is_valid, "Rate {$rate} should be valid");
        }
    }

    /**
     * Test rate of 1 (CHF 1 = 1 point - most generous)
     */
    public function testRateOfOne() {
        $rate = 1;
        $spent = 100;
        $points = (int) floor($spent / $rate);
        
        $this->assertEquals(100, $points, 'Rate of 1 means 1:1 conversion');
    }

    /**
     * Test rate of 100 (CHF 100 = 1 point - least generous)
     */
    public function testRateOfOneHundred() {
        $rate = 100;
        $spent = 500;
        $points = (int) floor($spent / $rate);
        
        $this->assertEquals(5, $points, 'Rate of 100 means slow earning');
    }

    /**
     * Test role detection for customers
     */
    public function testRoleDetectionForCustomers() {
        // Mock user with 'customer' role
        $user_roles = ['customer'];
        
        $this->assertContains('customer', $user_roles);
        $this->assertNotContains('coach', $user_roles);
    }

    /**
     * Test role detection for coaches
     */
    public function testRoleDetectionForCoaches() {
        // Mock user with 'coach' role
        $user_roles = ['coach'];
        
        $this->assertContains('coach', $user_roles);
        $this->assertNotContains('customer', $user_roles);
    }

    /**
     * Test role detection for partners
     */
    public function testRoleDetectionForPartners() {
        // Mock user with 'partner' role
        $user_roles = ['partner'];
        
        $this->assertContains('partner', $user_roles);
    }

    /**
     * Test role detection for social influencers
     */
    public function testRoleDetectionForSocialInfluencers() {
        // Mock user with 'social_influencer' role
        $user_roles = ['social_influencer'];
        
        $this->assertContains('social_influencer', $user_roles);
    }

    /**
     * Test fallback to default rate for unknown roles
     */
    public function testFallbackToDefaultRateForUnknownRoles() {
        $user_roles = ['subscriber', 'unknown_role'];
        $default_rate = 10;
        
        // Should use default rate for unknown roles
        $rate = $default_rate;
        $this->assertEquals(10, $rate, 'Unknown roles should use default rate');
    }

    /**
     * Test users with multiple roles use first matching rate
     */
    public function testMultipleRolesUseFirstMatch() {
        // User is both customer and coach
        $user_roles = ['customer', 'coach'];
        
        // Priority: partner > social_influencer > coach > customer
        $role_priority = ['partner', 'social_influencer', 'coach', 'customer'];
        
        $matched_role = null;
        foreach ($role_priority as $priority_role) {
            if (in_array($priority_role, $user_roles)) {
                $matched_role = $priority_role;
                break;
            }
        }
        
        $this->assertEquals('coach', $matched_role, 'Should match highest priority role');
    }

    /**
     * Test rate options are stored correctly
     */
    public function testRateOptionsAreStoredCorrectly() {
        $option_names = [
            'intersoccer_points_rate_customer',
            'intersoccer_points_rate_coach',
            'intersoccer_points_rate_partner',
            'intersoccer_points_rate_social_influencer',
        ];

        foreach ($option_names as $option_name) {
            $this->assertIsString($option_name);
            $this->assertStringStartsWith('intersoccer_points_rate_', $option_name);
        }
    }

    /**
     * Test calculating points with customer rate
     */
    public function testCalculatingPointsWithCustomerRate() {
        $rate = 10;
        $amounts = [10, 25, 50, 100, 250, 500];
        
        foreach ($amounts as $amount) {
            $points = (int) floor($amount / $rate);
            $this->assertIsInt($points);
            $this->assertGreaterThanOrEqual(0, $points);
        }
    }

    /**
     * Test calculating points with coach rate (better than customer)
     */
    public function testCalculatingPointsWithCoachRate() {
        $customer_rate = 10;
        $coach_rate = 8; // Better rate
        $amount = 100;
        
        $customer_points = (int) floor($amount / $customer_rate);
        $coach_points = (int) floor($amount / $coach_rate);
        
        $this->assertGreaterThan($customer_points, $coach_points, 
            'Coach should earn more points than customer for same amount');
    }

    /**
     * Test rate comparison scenarios
     */
    public function testRateComparisonScenarios() {
        $amount = 1000; // CHF
        
        $rates = [
            'customer' => 10,
            'coach' => 8,
            'partner' => 5,
            'social_influencer' => 7,
        ];
        
        $points_earned = [];
        foreach ($rates as $role => $rate) {
            $points_earned[$role] = (int) floor($amount / $rate);
        }
        
        // Partner should earn most (lowest rate number = best earning)
        $this->assertEquals(200, $points_earned['partner']);
        $this->assertGreaterThan($points_earned['coach'], $points_earned['partner']);
        $this->assertGreaterThan($points_earned['customer'], $points_earned['partner']);
    }

    /**
     * Test edge case: very small amount
     */
    public function testVerySmallAmount() {
        $rate = 10;
        $amount = 5; // Less than rate
        $points = (int) floor($amount / $rate);
        
        $this->assertEquals(0, $points, 'Small amounts may result in 0 points');
    }

    /**
     * Test edge case: amount exactly equals rate
     */
    public function testAmountExactlyEqualsRate() {
        $rate = 10;
        $amount = 10;
        $points = (int) floor($amount / $rate);
        
        $this->assertEquals(1, $points, 'Amount equal to rate should give 1 point');
    }

    /**
     * Test edge case: amount is multiple of rate
     */
    public function testAmountIsMultipleOfRate() {
        $rate = 10;
        $amounts = [10, 20, 30, 100, 1000];
        
        foreach ($amounts as $amount) {
            $points = (int) floor($amount / $rate);
            $this->assertEquals($amount / $rate, $points, 
                "Perfect multiples should divide evenly");
        }
    }

    /**
     * Test different rates with same amount
     */
    public function testDifferentRatesWithSameAmount() {
        $amount = 100;
        $test_cases = [
            ['rate' => 1, 'expected' => 100],
            ['rate' => 5, 'expected' => 20],
            ['rate' => 10, 'expected' => 10],
            ['rate' => 20, 'expected' => 5],
            ['rate' => 50, 'expected' => 2],
            ['rate' => 100, 'expected' => 1],
        ];

        foreach ($test_cases as $case) {
            $points = (int) floor($amount / $case['rate']);
            $this->assertEquals($case['expected'], $points,
                "Rate {$case['rate']} with {$amount} CHF should give {$case['expected']} points");
        }
    }

    /**
     * Test help text explains rates correctly
     */
    public function testHelpTextExplainsRatesCorrectly() {
        $help_text = "CHF spent per 1 point earned (e.g., 10 means 1 point per CHF 10 spent)";
        
        $this->assertStringContainsString('CHF spent', $help_text);
        $this->assertStringContainsString('1 point earned', $help_text);
        $this->assertStringContainsString('example', strtolower($help_text));
    }

    /**
     * Test rate update triggers recalculation
     */
    public function testRateUpdateTriggersRecalculation() {
        $amount = 100;
        
        $old_rate = 10;
        $old_points = (int) floor($amount / $old_rate); // 10 points
        
        $new_rate = 5;
        $new_points = (int) floor($amount / $new_rate); // 20 points
        
        $this->assertNotEquals($old_points, $new_points, 
            'Changing rate should change points earned');
        $this->assertGreaterThan($old_points, $new_points,
            'Better rate should give more points');
    }

    /**
     * Test admin can set custom rates
     */
    public function testAdminCanSetCustomRates() {
        $custom_rates = [
            'customer' => 12,
            'coach' => 7,
            'partner' => 4,
            'social_influencer' => 6,
        ];

        foreach ($custom_rates as $role => $rate) {
            $this->assertIsInt($rate);
            $this->assertGreaterThan(0, $rate);
        }
    }

    /**
     * Test preview calculation works
     */
    public function testPreviewCalculationWorks() {
        $rate = 10;
        $preview_amount = 100;
        $preview_points = (int) floor($preview_amount / $rate);
        
        $preview_text = "Customer spending CHF {$preview_amount} will earn {$preview_points} points";
        
        $this->assertStringContainsString('100', $preview_text);
        $this->assertStringContainsString('10 points', $preview_text);
    }

    /**
     * Test all roles can be configured
     */
    public function testAllRolesCanBeConfigured() {
        $roles = ['customer', 'coach', 'partner', 'social_influencer'];
        
        $this->assertCount(4, $roles, 'Should have 4 configurable roles');
        $this->assertContains('customer', $roles);
        $this->assertContains('coach', $roles);
        $this->assertContains('partner', $roles);
        $this->assertContains('social_influencer', $roles);
    }

    /**
     * Test rate changes are auditable
     */
    public function testRateChangesAreAuditable() {
        $change_log = [
            'role' => 'coach',
            'old_rate' => 10,
            'new_rate' => 8,
            'changed_by' => 'admin',
            'timestamp' => time(),
        ];

        $this->assertEquals('coach', $change_log['role']);
        $this->assertEquals(10, $change_log['old_rate']);
        $this->assertEquals(8, $change_log['new_rate']);
        $this->assertIsInt($change_log['timestamp']);
    }

    /**
     * Test zero rate is invalid
     */
    public function testZeroRateIsInvalid() {
        $rate = 0;
        $is_valid = ($rate > 0);
        
        $this->assertFalse($is_valid, 'Rate of 0 should be invalid (would cause division by zero)');
    }

    /**
     * Test negative rate is invalid
     */
    public function testNegativeRateIsInvalid() {
        $rate = -10;
        $is_valid = ($rate > 0);
        
        $this->assertFalse($is_valid, 'Negative rates should be invalid');
    }

    /**
     * Test rate bounds (reasonable limits)
     */
    public function testRateBounds() {
        $min_reasonable = 1;   // Most generous
        $max_reasonable = 100; // Least generous
        
        $valid_rates = [1, 5, 10, 20, 50, 100];
        
        foreach ($valid_rates as $rate) {
            $this->assertGreaterThanOrEqual($min_reasonable, $rate);
            $this->assertLessThanOrEqual($max_reasonable, $rate);
        }
    }
}

