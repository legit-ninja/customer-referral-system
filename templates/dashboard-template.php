<!-- templates/dashboard-template.php -->
<div class="intersoccer-dashboard">
    <h2><?php esc_html_e('Coach Referral Dashboard', 'intersoccer-referral'); ?></h2>
    <p>
        <?php
        printf(
            esc_html__('Total Credits: %s CHF', 'intersoccer-referral'),
            esc_html($credits)
        );
        ?>
    </p>
    <p>
        <?php esc_html_e('Your Referral Link:', 'intersoccer-referral'); ?>
        <a href="<?php echo esc_url($referral_link); ?>"><?php echo esc_html($referral_link); ?></a>
    </p>
    <button type="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($referral_link); ?>')">
        <?php esc_html_e('Copy Link', 'intersoccer-referral'); ?>
    </button>

    <h3><?php esc_html_e('Recent Referrals', 'intersoccer-referral'); ?></h3>
    <table>
        <thead>
            <tr>
                <th><?php esc_html_e('Order ID', 'intersoccer-referral'); ?></th>
                <th><?php esc_html_e('Commission', 'intersoccer-referral'); ?></th>
                <th><?php esc_html_e('Date', 'intersoccer-referral'); ?></th>
            </tr>
        </thead>
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