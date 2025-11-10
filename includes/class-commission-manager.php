<?php
// includes/class-commission-manager.php

/**
 * InterSoccer Commission Manager
 *
 * Handles all coach commission and earnings calculations.
 * Separated from points system for better architecture.
 */
class InterSoccer_Commission_Manager {

    /**
     * Singleton instance for reuse and direct method calls.
     *
     * @var InterSoccer_Commission_Manager|null
     */
    private static $instance = null;

    public function __construct() {
        if (self::$instance instanceof self) {
            return;
        }

        self::$instance = $this;

        // Hook into order processing for commission calculations
        add_action('woocommerce_order_status_completed', [$this, 'process_referral_commissions']);

        // Hook for referral rewards (coach points from referral codes)
        add_action('woocommerce_order_status_completed', [$this, 'process_referral_code_rewards']);
    }

    /**
     * Retrieve the singleton instance.
     *
     * @return self
     */
    public static function get_instance() {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Calculate base commission based on coach tier (customer count)
     */
    public static function calculate_base_commission($order, $coach_id) {
        $commissionable = self::get_commissionable_amount($order);

        // Get coach's customer count to determine tier
        $customer_count = self::get_coach_customer_count($coach_id);

        // Tiered commission rates based on recruited customers
        if ($customer_count >= 25) {
            $commission_rate = 0.20; // 20% for 25+ customers
        } elseif ($customer_count >= 11) {
            $commission_rate = 0.15; // 15% for 11-24 customers
        } else {
            $commission_rate = 0.10; // 10% for 1-10 customers
        }

        return $commissionable * $commission_rate;
    }

    /**
     * Get coach's total customer count
     */
    public static function get_coach_customer_count($coach_id) {
        global $wpdb;
        $referrals_table = $wpdb->prefix . 'intersoccer_referrals';

        // Count unique customers referred by this coach
        $customer_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT customer_id)
            FROM $referrals_table
            WHERE coach_id = %d AND status = 'completed'
        ", $coach_id));

        return intval($customer_count);
    }

    /**
     * Determine the commissionable order total, resilient to discounts applied at checkout.
     *
     * @param WC_Order|object $order
     * @return float
     */
    private static function get_commissionable_amount($order) {
        $net_total = 0.0;

        if (is_object($order) && method_exists($order, 'get_total')) {
            $net_total = (float) $order->get_total();
        }

        if (is_object($order) && method_exists($order, 'get_total_tax')) {
            $net_total -= (float) $order->get_total_tax();
        }

        if ($net_total < 0) {
            $net_total = 0.0;
        }

        return $net_total;
    }

    /**
     * Calculate partnership commission (ongoing commission based on coach tier)
     */
    public static function calculate_partnership_commission($order, $coach_id) {
        $commissionable = self::get_commissionable_amount($order);

        // Use same tiered rates as regular commissions for partnerships
        $customer_count = self::get_coach_customer_count($coach_id);
        if ($customer_count >= 25) {
            $base_rate = 0.20; // 20% for 25+ customers
        } elseif ($customer_count >= 11) {
            $base_rate = 0.15; // 15% for 11-24 customers
        } else {
            $base_rate = 0.10; // 10% for 1-10 customers
        }

        // Apply tier multiplier (additional bonus)
        $tier_bonus = self::calculate_tier_bonus($coach_id, $commissionable * $base_rate);

        return [
            'base_commission' => round($commissionable * $base_rate, 2),
            'tier_bonus' => round($tier_bonus, 2),
            'total_amount' => round(($commissionable * $base_rate) + $tier_bonus, 2)
        ];
    }

    /**
     * Calculate tier bonus multiplier
     */
    public static function calculate_tier_bonus($coach_id, $base_amount) {
        $tier = self::get_coach_tier($coach_id);

        $multipliers = [
            'Bronze' => 0,      // Base rate
            'Silver' => 0.02,   // +2%
            'Gold' => 0.05,     // +5%
            'Platinum' => 0.10  // +10%
        ];

        $multiplier = $multipliers[$tier] ?? 0;
        return $base_amount * $multiplier;
    }

