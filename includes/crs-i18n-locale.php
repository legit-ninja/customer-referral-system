<?php
/**
 * WPML-aware customer admin-ajax URLs and AJAX textdomain bootstrap.
 *
 * @package InterSoccer\Referral
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPML language code for the current request (e.g. fr, de), or empty.
 */
function intersoccer_crs_get_wpml_language_code() {
    $lang = apply_filters('wpml_current_language', null);
    if (!is_string($lang)) {
        return '';
    }
    $lang = trim($lang);
    return $lang === '' ? '' : $lang;
}

/**
 * Map WPML language code to WordPress locale for CRS .mo files.
 */
function intersoccer_crs_locale_from_wpml_code($code) {
    $code = trim((string) $code);
    if ($code === '') {
        return function_exists('determine_locale') ? determine_locale() : 'en_US';
    }
    if (strpos($code, '_') !== false) {
        return $code;
    }
    $map = [
        'en' => 'en_US',
        'fr' => 'fr_FR',
        'de' => 'de_DE',
    ];

    return $map[$code] ?? $code;
}

/**
 * Resolve plugin .mo path for a locale.
 */
function intersoccer_crs_mofile_for_locale($locale) {
    $locale = (string) $locale;
    if ($locale === '' || !defined('INTERSOCCER_REFERRAL_PATH')) {
        return '';
    }

    $base = INTERSOCCER_REFERRAL_PATH . 'languages/intersoccer-referral-';
    if (is_readable($base . $locale . '.mo')) {
        return $base . $locale . '.mo';
    }

    $short = strlen($locale) >= 2 ? substr($locale, 0, 2) : $locale;
    if (is_readable($base . $short . '.mo')) {
        return $base . $short . '.mo';
    }

    return '';
}

/**
 * Reload intersoccer-referral textdomain for a specific locale.
 */
function intersoccer_crs_reload_textdomain_for_locale($locale) {
    $domain = 'intersoccer-referral';
    if (function_exists('is_textdomain_loaded') && is_textdomain_loaded($domain) && function_exists('unload_textdomain')) {
        unload_textdomain($domain, true);
    }

    $mofile = intersoccer_crs_mofile_for_locale($locale);
    if ($mofile !== '' && function_exists('load_textdomain')) {
        $ok = (bool) load_textdomain($domain, $mofile, $locale);
        if ($ok) {
            $GLOBALS['intersoccer_crs_textdomain_locale'] = $locale;
        }
        return $ok;
    }

    if (class_exists('InterSoccer_Referral_System')) {
        InterSoccer_Referral_System::get_instance()->load_referral_textdomain();
        return true;
    }

    return false;
}

/**
 * admin-ajax.php for storefront scripts, with optional WPML lang query arg.
 *
 * @param array<string, string> $extra_args
 * @param bool                  $include_wpml_lang
 */
function intersoccer_crs_frontend_admin_ajax_url(array $extra_args = [], $include_wpml_lang = true) {
    $args = $extra_args;
    if ($include_wpml_lang) {
        $lang = intersoccer_crs_get_wpml_language_code();
        if ($lang !== '') {
            $args['lang'] = $lang;
        }
    }

    $url = admin_url('admin-ajax.php');
    if ($args !== []) {
        $url = add_query_arg($args, $url);
    }

    return $url;
}

/**
 * On customer admin-ajax, switch WPML + reload CRS catalog when lang is present.
 */
function intersoccer_crs_bootstrap_customer_ajax_language() {
    if (!function_exists('wp_doing_ajax') || !wp_doing_ajax()) {
        return;
    }

    if (function_exists('intersoccer_crs_is_admin_english_context')
        && intersoccer_crs_is_admin_english_context()
    ) {
        return;
    }

    $lang = '';
    if (isset($_REQUEST['lang'])) {
        $lang = sanitize_text_field(wp_unslash((string) $_REQUEST['lang']));
    }
    if ($lang === '') {
        return;
    }

    if (has_action('wpml_switch_language')) {
        do_action('wpml_switch_language', $lang);
    }

    intersoccer_crs_reload_textdomain_for_locale(intersoccer_crs_locale_from_wpml_code($lang));
}

add_action('admin_init', 'intersoccer_crs_bootstrap_customer_ajax_language', 0);
