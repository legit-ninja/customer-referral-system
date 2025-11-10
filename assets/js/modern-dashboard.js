/**
 * Modern Coach Dashboard JavaScript
 * Handles interactive functionality for the modern dashboard
 */

class ModernCoachDashboard {
    constructor() {
        const defaultLabels = {
            no_events_title: 'No events added yet',
            no_events_description: 'Add the events you coach so we can generate direct referral links for customers.',
            copy: 'Copy',
            open: 'Open',
            remove: 'Remove',
            search_prompt: 'Please enter at least two characters to search.',
            searching: 'Searching...',
            no_results: 'No events found.',
            search_failed: 'Search failed. Please try again.',
            event_selected: 'Event selected. Click "Request Event" to submit.',
            select_event_first: 'Please select an event before requesting.',
            request_success: 'Event request submitted. Awaiting approval.',
            request_error: 'Unable to request event.',
            network_error: 'Network error. Please try again.',
            event_link_copied: 'Event link copied to clipboard!',
            remove_confirm: 'Remove this event from your list?',
            remove_success: 'Event removed.',
            remove_error: 'Unable to remove event.',
            refresh_error: 'Unable to refresh events.',
            referral_link_copied: 'Referral link copied to clipboard!',
            event_result_meta_pattern: 'ID: %1$s • %2$s',
            social_modal_title: 'Create Social Media Post',
            social_instagram_title: 'Instagram Post',
            social_instagram_body: 'Transform your game with personalized soccer training! Join me at InterSoccer - link in bio!',
            social_facebook_title: 'Facebook Post',
            social_facebook_body: 'Looking to improve your soccer skills? I\'m now partnering with InterSoccer to offer personalized training programs. Click here to get started: %s',
            social_twitter_title: 'Twitter Post',
            social_twitter_body: 'Level up your soccer game! Join InterSoccer for personalized training. Link: %s #SoccerTraining #InterSoccer',
            social_copy_post: 'Copy Post',
            social_share_now: 'Share Now',
            social_copy_success: 'Post copied to clipboard!',
            social_share_copy_success: 'Post copied! Share it on your favorite social platform.',
            email_modal_title: 'Send Referral Email',
            email_recipient_label: 'Recipient Email',
            email_recipient_placeholder: 'friend@example.com',
            email_subject_label: 'Subject',
            email_subject_default: 'Join me at InterSoccer!',
            email_message_label: 'Message',
            email_message_default: "Hi there!\n\nI thought you'd be interested in InterSoccer's personalized soccer training programs. I've been really enjoying the coaching and wanted to share this opportunity with you.\n\nClick here to check it out: %1$s\n\nBest regards,\n%2$s",
            email_send_button: 'Send Email',
            cancel_button: 'Cancel',
            email_sending: 'Sending...',
            email_sent_success: 'Email sent successfully!',
            email_send_failed: 'Failed to send email',
            email_send_retry: 'Failed to send email. Please try again.',
            support_modal_title: 'Contact Support',
            support_live_chat_title: 'Live Chat',
            support_live_chat_description: 'Get instant help from our support team',
            support_live_chat_action: 'Start Chat',
            support_email_title: 'Email Support',
            support_email_description: 'Send us a detailed message',
            support_email_action: 'Send Email',
            support_faq_title: 'FAQ',
            support_faq_description: 'Browse our knowledge base',
            support_faq_action: 'View FAQ',
            modal_close_label: 'Close modal',
            chart_referrals_label: 'Referrals',
            chart_credits_label: 'Credits Earned',
            leaderboard_modal_title: 'Full Leaderboard',
            leaderboard_loading: 'Loading leaderboard...',
            leaderboard_you_badge: 'You',
            leaderboard_stats_pattern: '%1$s referrals • %2$s CHF'
        };

        this.labels = Object.assign({}, defaultLabels, (typeof intersoccer_dashboard !== 'undefined' && intersoccer_dashboard.i18n) ? intersoccer_dashboard.i18n : {});
        this.init();
    }

