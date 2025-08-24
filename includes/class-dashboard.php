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
        
        // Get customer badges and stats
        $total_referrals = count($referrals);
        $badges = $this->get_customer_badges($user_id, $total_referrals, $credits);
        $recent_activity = $this->get_recent_customer_activity($user_id);
        $leaderboard_position = $this->get_customer_leaderboard_position($user_id);
        
        ob_start();
        ?>
        <div class="intersoccer-customer-dashboard">
            <div class="dashboard-header">
                <h2>🎯 Your Referral Dashboard</h2>
                <div class="dashboard-stats">
                    <div class="stat-card credits-card" data-credits="<?php echo $credits; ?>">
                        <div class="stat-icon">💰</div>
                        <div class="stat-content">
                            <span class="stat-number" id="credits-display"><?php echo number_format($credits, 2); ?></span>
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
                    
                    <?php if ($leaderboard_position <= 10): ?>
                    <div class="stat-card leaderboard-card">
                        <div class="stat-icon">🏆</div>
                        <div class="stat-content">
                            <span class="stat-number">#<?php echo $leaderboard_position; ?></span>
                            <span class="stat-label">Leaderboard Rank</span>
                        </div>
                    </div>
                    <?php endif; ?>
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

            <!-- Referral Link Section -->
            <div class="referral-section">
                <h3>📤 Share & Earn</h3>
                <div class="referral-info">
                    <p class="referral-description">
                        <span class="highlight">Earn 500 points (50 CHF)</span> for every friend who joins InterSoccer! 
                        Share your personalized link:
                    </p>
                </div>
                
                <div class="referral-link-container">
                    <input type="text" id="referral-link" value="<?php echo esc_attr($referral_link); ?>" readonly>
                    <button id="copy-link-btn" class="copy-button" onclick="copyReferralLink()">
                        <span class="button-text">📋 Copy</span>
                        <span class="button-success">✅ Copied!</span>
                    </button>
                </div>
                
                <div class="social-share-buttons">
                    <a href="https://wa.me/?text=<?php echo urlencode("Join me at InterSoccer for amazing soccer training! " . $referral_link); ?>" 
                       target="_blank" class="social-btn whatsapp-btn">
                        📱 WhatsApp
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($referral_link); ?>" 
                       target="_blank" class="social-btn facebook-btn">
                        📘 Facebook
                    </a>
                    <a href="mailto:?subject=<?php echo urlencode('Join InterSoccer!'); ?>&body=<?php echo urlencode("I thought you'd love InterSoccer's soccer training programs! Join here: " . $referral_link); ?>" 
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
                            <span>Next milestone: 1000 points for 100 CHF bonus!</span>
                            <span class="progress-percentage"><?php echo min(100, ($credits / 1000) * 100); ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min(100, ($credits / 1000) * 100); ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="milestones">
                        <div class="milestone <?php echo $credits >= 500 ? 'achieved' : ''; ?>">
                            <span class="milestone-icon">🥉</span>
                            <span class="milestone-text">500 CHF</span>
                        </div>
                        <div class="milestone <?php echo $credits >= 1000 ? 'achieved' : ''; ?>">
                            <span class="milestone-icon">🥈</span>
                            <span class="milestone-text">1000 CHF</span>
                        </div>
                        <div class="milestone <?php echo $credits >= 2000 ? 'achieved' : ''; ?>">
                            <span class="milestone-icon">🥇</span>
                            <span class="milestone-text">2000 CHF</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gift Credits Section -->
            <div class="gift-section">
                <h3>🎁 Gift Credits</h3>
                <p>Spread the joy! Gift credits to friends and family (you get 20 points back!)</p>
                
                <form id="gift-credits" method="post" class="gift-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Gift Amount (50-<?php echo min($credits, 500); ?> CHF):</label>
                            <input type="number" name="gift_amount" min="50" max="<?php echo min($credits, 500); ?>" step="10" required>
                        </div>
                        <div class="form-group">
                            <label>Recipient Email:</label>
                            <input type="email" name="recipient_email" placeholder="friend@example.com" required>
                        </div>
                    </div>
                    <button type="submit" class="gift-button">🎁 Send Gift</button>
                </form>
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
        .referral-section, .progress-section, .gift-section, .activity-section {
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
        
        /* Form Styling */
        .gift-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .gift-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: transform 0.2s ease;
        }
        
        .gift-button:hover {
            transform: translateY(-2px);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
            
            .form-row {
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
        function copyReferralLink() {
            const linkInput = document.getElementById('referral-link');
            const copyBtn = document.getElementById('copy-link-btn');
            
            linkInput.select();
            document.execCommand('copy');
            
            copyBtn.classList.add('copied');
            
            // Trigger credit pulse animation
            const creditCard = document.querySelector('.credits-card');
            creditCard.style.animation = 'none';
            creditCard.offsetHeight; // Trigger reflow
            creditCard.style.animation = 'pulse 0.6s ease-out';
            
            setTimeout(() => {
                copyBtn.classList.remove('copied');
            }, 2000);
        }

        // Gift credits form handling
        jQuery('#gift-credits').on('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('.gift-button');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '⏳ Sending...';
            submitBtn.disabled = true;
            
            jQuery.post({
                url: intersoccer_dashboard.ajax_url,
                data: {
                    action: 'gift_credits',
                    nonce: intersoccer_dashboard.nonce,
                    gift_amount: jQuery('input[name="gift_amount"]').val(),
                    recipient_email: jQuery('input[name="recipient_email"]').val()
                },
                success: function(res) {
                    if (res.success) {
                        // Animate success
                        submitBtn.innerHTML = '✅ Sent!';
                        submitBtn.style.background = '#28a745';
                        
                        // Update credits display
                        const creditsDisplay = document.getElementById('credits-display');
                        const currentCredits = parseFloat(creditsDisplay.textContent.replace(',', ''));
                        const newCredits = currentCredits - parseFloat(jQuery('input[name="gift_amount"]').val()) + 20;
                        creditsDisplay.textContent = newCredits.toFixed(2);
                        
                        // Reset form
                        document.getElementById('gift-credits').reset();
                        
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        submitBtn.innerHTML = '❌ Error';
                        submitBtn.style.background = '#dc3545';
                        alert(res.data.message);
                    }
                },
                error: function() {
                    submitBtn.innerHTML = '❌ Error';
                    submitBtn.style.background = '#dc3545';
                },
                complete: function() {
                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        submitBtn.style.background = '';
                    }, 3000);
                }
            });
        });

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
}