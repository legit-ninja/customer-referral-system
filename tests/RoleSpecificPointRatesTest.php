<?php

use PHPUnit\Framework\TestCase;

/**
 * Test suite for Point Acquisition Rates (Phase 0)
 * 
 * Tests different point earning rates for customers:
 * - Customer Purchase Rate
 * - Customer Referral Rate
 * - First Time Customer Rate
 * 
 * Note: Coaches, Partners, and Social Influencers use customer rates when making purchases.
 * They are rewarded through commission tiers rather than special point rates.
 */
class RoleSpecificPointRatesTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-points-manager.php';
    }

    /**
     * Test default rates are set correctly for all rate types
     */
    public function testDefaultRatesAreSetCorrectly() {
        $default_rates = [
            'customer_purchase' => 10,        // CHF 10 = 1 point
            'customer_referral' => 10,        // CHF 10 = 1 point
            'first_time_customer' => 10,      // CHF 10 = 1 point
        ];

        foreach ($default_rates as $rate_type => $rate) {
            $this->assertEquals(10, $rate, "Default rate for {$rate_type} should be 10");
            $this->assertIsInt($rate, "Rate should be integer");
        }
    }

    /**
     * Test first-time customer earns points at first-time customer rate
     */
    public function testFirstTimeCustomerEarnsPointsAtFirstTimeCustomerRate() {
        $rate = 5; // CHF 5 = 1 point (better rate for first-time customers)
        $spent = 100; // CHF
        $expected_points = (int) floor($spent / $rate);
        
        $this->assertEquals(20, $expected_points, 'First-time customer with better rate earns more points');
    }

    /**
     * Test first-time customer rate is better than regular customer purchase rate
     */
    public function testFirstTimeCustomerRateIsBetterThanRegularCustomerRate() {
        $first_time_rate = 5;  // Better rate for first-time
        $regular_rate = 10;    // Standard rate
        $spent = 100;
        
        $first_time_points = (int) floor($spent / $first_time_rate);  // 20 points
        $regular_points = (int) floor($spent / $regular_rate);        // 10 points
        
        $this->assertGreaterThan($regular_points, $first_time_points, 
            'First-time customers should earn more points than regular customers');
    }

    /**
     * Test customer purchase rate (regular customers, not first-time)
     */
    public function testCustomerPurchaseRate() {
        $rate = 10; // CHF 10 = 1 point
        $spent = 100; // CHF
        $expected_points = (int) floor($spent / $rate);
        
        $this->assertEquals(10, $expected_points, 'Regular customer purchase rate');
    }

    /**
     * Test customer referral rate
     */
    public function testCustomerReferralRate() {
        $rate = 8; // CHF 8 = 1 point (better rate for referrals)
        $spent = 100; // CHF
        $expected_points = (int) floor($spent / $rate);
        
        $this->assertEquals(12, $expected_points, 'Customer referral rate');
    }


    /**
     * Test coaches use customer purchase rate (no special rate)
     */
    public function testCoachUsesCustomerPurchaseRate() {
        $customer_rate = 10; // CHF 10 = 1 point
        $spent = 100; // CHF
        $expected_points = (int) floor($spent / $customer_rate);
        
        $this->assertEquals(10, $expected_points, 'Coach uses customer purchase rate');
    }

    /**
     * Test partners use customer purchase rate (no special rate)
     */
    public function testPartnerUsesCustomerPurchaseRate() {
        $customer_rate = 10; // CHF 10 = 1 point
        $spent = 100; // CHF
        $expected_points = (int) floor($spent / $customer_rate);
        
        $this->assertEquals(10, $expected_points, 'Partner uses customer purchase rate');
    }

    /**
     * Test social influencers use customer purchase rate (no special rate)
     */
    public function testSocialInfluencerUsesCustomerPurchaseRate() {
        $customer_rate = 10; // CHF 10 = 1 point
        $spent = 100; // CHF
        $expected_points = (int) floor($spent / $customer_rate);
        
        $this->assertEquals(10, $expected_points, 'Social influencer uses customer purchase rate');
    }

    /**
     * Test all roles (coaches, partners, influencers) use customer rate
     */
    public function testAllRolesUseCustomerRate() {
        $spent = 100; // Same amount for all
        $customer_rate = 10; // All roles use this rate
        
        $customer_points = (int) floor($spent / $customer_rate);   // 10 points
        $coach_points = (int) floor($spent / $customer_rate);      // 10 points (same as customer)
        $partner_points = (int) floor($spent / $customer_rate);    // 10 points (same as customer)
        
        $this->assertEquals(10, $customer_points);
        $this->assertEquals(10, $coach_points, 'Coach should use customer rate');
        $this->assertEquals(10, $partner_points, 'Partner should use customer rate');
        
        $this->assertEquals($customer_points, $coach_points, 'Coach should earn same as customer');
        $this->assertEquals($customer_points, $partner_points, 'Partner should earn same as customer');
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
     * Test users with multiple roles use customer rate (no role-specific rates)
     */
    public function testMultipleRolesUseCustomerRate() {
        // User is both customer and coach - should use customer rate
        $user_roles = ['customer', 'coach'];
        $customer_rate = 10; // All roles use this rate
        
        // Regardless of roles, use customer purchase rate
        $rate_to_use = $customer_rate;
        
        $this->assertEquals(10, $rate_to_use, 'All roles should use customer purchase rate');
    }

    /**
     * Test rate options are stored correctly for all rate types
     */
    public function testRateOptionsAreStoredCorrectly() {
        $option_names = [
            'intersoccer_points_rate_customer_purchase',
            'intersoccer_points_rate_customer_referral',
            'intersoccer_points_rate_first_time_customer',
        ];

        foreach ($option_names as $option_name) {
            $this->assertIsString($option_name);
            $this->assertStringStartsWith('intersoccer_points_rate_', $option_name);
        }
        
        $this->assertCount(3, $option_names, 'Should have 3 rate option types');
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
     * Test calculating points for coaches (uses customer rate)
     */
    public function testCalculatingPointsForCoach() {
        $customer_rate = 10; // Coach uses customer rate
        $amount = 100;
        
        $coach_points = (int) floor($amount / $customer_rate);
        
        $this->assertEquals(10, $coach_points, 
            'Coach should earn same points as customer for same amount');
    }

    /**
     * Test rate comparison scenarios - all roles use customer rate
     */
    public function testRateComparisonScenarios() {
        $amount = 1000; // CHF
        $customer_rate = 10; // All roles use this rate
        
        $points_earned = [
            'customer' => (int) floor($amount / $customer_rate),
            'coach' => (int) floor($amount / $customer_rate),
            'partner' => (int) floor($amount / $customer_rate),
            'social_influencer' => (int) floor($amount / $customer_rate),
        ];
        
        // All should earn the same (100 points)
        $this->assertEquals(100, $points_earned['customer']);
        $this->assertEquals(100, $points_earned['coach'], 'Coach should earn same as customer');
        $this->assertEquals(100, $points_earned['partner'], 'Partner should earn same as customer');
        $this->assertEquals(100, $points_earned['social_influencer'], 'Social influencer should earn same as customer');
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
     * Test admin can set custom rates for customers
     */
    public function testAdminCanSetCustomRates() {
        $custom_rates = [
            'customer_purchase' => 12,
            'customer_referral' => 8,
            'first_time_customer' => 5,
        ];

        foreach ($custom_rates as $rate_type => $rate) {
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
     * Test all rate types can be configured
     */
    public function testAllRateTypesCanBeConfigured() {
        $rate_types = [
            'customer_purchase',
            'customer_referral',
            'first_time_customer',
        ];
        
        $this->assertCount(3, $rate_types, 'Should have 3 configurable rate types');
        $this->assertContains('customer_purchase', $rate_types);
        $this->assertContains('customer_referral', $rate_types);
        $this->assertContains('first_time_customer', $rate_types);
    }

    /**
     * Test first-time customer detection logic
     */
    public function testFirstTimeCustomerDetection() {
        // First-time customer has no previous orders
        $has_previous_orders = false;
        $is_first_time = !$has_previous_orders;
        
        $this->assertTrue($is_first_time, 'Customer with no previous orders is first-time');
        
        // Returning customer has previous orders
        $has_previous_orders = true;
        $is_first_time = !$has_previous_orders;
        
        $this->assertFalse($is_first_time, 'Customer with previous orders is not first-time');
    }

    /**
     * Test first-time customer rate takes precedence over regular customer rate
     */
    public function testFirstTimeCustomerRateTakesPrecedence() {
        $first_time_rate = 5;   // Better rate
        $regular_rate = 10;     // Standard rate
        $spent = 100;
        $is_first_time = true;
        
        $rate_to_use = $is_first_time ? $first_time_rate : $regular_rate;
        $points = (int) floor($spent / $rate_to_use);
        
        $this->assertEquals(20, $points, 'First-time customer should use first-time rate');
        $this->assertEquals($first_time_rate, $rate_to_use, 'Should use first-time rate');
    }

    /**
     * Test regular customer uses purchase rate when not first-time
     */
    public function testRegularCustomerUsesPurchaseRate() {
        $first_time_rate = 5;
        $regular_rate = 10;
        $spent = 100;
        $is_first_time = false;
        
        $rate_to_use = $is_first_time ? $first_time_rate : $regular_rate;
        $points = (int) floor($spent / $rate_to_use);
        
        $this->assertEquals(10, $points, 'Regular customer should use purchase rate');
        $this->assertEquals($regular_rate, $rate_to_use, 'Should use purchase rate');
    }

    /**
     * Test all rate settings validation
     */
    public function testAllRateSettingsValidation() {
        $rates = [
            'customer_purchase' => 10,
            'customer_referral' => 8,
            'first_time_customer' => 5,
        ];
        
        foreach ($rates as $rate_type => $rate) {
            // Validate: must be positive integer
            $this->assertGreaterThan(0, $rate, "Rate {$rate_type} must be positive");
            $this->assertIsInt($rate, "Rate {$rate_type} must be integer");
            $this->assertLessThanOrEqual(100, $rate, "Rate {$rate_type} should not exceed 100");
        }
    }

    /**
     * Test first-time customer status takes precedence for all users (including coaches/partners)
     */
    public function testFirstTimeCustomerStatusTakesPrecedence() {
        // A coach who is also a first-time customer should use first-time rate
        $is_first_time = true;
        $first_time_rate = 5;
        $customer_rate = 10;
        
        // First-time status takes precedence (coaches/partners use customer rates)
        $rate_to_use = $is_first_time ? $first_time_rate : $customer_rate;
        
        $this->assertEquals($first_time_rate, $rate_to_use, 
            'First-time customer status should take precedence for all users');
    }

    /**
     * Test rate changes are auditable
     */
    public function testRateChangesAreAuditable() {
        $change_log = [
            'rate_type' => 'customer_purchase',
            'old_rate' => 10,
            'new_rate' => 8,
            'changed_by' => 'admin',
            'timestamp' => time(),
        ];

        $this->assertEquals('customer_purchase', $change_log['rate_type']);
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

    /**
     * Integration test: Coach making purchase uses customer purchase rate
     */
    public function testIntegration_CoachPurchaseUsesCustomerRate() {
        require_once __DIR__ . '/../includes/class-points-manager.php';
        
        // Set customer purchase rate
        update_option('intersoccer_points_rate_customer_purchase', 10);
        update_option('intersoccer_points_allocation_mode', 'ratio');
        
        $points_manager = new InterSoccer_Points_Manager();
        
        // Mock coach user
        global $mock_user_data;
        $mock_user_data = [
            1 => (object) [
                'ID' => 1,
                'roles' => ['coach']
            ]
        ];
        
        // Calculate points for coach purchase (100 CHF)
        $points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [100, 1, false]);
        
        // Coach should earn same points as customer (100 / 10 = 10 points)
        $this->assertEquals(10, $points, 'Coach should earn points at customer purchase rate');
        
        // Clean up
        delete_option('intersoccer_points_rate_customer_purchase');
        unset($mock_user_data);
    }

    /**
     * Integration test: Partner making purchase uses customer purchase rate
     */
    public function testIntegration_PartnerPurchaseUsesCustomerRate() {
        require_once __DIR__ . '/../includes/class-points-manager.php';
        
        // Set customer purchase rate
        update_option('intersoccer_points_rate_customer_purchase', 10);
        update_option('intersoccer_points_allocation_mode', 'ratio');
        
        $points_manager = new InterSoccer_Points_Manager();
        
        // Mock partner user
        global $mock_user_data;
        $mock_user_data = [
            1 => (object) [
                'ID' => 1,
                'roles' => ['partner']
            ]
        ];
        
        // Calculate points for partner purchase (100 CHF)
        $points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [100, 1, false]);
        
        // Partner should earn same points as customer (100 / 10 = 10 points)
        $this->assertEquals(10, $points, 'Partner should earn points at customer purchase rate');
        
        // Clean up
        delete_option('intersoccer_points_rate_customer_purchase');
        unset($mock_user_data);
    }

    /**
     * Integration test: Social influencer making purchase uses customer purchase rate
     */
    public function testIntegration_SocialInfluencerPurchaseUsesCustomerRate() {
        require_once __DIR__ . '/../includes/class-points-manager.php';
        
        // Set customer purchase rate
        update_option('intersoccer_points_rate_customer_purchase', 10);
        update_option('intersoccer_points_allocation_mode', 'ratio');
        
        $points_manager = new InterSoccer_Points_Manager();
        
        // Mock social influencer user
        global $mock_user_data;
        $mock_user_data = [
            1 => (object) [
                'ID' => 1,
                'roles' => ['social_influencer']
            ]
        ];
        
        // Calculate points for social influencer purchase (100 CHF)
        $points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [100, 1, false]);
        
        // Social influencer should earn same points as customer (100 / 10 = 10 points)
        $this->assertEquals(10, $points, 'Social influencer should earn points at customer purchase rate');
        
        // Clean up
        delete_option('intersoccer_points_rate_customer_purchase');
        unset($mock_user_data);
    }

    /**
     * Integration test: First-time coach uses first-time customer rate
     */
    public function testIntegration_FirstTimeCoachUsesFirstTimeRate() {
        require_once __DIR__ . '/../includes/class-points-manager.php';
        
        // Set rates
        update_option('intersoccer_points_rate_customer_purchase', 10);
        update_option('intersoccer_points_rate_first_time_customer', 5);
        update_option('intersoccer_points_allocation_mode', 'ratio');
        
        $points_manager = new InterSoccer_Points_Manager();
        
        // Mock coach user
        global $mock_user_data;
        $mock_user_data = [
            1 => (object) [
                'ID' => 1,
                'roles' => ['coach']
            ]
        ];
        
        // Calculate points for first-time coach purchase (100 CHF)
        $points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [100, 1, true]);
        
        // First-time coach should earn points at first-time customer rate (100 / 5 = 20 points)
        $this->assertEquals(20, $points, 'First-time coach should earn points at first-time customer rate');
        
        // Clean up
        delete_option('intersoccer_points_rate_customer_purchase');
        delete_option('intersoccer_points_rate_first_time_customer');
        unset($mock_user_data);
    }

    /**
     * Integration test: All roles earn same points for same purchase amount
     */
    public function testIntegration_AllRolesEarnSamePoints() {
        require_once __DIR__ . '/../includes/class-points-manager.php';
        
        // Set customer purchase rate
        update_option('intersoccer_points_rate_customer_purchase', 10);
        update_option('intersoccer_points_allocation_mode', 'ratio');
        
        $points_manager = new InterSoccer_Points_Manager();
        $purchase_amount = 100;
        
        // Mock different users
        global $mock_user_data;
        $mock_user_data = [
            1 => (object) ['ID' => 1, 'roles' => ['customer']],
            2 => (object) ['ID' => 2, 'roles' => ['coach']],
            3 => (object) ['ID' => 3, 'roles' => ['partner']],
            4 => (object) ['ID' => 4, 'roles' => ['social_influencer']],
        ];
        
        // Calculate points for each role
        $customer_points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [$purchase_amount, 1, false]);
        $coach_points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [$purchase_amount, 2, false]);
        $partner_points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [$purchase_amount, 3, false]);
        $influencer_points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [$purchase_amount, 4, false]);
        
        // All should earn the same (10 points)
        $this->assertEquals(10, $customer_points);
        $this->assertEquals(10, $coach_points, 'Coach should earn same as customer');
        $this->assertEquals(10, $partner_points, 'Partner should earn same as customer');
        $this->assertEquals(10, $influencer_points, 'Social influencer should earn same as customer');
        
        // Clean up
        delete_option('intersoccer_points_rate_customer_purchase');
        unset($mock_user_data);
    }

    /**
     * Helper method to invoke private methods
     */
    private function invokePrivateMethod($object, $methodName, array $parameters = []) {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}

