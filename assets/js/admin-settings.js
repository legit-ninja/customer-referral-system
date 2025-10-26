jQuery(document).ready(function($) {
    'use strict';

    console.log('Admin settings JS loaded');

    // Check if form exists
    if ($('#coach-import-form').length === 0) {
        console.log('Coach import form not found');
        return;
    }

    console.log('Coach import form found, attaching handler');

    // Coach CSV Import Handler
    $('#coach-import-form').on('submit', function(e) {
        console.log('Form submit event triggered');
        e.preventDefault();
        console.log('Default prevented, starting AJAX import...');

        const formData = new FormData(this);
        const submitBtn = $('#import-submit-btn');
        const originalText = submitBtn.html();
        const importStatus = $('#import-status');
        const importResults = $('#import-results');
        const progressFill = $('#progress-fill');
        const progressText = $('#progress-text');
        const clearBtn = $('#clear-import-results');

        // Check if intersoccer_admin is defined
        if (typeof intersoccer_admin === 'undefined') {
            console.error('intersoccer_admin is not defined');
            alert('JavaScript error: intersoccer_admin not defined. Please refresh the page.');
            return false;
        }

        console.log('intersoccer_admin:', intersoccer_admin);

        // Reset UI
        importResults.hide();
        clearBtn.hide();
        progressFill.css('width', '0%');
        progressText.text('Preparing import...');

        // Disable form and show progress
        submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Importing...');
        importStatus.show();

        // Add the AJAX action to the form data
        formData.append('action', 'import_coaches_from_csv');
        console.log('FormData prepared with action');

        // Simulate progress updates
        let progress = 0;
        const progressInterval = setInterval(function() {
            progress += Math.random() * 15;
            if (progress > 90) progress = 90;
            progressFill.css('width', progress + '%');
            progressText.text('Processing coaches... ' + Math.round(progress) + '%');
        }, 500);

        // Submit the form via AJAX
        console.log('Sending AJAX request to:', intersoccer_admin.ajax_url);
        $.ajax({
            url: intersoccer_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('AJAX success:', response);
                clearInterval(progressInterval);
                progressFill.css('width', '100%');
                progressText.text('Import completed!');

                setTimeout(function() {
                    importStatus.hide();
                    submitBtn.prop('disabled', false).html(originalText);

                    if (response.success) {
                        displayImportResults(response.data);
                        clearBtn.show();
                    } else {
                        displayImportError(response.data || 'Unknown error occurred');
                    }
                }, 1000);
            },
            error: function(xhr, status, error) {
                console.log('AJAX error:', xhr, status, error);
                console.log('Response text:', xhr.responseText);
                clearInterval(progressInterval);
                importStatus.hide();
                submitBtn.prop('disabled', false).html(originalText);

                let errorMessage = 'Import failed. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                } else if (xhr.responseText) {
                    errorMessage = 'Server error: ' + xhr.responseText;
                }

                displayImportError(errorMessage);
            }
        });

        return false; // Extra prevention of form submission
    });

    console.log('Event handler attached to coach import form');

    // Clear import results
    $('#clear-import-results').on('click', function() {
        $('#import-results').hide();
        $(this).hide();
    });

    // Display import results
    function displayImportResults(data) {
        const resultsDiv = $('#import-summary-content');
        let html = '';

        if (data.created && data.created.length > 0) {
            html += '<div class="import-success">';
            html += '<h5>✅ Coaches Created (' + data.created.length + ')</h5>';
            html += '<ul>';
            data.created.forEach(function(coach) {
                html += '<li>' + coach.first_name + ' ' + coach.last_name + ' (' + coach.email + ')</li>';
            });
            html += '</ul></div>';
        }

        if (data.updated && data.updated.length > 0) {
            html += '<div class="import-success">';
            html += '<h5>🔄 Coaches Updated (' + data.updated.length + ')</h5>';
            html += '<ul>';
            data.updated.forEach(function(coach) {
                html += '<li>' + coach.first_name + ' ' + coach.last_name + ' (' + coach.email + ')</li>';
            });
            html += '</ul></div>';
        }

        if (data.errors && data.errors.length > 0) {
            html += '<div class="import-errors">';
            html += '<h5>⚠️ Errors (' + data.errors.length + ')</h5>';
            html += '<ul>';
            data.errors.forEach(function(error) {
                html += '<li>' + error + '</li>';
            });
            html += '</ul></div>';
        }

        if (data.skipped && data.skipped.length > 0) {
            html += '<div class="import-warnings">';
            html += '<h5>⏭️ Skipped (' + data.skipped.length + ')</h5>';
            html += '<ul>';
            data.skipped.forEach(function(coach) {
                html += '<li>' + coach.first_name + ' ' + coach.last_name + ' (' + coach.email + ') - ' + coach.reason + '</li>';
            });
            html += '</ul></div>';
        }

        resultsDiv.html(html);
        $('#import-results').show();
    }

    // Display import error
    function displayImportError(error) {
        const resultsDiv = $('#import-summary-content');
        resultsDiv.html('<div class="import-errors"><h5>❌ Import Failed</h5><p>' + error + '</p></div>');
        $('#import-results').show();
    }

    // File validation
    $('#coaches_csv').on('change', function() {
        const file = this.files[0];
        const maxSize = 10 * 1024 * 1024; // 10MB

        if (file) {
            if (file.size > maxSize) {
                alert('File size exceeds 10MB limit. Please choose a smaller file.');
                this.value = '';
                return;
            }

            if (!file.name.toLowerCase().endsWith('.csv')) {
                alert('Please select a CSV file.');
                this.value = '';
                return;
            }
        }
    });
});