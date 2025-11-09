<?php

use PHPUnit\Framework\TestCase;

class CustomerWidgetSummaryTest extends TestCase {
    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-elementor-widgets.php';

        global $mock_users;
        $mock_users[321] = (object) [
            'ID' => 321,
            'display_name' => 'Test Customer',
            'roles' => ['customer']
        ];
    }

    protected function tearDown(): void {
        delete_user_meta(321, 'intersoccer_points_balance');
        delete_user_meta(321, 'intersoccer_customer_referral_code');
        delete_user_meta(321, 'intersoccer_points_lifetime_earned');
        delete_user_meta(321, 'intersoccer_points_lifetime_redeemed');
        delete_user_meta(321, 'intersoccer_partnership_coach_id');

        global $mock_users;
        unset($mock_users[321], $mock_users[654]);
    }

    public function testSummaryIncludesPointsAndReferralLink() {
        update_user_meta(321, 'intersoccer_points_balance', 420);
        update_user_meta(321, 'intersoccer_points_lifetime_earned', 480);
        update_user_meta(321, 'intersoccer_points_lifetime_redeemed', 60);

        $summary = intersoccer_get_customer_widget_summary_data(321);

        $this->assertIsArray($summary);
        $this->assertSame(420, $summary['points']['balance']);
        $this->assertArrayHasKey('formatted_balance', $summary['points']);
        $this->assertArrayHasKey('link', $summary['referral']);
        $this->assertArrayHasKey('share_copy', $summary['referral']);
        $this->assertStringContainsString('cust_ref=', $summary['referral']['link']);
    }

    public function testSummaryGeneratesReferralCodeWhenMissing() {
        update_user_meta(321, 'intersoccer_points_balance', 75);
        delete_user_meta(321, 'intersoccer_customer_referral_code');

        $summary = intersoccer_get_customer_widget_summary_data(321);

        $this->assertNotEmpty($summary['referral']['code']);
        $this->assertStringContainsString($summary['referral']['code'], $summary['referral']['link']);
    }

    public function testSummaryIncludesCoachConnectionWhenLinked() {
        update_user_meta(321, 'intersoccer_points_balance', 90);
        update_user_meta(321, 'intersoccer_partnership_coach_id', 654);

        global $mock_users;
        $mock_users[654] = (object) [
            'ID' => 654,
            'display_name' => 'Coach Example',
            'roles' => ['coach']
        ];

        $summary = intersoccer_get_customer_widget_summary_data(321);

        $this->assertNotNull($summary['coach_connection']);
        $this->assertSame(654, $summary['coach_connection']['id']);
        $this->assertSame('Coach Example', $summary['coach_connection']['name']);
    }
}
