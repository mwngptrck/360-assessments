<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <!-- Bootstrap Container -->
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-12">
                <h1 class="wp-heading-inline mb-4">
                    <i class="bi bi-envelope me-2"></i>Send Emails to Users
                </h1>

                <?php if (isset($_GET['message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo esc_html($_GET['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo esc_html($_GET['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php
                // Initialize managers and data
                $user_manager = Assessment_360_User_Manager::get_instance();
                $grouped_users = $user_manager->get_users_grouped_by_group();
                $org_name = get_option('assessment_360_organization_name');

                // Define email templates
                $email_templates = array(
                    'welcome' => array(
                        'subject' => 'Welcome to the 360° Assessment',
                        'body' => sprintf(
                            '<p>Dear {first_name},</p>
                             <p>Welcome to the %s 360° Assessment program.</p>
                             <p>Your login credentials are:</p>
                             <p>Username: {email}<br>
                             Password: {new_password}</p>
                             <p>Please login at: {login_url}</p>
                             <p>For security reasons, we recommend changing your password after your first login.</p>
                             <p>Best regards,<br>%s Team</p>',
                            esc_html($org_name),
                            esc_html($org_name)
                        )
                    ),
                    'reminder' => array(
                        'subject' => 'Reminder: Pending Assessments',
                        'body' => sprintf(
                            '<p>Dear {first_name},</p>
                             <p>This is a reminder that you have pending assessments to complete:</p>
                             <p>{pending_list}</p>
                             <p>Please complete them by {due_date}.</p>
                             <p>Best regards,<br>%s Team</p>',
                            esc_html($org_name)
                        )
                    )
                );
                ?>

                <!-- Main Content -->
<!--
                <div class="card shadow-sm">
                    <div class="card-body">
-->
                        <div class="row">
                            <!-- Left Column - Email Form -->
                            <div class="col-md-7">
                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="needs-validation" novalidate>
                                    <?php wp_nonce_field('assessment_360_send_email', 'assessment_360_send_email_nonce'); ?>
                                    <input type="hidden" name="action" value="assessment_360_send_email">

                                    <!-- Template Selection Card -->
                                    <div class="card mb-4">
<!--
                                        <div class="card-header bg-light">
                                            <h5 class="card-title mb-0">
                                                <i class="bi bi-envelope-paper me-2"></i>Email Template
                                            </h5>
                                        </div>
-->
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label for="email_template" class="form-label">Select Template <span class="text-danger">*</span></label>
                                                <select id="email_template" name="email_template" class="form-select" required>
                                                    <option value="">Select Template</option>
                                                    <option value="welcome">Welcome Email</option>
                                                    <option value="reminder">Reminder Email</option>
                                                    <option value="custom">Custom Email</option>
                                                </select>
                                            </div>

                                            <!-- Custom Email Fields -->
                                            <div id="custom_email_fields" class="custom-email-section" style="display: none;">
                                                <div class="mb-3">
                                                    <label for="custom_email_subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                                    <input type="text" 
                                                           id="custom_email_subject" 
                                                           name="custom_email_subject" 
                                                           class="form-control">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="custom_email_body" class="form-label">Body <span class="text-danger">*</span></label>
                                                    <?php 
                                                    wp_editor(
                                                        '', 
                                                        'custom_email_body',
                                                        array(
                                                            'media_buttons' => false,
                                                            'textarea_name' => 'custom_email_body',
                                                            'textarea_rows' => 10,
                                                            'teeny' => true,
                                                            'quicktags' => true
                                                        )
                                                    );
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Recipients Card -->
                                    <div class="card mb-4">
                                        <div class="card-header bg-light">
                                            <h5 class="card-title mb-0">
                                                <i class="bi bi-people me-2"></i>Recipients
                                            </h5>
                                        </div>
                                        <div class="recipient-actions mt-3">
                                            <button type="button" class="btn btn-outline-primary btn-sm select-all-users">
                                                <i class="bi bi-check-all me-1"></i>Select All
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm clear-all-users">
                                                <i class="bi bi-x-lg me-1"></i>Clear All
                                            </button>
                                            <span class="selected-count ms-2"></span>
                                        </div>
                                        <div class="card-body">
                                            <?php if (!empty($grouped_users)): ?>
                                                <div class="recipient-groups">
                                                    <?php foreach ($grouped_users as $group_id => $group): 
                                                        if (empty($group['users'])) continue;
                                                    ?>
                                                        <div class="recipient-group card mb-3">
                                                            <div class="card-header bg-light py-2">
                                                                <label class="d-flex align-items-center mb-0">
                                                                    <input type="checkbox" 
                                                                           class="form-check-input me-2 group-select" 
                                                                           data-group="<?php echo esc_attr($group_id); ?>">
                                                                    <?php echo esc_html($group['name']); ?>
                                                                    <span class="badge bg-secondary ms-2">
                                                                        <?php echo count($group['users']); ?> users
                                                                    </span>
                                                                </label>
                                                            </div>
                                                            <div class="card-body py-2">
                                                                <?php foreach ($group['users'] as $user): ?>
                                                                    <div class="form-check mb-2">
                                                                        <input type="checkbox" 
                                                                               class="form-check-input user-select group-<?php echo esc_attr($group_id); ?>"
                                                                               name="recipients[]" 
                                                                               value="<?php echo esc_attr($user->id); ?>"
                                                                               id="user-<?php echo esc_attr($user->id); ?>">
                                                                        <label class="form-check-label" for="user-<?php echo esc_attr($user->id); ?>">
                                                                            <?php 
                                                                            echo esc_html(sprintf(
                                                                                '%s %s (%s)%s',
                                                                                $user->first_name,
                                                                                $user->last_name,
                                                                                $user->email,
                                                                                !empty($user->position_name) ? ' - ' . $user->position_name : ''
                                                                            ));
                                                                            ?>
                                                                        </label>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                
                                            <?php else: ?>
                                                <div class="alert alert-warning">
                                                    <p>No active users found in any group.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-send me-1"></i>Send Email
                                    </button>
                                </form>
                            </div>

                            <!-- Right Column - Preview -->
                            <div class="col-md-5">
                                <div class="sticky-top" style="top: 20px;">
                                    <div class="card">
                                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                            <h5 class="card-title mb-0">
                                                <i class="bi bi-eye me-2"></i>Email Preview
                                            </h5>
                                            <button class="btn btn-sm btn-outline-primary" onclick="refreshPreview()">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <div id="email_preview" class="email-preview-body">
                                                <div class="email-preview-placeholder">
                                                    Select an email template to preview
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div><!-- .row -->
                    <!-- </div>.card-body -->
                <!-- </div>.card.shadow-sm -->
            </div><!-- .col-12 -->
        </div><!-- .row -->
    </div><!-- .container-fluid -->
</div><!-- .wrap -->

<style>
/* Card Styles */
.card {
    border: none;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    max-width: 100%;
    padding: 0
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0,0,0,.125);
    padding: 1rem;
}
.card-body {padding: 0}
.card-title {
    margin-bottom: 0;
    color: #333;
}

/* Form Elements */
.form-label {
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.form-select, .form-control {
    max-width: 100%;
}
.wp-core-ui select {max-width: 100%}
/* Recipients Section */
.recipient-groups {
    max-height: 500px;
    overflow-y: auto;
    margin: 1rem 0;
}

.recipient-group {
    transition: transform 0.2s;
}

.recipient-group:hover {
    transform: translateY(-2px);
}

.recipient-group .card-header {
    padding: 0.75rem 1rem;
}

.recipient-group .card-body {
    padding: 1rem;
}

.form-check-label {
    cursor: pointer;
    user-select: none;
}

/* Preview Styles */
.email-preview-body {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    min-height: 500px;
    padding: 1.5rem;
}

.email-preview-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 400px;
    color: #6c757d;
    font-style: italic;
}

.email-preview-message {
    padding: 1rem;
}

.email-preview-subject {
    font-weight: bold;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #dee2e6;
}

.email-preview-content {
    line-height: 1.6;
}

/* WP Editor Adjustments */
.wp-editor-container {
    border: 1px solid #dee2e6;
    border-radius: 4px;
}

.wp-editor-area {
    border: none !important;
}

/* Custom Email Section */
.custom-email-section {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 4px;
    margin: 1rem 0;
}

/* Badge Styling */
.badge {
    font-weight: normal;
    padding: 0.5em 0.8em;
}

/* Recipient Actions */
.recipient-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.selected-count {
    color: #6c757d;
    font-style: italic;
}

/* Scrollbar Styling */
.recipient-groups::-webkit-scrollbar {
    width: 8px;
}

.recipient-groups::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.recipient-groups::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

.recipient-groups::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Responsive Design */
@media (max-width: 768px) {
    .sticky-top {
        position: static !important;
        margin-top: 2rem;
    }

    .recipient-groups {
        max-height: 400px;
    }

    .email-preview-body {
        min-height: 300px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Store email templates data
    const emailTemplates = <?php echo json_encode($email_templates); ?>;
    
    // Sample data for preview
    const sampleData = {
        '{first_name}': 'John',
        '{last_name}': 'Doe',
        '{email}': 'john.doe@example.com',
        '{org_name}': '<?php echo esc_js($org_name); ?>',
        '{login_url}': '<?php echo esc_js(home_url('/360-assessment-login/')); ?>',
        '{new_password}': 'Sample@Password123',
        '{assessment_name}': 'Q4 2025 Assessment',
        '{due_date}': '<?php echo date("F j, Y", strtotime("+30 days")); ?>',
        '{days_remaining}': '30',
        '{pending_list}': '<ul><li>Leadership Assessment</li><li>Team Collaboration Assessment</li></ul>'
    };

    // Handle template selection
    $('#email_template').on('change', function() {
        const selectedTemplate = $(this).val();
        const customFields = $('#custom_email_fields');
        
        if (selectedTemplate === 'custom') {
            customFields.slideDown();
            updatePreview(
                $('#custom_email_subject').val(),
                tinymce.get('custom_email_body') ? tinymce.get('custom_email_body').getContent() : ''
            );
        } else {
            customFields.slideUp();
            if (emailTemplates[selectedTemplate]) {
                updatePreview(
                    emailTemplates[selectedTemplate].subject,
                    emailTemplates[selectedTemplate].body
                );
            } else {
                showPreviewPlaceholder();
            }
        }
    });

    // Handle custom email changes
    $('#custom_email_subject').on('input', function() {
        if ($('#email_template').val() === 'custom') {
            updatePreview(
                $(this).val(),
                tinymce.get('custom_email_body') ? tinymce.get('custom_email_body').getContent() : ''
            );
        }
    });

    // TinyMCE editor change handler
    if (typeof tinymce !== 'undefined') {
        tinymce.on('AddEditor', function(e) {
            e.editor.on('Change KeyUp', function() {
                if ($('#email_template').val() === 'custom') {
                    updatePreview(
                        $('#custom_email_subject').val(),
                        this.getContent()
                    );
                }
            });
        });
    }

    // Update preview content
    function updatePreview(subject, body) {
        if (!subject && !body) {
            showPreviewPlaceholder();
            return;
        }

        const preview = `
            <div class="email-preview-message">
                <div class="email-preview-subject">
                    Subject: ${replacePlaceholders(subject) || '(No subject)'}
                </div>
                <div class="email-preview-content">
                    ${replacePlaceholders(body) || '(No content)'}
                </div>
            </div>
        `;
        
        $('#email_preview').html(preview);
    }

    // Show placeholder in preview
    function showPreviewPlaceholder() {
        $('#email_preview').html(`
            <div class="email-preview-placeholder">
                Select an email template to preview
            </div>
        `);
    }

    // Replace placeholders with sample data
    function replacePlaceholders(text) {
        if (!text) return '';
        return text.replace(/{[^}]+}/g, match => sampleData[match] || match);
    }

    // Refresh preview
    window.refreshPreview = function() {
        const template = $('#email_template').val();
        if (template === 'custom') {
            updatePreview(
                $('#custom_email_subject').val(),
                tinymce.get('custom_email_body') ? tinymce.get('custom_email_body').getContent() : ''
            );
        } else if (emailTemplates[template]) {
            updatePreview(
                emailTemplates[template].subject,
                emailTemplates[template].body
            );
        }
    };

    // Recipients Selection
    function updateSelectedCount() {
        const selectedCount = $('.user-select:checked').length;
        $('.selected-count').text(
            selectedCount > 0 ? 
            `${selectedCount} user${selectedCount !== 1 ? 's' : ''} selected` : 
            ''
        );
    }

    // Group selection
    $('.group-select').on('change', function() {
        const groupId = $(this).data('group');
        const isChecked = $(this).prop('checked');
        $(`.user-select.group-${groupId}`).prop('checked', isChecked);
        updateSelectedCount();
    });

    // Individual user selection
    $('.user-select').on('change', function() {
        const groupClass = $(this).attr('class').split(' ').find(c => c.startsWith('group-'));
        const groupId = groupClass.replace('group-', '');
        const totalUsers = $(`.${groupClass}`).length;
        const selectedUsers = $(`.${groupClass}:checked`).length;
        
        $(`input[data-group="${groupId}"]`).prop({
            checked: selectedUsers === totalUsers,
            indeterminate: selectedUsers > 0 && selectedUsers < totalUsers
        });
        
        updateSelectedCount();
    });

    // Select all users
    $('.select-all-users').on('click', function() {
        $('.user-select').prop('checked', true);
        $('.group-select').prop({
            checked: true,
            indeterminate: false
        });
        updateSelectedCount();
    });

    // Clear all selections
    $('.clear-all-users').on('click', function() {
        $('.user-select, .group-select').prop({
            checked: false,
            indeterminate: false
        });
        updateSelectedCount();
    });

    // Form validation
    $('form').on('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        const template = $('#email_template').val();
        const selectedUsers = $('.user-select:checked').length;

        if (!template) {
            e.preventDefault();
            alert('Please select an email template.');
            return false;
        }

        if (template === 'custom') {
            const subject = $('#custom_email_subject').val().trim();
            const body = tinymce.get('custom_email_body') ? 
                        tinymce.get('custom_email_body').getContent().trim() : '';
            
            if (!subject || !body) {
                e.preventDefault();
                alert('Please fill in both subject and body for custom email.');
                return false;
            }
        }

        if (selectedUsers === 0) {
            e.preventDefault();
            alert('Please select at least one recipient.');
            return false;
        }

        if (!confirm(`Are you sure you want to send this email to ${selectedUsers} recipient${selectedUsers !== 1 ? 's' : ''}?`)) {
            e.preventDefault();
            return false;
        }

        $(this).addClass('was-validated');
    });

    // Initialize
    updateSelectedCount();
    showPreviewPlaceholder();
});
</script>
