<?php
// includes/class-admin-settings.php

class InterSoccer_Admin_Settings {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Store instance for simulator class to use
        self::$instance = $this;
        add_action('admin_post_import_coaches_from_csv', [$this, 'import_coaches_from_csv']);
        add_action('wp_ajax_reset_all_customer_credits', [$this, 'reset_all_customer_credits']);
        add_action('wp_ajax_allocate_credits_to_customers', [$this, 'allocate_credits_to_customers']);
        add_action('wp_ajax_clear_audit_log', [$this, 'clear_audit_log']);
        add_action('wp_ajax_export_audit_log', [$this, 'export_audit_log']);
        add_action('wp_ajax_bulk_credit_adjustment', [$this, 'bulk_credit_adjustment']);
        add_action('wp_ajax_get_credit_statistics', [$this, 'get_credit_statistics']);
        add_action('wp_ajax_get_coach_statistics', [$this, 'get_coach_statistics']);
        add_action('wp_ajax_get_audit_log', [$this, 'get_audit_log']);
        add_action('wp_ajax_get_points_statistics', [$this, 'get_points_statistics_ajax']);
        add_action('wp_ajax_get_points_ledger', [$this, 'get_points_ledger_ajax']);
        add_action('admin_init', [$this, 'register_settings']);

        // Add AJAX handler for coach import
        add_action('wp_ajax_import_coaches_from_csv', [$this, 'ajax_import_coaches_from_csv']);

        // Phase 0: Role-specific point rates (Tools page helper)
        add_action('wp_ajax_save_points_rates', [$this, 'save_points_rates_ajax']);
        
        // Commission tier management
        add_action('wp_ajax_save_commission_tiers', [$this, 'save_commission_tiers_ajax']);
        add_action('wp_ajax_add_commission_tier', [$this, 'add_commission_tier_ajax']);
        add_action('wp_ajax_delete_commission_tier', [$this, 'delete_commission_tier_ajax']);

