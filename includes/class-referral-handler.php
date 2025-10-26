<?php
// includes/class-referral-handler.php

class InterSoccer_Referral_Handler {

    public function __construct() {
        add_action('init', [$this, 'handle_referral_cookie']);
        add_action('woocommerce_thankyou', [$this, 'process_referral_order']);
        // Disabled old credit discount system - replaced with points system in admin dashboard
        // add_action('woocommerce_cart_calculate_fees', [$this, 'apply_credit_discount']);
        // Disabled old slider interface - replaced with Amazon Prime style in admin dashboard
        // add_action('woocommerce_review_order_before_payment', [$this, 'add_credit_field']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'update_order_with_credits']);
        add_action('wp_ajax_gift_credits', [$this, 'handle_gift_credits']);
        // New partnership handlers
        add_action('wp_ajax_select_coach_partner', [$this, 'handle_coach_partnership_selection']);
        add_action('wp_ajax_switch_coach_partner', [$this, 'handle_coach_partnership_switch']);
        add_action('wp_ajax_get_available_coaches', [$this, 'get_available_coaches_ajax']);
        
        // Add partnership shortcode
        add_shortcode('intersoccer_coach_selection', [$this, 'render_coach_selection_interface']);
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

    /**
     * Handle coach partnership selection AJAX
     */
    public function handle_coach_partnership_selection() {
        check_ajax_referer('intersoccer_dashboard_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Must be logged in']);
        }
        
        $customer_id = get_current_user_id();
        $coach_id = intval($_POST['coach_id']);
        
        // Validate coach exists and has correct role
        $coach = get_user_by('ID', $coach_id);
        if (!$coach || !in_array('coach', $coach->roles)) {
            wp_send_json_error(['message' => 'Invalid coach selected']);
        }
        
        // Check if customer is in cooldown period
        $cooldown_end = get_user_meta($customer_id, 'intersoccer_partnership_switch_cooldown', true);
        if ($cooldown_end && strtotime($cooldown_end) > time()) {
            $remaining_hours = ceil((strtotime($cooldown_end) - time()) / 3600);
            wp_send_json_error(['message' => "You must wait {$remaining_hours} hours before changing coaches."]);
        }
        
        // Get current partnership coach for logging
        $current_coach_id = get_user_meta($customer_id, 'intersoccer_partnership_coach_id', true);
        
        // Set new partnership
        update_user_meta($customer_id, 'intersoccer_partnership_coach_id', $coach_id);
        update_user_meta($customer_id, 'intersoccer_partnership_start_date', current_time('mysql'));
        
        // Set cooldown if switching coaches (not first selection)
        if ($current_coach_id && $current_coach_id != $coach_id) {
            $cooldown_end_date = date('Y-m-d H:i:s', time() + (7 * 24 * 3600)); // 7 days
            update_user_meta($customer_id, 'intersoccer_partnership_switch_cooldown', $cooldown_end_date);
            
            error_log('Coach partnership switched - Customer: ' . $customer_id . ', From: ' . $current_coach_id . ', To: ' . $coach_id . ', Cooldown until: ' . $cooldown_end_date);
        } else {
            error_log('Coach partnership selected - Customer: ' . $customer_id . ', Coach: ' . $coach_id);
        }
        
        // Send notification emails
        $this->notify_partnership_selection($customer_id, $coach_id, $current_coach_id);
        