    init() {
        this.bindEvents();
        this.initializeCharts();
        this.initializeAnimations();
        this.loadDashboardData();
        this.initializeCoachEvents();
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

    initializeCoachEvents() {
        if (!intersoccer_dashboard || !intersoccer_dashboard.coach_events_nonce) {
            return;
        }

        const labels = this.labels;

        const searchBtn = document.getElementById('coach-event-search-btn');
        const searchInput = document.getElementById('coach-event-search-input');
        const addBtn = document.getElementById('coach-event-add-btn');
        const resultsContainer = document.getElementById('coach-event-search-results');
        const selectedIdInput = document.getElementById('coach-event-selected-id');
        const selectedTypeInput = document.getElementById('coach-event-selected-type');
        const spinner = document.getElementById('coach-event-spinner');
        const refreshBtn = document.getElementById('coach-events-refresh');

        if (searchBtn && searchInput) {
            const performSearch = () => {
                const term = searchInput.value.trim();
                if (term.length < 2) {
                    this.showNotification(labels.search_prompt, 'info');
                    return;
                }

                resultsContainer.innerHTML = `<p class="coach-event-search-empty">${this.escapeHtml(labels.searching)}</p>`;

                fetch(intersoccer_dashboard.ajax_url, {
                    method: 'POST',
                    body: new URLSearchParams({
                        action: 'intersoccer_search_events',
                        nonce: intersoccer_dashboard.coach_events_nonce,
                        term
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && Array.isArray(data.data.results)) {
                        this.renderCoachEventResults(data.data.results);
                    } else {
                        resultsContainer.innerHTML = `<p class="coach-event-search-empty">${this.escapeHtml(labels.no_results)}</p>`;
                    }
                })
                .catch(() => {
                    resultsContainer.innerHTML = `<p class="coach-event-search-empty">${this.escapeHtml(labels.search_failed)}</p>`;
                });
            };

            searchBtn.addEventListener('click', performSearch);
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    performSearch();
                }
            });
        }

        if (resultsContainer) {
            resultsContainer.addEventListener('click', (e) => {
                const item = e.target.closest('.coach-event-result');
                if (!item) return;

                const eventId = item.dataset.eventId;
                const eventType = item.dataset.eventType;
                const eventTitle = item.dataset.eventTitle;

                selectedIdInput.value = eventId;
                selectedTypeInput.value = eventType;
                searchInput.value = eventTitle;

                resultsContainer.querySelectorAll('.coach-event-result').forEach(el => el.classList.remove('selected'));
                item.classList.add('selected');

                this.showNotification(labels.event_selected, 'info');
            });
        }

