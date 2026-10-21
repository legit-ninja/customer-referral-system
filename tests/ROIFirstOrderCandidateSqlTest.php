<?php
/**
 * Runs the first-order discount candidate SQL (HPOS and posts branches) against fixture rows in
 * in-memory SQLite and compares the result with the legacy full order loop.
 */

use PHPUnit\Framework\TestCase;

/**
 * Minimal $wpdb over PDO SQLite: real quoting in prepare(), last_error on failure, query log.
 */
class InterSoccer_Test_Sqlite_WPDB {
    public $prefix = 'wp_';
    public $posts = 'wp_posts';
    public $postmeta = 'wp_postmeta';
    public $options = 'wp_options';
    public $usermeta = 'wp_usermeta';
    public $last_error = '';
    public $queries = [];
    public $fail_get_col = false;
    /** @var PDO */
    public $pdo;

    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function prepare($query, ...$args) {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }
        $pdo = $this->pdo;
        $i = 0;
        return preg_replace_callback('/%[sdf]/', function ($m) use (&$i, $args, $pdo) {
            $v = $args[$i++] ?? '';
            if ($m[0] === '%d') {
                return (string) (int) $v;
            }
            if ($m[0] === '%f') {
                return (string) (float) $v;
            }
            return $pdo->quote((string) $v);
        }, $query);
    }

    private function run($query) {
        $this->queries[] = $query;
        $this->last_error = '';
        try {
            return $this->pdo->query($query);
        } catch (PDOException $e) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    public function get_col($query) {
        if ($this->fail_get_col) {
            $this->queries[] = $query;
            $this->last_error = 'Simulated database error';
            return [];
        }
        $st = $this->run($query);
        return $st ? $st->fetchAll(PDO::FETCH_COLUMN, 0) : [];
    }

    public function get_row($query, $output = null) {
        if ($output === null) {
            $output = defined('OBJECT') ? OBJECT : 'OBJECT';
        }
        $rows = $this->get_results($query, $output);
        return $rows[0] ?? null;
    }

    public function get_results($query, $output = null) {
        if ($output === null) {
            $output = defined('OBJECT') ? OBJECT : 'OBJECT';
        }
        $st = $this->run($query);
        if (!$st) {
            return [];
        }
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if ($output === 'OBJECT' || (defined('OBJECT') && $output === OBJECT)) {
            return array_map(function ($row) {
                return (object) $row;
            }, $rows);
        }
        return $rows;
    }

    public function get_var($query) {
        if (preg_match("/^SHOW TABLES LIKE '([^']+)'$/", trim($query), $m)) {
            $query = "SELECT name FROM sqlite_master WHERE type = 'table' AND name = " . $this->pdo->quote($m[1]);
        }
        $query = preg_replace('/\s+LIMIT 1\s*$/', '', $query);
        $st = $this->run($query);
        if (!$st) {
            return null;
        }
        $v = $st->fetchColumn();
        return $v === false ? null : $v;
    }

    public function query($query) {
        $st = $this->run($query);
        return $st ? $st->rowCount() : false;
    }

    public function exec($sql) {
        $this->pdo->exec($sql);
    }
}

class ROIFirstOrderCandidateSqlTest extends TestCase {
    private $saved_wpdb;
    private $saved_globals = [];
    /** @var InterSoccer_Test_Sqlite_WPDB */
    private $db;
    private $next_item_id = 1;
    /** @var array id => [local datetime, status] for the legacy loop */
    private $legacy_index = [];
    private $legacy_calls = 0;

    const RANGE = ['start' => '2025-10-26', 'end' => '2026-03-29'];

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-roi-calculator.php';
        global $wpdb, $mock_wc_orders_by_id, $mock_wc_get_orders, $mock_wc_order_override, $mock_transients, $mock_scheduled_single_events, $mock_cron;
        $this->saved_wpdb = $wpdb;
        $this->saved_globals = [
            $mock_wc_orders_by_id ?? [], $mock_wc_get_orders ?? null, $mock_wc_order_override ?? null,
            $mock_transients ?? [], $mock_scheduled_single_events ?? [], $mock_cron ?? [],
            $GLOBALS['mock_site_timezone'] ?? null, get_option('woocommerce_custom_orders_table_enabled'),
        ];
        $mock_wc_orders_by_id = [];
        $mock_wc_order_override = null;
        $mock_transients = [];
        $mock_scheduled_single_events = [];
        $mock_cron = [];
        $GLOBALS['mock_site_timezone'] = 'Europe/Zurich';

