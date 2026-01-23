<?php
// includes/class-dashboard.php

class InterSoccer_Referral_Dashboard {

    /**
     * Guard to prevent rendering the customer dashboard multiple times per request.
     * This can happen if the dashboard is embedded via shortcode/widget AND also rendered via a WooCommerce endpoint.
     *
     * @var bool
     */
    private static $customer_dashboard_rendered = false;

    public function __construct() {
        add_shortcode('intersoccer_coach_dashboard', [$this, 'render_dashboard']);
        add_shortcode('intersoccer_customer_dashboard', [$this, 'render_customer_dashboard']);
        
        // Register WPML strings early on init hook
        add_action('init', [$this, 'register_wpml_strings'], 20);
    }
    
    /**
     * Register all dashboard strings with WPML for translation
     */
    public function register_wpml_strings() {
        if (!defined('ICL_SITEPRESS_VERSION')) {
            return;
        }
        
        // Register all customer dashboard strings
        $strings = [
            'customer_dashboard_share_earn' => 'Share & Earn',
            'customer_dashboard_earn_10_percent' => 'Earn 10% of their purchase value',
            'customer_dashboard_for_every_friend' => 'for every friend who makes a purchase!',
            'customer_dashboard_share_description' => 'Share your personalized referral code or link:',
            'customer_dashboard_your_referral_code' => 'Your Referral Code',
            'customer_dashboard_your_referral_link' => 'Your Referral Link',
            'customer_dashboard_copy_code' => 'Copy Code',
            'customer_dashboard_copy_link' => 'Copy Link',
            'customer_dashboard_copied' => 'Copied!',
            'customer_dashboard_whatsapp' => 'WhatsApp',
            'customer_dashboard_facebook' => 'Facebook',
            'customer_dashboard_email' => 'Email',
            'customer_dashboard_whatsapp_message' => 'Join me at InterSoccer for amazing soccer training! Use my referral code: %s or visit: %s',
            'customer_dashboard_facebook_message' => 'Join InterSoccer! Use my referral code: %s',
            'customer_dashboard_email_subject' => 'Join InterSoccer!',
            'customer_dashboard_email_body' => 'I thought you\'d love InterSoccer\'s soccer training programs! Use my referral code: %s or visit: %s',
        ];
        
        foreach ($strings as $name => $string) {
            // Try both methods for WPML registration
            if (function_exists('icl_register_string')) {
                icl_register_string('Intersoccer Referral System', $name, $string);
            } else {
                do_action('wpml_register_single_string', 'Intersoccer Referral System', $name, $string);
            }
        }
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
        global $wp_query;
        
        // Check if we're on a WooCommerce account endpoint - if so, only render via endpoint, not widget/shortcode
        $endpoint_slugs = ['referrals', 'parrainages', 'empfehlungen'];
        $is_endpoint = false;
        $detected_slug = null;
        foreach ($endpoint_slugs as $slug) {
            $query_var_value = get_query_var($slug, false);
            $wp_query_var = isset($wp_query->query_vars[$slug]) ? $wp_query->query_vars[$slug] : false;
            if ($query_var_value !== false || ($wp_query_var !== false && $wp_query_var)) {
                $is_endpoint = true;
                $detected_slug = $slug;
                break;
            }
        }
        
        // If we're on an endpoint, check if we're being called from the endpoint renderer
        // If not, return empty to prevent widget/shortcode from rendering on endpoint pages
        if ($is_endpoint) {
            $backtrace = wp_debug_backtrace_summary();
            $is_from_endpoint = (strpos($backtrace, 'render_customer_account_endpoint') !== false || 
                                 strpos($backtrace, 'woocommerce_account') !== false ||
                                 strpos($backtrace, 'maybe_render_endpoint') !== false);
            
            if (!$is_from_endpoint) {
                return '';
            }
        }
        
        // Guard against duplicate renders (endpoint + shortcode/widget, etc.)
        if (self::$customer_dashboard_rendered) {
            return '';
        }
        self::$customer_dashboard_rendered = true;

        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your referral dashboard.', 'intersoccer-referral') . '</p>';
        }
        $user_id = get_current_user_id();
        $referral_link = InterSoccer_Referral_Handler::generate_customer_referral_link($user_id);
        $referral_code = get_user_meta($user_id, 'intersoccer_customer_referral_code', true);
        if (!$referral_code) {
            // Generate code if it doesn't exist
            $referral_code = 'CUST' . $user_id . strtoupper(str_replace('_', '', wp_generate_password(6, false)));
            update_user_meta($user_id, 'intersoccer_customer_referral_code', $referral_code);
        }
        
