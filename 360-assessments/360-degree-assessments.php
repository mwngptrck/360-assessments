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

if (WP_DEBUG) {
    add_action('activated_plugin', function($plugin) {
        if ($plugin === plugin_basename(__FILE__)) {
            error_log('360 Assessments plugin activated');
            error_log('Plugin directory: ' . ASSESSMENT_360_PLUGIN_DIR);
            error_log('Included files: ' . print_r(get_included_files(), true));
        }
    });
}

// Define plugin constants
define('ASSESSMENT_360_VERSION', '2.0.0');
define('ASSESSMENT_360_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ASSESSMENT_360_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Autoload classes
spl_autoload_register(function ($class) {
    // Check if the class uses our prefix
    $prefix = 'Assessment_360_';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, strlen($prefix));

    // Convert class name to file path
    $file = ASSESSMENT_360_PLUGIN_DIR . 'includes/class-' . 
           strtolower(str_replace('_', '-', $relative_class)) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require_once $file;
        
        if (WP_DEBUG) {
            error_log('Loaded class file: ' . $file);
        }
    } else {
        if (WP_DEBUG) {
            error_log('Failed to load class file: ' . $file);
        }
    }
});

// Include required files

require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/functions.php';
require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/class-user-manager.php';
require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/class-group-manager.php';
require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/class-assessment-manager.php';
require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/class-template-loader.php';
require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/class-settings-manager.php';
require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/class-assessment.php';
require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/class-position.php';
require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/class-topic.php';
require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/class-section.php';
require_once ASSESSMENT_360_PLUGIN_DIR . 'includes/class-question.php';

// Initialize the plugin
function assessment_360_init() {
    // Initialize managers
    Assessment_360_User_Manager::get_instance();
    Assessment_360_Group_Manager::get_instance();
    Assessment_360_Assessment_Manager::get_instance();
    Assessment_360_Template_Loader::get_instance();
    Assessment_360_Settings_Manager::get_instance();
}
add_action('plugins_loaded', 'assessment_360_init');

// Plugin activation
register_activation_hook(__FILE__, 'assessment_360_activate');
function assessment_360_activate($network_wide) {
    global $wpdb;
    
    try {
        if (WP_DEBUG) {
            error_log('Starting 360 Assessments plugin activation');
        }

        // Initialize managers one by one with error checking
        try {
            $position_manager = Assessment_360_Position::get_instance();
            $position_manager->verify_table_structure();
            
            if (WP_DEBUG) {
                error_log('Position manager initialized');
            }
        } catch (Exception $e) {
            error_log('Error initializing position manager: ' . $e->getMessage());
            throw $e;
        }

        try {
            $group_manager = Assessment_360_Group_Manager::get_instance();
            $group_manager->verify_table_structure();
            
            if (WP_DEBUG) {
                error_log('Group manager initialized');
            }
        } catch (Exception $e) {
            error_log('Error initializing group manager: ' . $e->getMessage());
            throw $e;
        }

        try {
            $user_manager = Assessment_360_User_Manager::get_instance();
            $user_manager->verify_table_structure();
            
            if (WP_DEBUG) {
                error_log('User manager initialized');
            }
        } catch (Exception $e) {
            error_log('Error initializing user manager: ' . $e->getMessage());
            throw $e;
        }

        try {
            $assessment_manager = Assessment_360_Assessment_Manager::get_instance();
            $assessment_manager->verify_tables();
            
            if (WP_DEBUG) {
                error_log('Assessment manager initialized');
            }
        } catch (Exception $e) {
            error_log('Error initializing assessment manager: ' . $e->getMessage());
            throw $e;
        }

        if (WP_DEBUG) {
            error_log('All managers initialized successfully');
        }

        // Create required pages
        assessment_360_create_pages();

        if (WP_DEBUG) {
            error_log('Plugin activation completed successfully');
        }

    } catch (Exception $e) {
        error_log('Fatal error during plugin activation: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        
        // Deactivate the plugin
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        deactivate_plugins(plugin_basename(__FILE__));
        
        wp_die(
            'Error activating plugin: ' . esc_html($e->getMessage()) . 
            '<br><br><a href="' . admin_url('plugins.php') . '">&laquo; Return to Plugins</a>'
        );
    }
}

// Register custom endpoints
add_action('init', 'assessment_360_register_endpoints');
function assessment_360_register_endpoints() {
    add_rewrite_rule(
        'assessment-form/?$',
        'index.php?assessment_360_form=1',
        'top'
    );
    
    add_rewrite_tag('%assessment_360_form%', '1');
}

// Add query vars
add_filter('query_vars', 'assessment_360_query_vars');
function assessment_360_query_vars($query_vars) {
    $query_vars[] = 'assessment_360_form';
    $query_vars[] = 'assessment_id';
    $query_vars[] = 'assessee_id';
    $query_vars[] = 'self_assessment';
    return $query_vars;
}

// Template redirect
add_action('template_redirect', 'assessment_360_template_redirect');
function assessment_360_template_redirect() {
    if (get_query_var('assessment_360_form')) {
        if (WP_DEBUG) {
            error_log('Loading assessment form template');
            error_log('GET parameters: ' . print_r($_GET, true));
        }

        // Update path to correct template file
        $template = ASSESSMENT_360_PLUGIN_DIR . 'templates/page-assessment-form.php';
        
        if (file_exists($template)) {
            include $template;
            exit;
        } else {
            if (WP_DEBUG) {
                error_log('Assessment form template not found: ' . $template);
            }
        }
    }
}

// Helper function to generate assessment form URL
function assessment_360_get_form_url($assessment_id, $assessee_id, $is_self = false) {
    if (WP_DEBUG) {
        error_log("Generating form URL:");
        error_log("Assessment ID: $assessment_id");
        error_log("Assessee ID: $assessee_id");
        error_log("Is Self: " . ($is_self ? 'Yes' : 'No'));
    }

    $url = home_url('/assessment-form/');
    
    $params = array(
        'assessment_id' => $assessment_id,
        'assessee_id' => $assessee_id,
        'self_assessment' => $is_self ? '1' : '0'
    );
    
    return add_query_arg($params, $url);
}

// Flush rewrite rules on plugin activation
register_activation_hook(__FILE__, 'assessment_360_flush_rewrite_rules');
function assessment_360_flush_rewrite_rules() {
    assessment_360_register_endpoints();
    flush_rewrite_rules();
}

// Flush rewrite rules on plugin deactivation
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');


add_action('init', function() {
    if (WP_DEBUG && isset($_SERVER['REQUEST_URI'])) {
        error_log('Current Request: ' . $_SERVER['REQUEST_URI']);
        error_log('Query Vars: ' . print_r($GLOBALS['wp_query']->query_vars, true));
    }
});


// Generate assessment URL
function assessment_360_get_assessment_url($assessment_id, $assessee_id, $self_assessment = false) {
    $url = home_url('/assessment-form/');
    
    $params = array(
        'assessment_id' => $assessment_id,
        'assessee_id' => $assessee_id,
        'self_assessment' => $self_assessment ? 1 : 0
    );
    
    return add_query_arg($params, $url);
}

// Create admin menu structure
add_action('admin_menu', 'assessment_360_admin_menu');
function assessment_360_admin_menu() {
    // Main menu
    add_menu_page(
        '360° Assessments',
        '360° Assessments',
        'manage_options',
        'assessment-360',
        'assessment_360_dashboard_page',
        'dashicons-groups',
        30
    );
    
    // Submenus
    $submenus = array(
        'assessment-360-assessments' => array(
            'title' => 'Assessments',
            'callback' => 'assessment_360_assessments_page'
        ),
        'assessment-360-users' => array(
            'title' => 'Users',
            'callback' => 'assessment_360_users_page'
        ),
        'assessment-360-positions' => array(
            'title' => 'Positions',
            'callback' => 'assessment_360_positions_page'
        ),
        'assessment-360-groups' => array(
            'title' => 'User Groups',
            'callback' => 'assessment_360_groups_page'
        ),
        'assessment-360-forms' => array(
            'title' => 'Forms',
            'callback' => 'assessment_360_forms_page'
        ),
        'assessment-360-results' => array(
            'title' => 'Results',
            'callback' => 'assessment_360_results_page'
        ),
        'assessment-360-email-templates' => array(
            'title' => 'Send Emails',
            'callback' => 'assessment_360_email_templates_page'
        ),
        'assessment-360-settings' => array(
            'title' => 'Settings',
            'callback' => 'assessment_360_settings_page'
        )
        
    );
    
    foreach ($submenus as $slug => $menu) {
        add_submenu_page(
            'assessment-360', // Parent slug
            $menu['title'], // Page title
            $menu['title'], // Menu title
            'manage_options', // Capability
            $slug, // Menu slug
            $menu['callback'] // Function to display the page
        );
    }
}

// Add temporary debug button to admin
add_action('admin_menu', function() {
    if (WP_DEBUG) {
        add_submenu_page(
            'assessment-360',
            'Clear Sessions',
            'Clear Sessions',
            'manage_options',
            'assessment-360-clear-sessions',
            function() {
                assessment_360_clear_all_sessions();
                echo '<div class="notice notice-success"><p>All sessions cleared.</p></div>';
            }
        );
    }
});

// Page callbacks
function assessment_360_dashboard_page() {
    if (is_admin()) {
        try {
            $assessment_manager = Assessment_360_Assessment_Manager::get_instance();
            $user_manager = Assessment_360_User_Manager::get_instance();
            
            if (WP_DEBUG) {
                error_log('Loading admin dashboard');
            }

            // Get dashboard data with error handling
            try {
                $active_assessments_count = $assessment_manager->get_active_assessments_count();
            } catch (Exception $e) {
                if (WP_DEBUG) {
                    error_log('Error getting active assessments count: ' . $e->getMessage());
                }
                $active_assessments_count = 0;
            }

            try {
                $total_users_count = $user_manager->get_total_users_count();
            } catch (Exception $e) {
                if (WP_DEBUG) {
                    error_log('Error getting total users count: ' . $e->getMessage());
                }
                $total_users_count = 0;
            }

            try {
                $overall_completion_rate = $assessment_manager->get_overall_completion_rate();
            } catch (Exception $e) {
                if (WP_DEBUG) {
                    error_log('Error getting overall completion rate: ' . $e->getMessage());
                }
                $overall_completion_rate = 0;
            }

            try {
                $current_assessment = $assessment_manager->get_current_assessment();
                if ($current_assessment) {
                    $completion_stats = $assessment_manager->get_assessment_completion_stats($current_assessment->id);
                    $current_assessment->completion_rate = $completion_stats ? $completion_stats->completion_rate : 0;
                }
            } catch (Exception $e) {
                if (WP_DEBUG) {
                    error_log('Error getting current assessment: ' . $e->getMessage());
                }
                $current_assessment = null;
            }

            // Get additional statistics with error handling
            try {
                $user_stats = $user_manager->get_user_stats();
                if (WP_DEBUG) {
                    error_log('User stats loaded: ' . print_r($user_stats, true));
                }
            } catch (Exception $e) {
                if (WP_DEBUG) {
                    error_log('Error getting user stats: ' . $e->getMessage());
                }
                $user_stats = array(
                    'active' => 0,
                    'new_last_30_days' => 0,
                    'active_percentage' => 0
                );
            }

            try {
                // Explicitly request counts as per existing functionality
                $users_by_group = $user_manager->get_users_by_group(true);
                if (WP_DEBUG) {
                    error_log('Users by group loaded: ' . count($users_by_group) . ' groups found');
                }
            } catch (Exception $e) {
                if (WP_DEBUG) {
                    error_log('Error getting users by group: ' . $e->getMessage());
                }
                $users_by_group = array();
            }

            try {
                $recent_users = $user_manager->get_recent_users(5);
                if (WP_DEBUG) {
                    error_log('Recent users loaded: ' . count($recent_users) . ' users found');
                }
            } catch (Exception $e) {
                if (WP_DEBUG) {
                    error_log('Error getting recent users: ' . $e->getMessage());
                }
                $recent_users = array();
            }

            if (WP_DEBUG) {
                error_log('Dashboard data loaded successfully');
            }

            require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/dashboard.php';

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Critical error loading dashboard: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
            }
            wp_die('Error loading dashboard: ' . esc_html($e->getMessage()));
        }
    } else {
        if (WP_DEBUG) {
            error_log('Unauthorized access attempt to dashboard');
        }
        wp_die('Unauthorized access');
    }
}

