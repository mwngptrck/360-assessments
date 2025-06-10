<?php
/**
 * Template Name: Reset Password
 */

$user_id = isset($_GET['user']) ? intval($_GET['user']) : 0;
$token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

if (!$user_id || !$token) {
    wp_redirect(home_url('/360-assessment-login/'));
    exit;
}

$user = Assessment_360_User::get_instance();
if (!$user->verify_reset_token($user_id, $token)) {
    wp_redirect(add_query_arg(
        'error',
        'This password reset link has expired or is invalid.',
        home_url('/360-assessment-login/')
    ));
    exit;
}

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
            <h2><?php echo esc_html((string)$org_name); ?> - Reset Password</h2>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="assessment-360-message error">
                <p><?php echo esc_html((string)$_GET['error']); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="" class="reset-password-form">
            <?php wp_nonce_field('assessment_360_reset_password', 'assessment_360_reset_password_nonce'); ?>
            <input type="hidden" name="action" value="assessment_360_reset_password">
            <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">
            <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
            
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" 
                       name="password" 
                       id="password" 
                       required 
                       class="form-control" 
                       minlength="8">
                <small class="form-text">Password must be at least 8 characters long.</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" 
                       name="confirm_password" 
                       id="confirm_password" 
                       required 
                       class="form-control" 
                       minlength="8">
            </div>
            
            <button type="submit" class="button button-primary button-large">
                Set New Password
            </button>
        </form>
    </div>
</div>

<?php get_footer(); ?>
