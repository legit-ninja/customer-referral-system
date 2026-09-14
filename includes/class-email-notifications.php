<?php
/**
 * Email Notifications Manager
 * 
 * Handles automated email notifications for the referral system.
 * Stub implementation for testing purposes.
 */
class InterSoccer_Email_Notifications {
    
    /**
     * Send referral completion notification to coach
     *
     * @param int $coach_id The coach user ID
     * @param int $customer_id The customer user ID  
     * @param int $order_id The order ID
     * @return bool Success status
     */
    public function send_referral_completion_notification($coach_id, $customer_id, $order_id) {
        global $mock_email_queue;
        
        if (!is_array($mock_email_queue)) {
            $mock_email_queue = [];
        }
        
        $coach = get_userdata($coach_id);
        $coach_email = $coach ? $coach->user_email : 'coach@example.com';
        
        $mock_email_queue[] = [
            'to' => $coach_email,
            'subject' => 'New Referral Completed - Order #' . $order_id,
            'body' => sprintf(
                'Customer #%d has completed an order using your referral code. Order #%d.',
                $customer_id,
                $order_id
            ),
        ];
        
        return true;
    }
    
    /**
     * Send performance report email
     *
     * @param int $coach_id The coach user ID
     * @param array $stats Performance statistics
     * @return bool Success status
     */
    public function send_performance_report($coach_id, $stats = []) {
        global $mock_email_queue;
        
        if (!is_array($mock_email_queue)) {
            $mock_email_queue = [];
        }
        
        $coach = get_userdata($coach_id);
        $coach_email = $coach ? $coach->user_email : 'coach@example.com';
        
        $mock_email_queue[] = [
            'to' => $coach_email,
            'subject' => 'Your Weekly Performance Report',
            'body' => 'Here is your weekly performance summary.',
        ];
        
        return true;
    }
    
    /**
     * Send fraud detection alert
     *
     * @param array $alert_data Alert information
     * @return bool Success status
     */
    public function send_fraud_alert($alert_data = []) {
        global $mock_email_queue;
        
        if (!is_array($mock_email_queue)) {
            $mock_email_queue = [];
        }
        
        $admin_email = get_option('admin_email', 'admin@example.com');
        
        $mock_email_queue[] = [
            'to' => $admin_email,
            'subject' => 'Fraud Detection Alert',
            'body' => 'Potential fraud detected: ' . json_encode($alert_data),
        ];
        
        return true;
    }
    
    /**
     * Send system health notification
     *
     * @param array $health_data Health check data
     * @return bool Success status
     */
    public function send_system_health_notification($health_data = []) {
        global $mock_email_queue;
        
        if (!is_array($mock_email_queue)) {
            $mock_email_queue = [];
        }
        
        $admin_email = get_option('admin_email', 'admin@example.com');
        
        $mock_email_queue[] = [
            'to' => $admin_email,
            'subject' => 'System Health Report',
            'body' => 'System health status: ' . json_encode($health_data),
        ];
        
        return true;
    }
}
