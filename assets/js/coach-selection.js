(function($) {
    'use strict';

    $(document).ready(function() {
        const cfg = window.intersoccer_coach_selection || {};
        const i18n = cfg.i18n || {};

        let coachSearchTimeout;

        function t(key, fallback) {
            return (typeof i18n[key] === 'string' && i18n[key]) ? i18n[key] : fallback;
        }

        function loadCoaches(search = '', filter = 'all') {
            $('#coaches-list').html(
                '<div class="loading-coaches"><p>' + t('loading_coaches', 'Loading coaches...') + '</p></div>'
            );

            $.ajax({
                url: cfg.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_available_coaches',
                    nonce: cfg.nonce,
                    search: search,
                    filter: filter
                },
                success: function(response) {
                    if (response && response.success) {
                        displayCoaches(response.data && response.data.coaches ? response.data.coaches : []);
                    } else {
                        $('#coaches-list').html(
                            '<div class="no-coaches"><p>' + t('no_coaches_found', 'No coaches found.') + '</p></div>'
                        );
                    }
                },
                error: function() {
                    $('#coaches-list').html(
                        '<div class="error-coaches"><p>' + t('error_loading_coaches', 'Error loading coaches. Please try again.') + '</p></div>'
                    );
                }
            });
        }

        function displayCoaches(coaches) {
            if (!coaches || coaches.length === 0) {
                $('#coaches-list').html(
                    '<div class="no-coaches"><p>' + t('no_matching_coaches', 'No coaches found matching your criteria.') + '</p></div>'
                );
                return;
            }

            let html = '<div class="coaches-grid-inner">';
            coaches.forEach(function(coach) {
                const benefitsHtml = (coach.benefits || []).map(function(benefit) {
                    return '<li>' + benefit + '</li>';
                }).join('');

                html += `
                    <div class="coach-card" data-coach-id="${coach.id}">
                        <div class="coach-header">
                            <img src="${coach.avatar_url}" alt="${coach.name}" class="coach-avatar">
                            <div class="coach-basic-info">
                                <h4>${coach.name}</h4>
                                <span class="coach-specialty">${coach.specialty}</span>
                                <div class="coach-rating">
                                    ${'★'.repeat(Math.floor(coach.rating))}${'☆'.repeat(5 - Math.floor(coach.rating))}
                                    <span class="rating-number">(${coach.rating})</span>
                                </div>
                            </div>
                            <span class="coach-tier-badge ${String(coach.tier || '').toLowerCase()}">${coach.tier}</span>
                        </div>
                        <div class="coach-stats">
                            <div class="stat-item">
                                <span class="stat-number">${coach.total_athletes}</span>
                                <span class="stat-label">${t('athletes_label', 'Athletes')}</span>
                            </div>
                        </div>
                        <div class="coach-benefits">
                            <h5>${t('benefits_label', 'Benefits')}:</h5>
                            <ul>${benefitsHtml}</ul>
                        </div>
                        <button type="button" class="select-coach-btn" data-coach-id="${coach.id}">
                            ${t('select_this_coach', 'Select This Coach')}
                        </button>
                    </div>
                `;
            });
            html += '</div>';
            $('#coaches-list').html(html);
        }

        // Load coaches on page load
        loadCoaches();

        // Search functionality
        $('#coach-search').on('input', function() {
            clearTimeout(coachSearchTimeout);
            const searchTerm = $(this).val();
            coachSearchTimeout = setTimeout(function() {
                loadCoaches(searchTerm, $('#coach-filter').val());
            }, 300);
        });

        // Filter functionality
        $('#coach-filter').on('change', function() {
            loadCoaches($('#coach-search').val(), $(this).val());
        });

        // Coach selection
        $(document).on('click', '.select-coach-btn', function() {
            const coachId = $(this).data('coach-id');
            const coachCard = $(this).closest('.coach-card');
            const coachName = coachCard.find('h4').text();
            const confirmTpl = t('confirm_select_coach', 'Are you sure you want to select %s as your coach partner?');

            if (confirm(confirmTpl.replace('%s', coachName))) {
                selectCoach(coachId);
            }
        });

        // Switch coach
        $(document).on('click', '.switch-coach-btn', function() {
            if (confirm(t('confirm_switch', "Are you sure you want to switch coaches? You won't be able to change again for 7 days."))) {
                $('#coach-search').val('');
                $('#coach-filter').val('all');
                loadCoaches();
                $('.switch-notice').remove();
                $('.coach-selection-header').after(
                    '<div class="switch-notice info-notice"><p>' + t('select_new_notice', 'Select a new coach from the list below.') + '</p></div>'
                );
            }
        });

        function selectCoach(coachId) {
            $.ajax({
                url: cfg.ajax_url,
                type: 'POST',
                data: {
                    action: 'select_coach_partner',
                    nonce: cfg.nonce,
                    coach_id: coachId
                },
                success: function(response) {
                    if (response && response.success) {
                        location.reload(); // Reload to show new coach
                    } else {
                        alert((response && response.data && response.data.message) ? response.data.message : t('error_select_coach', 'Error selecting coach. Please try again.'));
                    }
                },
                error: function() {
                    alert(t('error_select_coach', 'Error selecting coach. Please try again.'));
                }
            });
        }
    });
})(jQuery);

