<?php
/**
 * Bootstrap for PHPUnit tests
 */

if (!defined('INTERSOCCER_PHPUNIT')) {
    define('INTERSOCCER_PHPUNIT', true);
}

// Define WordPress constants for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}
define('WP_PLUGIN_DIR', dirname(__DIR__));
define('WP_CONTENT_DIR', dirname(WP_PLUGIN_DIR));

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!defined('YEAR_IN_SECONDS')) {
    define('YEAR_IN_SECONDS', DAY_IN_SECONDS * 365);
}

if (!defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', DAY_IN_SECONDS * 7);
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $callback) {
        global $mock_shortcodes;
        if (!is_array($mock_shortcodes)) {
            $mock_shortcodes = [];
        }
        $mock_shortcodes[$tag] = $callback;
        return true;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        global $mock_user_capabilities;
        if (is_array($mock_user_capabilities) && array_key_exists($capability, $mock_user_capabilities)) {
            return (bool) $mock_user_capabilities[$capability];
        }
        return true;
    }
}

if (!function_exists('is_account_page')) {
    function is_account_page() {
        global $mock_is_account_page;
        return (bool) ($mock_is_account_page ?? false);
    }
}

if (!function_exists('get_avatar')) {
    function get_avatar($id_or_email, $size = 96, $default = '', $alt = '', $args = null) {
        return '<img src="https://example.com/avatar.jpg" width="' . (int) $size . '" alt="" />';
    }
}

if (!function_exists('human_time_diff')) {
    function human_time_diff($from, $to = null) {
        if ($to === null) {
            $to = time();
        }
        $diff = abs((int) $to - (int) $from);
        $days = (int) floor($diff / DAY_IN_SECONDS);
        return $days . ' days';
    }
}

if (!function_exists('get_query_var')) {
    function get_query_var($var, $default = false) {
        global $mock_query_vars;
        if (!is_array($mock_query_vars) || !array_key_exists($var, $mock_query_vars)) {
            return $default;
        }
        return $mock_query_vars[$var];
    }
}

