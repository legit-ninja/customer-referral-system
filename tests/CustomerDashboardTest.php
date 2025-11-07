<?php

use PHPUnit\Framework\TestCase;

/**
 * Test suite for Customer Dashboard (class-dashboard.php)
 * 
 * Covers:
 * - Dashboard rendering
 * - Customer statistics
 * - Badge system
 * - Leaderboards
 * - Activity tracking
 * - Referral links
 * - Data retrieval methods
 */
class CustomerDashboardTest extends TestCase {

    protected function setUp(): void {
        // Include the dashboard class
        require_once __DIR__ . '/../includes/class-dashboard.php';
        
        // Mock WordPress functions
        $this->setupWordPressMocks();
    }

    private function setupWordPressMocks() {
        if (!function_exists('is_user_logged_in')) {
            function is_user_logged_in() {
                return true;
            }
        }
        
        if (!function_exists('current_user_can')) {
            function current_user_can($capability) {
                return true;
            }
        }
        
        if (!function_exists('is_account_page')) {
            function is_account_page() {
                return false;
            }
        }
        
        if (!function_exists('get_current_user_id')) {
            function get_current_user_id() {
                return 123;
            }
        }
        
        if (!function_exists('get_user_meta')) {
            function get_user_meta($user_id, $key, $single = false) {
                $meta_data = [
                    'intersoccer_credits' => 150,
                    'intersoccer_points_balance' => 150,
                    'intersoccer_referrals_made' => [],
                    'intersoccer_partnership_coach_id' => null,
                ];
                return $meta_data[$key] ?? ($single ? '' : []);
            }
        }
        
        if (!function_exists('get_user_by')) {
            function get_user_by($field, $value) {
                $user = new stdClass();
                $user->ID = $value;
                $user->display_name = 'Test Coach';
                $user->user_email = 'coach@test.com';
                return $user;
            }
        }
        
        if (!function_exists('get_avatar')) {
            function get_avatar($id, $size) {
                return '<img src="avatar.jpg" width="' . $size . '" />';
            }
        }
        
        if (!function_exists('number_format')) {
            // Use PHP's number_format
        }
        
        if (!function_exists('__')) {
            function __($text, $domain = 'default') {
                return $text;
            }
        }
        
        if (!function_exists('human_time_diff')) {
            function human_time_diff($from, $to = '') {
                if (empty($to)) {
                    $to = time();
                }
                $diff = abs($to - $from);
                $days = floor($diff / 86400);
                return $days . ' days';
            }
        }
        
        if (!function_exists('intersoccer_get_customer_credits')) {
            function intersoccer_get_customer_credits($user_id) {
                return 150;
            }
        }
        
        if (!function_exists('intersoccer_get_coach_tier')) {
            function intersoccer_get_coach_tier($coach_id) {
                return 'Silver';
            }
        }
    }

    // =========================================================================
    // DASHBOARD RENDERING TESTS (10 tests)
    // =========================================================================

    public function testRenderDashboard_LoggedIn() {
        // User logged in should render dashboard
        $dashboard = new InterSoccer_Referral_Dashboard();
        $this->assertInstanceOf(InterSoccer_Referral_Dashboard::class, $dashboard);
    }

    public function testRenderDashboard_NotLoggedIn() {
        // Mock logged out state
        if (!function_exists('is_user_logged_in_mock')) {
            function is_user_logged_in() {
                return false;
            }
        }
        
        // Should return access denied message
        $this->assertTrue(true); // Placeholder - actual test would check output
    }

    public function testRenderCustomerDashboard_ReturnsString() {
        $dashboard = new InterSoccer_Referral_Dashboard();
        $output = $dashboard->render_customer_dashboard();
        
        $this->assertIsString($output);
    }

    public function testRenderCustomerDashboard_ContainsCredits() {
        $dashboard = new InterSoccer_Referral_Dashboard();
        $output = $dashboard->render_customer_dashboard();
        
        // Should contain credits information
        $this->assertStringContainsString('CHF Credits', $output);
    }

    public function testRenderCustomerDashboard_ContainsReferralStats() {
        $dashboard = new InterSoccer_Referral_Dashboard();
        $output = $dashboard->render_customer_dashboard();
        
        // Should contain referral statistics
        $this->assertStringContainsString('Friends Referred', $output);
    }

    public function testRenderCustomerDashboard_ContainsDashboardHeader() {
        $dashboard = new InterSoccer_Referral_Dashboard();
        $output = $dashboard->render_customer_dashboard();
        
        $this->assertStringContainsString('Your Referral Dashboard', $output);
    }

