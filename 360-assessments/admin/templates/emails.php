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

    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('assessment_360_email_settings_nonce'); ?>
        <input type="hidden" name="action" value="assessment_360_save_email_settings">

        <!-- Welcome Email Template -->
        <div class="email-template-section">
            <h2>Welcome Email</h2>
            <p class="description">This email is sent to users when their account is created.</p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="welcome_email_subject">Subject</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="welcome_email_subject" 
                               name="welcome_email_subject" 
                               class="large-text" 
                               value="<?php echo esc_attr(get_option('assessment_360_welcome_email_subject', '')); ?>">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="welcome_email_body">Body</label>
                    </th>
                    <td>
                        <textarea id="welcome_email_body" 
                                  name="welcome_email_body" 
                                  class="large-text code" 
                                  rows="10"><?php echo esc_textarea(get_option('assessment_360_welcome_email_body', '')); ?></textarea>
                        <p class="description">
                            Available placeholders:<br>
                            {first_name} - User's first name<br>
                            {email} - User's email address<br>
                            {password} - User's temporary password<br>
                            {login_url} - Login page URL<br>
                            {org_name} - Organization name
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Reminder Email Template -->
        <div class="email-template-section">
            <h2>Reminder Email</h2>
            <p class="description">This email is sent to users who have pending assessments.</p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="reminder_email_subject">Subject</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="reminder_email_subject" 
                               name="reminder_email_subject" 
                               class="large-text" 
                               value="<?php echo esc_attr(get_option('assessment_360_reminder_email_subject', '')); ?>">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="reminder_email_body">Body</label>
                    </th>
                    <td>
                        <textarea id="reminder_email_body" 
                                  name="reminder_email_body" 
                                  class="large-text code" 
                                  rows="10"><?php echo esc_textarea(get_option('assessment_360_reminder_email_body', '')); ?></textarea>
                        <p class="description">
                            Available placeholders:<br>
                            {first_name} - User's first name<br>
                            {assessment_name} - Assessment name<br>
                            {due_date} - Assessment due date<br>
                            {days_remaining} - Days remaining until due date<br>
                            {pending_list} - List of pending assessments<br>
                            {login_url} - Login page URL
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Email Preview -->
        <div class="email-preview-section">
            <h2>Email Preview</h2>
            <p class="description">This shows how your emails will look when sent.</p>

            <div class="email-preview-tabs">
                <button type="button" class="preview-tab active" data-template="welcome">Welcome Email</button>
                <button type="button" class="preview-tab" data-template="reminder">Reminder Email</button>
            </div>

            <div class="email-preview-content">
                <div id="welcome-preview" class="preview-panel active">
                    <!-- Welcome email preview will be loaded here -->
                </div>
                <div id="reminder-preview" class="preview-panel">
                    <!-- Reminder email preview will be loaded here -->
                </div>
            </div>
        </div>

        <?php submit_button('Save Email Templates'); ?>
    </form>
</div>

<style>
.email-template-section {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.email-preview-section {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.email-preview-tabs {
    margin-bottom: 20px;
    border-bottom: 1px solid #ccd0d4;
}

.preview-tab {
    padding: 10px 20px;
    margin: 0;
    border: none;
    background: none;
    cursor: pointer;
}

.preview-tab.active {
    border-bottom: 2px solid #0073aa;
    font-weight: 600;
}

.preview-panel {
    display: none;
    padding: 20px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
}

.preview-panel.active {
    display: block;
}

.description {
    color: #666;
    font-style: italic;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Preview tabs functionality
    $('.preview-tab').on('click', function() {
        const template = $(this).data('template');
        
        // Update tabs
        $('.preview-tab').removeClass('active');
        $(this).addClass('active');
        
        // Update panels
        $('.preview-panel').removeClass('active');
        $(`#${template}-preview`).addClass('active');
        
        // Generate preview
        updateEmailPreview(template);
    });

    // Update preview when template is changed
    $('#welcome_email_subject, #welcome_email_body, #reminder_email_subject, #reminder_email_body')
        .on('input', function() {
            const template = $(this).attr('id').includes('welcome') ? 'welcome' : 'reminder';
            updateEmailPreview(template);
        });

    function updateEmailPreview(template) {
        const subject = $(`#${template}_email_subject`).val();
        const body = $(`#${template}_email_body`).val();
        
        // Replace placeholders with sample data
        const sampleData = {
            first_name: 'John',
            email: 'john.doe@example.com',
            password: '********',
            login_url: '<?php echo esc_url(home_url('/360-assessment-login/')); ?>',
            org_name: '<?php echo esc_js(get_option('assessment_360_organization_name', 'Your Organization')); ?>',
            assessment_name: 'Q2 2025 Assessment',
            due_date: 'June 30, 2025',
            days_remaining: '15',
            pending_list: '- Jane Smith (Manager)\n- Bob Johnson (Peer)\n- Sarah Wilson (Direct Report)'
        };

        let previewSubject = subject;
        let previewBody = body;

        Object.keys(sampleData).forEach(key => {
            const placeholder = new RegExp(`{${key}}`, 'g');
            previewSubject = previewSubject.replace(placeholder, sampleData[key]);
            previewBody = previewBody.replace(placeholder, sampleData[key]);
        });

        // Update preview
        $(`#${template}-preview`).html(`
            <div class="email-preview">
                <div class="preview-subject">
                    <strong>Subject:</strong> ${previewSubject}
                </div>
                <div class="preview-body">
                    ${previewBody.replace(/\n/g, '<br>')}
                </div>
            </div>
        `);
    }

    // Initialize preview
    updateEmailPreview('welcome');
});
</script>
