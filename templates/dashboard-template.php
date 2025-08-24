<!-- templates/dashboard-template.php -->
<div class="intersoccer-dashboard">
    <h2>Coach Referral Dashboard</h2>
    <p>Total Credits: <?php echo esc_html($credits); ?> CHF</p>
    <p>Your Referral Link: <a href="<?php echo esc_url($referral_link); ?>"><?php echo esc_url($referral_link); ?></a></p>
    <button onclick="navigator.clipboard.writeText('<?php echo esc_js($referral_link); ?>')">Copy Link</button>
    
    <h3>Recent Referrals</h3>
    <table>
        <thead><tr><th>Order ID</th><th>Commission</th><th>Date</th></tr></thead>
        <tbody>
            <?php foreach ($referrals as $ref): ?>
                <tr>
                    <td><?php echo esc_html($ref->order_id); ?></td>
                    <td><?php echo esc_html($ref->commission_amount); ?></td>
                    <td><?php echo esc_html($ref->created_at); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Basic progress bar (placeholder) -->
    <div class="progress-bar" style="width: 100%; background: #eee; height: 20px;">
        <div style="width: <?php echo min(100, ($credits / 100) * 100); ?>%; background: #4caf50; height: 100%;"></div>
    </div>
</div>