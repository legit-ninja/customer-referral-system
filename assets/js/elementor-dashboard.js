// File: assets/js/elementor-dashboard.js

jQuery(document).ready(function($) {
    'use strict';
    
    // Global variables
    let selectedCoachId = null;
    let availableCoaches = [];
    let debounceTimer = null;

    if (typeof window.intersoccerFetchCustomerWidgetSummary === 'undefined') {
        window.intersoccerFetchCustomerWidgetSummary = function(options) {
            options = options || {};

            if (typeof intersoccer_elementor === 'undefined' || !intersoccer_elementor.ajax_url) {
                const endpointError = {
                    message: 'Customer widget endpoint is not available.',
                    code: 'intersoccer_endpoint_missing'
                };

                if (typeof options.onError === 'function') {
                    options.onError(endpointError);
                }

                return Promise.reject(endpointError);
            }

            const nonceValue = (typeof intersoccer_elementor !== 'undefined' && intersoccer_elementor.nonce)
                ? intersoccer_elementor.nonce
                : (typeof intersoccer_dashboard !== 'undefined' ? intersoccer_dashboard.nonce : '');

            const requestData = {
                action: 'intersoccer_get_customer_widget_summary',
                nonce: nonceValue
            };

            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: intersoccer_elementor.ajax_url,
                    type: 'POST',
                    data: requestData,
                    success: function(response) {
                        if (response && response.success) {
                            if (typeof options.onSuccess === 'function') {
                                options.onSuccess(response.data);
                            }
                            resolve(response.data);
                        } else {
                            const errorData = (response && response.data) ? response.data : {
                                message: 'Unknown response from server.',
                                code: 'intersoccer_unknown_response'
                            };

                            if (typeof options.onError === 'function') {
                                options.onError(errorData);
                            }

                            reject(errorData);
                        }
                    },
                    error: function(xhr, status, errorThrown) {
                        const ajaxError = {
                            message: errorThrown || 'Request failed.',
                            code: 'intersoccer_request_failed',
                            status: status
                        };

                        if (typeof options.onError === 'function') {
                            options.onError(ajaxError);
                        }

                        reject(ajaxError);
                    }
                });
            });
        };
    }
    
    // Initialize dashboard functionality
    initializeDashboard();
    
    function initializeDashboard() {
        // Initialize all dashboard components
        initializeAnimations();
        initializeCopyFunctionality();
        initializeCoachSelection();
        initializeGiftCredits();
        initializeProgressAnimations();
        initializeMobileResponsive();
        
        // Initialize tooltips if available
        if (typeof $.fn.tooltip !== 'undefined') {
            $('[title]').tooltip({
                placement: 'top',
                trigger: 'hover'
            });
        }
        
        console.log('InterSoccer Elementor Dashboard initialized');
    }
    
    // Animation handling
    function initializeAnimations() {
        // Intersection Observer for scroll animations
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-in');
                        
                        // Special handling for progress bars
                        const progressBars = entry.target.querySelectorAll('.progress-fill');
                        progressBars.forEach(function(bar) {
                            animateProgressBar(bar);
                        });
                        
                        // Special handling for number animations
                        const numbers = entry.target.querySelectorAll('.stat-number');
                        numbers.forEach(function(number) {
                            animateNumber(number);
                        });
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });
            
            // Observe all dashboard sections
            $('.intersoccer-elementor-dashboard > div, .stat-card, .badge-item').each(function() {
                observer.observe(this);
            });
        }
        
        // Animate credits display on page load
        setTimeout(function() {
            const creditsDisplay = document.getElementById('credits-display');
            if (creditsDisplay) {
                animateNumber(creditsDisplay);
            }
        }, 500);
    }
    
    function animateNumber(element) {
        if (element.dataset.animated === 'true') return;
        
        const targetValue = parseFloat(element.textContent.replace(/[^0-9.-]/g, ''));
        if (isNaN(targetValue)) return;
        
        let currentValue = 0;
        const increment = targetValue / 60; // 60 frames for 1 second at 60fps
        const isFloat = targetValue % 1 !== 0;
        
        element.dataset.animated = 'true';
        
        function updateNumber() {
            currentValue += increment;
            if (currentValue >= targetValue) {
                currentValue = targetValue;
                element.textContent = isFloat ? currentValue.toFixed(2) : Math.floor(currentValue);
                return;
            }
            
            element.textContent = isFloat ? currentValue.toFixed(2) : Math.floor(currentValue);
            requestAnimationFrame(updateNumber);
        }
        
        requestAnimationFrame(updateNumber);
    }
    
    function animateProgressBar(bar) {
        if (bar.dataset.animated === 'true') return;
        
        const targetWidth = bar.style.width || '0%';
        bar.style.width = '0%';
        bar.dataset.animated = 'true';
        
        setTimeout(function() {
            bar.style.width = targetWidth;
        }, 200);
    }
    
    // Copy referral link functionality
    function initializeCopyFunctionality() {
        $(document).on('click', '#copy-link-btn, .copy-referral-link', function(e) {
            e.preventDefault();
            copyReferralLink(this);
        });
    }
    
    window.copyReferralLink = function(button) {
        const linkInput = document.getElementById('referral-link') || 
                         button.parentElement.querySelector('input[readonly]');
        const copyBtn = button || document.getElementById('copy-link-btn');
        
        if (!linkInput || !copyBtn) {
            console.error('Required elements not found for copy functionality');
            return;
        }
        
        // Modern clipboard API
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(linkInput.value).then(function() {
                showCopySuccess(copyBtn);
                triggerCopyEffects();
            }).catch(function(err) {
                console.error('Failed to copy: ', err);
                fallbackCopy(linkInput, copyBtn);
            });
        } else {
            fallbackCopy(linkInput, copyBtn);
        }
    };
    
    function fallbackCopy(linkInput, copyBtn) {
        try {
            linkInput.select();
            linkInput.setSelectionRange(0, 99999); // For mobile devices
            document.execCommand('copy');
            showCopySuccess(copyBtn);
            triggerCopyEffects();
        } catch (err) {
            console.error('Fallback copy failed: ', err);
            showError('Failed to copy link');
        }
    }
    
    function showCopySuccess(button) {
        const $button = $(button);
        $button.addClass('copied');
        
        // Create ripple effect
        const ripple = $('<span class="copy-ripple"></span>');
        $button.append(ripple);
        
        setTimeout(function() {
            $button.removeClass('copied');
            ripple.remove();
        }, 2000);
    }
    
    function triggerCopyEffects() {
        // Trigger credit pulse animation
        const creditCard = $('.credits-card');
        if (creditCard.length) {
            creditCard.addClass('pulse-effect');
            setTimeout(function() {
                creditCard.removeClass('pulse-effect');
            }, 600);
        }
        
        // Show success toast if available
        showToast(intersoccer_elementor.strings.copy_success || 'Link copied!', 'success');
    }
    
    // Coach selection functionality
    function initializeCoachSelection() {
        // Modal handlers
        $(document).on('click', '.select-coach-btn, .change-coach-btn', function(e) {
            e.preventDefault();
            showCoachSelection();
        });
        
        $(document).on('click', '.close, .modal-backdrop', function(e) {
            if (e.target === this) {
                hideCoachSelection();
            }
        });
        
        // Close modal with escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#coach-selection-modal').is(':visible')) {
                hideCoachSelection();
            }
        });
        
        // Coach search functionality
        $(document).on('input', '#coach-search', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                searchCoaches();
            }, 300);
        });
        
        // Filter buttons
        $(document).on('click', '.filter-btn', function() {
            const filter = $(this).data('filter');
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            filterCoaches(filter);
        });
        
        // Coach selection
        $(document).on('click', '.coach-card', function() {
            const coachId = $(this).data('coach-id');
            selectCoach(coachId);
        });
        
        // Confirm selection
        $(document).on('click', '#confirm-selection', function() {
            confirmCoachSelection();
        });
    }
    
    window.showCoachSelection = function() {
        const modal = $('#coach-selection-modal');
        if (modal.length === 0) {
            console.error('Coach selection modal not found');
            return;
        }
        
        modal.show();
        $('body').addClass('modal-open');
        loadCoaches();
        
        // Focus management for accessibility
        modal.find('#coach-search').focus();
    };
    
    window.hideCoachSelection = function() {
        const modal = $('#coach-selection-modal');
        modal.hide();
        $('body').removeClass('modal-open');
        selectedCoachId = null;
        $('#confirm-selection').prop('disabled', true);
    };
    
    function loadCoaches(search = '', filter = 'all') {
        const coachesList = $('#coaches-list');
        coachesList.html('<div class="loading-spinner">Loading coaches...</div>');
        
        $.ajax({
            url: intersoccer_elementor.ajax_url,
            type: 'POST',
            data: {
                action: 'get_available_coaches',
                nonce: intersoccer_elementor.nonce,
                search: search,
                filter: filter
            },
            success: function(response) {
                if (response.success) {
                    availableCoaches = response.data.coaches;
                    renderCoaches(availableCoaches);
                } else {
                    coachesList.html('<p class="error-message">Failed to load coaches. Please try again.</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                coachesList.html('<p class="error-message">Error loading coaches. Please check your connection.</p>');
            }
        });
    }
    
    function renderCoaches(coaches) {
        const container = $('#coaches-list');
        
        if (coaches.length === 0) {
            container.html('<p class="no-results">No coaches found matching your criteria.</p>');
            return;
        }
        
        const coachesHtml = coaches.map(function(coach) {
            const benefitsHtml = coach.benefits.map(function(benefit) {
                return `<small>• ${benefit}</small>`;
            }).join('<br>');
            
            return `
                <div class="coach-card" data-coach-id="${coach.id}" tabindex="0" role="button" aria-label="Select ${coach.name}">
                    <div class="coach-card-header">
                        <h5>${escapeHtml(coach.name)}</h5>
                        <span class="coach-tier-badge ${coach.tier.toLowerCase()}">${coach.tier}</span>
                    </div>
                    <p class="coach-specialty">${escapeHtml(coach.specialty)}</p>
                    <div class="coach-stats">
                        <span>⭐ ${coach.rating}/5</span>
                        <span>👥 ${coach.total_athletes} athletes</span>
                    </div>
                    <div class="coach-benefits">
                        ${benefitsHtml}
                    </div>
                </div>
            `;
        }).join('');
        
        container.html(coachesHtml);
        
        // Add keyboard navigation for accessibility
        container.find('.coach-card').on('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).click();
            }
        });
    }
    
    function selectCoach(coachId) {
        // Remove previous selection
        $('.coach-card').removeClass('selected').attr('aria-selected', 'false');
        
        // Select new coach
        const selectedCard = $(`.coach-card[data-coach-id="${coachId}"]`);
        selectedCard.addClass('selected').attr('aria-selected', 'true');
        
        selectedCoachId = coachId;
        $('#confirm-selection').prop('disabled', false);
        
        // Announce selection for screen readers
        const coachName = selectedCard.find('h5').text();
        announceToScreenReader(`Selected ${coachName}`);
    }
    
    function confirmCoachSelection() {
        if (!selectedCoachId) return;
        
        const confirmBtn = $('#confirm-selection');
        const originalText = confirmBtn.text();
        
        confirmBtn.text('Connecting...').prop('disabled', true);
        
        $.ajax({
            url: intersoccer_elementor.ajax_url,
            type: 'POST',
            data: {
                action: 'select_coach_partner',
                nonce: intersoccer_elementor.nonce,
                coach_id: selectedCoachId
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showToast(response.data.message || 'Failed to connect with coach', 'error');
                    confirmBtn.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                showToast('Connection error. Please try again.', 'error');
                confirmBtn.text(originalText).prop('disabled', false);
            }
        });
    }
    
    window.searchCoaches = function() {
        const searchTerm = $('#coach-search').val();
        const activeFilter = $('.filter-btn.active').data('filter') || 'all';
        loadCoaches(searchTerm, activeFilter);
    };
    
    window.filterCoaches = function(filter) {
        const searchTerm = $('#coach-search').val();
        loadCoaches(searchTerm, filter);
    };
    
    // Gift credits functionality
    function initializeGiftCredits() {
        $(document).on('submit', '#gift-credits', function(e) {
            e.preventDefault();
            handleGiftCredits(this);
        });
        
        // Real-time validation
        $(document).on('input', 'input[name="gift_amount"]', function() {
            validateGiftAmount(this);
        });
        
        $(document).on('input', 'input[name="recipient_email"]', function() {
            validateEmail(this);
        });
    }
    
    function handleGiftCredits(form) {
        const $form = $(form);
        const submitBtn = $form.find('.gift-button');
        const originalText = submitBtn.html();
        
        // Validate form
        if (!validateGiftForm(form)) {
            return;
        }
        
        submitBtn.html('⏳ Sending...').prop('disabled', true);
        
        const formData = {
            action: 'gift_credits',
            nonce: intersoccer_elementor.nonce,
            gift_amount: $form.find('input[name="gift_amount"]').val(),
            recipient_email: $form.find('input[name="recipient_email"]').val()
        };
        
        $.ajax({
            url: intersoccer_elementor.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    handleGiftSuccess(submitBtn, response.data);
                    updateCreditsDisplay(response.data.new_credits);
                    form.reset();
                } else {
                    handleGiftError(submitBtn, response.data.message);
                }
            },
            error: function() {
                handleGiftError(submitBtn, 'Connection error. Please try again.');
            },
            complete: function() {
                setTimeout(function() {
                    submitBtn.html(originalText).prop('disabled', false);
                }, 3000);
            }
        });
    }
    
    function validateGiftForm(form) {
        const $form = $(form);
        const amount = parseFloat($form.find('input[name="gift_amount"]').val());
        const email = $form.find('input[name="recipient_email"]').val();
        
        if (!amount || amount < 50 || amount > 500) {
            showError('Gift amount must be between 50 and 500 CHF');
            return false;
        }
        
        if (!email || !isValidEmail(email)) {
            showError('Please enter a valid email address');
            return false;
        }
        
        return true;
    }
    
    function validateGiftAmount(input) {
        const amount = parseFloat(input.value);
        const $input = $(input);
        
        $input.removeClass('error valid');
        
        if (amount && amount >= 50 && amount <= 500) {
            $input.addClass('valid');
        } else if (input.value) {
            $input.addClass('error');
        }
    }
    
    function validateEmail(input) {
        const email = input.value;
        const $input = $(input);
        
        $input.removeClass('error valid');
        
        if (email && isValidEmail(email)) {
            $input.addClass('valid');
        } else if (email) {
            $input.addClass('error');
        }
    }
    
    function handleGiftSuccess(submitBtn, data) {
        submitBtn.html('✅ Sent!').css('background', '#28a745');
        showToast(data.message, 'success');
        
        // Trigger celebration animation
        triggerCelebration();
    }
    
    function handleGiftError(submitBtn, message) {
        submitBtn.html('❌ Error').css('background', '#dc3545');
        showToast(message, 'error');
    }
    
    function updateCreditsDisplay(newCredits) {
        const creditsDisplay = $('#credits-display');
        if (creditsDisplay.length && newCredits !== undefined) {
            creditsDisplay.text(parseFloat(newCredits).toFixed(2));
            
            // Animate the update
            creditsDisplay.addClass('credit-update-animation');
            setTimeout(function() {
                creditsDisplay.removeClass('credit-update-animation');
            }, 1000);
        }
    }
    
    // Progress animation initialization
    function initializeProgressAnimations() {
        // Animate progress bars when they come into view
        $('.progress-bar').each(function() {
            const $progressBar = $(this);
            const $fill = $progressBar.find('.progress-fill');
            const targetWidth = $fill.data('width') || $fill[0].style.width;
            
            // Reset width for animation
            $fill.css('width', '0%');
            
            // Animate when in viewport
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        setTimeout(function() {
                            $fill.css('width', targetWidth);
                        }, 200);
                        observer.unobserve(entry.target);
                    }
                });
            });
            
            observer.observe($progressBar[0]);
        });
    }
    
    // Mobile responsive enhancements
    function initializeMobileResponsive() {
        // Handle mobile modal improvements
        $(window).on('resize orientationchange', function() {
            adjustModalForMobile();
        });
        
        // Touch-friendly interactions
        if ('ontouchstart' in window) {
            $('body').addClass('touch-device');
            
            // Add touch feedback to interactive elements
            $('.stat-card, .badge-item, .coach-card, .activity-item').on('touchstart', function() {
                $(this).addClass('touch-active');
            }).on('touchend touchcancel', function() {
                $(this).removeClass('touch-active');
            });
        }
    }
    
    function adjustModalForMobile() {
        const modal = $('#coach-selection-modal');
        if (modal.is(':visible') && $(window).width() <= 768) {
            modal.find('.modal-content').css({
                'width': '95%',
                'height': '90vh',
                'margin': '5vh auto'
            });
        }
    }
    
    // Utility functions
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function showToast(message, type = 'info') {
        // Check if toast container exists, create if not
        let toastContainer = $('.toast-container');
        if (toastContainer.length === 0) {
            toastContainer = $('<div class="toast-container"></div>');
            $('body').append(toastContainer);
        }
        
        const toast = $(`
            <div class="toast toast-${type}">
                <div class="toast-content">
                    <span class="toast-message">${escapeHtml(message)}</span>
                    <button class="toast-close" aria-label="Close">&times;</button>
                </div>
            </div>
        `);
        
        toastContainer.append(toast);
        
        // Animate in
        setTimeout(function() {
            toast.addClass('toast-show');
        }, 100);
        
        // Auto dismiss
        const dismissTimer = setTimeout(function() {
            dismissToast(toast);
        }, type === 'error' ? 5000 : 3000);
        
        // Manual dismiss
        toast.find('.toast-close').on('click', function() {
            clearTimeout(dismissTimer);
            dismissToast(toast);
        });
    }
    
    function dismissToast(toast) {
        toast.removeClass('toast-show');
        setTimeout(function() {
            toast.remove();
        }, 300);
    }
    
    function showError(message) {
        showToast(message, 'error');
    }
    
    function triggerCelebration() {
        // Create celebration particles
        const celebration = $('<div class="celebration"></div>');
        $('body').append(celebration);
        
        // Add confetti particles
        for (let i = 0; i < 20; i++) {
            const particle = $(`<div class="confetti-particle confetti-${Math.floor(Math.random() * 4) + 1}"></div>`);
            particle.css({
                left: Math.random() * 100 + '%',
                animationDelay: Math.random() * 2 + 's',
                animationDuration: (Math.random() * 2 + 2) + 's'
            });
            celebration.append(particle);
        }
        
        // Remove after animation
        setTimeout(function() {
            celebration.remove();
        }, 4000);
    }
    
    function announceToScreenReader(message) {
        const announcement = $(`<div class="sr-only" aria-live="polite">${escapeHtml(message)}</div>`);
        $('body').append(announcement);
        setTimeout(function() {
            announcement.remove();
        }, 1000);
    }
    
    // Keyboard navigation improvements
    $(document).on('keydown', function(e) {
        // Handle modal navigation
        if ($('#coach-selection-modal').is(':visible')) {
            if (e.key === 'Tab') {
                handleModalTabNavigation(e);
            }
        }
    });
    
    function handleModalTabNavigation(e) {
        const modal = $('#coach-selection-modal');
        const focusableElements = modal.find('button, input, .coach-card[tabindex="0"]');
        const firstElement = focusableElements.first();
        const lastElement = focusableElements.last();
        
        if (e.shiftKey) {
            if (document.activeElement === firstElement[0]) {
                e.preventDefault();
                lastElement.focus();
            }
        } else {
            if (document.activeElement === lastElement[0]) {
                e.preventDefault();
                firstElement.focus();
            }
        }
    }
    
    // Enhanced error handling
    window.addEventListener('error', function(e) {
        console.error('JavaScript error in dashboard:', e.error);
        
        // Show user-friendly error for critical failures
        if (e.error.message.includes('intersoccer')) {
            showError('Dashboard error occurred. Please refresh the page.');
        }
    });
    
    // Performance monitoring
    if (window.performance && window.performance.mark) {
        performance.mark('intersoccer-dashboard-start');
        
        $(window).on('load', function() {
            performance.mark('intersoccer-dashboard-loaded');
            performance.measure('intersoccer-dashboard-load-time', 'intersoccer-dashboard-start', 'intersoccer-dashboard-loaded');
            
            const measures = performance.getEntriesByType('measure');
            const loadTime = measures.find(m => m.name === 'intersoccer-dashboard-load-time');
            if (loadTime && loadTime.duration > 3000) {
                console.warn('Dashboard loading took longer than expected:', loadTime.duration + 'ms');
            }
        });
    }
    
    // Accessibility improvements
    function enhanceAccessibility() {
        // Add ARIA labels where missing
        $('.stat-card').each(function(index) {
            const $card = $(this);
            if (!$card.attr('aria-label')) {
                const number = $card.find('.stat-number').text();
                const label = $card.find('.stat-label').text();
                $card.attr('aria-label', `${number} ${label}`);
            }
        });
        
        // Add role attributes
        $('.badges-container').attr('role', 'list');
        $('.badge-item').attr('role', 'listitem');
        $('.leaderboard-list').attr('role', 'list');
        $('.leaderboard-item').attr('role', 'listitem');
        
        // Improve focus management
        $('.copy-button, .social-btn, .gift-button').on('focus', function() {
            $(this).addClass('focus-visible');
        }).on('blur', function() {
            $(this).removeClass('focus-visible');
        });
    }
    
    // Initialize accessibility on DOM ready
    enhanceAccessibility();
    
    // Data persistence for offline scenarios
    function saveToLocalStorage(key, data) {
        try {
            if (typeof Storage !== "undefined") {
                localStorage.setItem('intersoccer_' + key, JSON.stringify(data));
            }
        } catch (e) {
            console.warn('LocalStorage not available:', e);
        }
    }
    
    function loadFromLocalStorage(key) {
        try {
            if (typeof Storage !== "undefined") {
                const data = localStorage.getItem('intersoccer_' + key);
                return data ? JSON.parse(data) : null;
            }
        } catch (e) {
            console.warn('Error loading from localStorage:', e);
        }
        return null;
    }
    
    // Cache coach data for better performance
    $(document).on('coach_data_loaded', function(e, coaches) {
        saveToLocalStorage('coaches_cache', {
            data: coaches,
            timestamp: Date.now()
        });
    });
    
    // Auto-save form data
    $('#gift-credits input').on('input', function() {
        const formData = $('#gift-credits').serialize();
        saveToLocalStorage('gift_form_draft', formData);
    });
    
    // Restore form data on page load
    const savedFormData = loadFromLocalStorage('gift_form_draft');
    if (savedFormData) {
        try {
            const params = new URLSearchParams(savedFormData);
            params.forEach((value, key) => {
                $(`#gift-credits [name="${key}"]`).val(value);
            });
        } catch (e) {
            console.warn('Error restoring form data:', e);
        }
    }
    
    // Clean up on form submit
    $('#gift-credits').on('submit', function() {
        try {
            localStorage.removeItem('intersoccer_gift_form_draft');
        } catch (e) {
            // Ignore localStorage errors
        }
    });
    
    // Debug mode for development
    if (window.location.hash === '#debug') {
        window.intersoccerDebug = {
            coaches: availableCoaches,
            selectedCoach: function() { return selectedCoachId; },
            showToast: showToast,
            triggerCelebration: triggerCelebration
        };
        console.log('InterSoccer Debug mode enabled', window.intersoccerDebug);
    }
});

