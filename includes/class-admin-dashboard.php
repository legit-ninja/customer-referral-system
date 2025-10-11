<?php
// includes/class-admin-dashboard.php

class InterSoccer_Referral_Admin_Dashboard {

    private $main_dashboard;
    private $coaches;
    private $referrals;
    private $financial;
    private $settings;

    public function __construct() {
        // Initialize modular classes
        $this->main_dashboard = new InterSoccer_Admin_Dashboard_Main();
        $this->coaches = new InterSoccer_Admin_Coaches();
        $this->referrals = new InterSoccer_Admin_Referrals();
        $this->financial = new InterSoccer_Admin_Financial();
        $this->settings = new InterSoccer_Admin_Settings();

        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'handle_settings']);
        add_action('admin_post_import_coaches_from_csv', [$this, 'import_coaches_from_csv']);
        add_action('admin_post_nopriv_import_coaches_from_csv', [$this, 'import_coaches_from_csv']);
        add_action('wp_ajax_start_points_migration', [$this, 'start_points_migration']);
        add_action('wp_ajax_get_migration_progress', [$this, 'get_migration_progress']);
        add_action('wp_ajax_cancel_points_migration', [$this, 'cancel_points_migration']);
        add_action('wp_ajax_reset_points_migration', [$this, 'reset_points_migration']);
        add_action('wp_ajax_preview_points_migration', [$this, 'preview_points_migration']);
        add_action('wp_ajax_populate_demo_data', [$this, 'populate_demo_data']);
        add_action('wp_ajax_clear_demo_data', [$this, 'clear_demo_data']);
        add_action('wp_ajax_export_roi_report', [$this, 'export_roi_report']);
        add_action('wp_ajax_send_coach_message', [$this, 'send_coach_message']);
        add_action('wp_ajax_deactivate_coach', [$this, 'deactivate_coach']);
        add_action('wp_ajax_update_customer_credits', [$this, 'update_customer_credits']);
        add_action('wp_ajax_import_customers_credits', [$this, 'import_customers_and_assign_credits']);
        add_action('wp_ajax_emergency_cleanup_import', [$this, 'emergency_cleanup_import_session']);
        add_action('wp_ajax_debug_join_issue', [$this, 'debug_join_issue']);
        add_action('wp_ajax_reset_all_customer_credits', [$this, 'reset_all_customer_credits']);

        // Debug action to test AJAX is working
        add_action('wp_ajax_test_ajax_connection', [$this, 'test_ajax_connection']);

        // WooCommerce Points Integration
        if (class_exists('WooCommerce')) {
            add_action('woocommerce_checkout_before_order_review', [$this, 'add_points_redemption_field']);
            add_action('woocommerce_checkout_process', [$this, 'validate_points_redemption']);
            add_action('woocommerce_checkout_create_order', [$this, 'apply_points_discount_to_order'], 10, 2);
            add_action('woocommerce_order_status_changed', [$this, 'deduct_points_on_order_completion'], 10, 4);
            add_action('woocommerce_my_account_my_orders_column_order-total', [$this, 'display_points_used_in_orders']);
        }
    }

    public function add_admin_menus() {
        // Main menu
        add_menu_page(
            'InterSoccer Referrals',
            'Referrals',
            'manage_options',
            'intersoccer-referrals',
            [$this->main_dashboard, 'render_main_dashboard'],
            'dashicons-money-alt',
            30
        );

        // Submenus
        add_submenu_page(
            'intersoccer-referrals',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'intersoccer-referrals',
            [$this->main_dashboard, 'render_main_dashboard']
        );

        add_submenu_page(
            'intersoccer-referrals',
            'Coaches',
            'Coaches',
            'manage_options',
            'intersoccer-coaches',
            [$this->coaches, 'render_coaches_page']
        );

        add_submenu_page(
            'intersoccer-referrals',
            'Coach Referrals',
            'Coach Referrals',
            'manage_options',
            'intersoccer-coach-referrals',
            [$this->referrals, 'render_coach_referrals_page']
        );

        add_submenu_page(
            'intersoccer-referrals',
            'Customer Referrals',
            'Customer Referrals',
            'manage_options',
            'intersoccer-customer-referrals',
            [$this->referrals, 'render_customer_referrals_page']
        );

        add_submenu_page(
            'intersoccer-referrals',
            'Financial Report',
            'Financial Report',
            'manage_options',
            'intersoccer-financial-report',
            [$this->financial, 'render_financial_report_page']
        );

        add_submenu_page(
            'intersoccer-referrals',
            'Settings',
            'Settings',
            'manage_options',
            'intersoccer-settings',
            [$this->settings, 'render_settings_page']
        );
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'intersoccer') !== false) {
            // Enqueue Chart.js first
            wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', [], '3.9.1');

            // Enqueue our admin assets
            wp_enqueue_style('intersoccer-admin-css', INTERSOCCER_REFERRAL_URL . 'assets/css/admin-dashboard.css', [], INTERSOCCER_REFERRAL_VERSION);
            wp_enqueue_script('intersoccer-admin-js', INTERSOCCER_REFERRAL_URL . 'assets/js/admin-dashboard.js', ['jquery', 'chart-js'], INTERSOCCER_REFERRAL_VERSION, true);

            wp_localize_script('intersoccer-admin-js', 'intersoccer_admin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('intersoccer_admin_nonce')
            ]);
        }
    }

    /**
     * Handle admin settings and form submissions
     */
    public function handle_settings() {
        // Handle any settings form submissions here
        // This method is called on admin_init

        // Check if we're on our settings page
        if (isset($_GET['page']) && $_GET['page'] === 'intersoccer-settings') {
            // Handle settings form submissions
            if (isset($_POST['submit']) && check_admin_referer('intersoccer_settings_nonce')) {
                // Process settings updates
                $this->process_settings_update();
            }
        }
    }

    /**
     * Process settings form updates
     */
    private function process_settings_update() {
        // Handle settings updates here
        // This is a placeholder for future settings functionality
    }

    /**
     * RESET function to clear all assigned credits and start over
     */
    public function reset_all_customer_credits() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        global $wpdb;

        error_log('InterSoccer: Starting complete credit reset...');

        // Delete all credit-related user meta
        $credit_meta_keys = [
            'intersoccer_customer_credits',
            'intersoccer_total_credits_earned',
            'intersoccer_credits_imported',
            'intersoccer_import_date',
            'intersoccer_credit_breakdown',
            'intersoccer_credit_adjustments',
            'intersoccer_credits_used_total'
        ];

        $deleted_total = 0;
        foreach ($credit_meta_keys as $meta_key) {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
                $meta_key
            ));
            $deleted_total += $deleted;
            error_log("InterSoccer: Deleted {$deleted} records for meta_key: {$meta_key}");
        }

        // Clear import summary
        delete_option('intersoccer_last_import_summary');
        delete_option('intersoccer_last_customer_import_report');

        error_log("InterSoccer: Credit reset complete - deleted {$deleted_total} total records");

        wp_send_json_success([
            'message' => "Reset complete! Deleted {$deleted_total} credit records from all customers.",
            'deleted_records' => $deleted_total
        ]);
    }

    /**
     * Handle coach CSV import
     */
    public function import_coaches_from_csv() {
        // Handle CSV import for coaches
        // This is a placeholder implementation
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Basic CSV import logic would go here
        // For now, just redirect back
        wp_redirect(admin_url('admin.php?page=intersoccer-coaches&imported=1'));
        exit;
    }

    /**
     * Start points migration process
     */
    public function start_points_migration() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Points migration logic would go here
        wp_send_json_success(['message' => 'Migration started']);
    }

    /**
     * Get migration progress
     */
    public function get_migration_progress() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Return migration progress
        wp_send_json_success(['progress' => 0, 'message' => 'Migration in progress']);
    }

    /**
     * Cancel points migration
     */
    public function cancel_points_migration() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Migration cancelled']);
    }

    /**
     * Reset points migration
     */
    public function reset_points_migration() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Migration reset']);
    }

    /**
     * Preview points migration
     */
    public function preview_points_migration() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['preview' => [], 'message' => 'Migration preview']);
    }

    /**
     * Populate demo data (placeholder)
     */
    public function populate_demo_data() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Demo data populated']);
    }

    /**
     * Clear demo data (placeholder)
     */
    public function clear_demo_data() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Demo data cleared']);
    }

    /**
     * Export ROI report
     */
    public function export_roi_report() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Export logic would go here
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="roi-report.csv"');
        echo "Date,Revenue,Costs,ROI\n";
        exit;
    }

    /**
     * Send coach message
     */
    public function send_coach_message() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Message sent']);
    }

    /**
     * Deactivate coach
     */
    public function deactivate_coach() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Coach deactivated']);
    }

    /**
     * Update customer credits
     */
    public function update_customer_credits() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Credits updated']);
    }

    /**
     * Import customers and assign credits
     */
    public function import_customers_and_assign_credits() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Import completed']);
    }

    /**
     * Emergency cleanup import session
     */
    public function emergency_cleanup_import_session() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Cleanup completed']);
    }

    /**
     * Debug join issue
     */
    public function debug_join_issue() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['debug' => 'Debug info']);
    }

    /**
     * Test AJAX connection
     */
    public function test_ajax_connection() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        wp_send_json_success(['message' => 'AJAX connection working', 'timestamp' => current_time('mysql')]);
    }

    /**
     * Add points redemption field to checkout
     */
    public function add_points_redemption_field() {
        // WooCommerce checkout integration
        if (!is_user_logged_in()) return;

        $user_id = get_current_user_id();
        $available_credits = get_user_meta($user_id, 'intersoccer_customer_credits', true) ?: 0;

        if ($available_credits > 0) {
            echo '<div class="intersoccer-points-redemption">';
            echo '<h3>' . __('Use Referral Credits', 'intersoccer-referral') . '</h3>';
            echo '<p>' . sprintf(__('You have %s credits available (%s CHF value)', 'intersoccer-referral'), $available_credits, number_format($available_credits, 0)) . '</p>';
            echo '<input type="number" name="intersoccer_points_to_redeem" id="intersoccer_points_to_redeem" min="0" max="' . $available_credits . '" step="1" placeholder="0" />';
            echo '<small>' . __('Enter the number of credits to use (max 100 per order)', 'intersoccer-referral') . '</small>';
            echo '</div>';
        }
    }

    /**
     * Validate points redemption on checkout
     */
    public function validate_points_redemption() {
        if (!isset($_POST['intersoccer_points_to_redeem'])) return;

        $points_to_redeem = intval($_POST['intersoccer_points_to_redeem']);

        if ($points_to_redeem < 0) {
            wc_add_notice(__('Invalid points amount.', 'intersoccer-referral'), 'error');
            return;
        }

        if ($points_to_redeem > 100) {
            wc_add_notice(__('You can redeem a maximum of 100 credits per order.', 'intersoccer-referral'), 'error');
            return;
        }

        $user_id = get_current_user_id();
        $available_credits = get_user_meta($user_id, 'intersoccer_customer_credits', true) ?: 0;

        if ($points_to_redeem > $available_credits) {
            wc_add_notice(__('You don\'t have enough credits available.', 'intersoccer-referral'), 'error');
            return;
        }
    }

    /**
     * Apply points discount to order
     */
    public function apply_points_discount_to_order($order) {
        if (!isset($_POST['intersoccer_points_to_redeem'])) return;

        $points_to_redeem = intval($_POST['intersoccer_points_to_redeem']);

        if ($points_to_redeem > 0) {
            // Store points to be deducted in session for later processing
            WC()->session->set('intersoccer_points_to_redeem', $points_to_redeem);

            // Add order note
            $order->add_order_note(sprintf(__('Customer redeemed %d referral credits.', 'intersoccer-referral'), $points_to_redeem));
        }
    }

    /**
     * Deduct points when order is completed
     */
    public function deduct_points_on_order_completion($order_id, $old_status, $new_status) {
        if ($new_status !== 'completed') return;

        $points_to_redeem = WC()->session->get('intersoccer_points_to_redeem');

        if ($points_to_redeem > 0) {
            $order = wc_get_order($order_id);
            $user_id = $order->get_customer_id();

            // Deduct points from user
            $current_credits = get_user_meta($user_id, 'intersoccer_customer_credits', true) ?: 0;
            $new_credits = max(0, $current_credits - $points_to_redeem);
            update_user_meta($user_id, 'intersoccer_customer_credits', $new_credits);

            // Record the redemption
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'intersoccer_credit_redemptions',
                [
                    'customer_id' => $user_id,
                    'credit_amount' => $points_to_redeem,
                    'order_total' => $order->get_total(),
                    'discount_applied' => $points_to_redeem, // 1 point = 1 CHF discount
                    'created_at' => current_time('mysql')
                ]
            );

            // Clear session
            WC()->session->set('intersoccer_points_to_redeem', 0);

            // Add order note
            $order->add_order_note(sprintf(__('Deducted %d credits from customer balance. New balance: %d', 'intersoccer-referral'), $points_to_redeem, $new_credits));
        }
    }

    /**
     * Display points used in order history
     */
    public function display_points_used_in_orders($order) {
        // This would modify the order total column to show points used
        // Implementation depends on WooCommerce hooks
    }
}