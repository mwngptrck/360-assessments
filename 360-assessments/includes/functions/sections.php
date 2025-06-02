<?php
if (!defined('ABSPATH')) exit;

/**
 * Section Management Functions
 */
function handle_save_section() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('save_section_nonce');

    $section_manager = Assessment_360_Section::get_instance();
    $data = [
        'name' => sanitize_text_field($_POST['section_name']),
        'topic_id' => intval($_POST['topic_id']),
        'position_ids' => isset($_POST['position_ids']) ? 
                         array_map('intval', $_POST['position_ids']) : []
    ];

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
add_action('admin_post_save_section', 'handle_save_section');

function handle_delete_section() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('delete_section');

    try {
        $section_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$section_id) {
            throw new Exception('Invalid section ID');
        }

        $section_manager = Assessment_360_Section::get_instance();
        
        // Check if section has questions
        if ($section_manager->section_has_questions($section_id)) {
            throw new Exception('Cannot delete section with existing questions');
        }

        $result = $section_manager->delete_section($section_id);
        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        wp_redirect(add_query_arg(
            'message',
            urlencode('Section deleted successfully'),
            admin_url('admin.php?page=assessment-360-forms#sections')
        ));
        exit;

    } catch (Exception $e) {
        wp_redirect(add_query_arg(
            'error',
            urlencode($e->getMessage()),
            admin_url('admin.php?page=assessment-360-forms#sections')
        ));
        exit;
    }
}
add_action('admin_post_delete_section', 'handle_delete_section');