function assessment_360_users_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    try {
        if (WP_DEBUG) {
            error_log('Starting users page load');
            error_log('REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
            error_log('GET parameters: ' . print_r($_GET, true));
        }

        // Load template with error handling
        $template_path = ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/users.php';
        
        if (!file_exists($template_path)) {
            throw new Exception('Template file not found: ' . $template_path);
        }

        if (WP_DEBUG) {
            error_log('Loading template: ' . $template_path);
        }

        // Start output buffering
        ob_start();
        
        // Include template
        include_once $template_path;
        
        // Get output
        $output = ob_get_clean();
        
        if (WP_DEBUG) {
            error_log('Template loaded successfully');
        }

        // Echo output
        echo $output;

    } catch (Exception $e) {
        if (WP_DEBUG) {
            error_log('Error in users page: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
        }
        
        // Clean any output
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        wp_die('Error loading page: ' . esc_html($e->getMessage()));
    }
}

function assessment_360_positions_page() {
    require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/positions.php';
}

function assessment_360_groups_page() {
    require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/user-groups.php';
}

function assessment_360_assessments_page() {
    require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/assessments.php';
}

//function assessment_360_user_management_page() {
//    require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/user-management.php';
//}

//function assessment_360_topics_page() {
//    require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/topics.php';
//}
//
//function assessment_360_sections_page() {
//    require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/sections.php';
//}
//
//function assessment_360_questions_page() {
//    require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/questions.php';
//}

function assessment_360_results_page() {
    require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/results.php';
}

function assessment_360_email_templates_page() {
    require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/email-templates.php';
}

function assessment_360_settings_page() {
    require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/settings.php';
}

function assessment_360_forms_page() {
    require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/forms.php';
}

add_action('admin_enqueue_scripts', 'assessment_360_admin_scripts');
function assessment_360_admin_scripts($hook) {
    if (strpos($hook, 'assessment-360') !== false) {
        wp_enqueue_script('assessment-360-admin', ASSESSMENT_360_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), ASSESSMENT_360_VERSION, true);
        
        wp_localize_script('assessment-360-admin', 'assessment360Ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonces' => array(
                'form_preview' => wp_create_nonce('assessment_360_form_preview'),
                'section_load' => wp_create_nonce('assessment_360_section_nonce'),
                'section_save' => wp_create_nonce('assessment_360_section_nonce'),
                'topic_save' => wp_create_nonce('assessment_360_topic_nonce'),
                'question_save' => wp_create_nonce('assessment_360_question_nonce')
            )
        ));
    }
}

