<?php
// includes/class-admin-dashboard.php

class InterSoccer_Referral_Admin_Dashboard {

    private $main_dashboard;
    private $coaches;
    private $referrals;
    private $financial;
    private $settings;
    private $points;
    private $coach_assignments;

      public function __construct() {
        // Initialize modular classes
        $this->main_dashboard = new InterSoccer_Admin_Dashboard_Main();
        $this->coaches = new InterSoccer_Admin_Coaches();
        $this->referrals = new InterSoccer_Admin_Referrals();
        $this->financial = new InterSoccer_Admin_Financial();
        $this->settings = new InterSoccer_Admin_Settings();
        $this->points = new InterSoccer_Admin_Points();

        // Initialize coach assignments if class exists
        if (class_exists('InterSoccer_Admin_Coach_Assignments')) {
            $this->coach_assignments = new InterSoccer_Admin_Coach_Assignments();
        } else {
            $this->coach_assignments = null;
        }

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
            add_action('woocommerce_review_order_before_payment', [$this, 'add_referral_code_field']);
            add_action('woocommerce_review_order_before_payment', [$this, 'add_points_redemption_field']);
            add_action('woocommerce_checkout_process', [$this, 'validate_points_redemption']);
            // add_action('woocommerce_checkout_create_order', [$this, 'apply_points_discount_to_order'], 10, 2); // Disabled - cart fees are automatically converted to order items
            add_action('woocommerce_order_status_changed', [$this, 'deduct_points_on_order_completion'], 10, 4);
            add_action('woocommerce_my_account_my_orders_column_order-total', [$this, 'display_points_used_in_orders']);
            add_action('woocommerce_cart_calculate_fees', [$this, 'apply_points_discount_as_fee'], 10, 1);
            add_action('wp_ajax_update_points_session', [$this, 'update_points_session']);
            add_action('wp_ajax_apply_referral_code', [$this, 'apply_referral_code_ajax']);
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

        // Only add coach assignments menu if class is available
        if ($this->coach_assignments) {
            add_submenu_page(
                'intersoccer-referrals',
                'Coach Assignments',
                'Coach Assignments',
                'manage_options',
                'intersoccer-coach-assignments',
                [$this->coach_assignments, 'render_page']
            );
        }
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
    public function add_referral_code_field() {
        // Add referral code input field before order review
        if (!is_user_logged_in()) return;

        echo '<div class="intersoccer-referral-code-wrapper" style="width: 100%; clear: both; margin-bottom: 20px;">';
        echo '<div class="intersoccer-referral-code" style="border: 1px solid #e1e5e9; border-radius: 8px; padding: 16px; margin: 0; background: #f0f9ff; width: 100%; box-sizing: border-box;">';
        echo '<div style="margin-bottom: 12px;">';
        echo '<label for="intersoccer_referral_code" style="font-weight: 600; color: #111827; margin: 0; display: block; margin-bottom: 8px;">' . __('Coach Referral Code (Optional)', 'intersoccer-referral') . '</label>';
        echo '<p style="margin: 0 0 12px 0; color: #6b7280; font-size: 14px;">' . __('Support your favorite coach! Enter their referral code to give them credit for this purchase.', 'intersoccer-referral') . '</p>';
        echo '<input type="text" name="intersoccer_referral_code" id="intersoccer_referral_code" placeholder="Enter referral code" style="width: 100%; max-width: 300px; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 14px;" />';
        echo '<button type="button" id="apply_referral_code" class="button button-secondary" style="margin-left: 8px; padding: 8px 16px;">' . __('Apply Code', 'intersoccer-referral') . '</button>';
        echo '</div>';
        echo '<div id="referral_code_message" style="display: none; margin-top: 8px; padding: 8px; border-radius: 4px; font-size: 14px;"></div>';
        echo '</div>';
        echo '</div>';
    }

    public function add_points_redemption_field() {
        // WooCommerce checkout integration
        if (!is_user_logged_in()) return;

        $user_id = get_current_user_id();
        $available_credits = get_user_meta($user_id, 'intersoccer_points_balance', true) ?: 0;
        error_log("Checkout points field - User: $user_id, Available credits: $available_credits");

        if ($available_credits > 0) {
            // Get cart total for context
            $cart_total = WC()->cart->get_total('edit');

            echo '<div class="intersoccer-points-redemption-wrapper" style="width: 100%; clear: both; margin-bottom: 20px;">';
            echo '<div class="intersoccer-points-redemption" style="border: 1px solid #e1e5e9; border-radius: 8px; padding: 16px; margin: 0; background: #f8fafc; width: 100%; box-sizing: border-box;">';
            echo '<div style="display: flex; align-items: center; margin-bottom: 12px;">';
            echo '<input type="checkbox" name="intersoccer_use_points" id="intersoccer_use_points" style="margin-right: 8px;" />';
            echo '<label for="intersoccer_use_points" style="font-weight: 600; color: #111827; margin: 0;">' . __('Use Loyalty Points', 'intersoccer-referral') . '</label>';
            echo '</div>';

            echo '<div class="points-details" style="display: none; margin-left: 24px;">';
            echo '<p style="margin: 8px 0; color: #6b7280; font-size: 14px;">' . sprintf(__('You have %s points available', 'intersoccer-referral'), '<strong>' . number_format($available_credits, 0) . '</strong>') . '</p>';

            // Quick apply buttons
            echo '<div class="points-quick-apply" style="margin: 12px 0;">';
            echo '<button type="button" class="apply-all-points button button-secondary" style="margin-right: 8px; padding: 6px 12px; font-size: 12px;">' . __('Apply All Available', 'intersoccer-referral') . '</button>';
            echo '</div>';

            // Custom amount input
            echo '<div class="custom-amount" style="margin: 12px 0;">';
            echo '<label for="intersoccer_points_to_redeem" style="display: block; margin-bottom: 4px; font-size: 14px; color: #374151;">' . __('Or enter custom amount:', 'intersoccer-referral') . '</label>';
            echo '<input type="number" name="intersoccer_points_to_redeem" id="intersoccer_points_to_redeem" min="0" max="' . $available_credits . '" step="1" placeholder="0" style="width: 120px; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 4px;" />';
            echo '<span style="margin-left: 8px; color: #6b7280; font-size: 14px;">points</span>';
            echo '</div>';
            
            // Help text explaining the limit
            echo '<p style="margin: 8px 0; color: #6b7280; font-size: 12px; font-style: italic;">' . __('You can redeem up to your cart total or available points, whichever is less.', 'intersoccer-referral') . '</p>';

            // Applied amount display
            echo '<div class="applied-amount" style="margin: 8px 0; padding: 8px; background: #ecfdf5; border: 1px solid #d1fae5; border-radius: 4px; display: none;">';
            echo '<span class="applied-text" style="color: #065f46; font-size: 14px;"></span>';
            echo '</div>';

            echo '</div>';
            echo '</div>';
            echo '</div>';

            // Add JavaScript for referral code and points redemption
            ?>
            <script>
            jQuery(document).ready(function($) {
                console.log('InterSoccer checkout JavaScript loaded');

                // Function to initialize points redemption handlers
                function initPointsRedemptionHandlers() {
                    console.log('Initializing points redemption handlers');

                    // Handle referral code application
                    $(document).off('click', '#apply_referral_code').on('click', '#apply_referral_code', function() {
                    var referralCode = $('#intersoccer_referral_code').val().trim();
                    var $message = $('#referral_code_message');
                    var $button = $(this);

                    if (!referralCode) {
                        $message.removeClass('success').addClass('error').html('<?php _e('Please enter a referral code', 'intersoccer-referral'); ?>').show();
                        return;
                    }

                    $button.prop('disabled', true).text('<?php _e('Applying...', 'intersoccer-referral'); ?>');

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'apply_referral_code',
                            referral_code: referralCode,
                            nonce: '<?php echo wp_create_nonce('intersoccer_checkout_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $message.removeClass('error').addClass('success').html(response.data.message).show();
                                $('#intersoccer_referral_code').prop('disabled', true);
                                $button.prop('disabled', true).text('<?php _e('Applied', 'intersoccer-referral'); ?>');
                                // Trigger checkout update to show discount
                                $(document.body).trigger('update_checkout');
                            } else {
                                $message.removeClass('success').addClass('error').html(response.data.message).show();
                                $button.prop('disabled', false).text('<?php _e('Apply Code', 'intersoccer-referral'); ?>');
                            }
                        },
                        error: function() {
                            $message.removeClass('success').addClass('error').html('<?php _e('Error applying referral code', 'intersoccer-referral'); ?>').show();
                            $button.prop('disabled', false).text('<?php _e('Apply Code', 'intersoccer-referral'); ?>');
                        }
                    });
                });

                    // Handle points redemption checkbox
                    $(document).off('change', '#intersoccer_use_points').on('change', '#intersoccer_use_points', function() {
                        console.log('Points checkbox changed:', $(this).is(':checked'));
                        var $pointsDetails = $(this).closest('.intersoccer-points-redemption').find('.points-details');
                        if ($(this).is(':checked')) {
                            $pointsDetails.slideDown();
                        } else {
                            $pointsDetails.slideUp();
                            // Clear any applied points
                            applyPointsAmount(0);
                        }
                    });

                    // Handle apply all points button
                    $(document).off('click', '.apply-all-points').on('click', '.apply-all-points', function() {
                        var availablePoints = <?php echo $available_credits; ?>;
                        applyPointsAmount(availablePoints);
                    });

                    // Handle custom amount input
                    $(document).off('input', '#intersoccer_points_to_redeem').on('input', '#intersoccer_points_to_redeem', function() {
                        var customAmount = parseInt($(this).val()) || 0;
                        applyPointsAmount(customAmount);
                    });
                }

                // Initialize handlers on page load
                initPointsRedemptionHandlers();

                // Re-initialize handlers after WooCommerce AJAX updates
                $(document.body).on('updated_checkout', function() {
                    console.log('Checkout updated, re-initializing handlers');
                    initPointsRedemptionHandlers();
                });

                // Function to apply points amount
                function applyPointsAmount(pointsAmount) {
                    console.log('Applying points amount:', pointsAmount);
                    var availablePoints = <?php echo $available_credits; ?>;

                    // Validate points amount (no 100-point limit, only available points limit)
                    if (pointsAmount < 0) pointsAmount = 0;
                    if (pointsAmount > availablePoints) pointsAmount = availablePoints;

                    console.log('Validated points amount:', pointsAmount, 'max allowed:', availablePoints);

                    // Update input field
                    $('#intersoccer_points_to_redeem').val(pointsAmount);

                    // Show applied amount
                    var $appliedAmount = $('.applied-amount');
                    var $appliedText = $('.applied-text');

                    if (pointsAmount > 0) {
                        $appliedText.text('<?php _e('Applied', 'intersoccer-referral'); ?> ' + pointsAmount + ' <?php _e('points discount', 'intersoccer-referral'); ?>');
                        $appliedAmount.show();
                    } else {
                        $appliedAmount.hide();
                    }

                    // Update session via AJAX
                    console.log('Making AJAX call to update points session');
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'update_points_session',
                            points_to_redeem: pointsAmount,
                            nonce: '<?php echo wp_create_nonce('intersoccer_checkout_nonce'); ?>'
                        },
                        success: function(response) {
                            console.log('AJAX success:', response);
                            if (response.success) {
                                // Trigger checkout update to recalculate totals
                                $(document.body).trigger('update_checkout');
                            } else {
                                console.error('Error updating points session:', response.data);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX error updating points session:', error, xhr.responseText);
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

        $user_id = get_current_user_id();
        $available_credits = get_user_meta($user_id, 'intersoccer_points_balance', true) ?: 0;

        if ($points_to_redeem > $available_credits) {
            wc_add_notice(__('You don\'t have enough points available.', 'intersoccer-referral'), 'error');
            return;
        }

        // Validate against cart total (not against arbitrary 100-point limit)
        $cart_total = WC()->cart->get_total('edit');
        if ($points_to_redeem > $cart_total) {
            wc_add_notice(__('Points redemption cannot exceed your cart total.', 'intersoccer-referral'), 'error');
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

        $order = wc_get_order($order_id);
        $points_to_redeem = WC()->session->get('intersoccer_points_to_redeem');

        if ($points_to_redeem > 0) {
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

        // Award points to coach for every purchase (CHF 10 spent = 1 point)
        $this->award_purchase_points_to_coach($order);

        // Award points to coach for referral code usage (one-time bonus)
        $referral_code = WC()->session->get('intersoccer_applied_referral_code');
        $referral_coach_id = WC()->session->get('intersoccer_referral_coach_id');
        error_log("Checking referral bonus: code=$referral_code, coach_id=$referral_coach_id");

        if ($referral_code && $referral_coach_id) {
            // Check if this is the customer's first completed order
            $customer_orders = wc_get_orders([
                'customer_id' => $order->get_customer_id(),
                'status' => 'completed',
                'limit' => 1
            ]);
            error_log("Customer completed orders count: " . count($customer_orders));

            // If this is their first completed order, award bonus points to coach
            if (count($customer_orders) === 1 && $customer_orders[0]->get_id() === $order_id) {
                error_log("Awarding referral bonus to coach $referral_coach_id");
                $points_to_award = 50; // Award 50 bonus points to coach for successful referral

                // Get current coach points balance
                $current_coach_points = get_user_meta($referral_coach_id, 'intersoccer_points_balance', true) ?: 0;
                $new_coach_points = $current_coach_points + $points_to_award;
                update_user_meta($referral_coach_id, 'intersoccer_points_balance', $new_coach_points);

                // Record the referral reward
                global $wpdb;
                $wpdb->insert(
                    $wpdb->prefix . 'intersoccer_referral_rewards',
                    [
                        'coach_id' => $referral_coach_id,
                        'customer_id' => $order->get_customer_id(),
                        'order_id' => $order_id,
                        'referral_code' => $referral_code,
                        'points_awarded' => $points_to_award,
                        'created_at' => current_time('mysql')
                    ]
                );

                // Add order note
                $coach_info = get_userdata($referral_coach_id);
                $order->add_order_note(sprintf(__('Awarded %d bonus points to coach %s for referral code usage. New balance: %d', 'intersoccer-referral'),
                    $points_to_award, $coach_info->display_name, $new_coach_points));

                // Clear referral session data
                WC()->session->set('intersoccer_applied_referral_code', null);
                WC()->session->set('intersoccer_referral_coach_id', null);
            }
        }
    }

    /**
     * Award points to coach for customer purchases (CHF 10 spent = 1 point)
     */
    private function award_purchase_points_to_coach($order) {
        $customer_id = $order->get_customer_id();
        error_log("Awarding purchase points: customer_id=$customer_id, order_id=" . $order->get_id());

        // Get the customer's preferred coach
        $coach_id = get_user_meta($customer_id, 'intersoccer_preferred_coach', true);
        error_log("Coach ID for customer $customer_id: $coach_id");

        if (!$coach_id) {
            error_log("No coach linked to customer $customer_id");
            return; // No linked coach
        }

        // Calculate points to award: CHF 10 spent = 1 point
        $order_total = $order->get_total();
        $points_to_award = floor($order_total / 10); // 1 point per CHF 10 spent
        error_log("Order total: $order_total, points to award: $points_to_award");

        if ($points_to_award <= 0) {
            error_log("No points to award for order total $order_total");
            return; // No points to award
        }

        // Get current coach points balance
        $current_coach_points = get_user_meta($coach_id, 'intersoccer_points_balance', true) ?: 0;
        $new_coach_points = $current_coach_points + $points_to_award;
        update_user_meta($coach_id, 'intersoccer_points_balance', $new_coach_points);
        error_log("Updated coach $coach_id points: $current_coach_points -> $new_coach_points");

        // Record the purchase reward
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'intersoccer_purchase_rewards',
            [
                'coach_id' => $coach_id,
                'customer_id' => $customer_id,
                'order_id' => $order->get_id(),
                'order_total' => $order_total,
                'points_awarded' => $points_to_award,
                'created_at' => current_time('mysql')
            ]
        );

        if ($result === false) {
            error_log("Failed to insert purchase reward: " . $wpdb->last_error);
        } else {
            error_log("Successfully recorded purchase reward for coach $coach_id");
        }

        // Add order note
        $coach_info = get_userdata($coach_id);
        $order->add_order_note(sprintf(__('Awarded %d points to coach %s for customer purchase (CHF %.2f). New balance: %d', 'intersoccer-referral'),
            $points_to_award, $coach_info->display_name, $order_total, $new_coach_points));

        error_log("Awarded $points_to_award points to coach $coach_id for customer $customer_id purchase of CHF $order_total");
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
        
        // Get cart total to limit redemption
        $cart_total = WC()->cart ? WC()->cart->get_total('edit') : 0;

        error_log("Points session update - User: $user_id, Requested: $points_to_redeem, Available: $available_points, Cart Total: $cart_total");

        // Limit to available points AND cart total (no 100-point limit)
        $points_to_redeem = min($points_to_redeem, $available_points, $cart_total);

        // Update session
        WC()->session->set('intersoccer_points_to_redeem', $points_to_redeem);

        wp_send_json_success([
            'points_to_redeem' => $points_to_redeem,
            'discount_amount' => $points_to_redeem
        ]);
    }

    public function apply_referral_code_ajax() {
        check_ajax_referer('intersoccer_checkout_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Must be logged in to apply referral code']);
        }

        $user_id = get_current_user_id();
        $referral_code = strtoupper(sanitize_text_field($_POST['referral_code']));



        // Check if referral code is already applied to this session
        $applied_code = WC()->session->get('intersoccer_applied_referral_code');
        if ($applied_code) {
            // Log attempt to apply multiple referral codes
            do_action('intersoccer_referral_code_invalid', $referral_code, 'code_already_applied');
            wp_send_json_error(['message' => 'Referral code already applied to this order']);
        }

        // Find coach with this referral code
        $coaches = get_users([
            'role' => 'coach',
            'meta_key' => 'referral_code',
            'meta_value' => $referral_code,
            'number' => 1
        ]);

        if (empty($coaches)) {
            // Log invalid referral code attempt
            do_action('intersoccer_referral_code_invalid', $referral_code, 'code_not_found');
            wp_send_json_error(['message' => 'Invalid referral code']);
        }

        $coach = $coaches[0];
        $coach_name = $coach->first_name . ' ' . $coach->last_name;

        // Store referral code and coach info in session
        WC()->session->set('intersoccer_applied_referral_code', $referral_code);
        WC()->session->set('intersoccer_referral_coach_id', $coach->ID);

        // Store the coach ID in customer metadata for ongoing commissions
        update_user_meta($user_id, 'intersoccer_preferred_coach', $coach->ID);

        // Log successful referral code usage
        do_action('intersoccer_referral_code_used', $referral_code, $user_id, $coach->ID);

        wp_send_json_success([
            'message' => sprintf(__('Referral code applied! You will receive a discount from coach %s.', 'intersoccer-referral'), $coach_name),
            'coach_name' => $coach_name,
            'discount_amount' => 10 // 10 CHF discount for referral codes
        ]);
    }

    /**
     * Apply points discount to cart total
     */
    public function apply_points_discount_as_fee($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        // Apply referral code discount
        $referral_code = WC()->session->get('intersoccer_applied_referral_code');
        if ($referral_code) {
            $discount_amount = -10; // 10 CHF discount for referral codes
            $cart->add_fee(__('Coach Referral Discount', 'intersoccer-referral'), $discount_amount, true, '');
            
            // Debug logging (only in debug mode to prevent excessive disk I/O)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("InterSoccer Referral: Applying referral discount - code=$referral_code, discount=$discount_amount");
            }
        }

        // Apply points discount
        $points_to_redeem = WC()->session->get('intersoccer_points_to_redeem', 0);

        if ($points_to_redeem > 0) {
            $discount_amount = -$points_to_redeem; // Negative fee for discount
            $cart->add_fee(__('Referral Credits Discount', 'intersoccer-referral'), $discount_amount, true, '');
            
            // Debug logging (only in debug mode to prevent excessive disk I/O)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("InterSoccer Referral: Applying points discount as fee - points=$points_to_redeem, discount=$discount_amount");
            }
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