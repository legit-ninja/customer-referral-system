<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-referral-handler.php';
require_once __DIR__ . '/../includes/class-points-manager.php';

class ReferralProcessedProbeOrder extends WC_Order {
    public $balance_when_marked = 'unset';
    public $referrer_id = 0;

    public function update_meta_data($key, $value) {
        if ($key === '_intersoccer_referral_processed' && $value === 'yes' && $this->balance_when_marked === 'unset') {
            global $mock_user_meta;
            $this->balance_when_marked = $mock_user_meta[$this->referrer_id]['intersoccer_points_balance'] ?? null;
        }
        parent::update_meta_data($key, $value);
    }
}

class ReferralProcessedMarkerTest extends TestCase {
    private $saved = [];

    protected function setUp(): void {
        global $mock_users, $mock_user_meta, $mock_session, $mock_orders, $mock_wc_order_override, $mock_post_meta, $mock_points_log_rows, $mock_points_balances, $mock_options;
        $this->saved = [
            'users' => $mock_users,
            'meta' => $mock_user_meta,
            'session' => $mock_session,
            'orders' => $mock_orders,
            'override' => $mock_wc_order_override,
            'post_meta' => $mock_post_meta,
            'log' => $mock_points_log_rows,
            'balances' => $mock_points_balances,
            'options' => $mock_options,
        ];
        $mock_orders = [];
        $mock_post_meta = [];
        $mock_wc_order_override = null;
    }

    protected function tearDown(): void {
        global $mock_users, $mock_user_meta, $mock_session, $mock_orders, $mock_wc_order_override, $mock_post_meta, $mock_points_log_rows, $mock_points_balances, $mock_options;
        $mock_users = $this->saved['users'];
        $mock_user_meta = $this->saved['meta'];
        $mock_session = $this->saved['session'];
        $mock_orders = $this->saved['orders'];
        $mock_wc_order_override = $this->saved['override'];
        $mock_post_meta = $this->saved['post_meta'];
        $mock_points_log_rows = $this->saved['log'];
        $mock_points_balances = $this->saved['balances'];
        $mock_options = $this->saved['options'];
    }

    private function referrerCustomer() {
        global $mock_users, $mock_user_meta, $mock_session;
        $referrer_id = 7501;
        $buyer_id = 7502;
        $mock_users[$referrer_id] = (object) [
            'ID' => $referrer_id,
            'roles' => ['customer'],
            'display_name' => 'Referrer',
            'user_email' => 'referrer@example.com',
        ];
        $mock_users[$buyer_id] = (object) [
            'ID' => $buyer_id,
            'roles' => ['customer'],
            'display_name' => 'Buyer',
            'user_email' => 'buyer@example.com',
        ];
        $mock_user_meta[$referrer_id] = ['intersoccer_customer_referral_code' => 'CUST7501'];
        $mock_user_meta[$buyer_id] = [];
        $mock_session = [
            'intersoccer_referral' => [
                'code' => 'CUST7501',
                'event_id' => null,
                'coach_event_id' => null,
                'set_at' => time(),
            ],
        ];
        return [$referrer_id, $buyer_id];
    }

    public function testCompletionMarkerIsOrderMetaAndIsSetBeforePointsAreAwarded() {
        global $mock_wc_order_override, $mock_post_meta, $mock_user_meta;

        list($referrer_id, $buyer_id) = $this->referrerCustomer();
        $order = new ReferralProcessedProbeOrder(75001);
        $order->referrer_id = $referrer_id;
        $order->set_id(75001);
        $order->set_status('completed');
        $order->set_customer_id($buyer_id);
        $order->set_total(100);
        $mock_wc_order_override = $order;

        $handler = new InterSoccer_Referral_Handler();
        $handler->process_referral_order(75001);

        $this->assertSame('yes', $order->get_meta('_intersoccer_referral_processed', true));
        $this->assertNull(
            $order->balance_when_marked,
            'The processed marker should be saved before referral points are awarded'
        );
        $this->assertSame(10, (int) ($mock_user_meta[$referrer_id]['intersoccer_points_balance'] ?? 0));
        $this->assertSame(50, (int) ($mock_user_meta[$buyer_id]['intersoccer_points_balance'] ?? 0));
        $this->assertArrayNotHasKey(75001, $mock_post_meta);
        $this->assertSame('', $order->get_meta('_intersoccer_referral_payload', true));

        $handler->process_referral_order(75001);

        $this->assertSame(10, (int) $mock_user_meta[$referrer_id]['intersoccer_points_balance']);
        $this->assertSame(50, (int) $mock_user_meta[$buyer_id]['intersoccer_points_balance']);
    }

    public function testUnfinishedOrderKeepsPayloadOnTheOrderAndIsNotMarkedProcessed() {
        global $mock_wc_order_override, $mock_post_meta;

        list($referrer_id, $buyer_id) = $this->referrerCustomer();
        $order = new WC_Order(75002);
        $order->set_id(75002);
        $order->set_status('processing');
        $order->set_customer_id($buyer_id);
        $order->set_total(80);
        $mock_wc_order_override = $order;

        (new InterSoccer_Referral_Handler())->process_referral_order(75002);

        $payload = $order->get_meta('_intersoccer_referral_payload', true);
        $this->assertIsArray($payload);
        $this->assertSame('CUST7501', $payload['code']);
        $this->assertNotSame('yes', $order->get_meta('_intersoccer_referral_processed', true));
        $this->assertArrayNotHasKey(75002, $mock_post_meta);
    }
}
