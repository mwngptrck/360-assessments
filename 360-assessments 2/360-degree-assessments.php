<?php
/*
Plugin Name: 360 Assessments - Merged Main file
Description: A comprehensive 360-degree feedback system for employee assessments
Version: 2.0.0
Author: Patrick Mwangi
License: GPL v2 or later
Text Domain: 360-degree-assessments
*/

set_error_handler(function($errno, $errstr) {
    if (str_contains($errstr, 'Passing null to parameter')) {
        return true; // Suppress the warning
    }
    return false; // Let other errors through
}, E_DEPRECATED);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

define('ASSESSMENT_360_VERSION', '2.0.0');
define('ASSESSMENT_360_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ASSESSMENT_360_PLUGIN_DIR', plugin_dir_path(__FILE__));

add_action('init', function() {
    if (!session_id() && !headers_sent()) {
        session_start();
    }
}, 1);

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'Assessment_360_';
    if (strpos($class, $prefix) !== 0) return;
    $relative_class = substr($class, strlen($prefix));
    $file = ASSESSMENT_360_PLUGIN_DIR . 'includes/class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';
    if (file_exists($file)) require_once $file;
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



// Activation
register_activation_hook(__FILE__, 'assessment_360_activate');
function assessment_360_activate($network_wide) {
    global $wpdb;

    try {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Initialize managers
        $user_manager = Assessment_360_User_Manager::get_instance();
        $group_manager = Assessment_360_Group_Manager::get_instance();
        $position = Assessment_360_Position::get_instance();
        
        // Create or update assessments table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_assessments (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            status enum('active','completed','deleted') NOT NULL DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id)
        ) {$wpdb->get_charset_collate()};";
        dbDelta($sql);

        // Create or update responses table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_assessment_responses (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            assessment_id bigint(20) NOT NULL,
            assessor_id bigint(20) NOT NULL,
            assessee_id bigint(20) NOT NULL,
            question_id bigint(20) NOT NULL,
            rating int(11),
            comment text,
            status enum('pending','completed') NOT NULL DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY assessment_id (assessment_id),
            KEY assessor_id (assessor_id),
            KEY assessee_id (assessee_id),
            KEY question_id (question_id)
        ) {$wpdb->get_charset_collate()};";
        dbDelta($sql);
        
        // Create required tables
        $user_manager->verify_table_structure();
        $group_manager->verify_table_structure();
        $position->verify_table_structure();
        
        // Create Peers group if it doesn't exist
        $peers_group = $group_manager->get_peers_group();
        if (!$peers_group) {
            $group_manager->create_group([
                'group_name' => 'Peers',
                'description' => 'Main peer group for assessments',
                'is_department' => 0
            ]);
        }
        
        // Set default email templates if not set
        if (!get_option('assessment_360_welcome_email')) {
            update_option('assessment_360_welcome_email', 
                "Welcome to the 360° Assessment System!\n\n" .
                "Your account has been created with the following credentials:\n" .
                "Email: {email}\n" .
                "Password: {password}\n\n" .
                "Please login at: {login_url}"
            );
        }
        
        if (!get_option('assessment_360_reminder_email')) {
            update_option('assessment_360_reminder_email',
                "Hello {first_name},\n\n" .
                "This is a reminder that you have pending assessments to complete.\n" .
                "Please login at {login_url} to complete your assessments.\n\n" .
                "Thank you."
            );
        }
        
        // Set plugin version
        add_option('assessment_360_version', '1.0.0');
        
        // Set flag for setup wizard
        add_option('assessment_360_do_setup', 'yes');
        
    } catch (Exception $e) {
        wp_die('Error during plugin activation: ' . esc_html($e->getMessage()));
    }
}

/**
 * Handle database updates safely
 */
function assessment_360_update_db() {
    global $wpdb;
    
    $current_version = get_option('assessment_360_version', '0');
    
    // Only run if needed
    if (version_compare($current_version, '1.0.0', '<')) {
        try {
            // Update assessments table status column
            $wpdb->query("
                ALTER TABLE {$wpdb->prefix}360_assessments 
                MODIFY COLUMN status enum('active','completed','deleted') 
                NOT NULL DEFAULT 'active'
            ");

            // Update responses table status column
            $wpdb->query("
                ALTER TABLE {$wpdb->prefix}360_assessment_responses 
                MODIFY COLUMN status enum('pending','completed') 
                NOT NULL DEFAULT 'pending'
            ");

            // Add completed_at column if it doesn't exist
            $assessments_columns = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}360_assessments");
            if (!in_array('completed_at', $assessments_columns)) {
                $wpdb->query("
                    ALTER TABLE {$wpdb->prefix}360_assessments 
                    ADD COLUMN completed_at datetime DEFAULT NULL
                ");
            }

            $responses_columns = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}360_assessment_responses");
            if (!in_array('completed_at', $responses_columns)) {
                $wpdb->query("
                    ALTER TABLE {$wpdb->prefix}360_assessment_responses 
                    ADD COLUMN completed_at datetime DEFAULT NULL
                ");
            }

            update_option('assessment_360_version', '1.0.0');
            
        } catch (Exception $e) {
            error_log('360 Assessment DB Update Error: ' . $e->getMessage());
        }
    }
}

// Plugin initialization
function assessment_360_init() {
    Assessment_360_User_Manager::get_instance();
    Assessment_360_Group_Manager::get_instance();
    Assessment_360_Assessment_Manager::get_instance();
    Assessment_360_Template_Loader::get_instance();
    Assessment_360_Settings_Manager::get_instance();
    
    assessment_360_update_db();
}
add_action('plugins_loaded', 'assessment_360_init');

//////////////Setup Wizard///////////////
// Add setup page to admin menu
function add_setup_page() {
    add_submenu_page(
        null, // Hidden from menu
        '360° Assessment Setup',
        'Setup',
        'manage_options',
        'assessment-360-setup',
        'render_setup_page'
    );
}
add_action('admin_menu', 'add_setup_page');

// Render setup page
function render_setup_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    require_once plugin_dir_path(__FILE__) . 'admin/templates/setup.php';
}

// Handle setup settings save
function handle_save_setup_settings() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('save_setup_settings', 'setup_settings_nonce');

    // Organization settings
    if (isset($_POST['org_name'])) {
        update_option('assessment_360_organization_name', sanitize_text_field($_POST['org_name']));
    }
    
    if (isset($_POST['org_logo'])) {
        update_option('assessment_360_organization_logo', esc_url_raw($_POST['org_logo']));
    }

    // Email templates - only save if not already set
    if (!get_option('assessment_360_welcome_email') && isset($_POST['welcome_email'])) {
        update_option('assessment_360_welcome_email', wp_kses_post($_POST['welcome_email']));
    }
    
    if (!get_option('assessment_360_reminder_email') && isset($_POST['reminder_email'])) {
        update_option('assessment_360_reminder_email', wp_kses_post($_POST['reminder_email']));
    }

    wp_redirect(add_query_arg(
        array(
            'page' => 'assessment-360-setup',
            'step' => 'groups',
            'message' => 'settings-saved'
        ),
        admin_url('admin.php')
    ));
    exit;
}
add_action('admin_post_save_setup_settings', 'handle_save_setup_settings');

