<?php
/**
 * 360 Assessments - Questions, Sections, Topics, Forms Functions & Handlers
 * 
 * Handles all logic for topics, sections, questions, and assessment form structure.
 * Migrated from the main plugin file.
 */

/* Topic Handlers */
add_action('admin_post_save_topic', 'handle_save_topic');
function handle_save_topic() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    check_admin_referer('save_topic_nonce');
    $topic_manager = Assessment_360_Topic::get_instance();
    $data = array(
        'name' => sanitize_text_field($_POST['topic_name'])
    );
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

add_action('admin_post_delete_topic', 'handle_delete_topic');
function handle_delete_topic() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    check_admin_referer('delete_topic', 'delete_topic_nonce');
    $topic_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$topic_id) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'error' => urlencode('Invalid topic ID')
        ], admin_url('admin.php')));
        exit;
    }
    $topic_manager = Assessment_360_Topic::get_instance();
    $result = $topic_manager->delete_topic($topic_id);
    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }
    wp_redirect(add_query_arg([
        'page' => 'assessment-360-forms',
        'message' => urlencode('Topic deleted successfully')
    ], admin_url('admin.php')));
    exit;
}

/* Section Handlers */
add_action('admin_post_save_section', 'handle_save_section');
function handle_save_section() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    check_admin_referer('save_section_nonce');
    $section_manager = Assessment_360_Section::get_instance();
    $data = array(
        'name' => sanitize_text_field($_POST['section_name']),
        'topic_id' => intval($_POST['topic_id']),
        'position_ids' => isset($_POST['position_ids']) ? array_map('intval', $_POST['position_ids']) : []
    );
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

add_action('admin_post_delete_section', 'handle_delete_section');
function handle_delete_section() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    check_admin_referer('delete_section', 'delete_section_nonce');
    $section_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$section_id) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'error' => urlencode('Invalid section ID')
        ], admin_url('admin.php')));
        exit;
    }
    $section_manager = Assessment_360_Section::get_instance();
    $result = $section_manager->delete_section($section_id);
    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }
    wp_redirect(add_query_arg([
        'page' => 'assessment-360-forms',
        'message' => urlencode('Section deleted successfully')
    ], admin_url('admin.php')));
    exit;
}

/* Question Handlers */
add_action('admin_post_save_question', 'handle_save_question');
function handle_save_question() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    check_admin_referer('save_question_nonce');
    $required_fields = array('question_text', 'section_id', 'position_id');
    $missing_fields = array();
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    if (!empty($missing_fields)) {        
        wp_redirect(add_query_arg(
            'error',
            'Missing required fields: ' . implode(', ', $missing_fields),
            wp_get_referer()
        ));
        exit;
    }
    $question_manager = Assessment_360_Question::get_instance();
    $data = array(
        'question_text' => sanitize_textarea_field($_POST['question_text']),
        'section_id' => intval($_POST['section_id']),
        'position_id' => intval($_POST['position_id']),
        'is_mandatory' => isset($_POST['is_mandatory']) ? 1 : 0,
        'has_comment_box' => isset($_POST['has_comment_box']) ? 1 : 0
    );
    if (empty($data['question_text'])) {
        wp_redirect(add_query_arg('error', 'Question text is required.', wp_get_referer()));
        exit;
    }
    if ($data['section_id'] <= 0) {
        wp_redirect(add_query_arg('error', 'Valid section is required.', wp_get_referer()));
        exit;
    }
    if ($data['position_id'] <= 0) {
        wp_redirect(add_query_arg('error', 'Valid position is required.', wp_get_referer()));
        exit;
    }
    try {
        if (isset($_POST['id'])) {
            $result = $question_manager->update_question(intval($_POST['id']), $data);
            $message = 'Question updated successfully.';
        } else {
            $result = $question_manager->create_question($data);
            $message = 'Question created successfully.';
        }
        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('error', $result->get_error_message(), wp_get_referer()));
        } else {
            wp_redirect(add_query_arg(
                'message',
                $message,
                admin_url('admin.php?page=assessment-360-forms#questions')
            ));
        }
    } catch (Exception $e) {
        wp_redirect(add_query_arg('error', 'Error saving question: ' . $e->getMessage(), wp_get_referer()));
    }
    exit;
}

add_action('admin_post_delete_question', 'handle_delete_question');
function handle_delete_question() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    check_admin_referer('delete_question');
    $question_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$question_id) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'error' => urlencode('Invalid question ID')
        ], admin_url('admin.php')));
        exit;
    }
    $question_manager = Assessment_360_Question::get_instance();
    $question = $question_manager->get_question($question_id);
    if (!$question) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'error' => urlencode('Question not found')
        ], admin_url('admin.php')));
        exit;
    }
    if ($question_manager->question_has_responses($question_id)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'error' => urlencode('Cannot delete question that has responses')
        ], admin_url('admin.php')));
        exit;
    }
    $result = $question_manager->delete_question($question_id);
    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-forms',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }
    wp_redirect(add_query_arg([
        'page' => 'assessment-360-forms',
        'message' => urlencode('Question deleted successfully')
    ], admin_url('admin.php')));
    exit;
}

/* Form Deletions for topics, sections, questions via GET (admin_init) */
add_action('admin_init', 'handle_form_deletions');
function handle_form_deletions() {
    if (!isset($_GET['action']) || !isset($_GET['page']) || $_GET['page'] !== 'assessment-360-forms') {
        return;
    }
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    $action = $_GET['action'];
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$id) {
        return;
    }
    switch ($action) {
        case 'delete_topic':
            if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_topic_' . $id)) {
                wp_die('Security check failed');
            }
            $result = Assessment_360_Topic::get_instance()->delete_topic($id);
            $redirect = 'topics';
            break;
        case 'delete_section':
            if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_section_' . $id)) {
                wp_die('Security check failed');
            }
            $result = Assessment_360_Section::get_instance()->delete_section($id);
            $redirect = 'sections';
            break;
        case 'delete_question':
            if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_question_' . $id)) {
                wp_die('Security check failed');
            }
            $result = Assessment_360_Question::get_instance()->delete_question($id);
            $redirect = 'questions';
            break;
        default:
            return;
    }
    if (is_wp_error($result)) {
        wp_redirect(add_query_arg(
            'error', 
            $result->get_error_message(), 
            admin_url("admin.php?page=assessment-360-forms#{$redirect}")
        ));
    } else {
        wp_redirect(add_query_arg(
            'message', 
            ucfirst(substr($action, 7)) . ' deleted successfully.', 
            admin_url("admin.php?page=assessment-360-forms#{$redirect}")
        ));
    }
    exit;
}

/**
 * ===========================
 * Suggestions for Improvement
 * ===========================
 * - Add AJAX endpoints for CRUD operations for better UX.
 * - Add logging for all form structure changes.
 * - Add validation for relationships (e.g., cannot delete section with questions).
 * - Add automated tests for all handlers.
 */