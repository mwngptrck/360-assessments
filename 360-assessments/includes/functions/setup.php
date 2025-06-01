<?php
if (!defined('ABSPATH')) exit;

/**
 * Setup Wizard Functions
 */
function assessment_360_setup_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $current_step = isset($_GET['step']) ? sanitize_key($_GET['step']) : 'welcome';

    $steps = [
        'welcome' => [
            'title' => 'Welcome',
            'next' => 'settings',
            'prev' => false
        ],
        'settings' => [
            'title' => 'General Settings',
            'next' => 'groups',
            'prev' => 'welcome'
        ],
        'groups' => [
            'title' => 'User Groups',
            'next' => 'positions',
            'prev' => 'settings'
        ],
        'positions' => [
            'title' => 'User Positions',
            'next' => 'complete',
            'prev' => 'groups'
        ],
        'complete' => [
            'title' => 'Setup Complete',
            'next' => false,
            'prev' => 'positions'
        ]
    ];

    require_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/setup-wizard.php';
}

/**
 * Add Setup Page to Admin Menu
 */
function assessment_360_add_setup_page() {
    add_submenu_page(
        null, // Hidden from menu
        'Setup Wizard',
        'Setup Wizard',
        'manage_options',
        'assessment-360-setup',
        'assessment_360_setup_page'
    );
}
add_action('admin_menu', 'assessment_360_add_setup_page');

/**
 * Handle Setup Redirect
 */
function assessment_360_handle_setup_redirect() {
    // Don't redirect if setup is already completed
    if (get_option('assessment_360_setup_completed') === 'yes') {
        return;
    }

    // Don't redirect on AJAX or POST requests
    if (wp_doing_ajax() || $_SERVER['REQUEST_METHOD'] === 'POST') {
        return;
    }

    // Don't redirect if already in setup wizard
    if (isset($_GET['page']) && $_GET['page'] === 'assessment-360-setup') {
        return;
    }

    // Only redirect on plugin-specific pages or immediately after activation
    $is_plugin_page = isset($_GET['page']) && strpos($_GET['page'], 'assessment-360') !== false;
    $just_activated = get_transient('assessment_360_activation_redirect');

    if (!$is_plugin_page && !$just_activated) {
        return;
    }

    // Don't redirect on specific admin actions
    if (isset($_GET['action'])) {
        $excluded_actions = [
            'save_setup_settings',
            'save_setup_group',
            'save_setup_position',
            'generate_pdf_report',
            'upload-plugin',
            'upload-theme',
            'activate-plugin',
            'deactivate-plugin',
            'update-plugin',
            'update-theme',
            'update-core',
            'do-plugin-upgrade',
            'do-theme-upgrade'
        ];
        if (in_array($_GET['action'], $excluded_actions)) {
            return;
        }
    }

    // Check if setup is needed
    if (get_option('assessment_360_do_setup') === 'yes') {
        // Clear the activation redirect transient
        delete_transient('assessment_360_activation_redirect');
        
        wp_safe_redirect(add_query_arg([
            'page' => 'assessment-360-setup',
            'step' => 'welcome'
        ], admin_url('admin.php')));
        exit;
    }
}
add_action('admin_init', 'assessment_360_handle_setup_redirect', 20);

/**
 * Set activation flags for setup wizard
 */
function assessment_360_set_activation_flags() {
    // Only set the setup flag if it hasn't been completed before
    if (get_option('assessment_360_setup_completed') !== 'yes') {
        update_option('assessment_360_do_setup', 'yes');
        set_transient('assessment_360_activation_redirect', true, 30);
    }
}

/**
 * Setup Redirect Helper
 */