function assessment_360_admin_enqueue_scripts($hook) {
    if (strpos($hook, 'assessment-360') !== false) {
        // Bootstrap CSS
        wp_enqueue_style(
            'bootstrap',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
            array(),
            '5.3.2'
        );

        // Bootstrap JavaScript and Popper.js
        wp_enqueue_script(
            'bootstrap-bundle',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js',
            array('jquery'),
            '5.3.2',
            true
        );

        // Your existing plugin styles and scripts
        wp_enqueue_style(
            'assessment-360-admin',
            ASSESSMENT_360_PLUGIN_URL . 'admin/css/admin-style.css',
            array('bootstrap'),
            ASSESSMENT_360_VERSION
        );
    }
}
add_action('admin_enqueue_scripts', 'assessment_360_admin_enqueue_scripts');


// Add admin assets
add_action('admin_enqueue_scripts', 'assessment_360_admin_assets');
function assessment_360_admin_assets($hook) {
    // Only load on plugin pages
    if (strpos($hook, 'assessment-360') === false) {
        return;
    }

    // Enqueue admin CSS
    wp_enqueue_style(
        'assessment-360-admin',
        ASSESSMENT_360_PLUGIN_URL . 'admin/css/admin-style.css',
        array(),
        ASSESSMENT_360_VERSION
    );

    // Enqueue admin JavaScript
    wp_enqueue_script(
        'assessment-360-admin',
        ASSESSMENT_360_PLUGIN_URL . 'admin/js/admin.js',
        array('jquery'),
        ASSESSMENT_360_VERSION,
        true
    );

    // Add AJAX URL and nonce
    wp_localize_script(
        'assessment-360-admin',
        'assessment360Ajax',
        array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('assessment_360_form_preview')
        )
    );
    
    wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'));

}

function assessment_360_register_public_assets() {
    // Register CSS
    wp_register_style(
        'assessment-360-form',
        ASSESSMENT_360_PLUGIN_URL . 'public/css/form.css',
        array(),
        ASSESSMENT_360_VERSION
    );

    // Register JavaScript
    wp_register_script(
        'assessment-360-form',
        ASSESSMENT_360_PLUGIN_URL . 'public/js/assessment-form.js',
        array('jquery'),
        ASSESSMENT_360_VERSION,
        true
    );
}
add_action('init', 'assessment_360_register_public_assets');

// In your main plugin file
function assessment_360_asset_version($file) {
    if (WP_DEBUG) {
        return filemtime(ASSESSMENT_360_PLUGIN_DIR . $file);
    }
    return ASSESSMENT_360_VERSION;
}

// Plugin deactivation
register_deactivation_hook(__FILE__, 'assessment_360_deactivate');
function assessment_360_deactivate() {
    // Cleanup tasks
    flush_rewrite_rules();
}

// Plugin uninstall
register_uninstall_hook(__FILE__, 'assessment_360_uninstall');
function assessment_360_uninstall() {
    if (get_option('assessment_360_allow_uninstall')) {
        Assessment_360_User_Manager::get_instance()->remove_tables();
        Assessment_360_Group_Manager::get_instance()->remove_tables();
        Assessment_360_Assessment_Manager::get_instance()->remove_tables();
        Assessment_360_Settings_Manager::get_instance()->remove_settings();
    }
}

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && $error['type'] === E_ERROR) {
        if (WP_DEBUG) {
            error_log('Fatal error in 360 Assessments plugin: ' . print_r($error, true));
        }
        
        if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'assessment-360-users') {
            wp_die(
                'An error occurred while loading the page. Please check the error logs for details.',
                'Error',
                ['back_link' => true]
            );
        }
    }
});

// Add form handling actions
add_action('admin_post_save_topic', 'handle_save_topic');
add_action('admin_post_save_section', 'handle_save_section');
add_action('admin_post_save_question', 'handle_save_question');
add_action('admin_init', 'handle_form_deletions');

// Add to your main plugin file (360-degree-assessments.php)
add_action('admin_post_update_user_status', 'assessment_360_update_user_status');
add_action('admin_post_delete_user', 'assessment_360_delete_user');

function assessment_360_update_user_status() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // Verify nonce
    $nonce = $_REQUEST['_wpnonce'] ?? '';
    $user_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
    
    if (!wp_verify_nonce($nonce, 'user-status_' . $user_id)) {
        wp_die('Invalid security token');
    }

    $action = $_REQUEST['status_action'] ?? '';
    $current_tab = $_REQUEST['tab'] ?? 'users';
    $current_status = $_REQUEST['current_status'] ?? 'active';

    $user_manager = Assessment_360_User_Manager::get_instance();
    $new_status = ($action === 'enable') ? 'active' : 'inactive';
    
    $result = $user_manager->update_user_status($user_id, $new_status);

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => $current_tab,
            'status' => $current_status,
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
    } else {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => $current_tab,
            'status' => $current_status,
            'message' => urlencode('User status updated successfully')
        ], admin_url('admin.php')));
    }
    exit;
}

function assessment_360_delete_user() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // Verify nonce
    $nonce = $_REQUEST['_wpnonce'] ?? '';
    $user_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
    
    if (!wp_verify_nonce($nonce, 'delete_user_' . $user_id)) {
        wp_die('Invalid security token');
    }

    $current_tab = $_REQUEST['tab'] ?? 'users';
    $current_status = $_REQUEST['current_status'] ?? 'active';

    $user_manager = Assessment_360_User_Manager::get_instance();
    $result = $user_manager->delete_user($user_id);

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => $current_tab,
            'status' => $current_status,
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
    } else {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => $current_tab,
            'status' => $current_status,
            'message' => urlencode('User deleted successfully')
        ], admin_url('admin.php')));
    }
    exit;
}

// Add action handlers
add_action('admin_post_bulk_action_users', 'handle_bulk_action_users');
add_action('admin_post_enable_user', 'handle_enable_user');
add_action('admin_post_disable_user', 'handle_disable_user');
add_action('admin_post_delete_user', 'handle_delete_user');

