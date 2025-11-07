<?php
/**
 * User Roles Management
 * 
 * Manages custom user roles:
 * - Coach
 * - Partner
 * - Social Influencer
 * - Content Creator
 */

class InterSoccer_User_Roles {

    public function __construct() {
        add_action('init', [$this, 'register_custom_roles']);
    }

    /**
     * Register custom user roles
     */
    public function register_custom_roles() {
        // Only register if not already registered
        if (!get_role('coach')) {
            $this->register_coach_role();
        }
        
        if (!get_role('partner')) {
            $this->register_partner_role();
        }
        
        if (!get_role('social_influencer')) {
            $this->register_social_influencer_role();
        }
        
        if (!get_role('content_creator')) {
            $this->register_content_creator_role();
        }
    }

    /**
     * Register Coach role
     */
    private function register_coach_role() {
        add_role('coach', 'Coach', [
            'read' => true,
            'view_referral_dashboard' => true,
            'manage_own_referrals' => true,
            'view_coach_dashboard' => true,
            'earn_commissions' => true,
            'use_referral_codes' => true,
        ]);
    }

    /**
     * Register Partner role
     */
    private function register_partner_role() {
        add_role('partner', 'Partner', [
            'read' => true,
            'view_referral_dashboard' => true,
            'manage_own_referrals' => true,
            'view_partner_dashboard' => true,
            'earn_commissions' => true,
            'use_referral_codes' => true,
            'access_partner_resources' => true,
            'premium_point_rate' => true, // Better earning rate
        ]);
    }

    /**
     * Register Social Influencer role
     */
    private function register_social_influencer_role() {
        add_role('social_influencer', 'Social Influencer', [
            'read' => true,
            'view_referral_dashboard' => true,
            'manage_own_referrals' => true,
            'view_influencer_dashboard' => true,
            'earn_commissions' => true,
            'use_referral_codes' => true,
            'create_social_content' => true,
            'access_marketing_materials' => true,
            'premium_point_rate' => true, // Better earning rate
        ]);
    }

    /**
     * Register Content Creator role
     */
    private function register_content_creator_role() {
        add_role('content_creator', 'Content Creator', [
            'read' => true,
            'view_referral_dashboard' => true,
            'manage_own_referrals' => true,
            'view_creator_dashboard' => true,
            'earn_commissions' => true,
            'use_referral_codes' => true,
            'create_content' => true,
            'upload_media' => true,
            'access_marketing_materials' => true,
        ]);
    }

    /**
     * Get role display name
     */
    public static function get_role_display_name($role) {
        $role_names = [
            'coach' => 'Coach',
            'partner' => 'Partner',
            'social_influencer' => 'Social Influencer',
            'content_creator' => 'Content Creator',
            'customer' => 'Customer',
            'administrator' => 'Administrator',
        ];
        
        return $role_names[$role] ?? ucfirst($role);
    }

    /**
     * Get all custom referral roles
     */
    public static function get_referral_roles() {
        return ['coach', 'partner', 'social_influencer', 'content_creator'];
    }

    /**
     * Check if user has referral role
     */
    public static function user_has_referral_role($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        $referral_roles = self::get_referral_roles();
        
        foreach ($user->roles as $role) {
            if (in_array($role, $referral_roles)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get user's primary referral role (highest priority)
     */
    public static function get_primary_referral_role($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return null;
        }
        
        // Priority order
        $priority_roles = ['partner', 'social_influencer', 'content_creator', 'coach'];
        
        foreach ($priority_roles as $role) {
            if (in_array($role, $user->roles)) {
                return $role;
            }
        }
        
        return null;
    }

    /**
     * Get role capabilities
     */
    public static function get_role_capabilities($role_name) {
        $role = get_role($role_name);
        return $role ? $role->capabilities : [];
    }

    /**
     * Check if role has capability
     */
    public static function role_has_capability($role_name, $capability) {
        $role = get_role($role_name);
        return $role && isset($role->capabilities[$capability]);
    }
}

// Initialize
new InterSoccer_User_Roles();

