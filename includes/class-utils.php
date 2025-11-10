<?php

/**
 * Additional utility functions for the customer credit system
 */

/**
 * Validate batch processing parameters
 */
function intersoccer_validate_batch_params($batch_size, $scan_limit) {
    $errors = [];
    
    if ($batch_size < 5 || $batch_size > 100) {
        $errors[] = 'Batch size must be between 5 and 100';
    }
    
    if ($scan_limit != -1 && ($scan_limit < 10 || $scan_limit > 10000)) {
        $errors[] = 'Scan limit must be between 10 and 10000 (or -1 for all)';
    }
    
    $max_execution_time = ini_get('max_execution_time');
    if ($max_execution_time > 0 && $max_execution_time < 30 && $batch_size > 15) {
        $errors[] = 'Batch size too large for server execution time limit. Reduce to 15 or lower.';
    }
    
    $memory_limit = ini_get('memory_limit');
    if ($memory_limit && $memory_limit !== '-1') {
        $memory_mb = intval($memory_limit);
        if ($memory_mb < 128 && $batch_size > 20) {
            $errors[] = 'Batch size too large for server memory limit. Reduce to 20 or lower.';
        }
    }
    
    return $errors;
}

/**
 * Get recommended batch configuration based on server capabilities
 */
function intersoccer_get_recommended_batch_config() {
    $max_execution_time = ini_get('max_execution_time');
    $memory_limit = ini_get('memory_limit');
    $memory_mb = $memory_limit ? intval($memory_limit) : 512;
    
    // Conservative defaults
    $recommended = [
        'batch_size' => 25,
        'scan_limit' => 1000,
        'timeout' => 90000, // 90 seconds
        'max_retries' => 3
    ];
    
    // Adjust based on server capabilities
    if ($max_execution_time > 0 && $max_execution_time < 30) {
        $recommended['batch_size'] = 15;
        $recommended['timeout'] = 60000;
    } elseif ($max_execution_time >= 60) {
        $recommended['batch_size'] = 35;
        $recommended['timeout'] = 120000;
    }
    
    if ($memory_mb < 128) {
        $recommended['batch_size'] = min($recommended['batch_size'], 15);
    } elseif ($memory_mb >= 512) {
        $recommended['batch_size'] = min($recommended['batch_size'] + 10, 50);
    }
    
    return $recommended;
}

/**
 * Credit calculation business rules and validation
 */
class InterSoccer_Credit_Calculator {
    
    private static $business_rules = [
        'max_credits_per_customer' => 500,
        'min_order_amount_for_credits' => 50,
        'base_credit_rates' => [
            'first_purchase' => 0.03,   // 3%
            'second_purchase' => 0.05,  // 5%
            'subsequent' => 0.08        // 8%
        ],
        'retention_bonuses' => [
            'multi_season' => 25,
            'long_term' => 50
        ],
        'surprise_bonus_range' => [15, 35],
        'min_orders_for_surprise' => 3,
        'min_spent_for_surprise' => 500
    ];
    
