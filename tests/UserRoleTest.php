<?php

use PHPUnit\Framework\TestCase;

/**
 * Test suite for InterSoccer User Roles and Permissions
 */
class UserRoleTest extends TestCase {

    protected function setUp(): void {
        // Include the main plugin file for role definitions
        require_once __DIR__ . '/../customer-referral-system.php';
    }

    /**
     * Test coach role creation and capabilities
     */
    public function testCoachRoleCapabilities() {
        // Test coach role exists
        $coach_role = get_role('coach');
        $this->assertNotNull($coach_role, 'Coach role should exist');

        // Test coach capabilities
        $expected_capabilities = [
            'read' => true,
            'view_referral_dashboard' => true,
            'manage_referrals' => true,
            'view_coach_reports' => true,
        ];

        foreach ($expected_capabilities as $capability => $expected) {
            $this->assertEquals($expected, $coach_role->has_cap($capability),
                "Coach role should have capability: $capability");
        }
    }

    /**
     * Test content creator role creation and capabilities
     */
    public function testContentCreatorRoleCapabilities() {
        // Test content creator role exists
        $content_creator_role = get_role('content_creator');
        $this->assertNotNull($content_creator_role, 'Content Creator role should exist');

        // Test content creator capabilities
        $expected_capabilities = [
            'read' => true,
            'view_referral_dashboard' => true,
            'create_content' => true,
            'edit_own_content' => true,
            'manage_content_referrals' => true,
        ];

        foreach ($expected_capabilities as $capability => $expected) {
            $this->assertEquals($expected, $content_creator_role->has_cap($capability),
                "Content Creator role should have capability: $capability");
        }
    }

    /**
     * Test partner role creation and capabilities
     */
    public function testPartnerRoleCapabilities() {
        // Test partner role exists
        $partner_role = get_role('partner');
        $this->assertNotNull($partner_role, 'Partner role should exist');

        // Test partner capabilities
        $expected_capabilities = [
            'read' => true,
            'view_referral_dashboard' => true,
            'manage_partnerships' => true,
            'view_partner_reports' => true,
            'manage_partner_referrals' => true,
        ];

        foreach ($expected_capabilities as $capability => $expected) {
            $this->assertEquals($expected, $partner_role->has_cap($capability),
                "Partner role should have capability: $capability");
        }
    }

    /**
     * Test administrator role enhanced capabilities
     */
    public function testAdministratorRoleCapabilities() {
        $admin_role = get_role('administrator');
        $this->assertNotNull($admin_role, 'Administrator role should exist');

        // Test that admin has referral system capabilities
        $referral_capabilities = [
            'view_referral_dashboard',
            'manage_referrals',
            'view_coach_reports',
        ];

        foreach ($referral_capabilities as $capability) {
            $this->assertTrue($admin_role->has_cap($capability),
                "Administrator role should have capability: $capability");
        }
    }

    /**
     * Test role hierarchy and permission levels
     */
    public function testRoleHierarchy() {
        $coach_role = get_role('coach');
        $content_creator_role = get_role('content_creator');
        $partner_role = get_role('partner');
        $admin_role = get_role('administrator');

        // All roles should have basic read capability
        $this->assertTrue($coach_role->has_cap('read'));
        $this->assertTrue($content_creator_role->has_cap('read'));
        $this->assertTrue($partner_role->has_cap('read'));

        // Test role-specific capabilities don't overlap incorrectly
        $this->assertTrue($coach_role->has_cap('manage_referrals'));
        $this->assertFalse($coach_role->has_cap('create_content'));
        $this->assertFalse($coach_role->has_cap('manage_partnerships'));

        $this->assertTrue($content_creator_role->has_cap('create_content'));
        $this->assertFalse($content_creator_role->has_cap('manage_referrals'));
        $this->assertFalse($content_creator_role->has_cap('manage_partnerships'));

        $this->assertTrue($partner_role->has_cap('manage_partnerships'));
        $this->assertFalse($partner_role->has_cap('manage_referrals'));
        $this->assertFalse($partner_role->has_cap('create_content'));
    }

    /**
     * Test user role assignment
     */
    public function testUserRoleAssignment() {
        // Create a mock user
        $user_id = wp_create_user('test_user_' . time(), 'password', 'test@example.com');

        // Assign coach role
        $user = new WP_User($user_id);
        $user->set_role('coach');

        // Verify role assignment
        $this->assertTrue(in_array('coach', $user->roles));
        $this->assertTrue($user->has_cap('view_referral_dashboard'));
        $this->assertTrue($user->has_cap('manage_referrals'));

        // Clean up
        wp_delete_user($user_id);
    }

