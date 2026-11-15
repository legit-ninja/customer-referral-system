<?php
/**
 * Order status changes and refunds must drop shared holder and revenue ROI transients.
 * Partial refunds are covered by woocommerce_order_refunded (see class doc on the calculator).
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ROIFirstOrderCandidateSqlTest.php';

/**
 * Records order writes. The cache hook must not call these.
 */
class InterSoccer_Order_Write_Spy extends WC_Order {
    /** @var array */
    public $write_calls = [];

    public function update_meta_data($key, $value) {
        $this->write_calls[] = 'update_meta_data';
        parent::update_meta_data($key, $value);
    }

    public function save() {
        $this->write_calls[] = 'save';
        return parent::save();
    }

    public function add_order_note($note) {
        $this->write_calls[] = 'add_order_note';
        parent::add_order_note($note);
    }
}

class OrderDependentCacheInvalidationTest extends TestCase {
    /** @var InterSoccer_Test_Sqlite_WPDB */
    private $db;

    private $saved_wpdb;

    /** @var array */
    private $saved_orders;

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-roi-calculator.php';
        global $wpdb, $mock_wc_orders_by_id;
        $this->saved_wpdb = $wpdb;
        $this->saved_orders = $mock_wc_orders_by_id ?? [];
        $mock_wc_orders_by_id = [];

