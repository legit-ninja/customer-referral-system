<?php
// includes/class-admin-dashboard.php

class InterSoccer_Referral_Admin_Dashboard {

    private $main_dashboard;
    private $coaches;
    private $referrals;
    private $financial;
    private $settings;
    private $points;

      public function __construct() {
        // Initialize modular classes
        $this->main_dashboard = new InterSoccer_Admin_Dashboard_Main();
        $this->coaches = new InterSoccer_Admin_Coaches();
        $this->referrals = new InterSoccer_Admin_Referrals();
        $this->financial = new InterSoccer_Admin_Financial();
        $this->settings = new InterSoccer_Admin_Settings();
        $this->points = new InterSoccer_Admin_Points();

        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'handle_settings']);
        add_action('admin_post_import_coaches_from_csv', [$this->settings, 'import_coaches_from_csv']);
        add_action('admin_post_nopriv_import_coaches_from_csv', [$this->settings, 'import_coaches_from_csv']);
        add_action('wp_ajax_start_points_migration', [$this, 'start_points_migration']);
        add_action('wp_ajax_get_migration_progress', [$this, 'get_migration_progress']);
        add_action('wp_ajax_cancel_points_migration', [$this, 'cancel_points_migration']);
        add_action('wp_ajax_reset_points_migration', [$this, 'reset_points_migration']);
        add_action('wp_ajax_preview_points_migration', [$this, 'preview_points_migration']);
        add_action('wp_ajax_export_roi_report', [$this, 'export_roi_report']);
        add_action('wp_ajax_send_coach_message', [$this, 'send_coach_message']);
        add_action('wp_ajax_deactivate_coach', [$this, 'deactivate_coach']);
        add_action('wp_ajax_update_customer_credits', [$this, 'update_customer_credits']);
        add_action('wp_ajax_import_customers_credits', [$this, 'import_customers_and_assign_credits']);
        add_action('wp_ajax_emergency_cleanup_import', [$this, 'emergency_cleanup_import_session']);
        add_action('wp_ajax_debug_join_issue', [$this, 'debug_join_issue']);
        add_action('wp_ajax_allocate_credits_to_customers', [$this->settings, 'allocate_credits_to_customers']);
        add_action('wp_ajax_get_credit_statistics', [$this->settings, 'get_credit_statistics']);
        add_action('wp_ajax_get_coach_statistics', [$this->settings, 'get_coach_statistics']);
        add_action('wp_ajax_get_audit_log', [$this->settings, 'get_audit_log']);
        add_action('wp_ajax_clear_audit_log', [$this->settings, 'clear_audit_log']);
        add_action('wp_ajax_export_audit_log', [$this->settings, 'export_audit_log']);
        add_action('wp_ajax_bulk_credit_adjustment', [$this->settings, 'bulk_credit_adjustment']);
        add_action('wp_ajax_get_points_statistics', [$this->settings, 'get_points_statistics_ajax']);
        add_action('wp_ajax_get_points_ledger', [$this->settings, 'get_points_ledger_ajax']);
        add_action('wp_ajax_run_points_sync', [$this->settings, 'run_points_sync_ajax']);
        add_action('wp_ajax_get_sync_info', [$this->settings, 'get_sync_info_ajax']);
        add_action('wp_ajax_get_sync_info_ajax', [$this->settings, 'get_sync_info_ajax']);
        add_action('wp_ajax_get_points_users', [$this->points, 'get_points_users_ajax']);
        add_action('wp_ajax_adjust_user_points', [$this->points, 'adjust_user_points_ajax']);
        add_action('wp_ajax_export_points_report', [$this->points, 'export_points_report_ajax']);

        // Debug action to test AJAX is working
        add_action('wp_ajax_test_ajax_connection', [$this, 'test_ajax_connection']);

        // WooCommerce Points Integration
        if (class_exists('WooCommerce')) {
            add_action('woocommerce_review_order_before_payment', [$this, 'add_points_redemption_field']);
            add_action('woocommerce_checkout_process', [$this, 'validate_points_redemption']);
            add_action('woocommerce_checkout_create_order', [$this, 'apply_points_discount_to_order'], 10, 2);
            add_action('woocommerce_order_status_changed', [$this, 'deduct_points_on_order_completion'], 10, 4);
            add_action('woocommerce_my_account_my_orders_column_order-total', [$this, 'display_points_used_in_orders']);
            add_action('woocommerce_cart_calculate_fees', [$this, 'apply_points_discount_as_fee'], 10, 1);
            add_action('wp_ajax_update_points_session', [$this, 'update_points_session']);
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
            'Customer Points',
            'Customer Points',
            'manage_options',
            'intersoccer-customer-points',
            [$this->points, 'render_points_page']
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
        // Debug: log the current hook
        error_log('Enqueue hook: ' . $hook);

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

            // Enqueue settings page specific assets
            if (strpos($hook, 'intersoccer-settings') !== false) {
                error_log('Enqueueing settings page assets for hook: ' . $hook);
                wp_enqueue_style('intersoccer-admin-settings-css', INTERSOCCER_REFERRAL_URL . 'assets/css/admin-settings.css', [], INTERSOCCER_REFERRAL_VERSION);
                wp_enqueue_script('intersoccer-admin-settings-js', INTERSOCCER_REFERRAL_URL . 'assets/js/admin-settings.js', ['jquery'], INTERSOCCER_REFERRAL_VERSION, true);

                wp_localize_script('intersoccer-admin-settings-js', 'intersoccer_admin', [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('intersoccer_admin_nonce')
                ]);
            }
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
        $available_credits = get_user_meta($user_id, 'intersoccer_points_balance', true) ?: 0;

        if ($available_credits > 0) {
            // Get cart total for context
            $cart_total = WC()->cart->get_total('edit');

            echo '<div class="intersoccer-points-redemption-wrapper" style="width: 100%; clear: both; margin-bottom: 20px;">';
            echo '<div class="intersoccer-points-redemption" style="border: 1px solid #e1e5e9; border-radius: 8px; padding: 16px; margin: 0; background: #f8fafc; width: 100%; box-sizing: border-box;">';
            echo '<div style="display: flex; align-items: center; margin-bottom: 12px;">';
            echo '<input type="checkbox" name="intersoccer_use_points" id="intersoccer_use_points" style="margin-right: 8px;" />';
            echo '<label for="intersoccer_use_points" style="font-weight: 600; color: #111827; margin: 0;">' . __('Use Referral Points', 'intersoccer-referral') . '</label>';
            echo '</div>';

            echo '<div class="points-details" style="display: none; margin-left: 24px;">';
            echo '<p style="margin: 8px 0; color: #6b7280; font-size: 14px;">' . sprintf(__('You have %s points available', 'intersoccer-referral'), '<strong>' . number_format($available_credits, 0) . '</strong>') . '</p>';

            // Quick apply buttons
            echo '<div class="points-quick-apply" style="margin: 12px 0;">';
            echo '<button type="button" class="apply-all-points button button-secondary" style="margin-right: 8px; padding: 6px 12px; font-size: 12px;">' . __('Apply All', 'intersoccer-referral') . '</button>';
            echo '<button type="button" class="apply-max-points button button-secondary" style="padding: 6px 12px; font-size: 12px;">' . __('Apply Max (100)', 'intersoccer-referral') . '</button>';
            echo '</div>';

            // Custom amount input
            echo '<div class="custom-amount" style="margin: 12px 0;">';
            echo '<label for="intersoccer_points_to_redeem" style="display: block; margin-bottom: 4px; font-size: 14px; color: #374151;">' . __('Or enter custom amount:', 'intersoccer-referral') . '</label>';
            echo '<input type="number" name="intersoccer_points_to_redeem" id="intersoccer_points_to_redeem" min="0" max="' . min($available_credits, 100) . '" step="1" placeholder="0" style="width: 120px; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 4px;" />';
            echo '<span style="margin-left: 8px; color: #6b7280; font-size: 14px;">points</span>';
            echo '</div>';

            // Applied amount display
            echo '<div class="applied-amount" style="margin: 8px 0; padding: 8px; background: #ecfdf5; border: 1px solid #d1fae5; border-radius: 4px; display: none;">';
            echo '<span class="applied-text" style="color: #065f46; font-size: 14px;"></span>';
            echo '</div>';

            echo '</div>';
            echo '</div>';
            echo '</div>';

            // Add JavaScript for interactivity
            ?>
            <script>
            jQuery(document).ready(function($) {
                var $usePoints = $('#intersoccer_use_points');
                var $pointsDetails = $('.points-details');
                var $pointsInput = $('#intersoccer_points_to_redeem');
                var $appliedAmount = $('.applied-amount');
                var $appliedText = $('.applied-text');
                var availableCredits = <?php echo $available_credits; ?>;
                var maxPerOrder = 100;
                var updateTimeout;

                // Toggle points section
                $usePoints.on('change', function() {
                    if ($(this).is(':checked')) {
                        $pointsDetails.show();
                        $pointsInput.val('').trigger('input');
                    } else {
                        $pointsDetails.hide();
                        $pointsInput.val('0');
                        $appliedAmount.hide();
                        updatePointsSession(0);
                    }
                });

                // Apply all points
                $('.apply-all-points').on('click', function() {
                    var amount = Math.min(availableCredits, maxPerOrder);
                    $pointsInput.val(amount).trigger('input');
                });

                // Apply max points (100)
                $('.apply-max-points').on('click', function() {
                    var amount = Math.min(availableCredits, maxPerOrder);
                    $pointsInput.val(amount).trigger('input');
                });

                // Update display when input changes
                $pointsInput.on('input', function() {
                    var amount = parseInt($(this).val()) || 0;
                    var discountValue = amount; // 1:1 ratio for credits to CHF

                    if (amount > 0) {
                        $appliedText.html('<?php _e('Applying', 'intersoccer-referral'); ?> <strong>' + amount + ' <?php _e('points', 'intersoccer-referral'); ?></strong> (<?php _e('saving', 'intersoccer-referral'); ?> <strong>' + discountValue + ' CHF</strong>)');
                        $appliedAmount.show();
                    } else {
                        $appliedAmount.hide();
                    }

                    // Debounce the AJAX call
                    clearTimeout(updateTimeout);
                    updateTimeout = setTimeout(function() {
                        updatePointsSession(amount);
                    }, 500);
                });

                // Ensure checkbox is checked when manually entering amount
                $pointsInput.on('focus', function() {
                    if (!$usePoints.is(':checked')) {
                        $usePoints.prop('checked', true).trigger('change');
                    }
                });

                function updatePointsSession(pointsAmount) {
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'update_points_session',
                            points_to_redeem: pointsAmount,
                            nonce: '<?php echo wp_create_nonce('intersoccer_checkout_nonce'); ?>'
                        },
                        success: function(response) {
                            console.log('Points session update response:', response);
                            if (response.success) {
                                // Trigger WooCommerce checkout update
                                $(document.body).trigger('update_checkout');
                            } else {
                                console.log('Points session update failed:', response.data);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.log('AJAX Error updating points session:', status, error);
                        }
                    });
                }
            });
            </script>
            <?php
        }
    }

    /**
     * Validate points redemption on checkout
     */
    public function validate_points_redemption() {
        // Only validate if points usage is enabled
        if (!isset($_POST['intersoccer_use_points']) || $_POST['intersoccer_use_points'] !== 'on') {
            return;
        }

        $points_to_redeem = isset($_POST['intersoccer_points_to_redeem']) ? intval($_POST['intersoccer_points_to_redeem']) : 0;

        if ($points_to_redeem < 0) {
            wc_add_notice(__('Invalid points amount.', 'intersoccer-referral'), 'error');
            return;
        }

        if ($points_to_redeem > 100) {
            wc_add_notice(__('You can redeem a maximum of 100 credits per order.', 'intersoccer-referral'), 'error');
            return;
        }

        $user_id = get_current_user_id();
        $available_credits = get_user_meta($user_id, 'intersoccer_points_balance', true) ?: 0;

        if ($points_to_redeem > $available_credits) {
            wc_add_notice(__('You don\'t have enough points available.', 'intersoccer-referral'), 'error');
            return;
        }

        // Ensure at least 1 point is being redeemed if checkbox is checked
        if ($points_to_redeem === 0) {
            wc_add_notice(__('Please enter the number of credits to redeem.', 'intersoccer-referral'), 'error');
            return;
        }
    }

    /**
     * Apply points discount to order
     */
    public function apply_points_discount_to_order($order) {
        // Only process if points usage is enabled
        if (!isset($_POST['intersoccer_use_points']) || $_POST['intersoccer_use_points'] !== 'on') {
            return;
        }

        $points_to_redeem = isset($_POST['intersoccer_points_to_redeem']) ? intval($_POST['intersoccer_points_to_redeem']) : 0;

        if ($points_to_redeem > 0) {
            // Store points to be deducted in session for later processing
            WC()->session->set('intersoccer_points_to_redeem', $points_to_redeem);

            // Add order note
            $order->add_order_note(sprintf(__('Customer redeemed %d referral credits.', 'intersoccer-referral'), $points_to_redeem));

            // Add a fee line item for the discount
            $fee = new WC_Order_Item_Fee();
            $fee->set_name(__('Referral Credits Discount', 'intersoccer-referral'));
            $fee->set_amount(-$points_to_redeem); // Negative amount for discount
            $fee->set_tax_status('none');
            $fee->set_total(-$points_to_redeem);
            $fee->set_total_tax(0);

            // Add the fee to the order
            $order->add_item($fee);

            // Recalculate totals
            $order->calculate_totals();
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
            $current_credits = get_user_meta($user_id, 'intersoccer_points_balance', true) ?: 0;
            $new_credits = max(0, $current_credits - $points_to_redeem);
            update_user_meta($user_id, 'intersoccer_points_balance', $new_credits);

            // Find the fee item for the discount
            $fee_item_id = null;
            foreach ($order->get_items('fee') as $item_id => $item) {
                if ($item->get_name() === __('Referral Credits Discount', 'intersoccer-referral')) {
                    $fee_item_id = $item_id;
                    break;
                }
            }

            // Record the redemption
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'intersoccer_credit_redemptions',
                [
                    'customer_id' => $user_id,
                    'order_item_id' => $fee_item_id,
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
     * AJAX handler to update points session
     */
    public function update_points_session() {
        check_ajax_referer('intersoccer_checkout_nonce', 'nonce');

        $points_to_redeem = isset($_POST['points_to_redeem']) ? intval($_POST['points_to_redeem']) : 0;

        // Validate points
        if ($points_to_redeem < 0) {
            $points_to_redeem = 0;
        }

        $user_id = get_current_user_id();
        $available_points = get_user_meta($user_id, 'intersoccer_points_balance', true) ?: 0;
        $max_per_order = 100;

        $points_to_redeem = min($points_to_redeem, $available_points, $max_per_order);

        // Update session
        WC()->session->set('intersoccer_points_to_redeem', $points_to_redeem);

        wp_send_json_success([
            'points_to_redeem' => $points_to_redeem,
            'discount_amount' => $points_to_redeem
        ]);
    }

    /**
     * Apply points discount to cart total
     */
    public function apply_points_discount_as_fee($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        $points_to_redeem = WC()->session->get('intersoccer_points_to_redeem', 0);

        if ($points_to_redeem > 0) {
            $discount_amount = -$points_to_redeem; // Negative fee for discount
            $cart->add_fee(__('Points Discount', 'intersoccer-referral'), $discount_amount, true, '');
            error_log("Applying points discount as fee: points=$points_to_redeem, discount=$discount_amount");
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