// Handle setup groups save
function handle_save_setup_groups() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('save_setup_groups', 'setup_groups_nonce');

    $group_manager = Assessment_360_Group_Manager::get_instance();

    // Create new group if name provided
    if (!empty($_POST['new_group_name'])) {
        $group_manager->create_group([
            'group_name' => sanitize_text_field($_POST['new_group_name']),
            'description' => sanitize_text_field($_POST['new_group_desc'] ?? ''),
            'is_department' => isset($_POST['new_group_is_department']) ? 1 : 0
        ]);
    }

    wp_redirect(add_query_arg(
        array(
            'page' => 'assessment-360-setup',
            'step' => 'positions',
            'message' => 'groups-saved'
        ),
        admin_url('admin.php')
    ));
    exit;
}
add_action('admin_post_save_setup_groups', 'handle_save_setup_groups');

// Handle setup positions save
function handle_save_setup_positions() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('save_setup_positions', 'setup_positions_nonce');

    // Create new position if name provided
    if (!empty($_POST['new_position_name'])) {
        $position = Assessment_360_Position::get_instance();
        
        // Adjust the method name and parameters according to your existing class
        $position->create([
            'name' => sanitize_text_field($_POST['new_position_name']),
            'description' => sanitize_text_field($_POST['new_position_desc'] ?? '')
        ]);
    }

    wp_redirect(add_query_arg(
        array(
            'page' => 'assessment-360-setup',
            'step' => 'complete',
            'message' => 'positions-saved'
        ),
        admin_url('admin.php')
    ));
    exit;
}
add_action('admin_post_save_setup_positions', 'handle_save_setup_positions');

// Redirect to setup wizard after activation
function assessment_360_redirect_to_setup() {
    if (get_option('assessment_360_do_setup') === 'yes') {
        delete_option('assessment_360_do_setup');
        wp_safe_redirect(add_query_arg(
            array(
                'page' => 'assessment-360-setup',
                'step' => 'settings'
            ),
            admin_url('admin.php')
        ));
        exit;
    }
}
add_action('admin_init', 'assessment_360_redirect_to_setup');

// Add setup notice if not completed
function assessment_360_setup_notice() {
    $screen = get_current_screen();
    if ($screen->id !== 'admin_page_assessment-360-setup' && get_option('assessment_360_do_setup') === 'yes') {
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
}
add_action('admin_notices', 'assessment_360_setup_notice');

//////////////Setup Wizard///////////////

// Flush rewrite rules on plugin activation
function assessment_360_flush_rewrite_rules() {
    assessment_360_register_endpoints();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'assessment_360_flush_rewrite_rules');

// Plugin deactivation
//register_deactivation_hook(__FILE__, 'assessment_360_deactivate');
//function assessment_360_deactivate() {
//    // Clear rewrite rules
//    flush_rewrite_rules();
//    
//    // Clear any transients
//    delete_transient('assessment_360_cache');
//}

// Plugin uninstall
register_uninstall_hook(__FILE__, 'assessment_360_uninstall');
function assessment_360_uninstall() {
    if (!get_option('assessment_360_allow_uninstall')) {
        return;
    }

    try {
        // Remove tables
        Assessment_360_User_Manager::get_instance()->remove_tables();
        Assessment_360_Group_Manager::get_instance()->remove_tables();
        Assessment_360_Assessment_Manager::get_instance()->remove_tables();
        Assessment_360_Settings_Manager::get_instance()->remove_settings();

        // Remove plugin options
        $options = [
            'assessment_360_version',
            'assessment_360_installed',
            'assessment_360_allow_uninstall',
            'assessment_360_organization_name',
            'assessment_360_organization_logo'
        ];

        foreach ($options as $option) {
            delete_option($option);
        }

        // Remove custom pages
        $pages = ['360-assessment-login', '360-assessment-dashboard'];
        foreach ($pages as $page_slug) {
            $page = get_page_by_path($page_slug);
            if ($page) {
                wp_delete_post($page->ID, true);
            }
        }

        // Clear any remaining transients
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '%assessment_360_%'"
        );

    } catch (Exception $e) {
        //Error code
    }
}

