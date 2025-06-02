<?php
if (!defined('ABSPATH')) exit;

/**
 * Topic Management Functions
 */
function handle_save_topic() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('save_topic_nonce');

    $topic_manager = Assessment_360_Topic::get_instance();
    $data = [
        'name' => sanitize_text_field($_POST['topic_name'])
    ];

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
add_action('admin_post_save_topic', 'handle_save_topic');

function handle_delete_topic() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('delete_topic');

    try {
        $topic_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$topic_id) {
            throw new Exception('Invalid topic ID');
        }

        $topic_manager = Assessment_360_Topic::get_instance();
        
        // Check if topic has sections
        if ($topic_manager->topic_has_sections($topic_id)) {
            throw new Exception('Cannot delete topic with existing sections');
        }

        $result = $topic_manager->delete_topic($topic_id);
        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        wp_redirect(add_query_arg(
            'message',
            urlencode('Topic deleted successfully'),
            admin_url('admin.php?page=assessment-360-forms#topics')
        ));
        exit;

    } catch (Exception $e) {
        wp_redirect(add_query_arg(
            'error',
            urlencode($e->getMessage()),
            admin_url('admin.php?page=assessment-360-forms#topics')
        ));
        exit;
    }
}
add_action('admin_post_delete_topic', 'handle_delete_topic');