if (!function_exists('wp_debug_backtrace_summary')) {
    function wp_debug_backtrace_summary() {
        global $mock_backtrace_summary;
        return $mock_backtrace_summary ?? '';
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('setcookie')) {
    function setcookie($name, $value = '', $expires = 0, $path = '', $domain = '', $secure = false, $httponly = false) {
        global $mock_cookies;
        if (!is_array($mock_cookies)) {
            $mock_cookies = [];
        }
        $mock_cookies[$name] = $value;
        return true;
    }
}

if (!function_exists('intersoccer_get_coach_tier')) {
    function intersoccer_get_coach_tier($coach_id = null) {
        if (class_exists('InterSoccer_Commission_Manager')) {
            return InterSoccer_Commission_Manager::get_coach_tier((int) ($coach_id ?: 0));
        }

        return 'Bronze';
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title($post = 0) {
        return is_object($post) && isset($post->ID) ? 'Event ' . $post->ID : 'Test Event';
    }
}

if (!function_exists('get_avatar_url')) {
    function get_avatar_url($id, $args = []) {
        return 'https://example.com/avatar/' . (int) $id;
    }
}

if (!function_exists('intersoccer_referral_log')) {
    function intersoccer_referral_log($message) {
        // no-op in tests
    }
}

if (!function_exists('intersoccer_referral_get_first_order_discount_amount')) {
    function intersoccer_referral_get_first_order_discount_amount($order) {
        if (is_object($order) && method_exists($order, 'get_meta')) {
            return (float) $order->get_meta('_intersoccer_first_order_discount_amount', true);
        }
        return 0.0;
    }
}

if (!function_exists('intersoccer_referral_get_coach_referral_bonus_points')) {
    function intersoccer_referral_get_coach_referral_bonus_points() {
        return max(0, (int) get_option('intersoccer_coach_referral_bonus_points', 50));
    }
}

// Include WordPress test utilities if available, otherwise mock
if (file_exists(dirname(__DIR__, 2) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
}

// Mock WordPress options storage
global $mock_options;
if (!isset($mock_options)) {
    $mock_options = [
        'intersoccer_commission_first' => 15,
        'intersoccer_commission_second' => 7.5,
        'intersoccer_commission_third' => 5,
        'intersoccer_loyalty_bonus_first' => 5,
        'intersoccer_loyalty_bonus_second' => 8,
        'intersoccer_loyalty_bonus_third' => 15,
        'intersoccer_retention_season_2' => 25,
        'intersoccer_retention_season_3' => 50,
        'intersoccer_network_effect_bonus' => 15,
        'intersoccer_tier_platinum' => 20,
        'intersoccer_tier_gold' => 10,
        'intersoccer_tier_silver' => 5,
    ];
}

// Mock WordPress functions if not available
if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        global $mock_options;
        return array_key_exists($key, $mock_options) ? $mock_options[$key] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($key, $value) {
        global $mock_options;
        $mock_options[$key] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($key) {
        global $mock_options;
        if (!array_key_exists($key, $mock_options)) {
            return false;
        }
        unset($mock_options[$key]);
        return true;
    }
}

global $mock_transients;
if (!isset($mock_transients)) {
    $mock_transients = [];
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        global $mock_transients;
        return $mock_transients[$transient] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        global $mock_transients;
        $mock_transients[$transient] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        global $mock_transients;
        if (!array_key_exists($transient, $mock_transients)) {
            return false;
        }
        unset($mock_transients[$transient]);
        return true;
    }
}

global $mock_cron;
if (!isset($mock_cron)) {
    $mock_cron = [];
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = []) {
        global $mock_cron;
        return $mock_cron[$hook] ?? false;
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) {
        global $mock_cron;
        $mock_cron[$hook] = $timestamp;
        return true;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
        return null;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value, ...$args) {
        return $value;
    }
}

// Mock post meta storage
global $mock_post_meta;
$mock_post_meta = [];

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false) {
        global $mock_post_meta;
        $post_id = (int) $post_id;
        if (!isset($mock_post_meta[$post_id])) {
            return $single ? '' : [];
        }

        if ($key === '') {
            return $mock_post_meta[$post_id];
        }

        if (!array_key_exists($key, $mock_post_meta[$post_id])) {
            return $single ? '' : [];
        }

        $value = $mock_post_meta[$post_id][$key];
        return $single ? $value : [$value];
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $key, $value) {
        global $mock_post_meta;
        $post_id = (int) $post_id;
        if (!isset($mock_post_meta[$post_id])) {
            $mock_post_meta[$post_id] = [];
        }
        $mock_post_meta[$post_id][$key] = $value;
        return true;
    }
}

if (!function_exists('delete_post_meta')) {
    function delete_post_meta($post_id, $key, $value = '') {
        global $mock_post_meta;
        $post_id = (int) $post_id;
        if (!isset($mock_post_meta[$post_id]) || !array_key_exists($key, $mock_post_meta[$post_id])) {
            return false;
        }

        if ($value !== '' && $mock_post_meta[$post_id][$key] !== $value) {
            return false;
        }

        unset($mock_post_meta[$post_id][$key]);
        return true;
    }
}

if (!function_exists('is_ssl')) {
    function is_ssl() {
        return false;
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key, $single = true) {
        global $mock_user_meta;
        if (!isset($mock_user_meta[$user_id])) {
            $mock_user_meta[$user_id] = [];
        }

        if (!array_key_exists($key, $mock_user_meta[$user_id])) {
            return $single ? '' : [];
        }

        return $mock_user_meta[$user_id][$key];
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $key, $value) {
        global $mock_user_meta;
        if (!isset($mock_user_meta[$user_id])) {
            $mock_user_meta[$user_id] = [];
        }
        $mock_user_meta[$user_id][$key] = $value;
        return true;
    }
}

if (!function_exists('current_time')) {
    function current_time($type = 'timestamp', $gmt = false) {
        return date($type === 'mysql' ? 'Y-m-d H:i:s' : 'U');
    }
}

