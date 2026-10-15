<?php
/**
 * WP-CLI: dry-run report for redeemed points not returned on old refunded orders.
 */

if (!defined('ABSPATH')) {
    exit;
}

class InterSoccer_CLI_Redeemed_Backfill {

    /**
     * Compute rows owed redeemed points (read-only).
     *
     * @param array $order_ids Optional explicit order IDs; empty scans refunded/cancelled via WC.
     * @return array<int, array{order_id:int, customer_id:int, redeemed:int, returned:int, owed:int}>
     */
    public static function compute_redeemed_backfill_rows(array $order_ids = []) {
        $rows = [];

        if (!empty($order_ids)) {
            foreach ($order_ids as $order_id) {
                $order = function_exists('wc_get_order') ? wc_get_order((int) $order_id) : null;
                if (!$order) {
                    continue;
                }
                $row = self::compute_row_for_order($order);
                if ($row !== null) {
                    $rows[] = $row;
                }
            }
            return $rows;
        }

        if (!function_exists('wc_get_orders')) {
            return [];
        }

        $orders = wc_get_orders([
            'status' => ['cancelled', 'refunded'],
            'limit' => 500,
            'return' => 'objects',
        ]);

        foreach ((array) $orders as $order) {
            if (!is_object($order)) {
                continue;
            }
            $row = self::compute_row_for_order($order);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param WC_Order $order
     * @return array|null
     */
    public static function compute_row_for_order($order) {
        if (!is_object($order) || !method_exists($order, 'get_meta')) {
            return null;
        }

        if ((int) $order->get_meta('_intersoccer_credits_deducted_on_completion', true) !== 1) {
            return null;
        }

        $redeemed = (int) $order->get_meta('_intersoccer_points_redeemed', true);
        if ($redeemed <= 0) {
            return null;
        }

        $returned = (int) $order->get_meta('_intersoccer_redeemed_points_returned_total', true);
        if ($returned <= 0 && (int) $order->get_meta('_intersoccer_redeemed_points_returned', true) === 1) {
            $returned = $redeemed;
        }

        if ($returned < $redeemed) {
            $ledger_returned = 0;
            if (class_exists('InterSoccer_Points_Manager')) {
                $manager = InterSoccer_Points_Manager::get_instance();
                $ledger_returned = (int) $manager->get_abs_points_log_sum_for_order_transaction_types(
                    (int) $order->get_id(),
                    ['points_refund']
                );
            }
            $returned = max($returned, min($redeemed, $ledger_returned));
        }

        $owed = max(0, $redeemed - $returned);
        if ($owed <= 0) {
            return null;
        }

        return [
            'order_id' => (int) $order->get_id(),
            'customer_id' => (int) $order->get_customer_id(),
            'redeemed' => $redeemed,
            'returned' => $returned,
            'owed' => $owed,
        ];
    }

    /**
     * Register WP-CLI command (dry-run report only).
     */
    public static function register_cli_commands() {
        if (!defined('WP_CLI') || !WP_CLI || !class_exists('WP_CLI')) {
            return;
        }

        \WP_CLI::add_command('intersoccer points-redeemed-backfill-report', function ($args, $assoc_args) {
            $format = isset($assoc_args['format']) ? (string) $assoc_args['format'] : 'table';
            $rows = self::compute_redeemed_backfill_rows();
            $total_owed = 0;
            foreach ($rows as $row) {
                $total_owed += (int) $row['owed'];
            }

            if ($format === 'csv') {
                \WP_CLI::log('order_id,customer_id,redeemed,returned,owed');
                foreach ($rows as $row) {
                    \WP_CLI::log(sprintf(
                        '%d,%d,%d,%d,%d',
                        $row['order_id'],
                        $row['customer_id'],
                        $row['redeemed'],
                        $row['returned'],
                        $row['owed']
                    ));
                }
                \WP_CLI::log(sprintf('total_owed,%d', $total_owed));
                return;
            }

            \WP_CLI\Utils\format_items('table', $rows, ['order_id', 'customer_id', 'redeemed', 'returned', 'owed']);
            \WP_CLI::log(sprintf('Total owed: %d points', $total_owed));
        });
    }
}
