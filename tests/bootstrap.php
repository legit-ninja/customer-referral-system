<?php
/**
 * Bootstrap for PHPUnit tests
 */

// Define WordPress constants for testing
define('WP_PLUGIN_DIR', dirname(__DIR__));
define('WP_CONTENT_DIR', dirname(WP_PLUGIN_DIR));

// Include WordPress test utilities if available, otherwise mock
if (file_exists(dirname(__DIR__, 2) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
}

// Include plugin files
require_once __DIR__ . '/../customer-referral-system.php';

// Mock WordPress functions if not available
if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        $options = [
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
        return $options[$key] ?? $default;
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key, $single = true) {
        // Mock user meta for testing
        static $user_meta = [];
        if (!isset($user_meta[$user_id])) {
            $user_meta[$user_id] = [];
        }
        return $user_meta[$user_id][$key] ?? ($single ? '' : []);
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $key, $value) {
        static $user_meta = [];
        if (!isset($user_meta[$user_id])) {
            $user_meta[$user_id] = [];
        }
        $user_meta[$user_id][$key] = $value;
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
            return '2025-01-01 12:00:00';
        }

        public function set_total($total) {
            $this->total = $total;
        }

        public function set_tax_total($tax) {
            $this->tax = $tax;
        }
    }
}

if (!function_exists('wc_get_order')) {
    function wc_get_order($order_id) {
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
    $wpdb->get_var = function($query) {
        // Mock database responses
        if (strpos($query, 'COUNT(*)') !== false) {
            return 5; // Mock referral count
        }
        if (strpos($query, 'SUM(pm.meta_value)') !== false) {
            global $mock_customer_spent;
            return $mock_customer_spent[1] ?? 0; // Mock customer spending
        }
        return 0;
    };
    $wpdb->get_row = function($query) {
        // Mock referral row
        return (object) [
            'id' => 1,
            'coach_id' => 2,
            'customer_id' => 1,
            'order_id' => 123,
            'purchase_count' => 1,
            'status' => 'pending'
        ];
    };
    $wpdb->get_results = function($query) {
        // Mock transaction results
        return [
            (object) ['transaction_type' => 'order_purchase', 'transaction_count' => 2, 'total_points' => 25, 'avg_points' => 12.5],
            (object) ['transaction_type' => 'points_redemption', 'transaction_count' => 1, 'total_points' => -5, 'avg_points' => -5]
        ];
    };
    $wpdb->update = function($table, $data, $where) {
        return 1; // Mock success
    };
    $wpdb->insert = function($table, $data) {
        static $insert_id = 1;
        return $insert_id++; // Mock auto-increment
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
        if (isset($args['role']) && $args['role'] === 'coach') {
            return array_filter($mock_users, function($user) {
                return in_array('coach', $user->roles ?? []);
            });
        }
        return $mock_users;
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
global $mock_current_user_id, $mock_user_meta, $mock_users, $mock_orders, $mock_session, $mock_customer_spent;
$mock_current_user_id = 1;
$mock_user_meta = [];
$mock_users = [];
$mock_orders = [];
$mock_session = [];
$mock_customer_spent = [];

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