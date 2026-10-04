<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-simulator.php';
require_once __DIR__ . '/../includes/class-admin-settings.php';

class ResetAllCreditsTest extends TestCase {
    private $saved_meta;
    private $saved_caps;
    private $saved_post;
    private $saved_user_id;
    private $saved_users;

    protected function setUp(): void {
        global $mock_user_meta, $mock_user_capabilities, $mock_current_user_id, $mock_users;
        $this->saved_meta = $mock_user_meta;
        $this->saved_caps = $mock_user_capabilities;
        $this->saved_post = $_POST;
        $this->saved_user_id = $mock_current_user_id;
        $this->saved_users = $mock_users;
        $mock_user_capabilities['manage_options'] = true;
        $mock_current_user_id = 7200;
        $mock_users[7200] = (object) [
            'ID' => 7200,
            'user_login' => 'admin',
            'roles' => ['administrator'],
        ];
        $_POST = ['nonce' => 'test'];
    }

    protected function tearDown(): void {
        global $mock_user_meta, $mock_user_capabilities, $mock_wp_json_response, $mock_current_user_id, $mock_users;
        $mock_user_meta = $this->saved_meta;
        $mock_user_capabilities = $this->saved_caps;
        $mock_current_user_id = $this->saved_user_id;
        $mock_users = $this->saved_users;
        $mock_wp_json_response = null;
        $_POST = $this->saved_post;
    }

    public function testResetAllCustomerCreditsDeletesPointsBalance() {
        global $mock_user_meta, $mock_wp_json_response;

        $user_id = 7201;
        $mock_user_meta[$user_id] = [
            'intersoccer_points_balance' => 15,
            'intersoccer_customer_credits' => 9,
            'intersoccer_total_credits_earned' => 9,
            'nickname' => 'keep-me',
        ];

        (new InterSoccer_Admin_Settings())->reset_all_customer_credits();

        $this->assertTrue($mock_wp_json_response['success']);
        $this->assertArrayNotHasKey('intersoccer_points_balance', $mock_user_meta[$user_id]);
        $this->assertArrayNotHasKey('intersoccer_customer_credits', $mock_user_meta[$user_id]);
        $this->assertArrayNotHasKey('intersoccer_total_credits_earned', $mock_user_meta[$user_id]);
        $this->assertSame('keep-me', $mock_user_meta[$user_id]['nickname']);
        $this->assertGreaterThan(0, $mock_wp_json_response['data']['deleted_records']);
    }
}