// Backend menu
add_action('admin_menu', function() {
    add_menu_page('360° Assessments', '360° Assessments', 'manage_options', 'assessment-360', 'assessment_360_dashboard_page', 'dashicons-groups', 30);
    $submenus = [
        'assessment-360-assessments' => ['title' => 'Assessments', 'callback' => 'assessment_360_assessments_page'],
        'assessment-360-user-management' => ['title' => 'User Management', 'callback' => 'assessment_360_user_management_page'],
        'assessment-360-forms' => ['title' => 'Forms', 'callback' => 'assessment_360_forms_page'],
        'assessment-360-results' => ['title' => 'Assessment Results', 'callback' => 'assessment_360_results'],
        'assessment-360-email-templates' => ['title' => 'Send Emails', 'callback' => 'assessment_360_email_templates_page'],
        'assessment-360-settings' => ['title' => 'Settings', 'callback' => 'assessment_360_settings_page'],
    ];
    foreach ($submenus as $slug => $menu) {
        add_submenu_page('assessment-360', $menu['title'], $menu['title'], 'manage_options', $slug, $menu['callback']);
    }
});

//Register public assets
add_action('init', 'assessment_360_register_public_assets', 20);

// Asset registration
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

//Required files
function assessment_360_positions_page() { require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/positions.php'; }
function assessment_360_groups_page() { require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/user-groups.php'; }
function assessment_360_assessments_page() { require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/assessments.php'; }
function assessment_360_results() { require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/results.php'; }
function assessment_360_email_templates_page() { require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/send-email.php'; }
function assessment_360_settings_page() { require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/settings.php'; }
function assessment_360_forms_page() { require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/forms.php'; }
function assessment_360_user_management_page() { require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/user-management.php';}

// Enqueue admin scripts and styles
function assessment_360_admin_enqueue_scripts($hook) {
    if (strpos($hook, 'assessment-360-setup') !== false) {
        wp_enqueue_style('wp-admin');
        wp_enqueue_style('buttons');
        wp_enqueue_style('dashboard');
        wp_enqueue_style('list-tables');
        wp_enqueue_style('bootstrap-icons');
        wp_enqueue_admin_bar_header_styles();
    }
}
add_action('admin_enqueue_scripts', 'assessment_360_admin_enqueue_scripts');

// Enqueue public assets when needed
add_action('wp_enqueue_scripts', 'assessment_360_enqueue_public_assets');
function assessment_360_enqueue_public_assets() {
    // Only load on assessment form page
    if (get_query_var('assessment_360_form')) {
        wp_enqueue_style('assessment-360-form');
    }
}

// Register rewrite rules and endpoints
function assessment_360_register_endpoints() {
    add_rewrite_rule('assessment-form/?$', 'index.php?assessment_360_form=1', 'top');
    add_rewrite_tag('%assessment_360_form%', '1');
}
add_action('init', 'assessment_360_register_endpoints', 10);

add_filter('query_vars', function($vars) {
    $vars[] = 'assessment_360_form';
    $vars[] = 'assessment_id';
    $vars[] = 'assessee_id';
    $vars[] = 'self_assessment';
    return $vars;
});

// Template redirect
add_action('template_redirect', 'assessment_360_template_redirect');
function assessment_360_template_redirect() {
    if (get_query_var('assessment_360_form')) {
        $template = ASSESSMENT_360_PLUGIN_DIR . 'templates/page-assessment-form.php';
        if (file_exists($template)) {
            include $template;
            exit;
        }
    }
}

// Page callbacks
function assessment_360_dashboard_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }

    try {
        $assessment_manager = Assessment_360_Assessment_Manager::get_instance();
        $user_manager = Assessment_360_User_Manager::get_instance();
        
        // Get dashboard data
        $active_assessments_count = $assessment_manager->get_active_assessments_count();
        $total_users_count = $user_manager->get_total_users_count();
        $overall_completion_rate = $assessment_manager->get_overall_completion_rate();
        $current_assessment = $assessment_manager->get_current_assessment();
        
        if ($current_assessment) {
            $completion_stats = $assessment_manager->get_assessment_completion_stats($current_assessment->id);
            $current_assessment->completion_rate = $completion_stats ? $completion_stats->completion_rate : 0;
        }

        // Get additional statistics
        $user_stats = $user_manager->get_user_stats();
        $users_by_group = $user_manager->get_users_by_group(true);
        $recent_users = $user_manager->get_recent_users(5);

        require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/dashboard.php';

    } catch (Exception $e) {
        //wp_die('Error loading dashboard: ' . esc_html($e->getMessage()));
    }
}

function assessment_360_users_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    try {
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        $template_path = '';

        switch ($action) {
            case 'view':
                $template_path = ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/user-profile.php';
                break;
            default:
                $template_path = ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/users.php';
                break;
        }

        if (!file_exists($template_path)) {
            throw new Exception('Template file not found: ' . $template_path);
        }

        // Start output buffering
        ob_start();
        include $template_path;
        echo ob_get_clean();

    } catch (Exception $e) {
        //wp_die('Error: ' . esc_html($e->getMessage()));
    }
}

// handle user login
add_action('init', function() {
    // Login
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assessment_360_login') {
        if (!isset($_POST['login_nonce']) || !wp_verify_nonce($_POST['login_nonce'], 'assessment_360_login')) {
            wp_redirect(add_query_arg('error', urlencode('Invalid security token'), home_url('/360-assessment-login/')));
            exit;
        }
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        if (empty($email) || empty($password)) {
            wp_redirect(add_query_arg('error', urlencode('Please enter both email and password.'), home_url('/360-assessment-login/')));
            exit;
        }
        $user_manager = Assessment_360_User_Manager::get_instance();
        $user = $user_manager->verify_login($email, $password);
        if (is_wp_error($user)) {
            wp_redirect(add_query_arg('error', urlencode($user->get_error_message()), home_url('/360-assessment-login/')));
            exit;
        }
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['login_time'] = time();
        wp_redirect(home_url('/360-assessment-dashboard/'));
        exit;
    }
    // Logout handler
    if (isset($_GET['action']) && $_GET['action'] === 'logout') {
        if (session_id()) {
            $_SESSION = [];
            session_destroy();
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }
        }
        wp_redirect(home_url('/360-assessment-login/'));
        exit;
    }
});

// Add helper function for logout
function assessment_360_logout() {
    // Clear session
    if (session_id()) {
        session_destroy();
    }
    $_SESSION = array();
    
    // Clear session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
}

// handle the password reset request
add_action('init', function() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
        isset($_POST['action']) && 
        $_POST['action'] === 'assessment_360_forgot_password') {
        
        if (!isset($_POST['assessment_360_forgot_password_nonce']) || 
            !wp_verify_nonce($_POST['assessment_360_forgot_password_nonce'], 'assessment_360_forgot_password')) {
            wp_redirect(add_query_arg('error', 'Invalid security token', wp_get_referer()));
            exit;
        }

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        if (empty($email)) {
            wp_redirect(add_query_arg('error', 'Please enter your email address.', wp_get_referer()));
            exit;
        }

        $user_manager = Assessment_360_User_Manager::get_instance();
        $result = $user_manager->send_password_reset_email($email);

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('error', $result->get_error_message(), wp_get_referer()));
        } else {
            wp_redirect(add_query_arg(
                'message', 
                'If the email exists in our system, password reset instructions will be sent.',
                wp_get_referer()
            ));
        }
        exit;
    }
});

