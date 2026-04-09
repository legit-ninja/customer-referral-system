<?php
/**
 * Plugin Name: InterSoccer Referral System
 * Plugin URI: https://intersoccer.ch
 * Description: Advanced coach referral program with gamification and comprehensive analytics.
 * Version: 1.3.10
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
define('INTERSOCCER_REFERRAL_VERSION', '1.11.11');
define('INTERSOCCER_REFERRAL_PATH', plugin_dir_path(__FILE__));
define('INTERSOCCER_REFERRAL_URL', plugin_dir_url(__FILE__));
define('INTERSOCCER_REFERRAL_BASENAME', plugin_basename(__FILE__));

/**
 * Lightweight debug logger for this plugin.
 *
 * @param string $message
 * @return void
 */
if (!function_exists('intersoccer_referral_log')) {
    function intersoccer_referral_log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log((string) $message);
        }
    }
}

if (!file_exists(INTERSOCCER_REFERRAL_PATH . 'includes/class-referral-handler.php')) {
    intersoccer_referral_log('Error: class-referral-handler.php not found at ' . INTERSOCCER_REFERRAL_PATH . 'includes/class-referral-handler.php');
}
// Include necessary files
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-referral-handler.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-commission-manager.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-commission-calculator.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-dashboard.php';
// Include modular admin dashboard classes
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-admin-dashboard-main.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-admin-coaches.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-admin-referrals.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-admin-financial.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-simulator.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-admin-settings.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-admin-points.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-admin-coach-assignments.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-coach-events-manager.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-admin-coach-events.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-points-manager.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-audit-logger.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-admin-audit.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-admin-dashboard.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-coach-admin-dashboard.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-user-roles.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-utils.php';
intersoccer_referral_log('All plugin files loaded, Referral Handler exists: ' . class_exists('InterSoccer_Referral_Handler'));

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
        add_action('init', [$this, 'load_referral_textdomain'], 1);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        register_uninstall_hook(__FILE__, ['InterSoccer_Referral_System', 'uninstall']);
    }

    /**
     * Load plugin text domain. Called on plugins_loaded (via init) and again on init priority 1
     * so that when WPML (or other plugins) set the locale after plugins_loaded, we load the
     * correct .mo for the current language (e.g. fr_FR, de_DE).
     */
    public function load_referral_textdomain() {
        $plugin_rel_path = dirname(INTERSOCCER_REFERRAL_BASENAME);
        $plugin_lang_dir = WP_PLUGIN_DIR . '/' . $plugin_rel_path . '/languages/';
        $locale = determine_locale();
        $domain = 'intersoccer-referral';
        $attempts = [];
        $locale_parts = preg_split('/[_-]/', (string) $locale);

        $attempts[] = $locale;
        if (!empty($locale_parts[0])) {
            $base_lang = $locale_parts[0];
            if ($base_lang !== $locale) {
                $attempts[] = $base_lang;
            }
            foreach (glob($plugin_lang_dir . $domain . '-' . $base_lang . '_*.mo') as $regional_file) {
                $regional_locale = str_replace($domain . '-', '', basename($regional_file, '.mo'));
                if ($regional_locale && $regional_locale !== $locale) {
                    $attempts[] = $regional_locale;
                }
            }
        }
        $attempts[] = '';
        $attempts = array_unique($attempts);

        $loaded = false;
        foreach ($attempts as $attempt_locale) {
            $candidate = $attempt_locale !== ''
                ? $plugin_lang_dir . $domain . '-' . $attempt_locale . '.mo'
                : $plugin_lang_dir . $domain . '.mo';

            if (@is_readable($candidate)) {
                $loaded = load_textdomain($domain, $candidate);
                if ($loaded && defined('WP_DEBUG') && WP_DEBUG) {
                    intersoccer_referral_log('InterSoccer Referral: Loaded translations from plugin directory: ' . $candidate);
                }
                break;
            }
        }

        if (!$loaded) {
            load_plugin_textdomain($domain, false, $plugin_rel_path . '/languages');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                intersoccer_referral_log('InterSoccer Referral: Attempted fallback translation loading for locale: ' . $locale);
            }
        }
    }
    
    public function init() {
        $this->load_referral_textdomain();

        // Initialize core classes
        new InterSoccer_Referral_Handler();
        new InterSoccer_Commission_Calculator();
        
        
        new InterSoccer_Referral_Dashboard();
        
        
        new InterSoccer_Referral_Admin_Dashboard();
        new InterSoccer_Coach_Admin_Dashboard();
        new InterSoccer_Points_Manager();
        new InterSoccer_Admin_Audit();

        // Initialize audit logging system
        InterSoccer_Audit_Logger::get_instance();

        // Add custom user roles
        $this->add_custom_roles();

        if (class_exists('WooCommerce')) {
            intersoccer_referral_log('InterSoccer: WooCommerce detected, version: ' . WC()->version);
        }

        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Move Elementor integration to plugins_loaded
        add_action('elementor/loaded', [$this,'initiate_elementor_integration']);
        add_action('elementor/init', [$this, 'initiate_elementor_integration']);

        // Customer-facing referral tools (WooCommerce My Account)
        add_action('init', [$this, 'register_customer_account_endpoint']);
        add_filter('query_vars', [$this, 'add_customer_account_query_var']);
        add_filter('woocommerce_get_query_vars', [$this, 'add_woocommerce_query_vars'], 10, 1);
        add_filter('woocommerce_account_menu_items', [$this, 'add_customer_dashboard_menu_item'], 10);
        add_filter('woocommerce_get_endpoint_url', [$this, 'filter_endpoint_url'], 10, 4);
        
        // Register endpoint content for all language variants (same pattern as player-management plugin)
        add_action('woocommerce_account_referrals_endpoint', [$this, 'render_customer_account_endpoint']);
        add_action('woocommerce_account_parrainages_endpoint', [$this, 'render_customer_account_endpoint']);
        add_action('woocommerce_account_empfehlungen_endpoint', [$this, 'render_customer_account_endpoint']);
    }

    /**
     * Guard to prevent rendering the referral endpoint more than once per request.
     *
     * @var bool
     */
    private static $referral_endpoint_rendered = false;

    public function initiate_elementor_integration() {
        
        require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-elementor-widgets.php';
        new InterSoccer_Elementor_Integration();
        intersoccer_referral_log('InterSoccer: Elementor Integration Loaded: InterSoccer_Elementor_Integration');
    }

    public function activate() {
        // Create database tables
        $this->create_database_tables();

        // Add custom user roles and capabilities
        $this->add_custom_roles();

        // Set default options
        $this->set_default_options();

        // Migrate existing referral codes to new format
        $this->migrate_referral_codes();

        // Initialize database optimization on plugin activation
        register_activation_hook(__FILE__, ['InterSoccer_Database_Optimizer', 'create_indexes']);

        // Flush rewrite rules
        flush_rewrite_rules();

        // Log activation
        intersoccer_referral_log('InterSoccer Referral System activated successfully');
    }
    
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('intersoccer_daily_cleanup');
        wp_clear_scheduled_hook('intersoccer_weekly_reports');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        intersoccer_referral_log('InterSoccer Referral System deactivated');
    }
    
    public static function uninstall() {
        // Remove custom roles
        remove_role('coach');
        remove_role('content_creator');
        remove_role('partner');
        
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
        intersoccer_referral_log('InterSoccer Referral System uninstalled');
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
        
        // Coach assignments table for venue/course access control
        $assignments_table = $wpdb->prefix . 'intersoccer_coach_assignments';
        $assignments_sql = "CREATE TABLE $assignments_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            coach_id bigint(20) unsigned NOT NULL,
            venue varchar(255) NOT NULL,
            canton varchar(100) DEFAULT '',
            assignment_type enum('venue','camp','course','event') DEFAULT 'venue',
            active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_coach_venue (coach_id, venue, assignment_type),
            KEY idx_coach_id (coach_id),
            KEY idx_venue (venue(100)),
            KEY idx_canton (canton),
            KEY idx_assignment_type (assignment_type),
            KEY idx_active (active)
        ) $charset_collate;";

        dbDelta($assignments_sql);

        // Coach events participation table (tracks which events coaches are associated with)
        $coach_events_table = $wpdb->prefix . 'intersoccer_coach_events';
        $coach_events_sql = "CREATE TABLE $coach_events_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            coach_id bigint(20) unsigned NOT NULL,
            event_id bigint(20) unsigned NOT NULL,
            event_type varchar(50) DEFAULT 'product' COMMENT 'Type of event reference (product, post, custom)',
            source enum('coach','admin') DEFAULT 'coach' COMMENT 'Who created the association',
            status enum('active','inactive','pending') DEFAULT 'active',
            assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
            assigned_by bigint(20) unsigned DEFAULT NULL COMMENT 'Admin/user who created association',
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            notes text,
            PRIMARY KEY (id),
            UNIQUE KEY unique_coach_event (coach_id, event_id, event_type),
            KEY idx_coach_id (coach_id),
            KEY idx_event_id (event_id),
            KEY idx_status (status),
            KEY idx_source (source),
            KEY idx_assigned_at (assigned_at)
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

        // Coach commissions table for finalized commission payouts
        $coach_commissions_table = $wpdb->prefix . 'intersoccer_coach_commissions';
        $coach_commissions_sql = "CREATE TABLE $coach_commissions_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            coach_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            commission_amount decimal(10,2) NOT NULL DEFAULT '0.00',
            commission_rate decimal(10,2) NOT NULL DEFAULT '0.00',
            order_total decimal(10,2) NOT NULL DEFAULT '0.00',
            currency varchar(10) DEFAULT 'CHF',
            status varchar(20) DEFAULT 'approved',
            notes longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_coach_order (coach_id, order_id),
            KEY idx_coach_id (coach_id),
            KEY idx_order_id (order_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        // Audit log table for detailed event tracking
        $audit_log_table = $wpdb->prefix . 'intersoccer_audit_log';
        $audit_log_sql = "CREATE TABLE $audit_log_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(100) NOT NULL,
            category varchar(50) NOT NULL DEFAULT 'general',
            user_id bigint(20) unsigned NULL,
            data longtext NOT NULL,
            ip_address varchar(45) DEFAULT '',
            user_agent text DEFAULT '',
            session_id varchar(255) DEFAULT '',
            created_at datetime NOT NULL,
            created_at_gmt datetime NOT NULL,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY category (category),
            KEY user_id (user_id),
            KEY created_at (created_at),
            KEY ip_address (ip_address)
        ) $charset_collate;";

        // Points log table for digital currency ledger
        $points_log_table = $wpdb->prefix . 'intersoccer_points_log';
        $points_log_sql = "CREATE TABLE $points_log_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned,
            transaction_type varchar(50) NOT NULL,
            points_amount int(11) NOT NULL COMMENT 'Integer points only - no fractional points',
            points_balance int(11) NOT NULL COMMENT 'Integer balance - updated Nov 4, 2025',
            reference_type varchar(50),
            reference_id bigint(20) unsigned,
            description text,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_customer_id (customer_id),
            KEY idx_order_id (order_id),
            KEY idx_transaction_type (transaction_type),
            KEY idx_created_at (created_at),
            KEY idx_reference (reference_type, reference_id)
        ) $charset_collate;";

        // Referral rewards table for tracking coach rewards from referral codes
        $referral_rewards_table = $wpdb->prefix . 'intersoccer_referral_rewards';
        $referral_rewards_sql = "CREATE TABLE $referral_rewards_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            coach_id bigint(20) unsigned NOT NULL,
            customer_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            referral_code varchar(100) NOT NULL,
            points_awarded int(11) NOT NULL DEFAULT 0 COMMENT 'Integer points only - updated Nov 4, 2025',
            discount_amount decimal(10,2) NOT NULL DEFAULT '0.00',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_coach_id (coach_id),
            KEY idx_customer_id (customer_id),
            KEY idx_order_id (order_id),
            KEY idx_referral_code (referral_code),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        // Purchase rewards table for tracking coach rewards from customer purchases
        $purchase_rewards_table = $wpdb->prefix . 'intersoccer_purchase_rewards';
        $purchase_rewards_sql = "CREATE TABLE $purchase_rewards_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            coach_id bigint(20) unsigned NOT NULL,
            customer_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            order_total decimal(10,2) NOT NULL DEFAULT '0.00',
            points_awarded int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_coach_id (coach_id),
            KEY idx_customer_id (customer_id),
            KEY idx_order_id (order_id),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($coach_events_sql);
        dbDelta($performance_sql);
        dbDelta($achievements_sql);
        dbDelta($partnerships_sql);
        dbDelta($referral_rewards_sql);
        dbDelta($purchase_rewards_sql);
        dbDelta($activities_sql);
        dbDelta($credits_sql);
        dbDelta($redemptions_sql);
        dbDelta($coach_commissions_sql);
        dbDelta($audit_log_sql);
        dbDelta($points_log_sql);
        dbDelta($assignments_sql);
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

        // Add Content Creator role
        $content_creator_capabilities = [
            'read' => true,
            'view_referral_dashboard' => true,
            'create_content' => true,
            'edit_own_content' => true,
            'manage_content_referrals' => true,
        ];

        if (!get_role('content_creator')) {
            add_role('content_creator', __('Content Creator', 'intersoccer-referral'), $content_creator_capabilities);
        }

        // Add Partner role
        $partner_capabilities = [
            'read' => true,
            'view_referral_dashboard' => true,
            'manage_partnerships' => true,
            'view_partner_reports' => true,
            'manage_partner_referrals' => true,
        ];

        if (!get_role('partner')) {
            add_role('partner', __('Partner', 'intersoccer-referral'), $partner_capabilities);
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

        // Loyalty bonuses (percentages of order total) - opt-in, default 0
        add_option('intersoccer_loyalty_bonus_first', 0);
        add_option('intersoccer_loyalty_bonus_second', 0);
        add_option('intersoccer_loyalty_bonus_third', 0);

        // Retention bonuses (fixed CHF) - opt-in, default 0
        add_option('intersoccer_retention_season_2', 0);
        add_option('intersoccer_retention_season_3', 0);

        // Network effect bonus (fixed CHF) - opt-in, default 0
        add_option('intersoccer_network_effect_bonus', 0);

        // Seasonal bonuses (percentages of base commission) - opt-in, default 0
        add_option('intersoccer_seasonal_bonus_aug_sep', 0);
        add_option('intersoccer_seasonal_bonus_nov_dec', 0);
        add_option('intersoccer_seasonal_bonus_mar_apr', 0);

        // Weekend bonus (percentage of base commission) - opt-in, default 0
        add_option('intersoccer_weekend_bonus', 0);

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
        add_option('intersoccer_referral_eligibility_months', 18);
        add_option('intersoccer_max_credits_per_order', 9999);
        add_option('intersoccer_passive_mode', true);

        // Upgrade legacy ceiling (pre-unlimited deployments defaulted to 100)
        $legacy_max = get_option('intersoccer_max_credits_per_order', 9999);
        if ((int) $legacy_max === 100) {
            update_option('intersoccer_max_credits_per_order', 9999);
        }
    }

    /**
     * Register WooCommerce My Account endpoint for the customer referral dashboard
     * Supports multiple language-specific slugs for WPML
     *
     * @return void
     */
    public function register_customer_account_endpoint() {
        if (!function_exists('add_rewrite_endpoint')) {
            return;
        }

        $mask = 0;
        if (defined('EP_ROOT')) {
            $mask |= EP_ROOT;
        }
        if (defined('EP_PAGES')) {
            $mask |= EP_PAGES;
        }
        if ($mask === 0) {
            $mask = 1;
        }

        // Define language-specific endpoint slugs
        $endpoint_slugs = [
            'en' => 'referrals',
            'fr' => 'parrainages',
            'de' => 'empfehlungen',
        ];

        // Register all language variants
        foreach ($endpoint_slugs as $lang => $slug) {
            add_rewrite_endpoint($slug, $mask);
        }
        
        // Also register the default English slug for backward compatibility
        add_rewrite_endpoint('referrals', $mask);
    }
    
    /**
     * Ensure WooCommerce recognizes all language variants of the endpoint
     * This is critical for WooCommerce to fire the endpoint action hooks
     * 
     * @param array $query_vars
     * @return array
     */
    public function add_woocommerce_query_vars($query_vars) {
        // Register ALL language versions so WooCommerce recognizes them all
        $endpoint_slugs = [
            'referrals',
            'parrainages',
            'empfehlungen',
        ];
        
        foreach ($endpoint_slugs as $slug) {
            $query_vars[$slug] = $slug;
        }
        
        return $query_vars;
    }

    /**
     * Ensure WooCommerce query vars include the referrals endpoint
     * Includes all language-specific variants
     *
     * @param array $vars
     * @return array
     */
    public function add_customer_account_query_var($vars) {
        
        // Define language-specific endpoint slugs
        $endpoint_slugs = [
            'en' => 'referrals',
            'fr' => 'parrainages',
            'de' => 'empfehlungen',
        ];

        // Add all language variants to query vars
        foreach ($endpoint_slugs as $lang => $slug) {
            if (!in_array($slug, $vars, true)) {
                $vars[] = $slug;
            }
        }
        
        // Also ensure default 'referrals' is included
        if (!in_array('referrals', $vars, true)) {
            $vars[] = 'referrals';
        }
        
        return $vars;
    }
    
    /**
     * Get the endpoint slug for the current language
     *
     * @return string
     */
    private function get_referrals_endpoint_slug() {
        // Define language-specific endpoint slugs
        $endpoint_slugs = [
            'en' => 'referrals',
            'fr' => 'parrainages',
            'de' => 'empfehlungen',
        ];

        // Get current language
        $current_lang = 'en'; // Default
        if (defined('ICL_SITEPRESS_VERSION') && function_exists('icl_get_current_language')) {
            $current_lang = icl_get_current_language();
        }

        // Return the slug for current language, or default to 'referrals'
        return isset($endpoint_slugs[$current_lang]) ? $endpoint_slugs[$current_lang] : 'referrals';
    }

    /**
     * Inject "Referrals" navigation entry into the WooCommerce account menu
     *
     * @param array $items
     * @return array
     */
    public function add_customer_dashboard_menu_item($items) {
        if (!$this->should_display_customer_dashboard()) {
            return $items;
        }

        // Get translated label - defaults to 'Referrals'
        $label = __('Referrals', 'intersoccer-referral');

        // WPML translation support
        if (defined('ICL_SITEPRESS_VERSION')) {
            // Register string for translation (only needs to happen once, but safe to call multiple times)
            do_action(
                'wpml_register_single_string',
                'Intersoccer Referral System',
                'woocommerce_account_menu_referrals',
                $label
            );

            // Get translated string
            $label = apply_filters(
                'wpml_translate_single_string',
                $label,
                'Intersoccer Referral System',
                'woocommerce_account_menu_referrals'
            );
        }

        // Get the endpoint slug for current language
        $endpoint_slug = $this->get_referrals_endpoint_slug();

        $new_items = [];
        $inserted = false;

        foreach ($items as $key => $item_label) {
            $new_items[$key] = $item_label;
            if (!$inserted && $key === 'dashboard') {
                $new_items[$endpoint_slug] = $label;
                $inserted = true;
            }
        }

        if (!$inserted) {
            $new_items[$endpoint_slug] = $label;
        }

        return $new_items;
    }

    /**
     * Filter WooCommerce endpoint URL to use language-specific slugs
     *
     * @param string $url
     * @param string $endpoint
     * @param string $value
     * @param string $permalink
     * @return string
     */
    public function filter_endpoint_url($url, $endpoint, $value, $permalink) {
        // Define language-specific endpoint slugs
        $endpoint_slugs = [
            'en' => 'referrals',
            'fr' => 'parrainages',
            'de' => 'empfehlungen',
        ];

        // Get current language
        $current_lang = 'en'; // Default
        if (defined('ICL_SITEPRESS_VERSION') && function_exists('icl_get_current_language')) {
            $current_lang = icl_get_current_language();
        }

        // If this is one of our referral endpoints, replace with language-specific slug
        if (in_array($endpoint, $endpoint_slugs, true) || $endpoint === 'referrals') {
            $correct_slug = isset($endpoint_slugs[$current_lang]) ? $endpoint_slugs[$current_lang] : 'referrals';
            
            // Only replace if the endpoint doesn't match the current language slug
            if ($endpoint !== $correct_slug) {
                // Replace the endpoint in the URL - handle both with and without trailing slash
                $url = str_replace('/' . $endpoint . '/', '/' . $correct_slug . '/', $url);
                $url = str_replace('/' . $endpoint . '?', '/' . $correct_slug . '?', $url);
                $url = preg_replace('#/' . preg_quote($endpoint, '#') . '$#', '/' . $correct_slug, $url);
            }
        }

        return $url;
    }

    /**
     * Render the customer referral dashboard within the WooCommerce account area
     *
     * @return void
     */
    public function render_customer_account_endpoint() {
        // Prevent duplicate renders in the same request.
        if (self::$referral_endpoint_rendered) {
            return;
        }
        self::$referral_endpoint_rendered = true;
        
        // Enqueue dashboard assets for My Account page
        wp_enqueue_style('intersoccer-dashboard-css', INTERSOCCER_REFERRAL_URL . 'assets/css/dashboard.css', [], INTERSOCCER_REFERRAL_VERSION);
        wp_enqueue_script('intersoccer-dashboard-js', INTERSOCCER_REFERRAL_URL . 'assets/js/dashboard.js', ['jquery'], INTERSOCCER_REFERRAL_VERSION, true);
        wp_localize_script('intersoccer-dashboard-js', 'intersoccer_dashboard', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('intersoccer_dashboard_nonce'),
        ]);
        
        $dashboard = new InterSoccer_Referral_Dashboard();
        echo $dashboard->render_customer_dashboard();
    }

    /**
     * Determine whether the current user should see the customer referral dashboard navigation
     *
     * @return bool
     */
    private function should_display_customer_dashboard() {
        // Must be logged in
        if (!is_user_logged_in()) {
            return false;
        }

        // Must have WooCommerce active
        if (!class_exists('WooCommerce')) {
            return false;
        }

        // Show to all logged-in users (including admins, coaches, etc.)
        return true;
    }

    /**
     * Migrate existing referral codes to new uppercase format without underscores
     */
    private function migrate_referral_codes() {
        global $wpdb;

        // Get all users with referral codes
        $users_with_codes = $wpdb->get_results("
            SELECT user_id, meta_key, meta_value
            FROM {$wpdb->usermeta}
            WHERE meta_key IN ('referral_code', 'intersoccer_customer_referral_code')
            AND meta_value LIKE '%_%'
        ");

        $migrated_count = 0;

        foreach ($users_with_codes as $user_meta) {
            $old_code = $user_meta->meta_value;
            $new_code = strtoupper(str_replace('_', '', $old_code));

            // Only update if the code actually changed
            if ($old_code !== $new_code) {
                update_user_meta($user_meta->user_id, $user_meta->meta_key, $new_code);
                $migrated_count++;

                intersoccer_referral_log("Migrated referral code for user {$user_meta->user_id}: {$old_code} -> {$new_code}");
            }
        }

        // Also update referral codes in the database tables
        $tables_to_update = [
            'intersoccer_referrals' => 'referral_code',
            'intersoccer_referral_rewards' => 'referral_code'
        ];

        foreach ($tables_to_update as $table => $column) {
            $table_name = $wpdb->prefix . $table;

            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                $codes_to_update = $wpdb->get_results($wpdb->prepare("
                    SELECT id, {$column} as code
                    FROM {$table_name}
                    WHERE {$column} LIKE %s
                ", '%_%'));

                foreach ($codes_to_update as $row) {
                    $new_code = strtoupper(str_replace('_', '', $row->code));
                    if ($row->code !== $new_code) {
                        $wpdb->update(
                            $table_name,
                            [$column => $new_code],
                            ['id' => $row->id]
                        );
                        $migrated_count++;
                    }
                }
            }
        }

        if ($migrated_count > 0) {
            intersoccer_referral_log("InterSoccer: Migrated {$migrated_count} referral codes to new format");
        }
    }
    
    /**
    * enqueue frontend assets
    */
    public function enqueue_frontend_assets() {
        if ($this->should_enqueue_customer_dashboard_assets()) {
            wp_enqueue_style(
                'intersoccer-customer-dashboard-css',
                INTERSOCCER_REFERRAL_URL . 'assets/css/customer-dashboard.css',
                [],
                INTERSOCCER_REFERRAL_VERSION
            );
            wp_enqueue_script(
                'intersoccer-customer-dashboard-js',
                INTERSOCCER_REFERRAL_URL . 'assets/js/customer-dashboard.js',
                [],
                INTERSOCCER_REFERRAL_VERSION,
                true
            );
        }

        // Checkout assets (referral code + points redemption fields)
        if (function_exists('is_checkout') && is_checkout() && is_user_logged_in() && !get_option('intersoccer_passive_mode', false)) {
            $available_points = (int) get_user_meta(get_current_user_id(), 'intersoccer_points_balance', true);

            wp_enqueue_script(
                'intersoccer-checkout-js',
                INTERSOCCER_REFERRAL_URL . 'assets/js/checkout.js',
                ['jquery'],
                INTERSOCCER_REFERRAL_VERSION,
                true
            );

            wp_localize_script('intersoccer-checkout-js', 'intersoccer_checkout', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('intersoccer_checkout_nonce'),
                'available_points' => $available_points,
                'i18n' => [
                    'please_enter_referral_code' => __('Please enter a referral code', 'intersoccer-referral'),
                    'applying' => __('Applying...', 'intersoccer-referral'),
                    'applied' => __('Applied', 'intersoccer-referral'),
                    'apply_code' => __('Apply Code', 'intersoccer-referral'),
                    'error_applying_referral_code' => __('Error applying referral code', 'intersoccer-referral'),
                    'discount_label' => __('Discount:', 'intersoccer-referral'),
                    'points_discount' => __('points discount', 'intersoccer-referral'),
                    'change_code' => __('Change Code', 'intersoccer-referral'),
                    'clearing' => __('Clearing...', 'intersoccer-referral'),
                ],
            ]);
        }

        // Modern coach dashboard assets
        if (is_user_logged_in() && current_user_can('coach') && !is_account_page()) {
            wp_enqueue_style('modern-dashboard-css', INTERSOCCER_REFERRAL_URL . 'assets/css/modern-dashboard.css', [], INTERSOCCER_REFERRAL_VERSION);
            wp_enqueue_style('aos-css', 'https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css', [], '2.3.4');
            wp_enqueue_script('aos-js', 'https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js', [], '2.3.4', true);
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.0', true);
            wp_enqueue_script('modern-dashboard-js', INTERSOCCER_REFERRAL_URL . 'assets/js/modern-dashboard.js', ['jquery', 'aos-js', 'chart-js'], INTERSOCCER_REFERRAL_VERSION, true);

            wp_localize_script('modern-dashboard-js', 'intersoccer_dashboard', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dashboard_nonce'),
                'coach_events_nonce' => wp_create_nonce('intersoccer_coach_events_nonce'),
                'user_id' => get_current_user_id(),
                'user_name' => wp_get_current_user()->display_name,
                'referral_link' => InterSoccer_Referral_Handler::generate_coach_referral_link(get_current_user_id()),
                'referral_code' => InterSoccer_Referral_Handler::get_coach_referral_code(get_current_user_id()),
                'admin_url' => admin_url(),
                'chart_data' => [
                    'labels' => $this->get_chart_labels(30),
                    'referrals' => $this->get_chart_data(get_current_user_id(), 30, 'referrals'),
                    'credits' => $this->get_chart_data(get_current_user_id(), 30, 'credits')
                ],
                'i18n' => function_exists('intersoccer_referral_get_dashboard_i18n') ? intersoccer_referral_get_dashboard_i18n() : []
            ]);
        }

        // Legacy coach dashboard assets (fallback)
        elseif (is_user_logged_in() && current_user_can('view_referral_dashboard') && !is_account_page()) {
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

        // Back-compat / shared object used by admin JS.
        wp_localize_script('intersoccer-admin-js', 'intersoccer_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('intersoccer_admin_nonce'),
            'i18n' => [
                'coach_events_select_event' => __('Please select an event before saving.', 'intersoccer-referral'),
                'coach_events_search_min_chars' => __('Enter at least two characters to search.', 'intersoccer-referral'),
                'coach_events_searching' => __('Searching…', 'intersoccer-referral'),
                'coach_events_no_events' => __('No events found.', 'intersoccer-referral'),
                'coach_events_search_failed' => __('Search failed. Please try again.', 'intersoccer-referral'),
                'coach_events_remove_confirm' => __('Remove this event assignment?', 'intersoccer-referral'),
                'coach_events_link_copied' => __('Link copied to clipboard.', 'intersoccer-referral'),
                'coach_events_press_ctrl_c' => __('Press Ctrl+C to copy the link', 'intersoccer-referral'),
                'coach_events_save_error' => __('Error saving event', 'intersoccer-referral'),
                'coach_events_save_network_error' => __('Network error while saving event', 'intersoccer-referral'),
                'coach_events_remove_error' => __('Error removing event', 'intersoccer-referral'),
                'coach_events_status_error' => __('Error updating status', 'intersoccer-referral'),
                'coach_events_status_network_error' => __('Network error updating status', 'intersoccer-referral'),
            ],
        ]);
    }

    public function debug_elementor_status() {
        intersoccer_referral_log('=== InterSoccer Elementor Debug ===');
        intersoccer_referral_log('Elementor Plugin class exists: ' . (class_exists('\Elementor\Plugin') ? 'YES' : 'NO'));
        
        if (class_exists('\Elementor\Plugin')) {
            intersoccer_referral_log('Elementor Plugin instance exists: ' . (isset(\Elementor\Plugin::$instance) ? 'YES' : 'NO'));
            intersoccer_referral_log('Elementor version: ' . (defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : 'NOT_DEFINED'));
        }
        
        intersoccer_referral_log('Available actions: ' . implode(', ', array_keys($GLOBALS['wp_filter'])));
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

    /**
     * Determine whether we should enqueue the customer dashboard-specific assets early (before wp_head).
     *
     * This covers:
     * - WooCommerce My Account endpoint rendering (referrals/parrainages/empfehlungen)
     * - Shortcode usage on regular pages
     * - Elementor widget usage (detected via page_has_elementor_widgets)
     *
     * @return bool
     */
    private function should_enqueue_customer_dashboard_assets() {
        if (!is_user_logged_in()) {
            return false;
        }

        // Woo My Account endpoint context
        if (function_exists('is_account_page') && is_account_page()) {
            $endpoint_slugs = ['referrals', 'parrainages', 'empfehlungen'];

            foreach ($endpoint_slugs as $slug) {
                if (get_query_var($slug, false) !== false) {
                    return true;
                }
            }
        }

        // Shortcode usage on standard pages
        global $post;
        if ($post && isset($post->post_content) && function_exists('has_shortcode')) {
            if (has_shortcode($post->post_content, 'intersoccer_customer_dashboard')) {
                return true;
            }
        }

        // Elementor widgets
        if (class_exists('\Elementor\Plugin') && $this->page_has_elementor_widgets()) {
            return true;
        }

        return false;
    }

    /**
     * Get chart labels for dashboard
     */
    private function get_chart_labels($days) {
        $labels = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $labels[] = date('M j', strtotime("-{$i} days"));
        }
        return $labels;
    }

    /**
     * Get chart data for dashboard
     */
    private function get_chart_data($coach_id, $days, $type) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';

        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $next_date = date('Y-m-d', strtotime("-" . ($i - 1) . " days"));

            if ($type === 'referrals') {
                $value = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE coach_id = %d AND DATE(created_at) = %s",
                    $coach_id, $date
                ));
            } else { // credits
                $value = $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(commission_amount), 0) FROM $table_name WHERE coach_id = %d AND DATE(created_at) = %s",
                    $coach_id, $date
                ));
            }

            $data[] = (float) $value;
        }

        return $data;
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