        $this->db = new InterSoccer_Test_Sqlite_WPDB();
        $this->db->exec('CREATE TABLE wp_options (option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT)');
        $wpdb = $this->db;
        parent::setUp();
    }

    protected function tearDown(): void {
        global $wpdb, $mock_wc_orders_by_id;
        $wpdb = $this->saved_wpdb;
        $mock_wc_orders_by_id = $this->saved_orders;
        parent::tearDown();
    }

    public function testStatusChangedAndRefundedHooksAreRegistered() {
        $source = file_get_contents(__DIR__ . '/../includes/class-roi-calculator.php');
        $this->assertNotFalse($source);

        $this->assertStringContainsString(
            "add_action('woocommerce_order_status_changed', ['InterSoccer_ROI_Calculator', 'invalidate_order_dependent_cache'], 20, 0);",
            $source
        );
        $this->assertStringContainsString(
            "add_action('woocommerce_order_refunded', ['InterSoccer_ROI_Calculator', 'invalidate_order_dependent_cache'], 20, 0);",
            $source
        );

        // wc_create_refund() fires woocommerce_order_refunded for partial and full refunds.
        // woocommerce_create_refund runs before save. woocommerce_refund_created fires in the same
        // breath as woocommerce_order_refunded, so a second hook would only repeat the DELETE.
        $this->assertStringNotContainsString("add_action('woocommerce_create_refund'", $source);
        $this->assertStringNotContainsString("add_action('woocommerce_refund_created'", $source);
        $this->assertStringNotContainsString("add_action('woocommerce_order_partially_refunded'", $source);

        $this->assertSame(
            'invalidate_order_dependent_cache',
            $this->callback_for_hook($source, 'woocommerce_order_status_changed')
        );
        $this->assertSame(
            'invalidate_order_dependent_cache',
            $this->callback_for_hook($source, 'woocommerce_order_refunded')
        );
    }

    public function testStatusChangedHandlerClearsHolderAndRevenueCaches() {
        $this->seed_order_dependent_transients();
        $this->fire_hook('woocommerce_order_status_changed');
        $this->assert_holder_and_revenue_cleared();
    }

    public function testRefundHandlerClearsHolderAndRevenueCaches() {
        $this->seed_order_dependent_transients();
        $this->fire_hook('woocommerce_order_refunded');
        $this->assert_holder_and_revenue_cleared();
    }

    public function testHandlersDoNotWriteTheOrder() {
        global $mock_wc_orders_by_id;

        $order = new InterSoccer_Order_Write_Spy(12901);
        $order->set_status('processing');
        $refund = new InterSoccer_Order_Write_Spy(12902);
        $refund->set_type('shop_order_refund');
        $refund->set_status('completed');
        $mock_wc_orders_by_id = [
            12901 => $order,
            12902 => $refund,
        ];

        $this->seed_order_dependent_transients();
        $this->fire_hook('woocommerce_order_status_changed');
        $this->fire_hook('woocommerce_order_refunded');

        $this->assertSame([], $order->write_calls);
        $this->assertSame([], $refund->write_calls);
        $this->assertSame([], $order->order_notes);
        $this->assertSame([], $refund->order_notes);
        $this->assertSame('processing', $order->get_status());
        $this->assertSame('completed', $refund->get_status());

        $this->assertNotEmpty($this->db->queries);
        foreach ($this->db->queries as $sql) {
            $this->assertStringContainsString('DELETE FROM', $sql);
            $this->assertStringContainsString('wp_options', $sql);
            $this->assertStringNotContainsString('wp_posts', $sql);
            $this->assertStringNotContainsString('wp_postmeta', $sql);
            $this->assertStringNotContainsString('woocommerce_order_items', $sql);
            $this->assertStringNotContainsString('wc_orders', $sql);
        }
    }

    private function seed_order_dependent_transients() {
        $rows = [
            '_transient_intersoccer_roi_holder_comparison_abc_hpos' => 'stale-holder-hpos',
            '_transient_timeout_intersoccer_roi_holder_comparison_abc_hpos' => '9999999999',
            '_transient_intersoccer_roi_holder_comparison_def_posts' => 'stale-holder-posts',
            '_transient_timeout_intersoccer_roi_holder_comparison_def_posts' => '9999999999',
            '_transient_intersoccer_roi_revenue_ghi_hpos' => 'stale-revenue-hpos',
            '_transient_timeout_intersoccer_roi_revenue_ghi_hpos' => '9999999999',
            '_transient_intersoccer_roi_revenue_jkl_posts' => 'stale-revenue-posts',
            '_transient_timeout_intersoccer_roi_revenue_jkl_posts' => '9999999999',
            '_transient_intersoccer_roi_first_order_discount_mno_hpos' => 'stale-discount',
            '_transient_timeout_intersoccer_roi_cost_breakdown_pqr_posts' => '9999999999',
            '_transient_intersoccer_roi_order_count_keep_posts' => 'keep-order-count',
            '_transient_intersoccer_roi_warm_lock' => '1',
            'unrelated_example_option' => 'keep-me',
        ];
        foreach ($rows as $name => $value) {
            $this->db->exec(
                "INSERT INTO wp_options (option_name, option_value) VALUES ("
                . $this->db->pdo->quote($name) . ", "
                . $this->db->pdo->quote($value) . ")"
            );
        }
    }

    private function assert_holder_and_revenue_cleared() {
        $left = $this->option_names();
        foreach ($left as $name) {
            $this->assertStringNotContainsString('holder_comparison_', $name);
            $this->assertStringNotContainsString('revenue_', $name);
            $this->assertStringNotContainsString('first_order_discount_', $name);
            $this->assertStringNotContainsString('cost_breakdown_', $name);
        }
        $this->assertSame(
            [
                '_transient_intersoccer_roi_order_count_keep_posts',
                '_transient_intersoccer_roi_warm_lock',
                'unrelated_example_option',
            ],
            $left
        );
    }

    /**
     * @return string[]
     */
    private function option_names() {
        $names = $this->db->pdo->query('SELECT option_name FROM wp_options ORDER BY option_name')->fetchAll(PDO::FETCH_COLUMN);
        return array_values($names);
    }

    private function fire_hook($hook) {
        $source = file_get_contents(__DIR__ . '/../includes/class-roi-calculator.php');
        $method = $this->callback_for_hook($source, $hook);
        InterSoccer_ROI_Calculator::$method();
    }

    /**
     * @param string $source
     * @param string $hook
     * @return string
     */
    private function callback_for_hook($source, $hook) {
        $needle = "add_action('" . $hook . "', ['InterSoccer_ROI_Calculator', '";
        $pos = strpos($source, $needle);
        $this->assertNotFalse($pos, $hook . ' callback is registered');
        $start = $pos + strlen($needle);
        $end = strpos($source, "'", $start);
        $this->assertNotFalse($end);
        return substr($source, $start, $end - $start);
    }
}