        $this->db = new InterSoccer_Test_Sqlite_WPDB();
        $this->db->exec("CREATE TABLE wp_posts (ID INTEGER PRIMARY KEY, post_type TEXT, post_status TEXT, post_date TEXT, post_date_gmt TEXT)");
        $this->db->exec("CREATE TABLE wp_postmeta (meta_id INTEGER PRIMARY KEY, post_id INTEGER, meta_key TEXT, meta_value TEXT)");
        $this->db->exec("CREATE TABLE wp_wc_orders (id INTEGER PRIMARY KEY, type TEXT, status TEXT, date_created_gmt TEXT)");
        $this->db->exec("CREATE TABLE wp_wc_orders_meta (id INTEGER PRIMARY KEY, order_id INTEGER, meta_key TEXT, meta_value TEXT)");
        $this->db->exec("CREATE TABLE wp_woocommerce_order_items (order_item_id INTEGER PRIMARY KEY, order_id INTEGER, order_item_name TEXT, order_item_type TEXT)");
        $this->db->exec("CREATE TABLE wp_woocommerce_order_itemmeta (meta_id INTEGER PRIMARY KEY, order_item_id INTEGER, meta_key TEXT, meta_value TEXT)");
        $this->db->exec("CREATE TABLE wp_options (option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT)");
        $wpdb = $this->db;

        $this->legacy_calls = 0;
        $index = &$this->legacy_index;
        $calls = &$this->legacy_calls;
        // Legacy loop source: WooCommerce semantics for 'Y-m-d...Y-m-d' = local calendar days, inclusive.
        $mock_wc_get_orders = function ($args) use (&$index, &$calls) {
            $calls++;
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

        $this->buildFixtures();
        parent::setUp();
    }

    protected function tearDown(): void {
        global $wpdb, $mock_wc_orders_by_id, $mock_wc_get_orders, $mock_wc_order_override, $mock_transients, $mock_scheduled_single_events, $mock_cron;
        $wpdb = $this->saved_wpdb;
        [$mock_wc_orders_by_id, $mock_wc_get_orders, $mock_wc_order_override, $mock_transients, $mock_scheduled_single_events, $mock_cron, $tz, $hpos] = $this->saved_globals;
        $GLOBALS['mock_site_timezone'] = $tz;
        if ($hpos === false) {
            delete_option('woocommerce_custom_orders_table_enabled');
        } else {
            update_option('woocommerce_custom_orders_table_enabled', $hpos);
        }
        $this->legacy_index = [];
        parent::tearDown();
    }

