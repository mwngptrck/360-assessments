<?php
if (!defined('ABSPATH')) exit;

/**
 * Question Management Functions
 */

/**
 * Handle Question Save
 */
function handle_save_question() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // Verify nonce
    if (!isset($_POST['save_question_nonce']) || 
        !wp_verify_nonce($_POST['save_question_nonce'], 'save_question_nonce')) {
        wp_die('Invalid security token');
    }

    try {
        $question_manager = Assessment_360_Question::get_instance();
        
        // Validate required fields
        $required_fields = ['question_text', 'section_id'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception(ucfirst(str_replace('_', ' ', $field)) . ' is required.');
            }
        }

        $data = [
            'question_text' => sanitize_textarea_field($_POST['question_text']),
            'section_id' => intval($_POST['section_id']),
            'question_order' => isset($_POST['question_order']) ? intval($_POST['question_order']) : 0,
            'is_mandatory' => isset($_POST['is_mandatory']) ? 1 : 0,
            'has_comment_box' => isset($_POST['has_comment_box']) ? 1 : 0,
            'status' => 'active'
        ];

        if (isset($_POST['id'])) {
            $result = $question_manager->update_question(intval($_POST['id']), $data);
            $message = 'Question updated successfully.';
        } else {
            $result = $question_manager->create_question($data);
            $message = 'Question created successfully.';
        }

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'tab' => 'questions',
            'message' => urlencode($message)
        ], admin_url('admin.php')));
        exit;

    } catch (Exception $e) {
        wp_safe_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'tab' => 'questions',
            'error' => urlencode($e->getMessage())
        ], admin_url('admin.php')));
        exit;
    }
}
add_action('admin_post_save_question', 'handle_save_question');

/**
 * Handle Question Delete
 */
function handle_delete_question() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('delete_question');

    try {
        $question_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$question_id) {
            throw new Exception('Invalid question ID');
        }

        $question_manager = Assessment_360_Question::get_instance();
        
        // Check if question has responses
        if ($question_manager->question_has_responses($question_id)) {
            throw new Exception('Cannot delete question with existing responses');
        }

        $result = $question_manager->delete_question($question_id);
        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'tab' => 'questions',
            'message' => urlencode('Question deleted successfully')
        ], admin_url('admin.php')));
        exit;

    } catch (Exception $e) {
        wp_safe_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'tab' => 'questions',
            'error' => urlencode($e->getMessage())
        ], admin_url('admin.php')));
        exit;
    }
}
add_action('admin_post_delete_question', 'handle_delete_question');

/**
 * Handle Question Order Update
 */
function handle_update_question_order() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('update_question_order');

    try {
        $question_manager = Assessment_360_Question::get_instance();
        $questions = isset($_POST['questions']) ? (array)$_POST['questions'] : [];
        
        if (empty($questions)) {
            throw new Exception('No questions provided');
        }

        foreach ($questions as $order => $question_id) {
            $result = $question_manager->update_question(
                intval($question_id),
                ['question_order' => intval($order)]
            );

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
        }

        wp_send_json_success('Question order updated successfully');

    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
add_action('wp_ajax_update_question_order', 'handle_update_question_order');

/**
 * Handle Question Status Update
 */
function handle_update_question_status() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('update_question_status');

    try {
        $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
        $new_status = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';

        if (!$question_id || !in_array($new_status, ['active', 'inactive'])) {
            throw new Exception('Invalid parameters');
        }

        $question_manager = Assessment_360_Question::get_instance();
        $result = $question_manager->update_question($question_id, ['status' => $new_status]);

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'tab' => 'questions',
            'message' => urlencode('Question status updated successfully')
        ], admin_url('admin.php')));
        exit;

    } catch (Exception $e) {
        wp_safe_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'tab' => 'questions',
            'error' => urlencode($e->getMessage())
        ], admin_url('admin.php')));
        exit;
    }
}
add_action('admin_post_update_question_status', 'handle_update_question_status');
