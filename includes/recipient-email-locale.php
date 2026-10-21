<?php
/**
 * Resolve recipient locale and send CRS emails with switch_to_locale when needed.
 *
 * @package InterSoccer\Referral
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Locale for a user (WPML user language when available, else get_user_locale).
 *
 * @param int $user_id User ID.
 */
function intersoccer_crs_get_user_locale_for_email($user_id) {
    $user_id = (int) $user_id;
    if ($user_id > 0) {
        $lang = apply_filters('wpml_user_language', null, $user_id);
        if (is_string($lang) && $lang !== '') {
            $locale = apply_filters('wpml_locale', null, $lang);
            if (is_string($locale) && $locale !== '') {
                return $locale;
            }
        }
    }

    if ($user_id > 0 && function_exists('get_user_locale')) {
        return get_user_locale($user_id);
    }

    return function_exists('determine_locale') ? determine_locale() : get_locale();
}

/**
 * Locale for a WooCommerce order (WPML order language, else customer user locale).
 *
 * @param WC_Order|object|null $order Order object.
 */
function intersoccer_crs_get_order_locale_for_email($order) {
    if (!is_object($order)) {
        return function_exists('determine_locale') ? determine_locale() : get_locale();
    }

    $order_id = method_exists($order, 'get_id') ? (int) $order->get_id() : 0;
    if ($order_id > 0) {
        $lang = apply_filters(
            'wpml_element_language_code',
            null,
            [
                'element_id' => $order_id,
                'element_type' => 'post_shop_order',
            ]
        );
        if (is_string($lang) && $lang !== '') {
            $locale = apply_filters('wpml_locale', null, $lang);
            if (is_string($locale) && $locale !== '') {
                return $locale;
            }
        }
    }

    if (method_exists($order, 'get_meta')) {
        $lang = $order->get_meta('wpml_language');
        if (is_string($lang) && $lang !== '') {
            $locale = apply_filters('wpml_locale', null, $lang);
            if (is_string($locale) && $locale !== '') {
                return $locale;
            }
        }
    }

    $customer_id = method_exists($order, 'get_customer_id') ? (int) $order->get_customer_id() : 0;
    if ($customer_id > 0) {
        return intersoccer_crs_get_user_locale_for_email($customer_id);
    }

    return function_exists('determine_locale') ? determine_locale() : get_locale();
}

/**
 * Run a callback under a specific locale; restores previous locale when possible.
 *
 * @param string   $locale   Target locale.
 * @param callable $callback Callback.
 * @return mixed
 */
function intersoccer_crs_with_email_locale($locale, $callback) {
    if (!is_callable($callback)) {
        return null;
    }

    $locale = is_string($locale) && $locale !== ''
        ? $locale
        : (function_exists('determine_locale') ? determine_locale() : get_locale());

    $switched = function_exists('switch_to_locale') ? switch_to_locale($locale) : false;
    try {
        return $callback();
    } finally {
        if ($switched && function_exists('restore_previous_locale')) {
            restore_previous_locale();
        }
    }
}

/**
 * @param int      $user_id  Recipient user ID.
 * @param callable $callback Builds and sends mail; return value from wp_mail.
 * @return mixed
 */
function intersoccer_crs_mail_for_user($user_id, $callback) {
    return intersoccer_crs_with_email_locale(
        intersoccer_crs_get_user_locale_for_email($user_id),
        $callback
    );
}

/**
 * @param WC_Order|object|null $order    Order.
 * @param callable             $callback Builds and sends mail.
 * @return mixed
 */
function intersoccer_crs_mail_for_order($order, $callback) {
    return intersoccer_crs_with_email_locale(
        intersoccer_crs_get_order_locale_for_email($order),
        $callback
    );
}