// Template overrides for login and dashboard
add_filter('template_include', function($template) {
    if (is_page('360-assessment-login')) {
        $new_template = ASSESSMENT_360_PLUGIN_DIR . 'templates/page-login.php';
        if (file_exists($new_template)) return $new_template;
    } elseif (is_page('360-assessment-dashboard')) {
        if (!isset($_SESSION['user_id'])) {
            wp_redirect(home_url('/360-assessment-login/'));
            exit;
        }
        $new_template = ASSESSMENT_360_PLUGIN_DIR . 'templates/page-dashboard.php';
        if (file_exists($new_template)) return $new_template;
    } elseif (is_page('forgot-password')) {
        $new_template = ASSESSMENT_360_PLUGIN_DIR . 'templates/forgot-password.php';
        if (file_exists($new_template)) return $new_template;
    } elseif (is_page('reset-password')) {
        $new_template = ASSESSMENT_360_PLUGIN_DIR . 'templates/reset-password.php';
        if (file_exists($new_template)) return $new_template;
    }
    return $template;
});

// Create login and dashboard pages if not exist
function assessment_360_create_pages() {
    if (!get_page_by_path('360-assessment-login')) {
        wp_insert_post([
            'post_title' => '360° Assessment Login',
            'post_name' => '360-assessment-login',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => ''
        ]);
    }
    if (!get_page_by_path('360-assessment-dashboard')) {
        wp_insert_post([
            'post_title' => '360° Assessment Dashboard',
            'post_name' => '360-assessment-dashboard',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => ''
        ]);
    }
    // Add Forgot Password page
    if (!get_page_by_path('forgot-password')) {
        wp_insert_post([
            'post_title' => 'Forgot Password',
            'post_name' => 'forgot-password',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => ''
        ]);
    }    
    // Create Forgot Password page
    if (!get_page_by_path('forgot-password')) {
        wp_insert_post([
            'post_title' => 'Forgot Password',
            'post_name' => 'forgot-password',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => ''
        ]);
    }

    // Create Reset Password page
    if (!get_page_by_path('reset-password')) {
        wp_insert_post([
            'post_title' => 'Reset Password',
            'post_name' => 'reset-password',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => ''
        ]);
    }
}

// Helper function for assessment form URL
function assessment_360_get_form_url($assessment_id, $assessee_id, $is_self = false) {
    return add_query_arg([
        'assessment_id' => $assessment_id,
        'assessee_id' => $assessee_id,
        'self_assessment' => $is_self ? '1' : '0'
    ], home_url('/assessment-form/'));
}

// Asset version (for cache busting)
function assessment_360_asset_version($file) {
    return ASSESSMENT_360_VERSION;
}

// Delete Assessment Handler
add_action('admin_post_delete_assessment', 'handle_delete_assessment');
function handle_delete_assessment() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('delete_assessment', 'delete_assessment_nonce');

    $assessment_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if (!$assessment_id) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Invalid assessment ID')
        ], admin_url('admin.php')));
        exit;
    }

    $assessment = Assessment_360_Assessment::get_instance();
    
    // Check if assessment exists and get its status
    $assessment_data = $assessment->get_assessment($assessment_id);
    if (!$assessment_data) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Assessment not found')
        ], admin_url('admin.php')));
        exit;
    }

    // Prevent deleting active assessments
    if ($assessment_data->status === 'active') {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Cannot delete an active assessment. Please complete it first.')
        ], admin_url('admin.php')));
        exit;
    }

    $result = $assessment->update_assessment_status($assessment_id, 'deleted');

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }

    wp_redirect(add_query_arg([
        'page' => 'assessment-360-assessments',
        'message' => urlencode('Assessment deleted successfully')
    ], admin_url('admin.php')));
    exit;
}

// Handle assessment restoration
function handle_restore_assessment() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('restore_assessment', 'restore_assessment_nonce');

    $assessment_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if (!$assessment_id) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Invalid assessment ID')
        ], admin_url('admin.php')));
        exit;
    }

    $assessment = Assessment_360_Assessment::get_instance();

    // Check if assessment exists
    if (!$assessment->assessment_exists($assessment_id)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Assessment not found')
        ], admin_url('admin.php')));
        exit;
    }

    $result = $assessment->update_assessment_status($assessment_id, 'completed');

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }

    wp_redirect(add_query_arg([
        'page' => 'assessment-360-assessments',
        'message' => urlencode('Assessment restored successfully')
    ], admin_url('admin.php')));
    exit;
}
add_action('admin_post_restore_assessment', 'handle_restore_assessment');

