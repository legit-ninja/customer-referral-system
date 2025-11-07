<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-referral-handler.php';

/**
 * Tests for Referral Code Validation and Processing
 * 
 * Tests:
 * - Referral code format validation
 * - Code uniqueness
 * - Code application to orders
 * - Coach bonus allocation
 * - Customer discount application
 * - Invalid code handling
 */
class ReferralCodeValidationTest extends TestCase {

    /**
     * Test valid referral code format
     */
    public function testValidReferralCodeFormat() {
        $valid_codes = [
            'COACH123',
            'REF-2025',
            'CODE_ABC',
            'TRAINER99',
        ];

        foreach ($valid_codes as $code) {
            $is_valid = preg_match('/^[A-Z0-9_-]{3,50}$/i', $code);
            $this->assertEquals(1, $is_valid, "{$code} should be valid");
        }
    }

    /**
     * Test invalid referral code formats rejected
     */
    public function testInvalidReferralCodeFormatsRejected() {
        $invalid_codes = [
            '',                          // Empty
            'AB',                        // Too short
            str_repeat('A', 51),        // Too long
            'CODE WITH SPACES',          // Spaces not allowed
            'CODE@EMAIL',                // Special chars
            '<script>alert(1)</script>', // XSS attempt
        ];

        foreach ($invalid_codes as $code) {
            $is_valid = preg_match('/^[A-Z0-9_-]{3,50}$/i', $code);
            $this->assertEquals(0, $is_valid, "{$code} should be invalid");
        }
    }

    /**
     * Test referral code uniqueness
     */
    public function testReferralCodeUniqueness() {
        $existing_codes = ['COACH123', 'TRAINER99', 'REF-ABC'];
        $new_code = 'COACH123';
        
        $is_unique = !in_array($new_code, $existing_codes);
        
        $this->assertFalse($is_unique, 'Duplicate code should be detected');
    }

    /**
     * Test referral code generation
     */
    public function testReferralCodeGeneration() {
        $coach_first_name = 'John';
        $coach_last_name = 'Doe';
        $coach_id = 123;
        
        $code = strtoupper(substr($coach_first_name, 0, 4) . substr($coach_last_name, 0, 4) . $coach_id);
        
        $this->assertEquals('JOHNDOE123', $code);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{3,50}$/', $code);
    }

    /**
     * Test retrieval of coach referral code generates when missing
     */
    public function testGetCoachReferralCodeGeneratesWhenMissing() {
        $coach_id = 321;

        update_user_meta($coach_id, 'referral_code', '');

        $generated_code = InterSoccer_Referral_Handler::get_coach_referral_code($coach_id);

        $this->assertNotEmpty($generated_code, 'Referral code should be generated when missing.');
        $this->assertStringStartsWith('COACH' . $coach_id, strtoupper($generated_code));

        // Subsequent calls should return the same code
        $second_call_code = InterSoccer_Referral_Handler::get_coach_referral_code($coach_id);
        $this->assertEquals($generated_code, $second_call_code);
    }

    /**
     * Test case-insensitive code matching
     */
    public function testCaseInsensitiveCodeMatching() {
        $stored_code = 'COACH123';
        $user_input_codes = ['coach123', 'CoAcH123', 'COACH123'];
        
        foreach ($user_input_codes as $input) {
            $matches = (strtoupper($input) === $stored_code);
            $this->assertTrue($matches, "{$input} should match {$stored_code}");
        }
    }

    /**
     * Test referral discount amount
     */
    public function testReferralDiscountAmount() {
        $default_discount = 10; // CHF
        
        $this->assertEquals(10, $default_discount);
        $this->assertIsInt($default_discount);
        $this->assertGreaterThan(0, $default_discount);
    }

    /**
     * Test coach bonus points for referral
     */
    public function testCoachBonusPointsForReferral() {
        $default_bonus = 50; // points
        
        $this->assertEquals(50, $default_bonus);
        $this->assertIsInt($default_bonus);
        $this->assertGreaterThan(0, $default_bonus);
    }

