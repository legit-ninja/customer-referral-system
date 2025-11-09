<?php

use PHPUnit\Framework\TestCase;

class CustomerAccountEndpointTest extends TestCase {

    protected function setUp(): void {
        if (!defined('EP_ROOT')) {
            define('EP_ROOT', 1);
        }
        if (!defined('EP_PAGES')) {
            define('EP_PAGES', 2);
        }

        if (!function_exists('add_rewrite_endpoint')) {
            function add_rewrite_endpoint($name, $places) {
                global $mock_rewrite_endpoints;
                if (!isset($mock_rewrite_endpoints)) {
                    $mock_rewrite_endpoints = [];
                }
                $mock_rewrite_endpoints[$name] = $places;
            }
        }

        if (!function_exists('wp_get_current_user')) {
            function wp_get_current_user() {
                global $mock_current_user_instance;
                if (!$mock_current_user_instance) {
                    $mock_current_user_instance = (object) [
                        'ID' => 1,
                        'roles' => ['customer'],
                    ];
                }
                return $mock_current_user_instance;
            }
        }

        if (!function_exists('is_user_logged_in')) {
            function is_user_logged_in() {
                return true;
            }
        }

        global $mock_current_user_instance;
        $mock_current_user_instance = (object) [
            'ID' => 1,
            'roles' => ['customer'],
        ];
    }

    protected function tearDown(): void {
        global $mock_rewrite_endpoints, $mock_current_user_instance;
        $mock_rewrite_endpoints = [];
        $mock_current_user_instance = null;
    }

    public function testRegisterCustomerAccountEndpointAddsRewriteRule() {
        global $mock_rewrite_endpoints;
        $mock_rewrite_endpoints = [];

        $plugin = intersoccer_referral_system();
        $plugin->register_customer_account_endpoint();

        $this->assertArrayHasKey('referrals', $mock_rewrite_endpoints);
        $this->assertGreaterThan(0, $mock_rewrite_endpoints['referrals']);
    }

    public function testCustomerMenuIncludesReferEarnForCustomers() {
        global $mock_current_user_instance;
        $mock_current_user_instance = (object) [
            'ID' => 25,
            'roles' => ['customer'],
        ];

        $plugin = intersoccer_referral_system();
        $menu = [
            'dashboard' => 'Dashboard',
            'orders' => 'Orders',
            'customer-logout' => 'Logout',
        ];

        $filtered = $plugin->add_customer_dashboard_menu_item($menu);
        $this->assertArrayHasKey('referrals', $filtered);

        $keys = array_keys($filtered);
        $this->assertEquals('referrals', $keys[1], 'Referral link should appear after the dashboard item.');
    }

    public function testCustomerMenuHiddenForCoachRole() {
        global $mock_current_user_instance;
        $mock_current_user_instance = (object) [
            'ID' => 42,
            'roles' => ['coach'],
        ];

        $plugin = intersoccer_referral_system();
        $menu = [
            'dashboard' => 'Dashboard',
            'orders' => 'Orders',
            'customer-logout' => 'Logout',
        ];

        $filtered = $plugin->add_customer_dashboard_menu_item($menu);
        $this->assertArrayNotHasKey('referrals', $filtered);
    }

    public function testCustomerEndpointRendersDashboard() {
        global $mock_current_user_instance;
        $mock_current_user_instance = (object) [
            'ID' => 77,
            'roles' => ['customer'],
        ];

        $plugin = intersoccer_referral_system();

        ob_start();
        $plugin->render_customer_account_endpoint();
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('Your Referral Dashboard', $output);
    }
}

