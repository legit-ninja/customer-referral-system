<?php

use PHPUnit\Framework\TestCase;

class ElementorCustomerHeaderBadgeWidgetTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-elementor-widgets.php';

        if (!function_exists('wp_get_current_user')) {
            function wp_get_current_user() {
                global $mock_current_elementor_user;
                if (!$mock_current_elementor_user) {
                    $mock_current_elementor_user = (object) [
                        'ID' => 99,
                        'roles' => ['customer'],
                    ];
                }
                return $mock_current_elementor_user;
            }
        }

        if (!function_exists('is_user_logged_in')) {
            function is_user_logged_in() {
                return true;
            }
        }

        global $mock_current_elementor_user;
        $mock_current_elementor_user = (object) [
            'ID' => 99,
            'roles' => ['customer'],
        ];
        update_user_meta(99, 'intersoccer_points_balance', 250);
    }

    protected function tearDown(): void {
        global $mock_current_elementor_user;
        $mock_current_elementor_user = null;
        delete_user_meta(99, 'intersoccer_points_balance');
    }

    public function testWidgetRendersForCustomerRole() {
        $widget = new InterSoccer_Customer_Header_Badge_Widget();
        ob_start();
        $widget->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('sc_layouts_customer_badge', $output);
        $this->assertStringContainsString('Share Link', $output);
    }

    public function testWidgetHiddenForCoachRole() {
        global $mock_current_elementor_user;
        $mock_current_elementor_user = (object) [
            'ID' => 77,
            'roles' => ['coach'],
        ];

        $widget = new InterSoccer_Customer_Header_Badge_Widget();
        ob_start();
        $widget->render();
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    public function testWidgetRespectsShowPointsSetting() {
        $widget = new InterSoccer_Customer_Header_Badge_Widget();
        $widget->set_settings([
            'show_points' => 'no',
            'show_referral_link' => 'yes',
            'badge_label' => 'Test Label',
            'cta_label' => 'Test CTA',
        ]);

        ob_start();
        $widget->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('Test Label', $output);
        $this->assertStringContainsString('Test CTA', $output);
        $this->assertStringNotContainsString('intersoccer-customer-badge__points', $output);
    }
}

