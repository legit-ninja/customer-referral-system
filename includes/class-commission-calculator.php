<?php
// includes/class-commission-calculator.php

class InterSoccer_Commission_Calculator {

    public static function calculate_commission($order, $purchase_count) {
        $total = $order->get_total() - $order->get_total_tax(); // Excl. tax, adjust as needed
        switch ($purchase_count) {
            case 1:
                return $total * 0.15; // 15%
            case 2:
                return $total * 0.075; // 7.5%
            default:
                return $total * 0.05; // 5%
        }
    }
}