function handle_user_actions() {
    static $already_run = false;
    
    // Prevent multiple executions
    if ($already_run) {
        return;
    }
    
    if (!isset($_GET['page']) || $_GET['page'] !== 'assessment-360-users') {
        return;
    }

    if (WP_DEBUG) {
        error_log('Starting user action handler');
        error_log('REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
        error_log('Request method: ' . $_SERVER['REQUEST_METHOD']);
    }

    if (!current_user_can('manage_options')) {
        if (WP_DEBUG) {
            error_log('User Management: Unauthorized access attempt');
        }
        wp_die('Unauthorized');
    }

    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $current_status = isset($_GET['status']) ? $_GET['status'] : 'active';

    if (WP_DEBUG) {
        error_log("Processing action: $action");
        error_log("User ID: $user_id");
        error_log("Current Status: $current_status");
    }

    // Edit and New actions should be handled by the template
    if ($action === 'edit' || $action === 'new') {
        $already_run = true;
        return;
    }

    // Return early if no action or no user ID for other actions
    if (!$action || !$user_id) {
        $already_run = true;
        return;
    }

    try {
        $user_manager = Assessment_360_User_Manager::get_instance();
        $redirect_args = array(
            'page' => 'assessment-360-users',
            'status' => $current_status
        );

        switch ($action) {
            case 'disable_user':
            case 'enable_user':
                if (!wp_verify_nonce($_GET['_wpnonce'], 'user_status_' . $user_id)) {
                    throw new Exception('Invalid security token');
                }
                
                $new_status = ($action === 'disable_user') ? 'inactive' : 'active';
                
                if (WP_DEBUG) {
                    error_log("Attempting to change user status: User ID = $user_id, New Status = $new_status");
                }
                
                $result = $user_manager->update_user_status($user_id, $new_status);
                if (is_wp_error($result)) {
                    throw new Exception($result->get_error_message());
                }
                
                $redirect_args['message'] = 'User ' . ($new_status === 'active' ? 'enabled' : 'disabled') . ' successfully.';
                break;

            case 'delete_user':
                if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_user_' . $user_id)) {
                    throw new Exception('Invalid security token');
                }
                
                if (WP_DEBUG) {
                    error_log('Attempting to delete user: ' . $user_id);
                }
                
                $result = $user_manager->delete_user($user_id);
                if (is_wp_error($result)) {
                    throw new Exception($result->get_error_message());
                }
                
                $redirect_args['message'] = 'User deleted successfully.';
                break;

            default:
                if (WP_DEBUG) {
                    error_log('Unknown action requested: ' . $action);
                }
                return;
        }

        if (WP_DEBUG) {
            error_log('Action completed successfully. Redirecting...');
            error_log('Redirect args: ' . print_r($redirect_args, true));
        }

        $already_run = true;
        wp_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;

    } catch (Exception $e) {
        if (WP_DEBUG) {
            error_log('Error in user action handler: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
        }

        $already_run = true;
        wp_redirect(add_query_arg(
            array(
                'page' => 'assessment-360-users',
                'status' => $current_status,
                'error' => urlencode($e->getMessage())
            ),
            admin_url('admin.php')
        ));
        exit;
    }
}

// Add this line to register the action handler
add_action('init', 'handle_user_actions', 10);

add_action('admin_post_save_user', 'handle_save_user');

function handle_save_user() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // Verify nonce
    if (!isset($_POST['save_user_nonce']) || !wp_verify_nonce($_POST['save_user_nonce'], 'save_user_nonce')) {
        if (WP_DEBUG) {
            error_log('User save failed: Invalid nonce');
        }
        wp_die('Invalid security token sent. Please try again.');
    }

    try {
        $user_manager = Assessment_360_User_Manager::get_instance();
        
        // Sanitize and validate input data
        $data = array(
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'email' => sanitize_email($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'group_id' => intval($_POST['group_id']),
            'position_id' => !empty($_POST['position_id']) ? intval($_POST['position_id']) : null,
            'status' => 'active'
        );

        if (WP_DEBUG) {
            error_log('Processing user save with data: ' . print_r($data, true));
        }

        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'email'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new Exception("$field is required");
            }
        }

        // Validate email format
        if (!is_email($data['email'])) {
            throw new Exception('Invalid email address');
        }

        // Create or update user
        if (isset($_POST['id'])) {
            $user_id = intval($_POST['id']);
            $result = $user_manager->update_user($user_id, $data);
            $message = 'User updated successfully';
            
            if (WP_DEBUG) {
                error_log("Updating user ID: $user_id");
            }
        } else {
            // Generate random password for new user
            $data['password'] = wp_generate_password(12, true, true);
            $result = $user_manager->create_user($data);
            $message = 'User created successfully';
            $user_id = $result;
            
            if (WP_DEBUG) {
                error_log("Created new user with ID: $user_id");
            }
        }

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        // Handle assessors if present
        if (isset($_POST['assessors']) && is_array($_POST['assessors'])) {
            $assessors = array_map('intval', $_POST['assessors']);
            
            if (WP_DEBUG) {
                error_log("Updating assessors for user $user_id: " . print_r($assessors, true));
            }
            
            $result = $user_manager->update_user_assessors($user_id, $assessors);
            
            if (is_wp_error($result)) {
                throw new Exception('Failed to update assessors: ' . $result->get_error_message());
            }
        }

        // Redirect with success message
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-users',
            'message' => urlencode($message)
        ], admin_url('admin.php')));
        exit;

    } catch (Exception $e) {
        if (WP_DEBUG) {
            error_log('Error saving user: ' . $e->getMessage());
        }

        wp_redirect(add_query_arg([
            'page' => 'assessment-360-users',
            'action' => isset($_POST['id']) ? 'edit' : 'new',
            'id' => isset($_POST['id']) ? $_POST['id'] : '',
            'error' => urlencode($e->getMessage())
        ], admin_url('admin.php')));
        exit;
    }
}

function test_user_management_db() {
    static $already_run = false;
    
    if ($already_run || !WP_DEBUG || !isset($_GET['page']) || $_GET['page'] !== 'assessment-360-users') {
        return;
    }
    
    $already_run = true;
    global $wpdb;
    
    error_log('Running database tests...');
    error_log('Database prefix: ' . $wpdb->prefix);
    
    // Test users table
    $users_table = $wpdb->prefix . '360_users';
    $test_query = "SHOW TABLES LIKE '$users_table'";
    $result = $wpdb->get_var($test_query);
    
    error_log('Users table exists: ' . ($result === $users_table ? 'Yes' : 'No'));
    
    if ($result === $users_table) {
        // Test table structure
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $users_table");
        //error_log('Table structure:');
        //error_log(print_r($columns, true));
        
        // Test user count
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $users_table");
        error_log('Total users: ' . $count);
        
        // Test status counts
        $active = $wpdb->get_var("SELECT COUNT(*) FROM $users_table WHERE status = 'active'");
        $inactive = $wpdb->get_var("SELECT COUNT(*) FROM $users_table WHERE status = 'inactive'");
        error_log("Active users: $active");
        error_log("Inactive users: $inactive");
    }
}
add_action('admin_init', 'test_user_management_db');


function handle_bulk_action_users() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('bulk_action_users', 'bulk_action_nonce');

    $action = isset($_POST['bulk-action']) ? $_POST['bulk-action'] : '';
    $users = isset($_POST['users']) ? array_map('intval', $_POST['users']) : array();
    $current_status = isset($_POST['status']) ? $_POST['status'] : 'active';

    if (empty($action) || empty($users)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-users',
            'status' => $current_status,
            'error' => 'Please select users and an action.'
        ], admin_url('admin.php')));
        exit;
    }

    $user_manager = Assessment_360_User_Manager::get_instance();
    $success_count = 0;
    $error_count = 0;

    foreach ($users as $user_id) {
        switch ($action) {
            case 'enable':
                $result = $user_manager->update_user_status($user_id, 'active');
                break;
            case 'disable':
                $result = $user_manager->update_user_status($user_id, 'inactive');
                break;
            case 'delete':
                $result = $user_manager->delete_user($user_id);
                break;
            default:
                $result = false;
        }

        if ($result === true) {
            $success_count++;
        } else {
            $error_count++;
        }
    }

    $message = '';
    if ($success_count > 0) {
        $message .= sprintf('%d user(s) processed successfully. ', $success_count);
    }
    if ($error_count > 0) {
        $message .= sprintf('%d user(s) failed to process.', $error_count);
    }

    wp_redirect(add_query_arg([
        'page' => 'assessment-360-users',
        'status' => $current_status,
        'message' => $message
    ], admin_url('admin.php')));
    exit;
}

