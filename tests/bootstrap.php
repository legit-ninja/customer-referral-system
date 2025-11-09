<?php
/**
 * Bootstrap for PHPUnit tests
 */

// Define WordPress constants for testing
define('WP_PLUGIN_DIR', dirname(__DIR__));
define('WP_CONTENT_DIR', dirname(WP_PLUGIN_DIR));

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!defined('YEAR_IN_SECONDS')) {
    define('YEAR_IN_SECONDS', DAY_IN_SECONDS * 365);
}

// Include WordPress test utilities if available, otherwise mock
if (file_exists(dirname(__DIR__, 2) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
}

// Include plugin files
require_once __DIR__ . '/../customer-referral-system.php';

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

        public function get_total() {
            return $this->total;
        }

        public function get_total_tax() {
            return $this->tax;
        }

        public function get_customer_id() {
            return 1;
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

        public function set_date_created($date_string) {
            $this->created_at = $date_string;
        }
    }
}

if (!function_exists('wc_get_order')) {
    function wc_get_order($order_id) {
        global $mock_wc_order_override;
        if ($mock_wc_order_override instanceof WC_Order) {
            return $mock_wc_order_override;
        }

        return new WC_Order();
    }
}

// Mock global $wpdb
global $wpdb;
if (!$wpdb) {
    $wpdb = new stdClass();
    $wpdb->prefix = 'wp_';
    $wpdb->prepare = function($query, ...$args) {
        return vsprintf(str_replace('%d', '%s', $query), $args);
    };
    $mock_wpdb_get_row_results = [];
    $mock_wpdb_get_results = [];
    $mock_wpdb_last_insert = null;
    $mock_wpdb_last_update = null;
    $mock_wpdb_last_delete = null;

    $wpdb->get_var = function($query) {
        global $mock_customer_spent;

        if (strpos($query, 'COUNT(*)') !== false) {
            return 15;
        }

        if (strpos($query, 'SUM(pm.meta_value)') !== false) {
            return $mock_customer_spent[1] ?? 0;
        }

        return 0;
    };

    $wpdb->get_row = function($query) use (&$mock_wpdb_get_row_results) {
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
            'status' => 'pending'
        ];
    };

    $wpdb->get_results = function($query) use (&$mock_wpdb_get_results) {
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
            (object) ['transaction_type' => 'points_redemption', 'transaction_count' => 1, 'total_points' => -5, 'avg_points' => -5]
        ];
    };

    $wpdb->update = function($table, $data, $where) use (&$mock_wpdb_last_update) {
        $mock_wpdb_last_update = compact('table', 'data', 'where');
        return 1;
    };

    $wpdb->insert = function($table, $data) use (&$mock_wpdb_last_insert) {
        static $insert_id = 1;
        $mock_wpdb_last_insert = compact('table', 'data');
        global $wpdb, $mock_wpdb_last_insert_by_table;
        if (!is_array($mock_wpdb_last_insert_by_table)) {
            $mock_wpdb_last_insert_by_table = [];
        }
        $mock_wpdb_last_insert_by_table[$table] = $data;
        $current_id = $insert_id++;
        $wpdb->insert_id = $current_id;
        return true;
    };

    $wpdb->delete = function($table, $where) use (&$mock_wpdb_last_delete) {
        $mock_wpdb_last_delete = compact('table', 'where');
        return 1;
    };
}

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
        return true; // Always pass in tests
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null) {
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null) {
        echo json_encode(['success' => false, 'data' => $data]);
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

if (!function_exists('get_user_by')) {
    function get_user_by($field, $value) {
        global $mock_users;
        if ($field === 'ID' && isset($mock_users[$value])) {
            return $mock_users[$value];
        }
        return null;
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
            $results = array_slice($results, 0, (int)$args['number']);
        }

        return $results;
    }
}

if (!function_exists('wc_get_orders')) {
    function wc_get_orders($args = []) {
        global $mock_orders;
        return $mock_orders ?? [];
    }
}

if (!function_exists('wc_add_notice')) {
    function wc_add_notice($message, $type = 'success') {
        // Mock notice addition
    }
}

// Initialize global mock variables
global $mock_current_user_id, $mock_user_meta, $mock_users, $mock_orders, $mock_session, $mock_customer_spent, $mock_get_posts_results, $mock_wpdb_get_row_results, $mock_wpdb_get_results, $mock_wpdb_last_insert, $mock_wpdb_last_update, $mock_wpdb_last_delete, $mock_wc_products, $mock_wc_product_lookup;
$mock_current_user_id = 1;
$mock_user_meta = [];
$mock_users = [];
$mock_orders = [];
$mock_session = [];
$mock_customer_spent = [];
$mock_get_posts_results = [];
$mock_wpdb_get_row_results = [];
$mock_wpdb_get_results = [];
$mock_wpdb_last_insert = null;
$mock_wpdb_last_update = null;
$mock_wpdb_last_delete = null;
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

// Mock WC_Order class enhancements
class WC_Order_Test extends WC_Order {
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

// Replace WC_Order with enhanced version
if (class_exists('WC_Order')) {
    class_alias('WC_Order_Test', 'WC_Order', true);
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