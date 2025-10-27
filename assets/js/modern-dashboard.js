/**
 * Modern Coach Dashboard JavaScript
 * Handles interactive functionality for the modern dashboard
 */

class ModernCoachDashboard {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.initializeCharts();
        this.initializeAnimations();
        this.loadDashboardData();
    }

    bindEvents() {
        // Copy link functionality
        const copyBtn = document.getElementById('copy-link');
        if (copyBtn) {
            copyBtn.addEventListener('click', () => this.copyReferralLink());
        }

        const copyLinkText = document.getElementById('copy-link-text');
        if (copyLinkText) {
            copyLinkText.addEventListener('click', () => this.copyReferralLink());
        }

        // QR Code modal
        const showQrBtn = document.getElementById('show-qr');
        if (showQrBtn) {
            showQrBtn.addEventListener('click', () => this.showQrModal());
        }

        // Modal close
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-close') || e.target.classList.contains('modal')) {
                this.hideModals();
            }
        });

        // Theme toggle
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => this.toggleTheme());
        }

        // Quick actions
        this.bindQuickActions();

        // Chart period selector
        const chartPeriod = document.getElementById('chart-period');
        if (chartPeriod) {
            chartPeriod.addEventListener('change', (e) => this.updateChartPeriod(e.target.value));
        }

        // View more buttons
        const viewAllActivity = document.getElementById('view-all-activity');
        if (viewAllActivity) {
            viewAllActivity.addEventListener('click', () => this.showAllActivity());
        }

        const viewFullLeaderboard = document.getElementById('view-full-leaderboard');
        if (viewFullLeaderboard) {
            viewFullLeaderboard.addEventListener('click', () => this.showFullLeaderboard());
        }
    }

    copyReferralLink() {
        const linkInput = document.getElementById('referral-link-input');
        if (linkInput) {
            linkInput.select();
            document.execCommand('copy');
            this.showNotification('Referral link copied to clipboard!', 'success');
        }
    }

    showQrModal() {
        const modal = document.getElementById('qr-modal');
        if (modal) {
            modal.style.display = 'block';
        }
    }

    hideModals() {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => modal.style.display = 'none');
    }

    toggleTheme() {
        const dashboard = document.querySelector('.modern-coach-dashboard');
        const currentTheme = dashboard.dataset.theme;
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';

        dashboard.dataset.theme = newTheme;
        localStorage.setItem('coach-dashboard-theme', newTheme);

        // Save to user meta via AJAX
        this.saveThemePreference(newTheme);
    }

    saveThemePreference(theme) {
        fetch(intersoccer_dashboard.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new FormData({
                action: 'update_dashboard_theme',
                theme: theme,
                nonce: intersoccer_dashboard.nonce
            })
        }).catch(error => {
            console.error('Failed to save theme preference:', error);
        });
    }

    bindQuickActions() {
        // Social media composer
        const createPostBtn = document.getElementById('create-post');
        if (createPostBtn) {
            createPostBtn.addEventListener('click', () => this.showSocialMediaComposer());
        }

        // Email composer
        const sendEmailBtn = document.getElementById('send-email');
        if (sendEmailBtn) {
            sendEmailBtn.addEventListener('click', () => this.showEmailComposer());
        }

        // Resources
        const viewResourcesBtn = document.getElementById('view-resources');
        if (viewResourcesBtn) {
            viewResourcesBtn.addEventListener('click', () => {
                window.location.href = intersoccer_dashboard.admin_url + 'admin.php?page=intersoccer-coach-resources';
            });
        }

        // Support
        const contactSupportBtn = document.getElementById('contact-support');
        if (contactSupportBtn) {
            contactSupportBtn.addEventListener('click', () => this.showSupportModal());
        }
    }

    showSocialMediaComposer() {
        const modal = this.createModal('Create Social Media Post', `
            <div class="social-templates">
                <div class="template-option" data-platform="instagram">
                    <h4>📸 Instagram Post</h4>
                    <p>"Transform your game with personalized soccer training! 🏈⚽ Join me at InterSoccer - link in bio!"</p>
                </div>
                <div class="template-option" data-platform="facebook">
                    <h4>📘 Facebook Post</h4>
                    <p>"Looking to improve your soccer skills? I'm now partnering with InterSoccer to offer personalized training programs. Click here to get started: ${intersoccer_dashboard.referral_link}"</p>
                </div>
                <div class="template-option" data-platform="twitter">
                    <h4>🐦 Twitter Post</h4>
                    <p>"Level up your soccer game! 🏆 Join InterSoccer for personalized training. Link: ${intersoccer_dashboard.referral_link} #SoccerTraining #InterSoccer"</p>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-primary" id="copy-post">Copy Post</button>
                <button class="btn-secondary" id="share-post">Share Now</button>
            </div>
        `);

        // Bind template selection
        let selectedTemplate = null;
        modal.querySelectorAll('.template-option').forEach(option => {
            option.addEventListener('click', function() {
                modal.querySelectorAll('.template-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                selectedTemplate = this.querySelector('p').textContent;
            });
        });

        // Bind action buttons
        modal.querySelector('#copy-post').addEventListener('click', () => {
            if (selectedTemplate) {
                this.copyToClipboard(selectedTemplate);
                this.showNotification('Post copied to clipboard!', 'success');
            }
        });

        modal.querySelector('#share-post').addEventListener('click', () => {
            if (selectedTemplate) {
                this.shareToSocialMedia(selectedTemplate);
            }
        });
    }

    showEmailComposer() {
        const modal = this.createModal('Send Referral Email', `
            <form id="email-form">
                <div class="form-group">
                    <label for="email-recipient">Recipient Email</label>
                    <input type="email" id="email-recipient" required placeholder="friend@example.com">
                </div>
                <div class="form-group">
                    <label for="email-subject">Subject</label>
                    <input type="text" id="email-subject" required value="Join me at InterSoccer!">
                </div>
                <div class="form-group">
                    <label for="email-message">Message</label>
                    <textarea id="email-message" rows="6" required>Hi there!

I thought you'd be interested in InterSoccer's personalized soccer training programs. I've been really enjoying the coaching and wanted to share this opportunity with you.

Click here to check it out: ${intersoccer_dashboard.referral_link}

Best regards,
${intersoccer_dashboard.user_name}</textarea>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-primary">Send Email</button>
                    <button type="button" class="btn-secondary" onclick="this.closest('.modal').remove()">Cancel</button>
                </div>
            </form>
        `);

        // Bind form submission
        modal.querySelector('#email-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.sendReferralEmail(new FormData(e.target));
        });
    }

    showSupportModal() {
        const modal = this.createModal('Contact Support', `
            <div class="support-options">
                <div class="support-option">
                    <div class="support-icon">💬</div>
                    <h4>Live Chat</h4>
                    <p>Get instant help from our support team</p>
                    <button class="btn-primary" onclick="window.open('https://intersoccer.com/support/chat', '_blank')">Start Chat</button>
                </div>
                <div class="support-option">
                    <div class="support-icon">📧</div>
                    <h4>Email Support</h4>
                    <p>Send us a detailed message</p>
                    <button class="btn-secondary" onclick="window.location.href='mailto:support@intersoccer.com'">Send Email</button>
                </div>
                <div class="support-option">
                    <div class="support-icon">❓</div>
                    <h4>FAQ</h4>
                    <p>Browse our knowledge base</p>
                    <button class="btn-secondary" onclick="window.open('https://intersoccer.com/faq', '_blank')">View FAQ</button>
                </div>
            </div>
        `);
    }

    createModal(title, content) {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>${title}</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    ${content}
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        modal.style.display = 'block';

        // Bind close events
        modal.querySelector('.modal-close').addEventListener('click', () => modal.remove());
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove();
        });

        return modal;
    }

    copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
        } else {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
        }
    }

    shareToSocialMedia(text) {
        // This would integrate with social media APIs
        // For now, just copy to clipboard
        this.copyToClipboard(text);
        this.showNotification('Post copied! Share it on your favorite social platform.', 'success');
    }

    sendReferralEmail(formData) {
        // Show loading state
        const submitBtn = document.querySelector('#email-form .btn-primary');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Sending...';
        submitBtn.disabled = true;

        // Prepare data
        formData.append('action', 'send_referral_email');
        formData.append('nonce', intersoccer_dashboard.nonce);

        fetch(intersoccer_dashboard.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showNotification('Email sent successfully!', 'success');
                document.querySelector('.modal').remove();
            } else {
                this.showNotification(data.data.message || 'Failed to send email', 'error');
            }
        })
        .catch(error => {
            console.error('Email send error:', error);
            this.showNotification('Failed to send email. Please try again.', 'error');
        })
        .finally(() => {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <span class="notification-icon">${type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ'}</span>
            <span class="notification-message">${message}</span>
        `;

        document.body.appendChild(notification);

        // Trigger animation
        setTimeout(() => notification.classList.add('show'), 100);

        // Auto remove
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    initializeCharts() {
        // Initialize performance chart if Chart.js is available
        if (typeof Chart !== 'undefined') {
            this.initializePerformanceChart();
        }
    }

    initializePerformanceChart() {
        const ctx = document.getElementById('performance-chart');
        if (!ctx) return;

        const chartData = intersoccer_dashboard.chart_data || {
            labels: [],
            referrals: [],
            credits: []
        };

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Referrals',
                    data: chartData.referrals,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Credits Earned',
                    data: chartData.credits,
                    borderColor: '#764ba2',
                    backgroundColor: 'rgba(118, 75, 162, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    }

    updateChartPeriod(days) {
        // Update chart data based on selected period
        fetch(intersoccer_dashboard.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new FormData({
                action: 'get_chart_data',
                period: days,
                nonce: intersoccer_dashboard.nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.updateChart(data.data);
            }
        })
        .catch(error => {
            console.error('Failed to update chart:', error);
        });
    }

    updateChart(data) {
        const chart = Chart.getChart('performance-chart');
        if (chart) {
            chart.data.labels = data.labels;
            chart.data.datasets[0].data = data.referrals;
            chart.data.datasets[1].data = data.credits;
            chart.update();
        }
    }

    initializeAnimations() {
        // Initialize AOS if available
        if (typeof AOS !== 'undefined') {
            AOS.init({
                duration: 600,
                easing: 'ease-out-cubic',
                once: true,
                offset: 50
            });
        }

        // Animate counters
        this.animateCounters();
    }

    animateCounters() {
        const counters = document.querySelectorAll('[data-counter]');
        counters.forEach(counter => {
            const target = parseInt(counter.dataset.counter);
            const current = parseInt(counter.textContent.replace(/,/g, '')) || 0;
            this.animateCounter(counter, current, target, 1000);
        });
    }

    animateCounter(element, start, end, duration) {
        const startTime = performance.now();
        const difference = end - start;

        const updateCounter = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);

            const current = Math.floor(start + (difference * progress));
            element.textContent = this.formatNumber(current);

            if (progress < 1) {
                requestAnimationFrame(updateCounter);
            }
        };

        requestAnimationFrame(updateCounter);
    }

    formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        } else if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toLocaleString();
    }

    loadDashboardData() {
        // Load any dynamic data that needs to be fetched
        this.loadLeaderboardData();
        this.loadActivityData();
    }

    loadLeaderboardData() {
        // This would fetch updated leaderboard data
        // Implementation depends on your backend API
    }

    loadActivityData() {
        // This would fetch recent activity data
        // Implementation depends on your backend API
    }

    showAllActivity() {
        // Navigate to full activity page or open modal
        window.location.href = intersoccer_dashboard.admin_url + 'admin.php?page=intersoccer-coach-activity';
    }

    showFullLeaderboard() {
        // Open full leaderboard modal or navigate to page
        const modal = this.createModal('Full Leaderboard', `
            <div id="full-leaderboard-content">
                <p>Loading leaderboard...</p>
            </div>
        `);

        // Load full leaderboard data
        fetch(intersoccer_dashboard.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new FormData({
                action: 'get_full_leaderboard',
                nonce: intersoccer_dashboard.nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const content = modal.querySelector('#full-leaderboard-content');
                content.innerHTML = this.renderLeaderboard(data.data);
            }
        })
        .catch(error => {
            console.error('Failed to load leaderboard:', error);
        });
    }

    renderLeaderboard(leaderboardData) {
        return `
            <div class="leaderboard-list full">
                ${leaderboardData.map((performer, index) => `
                    <div class="leaderboard-item ${performer.ID == intersoccer_dashboard.user_id ? 'current-user' : ''}">
                        <div class="rank-badge ${index < 3 ? 'top-' + (index + 1) : ''}">
                            ${index + 1}
                        </div>
                        <div class="performer-info">
                            <div class="performer-name">
                                ${performer.display_name}
                                ${performer.ID == intersoccer_dashboard.user_id ? '<span class="you-badge">You</span>' : ''}
                            </div>
                            <div class="performer-stats">
                                ${performer.referral_count} referrals •
                                ${this.formatNumber(performer.total_credits)} CHF
                            </div>
                        </div>
                        <div class="performer-tier">
                            <span class="tier-badge ${performer.tier.toLowerCase()}">
                                ${performer.tier}
                            </span>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }
}

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.modern-coach-dashboard')) {
        new ModernCoachDashboard();
    }
});

// Export for potential use in other scripts
window.ModernCoachDashboard = ModernCoachDashboard;