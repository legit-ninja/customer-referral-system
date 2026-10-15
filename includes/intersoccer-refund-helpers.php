<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('intersoccer_proportional_floor_int')) {
    /**
     * Floor a whole-number proportional share (points), avoiding float drift (e.g. 100 × 0.29).
     *
     * @param int   $whole
     * @param float $fraction
     * @return int
     */
    function intersoccer_proportional_floor_int($whole, $fraction) {
        $whole = (int) $whole;
        $fraction = (float) $fraction;
        if ($whole <= 0 || $fraction <= 0) {
            return 0;
        }
        if ($fraction >= 1.0) {
            return $whole;
        }

        return (int) min($whole, floor($whole * $fraction + 1e-9));
    }
}

if (!function_exists('intersoccer_money_amount_to_cents')) {
    /**
     * @param float $amount
     * @return int
     */
    function intersoccer_money_amount_to_cents($amount) {
        return (int) round((float) $amount * 100);
    }
}

if (!function_exists('intersoccer_money_floor_from_cents')) {
    /**
     * @param int $cents
     * @return float
     */
    function intersoccer_money_floor_from_cents($cents) {
        return (int) $cents / 100.0;
    }
}

if (!function_exists('intersoccer_money_floor_to_cents')) {
    /**
     * Floor a CHF amount to whole cents (never round up).
     *
     * @param float $amount
     * @return float
     */
    function intersoccer_money_floor_to_cents($amount) {
        $cents = intersoccer_money_amount_to_cents($amount);

        return intersoccer_money_floor_from_cents((int) floor($cents + 1e-9));
    }
}

if (!function_exists('intersoccer_proportional_floor_money')) {
    /**
     * Proportional commission share floored to cents.
     *
     * @param float $amount
     * @param float $fraction
     * @return float
     */
    function intersoccer_proportional_floor_money($amount, $fraction) {
        $amount = max(0.0, (float) $amount);
        $fraction = (float) $fraction;
        if ($amount <= 0 || $fraction <= 0) {
            return 0.0;
        }
        if ($fraction >= 1.0) {
            return intersoccer_money_floor_to_cents($amount);
        }

        $cents = intersoccer_money_amount_to_cents($amount);
        $target_cents = (int) floor($cents * $fraction + 1e-9);

        return intersoccer_money_floor_from_cents(min($cents, $target_cents));
    }
}

if (!function_exists('intersoccer_refund_order_lock_option_name')) {
    /**
     * @param int $order_id
     * @return string
     */
    function intersoccer_refund_order_lock_option_name($order_id) {
        return 'intersoccer_refund_lock_' . (int) $order_id;
    }
}

if (!function_exists('intersoccer_acquire_order_refund_lock')) {
    /**
     * Atomic mutex for per-order refund reversal (add_option-based).
     *
     * @param int $order_id
     * @param int $stale_seconds
     * @return bool
     */
    function intersoccer_acquire_order_refund_lock($order_id, $stale_seconds = 60) {
        $order_id = (int) $order_id;
        if ($order_id <= 0) {
            return false;
        }

        $option = intersoccer_refund_order_lock_option_name($order_id);
        $now = time();
        if (add_option($option, $now, '', 'no')) {
            return true;
        }

        $held_at = (int) get_option($option, 0);
        if ($held_at > 0 && ($now - $held_at) >= (int) $stale_seconds) {
            delete_option($option);
            return add_option($option, $now, '', 'no');
        }

        return false;
    }
}

if (!function_exists('intersoccer_release_order_refund_lock')) {
    /**
     * @param int $order_id
     * @return void
     */
    function intersoccer_release_order_refund_lock($order_id) {
        delete_option(intersoccer_refund_order_lock_option_name((int) $order_id));
    }
}

if (!function_exists('intersoccer_coach_credit_lock_option_name')) {
    /**
     * @param int $coach_id
     * @return string
     */
    function intersoccer_coach_credit_lock_option_name($coach_id) {
        return 'intersoccer_coach_credit_lock_' . (int) $coach_id;
    }
}

if (!function_exists('intersoccer_acquire_coach_credit_lock')) {
    /**
     * Mutex for coach CHF credit clawbacks (separate orders can share one coach).
     *
     * @param int $coach_id
     * @param int $stale_seconds
     * @return bool
     */
    function intersoccer_acquire_coach_credit_lock($coach_id, $stale_seconds = 60) {
        $coach_id = (int) $coach_id;
        if ($coach_id <= 0) {
            return false;
        }

        $option = intersoccer_coach_credit_lock_option_name($coach_id);
        $now = time();
        if (add_option($option, $now, '', 'no')) {
            return true;
        }

        $held_at = (int) get_option($option, 0);
        if ($held_at > 0 && ($now - $held_at) >= (int) $stale_seconds) {
            delete_option($option);
            return add_option($option, $now, '', 'no');
        }

        return false;
    }
}