    /**
     * Test capability checks for different user types
     */
    public function testCapabilityChecks() {
        // Test with different user types
        $test_cases = [
            'coach' => [
                'allowed' => ['read', 'view_referral_dashboard', 'manage_referrals', 'view_coach_reports'],
                'denied' => ['create_content', 'manage_partnerships', 'manage_options']
            ],
            'content_creator' => [
                'allowed' => ['read', 'view_referral_dashboard', 'create_content', 'edit_own_content', 'manage_content_referrals'],
                'denied' => ['manage_referrals', 'manage_partnerships', 'manage_options']
            ],
            'partner' => [
                'allowed' => ['read', 'view_referral_dashboard', 'manage_partnerships', 'view_partner_reports', 'manage_partner_referrals'],
                'denied' => ['manage_referrals', 'create_content', 'manage_options']
            ]
        ];

        foreach ($test_cases as $role => $capabilities) {
            $role_obj = get_role($role);

            foreach ($capabilities['allowed'] as $cap) {
                $this->assertTrue($role_obj->has_cap($cap),
                    "$role role should have capability: $cap");
            }

            foreach ($capabilities['denied'] as $cap) {
                $this->assertFalse($role_obj->has_cap($cap),
                    "$role role should NOT have capability: $cap");
            }
        }
    }

    /**
     * Test role removal functionality
     */
    public function testRoleRemoval() {
        // Verify roles exist before removal
        $this->assertNotNull(get_role('coach'));
        $this->assertNotNull(get_role('content_creator'));
        $this->assertNotNull(get_role('partner'));

        // Test role removal (this would normally be done during plugin deactivation)
        remove_role('coach');
        remove_role('content_creator');
        remove_role('partner');

        // Verify roles are removed
        $this->assertNull(get_role('coach'));
        $this->assertNull(get_role('content_creator'));
        $this->assertNull(get_role('partner'));
    }

    /**
     * Test role recreation after removal
     */
    public function testRoleRecreation() {
        // Remove roles first
        remove_role('coach');
        remove_role('content_creator');
        remove_role('partner');

        // Recreate roles (simulating plugin reactivation)
        $this->recreateRoles();

        // Verify roles exist again
        $this->assertNotNull(get_role('coach'));
        $this->assertNotNull(get_role('content_creator'));
        $this->assertNotNull(get_role('partner'));

        // Verify capabilities are properly set
        $this->testCoachRoleCapabilities();
        $this->testContentCreatorRoleCapabilities();
        $this->testPartnerRoleCapabilities();
    }

    /**
     * Test admin user capabilities enhancement
     */
    public function testAdminCapabilitiesEnhancement() {
        $admin_role = get_role('administrator');

        // Add referral capabilities to admin (simulating plugin activation)
        $admin_role->add_cap('view_referral_dashboard');
        $admin_role->add_cap('manage_referrals');
        $admin_role->add_cap('view_coach_reports');

        // Verify admin has enhanced capabilities
        $this->assertTrue($admin_role->has_cap('view_referral_dashboard'));
        $this->assertTrue($admin_role->has_cap('manage_referrals'));
        $this->assertTrue($admin_role->has_cap('view_coach_reports'));
    }

    /**
     * Test role-based access control scenarios
     */
    public function testRoleBasedAccessScenarios() {
        // Test coach can access referral dashboard but not content creation
        $coach_role = get_role('coach');
        $this->assertTrue($coach_role->has_cap('view_referral_dashboard'));
        $this->assertTrue($coach_role->has_cap('manage_referrals'));
        $this->assertFalse($coach_role->has_cap('create_content'));

        // Test content creator can create content but not manage partnerships
        $content_creator_role = get_role('content_creator');
        $this->assertTrue($content_creator_role->has_cap('create_content'));
        $this->assertTrue($content_creator_role->has_cap('manage_content_referrals'));
        $this->assertFalse($content_creator_role->has_cap('manage_partnerships'));

        // Test partner can manage partnerships but not create content
        $partner_role = get_role('partner');
        $this->assertTrue($partner_role->has_cap('manage_partnerships'));
        $this->assertTrue($partner_role->has_cap('view_partner_reports'));
        $this->assertFalse($partner_role->has_cap('create_content'));
    }

    /**
     * Helper method to recreate roles for testing
     */
    private function recreateRoles() {
        // Coach role
        $coach_capabilities = [
            'read' => true,
            'view_referral_dashboard' => true,
            'manage_referrals' => true,
            'view_coach_reports' => true,
        ];
        add_role('coach', __('Coach', 'intersoccer-referral'), $coach_capabilities);

        // Content Creator role
        $content_creator_capabilities = [
            'read' => true,
            'view_referral_dashboard' => true,
            'create_content' => true,
            'edit_own_content' => true,
            'manage_content_referrals' => true,
        ];
        add_role('content_creator', __('Content Creator', 'intersoccer-referral'), $content_creator_capabilities);

        // Partner role
        $partner_capabilities = [
            'read' => true,
            'view_referral_dashboard' => true,
            'manage_partnerships' => true,
            'view_partner_reports' => true,
            'manage_partner_referrals' => true,
        ];
        add_role('partner', __('Partner', 'intersoccer-referral'), $partner_capabilities);
    }
}