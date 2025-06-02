<?php
if (!defined('ABSPATH')) exit;

/**
 * Group Management Functions
 */
function handle_save_group() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // Verify nonce
    if (!isset($_POST['save_group_nonce']) || 
        !wp_verify_nonce($_POST['save_group_nonce'], 'save_group_nonce')) {
        wp_die('Invalid security token');
    }

    try {
        $group_manager = Assessment_360_Group_Manager::get_instance();
        $is_department = isset($_POST['is_department']) ? 1 : 0;
        
        $data = [
            'group_name' => sanitize_text_field($_POST['group_name']),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'is_department' => $is_department
        ];

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

        wp_safe_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'groups',
            'message' => urlencode($message)
        ], admin_url('admin.php')));
        exit;

    } catch (Exception $e) {
        wp_safe_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'groups',
            'error' => urlencode($e->getMessage())
        ], admin_url('admin.php')));
        exit;
    }
}
add_action('admin_post_save_group', 'handle_save_group');

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
    
    // Validation checks
    $group = $group_manager->get_group($group_id);
    if (!$group || 
        $group_manager->is_system_group($group->group_name) || 
        $group_manager->group_has_users($group_id)) {
        
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'groups',
            'error' => urlencode('Cannot delete this group')
        ], admin_url('admin.php')));
        exit;
    }

    $result = $group_manager->delete_group($group_id);
    
    wp_redirect(add_query_arg([
        'page' => 'assessment-360-user-management',
        'tab' => 'groups',
        'message' => is_wp_error($result) ? 
            urlencode($result->get_error_message()) : 
            urlencode('Group deleted successfully')
    ], admin_url('admin.php')));
    exit;
}
add_action('admin_post_delete_group', 'handle_delete_group');