// Mock WooCommerce order class
if (!class_exists('WC_Order')) {
    class WC_Order {
        private $total = 100;
        private $tax = 10;
        private $created_at = '2025-01-01 12:00:00';
        private $status = 'pending';
        private $id = 0;
        private $customer_id = 1;

        public function __construct($id = 0) {
            $this->id = (int) $id;
        }

        public function get_total() {
            return $this->total;
        }

        public function get_total_tax() {
            return $this->tax;
        }

        public function get_customer_id() {
            return $this->customer_id;
        }

        public function get_date_created() {
            return $this->created_at;
        }

        public function set_total($total) {
            $this->total = $total;
        }

        public function set_tax_total($tax) {
            $this->tax = $tax;
        }

        public function set_customer_id($customer_id) {
            $this->customer_id = (int) $customer_id;
        }

        public function set_date_created($date_string) {
            $this->created_at = $date_string;
        }

        public function set_status($status) {
            $this->status = ltrim((string) $status, 'wc-') ?: 'pending';
        }

        public function get_status() {
            return $this->status ?: 'pending';
        }

        public function has_status($statuses) {
            $current = $this->get_status();
            $current = ltrim($current, 'wc-');

            if (is_string($statuses)) {
                $statuses = [$statuses];
            }

            if (!is_array($statuses)) {
                return false;
            }

            foreach ($statuses as $status) {
                $normalized = ltrim((string) $status, 'wc-');
                if ($normalized === $current) {
                    return true;
                }
            }

            return false;
        }

        public function set_id($id) {
            $this->id = (int) $id;
        }

        public function get_id() {
            return $this->id ?: 123;
        }

        private $meta_data = [];

        public function get_meta($key, $single = true) {
            return $this->meta_data[$key] ?? ($single ? '' : []);
        }

        public function update_meta_data($key, $value) {
            $this->meta_data[$key] = $value;
        }

        public function delete_meta_data($key) {
            unset($this->meta_data[$key]);
        }

        public function add_order_note($note) {
            // Mock order note addition
        }

        public function get_billing_email() {
            return 'test@example.com';
        }

        public function get_currency() {
            return 'CHF';
        }
    }
}

if (!function_exists('wc_get_order')) {
    function wc_get_order($order_id) {
        global $mock_wc_order_override, $mock_wc_orders_by_id;

        if ($mock_wc_order_override instanceof WC_Order) {
            return $mock_wc_order_override;
        }

        $order_id = (int) $order_id;
        if ($order_id > 0 && isset($mock_wc_orders_by_id[$order_id])) {
            return $mock_wc_orders_by_id[$order_id];
        }

        $order = new WC_Order($order_id);
        if ($order_id > 0) {
            $mock_wc_orders_by_id[$order_id] = $order;
        }

        return $order;
    }
}