function assessment_360_setup_redirect($step, $message = '', $is_error = false) {
    // Clean any existing output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Remove setup redirect filter temporarily
    remove_action('admin_init', 'assessment_360_handle_setup_redirect', 20);
    
    // Prevent any further output
    if (!headers_sent()) {
        wp_safe_redirect(add_query_arg([
            'page' => 'assessment-360-setup',
            'step' => $step,
            $is_error ? 'error' : 'message' => urlencode($message)
        ], admin_url('admin.php')));
        exit;
    } else {
        // If headers are already sent, use JavaScript redirect
        echo '<script type="text/javascript">';
        echo 'window.location.href="' . esc_url(add_query_arg([
            'page' => 'assessment-360-setup',
            'step' => $step,
            $is_error ? 'error' : 'message' => urlencode($message)
        ], admin_url('admin.php'))) . '";';
        echo '</script>';
        echo 'If you are not redirected, please <a href="' . esc_url(add_query_arg([
            'page' => 'assessment-360-setup',
            'step' => $step,
            $is_error ? 'error' : 'message' => urlencode($message)
        ], admin_url('admin.php'))) . '">click here</a>.';
        exit;
    }
}

/**
 * Setup Notice
 */
function assessment_360_setup_notice() {
    // Only show to admins and only if setup isn't completed
    if (!current_user_can('manage_options') || 
        get_option('assessment_360_setup_completed') === 'yes') {
        return;
    }

    // Don't show on setup wizard pages
    $screen = get_current_screen();
    if ($screen && $screen->id === 'admin_page_assessment-360-setup') {
        return;
    }

    // Check if setup is needed
    if (get_option('assessment_360_do_setup') === 'yes') {
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <?php 
                echo wp_kses(
                    sprintf(
                        'Welcome to 360° Assessment! Please <a href="%s">complete the setup</a> to get started.',
                        esc_url(admin_url('admin.php?page=assessment-360-setup'))
                    ),
                    [
                        'a' => [
                            'href' => []
                        ]
                    ]
                ); 
                ?>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'assessment_360_setup_notice');

/**
 * Setup Assets
 */
function assessment_360_setup_assets($hook) {
    if (strpos($hook, 'assessment-360-setup') !== false) {
        wp_enqueue_style('wp-admin');
        wp_enqueue_style('buttons');
        wp_enqueue_style('dashboard');
        wp_enqueue_style('list-tables');
        wp_enqueue_style('bootstrap-icons');
        wp_enqueue_media();
        wp_enqueue_editor();
    }
}
add_action('admin_enqueue_scripts', 'assessment_360_setup_assets');

/**
 * Prevent Setup Redirect Loop
 */
function assessment_360_prevent_redirect_loop() {
    // Remove redirect action if we're in setup or setup is completed
    if (
        (isset($_GET['page']) && $_GET['page'] === 'assessment-360-setup') ||
        get_option('assessment_360_setup_completed') === 'yes'
    ) {
        remove_action('admin_init', 'assessment_360_handle_setup_redirect', 20);
    }
}
add_action('admin_init', 'assessment_360_prevent_redirect_loop', 1);

/**
 * Handle Setup Completion
 */
function assessment_360_complete_setup() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $is_completing = (
        isset($_GET['page']) && $_GET['page'] === 'assessment-360-setup' &&
        isset($_GET['step']) && $_GET['step'] === 'complete'
    ) || (
        isset($_POST['action']) && $_POST['action'] === 'complete_setup'
    );

    if (!$is_completing) {
        return;
    }

    try {
        // Clear setup flags
        delete_option('assessment_360_do_setup');
        delete_transient('assessment_360_activation_redirect');
        
        // Set completion flags
        update_option('assessment_360_setup_completed', 'yes');
        update_option('assessment_360_setup_completed_at', current_time('mysql'));
        
        error_log('360 Assessment setup completed at: ' . current_time('mysql'));

        assessment_360_safe_redirect(
            add_query_arg(
                'message',
                urlencode('Setup completed successfully'),
                admin_url('admin.php?page=assessment-360')
            )
        );

    } catch (Exception $e) {
        error_log('360 Assessment setup completion error: ' . $e->getMessage());
        assessment_360_setup_redirect('complete', $e->getMessage(), true);
    }
}
add_action('admin_init', 'assessment_360_complete_setup');
add_action('admin_post_complete_setup', 'assessment_360_complete_setup');

/**
 * Handle Settings Save
 */