// Handle assessment form submission
function handle_assessment_submission() {
    

    if (!isset($_POST['assessment_nonce']) || 
        !wp_verify_nonce($_POST['assessment_nonce'], 'submit_assessment')) {
        wp_die('Invalid security token');
    }

    try {
        // Validate required data
        $assessment_id = isset($_POST['assessment_id']) ? intval($_POST['assessment_id']) : 0;
        $assessor_id = isset($_POST['assessor_id']) ? intval($_POST['assessor_id']) : 0;
        $assessee_id = isset($_POST['assessee_id']) ? intval($_POST['assessee_id']) : 0;

        // Check for ratings in POST data
        $ratings = isset($_POST['rating']) ? $_POST['rating'] : [];
        $comments = isset($_POST['comment']) ? $_POST['comment'] : [];

        if (empty($ratings)) {
            throw new Exception('No ratings provided');
        }

        // Build responses array
        $responses = [];
        foreach ($ratings as $question_id => $rating) {
            $responses[$question_id] = [
                'rating' => intval($rating),
                'comment' => isset($comments[$question_id]) ? sanitize_textarea_field($comments[$question_id]) : ''
            ];
        }

        $assessment_manager = Assessment_360_Assessment_Manager::get_instance();
        
        // Save the assessment
        $result = $assessment_manager->save_assessment_response([
            'assessment_id' => $assessment_id,
            'assessor_id' => $assessor_id,
            'assessee_id' => $assessee_id,
            'responses' => $responses
        ]);

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        // Redirect to dashboard with success message
        wp_redirect(add_query_arg(
            'message',
            'assessment_submitted',
            home_url('/360-assessment-dashboard/')
        ));
        exit;

    } catch (Exception $e) {
        wp_redirect(add_query_arg([
            'error' => urlencode($e->getMessage()),
            'assessment_id' => $assessment_id,
            'assessee_id' => $assessee_id
        ], wp_get_referer()));
        exit;
    }
}
add_action('admin_post_submit_assessment', 'handle_assessment_submission');
add_action('admin_post_nopriv_submit_assessment', 'handle_assessment_submission');

//Save Assessment Handler
add_action('init', 'handle_save_assessment');
function handle_save_assessment() {
    if (!isset($_POST['action']) || $_POST['action'] !== 'save_assessment') {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    try {
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || 
            !wp_verify_nonce($_POST['_wpnonce'], 'save_assessment_nonce')) {
            throw new Exception('Invalid security token');
        }

        // Sanitize and validate input data
        $data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date' => sanitize_text_field($_POST['end_date'] ?? '')
        );

        // Validate required fields
        if (empty($data['name']) || empty($data['start_date']) || empty($data['end_date'])) {
            throw new Exception('Please fill in all required fields.');
        }

        // Validate dates
        $start_date = strtotime($data['start_date']);
        $end_date = strtotime($data['end_date']);
        $today = strtotime('today');

        if (!isset($_POST['id']) && $start_date < $today) {
            throw new Exception('Start date cannot be earlier than today.');
        }
        
        if ($end_date < $start_date) {
            throw new Exception('End date cannot be earlier than start date.');
        }

        $assessment_manager = Assessment_360_Assessment_Manager::get_instance();

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

        wp_redirect(add_query_arg(
            'message',
            urlencode($message),
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

// Enable Assessment Handler
add_action('admin_post_enable_assessment', 'handle_enable_assessment');
function handle_enable_assessment() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $assessment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$assessment_id || 
        !wp_verify_nonce($_GET['_wpnonce'], 'enable_assessment_' . $assessment_id)) {
        wp_die('Invalid request');
    }

    try {
        $assessment_manager = Assessment_360_Assessment_Manager::get_instance();
        $result = $assessment_manager->update_assessment_status($assessment_id, 'active');

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        wp_redirect(add_query_arg(
            'message',
            'Assessment enabled successfully',
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

//Handle assessment activation
function handle_activate_assessment() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('activate_assessment', 'activate_assessment_nonce');

    $assessment_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if (!$assessment_id) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Invalid assessment ID')
        ], admin_url('admin.php')));
        exit;
    }

    $assessment = Assessment_360_Assessment::get_instance();
    
    // Check if assessment exists
    $assessment_data = $assessment->get_assessment($assessment_id);
    if (!$assessment_data) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Assessment not found')
        ], admin_url('admin.php')));
        exit;
    }

    // Check if there's already an active assessment
    $active_assessment = $assessment->get_active_assessment();
    if ($active_assessment) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Another assessment is currently active. Please complete it first.')
        ], admin_url('admin.php')));
        exit;
    }

    // Update assessment status
    $result = $assessment->update_assessment_status($assessment_id, 'active');

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }

    wp_redirect(add_query_arg([
        'page' => 'assessment-360-assessments',
        'message' => urlencode('Assessment activated successfully')
    ], admin_url('admin.php')));
    exit;
}
add_action('admin_post_activate_assessment', 'handle_activate_assessment');

// Disable Assessment Handler
add_action('admin_post_disable_assessment', 'handle_disable_assessment');
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

// Complete Assessment Handler
add_action('admin_post_complete_assessment', 'handle_complete_assessment');
function handle_complete_assessment() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('complete_assessment', 'complete_assessment_nonce');

    $assessment_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if (!$assessment_id) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Invalid assessment ID')
        ], admin_url('admin.php')));
        exit;
    }

    $assessment = Assessment_360_Assessment::get_instance();
    
    // Check if assessment exists and is active
    $assessment_data = $assessment->get_assessment($assessment_id);
    if (!$assessment_data) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Assessment not found')
        ], admin_url('admin.php')));
        exit;
    }

    if ($assessment_data->status !== 'active') {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Only active assessments can be completed')
        ], admin_url('admin.php')));
        exit;
    }

    // Update assessment status
    $result = $assessment->update_assessment_status($assessment_id, 'completed');

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }

    wp_redirect(add_query_arg([
        'page' => 'assessment-360-assessments',
        'message' => urlencode('Assessment completed successfully')
    ], admin_url('admin.php')));
    exit;
}

