<?php
/**
 * Plugin Name: InterSoccer Referral System
 * Plugin URI: https://intersoccer.ch
 * Description: Coach referral program for recruiting and retaining participants.
 * Version: 0.1.0
 * Author: Legit Ninja
 * Author URI: https://github.com/legit-ninja
 * License: GPL-2.0+
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('INTERSOCCER_REFERRAL_VERSION', '0.1.0');
define('INTERSOCCER_REFERRAL_PATH', plugin_dir_path(__FILE__));
define('INTERSOCCER_REFERRAL_URL', plugin_dir_url(__FILE__));

// Include necessary files
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-referral-handler.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-commission-calculator.php';
require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-dashboard.php';

// Activation hook: Create database table
function intersoccer_referral_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'intersoccer_referrals';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coach_id INT,
        customer_id INT,
        order_id INT,
        commission_amount DECIMAL(10,2),
        status VARCHAR(20),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Add capabilities to coach role if needed
    $coach_role = get_role('coach');
    if ($coach_role) {
        $coach_role->add_cap('view_referral_dashboard');
    }
}
register_activation_hook(__FILE__, 'intersoccer_referral_activate');

// Initialize classes
function intersoccer_referral_init() {
    new InterSoccer_Referral_Handler();
    new InterSoccer_Commission_Calculator();
    new InterSoccer_Dashboard();
}
add_action('plugins_loaded', 'intersoccer_referral_init');

// Enqueue assets
function intersoccer_referral_enqueue_assets() {
    if (is_user_logged_in() && current_user_can('view_referral_dashboard')) {
        wp_enqueue_style('intersoccer-dashboard-css', INTERSOCCER_REFERRAL_URL . 'assets/css/dashboard.css', [], INTERSOCCER_REFERRAL_VERSION);
        wp_enqueue_script('intersoccer-dashboard-js', INTERSOCCER_REFERRAL_URL . 'assets/js/dashboard.js', ['jquery'], INTERSOCCER_REFERRAL_VERSION, true);
    }
}
add_action('wp_enqueue_scripts', 'intersoccer_referral_enqueue_assets');

// Settings page (basic)
function intersoccer_referral_settings_page() {
    add_menu_page(
        'Referral Settings',
        'Referrals',
        'manage_options',
        'intersoccer-referrals',
        'intersoccer_referral_settings_callback',
        'dashicons-money-alt',
        100
    );
}
add_action('admin_menu', 'intersoccer_referral_settings_page');

function intersoccer_referral_settings_callback() {
    echo '<div class="wrap"><h1>InterSoccer Referral Settings</h1><p>Basic settings placeholder.</p></div>';
}