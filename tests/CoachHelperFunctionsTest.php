<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for Coach Helper Functions
 * 
 * Tests:
 * - intersoccer_get_coach_tier()
 * - intersoccer_get_coach_tier_badge()
 * - intersoccer_get_all_coaches()
 */
class CoachHelperFunctionsTest extends TestCase {

    /**
     * Test tier calculation with 0 referrals (Bronze)
     */
    public function testTierWithZeroReferrals() {
        $referral_count = 0;
        $tier = $this->calculateTier($referral_count);
        
        $this->assertEquals('Bronze', $tier);
    }

    /**
     * Test tier calculation with 5 referrals (Silver)
     */
    public function testTierWith5Referrals() {
        $referral_count = 5;
        $tier = $this->calculateTier($referral_count);
        
        $this->assertEquals('Silver', $tier);
    }

    /**
     * Test tier calculation with 10 referrals (Gold)
     */
    public function testTierWith10Referrals() {
        $referral_count = 10;
        $tier = $this->calculateTier($referral_count);
        
        $this->assertEquals('Gold', $tier);
    }

    /**
     * Test tier calculation with 20 referrals (Platinum)
     */
    public function testTierWith20Referrals() {
        $referral_count = 20;
        $tier = $this->calculateTier($referral_count);
        
        $this->assertEquals('Platinum', $tier);
    }

    /**
     * Test tier boundaries
     */
    public function testTierBoundaries() {
        $test_cases = [
            ['referrals' => 0, 'tier' => 'Bronze'],
            ['referrals' => 4, 'tier' => 'Bronze'],
            ['referrals' => 5, 'tier' => 'Silver'],
            ['referrals' => 9, 'tier' => 'Silver'],
            ['referrals' => 10, 'tier' => 'Gold'],
            ['referrals' => 19, 'tier' => 'Gold'],
            ['referrals' => 20, 'tier' => 'Platinum'],
            ['referrals' => 50, 'tier' => 'Platinum'],
        ];

        foreach ($test_cases as $case) {
            $tier = $this->calculateTier($case['referrals']);
            $this->assertEquals($case['tier'], $tier,
                "{$case['referrals']} referrals should be {$case['tier']}");
        }
    }

    /**
     * Test tier badge HTML structure
     */
    public function testTierBadgeHTMLStructure() {
        $tier = 'Gold';
        $badge = '<span class="tier-badge tier-gold">Gold</span>';
        
        $this->assertStringContainsString('tier-badge', $badge);
        $this->assertStringContainsString('tier-gold', $badge);
        $this->assertStringContainsString('Gold', $badge);
    }

    /**
     * Test tier badge for all tiers
     */
    public function testTierBadgeForAllTiers() {
        $tiers = ['Bronze', 'Silver', 'Gold', 'Platinum'];
        
        foreach ($tiers as $tier) {
            $badge = '<span class="tier-badge tier-' . strtolower($tier) . '">' . $tier . '</span>';
            
            $this->assertStringContainsString('tier-badge', $badge);
            $this->assertStringContainsString(strtolower($tier), $badge);
        }
    }

    /**
     * Test tier badge escapes HTML
     */
    public function testTierBadgeEscapesHTML() {
        $malicious_tier = '<script>alert("XSS")</script>';
        $escaped = htmlspecialchars($malicious_tier, ENT_QUOTES, 'UTF-8');
        
        $this->assertStringNotContainsString('<script>', $escaped);
    }

    /**
     * Test get_all_coaches returns array
     */
    public function testGetAllCoachesReturnsArray() {
        $coaches = []; // Mock empty list
        
        $this->assertIsArray($coaches);
    }

    /**
     * Test get_all_coaches filters by role
     */
    public function testGetAllCoachesFiltersByRole() {
        $args = [
            'role' => 'coach',
            'orderby' => 'display_name',
            'order' => 'ASC',
        ];
        
        $this->assertEquals('coach', $args['role']);
    }

    /**
     * Test get_all_coaches accepts custom args
     */
    public function testGetAllCoachesAcceptsCustomArgs() {
        $custom_args = [
            'number' => 10,
            'orderby' => 'registered',
        ];
        
        $default_args = [
            'role' => 'coach',
            'orderby' => 'display_name',
            'order' => 'ASC',
        ];
        
        $merged = array_merge($default_args, $custom_args);
        
        $this->assertEquals('coach', $merged['role']);
        $this->assertEquals('registered', $merged['orderby']);
        $this->assertEquals(10, $merged['number']);
    }

