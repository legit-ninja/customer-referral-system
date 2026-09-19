/**
 * Admin Email Template Builder JavaScript
 * 
 * @package InterSoccer_Referral
 * @since 1.9.17
 */

(function($) {
    'use strict';

    var EmailTemplates = {
        init: function() {
            this.bindEvents();
            this.initMergeFieldDropdowns();
        },

        bindEvents: function() {
            var self = this;

            // Template form submit
            $('#email-template-form').on('submit', function(e) {
                e.preventDefault();
                self.saveTemplate($(this));
            });

            // Duplicate template
            $(document).on('click', '.duplicate-template', function(e) {
                e.preventDefault();
                self.duplicateTemplate($(this).data('id'));
            });

            // Archive template
            $(document).on('click', '.archive-template', function(e) {
                e.preventDefault();
                if (confirm(intersoccer_email_templates.i18n.confirm_archive)) {
                    self.archiveTemplate($(this).data('id'), false);
                }
            });

            // Restore template
            $(document).on('click', '.restore-template', function(e) {
                e.preventDefault();
                self.archiveTemplate($(this).data('id'), true);
            });

            // Delete template
            $(document).on('click', '.delete-template', function(e) {
                e.preventDefault();
                if (confirm(intersoccer_email_templates.i18n.confirm_delete)) {
                    self.deleteTemplate($(this).data('id'));
                }
            });

            // Preview button
            $('#preview-btn').on('click', function() {
                self.updatePreview();
            });

            // Test send button
            $('#test-send-btn').on('click', function() {
                self.testSend();
            });

            // Copy merge field on click
            $(document).on('click', '.copy-field', function() {
                self.copyToClipboard($(this));
            });

            // Auto-update preview on input change (debounced)
            var previewTimeout;
            $('#template-subject, #template-body').on('input', function() {
                clearTimeout(previewTimeout);
                previewTimeout = setTimeout(function() {
                    self.updatePreviewLive();
                }, 500);
            });
        },

        initMergeFieldDropdowns: function() {
            var self = this;

            // Toggle dropdown
            $(document).on('click', '.insert-merge-field-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var $dropdown = $(this).closest('.merge-field-dropdown');
                $('.merge-field-dropdown').not($dropdown).removeClass('open');
                $dropdown.toggleClass('open');
            });

            // Close dropdowns on outside click
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.merge-field-dropdown').length) {
                    $('.merge-field-dropdown').removeClass('open');
                }
            });

            // Insert field from dropdown
            $(document).on('click', '.merge-field-menu a', function(e) {
                e.preventDefault();
                var field = '{{' + $(this).data('field') + '}}';
                var targetId = $(this).closest('.merge-field-menu').data('target');
                self.insertAtCursor($('#' + targetId), field);
                $(this).closest('.merge-field-dropdown').removeClass('open');
            });
        },

        insertAtCursor: function($input, text) {
            var el = $input[0];
            
            if (document.selection) {
                el.focus();
                var sel = document.selection.createRange();
                sel.text = text;
            } else if (el.selectionStart || el.selectionStart === 0) {
                var startPos = el.selectionStart;
                var endPos = el.selectionEnd;
                var scrollTop = el.scrollTop;
                el.value = el.value.substring(0, startPos) + text + el.value.substring(endPos);
                el.focus();
                el.selectionStart = startPos + text.length;
                el.selectionEnd = startPos + text.length;
                el.scrollTop = scrollTop;
            } else {
                el.value += text;
                el.focus();
            }

            $input.trigger('input');
        },

        copyToClipboard: function($el) {
            var text = $el.data('field');
            var $code = $el;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    $code.addClass('copied');
                    setTimeout(function() {
                        $code.removeClass('copied');
                    }, 1500);
                });
            } else {
                var $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(text).select();
                document.execCommand('copy');
                $temp.remove();
                
                $code.addClass('copied');
                setTimeout(function() {
                    $code.removeClass('copied');
                }, 1500);
            }
        },

        saveTemplate: function($form) {
            var self = this;
            var $submitBtn = $form.find('button[type="submit"]');
            var $status = $form.find('.save-status');

            $submitBtn.addClass('loading').prop('disabled', true);
            $status.removeClass('success error').text(intersoccer_email_templates.i18n.saving);

            $.ajax({
                url: intersoccer_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'intersoccer_save_email_template',
                    nonce: intersoccer_admin.nonce,
                    id: $form.find('input[name="id"]').val(),
                    name: $form.find('input[name="name"]').val(),
                    subject: $form.find('input[name="subject"]').val(),
                    body: $form.find('textarea[name="body"]').val()
                },
                success: function(response) {
                    $submitBtn.removeClass('loading').prop('disabled', false);

                    if (response.success) {
                        $status.addClass('success').text(response.data.message);
                        
                        // Update form ID if this was a new template
                        if (response.data.template_id && !$form.find('input[name="id"]').val()) {
                            $form.find('input[name="id"]').val(response.data.template_id);
                            
                            // Update URL without reload
                            var url = new URL(window.location.href);
                            url.searchParams.set('action', 'edit');
                            url.searchParams.set('template_id', response.data.template_id);
                            window.history.replaceState({}, '', url.toString());
                        }

                        setTimeout(function() {
                            $status.text('');
                        }, 3000);
                    } else {
                        $status.addClass('error').text(response.data.message || intersoccer_email_templates.i18n.save_error);
                    }
                },
                error: function() {
                    $submitBtn.removeClass('loading').prop('disabled', false);
                    $status.addClass('error').text(intersoccer_email_templates.i18n.save_error);
                }
            });
        },

        duplicateTemplate: function(id) {
            $.ajax({
                url: intersoccer_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'intersoccer_duplicate_email_template',
                    nonce: intersoccer_admin.nonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = 'admin.php?page=intersoccer-email-templates&action=edit&template_id=' + response.data.template_id;
                    } else {
                        alert(response.data.message || intersoccer_email_templates.i18n.duplicate_error);
                    }
                },
                error: function() {
                    alert(intersoccer_email_templates.i18n.duplicate_error);
                }
            });
        },

        archiveTemplate: function(id, restore) {
            var self = this;

            $.ajax({
                url: intersoccer_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'intersoccer_archive_email_template',
                    nonce: intersoccer_admin.nonce,
                    id: id,
                    restore: restore ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert(response.data.message || intersoccer_email_templates.i18n.archive_error);
                    }
                },
                error: function() {
                    alert(intersoccer_email_templates.i18n.archive_error);
                }
            });
        },

        deleteTemplate: function(id) {
            $.ajax({
                url: intersoccer_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'intersoccer_delete_email_template',
                    nonce: intersoccer_admin.nonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert(response.data.message || intersoccer_email_templates.i18n.delete_error);
                    }
                },
                error: function() {
                    alert(intersoccer_email_templates.i18n.delete_error);
                }
            });
        },

        updatePreview: function() {
            var self = this;
            var $btn = $('#preview-btn');
            var templateId = $('input[name="id"]').val();

            $btn.addClass('loading').prop('disabled', true);

            $.ajax({
                url: intersoccer_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'intersoccer_preview_email_template',
                    nonce: intersoccer_admin.nonce,
                    id: templateId,
                    subject: $('#template-subject').val(),
                    body: $('#template-body').val(),
                    coach_id: $('#preview-coach').val(),
                    campaign_name: $('#preview-campaign-name').val(),
                    campaign_end_date: $('#preview-campaign-end').val(),
                    cta_url: $('#preview-cta-url').val()
                },
                success: function(response) {
                    $btn.removeClass('loading').prop('disabled', false);

                    if (response.success) {
                        $('#preview-subject').text(response.data.subject);
                        $('#preview-body').html(self.formatPreviewBody(response.data.body));
                    } else {
                        alert(response.data.message || intersoccer_email_templates.i18n.preview_error);
                    }
                },
                error: function() {
                    $btn.removeClass('loading').prop('disabled', false);
                    alert(intersoccer_email_templates.i18n.preview_error);
                }
            });
        },

        updatePreviewLive: function() {
            var subject = $('#template-subject').val();
            var body = $('#template-body').val();

            // Use sample values for live preview
            var sampleValues = {
                'coach_name': 'Sarah',
                'referral_code': 'SARAH2026',
                'campaign_name': $('#preview-campaign-name').val() || 'Autumn Enrollment 2026',
                'share_url': 'https://intersoccer.ch/?ref=SARAH2026',
                'campaign_end_date': this.formatDate($('#preview-campaign-end').val()) || '31-12-2026',
                'cta_url': $('#preview-cta-url').val() || 'https://intersoccer.ch/enroll'
            };

            // Replace merge fields
            for (var key in sampleValues) {
                var placeholder = '{{' + key + '}}';
                subject = subject.split(placeholder).join(sampleValues[key]);
                body = body.split(placeholder).join(sampleValues[key]);
            }

            $('#preview-subject').text(subject);
            $('#preview-body').html(this.formatPreviewBody(body));
        },

        formatDate: function(dateStr) {
            if (!dateStr) return '';
            var date = new Date(dateStr);
            if (isNaN(date.getTime())) return dateStr;
            
            var day = date.getDate();
            var month = date.getMonth() + 1;
            var year = date.getFullYear();
            
            return day + '-' + month + '-' + year;
        },

        formatPreviewBody: function(body) {
            // Escape HTML
            var escaped = $('<div>').text(body).html();
            
            // Convert newlines to <br>
            escaped = escaped.replace(/\n/g, '<br>');
            
            // Bold text (**text**)
            escaped = escaped.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            
            // Auto-link URLs
            escaped = escaped.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank">$1</a>');
            
            return escaped;
        },

        testSend: function() {
            var self = this;
            var $btn = $('#test-send-btn');
            var $status = $('.test-send-status');
            var templateId = $('input[name="id"]').val();

            if (!templateId) {
                $status.removeClass('success').addClass('error visible').text(intersoccer_email_templates.i18n.save_first);
                return;
            }

            $btn.addClass('loading').prop('disabled', true);
            $status.removeClass('success error visible');

            $.ajax({
                url: intersoccer_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'intersoccer_test_send_email_template',
                    nonce: intersoccer_admin.nonce,
                    id: templateId,
                    email: $('#test-email').val(),
                    coach_id: $('#preview-coach').val(),
                    campaign_name: $('#preview-campaign-name').val(),
                    campaign_end_date: $('#preview-campaign-end').val(),
                    cta_url: $('#preview-cta-url').val()
                },
                success: function(response) {
                    $btn.removeClass('loading').prop('disabled', false);

                    if (response.success) {
                        $status.removeClass('error').addClass('success visible').text(response.data.message);
                        $status.attr('data-status', 'success');
                    } else {
                        var errorMsg = response.data.message || intersoccer_email_templates.i18n.send_error;
                        $status.removeClass('success').addClass('error visible').text(errorMsg);
                        $status.attr('data-status', 'error');
                        
                        // Add data attributes for missing fields (for Tess's Soft-glance tests)
                        if (response.data.missing_fields) {
                            $status.attr('data-missing-fields', response.data.missing_fields.join(','));
                        }
                    }
                },
                error: function() {
                    $btn.removeClass('loading').prop('disabled', false);
                    $status.removeClass('success').addClass('error visible').text(intersoccer_email_templates.i18n.send_error);
                    $status.attr('data-status', 'error');
                }
            });
        }
    };

    $(document).ready(function() {
        EmailTemplates.init();
    });

})(jQuery);
