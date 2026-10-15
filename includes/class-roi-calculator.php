<?php
/**
 * ROI Calculator for InterSoccer Loyalty Program
 * 
 * Implements the approved ROI spec (v4):
 * - Rolling 12-month window using WordPress timezone consistently
 * - Ex-VAT revenue: line totals after discounts, net of refunds, excluding shipping/fees/tax
 * - Points counted as CHF 1 cost WHEN EARNED (not when redeemed)
 * - Adjustments split: goodwill/bonus (cost) vs correction (not cost)
 * - HPOS-compatible via wc_get_orders()
 * - Unredeemed points as liability with age breakdown
 * - Expiry/reversal reduce LIABILITY, not cost
 */

if (!class_exists('InterSoccer_ROI_Calculator')) {

class InterSoccer_ROI_Calculator {
    
    private $wpdb;
    private $table_prefix;
    private $period_months = 12;
    
    /** @var array Cached adjustment classification */
    private $adjustment_classification_cache = null;
    
    /** @var int Transient cache TTL in seconds (1 hour) */
    const CACHE_TTL = 3600;
    
    /** @var int Batch size for order processing */
    const BATCH_SIZE = 100;
    
    /** @var string Transient key prefix */
    const CACHE_PREFIX = 'intersoccer_roi_';
    
    /**
     * Incrementality range bounds for ROI calculation (Finance assumption).
     * 
     * Some referred/loyalty bookings would have happened anyway. The true incremental
     * value is somewhere between these bounds. Programs have different profiles:
     * 
     * REFERRAL: New families acquired via coach/customer referral codes
     * - LOW (25%): Conservative — many referred families would have found us anyway
     * - HIGH (75%): Moderate — referrals do bring in families who wouldn't have come
     * 
     * LOYALTY: Extra spend from points holders vs non-holders
     * - LOW (5%): Conservative — most of the spend lift is correlation, not causation
     * - HIGH (25%): Moderate — some extra spend is genuinely driven by points
     * 
     * These assumptions will be replaced once we have a baseline (repeat rate before
     * the program, or matched families who don't hold points).
     * 
     * @see get_roi_range_display()
     * @see calculate_referral_roi_with_range()
     * @see calculate_loyalty_roi_with_range()
     */
    const REFERRAL_INCREMENTALITY_LOW = 0.25;
    const REFERRAL_INCREMENTALITY_HIGH = 0.75;
    const LOYALTY_INCREMENTALITY_LOW = 0.05;
    const LOYALTY_INCREMENTALITY_HIGH = 0.25;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix;
    }
    
    /**
     * Get cached value or compute and cache it
     * 
     * @param string $key Cache key suffix
     * @param callable $compute Function to compute value if not cached
     * @param bool $force_refresh Skip cache and recompute
     * @return mixed Cached or computed value
     */
    private function get_cached($key, $compute, $force_refresh = false) {
        $cache_key = self::CACHE_PREFIX . $key;
        
        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        $value = $compute();
        set_transient($cache_key, $value, self::CACHE_TTL);
        return $value;
    }
    
    /**
     * Clear all ROI calculator caches
     * Called by manual refresh button or when settings change
     */
    public function clear_cache() {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . self::CACHE_PREFIX . '%'
        ));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_' . self::CACHE_PREFIX . '%'
        ));
        
        $this->adjustment_classification_cache = null;
        
        return true;
    }
    
    /**
     * Check if cache exists and get its age
     * 
     * @return array ['cached' => bool, 'age_minutes' => int|null]
     */
    public function get_cache_status() {
        $range = $this->get_period_range();
        $cache_key = '_transient_timeout_' . self::CACHE_PREFIX . 'revenue_' . md5($range['start'] . $range['end']);
        
        $timeout = get_option($cache_key);
        if ($timeout === false) {
            return ['cached' => false, 'age_minutes' => null];
        }
        
        $age_seconds = (self::CACHE_TTL) - ($timeout - time());
        return [
            'cached' => true,
            'age_minutes' => max(0, (int) floor($age_seconds / 60)),
        ];
    }
    
    /**
     * Get admin-editable ROI settings with defaults
     * Uses single option name: intersoccer_roi_camp_margin_percent
     */
    public function get_roi_settings() {
        return [
            'camp_margin_percent' => (float) get_option('intersoccer_roi_camp_margin_percent', 0),
            'ad_spend_chf' => (float) get_option('intersoccer_roi_ad_spend', 0),
            'ad_attributed_families' => (int) get_option('intersoccer_roi_ad_families', 0),
            'staff_time_monthly_chf' => (float) get_option('intersoccer_roi_staff_time_monthly', 0),
            'platform_fees_monthly_chf' => (float) get_option('intersoccer_roi_platform_fees_monthly', 0),
        ];
    }
    
    /**
     * Check if camp margin is set (required for ROI display)
     */
    public function has_camp_margin() {
        $settings = $this->get_roi_settings();
        return $settings['camp_margin_percent'] > 0;
    }
    
    /**
     * Check if ad spend data is available
     */
    public function has_ad_data() {
        $settings = $this->get_roi_settings();
        return $settings['ad_spend_chf'] > 0 && $settings['ad_attributed_families'] > 0;
    }
    
    /**
     * Get the rolling 12-month date range using WordPress timezone consistently
     * FIX #1: Use WordPress time for both ends
     */
    public function get_period_range() {
        $end_date = wp_date('Y-m-d');
        $start_date = wp_date('Y-m-d', strtotime("-{$this->period_months} months"));
        return ['start' => $start_date, 'end' => $end_date];
    }
    
    /**
     * Classify adjustments into goodwill (cost) vs correction (not cost)
     * FIX #4: Single classifier used everywhere
     * 
     * Goodwill/Bonus (COUNTS as loyalty cost):
     * - Descriptions with: goodwill, bonus, survey, credit, reward, compensation, apolog, gesture, exceptional
     * 
     * Correction (NOT cost):
     * - Descriptions with: cancel, error, incorrect, fix, return, refund, mistake, wrong, changed
     * - Transaction type: admin_reset
     * 
     * Unknown: treat as goodwill (conservative) and flag
     */
    public function classify_adjustments($range = null) {
        if ($range === null) {
            $range = $this->get_period_range();
        }
        
        $cache_key = $range['start'] . '_' . $range['end'];
        if ($this->adjustment_classification_cache !== null && 
            isset($this->adjustment_classification_cache['key']) && 
            $this->adjustment_classification_cache['key'] === $cache_key) {
            return $this->adjustment_classification_cache;
        }
        
        $points_log = $this->table_prefix . 'intersoccer_points_log';
        
        $adjustments = $this->wpdb->get_results($this->wpdb->prepare("
            SELECT points_amount, description, transaction_type
            FROM {$points_log}
            WHERE points_amount > 0
              AND transaction_type IN ('admin_adjustment', 'manual_adjustment', 'admin_balance_set', 'points_refund', 'admin_reset')
              AND created_at BETWEEN %s AND %s
        ", $range['start'], $range['end'] . ' 23:59:59'));
        
        $goodwill_points = 0;
        $correction_points = 0;
        $unclassified_count = 0;
        
        // Correction keywords: fix errors, don't create new value
        // Note: 'refund' type text is a correction (reversing a mistake), not goodwill
        $correction_keywords = ['cancel', 'error', 'incorrect', 'fix', 'return', 'refund', 'mistake', 'wrong', 'changed', 'test', 'testing', 'example', 'e2e', 'classic test'];
        
        // Goodwill keywords: intentional value given to customer
        // Note: 'credit' removed - too generic; 'refund credit' should match correction first
        $goodwill_keywords = ['goodwill', 'bonus', 'survey', 'reward', 'compensation', 'apolog', 'gesture', 'exceptional', 'not mature', 'mature enough', 'referral', 'referal', 'informed us'];
        
        foreach ($adjustments as $adj) {
            // Handle both object and array formats (arrays may come from test mocks)
            if (is_array($adj)) {
                $desc = strtolower($adj['description'] ?? '');
                $amount = (float) ($adj['points_amount'] ?? 0);
                $type = $adj['transaction_type'] ?? '';
            } else {
                $desc = strtolower($adj->description ?? '');
                $amount = (float) ($adj->points_amount ?? 0);
                $type = $adj->transaction_type ?? '';
            }
            
            // admin_reset and admin_balance_set are always corrections
            if ($type === 'admin_reset' || $type === 'admin_balance_set') {
                $correction_points += $amount;
                continue;
            }
            
            $is_correction = false;
            $is_goodwill = false;
            
            // Check correction keywords FIRST (e.g. 'refund credit' should be correction)
            foreach ($correction_keywords as $kw) {
                if (strpos($desc, $kw) !== false) {
                    $is_correction = true;
                    break;
                }
            }
            
            // Then check goodwill keywords (only if not already correction)
            if (!$is_correction) {
                foreach ($goodwill_keywords as $kw) {
                    if (strpos($desc, $kw) !== false) {
                        $is_goodwill = true;
                        break;
                    }
                }
            }
            
            if ($is_correction) {
                $correction_points += $amount;
            } elseif ($is_goodwill) {
                $goodwill_points += $amount;
            } else {
                // Unclassified defaults to correction (conservative - don't overcount cost)
                $correction_points += $amount;
                if (!empty($desc)) {
                    $unclassified_count++;
                }
            }
        }
        
        $this->adjustment_classification_cache = [
            'key' => $cache_key,
            'goodwill_chf' => $goodwill_points,
            'correction_chf' => $correction_points,
            'unclassified_count' => $unclassified_count,
            'has_goodwill' => $goodwill_points > 0,
            'has_corrections' => $correction_points > 0,
        ];
        
        return $this->adjustment_classification_cache;
    }
    
    /**
     * Get revenue ex-VAT using aggregate SQL query against HPOS tables
     * FIX #2 & #13: Line subtotals net of partial refunds, excluding shipping/fees/tax
     * 
     * Memory-optimized: uses aggregate query instead of loading full order objects
     */
    public function get_revenue_ex_vat($range = null, $force_refresh = false) {
        if ($range === null) {
            $range = $this->get_period_range();
        }
        
        $cache_key = 'revenue_' . md5($range['start'] . $range['end']);
        
        return $this->get_cached($cache_key, function() use ($range) {
            return $this->compute_revenue_ex_vat($range);
        }, $force_refresh);
    }
    
    /**
     * Check if HPOS is the active/authoritative data store
     */
    private function is_hpos_active() {
        // Use WooCommerce's own detection if available
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
            return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
        
        // Fallback: check option (HPOS setting)
        $hpos_enabled = get_option('woocommerce_custom_orders_table_enabled') === 'yes';
        if (!$hpos_enabled) {
            return false;
        }
        
        // Also verify the table has data (in case HPOS is enabled but not synced)
        $orders_table = $this->wpdb->prefix . 'wc_orders';
        $has_data = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$orders_table} WHERE type = 'shop_order' LIMIT 1") > 0;
        
        return $has_data;
    }
    
    /**
     * Compute revenue using aggregate SQL (memory-efficient)
     * 
     * Revenue = product line totals after coupons (ex-VAT, net of refunds)
     * Points are applied as a negative fee (Referral Credits Discount), so line totals
     * already exclude them - no add-back needed.
     */
    private function compute_revenue_ex_vat($range) {
        // Use WooCommerce HPOS tables for aggregate query
        $orders_table = $this->wpdb->prefix . 'wc_orders';
        $items_table = $this->wpdb->prefix . 'woocommerce_order_items';
        $itemmeta_table = $this->wpdb->prefix . 'woocommerce_order_itemmeta';
        
        // Check if HPOS is active and has data
        $hpos_enabled = $this->is_hpos_active();
        
        if ($hpos_enabled) {
            // HPOS aggregate query: sum _line_total from order items for completed/processing orders
            // _line_total = after line discounts (coupons reduce revenue, points are a fee not a line discount)
            // Convert local dates to GMT for HPOS date_created_gmt column comparison
            $start_gmt = get_gmt_from_date($range['start'] . ' 00:00:00', 'Y-m-d H:i:s');
            $end_gmt = get_gmt_from_date($range['end'] . ' 23:59:59', 'Y-m-d H:i:s');
            
            $total = (float) $this->wpdb->get_var($this->wpdb->prepare("
                SELECT COALESCE(SUM(im.meta_value), 0)
                FROM {$orders_table} o
                INNER JOIN {$items_table} oi ON o.id = oi.order_id AND oi.order_item_type = 'line_item'
                INNER JOIN {$itemmeta_table} im ON oi.order_item_id = im.order_item_id AND im.meta_key = '_line_total'
                WHERE o.type = 'shop_order'
                  AND o.status IN ('wc-completed', 'wc-processing')
                  AND o.date_created_gmt >= %s
                  AND o.date_created_gmt <= %s
            ", $start_gmt, $end_gmt));
            
            // Subtract refunded amounts (refund totals are negative in itemmeta)
            $refunds = (float) $this->wpdb->get_var($this->wpdb->prepare("
                SELECT COALESCE(SUM(im.meta_value), 0)
                FROM {$orders_table} o
                INNER JOIN {$orders_table} r ON r.parent_order_id = o.id AND r.type = 'shop_order_refund'
                INNER JOIN {$items_table} oi ON r.id = oi.order_id AND oi.order_item_type = 'line_item'
                INNER JOIN {$itemmeta_table} im ON oi.order_item_id = im.order_item_id AND im.meta_key = '_line_total'
                WHERE o.type = 'shop_order'
                  AND o.status IN ('wc-completed', 'wc-processing')
                  AND o.date_created_gmt >= %s
                  AND o.date_created_gmt <= %s
            ", $start_gmt, $end_gmt));
            
            return $total + $refunds; // refunds are negative, so adding subtracts
        }
        
        // Fallback: batched processing for non-HPOS installations
        return $this->compute_revenue_ex_vat_batched($range);
    }
    
    /**
     * Batched revenue calculation fallback for non-HPOS
     * 
     * Revenue = product line totals after coupons (ex-VAT, net of refunds)
     * Points are applied as a negative fee, so line totals already exclude them.
     */
    private function compute_revenue_ex_vat_batched($range) {
        $total = 0.0;
        $page = 1;
        
        do {
            $orders = wc_get_orders([
                'status' => ['completed', 'processing'],
                'date_created' => $range['start'] . '...' . $range['end'],
                'limit' => self::BATCH_SIZE,
                'page' => $page,
                'return' => 'ids',
            ]);
            
            foreach ($orders as $order_id) {
                $order = wc_get_order($order_id);
                if (!$order || $order->get_type() === 'shop_order_refund') continue;
                
                // Line totals AFTER discounts (coupons reduce revenue)
                foreach ($order->get_items() as $item) {
                    $total += (float) $item->get_total();
                }
                
                // Subtract refunded amounts
                if (method_exists($order, 'get_refunds')) {
                    foreach ($order->get_refunds() as $refund) {
                        foreach ($refund->get_items() as $refund_item) {
                            $total += (float) $refund_item->get_total();
                        }
                    }
                }
                
                // Free memory
                unset($order);
            }
            
            $page++;
        } while (count($orders) === self::BATCH_SIZE);
        
        return $total;
    }
    
    /**
     * Get order count using aggregate query
     */
    public function get_order_count($range = null, $force_refresh = false) {
        if ($range === null) {
            $range = $this->get_period_range();
        }
        
        $cache_key = 'order_count_' . md5($range['start'] . $range['end']);
        
        return $this->get_cached($cache_key, function() use ($range) {
            if ($this->is_hpos_active()) {
                $orders_table = $this->wpdb->prefix . 'wc_orders';
                return (int) $this->wpdb->get_var($this->wpdb->prepare("
                    SELECT COUNT(*)
                    FROM {$orders_table}
                    WHERE type = 'shop_order'
                      AND status IN ('wc-completed', 'wc-processing')
                      AND date_created_gmt >= %s
                      AND date_created_gmt < %s
                ", $range['start'] . ' 00:00:00', $range['end'] . ' 23:59:59'));
            }
            
            // Fallback for non-HPOS
            return count(wc_get_orders([
                'status' => ['completed', 'processing'],
                'date_created' => $range['start'] . '...' . $range['end'],
                'limit' => -1,
                'return' => 'ids',
            ]));
        }, $force_refresh);
    }
    
    /**
     * Check if a customer is a new family (no paid orders before the specified order)
     * FIX #10: Reusable for profit breakdown and leaderboard
     */
    public function is_new_family($customer_id, $referred_order_id) {
        $referred_order = wc_get_order($referred_order_id);
        if (!$referred_order) return true;
        
        $referred_date = $referred_order->get_date_created();
        if (!$referred_date) return true;
        
        $prior_orders = wc_get_orders([
            'customer_id' => $customer_id,
            'status' => ['completed', 'processing'],
            'date_created' => '<' . $referred_date->format('Y-m-d H:i:s'),
            'exclude' => [$referred_order_id],
            'limit' => 1,
            'return' => 'ids',
        ]);
        
        return empty($prior_orders);
    }
    
    /**
     * Get program cost breakdown
     * FIX #3, #4, #5: Unified cost calculation, no double-counting
     * 
     * Costs (CHF 1 per point when EARNED):
     * - Referral cost: commissions + referral points + first-order discount (net of refunds)
     * - Loyalty cost: purchase rewards + goodwill adjustments (excludes referral points)
     * 
     * NOT costs (shown separately):
     * - Corrections: fix errors, don't create new value
     */
    public function get_cost_breakdown() {
        $range = $this->get_period_range();
        $settings = $this->get_roi_settings();
        
        $points_log = $this->table_prefix . 'intersoccer_points_log';
        $commissions_table = $this->table_prefix . 'intersoccer_coach_commissions';
        
        // Referral-specific points (CHF 1 per point when earned)
        $referral_points = (float) $this->wpdb->get_var($this->wpdb->prepare("
            SELECT COALESCE(SUM(points_amount), 0)
            FROM {$points_log}
            WHERE points_amount > 0
              AND transaction_type IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')
              AND created_at BETWEEN %s AND %s
        ", $range['start'], $range['end'] . ' 23:59:59'));
        
        // Loyalty activity points (excludes referral types and ALL adjustments)
        $loyalty_activity_points = (float) $this->wpdb->get_var($this->wpdb->prepare("
            SELECT COALESCE(SUM(points_amount), 0)
            FROM {$points_log}
            WHERE points_amount > 0
              AND transaction_type NOT IN (
                  'customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward',
                  'admin_adjustment', 'manual_adjustment', 'admin_balance_set', 'points_refund', 'admin_reset',
                  'gift_sent', 'gift_received', 'gift_returned'
              )
              AND created_at BETWEEN %s AND %s
        ", $range['start'], $range['end'] . ' 23:59:59'));
        
        // Get classified adjustments (using shared classifier)
        $adj = $this->classify_adjustments($range);
        $goodwill_points = $adj['goodwill_chf'];
        $correction_points = $adj['correction_chf'];
        
        // FIX #5: Loyalty cost = activity points + goodwill (excludes referral points)
        $loyalty_cost = $loyalty_activity_points + $goodwill_points;
        
        // Total points earned (for card consistency) = referral + loyalty activity + goodwill
        // Excludes corrections as they don't create redeemable value
        $total_points_earned = $referral_points + $loyalty_activity_points + $goodwill_points;
        
        // Coach commissions
        $commissions = (float) $this->wpdb->get_var($this->wpdb->prepare("
            SELECT COALESCE(SUM(commission_amount), 0)
            FROM {$commissions_table}
            WHERE created_at BETWEEN %s AND %s
        ", $range['start'], $range['end'] . ' 23:59:59'));
        
        // First-order referral discount (ex-VAT, net of refunds)
        // Finance decision: Count as referral program cost; reversed if order is fully refunded
        $first_order_discount = $this->get_first_order_discount_cost($range);
        
        // Calculate costs
        $referral_cost = $commissions + $referral_points + $first_order_discount;
        $total_real_cost = $referral_cost + $loyalty_cost;
        
        // Revenue for % calculation
        $revenue = $this->get_revenue_ex_vat($range);
        $cost_percent_revenue = $revenue > 0 ? round(($total_real_cost / $revenue) * 100, 2) : 0;
        
        // Estimated costs from settings
        $months = $this->period_months;
        $ad_spend = $settings['ad_spend_chf'];
        $staff_time = $settings['staff_time_monthly_chf'] * $months;
        $platform_fees = $settings['platform_fees_monthly_chf'] * $months;
        
        return [
            'referral_points_chf' => $referral_points,
            'loyalty_activity_chf' => $loyalty_activity_points,
            'loyalty_points_chf' => $loyalty_activity_points, // Alias for dashboard compatibility
            'goodwill_points_chf' => $goodwill_points,
            'correction_points_chf' => $correction_points,
            'loyalty_cost_chf' => $loyalty_cost,
            'points_earned_chf' => $total_points_earned,
            'commissions_chf' => $commissions,
            'first_order_discount_chf' => $first_order_discount,
            'referral_cost_chf' => $referral_cost,
            'total_real_cost' => $total_real_cost,
            'revenue_ex_vat' => $revenue,
            'cost_percent_revenue' => $cost_percent_revenue,
            'ad_spend_chf' => $ad_spend,
            'staff_time_chf' => $staff_time,
            'platform_fees_chf' => $platform_fees,
            'total_estimated_chf' => $ad_spend + $staff_time + $platform_fees,
            'total_fully_loaded_chf' => $total_real_cost + $ad_spend + $staff_time + $platform_fees,
            'has_estimates' => ($ad_spend + $staff_time + $platform_fees) > 0,
            'has_goodwill' => $adj['has_goodwill'],
            'has_corrections' => $adj['has_corrections'],
            'has_adjustments' => $adj['has_goodwill'] || $adj['has_corrections'],
            'has_first_order_discount' => $first_order_discount > 0,
            'adjustment_points_chf' => $correction_points, // Non-cost adjustments for display
            'unclassified_adjustments' => $adj['unclassified_count'],
            'period_label' => __('Rolling 12 Months', 'intersoccer-referral'),
        ];
    }
    
    /**
     * Get first-order referral discount cost (ex-VAT, net of refunds)
     * 
     * Finance decision: The first-order referral discount is a REFERRAL PROGRAM COST.
     * 
     * Implementation:
     * - Reads the actual 'Referral Discount' fee line on the order (pre-tax total)
     * - Only counts orders with status completed or processing (same as revenue/bookings)
     * - Subtracts refunded fee amounts: if WooCommerce records per-fee refunds, uses those;
     *   otherwise reduces discount proportionally to total refund amount vs order total
     * - Revenue stays as product line totals (no subtraction for this fee - counted as cost only)
     * 
     * @param array $range Date range with 'start' and 'end' keys
     * @return float Total first-order discount cost in CHF (positive number)
     */
    public function get_first_order_discount_cost($range = null) {
        if ($range === null) {
            $range = $this->get_period_range();
        }
        
        // Always use batched processing to read actual fee lines
        // HPOS aggregate queries can't easily read fee line totals with refund handling
        return $this->get_first_order_discount_cost_batched($range);
    }
    
    /**
     * Calculate first-order discount cost from actual fee lines on orders
     * 
     * Identification strategy:
     * 1. Primary: order meta `_intersoccer_first_order_discount_applied` + `_intersoccer_first_order_discount_amount`
     * 2. Fallback for older orders: fee name matching (WPML-safe with EN/FR/DE labels)
     */
    private function get_first_order_discount_cost_batched($range) {
        $total = 0.0;
        $page = 1;
        
        do {
            $orders = wc_get_orders([
                'status' => ['completed', 'processing'],
                'date_created' => $range['start'] . '...' . $range['end'],
                'limit' => self::BATCH_SIZE,
                'page' => $page,
                'return' => 'ids',
            ]);
            
            foreach ($orders as $order_id) {
                $order = wc_get_order($order_id);
                if (!$order || $order->get_type() === 'shop_order_refund') continue;
                
                $fee_amount = $this->get_first_order_discount_from_order($order);
                
                if ($fee_amount <= 0) {
                    unset($order);
                    continue;
                }
                
                $refunded_fee = $this->get_refunded_first_order_discount($order, $fee_amount);
                $net_discount = $fee_amount - $refunded_fee;
                
                if ($net_discount > 0) {
                    $total += $net_discount;
                }
                
                unset($order);
            }
            
            $page++;
        } while (count($orders) === self::BATCH_SIZE);
        
        return $total;
    }
    
    /**
     * Get the first-order referral discount amount from an order
     * 
     * Identification priority:
     * 1. Fee item meta `_intersoccer_referral_discount_fee` (new orders)
     * 2. Order meta `_intersoccer_first_order_discount_amount` (recent orders)
     * 3. Fee name matching (older orders, WPML fallback)
     * 
     * @param WC_Order $order
     * @return float Discount amount (positive)
     */
    private function get_first_order_discount_from_order($order) {
        if (!method_exists($order, 'get_items')) {
            return 0.0;
        }
        
        // 1. Check fee item meta (most reliable, new orders)
        foreach ($order->get_items('fee') as $fee) {
            if (method_exists($fee, 'get_meta')) {
                $is_referral_fee = $fee->get_meta('_intersoccer_referral_discount_fee', true);
                if ($is_referral_fee) {
                    return abs((float) $fee->get_total());
                }
            }
        }
        
        // 2. Check order meta (recent orders before fee item meta was added)
        if (method_exists($order, 'get_meta')) {
            $applied = $order->get_meta('_intersoccer_first_order_discount_applied', true);
            if ($applied) {
                $amount = $order->get_meta('_intersoccer_first_order_discount_amount', true);
                if (is_numeric($amount) && (float) $amount > 0) {
                    return abs((float) $amount);
                }
            }
        }
        
        // 3. Fallback: scan fee lines by name (older orders)
        return $this->find_referral_discount_fee_by_name($order);
    }
    
    /**
     * Find the referral discount fee line by name (fallback for older orders)
     * 
     * Checks multiple translated labels: EN, FR (fr_CH.po), DE
     * 
     * @param WC_Order $order
     * @return float Fee amount (positive) or 0 if not found
     */
    private function find_referral_discount_fee_by_name($order) {
        $fee_labels = [
            __('Referral Discount', 'intersoccer-referral'),
            'Referral Discount',
            'Réduction de parrainage',  // fr_CH.po translation
            'Empfehlungsrabatt',         // de_CH.po translation
        ];
        
        foreach ($order->get_items('fee') as $fee) {
            $name = $fee->get_name();
            foreach ($fee_labels as $label) {
                if ($name === $label) {
                    return abs((float) $fee->get_total());
                }
            }
        }
        
        return 0.0;
    }
    
    /**
     * Get the refunded amount for the first-order discount fee
     * 
     * Finance rules for partial refund:
     * 1. If refund records which product lines were refunded, reverse discount by
     *    those lines' share of the order's product total.
     *    Example: product total 450, a 225 line refunded, discount 20, reverse 10.
     * 2. Otherwise, use cash ratio: refund amount / order total paid.
     *    Example: 215/430 × 20 = 10.
     * 3. Never reverse more than the original discount in total.
     * 
     * @param WC_Order $order
     * @param float $original_fee_amount The original discount amount
     * @return float Refunded amount (positive), capped at original
     */
    private function get_refunded_first_order_discount($order, $original_fee_amount) {
        if (!method_exists($order, 'get_refunds') || $original_fee_amount <= 0) {
            return 0.0;
        }
        
        $refunds = $order->get_refunds();
        if (empty($refunds)) {
            return 0.0;
        }
        
        $order_product_total = $this->get_order_product_total($order);
        $order_total_paid = abs((float) $order->get_total());
        
        $total_reversed = 0.0;
        
        foreach ($refunds as $refund) {
            $refund_product_total = $this->get_refund_product_total($refund);
            
            if ($refund_product_total > 0 && $order_product_total > 0) {
                $ratio = $refund_product_total / $order_product_total;
                $reversed = $original_fee_amount * $ratio;
            } else {
                $refund_amount = abs((float) $refund->get_total());
                if ($order_total_paid > 0) {
                    $ratio = $refund_amount / $order_total_paid;
                    $reversed = $original_fee_amount * $ratio;
                } else {
                    $reversed = 0.0;
                }
            }
            
            $total_reversed += $reversed;
        }
        
        return min($total_reversed, $original_fee_amount);
    }
    
    /**
     * Get the sum of product line totals on an order (excludes fees, shipping, tax)
     */
    private function get_order_product_total($order) {
        $total = 0.0;
        foreach ($order->get_items('line_item') as $item) {
            $total += abs((float) $item->get_total());
        }
        return $total;
    }
    
    /**
     * Get the sum of product line totals on a refund (negative values made positive)
     */
    private function get_refund_product_total($refund) {
        $total = 0.0;
        foreach ($refund->get_items('line_item') as $item) {
            $total += abs((float) $item->get_total());
        }
        return $total;
    }
    
    /**
     * Get points liability with redemption rate, age breakdown, and expiry policy
     * FIX #6: Compare like with like for month-over-month
     * FIX #7: Add age buckets
     * FIX #9: Redemption rate = redeemed / earned (excluding corrections)
     */
    public function get_points_liability() {
        $points_log = $this->table_prefix . 'intersoccer_points_log';
        $range = $this->get_period_range();
        
        // Current liability from usermeta (canonical snapshot)
        $current_liability = (float) $this->wpdb->get_var("
            SELECT COALESCE(SUM(meta_value), 0)
            FROM {$this->wpdb->usermeta}
            WHERE meta_key = 'intersoccer_points_balance'
              AND meta_value > 0
        ");
        
        // Get classified adjustments for proper earned calculation
        $adj = $this->classify_adjustments($range);
        
        // Points earned (12mo) excluding corrections (they don't create redeemable value)
        $earned_excluding_corrections = (float) $this->wpdb->get_var($this->wpdb->prepare("
            SELECT COALESCE(SUM(points_amount), 0)
            FROM {$points_log}
            WHERE points_amount > 0
              AND transaction_type NOT IN ('gift_sent', 'gift_received', 'gift_returned')
              AND created_at BETWEEN %s AND %s
        ", $range['start'], $range['end'] . ' 23:59:59')) - $adj['correction_chf'];
        
        // Points redeemed (12mo) - points_redemption only
        $redeemed_12mo = abs((float) $this->wpdb->get_var($this->wpdb->prepare("
            SELECT COALESCE(SUM(points_amount), 0)
            FROM {$points_log}
            WHERE transaction_type = 'points_redemption'
              AND created_at BETWEEN %s AND %s
        ", $range['start'], $range['end'] . ' 23:59:59')));
        
        // FIX #9: Redemption rate = redeemed / earned (excluding corrections)
        $redemption_rate = $earned_excluding_corrections > 0 
            ? round(($redeemed_12mo / $earned_excluding_corrections) * 100, 1) 
            : 0;
        
        // FIX #3: Expired/reversed reduce LIABILITY, not cost (shown separately)
        $total_expired = abs((float) $this->wpdb->get_var("
            SELECT COALESCE(SUM(points_amount), 0)
            FROM {$points_log}
            WHERE transaction_type = 'points_expired'
        "));
        
        $total_reversed = abs((float) $this->wpdb->get_var("
            SELECT COALESCE(SUM(points_amount), 0)
            FROM {$points_log}
            WHERE transaction_type IN ('order_refund', 'points_refund', 'reversal')
              AND points_amount < 0
        "));
        
        // FIX #7: Age breakdown of unredeemed points
        $age_breakdown = $this->get_points_age_breakdown();
        
        // Expiry policy from settings
        $expiry_months = (int) get_option('intersoccer_points_expiry_months', 0);
        $expiry_enabled = (bool) get_option('intersoccer_points_expiry_enabled', false);
        if ($expiry_enabled && $expiry_months > 0) {
            $expiry_policy = sprintf(__('Points expire after %d months', 'intersoccer-referral'), $expiry_months);
        } else {
            $expiry_policy = __('No expiry set', 'intersoccer-referral');
        }
        
        // FIX #6: Calculate last month's liability using the SAME method (usermeta snapshot simulation)
        // Exclude gifts from BOTH earned and deducted (same logic as age breakdown)
        // so last month and current month use consistent definitions
        $last_month_end = wp_date('Y-m-t', strtotime('-1 month'));
        $excluded_types = "('gift_sent', 'gift_received', 'gift_returned')";
        $last_month_liability = (float) $this->wpdb->get_var($this->wpdb->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN points_amount > 0 AND transaction_type NOT IN {$excluded_types} THEN points_amount ELSE 0 END), 0)
                - COALESCE(ABS(SUM(CASE WHEN points_amount < 0 AND transaction_type NOT IN {$excluded_types} THEN points_amount ELSE 0 END)), 0)
            FROM {$points_log}
            WHERE created_at <= %s
        ", $last_month_end . ' 23:59:59'));
        $last_month_liability = max(0, $last_month_liability);
        
        $change = $current_liability - $last_month_liability;
        
        // Get liability trend for chart (12 months)
        $trend = $this->get_liability_trend();
        
        return [
            'outstanding_points' => $current_liability,
            'current_chf' => $current_liability,
            'chf_liability' => $current_liability,
            'last_month_chf' => $last_month_liability,
            'change_chf' => $change,
            'change_percent' => $last_month_liability > 0 
                ? round(($change / $last_month_liability) * 100, 1) 
                : 0,
            'earned_12mo' => $earned_excluding_corrections,
            'redeemed_12mo' => $redeemed_12mo,
            'redemption_rate' => $redemption_rate,
            'total_expired' => $total_expired,
            'total_reversed' => $total_reversed,
            'liability_reductions' => $total_expired + $total_reversed,
            'age_breakdown' => $age_breakdown,
            'expiry_policy' => $expiry_policy,
            'trend' => $trend,
        ];
    }
    
    /**
     * FIX #7: Get age breakdown of unredeemed points (liability by age)
     * Buckets: 0-6 months, 6-12 months, 12+ months
     * 
     * This estimates how old the current outstanding liability is by looking at
     * the net balance by age bucket. Points are aged FIFO (oldest points redeemed first).
     * 
     * Gift transactions are excluded consistently from BOTH earnings AND deductions
     * (gifts move points between users, not out of the system).
     * 
     * Any gap between calculated total and usermeta total is shown as 'unreconciled'
     * rather than hidden via scaling.
     */
    private function get_points_age_breakdown() {
        $points_log = $this->table_prefix . 'intersoccer_points_log';
        
        $six_months_ago = wp_date('Y-m-d H:i:s', strtotime('-6 months'));
        $twelve_months_ago = wp_date('Y-m-d H:i:s', strtotime('-12 months'));
        
        // Get current total liability from usermeta (canonical)
        $total_liability = (float) $this->wpdb->get_var("
            SELECT COALESCE(SUM(meta_value), 0)
            FROM {$this->wpdb->usermeta}
            WHERE meta_key = 'intersoccer_points_balance'
              AND meta_value > 0
        ");
        
        // Transaction types that move points OUT of the system (not just between users)
        // Excludes gift_sent, gift_received, gift_returned which are internal transfers
        $excluded_from_earned = "('gift_sent', 'gift_received', 'gift_returned')";
        $excluded_from_deducted = "('gift_sent', 'gift_received', 'gift_returned')";
        
        // Get total earned by age bucket (excludes gifts)
        $earned_recent = (float) $this->wpdb->get_var($this->wpdb->prepare("
            SELECT COALESCE(SUM(points_amount), 0)
            FROM {$points_log}
            WHERE points_amount > 0
              AND transaction_type NOT IN {$excluded_from_earned}
              AND created_at >= %s
        ", $six_months_ago));
        
        $earned_mid = (float) $this->wpdb->get_var($this->wpdb->prepare("
            SELECT COALESCE(SUM(points_amount), 0)
            FROM {$points_log}
            WHERE points_amount > 0
              AND transaction_type NOT IN {$excluded_from_earned}
              AND created_at >= %s AND created_at < %s
        ", $twelve_months_ago, $six_months_ago));
        
        $earned_old = (float) $this->wpdb->get_var($this->wpdb->prepare("
            SELECT COALESCE(SUM(points_amount), 0)
            FROM {$points_log}
            WHERE points_amount > 0
              AND transaction_type NOT IN {$excluded_from_earned}
              AND created_at < %s
        ", $twelve_months_ago));
        
        // Total ever deducted (excludes gifts - redemptions, reversals, expired)
        $total_deducted = abs((float) $this->wpdb->get_var("
            SELECT COALESCE(SUM(points_amount), 0)
            FROM {$points_log}
            WHERE points_amount < 0
              AND transaction_type NOT IN {$excluded_from_deducted}
        "));
        
        // FIFO: oldest points are redeemed first
        // Subtract deductions from oldest bucket first
        $remaining_to_deduct = $total_deducted;
        
        $old_remaining = max(0, $earned_old - $remaining_to_deduct);
        $remaining_to_deduct = max(0, $remaining_to_deduct - $earned_old);
        
        $mid_remaining = max(0, $earned_mid - $remaining_to_deduct);
        $remaining_to_deduct = max(0, $remaining_to_deduct - $earned_mid);
        
        $recent_remaining = max(0, $earned_recent - $remaining_to_deduct);
        
        // Calculate total from log-based breakdown
        $calculated_total = $old_remaining + $mid_remaining + $recent_remaining;
        
        // Show any gap as 'unreconciled' instead of hiding via scaling
        $unreconciled = $total_liability - $calculated_total;
        
        return [
            '0_6_months' => round($recent_remaining),
            '6_12_months' => round($mid_remaining),
            '12_plus_months' => round($old_remaining),
            '0_6_months_pct' => $total_liability > 0 ? round(($recent_remaining / $total_liability) * 100, 1) : 0,
            '6_12_months_pct' => $total_liability > 0 ? round(($mid_remaining / $total_liability) * 100, 1) : 0,
            '12_plus_months_pct' => $total_liability > 0 ? round(($old_remaining / $total_liability) * 100, 1) : 0,
            'unreconciled' => round($unreconciled),
            'unreconciled_pct' => $total_liability > 0 ? round(($unreconciled / $total_liability) * 100, 1) : 0,
        ];
    }
    
    /**
     * Get referral funnel data
     * FIX #8: Booking = distinct referred order. Paid status from WC order status.
     */
    public function get_referral_funnel() {
        $range = $this->get_period_range();
        $referrals_table = $this->table_prefix . 'intersoccer_referrals';
        
        // Codes/links used
        $codes_used = (int) $this->wpdb->get_var($this->wpdb->prepare("
            SELECT COUNT(DISTINCT referral_code)
            FROM {$referrals_table}
            WHERE created_at BETWEEN %s AND %s
              AND referral_code IS NOT NULL
              AND referral_code != ''
        ", $range['start'], $range['end'] . ' 23:59:59'));
        
        // FIX #8: Bookings = distinct referred orders (not referral records)
        $booking_order_ids = $this->wpdb->get_col($this->wpdb->prepare("
            SELECT DISTINCT order_id
            FROM {$referrals_table}
            WHERE created_at BETWEEN %s AND %s
              AND order_id IS NOT NULL
              AND order_id > 0
        ", $range['start'], $range['end'] . ' 23:59:59'));
        
        $bookings = count($booking_order_ids);
        
        // FIX #8: Paid orders from WC order status, not referral status
        $paid_orders = 0;
        $pending_orders = 0;
        $failed_orders = 0;
        
        foreach ($booking_order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                $failed_orders++;
                continue;
            }
            
            $status = $order->get_status();
            if (in_array($status, ['completed', 'processing'])) {
                $paid_orders++;
            } elseif (in_array($status, ['pending', 'on-hold'])) {
                $pending_orders++;
            } else {
                $failed_orders++;
            }
        }
        
        // Gap explanation
        $gap = $bookings - $paid_orders;
        $gap_explanation = '';
        if ($gap > 0) {
            $parts = [];
            if ($pending_orders > 0) $parts[] = sprintf('%d pending', $pending_orders);
            if ($failed_orders > 0) $parts[] = sprintf('%d failed/cancelled', $failed_orders);
            $gap_explanation = implode(', ', $parts);
        }
        
        // Unique customers with paid orders
        $total_unique_customers = (int) $this->wpdb->get_var($this->wpdb->prepare("
            SELECT COUNT(DISTINCT r.customer_id)
            FROM {$referrals_table} r
            WHERE r.created_at BETWEEN %s AND %s
              AND r.order_id IN (
                  SELECT DISTINCT order_id FROM {$referrals_table}
                  WHERE created_at BETWEEN %s AND %s
              )
        ", $range['start'], $range['end'] . ' 23:59:59', $range['start'], $range['end'] . ' 23:59:59'));
        
        // Count paid unique customers (where the order is completed/processing)
        $new_families = 0;
        $existing_customers = 0;
        
        $customer_orders = $this->wpdb->get_results($this->wpdb->prepare("
            SELECT DISTINCT r.customer_id, r.order_id
            FROM {$referrals_table} r
            WHERE r.created_at BETWEEN %s AND %s
              AND r.order_id IS NOT NULL
        ", $range['start'], $range['end'] . ' 23:59:59'));
        
        $counted_customers = [];
        foreach ($customer_orders as $row) {
            // Handle both object and array formats (arrays may come from test mocks)
            $customer_id = is_object($row) ? ($row->customer_id ?? null) : ($row['customer_id'] ?? null);
            $order_id = is_object($row) ? ($row->order_id ?? null) : ($row['order_id'] ?? null);
            
            if (!$customer_id || !$order_id) continue;
            if (isset($counted_customers[$customer_id])) continue;
            
            $order = wc_get_order($order_id);
            if (!$order || !in_array($order->get_status(), ['completed', 'processing'])) continue;
            
            $counted_customers[$customer_id] = true;
            
            if ($this->is_new_family($customer_id, $order_id)) {
                $new_families++;
            } else {
                $existing_customers++;
            }
        }
        
        $total_unique_paid = count($counted_customers);
        $existing_customer_share = $total_unique_paid > 0 
            ? round(($existing_customers / $total_unique_paid) * 100, 1) 
            : 0;
        
        // Sample size warning
        $sample_warning = $total_unique_paid < 30 
            ? __('Too few families to judge yet.', 'intersoccer-referral') 
            : '';
        
        return [
            'codes_used' => $codes_used,
            'bookings' => $bookings,
            'bookings_definition' => __('Distinct referred orders', 'intersoccer-referral'),
            'paid_orders' => $paid_orders,
            'paid_orders_definition' => __('Orders with WC status completed/processing', 'intersoccer-referral'),
            'pending_orders' => $pending_orders,
            'failed_orders' => $failed_orders,
            'gap_explanation' => $gap_explanation,
            'total_unique_customers' => $total_unique_paid,
            'new_families' => $new_families,
            'new_families_definition' => __('No paid order before this referred order', 'intersoccer-referral'),
            'existing_customers' => $existing_customers,
            'existing_customer_share' => $existing_customer_share,
            'sample_size' => $total_unique_paid,
            'sample_warning' => $sample_warning,
            'has_data' => $bookings > 0,
            'visits_tracked' => false, // Visit tracking not yet implemented
            'visits' => 0,
            'period_label' => __('Rolling 12 Months', 'intersoccer-referral'),
        ];
    }
    
    /**
     * Calculate referral ROI
     * FIX #11: Revenue counts only orders from the referred order onward, deduplicated per customer
     * FIX #5: Use single referral cost source (get_cost_breakdown) for card and range
     */
    public function calculate_referral_roi() {
        $range = $this->get_period_range();
        $settings = $this->get_roi_settings();
        $referrals_table = $this->table_prefix . 'intersoccer_referrals';
        
        // Get funnel data
        $funnel = $this->get_referral_funnel();
        $new_families = $funnel['new_families'];
        
        // FIX #5: Single referral cost source for both card and range
        $costs = $this->get_cost_breakdown();
        $total_cost = $costs['referral_cost_chf'];
        $coach_commissions = $costs['commissions_chf'];
        $referral_points = $costs['referral_points_chf'];
        
        // FIX #11: Revenue from referred customers, from their referred order onward only
        // Deduplicated per customer (only count each customer once)
        $referred_revenue = 0.0;
        
        $referrals = $this->wpdb->get_results($this->wpdb->prepare("
            SELECT DISTINCT customer_id, order_id, created_at
            FROM {$referrals_table}
            WHERE created_at BETWEEN %s AND %s
              AND order_id IS NOT NULL
            ORDER BY created_at ASC
        ", $range['start'], $range['end'] . ' 23:59:59'));
        
        $counted_customers = [];
        foreach ($referrals as $ref) {
            // Handle both object and array formats (arrays may come from test mocks)
            $customer_id = is_object($ref) ? ($ref->customer_id ?? null) : ($ref['customer_id'] ?? null);
            $order_id = is_object($ref) ? ($ref->order_id ?? null) : ($ref['order_id'] ?? null);
            
            if (!$customer_id || !$order_id) continue;
            if (isset($counted_customers[$customer_id])) continue;
            $counted_customers[$customer_id] = true;
            
            // Finance dedupe: referral revenue = ONLY the first (referred) order
            // Later orders from this customer count toward loyalty if they hold points
            $referred_order = wc_get_order($order_id);
            if (!$referred_order) continue;
            
            // Skip if status is not completed/processing
            $order_status = $referred_order->get_status();
            if (!in_array($order_status, ['completed', 'processing'])) continue;
            
            // Only count if order is within the period (both start and end)
            $order_date = $referred_order->get_date_created();
            if (!$order_date) continue;
            $order_date_str = $order_date->format('Y-m-d');
            if ($order_date_str < $range['start'] || $order_date_str > $range['end']) continue;
            
            // Line totals AFTER discounts (coupons/promos reduce revenue)
            foreach ($referred_order->get_items() as $item) {
                $referred_revenue += (float) $item->get_total();
            }
            // Subtract refunds
            if (method_exists($referred_order, 'get_refunds')) {
                foreach ($referred_order->get_refunds() as $refund) {
                    foreach ($refund->get_items() as $refund_item) {
                        $referred_revenue += (float) $refund_item->get_total();
                    }
                }
            }
        }
        
        // Total site revenue for comparison
        $total_revenue = $this->get_revenue_ex_vat($range);
        $referred_share = $total_revenue > 0 ? ($referred_revenue / $total_revenue) * 100 : 0;
        
        $cost_per_family = $new_families > 0 ? $total_cost / $new_families : 0;
        
        // ROI calculation
        $roi_percentage = null;
        $roi_display = '';
        $has_data = ($total_cost > 0 || $referred_revenue > 0);
        
        if (!$this->has_camp_margin()) {
            $roi_display = '—';
            $show_prompt = true;
        } elseif ($total_cost > 0) {
            $margin_rate = $settings['camp_margin_percent'] / 100;
            $incremental_profit = $referred_revenue * $margin_rate;
            $roi_percentage = round((($incremental_profit - $total_cost) / $total_cost) * 100, 1);
            $roi_display = number_format($roi_percentage, 1) . '%';
            $show_prompt = false;
        } else {
            $roi_display = __('Not enough data', 'intersoccer-referral');
            $show_prompt = false;
        }
        
        return [
            'new_families' => $new_families,
            'sample_size' => $funnel['sample_size'],
            'sample_warning' => $funnel['sample_warning'],
            'revenue_ex_vat' => $referred_revenue,
            'total_revenue' => $total_revenue,
            'referred_share_percent' => round($referred_share, 1),
            'coach_commissions' => $coach_commissions,
            'referral_points_cost' => $referral_points,
            'total_cost' => $total_cost,
            'cost_per_family' => round($cost_per_family, 2),
            'roi_percentage' => $roi_percentage,
            'roi_display' => $roi_display,
            'has_data' => $has_data,
            'show_margin_prompt' => $show_prompt ?? false,
            'period_label' => __('Rolling 12 Months', 'intersoccer-referral'),
        ];
    }
    
    /**
     * Get first referred order IDs for Finance dedupe
     * 
     * Returns array of order_id => customer_id for orders that should be
     * excluded from loyalty holder spend (they're counted in referral revenue).
     */
    public function get_first_referred_orders($range = null) {
        if ($range === null) {
            $range = $this->get_period_range();
        }
        
        $referrals_table = $this->table_prefix . 'intersoccer_referrals';
        
        $referrals = $this->wpdb->get_results($this->wpdb->prepare("
            SELECT DISTINCT customer_id, order_id
            FROM {$referrals_table}
            WHERE created_at BETWEEN %s AND %s
              AND order_id IS NOT NULL
            ORDER BY created_at ASC
        ", $range['start'], $range['end'] . ' 23:59:59'));
        
        $first_orders = [];
        $seen_customers = [];
        
        foreach ($referrals as $ref) {
            $customer_id = is_object($ref) ? ($ref->customer_id ?? null) : ($ref['customer_id'] ?? null);
            $order_id = is_object($ref) ? ($ref->order_id ?? null) : ($ref['order_id'] ?? null);
            
            if (!$customer_id || !$order_id) continue;
            if (isset($seen_customers[$customer_id])) continue;
            
            $seen_customers[$customer_id] = true;
            $first_orders[(int) $order_id] = (int) $customer_id;
        }
        
        return $first_orders;
    }
    
    /**
     * Calculate loyalty ROI
     * FIX #3: Cost is points when EARNED. Expired/reversed reduce LIABILITY, not cost.
     */
    public function calculate_loyalty_roi() {
        $range = $this->get_period_range();
        $settings = $this->get_roi_settings();
        
        // Get cost breakdown (uses shared classifier)
        $costs = $this->get_cost_breakdown();
        $liability = $this->get_points_liability();
        
        // Get holder vs non-holder comparison
        $comparison = $this->get_holder_vs_nonholder_comparison($range);
        
        // FIX #3: Cost is loyalty_cost (points when earned), NOT net of expired/reversed
        $points_cost = $costs['loyalty_cost_chf'];
        
        // Extra spend from loyalty
        $extra_spend_per_customer = $comparison['holder_avg_spend'] - $comparison['non_holder_avg_spend'];
        $total_extra_spend = $extra_spend_per_customer * $comparison['holder_count'];
        
        // ROI calculation
        $roi_percentage = null;
        $roi_display = '';
        $has_data = ($points_cost > 0 || $comparison['holder_count'] > 0);
        
        if (!$this->has_camp_margin()) {
            $roi_display = '—';
            $show_prompt = true;
        } elseif ($points_cost > 0) {
            $margin_rate = $settings['camp_margin_percent'] / 100;
            $incremental_profit = $total_extra_spend * $margin_rate;
            $roi_percentage = round((($incremental_profit - $points_cost) / $points_cost) * 100, 1);
            $roi_display = number_format($roi_percentage, 1) . '%';
            $show_prompt = false;
        } else {
            $roi_display = __('Not enough data', 'intersoccer-referral');
            $show_prompt = false;
        }
        
        return [
            'holder_count' => $comparison['holder_count'],
            'non_holder_count' => $comparison['non_holder_count'],
            'holder_avg_spend' => $comparison['holder_avg_spend'],
            'non_holder_avg_spend' => $comparison['non_holder_avg_spend'],
            'holder_repeat_rate' => $comparison['holder_repeat_rate'],
            'non_holder_repeat_rate' => $comparison['non_holder_repeat_rate'],
            'extra_spend_per_customer' => round($extra_spend_per_customer, 2),
            'total_extra_spend' => $total_extra_spend,
            'points_cost' => $points_cost,
            'liability_reductions' => $liability['liability_reductions'],
            'roi_percentage' => $roi_percentage,
            'roi_display' => $roi_display,
            'has_data' => $has_data,
            'show_margin_prompt' => $show_prompt ?? false,
            'comparison_note' => __('Comparison, not proof the program caused it', 'intersoccer-referral'),
            'attribution_note' => __('Campaign and ad-spend decisions need channel attribution, which isn\'t tracked yet.', 'intersoccer-referral'),
        ];
    }
    
    /**
     * Get holder vs non-holder comparison using aggregate SQL
     * FIX #13: HPOS-compatible with memory optimization
     */
    public function get_holder_vs_nonholder_comparison($range = null, $force_refresh = false) {
        if ($range === null) {
            $range = $this->get_period_range();
        }
        
        $cache_key = 'holder_comparison_' . md5($range['start'] . $range['end']);
        
        return $this->get_cached($cache_key, function() use ($range) {
            return $this->compute_holder_vs_nonholder($range);
        }, $force_refresh);
    }
    
    /**
     * Compute holder vs non-holder comparison using aggregate queries
     * 
     * Revenue = product line totals after coupons (ex-VAT, net of refunds)
     */
    private function compute_holder_vs_nonholder($range) {
        $orders_table = $this->wpdb->prefix . 'wc_orders';
        $items_table = $this->wpdb->prefix . 'woocommerce_order_items';
        $itemmeta_table = $this->wpdb->prefix . 'woocommerce_order_itemmeta';
        
        // Get all users with points balance > 0
        $holders = $this->wpdb->get_col("
            SELECT user_id FROM {$this->wpdb->usermeta}
            WHERE meta_key = 'intersoccer_points_balance' AND meta_value > 0
        ");
        
        if (empty($holders)) {
            $holder_ids_sql = '0';
        } else {
            $holder_ids_sql = implode(',', array_map('intval', $holders));
        }
        
        $hpos_enabled = $this->is_hpos_active();
        
        // Finance dedupe: get first referred orders to exclude from holder spend
        $first_referred = $this->get_first_referred_orders($range);
        $excluded_order_ids = array_keys($first_referred);
        $excluded_ids_sql = !empty($excluded_order_ids) ? implode(',', array_map('intval', $excluded_order_ids)) : '0';
        
        if ($hpos_enabled) {
            // Convert local dates to GMT for HPOS date_created_gmt column comparison
            $start_gmt = get_gmt_from_date($range['start'] . ' 00:00:00', 'Y-m-d H:i:s');
            $end_gmt = get_gmt_from_date($range['end'] . ' 23:59:59', 'Y-m-d H:i:s');
            
            // HPOS aggregate query for holders - uses _line_total (after discounts)
            // Finance dedupe: excludes first referred orders (they're counted in referral revenue)
            $holder_stats = $this->wpdb->get_row($this->wpdb->prepare("
                SELECT 
                    COUNT(DISTINCT o.customer_id) as customer_count,
                    COUNT(DISTINCT o.id) as order_count,
                    COALESCE(SUM(im.meta_value), 0) as total_revenue
                FROM {$orders_table} o
                INNER JOIN {$items_table} oi ON o.id = oi.order_id AND oi.order_item_type = 'line_item'
                INNER JOIN {$itemmeta_table} im ON oi.order_item_id = im.order_item_id AND im.meta_key = '_line_total'
                WHERE o.type = 'shop_order'
                  AND o.status IN ('wc-completed', 'wc-processing')
                  AND o.date_created_gmt >= %s
                  AND o.date_created_gmt <= %s
                  AND o.customer_id IN ({$holder_ids_sql})
                  AND o.customer_id > 0
                  AND o.id NOT IN ({$excluded_ids_sql})
            ", $start_gmt, $end_gmt));
            
            // Subtract refunds for holders (also excluding refunds on first referred orders)
            $holder_refunds = (float) $this->wpdb->get_var($this->wpdb->prepare("
                SELECT COALESCE(SUM(im.meta_value), 0)
                FROM {$orders_table} o
                INNER JOIN {$orders_table} r ON r.parent_order_id = o.id AND r.type = 'shop_order_refund'
                INNER JOIN {$items_table} oi ON r.id = oi.order_id AND oi.order_item_type = 'line_item'
                INNER JOIN {$itemmeta_table} im ON oi.order_item_id = im.order_item_id AND im.meta_key = '_line_total'
                WHERE o.type = 'shop_order'
                  AND o.status IN ('wc-completed', 'wc-processing')
                  AND o.date_created_gmt >= %s
                  AND o.date_created_gmt <= %s
                  AND o.customer_id IN ({$holder_ids_sql})
                  AND o.customer_id > 0
                  AND o.id NOT IN ({$excluded_ids_sql})
            ", $start_gmt, $end_gmt));
            
            // Non-holders - uses _line_total (after discounts)
            $non_holder_stats = $this->wpdb->get_row($this->wpdb->prepare("
                SELECT 
                    COUNT(DISTINCT o.customer_id) as customer_count,
                    COUNT(DISTINCT o.id) as order_count,
                    COALESCE(SUM(im.meta_value), 0) as total_revenue
                FROM {$orders_table} o
                INNER JOIN {$items_table} oi ON o.id = oi.order_id AND oi.order_item_type = 'line_item'
                INNER JOIN {$itemmeta_table} im ON oi.order_item_id = im.order_item_id AND im.meta_key = '_line_total'
                WHERE o.type = 'shop_order'
                  AND o.status IN ('wc-completed', 'wc-processing')
                  AND o.date_created_gmt >= %s
                  AND o.date_created_gmt <= %s
                  AND o.customer_id NOT IN ({$holder_ids_sql})
                  AND o.customer_id > 0
            ", $start_gmt, $end_gmt));
            
            // Subtract refunds for non-holders
            $non_holder_refunds = (float) $this->wpdb->get_var($this->wpdb->prepare("
                SELECT COALESCE(SUM(im.meta_value), 0)
                FROM {$orders_table} o
                INNER JOIN {$orders_table} r ON r.parent_order_id = o.id AND r.type = 'shop_order_refund'
                INNER JOIN {$items_table} oi ON r.id = oi.order_id AND oi.order_item_type = 'line_item'
                INNER JOIN {$itemmeta_table} im ON oi.order_item_id = im.order_item_id AND im.meta_key = '_line_total'
                WHERE o.type = 'shop_order'
                  AND o.status IN ('wc-completed', 'wc-processing')
                  AND o.date_created_gmt >= %s
                  AND o.date_created_gmt <= %s
                  AND o.customer_id NOT IN ({$holder_ids_sql})
                  AND o.customer_id > 0
            ", $start_gmt, $end_gmt));
            
            // Calculate repeat rates using aggregate query
            // Finance dedupe: exclude first referred orders from holder repeat rate
            $holder_repeat = $this->calculate_repeat_rate_sql($holder_ids_sql, $range, true, $excluded_ids_sql);
            $non_holder_repeat = $this->calculate_repeat_rate_sql($holder_ids_sql, $range, false);
            
            // Finance ruling: "first orders included" decides who counts as holder or non-holder.
            // A customer with any order in the window (including their first referred order) is counted.
            // Dormant accounts (points but no orders in window) are excluded from holder count.
            // However, holder SPEND excludes first referred orders (those go to referral revenue).
            // Example: referred customer with 400 first + 300 later = holder count 1, spend 300.
            $active_holders = $this->wpdb->get_var($this->wpdb->prepare("
                SELECT COUNT(DISTINCT o.customer_id)
                FROM {$orders_table} o
                WHERE o.type = 'shop_order'
                  AND o.status IN ('wc-completed', 'wc-processing')
                  AND o.date_created_gmt >= %s
                  AND o.date_created_gmt <= %s
                  AND o.customer_id IN ({$holder_ids_sql})
                  AND o.customer_id > 0
            ", $start_gmt, $end_gmt));
            $holder_count = (int) $active_holders;
            $non_holder_count = (int) ($non_holder_stats->customer_count ?? 0);
            
            // Revenue = line totals + refunds (refunds are negative)
            $holder_total_revenue = (float) ($holder_stats->total_revenue ?? 0) + $holder_refunds;
            $non_holder_total_revenue = (float) ($non_holder_stats->total_revenue ?? 0) + $non_holder_refunds;
            
            return [
                'holder_count' => $holder_count,
                'non_holder_count' => $non_holder_count,
                'holder_avg_spend' => $holder_count > 0 ? round($holder_total_revenue / $holder_count, 2) : 0,
                'non_holder_avg_spend' => $non_holder_count > 0 ? round($non_holder_total_revenue / $non_holder_count, 2) : 0,
                'holder_orders' => (int) ($holder_stats->order_count ?? 0),
                'non_holder_orders' => (int) ($non_holder_stats->order_count ?? 0),
                'holder_repeat_rate' => $holder_repeat,
                'non_holder_repeat_rate' => $non_holder_repeat,
            ];
        }
        
        // Fallback: batched processing
        return $this->compute_holder_vs_nonholder_batched($range, array_flip($holders));
    }
    
    /**
     * Calculate repeat rate using aggregate SQL
     * 
     * @param string $holder_ids_sql Comma-separated holder IDs
     * @param array $range Date range with 'start' and 'end'
     * @param bool $is_holder Whether calculating for holders (true) or non-holders (false)
     * @param string|null $excluded_order_ids_sql Optional: first referred order IDs to exclude (Finance dedupe)
     */
    private function calculate_repeat_rate_sql($holder_ids_sql, $range, $is_holder, $excluded_order_ids_sql = null) {
        $orders_table = $this->wpdb->prefix . 'wc_orders';
        
        // Convert local dates to GMT for consistent bounds
        $start_gmt = get_gmt_from_date($range['start'] . ' 00:00:00', 'Y-m-d H:i:s');
        $end_gmt = get_gmt_from_date($range['end'] . ' 23:59:59', 'Y-m-d H:i:s');
        
        $operator = $is_holder ? 'IN' : 'NOT IN';
        
        // Finance dedupe: exclude first referred orders from holder repeat rate
        $exclusion_clause = '';
        if ($is_holder && $excluded_order_ids_sql && $excluded_order_ids_sql !== '0') {
            $exclusion_clause = " AND id NOT IN ({$excluded_order_ids_sql})";
        }
        
        // Count customers with 2+ orders (after excluding first referred orders for holders)
        $result = $this->wpdb->get_row($this->wpdb->prepare("
            SELECT 
                COUNT(DISTINCT customer_id) as total_customers,
                COUNT(DISTINCT CASE WHEN order_count >= 2 THEN customer_id END) as repeat_customers
            FROM (
                SELECT customer_id, COUNT(*) as order_count
                FROM {$orders_table}
                WHERE type = 'shop_order'
                  AND status IN ('wc-completed', 'wc-processing')
                  AND date_created_gmt >= %s
                  AND date_created_gmt <= %s
                  AND customer_id {$operator} ({$holder_ids_sql})
                  AND customer_id > 0
                  {$exclusion_clause}
                GROUP BY customer_id
            ) as customer_orders
        ", $start_gmt, $end_gmt));
        
        $total = (int) ($result->total_customers ?? 0);
        $repeat = (int) ($result->repeat_customers ?? 0);
        
        return $total > 0 ? round(($repeat / $total) * 100, 1) : 0;
    }
    
    /**
     * Batched fallback for holder comparison
     * 
     * Revenue = product line totals after coupons (ex-VAT, net of refunds)
     */
    private function compute_holder_vs_nonholder_batched($range, $holder_set) {
        $holder_stats = ['customers' => [], 'revenue' => 0, 'orders' => 0];
        $non_holder_stats = ['customers' => [], 'revenue' => 0, 'orders' => 0];
        // Track order counts per customer for repeat rate
        $holder_order_counts = [];
        $non_holder_order_counts = [];
        
        // Finance dedupe: get first referred orders to exclude from holder spend
        $first_referred = $this->get_first_referred_orders($range);
        
        // Finance ruling: referred holders count if they have at least one order in window
        // Their first order IS an order in window, so they count as holders even with 0 later spend
        // But we must verify the order is valid (completed/processing, in date range)
        foreach ($first_referred as $order_id => $customer_id) {
            if (isset($holder_set[$customer_id])) {
                $order = wc_get_order($order_id);
                if ($order && in_array($order->get_status(), ['completed', 'processing'])) {
                    $order_date = $order->get_date_created();
                    if ($order_date) {
                        $date_str = $order_date->format('Y-m-d');
                        if ($date_str >= $range['start'] && $date_str <= $range['end']) {
                            $holder_stats['customers'][$customer_id] = true;
                        }
                    }
                }
            }
        }
        
        $page = 1;
        do {
            $orders = wc_get_orders([
                'status' => ['completed', 'processing'],
                'date_created' => $range['start'] . '...' . $range['end'],
                'limit' => self::BATCH_SIZE,
                'page' => $page,
                'return' => 'ids',
            ]);
            
            foreach ($orders as $order_id) {
                $order = wc_get_order($order_id);
                if (!$order || $order->get_type() === 'shop_order_refund') continue;
                
                $customer_id = $order->get_customer_id();
                if (!$customer_id) continue;
                
                // Finance dedupe: skip first referred orders for holders (counted in referral)
                // But still track the customer as a holder (done above)
                if (isset($holder_set[$customer_id]) && isset($first_referred[$order_id])) {
                    continue;
                }
                
                // Line totals AFTER discounts (coupons reduce revenue)
                $revenue = 0;
                foreach ($order->get_items() as $item) {
                    $revenue += (float) $item->get_total();
                }
                
                // Subtract refunds
                if (method_exists($order, 'get_refunds')) {
                    foreach ($order->get_refunds() as $refund) {
                        foreach ($refund->get_items() as $refund_item) {
                            $revenue += (float) $refund_item->get_total();
                        }
                    }
                }
                
                if (isset($holder_set[$customer_id])) {
                    $holder_stats['customers'][$customer_id] = true;
                    $holder_stats['revenue'] += $revenue;
                    $holder_stats['orders']++;
                    $holder_order_counts[$customer_id] = ($holder_order_counts[$customer_id] ?? 0) + 1;
                } else {
                    $non_holder_stats['customers'][$customer_id] = true;
                    $non_holder_stats['revenue'] += $revenue;
                    $non_holder_stats['orders']++;
                    $non_holder_order_counts[$customer_id] = ($non_holder_order_counts[$customer_id] ?? 0) + 1;
                }
                
                unset($order);
            }
            
            $page++;
        } while (count($orders) === self::BATCH_SIZE);
        
        // Finance ruling: "first orders included" decides who counts as holder or non-holder.
        // holder_stats['customers'] includes all holders with any order in window (first or later).
        // Dormant accounts (points but no orders in window) are excluded from holder count.
        // holder SPEND excludes first referred orders (those go to referral revenue).
        // Example: referred customer with 400 first + 300 later = holder count 1, spend 300.
        $holder_count = count($holder_stats['customers']);
        $non_holder_count = count($non_holder_stats['customers']);
        
        // Calculate repeat rates (customers with 2+ orders)
        $holder_repeat = 0;
        foreach ($holder_order_counts as $count) {
            if ($count >= 2) $holder_repeat++;
        }
        $non_holder_repeat = 0;
        foreach ($non_holder_order_counts as $count) {
            if ($count >= 2) $non_holder_repeat++;
        }
        
        return [
            'holder_count' => $holder_count,
            'non_holder_count' => $non_holder_count,
            'holder_avg_spend' => $holder_count > 0 ? round($holder_stats['revenue'] / $holder_count, 2) : 0,
            'non_holder_avg_spend' => $non_holder_count > 0 ? round($non_holder_stats['revenue'] / $non_holder_count, 2) : 0,
            'holder_orders' => $holder_stats['orders'],
            'non_holder_orders' => $non_holder_stats['orders'],
            'holder_repeat_rate' => $holder_count > 0 ? round(($holder_repeat / $holder_count) * 100, 1) : null,
            'non_holder_repeat_rate' => $non_holder_count > 0 ? round(($non_holder_repeat / $non_holder_count) * 100, 1) : null,
        ];
    }
    
    /**
     * Get coach leaderboard
     * FIX #10: Apply new-family rule
     */
    public function get_coach_leaderboard($limit = 10) {
        $range = $this->get_period_range();
        $referrals_table = $this->table_prefix . 'intersoccer_referrals';
        
        // Get coaches with referrals
        $coach_data = $this->wpdb->get_results($this->wpdb->prepare("
            SELECT 
                r.coach_id,
                u.display_name,
                GROUP_CONCAT(DISTINCT CONCAT(r.customer_id, ':', r.order_id)) as customer_orders,
                COUNT(DISTINCT r.referral_code) as codes_used,
                COUNT(DISTINCT r.order_id) as total_referrals
            FROM {$referrals_table} r
            JOIN {$this->wpdb->users} u ON r.coach_id = u.ID
            WHERE r.created_at BETWEEN %s AND %s
              AND r.coach_id IS NOT NULL
              AND r.order_id IS NOT NULL
            GROUP BY r.coach_id, u.display_name
        ", $range['start'], $range['end'] . ' 23:59:59'));
        
        $results = [];
        foreach ($coach_data as $coach) {
            // Handle both object and array formats (arrays may come from test mocks)
            $customer_orders_str = is_object($coach) ? ($coach->customer_orders ?? '') : ($coach['customer_orders'] ?? '');
            $coach_id = is_object($coach) ? ($coach->coach_id ?? null) : ($coach['coach_id'] ?? null);
            $display_name = is_object($coach) ? ($coach->display_name ?? '') : ($coach['display_name'] ?? '');
            $codes_used = is_object($coach) ? ($coach->codes_used ?? 0) : ($coach['codes_used'] ?? 0);
            $total_referrals = is_object($coach) ? ($coach->total_referrals ?? 0) : ($coach['total_referrals'] ?? 0);
            
            $new_families = 0;
            $paid_orders = 0;
            
            // Parse customer:order pairs
            $pairs = explode(',', $customer_orders_str);
            $counted_customers = [];
            
            foreach ($pairs as $pair) {
                if (strpos($pair, ':') === false) continue;
                list($customer_id, $order_id) = explode(':', $pair);
                
                $order = wc_get_order($order_id);
                if (!$order || !in_array($order->get_status(), ['completed', 'processing'])) continue;
                
                $paid_orders++;
                
                if (!isset($counted_customers[$customer_id])) {
                    $counted_customers[$customer_id] = true;
                    if ($this->is_new_family($customer_id, $order_id)) {
                        $new_families++;
                    }
                }
            }
            
            $results[] = (object) [
                'coach_id' => $coach_id,
                'display_name' => $display_name,
                'new_families' => $new_families,
                'codes_used' => $codes_used,
                'paid_orders' => $paid_orders,
                'total_referrals' => $total_referrals,
            ];
        }
        
        // Sort by new_families desc, then paid_orders desc
        usort($results, function($a, $b) {
            if ($a->new_families !== $b->new_families) {
                return $b->new_families - $a->new_families;
            }
            return $b->paid_orders - $a->paid_orders;
        });
        
        return array_slice($results, 0, $limit);
    }
    
    /**
     * Get referral profit breakdown
     * FIX #10: Apply new-family rule
     */
    public function get_referral_profit_breakdown() {
        $range = $this->get_period_range();
        $settings = $this->get_roi_settings();
        $referrals_table = $this->table_prefix . 'intersoccer_referrals';
        
        if (!$this->has_camp_margin()) {
            return [
                'has_data' => false,
                'show_margin_prompt' => true,
            ];
        }
        
        $margin_rate = $settings['camp_margin_percent'] / 100;
        
        // Get referred customers
        $referrals = $this->wpdb->get_results($this->wpdb->prepare("
            SELECT DISTINCT customer_id, order_id, created_at
            FROM {$referrals_table}
            WHERE created_at BETWEEN %s AND %s
              AND order_id IS NOT NULL
            ORDER BY created_at ASC
        ", $range['start'], $range['end'] . ' 23:59:59'));
        
        $first_order_revenue = 0;
        $repeat_order_revenue = 0;
        $new_family_count = 0;
        $counted_customers = [];
        
        foreach ($referrals as $ref) {
            // Handle both object and array formats (arrays may come from test mocks)
            $customer_id = is_object($ref) ? ($ref->customer_id ?? null) : ($ref['customer_id'] ?? null);
            $order_id = is_object($ref) ? ($ref->order_id ?? null) : ($ref['order_id'] ?? null);
            
            if (!$customer_id || !$order_id) continue;
            if (isset($counted_customers[$customer_id])) continue;
            $counted_customers[$customer_id] = true;
            
            $referred_order = wc_get_order($order_id);
            if (!$referred_order || !in_array($referred_order->get_status(), ['completed', 'processing'])) continue;
            
            // FIX #10: Apply new-family rule
            if (!$this->is_new_family($customer_id, $order_id)) continue;
            
            $new_family_count++;
            $referred_date = $referred_order->get_date_created();
            $end_date = $referred_date ? wp_date('Y-m-d', strtotime($referred_date->format('Y-m-d') . ' +12 months')) : $range['end'];
            
            // Get all orders for this customer within 12 months of referral
            $customer_orders = wc_get_orders([
                'customer_id' => $customer_id,
                'status' => ['completed', 'processing'],
                'date_created' => $referred_date->format('Y-m-d H:i:s') . '...' . $end_date,
                'limit' => -1,
                'orderby' => 'date',
                'order' => 'ASC',
            ]);
            
            $is_first = true;
            foreach ($customer_orders as $order) {
                // Skip refund orders
                if ($order->get_type() === 'shop_order_refund') continue;
                
                // Line totals AFTER discounts (coupons reduce revenue)
                $revenue = 0;
                foreach ($order->get_items() as $item) {
                    $revenue += (float) $item->get_total();
                }
                if (method_exists($order, 'get_refunds')) {
                    foreach ($order->get_refunds() as $refund) {
                        foreach ($refund->get_items() as $refund_item) {
                            $revenue += (float) $refund_item->get_total();
                        }
                    }
                }
                
                if ($is_first) {
                    $first_order_revenue += $revenue;
                    $is_first = false;
                } else {
                    $repeat_order_revenue += $revenue;
                }
            }
        }
        
        // Get referral cost
        $costs = $this->get_cost_breakdown();
        $total_cost = $costs['referral_cost_chf'];
        
        $first_order_profit = $first_order_revenue * $margin_rate;
        $repeat_order_profit = $repeat_order_revenue * $margin_rate;
        $total_profit = $first_order_profit + $repeat_order_profit;
        
        $cost_per_family = $new_family_count > 0 ? $total_cost / $new_family_count : 0;
        
        // Cost per family from ads (if data available)
        $cost_per_family_ads = null;
        if ($this->has_ad_data()) {
            $cost_per_family_ads = $settings['ad_spend_chf'] / $settings['ad_attributed_families'];
        }
        
        return [
            'has_data' => $new_family_count > 0,
            'new_families' => $new_family_count,
            'first_order_revenue' => $first_order_revenue,
            'repeat_order_revenue' => $repeat_order_revenue,
            'first_order_profit' => $first_order_profit,
            'repeat_order_profit' => $repeat_order_profit,
            'total_profit' => $total_profit,
            'total_cost' => $total_cost,
            'cost_per_family_referral' => round($cost_per_family, 2),
            'cost_per_family_ads' => $cost_per_family_ads !== null ? round($cost_per_family_ads, 2) : null,
            'show_ad_comparison' => $this->has_ad_data(),
            'show_margin_prompt' => false,
        ];
    }
    
    /**
     * Calculate referral ROI with incrementality range
     * 
     * Uses REFERRAL_INCREMENTALITY_LOW (25%) to REFERRAL_INCREMENTALITY_HIGH (75%)
     * to estimate the true incremental value of referred revenue.
     * 
     * @return array ROI range with low/high estimates
     */
    public function calculate_referral_roi_with_range() {
        if (!$this->has_camp_margin()) {
            return [
                'low' => null,
                'high' => null,
                'has_data' => false,
                'show_margin_prompt' => true,
            ];
        }
        
        $settings = $this->get_roi_settings();
        $referral = $this->calculate_referral_roi();
        $costs = $this->get_cost_breakdown();
        
        $margin_rate = $settings['camp_margin_percent'] / 100;
        $referral_revenue = $referral['revenue_ex_vat'];
        $referral_cost = $costs['referral_cost_chf'];
        
        if ($referral_cost <= 0 || $referral_revenue <= 0) {
            return [
                'low' => null,
                'high' => null,
                'has_data' => false,
                'show_margin_prompt' => false,
            ];
        }
        
        // Low incrementality (25% of revenue is truly incremental)
        $low_profit = ($referral_revenue * self::REFERRAL_INCREMENTALITY_LOW) * $margin_rate;
        $low_roi = round((($low_profit - $referral_cost) / $referral_cost) * 100, 1);
        
        // High incrementality (75% of revenue is truly incremental)
        $high_profit = ($referral_revenue * self::REFERRAL_INCREMENTALITY_HIGH) * $margin_rate;
        $high_roi = round((($high_profit - $referral_cost) / $referral_cost) * 100, 1);
        
        return [
            'low' => $low_roi,
            'high' => $high_roi,
            'revenue' => $referral_revenue,
            'cost' => $referral_cost,
            'margin_rate' => $margin_rate,
            'incrementality_low' => self::REFERRAL_INCREMENTALITY_LOW,
            'incrementality_high' => self::REFERRAL_INCREMENTALITY_HIGH,
            'has_data' => true,
            'show_margin_prompt' => false,
            'tooltip' => sprintf(
                /* translators: 1: low incrementality percent, 2: high incrementality percent */
                __('Finance assumption: %1$d%%–%2$d%% of referred revenue is truly incremental. Will be replaced once we have a baseline.', 'intersoccer-referral'),
                (int) (self::REFERRAL_INCREMENTALITY_LOW * 100),
                (int) (self::REFERRAL_INCREMENTALITY_HIGH * 100)
            ),
        ];
    }
    
    /**
     * Calculate loyalty ROI with incrementality range
     * 
     * Uses LOYALTY_INCREMENTALITY_LOW (5%) to LOYALTY_INCREMENTALITY_HIGH (25%)
     * to estimate the true incremental value of extra spend from points holders.
     * 
     * @return array ROI range with low/high estimates
     */
    public function calculate_loyalty_roi_with_range() {
        if (!$this->has_camp_margin()) {
            return [
                'low' => null,
                'high' => null,
                'has_data' => false,
                'show_margin_prompt' => true,
            ];
        }
        
        $settings = $this->get_roi_settings();
        $loyalty = $this->calculate_loyalty_roi();
        $costs = $this->get_cost_breakdown();
        
        $margin_rate = $settings['camp_margin_percent'] / 100;
        $loyalty_extra_spend = $loyalty['total_extra_spend'];
        $loyalty_cost = $costs['loyalty_cost_chf'];
        
        if ($loyalty_cost <= 0 || $loyalty_extra_spend <= 0) {
            return [
                'low' => null,
                'high' => null,
                'has_data' => false,
                'show_margin_prompt' => false,
            ];
        }
        
        // Low incrementality (5% of extra spend is truly incremental)
        $low_profit = ($loyalty_extra_spend * self::LOYALTY_INCREMENTALITY_LOW) * $margin_rate;
        $low_roi = round((($low_profit - $loyalty_cost) / $loyalty_cost) * 100, 1);
        
        // High incrementality (25% of extra spend is truly incremental)
        $high_profit = ($loyalty_extra_spend * self::LOYALTY_INCREMENTALITY_HIGH) * $margin_rate;
        $high_roi = round((($high_profit - $loyalty_cost) / $loyalty_cost) * 100, 1);
        
        return [
            'low' => $low_roi,
            'high' => $high_roi,
            'extra_spend' => $loyalty_extra_spend,
            'cost' => $loyalty_cost,
            'margin_rate' => $margin_rate,
            'incrementality_low' => self::LOYALTY_INCREMENTALITY_LOW,
            'incrementality_high' => self::LOYALTY_INCREMENTALITY_HIGH,
            'has_data' => true,
            'show_margin_prompt' => false,
            'tooltip' => sprintf(
                /* translators: 1: low incrementality percent, 2: high incrementality percent */
                __('Finance assumption: %1$d%%–%2$d%% of extra spend is truly incremental. Will be replaced once we have a baseline.', 'intersoccer-referral'),
                (int) (self::LOYALTY_INCREMENTALITY_LOW * 100),
                (int) (self::LOYALTY_INCREMENTALITY_HIGH * 100)
            ),
        ];
    }
    
    /**
     * Get ROI range display (Combined)
     * 
     * Computes low/high bounds by applying each program's OWN incrementality range
     * to its own revenue/cost, then summing. Never applies one program's range to another.
     * 
     * Combined low = (referral_revenue × referral_low × margin) + (loyalty_spend × loyalty_low × margin) - total_cost
     * Combined high = (referral_revenue × referral_high × margin) + (loyalty_spend × loyalty_high × margin) - total_cost
     */
    public function get_roi_range_display() {
        if (!$this->has_camp_margin()) {
            return [
                'display' => '—',
                'has_data' => false,
                'is_range' => false,
                'show_margin_prompt' => true,
                'method' => null,
            ];
        }
        
        $settings = $this->get_roi_settings();
        $costs = $this->get_cost_breakdown();
        $referral = $this->calculate_referral_roi();
        $loyalty = $this->calculate_loyalty_roi();
        
        $margin_rate = $settings['camp_margin_percent'] / 100;
        $total_cost = $costs['total_real_cost'];
        
        $referral_revenue = $referral['revenue_ex_vat'];
        $loyalty_extra_spend = $loyalty['total_extra_spend'];
        
        if ($total_cost <= 0 || ($referral_revenue <= 0 && $loyalty_extra_spend <= 0)) {
            return [
                'display' => __('Not enough data', 'intersoccer-referral'),
                'has_data' => false,
                'is_range' => false,
                'show_margin_prompt' => false,
                'method' => null,
            ];
        }
        
        // Combined LOW: each program's low incrementality applied to its own revenue
        $low_referral_profit = ($referral_revenue * self::REFERRAL_INCREMENTALITY_LOW) * $margin_rate;
        $low_loyalty_profit = ($loyalty_extra_spend * self::LOYALTY_INCREMENTALITY_LOW) * $margin_rate;
        $low_total_profit = $low_referral_profit + $low_loyalty_profit;
        $low_roi = round((($low_total_profit - $total_cost) / $total_cost) * 100, 1);
        
        // Combined HIGH: each program's high incrementality applied to its own revenue
        $high_referral_profit = ($referral_revenue * self::REFERRAL_INCREMENTALITY_HIGH) * $margin_rate;
        $high_loyalty_profit = ($loyalty_extra_spend * self::LOYALTY_INCREMENTALITY_HIGH) * $margin_rate;
        $high_total_profit = $high_referral_profit + $high_loyalty_profit;
        $high_roi = round((($high_total_profit - $total_cost) / $total_cost) * 100, 1);
        
        if ($low_roi === $high_roi) {
            return [
                'display' => number_format($low_roi, 1) . '%',
                'has_data' => true,
                'is_range' => false,
                'show_margin_prompt' => false,
                'method' => 'incrementality',
            ];
        }
        
        return [
            'display' => number_format($low_roi, 1) . '% – ' . number_format($high_roi, 1) . '%',
            'has_data' => true,
            'is_range' => true,
            'min' => $low_roi,
            'max' => $high_roi,
            'show_margin_prompt' => false,
            'method' => 'incrementality',
            'method_note' => sprintf(
                /* translators: 1: referral low, 2: referral high, 3: loyalty low, 4: loyalty high */
                __('Referral %1$d%%–%2$d%%, Loyalty %3$d%%–%4$d%% incrementality (Finance assumption)', 'intersoccer-referral'),
                (int) (self::REFERRAL_INCREMENTALITY_LOW * 100),
                (int) (self::REFERRAL_INCREMENTALITY_HIGH * 100),
                (int) (self::LOYALTY_INCREMENTALITY_LOW * 100),
                (int) (self::LOYALTY_INCREMENTALITY_HIGH * 100)
            ),
            'tooltip' => __('Finance assumption: will be replaced once we have a baseline (e.g. repeat rate before program, or comparable families who don\'t hold points).', 'intersoccer-referral'),
            'attribution_rule' => __('Revenue attribution: A referred customer\'s first order counts as referral revenue. Their later orders count toward loyalty revenue if they hold points. Each order is counted once.', 'intersoccer-referral'),
        ];
    }
    
    /**
     * Get fully loaded ROI
     */
    public function calculate_fully_loaded_roi() {
        $costs = $this->get_cost_breakdown();
        $referral = $this->calculate_referral_roi();
        $loyalty = $this->calculate_loyalty_roi();
        $settings = $this->get_roi_settings();
        
        if (!$this->has_camp_margin()) {
            return [
                'has_data' => false,
                'show_margin_prompt' => true,
                'roi_display' => '—',
            ];
        }
        
        $margin_rate = $settings['camp_margin_percent'] / 100;
        
        $total_revenue = $referral['revenue_ex_vat'] + $loyalty['total_extra_spend'];
        $total_profit = $total_revenue * $margin_rate;
        $total_cost = $costs['total_fully_loaded_chf'];
        
        $roi_percentage = null;
        $roi_display = '';
        $has_data = $total_cost > 0;
        
        if ($total_cost > 0) {
            $roi_percentage = round((($total_profit - $total_cost) / $total_cost) * 100, 1);
            $roi_display = number_format($roi_percentage, 1) . '%';
        } else {
            $roi_display = __('Not enough data', 'intersoccer-referral');
        }
        
        return [
            'total_revenue' => $total_revenue,
            'total_profit' => $total_profit,
            'total_cost' => $total_cost,
            'roi_percentage' => $roi_percentage,
            'roi_display' => $roi_display,
            'has_data' => $has_data,
            'is_estimate' => true,
            'show_margin_prompt' => false,
        ];
    }
    
    /**
     * Get liability trend by month (12 months)
     */
    private function get_liability_trend() {
        $points_log = $this->table_prefix . 'intersoccer_points_log';
        
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $month_end = wp_date('Y-m-t', strtotime("-$i months"));
            $label = wp_date('M Y', strtotime("-$i months"));
            
            // Calculate cumulative liability as of month end
            $liability = (float) $this->wpdb->get_var($this->wpdb->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN points_amount > 0 THEN points_amount ELSE 0 END), 0)
                    - COALESCE(ABS(SUM(CASE WHEN points_amount < 0 THEN points_amount ELSE 0 END)), 0)
                FROM {$points_log}
                WHERE created_at <= %s
            ", $month_end . ' 23:59:59'));
            
            $data[] = [
                'label' => $label,
                'value' => max(0, $liability),
            ];
        }
        
        return $data;
    }
    
    /**
     * Get points activity by month
     */
    public function get_points_activity() {
        $points_log = $this->table_prefix . 'intersoccer_points_log';
        
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $month_start = wp_date('Y-m-01', strtotime("-$i months"));
            $month_end = wp_date('Y-m-t', strtotime("-$i months"));
            $label = wp_date('M Y', strtotime("-$i months"));
            
            $earned = (float) $this->wpdb->get_var($this->wpdb->prepare("
                SELECT COALESCE(SUM(points_amount), 0)
                FROM {$points_log}
                WHERE points_amount > 0
                  AND transaction_type NOT IN ('gift_sent', 'gift_received', 'gift_returned')
                  AND created_at BETWEEN %s AND %s
            ", $month_start, $month_end . ' 23:59:59'));
            
            $redeemed = abs((float) $this->wpdb->get_var($this->wpdb->prepare("
                SELECT COALESCE(SUM(points_amount), 0)
                FROM {$points_log}
                WHERE transaction_type = 'points_redemption'
                  AND created_at BETWEEN %s AND %s
            ", $month_start, $month_end . ' 23:59:59')));
            
            $expired = abs((float) $this->wpdb->get_var($this->wpdb->prepare("
                SELECT COALESCE(SUM(points_amount), 0)
                FROM {$points_log}
                WHERE transaction_type = 'points_expired'
                  AND created_at BETWEEN %s AND %s
            ", $month_start, $month_end . ' 23:59:59')));
            
            $data[] = [
                'label' => $label,
                'earned' => $earned,
                'redeemed' => $redeemed,
                'expired' => $expired,
            ];
        }
        
        return $data;
    }
    
    /**
     * Get new families by month
     */
    public function get_referral_families_by_month() {
        $referrals_table = $this->table_prefix . 'intersoccer_referrals';
        
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $month_start = wp_date('Y-m-01', strtotime("-$i months"));
            $month_end = wp_date('Y-m-t', strtotime("-$i months"));
            $label = wp_date('M Y', strtotime("-$i months"));
            
            // Get referrals in this month
            $referrals = $this->wpdb->get_results($this->wpdb->prepare("
                SELECT DISTINCT customer_id, order_id
                FROM {$referrals_table}
                WHERE created_at BETWEEN %s AND %s
                  AND order_id IS NOT NULL
            ", $month_start, $month_end . ' 23:59:59'));
            
            $new_families = 0;
            $counted = [];
            foreach ($referrals as $ref) {
                // Handle both object and array formats (arrays may come from test mocks)
                $customer_id = is_object($ref) ? ($ref->customer_id ?? null) : ($ref['customer_id'] ?? null);
                $order_id = is_object($ref) ? ($ref->order_id ?? null) : ($ref['order_id'] ?? null);
                
                if (!$customer_id || !$order_id) continue;
                if (isset($counted[$customer_id])) continue;
                $counted[$customer_id] = true;
                
                $order = wc_get_order($order_id);
                if ($order && in_array($order->get_status(), ['completed', 'processing'])) {
                    if ($this->is_new_family($customer_id, $order_id)) {
                        $new_families++;
                    }
                }
            }
            
            $data[] = [
                'label' => $label,
                'value' => $new_families,
            ];
        }
        
        return $data;
    }
}

} // end class_exists check