function handle_enable_user() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    check_admin_referer('user_status_' . $user_id);

    $user_manager = Assessment_360_User_Manager::get_instance();
    $result = $user_manager->update_user_status($user_id, 'active');

    $current_status = isset($_GET['status']) ? $_GET['status'] : 'inactive';

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-users',
            'status' => $current_status,
            'error' => $result->get_error_message()
        ], admin_url('admin.php')));
    } else {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-users',
            'status' => $current_status,
            'message' => 'User enabled successfully.'
        ], admin_url('admin.php')));
    }
    exit;
}

function handle_disable_user() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    check_admin_referer('user_status_' . $user_id);

    $user_manager = Assessment_360_User_Manager::get_instance();
    $result = $user_manager->update_user_status($user_id, 'inactive');

    $current_status = isset($_GET['status']) ? $_GET['status'] : 'active';

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-users',
            'status' => $current_status,
            'error' => $result->get_error_message()
        ], admin_url('admin.php')));
    } else {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-users',
            'status' => $current_status,
            'message' => 'User disabled successfully.'
        ], admin_url('admin.php')));
    }
    exit;
}

function handle_delete_user() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    check_admin_referer('delete_user_' . $user_id);

    $user_manager = Assessment_360_User_Manager::get_instance();
    $result = $user_manager->delete_user($user_id);

    $current_status = isset($_GET['status']) ? $_GET['status'] : 'active';

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-users',
            'status' => $current_status,
            'error' => $result->get_error_message()
        ], admin_url('admin.php')));
    } else {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-users',
            'status' => $current_status,
            'message' => 'User deleted successfully.'
        ], admin_url('admin.php')));
    }
    exit;
}

function handle_save_topic() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('save_topic_nonce');

    $topic_manager = Assessment_360_Topic::get_instance();
    $data = array(
        'name' => sanitize_text_field($_POST['topic_name'])
    );

    if (empty($data['name'])) {
        wp_redirect(add_query_arg('error', 'Topic name is required.', wp_get_referer()));
        exit;
    }

    if (isset($_POST['id'])) {
        $result = $topic_manager->update_topic(intval($_POST['id']), $data);
        $message = 'Topic updated successfully.';
    } else {
        $result = $topic_manager->create_topic($data);
        $message = 'Topic created successfully.';
    }

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg('error', $result->get_error_message(), wp_get_referer()));
    } else {
        wp_redirect(add_query_arg('message', $message, admin_url('admin.php?page=assessment-360-forms#topics')));
    }
    exit;
}

function handle_save_section() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('save_section_nonce');

    $section_manager = Assessment_360_Section::get_instance();
    $data = array(
        'name' => sanitize_text_field($_POST['section_name']),
        'topic_id' => intval($_POST['topic_id']),
        'position_ids' => isset($_POST['position_ids']) ? array_map('intval', $_POST['position_ids']) : []
    );

    if (empty($data['name']) || empty($data['topic_id']) || empty($data['position_ids'])) {
        wp_redirect(add_query_arg('error', 'All fields are required.', wp_get_referer()));
        exit;
    }

    if (isset($_POST['id'])) {
        $result = $section_manager->update_section(intval($_POST['id']), $data);
        $message = 'Section updated successfully.';
    } else {
        $result = $section_manager->create_section($data);
        $message = 'Section created successfully.';
    }

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg('error', $result->get_error_message(), wp_get_referer()));
    } else {
        wp_redirect(add_query_arg('message', $message, admin_url('admin.php?page=assessment-360-forms#sections')));
    }
    exit;
}

function handle_save_question() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('save_question_nonce');

    // Debug log
    if (WP_DEBUG) {
        error_log('POST data received: ' . print_r($_POST, true));
    }

    // Validate required fields
    $required_fields = array('question_text', 'section_id', 'position_id');
    $missing_fields = array();

    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }

    if (!empty($missing_fields)) {
        if (WP_DEBUG) {
            error_log('Missing required fields: ' . print_r($missing_fields, true));
        }
        wp_redirect(add_query_arg(
            'error',
            'Missing required fields: ' . implode(', ', $missing_fields),
            wp_get_referer()
        ));
        exit;
    }

    $question_manager = Assessment_360_Question::get_instance();
    
    // Prepare the data
    $data = array(
        'question_text' => sanitize_textarea_field($_POST['question_text']),
        'section_id' => intval($_POST['section_id']),
        'position_id' => intval($_POST['position_id']),
        'is_mandatory' => isset($_POST['is_mandatory']) ? 1 : 0,
        'has_comment_box' => isset($_POST['has_comment_box']) ? 1 : 0
    );

    if (WP_DEBUG) {
        error_log('Processed question data: ' . print_r($data, true));
    }

    // Validate data
    if (empty($data['question_text'])) {
        wp_redirect(add_query_arg('error', 'Question text is required.', wp_get_referer()));
        exit;
    }

    if ($data['section_id'] <= 0) {
        wp_redirect(add_query_arg('error', 'Valid section is required.', wp_get_referer()));
        exit;
    }

    if ($data['position_id'] <= 0) {
        wp_redirect(add_query_arg('error', 'Valid position is required.', wp_get_referer()));
        exit;
    }

    try {
        if (isset($_POST['id'])) {
            $result = $question_manager->update_question(intval($_POST['id']), $data);
            $message = 'Question updated successfully.';
        } else {
            $result = $question_manager->create_question($data);
            $message = 'Question created successfully.';
        }

        if (is_wp_error($result)) {
            if (WP_DEBUG) {
                error_log('Error saving question: ' . $result->get_error_message());
            }
            wp_redirect(add_query_arg('error', $result->get_error_message(), wp_get_referer()));
        } else {
            wp_redirect(add_query_arg(
                'message',
                $message,
                admin_url('admin.php?page=assessment-360-forms#questions')
            ));
        }
    } catch (Exception $e) {
        if (WP_DEBUG) {
            error_log('Exception saving question: ' . $e->getMessage());
        }
        wp_redirect(add_query_arg('error', 'Error saving question: ' . $e->getMessage(), wp_get_referer()));
    }
    exit;
}

function handle_form_deletions() {
    if (!isset($_GET['action']) || !isset($_GET['page']) || $_GET['page'] !== 'assessment-360-forms') {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $action = $_GET['action'];
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if (!$id) {
        return;
    }

    switch ($action) {
        case 'delete_topic':
            if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_topic_' . $id)) {
                wp_die('Security check failed');
            }
            
            $result = Assessment_360_Topic::get_instance()->delete_topic($id);
            $redirect = 'topics';
            break;

        case 'delete_section':
            if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_section_' . $id)) {
                wp_die('Security check failed');
            }
            
            $result = Assessment_360_Section::get_instance()->delete_section($id);
            $redirect = 'sections';
            break;

        case 'delete_question':
            if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_question_' . $id)) {
                wp_die('Security check failed');
            }
            
            $result = Assessment_360_Question::get_instance()->delete_question($id);
            $redirect = 'questions';
            break;

        default:
            return;
    }

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg(
            'error', 
            $result->get_error_message(), 
            admin_url("admin.php?page=assessment-360-forms#{$redirect}")
        ));
    } else {
        wp_redirect(add_query_arg(
            'message', 
            ucfirst(substr($action, 7)) . ' deleted successfully.', 
            admin_url("admin.php?page=assessment-360-forms#{$redirect}")
        ));
    }
    exit;
}



