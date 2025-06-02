<?php
if (!defined('ABSPATH')) exit;

// Check for missing tables
$missing_tables = get_option('assessment_360_missing_tables');
if ($missing_tables) {
    ?>
    <div class="notice notice-error">
        <p><strong>Error:</strong> Required database tables are missing. Please deactivate and reactivate the plugin.</p>
        <p>Missing tables: <?php echo esc_html(implode(', ', $missing_tables)); ?></p>
    </div>
    <?php
    return;
}

// Get existing values
$org_name = get_option('assessment_360_organization_name', '');
$org_logo = get_option('assessment_360_organization_logo', '');

// Default email templates
$welcome_email_subject_default = "Welcome to {org_name} 360° Assessment";
$welcome_email_body_default = "Dear {first_name},

Welcome to the {org_name} 360° Assessment System!

Your account has been created with the following credentials:
Email: {email}
Password: {password}

Please login at: {login_url}

For security reasons, we recommend changing your password after your first login.

Best regards,
The Assessment Team";

$reminder_email_subject_default = "Reminder: Complete Your 360° Assessment";
$reminder_email_body_default = "Dear {first_name},

This is a friendly reminder that you have pending assessments to complete.

Assessment Details:
- Name: {assessment_name}
- Due Date: {due_date}
- Days Remaining: {days_remaining}

Your pending assessments:
{pending_list}

Please login at {login_url} to complete your assessments.

Best regards,
The Assessment Team";

// Get saved values or use defaults
$welcome_email_subject = get_option('assessment_360_welcome_email_subject', $welcome_email_subject_default);
$welcome_email_body = get_option('assessment_360_welcome_email_body', $welcome_email_body_default);
$reminder_email_subject = get_option('assessment_360_reminder_email_subject', $reminder_email_subject_default);
$reminder_email_body = get_option('assessment_360_reminder_email_body', $reminder_email_body_default);

// Display any error messages
if (isset($_GET['error'])) {
    echo '<div class="notice notice-error"><p>' . esc_html($_GET['error']) . '</p></div>';
}

// Display success messages
if (isset($_GET['message'])) {
    echo '<div class="notice notice-success"><p>' . esc_html($_GET['message']) . '</p></div>';
}
?>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
    <?php wp_nonce_field('save_setup_settings', 'setup_settings_nonce'); ?>
    <input type="hidden" name="action" value="save_setup_settings">


    <h2>Organization Details</h2>
    <table class="form-table">
        <tr>
            <th><label for="org_name">Organization Name <span class="required">*</span></label></th>
            <td>
                <input type="text" 
                       id="org_name" 
                       name="org_name" 
                       class="regular-text" 
                       value="<?php echo esc_attr($org_name); ?>" 
                       required>
            </td>
        </tr>
        <tr>
            <th><label for="org_logo">Organization Logo</label></th>
            <td>
                <div class="logo-upload-container">
                    <!-- Logo Preview Container -->
                    <div id="logo-preview-container" class="current-logo mb-3" <?php echo empty($org_logo) ? 'style="display:none;"' : ''; ?>>
                        <img src="<?php echo esc_url($org_logo); ?>" 
                             id="logo-preview"
                             alt="Organization Logo" 
                             style="max-height: 100px;">
                    </div>

                    <!-- File Input -->
                    <input type="file" 
                           id="org_logo" 
                           name="org_logo" 
                           accept="image/jpeg,image/png,image/gif"
                           class="regular-text">

                    <!-- Error Container -->
                    <div id="logo-error" class="notice notice-error" style="display:none;"></div>

                    <p class="description">
                        Accepted formats: JPG, PNG, GIF. Maximum size: 1MB
                    </p>
                </div>
            </td>
        </tr>

    </table>

    <h2>Email Templates</h2>
    <p class="description">Available placeholders: {first_name}, {last_name}, {email}, {password}, {login_url}, {org_name}</p>
    
    <h3>Welcome Email</h3>
    <table class="form-table">
        <tr>
            <th><label for="welcome_email_subject">Subject</label></th>
            <td>
                <input type="text" 
                       id="welcome_email_subject" 
                       name="welcome_email_subject" 
                       class="regular-text" 
                       value="<?php echo esc_attr($welcome_email_subject); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="welcome_email_body">Body</label></th>
            <td>
                <textarea id="welcome_email_body" 
                          name="welcome_email_body" 
                          class="large-text" 
                          rows="10"><?php echo esc_textarea($welcome_email_body); ?></textarea>
            </td>
        </tr>
    </table>

    <h3>Reminder Email</h3>
    <p class="description">Additional placeholders: {assessment_name}, {due_date}, {days_remaining}, {pending_list}</p>
    <table class="form-table">
        <tr>
            <th><label for="reminder_email_subject">Subject</label></th>
            <td>
                <input type="text" 
                       id="reminder_email_subject" 
                       name="reminder_email_subject" 
                       class="regular-text" 
                       value="<?php echo esc_attr($reminder_email_subject); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="reminder_email_body">Body</label></th>
            <td>
                <textarea id="reminder_email_body" 
                          name="reminder_email_body" 
                          class="large-text" 
                          rows="10"><?php echo esc_textarea($reminder_email_body); ?></textarea>
            </td>
        </tr>
    </table>

    <div class="setup-actions">
        <a href="<?php echo esc_url(add_query_arg('step', 'welcome')); ?>" class="button">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <div>
            <a href="<?php echo esc_url(add_query_arg('step', 'groups')); ?>" class="button">Skip</a>
            <button type="submit" class="button button-primary">
                Save and Continue <i class="bi bi-arrow-right"></i>
            </button>
        </div>
    </div>