    /**
     * Get coach tier based on performance
     */
    public static function get_coach_tier($coach_id) {
        global $wpdb;
        $referrals_table = $wpdb->prefix . 'intersoccer_referrals';

        // Get coach's total successful referrals
        $referral_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $referrals_table
            WHERE coach_id = %d AND status = 'completed'
        ", $coach_id));

        // Tier thresholds
        if ($referral_count >= get_option('intersoccer_tier_platinum', 20)) {
            return 'Platinum';
        } elseif ($referral_count >= get_option('intersoccer_tier_gold', 10)) {
            return 'Gold';
        } elseif ($referral_count >= get_option('intersoccer_tier_silver', 5)) {
            return 'Silver';
        } else {
            return 'Bronze';
        }
    }

    /**
     * Process referral commissions when orders are completed
     */
    public function process_referral_commissions($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $customer_id = $order->get_customer_id();
        if (!$customer_id) return;

        // Check for existing referral
        global $wpdb;
        $referrals_table = $wpdb->prefix . 'intersoccer_referrals';
        $referral = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $referrals_table WHERE order_id = %d",
            $order_id
        ));

        if ($referral) {
            $commission_data = self::calculate_total_commission(
                $order,
                $referral->coach_id,
                $customer_id,
                $referral->purchase_count
            );

            if (!$order->has_status(['completed', 'wc-completed'])) {
                do_action('intersoccer_commission_skipped', $order_id, $referral->coach_id, 'order_not_completed');
                return;
            }

            // Update referral record with commission details
            $wpdb->update(
                $referrals_table,
                [
                    'commission_amount' => $commission_data['base_commission'],
                    'loyalty_bonus' => $commission_data['loyalty_bonus'],
                    'retention_bonus' => $commission_data['retention_bonus'] + $commission_data['network_bonus'],
                    'status' => 'completed',
                    'conversion_date' => current_time('mysql')
                ],
                ['id' => $referral->id]
            );

            // Update coach credits (commission earnings)
            $current_credits = (float) get_user_meta($referral->coach_id, 'intersoccer_credits', true);
            update_user_meta(
                $referral->coach_id,
                'intersoccer_credits',
                $current_credits + $commission_data['total_amount']
            );

            $this->record_commission_credit(
                (int) $referral->id,
                (int) $referral->coach_id,
                (int) $customer_id,
                (int) $order_id,
                (float) $commission_data['total_amount'],
                'commission',
                $order,
                $commission_data
            );

            // Log commission payment for audit
            do_action('intersoccer_commission_paid', $referral->coach_id, $commission_data['total_amount'], $order_id);

            // Log commission payment (debug mode only)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("InterSoccer Referral: Commission paid - Coach {$referral->coach_id} earned {$commission_data['total_amount']} CHF for order {$order_id}");
            }

            // Notify coach
            $this->notify_coach_of_commission($referral->coach_id, $order_id, $commission_data);
        }

        // Process partnership commissions if applicable
        $this->process_partnership_commissions($order_id, $customer_id, $referral);
    }

    /**
     * Process referral code rewards (coach points from referral codes)
     */
    public function process_referral_code_rewards($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $customer_id = $order->get_customer_id();
        if (!$customer_id) return;

        $referral_code = null;
        $referral_coach_id = null;

        if (function_exists('WC') && WC()->session) {
            $referral_code = WC()->session->get('intersoccer_applied_referral_code');
            $referral_coach_id = WC()->session->get('intersoccer_referral_coach_id');
        }

        if (empty($referral_code)) {
            $referral_code = get_post_meta($order_id, '_intersoccer_referral_code', true);
        }

        if (empty($referral_coach_id)) {
            $referral_coach_id = get_post_meta($order_id, '_intersoccer_referring_coach_id', true);
        }

        if ($referral_code && $referral_coach_id) {
            // Check if this is the customer's first completed order
            $customer_orders = wc_get_orders([
                'customer_id' => $customer_id,
                'status' => 'completed',
                'limit' => 1
            ]);

            // If this is their first completed order, award points to coach
            if (count($customer_orders) === 1 && $customer_orders[0]->get_id() === $order_id) {
                $points_to_award = 50; // Award 50 points to coach for successful referral

                // Get current coach points balance (note: this is different from commission credits)
                $current_coach_points = get_user_meta($referral_coach_id, 'intersoccer_points_balance', true) ?: 0;
                $new_coach_points = $current_coach_points + $points_to_award;
                update_user_meta($referral_coach_id, 'intersoccer_points_balance', $new_coach_points);

                // Record the referral reward
                global $wpdb;
                $rewards_table = $wpdb->prefix . 'intersoccer_referral_rewards';
                $wpdb->insert(
                    $rewards_table,
                    [
                        'coach_id' => $referral_coach_id,
                        'customer_id' => $customer_id,
                        'order_id' => $order_id,
                        'referral_code' => $referral_code,
                        'points_awarded' => $points_to_award,
                        'discount_amount' => 10.00, // 10 CHF discount given to customer
                        'created_at' => current_time('mysql')
                    ]
                );

                // Add order note
                $coach_info = get_userdata($referral_coach_id);
                $order->add_order_note(sprintf(
                    __('Awarded %d referral points to coach %s. New balance: %d points', 'intersoccer-referral'),
                    $points_to_award,
                    $coach_info->display_name,
                    $new_coach_points
                ));

                if (function_exists('WC') && WC()->session) {
                    WC()->session->set('intersoccer_applied_referral_code', null);
                    WC()->session->set('intersoccer_referral_coach_id', null);
                }

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("InterSoccer Referral: Referral reward - Coach {$referral_coach_id} earned {$points_to_award} points for referral code usage on order {$order_id}");
                }
            }
        }
    }

    /**
     * Process partnership commissions
     */
    private function process_partnership_commissions($order_id, $customer_id, $existing_referral = null) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        if (!$order->has_status(['completed', 'wc-completed'])) {
            do_action('intersoccer_commission_skipped', $order_id, 0, 'order_not_completed');
            return;
        }

        // Check for partnership coach
        $partnership_coach_id = get_user_meta($customer_id, 'intersoccer_partnership_coach_id', true);
        if (!$partnership_coach_id) return;

        // Check partnership cooldown
        $cooldown_end = get_user_meta($customer_id, 'intersoccer_partnership_switch_cooldown', true);
        if ($cooldown_end && strtotime($cooldown_end) > time()) {
            return;
        }

        // Calculate partnership commission
        $partnership_commission = self::calculate_partnership_commission($order, $partnership_coach_id);

        if ($existing_referral && (int) $existing_referral->coach_id === (int) $partnership_coach_id) {
            $this->merge_partnership_commission_into_referral($existing_referral, $partnership_commission, $order);
            return;
        }

        // Avoid duplicate referrals if one already exists for this order/coach pair
        global $wpdb;
        $referrals_table = $wpdb->prefix . 'intersoccer_referrals';

        $existing_partnership_referral = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$referrals_table} WHERE order_id = %d AND coach_id = %d",
            $order_id,
            $partnership_coach_id
        ));

        if ($existing_partnership_referral) {
            // Already recorded – nothing further to do to avoid duplicate ledger entries.
            return;
        }

        // Handle stacking if same coach as referral (already returned above) or separate partnership coach
        $total_commission = $partnership_commission['total_amount'];
        $commission_type = 'partnership';

        $wpdb->insert($referrals_table, [
            'coach_id' => $partnership_coach_id,
            'customer_id' => $customer_id,
            'referrer_id' => $partnership_coach_id,
            'referrer_type' => 'coach',
            'order_id' => $order_id,
            'commission_amount' => $partnership_commission['base_commission'],
            'loyalty_bonus' => 0, // Partnerships don't get loyalty bonuses
            'retention_bonus' => $partnership_commission['tier_bonus'],
            'status' => 'completed',
            'purchase_count' => 0, // Ongoing partnership
            'referral_code' => 'PARTNERSHIP_' . $partnership_coach_id,
            'conversion_date' => current_time('mysql')
        ]);

        // Update coach credits
        $current_credits = (float) get_user_meta($partnership_coach_id, 'intersoccer_credits', true);
        update_user_meta(
            $partnership_coach_id,
            'intersoccer_credits',
            $current_credits + $total_commission
        );

        $partnership_referral_id = property_exists($wpdb, 'insert_id') ? (int) $wpdb->insert_id : 0;
        if (!$partnership_referral_id && $existing_referral && $existing_referral->coach_id == $partnership_coach_id) {
            $partnership_referral_id = (int) $existing_referral->id;
        }

        $this->record_commission_credit(
            $partnership_referral_id,
            (int) $partnership_coach_id,
            (int) $customer_id,
            (int) $order_id,
            (float) $total_commission,
            $commission_type,
            $order,
            $partnership_commission
        );

        // Update partnership order count
        $partnership_orders = (int) get_user_meta($customer_id, 'intersoccer_partnership_order_count', true);
        update_user_meta($customer_id, 'intersoccer_partnership_order_count', $partnership_orders + 1);

        // Log partnership commission (debug mode only)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("InterSoccer Referral: Partnership commission - Customer {$customer_id}, Coach {$partnership_coach_id}, Amount {$total_commission} CHF");
        }

        // Notify coach
        $this->notify_coach_of_partnership_commission($partnership_coach_id, $order_id, $partnership_commission, $commission_type);
    }

    /**
     * Recalculate commissions for previously completed orders.
     *
     * @param array<string,mixed> $options {
     *     @type int|null           $order_id Specific order ID to sync.
     *     @type int                $limit    Maximum orders to process when scanning (default 100).
     *     @type array<int,string>  $statuses Post statuses to include when scanning orders.
     * }
     * @return int Number of orders processed.
     */
    public function sync_commissions(array $options = []) {
        $defaults = [
            'order_id' => null,
            'limit' => 100,
            'statuses' => ['completed', 'approved', 'paid'],
        ];

        $options = array_merge($defaults, $options);

        $referrals = $this->get_referrals_for_sync($options);
        $processed = 0;

        foreach ($referrals as $referral) {
            $processed += $this->sync_commission_from_referral($referral);
        }

        if (defined('WP_CLI') && WP_CLI) {
            if ($processed === 0) {
                \WP_CLI::log('No eligible referrals found to sync.');
            } else {
                \WP_CLI::success(sprintf('Synced %d referral commission record(s).', $processed));
            }
        }

        return $processed;
    }

    private function get_referrals_for_sync(array $options) {
        global $wpdb;

        $table = $wpdb->prefix . 'intersoccer_referrals';
        $limit = max(1, (int) $options['limit']);

        if (!empty($options['order_id'])) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE order_id = %d ORDER BY id DESC LIMIT %d",
                (int) $options['order_id'],
                $limit
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY conversion_date DESC, id DESC LIMIT %d",
                $limit
            );
        }

        if (!method_exists($wpdb, 'get_results')) {
            return [];
        }

        $results = $wpdb->get_results($sql) ?: [];

        $allowed_statuses = array_filter(array_map(function ($status) {
            $status = strtolower(trim((string) $status));
            return $status ? $status : null;
        }, !empty($options['statuses']) ? (array) $options['statuses'] : []));

        if (empty($allowed_statuses)) {
            $allowed_statuses = ['completed', 'approved', 'paid'];
        }

        $filtered = array_filter($results, function ($row) use ($allowed_statuses) {
            $status = isset($row->status) ? strtolower(trim((string) $row->status)) : '';
            return in_array($status, $allowed_statuses, true);
        });

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::log(sprintf('Loaded %d referral record(s) for commission sync.', count($filtered)));
        }

        return array_values($filtered);
    }

    private function sync_commission_from_referral($referral) {
        if (!is_object($referral)) {
            return 0;
        }

        $status = isset($referral->status) ? strtolower(trim((string) $referral->status)) : '';
        if (!in_array($status, ['completed', 'approved', 'paid'], true)) {
            return 0;
        }

        $referral_id = isset($referral->id) ? (int) $referral->id : 0;
        $order_id = isset($referral->order_id) ? (int) $referral->order_id : 0;
        $coach_id = isset($referral->coach_id) ? (int) $referral->coach_id : 0;
        $customer_id = isset($referral->customer_id) ? (int) $referral->customer_id : 0;

        if ($referral_id <= 0 || $order_id <= 0 || $coach_id <= 0) {
            return 0;
        }

        $base = isset($referral->commission_amount) ? (float) $referral->commission_amount : 0.0;
        $loyalty = isset($referral->loyalty_bonus) ? (float) $referral->loyalty_bonus : 0.0;
        $retention = isset($referral->retention_bonus) ? (float) $referral->retention_bonus : 0.0;
        $network = property_exists($referral, 'network_bonus') ? (float) $referral->network_bonus : 0.0;
        $tier = property_exists($referral, 'tier_bonus') ? (float) $referral->tier_bonus : 0.0;
        $seasonal = property_exists($referral, 'seasonal_bonus') ? (float) $referral->seasonal_bonus : 0.0;
        $weekend = property_exists($referral, 'weekend_bonus') ? (float) $referral->weekend_bonus : 0.0;

        $total = $base + $loyalty + $retention + $network + $tier + $seasonal + $weekend;

        if ($total <= 0) {
            return 0;
        }

        $order = wc_get_order($order_id);

        $breakdown = [
            'referral_id' => $referral_id,
            'base_commission' => $base,
            'loyalty_bonus' => $loyalty,
            'retention_bonus' => $retention,
            'network_bonus' => $network,
            'tier_bonus' => $tier,
            'seasonal_bonus' => $seasonal,
            'weekend_bonus' => $weekend,
            'purchase_count' => isset($referral->purchase_count) ? (int) $referral->purchase_count : null,
            'referral_code' => isset($referral->referral_code) ? $referral->referral_code : null,
            'conversion_date' => isset($referral->conversion_date) ? $referral->conversion_date : null,
        ];

        $type = 'commission';
        if (!empty($referral->referral_code) && strpos($referral->referral_code, 'PARTNERSHIP_') === 0) {
            $type = 'partnership';
        } elseif (isset($referral->purchase_count) && (int) $referral->purchase_count === 0) {
            $type = 'partnership';
        }

        $this->record_commission_credit(
            $referral_id,
            $coach_id,
            $customer_id,
            $order_id,
            $total,
            $type,
            $order,
            array_filter($breakdown, static function ($value) {
                return $value !== null;
            })
        );

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::log(sprintf('Synced referral #%d for order #%d (coach %d).', $referral_id, $order_id, $coach_id));
        }

        return 1;
    }

    /**
     * Calculate complete commission structure for an order
     */
    public static function calculate_total_commission($order, $coach_id, $customer_id, $purchase_count) {
        $base_commission = self::calculate_base_commission($order, $coach_id);
        $loyalty_bonus = self::calculate_loyalty_bonus($order, $purchase_count);
        $retention_bonus = self::calculate_retention_bonus($customer_id, date('Y'));
        $network_bonus = self::calculate_network_bonus($customer_id);
        $tier_bonus = self::calculate_tier_bonus($coach_id, $base_commission);
        $seasonal_bonus = self::calculate_seasonal_bonus($base_commission);
        $weekend_bonus = self::calculate_weekend_bonus($base_commission);

        return [
            'base_commission' => round($base_commission, 2),
            'loyalty_bonus' => round($loyalty_bonus, 2),
            'retention_bonus' => round($retention_bonus, 2),
            'network_bonus' => round($network_bonus, 2),
            'tier_bonus' => round($tier_bonus, 2),
            'seasonal_bonus' => round($seasonal_bonus, 2),
            'weekend_bonus' => round($weekend_bonus, 2),
            'total_amount' => round($base_commission + $loyalty_bonus + $retention_bonus + $network_bonus + $tier_bonus + $seasonal_bonus + $weekend_bonus, 2)
        ];
    }

    /**
     * Calculate loyalty bonus based on purchase count
     */
    public static function calculate_loyalty_bonus($order, $purchase_count) {
        $commissionable = self::get_commissionable_amount($order);

        // Get loyalty bonus rates from settings
        $first_loyalty = get_option('intersoccer_loyalty_bonus_first', 5) / 100;
        $second_loyalty = get_option('intersoccer_loyalty_bonus_second', 8) / 100;
        $third_loyalty = get_option('intersoccer_loyalty_bonus_third', 15) / 100;

        switch ($purchase_count) {
            case 1:
                return $commissionable * $first_loyalty;
            case 2:
                return $commissionable * $second_loyalty;
            default:
                return $commissionable * $third_loyalty;
        }
    }

    /**
     * Calculate retention bonus for returning customers
     */
    public static function calculate_retention_bonus($customer_id, $current_season) {
        $retention_bonus = 0;

        // Check if customer is returning from previous seasons
        $previous_orders = self::get_customer_seasonal_orders($customer_id);
        $seasons_participated = count($previous_orders);

        if ($seasons_participated >= 2) {
            // Customer returns for Season 2+
            $season_2_bonus = get_option('intersoccer_retention_season_2', 25);
            $retention_bonus += $season_2_bonus;
        }

        if ($seasons_participated >= 3) {
            // Customer returns for Season 3+
            $season_3_bonus = get_option('intersoccer_retention_season_3', 50);
            $retention_bonus += $season_3_bonus;
        }

        return $retention_bonus;
    }

    /**
     * Calculate network effect bonus
     */
    public static function calculate_network_bonus($customer_id) {
        global $wpdb;
        $referrals_table = $wpdb->prefix . 'intersoccer_referrals';

        // Check if this customer has referred others
        $referrals_made = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $referrals_table WHERE customer_id = %d AND status = 'completed'",
            $customer_id
        ));

        if ($referrals_made > 0) {
            return get_option('intersoccer_network_effect_bonus', 15);
        }

        return 0;
    }

    /**
     * Calculate seasonal bonus
     */
    public static function calculate_seasonal_bonus($base_amount, $order_date = null) {
        if (!$order_date) {
            $order_date = current_time('mysql');
        }

        $month = date('m', strtotime($order_date));
        $seasonal_multiplier = 1.0;

        // Back to school season (August-September)
        if (in_array($month, ['08', '09'])) {
            $seasonal_multiplier = 1.5; // 50% bonus
        }
        // Holiday season (November-December)
        elseif (in_array($month, ['11', '12'])) {
            $seasonal_multiplier = 1.3; // 30% bonus
        }
        // Spring season (March-April)
        elseif (in_array($month, ['03', '04'])) {
            $seasonal_multiplier = 1.2; // 20% bonus
        }

        return $base_amount * ($seasonal_multiplier - 1); // Return only the bonus amount
    }

    /**
     * Calculate weekend bonus
     */
    public static function calculate_weekend_bonus($base_amount, $order_date = null) {
        if (!$order_date) {
            $order_date = current_time('mysql');
        }

        $day_of_week = date('N', strtotime($order_date)); // 1 = Monday, 7 = Sunday

        // Weekend bonus (Saturday and Sunday)
        if (in_array($day_of_week, [6, 7])) {
            return $base_amount * 0.1; // 10% weekend bonus
        }

        return 0;
    }

    /**
     * Get customer's seasonal order history
     */
    private static function get_customer_seasonal_orders($customer_id) {
        $orders = wc_get_orders([
            'customer' => $customer_id,
            'status' => 'completed',
            'limit' => -1
        ]);

        $seasonal_orders = [];
        foreach ($orders as $order) {
            $year = date('Y', strtotime($order->get_date_created()));
            if (!in_array($year, $seasonal_orders)) {
                $seasonal_orders[] = $year;
            }
        }

        return $seasonal_orders;
    }

    /**
     * Notify coach of commission
     */
    private function notify_coach_of_commission($coach_id, $order_id, $commission_data) {
        if (!get_option('intersoccer_enable_email_notifications', 1)) {
            return;
        }

        $coach = get_user_by('ID', $coach_id);
        if (!$coach) return;

        $subject = sprintf(__('New Commission Earned - Order #%d', 'intersoccer-referral'), $order_id);

        $message = sprintf(
            __('Congratulations %s!

You\'ve earned a new commission:

💰 Base Commission: %.2f CHF
🎯 Loyalty Bonus: %.2f CHF
🔄 Retention Bonus: %.2f CHF
🏆 Tier Bonus: %.2f CHF
🎉 Seasonal Bonus: %.2f CHF
⚡ Weekend Bonus: %.2f CHF

💳 Total Earned: %.2f CHF

Your current tier: %s
Total credits: %.2f CHF

Keep up the excellent work!

Best regards,
The InterSoccer Team', 'intersoccer-referral'),
            $coach->display_name,
            $commission_data['base_commission'],
            $commission_data['loyalty_bonus'],
            $commission_data['retention_bonus'],
            $commission_data['tier_bonus'],
            $commission_data['seasonal_bonus'],
            $commission_data['weekend_bonus'],
            $commission_data['total_amount'],
            self::get_coach_tier($coach_id),
            get_user_meta($coach_id, 'intersoccer_credits', true) ?: 0
        );

        wp_mail($coach->user_email, $subject, $message);
    }

    /**
     * Notify coach of partnership commission
     */
    private function notify_coach_of_partnership_commission($coach_id, $order_id, $commission_data, $type = 'partnership') {
        if (!get_option('intersoccer_enable_email_notifications', 1)) {
            return;
        }

        $coach = get_user_by('ID', $coach_id);
        if (!$coach) return;

        $subject = sprintf(__('Partnership Commission Earned - Order #%d', 'intersoccer-referral'), $order_id);

        $type_label = $type === 'partnership_stacked' ? 'Partnership + Referral Bonus' : 'Partnership';

        $message = sprintf(
            __('Great news %s!

    Your partner made a purchase and you earned:

    🤝 %s Commission: %.2f CHF
    🏆 Tier Bonus: %.2f CHF
    💳 Total Earned: %.2f CHF

    Your current tier: %s
    Total credits: %.2f CHF

    Partnership earnings are growing!

    Best regards,
    The InterSoccer Team', 'intersoccer-referral'),
            $coach->display_name,
            $type_label,
            $commission_data['base_commission'],
            $commission_data['tier_bonus'],
            $commission_data['total_amount'],
            self::get_coach_tier($coach_id),
            get_user_meta($coach_id, 'intersoccer_credits', true) ?: 0
        );

        wp_mail($coach->user_email, $subject, $message);
    }

    /**
     * Get coach commission statistics
     */
    public static function get_coach_commission_stats($coach_id, $period = 'month') {
        global $wpdb;
        $referrals_table = $wpdb->prefix . 'intersoccer_referrals';

        $date_clause = '';
        switch ($period) {
            case 'week':
                $date_clause = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                break;
            case 'month':
                $date_clause = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)';
                break;
            case 'quarter':
                $date_clause = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)';
                break;
            case 'year':
                $date_clause = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)';
                break;
        }

        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) as total_referrals,
                SUM(commission_amount + loyalty_bonus + retention_bonus) as total_earnings,
                AVG(commission_amount) as avg_commission
            FROM $referrals_table
            WHERE coach_id = %d AND status = 'completed' $date_clause
        ", $coach_id));

        return [
            'total_referrals' => intval($stats->total_referrals),
            'total_earnings' => floatval($stats->total_earnings),
            'avg_commission' => floatval($stats->avg_commission),
            'tier' => self::get_coach_tier($coach_id)
        ];
    }

    private function merge_partnership_commission_into_referral($existing_referral, array $partnership_commission, $order) {
        if (empty($existing_referral) || empty($partnership_commission)) {
            return;
        }

        $additional_credit = isset($partnership_commission['total_amount'])
            ? (float) $partnership_commission['total_amount']
            : 0.0;

        if ($additional_credit <= 0) {
            return;
        }

        $coach_id = (int) $existing_referral->coach_id;
        $customer_id = (int) $existing_referral->customer_id;
        $order_id = (int) $existing_referral->order_id;
        $purchase_count = isset($existing_referral->purchase_count) ? (int) $existing_referral->purchase_count : 0;

        $commission_data = self::calculate_total_commission($order, $coach_id, $customer_id, $purchase_count);

        $existing_retention = (float) $commission_data['retention_bonus'] + (float) $commission_data['network_bonus'];
        $updated_retention = round($existing_retention + $additional_credit, 2);

        global $wpdb;
        $referrals_table = $wpdb->prefix . 'intersoccer_referrals';

        $wpdb->update(
            $referrals_table,
            [
                'commission_amount' => round($commission_data['base_commission'], 2),
                'loyalty_bonus' => round($commission_data['loyalty_bonus'], 2),
                'retention_bonus' => $updated_retention,
                'status' => 'completed',
                'conversion_date' => current_time('mysql'),
            ],
            ['id' => (int) $existing_referral->id]
        );

        // Update in-memory reference for downstream logic.
        $existing_referral->commission_amount = round($commission_data['base_commission'], 2);
        $existing_referral->loyalty_bonus = round($commission_data['loyalty_bonus'], 2);
        $existing_referral->retention_bonus = $updated_retention;

        // Award additional partnership credit on top of the original commission.
        $current_credits = (float) get_user_meta($coach_id, 'intersoccer_credits', true);
        update_user_meta($coach_id, 'intersoccer_credits', $current_credits + $additional_credit);

        $combined_total = round($commission_data['total_amount'] + $additional_credit, 2);

        $breakdown = array_merge(
            $commission_data,
            [
                'partnership_base' => isset($partnership_commission['base_commission'])
                    ? (float) $partnership_commission['base_commission']
                    : 0.0,
                'partnership_tier_bonus' => isset($partnership_commission['tier_bonus'])
                    ? (float) $partnership_commission['tier_bonus']
                    : 0.0,
                'partnership_total' => $additional_credit,
                'total_amount' => $combined_total,
            ]
        );

        $this->record_commission_credit(
            (int) $existing_referral->id,
            $coach_id,
            $customer_id,
            $order_id,
            $combined_total,
            'commission',
            $order,
            $breakdown
        );
    }

    private function record_coach_commission_entry($coach_id, $order_id, $amount, $type, $order = null, array $breakdown = []) {
        if ($coach_id <= 0 || $order_id <= 0 || $amount <= 0) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'intersoccer_coach_commissions';

        $order_total = 0.0;
        if ($order) {
            $order_total = self::get_commissionable_amount($order);
        }

        $commission_rate = ($order_total > 0)
            ? round(($amount / $order_total) * 100, 2)
            : 0.0;

        if (isset($wpdb->delete) && is_callable($wpdb->delete)) {
            $wpdb->delete($table, ['order_id' => $order_id, 'coach_id' => $coach_id]);
        }

        $wpdb->insert($table, [
            'coach_id' => $coach_id,
            'order_id' => $order_id,
            'commission_amount' => round($amount, 2),
            'commission_rate' => $commission_rate,
            'order_total' => round(max($order_total, 0), 2),
            'currency' => 'CHF',
            'status' => 'approved',
            'notes' => !empty($breakdown) ? wp_json_encode($breakdown) : null,
            'created_at' => current_time('mysql'),
        ]);

        if (isset($wpdb->insert_id)) {
            $this->last_coach_commission_id = (int) $wpdb->insert_id;
        } else {
            $this->last_coach_commission_id = 0;
        }
    }

    private function record_commission_credit($referral_id, $coach_id, $customer_id, $order_id, $amount, $type = 'commission', $order = null, array $breakdown = []) {
        if ($referral_id <= 0 || $coach_id <= 0 || $amount <= 0) {
            return;
        }

        global $wpdb;
        $credits_table = $wpdb->prefix . 'intersoccer_referral_credits';

        if (isset($wpdb->delete) && is_callable($wpdb->delete)) {
            $wpdb->delete($credits_table, ['referral_id' => $referral_id]);
        }

        $this->record_coach_commission_entry($coach_id, $order_id, $amount, $type, $order, $breakdown);

        $coach_commission_id = property_exists($this, 'last_coach_commission_id') ? (int) $this->last_coach_commission_id : 0;
        $referral_credit_id = 0;

        $wpdb->insert($credits_table, [
            'referral_id'   => $referral_id,
            'coach_id'      => $coach_id,
            'customer_id'   => $customer_id,
            'order_id'      => $order_id,
            'credit_amount' => round($amount, 2),
            'credit_type'   => $type,
            'status'        => 'earned',
            'expires_at'    => null,
            'created_at'    => current_time('mysql'),
            'updated_at'    => current_time('mysql'),
        ]);

        $this->last_coach_commission_id = 0;
    }
}