function assessment_360_clear_all_sessions() {
    if (!current_user_can('manage_options')) return;
    
    // Clear session data
    if (session_id()) {
        session_destroy();
    }
    $_SESSION = array();
    
    // Clear cookies
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Clear all cookies
    if (isset($_SERVER['HTTP_COOKIE'])) {
        $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
        foreach($cookies as $cookie) {
            $parts = explode('=', $cookie);
            $name = trim($parts[0]);
            setcookie($name, '', time() - 3600, '/');
        }
    }
}

// Session handling
function assessment_360_start_session() {
    if (!session_id() && !headers_sent()) {
        session_start();
    }
}
add_action('init', 'assessment_360_start_session', 1);

// Session verification
function assessment_360_verify_session() {
    $is_login_page = is_page('360-assessment-login');
    
    if (WP_DEBUG) {
        error_log('Verifying session...');
        error_log('Is login page: ' . ($is_login_page ? 'Yes' : 'No'));
        error_log('Session user ID: ' . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not set'));
    }

    if ($is_login_page && isset($_SESSION['user_id'])) {
        wp_redirect(home_url('/360-assessment-dashboard/'));
        exit;
    }

    if (!$is_login_page && !isset($_SESSION['user_id'])) {
        wp_redirect(home_url('/360-assessment-login/'));
        exit;
    }

    return isset($_SESSION['user_id']);
}

// Login handling
function assessment_360_handle_login() {
    if (!isset($_POST['action']) || $_POST['action'] !== 'assessment_360_login') {
        return;
    }

    if (WP_DEBUG) {
        error_log('Processing login...');
    }

    try {
        if (!isset($_POST['login_nonce']) || 
            !wp_verify_nonce($_POST['login_nonce'], 'assessment_360_login')) {
            throw new Exception('Invalid security token');
        }

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($email) || empty($password)) {
            throw new Exception('Please enter both email and password');
        }

        $user_manager = Assessment_360_User_Manager::get_instance();
        $user = $user_manager->verify_login($email, $password);

        if (is_wp_error($user)) {
            throw new Exception($user->get_error_message());
        }

        assessment_360_start_session();
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['login_time'] = time();

        if (WP_DEBUG) {
            error_log('Login successful for user: ' . $user->email);
        }

        wp_redirect(home_url('/360-assessment-dashboard/'));
        exit;

    } catch (Exception $e) {
        if (WP_DEBUG) {
            error_log('Login error: ' . $e->getMessage());
        }

        wp_redirect(add_query_arg(
            'error',
            urlencode($e->getMessage()),
            home_url('/360-assessment-login/')
        ));
        exit;
    }
}
add_action('init', 'assessment_360_handle_login');

// Logout handling
function assessment_360_handle_logout() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'logout') {
        return;
    }

    if (WP_DEBUG) {
        error_log('Processing logout...');
    }

    if (!isset($_GET['_wpnonce']) || 
        !wp_verify_nonce($_GET['_wpnonce'], 'assessment_360_logout')) {
        wp_die('Invalid security token');
    }

    if (session_id()) {
        session_destroy();
    }
    
    $_SESSION = array();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    if (WP_DEBUG) {
        error_log('Logout successful');
    }

    wp_redirect(home_url('/360-assessment-login/'));
    exit;
}
add_action('init', 'assessment_360_handle_logout');


// Remove the admin_post action and add the init action instead
remove_action('admin_post_save_assessment', 'handle_save_assessment');
add_action('init', 'handle_save_assessment');

function handle_save_assessment() {
    // Only process if this is a form submission
    if (!isset($_POST['action']) || $_POST['action'] !== 'save_assessment') {
        return;
    }

    if (!current_user_can('manage_options')) {
        if (WP_DEBUG) {
            error_log('Save Assessment: Unauthorized access attempt');
        }
        wp_die('Unauthorized');
    }

    // Verify nonce
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'save_assessment_nonce')) {
        if (WP_DEBUG) {
            error_log('Save Assessment: Invalid nonce');
        }
        wp_die('Invalid security token');
    }

    if (WP_DEBUG) {
        error_log('Save Assessment: Starting save process');
        error_log('POST data received: ' . print_r($_POST, true));
    }

    try {
        $assessment_manager = Assessment_360_Assessment_Manager::get_instance();
        
        // Sanitize and validate input data
        $data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date' => sanitize_text_field($_POST['end_date'] ?? '')
        );

        if (WP_DEBUG) {
            error_log('Save Assessment: Sanitized data: ' . print_r($data, true));
        }

        // Validate required fields
        if (empty($data['name']) || empty($data['start_date']) || empty($data['end_date'])) {
            throw new Exception('Please fill in all required fields.');
        }

        // Validate dates
        $start_date = strtotime($data['start_date']);
        $end_date = strtotime($data['end_date']);
        $today = strtotime('today');

        if (!isset($_POST['id'])) { // Only check for new assessments
            if ($start_date < $today) {
                throw new Exception('Start date cannot be earlier than today.');
            }
        }
        
        if ($end_date < $start_date) {
            throw new Exception('End date cannot be earlier than start date.');
        }

        // Update or create assessment
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            $result = $assessment_manager->update_assessment(intval($_POST['id']), $data);
            $message = 'Assessment updated successfully';
        } else {
            $result = $assessment_manager->create_assessment($data);
            $message = 'Assessment created successfully';
        }

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        if (WP_DEBUG) {
            error_log('Save Assessment: Success - ' . $message);
        }

        // Redirect with success message
        wp_redirect(add_query_arg(
            'message',
            urlencode($message),
            admin_url('admin.php?page=assessment-360-assessments')
        ));
        exit;

    } catch (Exception $e) {
        if (WP_DEBUG) {
            error_log('Save Assessment Error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
        }

        // Redirect with error message
        wp_redirect(add_query_arg(
            'error',
            urlencode($e->getMessage()),
            admin_url('admin.php?page=assessment-360-assessments')
        ));
        exit;
    }
}


// Add custom template for login page
add_filter('template_include', 'assessment_360_login_template');

function assessment_360_login_template($template) {
    if (is_page('360-assessment-login')) {
        $new_template = ASSESSMENT_360_PLUGIN_DIR . 'templates/page-login.php';
        if (file_exists($new_template)) {
            return $new_template;
        }
    }
    return $template;
}

// Register custom pages on plugin activation
register_activation_hook(__FILE__, 'assessment_360_create_pages');

function assessment_360_create_pages() {
    // Create Login page if it doesn't exist
    if (!get_page_by_path('360-assessment-login')) {
        wp_insert_post(array(
            'post_title' => '360° Assessment Login',
            'post_name' => '360-assessment-login',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => ''
        ));
    }

    // Create Dashboard page if it doesn't exist
    if (!get_page_by_path('360-assessment-dashboard')) {
        wp_insert_post(array(
            'post_title' => '360° Assessment Dashboard',
            'post_name' => '360-assessment-dashboard',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => ''
        ));
    }
}