    /**
     * Calculate credits with full business logic validation
     */
    private function calculate_customer_credits($orders, $customer_id) {
        $credits_breakdown = [
            'base_credits' => 0,
            'loyalty_percentage' => 0,
            'retention_bonus' => 0,
            'surprise_bonus' => 0,
            'total_credits' => 0,
            'calculation_details' => []
        ];
        
        if (empty($orders)) {
            return $credits_breakdown;
        }
        
        $total_spent = 0;
        $valid_orders = [];
        $seasons = [];
        
        // Filter and analyze orders
        foreach ($orders as $order) {
            $order_total = $order->get_total() - $order->get_total_tax();
            
            // Only count orders above 30 CHF (meaningful purchases)
            if ($order_total >= 30) {
                $valid_orders[] = $order;
                $total_spent += $order_total;
                
                // Track seasons
                $order_date = $order->get_date_created();
                if ($order_date) {
                    $month = $order_date->format('n');
                    $year = $order_date->format('Y');
                    $season_key = ($month >= 9) ? "{$year}-" . ($year + 1) : ($year - 1) . "-{$year}";
                    $seasons[$season_key] = true;
                }
            }
        }
        
        if (empty($valid_orders)) {
            $credits_breakdown['calculation_details'][] = 'No qualifying orders (minimum 30 CHF)';
            return $credits_breakdown;
        }
        
        $order_count = count($valid_orders);
        $season_count = count($seasons);
        
        $credits_breakdown['calculation_details'][] = "Qualifying orders: {$order_count} (total: " . round($total_spent, 2) . " CHF)";
        $credits_breakdown['calculation_details'][] = "Seasons active: {$season_count}";
        
        // MUCH MORE REASONABLE CREDIT RATES
        
        // 1. Base welcome credit (one-time only)
        if ($order_count >= 1) {
            $credits_breakdown['base_credits'] = 20; // Fixed 20 CHF welcome credit
            $credits_breakdown['calculation_details'][] = "Welcome credit: 20 CHF";
        }
        
        // 2. Loyalty percentage (very modest)
        if ($total_spent >= 100) {
            // Progressive rates but much lower
            if ($total_spent >= 1000) {
                $loyalty_rate = 0.015; // 1.5% for high spenders
            } elseif ($total_spent >= 500) {
                $loyalty_rate = 0.01;  // 1% for medium spenders
            } else {
                $loyalty_rate = 0.005; // 0.5% for regular customers
            }
            
            $credits_breakdown['loyalty_percentage'] = $total_spent * $loyalty_rate;
            $credits_breakdown['calculation_details'][] = "Loyalty bonus (" . ($loyalty_rate * 100) . "%): " . round($credits_breakdown['loyalty_percentage'], 2) . " CHF";
        }
        
        // 3. Multi-season retention bonus (modest)
        if ($season_count >= 2) {
            $credits_breakdown['retention_bonus'] = 15; // 15 CHF for returning customers
            $credits_breakdown['calculation_details'][] = "Multi-season bonus: 15 CHF";
        }
        if ($season_count >= 3) {
            $credits_breakdown['retention_bonus'] += 10; // Additional 10 CHF for long-term customers
            $credits_breakdown['calculation_details'][] = "Long-term customer bonus: +10 CHF";
        }
        
        // 4. High-value customer surprise bonus (rare)
        if ($order_count >= 5 && $total_spent >= 1500) {
            $credits_breakdown['surprise_bonus'] = mt_rand(10, 20);
            $credits_breakdown['calculation_details'][] = "VIP surprise bonus: " . $credits_breakdown['surprise_bonus'] . " CHF";
        }
        
        // Calculate total
        $raw_total = $credits_breakdown['base_credits'] + 
                    $credits_breakdown['loyalty_percentage'] + 
                    $credits_breakdown['retention_bonus'] + 
                    $credits_breakdown['surprise_bonus'];
        
        // Apply reasonable maximum (much lower)
        $max_credits = min(150, $total_spent * 0.10); // Maximum 150 CHF or 10% of total spent, whichever is lower
        $credits_breakdown['total_credits'] = min($raw_total, $max_credits);
        
        if ($raw_total > $max_credits) {
            $credits_breakdown['calculation_details'][] = "Credits capped at " . round($max_credits, 2) . " CHF (10% of spending or 150 CHF max)";
        }
        
        $credits_breakdown['calculation_details'][] = "Final credits: " . round($credits_breakdown['total_credits'], 2) . " CHF";
        
        return $credits_breakdown;
    }
    
    /**
     * Determine season from order date
     */
    private static function determine_season_from_date($date) {
        $month = $date->format('n');
        $year = $date->format('Y');
        
        // Soccer seasons typically run Sep-Jun
        if ($month >= 9) {
            return $year . '-' . ($year + 1);
        } else {
            return ($year - 1) . '-' . $year;
        }
    }
    
    /**
     * Validate credit calculation results
     */
    public static function validate_credit_calculation($credits, $customer_id) {
        $validation = ['valid' => true, 'warnings' => [], 'errors' => []];
        
        // Check for reasonable values
        if ($credits['total_credits'] < 0) {
            $validation['errors'][] = 'Negative total credits calculated';
            $validation['valid'] = false;
        }
        
        if ($credits['total_credits'] > self::$business_rules['max_credits_per_customer']) {
            $validation['errors'][] = 'Total credits exceed maximum allowed';
            $validation['valid'] = false;
        }
        
        // Check for suspicious patterns
        if ($credits['loyalty_bonus'] > ($credits['total_credits'] * 0.8)) {
            $validation['warnings'][] = 'Loyalty bonus unusually high compared to total';
        }
        
        if ($credits['surprise_bonus'] > 0 && ($credits['base_credits'] + $credits['loyalty_bonus']) == 0) {
            $validation['warnings'][] = 'Surprise bonus without other credits';
        }
        
        return $validation;
    }
    