function handle_setup_settings() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // Verify nonce
    if (!isset($_POST['setup_settings_nonce']) || 
        !wp_verify_nonce($_POST['setup_settings_nonce'], 'save_setup_settings')) {
        wp_die('Invalid security token');
    }

    try {
        // Handle logo upload
        if (!empty($_FILES['org_logo']['tmp_name'])) {
            $logo_result = assessment_360_handle_logo_upload($_FILES['org_logo']);
            if (is_wp_error($logo_result)) {
                throw new Exception($logo_result->get_error_message());
            }
        }

        // Save organization name (required)
        if (empty($_POST['org_name'])) {
            throw new Exception('Organization name is required.');
        }
        update_option('assessment_360_organization_name', sanitize_text_field($_POST['org_name']));

        // Save email templates
        $email_settings = [
            'welcome_email_subject' => [
                'default' => 'Welcome to {org_name} 360° Assessment',
                'sanitize' => 'sanitize_text_field'
            ],
            'welcome_email_body' => [
                'default' => "Dear {first_name},\n\nWelcome to the {org_name} 360° Assessment System!",
                'sanitize' => 'wp_kses_post'
            ],
            'reminder_email_subject' => [
                'default' => 'Reminder: Complete Your 360° Assessment',
                'sanitize' => 'sanitize_text_field'
            ],
            'reminder_email_body' => [
                'default' => "Dear {first_name},\n\nThis is a reminder about your pending assessment.",
                'sanitize' => 'wp_kses_post'
            ]
        ];

        foreach ($email_settings as $key => $setting) {
            if (isset($_POST[$key])) {
                $value = $_POST[$key];
                $sanitized_value = $setting['sanitize']($value);
                update_option('assessment_360_' . $key, $sanitized_value);
            }
        }

        assessment_360_setup_redirect('groups', 'Settings saved successfully');

    } catch (Exception $e) {
        assessment_360_setup_redirect('settings', $e->getMessage(), true);
    }
}
add_action('admin_post_save_setup_settings', 'handle_setup_settings');

/**
 * Handle Group Save in Setup Wizard
 */
function handle_setup_group_save() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // Verify nonce
    if (!isset($_POST['setup_group_nonce']) || 
        !wp_verify_nonce($_POST['setup_group_nonce'], 'save_setup_group')) {
        wp_die('Invalid security token');
    }

    try {
        $group_manager = Assessment_360_Group_Manager::get_instance();
        $groups = isset($_POST['groups']) ? (array)$_POST['groups'] : [];
        $success_count = 0;
        $errors = [];

        // Get existing groups for comparison
        $existing_groups = $group_manager->get_all_groups();
        $existing_names = array_map(function($group) {
            return strtolower($group->group_name);
        }, $existing_groups);

        $new_names = [];
        foreach ($groups as $group) {
            if (empty($group['name'])) continue;
            
            $name = strtolower(sanitize_text_field($group['name']));
            
            // Skip if it's an existing group (don't count as error)
            if (in_array($name, $existing_names)) {
                $success_count++; // Count as success since it exists
                continue;
            }
            
            // Only check for duplicates within new submissions
            if (in_array($name, $new_names)) {
                throw new Exception(sprintf('Duplicate group name: "%s"', $group['name']));
            }
            
            $new_names[] = $name;
        }

        // Create only new groups
        foreach ($groups as $group) {
            if (empty($group['name'])) {
                continue;
            }

            $name = strtolower(sanitize_text_field($group['name']));
            
            // Skip if group already exists
            if (in_array($name, $existing_names)) {
                continue;
            }

            $result = $group_manager->create_group([
                'group_name' => sanitize_text_field($group['name']),
                'description' => sanitize_textarea_field($group['description'] ?? ''),
                'is_department' => isset($group['is_department']) ? 1 : 0
            ]);

            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
            } else {
                $success_count++;
            }
        }

        // Clean output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Redirect to next step
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'assessment-360-setup',
                    'step' => 'positions',
                    'message' => $success_count > 0 ? 
                        sprintf(
                            '%d group%s processed successfully',
                            $success_count,
                            $success_count > 1 ? 's' : ''
                        ) : 'No new groups needed'
                ),
                admin_url('admin.php')
            )
        );
        exit;

    } catch (Exception $e) {
        error_log('360 Assessment Setup - Group Save Error: ' . $e->getMessage());
        
        // Clean output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'assessment-360-setup',
                    'step' => 'groups',
                    'error' => $e->getMessage()
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }
}
add_action('admin_post_save_setup_group', 'handle_setup_group_save');

