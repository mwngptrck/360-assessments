<?php
/**
 * Template Name: 360 Assessment Login
 */
if (!defined('ABSPATH')) exit;


// Start plugin session for login errors
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

//require_once locate_template('class-auth-handler.php');

// If already logged in as plugin user, redirect to dashboard
//if (Assessment_360_Auth_Handler::is_plugin_user_logged_in()) {
//    wp_redirect(home_url('/360-assessment-dashboard/'));
//    exit;
//}

// Get error message if any
$error_message = isset($_GET['error']) ? urldecode($_GET['error']) : '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>360° Assessment Login</title>
    <?php wp_head(); ?>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            max-width: 550px;
            margin: 0 auto;
            padding: 15px;
        }
        .login-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header img {
            max-width: 200px;
            height: auto;
            margin-bottom: 20px;
        }
        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        .btn-primary {
            padding: 12px;
            font-weight: 500;
        }
        .error-message {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-card">
                <div class="card-body p-4 p-md-5">
                    <div class="login-header">
                        <?php 
                        $logo_url = get_option('assessment_360_organization_logo');
                        if ($logo_url): 
                        ?>
                            <img src="<?php echo esc_url($logo_url); ?>" 
                                 alt="<?php echo esc_attr(get_option('assessment_360_organization_name')); ?>" 
                                 class="mb-4">
                        <?php endif; ?>
                        <h2><?php echo esc_html(get_option('assessment_360_organization_name')); ?></h2>
                        <h6 class="mb-4">360° Assessment</h6>
                    </div>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger error-message">
                            <?php echo esc_html($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="assessment_360_login">
                        <?php wp_nonce_field('assessment_360_login_action', 'assessment_360_login_nonce'); ?>
                        
                        <div class="mb-4">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo isset($_POST['email']) ? esc_attr($_POST['email']) : ''; ?>" 
                                   required>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            Login
                        </button>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <a href="<?php echo esc_url(home_url('/forgot-password/')); ?>" class="text-primary text-decoration-none">
                                <small><i class="bi bi-key me-1"></i>Forgot Password?</small>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (get_option('assessment_360_organization_name')): ?>
                <div class="text-center mt-4 text-muted">
                    <small>
                        <?php echo esc_html(get_option('assessment_360_organization_name')); ?> 
                        360° Assessment System
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php wp_footer(); ?>
</body>
</html>