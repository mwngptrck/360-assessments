<?php
/**
 * Template Name: Forgot Password
 */

get_header(); ?>

<div class="assessment-360-login">
    <div class="login-container">
        <?php
        $org_name = get_option('assessment_360_organization_name', '');
        $logo_url = get_option('assessment_360_organization_logo', '');
        ?>
        
        <div class="login-header">
            <?php if ($logo_url): ?>
                <div class="organization-logo">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($org_name); ?>">
                </div>
            <?php endif; ?>
            <h2><?php echo esc_html($org_name); ?> - Password Reset</h2>
        </div>

        <?php if (isset($_GET['message'])): ?>
            <div class="assessment-360-message success">
                <p><?php echo esc_html($_GET['message']); ?></p>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="assessment-360-message error">
                <p><?php echo esc_html($_GET['error']); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="" class="forgot-password-form">
            <?php wp_nonce_field('assessment_360_forgot_password', 'assessment_360_forgot_password_nonce'); ?>
            <input type="hidden" name="action" value="assessment_360_forgot_password">
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" 
                       name="email" 
                       id="email" 
                       required 
                       class="form-control" 
                       autocomplete="email"
                       value="<?php echo isset($_POST['email']) ? esc_attr($_POST['email']) : ''; ?>">
            </div>
            
            <button type="submit" class="button button-primary button-large">
                Reset Password
            </button>

            <div class="back-to-login">
                <a href="<?php echo esc_url(home_url('/360-assessment-login/')); ?>">
                    Back to Login
                </a>
            </div>
        </form>
    </div>
</div>

<?php get_footer(); ?>