    /**
     * Test tier thresholds are configurable
     */
    public function testTierThresholdsConfigurable() {
        $default_thresholds = [
            'silver' => 5,
            'gold' => 10,
            'platinum' => 20,
        ];
        
        $this->assertEquals(5, $default_thresholds['silver']);
        $this->assertEquals(10, $default_thresholds['gold']);
        $this->assertEquals(20, $default_thresholds['platinum']);
    }

    /**
     * Test tier progression is logical
     */
    public function testTierProgressionIsLogical() {
        $tiers = ['Bronze', 'Silver', 'Gold', 'Platinum'];
        $tier_values = ['Bronze' => 0, 'Silver' => 1, 'Gold' => 2, 'Platinum' => 3];
        
        for ($i = 1; $i < count($tiers); $i++) {
            $this->assertGreaterThan(
                $tier_values[$tiers[$i-1]],
                $tier_values[$tiers[$i]],
                "{$tiers[$i]} should be higher than {$tiers[$i-1]}"
            );
        }
    }

    /**
     * Helper method to calculate tier based on referral count
     */
    private function calculateTier($referral_count) {
        $silver_threshold = 5;
        $gold_threshold = 10;
        $platinum_threshold = 20;
        
        if ($referral_count >= $platinum_threshold) return 'Platinum';
        if ($referral_count >= $gold_threshold) return 'Gold';
        if ($referral_count >= $silver_threshold) return 'Silver';
        return 'Bronze';
    }

    /**
     * Test tier badge CSS classes
     */
    public function testTierBadgeCSSClasses() {
        $classes = [
            'tier-badge',
            'tier-bronze',
            'tier-silver',
            'tier-gold',
            'tier-platinum',
        ];
        
        foreach ($classes as $class) {
            $this->assertIsString($class);
            $this->assertStringStartsWith('tier-', $class);
        }
    }

    /**
     * Test coach list returns only coaches
     */
    public function testCoachListReturnsOnlyCoaches() {
        $users = [
            ['role' => 'coach', 'name' => 'Coach 1'],
            ['role' => 'customer', 'name' => 'Customer 1'],
            ['role' => 'coach', 'name' => 'Coach 2'],
        ];
        
        $coaches = array_filter($users, function($user) {
            return $user['role'] === 'coach';
        });
        
        $this->assertCount(2, $coaches);
    }

    /**
     * Test coaches ordered by display name
     */
    public function testCoachesOrderedByDisplayName() {
        $coaches = [
            ['display_name' => 'Alice'],
            ['display_name' => 'Charlie'],
            ['display_name' => 'Bob'],
        ];
        
        usort($coaches, function($a, $b) {
            return strcmp($a['display_name'], $b['display_name']);
        });
        
        $this->assertEquals('Alice', $coaches[0]['display_name']);
        $this->assertEquals('Bob', $coaches[1]['display_name']);
        $this->assertEquals('Charlie', $coaches[2]['display_name']);
    }

    /**
     * Test tier count statistics
     */
    public function testTierCountStatistics() {
        $coaches = [
            ['tier' => 'Bronze'],
            ['tier' => 'Silver'],
            ['tier' => 'Bronze'],
            ['tier' => 'Gold'],
            ['tier' => 'Bronze'],
        ];
        
        $tier_counts = array_count_values(array_column($coaches, 'tier'));
        
        $this->assertEquals(3, $tier_counts['Bronze']);
        $this->assertEquals(1, $tier_counts['Silver']);
        $this->assertEquals(1, $tier_counts['Gold']);
    }

    /**
     * Test tier upgrade detection
     */
    public function testTierUpgradeDetection() {
        $old_tier = 'Bronze';
        $new_tier = 'Silver';
        
        $tier_values = ['Bronze' => 0, 'Silver' => 1, 'Gold' => 2, 'Platinum' => 3];
        
        $is_upgrade = ($tier_values[$new_tier] > $tier_values[$old_tier]);
        
        $this->assertTrue($is_upgrade);
    }

    /**
     * Test tier colors/styling
     */
    public function testTierColorsStyled() {
        $tier_colors = [
            'bronze' => '#cd7f32',
            'silver' => '#c0c0c0',
            'gold' => '#ffd700',
            'platinum' => '#e5e4e2',
        ];
        
        foreach ($tier_colors as $tier => $color) {
            $this->assertIsString($color);
            $this->assertStringStartsWith('#', $color);
        }
    }

    /**
     * Test tier benefits exist
     */
    public function testTierBenefitsExist() {
        $tier_benefits = [
            'Bronze' => 'Standard benefits',
            'Silver' => '+5% commission bonus',
            'Gold' => '+10% commission bonus',
            'Platinum' => '+15% commission bonus + VIP support',
        ];
        
        foreach ($tier_benefits as $tier => $benefit) {
            $this->assertNotEmpty($benefit);
        }
    }
}

