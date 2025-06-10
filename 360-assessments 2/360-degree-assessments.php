<?php
/*
Plugin Name: 360 Assessments (Modular)
Description: A comprehensive 360-degree feedback system for employee assessments (modularized version)
Version: 3.0.0
Author: Patrick Mwangi
License: GPL v2 or later
Text Domain: 360-degree-assessments
*/

if (!defined('ABSPATH')) exit;

add_action('init', function() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
});

// Plugin constants
define('ASSESSMENT_360_VERSION', '3.0.0');
define('ASSESSMENT_360_PLUGIN_FILE', __FILE__);
define('ASSESSMENT_360_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ASSESSMENT_360_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Error handling for deprecated warnings
set_error_handler(function($errno, $errstr) {
    if (str_contains($errstr, 'Passing null to parameter')) return true;
    return false;
}, E_DEPRECATED);

// Autoload core classes (if using class files as in the original project)
spl_autoload_register(function ($class) {
    $prefix = 'Assessment_360_';
    if (strpos($class, $prefix) !== 0) return;
    $relative_class = substr($class, strlen($prefix));
    $file = ASSESSMENT_360_PLUGIN_DIR . 'includes/class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';
    if (file_exists($file)) require_once $file;
});

//login authentication
require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/class-auth-handler.php';

require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions.php';
// Include modularized function/handler files
require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/database.php';
require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/setup.php'; 
require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/users.php';
require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/assessments.php';
require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/assessment-form.php';
require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/email.php';
require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/pdf.php';
require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/forms.php';

// Register activation hook
register_activation_hook(__FILE__, 'assessment_360_plugin_activate');
function assessment_360_plugin_activate($network_wide) {
    // Create database tables
    if (function_exists('assessment_360_activate')) {
        assessment_360_activate($network_wide); // pass the argument!
    }

    // Wizard completion flag logic
    if (get_option('assessment_360_wizard_complete') != 1) {
        update_option('assessment_360_do_setup_redirect', 1);
    } else {
        update_option('assessment_360_do_dashboard_redirect', 1);
    }
}

// Redirect after plugin activation
add_action('admin_init', function() {
    // Setup wizard redirect
    if (get_option('assessment_360_do_setup_redirect')) {
        delete_option('assessment_360_do_setup_redirect');
        wp_safe_redirect(add_query_arg(
            array(
                'page' => 'assessment-360-setup',
                'step' => 'welcome'
            ),
            admin_url('admin.php')
        ));
        exit;
    }
    // Dashboard redirect if wizard complete
    if (get_option('assessment_360_do_dashboard_redirect')) {
        delete_option('assessment_360_do_dashboard_redirect');
        wp_safe_redirect(admin_url('admin.php?page=assessment-360'));
        exit;
    }
});

// Always register the hidden setup wizard page
add_action('admin_menu', function() {
    add_submenu_page(
        null,
        '360° Assessment Setup',
        'Setup',
        'manage_options',
        'assessment-360-setup',
        'render_setup_page'
    );

    // Register other menus only if setup is complete
    // (Legacy: check for 'assessment_360_do_setup' - but now use 'assessment_360_wizard_complete')
    if (get_option('assessment_360_wizard_complete') == 1) {
        add_menu_page('360° Assessments', '360° Assessments', 'manage_options', 'assessment-360', 'assessment_360_dashboard_page', 'dashicons-groups', 30);
        $submenus = [
            'assessment-360-assessments' => ['title' => 'Assessments', 'callback' => 'assessment_360_assessments_page'],
            'assessment-360-user-management' => ['title' => 'User Management', 'callback' => 'assessment_360_user_management_page'],
            'assessment-360-forms' => ['title' => 'Assessment Forms', 'callback' => 'assessment_360_forms_page'],
            'assessment-360-results' => ['title' => 'Assessment Results', 'callback' => 'assessment_360_results'],
            'assessment-360-email-templates' => ['title' => 'Send Emails', 'callback' => 'assessment_360_email_templates_page'],
            'assessment-360-settings' => ['title' => 'Settings', 'callback' => 'assessment_360_settings_page'],
        ];
        foreach ($submenus as $slug => $menu) {
            add_submenu_page('assessment-360', $menu['title'], $menu['title'], 'manage_options', $slug, $menu['callback']);
        }
    }
});

// Setup wizard page callback (must be loaded before admin_menu)
function render_setup_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    // Enqueue the media uploader scripts so wp.media works!
    wp_enqueue_media();
    require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/setup.php';
}

// Show setup wizard notice if not completed
add_action('admin_notices', function() {
    $screen = get_current_screen();
    if ($screen->id !== 'admin_page_assessment-360-setup' && get_option('assessment_360_wizard_complete') != 1) {
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <?php 
                echo wp_kses(
                    sprintf(
                        'Welcome to 360° Assessment! Please <a href="%s">complete the setup</a> to get started.',
                        esc_url(admin_url('admin.php?page=assessment-360-setup'))
                    ),
                    array('a' => array('href' => array()))
                ); 
                ?>
            </p>
        </div>
        <?php
    }
});

// Helper: Mark wizard as complete (call this at the end of setup wizard)
function assessment_360_mark_wizard_complete() {
    update_option('assessment_360_wizard_complete', 1);
}

