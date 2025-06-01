<?php
if (!defined('ABSPATH')) exit;

/**
 * Template handling functions
 */
function assessment_360_handle_templates($template) {
    if (is_page('360-assessment-login')) {
        $new_template = ASSESSMENT_360_PLUGIN_DIR . 'templates/page-login.php';
        if (file_exists($new_template)) return $new_template;
    } 
    elseif (is_page('360-assessment-dashboard')) {
        if (!isset($_SESSION['user_id'])) {
            wp_redirect(home_url('/360-assessment-login/'));
            exit;
        }
        $new_template = ASSESSMENT_360_PLUGIN_DIR . 'templates/page-dashboard.php';
        if (file_exists($new_template)) return $new_template;
    }
    elseif (is_page('forgot-password')) {
        $new_template = ASSESSMENT_360_PLUGIN_DIR . 'templates/forgot-password.php';
        if (file_exists($new_template)) return $new_template;
    }
    elseif (is_page('reset-password')) {
        $new_template = ASSESSMENT_360_PLUGIN_DIR . 'templates/reset-password.php';
        if (file_exists($new_template)) return $new_template;
    }
    return $template;
}
add_filter('template_include', 'assessment_360_handle_templates');
