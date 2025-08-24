<?php
// includes/class-commission-calculator.php

class InterSoccer_Commission_Calculator {

    public function __construct() {
        // Hook into order processing to calculate advanced bonuses - broader coverage
        add_action('woocommerce_order_status_processing', [$this, 'process_advanced_bonuses']);
        add_action('woocommerce_order_status_completed', [$this, 'process_advanced_bonuses']);
    }

    /**
     * Calculate base commission based on purchase count
     */
    public static function calculate_commission($order, $purchase_count) {
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
     * Calculate network effect bonus when referred customer brings friends
     */
    public static function calculate_network_effect_bonus($customer_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        
        // Check if this customer has referred others
        $referrals_made = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE customer_id = %d",
            $customer_id
        ));
        
        if ($referrals_made > 0) {
            return get_option('intersoccer_network_effect_bonus', 15);
        }
        
        return 0;
    }
    
    /**
     * Calculate performance tier multiplier bonus
     */
    public static function calculate_tier_bonus($coach_id, $base_amount) {
        $tier = intersoccer_get_coach_tier($coach_id);
        
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
     * Calculate seasonal multiplier bonus
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
     * Calculate weekend multiplier (if enabled)
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
     * Calculate complete commission structure for an order
     */
    public static function calculate_total_commission($order, $coach_id, $customer_id, $purchase_count) {
        $base_commission = self::calculate_commission($order, $purchase_count);
        $loyalty_bonus = self::calculate_loyalty_bonus($order, $purchase_count);
        $retention_bonus = self::calculate_retention_bonus($customer_id, date('Y'));
        $network_bonus = self::calculate_network_effect_bonus($customer_id);
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
     * Process advanced bonuses when order is completed
     */
    public function process_advanced_bonuses($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        // Check if this order has a referral
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        
        $referral = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE order_id = %d",
            $order_id
        ));
        
        if (!$referral) return;
        
        // Calculate advanced commission structure
        $commission_data = self::calculate_total_commission(
            $order,
            $referral->coach_id,
            $referral->customer_id,
            $referral->purchase_count
        );
        
        // Update referral record with detailed breakdown
        $wpdb->update(
            $table_name,
            [
                'commission_amount' => $commission_data['base_commission'],
                'loyalty_bonus' => $commission_data['loyalty_bonus'],
                'retention_bonus' => $commission_data['retention_bonus'] + $commission_data['network_bonus'],
                'status' => 'completed',
                'conversion_date' => current_time('mysql')
            ],
            ['id' => $referral->id]
        );
        
        // Update coach credits with total amount
        $current_credits = (float) get_user_meta($referral->coach_id, 'intersoccer_credits', true);
        update_user_meta(
            $referral->coach_id,
            'intersoccer_credits',
            $current_credits + $commission_data['total_amount']
        );
        
        // Log the advanced commission calculation
        error_log(sprintf(
            'Advanced commission calculated for Order #%d: Base: %.2f, Total: %.2f',
            $order_id,
            $commission_data['base_commission'],
            $commission_data['total_amount']
        ));
        
        // Trigger achievement check
        $this->check_coach_achievements($referral->coach_id, $commission_data);
        
        // Send notification to coach
        $this->notify_coach_of_commission($referral->coach_id, $order_id, $commission_data);
    }
    
    /**
     * Check and award coach achievements
     */
    private function check_coach_achievements($coach_id, $commission_data) {
        global $wpdb;
        $achievements_table = $wpdb->prefix . 'intersoccer_coach_achievements';
        $referrals_table = $wpdb->prefix . 'intersoccer_referrals';
        
        // Get coach's total stats
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_referrals,
                SUM(commission_amount + loyalty_bonus + retention_bonus) as total_earnings
            FROM $referrals_table 
            WHERE coach_id = %d AND status = 'completed'
        ", $coach_id));
        
        $achievements = [];
        
        // Referral milestones
        $referral_milestones = [5 => 'First 5', 10 => 'Perfect 10', 25 => 'Quarter Century', 50 => 'Half Century'];
        foreach ($referral_milestones as $milestone => $name) {
            if ($stats->total_referrals >= $milestone) {
                $achievements[] = [
                    'type' => 'referral_milestone',
                    'name' => $name . ' Referrals',
                    'description' => "Achieved {$milestone} successful referrals",
                    'points' => $milestone * 10
                ];
            }
        }
        
        // Earnings milestones
        $earning_milestones = [500 => 'Earner', 1000 => 'Big Earner', 2500 => 'Top Earner', 5000 => 'Elite Earner'];
        foreach ($earning_milestones as $milestone => $name) {
            if ($stats->total_earnings >= $milestone) {
                $achievements[] = [
                    'type' => 'earnings_milestone',
                    'name' => $name,
                    'description' => "Earned {$milestone} CHF in total commissions",
                    'points' => $milestone / 10
                ];
            }
        }
        
        // Seasonal achievements
        $current_month = date('m');
        if (in_array($current_month, ['08', '09']) && $commission_data['seasonal_bonus'] > 0) {
            $achievements[] = [
                'type' => 'seasonal_bonus',
                'name' => 'Back to School Champion',
                'description' => 'Earned seasonal bonus during back-to-school period',
                'points' => 100
            ];
        }
        
        // Weekend warrior
        if ($commission_data['weekend_bonus'] > 0) {
            $achievements[] = [
                'type' => 'weekend_bonus',
                'name' => 'Weekend Warrior',
                'description' => 'Earned commission on weekend',
                'points' => 25
            ];
        }
        
        // Insert new achievements
        foreach ($achievements as $achievement) {
            // Check if already awarded
            $exists = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM $achievements_table 
                WHERE coach_id = %d AND achievement_type = %s AND achievement_name = %s
            ", $coach_id, $achievement['type'], $achievement['name']));
            
            if (!$exists) {
                $wpdb->insert($achievements_table, [
                    'coach_id' => $coach_id,
                    'achievement_type' => $achievement['type'],
                    'achievement_name' => $achievement['name'],
                    'description' => $achievement['description'],
                    'points' => $achievement['points']
                ]);
            }
        }
    }
    
    /**
     * Send commission notification to coach
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
            intersoccer_get_coach_tier($coach_id),
            intersoccer_get_coach_credits($coach_id)
        );
        
        wp_mail($coach->user_email, $subject, $message);
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
     * Get coach's commission breakdown for a specific period
     */
    public static function get_coach_commission_breakdown($coach_id, $start_date = null, $end_date = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        
        $date_clause = '';
        $params = [$coach_id];
        
        if ($start_date && $end_date) {
            $date_clause = 'AND created_at BETWEEN %s AND %s';
            $params[] = $start_date;
            $params[] = $end_date;
        }
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                SUM(commission_amount) as total_commission,
                SUM(loyalty_bonus) as total_loyalty_bonus,
                SUM(retention_bonus) as total_retention_bonus,
                COUNT(*) as total_referrals,
                AVG(commission_amount) as avg_commission
            FROM $table_name 
            WHERE coach_id = %d AND status = 'completed' $date_clause
        ", $params));
        
        return $results[0] ?? (object)[
            'total_commission' => 0,
            'total_loyalty_bonus' => 0,
            'total_retention_bonus' => 0,
            'total_referrals' => 0,
            'avg_commission' => 0
        ];
    }
    
    /**
     * Calculate projected earnings for coach based on current performance
     */
    public static function calculate_projected_earnings($coach_id, $months_ahead = 3) {
        $current_month_performance = self::get_coach_commission_breakdown(
            $coach_id,
            date('Y-m-01'),
            date('Y-m-t')
        );
        
        // Simple projection based on current month performance
        $monthly_avg = $current_month_performance->total_commission + 
                      $current_month_performance->total_loyalty_bonus + 
                      $current_month_performance->total_retention_bonus;
        
        // Apply growth factor based on tier
        $tier = intersoccer_get_coach_tier($coach_id);
        $growth_factors = [
            'Bronze' => 1.0,
            'Silver' => 1.1,   // 10% growth potential
            'Gold' => 1.2,     // 20% growth potential
            'Platinum' => 1.3   // 30% growth potential
        ];
        
        $growth_factor = $growth_factors[$tier] ?? 1.0;
        
        return round($monthly_avg * $months_ahead * $growth_factor, 2);
    }
    
    /**
     * Get top performing coaches for leaderboard
     */
    public static function get_top_coaches($period = 'month', $limit = 10) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        
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
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                r.coach_id,
                u.display_name,
                u.user_email,
                COUNT(*) as referral_count,
                SUM(r.commission_amount + r.loyalty_bonus + r.retention_bonus) as total_earnings,
                AVG(r.commission_amount) as avg_commission
            FROM $table_name r
            LEFT JOIN {$wpdb->users} u ON r.coach_id = u.ID
            WHERE r.status = 'completed' $date_clause
            GROUP BY r.coach_id
            ORDER BY total_earnings DESC
            LIMIT %d
        ", $limit));
    }
    
    /**
     * Calculate commission trends for analytics
     */
    public static function get_commission_trends($months = 6) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        
        $trends = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month_start = date('Y-m-01', strtotime("-$i months"));
            $month_end = date('Y-m-t', strtotime("-$i months"));
            $month_label = date('M Y', strtotime("-$i months"));
            
            $data = $wpdb->get_row($wpdb->prepare("
                SELECT 
                    COUNT(*) as referrals,
                    SUM(commission_amount + loyalty_bonus + retention_bonus) as total_commission,
                    AVG(commission_amount) as avg_commission
                FROM $table_name 
                WHERE status = 'completed' AND created_at BETWEEN %s AND %s
            ", $month_start . ' 00:00:00', $month_end . ' 23:59:59'));
            
            $trends[] = [
                'month' => $month_label,
                'referrals' => (int) $data->referrals,
                'total_commission' => (float) $data->total_commission,
                'avg_commission' => (float) $data->avg_commission
            ];
        }
        
        return $trends;
    }
    
    /**
     * Generate commission report for admin
     */
    public static function generate_admin_report($start_date = null, $end_date = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        
        if (!$start_date) $start_date = date('Y-m-01');
        if (!$end_date) $end_date = date('Y-m-t');
        
        $summary = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_referrals,
                COUNT(DISTINCT coach_id) as active_coaches,
                COUNT(DISTINCT customer_id) as unique_customers,
                SUM(commission_amount) as total_base_commission,
                SUM(loyalty_bonus) as total_loyalty_bonus,
                SUM(retention_bonus) as total_retention_bonus,
                SUM(commission_amount + loyalty_bonus + retention_bonus) as total_payout
            FROM $table_name 
            WHERE status = 'completed' AND created_at BETWEEN %s AND %s
        ", $start_date . ' 00:00:00', $end_date . ' 23:59:59'));
        
        $tier_breakdown = $wpdb->get_results("
            SELECT 
                CASE 
                    WHEN referral_count >= " . get_option('intersoccer_tier_platinum', 20) . " THEN 'Platinum'
                    WHEN referral_count >= " . get_option('intersoccer_tier_gold', 10) . " THEN 'Gold'
                    WHEN referral_count >= " . get_option('intersoccer_tier_silver', 5) . " THEN 'Silver'
                    ELSE 'Bronze'
                END as tier,
                COUNT(*) as coach_count,
                SUM(total_commission) as tier_commission
            FROM (
                SELECT 
                    coach_id,
                    COUNT(*) as referral_count,
                    SUM(commission_amount + loyalty_bonus + retention_bonus) as total_commission
                FROM $table_name 
                WHERE status = 'completed'
                GROUP BY coach_id
            ) coach_stats
            GROUP BY tier
            ORDER BY tier_commission DESC
        ");
        
        return [
            'summary' => $summary,
            'tier_breakdown' => $tier_breakdown,
            'period' => [
                'start' => $start_date,
                'end' => $end_date
            ]
        ];
    }
}