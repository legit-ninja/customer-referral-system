<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ROIFirstOrderCandidateSqlTest.php';

/**
 * Brief crs-roi-followups: dashboard pending UI, HPOS holder refunds without line items, storage-mode cache keys.
 */
class ROIFollowupsTest extends TestCase {
    private $saved_wpdb;
    private $saved_globals = [];
    /** @var InterSoccer_Test_Sqlite_WPDB */
    private $db;
    private $next_item_id = 1;

    const RANGE = ['start' => '2026-01-01', 'end' => '2026-12-31'];

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-admin-dashboard-main.php';
        global $wpdb, $mock_wc_orders_by_id, $mock_wc_get_orders, $mock_wc_order_override, $mock_transients;
        $this->saved_wpdb = $wpdb;
        $this->saved_globals = [
            $mock_wc_orders_by_id ?? [],
            $mock_wc_get_orders ?? null,
            $mock_wc_order_override ?? null,
            $mock_transients ?? [],
            get_option('woocommerce_custom_orders_table_enabled'),
        ];
        $mock_wc_orders_by_id = [];
        $mock_wc_order_override = null;
        $mock_transients = [];

        $this->db = new InterSoccer_Test_Sqlite_WPDB();
        $this->db->exec("CREATE TABLE wp_usermeta (umeta_id INTEGER PRIMARY KEY, user_id INTEGER, meta_key TEXT, meta_value TEXT)");
        $this->db->exec("CREATE TABLE wp_intersoccer_referrals (id INTEGER PRIMARY KEY, customer_id INTEGER, order_id INTEGER, created_at TEXT)");
        $this->db->exec("CREATE TABLE wp_wc_orders (id INTEGER PRIMARY KEY, type TEXT, status TEXT, date_created_gmt TEXT, customer_id INTEGER, parent_order_id INTEGER, total_amount REAL)");
        $this->db->exec("CREATE TABLE wp_woocommerce_order_items (order_item_id INTEGER PRIMARY KEY, order_id INTEGER, order_item_name TEXT, order_item_type TEXT)");
        $this->db->exec("CREATE TABLE wp_woocommerce_order_itemmeta (meta_id INTEGER PRIMARY KEY, order_item_id INTEGER, meta_key TEXT, meta_value TEXT)");
        $this->db->exec("CREATE TABLE wp_options (option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT)");
        $wpdb = $this->db;

        $index = [];
        $mock_wc_get_orders = function ($args) use (&$index) {
            if (($args['page'] ?? 1) > 1) {
                return [];
            }
            [$start, $end] = explode('...', $args['date_created']);
            $statuses = (array) ($args['status'] ?? []);
            $ids = [];
            foreach ($index as $id => [$local, $status]) {
                $day = substr($local, 0, 10);
                if ($day >= $start && $day <= $end && in_array($status, $statuses, true)) {
                    $ids[] = $id;
                }
            }
            return $ids;
        };
        $this->order_index = &$index;

