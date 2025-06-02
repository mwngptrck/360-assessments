<?php
if (!defined('ABSPATH')) exit;

/**
 * User Management Functions
 */
function handle_save_user() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('save_user_nonce', 'save_user_nonce');

    try {
        $user_manager = Assessment_360_User_Manager::get_instance();
        $group_manager = Assessment_360_Group_Manager::get_instance();
        
        $group_id = intval($_POST['group_id']);
        $group = $group_manager->get_group($group_id);
        $is_peers = $group && strtolower($group->group_name) === 'peers';

        if ($is_peers && empty($_POST['department_id'])) {
            throw new Exception('Department is required for Peers group members.');
        }

        $data = [
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'email' => sanitize_email($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'group_id' => $group_id,
            'department_id' => $is_peers ? intval($_POST['department_id']) : null,
            'position_id' => $is_peers && !empty($_POST['position_id']) ? 
                           intval($_POST['position_id']) : null,
            'status' => 'active'
        ];

        foreach (['first_name', 'last_name', 'email', 'group_id'] as $field) {
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
            $assessors = isset($_POST['assessors']) ? 
                        array_map('intval', $_POST['assessors']) : [];
            $result = $user_manager->update_user_relationships($user_id, $assessors);
            
            if (is_wp_error($result)) {
                throw new Exception('Failed to update assessors: ' . $result->get_error_message());
            }
        } else {
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
add_action('admin_post_save_user', 'handle_save_user');

function handle_update_user_status() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    check_admin_referer('user_status_' . $user_id);

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
add_action('admin_post_update_user_status', 'handle_update_user_status');


/**
 * Get current user data
 *
 * @return object|null User data or null if not found
 */
function assessment_360_get_current_user() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $user_manager = Assessment_360_User_Manager::get_instance();
    return $user_manager->get_user($_SESSION['user_id']);
}