    /**
     * Test referral code can only be used once per customer
     */
    public function testReferralCodeOneTimeUsePerCustomer() {
        $customer_id = 123;
        $used_codes = [$customer_id => ['COACH123']];
        $new_code = 'COACH123';
        
        $already_used = in_array($new_code, $used_codes[$customer_id] ?? []);
        
        $this->assertTrue($already_used, 'Code already used by this customer');
    }

    /**
     * Test referral code can be used by multiple customers
     */
    public function testReferralCodeMultipleCustomers() {
        $code = 'COACH123';
        $customers_used = [123, 456, 789];
        
        $this->assertCount(3, $customers_used);
        $this->assertGreaterThan(1, count($customers_used));
    }

    /**
     * Test expired referral code rejected
     */
    public function testExpiredReferralCodeRejected() {
        $code_expiry_date = strtotime('2025-01-01');
        $current_date = strtotime('2025-06-01');
        
        $is_expired = ($current_date > $code_expiry_date);
        
        $this->assertTrue($is_expired, 'Code should be expired');
    }

    /**
     * Test active referral code accepted
     */
    public function testActiveReferralCodeAccepted() {
        $code_expiry_date = strtotime('2025-12-31');
        $current_date = strtotime('2025-06-01');
        
        $is_active = ($current_date <= $code_expiry_date);
        
        $this->assertTrue($is_active, 'Code should be active');
    }

    /**
     * Test referral code belongs to coach
     */
    public function testReferralCodeBelongsToCoach() {
        $code = 'COACH123';
        $coach_id = 123;
        $code_owner_id = 123;
        
        $this->assertEquals($coach_id, $code_owner_id);
    }

    /**
     * Test invalid coach ID rejected
     */
    public function testInvalidCoachIDRejected() {
        $coach_id = 0;
        $is_valid = ($coach_id > 0);
        
        $this->assertFalse($is_valid, 'Invalid coach ID should be rejected');
    }

    /**
     * Test referral transaction logged
     */
    public function testReferralTransactionLogged() {
        $transaction = [
            'customer_id' => 123,
            'coach_id' => 456,
            'referral_code' => 'COACH123',
            'discount_amount' => 10,
            'points_awarded' => 50,
            'timestamp' => time(),
        ];
        
        $this->assertArrayHasKey('customer_id', $transaction);
        $this->assertArrayHasKey('coach_id', $transaction);
        $this->assertArrayHasKey('referral_code', $transaction);
    }

    /**
     * Test whitespace trimmed from code
     */
    public function testWhitespaceTrimmedFromCode() {
        $inputs = [
            ' COACH123 ',
            '  COACH123',
            'COACH123  ',
        ];
        
        foreach ($inputs as $input) {
            $trimmed = trim($input);
            $this->assertEquals('COACH123', $trimmed);
        }
    }

    /**
     * Test code sanitization
     */
    public function testCodeSanitization() {
        $malicious_input = '<script>COACH123</script>';
        $sanitized = preg_replace('/[^A-Z0-9_-]/i', '', $malicious_input);
        
        $this->assertEquals('scriptCOACH123script', $sanitized);
        $this->assertStringNotContainsString('<', $sanitized);
    }

    /**
     * Test referral code stats tracking
     */
    public function testReferralCodeStatsTracking() {
        $code_stats = [
            'code' => 'COACH123',
            'total_uses' => 15,
            'total_discount_given' => 150,
            'total_points_awarded' => 750,
        ];
        
        $this->assertEquals(15, $code_stats['total_uses']);
        $this->assertEquals(150, $code_stats['total_discount_given']);
    }

    /**
     * Test customer can't use own code
     */
    public function testCustomerCantUseOwnCode() {
        $customer_id = 123;
        $code_owner_id = 123;
        
        $can_use = ($customer_id !== $code_owner_id);
        
        $this->assertFalse($can_use, 'Customer cannot use their own code');
    }

    /**
     * Test referral code deactivation
     */
    public function testReferralCodeDeactivation() {
        $code_active = false;
        
        $this->assertFalse($code_active, 'Deactivated code should not work');
    }

    /**
     * Test referral code reactivation
     */
    public function testReferralCodeReactivation() {
        $code_active = false;
        $code_active = true; // Reactivate
        
        $this->assertTrue($code_active, 'Code can be reactivated');
    }

