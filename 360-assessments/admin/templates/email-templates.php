<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1>Email Templates</h1>

    <?php if (isset($_GET['message'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($_GET['message']); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($_GET['error']); ?></p>
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

    <div class="email-template-page">
        <!-- Left Column - Email Form -->
        <div class="email-form-column">
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('assessment_360_send_email', 'assessment_360_send_email_nonce'); ?>
                <input type="hidden" name="action" value="assessment_360_send_email">

                <div class="email-template-container">
                    <div class="template-selection">
                        <label for="email_template" class="form-label">Select Template *</label>
                        <select id="email_template" name="email_template" class="form-select" required>
                            <option value="">Select Template</option>
                            <option value="welcome">Welcome Email</option>
                            <option value="reminder">Reminder Email</option>
                            <option value="custom">Custom Email</option>
                        </select>
                    </div>

                    <div id="custom_email_fields" class="custom-email-section" style="display: none;">
                        <div class="form-group">
                            <label for="custom_email_subject">Subject *</label>
                            <input type="text" 
                                   id="custom_email_subject" 
                                   name="custom_email_subject" 
                                   class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="custom_email_body">Body *</label>
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

                    <div class="recipients-section">
                        <label class="form-label">Select Recipients *</label>
                        <?php if (!empty($grouped_users)): ?>
                            <div class="recipient-groups">
                                <?php foreach ($grouped_users as $group_id => $group): 
                                    if (empty($group['users'])) continue;
                                ?>
                                    <div class="recipient-group">
                                        <div class="group-header">
                                            <label>
                                                <input type="checkbox" 
                                                       class="group-select" 
                                                       data-group="<?php echo esc_attr($group_id); ?>">
                                                <?php echo esc_html($group['name']); ?>
                                                <span class="user-count">(<?php echo count($group['users']); ?> users)</span>
                                            </label>
                                        </div>
                                        <div class="group-users">
                                            <?php foreach ($group['users'] as $user): ?>
                                                <div class="user-option">
                                                    <label>
                                                        <input type="checkbox" 
                                                               name="recipients[]" 
                                                               value="<?php echo esc_attr($user->id); ?>"
                                                               class="user-select group-<?php echo esc_attr($group_id); ?>">
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
                            <div class="recipient-actions">
                                <button type="button" class="button select-all-users">Select All</button>
                                <button type="button" class="button clear-all-users">Clear All</button>
                                <span class="selected-count"></span>
                            </div>
                        <?php else: ?>
                            <div class="notice notice-warning inline">
                                <p>No active users found in any group.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php submit_button('Send Email'); ?>
                </div>
            </form>
        </div>

        <!-- Right Column - Email Preview -->
        <div class="email-preview-column">
            <div class="email-preview-container">
                <div class="email-preview-header">
                    <h2>Email Preview</h2>
                </div>
                <div class="email-preview-content">
                    <div id="email_preview" class="email-preview-body">
                        <div class="email-preview-placeholder">
                            Select an email template to preview
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
/* Layout */
.email-template-page {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}

.email-form-column {
    flex: 1;
    min-width: 300px;
}

.email-preview-column {
    flex: 2;
    min-width: 400px;
}

/* Form Container */
.email-template-container {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.template-selection {
    margin-bottom: 20px;
}

form {margin-top: 0}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
}

.form-select {
    width: 100%;
    max-width: 300px;
}

/* Custom Email Section */
.custom-email-section {
    background: #f8f9fa;
    padding: 15px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    margin: 15px 0;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
}

.form-control {
    width: 100%;
}

/* Recipients Section */
.recipients-section {
    margin-top: 20px;
}

.recipient-groups {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin: 10px 0;
}

.recipient-group {
    margin-bottom: 10px;
    background: #f8f9fa;
    padding: 10px;
}

.recipient-group:last-child {
    margin-bottom: 0;
}

.group-header {
    padding-bottom: 8px;
    border-bottom: 1px solid #dee2e6;
    margin-bottom: 8px;
}

.group-header label {
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
}

.group-header label:hover {
    color: #2271b1;
}

.user-count {
    color: #666;
    font-size: 0.9em;
    margin-left: 5px;
}

.group-users {
    margin-left: 25px;
}

.user-option {
    margin-bottom: 5px;
}

.user-option label {
    display: flex;
    align-items: center;
    padding: 3px 5px;
    border-radius: 3px;
    cursor: pointer;
}

.user-option label:hover {
    background-color: #f0f0f1;
}

.user-option input[type="checkbox"],
.group-header input[type="checkbox"] {
    margin-right: 8px;
}

/* Recipient Actions */
.recipient-actions {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #dee2e6;
}

.recipient-actions .button {
    margin-right: 10px;
}

.selected-count {
    display: inline-block;
    margin-left: 10px;
    color: #666;
    font-style: italic;
}

/* Preview Styles */
.email-preview-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    height: 100%;
    min-height: 600px;
    display: flex;
    flex-direction: column;
    position: sticky;
    top: 32px;
}

.email-preview-header {
    padding: 15px 20px;
    border-bottom: 1px solid #ccd0d4;
    background: #f8f9fa;
}

.email-preview-header h2 {
    margin: 0;
    font-size: 1.2em;
    font-weight: 600;
    color: #1d2327;
}

.email-preview-content {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
}

.email-preview-body {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    min-height: 500px;
}

.email-preview-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    min-height: 300px;
    color: #6c757d;
    font-style: italic;
}

.email-preview-message {
    padding: 20px;
}

.email-preview-subject {
    font-weight: bold;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #dee2e6;
}

.email-preview-content {
    line-height: 1.6;
}

/* WordPress Editor Adjustments */
.wp-editor-container {
    border: 1px solid #ddd;
}

.wp-editor-area {
    border: none !important;
}

/* Responsive Design */
@media screen and (max-width: 1200px) {
    .email-template-page {
        flex-direction: column;
    }
    
    .email-form-column,
    .email-preview-column {
        width: 100%;
    }
    
    .email-preview-container {
        position: static;
        min-height: 400px;
    }
}

/* Notice Styling */
.notice {
    margin: 15px 0;
}

.notice.inline {
    margin: 5px 0;
    padding: 10px;
}

/* Submit Button Adjustment */
.submit {
    padding: 15px 0 0 0;
    margin-top: 15px;
    border-top: 1px solid #dee2e6;
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
        '{new_password}': 'Sample@Password123',  // Add this line
        '{assessment_name}': 'Sample Assessment',
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
                $('#custom_email_body').val()
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
    $('#custom_email_subject, #custom_email_body').on('change keyup', function() {
        updatePreview(
            $('#custom_email_subject').val(),
            $('#custom_email_body').val()
        );
    });

    // Update preview content
    function updatePreview(subject, body) {
        if (!subject && !body) {
            showPreviewPlaceholder();
            return;
        }

        const preview = `
            <div class="email-preview-message">
                <div class="email-preview-subject">
                    Subject: ${subject || '(No subject)'}
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

    // Recipients Selection Functionality
    
    // Update selected users count
    function updateSelectedCount() {
        const selectedCount = $('.user-select:checked').length;
        $('.selected-count').text(
            selectedCount > 0 ? 
            `${selectedCount} user${selectedCount !== 1 ? 's' : ''} selected` : 
            ''
        );
    }

    // Handle group selection
    $('.group-select').on('change', function() {
        const groupId = $(this).data('group');
        const isChecked = $(this).prop('checked');
        
        $(`.user-select.group-${groupId}`).prop('checked', isChecked);
        updateSelectedCount();
    });

    // Update group checkbox state when individual users are selected/deselected
    $('.user-select').on('change', function() {
        const groupClass = $(this).attr('class').split(' ')[1];
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

    // Form Validation
    $('form').on('submit', function(e) {
        // Check if template is selected
        const template = $('#email_template').val();
        if (!template) {
            e.preventDefault();
            alert('Please select an email template.');
            return false;
        }

        // Check if custom email fields are filled when custom template is selected
        if (template === 'custom') {
            const subject = $('#custom_email_subject').val().trim();
            const body = $('#custom_email_body').val().trim();
            
            if (!subject || !body) {
                e.preventDefault();
                alert('Please fill in both subject and body for custom email.');
                return false;
            }
        }

        // Check if recipients are selected
        const selectedUsers = $('.user-select:checked').length;
        if (selectedUsers === 0) {
            e.preventDefault();
            alert('Please select at least one recipient.');
            return false;
        }

        // Confirm sending
        if (!confirm(`Are you sure you want to send this email to ${selectedUsers} recipient${selectedUsers !== 1 ? 's' : ''}?`)) {
            e.preventDefault();
            return false;
        }

        return true;
    });

    // Initialize
    updateSelectedCount();
    showPreviewPlaceholder();

    // Optional: Initialize first group expanded
    $('.recipient-group:first .group-users').show();
});
</script>
