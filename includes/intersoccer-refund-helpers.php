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