        wp_send_json_success([
            'message' => 'Coach partnership updated successfully!',
            'coach_name' => $coach->display_name,
            'cooldown_set' => !empty($current_coach_id)
        ]);
    }

    /**
     * Get available coaches for selection
     */
    public function get_available_coaches_ajax() {
        check_ajax_referer('intersoccer_dashboard_nonce', 'nonce');
        
        $search_term = sanitize_text_field($_POST['search'] ?? '');
        $filter = sanitize_text_field($_POST['filter'] ?? 'all');
        
        $coaches = $this->get_available_coaches($search_term, $filter);
        
        wp_send_json_success(['coaches' => $coaches]);
    }

    /**
     * Render coach selection interface shortcode
     */
    public function render_coach_selection_interface($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to select a coach partner.</p>';
        }
        
        $customer_id = get_current_user_id();
        $current_coach_id = get_user_meta($customer_id, 'intersoccer_partnership_coach_id', true);
        $cooldown_end = get_user_meta($customer_id, 'intersoccer_partnership_switch_cooldown', true);
        
        ob_start();
        include INTERSOCCER_REFERRAL_PATH . 'templates/coach-selection-template.php';
        return ob_get_clean();
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
        $credits = $is_first_purchase ? 500 : 0;

        // Insert referral record
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

        // Auto-assign partnership if referred by coach and customer doesn't have one
        if ($referrer['type'] === 'coach' && $is_first_purchase) {
            $existing_partnership = get_user_meta($customer_id, 'intersoccer_partnership_coach_id', true);
            if (!$existing_partnership) {
                update_user_meta($customer_id, 'intersoccer_partnership_coach_id', $referrer['id']);
                update_user_meta($customer_id, 'intersoccer_partnership_start_date', current_time('mysql'));
                
                error_log('Auto-assigned partnership - Customer: ' . $customer_id . ', Coach: ' . $referrer['id']);
                
                // Notify customer about auto-assignment
                $coach = get_user_by('ID', $referrer['id']);
                if ($coach) {
                    $customer = get_user_by('ID', $customer_id);
                    $subject = 'Welcome to InterSoccer - Coach Connection Assigned!';
                    $message = sprintf(
                        'Hi %s,

    Welcome to InterSoccer! Since you joined through %s\'s referral, we\'ve automatically connected you as partners.

    This means:
    - %s will earn a 5%% commission on your future purchases
    - You get personalized support and training tips
    - Access to exclusive content and community features

    You can change your Coach Connection anytime in your dashboard if you prefer someone else.

    Enjoy your training!

    Best regards,
    The InterSoccer Team',
                        $customer->display_name,
                        $coach->display_name,
                        $coach->display_name
                    );
                    
                    wp_mail($customer->user_email, $subject, $message);
                }
            }
        }

        // Continue with existing referral processing...
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

        // Apply first-time customer benefits
        if ($is_first_purchase) {
            $customer_credits = (float) get_user_meta($customer_id, 'intersoccer_customer_credits', true);
            update_user_meta($customer_id, 'intersoccer_customer_credits', $customer_credits + 50);
            
            wp_mail($order->get_billing_email(), 'Welcome to InterSoccer!', 'Thanks for joining! You have 50 CHF credits and are connected with your coach partner.');
        }

        error_log('Processed referral order #' . $order_id . ', referrer_type: ' . $referrer['type'] . ', credits: ' . $credits);
        
        // Clear referral session
        WC()->session->__unset('intersoccer_referral');
        setcookie('intersoccer_referral', '', time() - 3600, '/');
    }

    /**
     * Get available coaches based on search and filter criteria
     */
    private function get_available_coaches($search_term = '', $filter = 'all') {
        $args = [
            'role' => 'coach',
            'number' => 20, // Limit results
            'orderby' => 'meta_value_num',
            'meta_key' => 'intersoccer_coach_rating',
            'order' => 'DESC'
        ];
        
        if ($search_term) {
            $args['search'] = '*' . $search_term . '*';
            $args['search_columns'] = ['display_name', 'user_email'];
        }
        
        $coaches = get_users($args);
        $coach_data = [];
        
        foreach ($coaches as $coach) {
            // Get coach stats
            global $wpdb;
            $table_name = $wpdb->prefix . 'intersoccer_referrals';
            
            $stats = $wpdb->get_row($wpdb->prepare("
                SELECT 
                    COUNT(*) as total_referrals,
                    AVG(commission_amount + loyalty_bonus + retention_bonus) as avg_commission
                FROM $table_name 
                WHERE coach_id = %d AND status = 'completed'
            ", $coach->ID));
            
            $tier = intersoccer_get_coach_tier($coach->ID);
            $specialty = get_user_meta($coach->ID, 'coach_specialty', true) ?: 'General Training';
            $rating = (float) get_user_meta($coach->ID, 'intersoccer_coach_rating', true) ?: 4.5;
            
            // Apply filter logic
            if ($filter !== 'all') {
                switch ($filter) {
                    case 'youth':
                        if (stripos($specialty, 'youth') === false) continue 2;
                        break;
                    case 'advanced':
                        if (stripos($specialty, 'advanced') === false && $tier !== 'Platinum') continue 2;
                        break;
                    case 'top':
                        if ($tier !== 'Gold' && $tier !== 'Platinum') continue 2;
                        break;
                    case 'local':
                        // Could implement location-based filtering here
                        break;
                }
            }
            
            $coach_data[] = [
                'id' => $coach->ID,
                'name' => $coach->display_name,
                'specialty' => $specialty,
                'tier' => $tier,
                'rating' => $rating,
                'total_athletes' => (int) $stats->total_referrals ?: 0,
                'avatar_url' => get_avatar_url($coach->ID, ['size' => 80]),
                'benefits' => $this->get_coach_benefits($coach->ID, $tier)
            ];
        }
        
        return $coach_data;
    }

    /**
     * Get coach-specific benefits based on tier
     */
    private function get_coach_benefits($coach_id, $tier) {
        $benefits = [
            '5% of your purchases support ' . get_user_by('ID', $coach_id)->display_name
        ];
        
        switch ($tier) {
            case 'Platinum':
                $benefits[] = 'Monthly video reviews';
                $benefits[] = 'Priority support access';
                $benefits[] = 'Exclusive content library';
                break;
            case 'Gold':
                $benefits[] = 'Advanced technique analysis';
                $benefits[] = 'Quarterly progress reports';
                break;
            case 'Silver':
                $benefits[] = 'Personalized training tips';
                $benefits[] = 'Equipment recommendations';
                break;
            default:
                $benefits[] = 'Training progress tracking';
                $benefits[] = 'Community forum access';
        }
        
        return $benefits;
    }

    /**
     * Send partnership selection notifications
     */
    private function notify_partnership_selection($customer_id, $new_coach_id, $old_coach_id = null) {
        if (!get_option('intersoccer_enable_email_notifications', 1)) {
            return;
        }
        
        $customer = get_user_by('ID', $customer_id);
        $new_coach = get_user_by('ID', $new_coach_id);
        
        // Notify new coach
        if ($new_coach) {
            $subject = __('New Partnership Connection!', 'intersoccer-referral');
            $message = sprintf(
                __('Great news %s!

    %s has chosen you as their Coach Connection partner.

    You will now earn a 5%% commission on all their future purchases. This is a great opportunity to build a lasting relationship and support their soccer journey.

    Customer Details:
    - Name: %s
    - Email: %s
    - Partnership started: %s

    Keep up the excellent coaching work!

    Best regards,
    The InterSoccer Team', 'intersoccer-referral'),
                $new_coach->display_name,
                $customer->display_name,
                $customer->display_name,
                $customer->user_email,
                current_time('F j, Y')
            );
            
            wp_mail($new_coach->user_email, $subject, $message);
        }
        
        // Notify old coach if switching
        if ($old_coach_id && $old_coach_id != $new_coach_id) {
            $old_coach = get_user_by('ID', $old_coach_id);
            if ($old_coach) {
                $subject = __('Partnership Update', 'intersoccer-referral');
                $message = sprintf(
                    __('Hi %s,

    %s has switched to a different Coach Connection partner.

    Don\'t worry - this is part of the process and doesn\'t reflect on your coaching abilities. Keep focusing on your other partnerships and referrals.

    You can always reconnect with customers through great service and engagement.

    Best regards,
    The InterSoccer Team', 'intersoccer-referral'),
                    $old_coach->display_name,
                    $customer->display_name
                );
                
                wp_mail($old_coach->user_email, $subject, $message);
            }
        }
    }

    /**
     * Get referrer by code (enhanced to handle both coach and customer referrals)
     */
    private function get_referrer_by_code($code) {
        // Check for coach referral code
        $coaches = get_users([
            'role' => 'coach',
            'meta_key' => 'referral_code',
            'meta_value' => $code
        ]);
        
        if (!empty($coaches)) {
            return [
                'id' => $coaches[0]->ID,
                'type' => 'coach',
                'user' => $coaches[0]
            ];
        }
        
        // Check for customer referral code
        $customers = get_users([
            'meta_key' => 'intersoccer_customer_referral_code',
            'meta_value' => $code
        ]);
        
        if (!empty($customers)) {
            return [
                'id' => $customers[0]->ID,
                'type' => 'customer',
                'user' => $customers[0]
            ];
        }
        
        return null;
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