<?php
/**
 * Plugin Name: InterSoccer Referral System
 * Plugin URI: https://intersoccer.ch
 * Description: Advanced coach referral program with gamification and comprehensive analytics.
 * Version: 1.0.0
 * Author: Legit Ninja
 * Author URI: https://github.com/legit-ninja
 * License: GPL-2.0+
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Text Domain: intersoccer-referral
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('INTERSOCCER_REFERRAL_VERSION', '1.0.0');
define('INTERSOCCER_REFERRAL_PATH', plugin_dir_path(__FILE__));
define('INTERSOCCER_REFERRAL_URL', plugin_dir_url(__FILE__));
define('INTERSOCCER_REFERRAL_BASENAME', plugin_basename(__FILE__));

if (!file_exists(INTERSOCCER_REFERRAL_PATH . 'includes/class-referral-handler.php')) {
    error_log('Error: class-referral-handler.php not found at ' . INTERSOCCER_REFERRAL_PATH . 'includes/class-referral-handler.php');
}
// Include necessary files
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-referral-handler.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-commission-calculator.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-dashboard.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-admin-dashboard.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-coach-admin-dashboard.php';
error_log('All plugin files loaded, Referral Handler exists: ' . class_exists('InterSoccer_Referral_Handler'));

// Main plugin class
class InterSoccer_Referral_System {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        register_uninstall_hook(__FILE__, ['InterSoccer_Referral_System', 'uninstall']);
    }
    
    public function init() {
        // Load text domain
        load_plugin_textdomain('intersoccer-referral', false, dirname(INTERSOCCER_REFERRAL_BASENAME) . '/languages');
        
        // Initialize classes FIRST (before coach admin dashboard)
        new InterSoccer_Referral_Handler();
        new InterSoccer_Commission_Calculator();
        new InterSoccer_Referral_Dashboard();
        new InterSoccer_Referral_Admin_Dashboard();
        
        // Initialize coach admin dashboard AFTER other classes
        new InterSoccer_Coach_Admin_Dashboard();
        
        // Add custom user roles
        $this->add_custom_roles();
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    public function activate() {
        // Create database tables
        $this->create_database_tables();
        
        // Add custom user roles and capabilities
        $this->add_custom_roles();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log activation
        error_log('InterSoccer Referral System activated successfully');
    }
    
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('intersoccer_daily_cleanup');
        wp_clear_scheduled_hook('intersoccer_weekly_reports');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        error_log('InterSoccer Referral System deactivated');
    }
    
    public static function uninstall() {
        // Remove custom roles
        remove_role('coach');
        
        // Remove options
        delete_option('intersoccer_commission_first');
        delete_option('intersoccer_commission_second');
        delete_option('intersoccer_commission_third');
        delete_option('intersoccer_version');
        
        // Optional: Remove database tables (commented out for safety)
        /*
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}intersoccer_referrals");
        */
        
        // Log uninstall
        error_log('InterSoccer Referral System uninstalled');
    }
    
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Referrals table
        $referrals_table = $wpdb->prefix . 'intersoccer_referrals';
        $sql = "CREATE TABLE $referrals_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            coach_id bigint(20) unsigned NOT NULL,
            customer_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            commission_amount decimal(10,2) NOT NULL DEFAULT '0.00',
            loyalty_bonus decimal(10,2) NOT NULL DEFAULT '0.00',
            retention_bonus decimal(10,2) NOT NULL DEFAULT '0.00',
            status varchar(20) NOT NULL DEFAULT 'pending',
            purchase_count int(11) NOT NULL DEFAULT 1,
            referral_code varchar(100) DEFAULT NULL,
            conversion_date datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_coach_id (coach_id),
            KEY idx_customer_id (customer_id),
            KEY idx_order_id (order_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        // Coach performance table
        $performance_table = $wpdb->prefix . 'intersoccer_coach_performance';
        $performance_sql = "CREATE TABLE $performance_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            coach_id bigint(20) unsigned NOT NULL,
            month_year varchar(7) NOT NULL,
            referrals_count int(11) NOT NULL DEFAULT 0,
            conversions_count int(11) NOT NULL DEFAULT 0,
            total_commission decimal(10,2) NOT NULL DEFAULT '0.00',
            total_bonuses decimal(10,2) NOT NULL DEFAULT '0.00',
            tier varchar(20) DEFAULT 'Bronze',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_coach_month (coach_id, month_year),
            KEY idx_month_year (month_year),
            KEY idx_tier (tier)
        ) $charset_collate;";
        
        // Coach achievements table
        $achievements_table = $wpdb->prefix . 'intersoccer_coach_achievements';
        $achievements_sql = "CREATE TABLE $achievements_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            coach_id bigint(20) unsigned NOT NULL,
            achievement_type varchar(50) NOT NULL,
            achievement_name varchar(100) NOT NULL,
            description text,
            points int(11) NOT NULL DEFAULT 0,
            earned_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_coach_id (coach_id),
            KEY idx_achievement_type (achievement_type),
            KEY idx_earned_at (earned_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($performance_sql);
        dbDelta($achievements_sql);
        
        // Update version
        update_option('intersoccer_version', INTERSOCCER_REFERRAL_VERSION);
    }
    
    private function add_custom_roles() {
        // Add Coach role
        $coach_capabilities = [
            'read' => true,
            'view_referral_dashboard' => true,
            'manage_referrals' => true,
            'view_coach_reports' => true,
        ];
        
        if (!get_role('coach')) {
            add_role('coach', __('Coach', 'intersoccer-referral'), $coach_capabilities);
        }
        
        // Add capabilities to existing roles
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('view_referral_dashboard');
            $admin_role->add_cap('manage_referrals');
            $admin_role->add_cap('view_coach_reports');
            $admin_role->add_cap('manage_coach_system');
        }
    }
    
    private function set_default_options() {
        // Commission rates
        add_option('intersoccer_commission_first', 15);
        add_option('intersoccer_commission_second', 7.5);
        add_option('intersoccer_commission_third', 5);
        
        // Loyalty bonuses
        add_option('intersoccer_loyalty_bonus_first', 5);
        add_option('intersoccer_loyalty_bonus_second', 8);
        add_option('intersoccer_loyalty_bonus_third', 15);
        
        // Retention bonuses
        add_option('intersoccer_retention_season_2', 25);
        add_option('intersoccer_retention_season_3', 50);
        add_option('intersoccer_network_effect_bonus', 15);
        
        // Tier thresholds
        add_option('intersoccer_tier_silver', 5);
        add_option('intersoccer_tier_gold', 10);
        add_option('intersoccer_tier_platinum', 20);
        
        // Customer incentives
        add_option('intersoccer_new_customer_discount', 10);
        add_option('intersoccer_new_customer_credits', 50);
        add_option('intersoccer_first_session_bonus', 200);
        
        // System settings
        add_option('intersoccer_cookie_duration', 30);
        add_option('intersoccer_enable_gamification', 1);
        add_option('intersoccer_enable_email_notifications', 1);
    }
    
    public function enqueue_frontend_assets() {
        if (is_user_logged_in() && current_user_can('view_referral_dashboard') && !is_account_page()) {
            wp_enqueue_style('intersoccer-dashboard-css', INTERSOCCER_REFERRAL_URL . 'assets/css/dashboard.css', [], INTERSOCCER_REFERRAL_VERSION);
            wp_enqueue_script('intersoccer-dashboard-js', INTERSOCCER_REFERRAL_URL . 'assets/js/dashboard.js', ['jquery'], INTERSOCCER_REFERRAL_VERSION, true);
            wp_localize_script('intersoccer-dashboard-js', 'intersoccer_dashboard', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('intersoccer_dashboard_nonce'),
                'copy_text' => __('Link copied!', 'intersoccer-referral'),
                'error_text' => __('Error occurred', 'intersoccer-referral')
            ]);
        }
    }
    
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'intersoccer') === false) {
            return;
        }
        
        wp_enqueue_style(
            'intersoccer-admin-css',
            INTERSOCCER_REFERRAL_URL . 'assets/css/admin-dashboard.css',
            [],
            INTERSOCCER_REFERRAL_VERSION
        );
        
        wp_enqueue_script(
            'chart-js',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js',
            [],
            '3.9.1'
        );
        
        wp_enqueue_script(
            'intersoccer-admin-js',
            INTERSOCCER_REFERRAL_URL . 'assets/js/admin-dashboard.js',
            ['jquery', 'chart-js'],
            INTERSOCCER_REFERRAL_VERSION,
            true
        );
        
        wp_localize_script('intersoccer-admin-js', 'intersoccer_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('intersoccer_admin_nonce'),
            'strings' => [
                'confirm_clear' => __('Are you sure you want to clear all demo data? This action cannot be undone.', 'intersoccer-referral'),
                'success_populate' => __('Demo data populated successfully!', 'intersoccer-referral'),
                'success_clear' => __('Demo data cleared successfully!', 'intersoccer-referral'),
                'error_generic' => __('An error occurred. Please try again.', 'intersoccer-referral')
            ]
        ]);
    }
}

