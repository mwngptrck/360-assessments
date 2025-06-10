<?php
/**
 * Template Name: Forgot Password
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password - <?php echo esc_html(get_option('assessment_360_organization_name')); ?></title>
    <?php wp_head(); ?>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <?php
                        $org_name = get_option('assessment_360_organization_name', '');
                        $logo_url = get_option('assessment_360_organization_logo', '');
                        ?>
                        
                        <div class="text-center mb-4">
                            <?php if ($logo_url): ?>
                                <img src="<?php echo esc_url($logo_url); ?>" 
                                     alt="<?php echo esc_attr($org_name); ?>"
                                     class="img-fluid mb-4"
                                     style="max-height: 60px;">
                            <?php endif; ?>
                            <h2 class="h4 mb-3">Password Reset</h2>
                            <p class="text-muted">Enter your email address to receive password reset instructions.</p>
                        </div>

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

                        <form method="post" action="" class="needs-validation" novalidate>
                            <?php wp_nonce_field('assessment_360_forgot_password', 'assessment_360_forgot_password_nonce'); ?>
                            <input type="hidden" name="action" value="assessment_360_forgot_password">
                            
                            <div class="mb-4">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" 
                                       name="email" 
                                       id="email" 
                                       class="form-control" 
                                       required 
                                       autocomplete="email"
                                       value="<?php echo isset($_POST['email']) ? esc_attr($_POST['email']) : ''; ?>">
                                <div class="invalid-feedback">
                                    Please enter a valid email address.
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="bi bi-envelope me-1"></i>Send Reset Link
                            </button>

                            <div class="text-center">
                                <a href="<?php echo esc_url(home_url('/360-assessment-login/')); ?>" 
                                   class="text-decoration-none">
                                    <i class="bi bi-arrow-left me-1"></i>Back to Login
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($org_name): ?>
                    <div class="text-center mt-4">
                        <small class="text-muted">
                            <?php echo esc_html($org_name); ?> 360° Assessment System
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function() {
        'use strict';
        
        document.querySelectorAll('.needs-validation').forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
    })();
    </script>
    <?php wp_footer(); ?>
</body>
</html>
