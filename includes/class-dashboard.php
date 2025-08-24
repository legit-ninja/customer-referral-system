<?php
// includes/class-dashboard.php

class InterSoccer_Dashboard {

    public function __construct() {
        add_shortcode('intersoccer_coach_dashboard', [$this, 'render_dashboard']);
    }

    public function render_dashboard() {
        if (!is_user_logged_in() || !current_user_can('view_referral_dashboard')) {
            return '<p>You do not have access to this dashboard.</p>';
        }

        $user_id = get_current_user_id();
        $credits = (float) get_user_meta($user_id, 'intersoccer_credits', true);
        $referral_link = InterSoccer_Referral_Handler::generate_coach_referral_link($user_id); // Generate if not exists
        $referrals = $this->get_recent_referrals($user_id);

        ob_start();
        include INTERSOCCER_REFERRAL_PATH . 'templates/dashboard-template.php';
        return ob_get_clean();
    }

    private function get_recent_referrals($coach_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE coach_id = %d ORDER BY created_at DESC LIMIT 5", $coach_id));
    }
}