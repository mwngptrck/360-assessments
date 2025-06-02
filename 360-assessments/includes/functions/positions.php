<?php
if (!defined('ABSPATH')) exit;

/**
 * Position Management Functions
 */
function handle_save_position() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // Verify nonce
    if (!isset($_POST['save_position_nonce']) || 
        !wp_verify_nonce($_POST['save_position_nonce'], 'save_position_nonce')) {
        wp_die('Invalid security token');
    }

    try {
        $position_manager = Assessment_360_Position::get_instance();
        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description'] ?? '')
        ];

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

        wp_safe_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'positions',
            'message' => urlencode($message)
        ], admin_url('admin.php')));
        exit;

    } catch (Exception $e) {
        wp_safe_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'positions',
            'error' => urlencode($e->getMessage())
        ], admin_url('admin.php')));
        exit;
    }
}
add_action('admin_post_save_position', 'handle_save_position');

function handle_delete_position() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('delete_position');

    $position_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$position_id) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'positions',
            'error' => urlencode('Invalid position ID')
        ], admin_url('admin.php')));
        exit;
    }

    $position_manager = Assessment_360_Position::get_instance();
    
    // Check if position has users
    if ($position_manager->position_has_users($position_id)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-user-management',
            'tab' => 'positions',
            'error' => urlencode('Cannot delete position with assigned users')
        ], admin_url('admin.php')));
        exit;
    }

    $result = $position_manager->delete_position($position_id);
    
    wp_redirect(add_query_arg([
        'page' => 'assessment-360-user-management',
        'tab' => 'positions',
        'message' => is_wp_error($result) ? 
            urlencode($result->get_error_message()) : 
            urlencode('Position deleted successfully')
    ], admin_url('admin.php')));
    exit;
}
add_action('admin_post_delete_position', 'handle_delete_position');
