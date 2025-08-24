<?php
// includes/class-referral-handler.php

class InterSoccer_Referral_Handler {

    public function __construct() {
        add_action('init', [$this, 'handle_referral_cookie']);
        add_action('woocommerce_thankyou', [$this, 'process_referral_order']);
        add_action('woocommerce_cart_calculate_fees', [$this, 'apply_credit_discount']);
        add_action('woocommerce_review_order_before_payment', [$this, 'add_credit_field']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'update_order_with_credits']);
        add_action('wp_ajax_gift_credits', [$this, 'handle_gift_credits']);
    }

    // Generate referral link
    public static function generate_customer_referral_link($user_id) {
        $code = get_user_meta($user_id, 'intersoccer_customer_referral_code', true);
        if (!$code) {
            $code = 'cust_' . $user_id . '_' . wp_generate_password(6, false);
            update_user_meta($user_id, 'intersoccer_customer_referral_code', $code);
        }
        return home_url('/?cust_ref=' . $code);
    }

    public static function generate_coach_referral_link($coach_id) {
        $code = get_user_meta($coach_id, 'referral_code', true);
        if (!$code) {
            $code = 'coach_' . $coach_id . '_' . wp_generate_password(6, false);
            update_user_meta($coach_id, 'referral_code', $code);
        }
        return home_url('/?ref=' . $code);
    }

    public function handle_gift_credits() {
        check_ajax_referer('intersoccer_dashboard_nonce');
        $amount = floatval($_POST['gift_amount']);
        $recipient = get_user_by('email', sanitize_email($_POST['recipient_email']));
        $sender_id = get_current_user_id();
        $sender_credits = intersoccer_get_customer_credits($sender_id);
        if ($recipient && $amount >= 50 && $amount <= $sender_credits) {
            update_user_meta($sender_id, 'intersoccer_customer_credits', $sender_credits - $amount);
            update_user_meta($recipient->ID, 'intersoccer_customer_credits', intersoccer_get_customer_credits($recipient->ID) + $amount);
            update_user_meta($sender_id, 'intersoccer_customer_credits', $sender_credits - $amount + 20); // Reciprocity bonus
            error_log('Credits gifted: ' . $amount . ' from user ' . $sender_id . ' to ' . $recipient->ID);
            wp_send_json_success(['message' => 'Credits gifted! You earned a 20-point bonus!']);
        }
        wp_send_json_error(['message' => 'Invalid gift request']);
    }

    public function render_gift_form() {
        $credits = intersoccer_get_customer_credits();
        ?>
        <form id="gift-credits" method="post">
            <label>Gift Points (Max <?php echo $credits; ?>):</label>
            <input type="number" name="gift_amount" max="<?php echo $credits; ?>" min="50" step="10">
            <label>To User (Email):</label>
            <input type="email" name="recipient_email">
            <button type="submit">Gift</button>
        </form>
        <?php
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
        $order = wc_get_order($order_id);
        $customer_id = $order->get_customer_id();
        $ref_code = WC()->session->get('intersoccer_referral') ?: ($_COOKIE['intersoccer_referral'] ?? '');
        if (!$ref_code) return;

        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        $referrer = $this->get_referrer_by_code($ref_code);
        if (!$referrer) return;

        $is_first_purchase = $this->is_first_purchase($customer_id);
        $commission = $referrer['type'] === 'coach' ? InterSoccer_Commission_Calculator::calculate_total_commission($order, $referrer['id'], $customer_id, $is_first_purchase ? 1 : 2) : 0;
        $credits = $is_first_purchase ? 500 : 0; // 500 points for first purchase

        $wpdb->insert($table_name, [
            'coach_id' => $referrer['type'] === 'coach' ? $referrer['id'] : null,
            'customer_id' => $customer_id,
            'referrer_id' => $referrer['id'],
            'referrer_type' => $referrer['type'],
            'order_id' => $order_id,
            'commission_amount' => $commission,
            'status' => 'pending',
            'purchase_count' => $is_first_purchase ? 1 : $this->get_purchase_count($customer_id),
            'referral_code' => $ref_code,
            'conversion_date' => current_time('mysql')
        ]);

        if ($referrer['type'] === 'coach') {
            $coach_credits = (float) get_user_meta($referrer['id'], 'intersoccer_credits', true);
            update_user_meta($referrer['id'], 'intersoccer_credits', $coach_credits + $commission);
        } else {
            $customer_credits = (float) get_user_meta($referrer['id'], 'intersoccer_customer_credits', true);
            update_user_meta($referrer['id'], 'intersoccer_customer_credits', $customer_credits + $credits);
            $referrals_made = get_user_meta($referrer['id'], 'intersoccer_referrals_made', true) ?: [];
            $referrals_made[] = ['order_id' => $order_id, 'date' => current_time('mysql')];
            update_user_meta($referrer['id'], 'intersoccer_referrals_made', $referrals_made);
        }

        if ($is_first_purchase) {
            $order->apply_discount(10);
            $order->save();
            $customer_credits = (float) get_user_meta($customer_id, 'intersoccer_customer_credits', true);
            update_user_meta($customer_id, 'intersoccer_customer_credits', $customer_credits + 50);
            wp_mail($order->get_billing_email(), 'Welcome to InterSoccer!', 'Thanks for joining via referral! You have 50 CHF credits.');
        }

        error_log('Processed referral order #' . $order_id . ', referrer_type: ' . $referrer['type'] . ', credits: ' . $credits);
        WC()->session->__unset('intersoccer_referral');
        setcookie('intersoccer_referral', '', time() - 3600, '/');
    }

    // Get coach by referral code
    private function get_coach_by_code($code) {
        $coaches = get_users(['role' => 'coach', 'meta_key' => 'referral_code', 'meta_value' => $code]);
        return !empty($coaches) ? $coaches[0] : null;
    }

    // Check if first purchase
    private function is_first_purchase($customer_id) {
        return wc_get_orders(['customer' => $customer_id, 'status' => 'completed', 'limit' => -1]) <= 1;
    }

    private function get_purchase_count($customer_id) {
        return count(wc_get_orders(['customer' => $customer_id, 'status' => 'completed', 'limit' => -1]));
    }

    // Add credit field to checkout
    public function add_credit_field() {
        $credits = intersoccer_get_customer_credits();
        ?>
        <div class="intersoccer-credits">
            <h3>Apply Credits</h3>
            <p>Available: <span id="avail-credits"><?php echo $credits; ?> CHF</p>
            <input type="range" id="credit-slider" name="intersoccer_apply_credits" min="0" max="<?php echo $credits; ?>" step="0.01" value="0">
            <span id="credit-display">0 CHF</span>
            <button type="button" id="apply-max-credits">Apply Max</button>
        </div>
        <script>
        jQuery('#credit-slider').on('input', function() {
            jQuery('#credit-display').text(this.value + ' CHF');
        });
        jQuery('#apply-max-credits').on('click', function() {
            jQuery('#credit-slider').val(<?php echo $credits; ?>).trigger('input');
        });
        </script>
        <?php
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