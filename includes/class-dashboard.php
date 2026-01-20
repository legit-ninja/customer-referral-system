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

        // Use modern dashboard for coaches
        if (current_user_can('coach')) {
            ob_start();
            include INTERSOCCER_REFERRAL_PATH . 'templates/modern-coach-dashboard.php';
            return ob_get_clean();
        }

        // Fallback to basic dashboard for other users
        $user_id = get_current_user_id();
        $credits = (float) get_user_meta($user_id, 'intersoccer_credits', true);
        $referral_link = InterSoccer_Referral_Handler::generate_coach_referral_link($user_id);
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
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your referral dashboard.', 'intersoccer-referral') . '</p>';
        }
        $user_id = get_current_user_id();
        $credits = intersoccer_get_customer_credits($user_id);
        $referrals = get_user_meta($user_id, 'intersoccer_referrals_made', true) ?: [];
        $referral_link = InterSoccer_Referral_Handler::generate_customer_referral_link($user_id);
        $referral_code = get_user_meta($user_id, 'intersoccer_customer_referral_code', true);
        if (!$referral_code) {
            // Generate code if it doesn't exist
            $referral_code = 'CUST' . $user_id . strtoupper(str_replace('_', '', wp_generate_password(6, false)));
            update_user_meta($user_id, 'intersoccer_customer_referral_code', $referral_code);
        }
        // Get customer badges and stats
        $total_referrals = count($referrals);
        $badges = $this->get_customer_badges($user_id, $total_referrals, $credits);
        $recent_activity = $this->get_recent_customer_activity($user_id);
        $leaderboard_position = $this->get_customer_leaderboard_position($user_id);
        
        ob_start();
        ?>
        <div class="intersoccer-customer-dashboard">
            <div class="dashboard-header">
                <h2>Your Referral Dashboard</h2>
                <div class="dashboard-stats">
                    <div class="stat-card credits-card" data-credits="<?php echo $credits; ?>">
                        <div class="stat-icon">💰</div>
                        <div class="stat-content">
                            <span class="stat-number" id="credits-display"><?php echo number_format($credits, 0); ?></span>
                            <span class="stat-label">CHF Credits</span>
                            <?php if ($credits > 0): ?>
                                <div class="credit-pulse"></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="stat-card referrals-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $total_referrals; ?></span>
                            <span class="stat-label">Friends Referred</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Badges Section -->
            <?php if (!empty($badges)): ?>
            <div class="badges-section">
                <h3>🏅 Your Achievements</h3>
                <div class="badges-container">
                    <?php foreach ($badges as $badge): ?>
                    <div class="badge-item <?php echo $badge['class']; ?>" title="<?php echo esc_attr($badge['description']); ?>">
                        <span class="badge-icon"><?php echo $badge['icon']; ?></span>
                        <span class="badge-name"><?php echo $badge['name']; ?></span>
                        <?php if ($badge['is_new']): ?>
                            <div class="badge-new-indicator">NEW!</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Referral Code & Link Section -->
            <div class="referral-section">
                <h3>📤 Share & Earn</h3>
                <div class="referral-info">
                    <p class="referral-description">
                        <span class="highlight"><?php esc_html_e('Earn 10% of their purchase value', 'intersoccer-referral'); ?></span> <?php esc_html_e('for every friend who makes a purchase!', 'intersoccer-referral'); ?> 
                        <?php esc_html_e('Share your personalized referral code or link:', 'intersoccer-referral'); ?>
                    </p>
                </div>
                
                <!-- Referral Code Display -->
                <div class="referral-code-container" style="margin-bottom: 20px;">
                    <label for="referral-code" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                        <?php esc_html_e('Your Referral Code', 'intersoccer-referral'); ?>
                    </label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="text" 
                               id="referral-code" 
                               value="<?php echo esc_attr($referral_code); ?>" 
                               readonly 
                               style="flex: 1; padding: 14px; border: 2px solid #667eea; border-radius: 8px; font-size: 18px; font-weight: bold; text-align: center; letter-spacing: 2px; font-family: monospace; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                        <button id="copy-code-btn" class="copy-button" onclick="copyReferralCode()" style="padding: 14px 24px; font-size: 16px;">
                            <span class="button-text">📋 Copy Code</span>
                            <span class="button-success">✅ Copied!</span>
                        </button>
                    </div>
                </div>
                
                <!-- Referral Link Display -->
                <div class="referral-link-container">
                    <label for="referral-link" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                        <?php esc_html_e('Your Referral Link', 'intersoccer-referral'); ?>
                    </label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" 
                               id="referral-link" 
                               value="<?php echo esc_attr($referral_link); ?>" 
                               readonly
                               style="flex: 1; padding: 12px; border: 2px solid #e1e5e9; border-radius: 6px; font-family: monospace; background: #f8f9fa; font-size: 14px;">
                        <button id="copy-link-btn" class="copy-button" onclick="copyReferralLink()" style="padding: 12px 20px;">
                            <span class="button-text">📋 Copy Link</span>
                            <span class="button-success">✅ Copied!</span>
                        </button>
                    </div>
                </div>
                
                <div class="social-share-buttons" style="margin-top: 20px;">
                    <a href="https://wa.me/?text=<?php echo urlencode("Join me at InterSoccer for amazing soccer training! Use my referral code: " . $referral_code . " or visit: " . $referral_link); ?>" 
                       target="_blank" class="social-btn whatsapp-btn">
                        📱 WhatsApp
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($referral_link); ?>&quote=<?php echo urlencode('Join InterSoccer! Use my referral code: ' . $referral_code); ?>" 
                       target="_blank" class="social-btn facebook-btn">
                        📘 Facebook
                    </a>
                    <a href="mailto:?subject=<?php echo urlencode('Join InterSoccer!'); ?>&body=<?php echo urlencode("I thought you'd love InterSoccer's soccer training programs! Use my referral code: " . $referral_code . " or visit: " . $referral_link); ?>" 
                       class="social-btn email-btn">
                        📧 Email
                    </a>
                </div>
            </div>

            <!-- Progress Section -->
            <div class="progress-section">
                <h3>📈 Your Progress</h3>
                <div class="progress-container">
                    <div class="progress-bar-wrapper">
                        <div class="progress-info">
                            <span><?php esc_html_e('Next milestone: 100 points for 100 CHF bonus!', 'intersoccer-referral'); ?></span>
                            <span class="progress-percentage"><?php echo min(100, ($credits / 100) * 100); ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min(100, ($credits / 100) * 100); ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="milestones">
                        <div class="milestone <?php echo $credits >= 100 ? 'achieved' : ''; ?>">
                            <span class="milestone-icon">🥉</span>
                            <span class="milestone-text">100 CHF</span>
                        </div>
                        <div class="milestone <?php echo $credits >= 500 ? 'achieved' : ''; ?>">
                            <span class="milestone-icon">🥈</span>
                            <span class="milestone-text">500 CHF</span>
                        </div>
                        <div class="milestone <?php echo $credits >= 1000 ? 'achieved' : ''; ?>">
                            <span class="milestone-icon">🥇</span>
                            <span class="milestone-text">1000 CHF</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <?php if (!empty($recent_activity)): ?>
            <div class="activity-section">
                <h3>📋 Recent Activity</h3>
                <div class="activity-feed">
                    <?php foreach ($recent_activity as $activity): ?>
                    <div class="activity-item">
                        <span class="activity-icon"><?php echo $activity['icon']; ?></span>
                        <div class="activity-content">
                            <p><?php echo $activity['message']; ?></p>
                            <span class="activity-time"><?php echo $activity['time']; ?></span>
                        </div>
                        <?php if (isset($activity['points'])): ?>
                        <span class="activity-points">+<?php echo $activity['points']; ?> CHF</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Enhanced CSS with Animations -->
        <style>
        .intersoccer-customer-dashboard {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .dashboard-header h2 {
            margin-bottom: 30px;
            color: #2c3e50;
            font-size: 28px;
        }
        
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .credits-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stat-icon {
            font-size: 24px;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            display: block;
        }
        
        .stat-label {
            font-size: 12px;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Credit Pulse Animation */
        .credit-pulse {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 10px;
            height: 10px;
            background: #00ff88;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.5); }
            100% { opacity: 1; transform: scale(1); }
        }
        
        /* Badges Section */
        .badges-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .badges-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .badge-item {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            position: relative;
            transition: transform 0.2s ease;
        }
        
        .badge-item:hover {
            transform: scale(1.05);
        }
        
        .badge-item.top-referrer {
            background: linear-gradient(135deg, #ffd89b 0%, #19547b 100%);
        }
        
        .badge-item.milestone-500 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .badge-icon {
            font-size: 24px;
            display: block;
            margin-bottom: 5px;
        }
        
        .badge-new-indicator {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff4757;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            animation: bounce 1s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        
        /* Referral Section */
        .referral-section, .progress-section, .activity-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .highlight {
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: bold;
        }
        
        .referral-link-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        #referral-link {
            flex: 1;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-family: monospace;
            background: #f8f9fa;
        }
        
        .copy-button {
            padding: 12px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        
        .copy-button:hover {
            background: #5a6fd8;
            transform: translateY(-1px);
        }
        
        .copy-button.copied .button-text {
            display: none;
        }
        
        .copy-button .button-success {
            display: none;
        }
        
        .copy-button.copied .button-success {
            display: inline;
        }
        
        .social-share-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .social-btn {
            padding: 10px 16px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: transform 0.2s ease;
        }
        
        .social-btn:hover {
            transform: translateY(-2px);
        }
        
        .whatsapp-btn { background: #25d366; color: white; }
        .facebook-btn { background: #4267b2; color: white; }
        .email-btn { background: #6c757d; color: white; }
        
        /* Progress Bar */
        .progress-bar {
            width: 100%;
            height: 12px;
            background: #e9ecef;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 1s ease-out;
            animation: fillProgress 2s ease-out;
        }
        
        @keyframes fillProgress {
            from { width: 0; }
        }
        
        .milestones {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
        }
        
        .milestone {
            text-align: center;
            opacity: 0.5;
            transition: all 0.3s ease;
        }
        
        .milestone.achieved {
            opacity: 1;
            transform: scale(1.1);
        }
        
        /* Activity Feed */
        .activity-feed {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            font-size: 20px;
            width: 40px;
            text-align: center;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-time {
            font-size: 12px;
            color: #666;
        }
        
        .activity-points {
            font-weight: bold;
            color: #28a745;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
            
            .social-share-buttons {
                justify-content: center;
            }
            
            .referral-link-container {
                flex-direction: column;
            }
        }
        </style>

        <script>

        // Copy referral code function
        function copyReferralCode() {
            const codeInput = document.getElementById('referral-code');
            const copyBtn = document.getElementById('copy-code-btn');
            
            codeInput.select();
            codeInput.setSelectionRange(0, 99999); // For mobile devices
            
            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(codeInput.value).then(function() {
                        copyBtn.classList.add('copied');
                        setTimeout(() => copyBtn.classList.remove('copied'), 2000);
                    });
                } else {
                    document.execCommand('copy');
                    copyBtn.classList.add('copied');
                    setTimeout(() => copyBtn.classList.remove('copied'), 2000);
                }
            } catch (err) {
                document.execCommand('copy');
                copyBtn.classList.add('copied');
                setTimeout(() => copyBtn.classList.remove('copied'), 2000);
            }
            
            // Trigger credit pulse animation
            const creditCard = document.querySelector('.credits-card');
            if (creditCard) {
                creditCard.style.animation = 'none';
                creditCard.offsetHeight; // Trigger reflow
                creditCard.style.animation = 'pulse 0.6s ease-out';
            }
        }

        // Copy referral link function
        function copyReferralLink() {
            const linkInput = document.getElementById('referral-link');
            const copyBtn = document.getElementById('copy-link-btn');
            
            linkInput.select();
            linkInput.setSelectionRange(0, 99999); // For mobile devices
            
            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(linkInput.value).then(function() {
                        copyBtn.classList.add('copied');
                        setTimeout(() => copyBtn.classList.remove('copied'), 2000);
                    });
                } else {
                    document.execCommand('copy');
                    copyBtn.classList.add('copied');
                    setTimeout(() => copyBtn.classList.remove('copied'), 2000);
                }
            } catch (err) {
                document.execCommand('copy');
                copyBtn.classList.add('copied');
                setTimeout(() => copyBtn.classList.remove('copied'), 2000);
            }
            
            // Trigger credit pulse animation
            const creditCard = document.querySelector('.credits-card');
            if (creditCard) {
                creditCard.style.animation = 'none';
                creditCard.offsetHeight; // Trigger reflow
                creditCard.style.animation = 'pulse 0.6s ease-out';
            }
        }


        // Animate numbers on page load
        document.addEventListener('DOMContentLoaded', function() {
            const creditNumber = document.getElementById('credits-display');
            const targetValue = parseFloat(creditNumber.textContent.replace(',', ''));
            
            let currentValue = 0;
            const increment = targetValue / 50;
            const timer = setInterval(() => {
                currentValue += increment;
                if (currentValue >= targetValue) {
                    currentValue = targetValue;
                    clearInterval(timer);
                }
                creditNumber.textContent = currentValue.toFixed(2);
            }, 30);
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Get customer badges based on activity
     */
    private function get_customer_badges($user_id, $total_referrals, $credits) {
        $badges = [];
        
        // Top Referrer Badge
        if ($total_referrals >= 5) {
            $badges[] = [
                'name' => 'Top Referrer',
                'icon' => '🌟',
                'class' => 'top-referrer',
                'description' => 'Referred 5+ friends',
                'is_new' => $this->is_badge_new($user_id, 'top_referrer')
            ];
        }
        
        // Milestone Badges
        if ($credits >= 500) {
            $badges[] = [
                'name' => '500 CHF Club',
                'icon' => '💎',
                'class' => 'milestone-500',
                'description' => 'Earned 500+ CHF in credits',
                'is_new' => $this->is_badge_new($user_id, 'milestone_500')
            ];
        }
        
        // First Referral Badge
        if ($total_referrals >= 1) {
            $badges[] = [
                'name' => 'First Friend',
                'icon' => '🤝',
                'class' => 'first-referral',
                'description' => 'Made your first referral',
                'is_new' => $this->is_badge_new($user_id, 'first_referral')
            ];
        }
        
        return $badges;
    }

    private function is_badge_new($user_id, $badge_key) {
        $awarded_badges = get_user_meta($user_id, 'intersoccer_customer_badges', true) ?: [];
        return !in_array($badge_key, $awarded_badges);
    }

    private function get_recent_customer_activity($user_id) {
        // Placeholder for recent activity
        return [
            [
                'icon' => '🎉',
                'message' => 'Welcome bonus earned!',
                'time' => '2 hours ago',
                'points' => 50
            ],
            [
                'icon' => '👥',
                'message' => 'Friend joined via your link',
                'time' => '1 day ago',
                'points' => 500
            ]
        ];
    }

    private function get_customer_leaderboard_position($user_id) {
        // Placeholder - implement actual leaderboard logic
        return rand(3, 15);
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

    // Helper methods for modern coach dashboard
    private function get_monthly_stats($coach_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';

        // Get current month stats
        $current_month = date('Y-m-01 00:00:00');
        $last_month = date('Y-m-01 00:00:00', strtotime('-1 month'));

        $current_referrals = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE coach_id = %d AND created_at >= %s",
            $coach_id, $current_month
        ));

        $last_month_referrals = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE coach_id = %d AND created_at >= %s AND created_at < %s",
            $coach_id, $last_month, $current_month
        ));

        $conversion_rate = $current_referrals > 0 ? min(100, ($current_referrals / max(1, $current_referrals + 5)) * 100) : 0;
        $last_month_conversion = $last_month_referrals > 0 ? min(100, ($last_month_referrals / max(1, $last_month_referrals + 5)) * 100) : 0;

        return [
            'new_referrals' => $current_referrals,
            'conversion_rate' => round($conversion_rate, 1),
            'conversion_trend' => $conversion_rate - $last_month_conversion
        ];
    }

    private function get_tier_progress($tier, $referral_count) {
        $tiers = [
            'bronze' => ['min' => 0, 'max' => 10],
            'silver' => ['min' => 11, 'max' => 24],
            'gold' => ['min' => 25, 'max' => 50],
            'platinum' => ['min' => 51, 'max' => 100]
        ];

        if (!isset($tiers[$tier])) return 0;

        $current_tier = $tiers[$tier];
        $progress = ($referral_count - $current_tier['min']) / ($current_tier['max'] - $current_tier['min']) * 100;
        return min(100, max(0, $progress));
    }

    private function get_next_tier_requirements($tier) {
        $tiers = [
            'bronze' => '11 referrals for Silver',
            'silver' => '25 referrals for Gold',
            'gold' => '51 referrals for Platinum',
            'platinum' => 'Platinum tier achieved!'
        ];

        return $tiers[$tier] ?? 'Keep going!';
    }

    private function get_top_performers() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';

        return $wpdb->get_results($wpdb->prepare("
            SELECT
                u.ID,
                u.display_name,
                COUNT(r.id) as referral_count,
                COALESCE(SUM(r.commission_amount), 0) as total_credits,
                intersoccer_get_coach_tier(u.ID) as tier
            FROM {$wpdb->users} u
            LEFT JOIN $table_name r ON u.ID = r.coach_id
            WHERE u.ID IN (
                SELECT DISTINCT coach_id FROM $table_name
                UNION
                SELECT DISTINCT ID FROM {$wpdb->users} WHERE intersoccer_get_coach_tier(ID) IS NOT NULL
            )
            GROUP BY u.ID
            ORDER BY total_credits DESC, referral_count DESC
            LIMIT 10
        "));
    }

    private function get_coach_rank($coach_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';

        $coach_credits = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(commission_amount), 0)
            FROM $table_name
            WHERE coach_id = %d
        ", $coach_id));

        $rank = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT coach_id) + 1
            FROM $table_name
            GROUP BY coach_id
            HAVING SUM(commission_amount) > %d
        ", $coach_credits));

        return $rank ?: 1;
    }

    private function get_coach_achievements($coach_id) {
        $referral_count = $this->get_coach_referral_count($coach_id);
        $credits = (float) get_user_meta($coach_id, 'intersoccer_credits', true);

        $achievements = [
            [
                'title' => 'First Referral',
                'description' => 'Made your first successful referral',
                'icon' => 'handshake',
                'unlocked' => $referral_count >= 1,
                'progress' => min(100, $referral_count * 100),
                'progress_text' => $referral_count >= 1 ? 'Completed!' : 'Make 1 referral'
            ],
            [
                'title' => 'Top Earner',
                'description' => 'Earned 500 CHF in commissions',
                'icon' => 'trophy',
                'unlocked' => $credits >= 500,
                'progress' => min(100, ($credits / 500) * 100),
                'progress_text' => $credits >= 500 ? 'Completed!' : number_format(500 - $credits, 0) . ' CHF to go'
            ],
            [
                'title' => 'Referral Master',
                'description' => 'Generated 25 successful referrals',
                'icon' => 'users',
                'unlocked' => $referral_count >= 25,
                'progress' => min(100, ($referral_count / 25) * 100),
                'progress_text' => $referral_count >= 25 ? 'Completed!' : (25 - $referral_count) . ' referrals to go'
            ],
            [
                'title' => 'Commission Champion',
                'description' => 'Earned 1000 CHF in total commissions',
                'icon' => 'crown',
                'unlocked' => $credits >= 1000,
                'progress' => min(100, ($credits / 1000) * 100),
                'progress_text' => $credits >= 1000 ? 'Completed!' : number_format(1000 - $credits, 0) . ' CHF to go'
            ]
        ];

        return $achievements;
    }

    private function get_chart_labels($days) {
        $labels = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $labels[] = date('M j', strtotime("-{$i} days"));
        }
        return $labels;
    }

    private function get_chart_data($coach_id, $days, $type) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';

        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $next_date = date('Y-m-d', strtotime("-" . ($i - 1) . " days"));

            if ($type === 'referrals') {
                $value = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE coach_id = %d AND DATE(created_at) = %s",
                    $coach_id, $date
                ));
            } else { // credits
                $value = $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(commission_amount), 0) FROM $table_name WHERE coach_id = %d AND DATE(created_at) = %s",
                    $coach_id, $date
                ));
            }

            $data[] = (float) $value;
        }

        return $data;
    }

    private function get_customer_name($customer_id) {
        $user = get_user_by('ID', $customer_id);
        return $user ? $user->display_name : 'Unknown Customer';
    }

    /**
     * Get count of customers linked to this coach
     */
    private function get_linked_customers_count($coach_id) {
        global $wpdb;

        // Count unique customers who have this coach as their preferred coach
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT user_id)
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'intersoccer_preferred_coach'
            AND meta_value = %d
        ", $coach_id));

        return (int) $count;
    }
}