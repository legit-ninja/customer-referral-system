<?php
/**
 * Referral role registration and capability merging.
 *
 * Merges CRS capabilities into roles that may already exist (e.g. coach from Player Management).
 */

defined('ABSPATH') or die('No script kiddies please!');

class InterSoccer_Referral_Role_Registration {

    /**
     * Register referral roles or merge capabilities into existing roles.
     */
    public static function register_custom_roles() {
        self::register_or_merge_role('coach', __('Coach', 'intersoccer-referral'), [
            'read' => true,
            'view_referral_dashboard' => true,
            'manage_referrals' => true,
            'view_coach_reports' => true,
        ]);

        self::register_or_merge_role('content_creator', __('Content Creator', 'intersoccer-referral'), [
            'read' => true,
            'view_referral_dashboard' => true,
            'create_content' => true,
            'edit_own_content' => true,
            'manage_content_referrals' => true,
        ]);

        self::register_or_merge_role('partner', __('Partner', 'intersoccer-referral'), [
            'read' => true,
            'view_referral_dashboard' => true,
            'manage_partnerships' => true,
            'view_partner_reports' => true,
            'manage_partner_referrals' => true,
        ]);
    }

    /**
     * @param string $role_name
     * @param string $display_name
     * @param array<string, bool> $capabilities
     */
    private static function register_or_merge_role($role_name, $display_name, array $capabilities) {
        $role = get_role($role_name);
        if (!$role) {
            add_role($role_name, $display_name, $capabilities);
            return;
        }

        foreach ($capabilities as $capability => $granted) {
            $role->add_cap($capability, $granted);
        }
    }

    /**
     * Grant referral admin capabilities to administrators.
     */
    public static function register_admin_capabilities() {
        $admin_role = get_role('administrator');
        if (!$admin_role) {
            return;
        }

        foreach (['view_referral_dashboard', 'manage_referrals', 'view_coach_reports', 'manage_coach_system'] as $capability) {
            $admin_role->add_cap($capability);
        }
    }
}