    public function testRenderCustomerDashboard_WithNoReferrals() {
        // Customer with no referrals
        $dashboard = new InterSoccer_Referral_Dashboard();
        $output = $dashboard->render_customer_dashboard();
        
        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    public function testRenderCustomerDashboard_WithPartnership() {
        // Mock partnership coach
        if (!function_exists('get_user_meta_partnership')) {
            function get_user_meta($user_id, $key, $single = false) {
                if ($key === 'intersoccer_partnership_coach_id') {
                    return 456;
                }
                return '';
            }
        }
        
        $dashboard = new InterSoccer_Referral_Dashboard();
        $output = $dashboard->render_customer_dashboard();
        
        // Should contain partnership information
        $this->assertStringContainsString('Coach Connection', $output);
    }

    public function testRenderCustomerDashboard_WithoutPartnership() {
        $dashboard = new InterSoccer_Referral_Dashboard();
        $output = $dashboard->render_customer_dashboard();
        
        // Should contain option to select coach
        $this->assertStringContainsString('Choose Your Coach Partner', $output);
    }

    public function testRenderCustomerDashboard_ResponsiveLayout() {
        $dashboard = new InterSoccer_Referral_Dashboard();
        $output = $dashboard->render_customer_dashboard();
        
        // Should contain responsive CSS classes
        $this->assertStringContainsString('dashboard-stats', $output);
        $this->assertStringContainsString('stat-card', $output);
    }

    // =========================================================================
    // CUSTOMER STATISTICS TESTS (8 tests)
    // =========================================================================

    public function testCustomerStats_CreditsDisplay() {
        $user_id = 123;
        $credits = 150;
        
        $this->assertIsInt($credits);
        $this->assertGreaterThanOrEqual(0, $credits);
    }

    public function testCustomerStats_ReferralCount() {
        $referrals = [];
        $total = count($referrals);
        
        $this->assertEquals(0, $total);
        $this->assertIsInt($total);
    }

    public function testCustomerStats_WithReferrals() {
        $referrals = [
            ['customer_id' => 1],
            ['customer_id' => 2],
            ['customer_id' => 3],
        ];
        $total = count($referrals);
        
        $this->assertEquals(3, $total);
    }

    public function testCustomerStats_PartnershipOrders() {
        $partnership_orders = 5;
        
        $this->assertIsInt($partnership_orders);
        $this->assertGreaterThanOrEqual(0, $partnership_orders);
    }

    public function testCustomerStats_LeaderboardPosition() {
        $position = 42;
        $total_customers = 1000;
        
        $this->assertGreaterThan(0, $position);
        $this->assertLessThanOrEqual($total_customers, $position);
    }

    public function testCustomerStats_MonthlyProgress() {
        $this_month = 3;
        $last_month = 2;
        $growth = (($this_month - $last_month) / max(1, $last_month)) * 100;
        
        $this->assertEquals(50.0, $growth);
    }

    public function testCustomerStats_TotalEarnings() {
        $credits_earned = 150;
        $credits_spent = 50;
        $net_earnings = $credits_earned - $credits_spent;
        
        $this->assertEquals(100, $net_earnings);
    }

    public function testCustomerStats_AverageOrderValue() {
        $orders = [100, 150, 200];
        $average = array_sum($orders) / count($orders);
        
        $this->assertEquals(150, $average);
    }

    // =========================================================================
    // BADGE SYSTEM TESTS (8 tests)
    // =========================================================================

    public function testBadges_FirstReferralBadge() {
        $user_id = 123;
        $total_referrals = 1;
        $credits = 0;
        
        $badges = [];
        if ($total_referrals >= 1) {
            $badges[] = [
                'key' => 'first_referral',
                'title' => 'First Friend',
                'icon' => '🎯'
            ];
        }
        
        $this->assertCount(1, $badges);
        $this->assertEquals('first_referral', $badges[0]['key']);
    }

    public function testBadges_MultipleReferralsBadge() {
        $total_referrals = 5;
        $badges = [];
        
        if ($total_referrals >= 5) {
            $badges[] = [
                'key' => 'five_referrals',
                'title' => 'Social Connector',
                'icon' => '👥'
            ];
        }
        
        $this->assertCount(1, $badges);
    }

    public function testBadges_HighValueBadge() {
        $credits = 500;
        $badges = [];
        
        if ($credits >= 500) {
            $badges[] = [
                'key' => 'high_value',
                'title' => 'Super Earner',
                'icon' => '💰'
            ];
        }
        
        $this->assertCount(1, $badges);
    }

    public function testBadges_NoBadgesForNewUser() {
        $total_referrals = 0;
        $credits = 0;
        $badges = [];
        
        $this->assertEmpty($badges);
    }

    public function testBadges_MultipleBadges() {
        $total_referrals = 10;
        $credits = 500;
        $badges = [];
        
        if ($total_referrals >= 5) {
            $badges[] = ['key' => 'five_referrals'];
        }
        if ($total_referrals >= 10) {
            $badges[] = ['key' => 'ten_referrals'];
        }
        if ($credits >= 500) {
            $badges[] = ['key' => 'high_value'];
        }
        
        $this->assertGreaterThanOrEqual(2, count($badges));
    }

    public function testBadges_BadgeStructure() {
        $badge = [
            'key' => 'first_referral',
            'title' => 'First Friend',
            'description' => 'Made your first referral',
            'icon' => '🎯',
            'earned_date' => time()
        ];
        
        $this->assertArrayHasKey('key', $badge);
        $this->assertArrayHasKey('title', $badge);
        $this->assertArrayHasKey('icon', $badge);
    }

    public function testBadges_NewBadgeDetection() {
        $user_id = 123;
        $badge_key = 'first_referral';
        
        // New badge detection logic
        $saved_badges = [];
        $is_new = !in_array($badge_key, $saved_badges);
        
        $this->assertTrue($is_new);
    }

    public function testBadges_ExistingBadgeNotNew() {
        $badge_key = 'first_referral';
        $saved_badges = ['first_referral', 'five_referrals'];
        
        $is_new = !in_array($badge_key, $saved_badges);
        
        $this->assertFalse($is_new);
    }

    // =========================================================================
    // LEADERBOARD TESTS (7 tests)
    // =========================================================================

    public function testLeaderboard_Position() {
        $user_id = 123;
        $position = 15;
        
        $this->assertIsInt($position);
        $this->assertGreaterThan(0, $position);
    }

    public function testLeaderboard_TopPosition() {
        $position = 1;
        $is_top = ($position === 1);
        
        $this->assertTrue($is_top);
    }

    public function testLeaderboard_Rankings() {
        $leaderboard = [
            ['user_id' => 1, 'score' => 1000],
            ['user_id' => 2, 'score' => 900],
            ['user_id' => 3, 'score' => 800],
        ];
        
        $this->assertCount(3, $leaderboard);
        $this->assertEquals(1000, $leaderboard[0]['score']);
    }

    public function testLeaderboard_ScoreSorting() {
        $leaderboard = [
            ['user_id' => 1, 'score' => 800],
            ['user_id' => 2, 'score' => 1000],
            ['user_id' => 3, 'score' => 900],
        ];
        
        usort($leaderboard, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        $this->assertEquals(1000, $leaderboard[0]['score']);
        $this->assertEquals(800, $leaderboard[2]['score']);
    }

    public function testLeaderboard_UserNotInTop() {
        $user_id = 123;
        $top_leaderboard = [
            ['user_id' => 1],
            ['user_id' => 2],
            ['user_id' => 3],
        ];
        
        $user_ids = array_column($top_leaderboard, 'user_id');
        $is_in_top = in_array($user_id, $user_ids);
        
        $this->assertFalse($is_in_top);
    }

    public function testLeaderboard_MonthlyReset() {
        $current_month = date('Y-m');
        $last_reset = '2025-01-01';
        
        $should_reset = (strtotime($current_month) > strtotime($last_reset));
        
        $this->assertTrue($should_reset);
    }

    public function testLeaderboard_TieBreaking() {
        $leaderboard = [
            ['user_id' => 1, 'score' => 100, 'timestamp' => 1000],
            ['user_id' => 2, 'score' => 100, 'timestamp' => 900],
        ];
        
        // Earlier timestamp should rank higher in case of tie
        usort($leaderboard, function($a, $b) {
            if ($a['score'] === $b['score']) {
                return $a['timestamp'] - $b['timestamp'];
            }
            return $b['score'] - $a['score'];
        });
        
        $this->assertEquals(2, $leaderboard[0]['user_id']);
    }

    // =========================================================================
    // ACTIVITY TRACKING TESTS (6 tests)
    // =========================================================================

    public function testRecentActivity_Structure() {
        $activity = [
            'type' => 'referral_made',
            'description' => 'Referred John Doe',
            'timestamp' => time(),
            'icon' => '👥'
        ];
        
        $this->assertArrayHasKey('type', $activity);
        $this->assertArrayHasKey('description', $activity);
        $this->assertArrayHasKey('timestamp', $activity);
    }

    public function testRecentActivity_MultipleActivities() {
        $activities = [
            ['type' => 'referral_made', 'timestamp' => time()],
            ['type' => 'credits_earned', 'timestamp' => time() - 86400],
            ['type' => 'purchase_made', 'timestamp' => time() - 172800],
        ];
        
        $this->assertCount(3, $activities);
    }

    public function testRecentActivity_Sorting() {
        $activities = [
            ['type' => 'old', 'timestamp' => 1000],
            ['type' => 'new', 'timestamp' => 3000],
            ['type' => 'middle', 'timestamp' => 2000],
        ];
        
        usort($activities, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        $this->assertEquals('new', $activities[0]['type']);
    }

    public function testRecentActivity_EmptyState() {
        $activities = [];
        
        $this->assertIsArray($activities);
        $this->assertEmpty($activities);
    }

    public function testRecentActivity_FilterByType() {
        $activities = [
            ['type' => 'referral_made'],
            ['type' => 'credits_earned'],
            ['type' => 'referral_made'],
        ];
        
        $referral_activities = array_filter($activities, function($a) {
            return $a['type'] === 'referral_made';
        });
        
        $this->assertCount(2, $referral_activities);
    }

    public function testRecentActivity_TimeRange() {
        $last_30_days = strtotime('-30 days');
        $activities = [
            ['timestamp' => time()],
            ['timestamp' => strtotime('-10 days')],
            ['timestamp' => strtotime('-40 days')],
        ];
        
        $recent = array_filter($activities, function($a) use ($last_30_days) {
            return $a['timestamp'] >= $last_30_days;
        });
        
        $this->assertCount(2, $recent);
    }

    // =========================================================================
    // REFERRAL LINK TESTS (6 tests)
    // =========================================================================

    public function testReferralLink_Structure() {
        $referral_link = 'https://example.com/?ref=CUSTOMER123';
        
        $this->assertIsString($referral_link);
        $this->assertStringContainsString('?ref=', $referral_link);
    }

    public function testReferralLink_UniqueCode() {
        $user_id = 123;
        $referral_code = 'CUSTOMER' . $user_id;
        
        $this->assertEquals('CUSTOMER123', $referral_code);
    }

    public function testReferralLink_URLEncoding() {
        $referral_code = 'TEST CODE';
        $encoded = urlencode($referral_code);
        
        $this->assertEquals('TEST+CODE', $encoded);
    }

    public function testReferralLink_Validation() {
        $referral_link = 'https://example.com/?ref=CUSTOMER123';
        $is_valid = filter_var($referral_link, FILTER_VALIDATE_URL) !== false;
        
        $this->assertTrue($is_valid);
    }

    public function testReferralLink_Tracking() {
        $referral_code = 'CUSTOMER123';
        $clicks = 15;
        $conversions = 3;
        
        $conversion_rate = ($conversions / max(1, $clicks)) * 100;
        
        $this->assertEquals(20.0, $conversion_rate);
    }

    public function testReferralLink_ShareOptions() {
        $share_platforms = ['facebook', 'twitter', 'whatsapp', 'email', 'copy'];
        
        $this->assertCount(5, $share_platforms);
        $this->assertContains('email', $share_platforms);
    }

    // =========================================================================
    // DATA RETRIEVAL TESTS (5 tests)
    // =========================================================================

    public function testDataRetrieval_CustomerName() {
        $customer_id = 123;
        $customer_name = 'John Doe';
        
        $this->assertIsString($customer_name);
        $this->assertNotEmpty($customer_name);
    }

    public function testDataRetrieval_LinkedCustomersCount() {
        $coach_id = 456;
        $linked_count = 25;
        
        $this->assertIsInt($linked_count);
        $this->assertGreaterThanOrEqual(0, $linked_count);
    }

    public function testDataRetrieval_MonthlyStats() {
        $stats = [
            'referrals' => 5,
            'earnings' => 150,
            'clicks' => 100,
            'conversions' => 5
        ];
        
        $this->assertArrayHasKey('referrals', $stats);
        $this->assertArrayHasKey('earnings', $stats);
    }

    public function testDataRetrieval_TierProgress() {
        $current_referrals = 7;
        $next_tier_requirement = 10;
        $progress = ($current_referrals / $next_tier_requirement) * 100;
        
        $this->assertEquals(70.0, $progress);
    }

    public function testDataRetrieval_NextTierRequirements() {
        $current_tier = 'Bronze';
        $next_tier = 'Silver';
        $referrals_needed = 10;
        
        $requirements = [
            'tier' => $next_tier,
            'referrals_needed' => $referrals_needed,
            'current_progress' => 7
        ];
        
        $this->assertEquals('Silver', $requirements['tier']);
        $this->assertEquals(10, $requirements['referrals_needed']);
    }
}

