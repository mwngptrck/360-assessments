<?php
/**
 * 360 Assessments User, Position, and Group Functions & Handlers
 * 
 * This file contains all functions and handlers related to:
 * - Users (create, update, delete, list, status)
 * - Positions (create, update, delete, restore)
 * - Groups (create, update, delete, restore)
 * - User relationships (e.g., assign assessors, user-group linking)
 * 
 * All logic is migrated from the original main plugin file for 360-degree Assessments.
 * 
 * Assumes all relevant classes (Assessment_360_User_Manager, Assessment_360_Group_Manager, Assessment_360_Position) are loaded.
 */

/* User Handlers */
add_action('admin_post_save_user', 'handle_save_user');
function handle_save_user() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    if (!isset($_POST['save_user_nonce']) || !wp_verify_nonce($_POST['save_user_nonce'], 'save_user_nonce')) {
        wp_die('Invalid security token');
    }
    try {
        $user_manager = Assessment_360_User_Manager::get_instance();
        $group_manager = Assessment_360_Group_Manager::get_instance();
        // Get current active assessment
        global $wpdb;
        $assessment_id = $wpdb->get_var(
            "SELECT id FROM {$wpdb->prefix}360_assessments WHERE status = 'active' ORDER BY id DESC LIMIT 1"
        );
        if (!$assessment_id) {
            throw new Exception('No active assessment found.');
        }
        $group_id = intval($_POST['group_id']);
        $group = $group_manager->get_group($group_id);
        $is_peers = $group && strtolower($group->group_name) === 'peers';
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
        $required_fields = ['first_name', 'last_name', 'email', 'group_id'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new Exception("$field is required");
            }
        }
        if (isset($_POST['id'])) {
            $user_id = intval($_POST['id']);
            $result = $user_manager->update_user($user_id, $data);
            $message = 'User updated successfully';
        } else {
            $data['password'] = wp_generate_password(12, true, true);
            $result = $user_manager->create_user($data);
            $message = 'User created successfully';
            $user_id = $result;
        }
        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }
        if ($is_peers) {
            $assessors = isset($_POST['assessors']) ? array_map('intval', $_POST['assessors']) : [];
            $result = $user_manager->update_user_relationships($user_id, $assessors, $assessment_id);
            if (is_wp_error($result)) {
                throw new Exception('Failed to update assessors: ' . $result->get_error_message());
            }
        } else {
            $user_manager->update_user_relationships($user_id, [], $assessment_id);
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

add_action('admin_post_delete_user', 'handle_delete_user');
function handle_delete_user() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    check_admin_referer('delete_user');
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

add_action('admin_post_disable_user', 'handle_disable_user');
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
    $result = $user_manager->update_user_status($user_id, 'inactive');
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

add_action('admin_post_enable_user', 'handle_enable_user');
function handle_enable_user() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    check_admin_referer('enable_user', 'enable_user_nonce');
    $user_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $current_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'inactive';
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
    $result = $user_manager->update_user_status($user_id, 'active');
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
        'message' => urlencode('User enabled successfully')
    ], admin_url('admin.php')));
    exit;
}

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

/* Position Handlers */
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
    $pos = $position->get_position($position_id);
    if (!$pos) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'positions',
            'error' => urlencode('Position not found.')
        ], admin_url('admin.php')));
        exit;
    }
    if ($position->is_position_in_use($position_id)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'positions',
            'error' => urlencode('Cannot delete position that is assigned to users. Please reassign users first.')
        ], admin_url('admin.php')));
        exit;
    }
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
    $pos = $position->get_position($position_id);
    if (!$pos) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'positions',
            'error' => urlencode('Position not found.')
        ], admin_url('admin.php')));
        exit;
    }
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

add_action('admin_post_restore_position', 'handle_restore_position');
function handle_restore_position() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
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

/* Group Handlers */
add_action('admin_post_save_group', 'handle_save_group');
function handle_save_group() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    check_admin_referer('save_group_nonce');
    try {
        $group_manager = Assessment_360_Group_Manager::get_instance();
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
            urlencode($e->get_message()),
            wp_get_referer()
        ));
        exit;
    }
}

add_action('admin_post_delete_group', 'handle_delete_group');
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
    $group = $group_manager->get_group($group_id);
    if (!$group) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'groups',
            'error' => urlencode('Group not found')
        ], admin_url('admin.php')));
        exit;
    }
    if ($group_manager->is_system_group($group->group_name)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'groups',
            'error' => urlencode('Cannot delete system groups')
        ], admin_url('admin.php')));
        exit;
    }
    if ($group_manager->group_has_users($group_id)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'groups',
            'error' => urlencode('Cannot delete group that has users assigned to it')
        ], admin_url('admin.php')));
        exit;
    }
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

/* Session utility for frontend user logout */
function assessment_360_logout() {
    if (session_id()) {
        session_destroy();
    }
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
}

/**
 * ===========================
 * Suggestions for Improvement
 * ===========================
 * - Consider using custom capabilities for more granular access control.
 * - Add input validation and error handling for each field, not just required ones.
 * - Consider WP background processing for bulk user actions for large data sets.
 * - Use AJAX for user status/bulk actions to improve UX.
 * - Add better logging for user actions (creation, deletion, updates).
 * - Abstract notices/messages for i18n support.
 * - Add unit tests for all handlers.
 */