<?php
// includes/class-dashboard.php

class InterSoccer_Referral_Dashboard {

    public function __construct() {
        add_shortcode('intersoccer_coach_dashboard', [$this, 'render_dashboard']);
        add_shortcode('intersoccer_customer_dashboard', [$this, 'render_customer_dashboard']);
    }

    public function render_dashboard() {
        if (!is_user_logged_in() || !current_user_can('view_referral_dashboard') || is_account_page()) {
            return '<p>You do not have access to this dashboard.</p>';
        }

        $user_id = get_current_user_id();
        $credits = (float) get_user_meta($user_id, 'intersoccer_credits', true);
        $referral_link = InterSoccer_Referral_Handler::generate_coach_referral_link($user_id); // Generate if not exists
        $referrals = $this->get_recent_referrals($user_id);

        ob_start();
        include INTERSOCCER_REFERRAL_PATH . 'templates/dashboard-template.php';
        return ob_get_clean();
    }

    private function get_recent_referrals($coach_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE coach_id = %d ORDER BY created_at DESC LIMIT 5", $coach_id));
    }

    public function render_customer_dashboard() {
        if (!is_user_logged_in() || is_account_page()) {
            return '<p>' . __('Please log in to view your referral dashboard.', 'intersoccer-referral') . '</p>';
        }
        $user_id = get_current_user_id();
        $credits = intersoccer_get_customer_credits($user_id);
        $referrals = get_user_meta($user_id, 'intersoccer_referrals_made', true) ?: [];
        $referral_link = InterSoccer_Referral_Handler::generate_customer_referral_link($user_id);
        $leaderboard = $this->get_customer_leaderboard($user_id);
        ob_start();
        ?>
        <div class="intersoccer-customer-dashboard">
            <h2>Your Referral Dashboard</h2>
            <p>Earn 500 points (50 CHF) per friend who signs up! Share your link:</p>
            <p><a href="<?php echo esc_url($referral_link); ?>" id="referral-link"><?php echo esc_url($referral_link); ?></a></p>
            <button onclick="navigator.clipboard.writeText('<?php echo esc_js($referral_link); ?>')" class="button copy-link">Copy Link</button>
            <p><strong>Points:</strong> <?php echo number_format($credits, 2); ?> CHF</p>
            <p><strong>Referrals:</strong> <?php echo count($referrals); ?></p>
            <progress value="<?php echo $credits; ?>" max="1000"></progress>
            <p><small>Next milestone: 1000 points for a 100 CHF credit!</small></p>
            <h3>Monthly Referral Leaderboard</h3>
            <div class="customer-leaderboard">
                <?php foreach ($leaderboard['top'] as $index => $customer): ?>
                <div class="leaderboard-item">
                    <span class="rank"><?php echo $index + 1; ?></span>
                    <span class="name"><?php echo esc_html($customer->display_name); ?></span>
                    <span class="referrals"><?php echo $customer->referral_count; ?> referrals</span>
                    <?php if ($index < 3): ?><span class="reward">50 bonus points!</span><?php endif; ?>
                </div>
                <?php endforeach; ?>
                <p>Your Rank: <?php echo $leaderboard['user_rank'] ?: 'Unranked'; ?></p>
            </div>
            <h3>Gift Points</h3>
            <form id="gift-credits" method="post">
                <label>Gift Points (50-<?php echo $credits; ?>):</label>
                <input type="number" name="gift_amount" min="50" max="<?php echo $credits; ?>" step="10" required>
                <label>To User (Email):</label>
                <input type="email" name="recipient_email" required>
                <button type="submit" class="button">Gift</button>
            </form>
        </div>
        <script>
        jQuery('#gift-credits').on('submit', function(e) {
            e.preventDefault();
            jQuery.post({
                url: intersoccer_dashboard.ajax_url,
                data: {
                    action: 'gift_credits',
                    nonce: intersoccer_dashboard.nonce,
                    gift_amount: jQuery('input[name="gift_amount"]').val(),
                    recipient_email: jQuery('input[name="recipient_email"]').val()
                },
                success: function(res) {
                    alert(res.data.message);
                    if (res.success) location.reload();
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    private function get_customer_leaderboard($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        $top = $wpdb->get_results($wpdb->prepare("
            SELECT u.ID, u.display_name, COUNT(r.id) as referral_count
            FROM {$wpdb->users} u
            LEFT JOIN $table_name r ON u.ID = r.customer_id
            WHERE r.created_at >= %s
            GROUP BY u.ID
            ORDER BY referral_count DESC
            LIMIT 5",
            date('Y-m-01 00:00:00')
        ));
        $user_rank = $wpdb->get_var($wpdb->prepare("
            SELECT (COUNT(*) + 1)
            FROM $table_name r
            JOIN $table_name r2 ON r.customer_id != %d AND r2.customer_id = %d
            WHERE r.created_at >= %s AND r2.created_at >= %s
            GROUP BY r.customer_id
            HAVING COUNT(r.id) > (SELECT COUNT(*) FROM $table_name WHERE customer_id = %d AND created_at >= %s)",
            $user_id, $user_id, date('Y-m-01 00:00:00'), date('Y-m-01 00:00:00'), $user_id, date('Y-m-01 00:00:00')
        ));
        return ['top' => $top, 'user_rank' => $user_rank];
    }
}