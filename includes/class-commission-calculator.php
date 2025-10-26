<?php
// includes/class-commission-calculator.php

/**
 * InterSoccer Commission Calculator
 *
 * @deprecated This class is being phased out in favor of InterSoccer_Commission_Manager
 * for better separation of concerns between customer points and coach commissions.
 */
class InterSoccer_Commission_Calculator {

    private $commission_manager;

    public function __construct() {
        // Initialize the new commission manager if available
        if (class_exists('InterSoccer_Commission_Manager')) {
            $this->commission_manager = new InterSoccer_Commission_Manager();
        } else {
            // Fallback: log error but don't break the site
            error_log('InterSoccer: Commission Manager class not found - using fallback mode');
            $this->commission_manager = null;
        }

        // Add deprecation notice
        add_action('admin_notices', [$this, 'deprecation_notice']);
    }

    /**
     * Show deprecation notice in admin
     */
    public function deprecation_notice() {
        if (current_user_can('manage_options')) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>InterSoccer Commission Calculator Deprecation:</strong> ';
            echo 'This class is being phased out. Commission logic has been moved to InterSoccer_Commission_Manager for better architecture.</p>';
            echo '</div>';
        }
    }

    /**
     * @deprecated Use InterSoccer_Commission_Manager::calculate_base_commission() instead
     */
    public static function calculate_commission($order, $purchase_count) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager::calculate_base_commission');

        if (class_exists('InterSoccer_Commission_Manager')) {
            return InterSoccer_Commission_Manager::calculate_base_commission($order, $purchase_count);
        }

        // Fallback calculation
        $total = $order->get_total() - $order->get_total_tax();
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
     * @deprecated Use InterSoccer_Commission_Manager::calculate_loyalty_bonus() instead
     */
    public static function calculate_loyalty_bonus($order, $purchase_count) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager::calculate_loyalty_bonus');
        return InterSoccer_Commission_Manager::calculate_loyalty_bonus($order, $purchase_count);
    }

    /**
     * @deprecated Use InterSoccer_Commission_Manager::calculate_retention_bonus() instead
     */
    public static function calculate_retention_bonus($customer_id, $current_season) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager::calculate_retention_bonus');
        return InterSoccer_Commission_Manager::calculate_retention_bonus($customer_id, $current_season);
    }

    /**
     * @deprecated Use InterSoccer_Commission_Manager::calculate_network_bonus() instead
     */
    public static function calculate_network_effect_bonus($customer_id) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager::calculate_network_bonus');
        return InterSoccer_Commission_Manager::calculate_network_bonus($customer_id);
    }

    /**
     * @deprecated Use InterSoccer_Commission_Manager::calculate_tier_bonus() instead
     */
    public static function calculate_tier_bonus($coach_id, $base_amount) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager::calculate_tier_bonus');
        return InterSoccer_Commission_Manager::calculate_tier_bonus($coach_id, $base_amount);
    }

    /**
     * @deprecated Use InterSoccer_Commission_Manager::calculate_seasonal_bonus() instead
     */
    public static function calculate_seasonal_bonus($base_amount, $order_date = null) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager::calculate_seasonal_bonus');
        return InterSoccer_Commission_Manager::calculate_seasonal_bonus($base_amount, $order_date);
    }

    /**
     * @deprecated Use InterSoccer_Commission_Manager::calculate_weekend_bonus() instead
     */
    public static function calculate_weekend_bonus($base_amount, $order_date = null) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager::calculate_weekend_bonus');
        return InterSoccer_Commission_Manager::calculate_weekend_bonus($base_amount, $order_date);
    }

    /**
     * @deprecated Use InterSoccer_Commission_Manager::calculate_total_commission() instead
     */
    public static function calculate_total_commission($order, $coach_id, $customer_id, $purchase_count) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager::calculate_total_commission');

        if (class_exists('InterSoccer_Commission_Manager')) {
            return InterSoccer_Commission_Manager::calculate_total_commission($order, $coach_id, $customer_id, $purchase_count);
        }

        // Fallback calculation
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
     * @deprecated Use InterSoccer_Commission_Manager::get_coach_tier() instead
     */
    public static function get_coach_tier($coach_id) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager::get_coach_tier');
        return InterSoccer_Commission_Manager::get_coach_tier($coach_id);
    }

    /**
     * @deprecated Use InterSoccer_Commission_Manager::get_coach_commission_stats() instead
     */
    public static function get_coach_commission_breakdown($coach_id, $start_date = null, $end_date = null) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager::get_coach_commission_stats');
        return InterSoccer_Commission_Manager::get_coach_commission_stats($coach_id, 'month');
    }

    /**
     * @deprecated Use InterSoccer_Commission_Manager::calculate_partnership_commission() instead
     */
    public static function calculate_partnership_commission($order, $coach_id) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager::calculate_partnership_commission');
        return InterSoccer_Commission_Manager::calculate_partnership_commission($order, $coach_id);
    }

    /**
     * @deprecated Process commissions through InterSoccer_Commission_Manager hooks instead
     */
    public function process_advanced_bonuses($order_id) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager hooks');
        // This method is now handled by the Commission Manager's hooks
        // No action needed here as the new manager handles this automatically
    }

    /**
     * @deprecated Process partnership commissions through InterSoccer_Commission_Manager hooks instead
     */
    private function process_partnership_commission($order_id, $customer_id, $partnership_coach_id, $existing_referral = null) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager hooks');
        // This method is now handled by the Commission Manager's hooks
        // No action needed here as the new manager handles this automatically
    }

    /**
     * @deprecated Use InterSoccer_Commission_Manager::get_top_coaches() instead
     */
    public static function get_top_coaches($period = 'month', $limit = 10) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager::get_top_coaches');
        // This functionality is not directly available in the new manager
        // Return empty array for backward compatibility
        return [];
    }

    /**
     * @deprecated Use InterSoccer_Commission_Manager methods instead
     */
    public static function get_commission_trends($months = 6) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager methods');
        // This functionality is not directly available in the new manager
        // Return empty array for backward compatibility
        return [];
    }

    /**
     * @deprecated Use InterSoccer_Commission_Manager methods instead
     */
    public static function generate_admin_report($start_date = null, $end_date = null) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager methods');
        // This functionality is not directly available in the new manager
        // Return empty array for backward compatibility
        return [];
    }

    /**
     * @deprecated Use InterSoccer_Commission_Manager methods instead
     */
    public static function calculate_projected_earnings($coach_id, $months_ahead = 3) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager methods');
        // This functionality is not directly available in the new manager
        // Return 0 for backward compatibility
        return 0;
    }

    /**
     * @deprecated Use InterSoccer_Commission_Manager methods instead
     */
    private static function get_customer_seasonal_orders($customer_id) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager methods');
        // This functionality is not directly available in the new manager
        // Return empty array for backward compatibility
        return [];
    }

    /**
     * @deprecated Use InterSoccer_Commission_Manager methods instead
     */
    private function check_coach_achievements($coach_id, $commission_data) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager methods');
        // This functionality is not directly available in the new manager
        // No action needed here
    }

    /**
     * @deprecated Use InterSoccer_Commission_Manager methods instead
     */
    private function notify_coach_of_commission($coach_id, $order_id, $commission_data) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager methods');
        // This functionality is now handled by the Commission Manager
        // No action needed here
    }

    /**
     * @deprecated Use InterSoccer_Commission_Manager methods instead
     */
    private function notify_coach_of_partnership_commission($coach_id, $order_id, $commission_data, $type = 'partnership') {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager methods');
        // This functionality is now handled by the Commission Manager
        // No action needed here
    }

    /**
     * @deprecated Use InterSoccer_Commission_Manager::calculate_stacked_commission() instead
     */
    public static function calculate_stacked_commission($order, $coach_id, $customer_id, $purchase_count) {
        _deprecated_function(__METHOD__, '1.0.0', 'InterSoccer_Commission_Manager::calculate_stacked_commission');
        // This functionality is not directly available in the new manager
        // Return basic commission for backward compatibility
        return self::calculate_total_commission($order, $coach_id, $customer_id, $purchase_count);
    }
}