    /**
     * @param int $id
     * @param string $local Local (Europe/Zurich) creation time
     * @param array $o status, meta (order meta), fees [[name,total,meta]], lines [totals], refunds [[total, lines]]
     */
    private function addOrder($id, $local, array $o = []) {
        global $mock_wc_orders_by_id;
        $status = $o['status'] ?? 'completed';
        $gmt = get_gmt_from_date($local);

        $this->db->exec(sprintf("INSERT INTO wp_posts VALUES (%d, 'shop_order', 'wc-%s', '%s', '%s')", $id, $status, $local, $gmt));
        $this->db->exec(sprintf("INSERT INTO wp_wc_orders VALUES (%d, 'shop_order', 'wc-%s', '%s')", $id, $status, $gmt));

        $order = new WC_Order($id);
        $order->set_id($id);
        $order->set_status($status);
        $order->set_date_created($local);
        foreach ($o['meta'] ?? [] as $k => $v) {
            $order->update_meta_data($k, $v);
            foreach (['wp_postmeta (post_id', 'wp_wc_orders_meta (order_id'] as $t) {
                $this->db->exec(sprintf("INSERT INTO %s, meta_key, meta_value) VALUES (%d, %s, %s)", $t, $id, $this->db->pdo->quote($k), $this->db->pdo->quote((string) $v)));
            }
        }
        $product_total = 0.0;
        foreach ($o['lines'] ?? [100] as $line_total) {
            $item = new WC_Order_Item_Product();
            $item->set_name('Camp');
            $item->set_subtotal($line_total);
            $item->set_total($line_total);
            $order->add_item($item);
            $product_total += $line_total;
        }
        $fee_total = 0.0;
        foreach ($o['fees'] ?? [] as [$name, $total, $fee_meta]) {
            $fee = new WC_Order_Item_Fee($name, $total);
            $item_id = $this->next_item_id++;
            $this->db->exec(sprintf("INSERT INTO wp_woocommerce_order_items VALUES (%d, %d, %s, 'fee')", $item_id, $id, $this->db->pdo->quote($name)));
            foreach ($fee_meta as $k => $v) {
                $fee->add_meta_data($k, $v);
                $this->db->exec(sprintf("INSERT INTO wp_woocommerce_order_itemmeta (order_item_id, meta_key, meta_value) VALUES (%d, %s, %s)", $item_id, $this->db->pdo->quote($k), $this->db->pdo->quote((string) $v)));
            }
            $order->add_item($fee);
            $fee_total += $total;
        }
        $order->set_total($product_total + $fee_total);
        foreach ($o['refunds'] ?? [] as $n => [$refund_total, $refund_lines]) {
            $refund = new WC_Order_Refund($id * 1000 + $n);
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
        $this->legacy_index[$id] = [$local, $status];
    }

    /**
     * Window 2025-10-26 .. 2026-03-29 local: starts on the autumn DST change, ends on the spring one.
     * Expected first-order total: 20 + 15 + 12 + 10 = 57.
     */
    private function buildFixtures() {
        $applied = ['_intersoccer_first_order_discount_applied' => 1, '_intersoccer_first_order_discount_amount' => 20];
        // 1: 23:30 local the day before the window (21:30 GMT) -> out.
        $this->addOrder(1, '2025-10-25 23:30:00', ['meta' => $applied]);
        // 2: 00:30 local on the first day, still CEST (2025-10-25 22:30 GMT) -> in, order meta 20.
        $this->addOrder(2, '2025-10-26 00:30:00', ['meta' => $applied]);
        // 3: amount meta only (no applied flag) -> candidate, contributes 0 (same as legacy).
        $this->addOrder(3, '2025-11-10 10:00:00', ['meta' => ['_intersoccer_first_order_discount_amount' => 30]]);
        // 4: fee-meta only, 23:30 local on the last day after the spring change (21:30 GMT) -> in, 15.
        $this->addOrder(4, '2026-03-29 23:30:00', ['fees' => [['Discount', -15, ['_intersoccer_referral_discount_fee' => 'yes']]]]);
        // 5: 00:30 local the day after the window (2026-03-29 22:30 GMT) -> out.
        $this->addOrder(5, '2026-03-30 00:30:00', ['fees' => [['Discount', -15, ['_intersoccer_referral_discount_fee' => 'yes']]]]);
        // 6: fee-name only (translated label) -> in, 12.
        $this->addOrder(6, '2026-01-15 10:00:00', ['fees' => [['Réduction de parrainage', -12, []]]]);
        // 7: refund-net: 20 discount, 200 products, 100 of product lines refunded -> net 10.
        $this->addOrder(7, '2026-02-01 09:00:00', [
            'lines' => [100, 100],
            'fees' => [['Discount', -20, ['_intersoccer_referral_discount_fee' => 1]]],
            'refunds' => [[-100, [-100]]],
        ]);
        // 8: no discount at all -> not a candidate.
        $this->addOrder(8, '2026-02-02 09:00:00');
        // 9: cancelled with a referral fee -> excluded by status in both paths.
        $this->addOrder(9, '2026-02-03 09:00:00', ['status' => 'cancelled', 'fees' => [['Discount', -25, ['_intersoccer_referral_discount_fee' => 1]]]]);
        // 10: fee meta present but falsy, unrelated fee name -> candidate, contributes 0.
        $this->addOrder(10, '2026-02-04 09:00:00', ['fees' => [['Other fee', -5, ['_intersoccer_referral_discount_fee' => '0']]]]);
    }

    private function invoke($calculator, $method, ...$args) {
        $m = new ReflectionMethod($calculator, $method);
        $m->setAccessible(true);
        return $m->invoke($calculator, ...$args);
    }

    private function assertBranchMatchesLegacy($hpos) {
        if ($hpos) {
            update_option('woocommerce_custom_orders_table_enabled', 'yes');
        } else {
            delete_option('woocommerce_custom_orders_table_enabled');
        }
        $calculator = new InterSoccer_ROI_Calculator();

        $legacy_total = $this->invoke($calculator, 'compute_first_order_discount_cost_legacy', self::RANGE);
        $this->assertGreaterThan(0, $this->legacy_calls);

        $this->db->queries = [];
        $ids = $this->invoke($calculator, 'get_first_order_discount_candidate_order_ids', self::RANGE);
        sort($ids);
        $this->assertSame([2, 3, 4, 6, 7, 10], $ids, 'Candidate ids (edges, statuses and non-discount orders)');

        $sql = end($this->db->queries);
        $this->assertStringContainsString($hpos ? 'o.date_created_gmt >= ' : 'p.post_date_gmt >= ', $sql);
        $this->assertStringContainsString("'2025-10-25 22:00:00'", $sql, 'Window start converted from local to GMT');
        $this->assertStringContainsString("'2026-03-29 21:59:59'", $sql, 'Window end converted from local to GMT');
        $this->assertStringContainsString($hpos ? 'wp_wc_orders_meta' : 'wp_postmeta', $sql);

        $this->legacy_calls = 0;
        $hybrid_total = $this->invoke($calculator, 'compute_first_order_discount_cost', self::RANGE);
        $this->assertSame(0, $this->legacy_calls, 'SQL path must not fall back to the full scan');
        $this->assertEqualsWithDelta(57.0, $legacy_total, 0.0001);
        $this->assertEqualsWithDelta($legacy_total, $hybrid_total, 0.0001);
    }

    public function testCandidateSql_PostsBranch_MatchesLegacyLoop() {
        $this->assertBranchMatchesLegacy(false);
    }

    public function testCandidateSql_HposBranch_MatchesLegacyLoop() {
        $this->assertBranchMatchesLegacy(true);
    }

    public function testCandidateSql_EmptySuccessfulResultIsEmptyArray() {
        $calculator = new InterSoccer_ROI_Calculator();
        $range = ['start' => '2024-01-01', 'end' => '2024-01-31'];
        $this->assertSame([], $this->invoke($calculator, 'get_first_order_discount_candidate_order_ids', $range));
        $this->legacy_calls = 0;
        $this->assertSame(0.0, (float) $this->invoke($calculator, 'compute_first_order_discount_cost', $range));
        $this->assertSame(0, $this->legacy_calls);
    }

    public function testCandidateSql_DatabaseErrorFallsBackToFullScan() {
        foreach ([false, true] as $hpos) {
            if ($hpos) {
                update_option('woocommerce_custom_orders_table_enabled', 'yes');
            } else {
                delete_option('woocommerce_custom_orders_table_enabled');
            }
            $this->db->fail_get_col = true;
            $calculator = new InterSoccer_ROI_Calculator();
            $this->assertNull($this->invoke($calculator, 'get_first_order_discount_candidate_order_ids', self::RANGE));
            $this->legacy_calls = 0;
            $total = $this->invoke($calculator, 'compute_first_order_discount_cost', self::RANGE);
            $this->assertGreaterThan(0, $this->legacy_calls, 'Must fall back to the full scan');
            $this->assertEqualsWithDelta(57.0, $total, 0.0001);
            $this->db->fail_get_col = false;
        }
    }

    public function testCandidateSql_MissingHposTablesFallsBackToFullScan() {
        update_option('woocommerce_custom_orders_table_enabled', 'yes');
        $this->db->exec('DROP TABLE wp_wc_orders_meta');
        $calculator = new InterSoccer_ROI_Calculator();
        $this->assertNull($this->invoke($calculator, 'get_first_order_discount_candidate_order_ids', self::RANGE));
        $this->legacy_calls = 0;
        $this->assertEqualsWithDelta(57.0, $this->invoke($calculator, 'compute_first_order_discount_cost', self::RANGE), 0.0001);
        $this->assertGreaterThan(0, $this->legacy_calls);
    }

    /**
     * S3: an aborted compute (get_cached() returns false) is not memoized or cached as 0.0.
     */
    public function testFirstOrderDiscount_AbortIsNotMemoizedAsZero() {
        global $mock_transients;
        $calculator = new InterSoccer_ROI_Calculator();
        $deadline = new ReflectionProperty($calculator, 'roi_compute_deadline');
        $deadline->setAccessible(true);
        $deadline->setValue($calculator, microtime(true) - 1);

        $this->assertSame(0.0, $calculator->get_first_order_discount_cost(self::RANGE));
        $memo = new ReflectionProperty($calculator, 'request_first_order_discount_memo');
        $memo->setAccessible(true);
        $this->assertSame([], $memo->getValue($calculator));
        foreach (array_keys($mock_transients) as $key) {
            $this->assertStringNotContainsString('first_order_discount_', $key);
        }
        $this->assertTrue($calculator->is_roi_widgets_pending());

        // Budget lifted: same instance now computes the real value instead of a memoized 0.
        $deadline->setValue($calculator, null);
        $this->assertEqualsWithDelta(57.0, $calculator->get_first_order_discount_cost(self::RANGE), 0.0001);
    }

    /**
     * Order status change / refund drops first-order and cost-breakdown transients only.
     */
    public function testOrderChangeInvalidatesOrderDependentTransients() {
        $names = [
            '_transient_intersoccer_roi_first_order_discount_abc_hpos',
            '_transient_timeout_intersoccer_roi_first_order_discount_abc_hpos',
            '_transient_intersoccer_roi_cost_breakdown_def_posts',
            '_transient_timeout_intersoccer_roi_cost_breakdown_def_posts',
            '_transient_intersoccer_roi_holder_comparison_ghi_hpos',
            '_transient_intersoccer_roi_revenue_jkl_posts',
        ];
        foreach ($names as $n) {
            $this->db->exec("INSERT INTO wp_options (option_name, option_value) VALUES ('{$n}', '1')");
        }
        InterSoccer_ROI_Calculator::invalidate_order_dependent_cache();
        $left = $this->db->pdo->query('SELECT option_name FROM wp_options ORDER BY option_name')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame([
            '_transient_intersoccer_roi_holder_comparison_ghi_hpos',
            '_transient_intersoccer_roi_revenue_jkl_posts',
        ], $left);

        $source = file_get_contents(__DIR__ . '/../includes/class-roi-calculator.php');
        $this->assertStringContainsString("add_action('woocommerce_order_status_changed', ['InterSoccer_ROI_Calculator', 'invalidate_order_dependent_cache']", $source);
        $this->assertStringContainsString("add_action('woocommerce_order_refunded', ['InterSoccer_ROI_Calculator', 'invalidate_order_dependent_cache']", $source);
    }

    /**
     * Two requests overrunning together: the lock transient stops a second queue.
     */
    public function testScheduleWarm_LockPreventsDoubleQueue() {
        global $mock_scheduled_single_events, $mock_transients;
        $calculator = new InterSoccer_ROI_Calculator();
        $calculator->schedule_roi_cache_warm();
        $this->assertCount(1, $mock_scheduled_single_events);
        $this->assertArrayHasKey('intersoccer_roi_warm_lock', $mock_transients);

        // Second request before the first event is visible to it.
        $mock_scheduled_single_events = [];
        (new InterSoccer_ROI_Calculator())->schedule_roi_cache_warm();
        $this->assertCount(0, $mock_scheduled_single_events, 'Lock must stop a second queue');

        // Lock expired: scheduling works again.
        unset($mock_transients['intersoccer_roi_warm_lock']);
        (new InterSoccer_ROI_Calculator())->schedule_roi_cache_warm();
        $this->assertCount(1, $mock_scheduled_single_events);
    }
}