// Mock global $wpdb (real methods — closures on stdClass are not callable as $wpdb->method()).
if (!class_exists('Mock_WPDB')) {
    class Mock_WPDB {
        public $prefix = 'wp_';
        public $posts = 'wp_posts';
        public $postmeta = 'wp_postmeta';
        public $usermeta = 'wp_usermeta';
        public $insert_id = 0;
        public $last_error = '';

        public function prepare($query, ...$args) {
            if (empty($args)) {
                return $query;
            }
            return vsprintf(str_replace('%d', '%s', $query), $args);
        }

        public function query($query) {
            global $mock_wpdb_last_query;
            $mock_wpdb_last_query = $query;
            return true;
        }

        public function get_var($query) {
            global $mock_customer_spent, $mock_wpdb_get_var_results, $mock_points_balances, $mock_order_points_allocated, $mock_points_log_rows, $mock_purchase_rewards_by_order;

            if (!empty($mock_wpdb_get_var_results)) {
                foreach ($mock_wpdb_get_var_results as $needle => $result) {
                    if ($needle !== '' && strpos($query, $needle) !== false) {
                        return is_callable($result) ? $result($query) : $result;
                    }
                }
            }

            if (strpos($query, 'SUM(points_amount)') !== false || strpos($query, 'COALESCE(SUM(points_amount)') !== false) {
                $positive = 0;
                $negative = 0;
                foreach ($mock_points_log_rows as $row) {
                    $amount = (float) ($row['points_amount'] ?? 0);
                    if ($amount > 0) {
                        $positive += $amount;
                    } elseif ($amount < 0) {
                        $negative += abs($amount);
                    }
                }

                if (strpos($query, 'points_amount > 0') !== false || strpos($query, 'points_amount)>0') !== false) {
                    return $positive;
                }

                if (strpos($query, 'points_amount < 0') !== false || strpos($query, 'points_amount)<0') !== false) {
                    return $negative;
                }
            }

            if (strpos($query, 'points_balance') !== false && preg_match('/customer_id\s*=\s*(\d+)/', $query, $matches)) {
                $customer_id = (int) $matches[1];
                return $mock_points_balances[$customer_id] ?? 0;
            }

            if (strpos($query, 'COUNT(*)') !== false) {
                if (strpos($query, 'DISTINCT customer_id') !== false) {
                    $customers = [];
                    foreach ($mock_points_log_rows as $row) {
                        if (($row['points_balance'] ?? 0) > 0) {
                            $customers[(int) $row['customer_id']] = true;
                        }
                    }
                    return count($customers);
                }

                if (strpos($query, 'order_id') !== false && preg_match('/order_id\s*=\s*(\d+)/', $query, $matches)) {
                    $order_id = (int) $matches[1];
                    return !empty($mock_order_points_allocated[$order_id]) ? 1 : 0;
                }
                return 0;
            }

            if (strpos($query, 'SUM(pm.meta_value)') !== false) {
                if (preg_match('/user_id\s*=\s*(\d+)/', $query, $matches)) {
                    $user_id = (int) $matches[1];
                    return $mock_customer_spent[$user_id] ?? 0;
                }
                return $mock_customer_spent[1] ?? 0;
            }

            if (strpos($query, 'points_amount') !== false && preg_match('/order_id\s*=\s*(\d+)/', $query, $matches)) {
                $order_id = (int) $matches[1];
                return $mock_order_points_allocated[$order_id] ?? 0;
            }

            if (strpos($query, 'purchase_rewards') !== false && preg_match('/order_id\s*=\s*(\d+)/', $query, $matches)) {
                $order_id = (int) $matches[1];
                return $mock_purchase_rewards_by_order[$order_id] ?? null;
            }

            if (strpos($query, 'latest_balances') !== false) {
                return array_sum($mock_points_balances);
            }

            return 0;
        }

        public function get_row($query) {
            global $mock_wpdb_get_row_results;

            foreach ($mock_wpdb_get_row_results as $needle => $result) {
                if ($needle === '__queue__') {
                    $queued = array_shift($mock_wpdb_get_row_results[$needle]);
                    if ($queued !== null) {
                        return is_callable($queued) ? $queued($query) : $queued;
                    }
                    continue;
                }

                if ($needle !== '' && strpos($query, $needle) !== false) {
                    return is_callable($result) ? $result($query) : $result;
                }
            }

            return (object) [
                'id' => 1,
                'coach_id' => 2,
                'customer_id' => 1,
                'order_id' => 123,
                'purchase_count' => 1,
                'status' => 'pending',
            ];
        }

        public function get_results($query) {
            global $mock_wpdb_get_results;

            foreach ($mock_wpdb_get_results as $needle => $result) {
                if ($needle === '__queue__') {
                    $queued = array_shift($mock_wpdb_get_results[$needle]);
                    if ($queued !== null) {
                        return is_callable($queued) ? $queued($query) : $queued;
                    }
                    continue;
                }

                if ($needle !== '' && strpos($query, $needle) !== false) {
                    return is_callable($result) ? $result($query) : $result;
                }
            }

            return [
                (object) ['transaction_type' => 'order_purchase', 'transaction_count' => 2, 'total_points' => 25, 'avg_points' => 12.5],
                (object) ['transaction_type' => 'points_redemption', 'transaction_count' => 1, 'total_points' => -5, 'avg_points' => -5],
            ];
        }

        public function update($table, $data, $where) {
            global $mock_wpdb_last_update;
            $mock_wpdb_last_update = compact('table', 'data', 'where');
            return 1;
        }

        public function insert($table, $data) {
            global $mock_wpdb_last_insert, $mock_wpdb_last_insert_by_table, $mock_points_balances, $mock_order_points_allocated, $mock_points_log_rows, $mock_purchase_rewards_by_order;

            static $insert_id = 1;
            $mock_wpdb_last_insert = compact('table', 'data');
            if (!is_array($mock_wpdb_last_insert_by_table)) {
                $mock_wpdb_last_insert_by_table = [];
            }
            $mock_wpdb_last_insert_by_table[$table] = $data;

            if (strpos($table, 'points_log') !== false) {
                $mock_points_log_rows[] = $data;
                if (isset($data['customer_id'], $data['points_balance'])) {
                    $mock_points_balances[(int) $data['customer_id']] = $data['points_balance'];
                }
                if (isset($data['order_id'], $data['transaction_type']) && $data['transaction_type'] === 'order_purchase') {
                    $mock_order_points_allocated[(int) $data['order_id']] = $data['points_amount'] ?? true;
                }
            }

            if (strpos($table, 'purchase_rewards') !== false && isset($data['order_id'])) {
                $mock_purchase_rewards_by_order[(int) $data['order_id']] = $insert_id;
            }

            $this->insert_id = $insert_id++;
            return true;
        }

        public function delete($table, $where) {
            global $mock_wpdb_last_delete;
            $mock_wpdb_last_delete = compact('table', 'where');
            return 1;
        }
    }
}