// Add custom template for dashboard page
add_filter('template_include', 'assessment_360_custom_templates');

function assessment_360_custom_templates($template) {
    if (is_page('360-assessment-dashboard')) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_redirect(home_url('/360-assessment-login/'));
            exit;
        }

        $new_template = ASSESSMENT_360_PLUGIN_DIR . 'templates/page-dashboard.php';
        if (file_exists($new_template)) {
            return $new_template;
        }
    }
    
//    if (is_page('360-assessment-login')) {
//        $new_template = ASSESSMENT_360_PLUGIN_DIR . 'templates/page-login.php';
//        if (file_exists($new_template)) {
//            return $new_template;
//        }
//    }
    
    return $template;
}

function handle_assessment_360_send_email() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('assessment_360_send_email', 'assessment_360_send_email_nonce');

    $template = isset($_POST['email_template']) ? $_POST['email_template'] : '';
    $recipients = isset($_POST['recipients']) ? array_map('intval', $_POST['recipients']) : array();

    if (empty($template) || empty($recipients)) {
        wp_redirect(add_query_arg('error', 'Missing required fields', wp_get_referer()));
        exit;
    }

    $user_manager = Assessment_360_User_Manager::get_instance();
    $success_count = 0;
    $error_count = 0;

    foreach ($recipients as $user_id) {
        $user = $user_manager->get_user($user_id);
        if (!$user) continue;

        // Get email content
        $subject = '';
        $body = '';
        
        if ($template === 'custom') {
            $subject = sanitize_text_field($_POST['custom_email_subject']);
            $body = wp_kses_post($_POST['custom_email_body']);
        } else {
            global $email_templates;
            if (!isset($email_templates[$template])) continue;
            
            $subject = $email_templates[$template]['subject'];
            $body = $email_templates[$template]['body'];
        }

        // Handle welcome email special case
        $new_password = '';
        if ($template === 'welcome') {
            // Reset user's password
            $new_password = $user_manager->reset_user_password($user_id);
            if (is_wp_error($new_password)) {
                $error_count++;
                continue;
            }
        }

        // Replace placeholders
        $replacements = array(
            '{first_name}' => $user->first_name,
            '{last_name}' => $user->last_name,
            '{email}' => $user->email,
            '{org_name}' => get_option('assessment_360_organization_name'),
            '{login_url}' => home_url('/360-assessment-login/'),
            '{new_password}' => $new_password
        );

        $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
        $body = str_replace(array_keys($replacements), array_values($replacements), $body);

        // Send email
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $sent = wp_mail($user->email, $subject, $body, $headers);

        if ($sent) {
            $success_count++;
        } else {
            $error_count++;
        }
    }

    // Redirect with results
    $message = sprintf(
        'Emails sent successfully to %d recipient(s).%s',
        $success_count,
        $error_count > 0 ? " Failed to send to $error_count recipient(s)." : ''
    );

    wp_redirect(add_query_arg(
        $error_count > 0 ? 'error' : 'message',
        urlencode($message),
        wp_get_referer()
    ));
    exit;
}
add_action('admin_post_assessment_360_send_email', 'handle_assessment_360_send_email');

// Add these action handlers
add_action('admin_post_update_user_status', 'handle_update_user_status');
function handle_update_user_status() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('user_status_' . $_POST['user_id']);

    $user_manager = Assessment_360_User_Manager::get_instance();
    $result = $user_manager->update_user_status(
        intval($_POST['user_id']),
        sanitize_text_field($_POST['status'])
    );

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg('error', urlencode($result->get_error_message()), wp_get_referer()));
    } else {
        wp_redirect(add_query_arg('message', 'User status updated successfully', wp_get_referer()));
    }
    exit;
}

//temporary
// Add this temporarily to check table structures
//add_action('admin_init', function() {
//    if (current_user_can('manage_options')) {
//        $user_manager = Assessment_360_User_Manager::get_instance();
//        $user_manager->verify_table_structure();
//        
//        // Test query
//        global $wpdb;
//        $test_query = "SELECT u.*, 
//                             p.name as position_name, 
//                             g.group_name as group_name
//                      FROM {$wpdb->prefix}360_users u
//                      LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id
//                      LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
//                      LIMIT 1";
//        
//        if (WP_DEBUG) {
//            error_log('Testing database connection and queries...');
//            
//            $result = $wpdb->get_results($test_query);
//            if ($wpdb->last_error) {
//                error_log('Test query error: ' . $wpdb->last_error);
//                error_log('Test query: ' . $test_query);
//            } else {
//                error_log('Database connection successful');
//                error_log('Found ' . count($result) . ' results');
//            }
//        }
//    }
//});
//temporary


add_action('admin_post_save_position', 'handle_save_position');
add_action('admin_post_delete_position', 'handle_delete_position');

function handle_save_position() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('save_position');

    $position_manager = Assessment_360_Position::get_instance();
    $data = array(
        'name' => sanitize_text_field($_POST['name']),
        'description' => sanitize_textarea_field($_POST['description'])
    );

    if (empty($data['name'])) {
        wp_redirect(add_query_arg('error', 'Position name is required.', wp_get_referer()));
        exit;
    }

    if (isset($_POST['id'])) {
        $result = $position_manager->update_position(intval($_POST['id']), $data);
        $message = 'Position updated successfully.';
    } else {
        $result = $position_manager->create_position($data);
        $message = 'Position created successfully.';
    }

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg('error', $result->get_error_message(), wp_get_referer()));
    } else {
        wp_redirect(add_query_arg('message', $message, admin_url('admin.php?page=assessment-360-positions')));
    }
    exit;
}

function handle_delete_position() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('delete_position_' . $_POST['id']);

    $position_manager = Assessment_360_Position::get_instance();
    $result = $position_manager->delete_position(intval($_POST['id']));

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg('error', $result->get_error_message(), wp_get_referer()));
    } else {
        wp_redirect(add_query_arg('message', 'Position deleted successfully.', admin_url('admin.php?page=assessment-360-positions')));
    }
    exit;
}

add_action('admin_post_restore_position', 'handle_restore_position');

function handle_restore_position() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('restore_position_' . $_POST['id']);

    $position_manager = Assessment_360_Position::get_instance();
    $result = $position_manager->restore_position(intval($_POST['id']));

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg('error', $result->get_error_message(), wp_get_referer()));
    } else {
        wp_redirect(add_query_arg('message', 'Position restored successfully.', wp_get_referer()));
    }
    exit;
}

function assessment_360_verify_db_connection() {
    global $wpdb;
    
    // Test database connection
    $test_query = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}360_users");
    
    if ($wpdb->last_error) {
        error_log('Database connection error: ' . $wpdb->last_error);
        return false;
    }
    
    error_log('Database connection successful. Found ' . $test_query . ' users.');
    return true;
}

add_action('admin_post_enable_assessment', 'handle_enable_assessment');
add_action('admin_post_disable_assessment', 'handle_disable_assessment');