//Save User Handlers
add_action('admin_post_save_user', 'handle_save_user');
function handle_save_user() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // Verify nonce
    if (!isset($_POST['save_user_nonce']) || !wp_verify_nonce($_POST['save_user_nonce'], 'save_user_nonce')) {
        wp_die('Invalid security token');
    }

    try {
        $user_manager = Assessment_360_User_Manager::get_instance();
        $group_manager = Assessment_360_Group_Manager::get_instance();
        
        // Get group info to check if it's Peers
        $group_id = intval($_POST['group_id']);
        $group = $group_manager->get_group($group_id);
        $is_peers = $group && strtolower($group->group_name) === 'peers';

        // Validate department for Peers group
        if ($is_peers && empty($_POST['department_id'])) {
            throw new Exception('Department is required for Peers group members.');
        }

        // Sanitize and validate input data
        $data = array(
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'email' => sanitize_email($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'group_id' => $group_id,
            'department_id' => $is_peers ? intval($_POST['department_id']) : null,
            'position_id' => $is_peers && !empty($_POST['position_id']) ? intval($_POST['position_id']) : null,
            'status' => 'active'
        );

        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'email', 'group_id'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new Exception("$field is required");
            }
        }

        // Create or update user
        if (isset($_POST['id'])) {
            $user_id = intval($_POST['id']);
            $result = $user_manager->update_user($user_id, $data);
            $message = 'User updated successfully';
        } else {
            // Generate random password for new user
            $data['password'] = wp_generate_password(12, true, true);
            $result = $user_manager->create_user($data);
            $message = 'User created successfully';
            $user_id = $result;
        }

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        // Handle assessors only for Peers group
        if ($is_peers) {
            $assessors = isset($_POST['assessors']) ? array_map('intval', $_POST['assessors']) : [];
            $result = $user_manager->update_user_relationships($user_id, $assessors);
            
            if (is_wp_error($result)) {
                throw new Exception('Failed to update assessors: ' . $result->get_error_message());
            }
        } else {
            // Clear any existing relationships if not Peers group
            $user_manager->update_user_relationships($user_id, []);
        }

        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'message' => urlencode($message)
        ], admin_url('admin.php')));
        exit;

    } catch (Exception $e) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'action' => isset($_POST['id']) ? 'edit' : 'new',
            'id' => isset($_POST['id']) ? $_POST['id'] : '',
            'error' => urlencode($e->getMessage())
        ], admin_url('admin.php')));
        exit;
    }
}

// Update User Handler
add_action('admin_post_update_user_status', 'handle_update_user_status');
function handle_update_user_status() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    if (!wp_verify_nonce($_POST['_wpnonce'], 'user_status_' . $user_id)) {
        wp_die('Invalid security token');
    }

    $user_manager = Assessment_360_User_Manager::get_instance();
    $new_status = sanitize_text_field($_POST['status']);
    $result = $user_manager->update_user_status($user_id, $new_status);

    wp_redirect(add_query_arg([
        'page' => 'assessment-360-user-management',
        'status' => $_POST['current_status'] ?? 'active',
        'message' => is_wp_error($result) ? 
            urlencode($result->get_error_message()) : 
            'User status updated successfully'
    ], admin_url('admin.php')));
    exit;
}

// Delete User Handler
add_action('admin_post_delete_user', 'handle_delete_user');
function handle_delete_user() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('delete_user');

    // Changed from user_id to id to match the form input
    $user_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if (!$user_id) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'users',
            'error' => urlencode('Invalid user ID')
        ], admin_url('admin.php')));
        exit;
    }

    $user_manager = Assessment_360_User_Manager::get_instance();
    $result = $user_manager->delete_user($user_id);

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'users',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }

    wp_redirect(add_query_arg([
        'page' => 'assessment-360-user-management',
        'tab' => 'users',
        'message' => urlencode('User deleted successfully')
    ], admin_url('admin.php')));
    exit;
}

// Handle user disable
function handle_disable_user() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('disable_user', 'disable_user_nonce');

    $user_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $current_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active';
    
    if (!$user_id) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'users',
            'status' => $current_status,
            'error' => urlencode('Invalid user ID')
        ], admin_url('admin.php')));
        exit;
    }

    $user_manager = Assessment_360_User_Manager::get_instance();
    $result = $user_manager->update_user_status($user_id, 'disabled');

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'users',
            'status' => $current_status,
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }

    wp_redirect(add_query_arg([
        'page' => 'assessment-360-user-management',
        'tab' => 'users',
        'status' => $current_status,
        'message' => urlencode('User disabled successfully')
    ], admin_url('admin.php')));
    exit;
}
add_action('admin_post_disable_user', 'handle_disable_user');

// Bulk user actions Handler
add_action('admin_post_bulk_action_users', 'handle_bulk_action_users');
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
            'page' => 'assessment-360-user-management',
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
        'page' => 'assessment-360-user-management',
        'status' => $current_status,
        'message' => $message
    ], admin_url('admin.php')));
    exit;
}

// Save Position Handler
add_action('admin_post_save_position', 'handle_save_position');
function handle_save_position() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('save_position');

    try {
        $position_manager = Assessment_360_Position::get_instance();
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description'])
        );

        if (empty($data['name'])) {
            throw new Exception('Position name is required.');
        }

        if (isset($_POST['id'])) {
            $result = $position_manager->update_position(intval($_POST['id']), $data);
            $message = 'Position updated successfully.';
        } else {
            $result = $position_manager->create_position($data);
            $message = 'Position created successfully.';
        }

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        wp_redirect(add_query_arg(
            'message',
            urlencode($message),
            admin_url('admin.php?page=assessment-360-user-management&tab=positions')
        ));
        exit;

    } catch (Exception $e) {
        wp_redirect(add_query_arg(
            'error',
            urlencode($e->getMessage()),
            wp_get_referer()
        ));
        exit;
    }
}