global $wpdb;
$wpdb = new Mock_WPDB();

// Mock additional WordPress functions
if (!function_exists('wp_create_user')) {
    function wp_create_user($username, $password, $email = '') {
        static $user_id = 1000;
        return $user_id++;
    }
}

if (!function_exists('wp_delete_user')) {
    function wp_delete_user($user_id) {
        return true;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action) {
        return 'test_nonce_' . $action;
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action, $query_arg = false, $die = true) {
        global $mock_ajax_referer_valid;
        if (isset($mock_ajax_referer_valid) && $mock_ajax_referer_valid === false) {
            if ($die) {
                throw new Exception('Nonce verification failed');
            }
            return false;
        }
        return true;
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        global $mock_wp_verify_nonce_result;
        if (isset($mock_wp_verify_nonce_result)) {
            return (bool) $mock_wp_verify_nonce_result;
        }
        return true;
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null) {
        global $mock_wp_json_response;
        $mock_wp_json_response = ['success' => true, 'data' => $data];
        if (defined('INTERSOCCER_PHPUNIT') && INTERSOCCER_PHPUNIT) {
            return;
        }
        echo json_encode($mock_wp_json_response);
        exit;
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null) {
        global $mock_wp_json_response;
        $mock_wp_json_response = ['success' => false, 'data' => $data];
        if (defined('INTERSOCCER_PHPUNIT') && INTERSOCCER_PHPUNIT) {
            return;
        }
        echo json_encode($mock_wp_json_response);
        exit;
    }
}

if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
        return true; // Mock successful email sending
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $password;
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs(intval($maybeint));
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $key = strtolower($key);
        return preg_replace('/[^a-z0-9_]/', '', $key);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        $filtered = strip_tags($str);
        $filtered = preg_replace('/[\r\n\t\0\x0B]/', '', $filtered);
        return trim($filtered);
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        $filtered = preg_replace('/[\x00-\x1F\x7F]/', '', $str);
        return trim($filtered);
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('_n')) {
    function _n($single, $plural, $number, $domain = 'default') {
        return $number == 1 ? $single : $plural;
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        $base = 'https://example.com';
        if ($path) {
            return rtrim($base, '/') . '/' . ltrim($path, '/');
        }
        return $base;
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url = '') {
        $url = $url ?: home_url('/');
        $parsed = parse_url($url);
        $query = [];
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }
        foreach ($args as $key => $value) {
            if ($value === null) {
                unset($query[$key]);
            } else {
                $query[$key] = $value;
            }
        }
        $parsed['query'] = http_build_query($query);
        $result = $parsed['scheme'] . '://' . $parsed['host'];
        if (!empty($parsed['path'])) {
            $result .= $parsed['path'];
        }
        if (!empty($parsed['query'])) {
            $result .= '?' . $parsed['query'];
        }
        return $result;
    }
}