    /**
     * Get business rules for frontend display
     */
    public static function get_business_rules_summary() {
        return [
            'max_credits' => self::$business_rules['max_credits_per_customer'],
            'min_order_amount' => self::$business_rules['min_order_amount_for_credits'],
            'credit_rates' => [
                'First purchase: ' . (self::$business_rules['base_credit_rates']['first_purchase'] * 100) . '% + CHF 50 bonus',
                'Second purchase: ' . (self::$business_rules['base_credit_rates']['second_purchase'] * 100) . '%',
                'Subsequent purchases: ' . (self::$business_rules['base_credit_rates']['subsequent'] * 100) . '%'
            ],
            'retention_bonuses' => [
                'Multi-season customer: CHF ' . self::$business_rules['retention_bonuses']['multi_season'],
                'Long-term customer (3+ seasons): CHF ' . self::$business_rules['retention_bonuses']['long_term']
            ],
            'surprise_bonus' => 'CHF ' . self::$business_rules['surprise_bonus_range'][0] . '-' . self::$business_rules['surprise_bonus_range'][1] . ' for loyal customers'
        ];
    }
}

/**
 * Enhanced error handling and logging
 */
class InterSoccer_Import_Logger {
    
    private static $log_levels = [
        'ERROR' => 4,
        'WARNING' => 3,
        'SUCCESS' => 2,
        'INFO' => 1
    ];
    
    private static $log_file = null;
    
    /**
     * Initialize logging
     */
    public static function init() {
        if (!self::$log_file) {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/intersoccer-logs';
            
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
            }
            
            self::$log_file = $log_dir . '/customer-import-' . date('Y-m-d') . '.log';
        }
    }
    
    /**
     * Log message with level and context
     */
    public static function log($message, $level = 'INFO', $context = []) {
        self::init();
        
        $timestamp = current_time('Y-m-d H:i:s');
        $context_str = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $log_entry = "[{$timestamp}] {$level}: {$message}{$context_str}" . PHP_EOL;
        
        // Write to file
        error_log($log_entry, 3, self::$log_file);
        
        // Also log to WordPress error log for critical errors
        if ($level === 'ERROR') {
            error_log("InterSoccer Import Error: {$message}");
        }
    }
    
    /**
     * Log batch processing results
     */
    public static function log_batch_results($batch_index, $results) {
        $summary = [
            'batch' => $batch_index,
            'total' => count($results),
            'success' => count(array_filter($results, function($r) { return $r['status'] === 'success'; })),
            'errors' => count(array_filter($results, function($r) { return $r['status'] === 'error'; })),
            'skipped' => count(array_filter($results, function($r) { return $r['status'] === 'skipped'; })),
            'credits_assigned' => array_sum(array_column($results, 'credits_assigned'))
        ];
        
        self::log("Batch {$batch_index} completed", 'INFO', $summary);
        
        // Log individual errors for debugging
        $errors = array_filter($results, function($r) { return $r['status'] === 'error'; });
        foreach ($errors as $error) {
            self::log("Customer {$error['customer_id']} error: {$error['message']}", 'ERROR', [
                'customer_id' => $error['customer_id'],
                'customer_name' => $error['customer_name'] ?? 'Unknown'
            ]);
        }
    }
    
    /**
     * Get log file path for download
     */
    public static function get_log_file_path() {
        self::init();
        return file_exists(self::$log_file) ? self::$log_file : null;
    }
    
    /**
     * Clean up old log files
     */
    public static function cleanup_old_logs($days = 30) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/intersoccer-logs';
        
        if (!file_exists($log_dir)) {
            return;
        }
        
        $files = glob($log_dir . '/customer-import-*.log');
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
}

/**
 * Schedule log cleanup
 */
if (!wp_next_scheduled('intersoccer_cleanup_import_logs')) {
    wp_schedule_event(time(), 'weekly', 'intersoccer_cleanup_import_logs');
}