// Delete Position Handler
add_action('admin_post_delete_position', 'handle_delete_position');
function handle_delete_position() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('delete_position', 'delete_position_nonce');

    $position_id = isset($_POST['position_id']) ? intval($_POST['position_id']) : 0;
    if (!$position_id) {
        wp_die('Invalid position ID');
    }

    $position = Assessment_360_Position::get_instance();

    // Check if position exists
    $pos = $position->get_position($position_id);
    if (!$pos) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'positions',
            'error' => urlencode('Position not found.')
        ], admin_url('admin.php')));
        exit;
    }

    // Check if position is in use
    if ($position->is_position_in_use($position_id)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'positions',
            'error' => urlencode('Cannot delete position that is assigned to users. Please reassign users first.')
        ], admin_url('admin.php')));
        exit;
    }

    // Delete position
    $result = $position->delete_position($position_id);

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'positions',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }

    wp_redirect(add_query_arg([
        'page' => 'assessment-360-user-management',
        'tab' => 'positions',
        'message' => urlencode('Position deleted successfully.')
    ], admin_url('admin.php')));
    exit;
}

//Handle permanent position deletion
add_action('admin_post_permanent_delete_position', 'handle_permanent_delete_position');
function handle_permanent_delete_position() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('permanent_delete_position', 'permanent_delete_position_nonce');

    $position_id = isset($_POST['position_id']) ? intval($_POST['position_id']) : 0;
    if (!$position_id) {
        wp_die('Invalid position ID');
    }

    $position = Assessment_360_Position::get_instance();
    
    // Check if position exists and is deleted
    $pos = $position->get_position($position_id);
    if (!$pos) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'positions',
            'error' => urlencode('Position not found.')
        ], admin_url('admin.php')));
        exit;
    }

    // Perform the permanent deletion
    $result = $position->permanently_delete_position($position_id);

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'positions',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }

    wp_redirect(add_query_arg([
        'page' => 'assessment-360-user-management',
        'tab' => 'positions',
        'message' => urlencode('Position permanently deleted.')
    ], admin_url('admin.php')));
    exit;
}

// Restore Position Handler
add_action('admin_post_restore_position', 'handle_restore_position');
function handle_restore_position() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // Verify nonce
    check_admin_referer('restore_position', 'restore_position_nonce');

    $position_id = isset($_POST['position_id']) ? intval($_POST['position_id']) : 0;
    if (!$position_id) {
        wp_die('Invalid position ID');
    }

    $position = Assessment_360_Position::get_instance();
    $result = $position->restore_position($position_id);

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'positions',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }

    wp_redirect(add_query_arg([
        'page' => 'assessment-360-user-management',
        'tab' => 'positions',
        'message' => urlencode('Position restored successfully.')
    ], admin_url('admin.php')));
    exit;
}

//Topics Handler
add_action('admin_post_save_topic', 'handle_save_topic');
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

// Topics Delete handler
add_action('admin_post_delete_topic', 'handle_delete_topic');
function handle_delete_topic() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('delete_topic', 'delete_topic_nonce');

    $topic_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if (!$topic_id) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'error' => urlencode('Invalid topic ID')
        ], admin_url('admin.php')));
        exit;
    }

    $topic_manager = Assessment_360_Topic::get_instance();
    $result = $topic_manager->delete_topic($topic_id);

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }

    wp_redirect(add_query_arg([
        'page' => 'assessment-360-forms',
        'message' => urlencode('Topic deleted successfully')
    ], admin_url('admin.php')));
    exit;
}

//Form Deletions Handler (Topics, Sections, Questions)
add_action('admin_init', 'handle_form_deletions');
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

// Sections Delete handler
add_action('admin_post_delete_section', 'handle_delete_section');
function handle_delete_section() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('delete_section', 'delete_section_nonce');

    $section_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if (!$section_id) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'error' => urlencode('Invalid section ID')
        ], admin_url('admin.php')));
        exit;
    }

    $section_manager = Assessment_360_Section::get_instance();
    $result = $section_manager->delete_section($section_id);

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }

    wp_redirect(add_query_arg([
        'page' => 'assessment-360-forms',
        'message' => urlencode('Section deleted successfully')
    ], admin_url('admin.php')));
    exit;
}

// Save Section Handler
add_action('admin_post_save_section', 'handle_save_section');
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

// Save Question Handler
add_action('admin_post_save_question', 'handle_save_question');
function handle_save_question() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('save_question_nonce');

    

    // Validate required fields
    $required_fields = array('question_text', 'section_id', 'position_id');
    $missing_fields = array();

    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }

    if (!empty($missing_fields)) {        
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
            wp_redirect(add_query_arg('error', $result->get_error_message(), wp_get_referer()));
        } else {
            wp_redirect(add_query_arg(
                'message',
                $message,
                admin_url('admin.php?page=assessment-360-forms#questions')
            ));
        }
    } catch (Exception $e) {
        wp_redirect(add_query_arg('error', 'Error saving question: ' . $e->getMessage(), wp_get_referer()));
    }
    exit;
}

// Questions Delete handler
function handle_delete_question() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('delete_question');

    $question_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if (!$question_id) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'error' => urlencode('Invalid question ID')
        ], admin_url('admin.php')));
        exit;
    }

    $question_manager = Assessment_360_Question::get_instance();
    
    // Check if question exists
    $question = $question_manager->get_question($question_id);
    if (!$question) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'error' => urlencode('Question not found')
        ], admin_url('admin.php')));
        exit;
    }

    // Check if question has responses
    if ($question_manager->question_has_responses($question_id)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'error' => urlencode('Cannot delete question that has responses')
        ], admin_url('admin.php')));
        exit;
    }

    // Delete the question
    $result = $question_manager->delete_question($question_id);

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }

    wp_redirect(add_query_arg([
        'page' => 'assessment-360-forms',
        'message' => urlencode('Question deleted successfully')
    ], admin_url('admin.php')));
    exit;
}
add_action('admin_post_delete_question', 'handle_delete_question');