/**
 * Handle Position Save in Setup Wizard
 */
function handle_setup_position_save() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // Verify nonce
    if (!isset($_POST['setup_position_nonce']) || 
        !wp_verify_nonce($_POST['setup_position_nonce'], 'save_setup_position')) {
        wp_die('Invalid security token');
    }

    try {
        // Clean any existing output
        while (ob_get_level()) {
            ob_end_clean();
        }

        $position_manager = Assessment_360_Position::get_instance();
        $positions = isset($_POST['positions']) ? (array)$_POST['positions'] : [];
        $success_count = 0;
        $errors = [];

        // Get existing positions for comparison
        $existing_positions = $position_manager->get_all_positions();
        $existing_names = array_map(function($pos) {
            return strtolower($pos->name);
        }, $existing_positions);

        $new_names = [];
        foreach ($positions as $position) {
            if (empty($position['name'])) continue;
            
            $name = strtolower(sanitize_text_field($position['name']));
            
            // Skip if it's an existing position (don't count as error)
            if (in_array($name, $existing_names)) {
                $success_count++; // Count as success since it exists
                continue;
            }
            
            // Only check for duplicates within new submissions
            if (in_array($name, $new_names)) {
                throw new Exception(sprintf('Duplicate position name: "%s"', $position['name']));
            }
            
            $new_names[] = $name;
        }

        // Create only new positions
        foreach ($positions as $position) {
            if (empty($position['name'])) {
                continue;
            }

            $name = strtolower(sanitize_text_field($position['name']));
            
            // Skip if position already exists
            if (in_array($name, $existing_names)) {
                continue;
            }

            $result = $position_manager->create_position([
                'name' => sanitize_text_field($position['name']),
                'description' => sanitize_textarea_field($position['description'] ?? '')
            ]);

            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
            } else {
                $success_count++;
            }
        }

        // Prepare redirect URL
        $redirect_url = add_query_arg(
            array(
                'page' => 'assessment-360-setup',
                'step' => 'complete',
                'message' => urlencode($success_count > 0 ? 
                    sprintf(
                        '%d position%s processed successfully',
                        $success_count,
                        $success_count > 1 ? 's' : ''
                    ) : 'No new positions needed'
                )
            ),
            admin_url('admin.php')
        );

        // Remove any filters that might interfere with redirect
        remove_all_filters('wp_redirect');
        remove_all_filters('wp_redirect_status');

        // Ensure headers haven't been sent
        if (!headers_sent()) {
            wp_redirect($redirect_url);
            exit;
        } else {
            // Fallback if headers were sent
            echo '<script type="text/javascript">';
            echo 'window.location.href="' . esc_js($redirect_url) . '";';
            echo '</script>';
            echo '<noscript>';
            echo '<meta http-equiv="refresh" content="0;url=' . esc_url($redirect_url) . '">';
            echo '</noscript>';
            echo 'If you are not redirected automatically, please <a href="' . esc_url($redirect_url) . '">click here</a>.';
            exit;
        }

    } catch (Exception $e) {
        error_log('360 Assessment Setup - Position Save Error: ' . $e->getMessage());
        
        // Clean any output
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Redirect back to positions step with error
        $error_url = add_query_arg(
            array(
                'page' => 'assessment-360-setup',
                'step' => 'positions',
                'error' => urlencode($e->getMessage())
            ),
            admin_url('admin.php')
        );

        if (!headers_sent()) {
            wp_redirect($error_url);
            exit;
        } else {
            echo '<script type="text/javascript">';
            echo 'window.location.href="' . esc_js($error_url) . '";';
            echo '</script>';
            echo '<noscript>';
            echo '<meta http-equiv="refresh" content="0;url=' . esc_url($error_url) . '">';
            echo '</noscript>';
            echo 'If you are not redirected automatically, please <a href="' . esc_url($error_url) . '">click here</a>.';
            exit;
        }
    }
}
add_action('admin_post_save_setup_position', 'handle_setup_position_save');
