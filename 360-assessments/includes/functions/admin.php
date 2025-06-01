<?php
if (!defined('ABSPATH')) exit;

/**
 * Asset Registration
 */
// Register public assets
function assessment_360_register_public_assets() {
    wp_register_style('assessment-360-form', ASSESSMENT_360_PLUGIN_URL . 'public/css/form.css', [], ASSESSMENT_360_VERSION);
    wp_register_script('assessment-360-form', ASSESSMENT_360_PLUGIN_URL . 'public/js/assessment-form.js', ['jquery'], ASSESSMENT_360_VERSION, true);
}
add_action('init', 'assessment_360_register_public_assets', 20);

/**
 * Admin Menu Setup
 */
function assessment_360_register_admin_menu() {
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
    $submenus = [
        'assessment-360-assessments' => [
            'title' => 'Assessments',
            'callback' => 'assessment_360_assessments_page'
        ],
        'assessment-360-user-management' => [
            'title' => 'User Management',
            'callback' => 'assessment_360_user_management_page'
        ],
        'assessment-360-forms' => [
            'title' => 'Assessment Forms',
            'callback' => 'assessment_360_forms_page'
        ],
        'assessment-360-results' => [
            'title' => 'Assessment Results',
            'callback' => 'assessment_360_results'
        ],
        'assessment-360-email-templates' => [
            'title' => 'Send Emails',
            'callback' => 'assessment_360_email_templates_page'
        ],
        'assessment-360-settings' => [
            'title' => 'Settings',
            'callback' => 'assessment_360_settings_page'
        ]
    ];

    foreach ($submenus as $slug => $menu) {
        add_submenu_page(
            'assessment-360',
            $menu['title'],
            $menu['title'],
            'manage_options',
            $slug,
            $menu['callback']
        );
    }
}
add_action('admin_menu', 'assessment_360_register_admin_menu');

/**
 * Admin Page Callbacks
 */
function assessment_360_dashboard_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }

    try {
        $assessment_manager = Assessment_360_Assessment::get_instance();
        $user_manager = Assessment_360_User_Manager::get_instance();
        
        // Get dashboard data
        $active_assessments_count = $assessment_manager->get_active_assessments_count() ?? 0;
        $total_users_count = $user_manager->get_total_users_count() ?? 0;
        $overall_completion_rate = $assessment_manager->get_overall_completion_rate() ?? 0;
        $current_assessment = $assessment_manager->get_current_assessment();
        
        if ($current_assessment) {
            $completion_stats = $assessment_manager->get_assessment_completion_stats($current_assessment->id);
            $current_assessment->completion_rate = $completion_stats ? $completion_stats->completion_rate : 0;
        }

        // Get additional statistics
        $user_stats = $user_manager->get_user_stats() ?? new stdClass();
        $users_by_group = $user_manager->get_users_by_group(true) ?? [];
        $recent_users = $user_manager->get_recent_users(5) ?? [];

        require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/dashboard.php';

    } catch (Exception $e) {
        error_log('Dashboard Error: ' . $e->getMessage());
        echo '<div class="notice notice-error"><p>Error loading dashboard: ' . 
             esc_html($e->getMessage()) . '</p></div>';
    }
}

function assessment_360_assessments_page() { 
    require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/assessments.php'; 
}

function assessment_360_user_management_page() { 
    require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/user-management.php';
}

function assessment_360_forms_page() { 
    require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/forms.php'; 
}

function assessment_360_results() { 
    require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/results.php'; 
}

function assessment_360_email_templates_page() { 
    require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/send-email.php'; 
}

function assessment_360_settings_page() { 
    require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/settings.php'; 
}

function assessment_360_verify_requirements() {
    // Check for missing tables
    $missing_tables = get_option('assessment_360_missing_tables');
    if ($missing_tables) {
        // Force table creation
        Assessment_360_Activator::setup_database();
        
        // Check again
        $missing_tables = get_option('assessment_360_missing_tables');
        if ($missing_tables) {
            add_action('admin_notices', function() use ($missing_tables) {
                ?>
                <div class="notice notice-error">
                    <p><strong>360° Assessment Error:</strong> Database tables are missing. Please deactivate and reactivate the plugin.</p>
                    <p>Missing tables: <?php echo esc_html(implode(', ', $missing_tables)); ?></p>
                </div>
                <?php
            });
        }
    }
}
add_action('admin_init', 'assessment_360_verify_requirements');


/**
 * Handle logo upload in admin
 */
function assessment_360_handle_logo_upload($file) {
    if (!current_user_can('manage_options')) {
        return new WP_Error('unauthorized', 'Unauthorized access');
    }

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 1048576; // 1MB

    if (!in_array($file['type'], $allowed_types)) {
        return new WP_Error('invalid_type', 'Invalid file type');
    }

    if ($file['size'] > $max_size) {
        return new WP_Error('file_too_large', 'File is too large');
    }

    $attachment_id = media_handle_upload('org_logo', 0);
    if (is_wp_error($attachment_id)) {
        return $attachment_id;
    }

    $attachment_url = wp_get_attachment_url($attachment_id);
    update_option('assessment_360_organization_logo', $attachment_url);

    return $attachment_url;
}