add_action('intersoccer_cleanup_import_logs', function() {
    InterSoccer_Import_Logger::cleanup_old_logs();
});

/**
 * AJAX handler for downloading import logs
 */
add_action('wp_ajax_download_import_log', function() {
    check_ajax_referer('intersoccer_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $log_file = InterSoccer_Import_Logger::get_log_file_path();
    
    if (!$log_file || !file_exists($log_file)) {
        wp_die('Log file not found');
    }
    
    $filename = 'intersoccer-import-log-' . date('Y-m-d') . '.txt';
    
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($log_file));
    
    readfile($log_file);
    exit;
});

/**
 * Database optimization for customer credit queries
 */
class InterSoccer_Database_Optimizer {
    
    /**
     * Create indexes to speed up customer credit queries
     */
    public static function create_indexes() {
        global $wpdb;
        
        // Index for faster customer credit lookups
        $wpdb->query("
            CREATE INDEX IF NOT EXISTS idx_intersoccer_customer_credits 
            ON {$wpdb->usermeta} (meta_key, user_id) 
            WHERE meta_key = 'intersoccer_customer_credits'
        ");
        
        // Index for faster order queries by customer
        $wpdb->query("
            CREATE INDEX IF NOT EXISTS idx_orders_by_customer 
            ON {$wpdb->posts} (post_author, post_type, post_status, post_date)
            WHERE post_type = 'shop_order'
        ");
        
        error_log('InterSoccer: Database indexes created for customer credit optimization');
    }
    
    /**
     * Analyze query performance
     */
    public static function analyze_query_performance() {
        global $wpdb;
        
        $start_time = microtime(true);
        
        // Test customer lookup query
        $wpdb->get_results("
            SELECT COUNT(*) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'intersoccer_customer_credits' 
            AND meta_value > 0
        ");
        
        $customer_query_time = microtime(true) - $start_time;
        
        $start_time = microtime(true);
        
        // Test order lookup query
        $wpdb->get_results("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'shop_order' 
            AND post_status IN ('wc-completed', 'wc-processing')
            LIMIT 1000
        ");
        
        $order_query_time = microtime(true) - $start_time;
        
        return [
            'customer_query_time' => round($customer_query_time * 1000, 2) . 'ms',
            'order_query_time' => round($order_query_time * 1000, 2) . 'ms',
            'performance_rating' => ($customer_query_time + $order_query_time) < 0.1 ? 'Good' : 'Bad',
                                   ($customer_query_time + $order_query_time) < 0.5 ? 'Moderate' : 'Poor'
        ];
    }
}

if (!function_exists('intersoccer_referral_get_dashboard_i18n')) {
    /**
     * Retrieve i18n strings for the modern coach dashboard interfaces.
     *
     * @return array
     */
    function intersoccer_referral_get_dashboard_i18n() {
        return [
            'no_events_title' => __('No events added yet', 'intersoccer-referral'),
            'no_events_description' => __('Add the events you coach so we can generate direct referral links for customers.', 'intersoccer-referral'),
            'copy' => __('Copy', 'intersoccer-referral'),
            'open' => __('Open', 'intersoccer-referral'),
            'remove' => __('Remove', 'intersoccer-referral'),
            'search_prompt' => __('Please enter at least two characters to search.', 'intersoccer-referral'),
            'searching' => __('Searching...', 'intersoccer-referral'),
            'no_results' => __('No events found.', 'intersoccer-referral'),
            'search_failed' => __('Search failed. Please try again.', 'intersoccer-referral'),
            'event_selected' => __('Event selected. Click "Request Event" to submit.', 'intersoccer-referral'),
            'select_event_first' => __('Please select an event before requesting.', 'intersoccer-referral'),
            'request_success' => __('Event request submitted. Awaiting approval.', 'intersoccer-referral'),
            'request_error' => __('Unable to request event.', 'intersoccer-referral'),
            'network_error' => __('Network error. Please try again.', 'intersoccer-referral'),
            'event_link_copied' => __('Event link copied to clipboard!', 'intersoccer-referral'),
            'remove_confirm' => __('Remove this event from your list?', 'intersoccer-referral'),
            'remove_success' => __('Event removed.', 'intersoccer-referral'),
            'remove_error' => __('Unable to remove event.', 'intersoccer-referral'),
            'refresh_error' => __('Unable to refresh events.', 'intersoccer-referral'),
            'referral_link_copied' => __('Referral link copied to clipboard!', 'intersoccer-referral'),
            'event_result_meta_pattern' => __('ID: %1$s • %2$s', 'intersoccer-referral'),
            'social_modal_title' => __('Create Social Media Post', 'intersoccer-referral'),
            'social_instagram_title' => __('Instagram Post', 'intersoccer-referral'),
            'social_instagram_body' => __('Transform your game with personalized soccer training! Join me at InterSoccer - link in bio!', 'intersoccer-referral'),
            'social_facebook_title' => __('Facebook Post', 'intersoccer-referral'),
            'social_facebook_body' => __('Looking to improve your soccer skills? I\'m now partnering with InterSoccer to offer personalized training programs. Click here to get started: %s', 'intersoccer-referral'),
            'social_twitter_title' => __('Twitter Post', 'intersoccer-referral'),
            'social_twitter_body' => __('Level up your soccer game! Join InterSoccer for personalized training. Link: %s #SoccerTraining #InterSoccer', 'intersoccer-referral'),
            'social_copy_post' => __('Copy Post', 'intersoccer-referral'),
            'social_share_now' => __('Share Now', 'intersoccer-referral'),
            'social_copy_success' => __('Post copied to clipboard!', 'intersoccer-referral'),
            'social_share_copy_success' => __('Post copied! Share it on your favorite social platform.', 'intersoccer-referral'),
            'email_modal_title' => __('Send Referral Email', 'intersoccer-referral'),
            'email_recipient_label' => __('Recipient Email', 'intersoccer-referral'),
            'email_recipient_placeholder' => __('friend@example.com', 'intersoccer-referral'),
            'email_subject_label' => __('Subject', 'intersoccer-referral'),
            'email_subject_default' => __('Join me at InterSoccer!', 'intersoccer-referral'),
            'email_message_label' => __('Message', 'intersoccer-referral'),
            'email_message_default' => __("Hi there!\n\nI thought you'd be interested in InterSoccer's personalized soccer training programs. I've been really enjoying the coaching and wanted to share this opportunity with you.\n\nClick here to check it out: %1$s\n\nBest regards,\n%2$s", 'intersoccer-referral'),
            'email_send_button' => __('Send Email', 'intersoccer-referral'),
            'cancel_button' => __('Cancel', 'intersoccer-referral'),
            'email_sending' => __('Sending...', 'intersoccer-referral'),
            'email_sent_success' => __('Email sent successfully!', 'intersoccer-referral'),
            'email_send_failed' => __('Failed to send email', 'intersoccer-referral'),
            'email_send_retry' => __('Failed to send email. Please try again.', 'intersoccer-referral'),
            'support_modal_title' => __('Contact Support', 'intersoccer-referral'),
            'support_live_chat_title' => __('Live Chat', 'intersoccer-referral'),
            'support_live_chat_description' => __('Get instant help from our support team', 'intersoccer-referral'),
            'support_live_chat_action' => __('Start Chat', 'intersoccer-referral'),
            'support_email_title' => __('Email Support', 'intersoccer-referral'),
            'support_email_description' => __('Send us a detailed message', 'intersoccer-referral'),
            'support_email_action' => __('Send Email', 'intersoccer-referral'),
            'support_faq_title' => __('FAQ', 'intersoccer-referral'),
            'support_faq_description' => __('Browse our knowledge base', 'intersoccer-referral'),
            'support_faq_action' => __('View FAQ', 'intersoccer-referral'),
            'modal_close_label' => __('Close modal', 'intersoccer-referral'),
            'chart_referrals_label' => __('Referrals', 'intersoccer-referral'),
            'chart_credits_label' => __('Credits Earned', 'intersoccer-referral'),
            'leaderboard_modal_title' => __('Full Leaderboard', 'intersoccer-referral'),
            'leaderboard_loading' => __('Loading leaderboard...', 'intersoccer-referral'),
            'leaderboard_you_badge' => __('You', 'intersoccer-referral'),
            'leaderboard_stats_pattern' => __('%1$s referrals • %2$s CHF', 'intersoccer-referral'),
        ];
    }
}