if (!function_exists('intersoccer_release_coach_credit_lock')) {
    /**
     * @param int $coach_id
     * @return void
     */
    function intersoccer_release_coach_credit_lock($coach_id) {
        delete_option(intersoccer_coach_credit_lock_option_name((int) $coach_id));
    }
}

if (!function_exists('intersoccer_refund_sync_retry_hook')) {
    /**
     * Cron hook for deferred per-order refund sync when the mutex is busy.
     *
     * @return string
     */
    function intersoccer_refund_sync_retry_hook() {
        return 'intersoccer_order_refund_sync_retry';
    }
}

if (!function_exists('intersoccer_order_refund_sync_retry_max_attempts')) {
    /**
     * Maximum deferred refund-sync retries before giving up (per order + channel).
     *
     * @return int
     */
    function intersoccer_order_refund_sync_retry_max_attempts() {
        $default = 5;
        if (function_exists('apply_filters')) {
            return max(1, (int) apply_filters('intersoccer_order_refund_sync_retry_max_attempts', $default));
        }
        return $default;
    }
}

if (!function_exists('intersoccer_abandon_order_refund_sync_retry')) {
    /**
     * Stop scheduling further refund sync retries after repeated failures.
     *
     * @param int    $order_id
     * @param string $channel points|commission
     * @param array  $payload
     * @return void
     */
    function intersoccer_abandon_order_refund_sync_retry($order_id, $channel, array $payload = []) {
        $order_id = (int) $order_id;
        $channel = (string) $channel;
        if ($order_id <= 0 || $channel === '') {
            return;
        }

        if (function_exists('intersoccer_referral_log')) {
            intersoccer_referral_log(
                'InterSoccer: Refund sync abandoned for order ' . $order_id
                . ' (' . $channel . ') after '
                . intersoccer_order_refund_sync_retry_max_attempts()
                . ' failed retries'
            );
        }

        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
        if ($order && method_exists($order, 'add_order_note')) {
            $order->add_order_note(
                'InterSoccer refund sync stopped after repeated failures. Please reconcile points and commission manually.'
            );
        }
    }
}

if (!function_exists('intersoccer_schedule_order_refund_sync_retry_after_failure')) {
    /**
     * Schedule the next retry or abandon when the attempt budget is exhausted.
     *
     * @param int    $order_id
     * @param string $channel points|commission
     * @param array  $payload
     * @param int    $delay_seconds
     * @return bool Whether a new event was scheduled.
     */
    function intersoccer_schedule_order_refund_sync_retry_after_failure(
        $order_id,
        $channel,
        array $payload = [],
        $delay_seconds = 60
    ) {
        $completed = (int) ($payload['retry_attempt'] ?? 0);
        $next = $completed + 1;
        if ($next > intersoccer_order_refund_sync_retry_max_attempts()) {
            intersoccer_abandon_order_refund_sync_retry($order_id, $channel, $payload);
            return false;
        }
        $payload['retry_attempt'] = $next;
        return intersoccer_schedule_order_refund_sync_retry($order_id, $channel, $payload, $delay_seconds);
    }
}

if (!function_exists('intersoccer_schedule_order_refund_sync_retry')) {
    /**
     * Schedule a single retry for points or commission refund sync (deduped per order + channel).
     *
     * @param int    $order_id
     * @param string $channel points|commission
     * @param array  $payload reason, refund_id, force_full, etc.
     * @param int    $delay_seconds
     * @return bool Whether a new event was scheduled.
     */
    function intersoccer_schedule_order_refund_sync_retry($order_id, $channel, array $payload = [], $delay_seconds = 60) {
        $order_id = (int) $order_id;
        $channel = (string) $channel;
        if ($order_id <= 0 || $channel === '') {
            return false;
        }

        $hook = intersoccer_refund_sync_retry_hook();
        $args = [$order_id, $channel, $payload];
        if (function_exists('wp_next_scheduled') && wp_next_scheduled($hook, $args)) {
            return false;
        }

        if (!function_exists('wp_schedule_single_event')) {
            return false;
        }

        return (bool) wp_schedule_single_event(time() + max(1, (int) $delay_seconds), $hook, $args);
    }
}