if (!function_exists('get_post')) {
    function get_post($post_id) {
        return (object) [
            'ID' => $post_id,
            'post_title' => 'Test Event ' . $post_id,
            'post_type' => 'product',
            'post_status' => 'publish',
        ];
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($post) {
        $id = is_object($post) ? $post->ID : intval($post);
        return home_url('/?p=' . $id);
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args = []) {
        global $mock_get_posts_results;
        return $mock_get_posts_results ?? [];
    }
}

if (!function_exists('get_avatar_url')) {
    function get_avatar_url($user_id, $args = []) {
        return 'https://example.com/avatar/' . $user_id . '.jpg';
    }
}

if (!function_exists('is_checkout')) {
    function is_checkout() {
        return true;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() {
        global $mock_current_user_id;
        return !empty($mock_current_user_id);
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        global $mock_current_user_id;
        return $mock_current_user_id ?? 1;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $message;

        public function __construct($code = '', $message = '', $data = '') {
            $this->message = (string) $message;
        }

        public function get_error_message() {
            return $this->message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('is_email')) {
    function is_email($email) {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('sanitize_user')) {
    function sanitize_user($username, $strict = false) {
        $username = strtolower(trim((string) $username));
        return preg_replace('/[^a-z0-9._@-]/', '', $username);
    }
}

if (!function_exists('wp_insert_user')) {
    function wp_insert_user($userdata) {
        global $mock_users;

        static $next_id = 2000;
        $id = $next_id++;

        $user = new WP_User($id);
        $user->user_login = $userdata['user_login'] ?? ('user' . $id);
        $user->user_email = $userdata['user_email'] ?? ('user' . $id . '@example.com');
        $user->display_name = $userdata['display_name'] ?? $user->user_login;

        if (!empty($userdata['role'])) {
            $user->set_role($userdata['role']);
        }

        $mock_users[$id] = $user;

        return $id;
    }
}

if (!function_exists('wp_update_user')) {
    function wp_update_user($userdata) {
        global $mock_users;

        $id = (int) ($userdata['ID'] ?? 0);
        if (!$id || !isset($mock_users[$id])) {
            return new WP_Error('invalid_user', 'Invalid user ID');
        }

        $user = $mock_users[$id];
        if (isset($userdata['first_name'])) {
            $user->first_name = $userdata['first_name'];
        }
        if (isset($userdata['last_name'])) {
            $user->last_name = $userdata['last_name'];
        }
        if (isset($userdata['display_name'])) {
            $user->display_name = $userdata['display_name'];
        }

        return $id;
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        global $mock_current_user_id, $mock_users;

        $user_id = $mock_current_user_id ?? 1;
        if (isset($mock_users[$user_id])) {
            return $mock_users[$user_id];
        }

        $user = new WP_User($user_id);
        $user->user_login = 'admin';
        return $user;
    }
}

if (!function_exists('get_user_by')) {
    function get_user_by($field, $value) {
        global $mock_users, $mock_user_roles;

        if ($field === 'ID' && isset($mock_users[$value])) {
            return $mock_users[$value];
        }

        if ($field === 'email') {
            foreach ($mock_users as $user) {
                if (isset($user->user_email) && strcasecmp($user->user_email, (string) $value) === 0) {
                    return $user;
                }
            }
        }

        if ($field === 'ID' && is_numeric($value)) {
            $user_id = (int) $value;
            $user = new WP_User($user_id);
            if (!empty($mock_user_roles[$user_id])) {
                $user->set_role($mock_user_roles[$user_id][0]);
            }
            return $user;
        }

        return null;
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata($user_id) {
        return get_user_by('ID', $user_id);
    }
}

if (!function_exists('get_users')) {
    function get_users($args = []) {
        global $mock_users;
        $results = $mock_users;

        if (isset($args['role'])) {
            $role = $args['role'];
            $results = array_filter($results, function($user) use ($role) {
                return in_array($role, $user->roles ?? []);
            });
        }

        if (isset($args['meta_key'])) {
            $meta_key = $args['meta_key'];
            $meta_value = isset($args['meta_value']) ? $args['meta_value'] : null;
            $results = array_filter($results, function($user) use ($meta_key, $meta_value) {
                $value = get_user_meta($user->ID, $meta_key, true);
                if ($meta_value === null) {
                    return $value !== '' && $value !== null;
                }
                return strtoupper((string)$value) === strtoupper((string)$meta_value);
            });
        }

        if (isset($args['number']) && is_numeric($args['number'])) {
            $results = array_slice($results, 0, (int)$args['number'], true);
        }

        return array_values($results);
    }
}

if (!function_exists('wc_get_orders')) {
    function wc_get_orders($args = []) {
        global $mock_orders, $mock_wc_get_orders;

        if (isset($mock_wc_get_orders) && is_callable($mock_wc_get_orders)) {
            return $mock_wc_get_orders($args);
        }

        return $mock_orders ?? [];
    }
}

if (!function_exists('wc_add_notice')) {
    function wc_add_notice($message, $type = 'success') {
        // Mock notice addition
    }
}

// Initialize global mock variables
global $mock_current_user_id, $mock_user_meta, $mock_users, $mock_orders, $mock_session, $mock_customer_spent, $mock_get_posts_results, $mock_wpdb_get_row_results, $mock_wpdb_get_results, $mock_wpdb_get_var_results, $mock_wpdb_last_insert, $mock_wpdb_last_update, $mock_wpdb_last_delete, $mock_wc_products, $mock_wc_product_lookup, $mock_points_balances, $mock_order_points_allocated, $mock_points_log_rows, $mock_purchase_rewards_by_order, $mock_wc_orders_by_id, $mock_wc_get_orders, $mock_user_roles, $mock_shortcodes, $mock_user_capabilities, $mock_is_account_page, $mock_query_vars, $mock_backtrace_summary, $mock_ajax_referer_valid, $mock_wp_verify_nonce_result;
$mock_current_user_id = 1;
$mock_user_meta = [];
$mock_shortcodes = [];
$mock_user_capabilities = [];
$mock_ajax_referer_valid = null;
$mock_wp_verify_nonce_result = null;
$mock_is_account_page = false;
$mock_query_vars = [];
$mock_backtrace_summary = '';
$mock_users = [];
$mock_orders = [];
$mock_session = [];
$mock_customer_spent = [];
$mock_get_posts_results = [];
$mock_wpdb_get_row_results = [];
$mock_wpdb_get_results = [];
$mock_wpdb_get_var_results = [];
$mock_wpdb_last_insert = null;
$mock_wpdb_last_update = null;
$mock_wpdb_last_delete = null;
$mock_points_balances = [];
$mock_order_points_allocated = [];
$mock_points_log_rows = [];
$mock_purchase_rewards_by_order = [];
$mock_wc_orders_by_id = [];
$mock_wc_get_orders = null;
$mock_user_roles = [];
$mock_wc_products = [];
$mock_wc_product_lookup = [];

// WooCommerce product stubs
if (!class_exists('WC_Product')) {
    class WC_Product {
        protected $id;
        protected $name;
        protected $type;
        protected $status;
        protected $data;

        public function __construct($id, $name = '', $type = 'simple', $status = 'publish', $data = []) {
            $this->id = $id;
            $this->name = $name ?: 'Product ' . $id;
            $this->type = $type;
            $this->status = $status;
            $this->data = $data;
        }

        public function get_id() {
            return $this->id;
        }

        public function get_name() {
            return $this->name;
        }

        public function is_type($type) {
            return $this->type === $type;
        }

        public function get_permalink() {
            return home_url('/?p=' . $this->id);
        }

        public function get_status() {
            return $this->status;
        }

        public function get_visible_children() {
            return $this->data['children'] ?? [];
        }

        public function get_attributes() {
            return $this->data['attributes'] ?? [];
        }

        public function get_parent_id() {
            return $this->data['parent'] ?? 0;
        }
    }
}

if (!class_exists('WC_Product_Variable')) {
    class WC_Product_Variable extends WC_Product {
        public function __construct($id, $name = '', $status = 'publish', $data = []) {
            parent::__construct($id, $name, 'variable', $status, $data);
        }
    }
}

if (!class_exists('WC_Product_Variation')) {
    class WC_Product_Variation extends WC_Product {
        public function __construct($id, $name = '', $status = 'publish', $data = []) {
            parent::__construct($id, $name, 'variation', $status, $data);
        }
    }
}

if (!function_exists('wc_get_products')) {
    function wc_get_products($args = []) {
        global $mock_wc_products;
        return $mock_wc_products;
    }
}

if (!function_exists('wc_get_product')) {
    function wc_get_product($product_id) {
        global $mock_wc_product_lookup;
        return $mock_wc_product_lookup[$product_id] ?? null;
    }
}

if (!function_exists('wc_get_formatted_variation')) {
    function wc_get_formatted_variation($product, $include_names = true, $skip_attributes = false, $use_accessors = false) {
        if (!method_exists($product, 'get_attributes')) {
            return '';
        }

        $attributes = $product->get_attributes();
        if (empty($attributes)) {
            return '';
        }

        $parts = [];
        foreach ($attributes as $key => $value) {
            $label = $include_names ? ucwords(str_replace(['pa_', '_', '-'], ['', ' ', ' '], $key)) . ': ' : '';
            $parts[] = $label . $value;
        }

        return implode(', ', $parts);
    }
}

// Mock WC class
if (!class_exists('WC')) {
    class WC {
        public static function session() {
            return new class {
                public function get($key, $default = null) {
                    global $mock_session;
                    return $mock_session[$key] ?? $default;
                }
                public function set($key, $value) {
                    global $mock_session;
                    $mock_session[$key] = $value;
                }
                public function __unset($key) {
                    global $mock_session;
                    unset($mock_session[$key]);
                }
            };
        }
    }
}

if (!function_exists('WC')) {
    function WC() {
        static $wc_facade = null;

        if ($wc_facade === null) {
            $wc_facade = new class {
                public $session;

                public function __construct() {
                    $this->session = WC::session();
                }
            };
        }

        return $wc_facade;
    }
}

// Mock WP_User class
if (!class_exists('WP_User')) {
    class WP_User {
        public $ID;
        public $roles = [];
        public $user_login = '';
        public $display_name = '';
        public $user_email = '';

        public function __construct($user_id = 0) {
            $this->ID = $user_id;
            $this->user_login = 'test_user';
            $this->display_name = 'Test User';
            $this->user_email = 'test@example.com';
        }

        public function set_role($role) {
            $this->roles = [$role];
        }

        public function has_cap($cap) {
            $role_obj = get_role($this->roles[0] ?? '');
            return $role_obj ? $role_obj->has_cap($cap) : false;
        }
    }
}

// Mock WP_Role class
if (!class_exists('WP_Role')) {
    class WP_Role {
        private $capabilities = [];

        public function __construct($role, $capabilities = []) {
            $this->capabilities = $capabilities;
        }

        public function has_cap($cap) {
            return isset($this->capabilities[$cap]) && $this->capabilities[$cap];
        }

        public function add_cap($cap, $grant = true) {
            $this->capabilities[$cap] = $grant;
        }
    }
}

// Mock role storage
global $mock_roles;
$mock_roles = [];

if (!function_exists('add_role')) {
    function add_role($role, $display_name, $capabilities = []) {
        global $mock_roles;
        $mock_roles[$role] = new WP_Role($role, $capabilities);
        return $mock_roles[$role];
    }
}

if (!function_exists('get_role')) {
    function get_role($role) {
        global $mock_roles;
        return $mock_roles[$role] ?? null;
    }
}

if (!function_exists('remove_role')) {
    function remove_role($role) {
        global $mock_roles;
        unset($mock_roles[$role]);
        return true;
    }
}

// Initialize default roles
add_role('administrator', 'Administrator', ['manage_options' => true]);
add_role('subscriber', 'Subscriber', ['read' => true]);
add_role('coach', 'Coach', ['read' => true]);