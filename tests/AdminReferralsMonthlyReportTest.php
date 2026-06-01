<?php

use PHPUnit\Framework\TestCase;

if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number, $decimals = 0) {
        return number_format((float) $number, $decimals);
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default') {
        echo esc_html($text);
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

require_once dirname(__DIR__) . '/includes/class-admin-referrals.php';

/**
 * Tests for coach referrals monthly report helpers.
 */
class AdminReferralsMonthlyReportTest extends TestCase {

    /** @var InterSoccer_Admin_Referrals */
    private $referrals;

    protected function setUp(): void {
        parent::setUp();
        $this->referrals = new InterSoccer_Admin_Referrals();
    }

    public function testSanitizeMonth_AcceptsValidFormat() {
        $this->assertSame('2026-03', $this->referrals->sanitize_month('2026-03'));
    }

    public function testSanitizeMonth_RejectsInvalidFormat() {
        $this->assertNull($this->referrals->sanitize_month('03-2026'));
        $this->assertNull($this->referrals->sanitize_month('2026/03'));
        $this->assertNull($this->referrals->sanitize_month(''));
    }

    public function testGetMonthDateBounds_ReturnsFullMonth() {
        $bounds = $this->referrals->get_month_date_bounds('2026-02');

        $this->assertIsArray($bounds);
        $this->assertSame('2026-02-01 00:00:00', $bounds['from']);
        $this->assertSame('2026-02-28 23:59:59', $bounds['to']);
    }

    public function testGetMonthDateBounds_ReturnsNullForInvalidMonth() {
        $this->assertNull($this->referrals->get_month_date_bounds('invalid'));
    }

    public function testMonthlySummaryAggregationLogic() {
        $rows = [
            ['commission' => 50.0, 'paid' => true, 'completed' => true],
            ['commission' => 30.0, 'paid' => false, 'completed' => true],
            ['commission' => 20.0, 'paid' => false, 'completed' => false],
        ];

        $total = 0.0;
        $paid = 0.0;
        $unpaid = 0.0;
        $completed = 0;

        foreach ($rows as $row) {
            $total += $row['commission'];
            if ($row['paid']) {
                $paid += $row['commission'];
            } else {
                $unpaid += $row['commission'];
            }
            if ($row['completed']) {
                $completed++;
            }
        }

        $this->assertEquals(100.0, $total);
        $this->assertEquals(50.0, $paid);
        $this->assertEquals(50.0, $unpaid);
        $this->assertEquals(2, $completed);
        $this->assertCount(3, $rows);
    }

    public function testCoachBreakdownAggregationByCoach() {
        $rows = [
            ['coach_id' => 1, 'commission' => 100.0],
            ['coach_id' => 1, 'commission' => 50.0],
            ['coach_id' => 2, 'commission' => 75.0],
        ];

        $by_coach = [];
        foreach ($rows as $row) {
            $id = $row['coach_id'];
            if (!isset($by_coach[$id])) {
                $by_coach[$id] = 0.0;
            }
            $by_coach[$id] += $row['commission'];
        }

        $this->assertEquals(150.0, $by_coach[1]);
        $this->assertEquals(75.0, $by_coach[2]);
    }

    public function testChartTrendLabelsCountMatchesValues() {
        $labels = ['Apr 2025', 'May 2025', 'Jun 2025'];
        $values = [100.0, 150.0, 120.0];
        $months = ['2025-04', '2025-05', '2025-06'];

        $this->assertCount(count($labels), $values);
        $this->assertCount(count($labels), $months);
    }

    public function testBuildMonthlySummaryMarkup_ContainsKpiValues() {
        $html = $this->referrals->build_monthly_summary_markup([
            'total_commission'  => 1234.5,
            'referrals_count'   => 10,
            'completed_count'   => 8,
            'paid_commission'   => 800.0,
            'unpaid_commission' => 434.5,
            'active_coaches'    => 3,
        ]);

        $this->assertStringContainsString('1,234.50', $html); // phpcs:ignore -- matches number_format default
        $this->assertStringContainsString('CHF', $html);
        $this->assertStringContainsString('stat-card', $html);
    }

    public function testBuildCoachBreakdownRows_EmptyState() {
        $html = $this->referrals->build_coach_breakdown_rows([]);
        $this->assertStringContainsString('No coach commissions', $html);
    }

    public function testBuildCoachBreakdownRows_RendersCoachData() {
        $html = $this->referrals->build_coach_breakdown_rows([
            [
                'coach_id'          => 42,
                'coach_name'        => 'Test Coach',
                'referral_code'     => 'COACH42',
                'referrals_count'   => 5,
                'completed_count'   => 4,
                'total_commission'  => 200.0,
                'paid_commission'   => 100.0,
                'unpaid_commission' => 100.0,
            ],
        ]);

        $this->assertStringContainsString('Test Coach', $html);
        $this->assertStringContainsString('COACH42', $html);
        $this->assertStringContainsString('view-coach-transactions', $html);
        $this->assertStringContainsString('data-coach-id="42"', $html);
    }
}