// CSS for toast notifications and other dynamic elements
const dynamicStyles = `
<style>
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10001;
    max-width: 350px;
}

.toast {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    margin-bottom: 10px;
    transform: translateX(100%);
    opacity: 0;
    transition: all 0.3s ease;
    border-left: 4px solid #007bff;
}

.toast.toast-success {
    border-left-color: #28a745;
}

.toast.toast-error {
    border-left-color: #dc3545;
}

.toast.toast-warning {
    border-left-color: #ffc107;
}

.toast.toast-show {
    transform: translateX(0);
    opacity: 1;
}

.toast-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px;
}

.toast-message {
    flex: 1;
    margin-right: 10px;
    font-size: 14px;
    line-height: 1.4;
}

.toast-close {
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    opacity: 0.6;
    transition: opacity 0.2s ease;
}

.toast-close:hover {
    opacity: 1;
}

.celebration {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 9999;
}

.confetti-particle {
    position: absolute;
    width: 8px;
    height: 8px;
    animation: confetti-fall linear infinite;
}

.confetti-1 { background: #ff6b6b; }
.confetti-2 { background: #4ecdc4; }
.confetti-3 { background: #45b7d1; }
.confetti-4 { background: #f9ca24; }

@keyframes confetti-fall {
    0% {
        transform: translateY(-100vh) rotate(0deg);
        opacity: 1;
    }
    100% {
        transform: translateY(100vh) rotate(360deg);
        opacity: 0;
    }
}

.credit-update-animation {
    animation: creditUpdate 0.8s ease-out;
}

@keyframes creditUpdate {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); color: #28a745; }
    100% { transform: scale(1); }
}

.copy-ripple {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.6);
    transform: translate(-50%, -50%);
    animation: ripple 0.6s ease-out;
}

@keyframes ripple {
    to {
        width: 40px;
        height: 40px;
        opacity: 0;
    }
}

.pulse-effect {
    animation: pulseEffect 0.6s ease-out;
}

@keyframes pulseEffect {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.touch-device .touch-active {
    transform: scale(0.98);
    opacity: 0.8;
}

.focus-visible {
    outline: 3px solid #667eea !important;
    outline-offset: 2px !important;
}

.form-group input.valid {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

.form-group input.error {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.loading-spinner {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.loading-spinner:after {
    content: '';
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #667eea;
    border-radius: 50%;
    margin-left: 10px;
    animation: spin 1s linear infinite;
}

.error-message {
    text-align: center;
    color: #dc3545;
    padding: 20px;
    background: #f8d7da;
    border-radius: 6px;
    margin: 20px 0;
}

.no-results {
    text-align: center;
    color: #6c757d;
    padding: 40px;
    font-style: italic;
}

@media (max-width: 768px) {
    .toast-container {
        left: 20px;
        right: 20px;
        max-width: none;
    }
    
    .toast {
        transform: translateY(-100%);
    }
    
    .toast.toast-show {
        transform: translateY(0);
    }
}
</style>
`;

document.head.insertAdjacentHTML('beforeend', dynamicStyles);