        parent::setUp();
    }

    /** @var array */
    private $order_index;

    protected function tearDown(): void {
        global $wpdb, $mock_wc_orders_by_id, $mock_wc_get_orders, $mock_wc_order_override, $mock_transients;
        $wpdb = $this->saved_wpdb;
        [$mock_wc_orders_by_id, $mock_wc_get_orders, $mock_wc_order_override, $mock_transients, $hpos] = $this->saved_globals;
        if ($hpos === false) {
            delete_option('woocommerce_custom_orders_table_enabled');
        } else {
            update_option('woocommerce_custom_orders_table_enabled', $hpos);
        }
        parent::tearDown();
    }

    private function invoke($object, $method, ...$args) {
        $m = new ReflectionMethod($object, $method);
        $m->setAccessible(true);
        return $m->invoke($object, ...$args);
    }

    /**
     * (a) Source-level: pending branch shows roi_pending_message instead of CHF / "Not enough data".
     */
    public function testDashboardPending_ShowsPendingMessageNotChfOrEmptyCompare_SourceAssertions() {
        $source = file_get_contents(__DIR__ . '/../includes/class-admin-dashboard-main.php');
        $this->assertStringContainsString(
            "if (\$roi_widgets_pending || !empty(\$loyalty_roi['pending']) || !empty(\$loyalty_roi_range['pending'])) {\n                                echo esc_html(\$roi_pending_message);",
            $source
        );
        $this->assertStringContainsString(
            "printf(esc_html__('Extra spend: +%s CHF/customer', 'intersoccer-referral'), number_format(\$loyalty_roi['extra_spend_per_customer'], 0));",
            $source
        );
        $this->assertStringContainsString("<?php if (!empty(\$comparison['pending'])): ?>", $source);
        $this->assertStringContainsString("get_roi_pending_message()", $source);
        $this->assertMatchesRegularExpression(
            '/elseif \(\$comparison\[\'holder_count\'\] > 0\):.*?Not enough data to compare\./s',
            $source
        );
    }

    /**
     * (c) Transient keys include storage mode; caches do not cross modes; prefix invalidation still works.
     */
    public function testRoiCacheKey_IncludesStorageModeAndInvalidatesWithSuffix() {
        global $mock_transients;
        $range = self::RANGE;
        $hash = md5($range['start'] . $range['end']);
        $suffix = 'revenue_' . $hash;

        delete_option('woocommerce_custom_orders_table_enabled');
        $posts_calc = new InterSoccer_ROI_Calculator();
        $posts_key = $this->invoke($posts_calc, 'storage_mode_cache_key', $suffix);
        $this->assertSame('intersoccer_roi_' . $suffix . '_posts', $posts_key);

        update_option('woocommerce_custom_orders_table_enabled', 'yes');
        $this->db->exec("INSERT INTO wp_wc_orders (id, type, status, date_created_gmt, customer_id, parent_order_id, total_amount) VALUES (1, 'shop_order', 'wc-completed', '2026-01-01 00:00:00', 1, NULL, 10)");
        $hpos_calc = new InterSoccer_ROI_Calculator();
        $hpos_key = $this->invoke($hpos_calc, 'storage_mode_cache_key', $suffix);
        $this->assertSame('intersoccer_roi_' . $suffix . '_hpos', $hpos_key);
        $this->assertNotSame($posts_key, $hpos_key);

        set_transient($posts_key, 42.0, 3600);
        set_transient($hpos_key, 99.0, 3600);
        $this->assertSame(42.0, get_transient($posts_key));
        $this->assertSame(99.0, get_transient($hpos_key));

        $memo = new ReflectionProperty($posts_calc, 'storage_mode_cache_suffix');
        $memo->setAccessible(true);
        $memo->setValue($posts_calc, 'posts');
        $cached_posts = $this->invoke($posts_calc, 'get_cached', $suffix, function () {
            return 777.0;
        });
        $this->assertSame(42.0, $cached_posts, 'Posts calculator must not read HPOS cache');

        foreach ([
            '_transient_intersoccer_roi_first_order_discount_x_hpos',
            '_transient_timeout_intersoccer_roi_cost_breakdown_y_posts',
            '_transient_intersoccer_roi_holder_comparison_z_hpos',
        ] as $name) {
            $this->db->exec("INSERT INTO wp_options (option_name, option_value) VALUES ('{$name}', '1')");
        }
        InterSoccer_ROI_Calculator::invalidate_order_dependent_cache();
        $left = $this->db->pdo->query('SELECT option_name FROM wp_options ORDER BY option_name')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['_transient_intersoccer_roi_holder_comparison_z_hpos'], $left);

        foreach (['_transient_intersoccer_roi_revenue_rm_posts', '_transient_timeout_intersoccer_roi_revenue_rm_posts'] as $name) {
            $this->db->exec("INSERT INTO wp_options (option_name, option_value) VALUES ('{$name}', '1')");
        }
        $hpos_calc->clear_cache();
        $left_after_clear = $this->db->pdo->query('SELECT COUNT(*) FROM wp_options')->fetchColumn();
        $this->assertSame(0, (int) $left_after_clear);
    }

    /**
     * (b) HPOS holder/non-holder avg spend includes refunds without line items (matches posts-path order rule).
     */
    public function testHolderComparison_HposIncludesRefundsWithoutLineItems() {
        update_option('woocommerce_custom_orders_table_enabled', 'yes');
        $this->db->exec("INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (101, 'intersoccer_points_balance', '10')");

        $local = '2026-06-15 10:00:00';
        $gmt = get_gmt_from_date($local);

        $this->seedHposShopOrder(1001, 101, $gmt, [1000.0]);
        $this->seedHposRefundWithLines(10011, 1001, 101, $gmt, -50.0);
        $this->seedHposRefundWithoutLines(10012, 1001, 101, $gmt, -30.0);

        $this->seedHposShopOrder(2001, 201, $gmt, [2000.0]);
        $this->seedHposRefundWithLines(20011, 2001, 201, $gmt, -100.0);
        $this->seedHposRefundWithoutLines(20012, 2001, 201, $gmt, -20.0);

        $this->seedLegacyWcOrder(1001, 101, $local, [1000.0], [
            [-50.0, [-50.0]],
            [-30.0, []],
        ]);
        $this->seedLegacyWcOrder(2001, 201, $local, [2000.0], [
            [-100.0, [-100.0]],
            [-20.0, []],
        ]);

        $calculator = new InterSoccer_ROI_Calculator();
        $hpos = $this->invoke($calculator, 'compute_holder_vs_nonholder', self::RANGE);
        $this->assertEqualsWithDelta(920.0, $hpos['holder_avg_spend'], 0.01);
        $this->assertEqualsWithDelta(1880.0, $hpos['non_holder_avg_spend'], 0.01);

        $holder_line_only = 1000.0 + (-50.0);
        $non_holder_line_only = 2000.0 + (-100.0);
        $this->assertEqualsWithDelta($holder_line_only, $hpos['holder_avg_spend'] + 30.0, 0.01, 'No-line holder refund must change avg');
        $this->assertEqualsWithDelta($non_holder_line_only, $hpos['non_holder_avg_spend'] + 20.0, 0.01);

        delete_option('woocommerce_custom_orders_table_enabled');
        $posts_calc = new InterSoccer_ROI_Calculator();
        $posts = $this->invoke($posts_calc, 'compute_holder_vs_nonholder', self::RANGE);
        $this->assertEqualsWithDelta($hpos['holder_avg_spend'], $posts['holder_avg_spend'], 0.01);
        $this->assertEqualsWithDelta($hpos['non_holder_avg_spend'], $posts['non_holder_avg_spend'], 0.01);
    }

    private function seedHposShopOrder($id, $customer_id, $gmt, array $line_totals) {
        $this->db->exec(sprintf(
            "INSERT INTO wp_wc_orders (id, type, status, date_created_gmt, customer_id, parent_order_id, total_amount) VALUES (%d, 'shop_order', 'wc-completed', %s, %d, NULL, %f)",
            $id,
            $this->db->pdo->quote($gmt),
            $customer_id,
            array_sum($line_totals)
        ));
        foreach ($line_totals as $total) {
            $item_id = $this->next_item_id++;
            $this->db->exec("INSERT INTO wp_woocommerce_order_items (order_item_id, order_id, order_item_name, order_item_type) VALUES ({$item_id}, {$id}, 'Camp', 'line_item')");
            $this->db->exec("INSERT INTO wp_woocommerce_order_itemmeta (order_item_id, meta_key, meta_value) VALUES ({$item_id}, '_line_total', '{$total}')");
        }
    }

    private function seedHposRefundWithLines($refund_id, $parent_id, $customer_id, $gmt, $line_total) {
        $this->db->exec(sprintf(
            "INSERT INTO wp_wc_orders (id, type, status, date_created_gmt, customer_id, parent_order_id, total_amount) VALUES (%d, 'shop_order_refund', 'wc-completed', %s, %d, %d, %f)",
            $refund_id,
            $this->db->pdo->quote($gmt),
            $customer_id,
            $parent_id,
            $line_total
        ));
        $item_id = $this->next_item_id++;
        $this->db->exec("INSERT INTO wp_woocommerce_order_items (order_item_id, order_id, order_item_name, order_item_type) VALUES ({$item_id}, {$refund_id}, 'Camp', 'line_item')");
        $this->db->exec("INSERT INTO wp_woocommerce_order_itemmeta (order_item_id, meta_key, meta_value) VALUES ({$item_id}, '_line_total', '{$line_total}')");
    }

    private function seedHposRefundWithoutLines($refund_id, $parent_id, $customer_id, $gmt, $total_amount) {
        $this->db->exec(sprintf(
            "INSERT INTO wp_wc_orders (id, type, status, date_created_gmt, customer_id, parent_order_id, total_amount) VALUES (%d, 'shop_order_refund', 'wc-completed', %s, %d, %d, %f)",
            $refund_id,
            $this->db->pdo->quote($gmt),
            $customer_id,
            $parent_id,
            $total_amount
        ));
    }

    /**
     * @param array $refunds list of [refund_total, line_totals[]]
     */
    private function seedLegacyWcOrder($id, $customer_id, $local, array $lines, array $refunds) {
        global $mock_wc_orders_by_id;
        $order = new WC_Order($id);
        $order->set_id($id);
        $order->set_status('completed');
        $order->set_date_created($local);
        $order->set_customer_id($customer_id);
        foreach ($lines as $line_total) {
            $item = new WC_Order_Item_Product();
            $item->set_name('Camp');
            $item->set_total($line_total);
            $order->add_item($item);
        }
        $n = 0;
        foreach ($refunds as [$refund_total, $refund_lines]) {
            $refund = new WC_Order_Refund($id * 1000 + $n++);
            $refund->set_parent_id($id);
            $refund->set_total($refund_total);
            foreach ($refund_lines as $rl) {
                $ri = new WC_Order_Item_Product();
                $ri->set_name('Camp');
                $ri->set_total($rl);
                $refund->add_item($ri);
            }
            $order->add_refund($refund);
        }
        $mock_wc_orders_by_id[$id] = $order;
        $this->order_index[$id] = [$local, 'completed'];
    }
}
