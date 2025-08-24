<?php
// includes/class-referral-handler.php

class InterSoccer_Referral_Handler {

    public function __construct() {
        add_action('init', [$this, 'handle_referral_cookie']);
        add_action('woocommerce_thankyou', [$this, 'process_referral_order']);
        add_action('woocommerce_cart_calculate_fees', [$this, 'apply_credit_discount']);
        add_action('woocommerce_review_order_before_payment', [$this, 'add_credit_field']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'update_order_with_credits']);
    }

    // Generate referral link
    public static function generate_coach_referral_link($coach_id) {
        $code = 'coach_' . $coach_id . '_' . wp_generate_password(6, false);
        update_user_meta($coach_id, 'referral_code', $code);
        return home_url('/?ref=' . $code);
    }

    // Handle referral cookie on init
    public function handle_referral_cookie() {
        if (isset($_GET['ref']) && !empty($_GET['ref'])) {
            $ref_code = sanitize_text_field($_GET['ref']);
            setcookie('intersoccer_referral', $ref_code, time() + (30 * DAY_IN_SECONDS), '/'); // 30 days
        }
    }

    // Process referral on order completion
    public function process_referral_order($order_id) {
        if (!isset($_COOKIE['intersoccer_referral'])) {
            return;
        }

        $ref_code = sanitize_text_field($_COOKIE['intersoccer_referral']);
        $coach = $this->get_coach_by_code($ref_code);
        if (!$coach) {
            return;
        }

        $order = wc_get_order($order_id);
        $customer_id = $order->get_customer_id();
        $is_first_purchase = $this->is_first_purchase($customer_id);

        // Calculate commission
        $commission = InterSoccer_Commission_Calculator::calculate_commission($order, $is_first_purchase ? 1 : 2);

        // Store referral
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        $wpdb->insert($table_name, [
            'coach_id' => $coach->ID,
            'customer_id' => $customer_id,
            'order_id' => $order_id,
            'commission_amount' => $commission,
            'status' => 'pending',
        ]);

        // Update coach credits
        $current_credits = (float) get_user_meta($coach->ID, 'intersoccer_credits', true);
        update_user_meta($coach->ID, 'intersoccer_credits', $current_credits + $commission);

        // New customer incentives
        if ($is_first_purchase) {
            $order->apply_discount(10); // 10% discount - simplistic, adjust as needed
            $order->save();

            // Award 500 points (50 CHF)
            $customer_credits = (float) get_user_meta($customer_id, 'intersoccer_customer_credits', true);
            update_user_meta($customer_id, 'intersoccer_customer_credits', $customer_credits + 50);

            // Simple welcome email
            wp_mail($order->get_billing_email(), 'Welcome to InterSoccer!', 'Thanks for joining via referral! You have 50 CHF credits.');
        }

        // Clear cookie
        setcookie('intersoccer_referral', '', time() - 3600, '/');
    }

    // Get coach by referral code
    private function get_coach_by_code($code) {
        $coaches = get_users(['role' => 'coach', 'meta_key' => 'referral_code', 'meta_value' => $code]);
        return !empty($coaches) ? $coaches[0] : null;
    }

    // Check if first purchase
    private function is_first_purchase($customer_id) {
        $orders = wc_get_orders(['customer' => $customer_id, 'status' => 'completed', 'limit' => -1]);
        return count($orders) === 1; // Including current one
    }

    // Add credit field to checkout
    public function add_credit_field() {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $credits = (float) get_user_meta($user_id, 'intersoccer_customer_credits', true);
            if ($credits > 0) {
                echo '<div id="intersoccer-credits"><h3>Apply Credits</h3>';
                woocommerce_form_field('intersoccer_apply_credits', [
                    'type' => 'number',
                    'label' => 'Apply up to ' . $credits . ' CHF',
                    'max' => $credits,
                    'min' => 0,
                    'step' => 0.01,
                ], WC()->checkout->get_value('intersoccer_apply_credits'));
                echo '</div>';
            }
        }
    }

    // Apply credit discount
    public function apply_credit_discount($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        if (did_action('woocommerce_cart_calculate_fees') >= 2) return;

        $apply_credits = isset($_POST['intersoccer_apply_credits']) ? floatval($_POST['intersoccer_apply_credits']) : 0;
        if ($apply_credits > 0 && is_user_logged_in()) {
            $user_id = get_current_user_id();
            $credits = (float) get_user_meta($user_id, 'intersoccer_customer_credits', true);
            $apply_credits = min($apply_credits, $credits, $cart->get_subtotal());
            if ($apply_credits > 0) {
                $cart->add_fee('Credits Applied', -$apply_credits, false);
            }
        }
    }

    // Update order with credits used
    public function update_order_with_credits($order_id) {
        $apply_credits = isset($_POST['intersoccer_apply_credits']) ? floatval($_POST['intersoccer_apply_credits']) : 0;
        if ($apply_credits > 0 && is_user_logged_in()) {
            $user_id = get_current_user_id();
            $credits = (float) get_user_meta($user_id, 'intersoccer_customer_credits', true);
            update_user_meta($user_id, 'intersoccer_customer_credits', $credits - $apply_credits);
            update_post_meta($order_id, '_intersoccer_credits_used', $apply_credits);
        }
    }
}