// Plugin initialization: managers, DB updates, setup wizard
function assessment_360_init() {
    Assessment_360_User_Manager::get_instance();
    Assessment_360_Group_Manager::get_instance();
    Assessment_360_Assessment_Manager::get_instance();
    Assessment_360_Template_Loader::get_instance();
    Assessment_360_Settings_Manager::get_instance();
    assessment_360_update_db();
}
add_action('plugins_loaded', 'assessment_360_init');

// Flush rewrite rules on activation (form endpoint etc.)
function assessment_360_flush_rewrite_rules() {
    assessment_360_register_endpoints();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'assessment_360_flush_rewrite_rules');

// Register rewrite rules and endpoints
function assessment_360_register_endpoints() {
    add_rewrite_rule('assessment-form/?$', 'index.php?assessment_360_form=1', 'top');
    add_rewrite_tag('%assessment_360_form%', '1');
}
add_action('init', 'assessment_360_register_endpoints', 10);

// Add custom query vars
add_filter('query_vars', function($vars) {
    $vars[] = 'assessment_360_form';
    $vars[] = 'assessment_id';
    $vars[] = 'assessee_id';
    $vars[] = 'self_assessment';
    return $vars;
});

// Template redirect for frontend assessment form
add_action('template_redirect', function() {
    if (get_query_var('assessment_360_form')) {
        $template = ASSESSMENT_360_PLUGIN_DIR . 'templates/page-assessment-form.php';
        if (file_exists($template)) {
            include $template;
            exit;
        }
    }
});

// Template overrides for login/dashboard/forgot/reset pages
add_filter('template_include', function($template) {
    // 360 Assessment Login page
    if (is_page('360-assessment-login')) {
        $new_template = ASSESSMENT_360_PLUGIN_DIR . 'templates/page-login.php';
        if (file_exists($new_template)) return $new_template;
    }
    // 360 Assessment Dashboard page
    elseif (is_page('360-assessment-dashboard')) {
        // Use plugin session key, not $_SESSION['user_id']
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['assessment_360_user_id'])) {
            wp_redirect(home_url('/360-assessment-login/'));
            exit;
        }
        $new_template = ASSESSMENT_360_PLUGIN_DIR . 'templates/page-dashboard.php';
        if (file_exists($new_template)) return $new_template;
    }
    // Forgot Password page
    elseif (is_page('forgot-password')) {
        $new_template = ASSESSMENT_360_PLUGIN_DIR . 'templates/forgot-password.php';
        if (file_exists($new_template)) return $new_template;
    }
    // Reset Password page
    elseif (is_page('reset-password')) {
        $new_template = ASSESSMENT_360_PLUGIN_DIR . 'templates/reset-password.php';
        if (file_exists($new_template)) return $new_template;
    }
    //Assessment form
    elseif (is_page('360-assessment-form')) {
        $new_template = ASSESSMENT_360_PLUGIN_DIR . 'templates/page-assessment-form.php';
        if (file_exists($new_template)) return $new_template;
    }
    return $template;
});

// Register and enqueue assets (admin and public)
add_action('init', 'assessment_360_register_public_assets', 20);
function assessment_360_register_public_assets() {
    wp_register_style('assessment-360-form', ASSESSMENT_360_PLUGIN_URL . 'public/css/form.css', [], ASSESSMENT_360_VERSION);
    wp_register_script('assessment-360-form', ASSESSMENT_360_PLUGIN_URL . 'public/js/assessment-form.js', ['jquery'], ASSESSMENT_360_VERSION, true);
}
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'assessment-360') !== false) {
        wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css', [], '5.3.2');
        wp_enqueue_script('bootstrap-bundle', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js', ['jquery'], '5.3.2', true);
        wp_enqueue_style('bootstrap-icons', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css', array(), '1.11.2' );
        wp_enqueue_style('assessment-360-admin', ASSESSMENT_360_PLUGIN_URL . 'admin/css/admin-style.css', ['bootstrap'], ASSESSMENT_360_VERSION);
        wp_enqueue_script('assessment-360-admin', ASSESSMENT_360_PLUGIN_URL . 'admin/js/admin.js', ['jquery'], ASSESSMENT_360_VERSION, true);
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery']);
        wp_localize_script('assessment-360-admin', 'assessment360Ajax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('assessment_360_form_preview')
        ]);
    }
});
add_action('wp_enqueue_scripts', 'assessment_360_enqueue_public_assets');
function assessment_360_enqueue_public_assets() {
    if (get_query_var('assessment_360_form')) {
        wp_enqueue_style('assessment-360-form');
    }
}

// Admin page callbacks (these are usually just template includes)
function assessment_360_dashboard_page()      { require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/dashboard.php'; }
function assessment_360_assessments_page()    { require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/assessments.php'; }
function assessment_360_user_management_page(){ require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/user-management.php'; }
function assessment_360_forms_page()          { require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/forms.php'; }
function assessment_360_results()             { require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/results.php'; }
function assessment_360_email_templates_page(){ require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/send-email.php'; }
function assessment_360_settings_page()       { require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/settings.php'; }

// End of main plugin file

/**
 * ===========================
 * Suggestions for Improvement
 * ===========================
 * - Consider using dependency injection for main class loading.
 * - Add a Service Provider pattern for better extensibility.
 * - Move menu page registration to its own file/class.
 * - Add hooks for third-party plugin extensibility.
 * - Add automated tests for plugin initialization and admin menu visibility logic.
 */