function handle_enable_assessment() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $assessment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$assessment_id) {
        wp_die('Invalid assessment ID');
    }

    check_admin_referer('enable_assessment_' . $assessment_id);

    try {
        if (WP_DEBUG) {
            error_log("Handling enable assessment: ID = $assessment_id");
        }

        $assessment_manager = Assessment_360_Assessment_Manager::get_instance();
        $result = $assessment_manager->update_assessment_status($assessment_id, 'active');

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        wp_redirect(add_query_arg(
            'message',
            urlencode('Assessment enabled successfully'),
            admin_url('admin.php?page=assessment-360-assessments')
        ));
        exit;

    } catch (Exception $e) {
        if (WP_DEBUG) {
            error_log('Error enabling assessment: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
        }

        wp_redirect(add_query_arg(
            'error',
            urlencode($e->getMessage()),
            admin_url('admin.php?page=assessment-360-assessments')
        ));
        exit;
    }
}

function handle_disable_assessment() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $assessment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    check_admin_referer('disable_assessment_' . $assessment_id);

    try {
        $assessment_manager = Assessment_360_Assessment_Manager::get_instance();
        $result = $assessment_manager->update_assessment_status($assessment_id, 'inactive');

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        wp_redirect(add_query_arg(
            'message',
            urlencode('Assessment disabled successfully'),
            admin_url('admin.php?page=assessment-360-assessments')
        ));
        exit;

    } catch (Exception $e) {
        wp_redirect(add_query_arg(
            'error',
            urlencode($e->getMessage()),
            admin_url('admin.php?page=assessment-360-assessments')
        ));
        exit;
    }
}

// Remove old action if it exists
remove_action('admin_post_delete_assessment', 'handle_delete_assessment');

// Add the new action handler
add_action('admin_init', 'handle_delete_assessment');

function handle_delete_assessment() {
    // Only process if this is a delete assessment request
    if (!isset($_GET['action']) || $_GET['action'] !== 'delete_assessment') {
        return;
    }

    if (!current_user_can('manage_options')) {
        if (WP_DEBUG) {
            error_log('Delete Assessment: Unauthorized access attempt');
        }
        wp_die('Unauthorized');
    }

    $assessment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if (!$assessment_id) {
        if (WP_DEBUG) {
            error_log('Delete Assessment: No assessment ID provided');
        }
        wp_redirect(add_query_arg(
            'error',
            'No assessment ID provided',
            admin_url('admin.php?page=assessment-360-assessments')
        ));
        exit;
    }

    // Verify nonce
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_assessment_' . $assessment_id)) {
        if (WP_DEBUG) {
            error_log('Delete Assessment: Invalid nonce');
        }
        wp_die('Security check failed');
    }

    try {
        if (WP_DEBUG) {
            error_log("Delete Assessment: Attempting to delete assessment ID: $assessment_id");
        }

        $assessment_manager = Assessment_360_Assessment_Manager::get_instance();
        
        // Check if assessment exists and can be deleted
        $assessment = $assessment_manager->get_assessment($assessment_id);
        if (!$assessment) {
            throw new Exception('Assessment not found');
        }

        if ($assessment->status === 'active') {
            throw new Exception('Cannot delete an active assessment');
        }

        // Perform deletion
        $result = $assessment_manager->delete_assessment($assessment_id);

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        if (WP_DEBUG) {
            error_log("Delete Assessment: Successfully deleted assessment ID: $assessment_id");
        }

        // Redirect with success message
        wp_redirect(add_query_arg(
            'message',
            urlencode('Assessment deleted successfully'),
            admin_url('admin.php?page=assessment-360-assessments')
        ));
        exit;

    } catch (Exception $e) {
        if (WP_DEBUG) {
            error_log('Delete Assessment Error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
        }

        // Redirect with error message
        wp_redirect(add_query_arg(
            'error',
            urlencode($e->getMessage()),
            admin_url('admin.php?page=assessment-360-assessments')
        ));
        exit;
    }
}

add_action('admin_post_complete_assessment', 'handle_complete_assessment');

function handle_complete_assessment() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $assessment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$assessment_id) {
        wp_die('Invalid assessment ID');
    }

    check_admin_referer('complete_assessment_' . $assessment_id);

    try {
        if (WP_DEBUG) {
            error_log("Handling complete assessment request for ID: $assessment_id");
        }

        $assessment_manager = Assessment_360_Assessment_Manager::get_instance();
        
        // Get current assessment status
        $assessment = $assessment_manager->get_assessment($assessment_id);
        if (!$assessment) {
            throw new Exception('Assessment not found');
        }

        if ($assessment->status !== 'active') {
            throw new Exception('Only active assessments can be marked as completed');
        }

        $result = $assessment_manager->update_assessment_status($assessment_id, 'completed');

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        wp_redirect(add_query_arg(
            'message',
            urlencode('Assessment marked as completed successfully'),
            admin_url('admin.php?page=assessment-360-assessments')
        ));
        exit;

    } catch (Exception $e) {
        if (WP_DEBUG) {
            error_log('Error completing assessment: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
        }

        wp_redirect(add_query_arg(
            'error',
            urlencode($e->getMessage()),
            admin_url('admin.php?page=assessment-360-assessments')
        ));
        exit;
    }
}

// Add form submission handler
add_action('admin_post_submit_assessment', 'handle_assessment_submission');
add_action('admin_post_nopriv_submit_assessment', 'handle_assessment_submission');

function handle_assessment_submission() {
    if (!isset($_POST['assessment_nonce']) || 
        !wp_verify_nonce($_POST['assessment_nonce'], 'submit_assessment')) {
        wp_die('Invalid request');
    }

    try {
        // Validate required data
        $assessor_id = isset($_POST['assessor_id']) ? intval($_POST['assessor_id']) : 0;
        $assessee_id = isset($_POST['assessee_id']) ? intval($_POST['assessee_id']) : 0;
        $ratings = isset($_POST['ratings']) ? $_POST['ratings'] : array();
        $comments = isset($_POST['comments']) ? $_POST['comments'] : array();

        if (!$assessor_id || !$assessee_id) {
            throw new Exception('Invalid assessment data');
        }

        if (empty($ratings)) {
            throw new Exception('No ratings provided');
        }

        // Initialize managers
        $assessment_manager = Assessment_360_Assessment_Manager::get_instance();
        $user_manager = Assessment_360_User_Manager::get_instance();

        // Verify users exist
        $assessor = $user_manager->get_user($assessor_id);
        $assessee = $user_manager->get_user($assessee_id);

        if (!$assessor || !$assessee) {
            throw new Exception('Invalid user data');
        }

        // Check if assessment already completed
        if ($assessment_manager->is_assessment_completed($assessor_id, $assessee_id)) {
            throw new Exception('Assessment already completed');
        }

        // Prepare assessment data
        $assessment_data = array(
            'assessor_id' => $assessor_id,
            'assessee_id' => $assessee_id,
            'ratings' => array_map('intval', $ratings),
            'comments' => array_map('sanitize_textarea_field', $comments),
            'submitted_at' => current_time('mysql')
        );

        // Save assessment
        $result = $assessment_manager->save_assessment($assessment_data);

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        // Redirect with success message
        wp_redirect(add_query_arg(
            'message', 
            'assessment_submitted',
            home_url('/360-assessment-dashboard/')
        ));
        exit;

    } catch (Exception $e) {
        if (WP_DEBUG) {
            error_log('Assessment submission error: ' . $e->getMessage());
        }

        wp_redirect(add_query_arg(
            'error',
            urlencode($e->getMessage()),
            wp_get_referer()
        ));
        exit;
    }
}