        // Define all strings with readable names (for WPML)
        $string_name_share_earn = 'customer_dashboard_share_earn';
        $string_name_earn_text = 'customer_dashboard_earn_10_percent';
        $string_name_for_every_friend = 'customer_dashboard_for_every_friend';
        $string_name_share_description = 'customer_dashboard_share_description';
        $string_name_your_referral_code = 'customer_dashboard_your_referral_code';
        $string_name_your_referral_link = 'customer_dashboard_your_referral_link';
        $string_name_copy_code = 'customer_dashboard_copy_code';
        $string_name_copy_link = 'customer_dashboard_copy_link';
        $string_name_copied = 'customer_dashboard_copied';
        $string_name_whatsapp = 'customer_dashboard_whatsapp';
        $string_name_facebook = 'customer_dashboard_facebook';
        $string_name_email = 'customer_dashboard_email';
        $string_name_whatsapp_message = 'customer_dashboard_whatsapp_message';
        $string_name_facebook_message = 'customer_dashboard_facebook_message';
        $string_name_email_subject = 'customer_dashboard_email_subject';
        $string_name_email_body = 'customer_dashboard_email_body';
        
        // Define original English strings
        $original_share_earn = 'Share & Earn';
        $original_earn_text = 'Earn 10% of their purchase value';
        $original_for_every_friend = 'for every friend who makes a purchase!';
        $original_share_description = 'Share your personalized referral code or link:';
        $original_your_referral_code = 'Your Referral Code';
        $original_your_referral_link = 'Your Referral Link';
        $original_copy_code = 'Copy Code';
        $original_copy_link = 'Copy Link';
        $original_copied = 'Copied!';
        $original_whatsapp = 'WhatsApp';
        $original_facebook = 'Facebook';
        $original_email = 'Email';
        $original_whatsapp_message = 'Join me at InterSoccer for amazing soccer training! Use my referral code: %s or visit: %s';
        $original_facebook_message = 'Join InterSoccer! Use my referral code: %s';
        $original_email_subject = 'Join InterSoccer!';
        $original_email_body = 'I thought you\'d love InterSoccer\'s soccer training programs! Use my referral code: %s or visit: %s';
        
        // Get translated strings - use WordPress translation as fallback
        $share_earn_title = __('Share & Earn', 'intersoccer-referral');
        $earn_text = __('Earn 10% of their purchase value', 'intersoccer-referral');
        $for_every_friend = __('for every friend who makes a purchase!', 'intersoccer-referral');
        $share_description = __('Share your personalized referral code or link:', 'intersoccer-referral');
        $your_referral_code_label = __('Your Referral Code', 'intersoccer-referral');
        $your_referral_link_label = __('Your Referral Link', 'intersoccer-referral');
        $copy_code_text = __('Copy Code', 'intersoccer-referral');
        $copy_link_text = __('Copy Link', 'intersoccer-referral');
        $copied_text = __('Copied!', 'intersoccer-referral');
        $whatsapp_label = __('WhatsApp', 'intersoccer-referral');
        $facebook_label = __('Facebook', 'intersoccer-referral');
        $email_label = __('Email', 'intersoccer-referral');
        $whatsapp_message_template = __('Join me at InterSoccer for amazing soccer training! Use my referral code: %s or visit: %s', 'intersoccer-referral');
        $facebook_message_template = __('Join InterSoccer! Use my referral code: %s', 'intersoccer-referral');
        $email_subject = __('Join InterSoccer!', 'intersoccer-referral');
        $email_body_template = __('I thought you\'d love InterSoccer\'s soccer training programs! Use my referral code: %s or visit: %s', 'intersoccer-referral');
        
