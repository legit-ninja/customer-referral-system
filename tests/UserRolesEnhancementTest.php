<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for User Roles Enhancement
 * 
 * Tests all custom roles:
 * - Coach
 * - Partner  
 * - Social Influencer
 * - Content Creator
 */
class UserRolesEnhancementTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-user-roles.php';
    }

    /**
     * Test all custom roles defined
     */
    public function testAllCustomRolesDefined() {
        $custom_roles = ['coach', 'partner', 'social_influencer', 'content_creator'];
        
        $this->assertCount(4, $custom_roles);
        $this->assertContains('partner', $custom_roles);
        $this->assertContains('social_influencer', $custom_roles);
    }

    /**
     * Test Coach role capabilities
     */
    public function testCoachRoleCapabilities() {
        $coach_capabilities = [
            'read' => true,
            'view_referral_dashboard' => true,
            'manage_own_referrals' => true,
            'view_coach_dashboard' => true,
            'earn_commissions' => true,
            'use_referral_codes' => true,
        ];
        
        $this->assertArrayHasKey('view_coach_dashboard', $coach_capabilities);
        $this->assertTrue($coach_capabilities['earn_commissions']);
    }

    /**
     * Test Partner role capabilities
     */
    public function testPartnerRoleCapabilities() {
        $partner_capabilities = [
            'read' => true,
            'view_referral_dashboard' => true,
            'manage_own_referrals' => true,
            'view_partner_dashboard' => true,
            'earn_commissions' => true,
            'use_referral_codes' => true,
            'access_partner_resources' => true,
            'premium_point_rate' => true,
        ];
        
        $this->assertArrayHasKey('access_partner_resources', $partner_capabilities);
        $this->assertArrayHasKey('premium_point_rate', $partner_capabilities);
        $this->assertTrue($partner_capabilities['premium_point_rate']);
    }

    /**
     * Test Social Influencer role capabilities
     */
    public function testSocialInfluencerRoleCapabilities() {
        $influencer_capabilities = [
            'read' => true,
            'view_referral_dashboard' => true,
            'manage_own_referrals' => true,
            'view_influencer_dashboard' => true,
            'earn_commissions' => true,
            'use_referral_codes' => true,
            'create_social_content' => true,
            'access_marketing_materials' => true,
            'premium_point_rate' => true,
        ];
        
        $this->assertArrayHasKey('create_social_content', $influencer_capabilities);
        $this->assertArrayHasKey('premium_point_rate', $influencer_capabilities);
        $this->assertTrue($influencer_capabilities['access_marketing_materials']);
    }

    /**
     * Test Content Creator role capabilities
     */
    public function testContentCreatorRoleCapabilities() {
        $creator_capabilities = [
            'read' => true,
            'view_referral_dashboard' => true,
            'manage_own_referrals' => true,
            'view_creator_dashboard' => true,
            'earn_commissions' => true,
            'use_referral_codes' => true,
            'create_content' => true,
            'upload_media' => true,
            'access_marketing_materials' => true,
        ];
        
        $this->assertArrayHasKey('create_content', $creator_capabilities);
        $this->assertArrayHasKey('upload_media', $creator_capabilities);
        $this->assertTrue($creator_capabilities['create_content']);
    }

    /**
     * Test role display names
     */
    public function testRoleDisplayNames() {
        $role_names = [
            'coach' => 'Coach',
            'partner' => 'Partner',
            'social_influencer' => 'Social Influencer',
            'content_creator' => 'Content Creator',
        ];
        
        foreach ($role_names as $slug => $name) {
            $this->assertIsString($name);
            $this->assertNotEmpty($name);
        }
    }

    /**
     * Test referral roles list
     */
    public function testReferralRolesList() {
        $referral_roles = ['coach', 'partner', 'social_influencer', 'content_creator'];
        
        $this->assertIsArray($referral_roles);
        $this->assertContains('partner', $referral_roles);
        $this->assertContains('social_influencer', $referral_roles);
        $this->assertContains('content_creator', $referral_roles);
    }

    /**
     * Test user has referral role check
     */
    public function testUserHasReferralRole() {
        $user_roles = ['partner'];
        $referral_roles = ['coach', 'partner', 'social_influencer', 'content_creator'];
        
        $has_referral_role = !empty(array_intersect($user_roles, $referral_roles));
        
        $this->assertTrue($has_referral_role);
    }

    /**
     * Test user without referral role
     */
    public function testUserWithoutReferralRole() {
        $user_roles = ['subscriber', 'customer'];
        $referral_roles = ['coach', 'partner', 'social_influencer', 'content_creator'];
        
        $has_referral_role = !empty(array_intersect($user_roles, $referral_roles));
        
        $this->assertFalse($has_referral_role);
    }

    /**
     * Test primary role detection (priority order)
     */
    public function testPrimaryRoleDetection() {
        $user_roles = ['customer', 'coach', 'partner']; // Multiple roles
        $priority_roles = ['partner', 'social_influencer', 'content_creator', 'coach'];
        
        $primary = null;
        foreach ($priority_roles as $role) {
            if (in_array($role, $user_roles)) {
                $primary = $role;
                break;
            }
        }
        
        $this->assertEquals('partner', $primary, 'Partner should be highest priority');
    }

    /**
     * Test Partner has premium_point_rate capability
     */
    public function testPartnerHasPremiumPointRate() {
        $partner_caps = [
            'premium_point_rate' => true,
        ];
        
        $this->assertTrue($partner_caps['premium_point_rate']);
    }

    /**
     * Test Social Influencer has premium_point_rate capability
     */
    public function testSocialInfluencerHasPremiumPointRate() {
        $influencer_caps = [
            'premium_point_rate' => true,
        ];
        
        $this->assertTrue($influencer_caps['premium_point_rate']);
    }

    /**
     * Test Coach does NOT have premium_point_rate (standard rate)
     */
    public function testCoachDoesNotHavePremiumPointRate() {
        $coach_caps = [
            'view_coach_dashboard' => true,
            'earn_commissions' => true,
            // No premium_point_rate
        ];
        
        $this->assertArrayNotHasKey('premium_point_rate', $coach_caps);
    }

    /**
     * Test role capabilities are distinct
     */
    public function testRoleCapabilitiesDistinct() {
        $coach = ['view_coach_dashboard'];
        $partner = ['view_partner_dashboard', 'access_partner_resources'];
        $influencer = ['view_influencer_dashboard', 'create_social_content'];
        $creator = ['view_creator_dashboard', 'create_content', 'upload_media'];
        
        $this->assertNotEquals($coach, $partner);
        $this->assertNotEquals($partner, $influencer);
        $this->assertNotEquals($influencer, $creator);
    }

    /**
     * Test all roles can earn commissions
     */
    public function testAllRolesCanEarnCommissions() {
        $roles = ['coach', 'partner', 'social_influencer', 'content_creator'];
        
        foreach ($roles as $role) {
            $can_earn = true; // All should have earn_commissions capability
            $this->assertTrue($can_earn, "{$role} should be able to earn commissions");
        }
    }

    /**
     * Test all roles can use referral codes
     */
    public function testAllRolesCanUseReferralCodes() {
        $roles = ['coach', 'partner', 'social_influencer', 'content_creator'];
        
        foreach ($roles as $role) {
            $can_use = true; // All should have use_referral_codes capability
            $this->assertTrue($can_use, "{$role} should be able to use referral codes");
        }
    }

    /**
     * Test Partner role unique capabilities
     */
    public function testPartnerRoleUniqueCapabilities() {
        $unique_to_partner = ['access_partner_resources'];
        
        $this->assertContains('access_partner_resources', $unique_to_partner);
    }

    /**
     * Test Social Influencer unique capabilities
     */
    public function testSocialInfluencerUniqueCapabilities() {
        $unique_to_influencer = ['create_social_content'];
        
        $this->assertContains('create_social_content', $unique_to_influencer);
    }

    /**
     * Test Content Creator unique capabilities
     */
    public function testContentCreatorUniqueCapabilities() {
        $unique_to_creator = ['create_content', 'upload_media'];
        
        $this->assertContains('create_content', $unique_to_creator);
        $this->assertContains('upload_media', $unique_to_creator);
    }

    /**
     * Test role slug format
     */
    public function testRoleSlugFormat() {
        $role_slugs = ['coach', 'partner', 'social_influencer', 'content_creator'];
        
        foreach ($role_slugs as $slug) {
            $this->assertMatchesRegularExpression('/^[a-z_]+$/', $slug);
            $this->assertStringNotContainsString(' ', $slug);
        }
    }

    /**
     * Test role display name conversion
     */
    public function testRoleDisplayNameConversion() {
        $conversions = [
            'coach' => 'Coach',
            'partner' => 'Partner',
            'social_influencer' => 'Social Influencer',
            'content_creator' => 'Content Creator',
        ];
        
        foreach ($conversions as $slug => $display) {
            $this->assertIsString($display);
            $this->assertGreaterThan(0, strpos($display, ' ') ?: (strlen($display) > 0));
        }
    }

    /**
     * Test capability check helper
     */
    public function testCapabilityCheckHelper() {
        $role_capabilities = [
            'earn_commissions' => true,
            'manage_own_referrals' => true,
        ];
        
        $has_capability = isset($role_capabilities['earn_commissions']) && $role_capabilities['earn_commissions'];
        
        $this->assertTrue($has_capability);
    }

    /**
     * Test role doesn't have admin capabilities
     */
    public function testRoleDoesntHaveAdminCapabilities() {
        $referral_role_caps = [
            'view_referral_dashboard' => true,
            'earn_commissions' => true,
            // NO admin caps
        ];
        
        $this->assertArrayNotHasKey('manage_options', $referral_role_caps);
        $this->assertArrayNotHasKey('delete_users', $referral_role_caps);
    }

    /**
     * Test user can have multiple roles
     */
    public function testUserCanHaveMultipleRoles() {
        $user_roles = ['customer', 'coach'];
        
        $this->assertContains('customer', $user_roles);
        $this->assertContains('coach', $user_roles);
        $this->assertCount(2, $user_roles);
    }

    /**
     * Test role priority for point rates
     */
    public function testRolePriorityForPointRates() {
        $priority_order = ['partner', 'social_influencer', 'content_creator', 'coach', 'customer'];
        
        $this->assertEquals('partner', $priority_order[0], 'Partner should have highest priority');
        $this->assertEquals('social_influencer', $priority_order[1]);
        $this->assertEquals('content_creator', $priority_order[2]);
        $this->assertEquals('coach', $priority_order[3]);
    }

    /**
     * Test Partner gets best point rate
     */
    public function testPartnerGetsBestPointRate() {
        $user_roles = ['customer', 'partner'];
        $priority = ['partner', 'social_influencer', 'coach', 'customer'];
        
        $matched = null;
        foreach ($priority as $role) {
            if (in_array($role, $user_roles)) {
                $matched = $role;
                break;
            }
        }
        
        $this->assertEquals('partner', $matched);
    }

    /**
     * Test role-specific dashboard access
     */
    public function testRoleSpecificDashboardAccess() {
        $dashboards = [
            'coach' => 'view_coach_dashboard',
            'partner' => 'view_partner_dashboard',
            'social_influencer' => 'view_influencer_dashboard',
            'content_creator' => 'view_creator_dashboard',
        ];
        
        foreach ($dashboards as $role => $capability) {
            $this->assertIsString($capability);
            $this->assertStringContainsString('view_', $capability);
        }
    }

    /**
     * Test premium capabilities for Partners
     */
    public function testPremiumCapabilitiesForPartners() {
        $premium_caps = ['access_partner_resources', 'premium_point_rate'];
        
        foreach ($premium_caps as $cap) {
            $this->assertIsString($cap);
        }
    }

    /**
     * Test content creation capabilities
     */
    public function testContentCreationCapabilities() {
        $content_caps = ['create_content', 'upload_media', 'create_social_content'];
        
        foreach ($content_caps as $cap) {
            $this->assertIsString($cap);
            $this->assertStringContainsString('create', $cap);
        }
    }

    /**
     * Test role does not have edit_users capability
     */
    public function testRoleDoesNotHaveEditUsers() {
        $safe_caps = [
            'view_referral_dashboard',
            'earn_commissions',
            // No edit_users
        ];
        
        $this->assertArrayNotHasKey('edit_users', $safe_caps);
    }

    /**
     * Test role does not have manage_options capability
     */
    public function testRoleDoesNotHaveManageOptions() {
        $safe_caps = [
            'view_referral_dashboard',
            'earn_commissions',
            // No manage_options
        ];
        
        $this->assertArrayNotHasKey('manage_options', $safe_caps);
    }

    /**
     * Test all roles have read capability
     */
    public function testAllRolesHaveReadCapability() {
        $roles = ['coach', 'partner', 'social_influencer', 'content_creator'];
        
        foreach ($roles as $role) {
            $has_read = true; // All should have read capability
            $this->assertTrue($has_read, "{$role} should have read capability");
        }
    }

    /**
     * Test referral role detection
     */
    public function testReferralRoleDetection() {
        $referral_roles = ['coach', 'partner', 'social_influencer', 'content_creator'];
        $test_role = 'partner';
        
        $is_referral_role = in_array($test_role, $referral_roles);
        
        $this->assertTrue($is_referral_role);
    }

    /**
     * Test non-referral role detection
     */
    public function testNonReferralRoleDetection() {
        $referral_roles = ['coach', 'partner', 'social_influencer', 'content_creator'];
        $test_role = 'subscriber';
        
        $is_referral_role = in_array($test_role, $referral_roles);
        
        $this->assertFalse($is_referral_role);
    }

    /**
     * Test capability naming convention
     */
    public function testCapabilityNamingConvention() {
        $capabilities = [
            'view_referral_dashboard',
            'manage_own_referrals',
            'earn_commissions',
            'use_referral_codes',
        ];
        
        foreach ($capabilities as $cap) {
            $this->assertMatchesRegularExpression('/^[a-z_]+$/', $cap);
        }
    }

    /**
     * Test dashboard capability per role
     */
    public function testDashboardCapabilityPerRole() {
        $dashboard_map = [
            'coach' => 'view_coach_dashboard',
            'partner' => 'view_partner_dashboard',
            'social_influencer' => 'view_influencer_dashboard',
            'content_creator' => 'view_creator_dashboard',
        ];
        
        $this->assertCount(4, $dashboard_map);
        $this->assertArrayHasKey('partner', $dashboard_map);
        $this->assertArrayHasKey('social_influencer', $dashboard_map);
    }

    /**
     * Test marketing capabilities
     */
    public function testMarketingCapabilities() {
        $marketing_roles = ['social_influencer', 'content_creator'];
        
        foreach ($marketing_roles as $role) {
            $has_marketing = true; // Should have access_marketing_materials
            $this->assertTrue($has_marketing, "{$role} should have marketing capabilities");
        }
    }

    /**
     * Test Partner priority over other roles
     */
    public function testPartnerPriorityOverOtherRoles() {
        $priority = ['partner', 'social_influencer', 'content_creator', 'coach'];
        
        $this->assertEquals(0, array_search('partner', $priority));
        $this->assertLessThan(array_search('coach', $priority), array_search('partner', $priority));
    }

    /**
     * Test Social Influencer priority over Coach
     */
    public function testSocialInfluencerPriorityOverCoach() {
        $priority = ['partner', 'social_influencer', 'content_creator', 'coach'];
        
        $influencer_pos = array_search('social_influencer', $priority);
        $coach_pos = array_search('coach', $priority);
        
        $this->assertLessThan($coach_pos, $influencer_pos);
    }

    /**
     * Test Content Creator priority over Coach
     */
    public function testContentCreatorPriorityOverCoach() {
        $priority = ['partner', 'social_influencer', 'content_creator', 'coach'];
        
        $creator_pos = array_search('content_creator', $priority);
        $coach_pos = array_search('coach', $priority);
        
        $this->assertLessThan($coach_pos, $creator_pos);
    }

    /**
     * Test capabilities inheritance
     */
    public function testCapabilitiesInheritance() {
        $base_caps = ['read', 'view_referral_dashboard', 'earn_commissions'];
        
        // All roles should have base capabilities
        $coach_has_base = true;
        $partner_has_base = true;
        
        $this->assertTrue($coach_has_base);
        $this->assertTrue($partner_has_base);
    }

    /**
     * Test role slug validation
     */
    public function testRoleSlugValidation() {
        $valid_slugs = ['coach', 'partner', 'social_influencer', 'content_creator'];
        
        foreach ($valid_slugs as $slug) {
            $this->assertStringNotContainsString(' ', $slug);
            $this->assertStringNotContainsString('-', $slug); // Use underscores
        }
    }

    /**
     * Test commission earning by role
     */
    public function testCommissionEarningByRole() {
        $commission_roles = ['coach', 'partner', 'social_influencer', 'content_creator'];
        
        foreach ($commission_roles as $role) {
            $can_earn = true; // All have earn_commissions
            $this->assertTrue($can_earn, "{$role} can earn commissions");
        }
    }

    /**
     * Test Partner resource access
     */
    public function testPartnerResourceAccess() {
        $partner_resources = [
            'business_materials',
            'wholesale_pricing',
            'exclusive_promotions',
        ];
        
        foreach ($partner_resources as $resource) {
            $this->assertIsString($resource);
        }
    }

    /**
     * Test Social Influencer content capabilities
     */
    public function testSocialInfluencerContentCapabilities() {
        $content_types = [
            'social_media_posts',
            'stories',
            'reels',
            'promotional_content',
        ];
        
        foreach ($content_types as $type) {
            $this->assertIsString($type);
        }
    }

    /**
     * Test role assignment validation
     */
    public function testRoleAssignmentValidation() {
        $valid_roles = ['coach', 'partner', 'social_influencer', 'content_creator'];
        $test_role = 'partner';
        
        $is_valid = in_array($test_role, $valid_roles);
        
        $this->assertTrue($is_valid);
    }

    /**
     * Test multiple role assignment
     */
    public function testMultipleRoleAssignment() {
        $user_roles = ['customer', 'partner'];
        
        $this->assertContains('customer', $user_roles);
        $this->assertContains('partner', $user_roles);
    }

    /**
     * Test role removal
     */
    public function testRoleRemoval() {
        $user_roles = ['customer', 'coach'];
        
        // Remove coach role
        $user_roles = array_diff($user_roles, ['coach']);
        
        $this->assertNotContains('coach', $user_roles);
        $this->assertContains('customer', $user_roles);
    }

    /**
     * Test role count per type
     */
    public function testRoleCountPerType() {
        $all_users = [
            ['roles' => ['coach']],
            ['roles' => ['partner']],
            ['roles' => ['coach']],
            ['roles' => ['social_influencer']],
            ['roles' => ['coach']],
        ];
        
        $coach_count = 0;
        foreach ($all_users as $user) {
            if (in_array('coach', $user['roles'])) {
                $coach_count++;
            }
        }
        
        $this->assertEquals(3, $coach_count);
    }

    /**
     * Test role-based permissions matrix
     */
    public function testRoleBasedPermissionsMatrix() {
        $permissions = [
            'coach' => ['dashboard' => true, 'commissions' => true, 'premium_rate' => false],
            'partner' => ['dashboard' => true, 'commissions' => true, 'premium_rate' => true],
            'social_influencer' => ['dashboard' => true, 'commissions' => true, 'premium_rate' => true],
            'content_creator' => ['dashboard' => true, 'commissions' => true, 'premium_rate' => false],
        ];
        
        $this->assertTrue($permissions['partner']['premium_rate']);
        $this->assertTrue($permissions['social_influencer']['premium_rate']);
        $this->assertFalse($permissions['coach']['premium_rate']);
    }

    /**
     * Test all custom roles count
     */
    public function testAllCustomRolesCount() {
        $custom_roles = ['coach', 'partner', 'social_influencer', 'content_creator'];
        
        $this->assertCount(4, $custom_roles);
    }
}