// Initialize the plugin
function intersoccer_referral_system() {
    return InterSoccer_Referral_System::get_instance();
}

// Start the plugin
intersoccer_referral_system();

// Utility functions for template use
function intersoccer_get_coach_referral_link($coach_id = null) {
    if (!$coach_id) {
        $coach_id = get_current_user_id();
    }
    return InterSoccer_Referral_Handler::generate_coach_referral_link($coach_id);
}

function intersoccer_get_coach_credits($coach_id = null) {
    if (!$coach_id) {
        $coach_id = get_current_user_id();
    }
    return (float) get_user_meta($coach_id, 'intersoccer_credits', true);
}

function intersoccer_get_customer_credits($customer_id = null) {
    if (!$customer_id) {
        $customer_id = get_current_user_id();
    }
    return (float) get_user_meta($customer_id, 'intersoccer_customer_credits', true);
}

function intersoccer_get_coach_tier($coach_id = null) {
    if (!$coach_id) {
        $coach_id = get_current_user_id();
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'intersoccer_referrals';
    $referral_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE coach_id = %d AND status = 'completed'",
        $coach_id
    ));
    
    $silver_threshold = get_option('intersoccer_tier_silver', 5);
    $gold_threshold = get_option('intersoccer_tier_gold', 10);
    $platinum_threshold = get_option('intersoccer_tier_platinum', 20);
    
    if ($referral_count >= $platinum_threshold) return 'Platinum';
    if ($referral_count >= $gold_threshold) return 'Gold';
    if ($referral_count >= $silver_threshold) return 'Silver';
    return 'Bronze';
}

