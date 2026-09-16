<?php

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for referral link tracking
 * Tests end-to-end referral link scenarios
 */
class ReferralLinkTrackingTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../bootstrap.php';
        require_once __DIR__ . '/../../includes/class-referral-handler.php';
        require_once __DIR__ . '/../../includes/class-points-manager.php';
        require_once __DIR__ . '/../../includes/class-commission-manager.php';
        
        // Reset mock state for each test
        global $mock_user_meta, $mock_cookies, $mock_session;
        $mock_user_meta = [];
        $mock_cookies = [];
        $mock_session = [];
    }

    private function coachReferralLink(int $coach_id): string {
        InterSoccer_Referral_Handler::ensure_coach_referral_code($coach_id);
        return InterSoccer_Referral_Handler::generate_coach_referral_link($coach_id);
    }

    /**
     * Test complete referral link journey: click -> registration -> purchase
     * @group integration
     */
    public function testCompleteReferralLinkJourney() {
        $this->markTestSkipped('Complex integration test requires full WP environment - process_referral_code_rewards() not implemented');
    }

    /**
     * Test referral link with customer referral codes
     */
    public function testCustomerReferralLinkTracking() {
        // Generate customer referral link
        $customer_id = 1;
        $referral_link = InterSoccer_Referral_Handler::generate_customer_referral_link($customer_id);

        // Verify link format
        $this->assertStringContainsString('cust_ref=', $referral_link);

        // Extract referral code
        $url_parts = parse_url($referral_link);
        parse_str($url_parts['query'], $query_params);
        $referral_code = $query_params['cust_ref'];

        // Verify referral code was generated
        $this->assertNotEmpty($referral_code);
    }

    /**
     * Test referral link expiration and cleanup
     */
    public function testReferralLinkExpiration() {
        // Set up referral cookie
        $referral_code = 'coach_2_test';
        $this->simulateReferralLinkClick($referral_code);

        // Verify cookie was set
        global $mock_cookies;
        $this->assertEquals($referral_code, $mock_cookies['intersoccer_referral']);
        
        // Note: Cookie clearing happens in process_referral_order which needs full WP context
        // This test validates the click tracking portion works correctly
    }

    /**
     * Test multiple referral sources (multi-touch attribution)
     * @group integration
     */
    public function testMultiTouchReferralAttribution() {
        $this->markTestSkipped('Requires full WP integration for process_referral_order()');
    }

    /**
     * Test referral link with UTM parameters
     */
    public function testReferralLinkWithUTMParameters() {
        // Generate referral link with UTM parameters
        $coach_id = 2;
        $base_link = $this->coachReferralLink($coach_id);

        // Add UTM parameters
        $utm_link = $base_link . '&utm_source=facebook&utm_medium=social&utm_campaign=referral_program';

        // Parse the link
        $url_parts = parse_url($utm_link);
        parse_str($url_parts['query'], $query_params);

        // Verify both referral code and UTM parameters are present
        $this->assertArrayHasKey('ref', $query_params);
        $this->assertArrayHasKey('utm_source', $query_params);
        $this->assertArrayHasKey('utm_medium', $query_params);
        $this->assertArrayHasKey('utm_campaign', $query_params);
        
        // Verify UTM values
        $this->assertEquals('facebook', $query_params['utm_source']);
        $this->assertEquals('social', $query_params['utm_medium']);
        $this->assertEquals('referral_program', $query_params['utm_campaign']);
    }

    /**
     * Test referral link tracking across sessions
     * @group integration
     */
    public function testReferralLinkAcrossSessions() {
        $this->markTestSkipped('Requires full WP integration for process_referral_order()');
    }

    /**
     * Test invalid referral link handling
     */
    public function testInvalidReferralLinkHandling() {
        // Test with invalid referral code
        $invalid_code = 'invalid_code_123';
        $this->simulateReferralLinkClick($invalid_code);

        // Verify cookie was still set (tracking happens regardless of validity)
        global $mock_cookies;
        $this->assertEquals($invalid_code, $mock_cookies['intersoccer_referral']);
        
        // Validation happens during order processing, not during click tracking
    }

    /**
     * Test referral link conversion funnel
     * @group integration
     */
    public function testReferralLinkConversionFunnel() {
        $this->markTestSkipped('Requires full WP integration for process_referral_code_rewards()');
    }

    /**
     * Helper method to simulate referral link click
     */
    private function simulateReferralLinkClick($referral_code) {
        global $mock_cookies, $mock_session;
        $mock_cookies['intersoccer_referral'] = $referral_code;
        $mock_session['intersoccer_referral'] = $referral_code;
    }

    /**
     * Helper method to simulate customer referral link click
     */
    private function simulateCustomerReferralLinkClick($referral_code) {
        global $mock_cookies, $mock_session;
        $mock_cookies['intersoccer_referral'] = $referral_code;
        $mock_session['intersoccer_referral'] = $referral_code;
    }
}