        // Simulator functionality is now handled by InterSoccer_Simulator class
        // Initialize simulator instance to register its AJAX handlers
        // The simulator class will delegate back to these methods
        InterSoccer_Simulator::get_instance();
    }

    public function render_settings_page() {
        $allocation_mode   = get_option('intersoccer_points_allocation_mode', 'ratio');
        $percentage_rate   = (float) get_option('intersoccer_points_percentage_rate', 0);
        $eligibility_months = (int) get_option('intersoccer_referral_eligibility_months', 18);
        $credit_value      = (float) get_option('intersoccer_credit_value', 1);
        $max_credits       = (int) get_option('intersoccer_max_credits_per_order', 9999);
        
        // Get current tab from URL or default to 'general'
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        ?>
        <div class="wrap intersoccer-admin">
            <h1 class="wp-heading-inline"><?php esc_html_e('Referral System Settings', 'intersoccer-referral'); ?></h1>

            <p class="description">
                <?php esc_html_e('Use this page to control how customers and coaches earn and spend points. For imports, bulk updates, or maintenance tools, use the Tools page.', 'intersoccer-referral'); ?>
            </p>

            <!-- Settings Tabs -->
            <nav class="nav-tab-wrapper" style="margin: 20px 0 0 0;">
                <a href="?page=intersoccer-settings&tab=general" class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('General Settings', 'intersoccer-referral'); ?>
                </a>
                <a href="?page=intersoccer-settings&tab=points" class="nav-tab <?php echo $current_tab === 'points' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Points Configuration', 'intersoccer-referral'); ?>
                </a>
            </nav>

            <!-- Current Configuration Overview -->
            <div class="intersoccer-settings-section">
                <h2><?php esc_html_e('Current Configuration Overview', 'intersoccer-referral'); ?></h2>
                <div class="info-grid">
                    <div class="info-item">
                        <strong><?php esc_html_e('Points Earning Mode:', 'intersoccer-referral'); ?></strong>
                        <span>
                            <?php esc_html_e('Fixed CHF per point (role-based rates)', 'intersoccer-referral'); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <strong><?php esc_html_e('New Customer Window:', 'intersoccer-referral'); ?></strong>
                        <span>
                            <?php
                            if ($eligibility_months <= 0) {
                                esc_html_e('Disabled (no dormancy rule)', 'intersoccer-referral');
                            } else {
                                printf(
                                    /* translators: %d is number of months */
                                    esc_html__('%d months without completed orders', 'intersoccer-referral'),
                                    $eligibility_months
                                );
                            }
                            ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <strong><?php esc_html_e('Credit Value:', 'intersoccer-referral'); ?></strong>
                        <span>
                            <?php
                            printf(
                                /* translators: %s is a CHF amount */
                                esc_html__('1 point = %s CHF at checkout', 'intersoccer-referral'),
                                number_format_i18n($credit_value, 2)
                            );
                            ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <strong><?php esc_html_e('Max Credits per Order:', 'intersoccer-referral'); ?></strong>
                        <span>
                            <?php
                            if ($max_credits >= 9999) {
                                esc_html_e('Effectively unlimited (limited only by order total)', 'intersoccer-referral');
                            } else {
                                printf(
                                    /* translators: %d is a points amount */
                                    esc_html__('%d points', 'intersoccer-referral'),
                                    $max_credits
                                );
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <?php if ($current_tab === 'general'): ?>
            <!-- General Plugin Settings -->
            <div class="intersoccer-settings-section">
                <h2><?php esc_html_e('General Settings', 'intersoccer-referral'); ?></h2>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('intersoccer_settings');
                    do_settings_sections('intersoccer_settings');
                    ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Point Value at Checkout (CHF)', 'intersoccer-referral'); ?></th>
                            <td>
                                <input type="number" name="intersoccer_credit_value" value="<?php echo esc_attr($credit_value); ?>" step="0.01" min="0.01">
                                <p class="description">
                                    <?php esc_html_e('How much 1 point is worth in CHF when a customer uses points at checkout.', 'intersoccer-referral'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Maximum Points per Order', 'intersoccer-referral'); ?></th>
                            <td>
                                <input type="number" name="intersoccer_max_credits_per_order" value="<?php echo esc_attr($max_credits); ?>" min="1" max="9999">
                                <p class="description">
                                    <?php esc_html_e('Maximum number of points a customer can apply to a single order. This is still limited by the order total.', 'intersoccer-referral'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Debug Logging', 'intersoccer-referral'); ?></th>
                            <td>
                                <input type="checkbox" name="intersoccer_debug_logging" value="1" <?php checked(get_option('intersoccer_debug_logging'), '1'); ?>>
                                <p class="description">
                                    <?php esc_html_e('Turn this on only when troubleshooting. Detailed logs are written to the WordPress debug log.', 'intersoccer-referral'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="referral-eligibility-window">
                                    <?php esc_html_e('New Customer & Eligibility Window (months)', 'intersoccer-referral'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number"
                                       id="referral-eligibility-window"
                                       name="intersoccer_referral_eligibility_months"
                                       value="<?php echo esc_attr($eligibility_months); ?>"
                                       min="0"
                                       max="60"
                                       step="1">
                                <p class="description">
                                    <?php esc_html_e('We treat a customer as “new” if they have no completed orders during this period. This controls first-time discounts and the Eligibility column. Set to 0 to disable.', 'intersoccer-referral'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Point Allocation Method', 'intersoccer-referral'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="radio"
                                               name="intersoccer_points_allocation_method"
                                               value="instant"
                                               <?php checked(get_option('intersoccer_points_allocation_method', 'instant'), 'instant'); ?> />
                                        <?php esc_html_e('Instant', 'intersoccer-referral'); ?>
                                    </label>
                                    <p class="description" style="margin-left: 25px; margin-top: 5px;">
                                        <?php esc_html_e('Points are allocated immediately when orders are completed.', 'intersoccer-referral'); ?>
                                    </p>
                                    <label style="display: block; margin-top: 15px;">
                                        <input type="radio"
                                               name="intersoccer_points_allocation_method"
                                               value="deferred"
                                               <?php checked(get_option('intersoccer_points_allocation_method', 'instant'), 'deferred'); ?> />
                                        <?php esc_html_e('Deferred (Weekly)', 'intersoccer-referral'); ?>
                                    </label>
                                    <p class="description" style="margin-left: 25px; margin-top: 5px;">
                                        <?php esc_html_e('Points are allocated via a weekly scheduled task (wp_cron). More efficient for high-volume sites.', 'intersoccer-referral'); ?>
                                    </p>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Passive Mode', 'intersoccer-referral'); ?></th>
                            <td>
                                <input type="checkbox" name="intersoccer_passive_mode" value="1" <?php checked(get_option('intersoccer_passive_mode', false), true); ?>>
                                <p class="description">
                                    <?php esc_html_e('When enabled, the system will track customer purchases and monitor point accrual, but checkout fields (referral code and points redemption) will be hidden from customers. This allows you to monitor the system before enabling customer-facing features.', 'intersoccer-referral'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save Settings'); ?>
                </form>
                
            </div>
            <?php endif; ?>

            <?php if ($current_tab === 'points'): ?>
            <!-- Phase 0: Points & Earning Rules (Role-Specific Point Acquisition Rates) -->
            <div class="intersoccer-settings-section">
                <h2>⭐ <?php esc_html_e('Points & Earning Rules (Role-Specific Point Acquisition Rates)', 'intersoccer-referral'); ?></h2>
                <div class="settings-notice">
                    <div class="notice notice-info">
                        <p><strong>💡 Configure point earning rates for customers</strong></p>
                        <p><strong>How it works:</strong></p>
                        <ul>
                            <li>Set how many CHF customers must spend to earn 1 point</li>
                            <li>Lower numbers = better rates (faster point earning)</li>
                            <li>Example: Rate of 5 means CHF 5 spent = 1 point</li>
                            <li><strong>Note:</strong> Coaches, Partners, and Social Influencers use the Customer Purchase Rate when making purchases. They are rewarded through commission tiers (see below) rather than special point rates.</li>
                        </ul>
                        <p><strong>How it works:</strong> The system uses a fixed CHF-per-point model. Each rate card shows the percentage equivalent (based on point value at checkout) for easy visualization. The go-live date is configured in the controls at the top of this section.</p>
                    </div>
                </div>

                <?php
                // Configuration - default to fixed-rate (ratio mode)
                $go_live_option = get_option('intersoccer_points_golive_date', '');
                $go_live_status = __('Points accumulation is active immediately.', 'intersoccer-referral');
                $credit_value = (float) get_option('intersoccer_credit_value', 1); // 1 point = X CHF at checkout

                if (!empty($go_live_option)) {
                    $go_live_timestamp = strtotime($go_live_option . ' 00:00:00');
                    if ($go_live_timestamp) {
                        $formatted_go_live = date_i18n(get_option('date_format', 'F j, Y'), $go_live_timestamp);
                        $current_timestamp = current_time('timestamp');

                        if ($current_timestamp < $go_live_timestamp) {
                            $go_live_status = sprintf(
                                __('Points accumulation is scheduled to begin on %s.', 'intersoccer-referral'),
                                $formatted_go_live
                            );
                        } else {
                            $go_live_status = sprintf(
                                __('Points accumulation has been active since %s.', 'intersoccer-referral'),
                                $formatted_go_live
                            );
                        }
                    }
                }
                ?>

                <form id="points-rates-form" method="post">
                    <?php wp_nonce_field('intersoccer_points_rates_save', 'points_rates_nonce'); ?>

                    <div class="points-config-grid" style="display: grid; grid-template-columns: 1.2fr 2fr; gap: 24px; align-items: flex-start;">
                        <!-- Configuration (Go-Live + Mode) -->
                        <div class="points-config-card" style="background:#fff;border:1px solid #e1e1e1;border-radius:8px;padding:20px;">
                            <h3 style="margin-top:0;"><?php esc_html_e('Points Configuration', 'intersoccer-referral'); ?></h3>
                            <table class="form-table" style="margin-top:0;">
                                <tr>
                                    <th scope="row">
                                        <label for="points-golive-date"><?php esc_html_e('Points Go-Live Date', 'intersoccer-referral'); ?></label>
                                    </th>
                                    <td>
                                        <input type="date"
                                               id="points-golive-date"
                                               name="intersoccer_points_golive_date"
                                               class="regular-text"
                                               value="<?php echo esc_attr($go_live_option); ?>">
                                        <p class="description">
                                            <?php esc_html_e('Points will only be awarded for orders placed on or after this date. Leave blank to start awarding points immediately.', 'intersoccer-referral'); ?>
                                        </p>
                                        <p class="description status-description">
                                            <strong><?php esc_html_e('Status:', 'intersoccer-referral'); ?></strong>
                                            <?php echo esc_html($go_live_status); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Role-Based Rate Cards -->
                        <div>
                    <div class="rates-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                        
                        <!-- Customer Purchase Rate -->
                        <div class="rate-card" style="background: #fff; border: 2px solid #e2e8f0; border-radius: 8px; padding: 20px;">
                            <h3 style="margin: 0 0 15px 0; color: #2563eb;">🛒 Customer Purchase Rate</h3>
                            <div class="rate-input-group">
                                <label for="rate_customer_purchase" style="display: block; margin-bottom: 8px; font-weight: 600;">
                                    CHF per 1 Point:
                                </label>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <input 
                                        type="number" 
                                        id="rate_customer_purchase" 
                                        name="rate_customer_purchase" 
                                        value="<?php echo esc_attr(get_option('intersoccer_points_rate_customer_purchase', 10)); ?>"
                                        min="1"
                                        max="100"
                                        step="1"
                                        class="regular-text"
                                        style="width: 100px;"
                                        required
                                    />
                                    <span class="rate-percentage" data-rate-input="rate_customer_purchase" style="color: #666; font-size: 14px;">
                                        (<?php 
                                        $rate = max(1, get_option('intersoccer_points_rate_customer_purchase', 10));
                                        $percentage = ($credit_value / $rate) * 100;
                                        echo number_format($percentage, 1); 
                                        ?>%)
                                    </span>
                                </div>
                                <p class="description" style="margin-top: 8px;">
                                    Points earned when customers purchase event tickets<br/>
                                    <strong>Current preview:</strong> CHF 100 spent = 
                                    <span class="preview-points" data-role="customer_purchase">
                                        <?php echo floor(100 / max(1, get_option('intersoccer_points_rate_customer_purchase', 10))); ?>
                                    </span> points
                                </p>
                            </div>
                        </div>

                        <!-- Customer Referral Rate -->
                        <div class="rate-card" style="background: #fff; border: 2px solid #e2e8f0; border-radius: 8px; padding: 20px;">
                            <h3 style="margin: 0 0 15px 0; color: #2563eb;">👥 Customer Referral Rate</h3>
                            <div class="rate-input-group">
                                <label for="rate_customer_referral" style="display: block; margin-bottom: 8px; font-weight: 600;">
                                    CHF per 1 Point:
                                </label>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <input 
                                        type="number" 
                                        id="rate_customer_referral" 
                                        name="rate_customer_referral" 
                                        value="<?php echo esc_attr(get_option('intersoccer_points_rate_customer_referral', 10)); ?>"
                                        min="1"
                                        max="100"
                                        step="1"
                                        class="regular-text"
                                        style="width: 100px;"
                                        required
                                    />
                                    <span class="rate-percentage" data-rate-input="rate_customer_referral" style="color: #666; font-size: 14px;">
                                        (<?php 
                                        $rate = max(1, get_option('intersoccer_points_rate_customer_referral', 10));
                                        $percentage = ($credit_value / $rate) * 100;
                                        echo number_format($percentage, 1); 
                                        ?>%)
                                    </span>
                                </div>
                                <p class="description" style="margin-top: 8px;">
                                    Points earned when customers refer another customer<br/>
                                    <strong>Current preview:</strong> CHF 100 spent = 
                                    <span class="preview-points" data-role="customer_referral">
                                        <?php echo floor(100 / max(1, get_option('intersoccer_points_rate_customer_referral', 10))); ?>
                                    </span> points
                                </p>
                            </div>
                        </div>

                        <!-- First Time Customer Rate -->
                        <div class="rate-card" style="background: #fff; border: 2px solid #e2e8f0; border-radius: 8px; padding: 20px;">
                            <h3 style="margin: 0 0 15px 0; color: #f59e0b;">⭐ First Time Customer Rate</h3>
                            <div class="rate-input-group">
                                <label for="rate_first_time_customer" style="display: block; margin-bottom: 8px; font-weight: 600;">
                                    CHF per 1 Point:
                                </label>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <input 
                                        type="number" 
                                        id="rate_first_time_customer" 
                                        name="rate_first_time_customer" 
                                        value="<?php echo esc_attr(get_option('intersoccer_points_rate_first_time_customer', 10)); ?>"
                                        min="1"
                                        max="100"
                                        step="1"
                                        class="regular-text"
                                        style="width: 100px;"
                                        required
                                    />
                                    <span class="rate-percentage" data-rate-input="rate_first_time_customer" style="color: #666; font-size: 14px;">
                                        (<?php 
                                        $rate = max(1, get_option('intersoccer_points_rate_first_time_customer', 10));
                                        $percentage = ($credit_value / $rate) * 100;
                                        echo number_format($percentage, 1); 
                                        ?>%)
                                    </span>
                                </div>
                                <p class="description" style="margin-top: 8px;">
                                    Points earned by first-time customers on their first purchase<br/>
                                    <strong>Current preview:</strong> CHF 100 spent = 
                                    <span class="preview-points" data-role="first_time_customer">
                                        <?php echo floor(100 / max(1, get_option('intersoccer_points_rate_first_time_customer', 10))); ?>
                                    </span> points
                                </p>
                            </div>
                        </div>


                    </div>
                        </div>
                    </div>


                    <div style="margin-top: 20px;">
                        <button type="submit" class="button button-primary button-large" id="save-points-rates">
                            <span class="dashicons dashicons-saved"></span>
                            <?php esc_html_e('Save Points Configuration & Rates', 'intersoccer-referral'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="reset-points-rates" style="margin-left: 10px;">
                            <?php esc_html_e('Reset Customer Rates to Defaults (10 CHF = 1 point)', 'intersoccer-referral'); ?>
                        </button>
                    </div>
                </form>

                <div id="rates-save-message" style="display: none; margin-top: 20px;"></div>

                <!-- Tiered Commission Rates Section -->
                <div class="intersoccer-settings-section" style="margin-top: 40px;">
                    <h2><?php esc_html_e('Tiered Commission Rates (%)', 'intersoccer-referral'); ?></h2>
                    <p class="description">
                        <?php esc_html_e('Configure commission rates based on the number of customers recruited. Each role (Coaches, Partners, Social Influencers) can have their own tier structure. Tiers are evaluated in order, and the first matching tier is used.', 'intersoccer-referral'); ?>
                    </p>

                    <!-- Coach Commission Tiers -->
                    <div style="background: #fff; border: 2px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-top: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #16a34a;">⚽ <?php esc_html_e('Coach Commission Tiers', 'intersoccer-referral'); ?></h3>
                        <div id="coach-commission-tiers-container">
                            <?php
                            $coach_tiers = get_option('intersoccer_commission_tiers_coach', [
                                ['min_customers' => 1, 'max_customers' => 10, 'rate' => 10],
                                ['min_customers' => 11, 'max_customers' => 24, 'rate' => 15],
                                ['min_customers' => 25, 'max_customers' => 999999, 'rate' => 20],
                            ]);
                            
                            foreach ($coach_tiers as $index => $tier) {
                                ?>
                                <div class="commission-tier-row" data-role="coach" data-tier-index="<?php echo esc_attr($index); ?>" style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                                    <input type="number" 
                                           class="tier-min-customers" 
                                           value="<?php echo esc_attr($tier['min_customers']); ?>" 
                                           min="1" 
                                           style="width: 80px;" 
                                           placeholder="Min">
                                    <span><?php esc_html_e('to', 'intersoccer-referral'); ?></span>
                                    <input type="number" 
                                           class="tier-max-customers" 
                                           value="<?php echo esc_attr($tier['max_customers'] >= 999999 ? '' : $tier['max_customers']); ?>" 
                                           min="1" 
                                           style="width: 80px;" 
                                           placeholder="Max or +">
                                    <span><?php esc_html_e('customers:', 'intersoccer-referral'); ?></span>
                                    <input type="number" 
                                           class="tier-rate" 
                                           value="<?php echo esc_attr($tier['rate']); ?>" 
                                           min="0" 
                                           max="100" 
                                           step="0.1" 
                                           style="width: 80px;" 
                                           placeholder="%">
                                    <span>%</span>
                                    <span class="tier-example" style="color: #666; font-size: 13px; margin-left: 10px;">
                                        (<?php 
                                        $example_order = 500;
                                        $example_commission = ($example_order * floatval($tier['rate'])) / 100;
                                        printf(esc_html__('CHF %s on %s CHF order', 'intersoccer-referral'), 
                                            number_format($example_commission, 2), 
                                            number_format($example_order, 0)
                                        );
                                        ?>)
                                    </span>
                                    <button type="button" class="button button-small delete-tier" style="margin-left: auto;"><?php esc_html_e('Delete', 'intersoccer-referral'); ?></button>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                        <button type="button" class="button button-secondary add-commission-tier" data-role="coach" style="margin-top: 10px;">
                            <span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e('Add Tier', 'intersoccer-referral'); ?>
                        </button>
                        <div id="coach-commission-tiers-message" style="display: none; margin-top: 10px;"></div>
                    </div>

                    <!-- Partner Commission Tiers -->
                    <div style="background: #fff; border: 2px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-top: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #dc2626;">🤝 <?php esc_html_e('Partner Commission Tiers', 'intersoccer-referral'); ?></h3>
                        <div id="partner-commission-tiers-container">
                            <?php
                            $partner_tiers = get_option('intersoccer_commission_tiers_partner', [
                                ['min_customers' => 1, 'max_customers' => 10, 'rate' => 10],
                                ['min_customers' => 11, 'max_customers' => 24, 'rate' => 15],
                                ['min_customers' => 25, 'max_customers' => 999999, 'rate' => 20],
                            ]);
                            
                            foreach ($partner_tiers as $index => $tier) {
                                ?>
                                <div class="commission-tier-row" data-role="partner" data-tier-index="<?php echo esc_attr($index); ?>" style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                                    <input type="number" 
                                           class="tier-min-customers" 
                                           value="<?php echo esc_attr($tier['min_customers']); ?>" 
                                           min="1" 
                                           style="width: 80px;" 
                                           placeholder="Min">
                                    <span><?php esc_html_e('to', 'intersoccer-referral'); ?></span>
                                    <input type="number" 
                                           class="tier-max-customers" 
                                           value="<?php echo esc_attr($tier['max_customers'] >= 999999 ? '' : $tier['max_customers']); ?>" 
                                           min="1" 
                                           style="width: 80px;" 
                                           placeholder="Max or +">
                                    <span><?php esc_html_e('customers:', 'intersoccer-referral'); ?></span>
                                    <input type="number" 
                                           class="tier-rate" 
                                           value="<?php echo esc_attr($tier['rate']); ?>" 
                                           min="0" 
                                           max="100" 
                                           step="0.1" 
                                           style="width: 80px;" 
                                           placeholder="%">
                                    <span>%</span>
                                    <span class="tier-example" style="color: #666; font-size: 13px; margin-left: 10px;">
                                        (<?php 
                                        $example_order = 500;
                                        $example_commission = ($example_order * floatval($tier['rate'])) / 100;
                                        printf(esc_html__('CHF %s on %s CHF order', 'intersoccer-referral'), 
                                            number_format($example_commission, 2), 
                                            number_format($example_order, 0)
                                        );
                                        ?>)
                                    </span>
                                    <button type="button" class="button button-small delete-tier" style="margin-left: auto;"><?php esc_html_e('Delete', 'intersoccer-referral'); ?></button>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                        <button type="button" class="button button-secondary add-commission-tier" data-role="partner" style="margin-top: 10px;">
                            <span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e('Add Tier', 'intersoccer-referral'); ?>
                        </button>
                        <div id="partner-commission-tiers-message" style="display: none; margin-top: 10px;"></div>
                    </div>

                    <!-- Social Influencer Commission Tiers -->
                    <div style="background: #fff; border: 2px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-top: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #9333ea;">📱 <?php esc_html_e('Social Influencer Commission Tiers', 'intersoccer-referral'); ?></h3>
                        <div id="social_influencer-commission-tiers-container">
                            <?php
                            $social_influencer_tiers = get_option('intersoccer_commission_tiers_social_influencer', [
                                ['min_customers' => 1, 'max_customers' => 10, 'rate' => 10],
                                ['min_customers' => 11, 'max_customers' => 24, 'rate' => 15],
                                ['min_customers' => 25, 'max_customers' => 999999, 'rate' => 20],
                            ]);
                            
                            foreach ($social_influencer_tiers as $index => $tier) {
                                ?>
                                <div class="commission-tier-row" data-role="social_influencer" data-tier-index="<?php echo esc_attr($index); ?>" style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                                    <input type="number" 
                                           class="tier-min-customers" 
                                           value="<?php echo esc_attr($tier['min_customers']); ?>" 
                                           min="1" 
                                           style="width: 80px;" 
                                           placeholder="Min">
                                    <span><?php esc_html_e('to', 'intersoccer-referral'); ?></span>
                                    <input type="number" 
                                           class="tier-max-customers" 
                                           value="<?php echo esc_attr($tier['max_customers'] >= 999999 ? '' : $tier['max_customers']); ?>" 
                                           min="1" 
                                           style="width: 80px;" 
                                           placeholder="Max or +">
                                    <span><?php esc_html_e('customers:', 'intersoccer-referral'); ?></span>
                                    <input type="number" 
                                           class="tier-rate" 
                                           value="<?php echo esc_attr($tier['rate']); ?>" 
                                           min="0" 
                                           max="100" 
                                           step="0.1" 
                                           style="width: 80px;" 
                                           placeholder="%">
                                    <span>%</span>
                                    <span class="tier-example" style="color: #666; font-size: 13px; margin-left: 10px;">
                                        (<?php 
                                        $example_order = 500;
                                        $example_commission = ($example_order * floatval($tier['rate'])) / 100;
                                        printf(esc_html__('CHF %s on %s CHF order', 'intersoccer-referral'), 
                                            number_format($example_commission, 2), 
                                            number_format($example_order, 0)
                                        );
                                        ?>)
                                    </span>
                                    <button type="button" class="button button-small delete-tier" style="margin-left: auto;"><?php esc_html_e('Delete', 'intersoccer-referral'); ?></button>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                        <button type="button" class="button button-secondary add-commission-tier" data-role="social_influencer" style="margin-top: 10px;">
                            <span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e('Add Tier', 'intersoccer-referral'); ?>
                        </button>
                        <div id="social_influencer-commission-tiers-message" style="display: none; margin-top: 10px;"></div>
                    </div>

                    <button type="button" id="save-all-commission-tiers" class="button button-primary" style="margin-top: 20px;">
                        <?php esc_html_e('Save All Commission Tiers', 'intersoccer-referral'); ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render the Referral Tools page.
     *
     * NOTE: At the moment, the detailed tools UI still lives on the
     * Settings page. This method is a safe placeholder to avoid fatal
     * errors when visiting the Tools submenu.
     */
    public function render_tools_page() {
        ?>
        <div class="wrap intersoccer-admin">
            <h1 class="wp-heading-inline"><?php esc_html_e('Referral Tools', 'intersoccer-referral'); ?></h1>
            <p class="description">
                <?php esc_html_e('Maintenance and data tools for managing points, credits, imports, and system maintenance.', 'intersoccer-referral'); ?>
            </p>

            <!-- Points Management -->
            <div class="intersoccer-settings-section">
                <h2><?php esc_html_e('Points Management', 'intersoccer-referral'); ?></h2>
                <div class="settings-grid">
                    <div class="settings-card">
                        <h3><?php esc_html_e('Points Statistics', 'intersoccer-referral'); ?></h3>
                        <div id="points-stats">
                            <p><?php esc_html_e('Loading points statistics...', 'intersoccer-referral'); ?></p>
                        </div>
                        <button id="refresh-points-stats" class="button"><?php esc_html_e('Refresh Stats', 'intersoccer-referral'); ?></button>
                    </div>

                    <div class="settings-card">
                        <h3><?php esc_html_e('Points Ledger', 'intersoccer-referral'); ?></h3>
                        <p><?php esc_html_e('View detailed points transaction history.', 'intersoccer-referral'); ?></p>
                        <button id="view-points-ledger" class="button button-secondary"><?php esc_html_e('View Ledger', 'intersoccer-referral'); ?></button>
                        <div id="points-ledger-container" style="display: none; margin-top: 15px;">
                            <div id="points-ledger-content">
                                <p><?php esc_html_e('Loading ledger...', 'intersoccer-referral'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales & Marketing Revenue Simulator -->
            <div class="intersoccer-settings-section">
                <h2><?php esc_html_e('Sales & Marketing Revenue Simulator', 'intersoccer-referral'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Model different referral program configurations, compare scenarios, justify ROI, find optimal rates, and create revenue projections using historical sales data. Perfect for Sales & Marketing planning and presentations.', 'intersoccer-referral'); ?>
                </p>

                <!-- Scenario Management Section -->
                <div class="simulator-scenario-management" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; margin-top: 20px; margin-bottom: 20px;">
                    <h3 style="margin-top: 0;"><?php esc_html_e('Scenario Management', 'intersoccer-referral'); ?></h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label for="simulator-scenario-name" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                <?php esc_html_e('Scenario Name', 'intersoccer-referral'); ?>
                            </label>
                            <input type="text" 
                                   id="simulator-scenario-name" 
                                   class="regular-text" 
                                   placeholder="<?php esc_attr_e('e.g., Conservative, Moderate, Aggressive', 'intersoccer-referral'); ?>"
                                   style="width: 100%;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                                <?php esc_html_e('Quick Templates', 'intersoccer-referral'); ?>
                            </label>
                            <select id="simulator-scenario-template" class="regular-text" style="width: 100%;">
                                <option value=""><?php esc_html_e('-- Select Template --', 'intersoccer-referral'); ?></option>
                                <option value="baseline"><?php esc_html_e('Baseline (No Program)', 'intersoccer-referral'); ?></option>
                                <option value="conservative"><?php esc_html_e('Conservative', 'intersoccer-referral'); ?></option>
                                <option value="moderate"><?php esc_html_e('Moderate', 'intersoccer-referral'); ?></option>
                                <option value="aggressive"><?php esc_html_e('Aggressive', 'intersoccer-referral'); ?></option>
                                <option value="current"><?php esc_html_e('Current Settings', 'intersoccer-referral'); ?></option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                                <?php esc_html_e('Saved Scenarios', 'intersoccer-referral'); ?>
                            </label>
                            <select id="simulator-load-scenario" class="regular-text" style="width: 100%;">
                                <option value=""><?php esc_html_e('-- Load Saved Scenario --', 'intersoccer-referral'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="button" id="simulator-save-scenario" class="button button-secondary">
                            <span class="dashicons dashicons-saved" style="vertical-align: middle;"></span>
                            <?php esc_html_e('Save Scenario', 'intersoccer-referral'); ?>
                        </button>
                        <button type="button" id="simulator-delete-scenario" class="button button-link-delete" style="display: none;">
                            <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
                            <?php esc_html_e('Delete Scenario', 'intersoccer-referral'); ?>
                        </button>
                        <button type="button" id="simulator-load-current-settings" class="button button-secondary">
                            <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                            <?php esc_html_e('Load Current Settings', 'intersoccer-referral'); ?>
                        </button>
                    </div>
                </div>

                <div class="simulator-container" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; margin-top: 20px;">
                    <!-- Comparison Mode Toggle -->
                    <div style="background: #f0f6fc; padding: 15px; border: 1px solid #c3d4e6; border-radius: 4px; margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 10px; font-weight: 600;">
                            <input type="checkbox" id="simulator-compare-mode" value="1">
                            <?php esc_html_e('Compare Multiple Scenarios', 'intersoccer-referral'); ?>
                        </label>
                        <p class="description" style="margin-top: 5px; margin-bottom: 0;">
                            <?php esc_html_e('Enable to compare 2-3 scenarios side-by-side. Each scenario can have different variable settings.', 'intersoccer-referral'); ?>
                        </p>
                    </div>

                    <div class="simulator-form">
                        <!-- Date Range Selection -->
                        <div style="background: #f0f6fc; padding: 15px; border: 1px solid #c3d4e6; border-radius: 4px; margin-bottom: 20px;">
                            <h4 style="margin-top: 0;"><?php esc_html_e('Date Range', 'intersoccer-referral'); ?></h4>
                            <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                                <label style="font-weight: 600;">
                                    <?php esc_html_e('From:', 'intersoccer-referral'); ?>
                                    <input type="date" 
                                           id="simulator-date-from" 
                                           class="regular-text" 
                                           style="margin-left: 5px; padding: 5px;">
                                </label>
                                <label style="font-weight: 600;">
                                    <?php esc_html_e('To:', 'intersoccer-referral'); ?>
                                    <input type="date" 
                                           id="simulator-date-to" 
                                           class="regular-text" 
                                           style="margin-left: 5px; padding: 5px;">
                                </label>
                            </div>
                            <p class="description" style="margin-top: 10px; margin-bottom: 0;">
                                <?php esc_html_e('Select a date range to analyze all orders within that period. Use 3-4 months of historical data for accurate projections.', 'intersoccer-referral'); ?>
                            </p>
                        </div>

                        <!-- Variable Controls Tabs -->
                        <div class="simulator-tabs" style="margin-bottom: 20px;">
                            <nav class="nav-tab-wrapper" style="margin-bottom: 0;">
                                <a href="#simulator-tab-points" class="nav-tab nav-tab-active" data-tab="points">
                                    <?php esc_html_e('Points Configuration', 'intersoccer-referral'); ?>
                                </a>
                                <a href="#simulator-tab-commissions" class="nav-tab" data-tab="commissions">
                                    <?php esc_html_e('Commission Tiers', 'intersoccer-referral'); ?>
                                </a>
                                <a href="#simulator-tab-referrals" class="nav-tab" data-tab="referrals">
                                    <?php esc_html_e('Referral Settings', 'intersoccer-referral'); ?>
                                </a>
                                <a href="#simulator-tab-projections" class="nav-tab" data-tab="projections">
                                    <?php esc_html_e('Projections', 'intersoccer-referral'); ?>
                                </a>
                            </nav>

                            <!-- Points Configuration Tab -->
                            <div id="simulator-tab-points" class="simulator-tab-content" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 4px 4px;">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label><?php esc_html_e('Points Allocation Mode', 'intersoccer-referral'); ?></label>
                                        </th>
                                        <td>
                                            <fieldset>
                                                <label style="display: block; margin-bottom: 10px;">
                                                    <input type="radio" 
                                                           name="simulator-points-mode" 
                                                           value="ratio" 
                                                           checked>
                                                    <?php esc_html_e('Fixed CHF per point (ratio mode)', 'intersoccer-referral'); ?>
                                                </label>
                                                <label style="display: block; margin-bottom: 10px;">
                                                    <input type="radio" 
                                                           name="simulator-points-mode" 
                                                           value="percentage">
                                                    <?php esc_html_e('Percentage of order total', 'intersoccer-referral'); ?>
                                                </label>
                                            </fieldset>
                                        </td>
                                    </tr>
                                    <tr id="simulator-percentage-rate-row" style="display: none;">
                                        <th scope="row">
                                            <label for="simulator-percentage-rate"><?php esc_html_e('Percentage Rate (%)', 'intersoccer-referral'); ?></label>
                                        </th>
                                        <td>
                                            <input type="number" 
                                                   id="simulator-percentage-rate" 
                                                   class="small-text" 
                                                   value="<?php echo esc_attr(get_option('intersoccer_points_percentage_rate', 0)); ?>" 
                                                   min="0" 
                                                   max="100" 
                                                   step="0.1">
                                            <p class="description">
                                                <?php esc_html_e('Percentage of order total converted to points (e.g., 12.5% means CHF 200 order = 25 points)', 'intersoccer-referral'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="simulator-points-rate-purchase"><?php esc_html_e('Customer Purchase Rate (CHF per point)', 'intersoccer-referral'); ?></label>
                                        </th>
                                        <td>
                                            <input type="number" 
                                                   id="simulator-points-rate-purchase" 
                                                   class="small-text" 
                                                   value="<?php echo esc_attr(get_option('intersoccer_points_rate_customer_purchase', 10)); ?>" 
                                                   min="1" 
                                                   max="100">
                                            <p class="description">
                                                <?php esc_html_e('CHF required to earn 1 point (e.g., 10 = CHF 10 spent = 1 point)', 'intersoccer-referral'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="simulator-points-rate-referral"><?php esc_html_e('Customer Referral Rate (CHF per point)', 'intersoccer-referral'); ?></label>
                                        </th>
                                        <td>
                                            <input type="number" 
                                                   id="simulator-points-rate-referral" 
                                                   class="small-text" 
                                                   value="<?php echo esc_attr(get_option('intersoccer_points_rate_customer_referral', 10)); ?>" 
                                                   min="1" 
                                                   max="100">
                                            <p class="description">
                                                <?php esc_html_e('Points earned when customers refer another customer', 'intersoccer-referral'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="simulator-points-rate-first-time"><?php esc_html_e('First-Time Customer Rate (CHF per point)', 'intersoccer-referral'); ?></label>
                                        </th>
                                        <td>
                                            <input type="number" 
                                                   id="simulator-points-rate-first-time" 
                                                   class="small-text" 
                                                   value="<?php echo esc_attr(get_option('intersoccer_points_rate_first_time_customer', 10)); ?>" 
                                                   min="1" 
                                                   max="100">
                                            <p class="description">
                                                <?php esc_html_e('Points earned by first-time customers on their first purchase', 'intersoccer-referral'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="simulator-points-value"><?php esc_html_e('Points Value at Checkout (CHF per point)', 'intersoccer-referral'); ?></label>
                                        </th>
                                        <td>
                                            <input type="number" 
                                                   id="simulator-points-value" 
                                                   class="small-text" 
                                                   value="<?php echo esc_attr(get_option('intersoccer_credit_value', 1)); ?>" 
                                                   min="0.01" 
                                                   step="0.01">
                                            <p class="description">
                                                <?php esc_html_e('Value of 1 point when customers redeem at checkout (e.g., 1.00 = 1 point = 1 CHF discount)', 'intersoccer-referral'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Potential Growth Section -->
                                <div style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 20px; margin-top: 30px;">
                                    <h3 style="margin: 0 0 15px 0; color: #856404;">
                                        <span class="dashicons dashicons-chart-line" style="vertical-align: middle;"></span>
                                        <?php esc_html_e('Potential Growth Simulation', 'intersoccer-referral'); ?>
                                    </h3>
                                    <p class="description" style="margin-bottom: 15px;">
                                        <?php esc_html_e('Simulate what would have happened if historical orders had referrals. This multiplies the order count to model referral-driven growth (e.g., 30% growth = 30% more orders due to referrals).', 'intersoccer-referral'); ?>
                                    </p>
                                    
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="simulator-enable-growth">
                                                    <?php esc_html_e('Enable Potential Growth', 'intersoccer-referral'); ?>
                                                </label>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="checkbox" 
                                                           id="simulator-enable-growth" 
                                                           value="1">
                                                    <?php esc_html_e('Apply growth multiplier to simulate referral-driven order increase', 'intersoccer-referral'); ?>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr id="simulator-growth-percentage-row" style="display: none;">
                                            <th scope="row">
                                                <label for="simulator-growth-percentage">
                                                    <?php esc_html_e('Growth Percentage (%)', 'intersoccer-referral'); ?>
                                                </label>
                                            </th>
                                            <td>
                                                <input type="number" 
                                                       id="simulator-growth-percentage" 
                                                       class="small-text" 
                                                       value="30" 
                                                       min="0" 
                                                       max="200" 
                                                       step="1">
                                                <p class="description">
                                                    <?php esc_html_e('Percentage increase in orders due to referrals (e.g., 30 = 30% more orders, so 100 orders become 130 orders)', 'intersoccer-referral'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Commission Tiers Tab -->
                            <div id="simulator-tab-commissions" class="simulator-tab-content" style="display: none; background: #fff; padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 4px 4px;">
                                <div style="margin-bottom: 20px;">
                                    <p class="description">
                                        <?php esc_html_e('Configure commission rates based on customer count. Each role (Coaches, Partners, Social Influencers) has separate tier structures. Commission rates are tiered - higher customer counts earn higher commission rates.', 'intersoccer-referral'); ?>
                                    </p>
                                </div>

                                <div id="simulator-commission-tiers-container">
                                    <?php
                                    // Load commission tiers for all roles
                                    $roles = ['coach', 'partner', 'social_influencer'];
                                    $role_labels = [
                                        'coach' => __('Coach', 'intersoccer-referral'),
                                        'partner' => __('Partner', 'intersoccer-referral'),
                                        'social_influencer' => __('Social Influencer', 'intersoccer-referral'),
                                    ];
                                    $role_colors = [
                                        'coach' => '#16a34a',
                                        'partner' => '#dc2626',
                                        'social_influencer' => '#9333ea',
                                    ];
                                    
                                    foreach ($roles as $role):
                                        $tiers = get_option('intersoccer_commission_tiers_' . $role, [
                                            ['min_customers' => 1, 'max_customers' => 10, 'rate' => 10],
                                            ['min_customers' => 11, 'max_customers' => 24, 'rate' => 15],
                                            ['min_customers' => 25, 'max_customers' => 999999, 'rate' => 20],
                                        ]);
                                    ?>
                                    <div style="background: #f9f9f9; border: 1px solid #e2e8f0; border-radius: 4px; padding: 15px; margin-bottom: 20px;">
                                        <h4 style="margin-top: 0; color: <?php echo esc_attr($role_colors[$role]); ?>;">
                                            <?php echo esc_html($role_labels[$role]); ?> <?php esc_html_e('Commission Tiers', 'intersoccer-referral'); ?>
                                        </h4>
                                        <div class="simulator-commission-tiers" data-role="<?php echo esc_attr($role); ?>">
                                            <?php foreach ($tiers as $index => $tier): ?>
                                            <div class="simulator-tier-row" style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; padding: 8px; background: #fff; border-radius: 3px;">
                                                <input type="number" 
                                                       class="simulator-tier-min" 
                                                       value="<?php echo esc_attr($tier['min_customers']); ?>" 
                                                       min="1" 
                                                       style="width: 70px;" 
                                                       placeholder="Min">
                                                <span><?php esc_html_e('to', 'intersoccer-referral'); ?></span>
                                                <input type="number" 
                                                       class="simulator-tier-max" 
                                                       value="<?php echo esc_attr($tier['max_customers'] >= 999999 ? '' : $tier['max_customers']); ?>" 
                                                       min="1" 
                                                       style="width: 70px;" 
                                                       placeholder="Max">
                                                <span><?php esc_html_e('customers:', 'intersoccer-referral'); ?></span>
                                                <input type="number" 
                                                       class="simulator-tier-rate" 
                                                       value="<?php echo esc_attr($tier['rate']); ?>" 
                                                       min="0" 
                                                       max="100" 
                                                       step="0.1" 
                                                       style="width: 70px;" 
                                                       placeholder="%">
                                                <span>%</span>
                                                <button type="button" class="button button-small simulator-remove-tier" style="margin-left: auto;">
                                                    <?php esc_html_e('Remove', 'intersoccer-referral'); ?>
                                                </button>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <button type="button" class="button button-small simulator-add-tier" data-role="<?php echo esc_attr($role); ?>" style="margin-top: 8px;">
                                            <span class="dashicons dashicons-plus-alt"></span>
                                            <?php esc_html_e('Add Tier', 'intersoccer-referral'); ?>
                                        </button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Referral Settings Tab -->
                            <div id="simulator-tab-referrals" class="simulator-tab-content" style="display: none; background: #fff; padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 4px 4px;">
                                <div style="background: #e3f2fd; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                                    <button type="button" id="simulator-load-recommendations" class="button button-secondary">
                                        <span class="dashicons dashicons-lightbulb" style="vertical-align: middle;"></span>
                                        <?php esc_html_e('Get Recommendations from Historical Data', 'intersoccer-referral'); ?>
                                    </button>
                                    <div id="simulator-recommendations" style="margin-top: 15px; display: none;"></div>
                                </div>
                                
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="simulator-customer-referral-rate"><?php esc_html_e('Customer Referral Rate (%)', 'intersoccer-referral'); ?></label>
                                        </th>
                                        <td>
                                            <input type="number" 
                                                   id="simulator-customer-referral-rate" 
                                                   class="small-text" 
                                                   value="10" 
                                                   min="0" 
                                                   max="100" 
                                                   step="0.1">
                                            <p class="description">
                                                <?php esc_html_e('Percentage of orders that use customer referral codes (customers referring other customers).', 'intersoccer-referral'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="simulator-coach-referral-rate"><?php esc_html_e('Coach Referral Rate (%)', 'intersoccer-referral'); ?></label>
                                        </th>
                                        <td>
                                            <input type="number" 
                                                   id="simulator-coach-referral-rate" 
                                                   class="small-text" 
                                                   value="5" 
                                                   min="0" 
                                                   max="100" 
                                                   step="0.1">
                                            <p class="description">
                                                <?php esc_html_e('Percentage of orders that use coach referral codes (coaches referring customers).', 'intersoccer-referral'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="simulator-referral-rate"><?php esc_html_e('Total Referral Rate (%)', 'intersoccer-referral'); ?></label>
                                        </th>
                                        <td>
                                            <input type="number" 
                                                   id="simulator-referral-rate" 
                                                   class="small-text" 
                                                   value="15" 
                                                   min="0" 
                                                   max="100" 
                                                   step="0.1"
                                                   readonly
                                                   style="background: #f0f0f0;">
                                            <p class="description">
                                                <?php esc_html_e('Total referral rate (Customer + Coach). This is calculated automatically.', 'intersoccer-referral'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="simulator-first-time-discount"><?php esc_html_e('First-Time Customer Discount (CHF)', 'intersoccer-referral'); ?></label>
                                        </th>
                                        <td>
                                            <input type="number" 
                                                   id="simulator-first-time-discount" 
                                                   class="small-text" 
                                                   value="<?php echo esc_attr(get_option('intersoccer_first_time_discount_amount', 10)); ?>" 
                                                   min="0" 
                                                   step="1">
                                            <p class="description">
                                                <?php esc_html_e('Discount amount applied to first-time customers when they use a referral code', 'intersoccer-referral'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label><?php esc_html_e('Referral Distribution', 'intersoccer-referral'); ?></label>
                                        </th>
                                        <td>
                                            <p class="description">
                                                <?php esc_html_e('Distribution of referrals across roles (used when referral rate > 0)', 'intersoccer-referral'); ?>
                                            </p>
                                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 10px;">
                                                <div>
                                                    <label for="simulator-dist-coach" style="display: block; margin-bottom: 5px;">
                                                        <?php esc_html_e('Coach (%)', 'intersoccer-referral'); ?>
                                                    </label>
                                                    <input type="number" 
                                                           id="simulator-dist-coach" 
                                                           class="small-text" 
                                                           value="60" 
                                                           min="0" 
                                                           max="100" 
                                                           step="1">
                                                </div>
                                                <div>
                                                    <label for="simulator-dist-partner" style="display: block; margin-bottom: 5px;">
                                                        <?php esc_html_e('Partner (%)', 'intersoccer-referral'); ?>
                                                    </label>
                                                    <input type="number" 
                                                           id="simulator-dist-partner" 
                                                           class="small-text" 
                                                           value="25" 
                                                           min="0" 
                                                           max="100" 
                                                           step="1">
                                                </div>
                                                <div>
                                                    <label for="simulator-dist-influencer" style="display: block; margin-bottom: 5px;">
                                                        <?php esc_html_e('Influencer (%)', 'intersoccer-referral'); ?>
                                                    </label>
                                                    <input type="number" 
                                                           id="simulator-dist-influencer" 
                                                           class="small-text" 
                                                           value="15" 
                                                           min="0" 
                                                           max="100" 
                                                           step="1">
                                                </div>
                                            </div>
                                            <p class="description" style="margin-top: 10px;">
                                                <?php esc_html_e('Total should equal 100%. This determines how referrals are distributed across roles.', 'intersoccer-referral'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Projections Tab -->
                            <div id="simulator-tab-projections" class="simulator-tab-content" style="display: none; background: #fff; padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 4px 4px;">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="simulator-project-months"><?php esc_html_e('Project Forward (Months)', 'intersoccer-referral'); ?></label>
                                        </th>
                                        <td>
                                            <input type="number" 
                                                   id="simulator-project-months" 
                                                   class="small-text" 
                                                   value="0" 
                                                   min="0" 
                                                   max="24">
                                            <p class="description">
                                                <?php esc_html_e('Extend analysis forward by X months using growth assumptions (0 = no projection)', 'intersoccer-referral'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="simulator-revenue-growth"><?php esc_html_e('Revenue Growth Rate (% per month)', 'intersoccer-referral'); ?></label>
                                        </th>
                                        <td>
                                            <input type="number" 
                                                   id="simulator-revenue-growth" 
                                                   class="small-text" 
                                                   value="5" 
                                                   min="-50" 
                                                   max="100" 
                                                   step="0.1">
                                            <p class="description">
                                                <?php esc_html_e('Expected monthly revenue growth percentage (e.g., 5 = 5% growth per month)', 'intersoccer-referral'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="simulator-referral-adoption-start"><?php esc_html_e('Referral Adoption Start (%)', 'intersoccer-referral'); ?></label>
                                        </th>
                                        <td>
                                            <input type="number" 
                                                   id="simulator-referral-adoption-start" 
                                                   class="small-text" 
                                                   value="15" 
                                                   min="0" 
                                                   max="100" 
                                                   step="0.1">
                                            <p class="description">
                                                <?php esc_html_e('Starting referral rate at beginning of projection period', 'intersoccer-referral'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="simulator-referral-adoption-end"><?php esc_html_e('Referral Adoption Target (%)', 'intersoccer-referral'); ?></label>
                                        </th>
                                        <td>
                                            <input type="number" 
                                                   id="simulator-referral-adoption-end" 
                                                   class="small-text" 
                                                   value="30" 
                                                   min="0" 
                                                   max="100" 
                                                   step="0.1">
                                            <p class="description">
                                                <?php esc_html_e('Target referral rate at end of projection period (linear growth assumed)', 'intersoccer-referral'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <p class="submit">
                            <button type="button" 
                                    id="simulator-run" 
                                    class="button button-primary button-large">
                                <span class="dashicons dashicons-chart-line" style="vertical-align: middle;"></span>
                                <?php esc_html_e('Run Simulation', 'intersoccer-referral'); ?>
                            </button>
                            <button type="button" 
                                    id="simulator-run-sensitivity" 
                                    class="button button-secondary">
                                <span class="dashicons dashicons-chart-bar" style="vertical-align: middle;"></span>
                                <?php esc_html_e('Run Sensitivity Analysis', 'intersoccer-referral'); ?>
                            </button>
                            <span class="spinner" id="simulator-spinner" style="float: none; margin-left: 10px;"></span>
                        </p>
                    </div>

                    <div id="simulator-results" style="margin-top: 30px; display: none;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3 style="margin: 0;"><?php esc_html_e('Simulation Results', 'intersoccer-referral'); ?></h3>
                            <div style="display: flex; gap: 10px;">
                                <button type="button" id="simulator-export-excel" class="button button-secondary">
                                    <span class="dashicons dashicons-media-spreadsheet" style="vertical-align: middle;"></span>
                                    <?php esc_html_e('Export to Excel', 'intersoccer-referral'); ?>
                                </button>
                                <button type="button" id="simulator-export-pdf" class="button button-secondary">
                                    <span class="dashicons dashicons-media-document" style="vertical-align: middle;"></span>
                                    <?php esc_html_e('Export to PDF', 'intersoccer-referral'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Executive Summary Dashboard -->
                        <div id="simulator-executive-summary" style="display: none; background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px;">
                            <h4 style="margin-top: 0;"><?php esc_html_e('Executive Summary', 'intersoccer-referral'); ?></h4>
                            <div id="simulator-executive-summary-content"></div>
                        </div>

                        <!-- Comparison View -->
                        <div id="simulator-comparison-view" style="display: none; background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px;">
                            <h4 style="margin-top: 0;"><?php esc_html_e('Scenario Comparison', 'intersoccer-referral'); ?></h4>
                            <div id="simulator-comparison-content"></div>
                        </div>

                        <!-- Single Scenario Results -->
                        <div id="simulator-results-content" style="background: #f9f9f9; padding: 20px; border-radius: 4px; border: 1px solid #ddd;"></div>
                        
                        <!-- Charts Container -->
                        <div id="simulator-chart-container" style="margin-top: 30px; display: none;">
                            <div class="dashboard-widget chart-widget" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
                                <h4 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600;"><?php esc_html_e('Monthly Financial Performance', 'intersoccer-referral'); ?></h4>
                                <div style="position: relative; height: 400px;">
                                    <canvas id="simulatorFinancialChart"></canvas>
                                </div>
                                <div class="chart-legend" style="margin-top: 15px; display: flex; gap: 20px; flex-wrap: wrap; justify-content: center;">
                                    <span class="legend-item"><span class="color-box" style="background: #f39c12; display: inline-block; width: 12px; height: 12px; margin-right: 5px; border-radius: 2px;"></span><?php esc_html_e('Revenue', 'intersoccer-referral'); ?></span>
                                    <span class="legend-item"><span class="color-box" style="background: #9b59b6; display: inline-block; width: 12px; height: 12px; margin-right: 5px; border-radius: 2px;"></span><?php esc_html_e('Costs', 'intersoccer-referral'); ?></span>
                                    <span class="legend-item"><span class="color-box" style="background: #27ae60; display: inline-block; width: 12px; height: 12px; margin-right: 5px; border-radius: 2px;"></span><?php esc_html_e('Net Profit/Loss', 'intersoccer-referral'); ?></span>
                                </div>
                            </div>
                            
                            <!-- Multi-Scenario Comparison Chart -->
                            <div id="simulator-comparison-chart-container" style="display: none; background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
                                <h4 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600;"><?php esc_html_e('Scenario Comparison Chart', 'intersoccer-referral'); ?></h4>
                                <div style="position: relative; height: 400px;">
                                    <canvas id="simulatorComparisonChart"></canvas>
                                </div>
                            </div>

                            <!-- ROI Comparison Chart -->
                            <div id="simulator-roi-chart-container" style="display: none; background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
                                <h4 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600;"><?php esc_html_e('ROI Comparison', 'intersoccer-referral'); ?></h4>
                                <div style="position: relative; height: 400px;">
                                    <canvas id="simulatorROIChart"></canvas>
                                </div>
                            </div>

                            <!-- Sensitivity Analysis Chart -->
                            <div id="simulator-sensitivity-chart-container" style="display: none; background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
                                <h4 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600;"><?php esc_html_e('Sensitivity Analysis', 'intersoccer-referral'); ?></h4>
                                <p class="description">
                                    <?php esc_html_e('Shows which variables have the biggest impact on net profit/loss', 'intersoccer-referral'); ?>
                                </p>
                                <div style="position: relative; height: 400px;">
                                    <canvas id="simulatorSensitivityChart"></canvas>
                                </div>
                            </div>

                            <!-- Referrals Breakdown Chart -->
                            <div id="simulator-referrals-chart-container" style="display: none; background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
                                <h4 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600;"><?php esc_html_e('Referrals Breakdown', 'intersoccer-referral'); ?></h4>
                                <p class="description">
                                    <?php esc_html_e('Distribution of referrals between customers and coaches', 'intersoccer-referral'); ?>
                                </p>
                                <div style="position: relative; height: 300px;">
                                    <canvas id="simulatorReferralsChart"></canvas>
                                </div>
                            </div>

                            <!-- Commissions Breakdown Chart -->
                            <div id="simulator-commissions-chart-container" style="display: none; background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                <h4 style="margin-top: 0; margin-bottom: 15px; font-size: 18px; font-weight: 600;"><?php esc_html_e('Commissions by Role', 'intersoccer-referral'); ?></h4>
                                
                                <!-- Instructions Banner -->
                                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 15px; border-radius: 6px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                                        <span class="dashicons dashicons-info" style="font-size: 20px; margin-top: 2px; opacity: 0.9;"></span>
                                        <div style="flex: 1;">
                                            <strong style="display: block; margin-bottom: 8px; font-size: 14px;">
                                                <?php esc_html_e('What affects this chart?', 'intersoccer-referral'); ?>
                                            </strong>
                                            <ul style="margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.6; opacity: 0.95;">
                                                <li>
                                                    <strong><?php esc_html_e('Coach Referral Rate:', 'intersoccer-referral'); ?></strong> 
                                                    <?php esc_html_e('Determines how many orders have coach referrals (found in "Referral Settings" tab)', 'intersoccer-referral'); ?>
                                                </li>
                                                <li>
                                                    <strong><?php esc_html_e('Referral Distribution:', 'intersoccer-referral'); ?></strong> 
                                                    <?php esc_html_e('Splits coach referrals between Coach %, Partner %, and Influencer % (found in "Referral Settings" tab)', 'intersoccer-referral'); ?>
                                                </li>
                                                <li>
                                                    <strong><?php esc_html_e('Commission Tiers:', 'intersoccer-referral'); ?></strong> 
                                                    <?php esc_html_e('The actual commission rates for each role based on customer count (found in "Commission Tiers" tab)', 'intersoccer-referral'); ?>
                                                </li>
                                                <li>
                                                    <strong><?php esc_html_e('Order Values:', 'intersoccer-referral'); ?></strong> 
                                                    <?php esc_html_e('Commissions are calculated as a percentage of order subtotal (excluding tax)', 'intersoccer-referral'); ?>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <p class="description" style="margin-bottom: 15px;">
                                    <?php esc_html_e('Total commissions paid to coaches, partners, and influencers', 'intersoccer-referral'); ?>
                                </p>
                                <div style="position: relative; height: 300px;">
                                    <canvas id="simulatorCommissionsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coach Role Restoration -->
            <div class="intersoccer-settings-section">
                <h2><?php esc_html_e('Coach Role Restoration', 'intersoccer-referral'); ?></h2>
                <div class="role-restore-notice">
                    <div class="notice notice-warning">
                        <p><strong><?php esc_html_e('Missing Coach Roles:', 'intersoccer-referral'); ?></strong> <?php esc_html_e('If existing coaches are missing their "Coach" role assignment, use this tool to restore them based on referral data.', 'intersoccer-referral'); ?></p>
                        <p><strong><?php esc_html_e('What it does:', 'intersoccer-referral'); ?></strong></p>
                        <ul>
                            <li><?php esc_html_e('Scans referral records to identify users who should have coach roles', 'intersoccer-referral'); ?></li>
                            <li><?php esc_html_e('Restores the "Coach" role to users who have active referrals', 'intersoccer-referral'); ?></li>
                            <li><?php esc_html_e('Does not affect users who already have the correct role', 'intersoccer-referral'); ?></li>
                        </ul>
                    </div>
                </div>

                <div class="role-restore-controls">
                    <div class="restore-card">
                        <h3><?php esc_html_e('Restore Missing Coach Roles', 'intersoccer-referral'); ?></h3>
                        <p><?php esc_html_e('Restore coach roles based on existing referral data', 'intersoccer-referral'); ?></p>
                        <div class="restore-status" id="restore-status">
                            <span class="status-indicator status-ready"><?php esc_html_e('Ready to restore', 'intersoccer-referral'); ?></span>
                        </div>
                        <button id="restore-coach-roles" class="button button-primary">
                            <span class="dashicons dashicons-admin-users"></span>
                            <?php esc_html_e('Restore Coach Roles', 'intersoccer-referral'); ?>
                        </button>
                        <div id="restore-progress" style="display: none; margin-top: 20px;">
                            <div class="progress-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" id="restore-progress-fill" style="width: 0%"></div>
                                </div>
                                <div class="progress-text" id="restore-progress-text"><?php esc_html_e('Scanning referral data...', 'intersoccer-referral'); ?></div>
                            </div>
                            <div class="restore-details" id="restore-details" style="margin-top: 15px;">
                                <div class="detail-item"><strong><?php esc_html_e('Coaches Found:', 'intersoccer-referral'); ?></strong> <span id="coaches-found">0</span></div>
                                <div class="detail-item"><strong><?php esc_html_e('Roles Restored:', 'intersoccer-referral'); ?></strong> <span id="roles-restored">0</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coach CSV Import -->
            <div class="intersoccer-settings-section">
                <h2><?php esc_html_e('Coach CSV Import', 'intersoccer-referral'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Import coaches from a CSV file. The CSV should include columns for First Name, Last Name, and Email at minimum.', 'intersoccer-referral'); ?>
                </p>
                
                <div class="coach-import-container" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; margin-top: 20px;">
                    <form id="coach-import-form" method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('import_coaches_from_csv', '_wpnonce'); ?>
                        
                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="coaches_csv"><?php esc_html_e('CSV File', 'intersoccer-referral'); ?></label>
                                    </th>
                                    <td>
                                        <input type="file" 
                                               id="coaches_csv" 
                                               name="coaches_csv" 
                                               accept=".csv"
                                               required>
                                        <p class="description">
                                            <?php esc_html_e('Select a CSV file containing coach information. Maximum file size: 10MB.', 'intersoccer-referral'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="update_existing"><?php esc_html_e('Update Existing', 'intersoccer-referral'); ?></label>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   id="update_existing" 
                                                   name="update_existing" 
                                                   value="1">
                                            <?php esc_html_e('Update existing coaches if they already exist (by email)', 'intersoccer-referral'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" 
                                    id="import-submit-btn" 
                                    class="button button-primary">
                                <span class="dashicons dashicons-upload"></span>
                                <?php esc_html_e('Import Coaches', 'intersoccer-referral'); ?>
                            </button>
                        </p>
                        
                        <!-- Import Status -->
                        <div id="import-status" style="display: none; margin-top: 20px;">
                            <div class="progress-container" style="margin-bottom: 10px;">
                                <div class="progress-bar" style="background: #f0f0f0; border-radius: 4px; overflow: hidden; height: 30px;">
                                    <div id="progress-fill" class="progress-fill" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
                                </div>
                                <div id="progress-text" class="progress-text" style="text-align: center; margin-top: 10px;"></div>
                            </div>
                        </div>
                        
                        <!-- Import Results -->
                        <div id="import-results" style="display: none; margin-top: 20px;">
                            <div id="import-summary-content"></div>
                            <button type="button" id="clear-import-results" class="button" style="margin-top: 10px;"><?php esc_html_e('Clear Results', 'intersoccer-referral'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

        /* <script>
        jQuery(document).ready(function($) {
            // Credit reset functionality
            $('#reset-all-credits').on('click', function(e) {
                e.preventDefault();
                if (!confirm('Are you sure you want to reset ALL customer credits? This action cannot be undone!')) {
                    return;
                }

                $(this).prop('disabled', true).text('Resetting...');

                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'reset_all_customer_credits',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        alert(response.data.message);
                        location.reload();
                    },
                    error: function() {
                        alert('Error resetting credits. Please try again.');
                        $('#reset-all-credits').prop('disabled', false).text('Reset All Credits');
                    }
                });
            });

            // Bulk credit allocation
            $('#bulk-credit-form').on('submit', function(e) {
                e.preventDefault();

                const type = $('#allocation-type').val();
                const amount = $('#credit-amount').val();

                if (!amount || amount < 1) {
                    alert('Please enter a valid credit amount.');
                    return;
                }

                if (!confirm(`Allocate ${amount} credits to ${type.replace('_', ' ')} customers?`)) {
                    return;
                }

                const $submitBtn = $(this).find('button[type="submit"]');
                $submitBtn.prop('disabled', true).text('Allocating...');

                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'allocate_credits_to_customers',
                        nonce: intersoccer_admin.nonce,
                        allocation_type: type,
                        credit_amount: amount
                    },
                    success: function(response) {
                        alert(response.data.message);
                        location.reload();
                    },
                    error: function() {
                        alert('Error allocating credits. Please try again.');
                        $submitBtn.prop('disabled', false).text('Allocate Credits');
                    }
                });
            });

            // Load credit statistics
            function loadCreditStats() {
                $('#credit-stats').html('<p>Loading statistics...</p>');
                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_credit_statistics',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        $('#credit-stats').html(response.data.html);
                    },
                    error: function() {
                        $('#credit-stats').html('<p>Error loading statistics</p>');
                    }
                });
            }

            // Load coach statistics
            function loadCoachStats() {
                $('#coach-stats').html('<p>Loading coach statistics...</p>');
                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_coach_statistics',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        $('#coach-stats').html(response.data.html);
                    },
                    error: function() {
                        $('#coach-stats').html('<p>Error loading coach statistics</p>');
                    }
                });
            }

            // Load audit log
            function loadAuditLog(filter = 'all') {
                $('#audit-log-container').html('<div class="audit-log-entry loading">Loading audit log...</div>');
                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_audit_log',
                        nonce: intersoccer_admin.nonce,
                        filter: filter
                    },
                    success: function(response) {
                        $('#audit-log-container').html(response.data.html);
                    },
                    error: function() {
                        $('#audit-log-container').html('<div class="audit-log-entry error">Error loading audit log</div>');
                    }
                });
            }

            // Load points statistics
            function loadPointsStats() {
                $('#points-stats').html('<p>Loading points statistics...</p>');
                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_points_statistics',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        $('#points-stats').html(response.data.html);
                    },
                    error: function() {
                        $('#points-stats').html('<p>Error loading points statistics</p>');
                    }
                });
            }

            // View points ledger
            $('#view-points-ledger').on('click', function() {
                const $container = $('#points-ledger-container');
                const $content = $('#points-ledger-content');

                if ($container.is(':visible')) {
                    $container.hide();
                    return;
                }

                $container.show();
                $content.html('<p>Loading points ledger...</p>');

                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_points_ledger',
                        nonce: intersoccer_admin.nonce,
                        limit: 20
                    },
                    success: function(response) {
                        $content.html(response.data.html);
                    },
                    error: function() {
                        $content.html('<p>Error loading points ledger</p>');
                    }
                });
            });

            // Event handlers
            $('#refresh-stats').on('click', loadCreditStats);
            $('#refresh-audit-log').on('click', function() { loadAuditLog($('#audit-filter').val()); });
            $('#audit-filter').on('change', function() { loadAuditLog($(this).val()); });

            $('#clear-audit-log').on('click', function() {
                if (!confirm('Clear all audit log entries?')) return;

                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'clear_audit_log',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        alert(response.data.message);
                        loadAuditLog();
                    }
                });
            });

            $('#export-audit-log').on('click', function() {
                window.open(intersoccer_admin.ajax_url + '?action=export_audit_log&nonce=' + intersoccer_admin.nonce, '_blank');
            });

            $('#refresh-points-stats').on('click', loadPointsStats);

            // Event handlers
            $('#refresh-stats').on('click', loadCreditStats);
            $('#refresh-audit-log').on('click', function() { loadAuditLog($('#audit-filter').val()); });
            $('#audit-filter').on('change', function() { loadAuditLog($(this).val()); });

            $('#clear-audit-log').on('click', function() {
                if (!confirm('Clear all audit log entries?')) return;

                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'clear_audit_log',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        alert(response.data.message);
                        loadAuditLog();
                    }
                });
            });

            $('#export-audit-log').on('click', function() {
                window.open(intersoccer_admin.ajax_url + '?action=export_audit_log&nonce=' + intersoccer_admin.nonce, '_blank');
            });

            // Coach Import Form Handler is now handled by admin-settings.js external file
            // Removed duplicate inline handler to avoid conflicts

            // Coach Role Restoration
            $('#restore-coach-roles').on('click', function(e) {
                e.preventDefault();

                if (!confirm('This will scan referral data and restore coach roles to users who should have them. Continue?')) {
                    return;
                }

                const $button = $(this);
                const $progress = $('#restore-progress');
                const $status = $('#restore-status');
                const $progressFill = $('#restore-progress-fill');
                const $progressText = $('#restore-progress-text');

                // Update status
                $status.html('<span class="status-indicator status-running">Restoring roles...</span>');
                $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Restoring...');

                $progress.show();
                $progressFill.css('width', '0%');
                $progressText.text('Scanning referral data...');

                // Start the restoration
                restoreCoachRoles();
            });

            function restoreCoachRoles() {
                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'restore_coach_roles',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#restore-progress-fill').css('width', '100%');
                            $('#restore-progress-text').text('Restoration completed successfully!');
                            $('#restore-status').html('<span class="status-indicator status-success">Restoration completed</span>');
                            $('#restore-coach-roles').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Restoration Complete');

                            // Update details
                            $('#coaches-found').text(response.data.coaches_found || 0);
                            $('#roles-restored').text(response.data.roles_restored || 0);

                            // Show success message
                            setTimeout(() => {
                                alert(response.data.message);
                                loadCoachStats();
                            }, 1000);
                        } else {
                            $('#restore-status').html('<span class="status-indicator status-error">Restoration failed</span>');
                            $('#restore-coach-roles').prop('disabled', false).html('<span class="dashicons dashicons-warning"></span> Retry Restoration');
                            alert('Restoration failed: ' + (response.data?.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#restore-status').html('<span class="status-indicator status-error">Restoration error</span>');
                        $('#restore-coach-roles').prop('disabled', false).html('<span class="dashicons dashicons-warning"></span> Retry Restoration');
                        $('#restore-progress-text').text('Error occurred during restoration');
                        alert('AJAX Error: ' + error);
                    }
                });
            }

            // Initialize
            loadCreditStats();
            loadCoachStats();
            loadAuditLog();
            loadPointsStats();
        });
        </script>

        <style>
        .intersoccer-settings-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .intersoccer-settings-section h2 {
            margin-top: 0;
            color: #23282d;
            border-bottom: 2px solid #f1f1f1;
            padding-bottom: 10px;
        }

        .info-grid, .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .info-item, .settings-card {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e1e1e1;
        }

        .settings-card h3 {
            margin-top: 0;
            color: #23282d;
        }

        .status-badge.active { background: #d5f4e6; color: #27ae60; padding: 4px 8px; border-radius: 4px; }
        .status-badge.inactive { background: #fadbd8; color: #e74c3c; padding: 4px 8px; border-radius: 4px; }

        .button-danger {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        .button-danger:hover {
            background: #c82333;
            border-color: #bd2130;
        }

        .audit-log-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .audit-log-entry {
            padding: 12px;
            border-bottom: 1px solid #e1e1e1;
            font-family: monospace;
            font-size: 12px;
        }

        .audit-log-entry.loading {
            text-align: center;
            color: #666;
        }

        .audit-log-entry.error {
            color: #e74c3c;
            text-align: center;
        }

        .audit-log-entry .timestamp {
            color: #666;
            margin-right: 10px;
        }

        .audit-log-entry .action {
            font-weight: bold;
            margin-right: 10px;
        }

        .audit-log-entry .user {
            color: #007cba;
            margin-right: 10px;
        }

        .progress-bar {
            width: 100%;
            background: #e1e1e1;
            border-radius: 4px;
            overflow: hidden;
            height: 8px;
            margin-top: 5px;
        }

        .progress-fill {
            height: 100%;
            background: #28a745;
            transition: width 0.4s ease;
        }

        .sync-notice {
            margin-bottom: 25px;
        }

        .sync-controls {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }

        .sync-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #e1e1e1;
        }

        .sync-card h3 {
            margin-top: 0;
            color: #23282d;
            margin-bottom: 15px;
        }

        .sync-status {
            margin: 15px 0;
        }

        .status-indicator {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-ready {
            background: #fff3cd;
            color: #856404;
        }

        .status-running {
            background: #cce5ff;
            color: #004085;
        }

        .status-success {
            background: #d5f4e6;
            color: #155724;
        }

        .status-error {
            background: #fadbd8;
            color: #721c24;
        }

        .progress-container {
            margin-bottom: 15px;
        }

        .progress-bar {
            width: 100%;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            height: 12px;
            margin-bottom: 8px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #007cba, #28a745);
            transition: width 0.5s ease;
        }

        .progress-text {
            font-size: 14px;
            color: #666;
            text-align: center;
        }

        .sync-details {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .detail-item:last-child {
            margin-bottom: 0;
        }

        .spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .role-restore-notice {
            margin-bottom: 25px;
        }

        .role-restore-controls {
            display: grid;
            grid-template-columns: 1fr;
            gap: 25px;
        }

        .restore-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #e1e1e1;
        }

        .restore-card h3 {
            margin-top: 0;
            color: #23282d;
            margin-bottom: 15px;
        }

        .restore-status {
            margin: 15px 0;
        }

        .restore-details {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }

        .restore-details .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .restore-details .detail-item:last-child {
            margin-bottom: 0;
        }
        </style> */

    /**
     * Check if database tables exist
     */
    private function check_database_tables() {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'intersoccer_referrals',
            $wpdb->prefix . 'intersoccer_referral_credits',
            $wpdb->prefix . 'intersoccer_credit_redemptions'
        ];

        foreach ($tables as $table) {
            // Use $wpdb->prepare() to prevent potential SQL injection via the table name placeholder.
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
                return false;
            }
        }

        return true;
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

        $this->log_audit('credit_reset', 'Starting complete credit reset for all customers');

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
        }

        // Clear import summary
        delete_option('intersoccer_last_import_summary');
        delete_option('intersoccer_last_customer_import_report');

        $this->log_audit('credit_reset', "Credit reset complete - deleted {$deleted_total} total records");

        wp_send_json_success([
            'message' => "Reset complete! Deleted {$deleted_total} credit records from all customers.",
            'deleted_records' => $deleted_total
        ]);
    }

    /**
     * Allocate credits to customers based on criteria
     */
    public function allocate_credits_to_customers() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $allocation_type = sanitize_text_field($_POST['allocation_type']);
        $credit_amount = intval($_POST['credit_amount']);

        if ($credit_amount < 1 || $credit_amount > 100) {
            wp_send_json_error(['message' => 'Credit amount must be between 1 and 100']);
        }

        global $wpdb;

        $this->log_audit('bulk_allocation', "Starting bulk credit allocation: {$credit_amount} credits to {$allocation_type} customers");

        // Build user query based on allocation type
        $where_clause = "WHERE 1=1";
        switch ($allocation_type) {
            case 'coaches':
                $where_clause .= " AND ID IN (
                    SELECT DISTINCT coach_id FROM {$wpdb->prefix}intersoccer_referrals
                    WHERE coach_id IS NOT NULL
                )";
                break;
            case 'zero_balance':
                $where_clause .= " AND ID NOT IN (
                    SELECT user_id FROM {$wpdb->usermeta}
                    WHERE meta_key = 'intersoccer_customer_credits'
                    AND meta_value > 0
                )";
                break;
        }

        $users = $wpdb->get_results("SELECT ID, user_email FROM {$wpdb->users} {$where_clause}");

        $allocated_count = 0;
        foreach ($users as $user) {
            $current_credits = get_user_meta($user->ID, 'intersoccer_customer_credits', true) ?: 0;
            $new_credits = $current_credits + $credit_amount;

            update_user_meta($user->ID, 'intersoccer_customer_credits', $new_credits);

            // Log the adjustment
            $adjustments = get_user_meta($user->ID, 'intersoccer_credit_adjustments', true) ?: [];
            $adjustments[] = [
                'amount' => $credit_amount,
                'reason' => "Bulk allocation ({$allocation_type})",
                'timestamp' => current_time('mysql'),
                'admin' => get_current_user_id()
            ];
            update_user_meta($user->ID, 'intersoccer_credit_adjustments', $adjustments);

            $allocated_count++;
        }

        $this->log_audit('bulk_allocation', "Bulk allocation complete: {$credit_amount} credits allocated to {$allocated_count} customers");

        wp_send_json_success([
            'message' => "Successfully allocated {$credit_amount} credits to {$allocated_count} customers.",
            'allocated_count' => $allocated_count
        ]);
    }

    /**
     * Handle coach CSV import
     */
    public function import_coaches_from_csv() {
        // Handle AJAX requests
        if (wp_doing_ajax()) {
            $this->ajax_import_coaches_from_csv();
            return;
        }

        // Handle regular form submission (legacy support)
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (!isset($_FILES['coaches_csv']) || $_FILES['coaches_csv']['error'] !== UPLOAD_ERR_OK) {
            wp_die('No file uploaded or upload error');
        }

        $file = $_FILES['coaches_csv']['tmp_name'];

        try {
            $results = $this->process_coach_csv_import($file, false);
            $imported = count($results['created']) + count($results['updated']);
            $errors = count($results['errors']);

            // Store import report
            update_option('intersoccer_last_coach_import', [
                'timestamp' => current_time('mysql'),
                'results' => $results
            ]);

            wp_redirect(add_query_arg([
                'page' => 'intersoccer-settings',
                'imported' => $imported,
                'errors' => $errors
            ], admin_url('admin.php')));
        } catch (Exception $e) {
            wp_redirect(add_query_arg([
                'page' => 'intersoccer-settings',
                'error' => urlencode($e->getMessage())
            ], admin_url('admin.php')));
        }
        exit;
    }

    /**
     * AJAX handler for coach CSV import
     */
    public function ajax_import_coaches_from_csv() {
        try {
            // Verify nonce and permissions before any logging to avoid leaking request data
            if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'import_coaches_from_csv')) {
                intersoccer_referral_log('InterSoccer: Coach CSV AJAX import — nonce verification failed');
                wp_send_json_error('Invalid nonce');
                return;
            }

            if (!current_user_can('manage_options')) {
                intersoccer_referral_log('InterSoccer: Coach CSV AJAX import — permission check failed for user ' . get_current_user_id());
                wp_send_json_error('Insufficient permissions');
                return;
            }

            if (!isset($_FILES['coaches_csv']) || $_FILES['coaches_csv']['error'] !== UPLOAD_ERR_OK) {
                $error_code = isset($_FILES['coaches_csv']['error']) ? (int) $_FILES['coaches_csv']['error'] : -1;
                intersoccer_referral_log('InterSoccer: Coach CSV AJAX import — file upload error code: ' . $error_code);
                wp_send_json_error('File upload error: ' . $error_code);
                return;
            }

            $file = $_FILES['coaches_csv']['tmp_name'];
            $update_existing = isset($_POST['update_existing']) && $_POST['update_existing'] == '1';

            intersoccer_referral_log('InterSoccer: Coach CSV AJAX import started, update_existing: ' . ($update_existing ? 'yes' : 'no'));

            $results = $this->process_coach_csv_import($file, $update_existing);

            intersoccer_referral_log('Import completed successfully: ' . print_r($results, true));
            wp_send_json_success($results);

        } catch (Exception $e) {
            intersoccer_referral_log('Exception in AJAX import: ' . $e->getMessage());
            intersoccer_referral_log('Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error('Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Process coach CSV import
     */
    private function process_coach_csv_import($file_path, $update_existing = false) {
        if (($handle = fopen($file_path, 'r')) === false) {
            throw new Exception('Could not open uploaded file');
        }

        $this->log_audit('coach_import', 'Starting coach CSV import (AJAX)');

        // Read header row - skip empty/title rows
        $header = null;
        $max_rows_to_check = 5; // Check up to 5 rows for valid headers
        $rows_checked = 0;
        
        while (($potential_header = fgetcsv($handle, 1000, ',')) !== false && $rows_checked < $max_rows_to_check) {
            $rows_checked++;
            
            // Skip completely empty rows
            if (empty(array_filter($potential_header, function($cell) { return !empty(trim($cell)); }))) {
                intersoccer_referral_log("Skipping empty row {$rows_checked}");
                continue;
            }
            
            // Skip rows that are likely titles (have only 1-2 non-empty cells)
            $non_empty_count = count(array_filter($potential_header, function($cell) { return !empty(trim($cell)); }));
            if ($non_empty_count < 3) {
                intersoccer_referral_log("Skipping likely title row {$rows_checked}: " . implode(', ', $potential_header));
                continue;
            }
            
            // This looks like a valid header row
            $header = $potential_header;
            intersoccer_referral_log("Found valid header row at line {$rows_checked}: " . implode(', ', $header));
            break;
        }
        
        if (!$header) {
            fclose($handle);
            throw new Exception('Could not find valid CSV headers. Check that your CSV has a header row with at least 3 columns (First Name, Last Name, Email). Checked ' . $rows_checked . ' rows.');
        }

        // Normalize headers (lowercase, trim, replace spaces with underscores)
        $normalized_header = array_map(function($col) {
            return strtolower(str_replace(' ', '_', trim($col)));
        }, $header);

        // Log the headers we found
        intersoccer_referral_log('CSV Headers found: ' . implode(', ', $header));
        intersoccer_referral_log('Normalized headers: ' . implode(', ', $normalized_header));

        // Map common column name variations to standard names
        $column_mapping = [
            // First name variations
            'first_name' => 'first_name',
            'firstname' => 'first_name',
            'given_name' => 'first_name',
            'forename' => 'first_name',
            'name' => 'first_name', // If only one "name" column, use it as first_name
            
            // Last name variations
            'last_name' => 'last_name',
            'lastname' => 'last_name',
            'surname' => 'last_name',
            'family_name' => 'last_name',
            
            // Email variations
            'email' => 'email',
            'e-mail' => 'email',
            'email_address' => 'email',
            'mail' => 'email',
            
            // Optional fields
            'phone' => 'phone',
            'telephone' => 'phone',
            'phone_number' => 'phone',
            'mobile' => 'phone',
            
            'specialization' => 'specialization',
            'specialty' => 'specialization',
            'focus' => 'specialization',
            
            'location' => 'location',
            'city' => 'location',
            'region' => 'location',
            
            'experience_years' => 'experience_years',
            'experience' => 'experience_years',
            'years_experience' => 'experience_years',
            
            'bio' => 'bio',
            'biography' => 'bio',
            'description' => 'bio',
            'about' => 'bio'
        ];

        // Map the normalized headers to standard field names
        $field_map = [];
        foreach ($normalized_header as $index => $norm_col) {
            if (isset($column_mapping[$norm_col])) {
                $standard_name = $column_mapping[$norm_col];
                $field_map[$standard_name] = $index;
            }
        }

        // Validate required columns are present
        $required_columns = ['first_name', 'last_name', 'email'];
        $missing_columns = [];
        foreach ($required_columns as $required) {
            if (!isset($field_map[$required])) {
                $missing_columns[] = $required;
            }
        }

        if (!empty($missing_columns)) {
            fclose($handle);
            $error_msg = 'Missing required columns: ' . implode(', ', $missing_columns) . "\n";
            $error_msg .= 'Found columns: ' . implode(', ', $header) . "\n";
            $error_msg .= 'Supported variations: first_name/firstname/given_name, last_name/lastname/surname, email/e-mail/email_address';
            throw new Exception($error_msg);
        }

        intersoccer_referral_log('Field mapping: ' . json_encode($field_map));

        $results = [
            'created' => [],
            'updated' => [],
            'skipped' => [],
            'errors' => []
        ];

        $row_number = 1;
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $row_number++;

            if (count($data) !== count($header)) {
                $results['errors'][] = "Row {$row_number}: Invalid number of columns";
                continue;
            }

            // Map data to standard field names using field_map
            $coach_data = [];
            foreach ($field_map as $standard_name => $column_index) {
                $coach_data[$standard_name] = isset($data[$column_index]) ? $data[$column_index] : '';
            }

            // Validate required fields
            if (empty(trim($coach_data['email'])) || empty(trim($coach_data['first_name'])) || empty(trim($coach_data['last_name']))) {
                $results['errors'][] = "Row {$row_number}: Missing required fields (email, first_name, last_name)";
                continue;
            }

            if (!is_email($coach_data['email'])) {
                $results['errors'][] = "Row {$row_number}: Invalid email address: {$coach_data['email']}";
                continue;
            }

            try {
                $result = $this->create_or_update_coach($coach_data, $update_existing);
                $coach_info = [
                    'first_name' => $coach_data['first_name'],
                    'last_name' => $coach_data['last_name'],
                    'email' => $coach_data['email']
                ];

                if ($result['action'] === 'created') {
                    $results['created'][] = $coach_info;
                } elseif ($result['action'] === 'updated') {
                    $results['updated'][] = $coach_info;
                } elseif ($result['action'] === 'skipped') {
                    $coach_info['reason'] = $result['reason'];
                    $results['skipped'][] = $coach_info;
                }
            } catch (Exception $e) {
                $results['errors'][] = "Row {$row_number}: " . $e->getMessage();
            }
        }

        fclose($handle);

        $this->log_audit('coach_import', sprintf(
            'Coach import complete: %d created, %d updated, %d skipped, %d errors',
            count($results['created']),
            count($results['updated']),
            count($results['skipped']),
            count($results['errors'])
        ));

        return $results;
    }

    /**
     * Create or update coach user
     */
    private function create_or_update_coach($coach_data, $update_existing = false) {
        // Check if user exists
        $user = get_user_by('email', $coach_data['email']);

        if (!$user) {
            // Create new user with coach role directly
            $user_id = wp_insert_user([
                'user_login' => sanitize_user($coach_data['email']),
                'user_pass' => wp_generate_password(),
                'user_email' => $coach_data['email'],
                'role' => 'coach'
            ]);

            if (is_wp_error($user_id)) {
                throw new Exception('Failed to create user: ' . $user_id->get_error_message());
            }

            $user = get_user_by('ID', $user_id);
            $action = 'created';
        } else {
            // User exists
            if (!$update_existing) {
                return ['action' => 'skipped', 'reason' => 'User already exists'];
            }
            $user_id = $user->ID;
            $action = 'updated';
        }

        // Update user meta
        wp_update_user([
            'ID' => $user_id,
            'first_name' => sanitize_text_field($coach_data['first_name']),
            'last_name' => sanitize_text_field($coach_data['last_name']),
            'display_name' => $coach_data['first_name'] . ' ' . $coach_data['last_name']
        ]);

        // Update coach-specific meta
        if (isset($coach_data['specialization'])) {
            update_user_meta($user_id, 'intersoccer_coach_specialization', sanitize_text_field($coach_data['specialization']));
        }
        if (isset($coach_data['location'])) {
            update_user_meta($user_id, 'intersoccer_coach_location', sanitize_text_field($coach_data['location']));
        }
        if (isset($coach_data['experience_years'])) {
            update_user_meta($user_id, 'intersoccer_coach_experience', intval($coach_data['experience_years']));
        }
        if (isset($coach_data['bio'])) {
            update_user_meta($user_id, 'intersoccer_coach_bio', sanitize_textarea_field($coach_data['bio']));
        }
        if (isset($coach_data['phone'])) {
            update_user_meta($user_id, 'intersoccer_coach_phone', sanitize_text_field($coach_data['phone']));
        }

        // Ensure coach role is set for all imported coaches
        if (get_role('coach')) {
            $user->set_role('coach');
        } else {
            intersoccer_referral_log('InterSoccer: Coach role not found during import');
        }

        // Generate referral code for coach if not exists
        $existing_code = get_user_meta($user_id, 'referral_code', true);
        if (empty($existing_code)) {
            $referral_code = 'COACH' . $user_id . strtoupper(str_replace('_', '', wp_generate_password(6, false)));
            update_user_meta($user_id, 'referral_code', $referral_code);
        }

        return ['action' => $action, 'user_id' => $user_id];
    }

    /**
     * Clear audit log
     */
    public function clear_audit_log() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        delete_option('intersoccer_audit_log');
        $this->log_audit('admin_action', 'Audit log cleared by admin');

        wp_send_json_success(['message' => 'Audit log cleared successfully']);
    }

    /**
     * Export audit log
     */
    public function export_audit_log() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $audit_log = get_option('intersoccer_audit_log', []);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="intersoccer-audit-log-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Timestamp', 'Action', 'User', 'Details']);

        foreach (array_reverse($audit_log) as $entry) {
            fputcsv($output, [
                $entry['timestamp'],
                $entry['action'],
                $entry['user'] ?: 'System',
                $entry['details']
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Bulk credit adjustment
     */
    public function bulk_credit_adjustment() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        // Implementation for bulk credit adjustments
        wp_send_json_success(['message' => 'Bulk adjustment completed']);
    }

    /**
     * Log audit event
     */
    private function log_audit($action, $details) {
        $audit_log = get_option('intersoccer_audit_log', []);

        $audit_log[] = [
            'timestamp' => current_time('mysql'),
            'action' => $action,
            'user' => wp_get_current_user()->user_login,
            'details' => $details
        ];

        // Keep only last 1000 entries
        if (count($audit_log) > 1000) {
            $audit_log = array_slice($audit_log, -1000);
        }

        update_option('intersoccer_audit_log', $audit_log);
    }

    /**
     * Get credit statistics (AJAX)
     */
    public function get_credit_statistics() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        global $wpdb;

        $stats = $wpdb->get_row("
            SELECT
                COUNT(DISTINCT CASE WHEN um.meta_value > 0 THEN um.user_id END) as customers_with_credits,
                SUM(CAST(um.meta_value AS UNSIGNED)) as total_credits,
                AVG(CAST(um.meta_value AS UNSIGNED)) as avg_credits
            FROM {$wpdb->usermeta} um
            WHERE um.meta_key = 'intersoccer_customer_credits'
        ");

        $html = "
            <p><strong>Customers with Credits:</strong> " . ($stats->customers_with_credits ?: 0) . "</p>
            <p><strong>Total Credits in System:</strong> " . ($stats->total_credits ?: 0) . "</p>
            <p><strong>Average Credits per Customer:</strong> " . round($stats->avg_credits ?: 0, 2) . "</p>
        ";

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Get coach statistics (AJAX)
     */
    public function get_coach_statistics() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $coach_users = get_users(['role' => 'coach']);

        $total_coaches = count($coach_users);
        $active_coaches = 0;

        foreach ($coach_users as $coach) {
            $last_login = get_user_meta($coach->ID, 'last_login', true);
            if ($last_login && strtotime($last_login) > strtotime('-30 days')) {
                $active_coaches++;
            }
        }

        $html = "
            <p><strong>Total Coaches:</strong> {$total_coaches}</p>
            <p><strong>Active Coaches (30 days):</strong> {$active_coaches}</p>
        ";

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Get audit log (AJAX)
     */
    public function get_audit_log() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $audit_log = get_option('intersoccer_audit_log', []);
        $filter = sanitize_text_field($_POST['filter'] ?? 'all');

        $html = '';
        $filtered_log = array_reverse($audit_log);

        if ($filter !== 'all') {
            $filtered_log = array_filter($filtered_log, function($entry) use ($filter) {
                return strpos($entry['action'], $filter) !== false;
            });
        }

        if (empty($filtered_log)) {
            $html = '<div class="audit-log-entry">No audit log entries found</div>';
        } else {
            foreach (array_slice($filtered_log, 0, 50) as $entry) {
                $html .= sprintf(
                    '<div class="audit-log-entry">
                        <span class="timestamp">%s</span>
                        <span class="action">%s</span>
                        <span class="user">%s</span>
                        <span class="details">%s</span>
                    </div>',
                    esc_html($entry['timestamp']),
                    esc_html($entry['action']),
                    esc_html($entry['user'] ?: 'System'),
                    esc_html($entry['details'])
                );
            }
        }

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Get points statistics via AJAX
     */
    public function get_points_statistics_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $points_manager = new InterSoccer_Points_Manager();
        $stats = $points_manager->get_points_statistics();

        $html = "
            <p><strong>Total Points Earned:</strong> " . number_format($stats['total_earned'], 2) . "</p>
            <p><strong>Total Points Spent:</strong> " . number_format($stats['total_spent'], 2) . "</p>
            <p><strong>Current Balance:</strong> " . number_format($stats['current_balance'], 0) . "</p>
            <p><strong>Customers with Points:</strong> " . number_format($stats['customers_with_points']) . "</p>
            <p><strong>Avg Points per Customer:</strong> " . number_format($stats['avg_points_per_customer'], 0) . "</p>
        ";

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Get points ledger via AJAX
     */
    public function get_points_ledger_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        global $wpdb;
        $points_log_table = $wpdb->prefix . 'intersoccer_points_log';
        $limit = intval($_POST['limit'] ?? 20);

        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT pl.*, u.display_name, u.user_email
             FROM {$points_log_table} pl
             LEFT JOIN {$wpdb->users} u ON pl.customer_id = u.ID
             ORDER BY pl.created_at DESC, pl.id DESC
             LIMIT %d",
            $limit
        ));

        if (empty($transactions)) {
            $html = '<p>No points transactions found.</p>';
        } else {
            $html = '<table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Balance</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($transactions as $transaction) {
                $amount_class = $transaction->points_amount >= 0 ? 'positive' : 'negative';
                $html .= sprintf(
                    '<tr>
                        <td>%s</td>
                        <td>%s<br><small>%s</small></td>
                        <td>%s</td>
                        <td class="%s">%s</td>
                        <td>%.2f</td>
                        <td>%s</td>
                    </tr>',
                    date('Y-m-d H:i', strtotime($transaction->created_at)),
                    esc_html($transaction->display_name ?: 'Unknown'),
                    esc_html($transaction->user_email ?: $transaction->customer_id),
                    esc_html($transaction->transaction_type),
                    $amount_class,
                    ($transaction->points_amount >= 0 ? '+' : '') . number_format($transaction->points_amount, 0),
                    number_format(intval($transaction->points_balance), 0),
                    esc_html($transaction->description)
                );
            }

            $html .= '</tbody></table>';
        }

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Register WordPress settings
     */
    public function register_settings() {
        register_setting('intersoccer_settings', 'intersoccer_credit_value', [
            'type' => 'number',
            'default' => '1',
            'sanitize_callback' => 'floatval'
        ]);

        register_setting('intersoccer_settings', 'intersoccer_max_credits_per_order', [
            'type' => 'number',
            'default' => '9999',
            'sanitize_callback' => 'intval'
        ]);

        register_setting('intersoccer_settings', 'intersoccer_debug_logging', [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'boolval'
        ]);

        register_setting('intersoccer_settings', 'intersoccer_referral_eligibility_months', [
            'type' => 'number',
            'default' => 18,
            'sanitize_callback' => [$this, 'sanitize_non_negative_int_option']
        ]);

        register_setting('intersoccer_points_configuration', 'intersoccer_points_allocation_mode', [
            'type' => 'string',
            'default' => 'ratio',
            'sanitize_callback' => [$this, 'sanitize_allocation_mode_option']
        ]);

        register_setting('intersoccer_points_configuration', 'intersoccer_points_percentage_rate', [
            'type' => 'number',
            'default' => 0,
            'sanitize_callback' => [$this, 'sanitize_percentage_option']
        ]);

        register_setting('intersoccer_points_configuration', 'intersoccer_points_golive_date', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => [$this, 'sanitize_date_option']
        ]);

        register_setting('intersoccer_settings', 'intersoccer_points_allocation_method', [
            'type' => 'string',
            'default' => 'instant',
            'sanitize_callback' => function($value) {
                return in_array($value, ['instant', 'deferred']) ? $value : 'instant';
            }
        ]);

        register_setting('intersoccer_settings', 'intersoccer_passive_mode', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'boolval'
        ]);
    }

    /**
     * Migrate users from old intersoccer_coach role to coach role
     */
    public function migrate_coach_roles() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $old_role_users = get_users(['role' => 'intersoccer_coach']);
        $migrated = 0;

        foreach ($old_role_users as $user) {
            $user->remove_role('intersoccer_coach');
            $user->add_role('coach');
            $migrated++;
        }

        $this->log_audit('role_migration', "Migrated {$migrated} users from intersoccer_coach to coach role");

        wp_redirect(add_query_arg([
            'page' => 'intersoccer-settings',
            'migrated' => $migrated
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Restore coach roles via AJAX
     */
    public function restore_coach_roles_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        global $wpdb;

        $this->log_audit('role_restoration', 'Starting coach role restoration based on referral data');

        // Find all users who have coach referrals but might not have the coach role
        $coach_users = $wpdb->get_col(
            "SELECT DISTINCT coach_id FROM {$wpdb->prefix}intersoccer_referrals
             WHERE coach_id IS NOT NULL AND coach_id > 0"
        );

        $coaches_found = count($coach_users);
        $roles_restored = 0;

        foreach ($coach_users as $user_id) {
            $user = get_user_by('ID', $user_id);
            if (!$user) continue;

            // Check if user already has coach role
            if (!in_array('coach', $user->roles)) {
                // Add coach role
                $user->add_role('coach');
                $roles_restored++;
                intersoccer_referral_log("InterSoccer: Restored coach role to user {$user_id} ({$user->user_email})");
            }
        }

        $this->log_audit('role_restoration', "Coach role restoration complete: {$coaches_found} coaches found, {$roles_restored} roles restored");

        wp_send_json_success([
            'message' => "Role restoration completed! Found {$coaches_found} coaches, restored {$roles_restored} roles.",
            'coaches_found' => $coaches_found,
            'roles_restored' => $roles_restored
        ]);
    }

    /**
     * Save points rates via AJAX (Phase 0)
     */
    public function save_points_rates_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $rate_customer_purchase = intval($_POST['rate_customer_purchase'] ?? 10);
        $rate_customer_referral = intval($_POST['rate_customer_referral'] ?? 10);
        $rate_first_time_customer = intval($_POST['rate_first_time_customer'] ?? 10);

        // Validate rates (must be positive integers)
        if ($rate_customer_purchase < 1 || $rate_customer_referral < 1 || $rate_first_time_customer < 1) {
            wp_send_json_error(['message' => 'All rates must be positive numbers (minimum 1)']);
        }

        if ($rate_customer_purchase > 100 || $rate_customer_referral > 100 || $rate_first_time_customer > 100) {
            wp_send_json_error(['message' => 'Rates cannot exceed 100']);
        }

        // Save rates
        update_option('intersoccer_points_rate_customer_purchase', $rate_customer_purchase);
        update_option('intersoccer_points_rate_customer_referral', $rate_customer_referral);
        update_option('intersoccer_points_rate_first_time_customer', $rate_first_time_customer);

        // Also save go-live date if provided
        if (isset($_POST['intersoccer_points_golive_date'])) {
            $go_live_date = sanitize_text_field($_POST['intersoccer_points_golive_date']);
            update_option('intersoccer_points_golive_date', $go_live_date);
        }

        // Ensure allocation mode is set to 'ratio' (fixed-rate) as default
        update_option('intersoccer_points_allocation_mode', 'ratio');

        // Log the change
        $this->log_audit('points_rates_updated', sprintf(
            'Point rates updated - Customer Purchase: %d, Customer Referral: %d, First Time Customer: %d',
            $rate_customer_purchase, $rate_customer_referral, $rate_first_time_customer
        ));

        wp_send_json_success([
            'message' => 'Point rates saved successfully!',
            'rates' => [
                'customer_purchase' => $rate_customer_purchase,
                'customer_referral' => $rate_customer_referral,
                'first_time_customer' => $rate_first_time_customer,
            ]
        ]);
    }

    /**
     * Sanitize date options (expects YYYY-MM-DD format)
     *
     * @param string $value Raw option value
     * @return string Sanitized date or empty string when invalid
     */
    public function sanitize_date_option($value) {
        $value = sanitize_text_field($value);

        if (empty($value)) {
            return '';
        }

        $date = DateTime::createFromFormat('Y-m-d', $value);

        if ($date instanceof DateTime && $date->format('Y-m-d') === $value) {
            return $value;
        }

        return '';
    }

    /**
     * Sanitize numeric options expected to be non-negative integers
     *
     * @param mixed $value
     * @return int
     */
    public function sanitize_non_negative_int_option($value) {
        $value = intval($value);

        if ($value < 0) {
            return 0;
        }

        return $value;
    }

    /**
     * Sanitize allocation mode option.
     *
     * @param mixed $value
     * @return string
     */
    public function sanitize_allocation_mode_option($value) {
        $value = is_string($value) ? strtolower($value) : 'ratio';

        return in_array($value, ['ratio', 'percentage'], true) ? $value : 'ratio';
    }

    /**
     * Sanitize percentage option to stay within 0-100 range.
     *
     * @param mixed $value
     * @return float
     */
    public function sanitize_percentage_option($value) {
        if (!is_numeric($value)) {
            return 0.0;
        }

        $value = (float) $value;

        if ($value < 0) {
            $value = 0.0;
        }

        if ($value > 100) {
            $value = 100.0;
        }

        return round($value, 1);
    }

    /**
     * Save commission tiers via AJAX (role-specific)
     */
    public function save_commission_tiers_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        if (!isset($_POST['tiers'])) {
            wp_send_json_error(['message' => 'No tiers data provided']);
        }

        $tiers_json = sanitize_text_field($_POST['tiers']);
        $all_tiers = json_decode(stripslashes($tiers_json), true);

        if (!is_array($all_tiers) || empty($all_tiers)) {
            wp_send_json_error(['message' => 'Invalid tiers data']);
        }

        $roles = ['coach', 'partner', 'social_influencer'];
        $total_tiers = 0;

        // Process tiers for each role
        foreach ($roles as $role) {
            if (!isset($all_tiers[$role]) || !is_array($all_tiers[$role])) {
                continue;
            }

            $tiers = $all_tiers[$role];
            $sanitized_tiers = [];

            // Validate and sanitize tiers for this role
            foreach ($tiers as $tier) {
                $min = max(1, intval($tier['min_customers'] ?? 1));
                $max = isset($tier['max_customers']) && $tier['max_customers'] !== '' 
                    ? max($min, intval($tier['max_customers'])) 
                    : 999999;
                $rate = max(0, min(100, floatval($tier['rate'] ?? 0)));

                $sanitized_tiers[] = [
                    'min_customers' => $min,
                    'max_customers' => $max,
                    'rate' => $rate
                ];
            }

            // Sort tiers by min_customers
            usort($sanitized_tiers, function($a, $b) {
                return $a['min_customers'] <=> $b['min_customers'];
            });

            // Save tiers for this role
            $option_name = 'intersoccer_commission_tiers_' . $role;
            update_option($option_name, $sanitized_tiers);
            $total_tiers += count($sanitized_tiers);

            $this->log_audit('commission_tiers_updated', sprintf(
                'Commission tiers updated for %s: %d tiers configured',
                $role,
                count($sanitized_tiers)
            ));
        }

        wp_send_json_success([
            'message' => sprintf(
                __('Successfully saved %d commission tier(s) across all roles.', 'intersoccer-referral'),
                $total_tiers
            )
        ]);
    }

    /**
     * Search orders for simulator via AJAX
     */
    public function ajax_search_orders_for_simulator() {
        check_ajax_referer('intersoccer_simulator_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'intersoccer-referral')]);
        }

        $search = sanitize_text_field($_POST['search'] ?? '');
        if (empty($search)) {
            wp_send_json_error(['message' => __('Please provide a search term', 'intersoccer-referral')]);
        }

        // Search by order ID if numeric, otherwise search by customer name/email
        $args = [
            'limit' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if (is_numeric($search)) {
            $args['include'] = [absint($search)];
        } else {
            // Search by customer name or email
            $customers = get_users([
                'search' => '*' . esc_attr($search) . '*',
                'search_columns' => ['user_login', 'user_nicename', 'user_email', 'display_name'],
                'number' => 10,
            ]);

            if (!empty($customers)) {
                $args['customer_id'] = array_map(function($user) {
                    return $user->ID;
                }, $customers);
            } else {
                wp_send_json_success(['orders' => []]);
            }
        }

        $orders = wc_get_orders($args);
        $results = [];

        foreach ($orders as $order) {
            $customer = $order->get_user();
            $customer_name = $customer ? $customer->display_name : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            if (empty(trim($customer_name))) {
                $customer_name = __('Guest', 'intersoccer-referral');
            }

            $results[] = [
                'id' => $order->get_id(),
                'customer_name' => $customer_name,
                'total_formatted' => wc_price($order->get_total(), ['currency' => $order->get_currency()]),
                'date_formatted' => $order->get_date_created()->date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
            ];
        }

        wp_send_json_success(['orders' => $results]);
    }

    /**
     * Run referral simulation via AJAX
     * Delegated to InterSoccer_Simulator class
     */
    public function ajax_run_referral_simulation() {
        // Delegate to admin-settings implementation (methods still here for now)
        // TODO: Move full implementation to InterSoccer_Simulator class
        $mode = sanitize_text_field($_POST['mode'] ?? 'date-range');
        
        // Get settings from POST (can be JSON string or array)
        $settings_json = $_POST['settings'] ?? '';
        if (is_string($settings_json) && !empty($settings_json)) {
            $settings = json_decode(stripslashes($settings_json), true);
        } else {
            $settings = $_POST['settings'] ?? [];
        }
        
        // Handle date range mode (primary mode now)
        if ($mode === 'date-range') {
            $compare_mode = !empty($_POST['compare_mode']);
            
            if ($compare_mode) {
                return $this->run_comparison_simulation($settings);
            } else {
                return $this->run_date_range_simulation($settings);
            }
        }

        // Single order mode (existing logic)
        $order_id = absint($_POST['order_id'] ?? 0);
        if (!$order_id) {
            wp_send_json_error(['message' => __('Invalid order ID', 'intersoccer-referral')]);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => __('Order not found', 'intersoccer-referral')]);
        }

        // Get order details
        $order_total = (float) $order->get_total();
        $order_tax = (float) $order->get_total_tax();
        $order_subtotal = $order_total - $order_tax;
        $customer = $order->get_user();
        $customer_name = $customer ? $customer->display_name : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        if (empty(trim($customer_name))) {
            $customer_name = __('Guest', 'intersoccer-referral');
        }

        // Initialize results array
        $results = [
            'order_info' => [
                'id' => $order_id,
                'customer_name' => $customer_name,
                'total' => $order_total,
                'subtotal' => $order_subtotal,
                'tax' => $order_tax,
                'currency' => $order->get_currency(),
            ],
            'simulation_settings' => [
                'role' => $role,
                'customer_count' => $customer_count,
                'is_first_time' => $is_first_time,
            ],
            'points_calculation' => null,
            'commission_calculation' => null,
        ];

        // Calculate points if customer role
        if ($role === 'customer') {
            if (!class_exists('InterSoccer_Points_Manager')) {
                require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-points-manager.php';
            }
            $points_manager = new InterSoccer_Points_Manager();

            // Use reflection to access private method
            $reflection = new ReflectionClass($points_manager);
            $method = $reflection->getMethod('calculate_points_from_amount');
            $method->setAccessible(true);

            $points = $method->invoke($points_manager, $order_subtotal, null, $is_first_time);

            $allocation_mode = get_option('intersoccer_points_allocation_mode', 'ratio');
            $points_rate = null;
            $percentage_rate = null;

            if ($allocation_mode === 'percentage') {
                $percentage_rate = (float) get_option('intersoccer_points_percentage_rate', 0);
            } else {
                if ($is_first_time) {
                    $points_rate = (int) get_option('intersoccer_points_rate_first_time_customer', 10);
                } else {
                    $points_rate = (int) get_option('intersoccer_points_rate_customer_purchase', 10);
                }
            }

            $points_value = (float) get_option('intersoccer_credit_value', 1);

            $results['points_calculation'] = [
                'points_earned' => $points,
                'allocation_mode' => $allocation_mode,
                'points_rate' => $points_rate,
                'percentage_rate' => $percentage_rate,
                'points_value_chf' => $points_value,
                'points_value_total' => $points * $points_value,
            ];
        }

        // Calculate commission if coach/partner/influencer role
        if (in_array($role, ['coach', 'partner', 'social_influencer'])) {
            if (!class_exists('InterSoccer_Commission_Manager')) {
                require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-commission-manager.php';
            }

            // Get commission rate based on customer count and role
            $commission_rate = InterSoccer_Commission_Manager::get_commission_rate_for_customer_count($customer_count, $role);
            $commissionable_amount = $order_subtotal; // Exclude tax
            $base_commission = $commissionable_amount * $commission_rate;

            // Commission Tiers already handle tiering - no separate tier bonuses
            // Get tier name for display purposes
            $tier = 'Bronze';
            if ($customer_count >= get_option('intersoccer_tier_platinum', 20)) {
                $tier = 'Platinum';
            } elseif ($customer_count >= get_option('intersoccer_tier_gold', 10)) {
                $tier = 'Gold';
            } elseif ($customer_count >= get_option('intersoccer_tier_silver', 5)) {
                $tier = 'Silver';
            }

            $results['commission_calculation'] = [
                'role' => $role,
                'customer_count' => $customer_count,
                'tier' => $tier,
                'commission_rate_percent' => $commission_rate * 100,
                'commissionable_amount' => $commissionable_amount,
                'base_commission' => round($base_commission, 2),
                'tier_bonus' => 0.0, // Deprecated - Commission Tiers handle tiering
                'total_commission' => round($base_commission, 2),
            ];
        }

        // Generate HTML output
        ob_start();
        ?>
        <div class="simulation-results">
            <h4><?php esc_html_e('Order Information', 'intersoccer-referral'); ?></h4>
            <table class="widefat" style="margin-bottom: 20px;">
                <tbody>
                    <tr>
                        <th style="width: 200px;"><?php esc_html_e('Order ID', 'intersoccer-referral'); ?></th>
                        <td>#<?php echo esc_html($results['order_info']['id']); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Customer', 'intersoccer-referral'); ?></th>
                        <td><?php echo esc_html($results['order_info']['customer_name']); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Order Total', 'intersoccer-referral'); ?></th>
                        <td><?php echo wc_price($results['order_info']['total'], ['currency' => $results['order_info']['currency']]); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Subtotal (ex. tax)', 'intersoccer-referral'); ?></th>
                        <td><?php echo wc_price($results['order_info']['subtotal'], ['currency' => $results['order_info']['currency']]); ?></td>
                    </tr>
                </tbody>
            </table>

            <h4><?php esc_html_e('Simulation Settings', 'intersoccer-referral'); ?></h4>
            <table class="widefat" style="margin-bottom: 20px;">
                <tbody>
                    <tr>
                        <th style="width: 200px;"><?php esc_html_e('Role', 'intersoccer-referral'); ?></th>
                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $results['simulation_settings']['role']))); ?></td>
                    </tr>
                    <?php if (in_array($role, ['coach', 'partner', 'social_influencer'])): ?>
                    <tr>
                        <th><?php esc_html_e('Customer Count', 'intersoccer-referral'); ?></th>
                        <td><?php echo esc_html($results['simulation_settings']['customer_count']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><?php esc_html_e('First-Time Customer', 'intersoccer-referral'); ?></th>
                        <td><?php echo $results['simulation_settings']['is_first_time'] ? __('Yes', 'intersoccer-referral') : __('No', 'intersoccer-referral'); ?></td>
                    </tr>
                </tbody>
            </table>

            <?php if ($results['points_calculation']): ?>
            <h4><?php esc_html_e('Points Calculation', 'intersoccer-referral'); ?></h4>
            <table class="widefat" style="margin-bottom: 20px;">
                <tbody>
                    <tr>
                        <th style="width: 200px;"><?php esc_html_e('Allocation Mode', 'intersoccer-referral'); ?></th>
                        <td><?php echo esc_html(ucfirst($results['points_calculation']['allocation_mode'])); ?></td>
                    </tr>
                    <?php if ($results['points_calculation']['allocation_mode'] === 'percentage'): ?>
                    <tr>
                        <th><?php esc_html_e('Percentage Rate', 'intersoccer-referral'); ?></th>
                        <td><?php echo esc_html(number_format($results['points_calculation']['percentage_rate'], 2)); ?>%</td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <th><?php esc_html_e('Points Rate', 'intersoccer-referral'); ?></th>
                        <td><?php echo esc_html($results['points_calculation']['points_rate']); ?> CHF per point</td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><?php esc_html_e('Points Earned', 'intersoccer-referral'); ?></th>
                        <td><strong><?php echo esc_html(number_format($results['points_calculation']['points_earned'])); ?></strong> points</td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Points Value', 'intersoccer-referral'); ?></th>
                        <td><?php echo wc_price($results['points_calculation']['points_value_total'], ['currency' => $results['order_info']['currency']]); ?> (<?php echo esc_html($results['points_calculation']['points_value_chf']); ?> CHF per point)</td>
                    </tr>
                </tbody>
            </table>
            <?php endif; ?>

            <?php if ($results['commission_calculation']): ?>
            <h4><?php esc_html_e('Commission Calculation', 'intersoccer-referral'); ?></h4>
            <table class="widefat" style="margin-bottom: 20px;">
                <tbody>
                    <tr>
                        <th style="width: 200px;"><?php esc_html_e('Tier', 'intersoccer-referral'); ?></th>
                        <td><?php echo esc_html($results['commission_calculation']['tier']); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Commission Rate', 'intersoccer-referral'); ?></th>
                        <td><?php echo esc_html(number_format($results['commission_calculation']['commission_rate_percent'], 2)); ?>%</td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Commissionable Amount', 'intersoccer-referral'); ?></th>
                        <td><?php echo wc_price($results['commission_calculation']['commissionable_amount'], ['currency' => $results['order_info']['currency']]); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Base Commission', 'intersoccer-referral'); ?></th>
                        <td><?php echo wc_price($results['commission_calculation']['base_commission'], ['currency' => $results['order_info']['currency']]); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Tier Bonus', 'intersoccer-referral'); ?></th>
                        <td><?php echo wc_price($results['commission_calculation']['tier_bonus'], ['currency' => $results['order_info']['currency']]); ?></td>
                    </tr>
                    <tr style="background: #f0f0f0;">
                        <th><strong><?php esc_html_e('Total Commission', 'intersoccer-referral'); ?></strong></th>
                        <td><strong><?php echo wc_price($results['commission_calculation']['total_commission'], ['currency' => $results['order_info']['currency']]); ?></strong></td>
                    </tr>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Run date range simulation for profit/loss analysis
     */
    private function run_date_range_simulation($settings = []) {
        $date_from = !empty($settings['date_from']) ? sanitize_text_field($settings['date_from']) : sanitize_text_field($_POST['date_from'] ?? '');
        $date_to = !empty($settings['date_to']) ? sanitize_text_field($settings['date_to']) : sanitize_text_field($_POST['date_to'] ?? '');

        if (empty($date_from) || empty($date_to)) {
            wp_send_json_error(['message' => __('Please provide both start and end dates', 'intersoccer-referral')]);
        }

        // Convert dates to timestamps for WooCommerce query
        $date_from_ts = strtotime($date_from . ' 00:00:00');
        $date_to_ts = strtotime($date_to . ' 23:59:59');

        if (!$date_from_ts || !$date_to_ts || $date_from_ts > $date_to_ts) {
            wp_send_json_error(['message' => __('Invalid date range', 'intersoccer-referral')]);
        }

        // Fetch orders in date range
        $date_from_str = date('Y-m-d H:i:s', $date_from_ts);
        $date_to_str = date('Y-m-d H:i:s', $date_to_ts);
        
        $orders = wc_get_orders([
            'limit' => -1,
            'status' => ['wc-completed', 'completed'],
            'date_created' => $date_from_str . '...' . $date_to_str,
            'orderby' => 'date',
            'order' => 'ASC',
            'type' => 'shop_order', // Exclude refunds and other order types
        ]);
        
        // Filter out any refunds or invalid orders
        $orders = array_filter($orders, function($order) {
            return $order && is_a($order, 'WC_Order') && !is_a($order, 'WC_Order_Refund');
        });

        if (empty($orders)) {
            wp_send_json_error(['message' => __('No completed orders found in the selected date range', 'intersoccer-referral')]);
        }

        // Apply potential growth if enabled
        $enable_growth = !empty($settings['enable_growth']);
        $growth_percentage = (float) ($settings['growth_percentage'] ?? 0);
        $original_order_count = count($orders);
        
        if ($enable_growth && $growth_percentage > 0) {
            // Calculate how many additional orders to create
            $growth_multiplier = 1 + ($growth_percentage / 100);
            $target_order_count = (int) round($original_order_count * $growth_multiplier);
            $additional_orders_needed = $target_order_count - $original_order_count;
            
            // Duplicate random orders to simulate growth
            if ($additional_orders_needed > 0) {
                $orders_array = array_values($orders); // Re-index array
                $growth_orders = [];
                
                for ($i = 0; $i < $additional_orders_needed; $i++) {
                    // Pick a random order to duplicate
                    $random_index = array_rand($orders_array);
                    $source_order = $orders_array[$random_index];
                    
                    // Create a clone-like order object (we'll simulate it)
                    // Since we can't clone WC_Order objects easily, we'll create a simple object
                    // that mimics the order properties we need
                    $growth_order = (object) [
                        'id' => 'growth_' . $source_order->get_id() . '_' . $i,
                        'order' => $source_order, // Keep reference to original
                        'is_growth_order' => true,
                    ];
                    $growth_orders[] = $growth_order;
                }
                
                // Merge growth orders with original orders
                $orders = array_merge($orders, $growth_orders);
            }
        }

        // Initialize aggregate metrics
        $metrics = [
            'total_orders' => 0,
            'original_orders' => $original_order_count,
            'growth_orders' => 0,
            'growth_percentage' => $enable_growth ? $growth_percentage : 0,
            'total_revenue' => 0.0,
            'total_subtotal' => 0.0,
            'total_tax' => 0.0,
            'total_points_earned' => 0,
            'total_points_value' => 0.0,
            'total_commissions' => 0.0,
            'customer_referrals' => 0,
            'coach_referrals' => 0,
            'commissions_by_role' => [
                'coach' => 0.0,
                'partner' => 0.0,
                'social_influencer' => 0.0,
            ],
            'first_time' => [
                'orders' => 0,
                'revenue' => 0.0,
                'subtotal' => 0.0,
                'points_earned' => 0,
                'points_value' => 0.0,
                'commissions' => 0.0,
            ],
            'returning' => [
                'orders' => 0,
                'revenue' => 0.0,
                'subtotal' => 0.0,
                'points_earned' => 0,
                'points_value' => 0.0,
                'commissions' => 0.0,
            ],
        ];

        // Initialize monthly breakdown for chart
        $monthly_data = [];

        // Load required classes
        if (!class_exists('InterSoccer_Points_Manager')) {
            require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-points-manager.php';
        }
        if (!class_exists('InterSoccer_Commission_Manager')) {
            require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-commission-manager.php';
        }

        // Extract settings with defaults
        $points_mode = $settings['points_mode'] ?? get_option('intersoccer_points_allocation_mode', 'ratio');
        $percentage_rate = (float) ($settings['percentage_rate'] ?? get_option('intersoccer_points_percentage_rate', 0));
        $points_rate_purchase = (int) ($settings['points_rate_purchase'] ?? get_option('intersoccer_points_rate_customer_purchase', 10));
        $points_rate_referral = (int) ($settings['points_rate_referral'] ?? get_option('intersoccer_points_rate_customer_referral', 10));
        $points_rate_first_time = (int) ($settings['points_rate_first_time'] ?? get_option('intersoccer_points_rate_first_time_customer', 10));
        $points_value = (float) ($settings['points_value'] ?? get_option('intersoccer_credit_value', 1));
        // Separate customer and coach referral rates
        $customer_referral_rate = (float) ($settings['customer_referral_rate'] ?? 10);
        $coach_referral_rate = (float) ($settings['coach_referral_rate'] ?? 5);
        $referral_rate = $customer_referral_rate + $coach_referral_rate; // Total for backward compatibility
        // Tier bonuses deprecated - Commission Tiers handle all tiering
        $commission_tiers = $settings['commission_tiers'] ?? [
            'coach' => get_option('intersoccer_commission_tiers_coach', []),
            'partner' => get_option('intersoccer_commission_tiers_partner', []),
            'social_influencer' => get_option('intersoccer_commission_tiers_social_influencer', []),
        ];
        $dist_coach = (int) ($settings['dist_coach'] ?? 60);
        $dist_partner = (int) ($settings['dist_partner'] ?? 25);
        $dist_influencer = (int) ($settings['dist_influencer'] ?? 15);

        $points_manager = new InterSoccer_Points_Manager();
        $reflection = new ReflectionClass($points_manager);
        $calculate_points_method = $reflection->getMethod('calculate_points_from_amount');
        $calculate_points_method->setAccessible(true);
        $is_first_time_method = $reflection->getMethod('is_first_time_customer');
        $is_first_time_method->setAccessible(true);

        // Track referral assignments for commission calculations
        $referral_assignments = [];
        $total_orders_count = count($orders);
        $orders_with_customer_referrals = (int) round($total_orders_count * ($customer_referral_rate / 100));
        $orders_with_coach_referrals = (int) round($total_orders_count * ($coach_referral_rate / 100));
        $orders_with_referrals = $orders_with_customer_referrals + $orders_with_coach_referrals;
        
        // Randomly assign referrals to orders
        $order_indices = range(0, $total_orders_count - 1);
        shuffle($order_indices);
        
        // Assign customer referrals
        $customer_referral_indices = array_slice($order_indices, 0, $orders_with_customer_referrals);
        $customer_referral_indices = array_flip($customer_referral_indices);
        
        // Assign coach referrals (from remaining orders)
        $remaining_indices = array_slice($order_indices, $orders_with_customer_referrals);
        shuffle($remaining_indices);
        $coach_referral_indices = array_slice($remaining_indices, 0, $orders_with_coach_referrals);
        $coach_referral_indices = array_flip($coach_referral_indices);
        
        // Combined referral indices
        $referral_order_indices = array_merge($customer_referral_indices, $coach_referral_indices);

        // Process each order
        $order_index = 0;
        foreach ($orders as $order) {
            // Handle growth orders (simulated orders)
            $is_growth_order = false;
            $source_order = null;
            
            if (is_object($order) && isset($order->is_growth_order) && $order->is_growth_order) {
                $is_growth_order = true;
                $source_order = $order->order; // Get the original WC_Order
                $order = $source_order; // Use source order for processing
            }
            
            // Skip if not a valid order object
            if (!is_a($order, 'WC_Order') || is_a($order, 'WC_Order_Refund')) {
                continue;
            }
            
            $customer_id = $order->get_customer_id();
            $order_total = (float) $order->get_total();
            $order_tax = (float) $order->get_total_tax();
            $order_subtotal = $order_total - $order_tax;

            // Get order month for chart grouping
            $order_date = $order->get_date_created();
            $month_key = $order_date ? $order_date->date('Y-m') : date('Y-m', strtotime($order->get_date_created()));
            if (!isset($monthly_data[$month_key])) {
                $monthly_data[$month_key] = [
                    'revenue' => 0.0,
                    'costs' => 0.0,
                    'profit' => 0.0,
                    'customer_referrals' => 0,
                    'coach_referrals' => 0,
                    'commissions_by_role' => [
                        'coach' => 0.0,
                        'partner' => 0.0,
                        'social_influencer' => 0.0,
                    ],
                ];
            }

            // Determine if first-time customer
            $is_first_time = $is_first_time_method->invoke($points_manager, $customer_id, $order->get_id());
            $category = $is_first_time ? 'first_time' : 'returning';

            // Check if this order has a referral (based on referral rates)
            $has_customer_referral = isset($customer_referral_indices[$order_index]);
            $has_coach_referral = isset($coach_referral_indices[$order_index]);
            $has_referral = $has_customer_referral || $has_coach_referral;
            $referral_role = null;
            $referral_type = null;
            
            if ($has_referral) {
                if ($has_coach_referral) {
                    // Coach referral - assign role based on distribution
                    $rand = rand(1, 100);
                    if ($rand <= $dist_coach) {
                        $referral_role = 'coach';
                    } elseif ($rand <= ($dist_coach + $dist_partner)) {
                        $referral_role = 'partner';
                    } else {
                        $referral_role = 'social_influencer';
                    }
                    $referral_type = 'coach';
                    $metrics['coach_referrals']++;
                    $monthly_data[$month_key]['coach_referrals']++;
                } else {
                    // Customer referral
                    $referral_type = 'customer';
                    $metrics['customer_referrals']++;
                    $monthly_data[$month_key]['customer_referrals']++;
                    // Customer referrals don't generate commissions, only points
                }
            }

            // Update aggregate metrics
            $metrics['total_orders']++;
            if ($is_growth_order) {
                $metrics['growth_orders']++;
            }
            $metrics['total_revenue'] += $order_total;
            $metrics['total_subtotal'] += $order_subtotal;
            $metrics['total_tax'] += $order_tax;

            $metrics[$category]['orders']++;
            $metrics[$category]['revenue'] += $order_total;
            $metrics[$category]['subtotal'] += $order_subtotal;

            $monthly_revenue = $order_total;
            $monthly_costs = 0.0;

            // Calculate points (always calculate for customers)
            if ($points_mode === 'percentage' && $percentage_rate > 0) {
                $points = (int) floor(($order_subtotal * $percentage_rate) / 100);
            } else {
                $rate = $is_first_time ? $points_rate_first_time : $points_rate_purchase;
                $points = $rate > 0 ? (int) floor($order_subtotal / $rate) : 0;
            }
            
            $points_value_amount = $points * $points_value;
            $metrics['total_points_earned'] += $points;
            $metrics['total_points_value'] += $points_value_amount;
            $metrics[$category]['points_earned'] += $points;
            $metrics[$category]['points_value'] += $points_value_amount;
            $monthly_costs += $points_value_amount;

            // Calculate commission if order has coach referral (coaches/partners/influencers get commissions)
            if ($has_referral && $referral_type === 'coach' && $referral_role && isset($commission_tiers[$referral_role])) {
                // Simulate customer count for tier calculation (simplified - could be improved)
                $simulated_customer_count = rand(1, 50);
                
                // Find matching tier
                $commission_rate = 0;
                foreach ($commission_tiers[$referral_role] as $tier) {
                    if ($simulated_customer_count >= $tier['min_customers'] && 
                        $simulated_customer_count <= $tier['max_customers']) {
                        $commission_rate = (float) $tier['rate'] / 100;
                        break;
                    }
                }
                
                // Commission Tiers already handle tiering - no separate tier bonuses
                $total_commission = $order_subtotal * $commission_rate;

                $metrics['total_commissions'] += $total_commission;
                $metrics[$category]['commissions'] += $total_commission;
                if (isset($metrics['commissions_by_role'][$referral_role])) {
                    $metrics['commissions_by_role'][$referral_role] += $total_commission;
                }
                // Track monthly commissions by role
                if (isset($monthly_data[$month_key]['commissions_by_role'][$referral_role])) {
                    $monthly_data[$month_key]['commissions_by_role'][$referral_role] += $total_commission;
                }
                $monthly_costs += $total_commission;
            }

            // Update monthly data
            $monthly_data[$month_key]['revenue'] += $monthly_revenue;
            $monthly_data[$month_key]['costs'] += $monthly_costs;
            $monthly_data[$month_key]['profit'] += ($monthly_revenue - $monthly_costs);
            
            $order_index++;
        }

        // Calculate profit/loss
        $total_costs = $metrics['total_points_value'] + $metrics['total_commissions'];
        $net_profit = $metrics['total_revenue'] - $total_costs;
        $profit_margin = $metrics['total_revenue'] > 0 ? ($net_profit / $metrics['total_revenue']) * 100 : 0;

        // Calculate additional metrics for executive summary
        $avg_order_value = $metrics['total_orders'] > 0 ? $metrics['total_revenue'] / $metrics['total_orders'] : 0;
        $avg_points_per_order = $metrics['total_orders'] > 0 ? $metrics['total_points_earned'] / $metrics['total_orders'] : 0;
        $avg_commission_per_order = $metrics['total_orders'] > 0 ? $metrics['total_commissions'] / $metrics['total_orders'] : 0;
        $cost_per_order = $metrics['total_orders'] > 0 ? $total_costs / $metrics['total_orders'] : 0;
        $roi = $total_costs > 0 ? (($net_profit / $total_costs) * 100) : 0;
        
        // Calculate break-even point (how many orders needed to cover costs)
        // Break-even = total costs / (revenue per order - cost per order)
        $revenue_per_order = $avg_order_value;
        $profit_per_order = $revenue_per_order - $cost_per_order;
        $break_even_orders = ($profit_per_order > 0) ? ceil($total_costs / $profit_per_order) : 0;

        // Handle projections if requested
        $project_months = (int) ($settings['project_months'] ?? 0);
        $revenue_growth = (float) ($settings['revenue_growth'] ?? 5);
        $referral_adoption_start = (float) ($settings['referral_adoption_start'] ?? $referral_rate);
        $referral_adoption_end = (float) ($settings['referral_adoption_end'] ?? 30);
        
        $projected_data = [];
        if ($project_months > 0) {
            // Calculate average monthly revenue and costs from historical data
            $monthly_avg_revenue = count($monthly_data) > 0 ? array_sum(array_column($monthly_data, 'revenue')) / count($monthly_data) : 0;
            $monthly_avg_costs = count($monthly_data) > 0 ? array_sum(array_column($monthly_data, 'costs')) / count($monthly_data) : 0;
            $monthly_avg_profit = $monthly_avg_revenue - $monthly_avg_costs;
            
            // Get last month from historical data
            $last_month = max(array_keys($monthly_data));
            $last_month_date = new DateTime($last_month . '-01');
            
            for ($i = 1; $i <= $project_months; $i++) {
                $project_month = clone $last_month_date;
                $project_month->modify("+{$i} months");
                $month_key = $project_month->format('Y-m');
                
                // Calculate growth-adjusted revenue
                $growth_factor = pow(1 + ($revenue_growth / 100), $i);
                $projected_revenue = $monthly_avg_revenue * $growth_factor;
                
                // Calculate referral adoption rate (linear growth)
                $adoption_rate = $referral_adoption_start + (($referral_adoption_end - $referral_adoption_start) * ($i / $project_months));
                $adoption_factor = $adoption_rate / max($referral_rate, 1);
                
                // Projected costs scale with revenue and adoption
                $projected_costs = $monthly_avg_costs * $growth_factor * $adoption_factor;
                $projected_profit = $projected_revenue - $projected_costs;
                
                $projected_data[$month_key] = [
                    'revenue' => $projected_revenue,
                    'costs' => $projected_costs,
                    'profit' => $projected_profit,
                ];
                
                // Add to monthly_data for chart
                $monthly_data[$month_key] = [
                    'revenue' => $projected_revenue,
                    'costs' => $projected_costs,
                    'profit' => $projected_profit,
                ];
            }
        }

        // Prepare chart data
        ksort($monthly_data); // Sort by month
        $chart_labels = [];
        $chart_revenue = [];
        $chart_costs = [];
        $chart_profit = [];
        $is_historical = [];
        
        // Find last historical month (before projections)
        $historical_months = array_filter(array_keys($monthly_data), function($k) use ($projected_data) {
            return !isset($projected_data[$k]);
        });
        $last_historical_month = !empty($historical_months) ? max($historical_months) : null;
        $last_historical_index = $last_historical_month ? array_search($last_historical_month, array_keys($monthly_data)) : -1;

        foreach ($monthly_data as $month => $data) {
            $chart_labels[] = date_i18n('M Y', strtotime($month . '-01'));
            $chart_revenue[] = round($data['revenue'], 2);
            $chart_costs[] = round($data['costs'], 2);
            $chart_profit[] = round($data['profit'], 2);
            $is_historical[] = !isset($projected_data[$month]);
        }

        // Prepare monthly referrals and commissions data for charts
        $monthly_referrals_customer = [];
        $monthly_referrals_coach = [];
        $monthly_commissions_coach = [];
        $monthly_commissions_partner = [];
        $monthly_commissions_influencer = [];
        
        foreach ($monthly_data as $month => $data) {
            $monthly_referrals_customer[] = $data['customer_referrals'] ?? 0;
            $monthly_referrals_coach[] = $data['coach_referrals'] ?? 0;
            $monthly_commissions_coach[] = round($data['commissions_by_role']['coach'] ?? 0, 2);
            $monthly_commissions_partner[] = round($data['commissions_by_role']['partner'] ?? 0, 2);
            $monthly_commissions_influencer[] = round($data['commissions_by_role']['social_influencer'] ?? 0, 2);
        }

        $chart_data = [
            'labels' => $chart_labels,
            'revenue' => $chart_revenue,
            'costs' => $chart_costs,
            'profit' => $chart_profit,
            'revenue_label' => __('Revenue', 'intersoccer-referral'),
            'costs_label' => __('Total Costs (Points + Commissions)', 'intersoccer-referral'),
            'profit_label' => __('Net Profit/Loss', 'intersoccer-referral'),
            'is_historical' => $is_historical,
            'last_historical_index' => $last_historical_index,
            'referrals' => [
                'labels' => $chart_labels,
                'customer' => $monthly_referrals_customer,
                'coach' => $monthly_referrals_coach,
                'total_customer' => $metrics['customer_referrals'],
                'total_coach' => $metrics['coach_referrals'],
                'total' => $metrics['customer_referrals'] + $metrics['coach_referrals'],
            ],
            'commissions_by_role' => [
                'labels' => $chart_labels,
                'coach' => $monthly_commissions_coach,
                'partner' => $monthly_commissions_partner,
                'social_influencer' => $monthly_commissions_influencer,
                'total_coach' => $metrics['commissions_by_role']['coach'],
                'total_partner' => $metrics['commissions_by_role']['partner'],
                'total_social_influencer' => $metrics['commissions_by_role']['social_influencer'],
            ],
        ];

        // Generate Executive Summary
        $executive_summary = $this->generate_executive_summary([
            'total_revenue' => $metrics['total_revenue'],
            'total_costs' => $total_costs,
            'net_profit' => $net_profit,
            'profit_margin' => $profit_margin,
            'total_orders' => $metrics['total_orders'],
            'original_orders' => $metrics['original_orders'],
            'growth_orders' => $metrics['growth_orders'],
            'growth_percentage' => $metrics['growth_percentage'],
            'avg_order_value' => $avg_order_value,
            'roi' => $roi,
            'break_even_orders' => $break_even_orders,
            'referral_rate' => $referral_rate,
            'total_points_earned' => $metrics['total_points_earned'],
            'total_commissions' => $metrics['total_commissions'],
            'project_months' => $project_months,
            'projected_data' => $projected_data,
        ]);

        // Generate HTML report
        ob_start();
        ?>
        <div class="simulation-results date-range-results">
            <h4><?php esc_html_e('Date Range Analysis Summary', 'intersoccer-referral'); ?></h4>
            <table class="widefat" style="margin-bottom: 20px;">
                <tbody>
                    <tr>
                        <th style="width: 200px;"><?php esc_html_e('Date Range', 'intersoccer-referral'); ?></th>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), $date_from_ts)); ?> - <?php echo esc_html(date_i18n(get_option('date_format'), $date_to_ts)); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Total Orders', 'intersoccer-referral'); ?></th>
                        <td>
                            <strong><?php echo esc_html(number_format($metrics['total_orders'])); ?></strong>
                            <?php if (!empty($metrics['growth_orders']) && $metrics['growth_orders'] > 0): ?>
                                <span style="color: #856404; font-size: 0.9em; margin-left: 10px;">
                                    (<?php echo esc_html(number_format($metrics['original_orders'])); ?> original + 
                                    <?php echo esc_html(number_format($metrics['growth_orders'])); ?> growth orders)
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (!empty($metrics['growth_percentage']) && $metrics['growth_percentage'] > 0): ?>
                    <tr style="background: #fff3cd;">
                        <th><?php esc_html_e('Potential Growth Applied', 'intersoccer-referral'); ?></th>
                        <td>
                            <strong style="color: #856404;">
                                <?php echo esc_html(number_format($metrics['growth_percentage'], 1)); ?>%
                            </strong>
                            <span style="font-size: 0.9em; margin-left: 10px;">
                                (<?php echo esc_html(__('Simulated additional orders due to referral-driven growth', 'intersoccer-referral')); ?>)
                            </span>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><?php esc_html_e('Role', 'intersoccer-referral'); ?></th>
                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $role))); ?></td>
                    </tr>
                </tbody>
            </table>

            <h4><?php esc_html_e('Financial Summary', 'intersoccer-referral'); ?></h4>
            <table class="widefat" style="margin-bottom: 20px;">
                <tbody>
                    <tr>
                        <th style="width: 200px;"><?php esc_html_e('Total Revenue', 'intersoccer-referral'); ?></th>
                        <td><strong><?php echo wc_price($metrics['total_revenue']); ?></strong></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Total Subtotal (ex. tax)', 'intersoccer-referral'); ?></th>
                        <td><?php echo wc_price($metrics['total_subtotal']); ?></td>
                    </tr>
                    <?php if ($role === 'customer'): ?>
                    <tr>
                        <th><?php esc_html_e('Total Points Earned', 'intersoccer-referral'); ?></th>
                        <td><strong><?php echo esc_html(number_format($metrics['total_points_earned'])); ?></strong> points</td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Total Points Value', 'intersoccer-referral'); ?></th>
                        <td><?php echo wc_price($metrics['total_points_value']); ?> (<?php echo esc_html($points_value); ?> CHF per point)</td>
                    </tr>
                    <?php endif; ?>
                    <?php if (in_array($role, ['coach', 'partner', 'social_influencer'])): ?>
                    <tr>
                        <th><?php esc_html_e('Total Commissions Paid', 'intersoccer-referral'); ?></th>
                        <td><?php echo wc_price($metrics['total_commissions']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr style="background: #f0f0f0;">
                        <th><strong><?php esc_html_e('Total Costs', 'intersoccer-referral'); ?></strong></th>
                        <td><strong><?php echo wc_price($total_costs); ?></strong></td>
                    </tr>
                    <tr style="background: <?php echo $net_profit >= 0 ? '#d4edda' : '#f8d7da'; ?>;">
                        <th><strong><?php esc_html_e('Net Profit/Loss', 'intersoccer-referral'); ?></strong></th>
                        <td><strong style="color: <?php echo $net_profit >= 0 ? '#155724' : '#721c24'; ?>;">
                            <?php echo wc_price($net_profit); ?> 
                            (<?php echo esc_html(number_format($profit_margin, 2)); ?>% margin)
                        </strong></td>
                    </tr>
                </tbody>
            </table>

            <h4><?php esc_html_e('Breakdown by Customer Type', 'intersoccer-referral'); ?></h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <!-- First-Time Customers -->
                <div>
                    <h5 style="background: #e3f2fd; padding: 10px; margin: 0 0 10px 0;">
                        <?php esc_html_e('First-Time Customers', 'intersoccer-referral'); ?>
                    </h5>
                    <table class="widefat">
                        <tbody>
                            <tr>
                                <th style="width: 150px;"><?php esc_html_e('Orders', 'intersoccer-referral'); ?></th>
                                <td><?php echo esc_html(number_format($metrics['first_time']['orders'])); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Revenue', 'intersoccer-referral'); ?></th>
                                <td><?php echo wc_price($metrics['first_time']['revenue']); ?></td>
                            </tr>
                            <?php if ($role === 'customer'): ?>
                            <tr>
                                <th><?php esc_html_e('Points Earned', 'intersoccer-referral'); ?></th>
                                <td><?php echo esc_html(number_format($metrics['first_time']['points_earned'])); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Points Value', 'intersoccer-referral'); ?></th>
                                <td><?php echo wc_price($metrics['first_time']['points_value']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (in_array($role, ['coach', 'partner', 'social_influencer'])): ?>
                            <tr>
                                <th><?php esc_html_e('Commissions', 'intersoccer-referral'); ?></th>
                                <td><?php echo wc_price($metrics['first_time']['commissions']); ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Returning Customers -->
                <div>
                    <h5 style="background: #fff3e0; padding: 10px; margin: 0 0 10px 0;">
                        <?php esc_html_e('Returning Customers', 'intersoccer-referral'); ?>
                    </h5>
                    <table class="widefat">
                        <tbody>
                            <tr>
                                <th style="width: 150px;"><?php esc_html_e('Orders', 'intersoccer-referral'); ?></th>
                                <td><?php echo esc_html(number_format($metrics['returning']['orders'])); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Revenue', 'intersoccer-referral'); ?></th>
                                <td><?php echo wc_price($metrics['returning']['revenue']); ?></td>
                            </tr>
                            <?php if ($role === 'customer'): ?>
                            <tr>
                                <th><?php esc_html_e('Points Earned', 'intersoccer-referral'); ?></th>
                                <td><?php echo esc_html(number_format($metrics['returning']['points_earned'])); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Points Value', 'intersoccer-referral'); ?></th>
                                <td><?php echo wc_price($metrics['returning']['points_value']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (in_array($role, ['coach', 'partner', 'social_influencer'])): ?>
                            <tr>
                                <th><?php esc_html_e('Commissions', 'intersoccer-referral'); ?></th>
                                <td><?php echo wc_price($metrics['returning']['commissions']); ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="background: #f9f9f9; padding: 15px; border-radius: 4px; border: 1px solid #ddd;">
                <h5><?php esc_html_e('Key Insights', 'intersoccer-referral'); ?></h5>
                <ul>
                    <li>
                        <?php
                        $first_time_percentage = $metrics['total_orders'] > 0 
                            ? ($metrics['first_time']['orders'] / $metrics['total_orders']) * 100 
                            : 0;
                        printf(
                            esc_html__('First-time customers: %d orders (%.1f%%)', 'intersoccer-referral'),
                            $metrics['first_time']['orders'],
                            $first_time_percentage
                        );
                        ?>
                    </li>
                    <li>
                        <?php
                        $returning_percentage = $metrics['total_orders'] > 0 
                            ? ($metrics['returning']['orders'] / $metrics['total_orders']) * 100 
                            : 0;
                        printf(
                            esc_html__('Returning customers: %d orders (%.1f%%)', 'intersoccer-referral'),
                            $metrics['returning']['orders'],
                            $returning_percentage
                        );
                        ?>
                    </li>
                    <li>
                        <?php
                        $avg_order_value = $metrics['total_orders'] > 0 
                            ? $metrics['total_revenue'] / $metrics['total_orders'] 
                            : 0;
                        printf(
                            esc_html__('Average order value: %s', 'intersoccer-referral'),
                            wc_price($avg_order_value)
                        );
                        ?>
                    </li>
                    <?php if ($role === 'customer'): ?>
                    <li>
                        <?php
                        $avg_points_per_order = $metrics['total_orders'] > 0 
                            ? $metrics['total_points_earned'] / $metrics['total_orders'] 
                            : 0;
                        printf(
                            esc_html__('Average points per order: %.1f points', 'intersoccer-referral'),
                            $avg_points_per_order
                        );
                        ?>
                    </li>
                    <?php endif; ?>
                    <?php if (in_array($role, ['coach', 'partner', 'social_influencer'])): ?>
                    <li>
                        <?php
                        $avg_commission_per_order = $metrics['total_orders'] > 0 
                            ? $metrics['total_commissions'] / $metrics['total_orders'] 
                            : 0;
                        printf(
                            esc_html__('Average commission per order: %s', 'intersoccer-referral'),
                            wc_price($avg_commission_per_order)
                        );
                        ?>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <?php
        $html = ob_get_clean();

        wp_send_json_success([
            'html' => $html,
            'chart_data' => $chart_data,
            'executive_summary' => $executive_summary,
            'metrics' => [
                'total_revenue' => $metrics['total_revenue'],
                'total_costs' => $total_costs,
                'net_profit' => $net_profit,
                'profit_margin' => $profit_margin,
                'roi' => $roi,
                'avg_order_value' => $avg_order_value,
                'break_even_orders' => $break_even_orders,
            ]
        ]);
    }

    /**
     * Run comparison simulation (current scenario vs baseline)
     */
    private function run_comparison_simulation($settings) {
        // Run current scenario
        $current_result = $this->run_date_range_simulation_internal($settings, 'Current Scenario');
        
        // Run baseline scenario (no program)
        $baseline_settings = $settings;
        $baseline_settings['referral_rate'] = 0;
        $baseline_settings['points_rate_purchase'] = 0;
        $baseline_settings['points_rate_referral'] = 0;
        $baseline_settings['points_rate_first_time'] = 0;
        $baseline_result = $this->run_date_range_simulation_internal($baseline_settings, 'Baseline (No Program)');
        
        // Generate comparison HTML
        ob_start();
        ?>
        <div class="comparison-results" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div style="background: #fff; padding: 20px; border: 2px solid #4facfe; border-radius: 8px;">
                <h4 style="margin-top: 0; color: #4facfe;"><?php esc_html_e('Current Scenario', 'intersoccer-referral'); ?></h4>
                <?php echo $current_result['executive_summary']; ?>
            </div>
            <div style="background: #fff; padding: 20px; border: 2px solid #95a5a6; border-radius: 8px;">
                <h4 style="margin-top: 0; color: #95a5a6;"><?php esc_html_e('Baseline (No Program)', 'intersoccer-referral'); ?></h4>
                <?php echo $baseline_result['executive_summary']; ?>
            </div>
        </div>
        
        <div style="background: #e8f5e9; padding: 20px; border-radius: 8px; margin-top: 20px;">
            <h4><?php esc_html_e('Program Impact', 'intersoccer-referral'); ?></h4>
            <?php
            $revenue_diff = $current_result['metrics']['total_revenue'] - $baseline_result['metrics']['total_revenue'];
            $cost_diff = $current_result['metrics']['total_costs'] - $baseline_result['metrics']['total_costs'];
            $profit_diff = $current_result['metrics']['net_profit'] - $baseline_result['metrics']['net_profit'];
            ?>
            <table class="widefat">
                <tbody>
                    <tr>
                        <th style="width: 200px;"><?php esc_html_e('Additional Revenue', 'intersoccer-referral'); ?></th>
                        <td><strong style="color: #2e7d32;"><?php echo wc_price($revenue_diff); ?></strong></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Program Costs', 'intersoccer-referral'); ?></th>
                        <td><strong style="color: #c62828;"><?php echo wc_price($cost_diff); ?></strong></td>
                    </tr>
                    <tr style="background: #c8e6c9;">
                        <th><strong><?php esc_html_e('Net Impact', 'intersoccer-referral'); ?></strong></th>
                        <td><strong style="color: <?php echo $profit_diff >= 0 ? '#2e7d32' : '#c62828'; ?>; font-size: 18px;">
                            <?php echo wc_price($profit_diff); ?>
                            (<?php echo $profit_diff >= 0 ? '+' : ''; ?><?php echo esc_html(number_format(($profit_diff / max($baseline_result['metrics']['net_profit'], 1)) * 100, 1)); ?>%)
                        </strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
        $comparison_html = ob_get_clean();
        
        // Prepare comparison chart data
        $comparison_chart_data = [
            'labels' => $current_result['chart_data']['labels'],
            'scenarios' => [
                [
                    'name' => 'Current Scenario',
                    'profit' => $current_result['chart_data']['profit'],
                ],
                [
                    'name' => 'Baseline (No Program)',
                    'profit' => $baseline_result['chart_data']['profit'],
                ],
            ],
        ];
        
        wp_send_json_success([
            'html' => $current_result['html'],
            'chart_data' => $current_result['chart_data'],
            'executive_summary' => $current_result['executive_summary'],
            'comparison' => $comparison_html,
            'comparison_chart_data' => $comparison_chart_data,
            'metrics' => $current_result['metrics'],
        ]);
    }

    /**
     * Internal method to run simulation and return results (for comparison mode)
     */
    private function run_date_range_simulation_internal($settings, $scenario_name = '') {
        // This is essentially the same as run_date_range_simulation but returns data instead of sending JSON
        // We'll extract the core logic into a shared method
        // For now, let's duplicate and modify to return data
        
        $date_from = !empty($settings['date_from']) ? sanitize_text_field($settings['date_from']) : sanitize_text_field($_POST['date_from'] ?? '');
        $date_to = !empty($settings['date_to']) ? sanitize_text_field($settings['date_to']) : sanitize_text_field($_POST['date_to'] ?? '');

        if (empty($date_from) || empty($date_to)) {
            return [
                'error' => __('Please provide both start and end dates', 'intersoccer-referral'),
                'html' => '',
                'chart_data' => [],
                'executive_summary' => '',
                'metrics' => [],
            ];
        }

        // Convert dates to timestamps
        $date_from_ts = strtotime($date_from . ' 00:00:00');
        $date_to_ts = strtotime($date_to . ' 23:59:59');

        if (!$date_from_ts || !$date_to_ts || $date_from_ts > $date_to_ts) {
            return [
                'error' => __('Invalid date range', 'intersoccer-referral'),
                'html' => '',
                'chart_data' => [],
                'executive_summary' => '',
                'metrics' => [],
            ];
        }

        // Fetch orders (same as main method)
        $date_from_str = date('Y-m-d H:i:s', $date_from_ts);
        $date_to_str = date('Y-m-d H:i:s', $date_to_ts);
        
        $orders = wc_get_orders([
            'limit' => -1,
            'status' => ['wc-completed', 'completed'],
            'date_created' => $date_from_str . '...' . $date_to_str,
            'orderby' => 'date',
            'order' => 'ASC',
            'type' => 'shop_order',
        ]);
        
        $orders = array_filter($orders, function($order) {
            return $order && is_a($order, 'WC_Order') && !is_a($order, 'WC_Order_Refund');
        });

        if (empty($orders)) {
            return [
                'error' => __('No completed orders found in the selected date range', 'intersoccer-referral'),
                'html' => '',
                'chart_data' => [],
                'executive_summary' => '',
                'metrics' => [],
            ];
        }

        // Apply potential growth if enabled (same as main method)
        $enable_growth = !empty($settings['enable_growth']);
        $growth_percentage = (float) ($settings['growth_percentage'] ?? 0);
        $original_order_count = count($orders);
        
        if ($enable_growth && $growth_percentage > 0) {
            $growth_multiplier = 1 + ($growth_percentage / 100);
            $target_order_count = (int) round($original_order_count * $growth_multiplier);
            $additional_orders_needed = $target_order_count - $original_order_count;
            
            if ($additional_orders_needed > 0) {
                $orders_array = array_values($orders);
                $growth_orders = [];
                
                for ($i = 0; $i < $additional_orders_needed; $i++) {
                    $random_index = array_rand($orders_array);
                    $source_order = $orders_array[$random_index];
                    
                    $growth_order = (object) [
                        'id' => 'growth_' . $source_order->get_id() . '_' . $i,
                        'order' => $source_order,
                        'is_growth_order' => true,
                    ];
                    $growth_orders[] = $growth_order;
                }
                
                $orders = array_merge($orders, $growth_orders);
            }
        }

        // Initialize metrics (same structure as main method)
        $metrics = [
            'total_orders' => 0,
            'original_orders' => $original_order_count,
            'growth_orders' => 0,
            'growth_percentage' => $enable_growth ? $growth_percentage : 0,
            'total_revenue' => 0.0,
            'total_subtotal' => 0.0,
            'total_tax' => 0.0,
            'total_points_earned' => 0,
            'total_points_value' => 0.0,
            'total_commissions' => 0.0,
            'first_time' => [
                'orders' => 0,
                'revenue' => 0.0,
                'subtotal' => 0.0,
                'points_earned' => 0,
                'points_value' => 0.0,
                'commissions' => 0.0,
            ],
            'returning' => [
                'orders' => 0,
                'revenue' => 0.0,
                'subtotal' => 0.0,
                'points_earned' => 0,
                'points_value' => 0.0,
                'commissions' => 0.0,
            ],
        ];

        $monthly_data = [];

        // Load required classes
        if (!class_exists('InterSoccer_Points_Manager')) {
            require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-points-manager.php';
        }
        if (!class_exists('InterSoccer_Commission_Manager')) {
            require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-commission-manager.php';
        }

        // Extract settings (same as main method)
        $points_mode = $settings['points_mode'] ?? get_option('intersoccer_points_allocation_mode', 'ratio');
        $percentage_rate = (float) ($settings['percentage_rate'] ?? get_option('intersoccer_points_percentage_rate', 0));
        $points_rate_purchase = (int) ($settings['points_rate_purchase'] ?? get_option('intersoccer_points_rate_customer_purchase', 10));
        $points_rate_referral = (int) ($settings['points_rate_referral'] ?? get_option('intersoccer_points_rate_customer_referral', 10));
        $points_rate_first_time = (int) ($settings['points_rate_first_time'] ?? get_option('intersoccer_points_rate_first_time_customer', 10));
        $points_value = (float) ($settings['points_value'] ?? get_option('intersoccer_credit_value', 1));
        $referral_rate = (float) ($settings['referral_rate'] ?? 15);
        // Tier bonuses deprecated - Commission Tiers handle all tiering
        $commission_tiers = $settings['commission_tiers'] ?? [
            'coach' => get_option('intersoccer_commission_tiers_coach', []),
            'partner' => get_option('intersoccer_commission_tiers_partner', []),
            'social_influencer' => get_option('intersoccer_commission_tiers_social_influencer', []),
        ];
        $dist_coach = (int) ($settings['dist_coach'] ?? 60);
        $dist_partner = (int) ($settings['dist_partner'] ?? 25);
        $dist_influencer = (int) ($settings['dist_influencer'] ?? 15);

        $points_manager = new InterSoccer_Points_Manager();
        $reflection = new ReflectionClass($points_manager);
        $is_first_time_method = $reflection->getMethod('is_first_time_customer');
        $is_first_time_method->setAccessible(true);

        // Track referral assignments
        $total_orders_count = count($orders);
        $orders_with_referrals = (int) round($total_orders_count * ($referral_rate / 100));
        
        $order_indices = range(0, $total_orders_count - 1);
        shuffle($order_indices);
        $referral_order_indices = array_slice($order_indices, 0, $orders_with_referrals);
        $referral_order_indices = array_flip($referral_order_indices);

        // Process orders (same logic as main method)
        $order_index = 0;
        foreach ($orders as $order) {
            // Handle growth orders (simulated orders)
            $is_growth_order = false;
            $source_order = null;
            
            if (is_object($order) && isset($order->is_growth_order) && $order->is_growth_order) {
                $is_growth_order = true;
                $source_order = $order->order;
                $order = $source_order;
            }
            
            if (!is_a($order, 'WC_Order') || is_a($order, 'WC_Order_Refund')) {
                continue;
            }
            
            $customer_id = $order->get_customer_id();
            $order_total = (float) $order->get_total();
            $order_tax = (float) $order->get_total_tax();
            $order_subtotal = $order_total - $order_tax;

            $order_date = $order->get_date_created();
            $month_key = $order_date ? $order_date->date('Y-m') : date('Y-m', strtotime($order->get_date_created()));
            if (!isset($monthly_data[$month_key])) {
                $monthly_data[$month_key] = [
                    'revenue' => 0.0,
                    'costs' => 0.0,
                    'profit' => 0.0,
                ];
            }

            $is_first_time = $is_first_time_method->invoke($points_manager, $customer_id, $order->get_id());
            $category = $is_first_time ? 'first_time' : 'returning';

            $has_referral = isset($referral_order_indices[$order_index]);
            $referral_role = null;
            if ($has_referral) {
                $rand = rand(1, 100);
                if ($rand <= $dist_coach) {
                    $referral_role = 'coach';
                } elseif ($rand <= ($dist_coach + $dist_partner)) {
                    $referral_role = 'partner';
                } else {
                    $referral_role = 'social_influencer';
                }
            }

            $metrics['total_orders']++;
            if ($is_growth_order) {
                $metrics['growth_orders']++;
            }
            $metrics['total_revenue'] += $order_total;
            $metrics['total_subtotal'] += $order_subtotal;
            $metrics['total_tax'] += $order_tax;

            $metrics[$category]['orders']++;
            $metrics[$category]['revenue'] += $order_total;
            $metrics[$category]['subtotal'] += $order_subtotal;

            $monthly_revenue = $order_total;
            $monthly_costs = 0.0;

            // Calculate points
            if ($points_mode === 'percentage' && $percentage_rate > 0) {
                $points = (int) floor(($order_subtotal * $percentage_rate) / 100);
            } else {
                $rate = $is_first_time ? $points_rate_first_time : $points_rate_purchase;
                $points = $rate > 0 ? (int) floor($order_subtotal / $rate) : 0;
            }
            
            $points_value_amount = $points * $points_value;
            $metrics['total_points_earned'] += $points;
            $metrics['total_points_value'] += $points_value_amount;
            $metrics[$category]['points_earned'] += $points;
            $metrics[$category]['points_value'] += $points_value_amount;
            $monthly_costs += $points_value_amount;

            // Calculate commission if order has referral
            if ($has_referral && $referral_role && isset($commission_tiers[$referral_role])) {
                $simulated_customer_count = rand(1, 50);
                
                $commission_rate = 0;
                foreach ($commission_tiers[$referral_role] as $tier) {
                    if ($simulated_customer_count >= $tier['min_customers'] && 
                        $simulated_customer_count <= $tier['max_customers']) {
                        $commission_rate = (float) $tier['rate'] / 100;
                        break;
                    }
                }
                
                // Commission Tiers already handle tiering - no separate tier bonuses
                $total_commission = $order_subtotal * $commission_rate;

                $metrics['total_commissions'] += $total_commission;
                $metrics[$category]['commissions'] += $total_commission;
                if (isset($metrics['commissions_by_role'][$referral_role])) {
                    $metrics['commissions_by_role'][$referral_role] += $total_commission;
                }
                $monthly_costs += $total_commission;
            }

            $monthly_data[$month_key]['revenue'] += $monthly_revenue;
            $monthly_data[$month_key]['costs'] += $monthly_costs;
            $monthly_data[$month_key]['profit'] += ($monthly_revenue - $monthly_costs);
            
            $order_index++;
        }

        // Calculate metrics (same as main method)
        $total_costs = $metrics['total_points_value'] + $metrics['total_commissions'];
        $net_profit = $metrics['total_revenue'] - $total_costs;
        $profit_margin = $metrics['total_revenue'] > 0 ? ($net_profit / $metrics['total_revenue']) * 100 : 0;
        $avg_order_value = $metrics['total_orders'] > 0 ? $metrics['total_revenue'] / $metrics['total_orders'] : 0;
        $cost_per_order = $metrics['total_orders'] > 0 ? $total_costs / $metrics['total_orders'] : 0;
        $roi = $total_costs > 0 ? (($net_profit / $total_costs) * 100) : 0;
        $revenue_per_order = $avg_order_value;
        $profit_per_order = $revenue_per_order - $cost_per_order;
        $break_even_orders = ($profit_per_order > 0) ? ceil($total_costs / $profit_per_order) : 0;

        // Handle projections
        $project_months = (int) ($settings['project_months'] ?? 0);
        $revenue_growth = (float) ($settings['revenue_growth'] ?? 5);
        $referral_adoption_start = (float) ($settings['referral_adoption_start'] ?? $referral_rate);
        $referral_adoption_end = (float) ($settings['referral_adoption_end'] ?? 30);
        
        $projected_data = [];
        if ($project_months > 0) {
            $monthly_avg_revenue = count($monthly_data) > 0 ? array_sum(array_column($monthly_data, 'revenue')) / count($monthly_data) : 0;
            $monthly_avg_costs = count($monthly_data) > 0 ? array_sum(array_column($monthly_data, 'costs')) / count($monthly_data) : 0;
            
            $last_month = max(array_keys($monthly_data));
            $last_month_date = new DateTime($last_month . '-01');
            
            for ($i = 1; $i <= $project_months; $i++) {
                $project_month = clone $last_month_date;
                $project_month->modify("+{$i} months");
                $month_key = $project_month->format('Y-m');
                
                $growth_factor = pow(1 + ($revenue_growth / 100), $i);
                $projected_revenue = $monthly_avg_revenue * $growth_factor;
                
                $adoption_rate = $referral_adoption_start + (($referral_adoption_end - $referral_adoption_start) * ($i / $project_months));
                $adoption_factor = $adoption_rate / max($referral_rate, 1);
                
                $projected_costs = $monthly_avg_costs * $growth_factor * $adoption_factor;
                $projected_profit = $projected_revenue - $projected_costs;
                
                $projected_data[$month_key] = [
                    'revenue' => $projected_revenue,
                    'costs' => $projected_costs,
                    'profit' => $projected_profit,
                ];
                
                $monthly_data[$month_key] = [
                    'revenue' => $projected_revenue,
                    'costs' => $projected_costs,
                    'profit' => $projected_profit,
                ];
            }
        }

        // Prepare chart data
        ksort($monthly_data);
        $chart_labels = [];
        $chart_revenue = [];
        $chart_costs = [];
        $chart_profit = [];
        $is_historical = [];
        
        $historical_months = array_filter(array_keys($monthly_data), function($k) use ($projected_data) {
            return !isset($projected_data[$k]);
        });
        $last_historical_month = !empty($historical_months) ? max($historical_months) : null;
        $last_historical_index = $last_historical_month ? array_search($last_historical_month, array_keys($monthly_data)) : -1;

        foreach ($monthly_data as $month => $data) {
            $chart_labels[] = date_i18n('M Y', strtotime($month . '-01'));
            $chart_revenue[] = round($data['revenue'], 2);
            $chart_costs[] = round($data['costs'], 2);
            $chart_profit[] = round($data['profit'], 2);
            $is_historical[] = !isset($projected_data[$month]);
        }

        $chart_data = [
            'labels' => $chart_labels,
            'revenue' => $chart_revenue,
            'costs' => $chart_costs,
            'profit' => $chart_profit,
            'revenue_label' => __('Revenue', 'intersoccer-referral'),
            'costs_label' => __('Total Costs (Points + Commissions)', 'intersoccer-referral'),
            'profit_label' => __('Net Profit/Loss', 'intersoccer-referral'),
            'is_historical' => $is_historical,
            'last_historical_index' => $last_historical_index,
        ];

        // Generate executive summary
        $executive_summary = $this->generate_executive_summary([
            'total_revenue' => $metrics['total_revenue'],
            'total_costs' => $total_costs,
            'net_profit' => $net_profit,
            'profit_margin' => $profit_margin,
            'total_orders' => $metrics['total_orders'],
            'original_orders' => $metrics['original_orders'] ?? $metrics['total_orders'],
            'growth_orders' => $metrics['growth_orders'] ?? 0,
            'growth_percentage' => $metrics['growth_percentage'] ?? 0,
            'avg_order_value' => $avg_order_value,
            'roi' => $roi,
            'break_even_orders' => $break_even_orders,
            'referral_rate' => $referral_rate,
            'total_points_earned' => $metrics['total_points_earned'],
            'total_commissions' => $metrics['total_commissions'],
            'project_months' => $project_months,
            'projected_data' => $projected_data,
        ]);

        // Generate HTML (simplified for comparison)
        ob_start();
        ?>
        <div class="simulation-results">
            <h4><?php echo esc_html($scenario_name); ?></h4>
            <p>
                <strong><?php esc_html_e('Net Profit:', 'intersoccer-referral'); ?></strong> 
                <?php echo wc_price($net_profit); ?> 
                (<?php echo esc_html(number_format($profit_margin, 2)); ?>% margin)
            </p>
        </div>
        <?php
        $html = ob_get_clean();

        return [
            'html' => $html,
            'chart_data' => $chart_data,
            'executive_summary' => $executive_summary,
            'metrics' => [
                'total_revenue' => $metrics['total_revenue'],
                'total_costs' => $total_costs,
                'net_profit' => $net_profit,
                'profit_margin' => $profit_margin,
                'roi' => $roi,
                'avg_order_value' => $avg_order_value,
                'break_even_orders' => $break_even_orders,
            ],
        ];
    }

    /**
     * Generate executive summary HTML
     */
    private function generate_executive_summary($data) {
        ob_start();
        ?>
        <div class="executive-summary-dashboard" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <!-- ROI Card -->
            <div class="summary-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <h4 style="margin: 0 0 10px 0; font-size: 14px; opacity: 0.9;"><?php esc_html_e('ROI', 'intersoccer-referral'); ?></h4>
                <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;">
                    <?php echo esc_html(number_format($data['roi'], 1)); ?>%
                </div>
                <p style="margin: 0; font-size: 12px; opacity: 0.8;">
                    <?php echo $data['roi'] >= 0 
                        ? esc_html__('Positive return on investment', 'intersoccer-referral')
                        : esc_html__('Negative return - review costs', 'intersoccer-referral'); ?>
                </p>
            </div>

            <!-- Net Profit Card -->
            <div class="summary-card" style="background: linear-gradient(135deg, <?php echo $data['net_profit'] >= 0 ? '#11998e' : '#ee0979'; ?> 0%, <?php echo $data['net_profit'] >= 0 ? '#38ef7d' : '#ff6a00'; ?> 100%); color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <h4 style="margin: 0 0 10px 0; font-size: 14px; opacity: 0.9;"><?php esc_html_e('Net Profit/Loss', 'intersoccer-referral'); ?></h4>
                <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;">
                    <?php echo wc_price($data['net_profit']); ?>
                </div>
                <p style="margin: 0; font-size: 12px; opacity: 0.8;">
                    <?php echo esc_html(number_format($data['profit_margin'], 2)); ?>% <?php esc_html_e('margin', 'intersoccer-referral'); ?>
                </p>
            </div>

            <!-- Total Revenue Card -->
            <div class="summary-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <h4 style="margin: 0 0 10px 0; font-size: 14px; opacity: 0.9;"><?php esc_html_e('Total Revenue', 'intersoccer-referral'); ?></h4>
                <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;">
                    <?php echo wc_price($data['total_revenue']); ?>
                </div>
                <p style="margin: 0; font-size: 12px; opacity: 0.8;">
                    <?php echo esc_html(number_format($data['total_orders'])); ?> <?php esc_html_e('orders', 'intersoccer-referral'); ?>
                    <?php if (!empty($data['growth_orders']) && $data['growth_orders'] > 0): ?>
                        <br><span style="font-size: 11px; opacity: 0.7;">
                            (<?php echo esc_html(number_format($data['original_orders'])); ?> original + <?php echo esc_html(number_format($data['growth_orders'])); ?> growth)
                        </span>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Program Costs Card -->
            <div class="summary-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <h4 style="margin: 0 0 10px 0; font-size: 14px; opacity: 0.9;"><?php esc_html_e('Program Costs', 'intersoccer-referral'); ?></h4>
                <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;">
                    <?php echo wc_price($data['total_costs']); ?>
                </div>
                <p style="margin: 0; font-size: 12px; opacity: 0.8;">
                    <?php 
                    $cost_percentage = $data['total_revenue'] > 0 ? ($data['total_costs'] / $data['total_revenue']) * 100 : 0;
                    echo esc_html(number_format($cost_percentage, 1)); 
                    ?>% <?php esc_html_e('of revenue', 'intersoccer-referral'); ?>
                </p>
            </div>
        </div>

        <!-- Key Insights -->
        <div class="key-insights" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px;">
            <h4 style="margin-top: 0;"><?php esc_html_e('Key Insights', 'intersoccer-referral'); ?></h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
                <div>
                    <strong><?php esc_html_e('Average Order Value:', 'intersoccer-referral'); ?></strong>
                    <?php echo wc_price($data['avg_order_value']); ?>
                </div>
                <div>
                    <strong><?php esc_html_e('Referral Adoption Rate:', 'intersoccer-referral'); ?></strong>
                    <?php echo esc_html(number_format($data['referral_rate'], 1)); ?>%
                </div>
                <?php if ($data['break_even_orders'] > 0): ?>
                <div>
                    <strong><?php esc_html_e('Break-Even Point:', 'intersoccer-referral'); ?></strong>
                    <?php echo esc_html(number_format($data['break_even_orders'])); ?> <?php esc_html_e('orders', 'intersoccer-referral'); ?>
                </div>
                <?php endif; ?>
                <?php if ($data['total_points_earned'] > 0): ?>
                <div>
                    <strong><?php esc_html_e('Total Points Issued:', 'intersoccer-referral'); ?></strong>
                    <?php echo esc_html(number_format($data['total_points_earned'])); ?>
                </div>
                <?php endif; ?>
                <?php if ($data['total_commissions'] > 0): ?>
                <div>
                    <strong><?php esc_html_e('Total Commissions Paid:', 'intersoccer-referral'); ?></strong>
                    <?php echo wc_price($data['total_commissions']); ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($data['growth_percentage']) && $data['growth_percentage'] > 0): ?>
                <div style="background: #fff3cd; padding: 10px; border-radius: 4px; border-left: 4px solid #ffc107;">
                    <strong><?php esc_html_e('Potential Growth Applied:', 'intersoccer-referral'); ?></strong>
                    <?php echo esc_html(number_format($data['growth_percentage'], 1)); ?>%
                    <br><small style="color: #856404;">
                        <?php esc_html_e('Simulated', 'intersoccer-referral'); ?> <?php echo esc_html(number_format($data['growth_orders'])); ?> 
                        <?php esc_html_e('additional orders due to referral-driven growth', 'intersoccer-referral'); ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($data['projected_data'])): ?>
            <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #dee2e6;">
                <h5><?php esc_html_e('Projected Performance', 'intersoccer-referral'); ?></h5>
                <?php
                $projected_total_revenue = array_sum(array_column($data['projected_data'], 'revenue'));
                $projected_total_costs = array_sum(array_column($data['projected_data'], 'costs'));
                $projected_total_profit = $projected_total_revenue - $projected_total_costs;
                $projected_profit_margin = $projected_total_revenue > 0 ? ($projected_total_profit / $projected_total_revenue) * 100 : 0;
                ?>
                <p>
                    <strong><?php esc_html_e('Next', 'intersoccer-referral'); ?> <?php echo esc_html($data['project_months']); ?> <?php esc_html_e('months projection:', 'intersoccer-referral'); ?></strong><br>
                    <?php esc_html_e('Projected Revenue:', 'intersoccer-referral'); ?> <strong><?php echo wc_price($projected_total_revenue); ?></strong><br>
                    <?php esc_html_e('Projected Costs:', 'intersoccer-referral'); ?> <strong><?php echo wc_price($projected_total_costs); ?></strong><br>
                    <?php esc_html_e('Projected Profit:', 'intersoccer-referral'); ?> <strong style="color: <?php echo $projected_total_profit >= 0 ? '#28a745' : '#dc3545'; ?>;">
                        <?php echo wc_price($projected_total_profit); ?> (<?php echo esc_html(number_format($projected_profit_margin, 2)); ?>%)
                    </strong>
                </p>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Save simulator scenario
     */
    public function ajax_save_simulator_scenario() {
        check_ajax_referer('intersoccer_simulator_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'intersoccer-referral')]);
        }

        $name = sanitize_text_field($_POST['name'] ?? '');
        if (empty($name)) {
            wp_send_json_error(['message' => __('Scenario name is required', 'intersoccer-referral')]);
        }

        $settings_json = $_POST['settings'] ?? '';
        $settings = is_string($settings_json) ? json_decode(stripslashes($settings_json), true) : $settings_json;
        
        if (empty($settings)) {
            wp_send_json_error(['message' => __('Settings are required', 'intersoccer-referral')]);
        }

        $scenarios = get_option('intersoccer_simulator_scenarios', []);
        $scenario_id = uniqid('scenario_');
        
        $scenarios[$scenario_id] = [
            'id' => $scenario_id,
            'name' => $name,
            'settings' => $settings,
            'created' => current_time('mysql'),
            'created_timestamp' => time()
        ];

        update_option('intersoccer_simulator_scenarios', $scenarios);
        wp_send_json_success(['scenario_id' => $scenario_id]);
    }

    /**
     * Load simulator scenario
     */
    public function ajax_load_simulator_scenario() {
        check_ajax_referer('intersoccer_simulator_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'intersoccer-referral')]);
        }

        $scenario_id = sanitize_text_field($_POST['scenario_id'] ?? '');
        if (empty($scenario_id)) {
            wp_send_json_error(['message' => __('Scenario ID is required', 'intersoccer-referral')]);
        }

        $scenarios = get_option('intersoccer_simulator_scenarios', []);
        if (!isset($scenarios[$scenario_id])) {
            wp_send_json_error(['message' => __('Scenario not found', 'intersoccer-referral')]);
        }

        $scenario = $scenarios[$scenario_id];
        wp_send_json_success([
            'name' => $scenario['name'],
            'settings' => $scenario['settings']
        ]);
    }

    /**
     * List simulator scenarios
     */
    public function ajax_list_simulator_scenarios() {
        check_ajax_referer('intersoccer_simulator_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'intersoccer-referral')]);
        }

        $scenarios = get_option('intersoccer_simulator_scenarios', []);
        $scenarios_list = [];
        
        foreach ($scenarios as $id => $scenario) {
            $timestamp = isset($scenario['created_timestamp']) ? $scenario['created_timestamp'] : (isset($scenario['created']) ? strtotime($scenario['created']) : 0);
            $scenarios_list[] = [
                'id' => $id,
                'name' => isset($scenario['name']) ? $scenario['name'] : __('Unnamed Scenario', 'intersoccer-referral'),
                'date' => $timestamp > 0 ? date_i18n(get_option('date_format'), $timestamp) : __('Unknown', 'intersoccer-referral'),
                'timestamp' => $timestamp
            ];
        }

        // Sort by creation date, newest first
        usort($scenarios_list, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        wp_send_json_success(['scenarios' => $scenarios_list]);
    }

    /**
     * Delete simulator scenario
     */
    public function ajax_delete_simulator_scenario() {
        check_ajax_referer('intersoccer_simulator_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'intersoccer-referral')]);
        }

        $scenario_id = sanitize_text_field($_POST['scenario_id'] ?? '');
        if (empty($scenario_id)) {
            wp_send_json_error(['message' => __('Scenario ID is required', 'intersoccer-referral')]);
        }

        $scenarios = get_option('intersoccer_simulator_scenarios', []);
        if (isset($scenarios[$scenario_id])) {
            unset($scenarios[$scenario_id]);
            update_option('intersoccer_simulator_scenarios', $scenarios);
        }

        wp_send_json_success();
    }

    /**
     * Load scenario template
     */
    public function ajax_load_scenario_template() {
        check_ajax_referer('intersoccer_simulator_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'intersoccer-referral')]);
        }

        $template = sanitize_text_field($_POST['template'] ?? '');
        $settings = [];

        switch ($template) {
            case 'baseline':
                // No program - zero costs
                $settings = [
                    'referral_rate' => 0,
                    'points_rate_purchase' => 0,
                    'points_rate_referral' => 0,
                    'points_rate_first_time' => 0,
                ];
                break;
            case 'conservative':
                $settings = [
                    'points_rate_purchase' => 15,
                    'points_rate_referral' => 12,
                    'points_rate_first_time' => 10,
                    'referral_rate' => 10,
                ];
                break;
            case 'moderate':
                $settings = [
                    'points_rate_purchase' => 10,
                    'points_rate_referral' => 8,
                    'points_rate_first_time' => 7,
                    'referral_rate' => 20,
                ];
                break;
            case 'aggressive':
                $settings = [
                    'points_rate_purchase' => 7,
                    'points_rate_referral' => 5,
                    'points_rate_first_time' => 4,
                    'referral_rate' => 35,
                ];
                break;
            case 'current':
                return $this->ajax_load_current_settings();
        }

        // Load default commission tiers
        $settings['commission_tiers'] = [
            'coach' => get_option('intersoccer_commission_tiers_coach', []),
            'partner' => get_option('intersoccer_commission_tiers_partner', []),
            'social_influencer' => get_option('intersoccer_commission_tiers_social_influencer', []),
        ];

        wp_send_json_success(['settings' => $settings]);
    }

    /**
     * Load current settings
     */
    public function ajax_load_current_settings() {
        check_ajax_referer('intersoccer_simulator_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'intersoccer-referral')]);
        }

        $settings = [
            'points_mode' => get_option('intersoccer_points_allocation_mode', 'ratio'),
            'percentage_rate' => (float) get_option('intersoccer_points_percentage_rate', 0),
            'points_rate_purchase' => (int) get_option('intersoccer_points_rate_customer_purchase', 10),
            'points_rate_referral' => (int) get_option('intersoccer_points_rate_customer_referral', 10),
            'points_rate_first_time' => (int) get_option('intersoccer_points_rate_first_time_customer', 10),
            'points_value' => (float) get_option('intersoccer_credit_value', 1),
            'referral_rate' => 15, // Default
            'first_time_discount' => (float) get_option('intersoccer_first_time_discount_amount', 10),
            'dist_coach' => 60,
            'dist_partner' => 25,
            'dist_influencer' => 15,
            'commission_tiers' => [
                'coach' => get_option('intersoccer_commission_tiers_coach', []),
                'partner' => get_option('intersoccer_commission_tiers_partner', []),
                'social_influencer' => get_option('intersoccer_commission_tiers_social_influencer', []),
            ],
        ];

        wp_send_json_success(['settings' => $settings]);
    }

    /**
     * Export simulator to Excel
     */
    public function ajax_export_simulator_excel() {
        check_ajax_referer('intersoccer_simulator_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'intersoccer-referral'));
        }

        // TODO: Implement Excel export using PhpSpreadsheet
        wp_send_json_error(['message' => __('Excel export not yet implemented', 'intersoccer-referral')]);
    }

    /**
     * Export simulator to PDF
     */
    public function ajax_export_simulator_pdf() {
        check_ajax_referer('intersoccer_simulator_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'intersoccer-referral'));
        }

        // TODO: Implement PDF export using TCPDF
        wp_send_json_error(['message' => __('PDF export not yet implemented', 'intersoccer-referral')]);
    }

    /**
     * Get recommendations based on historical data
     */
    public function ajax_get_recommendations() {
        check_ajax_referer('intersoccer_simulator_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'intersoccer-referral')]);
        }

        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to = sanitize_text_field($_POST['date_to'] ?? '');
        
        if (empty($date_from) || empty($date_to)) {
            wp_send_json_error(['message' => __('Please provide both start and end dates', 'intersoccer-referral')]);
        }

        global $wpdb;
        
        $date_from_str = date('Y-m-d H:i:s', strtotime($date_from . ' 00:00:00'));
        $date_to_str = date('Y-m-d H:i:s', strtotime($date_to . ' 23:59:59'));
        
        // Get orders
        $orders = wc_get_orders([
            'limit' => -1,
            'status' => ['wc-completed', 'completed'],
            'date_created' => $date_from_str . '...' . $date_to_str,
            'orderby' => 'date',
            'order' => 'ASC',
            'type' => 'shop_order',
        ]);
        
        $orders = array_filter($orders, function($order) {
            return $order && is_a($order, 'WC_Order') && !is_a($order, 'WC_Order_Refund');
        });
        
        // Get actual referrals from database
        $referrals_table = $wpdb->prefix . 'intersoccer_referrals';
        $referrals = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$referrals_table} 
            WHERE created_at >= %s AND created_at <= %s",
            $date_from_str,
            $date_to_str
        ));
        
        // Get commissions
        $commissions_table = $wpdb->prefix . 'intersoccer_coach_commissions';
        $commissions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$commissions_table} 
            WHERE created_at >= %s AND created_at <= %s",
            $date_from_str,
            $date_to_str
        ));
        
        // Get points issued
        $points_table = $wpdb->prefix . 'intersoccer_points_log';
        $points = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$points_table} 
            WHERE created_at >= %s AND created_at <= %s",
            $date_from_str,
            $date_to_str
        ));
        
        // Count coaches
        $coaches = get_users(['role' => 'coach']);
        $coach_count = count($coaches);
        
        $total_orders = count($orders);
        $total_revenue = array_sum(array_map(function($o) { return (float) $o->get_total(); }, $orders));
        
        // Calculate actual rates
        $actual_referral_rate = $total_orders > 0 
            ? (count($referrals) / $total_orders) * 100 
            : 0;
        
        // Calculate average commission per referral
        $total_commissions = array_sum(array_column($commissions, 'amount'));
        $avg_commission = count($referrals) > 0 
            ? $total_commissions / count($referrals) 
            : 0;
        
        // Calculate average order value
        $avg_order_value = $total_orders > 0 
            ? $total_revenue / $total_orders 
            : 0;
        
        // Calculate points per order
        $total_points = array_sum(array_column($points, 'points'));
        $avg_points_per_order = $total_orders > 0 
            ? $total_points / $total_orders 
            : 0;
        
        // Calculate coach referral rate (referrals by coaches / total referrals)
        $coach_referrals = array_filter($referrals, function($r) {
            return isset($r->referrer_type) && $r->referrer_type === 'coach';
        });
        $coach_referral_rate = count($referrals) > 0
            ? (count($coach_referrals) / count($referrals)) * 100
            : 0;
        
        // Calculate customer referral rate
        $customer_referrals = array_filter($referrals, function($r) {
            return isset($r->referrer_type) && $r->referrer_type === 'customer';
        });
        $customer_referral_rate = count($referrals) > 0
            ? (count($customer_referrals) / count($referrals)) * 100
            : 0;
        
        // Calculate actual rates as percentage of orders
        $coach_referral_rate_of_orders = $total_orders > 0
            ? (count($coach_referrals) / $total_orders) * 100
            : 0;
        $customer_referral_rate_of_orders = $total_orders > 0
            ? (count($customer_referrals) / $total_orders) * 100
            : 0;
        
        // Generate recommendations based on historical data
        $recommendations = [];
        
        // Calculate optimal rates based on profitability analysis
        $total_costs = $total_commissions + ($total_points * get_option('intersoccer_credit_value', 1));
        $net_profit = $total_revenue - $total_costs;
        $profit_margin = $total_revenue > 0 ? ($net_profit / $total_revenue) * 100 : 0;
        
        // Optimal referral rate recommendation (aim for 15-20% with good profit margin)
        $optimal_referral_rate = 15; // Base recommendation
        if ($profit_margin < 40 && $actual_referral_rate > 15) {
            // If profit margin is low and referral rate is high, recommend reducing
            $optimal_referral_rate = max(10, $actual_referral_rate - 5);
            $recommendations[] = [
                'type' => 'referral_rate',
                'title' => __('Optimize Referral Rate', 'intersoccer-referral'),
                'current' => number_format($actual_referral_rate, 1) . '%',
                'recommended' => number_format($optimal_referral_rate, 1) . '%',
                'reason' => sprintf(__('Current rate of %s%% with %s%% profit margin. Reducing to %s%% may improve profitability.', 'intersoccer-referral'), 
                    number_format($actual_referral_rate, 1), 
                    number_format($profit_margin, 1),
                    number_format($optimal_referral_rate, 1)
                ),
            ];
        } elseif ($profit_margin > 50 && $actual_referral_rate < 15) {
            // If profit margin is high and referral rate is low, recommend increasing
            $optimal_referral_rate = min(25, $actual_referral_rate + 5);
            $recommendations[] = [
                'type' => 'referral_rate',
                'title' => __('Increase Referral Rate', 'intersoccer-referral'),
                'current' => number_format($actual_referral_rate, 1) . '%',
                'recommended' => number_format($optimal_referral_rate, 1) . '%',
                'reason' => sprintf(__('Strong profit margin (%s%%) allows for increased referral rate to %s%% to drive more growth.', 'intersoccer-referral'),
                    number_format($profit_margin, 1),
                    number_format($optimal_referral_rate, 1)
                ),
            ];
        }
        
        // Coach vs Customer referral rate split recommendation
        if ($coach_referral_rate_of_orders > 0 || $customer_referral_rate_of_orders > 0) {
            $total_referral_rate = $coach_referral_rate_of_orders + $customer_referral_rate_of_orders;
            $coach_ratio = $total_referral_rate > 0 ? ($coach_referral_rate_of_orders / $total_referral_rate) * 100 : 0;
            
            // Optimal split: 60-70% coach, 30-40% customer (coaches drive more value)
            if ($coach_ratio < 50) {
                $recommended_coach_rate = $total_referral_rate * 0.65;
                $recommended_customer_rate = $total_referral_rate * 0.35;
                $recommendations[] = [
                    'type' => 'referral_split',
                    'title' => __('Optimize Coach/Customer Split', 'intersoccer-referral'),
                    'current' => sprintf(__('Coach: %s%%, Customer: %s%%', 'intersoccer-referral'), 
                        number_format($coach_referral_rate_of_orders, 1),
                        number_format($customer_referral_rate_of_orders, 1)
                    ),
                    'recommended' => sprintf(__('Coach: %s%%, Customer: %s%%', 'intersoccer-referral'),
                        number_format($recommended_coach_rate, 1),
                        number_format($recommended_customer_rate, 1)
                    ),
                    'reason' => __('Coach referrals typically drive higher-value customers. Consider incentivizing coaches more.', 'intersoccer-referral'),
                ];
            }
        }
        
        // Commission rate recommendation based on profitability
        if ($avg_commission > 0 && $avg_order_value > 0) {
            $commission_percentage = ($avg_commission / $avg_order_value) * 100;
            $optimal_commission_rate = 12; // Base recommendation
            
            if ($commission_percentage > 18) {
                $optimal_commission_rate = max(10, $commission_percentage - 3);
                $recommendations[] = [
                    'type' => 'commission',
                    'title' => __('Optimize Commission Rates', 'intersoccer-referral'),
                    'current' => number_format($commission_percentage, 1) . '%',
                    'recommended' => number_format($optimal_commission_rate, 1) . '%',
                    'reason' => sprintf(__('Commissions at %s%% of order value are high. Reducing to %s%% may improve profitability while maintaining coach motivation.', 'intersoccer-referral'),
                        number_format($commission_percentage, 1),
                        number_format($optimal_commission_rate, 1)
                    ),
                ];
            } elseif ($commission_percentage < 8 && $profit_margin > 45) {
                $optimal_commission_rate = min(15, $commission_percentage + 2);
                $recommendations[] = [
                    'type' => 'commission',
                    'title' => __('Increase Commission Rates', 'intersoccer-referral'),
                    'current' => number_format($commission_percentage, 1) . '%',
                    'recommended' => number_format($optimal_commission_rate, 1) . '%',
                    'reason' => __('Strong profitability allows for higher commissions to increase coach engagement and referrals.', 'intersoccer-referral'),
                ];
            }
        }
        
        // Points rate recommendation
        if ($avg_points_per_order > 0 && $avg_order_value > 0) {
            $points_value = get_option('intersoccer_credit_value', 1);
            $points_cost_per_order = $avg_points_per_order * $points_value;
            $points_percentage = ($points_cost_per_order / $avg_order_value) * 100;
            
            // Optimal points cost: 2-5% of order value
            if ($points_percentage > 6) {
                $recommendations[] = [
                    'type' => 'points',
                    'title' => __('Reduce Points Allocation', 'intersoccer-referral'),
                    'current' => number_format($points_percentage, 1) . '% of order value',
                    'recommended' => '3-5% of order value',
                    'reason' => __('Points costs are high. Consider increasing points rate (CHF per point) to reduce allocation.', 'intersoccer-referral'),
                ];
            } elseif ($points_percentage < 1.5 && $profit_margin > 50) {
                $recommendations[] = [
                    'type' => 'points',
                    'title' => __('Increase Points Allocation', 'intersoccer-referral'),
                    'current' => number_format($points_percentage, 1) . '% of order value',
                    'recommended' => '3-4% of order value',
                    'reason' => __('Low points allocation. Increasing can improve customer loyalty and retention.', 'intersoccer-referral'),
                ];
            }
        }
        
        wp_send_json_success([
            'recommendations' => $recommendations,
            'historical' => [
                'total_orders' => $total_orders,
                'total_referrals' => count($referrals),
                'actual_referral_rate' => $actual_referral_rate,
                'coach_referral_rate' => $coach_referral_rate_of_orders,
                'customer_referral_rate' => $customer_referral_rate_of_orders,
                'avg_commission' => $avg_commission,
                'avg_order_value' => $avg_order_value,
                'avg_points_per_order' => $avg_points_per_order,
                'coach_count' => $coach_count,
            ],
        ]);
    }

    /**
     * Run sensitivity analysis
     */
    public function ajax_run_sensitivity_analysis() {
        check_ajax_referer('intersoccer_simulator_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'intersoccer-referral')]);
        }

        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to = sanitize_text_field($_POST['date_to'] ?? '');
        
        if (empty($date_from) || empty($date_to)) {
            wp_send_json_error(['message' => __('Please provide both start and end dates', 'intersoccer-referral')]);
        }

        $settings_json = $_POST['settings'] ?? '';
        $base_settings = is_string($settings_json) ? json_decode(stripslashes($settings_json), true) : $settings_json;
        
        if (empty($base_settings)) {
            wp_send_json_error(['message' => __('Settings are required', 'intersoccer-referral')]);
        }

        // Run baseline simulation
        $baseline_result = $this->run_date_range_simulation_internal($base_settings, 'Baseline');
        if (isset($baseline_result['error'])) {
            wp_send_json_error(['message' => $baseline_result['error']]);
        }
        
        $baseline_profit = $baseline_result['metrics']['net_profit'];
        
        // Test each variable with +/- 10% change
        $variables_to_test = [
            'customer_referral_rate' => ['label' => __('Customer Referral Rate', 'intersoccer-referral'), 'base' => $base_settings['customer_referral_rate'] ?? 10],
            'coach_referral_rate' => ['label' => __('Coach Referral Rate', 'intersoccer-referral'), 'base' => $base_settings['coach_referral_rate'] ?? 5],
            'points_rate_purchase' => ['label' => __('Points Rate (Purchase)', 'intersoccer-referral'), 'base' => $base_settings['points_rate_purchase'] ?? 10],
            'points_rate_first_time' => ['label' => __('Points Rate (First-Time)', 'intersoccer-referral'), 'base' => $base_settings['points_rate_first_time'] ?? 10],
            'points_value' => ['label' => __('Points Value', 'intersoccer-referral'), 'base' => $base_settings['points_value'] ?? 1],
            'first_time_discount' => ['label' => __('First-Time Discount', 'intersoccer-referral'), 'base' => $base_settings['first_time_discount'] ?? 10],
        ];
        
        $sensitivity_results = [];
        
        foreach ($variables_to_test as $var_key => $var_info) {
            if ($var_info['base'] == 0) continue; // Skip zero values
            
            // Test +10%
            $test_settings_plus = $base_settings;
            if (in_array($var_key, ['customer_referral_rate', 'coach_referral_rate', 'points_value', 'first_time_discount'])) {
                $test_settings_plus[$var_key] = $var_info['base'] * 1.1;
            } else {
                // For rates, +10% means 10% fewer points (higher rate = fewer points)
                $test_settings_plus[$var_key] = $var_info['base'] * 1.1;
            }
            
            $result_plus = $this->run_date_range_simulation_internal($test_settings_plus, 'Test');
            if (!isset($result_plus['error'])) {
                $profit_plus = $result_plus['metrics']['net_profit'];
                $impact_plus = $baseline_profit != 0 ? (($profit_plus - $baseline_profit) / abs($baseline_profit)) * 100 : 0;
                
                // Test -10%
                $test_settings_minus = $base_settings;
                if (in_array($var_key, ['customer_referral_rate', 'coach_referral_rate', 'points_value', 'first_time_discount'])) {
                    $test_settings_minus[$var_key] = max(0, $var_info['base'] * 0.9);
                } else {
                    $test_settings_minus[$var_key] = max(1, $var_info['base'] * 0.9);
                }
                
                $result_minus = $this->run_date_range_simulation_internal($test_settings_minus, 'Test');
                if (!isset($result_minus['error'])) {
                    $profit_minus = $result_minus['metrics']['net_profit'];
                    $impact_minus = $baseline_profit != 0 ? (($profit_minus - $baseline_profit) / abs($baseline_profit)) * 100 : 0;
                    
                    // Average impact (sensitivity)
                    $avg_impact = ($impact_plus + abs($impact_minus)) / 2;
                    $sensitivity_results[] = [
                        'label' => $var_info['label'],
                        'impact' => $avg_impact,
                        'impact_plus' => $impact_plus,
                        'impact_minus' => $impact_minus,
                    ];
                }
            }
        }
        
        // Sort by absolute impact (highest first)
        usort($sensitivity_results, function($a, $b) {
            return abs($b['impact']) - abs($a['impact']);
        });
        
        $sensitivity_data = [
            'labels' => array_column($sensitivity_results, 'label'),
            'impacts' => array_column($sensitivity_results, 'impact'),
        ];
        
        wp_send_json_success([
            'sensitivity_data' => $sensitivity_data,
            'results' => $sensitivity_results,
        ]);
    }
}