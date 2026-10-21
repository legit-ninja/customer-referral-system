<?php
/**
 * Force CRS admin UI strings to English (office language).
 *
 * @package InterSoccer\Referral
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRS text domains controlled by this helper.
 *
 * @return string[]
 */
function intersoccer_crs_admin_english_text_domains() {
    return [
        'intersoccer-referral',
        'Intersoccer Referral System',
    ];
}

/**
 * Whether the current request is a storefront-originated admin-ajax call.
 */
function intersoccer_crs_is_frontend_originated_ajax() {
    if (!function_exists('wp_doing_ajax') || !wp_doing_ajax()) {
        return false;
    }

    $referer = function_exists('wp_get_raw_referer') ? wp_get_raw_referer() : '';
    if ($referer === '' || $referer === false) {
        return true;
    }

    $admin_prefix = admin_url();
    return strpos($referer, $admin_prefix) !== 0;
}

/**
 * True when CRS gettext should return the original English msgid (admin screens).
 */
function intersoccer_crs_is_admin_english_context() {
    $filtered = apply_filters('intersoccer_crs_admin_force_english', null);
    if ($filtered !== null) {
        return (bool) $filtered;
    }

    if (!function_exists('is_admin') || !is_admin()) {
        return false;
    }

    if (function_exists('is_locale_switched') && is_locale_switched()) {
        return false;
    }

    if (intersoccer_crs_is_frontend_originated_ajax()) {
        return false;
    }

    return true;
}

/**
 * @param string $domain Text domain.
 */
function intersoccer_crs_is_admin_english_domain($domain) {
    return in_array($domain, intersoccer_crs_admin_english_text_domains(), true);
}

/**
 * @param string $translation Translated text.
 * @param string $text        Original msgid.
 * @param string $domain      Text domain.
 * @return string
 */
function intersoccer_crs_filter_gettext($translation, $text, $domain) {
    if (!intersoccer_crs_is_admin_english_domain($domain) || !intersoccer_crs_is_admin_english_context()) {
        return $translation;
    }

    return $text;
}

/**
 * @param string $translation Translated text.
 * @param string $single      Singular msgid.
 * @param string $plural      Plural msgid.
 * @param int    $number      Count.
 * @param string $domain      Text domain.
 * @return string
 */
function intersoccer_crs_filter_ngettext($translation, $single, $plural, $number, $domain) {
    if (!intersoccer_crs_is_admin_english_domain($domain) || !intersoccer_crs_is_admin_english_context()) {
        return $translation;
    }

    return (int) $number === 1 ? $single : $plural;
}

/**
 * @param string $translation Translated text.
 * @param string $text        Original msgid.
 * @param string $context     Context.
 * @param string $domain      Text domain.
 * @return string
 */
function intersoccer_crs_filter_gettext_with_context($translation, $text, $context, $domain) {
    if (!intersoccer_crs_is_admin_english_domain($domain) || !intersoccer_crs_is_admin_english_context()) {
        return $translation;
    }

    return $text;
}

/**
 * @param string $translation Translated text.
 * @param string $single      Singular msgid.
 * @param string $plural      Plural msgid.
 * @param int    $number      Count.
 * @param string $context     Context.
 * @param string $domain      Text domain.
 * @return string
 */
function intersoccer_crs_filter_ngettext_with_context($translation, $single, $plural, $number, $context, $domain) {
    if (!intersoccer_crs_is_admin_english_domain($domain) || !intersoccer_crs_is_admin_english_context()) {
        return $translation;
    }

    return (int) $number === 1 ? $single : $plural;
}

add_filter('gettext_intersoccer-referral', 'intersoccer_crs_filter_gettext', 10, 3);
add_filter('ngettext_intersoccer-referral', 'intersoccer_crs_filter_ngettext', 10, 5);
add_filter('gettext_with_context_intersoccer-referral', 'intersoccer_crs_filter_gettext_with_context', 10, 4);
add_filter('ngettext_with_context_intersoccer-referral', 'intersoccer_crs_filter_ngettext_with_context', 10, 6);