// Template functions for shortcodes
function intersoccer_coach_dashboard_shortcode($atts) {
    $dashboard = new InterSoccer_Dashboard();
    return $dashboard->render_dashboard();
}
add_shortcode('intersoccer_coach_dashboard', 'intersoccer_coach_dashboard_shortcode');

// AJAX handlers for coach dashboard
add_action('wp_ajax_intersoccer_copy_referral_link', function() {
    check_ajax_referer('intersoccer_dashboard_nonce', 'nonce');
    
    if (!current_user_can('view_referral_dashboard')) {
        wp_die('Insufficient permissions');
    }
    
    $user_id = get_current_user_id();
    $referral_link = intersoccer_get_coach_referral_link($user_id);
    
    wp_send_json_success(['link' => $referral_link]);
});

// Scheduled events
add_action('wp', function() {
    if (!wp_next_scheduled('intersoccer_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'intersoccer_daily_cleanup');
    }
    
    if (!wp_next_scheduled('intersoccer_weekly_reports')) {
        wp_schedule_event(time(), 'weekly', 'intersoccer_weekly_reports');
    }
});

// Daily cleanup task
add_action('intersoccer_daily_cleanup', function() {
    global $wpdb;
    
    // Clean up expired referral cookies (older than 30 days)
    $cookie_duration = get_option('intersoccer_cookie_duration', 30);
    $expiry_date = date('Y-m-d H:i:s', strtotime("-{$cookie_duration} days"));
    
    // Update performance metrics
    $coaches = get_users(['role' => 'coach']);
    foreach ($coaches as $coach) {
        intersoccer_update_coach_performance($coach->ID);
    }
    
    error_log('InterSoccer daily cleanup completed');
});

// Weekly reports
add_action('intersoccer_weekly_reports', function() {
    if (!get_option('intersoccer_enable_email_notifications')) {
        return;
    }
    
    // Send weekly performance reports to coaches
    $coaches = get_users(['role' => 'coach']);
    foreach ($coaches as $coach) {
        intersoccer_send_weekly_report($coach->ID);
    }
    
    error_log('InterSoccer weekly reports sent');
});

// Helper function to update coach performance
function intersoccer_update_coach_performance($coach_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'intersoccer_referrals';
    $performance_table = $wpdb->prefix . 'intersoccer_coach_performance';
    
    $current_month = date('Y-m');
    
    // Get current month stats
    $stats = $wpdb->get_row($wpdb->prepare("
        SELECT 
            COUNT(*) as referrals_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as conversions_count,
            SUM(CASE WHEN status = 'completed' THEN commission_amount ELSE 0 END) as total_commission,
            SUM(CASE WHEN status = 'completed' THEN loyalty_bonus + retention_bonus ELSE 0 END) as total_bonuses
        FROM $table_name 
        WHERE coach_id = %d AND DATE_FORMAT(created_at, '%%Y-%%m') = %s
    ", $coach_id, $current_month));
    
    $tier = intersoccer_get_coach_tier($coach_id);
    
    // Insert or update performance record
    $wpdb->replace($performance_table, [
        'coach_id' => $coach_id,
        'month_year' => $current_month,
        'referrals_count' => $stats->referrals_count,
        'conversions_count' => $stats->conversions_count,
        'total_commission' => $stats->total_commission ?: 0,
        'total_bonuses' => $stats->total_bonuses ?: 0,
        'tier' => $tier
    ]);
}

// Helper function to send weekly reports
function intersoccer_send_weekly_report($coach_id) {
    $coach = get_user_by('ID', $coach_id);
    if (!$coach) return;
    
    // Get weekly stats
    global $wpdb;
    $table_name = $wpdb->prefix . 'intersoccer_referrals';
    
    $weekly_stats = $wpdb->get_row($wpdb->prepare("
        SELECT 
            COUNT(*) as weekly_referrals,
            SUM(CASE WHEN status = 'completed' THEN commission_amount ELSE 0 END) as weekly_commission
        FROM $table_name 
        WHERE coach_id = %d AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ", $coach_id));
    
    $credits = intersoccer_get_coach_credits($coach_id);
    $tier = intersoccer_get_coach_tier($coach_id);
    
    // Compose email
    $subject = sprintf(__('Your Weekly InterSoccer Report - %s', 'intersoccer-referral'), date('M d, Y'));
    
    $message = sprintf(
        __('Hi %s,

Here\'s your weekly performance summary:

🎯 New Referrals: %d
💰 Commission Earned: %.2f CHF
💳 Total Credits: %.2f CHF
🏆 Current Tier: %s

Keep up the great work!

Best regards,
The InterSoccer Team', 'intersoccer-referral'),
        $coach->display_name,
        $weekly_stats->weekly_referrals,
        $weekly_stats->weekly_commission ?: 0,
        $credits,
        $tier
    );
    
    wp_mail($coach->user_email, $subject, $message);
}