// Save Group Handler
function handle_save_group() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('save_group_nonce');

    try {
        $group_manager = Assessment_360_Group_Manager::get_instance();
        
        // Explicitly set is_department based on checkbox presence
        $is_department = isset($_POST['is_department']) ? 1 : 0;
        
        $data = array(
            'group_name' => sanitize_text_field($_POST['group_name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'is_department' => $is_department
        );

        if (empty($data['group_name'])) {
            throw new Exception('Group name is required.');
        }

        if (isset($_POST['id'])) {
            $result = $group_manager->update_group(intval($_POST['id']), $data);
            $message = 'Group updated successfully.';
        } else {
            $result = $group_manager->create_group($data);
            $message = 'Group created successfully.';
        }

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        wp_redirect(add_query_arg(
            'message',
            urlencode($message),
            admin_url('admin.php?page=assessment-360-user-management&tab=groups')
        ));
        exit;

    } catch (Exception $e) {
        wp_redirect(add_query_arg(
            'error',
            urlencode($e->getMessage()),
            wp_get_referer()
        ));
        exit;
    }
}
add_action('admin_post_save_group', 'handle_save_group');

// Delete Group Handler 
function handle_delete_group() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('delete_group');

    $group_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$group_id) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'groups',
            'error' => urlencode('Invalid group ID')
        ], admin_url('admin.php')));
        exit;
    }

    $group_manager = Assessment_360_Group_Manager::get_instance();
    
    // Check if group exists
    $group = $group_manager->get_group($group_id);
    if (!$group) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'groups',
            'error' => urlencode('Group not found')
        ], admin_url('admin.php')));
        exit;
    }

    // Check if it's a system group
    if ($group_manager->is_system_group($group->group_name)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'groups',
            'error' => urlencode('Cannot delete system groups')
        ], admin_url('admin.php')));
        exit;
    }

    // Check if group has users
    if ($group_manager->group_has_users($group_id)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'groups',
            'error' => urlencode('Cannot delete group that has users assigned to it')
        ], admin_url('admin.php')));
        exit;
    }

    // Delete the group
    $result = $group_manager->delete_group($group_id);
    
    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'groups',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }

    wp_redirect(add_query_arg([
        'page' => 'assessment-360-user-management',
        'tab' => 'groups',
        'message' => urlencode('Group deleted successfully')
    ], admin_url('admin.php')));
    exit;
}
add_action('admin_post_delete_group', 'handle_delete_group');

// Email Handlers
add_action('admin_post_assessment_360_send_email', 'handle_assessment_360_send_email');
function handle_assessment_360_send_email() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('assessment_360_send_email', 'assessment_360_send_email_nonce');

    try {
        $template = isset($_POST['email_template']) ? $_POST['email_template'] : '';
        $recipients = isset($_POST['recipients']) ? array_map('intval', $_POST['recipients']) : array();

        if (empty($template) || empty($recipients)) {
            throw new Exception('Missing required fields');
        }

        $user_manager = Assessment_360_User_Manager::get_instance();
        $success_count = 0;
        $error_count = 0;

        foreach ($recipients as $user_id) {
            $user = $user_manager->get_user($user_id);
            if (!$user) continue;

            // Get email content
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

    } catch (Exception $e) {
        wp_redirect(add_query_arg(
            'error',
            urlencode($e->getMessage()),
            wp_get_referer()
        ));
        exit;
    }
}

// Handle backup creation
function handle_create_backup() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('create_backup_nonce');

    $backup_manager = Assessment_360_Backup_Manager::get_instance();
    $result = $backup_manager->create_backup();

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-settings#database',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
    } else {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-settings#database',
            'message' => urlencode('Backup created successfully')
        ], admin_url('admin.php')));
    }
    exit;
}
add_action('admin_post_create_backup', 'handle_create_backup');

// Handle backup deletion
function handle_delete_backup() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $file = isset($_GET['file']) ? sanitize_file_name($_GET['file']) : '';
    if (!$file) {
        wp_die('Invalid backup file');
    }

    check_admin_referer('delete_backup_' . $file);

    $backup_manager = Assessment_360_Backup_Manager::get_instance();
    $result = $backup_manager->delete_backup($file);

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-settings#database',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
    } else {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-settings#database',
            'message' => urlencode('Backup deleted successfully')
        ], admin_url('admin.php')));
    }
    exit;
}
add_action('admin_post_delete_backup', 'handle_delete_backup');

//PDF export
function handle_export_pdf() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    
    if (!$assessment_id || !$user_id) {
        wp_die('Invalid parameters');
    }
    
    // Get user and assessment details for filename
    $user_manager = Assessment_360_User_Manager::get_instance();
    $assessment_manager = Assessment_360_Assessment::get_instance();
    
    $user = $user_manager->get_user($user_id);
    $assessment = $assessment_manager->get_assessment($assessment_id);
    
    if (!$user || !$assessment) {
        wp_die('User or assessment not found');
    }
    
    // Create sanitized filename
    $filename = sprintf(
        '360_assessment_%s_%s_%s.pdf',
        sanitize_file_name($user->first_name . '_' . $user->last_name),
        sanitize_file_name($assessment->name),
        date('Y-m-d')
    );
    
    require_once plugin_dir_path(__FILE__) . 'includes/class-pdf-generator.php';
    
    $pdf = new Assessment_360_PDF_Generator();
    $result = $pdf->generateAssessmentReport($assessment_id, $user_id);
    
    if (!$result) {
        wp_die('Failed to generate PDF');
    }
    
    // Output PDF with custom filename
    $pdf->Output($filename, 'D');
    exit;
}
add_action('admin_post_export_pdf', 'handle_export_pdf');

function get_rating_color($rating) {
    if ($rating >= 4.5) return 'success';
    if ($rating >= 3.5) return 'info';
    if ($rating >= 2.5) return 'warning';
    return 'danger';
}


///REMOVE
