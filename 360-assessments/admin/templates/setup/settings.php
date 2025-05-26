<h2 class="h4 mb-4">General Settings</h2>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('save_setup_settings'); ?>
    <input type="hidden" name="action" value="save_setup_settings">
    <input type="hidden" name="next_step" value="<?php echo esc_attr($steps[$current_step]['next']); ?>">
    <?php wp_nonce_field('save_setup_settings'); ?>
    <input type="hidden" name="action" value="save_setup_settings">
    <input type="hidden" name="next_step" value="<?php echo esc_attr($steps[$current_step]['next']); ?>">
    
    <!-- Organization Settings -->
    <div class="mb-3">
        <label for="org_name" class="form-label">Organization Name</label>
        <input type="text" class="form-control" id="org_name" name="org_name" 
               value="<?php echo esc_attr(get_option('assessment_360_organization_name')); ?>">
    </div>

    <div class="mb-3">
        <label for="org_logo" class="form-label">Organization Logo URL</label>
        <input type="url" class="form-control" id="org_logo" name="org_logo" 
               value="<?php echo esc_attr(get_option('assessment_360_organization_logo')); ?>">
    </div>

    <!-- Email Templates -->
    <div class="mb-3">
        <label for="welcome_email" class="form-label">Welcome Email Template</label>
        <textarea class="form-control" id="welcome_email" name="welcome_email" rows="4"><?php 
            echo esc_textarea(get_option('assessment_360_welcome_email', 
                "Welcome to the 360° Assessment System!\n\n" .
                "Your account has been created with the following credentials:\n" .
                "Email: {email}\n" .
                "Password: {password}\n\n" .
                "Please login at: {login_url}"
            )); 
        ?></textarea>
        <div class="form-text">
            Available variables: {first_name}, {last_name}, {email}, {password}, {login_url}
        </div>
    </div>

    <div class="mb-3">
        <label for="reminder_email" class="form-label">Reminder Email Template</label>
        <textarea class="form-control" id="reminder_email" name="reminder_email" rows="4"><?php 
            echo esc_textarea(get_option('assessment_360_reminder_email',
                "Hello {first_name},\n\n" .
                "This is a reminder that you have pending assessments to complete.\n" .
                "Please login at {login_url} to complete your assessments.\n\n" .
                "Thank you."
            )); 
        ?></textarea>
        <div class="form-text">
            Available variables: {first_name}, {last_name}, {login_url}
        </div>
    </div>

    <!-- Navigation buttons -->
    <div class="setup-actions">
        <div>
            <?php if ($current_step !== 'welcome' && $current_step !== 'complete'): ?>
                <a href="<?php echo esc_url(get_setup_step_url($steps[$current_step]['next'])); ?>" 
                   class="btn btn-outline-secondary">
                    Skip this step
                </a>
            <?php endif; ?>
        </div>
        <div>
            <?php if ($current_step === 'complete'): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=assessment-360-dashboard')); ?>" 
                   class="btn btn-primary">
                    Go to Dashboard <i class="bi bi-arrow-right ms-2"></i>
                </a>
            <?php else: ?>
                <button type="submit" class="btn btn-primary">
                    <?php echo $current_step === 'welcome' ? 'Get Started' : 'Save and Continue'; ?> 
                    <i class="bi bi-arrow-right ms-2"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>
</form>
