<?php
/**
 * Plugin Name: InterSoccer Referral System
 * Plugin URI: https://intersoccer.ch
 * Description: Advanced coach referral program with gamification and comprehensive analytics.
 * Version: 1.0.0
 * Author: Jeremy Lee
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
// Include modular admin dashboard classes
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-admin-dashboard-main.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-admin-coaches.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-admin-referrals.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-admin-financial.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-admin-settings.php';
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
        // Load text domain (move to avoid early loading issues)
        load_plugin_textdomain('intersoccer-referral', false, dirname(INTERSOCCER_REFERRAL_BASENAME) . '/languages');

        // Initialize core classes
        new InterSoccer_Referral_Handler();
        new InterSoccer_Commission_Calculator();
        new InterSoccer_Referral_Dashboard();
        new InterSoccer_Referral_Admin_Dashboard();
        new InterSoccer_Coach_Admin_Dashboard();

        // Add custom user roles
        $this->add_custom_roles();

        if (class_exists('WooCommerce')) {
            error_log('InterSoccer: WooCommerce detected, version: ' . WC()->version);
        }

        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Move Elementor integration to plugins_loaded
        add_action('elementor/loaded', [$this,'initiate_elementor_integration']);
        add_action('elementor/init', [$this, 'initiate_elementor_integration']);
    }

    public function initiate_elementor_integration() {
        
        require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-elementor-widgets.php';
        new InterSoccer_Elementor_Integration();
        error_log('InterSoccer: Elementor Integration Loaded: InterSoccer_Elementor_Integration');
    }

    public function activate() {
        // Create database tables
        $this->create_database_tables();
        
        // Add custom user roles and capabilities
        $this->add_custom_roles();
        
        // Set default options
        $this->set_default_options();

        // Initialize database optimization on plugin activation
        register_activation_hook(__FILE__, ['InterSoccer_Database_Optimizer', 'create_indexes']);
        
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
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id INT NOT NULL AUTO_INCREMENT,
            coach_id BIGINT UNSIGNED,
            customer_id BIGINT UNSIGNED NOT NULL,
            referrer_id BIGINT UNSIGNED,
            referrer_type ENUM('coach', 'customer') DEFAULT 'coach',
            order_id BIGINT UNSIGNED NOT NULL,
            commission_amount DECIMAL(10,2) DEFAULT '0.00',
            loyalty_bonus DECIMAL(10,2) DEFAULT '0.00',
            retention_bonus DECIMAL(10,2) DEFAULT '0.00',
            status VARCHAR(20) DEFAULT 'pending',
            purchase_count INT DEFAULT 1,
            referral_code VARCHAR(100),
            conversion_date DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_coach_id (coach_id),
            KEY idx_customer_id (customer_id),
            KEY idx_referrer_id (referrer_id),
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

        // Customer partnerships table
        $partnerships_table = $wpdb->prefix . 'intersoccer_customer_partnerships';
        $partnerships_sql = "CREATE TABLE $partnerships_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            coach_id bigint(20) unsigned NOT NULL,
            start_date datetime DEFAULT CURRENT_TIMESTAMP,
            end_date datetime NULL,
            total_orders int(11) DEFAULT 0,
            total_commission decimal(10,2) DEFAULT '0.00',
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_active_partnership (customer_id, status),
            KEY idx_coach_id (coach_id),
            KEY idx_status (status)
        ) $charset_collate;";

        // Customer activities table for tracking
        $activities_table = $wpdb->prefix . 'intersoccer_customer_activities';
        $activities_sql = "CREATE TABLE $activities_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            activity_type varchar(50) NOT NULL,
            activity_data longtext,
            points_awarded int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_customer_id (customer_id),
            KEY idx_activity_type (activity_type),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        // Referral credits table for customer credit system
        $credits_table = $wpdb->prefix . 'intersoccer_referral_credits';
        $credits_sql = "CREATE TABLE $credits_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            referral_id int(11),
            customer_id bigint(20) unsigned NOT NULL,
            coach_id bigint(20) unsigned,
            credit_amount decimal(10,2) NOT NULL DEFAULT '0.00',
            credit_type varchar(50) DEFAULT 'referral',
            status varchar(20) DEFAULT 'active',
            expires_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_customer_id (customer_id),
            KEY idx_coach_id (coach_id),
            KEY idx_referral_id (referral_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        // Credit redemptions table for tracking credit usage
        $redemptions_table = $wpdb->prefix . 'intersoccer_credit_redemptions';
        $redemptions_sql = "CREATE TABLE $redemptions_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            order_item_id bigint(20) unsigned,
            credit_amount decimal(10,2) NOT NULL DEFAULT '0.00',
            order_total decimal(10,2) NOT NULL DEFAULT '0.00',
            discount_applied decimal(10,2) NOT NULL DEFAULT '0.00',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_customer_id (customer_id),
            KEY idx_order_item_id (order_item_id),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($performance_sql);
        dbDelta($achievements_sql);
        dbDelta($partnerships_sql);
        dbDelta($activities_sql);
        dbDelta($credits_sql);
        dbDelta($redemptions_sql);
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
    
    /**
    * enqueue frontend assets
    */
    public function enqueue_frontend_assets() {
        // Coach dashboard assets (existing)
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

        // Customer dashboard assets
        if (is_user_logged_in()) {
            // Enqueue customer-specific assets
            if (!wp_script_is('intersoccer-dashboard-js', 'enqueued')) {
                wp_enqueue_script('intersoccer-customer-dashboard-js', INTERSOCCER_REFERRAL_URL . 'assets/js/dashboard.js', ['jquery'], INTERSOCCER_REFERRAL_VERSION, true);
            }
            
            // Always localize for customers (might not have coach capabilities)
            if (!wp_script_is('intersoccer_dashboard', 'done')) {
                wp_localize_script(
                    wp_script_is('intersoccer-dashboard-js', 'enqueued') ? 'intersoccer-dashboard-js' : 'intersoccer-customer-dashboard-js',
                    'intersoccer_dashboard',
                    [
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('intersoccer_dashboard_nonce'),
                        'copy_text' => __('Link copied!', 'intersoccer-referral'),
                        'error_text' => __('Error occurred', 'intersoccer-referral')
                    ]
                );
            }
        }
        
        // Elementor-specific assets (new) - only load if Elementor is active
        if (class_exists('\Elementor\Plugin')) {
            // Load for Elementor preview mode or when widgets are present on page
            if (\Elementor\Plugin::$instance->preview->is_preview_mode() || 
                $this->page_has_elementor_widgets()) {
                
                wp_enqueue_style(
                    'intersoccer-elementor-dashboard-css',
                    INTERSOCCER_REFERRAL_URL . 'assets/css/elementor-dashboard.css',
                    [],
                    INTERSOCCER_REFERRAL_VERSION
                );
                
                wp_enqueue_script(
                    'intersoccer-elementor-dashboard-js',
                    INTERSOCCER_REFERRAL_URL . 'assets/js/elementor-dashboard.js',
                    ['jquery'],
                    INTERSOCCER_REFERRAL_VERSION,
                    true
                );
                
                wp_localize_script('intersoccer-elementor-dashboard-js', 'intersoccer_elementor', [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('intersoccer_elementor_nonce'),
                    'strings' => [
                        'copy_success' => __('Link copied!', 'intersoccer-referral'),
                        'copy_error' => __('Failed to copy', 'intersoccer-referral'),
                        'loading' => __('Loading...', 'intersoccer-referral'),
                    ]
                ]);
            }
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

    public function debug_elementor_status() {
        error_log('=== InterSoccer Elementor Debug ===');
        error_log('Elementor Plugin class exists: ' . (class_exists('\Elementor\Plugin') ? 'YES' : 'NO'));
        
        if (class_exists('\Elementor\Plugin')) {
            error_log('Elementor Plugin instance exists: ' . (isset(\Elementor\Plugin::$instance) ? 'YES' : 'NO'));
            error_log('Elementor version: ' . (defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : 'NOT_DEFINED'));
        }
        
        error_log('Available actions: ' . implode(', ', array_keys($GLOBALS['wp_filter'])));
    }

    /**
     * Check if page has Elementor widgets
     */

    private function page_has_elementor_widgets() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // Check if page content contains InterSoccer Elementor widgets
        $widget_types = [
            'intersoccer_customer_dashboard',
            'intersoccer_coach_dashboard', 
            'intersoccer_referral_stats',
            'intersoccer_coach_leaderboard',
            'intersoccer_customer_progress'
        ];
        
        foreach ($widget_types as $widget_type) {
            if (strpos($post->post_content, $widget_type) !== false) {
                return true;
            }
        }
        
        return false;
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
    
    // Get current month stats with proper NULL handling
    $stats = $wpdb->get_row($wpdb->prepare("
        SELECT 
            COUNT(*) as referrals_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as conversions_count,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN commission_amount ELSE 0 END), 0) as total_commission,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN loyalty_bonus + retention_bonus ELSE 0 END), 0) as total_bonuses
        FROM $table_name 
        WHERE coach_id = %d AND DATE_FORMAT(created_at, '%%Y-%%m') = %s
    ", $coach_id, $current_month));
    
    $tier = intersoccer_get_coach_tier($coach_id);
    
    // Ensure all values are not null and properly typed
    $data = [
        'coach_id' => (int) $coach_id,
        'month_year' => $current_month,
        'referrals_count' => (int) ($stats->referrals_count ?? 0),
        'conversions_count' => (int) ($stats->conversions_count ?? 0),
        'total_commission' => (float) ($stats->total_commission ?? 0.00),
        'total_bonuses' => (float) ($stats->total_bonuses ?? 0.00),
        'tier' => $tier
    ];
    
    // Use wpdb->replace with proper data types
    $result = $wpdb->replace($performance_table, $data);
    
    if ($result === false) {
        error_log("InterSoccer: Failed to update coach performance for coach $coach_id: " . $wpdb->last_error);
    }
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