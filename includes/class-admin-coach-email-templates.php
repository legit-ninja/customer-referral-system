<?php
/**
 * Admin Coach Email Templates Page
 * 
 * Admin interface for creating, editing, and managing coach email templates.
 * Implements AC §§1-3, 9-10 for admin UI and microcopy.
 * 
 * @package InterSoccer_Referral
 * @since 1.9.17
 */

if (!defined('ABSPATH')) {
    exit;
}

class InterSoccer_Admin_Coach_Email_Templates {

    private $template_manager;

    public function __construct() {
        $this->template_manager = InterSoccer_Coach_Email_Templates::get_instance();
    }

    /**
     * Render the main admin page
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'intersoccer-referral'));
        }

        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $template_id = isset($_GET['template_id']) ? absint($_GET['template_id']) : 0;

        ?>
        <div class="wrap intersoccer-admin intersoccer-email-templates">
            <?php
            switch ($action) {
                case 'new':
                case 'edit':
                    $this->render_editor($template_id);
                    break;
                default:
                    $this->render_list();
                    break;
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render template list view
     * AC §1: Admin can create, edit, duplicate, and archive
     */
    private function render_list() {
        $show_archived = !empty($_GET['show_archived']);
        $templates = $this->template_manager->get_templates([
            'status' => $show_archived ? 'all' : 'active',
            'include_archived' => $show_archived,
        ]);

        $coaches = get_users(['role' => 'coach', 'orderby' => 'display_name']);
        ?>
        <h1 class="wp-heading-inline">
            <?php esc_html_e('Coach Email Templates', 'intersoccer-referral'); ?>
        </h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=intersoccer-email-templates&action=new')); ?>" class="page-title-action">
            <?php esc_html_e('Create New Template', 'intersoccer-referral'); ?>
        </a>
        <hr class="wp-header-end">

        <!-- AC §10: Friendly microcopy for admin-facing (coach context) -->
        <p class="description" style="margin-bottom: 20px;">
            <?php esc_html_e('Build email templates to share referral campaigns with your coaches. Use merge fields to personalize each message automatically.', 'intersoccer-referral'); ?>
        </p>

        <div class="email-templates-toolbar">
            <label>
                <input type="checkbox" id="show-archived" <?php checked($show_archived); ?>>
                <?php esc_html_e('Show archived templates', 'intersoccer-referral'); ?>
            </label>
        </div>

        <?php if (empty($templates)) : ?>
            <div class="email-templates-empty">
                <div class="empty-icon">📧</div>
                <h3><?php esc_html_e('No templates yet', 'intersoccer-referral'); ?></h3>
                <p><?php esc_html_e('Create your first email template to start sending referral campaigns to coaches.', 'intersoccer-referral'); ?></p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=intersoccer-email-templates&action=new')); ?>" class="button button-primary button-hero">
                    <?php esc_html_e('Create First Template', 'intersoccer-referral'); ?>
                </a>
            </div>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped email-templates-table">
                <thead>
                    <tr>
                        <th class="column-name"><?php esc_html_e('Template Name', 'intersoccer-referral'); ?></th>
                        <th class="column-subject"><?php esc_html_e('Subject', 'intersoccer-referral'); ?></th>
                        <th class="column-status"><?php esc_html_e('Status', 'intersoccer-referral'); ?></th>
                        <th class="column-updated"><?php esc_html_e('Last Updated', 'intersoccer-referral'); ?></th>
                        <th class="column-actions"><?php esc_html_e('Actions', 'intersoccer-referral'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $template) : ?>
                        <tr data-template-id="<?php echo esc_attr($template['id']); ?>" class="<?php echo $template['status'] === 'archived' ? 'archived' : ''; ?>">
                            <td class="column-name">
                                <strong>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=intersoccer-email-templates&action=edit&template_id=' . $template['id'])); ?>">
                                        <?php echo esc_html($template['name']); ?>
                                    </a>
                                </strong>
                                <?php if ($template['is_default']) : ?>
                                    <span class="default-badge"><?php esc_html_e('Default', 'intersoccer-referral'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-subject">
                                <code><?php echo esc_html($template['subject']); ?></code>
                            </td>
                            <td class="column-status">
                                <?php if ($template['status'] === 'archived') : ?>
                                    <span class="status-badge status-archived"><?php esc_html_e('Archived', 'intersoccer-referral'); ?></span>
                                <?php else : ?>
                                    <span class="status-badge status-active"><?php esc_html_e('Active', 'intersoccer-referral'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-updated">
                                <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($template['updated_at']))); ?>
                            </td>
                            <td class="column-actions">
                                <div class="row-actions">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=intersoccer-email-templates&action=edit&template_id=' . $template['id'])); ?>" class="edit">
                                        <?php esc_html_e('Edit', 'intersoccer-referral'); ?>
                                    </a>
                                    <span class="sep">|</span>
                                    <a href="#" class="duplicate-template" data-id="<?php echo esc_attr($template['id']); ?>">
                                        <?php esc_html_e('Duplicate', 'intersoccer-referral'); ?>
                                    </a>
                                    <span class="sep">|</span>
                                    <?php if ($template['status'] === 'archived') : ?>
                                        <a href="#" class="restore-template" data-id="<?php echo esc_attr($template['id']); ?>">
                                            <?php esc_html_e('Restore', 'intersoccer-referral'); ?>
                                        </a>
                                        <span class="sep">|</span>
                                        <a href="#" class="delete-template trash" data-id="<?php echo esc_attr($template['id']); ?>">
                                            <?php esc_html_e('Delete Permanently', 'intersoccer-referral'); ?>
                                        </a>
                                    <?php else : ?>
                                        <a href="#" class="archive-template" data-id="<?php echo esc_attr($template['id']); ?>">
                                            <?php esc_html_e('Archive', 'intersoccer-referral'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <script>
        jQuery(function($) {
            $('#show-archived').on('change', function() {
                var url = new URL(window.location.href);
                if (this.checked) {
                    url.searchParams.set('show_archived', '1');
                } else {
                    url.searchParams.delete('show_archived');
                }
                window.location.href = url.toString();
            });
        });
        </script>
        <?php
    }

    /**
     * Render template editor
     * AC §2: Template has editable subject and body with visible insert controls
     */
    private function render_editor($template_id = 0) {
        $template = null;
        $is_new = true;

        if ($template_id > 0) {
            $template = $this->template_manager->get_template($template_id);
            if (!$template) {
                wp_die(__('Template not found', 'intersoccer-referral'));
            }
            $is_new = false;
        }

        $merge_fields = $this->template_manager->get_merge_fields();
        $coaches = get_users(['role' => 'coach', 'orderby' => 'display_name', 'number' => 50]);
        ?>
        <h1>
            <?php if ($is_new) : ?>
                <?php esc_html_e('Create New Template', 'intersoccer-referral'); ?>
            <?php else : ?>
                <?php esc_html_e('Edit Template', 'intersoccer-referral'); ?>
            <?php endif; ?>
        </h1>

        <a href="<?php echo esc_url(admin_url('admin.php?page=intersoccer-email-templates')); ?>" class="back-link">
            &larr; <?php esc_html_e('Back to Templates', 'intersoccer-referral'); ?>
        </a>

        <div class="email-template-editor">
            <div class="editor-main">
                <form id="email-template-form" class="email-template-form">
                    <input type="hidden" name="id" value="<?php echo esc_attr($template_id); ?>">
                    <?php wp_nonce_field('intersoccer_admin_nonce', 'nonce'); ?>

                    <div class="form-field">
                        <label for="template-name"><?php esc_html_e('Template Name', 'intersoccer-referral'); ?></label>
                        <input type="text" id="template-name" name="name" value="<?php echo $template ? esc_attr($template['name']) : ''; ?>" placeholder="<?php esc_attr_e('e.g., Welcome Campaign', 'intersoccer-referral'); ?>" required>
                        <p class="description"><?php esc_html_e('Give your template a friendly name to find it later.', 'intersoccer-referral'); ?></p>
                    </div>

                    <div class="form-field">
                        <label for="template-subject"><?php esc_html_e('Email Subject', 'intersoccer-referral'); ?></label>
                        <div class="input-with-insert">
                            <input type="text" id="template-subject" name="subject" value="<?php echo $template ? esc_attr($template['subject']) : ''; ?>" placeholder="<?php esc_attr_e('e.g., Hey {{coach_name}}, share the soccer love!', 'intersoccer-referral'); ?>" required>
                            <div class="merge-field-dropdown">
                                <button type="button" class="button insert-merge-field-btn" data-target="template-subject">
                                    <?php esc_html_e('Insert Field', 'intersoccer-referral'); ?> ▾
                                </button>
                                <ul class="merge-field-menu" data-target="template-subject">
                                    <?php foreach ($merge_fields as $key => $field) : ?>
                                        <li>
                                            <a href="#" data-field="<?php echo esc_attr($key); ?>" title="<?php echo esc_attr($field['description']); ?>">
                                                <code>{{<?php echo esc_html($key); ?>}}</code>
                                                <span><?php echo esc_html($field['label']); ?></span>
                                                <?php if ($field['required']) : ?>
                                                    <em class="required"><?php esc_html_e('required', 'intersoccer-referral'); ?></em>
                                                <?php endif; ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="template-body"><?php esc_html_e('Email Body', 'intersoccer-referral'); ?></label>
                        <div class="body-toolbar">
                            <div class="merge-field-dropdown">
                                <button type="button" class="button insert-merge-field-btn" data-target="template-body">
                                    <?php esc_html_e('Insert Merge Field', 'intersoccer-referral'); ?> ▾
                                </button>
                                <ul class="merge-field-menu" data-target="template-body">
                                    <?php foreach ($merge_fields as $key => $field) : ?>
                                        <li>
                                            <a href="#" data-field="<?php echo esc_attr($key); ?>" title="<?php echo esc_attr($field['description']); ?>">
                                                <code>{{<?php echo esc_html($key); ?>}}</code>
                                                <span><?php echo esc_html($field['label']); ?></span>
                                                <?php if ($field['required']) : ?>
                                                    <em class="required"><?php esc_html_e('required', 'intersoccer-referral'); ?></em>
                                                <?php endif; ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <span class="formatting-hint">
                                <?php esc_html_e('Use **bold** for emphasis, URLs auto-link', 'intersoccer-referral'); ?>
                            </span>
                        </div>
                        <textarea id="template-body" name="body" rows="15" placeholder="<?php esc_attr_e("Hi {{coach_name}},\n\nWe're excited to have you on board! Your referral code is ready:\n\n**{{referral_code}}**\n\nShare your link: {{share_url}}\n\nCheers,\nThe Team", 'intersoccer-referral'); ?>" required><?php echo $template ? esc_textarea($template['body']) : ''; ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="button button-primary button-large">
                            <?php if ($is_new) : ?>
                                <?php esc_html_e('Create Template', 'intersoccer-referral'); ?>
                            <?php else : ?>
                                <?php esc_html_e('Save Changes', 'intersoccer-referral'); ?>
                            <?php endif; ?>
                        </button>
                        <span class="save-status"></span>
                    </div>
                </form>
            </div>

            <div class="editor-sidebar">
                <!-- Merge Fields Reference -->
                <div class="sidebar-section merge-fields-reference">
                    <h3><?php esc_html_e('Merge Fields', 'intersoccer-referral'); ?></h3>
                    <p class="description"><?php esc_html_e('Click to copy, then paste into subject or body.', 'intersoccer-referral'); ?></p>
                    <ul class="merge-field-list">
                        <?php foreach ($merge_fields as $key => $field) : ?>
                            <li class="merge-field-item <?php echo $field['required'] ? 'required' : ''; ?>">
                                <code class="copy-field" data-field="{{<?php echo esc_attr($key); ?>}}" title="<?php esc_attr_e('Click to copy', 'intersoccer-referral'); ?>">{{<?php echo esc_html($key); ?>}}</code>
                                <span class="field-label"><?php echo esc_html($field['label']); ?></span>
                                <?php if ($field['required']) : ?>
                                    <span class="required-star" title="<?php esc_attr_e('Required for send', 'intersoccer-referral'); ?>">*</span>
                                <?php endif; ?>
                                <span class="field-sample"><?php echo esc_html($field['sample']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="required-note">
                        <span class="required-star">*</span> <?php esc_html_e('Required fields must have values when sending', 'intersoccer-referral'); ?>
                    </p>
                </div>

                <!-- Preview Panel -->
                <div class="sidebar-section preview-section">
                    <h3><?php esc_html_e('Preview', 'intersoccer-referral'); ?></h3>
                    <div class="preview-options">
                        <label for="preview-coach"><?php esc_html_e('Preview as coach:', 'intersoccer-referral'); ?></label>
                        <select id="preview-coach">
                            <option value=""><?php esc_html_e('— Use sample data —', 'intersoccer-referral'); ?></option>
                            <?php foreach ($coaches as $coach) : ?>
                                <option value="<?php echo esc_attr($coach->ID); ?>">
                                    <?php echo esc_html($coach->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="preview-campaign-options">
                        <label for="preview-campaign-name"><?php esc_html_e('Campaign name:', 'intersoccer-referral'); ?></label>
                        <input type="text" id="preview-campaign-name" placeholder="<?php esc_attr_e('Autumn Enrollment 2026', 'intersoccer-referral'); ?>">
                        
                        <label for="preview-campaign-end"><?php esc_html_e('End date:', 'intersoccer-referral'); ?></label>
                        <input type="date" id="preview-campaign-end">
                        
                        <label for="preview-cta-url"><?php esc_html_e('CTA URL:', 'intersoccer-referral'); ?></label>
                        <input type="url" id="preview-cta-url" placeholder="https://intersoccer.ch/enroll">
                    </div>
                    <button type="button" id="preview-btn" class="button">
                        <?php esc_html_e('Update Preview', 'intersoccer-referral'); ?>
                    </button>
                    <div class="preview-content">
                        <div class="preview-subject-wrap">
                            <strong><?php esc_html_e('Subject:', 'intersoccer-referral'); ?></strong>
                            <span id="preview-subject"></span>
                        </div>
                        <div class="preview-body-wrap">
                            <div id="preview-body"></div>
                        </div>
                    </div>
                </div>

                <!-- Test Send Panel -->
                <div class="sidebar-section test-send-section">
                    <h3><?php esc_html_e('Test Send', 'intersoccer-referral'); ?></h3>
                    <p class="description"><?php esc_html_e('Send a test email to check how it looks in an inbox.', 'intersoccer-referral'); ?></p>
                    <div class="test-send-options">
                        <label for="test-email"><?php esc_html_e('Send to:', 'intersoccer-referral'); ?></label>
                        <input type="email" id="test-email" value="<?php echo esc_attr(get_option('admin_email')); ?>" placeholder="admin@example.com">
                    </div>
                    <button type="button" id="test-send-btn" class="button">
                        <?php esc_html_e('Send Test Email', 'intersoccer-referral'); ?>
                    </button>
                    <div class="test-send-status"></div>
                </div>
            </div>
        </div>
        <?php
    }
}
