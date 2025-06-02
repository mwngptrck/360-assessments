<?php
/*
Plugin Name: 360 Assessments
Description: A comprehensive 360-degree feedback system for employee assessments
Version: 2.0.0
Author: Patrick Mwangi
License: GPL v2 or later
Text Domain: 360-degree-assessments
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core Setup
 */
// Define constants
define('ASSESSMENT_360_VERSION', '2.0.0');
define('ASSESSMENT_360_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ASSESSMENT_360_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Core Helper Functions - Required for Activation
 */
if (!function_exists('assessment_360_safe_redirect')) {
    function assessment_360_safe_redirect($url, $status = 302) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        wp_safe_redirect($url, $status);
        exit;
    }
}

/**
 * Required Files - Core Classes
 */
require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/classes/class-activator.php';

/**
 * Plugin Lifecycle Hooks
 */
// Activation
register_activation_hook(__FILE__, function() {
    // Run activation
    Assessment_360_Activator::activate();
    
    // Load setup functions
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/setup.php';
    
    // Set activation flags
    assessment_360_set_activation_flags();
});

// Deactivation
function assessment_360_deactivate() {
    delete_transient('assessment_360_activation_redirect');
}
register_deactivation_hook(__FILE__, 'assessment_360_deactivate');

// After all plugins are loaded
add_action('plugins_loaded', function() {
    /**
     * Required Files - Classes
     */
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/classes/class-user-manager.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/classes/class-group-manager.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/classes/class-assessment-manager.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/classes/class-template-loader.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/classes/class-settings-manager.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/classes/class-assessment.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/classes/class-position.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/classes/class-topic.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/classes/class-section.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/classes/class-question.php';

    /**
     * Required Files - Functions
     */
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/admin.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/setup.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/users.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/assessment.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/forms.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/groups.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/topics.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/sections.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/positions.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/questions.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/auth.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/email.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/template.php';
    require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions/uninstall.php';

    // Start session
    add_action('init', function() {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
    }, 1);
    
    // Admin initialization
    add_action('admin_init', function() {
        // Start output buffering
        ob_start();

        // Ensure admin context
        if (!defined('WP_ADMIN')) {
            define('WP_ADMIN', true);
        }

        // Ensure user session is maintained
        if (!session_id() && !headers_sent()) {
            session_start();
        }

        // Remove setup redirect for administrators
        if (current_user_can('manage_options')) {
            remove_action('admin_init', 'assessment_360_handle_setup_redirect', 20);
        }
    });

    // Prevent unauthorized redirects
    add_filter('wp_redirect', function($location) {
        error_log('Redirect attempted to: ' . $location);

        // If it's a setup wizard redirect, ensure it goes to admin
        if (
            isset($_POST['is_setup_wizard']) && 
            $_POST['is_setup_wizard'] === '1' &&
            strpos($location, '/wp-login.php') !== false
        ) {
            return admin_url('admin.php?page=assessment-360-setup&step=complete');
        }

        return $location;
    }, 999);

    // Maintain admin context
    add_action('admin_init', function() {
        if (
            isset($_POST['action']) && 
            $_POST['action'] === 'save_setup_position'
        ) {
            if (!defined('WP_ADMIN')) {
                define('WP_ADMIN', true);
            }
        }
    });

    // Clean up at shutdown
    add_action('shutdown', function() {
        while (ob_get_level()) {
            ob_end_clean();
        }
    });

    // Handle PHP 8 deprecation notices
    set_error_handler(function($errno, $errstr) {
        if (str_contains($errstr, 'Passing null to parameter')) {
            return true;
        }
        return false;
    }, E_DEPRECATED);
    
    // Admin assets
    add_action('admin_enqueue_scripts', function($hook) {
        if (strpos($hook, 'assessment-360') !== false) {
            wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css', [], '5.3.2');
            wp_enqueue_script('bootstrap-bundle', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js', ['jquery'], '5.3.2', true);
            wp_enqueue_style('bootstrap-icons', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css', array(), '1.11.2' );
            wp_enqueue_style('assessment-360-admin', ASSESSMENT_360_PLUGIN_URL . 'admin/css/admin-style.css', ['bootstrap'], ASSESSMENT_360_VERSION);
            wp_enqueue_script('assessment-360-admin', ASSESSMENT_360_PLUGIN_URL . 'admin/js/admin.js', ['jquery'], ASSESSMENT_360_VERSION, true);
            wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
            wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery']);
        }
    });
});

// Register uninstall hook
register_uninstall_hook(__FILE__, 'assessment_360_uninstall');