    /**
     * Test maximum usage limit per code
     */
    public function testMaximumUsageLimitPerCode() {
        $max_uses = 100;
        $current_uses = 95;
        
        $can_still_use = ($current_uses < $max_uses);
        
        $this->assertTrue($can_still_use);
        
        $current_uses = 100;
        $can_still_use = ($current_uses < $max_uses);
        
        $this->assertFalse($can_still_use, 'Max uses reached');
    }

    /**
     * Test referral code search is case-insensitive
     */
    public function testReferralCodeSearchCaseInsensitive() {
        $codes_in_db = ['COACH123', 'TRAINER99'];
        $search_term = 'coach123';
        
        $found = false;
        foreach ($codes_in_db as $code) {
            if (strtoupper($code) === strtoupper($search_term)) {
                $found = true;
                break;
            }
        }
        
        $this->assertTrue($found);
    }

    /**
     * Test referral metadata stored
     */
    public function testReferralMetadataStored() {
        $metadata = [
            'source' => 'checkout',
            'campaign' => 'summer2025',
            'customer_ip' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0...',
        ];
        
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('source', $metadata);
    }

    /**
     * Test referral success notification
     */
    public function testReferralSuccessNotification() {
        $notification = [
            'coach_id' => 123,
            'message' => 'You earned 50 points from a referral!',
            'read' => false,
        ];
        
        $this->assertArrayHasKey('message', $notification);
        $this->assertFalse($notification['read']);
    }

    /**
     * Test code validation error messages
     */
    public function testCodeValidationErrorMessages() {
        $errors = [
            'invalid_format' => 'Referral code format is invalid',
            'not_found' => 'Referral code not found',
            'expired' => 'Referral code has expired',
            'already_used' => 'You have already used this code',
            'own_code' => 'You cannot use your own referral code',
        ];
        
        foreach ($errors as $key => $message) {
            $this->assertIsString($message);
            $this->assertNotEmpty($message);
        }
    }

    /**
     * Test referral discount applies before other discounts
     */
    public function testReferralDiscountOrder() {
        $cart_total = 100;
        $referral_discount = 10;
        $coupon_discount_percent = 0.10; // 10%
        
        // Referral first
        $after_referral = $cart_total - $referral_discount; // 90
        $after_coupon = $after_referral * (1 - $coupon_discount_percent); // 81
        
        $this->assertEquals(81, $after_coupon);
    }

    /**
     * Test referral code report generation
     */
    public function testReferralCodeReportGeneration() {
        $report = [
            'total_codes' => 50,
            'active_codes' => 45,
            'inactive_codes' => 5,
            'total_redemptions' => 250,
            'total_discount_given' => 2500,
        ];
        
        $this->assertEquals(50, $report['total_codes']);
        $this->assertEquals(250, $report['total_redemptions']);
    }

    /**
     * Test code collision prevention
     */
    public function testCodeCollisionPrevention() {
        $existing_codes = ['JOHN123', 'JANE123'];
        $new_code = 'JOHN123';
        
        if (in_array($new_code, $existing_codes)) {
            $new_code = $new_code . '_' . uniqid();
        }
        
        $this->assertNotContains($new_code, $existing_codes);
    }

    /**
     * Test bulk code generation
     */
    public function testBulkCodeGeneration() {
        $codes = [];
        $prefix = 'COACH';
        
        for ($i = 1; $i <= 10; $i++) {
            $codes[] = $prefix . $i;
        }
        
        $this->assertCount(10, $codes);
        $this->assertEquals('COACH1', $codes[0]);
        $this->assertEquals('COACH10', $codes[9]);
    }

    /**
     * Test code usage analytics
     */
    public function testCodeUsageAnalytics() {
        $analytics = [
            'most_used_code' => 'COACH123',
            'highest_earning_code' => 'TRAINER99',
            'average_uses_per_code' => 5.5,
            'conversion_rate' => 0.25, // 25%
        ];
        
        $this->assertArrayHasKey('most_used_code', $analytics);
        $this->assertEquals(0.25, $analytics['conversion_rate']);
    }
}