</form>

<style>
.required {
    color: #dc3545;
}


.setup-actions {
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
}
.notice {
    margin: 1rem 0;
}

.logo-upload-container {
    margin-bottom: 20px;
}

.current-logo {
    background: #fff;
    border: 1px solid #ddd;
    padding: 10px;
    border-radius: 4px;
    display: inline-block;
    margin-bottom: 10px;
}

#logo-error {
    margin: 10px 0;
    padding: 8px 12px;
    border-left: 4px solid #dc3545;
}

.notice-error {
    border-left-color: #dc3545;
    background: #fef0f0;
}

/* Improve file input appearance */
input[type="file"] {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fff;
    width: 100%;
    max-width: 400px;
}

.description {
    margin-top: 8px;
    color: #666;
    font-style: italic;
}
</style>


<script>
jQuery(document).ready(function($) {
    // Logo upload handling
    const logoInput = $('#org_logo');
    const logoPreview = $('#logo-preview');
    const logoPreviewContainer = $('#logo-preview-container');
    const errorDiv = $('#logo-error');
    const maxSize = 1048576; // 1MB in bytes
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

    logoInput.on('change', function(e) {
        const file = this.files[0];
        errorDiv.hide().html('');

        if (file) {
            // Validate file type
            if (!allowedTypes.includes(file.type)) {
                errorDiv.html('<p>Invalid file type. Please upload a JPG, PNG, or GIF image.</p>').show();
                this.value = '';
                return;
            }

            // Validate file size
            if (file.size > maxSize) {
                errorDiv.html('<p>File is too large. Maximum size is 1MB.</p>').show();
                this.value = '';
                return;
            }

            // Show preview
            const reader = new FileReader();
            reader.onload = function(e) {
                logoPreview.attr('src', e.target.result);
                logoPreviewContainer.show();
            };
            reader.readAsDataURL(file);
        }
    });

    // Form validation
    $('form').on('submit', function(e) {
        const file = logoInput[0].files[0];
        if (file) {
            if (!allowedTypes.includes(file.type)) {
                e.preventDefault();
                errorDiv.html('<p>Invalid file type. Please upload a JPG, PNG, or GIF image.</p>').show();
                return false;
            }

            if (file.size > maxSize) {
                e.preventDefault();
                errorDiv.html('<p>File is too large. Maximum size is 1MB.</p>').show();
                return false;
            }
        }

        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true)
                .html('<i class="bi bi-hourglass-split me-2"></i>Saving...');

        // Re-enable after 3 seconds if form hasn't redirected
        setTimeout(() => {
            if (submitBtn.prop('disabled')) {
                submitBtn.prop('disabled', false).html(originalText);
            }
        }, 3000);
    });
});
</script>