/**
 * Get coach tier badge HTML
 * 
 * @param int $coach_id Coach user ID (optional, defaults to current user)
 * @return string HTML badge element
 */
function intersoccer_get_coach_tier_badge($coach_id = null) {
    $tier = intersoccer_get_coach_tier($coach_id);
    $tier_lower = strtolower($tier);
    
    return '<span class="tier-badge tier-' . esc_attr($tier_lower) . '">' . esc_html($tier) . '</span>';
}

/**
 * Get all coaches
 * 
 * @param array $args Optional arguments for get_users()
 * @return array Array of WP_User objects with coach role
 */
function intersoccer_get_all_coaches($args = []) {
    $default_args = [
        'role' => 'coach',
        'orderby' => 'display_name',
        'order' => 'ASC',
        'number' => -1, // Get all
    ];
    
    $merged_args = array_merge($default_args, $args);
    return get_users($merged_args);
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
    
    intersoccer_referral_log('InterSoccer daily cleanup completed');
});

// Weekly reports
add_action('intersoccer_weekly_reports', function() {
    if (!get_option('intersoccer_enable_email_notifications', 1)) {
        return;
    }
    
    // Send weekly performance reports to coaches
    $coaches = get_users(['role' => 'coach']);
    foreach ($coaches as $coach) {
        intersoccer_send_weekly_report($coach->ID);
    }
    
    intersoccer_referral_log('InterSoccer weekly reports sent');
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
        intersoccer_referral_log("InterSoccer: Failed to update coach performance for coach $coach_id: " . $wpdb->last_error);
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

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('intersoccer sync-commissions', function($args, $assoc_args) {
        $manager = InterSoccer_Commission_Manager::get_instance();

        $order_id = isset($assoc_args['order']) ? absint($assoc_args['order']) : null;
        $limit = isset($assoc_args['limit']) ? max(1, (int) $assoc_args['limit']) : 100;
        $statuses = ['wc-completed', 'completed', 'wc-processing'];

        if (isset($assoc_args['status'])) {
            $status_arg = $assoc_args['status'];
            if (!is_array($status_arg)) {
                $status_arg = array_map('trim', explode(',', (string) $status_arg));
            }
            $filtered = array_filter(array_map('sanitize_key', $status_arg));
            if (!empty($filtered)) {
                $statuses = $filtered;
            }
        }

        $processed = $manager->sync_commissions([
            'order_id' => $order_id,
            'limit' => $limit,
            'statuses' => $statuses,
        ]);

        WP_CLI::success(sprintf('Processed %d order(s).', $processed));
    });
}