<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1>360° Assessment Settings</h1>

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

    <!-- Tabs -->
    <h2 class="nav-tab-wrapper">
        <a href="#general-settings" class="nav-tab nav-tab-active" data-tab="general-settings">General Settings</a>
        <a href="#email-settings" class="nav-tab" data-tab="email-settings">Email Settings</a>
    </h2>

    <!-- General Settings Tab -->
    <div id="general-settings" class="settings-tab active">
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
            <?php wp_nonce_field('assessment_360_settings', 'assessment_360_settings_nonce'); ?>
            <input type="hidden" name="action" value="assessment_360_save_settings">

            <div class="container">
                <div class="row">
                    <!-- Left Column: Organization Name & Logo -->
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="organization_name" class="form-label">Organization Name *</label>
                            <input type="text" 
                                   id="organization_name" 
                                   name="organization_name" 
                                   class="form-control" 
                                   value="<?php echo esc_attr(get_option('assessment_360_organization_name', '')); ?>" 
                                   required>
                        </div>

                        <div class="mb-4">
                            <label for="organization_logo" class="form-label">Organization Logo</label>
                            <?php 
                            $logo_url = get_option('assessment_360_organization_logo', '');
                            if ($logo_url): 
                            ?>
                                <div class="mb-2">
                                    <img src="<?php echo esc_url($logo_url); ?>" 
                                         alt="Organization Logo" 
                                         class="img-fluid" 
                                         style="max-width: 200px;">
                                </div>
                            <?php endif; ?>
                            <input type="file" 
                                   id="organization_logo" 
                                   name="organization_logo" 
                                   class="form-control" 
                                   accept="image/*">
                            <div class="form-text">
                                Recommended size: 200x50 pixels. Maximum file size: 2MB.
                            </div>
                            <?php if ($logo_url): ?>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo">
                                    <label class="form-check-label" for="remove_logo">
                                        Remove current logo
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right Column: Contact Details -->
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="contact_email" class="form-label">Contact Email</label>
                            <input type="email" 
                                   id="contact_email" 
                                   name="contact_email" 
                                   class="form-control" 
                                   value="<?php echo esc_attr(get_option('assessment_360_contact_email', '')); ?>">
                        </div>

                        <div class="mb-4">
                            <label for="contact_phone" class="form-label">Contact Phone</label>
                            <input type="tel" 
                                   id="contact_phone" 
                                   name="contact_phone" 
                                   class="form-control" 
                                   value="<?php echo esc_attr(get_option('assessment_360_contact_phone', '')); ?>">
                        </div>

                        <div class="mb-4">
                            <label for="contact_address" class="form-label">Contact Address</label>
                            <textarea id="contact_address" 
                                      name="contact_address" 
                                      class="form-control" 
                                      rows="3"><?php echo esc_textarea(get_option('assessment_360_contact_address', '')); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>


            <?php submit_button('Save General Settings'); ?>
        </form>
    </div>

    <!-- Email Settings Tab -->
    <div id="email-settings" class="settings-tab">
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('assessment_360_email_settings', 'assessment_360_email_settings_nonce'); ?>
            <input type="hidden" name="action" value="assessment_360_save_email_settings">

            <table class="form-table">
                <tr>
                    <th scope="row">Welcome Email</th>
                    <td>
                        <p>
                            <label for="welcome_email_subject">Subject</label><br>
                            <input type="text" 
                                   id="welcome_email_subject" 
                                   name="welcome_email_subject" 
                                   class="large-text" 
                                   value="<?php echo esc_attr(get_option('assessment_360_welcome_email_subject', '')); ?>">
                        </p>
                        <p>
                            <label for="welcome_email_body">Body</label><br>
                            <?php 
                            wp_editor(
                                get_option('assessment_360_welcome_email_body', ''), 
                                'welcome_email_body',
                                array(
                                    'media_buttons' => false,
                                    'textarea_name' => 'welcome_email_body',
                                    'textarea_rows' => 10,
                                    'teeny' => true,
                                    'quicktags' => true
                                )
                            );
                            ?>
                            <p class="description">
                                Available placeholders: {first_name}, {email}, {password}, {login_url}, {org_name}
                            </p>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Reminder Email</th>
                    <td>
                        <p>
                            <label for="reminder_email_subject">Subject</label><br>
                            <input type="text" 
                                   id="reminder_email_subject" 
                                   name="reminder_email_subject" 
                                   class="large-text" 
                                   value="<?php echo esc_attr(get_option('assessment_360_reminder_email_subject', '')); ?>">
                        </p>
                        <p>
                            <label for="reminder_email_body">Body</label><br>
                            <?php 
                            wp_editor(
                                get_option('assessment_360_reminder_email_body', ''), 
                                'reminder_email_body',
                                array(
                                    'media_buttons' => false,
                                    'textarea_name' => 'reminder_email_body',
                                    'textarea_rows' => 10,
                                    'teeny' => true,
                                    'quicktags' => true
                                )
                            );
                            ?>
                            <p class="description">
                                Available placeholders: {first_name}, {assessment_name}, {due_date}, {days_remaining}, {pending_list}, {login_url}
                            </p>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Save Email Settings'); ?>
        </form>
    </div>
</div>

<style>
.settings-tab {
    display: none;
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-top: none;
    margin-bottom: 20px;
}

.settings-tab.active {
    display: block;
}

.current-logo {
    margin-bottom: 15px;
}

.current-logo img {
    max-width: 200px;
    height: auto;
    border: 1px solid #ddd;
    padding: 5px;
}

.form-table th {
    width: 200px;
}

.wp-editor-container {
    margin-bottom: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab functionality
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Update tabs
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Update content
        $('.settings-tab').removeClass('active');
        $($(this).attr('href')).addClass('active');
        
        // Update URL hash
        history.pushState(null, null, $(this).attr('href'));
    });

    // Handle hash changes
    $(window).on('hashchange', function() {
        const hash = window.location.hash || '#general-settings';
        $(`.nav-tab[href="${hash}"]`).trigger('click');
    }).trigger('hashchange');

    // File input handling
    $('#organization_logo').on('change', function() {
        const file = this.files[0];
        if (file) {
            if (file.size > 2 * 1024 * 1024) { // 2MB
                alert('File size exceeds 2MB limit.');
                this.value = '';
            }
        }
    });

    // Remove logo checkbox handling
    $('input[name="remove_logo"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('#organization_logo').prop('disabled', true);
        } else {
            $('#organization_logo').prop('disabled', false);
        }
    });
});
</script>
