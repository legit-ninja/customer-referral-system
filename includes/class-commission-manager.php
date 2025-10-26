<?php
// includes/class-commission-manager.php

/**
 * InterSoccer Commission Manager
 *
 * Handles all coach commission and earnings calculations.
 * Separated from points system for better architecture.
 */
class InterSoccer_Commission_Manager {

    public function __construct() {
        // Hook into order processing for commission calculations
        add_action('woocommerce_order_status_processing', [$this, 'process_referral_commissions']);
        add_action('woocommerce_order_status_completed', [$this, 'process_referral_commissions']);

        // Hook for referral rewards (coach points from referral codes)
        add_action('woocommerce_order_status_completed', [$this, 'process_referral_code_rewards']);
    }

    /**
     * Calculate base commission based on purchase count
     */
    public static function calculate_base_commission($order, $purchase_count) {
        $total = $order->get_total() - $order->get_total_tax();

        // Get commission rates from settings
        $first_rate = get_option('intersoccer_commission_first', 15) / 100;
        $second_rate = get_option('intersoccer_commission_second', 7.5) / 100;
        $third_rate = get_option('intersoccer_commission_third', 5) / 100;

        switch ($purchase_count) {
            case 1:
                return $total * $first_rate;
            case 2:
                return $total * $second_rate;
            default:
                return $total * $third_rate;
        }
    }

    /**
     * Calculate partnership commission (ongoing 5% commission)
     */
    public static function calculate_partnership_commission($order, $coach_id) {
        $total = $order->get_total() - $order->get_total_tax();
        $base_rate = 0.05; // 5% base partnership commission

        // Apply tier multiplier
        $tier_bonus = self::calculate_tier_bonus($coach_id, $total * $base_rate);

        return [
            'base_commission' => round($total * $base_rate, 2),
            'tier_bonus' => round($tier_bonus, 2),
            'total_amount' => round(($total * $base_rate) + $tier_bonus, 2)
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

            // Log commission payment for audit
            do_action('intersoccer_commission_paid', $referral->coach_id, $commission_data['total_amount'], $order_id);

            // Log commission payment
            error_log("Commission paid: Coach {$referral->coach_id} earned {$commission_data['total_amount']} CHF for order {$order_id}");

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

        // Check if this customer used a referral code
        $referral_code = WC()->session->get('intersoccer_applied_referral_code');
        $referral_coach_id = WC()->session->get('intersoccer_referral_coach_id');

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

                // Clear referral session data
                WC()->session->set('intersoccer_applied_referral_code', null);
                WC()->session->set('intersoccer_referral_coach_id', null);

                error_log("Referral reward: Coach {$referral_coach_id} earned {$points_to_award} points for referral code usage on order {$order_id}");
            }
        }
    }

    /**
     * Process partnership commissions
     */
    private function process_partnership_commissions($order_id, $customer_id, $existing_referral = null) {
        $order = wc_get_order($order_id);
        if (!$order) return;

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

        // Handle stacking if same coach as referral
        $total_commission = $partnership_commission['total_amount'];
        $commission_type = 'partnership';

        if ($existing_referral && $existing_referral->coach_id == $partnership_coach_id) {
            // For stacked commissions, we use the partnership portion only
            $total_commission = $partnership_commission['total_amount'];
            $commission_type = 'partnership_stacked';
        }

        // Insert partnership commission record
        global $wpdb;
        $referrals_table = $wpdb->prefix . 'intersoccer_referrals';

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

        // Update partnership order count
        $partnership_orders = (int) get_user_meta($customer_id, 'intersoccer_partnership_order_count', true);
        update_user_meta($customer_id, 'intersoccer_partnership_order_count', $partnership_orders + 1);

        // Log partnership commission
        error_log("Partnership commission: Customer {$customer_id}, Coach {$partnership_coach_id}, Amount {$total_commission} CHF");

        // Notify coach
        $this->notify_coach_of_partnership_commission($partnership_coach_id, $order_id, $partnership_commission, $commission_type);
    }

    /**
     * Calculate complete commission structure for an order
     */
    public static function calculate_total_commission($order, $coach_id, $customer_id, $purchase_count) {
        $base_commission = self::calculate_base_commission($order, $purchase_count);
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
        $total = $order->get_total() - $order->get_total_tax();

        // Get loyalty bonus rates from settings
        $first_loyalty = get_option('intersoccer_loyalty_bonus_first', 5) / 100;
        $second_loyalty = get_option('intersoccer_loyalty_bonus_second', 8) / 100;
        $third_loyalty = get_option('intersoccer_loyalty_bonus_third', 15) / 100;

        switch ($purchase_count) {
            case 1:
                return $total * $first_loyalty;
            case 2:
                return $total * $second_loyalty;
            default:
                return $total * $third_loyalty;
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
}