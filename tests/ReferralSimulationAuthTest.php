<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-simulator.php';
require_once __DIR__ . '/../includes/class-admin-settings.php';

/**
 * A logged-in customer must not be able to run the referral simulator.
 */
class ReferralSimulationAuthTest extends TestCase {

    protected function tearDown(): void {
        global $mock_user_capabilities, $mock_ajax_referer_valid, $mock_wp_json_response, $mock_orders;

        $mock_user_capabilities = [];
        $mock_ajax_referer_valid = null;
        $mock_wp_json_response = null;
        $mock_orders = [];
        $_POST = [];

        parent::tearDown();
    }

    public function testRunReferralSimulationRejectsAMissingNonce() {
        global $mock_ajax_referer_valid, $mock_user_capabilities, $mock_wp_json_response;

        $mock_ajax_referer_valid = false;
        $mock_user_capabilities = ['manage_options' => true];
        $mock_wp_json_response = null;

        $_POST = [
            'action' => 'intersoccer_run_referral_simulation',
            'mode' => 'date-range',
            'nonce' => 'missing',
        ];

        $settings = new InterSoccer_Admin_Settings();

        try {
            $settings->ajax_run_referral_simulation();
            $this->fail('A missing nonce must stop the simulation before it reads orders.');
        } catch (Exception $e) {
            $this->assertStringContainsString('Nonce', $e->getMessage());
        }

        $this->assertNull($mock_wp_json_response);
    }

    public function testCustomerCannotReadOrderOrRevenueFromTheSimulator() {
        global $mock_ajax_referer_valid, $mock_user_capabilities, $mock_wp_json_response, $mock_orders;

        $mock_ajax_referer_valid = null;
        $mock_user_capabilities = ['manage_options' => false];
        $mock_wp_json_response = null;

        $order = new WC_Order(4242);
        $order->set_id(4242);
        $order->set_total(9999);
        $mock_orders = [$order];

        $_POST = [
            'action' => 'intersoccer_run_referral_simulation',
            'mode' => 'date-range',
            'nonce' => 'test_nonce_intersoccer_simulator_nonce',
            'settings' => json_encode([
                'date_from' => '2026-01-01',
                'date_to' => '2026-01-31',
            ]),
        ];

        $settings = new InterSoccer_Admin_Settings();
        $settings->ajax_run_referral_simulation();

        $this->assertIsArray($mock_wp_json_response);
        $this->assertFalse($mock_wp_json_response['success']);
        $encoded = json_encode($mock_wp_json_response);
        $this->assertStringNotContainsString('9999', $encoded);
        $this->assertStringNotContainsString('total_revenue', $encoded);
    }
}