        if (addBtn) {
            addBtn.addEventListener('click', () => {
                const eventId = selectedIdInput.value;
                if (!eventId) {
                    this.showNotification(labels.select_event_first, 'error');
                    return;
                }

                if (spinner) {
                    spinner.classList.add('is-active');
                }

                fetch(intersoccer_dashboard.ajax_url, {
                    method: 'POST',
                    body: new URLSearchParams({
                        action: 'intersoccer_save_coach_event',
                        nonce: intersoccer_dashboard.coach_events_nonce,
                        event_id: eventId,
                        event_type: selectedTypeInput.value
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.showNotification(labels.request_success, 'success');
                        selectedIdInput.value = '';
                        selectedTypeInput.value = '';
                        searchInput.value = '';
                        resultsContainer.innerHTML = '';
                        this.refreshCoachEvents();
                    } else {
                        const message = data.data && (data.data.message || data.data);
                        this.showNotification(message || labels.request_error, 'error');
                    }
                })
                .catch(() => {
                    this.showNotification(labels.network_error, 'error');
                })
                .finally(() => {
                    if (spinner) {
                        spinner.classList.remove('is-active');
                    }
                });
            });
        }

        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.refreshCoachEvents());
        }

        const eventsBody = document.getElementById('coach-events-body');
        if (eventsBody) {
            eventsBody.addEventListener('click', (e) => {
                const copyBtn = e.target.closest('.coach-event-copy');
                if (copyBtn && copyBtn.dataset.link) {
                    this.copyToClipboard(copyBtn.dataset.link);
                    this.showNotification(labels.event_link_copied, 'success');
                }

                const removeBtn = e.target.closest('.coach-event-remove');
                if (removeBtn) {
                    if (!confirm(labels.remove_confirm)) {
                        return;
                    }

                    removeBtn.disabled = true;
                    fetch(intersoccer_dashboard.ajax_url, {
                        method: 'POST',
                        body: new URLSearchParams({
                            action: 'intersoccer_delete_coach_event',
                            nonce: intersoccer_dashboard.coach_events_nonce,
                            assignment_id: removeBtn.dataset.assignmentId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.showNotification(labels.remove_success, 'success');
                            this.refreshCoachEvents();
                        } else {
                            const errorMessage = data.data && (data.data.message || data.data);
                            this.showNotification(errorMessage || labels.remove_error, 'error');
                        }
                    })
                    .catch(() => {
                        this.showNotification(labels.network_error, 'error');
                    })
                    .finally(() => {
                        removeBtn.disabled = false;
                    });
                }
            });
        }
    }

    renderCoachEventResults(results) {
        const container = document.getElementById('coach-event-search-results');
        if (!container) {
            return;
        }

        const labels = this.labels;

        if (!results.length) {
            container.innerHTML = `<p class="coach-event-search-empty">${this.escapeHtml(labels.no_results)}</p>`;
            return;
        }

        container.innerHTML = results.map(result => {
            const idValue = String(result.id);
            const typeLabelValue = result.type_label || result.type || '';
            const metaText = this.formatLabel('event_result_meta_pattern', idValue, typeLabelValue);
            const id = this.escapeHtml(idValue);
            const title = this.escapeHtml(result.title || '');
            const typeLabelAttr = this.escapeHtml(result.type || '');
            return `
                <button type="button" class="coach-event-result" data-event-id="${id}" data-event-type="${typeLabelAttr}" data-event-title="${title}">
                    <strong>${title}</strong>
                    <span class="coach-event-result-meta">${this.escapeHtml(metaText)}</span>
                </button>
            `;
        }).join('');
    }

    refreshCoachEvents() {
        const body = document.getElementById('coach-events-body');
        if (!body) return;

        const labels = this.labels;

        fetch(intersoccer_dashboard.ajax_url, {
            method: 'POST',
            body: new URLSearchParams({
                action: 'intersoccer_get_coach_events',
                nonce: intersoccer_dashboard.coach_events_nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                this.showNotification(labels.refresh_error, 'error');
                return;
            }

            const events = (data.data && data.data.events) || [];
            if (!events.length) {
                body.innerHTML = `
                    <div class="empty-state">
                        <i class="icon-calendar"></i>
                        <h4>${this.escapeHtml(labels.no_events_title)}</h4>
                        <p>${this.escapeHtml(labels.no_events_description)}</p>
                    </div>
                `;
                return;
            }

            body.innerHTML = `
                <ul class="coach-events-list">
                    ${events.map(event => {
                        const assignmentId = this.escapeHtml(String(event.id));
                        const title = this.escapeHtml(event.event_title || '');
                        const permalink = event.event_permalink ? this.escapeHtml(event.event_permalink) : '';
                        const statusClass = (event.status || '').toString().toLowerCase().replace(/[^a-z0-9_-]/g, '');
                        const statusLabel = this.escapeHtml(event.status_label || event.status || '');
                        const sourceLabel = this.escapeHtml(event.source_label || event.source || '');
                        const assignedDate = event.assigned_at ? `<span class="event-date">• ${this.escapeHtml(event.assigned_at)}</span>` : '';
                        const eventLink = event.event_link ? this.escapeHtml(event.event_link) : '';
                        const shareMarkup = eventLink ? `
                            <div class="event-share">
                                <input type="text" class="coach-event-share-input" value="${eventLink}" readonly>
                                <button class="btn-tertiary coach-event-copy" data-link="${eventLink}">${this.escapeHtml(labels.copy)}</button>
                                <a class="btn-secondary" href="${eventLink}" target="_blank" rel="noopener noreferrer">${this.escapeHtml(labels.open)}</a>
                            </div>
                        ` : '';

                        const titleMarkup = permalink
                            ? `<a href="${permalink}" target="_blank" rel="noopener noreferrer">${title}</a>`
                            : title;

                        return `
                            <li class="coach-event-item" data-assignment-id="${assignmentId}">
                                <div class="event-title">
                                    ${titleMarkup}
                                </div>
                                <div class="event-meta">
                                    <span class="event-status status-${statusClass}">${statusLabel}</span>
                                    <span class="event-source">• ${sourceLabel}</span>
                                    ${assignedDate}
                                </div>
                                ${shareMarkup}
                                <div class="event-actions">
                                    <button class="btn-tertiary coach-event-remove" data-assignment-id="${assignmentId}">${this.escapeHtml(labels.remove)}</button>
                                </div>
                            </li>
                        `;
                    }).join('')}
                </ul>
            `;
        })
        .catch(() => {
            this.showNotification(labels.refresh_error, 'error');
        });
    }

    copyReferralLink() {
        const linkInput = document.getElementById('referral-link-input');
        if (linkInput) {
            linkInput.select();
            document.execCommand('copy');
            this.showNotification(this.getLabel('referral_link_copied'), 'success');
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
        const labels = this.labels;
        const referralLink = intersoccer_dashboard.referral_link || '';

        const modal = this.createModal(this.getLabel('social_modal_title'), `
            <div class="social-templates">
                <div class="template-option" data-platform="instagram">
                    <h4>${this.escapeHtml(this.getLabel('social_instagram_title'))}</h4>
                    <p>${this.escapeHtml(this.formatLabel('social_instagram_body'))}</p>
                </div>
                <div class="template-option" data-platform="facebook">
                    <h4>${this.escapeHtml(this.getLabel('social_facebook_title'))}</h4>
                    <p>${this.escapeHtml(this.formatLabel('social_facebook_body', referralLink))}</p>
                </div>
                <div class="template-option" data-platform="twitter">
                    <h4>${this.escapeHtml(this.getLabel('social_twitter_title'))}</h4>
                    <p>${this.escapeHtml(this.formatLabel('social_twitter_body', referralLink))}</p>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-primary" id="copy-post">${this.escapeHtml(this.getLabel('social_copy_post'))}</button>
                <button class="btn-secondary" id="share-post">${this.escapeHtml(this.getLabel('social_share_now'))}</button>
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
                this.showNotification(labels.social_copy_success, 'success');
            }
        });

        modal.querySelector('#share-post').addEventListener('click', () => {
            if (selectedTemplate) {
                this.shareToSocialMedia(selectedTemplate);
            }
        });
    }

    showEmailComposer() {
        const labels = this.labels;
        const referralLink = intersoccer_dashboard.referral_link || '';
        const userName = intersoccer_dashboard.user_name || '';
        const emailBody = this.formatLabel('email_message_default', referralLink, userName);

        const modal = this.createModal(this.getLabel('email_modal_title'), `
            <form id="email-form">
                <div class="form-group">
                    <label for="email-recipient">${this.escapeHtml(this.getLabel('email_recipient_label'))}</label>
                    <input type="email" id="email-recipient" required placeholder="${this.escapeHtml(this.getLabel('email_recipient_placeholder'))}">
                </div>
                <div class="form-group">
                    <label for="email-subject">${this.escapeHtml(this.getLabel('email_subject_label'))}</label>
                    <input type="text" id="email-subject" required value="${this.escapeHtml(this.getLabel('email_subject_default'))}">
                </div>
                <div class="form-group">
                    <label for="email-message">${this.escapeHtml(this.getLabel('email_message_label'))}</label>
                    <textarea id="email-message" rows="6" required>${this.escapeHtml(emailBody)}</textarea>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-primary">${this.escapeHtml(this.getLabel('email_send_button'))}</button>
                    <button type="button" class="btn-secondary" data-modal-dismiss="true">${this.escapeHtml(this.getLabel('cancel_button'))}</button>
                </div>
            </form>
        `);

        modal.querySelector('[data-modal-dismiss="true"]').addEventListener('click', () => modal.remove());

        // Bind form submission
        modal.querySelector('#email-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.sendReferralEmail(new FormData(e.target));
        });
    }

    showSupportModal() {
        const labels = this.labels;
        const modal = this.createModal(this.getLabel('support_modal_title'), `
            <div class="support-options">
                <div class="support-option">
                    <div class="support-icon">💬</div>
                    <h4>${this.escapeHtml(this.getLabel('support_live_chat_title'))}</h4>
                    <p>${this.escapeHtml(this.getLabel('support_live_chat_description'))}</p>
                    <button class="btn-primary" onclick="window.open('https://intersoccer.com/support/chat', '_blank')">${this.escapeHtml(this.getLabel('support_live_chat_action'))}</button>
                </div>
                <div class="support-option">
                    <div class="support-icon">📧</div>
                    <h4>${this.escapeHtml(this.getLabel('support_email_title'))}</h4>
                    <p>${this.escapeHtml(this.getLabel('support_email_description'))}</p>
                    <button class="btn-secondary" onclick="window.location.href='mailto:support@intersoccer.com'">${this.escapeHtml(this.getLabel('support_email_action'))}</button>
                </div>
                <div class="support-option">
                    <div class="support-icon">❓</div>
                    <h4>${this.escapeHtml(this.getLabel('support_faq_title'))}</h4>
                    <p>${this.escapeHtml(this.getLabel('support_faq_description'))}</p>
                    <button class="btn-secondary" onclick="window.open('https://intersoccer.com/faq', '_blank')">${this.escapeHtml(this.getLabel('support_faq_action'))}</button>
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
                    <button class="modal-close" type="button" aria-label="${this.escapeHtml(this.getLabel('modal_close_label'))}">&times;</button>
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
        this.showNotification(this.getLabel('social_share_copy_success'), 'success');
    }

    sendReferralEmail(formData) {
        // Show loading state
        const submitBtn = document.querySelector('#email-form .btn-primary');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = this.getLabel('email_sending');
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
                this.showNotification(this.getLabel('email_sent_success'), 'success');
                document.querySelector('.modal').remove();
            } else {
                const message = data.data && (data.data.message || data.data);
                this.showNotification(message || this.getLabel('email_send_failed'), 'error');
            }
        })
        .catch(error => {
            console.error('Email send error:', error);
            this.showNotification(this.getLabel('email_send_retry'), 'error');
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

    getLabel(key) {
        return this.labels[key] || '';
    }

    formatLabel(key, ...args) {
        let template = this.getLabel(key);
        if (!template) {
            return '';
        }

        if (!args.length) {
            return template;
        }

        args.forEach((value, index) => {
            const positional = new RegExp(`%${index + 1}\\$s`, 'g');
            template = template.replace(positional, value);
            template = template.replace('%s', value);
        });

        return template;
    }

    escapeHtml(str) {
        if (typeof str !== 'string') {
            return str;
        }

        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
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

        const existingChart = Chart.getChart(ctx);
        if (existingChart) {
            existingChart.destroy();
        }

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: this.getLabel('chart_referrals_label'),
                    data: chartData.referrals,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: this.getLabel('chart_credits_label'),
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
        const modal = this.createModal(this.getLabel('leaderboard_modal_title'), `
            <div id="full-leaderboard-content">
                <p>${this.escapeHtml(this.getLabel('leaderboard_loading'))}</p>
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
        const youBadge = this.escapeHtml(this.getLabel('leaderboard_you_badge'));
        return `
            <div class="leaderboard-list full">
                ${leaderboardData.map((performer, index) => {
                    const statsText = this.formatLabel(
                        'leaderboard_stats_pattern',
                        String(performer.referral_count),
                        this.formatNumber(performer.total_credits)
                    );
                    const tierClass = (performer.tier || '').toString().toLowerCase().replace(/[^a-z0-9_-]/g, '');
                    return `
                        <div class="leaderboard-item ${performer.ID == intersoccer_dashboard.user_id ? 'current-user' : ''}">
                            <div class="rank-badge ${index < 3 ? 'top-' + (index + 1) : ''}">
                                ${index + 1}
                            </div>
                            <div class="performer-info">
                                <div class="performer-name">
                                    ${this.escapeHtml(performer.display_name)}
                                    ${performer.ID == intersoccer_dashboard.user_id ? `<span class="you-badge">${youBadge}</span>` : ''}
                                </div>
                                <div class="performer-stats">
                                    ${this.escapeHtml(statsText)}
                                </div>
                            </div>
                            <div class="performer-tier">
                                <span class="tier-badge ${tierClass}">
                                    ${this.escapeHtml(performer.tier)}
                                </span>
                            </div>
                        </div>
                    `;
                }).join('')}
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