if (!function_exists('intersoccer_classify_adjustment_cost_bucket')) {
    /**
     * Ledger / ROI: classify a positive adjustment row as correction cost vs goodwill gift.
     *
     * @param string $transaction_type
     * @param string $description
     * @param string $metadata_json
     * @return string correction|goodwill
     */
    function intersoccer_classify_adjustment_cost_bucket($transaction_type, $description, $metadata_json = '') {
        $transaction_type = (string) $transaction_type;
        $desc = strtolower((string) $description);

        if ($transaction_type === 'admin_reset' || $transaction_type === 'admin_balance_set') {
            return 'correction';
        }

        $adjustment_reason = '';
        if (is_string($metadata_json) && $metadata_json !== '') {
            $decoded = json_decode($metadata_json, true);
            if (is_array($decoded) && isset($decoded['adjustment_reason'])) {
                $adjustment_reason = (string) $decoded['adjustment_reason'];
            }
        }

        if ($adjustment_reason === 'correction') {
            return 'correction';
        }
        if ($adjustment_reason === 'goodwill' || $adjustment_reason === 'bonus') {
            return 'goodwill';
        }

        $correction_keywords = ['cancel', 'error', 'incorrect', 'fix', 'return', 'refund', 'mistake', 'wrong', 'changed', 'test', 'testing', 'example', 'e2e', 'classic test'];
        $goodwill_keywords = ['goodwill', 'bonus', 'survey', 'reward', 'compensation', 'apolog', 'gesture', 'exceptional', 'not mature', 'mature enough', 'referral', 'referal', 'informed us'];

        foreach ($correction_keywords as $kw) {
            if ($kw !== '' && strpos($desc, $kw) !== false) {
                return 'correction';
            }
        }
        foreach ($goodwill_keywords as $kw) {
            if ($kw !== '' && strpos($desc, $kw) !== false) {
                return 'goodwill';
            }
        }

        return 'goodwill';
    }
}

if (!function_exists('intersoccer_adjustment_row_is_untagged_default')) {
    /**
     * True when a positive adjustment row has no tag/metadata reason and no description keyword match.
     *
     * @param string $transaction_type
     * @param string $description
     * @param string $metadata_json
     * @return bool
     */
    function intersoccer_adjustment_row_is_untagged_default($transaction_type, $description, $metadata_json = '') {
        $transaction_type = (string) $transaction_type;
        if ($transaction_type === 'admin_reset' || $transaction_type === 'admin_balance_set') {
            return false;
        }

        $adjustment_reason = '';
        if (is_string($metadata_json) && $metadata_json !== '') {
            $decoded = json_decode($metadata_json, true);
            if (is_array($decoded) && isset($decoded['adjustment_reason'])) {
                $adjustment_reason = (string) $decoded['adjustment_reason'];
            }
        }
        if ($adjustment_reason !== '') {
            return false;
        }

        $desc = strtolower((string) $description);
        $keywords = [
            'cancel', 'error', 'incorrect', 'fix', 'return', 'refund', 'mistake', 'wrong', 'changed', 'test', 'testing', 'example', 'e2e', 'classic test',
            'goodwill', 'bonus', 'survey', 'reward', 'compensation', 'apolog', 'gesture', 'exceptional', 'not mature', 'mature enough', 'referral', 'referal', 'informed us',
        ];
        foreach ($keywords as $kw) {
            if ($kw !== '' && strpos($desc, $kw) !== false) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('intersoccer_positive_ledger_lot_is_gift')) {
    /**
     * Whether a positive points lot is a protected gift (not clawed on earned reversal).
     *
     * @param string $transaction_type
     * @param int    $points_amount
     * @param string $metadata_json
     * @param string $description
     * @return bool
     */
    function intersoccer_positive_ledger_lot_is_gift($transaction_type, $points_amount, $metadata_json = '', $description = '') {
        $transaction_type = (string) $transaction_type;
        $points_amount = (int) $points_amount;
        if ($points_amount <= 0) {
            return false;
        }

        if (in_array($transaction_type, ['admin_adjustment', 'manual_adjustment', 'points_refund'], true)) {
            return intersoccer_classify_adjustment_cost_bucket($transaction_type, $description, $metadata_json) !== 'correction';
        }

        if ($transaction_type === 'admin_reset' || $transaction_type === 'admin_balance_set') {
            return false;
        }

        $gift_types = (array) apply_filters('intersoccer_points_gift_credit_types', ['gift_received', 'admin_adjustment']);

        return in_array($transaction_type, array_map('strval', $gift_types), true);
    }
}
