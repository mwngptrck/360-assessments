<?php
if (!defined('ABSPATH')) exit;

// START SESSION for wizard data persistence
add_action('init', function() {
    if (!session_id() && !headers_sent()) session_start();
}, 1);

// Helper: store/retrieve wizard data
function assessment_360_set_wizard_data($step, $data) {
    $_SESSION['assessment_360_wizard'][$step] = $data;
}
function assessment_360_get_wizard_data($step) {
    return isset($_SESSION['assessment_360_wizard'][$step]) ? $_SESSION['assessment_360_wizard'][$step] : [];
}

// Helper: set error message
function assessment_360_set_wizard_error($message) {
    $_SESSION['assessment_360_wizard_error'] = $message;
}
function assessment_360_get_wizard_error() {
    if (!empty($_SESSION['assessment_360_wizard_error'])) {
        $msg = $_SESSION['assessment_360_wizard_error'];
        unset($_SESSION['assessment_360_wizard_error']);
        return $msg;
    }
    return '';
}

// GROUPS STEP HANDLER
function handle_save_setup_groups() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('save_setup_groups', 'setup_groups_nonce');

    // Accept multiple group names/descriptions
    $group_names = isset($_POST['group_name']) ? array_map('sanitize_text_field', (array)$_POST['group_name']) : [];
    $group_descs = isset($_POST['group_desc']) ? array_map('sanitize_text_field', (array)$_POST['group_desc']) : [];
    $is_departments = isset($_POST['is_department']) ? $_POST['is_department'] : [];

    // Remove blanks and trim
    $entries = [];
    foreach ($group_names as $i => $name) {
        $name = trim($name);
        if ($name !== '') {
            $entries[] = [
                'name' => $name,
                'desc' => isset($group_descs[$i]) ? $group_descs[$i] : '',
                'is_department' => isset($is_departments[$i]) ? 1 : 0
            ];
        }
    }

    // Check for duplicates in submitted
    $lower_names = array_map('strtolower', array_column($entries, 'name'));
    if (count($lower_names) !== count(array_unique($lower_names))) {
        assessment_360_set_wizard_error('Duplicate group names detected in your submission.');
        assessment_360_set_wizard_data('groups', $entries);
        wp_redirect(add_query_arg(['page'=>'assessment-360-setup','step'=>'groups'], admin_url('admin.php')));
        exit;
    }

    // Check for duplicates in DB
    $group_manager = Assessment_360_Group_Manager::get_instance();
    $existing_groups = $group_manager->get_all_groups();
    $existing_names = array_map(function($g){return strtolower($g->group_name);}, $existing_groups);

    // Save to DB if not exists, skip error if exists
    foreach ($entries as $entry) {
        if (!in_array(strtolower($entry['name']), $existing_names)) {
            $group_manager->create_group([
                'group_name' => $entry['name'],
                'description' => $entry['desc'],
                'is_department' => $entry['is_department'],
            ]);
        }
        // If exists, just skip and continue to next step
    }
    assessment_360_set_wizard_data('groups', $entries);
    // Next step
    wp_redirect(admin_url('admin.php?page=assessment-360-setup&step=positions'));
    exit;
}
add_action('admin_post_save_setup_groups', 'handle_save_setup_groups');

// POSITIONS STEP HANDLER (similar logic)
function handle_save_setup_positions() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('save_setup_positions', 'setup_positions_nonce');

    $pos_names = isset($_POST['position_name']) ? array_map('sanitize_text_field', (array)$_POST['position_name']) : [];
    $pos_descs = isset($_POST['position_desc']) ? array_map('sanitize_text_field', (array)$_POST['position_desc']) : [];

    $entries = [];
    foreach ($pos_names as $i => $name) {
        $name = trim($name);
        if ($name !== '') {
            $entries[] = [
                'name' => $name,
                'desc' => isset($pos_descs[$i]) ? $pos_descs[$i] : ''
            ];
        }
    }

    $lower_names = array_map('strtolower', array_column($entries, 'name'));
    if (count($lower_names) !== count(array_unique($lower_names))) {
        assessment_360_set_wizard_error('Duplicate position names detected in your submission.');
        assessment_360_set_wizard_data('positions', $entries);
        wp_redirect(add_query_arg(['page'=>'assessment-360-setup','step'=>'positions'], admin_url('admin.php')));
        exit;
    }

    $position_manager = Assessment_360_Position::get_instance();
    $existing_positions = $position_manager->get_all_positions(false);
    $existing_names = array_map(function($p){return strtolower($p->name);}, $existing_positions);

    foreach ($entries as $entry) {
        if (!in_array(strtolower($entry['name']), $existing_names)) {
            $position_manager->create_position([
                'name' => $entry['name'],
                'description' => $entry['desc']
            ]);
        }
        // If exists, just skip and continue to next step
    }
    assessment_360_set_wizard_data('positions', $entries);
    wp_redirect(admin_url('admin.php?page=assessment-360-setup&step=complete'));
    exit;
}
add_action('admin_post_save_setup_positions', 'handle_save_setup_positions');

// SETTINGS STEP HANDLER (add session persistence/back)
function handle_save_setup_settings() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('save_setup_settings', 'setup_settings_nonce');

    $data = [
        'org_name' => isset($_POST['org_name']) ? sanitize_text_field($_POST['org_name']) : '',
        'org_logo' => isset($_POST['org_logo']) ? esc_url_raw($_POST['org_logo']) : '',
        'welcome_email' => isset($_POST['welcome_email']) ? wp_kses_post($_POST['welcome_email']) : '',
        'reminder_email' => isset($_POST['reminder_email']) ? wp_kses_post($_POST['reminder_email']) : '',
    ];
    assessment_360_set_wizard_data('settings', $data);

    // Save settings as before...
    if ($data['org_name']) update_option('assessment_360_organization_name', $data['org_name']);
    if ($data['org_logo']) update_option('assessment_360_organization_logo', $data['org_logo']);
    if ($data['welcome_email']) update_option('assessment_360_welcome_email', $data['welcome_email']);
    if ($data['reminder_email']) update_option('assessment_360_reminder_email', $data['reminder_email']);

    wp_redirect(add_query_arg(['page'=>'assessment-360-setup','step'=>'groups'], admin_url('admin.php')));
    exit;
}
add_action('admin_post_save_setup_settings', 'handle_save_setup_settings');