        // Apply WPML translations if available (strings are registered on init hook)
        if (defined('ICL_SITEPRESS_VERSION')) {
            $current_lang = function_exists('icl_get_current_language') ? icl_get_current_language() : 'en';
            // Try multiple methods to retrieve translations
            // Method 1: apply_filters('wpml_translate_single_string', ...) - recommended for newer WPML versions
            // Method 2: icl_t() - older method but still supported
            
            // Helper function to get translation with fallback
            $get_translation = function($original, $name) use ($current_lang) {
                global $wpdb;
                
                // First, check if translation exists in WPML database directly
                // This is more reliable than the filter when translations exist but filter doesn't work
                $string_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}icl_strings WHERE context = %s AND name = %s",
                    'Intersoccer Referral System',
                    $name
                ));
                
                if ($string_id) {
                    // Check if translation exists for current language
                    // Try both 'fr' and 'fr_FR' format (WPML might use either)
                    $translation = $wpdb->get_row($wpdb->prepare(
                        "SELECT value, status FROM {$wpdb->prefix}icl_string_translations WHERE string_id = %d AND language = %s",
                        $string_id,
                        $current_lang
                    ));
                    
                    // If not found, try with underscore format (e.g., fr_FR instead of fr)
                    if (!$translation && strlen($current_lang) == 2) {
                        $lang_with_locale = $current_lang . '_' . strtoupper($current_lang);
                        $translation = $wpdb->get_row($wpdb->prepare(
                            "SELECT value, status FROM {$wpdb->prefix}icl_string_translations WHERE string_id = %d AND language = %s",
                            $string_id,
                            $lang_with_locale
                        ));
                    }
                    
                    // If translation found and status is 10 (complete), use it
                    if ($translation && $translation->status == 10 && !empty($translation->value)) {
                        return $translation->value;
                    }
                }
                
                // Fallback: Try apply_filters (newer WPML method)
                if (has_filter('wpml_translate_single_string')) {
                    return apply_filters('wpml_translate_single_string', $original, 'Intersoccer Referral System', $name, $current_lang);
                }
                
                // Fallback: Try icl_t()
                if (function_exists('icl_t')) {
                    return icl_t('Intersoccer Referral System', $name, $original);
                }
                
                return $original;
            };
            
            $share_earn_title = $get_translation($original_share_earn, $string_name_share_earn);
            $earn_text = $get_translation($original_earn_text, $string_name_earn_text);
            $for_every_friend = $get_translation($original_for_every_friend, $string_name_for_every_friend);
            $share_description = $get_translation($original_share_description, $string_name_share_description);
            $your_referral_code_label = $get_translation($original_your_referral_code, $string_name_your_referral_code);
            $your_referral_link_label = $get_translation($original_your_referral_link, $string_name_your_referral_link);
            $copy_code_text = $get_translation($original_copy_code, $string_name_copy_code);
            $copy_link_text = $get_translation($original_copy_link, $string_name_copy_link);
            $copied_text = $get_translation($original_copied, $string_name_copied);
            $whatsapp_label = $get_translation($original_whatsapp, $string_name_whatsapp);
            $facebook_label = $get_translation($original_facebook, $string_name_facebook);
            $email_label = $get_translation($original_email, $string_name_email);
            $whatsapp_message_template = $get_translation($original_whatsapp_message, $string_name_whatsapp_message);
            $facebook_message_template = $get_translation($original_facebook_message, $string_name_facebook_message);
            $email_subject = $get_translation($original_email_subject, $string_name_email_subject);
            $email_body_template = $get_translation($original_email_body, $string_name_email_body);
        }
        
        // Build messages with referral code and link
        $whatsapp_message = sprintf($whatsapp_message_template, $referral_code, $referral_link);
        $facebook_message = sprintf($facebook_message_template, $referral_code);
        $email_body = sprintf($email_body_template, $referral_code, $referral_link);
        
        ob_start();
        ?>
        <div class="intersoccer-customer-dashboard">
            <!-- Referral Code & Link Section -->
            <div class="referral-section">
                <h3><?php echo esc_html($share_earn_title); ?></h3>
                <div class="referral-info">
                    <p class="referral-description">
                        <span class="highlight"><?php echo esc_html($earn_text); ?></span> <?php echo esc_html($for_every_friend); ?> 
                        <?php echo esc_html($share_description); ?>
                    </p>
                </div>
                
                <!-- Referral Code Display -->
                <div class="referral-code-container" style="margin-bottom: 20px;">
                    <label for="referral-code" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                        <?php echo esc_html($your_referral_code_label); ?>
                    </label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="text" 
                               id="referral-code" 
                               value="<?php echo esc_attr($referral_code); ?>" 
                               readonly 
                               style="flex: 1; padding: 14px; border: 2px solid #667eea; border-radius: 8px; font-size: 18px; font-weight: bold; text-align: center; letter-spacing: 2px; font-family: monospace; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                        <button id="copy-code-btn" class="copy-button" onclick="copyReferralCode()" style="padding: 14px 24px; font-size: 16px;">
                            <span class="button-text">📋 <?php echo esc_html($copy_code_text); ?></span>
                            <span class="button-success">✅ <?php echo esc_html($copied_text); ?></span>
                        </button>
                    </div>
                </div>
                
                <!-- Referral Link Display -->
                <div class="referral-link-container">
                    <label for="referral-link" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                        <?php echo esc_html($your_referral_link_label); ?>
                    </label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" 
                               id="referral-link" 
                               value="<?php echo esc_attr($referral_link); ?>" 
                               readonly
                               style="flex: 1; padding: 12px; border: 2px solid #e1e5e9; border-radius: 6px; font-family: monospace; background: #f8f9fa; font-size: 14px;">
                        <button id="copy-link-btn" class="copy-button" onclick="copyReferralLink()" style="padding: 12px 20px;">
                            <span class="button-text">📋 <?php echo esc_html($copy_link_text); ?></span>
                            <span class="button-success">✅ <?php echo esc_html($copied_text); ?></span>
                        </button>
                    </div>
                </div>
                
                <div class="social-share-buttons" style="margin-top: 20px;">
                    <a href="https://wa.me/?text=<?php echo urlencode($whatsapp_message); ?>" 
                       target="_blank" class="social-btn whatsapp-btn">
                        📱 <?php echo esc_html($whatsapp_label); ?>
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($referral_link); ?>&quote=<?php echo urlencode($facebook_message); ?>" 
                       target="_blank" class="social-btn facebook-btn">
                        📘 <?php echo esc_html($facebook_label); ?>
                    </a>
                    <a href="mailto:?subject=<?php echo urlencode($email_subject); ?>&body=<?php echo urlencode($email_body); ?>" 
                       class="social-btn email-btn">
                        📧 <?php echo esc_html($email_label); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Enhanced CSS -->
        <style>
        .intersoccer-customer-dashboard {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        /* Referral Section */
        .referral-section {
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
        
        /* Responsive */
        @media (max-width: 768px) {
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
        }
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