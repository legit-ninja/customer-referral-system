<?php

use PHPUnit\Framework\TestCase;

class ElementorPointsBalanceReadTest extends TestCase {
    private $saved_meta;
    private $saved_caps;

    protected function setUp(): void {
        global $mock_user_meta, $mock_user_capabilities;
        $this->saved_meta = $mock_user_meta;
        $this->saved_caps = $mock_user_capabilities;

        if (!class_exists('Elementor\\Widget_Base')) {
            eval(<<<'PHP'
namespace Elementor {
    class Widget_Base {
        public function __construct($data = [], $args = null) {}
        public function get_settings_for_display() { return []; }
    }
}
PHP
            );
        }

        require_once __DIR__ . '/../includes/class-elementor-widgets.php';
    }

    protected function tearDown(): void {
        global $mock_user_meta, $mock_user_capabilities;
        $mock_user_meta = $this->saved_meta;
        $mock_user_capabilities = $this->saved_caps;
    }

    private function invoke($object, $method, array $args) {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($object, $args);
    }

    public function testWidgetsReadPointsBalanceWhenLegacyCreditsAreZero() {
        global $mock_user_meta, $mock_user_capabilities;

        $user_id = 7301;
        $mock_user_meta[$user_id] = [
            'intersoccer_points_balance' => 42,
            'intersoccer_customer_credits' => 0,
        ];
        $mock_user_capabilities['view_referral_dashboard'] = false;

        $dashboard = new InterSoccer_Customer_Dashboard_Widget();
        $credits = $this->invoke($dashboard, 'get_customer_credits_safe', [$user_id]);
        $this->assertEquals(42, $credits);

        $stats = new InterSoccer_Referral_Stats_Widget();
        $stat = $this->invoke($stats, 'get_stat_value', ['credits', $user_id]);
        $this->assertSame('42', $stat);

        $progress = new InterSoccer_Customer_Progress_Widget();
        ob_start();
        $this->invoke($progress, 'render_credits_progress', [$user_id, []]);
        $html = ob_get_clean();
        $this->assertStringContainsString('42 / 1000', $html);
        $this->assertStringNotContainsString('0 / 1000', $html);
    }
}
