<?php
/**
 * 360 Assessments - Assessment Management Functions & Handlers
 * 
 * Handles all logic for creating, updating, deleting, restoring, enabling, disabling, and completing assessments.
 * Also includes assessment form submission and related actions.
 */

/* Assessment CRUD and Status Handlers */

// Save assessment (create or update)
add_action('init', 'handle_save_assessment');
function handle_save_assessment() {
    if (!isset($_POST['action']) || $_POST['action'] !== 'save_assessment') {
        return;
    }
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    try {
        if (!isset($_POST['_wpnonce']) || 
            !wp_verify_nonce($_POST['_wpnonce'], 'save_assessment_nonce')) {
            throw new Exception('Invalid security token');
        }
        $data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date' => sanitize_text_field($_POST['end_date'] ?? '')
        );
        if (empty($data['name']) || empty($data['start_date']) || empty($data['end_date'])) {
            throw new Exception('Please fill in all required fields.');
        }
        $start_date = strtotime($data['start_date']);
        $end_date = strtotime($data['end_date']);
        $today = strtotime('today');
        if (!isset($_POST['id']) && $start_date < $today) {
            throw new Exception('Start date cannot be earlier than today.');
        }
        if ($end_date < $start_date) {
            throw new Exception('End date cannot be earlier than start date.');
        }
        $assessment_manager = Assessment_360_Assessment_Manager::get_instance();
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            $result = $assessment_manager->update_assessment(intval($_POST['id']), $data);
            $message = 'Assessment updated successfully';
        } else {
            $result = $assessment_manager->create_assessment($data);
            $message = 'Assessment created successfully';
        }
        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }
        wp_redirect(add_query_arg(
            'message',
            urlencode($message),
            admin_url('admin.php?page=assessment-360-assessments')
        ));
        exit;
    } catch (Exception $e) {
        wp_redirect(add_query_arg(
            'error',
            urlencode($e->getMessage()),
            admin_url('admin.php?page=assessment-360-assessments')
        ));
        exit;
    }
}

// Delete assessment (soft delete)
add_action('admin_post_delete_assessment', 'handle_delete_assessment');
function handle_delete_assessment() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    check_admin_referer('delete_assessment', 'delete_assessment_nonce');
    $assessment_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$assessment_id) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Invalid assessment ID')
        ], admin_url('admin.php')));
        exit;
    }
    $assessment = Assessment_360_Assessment::get_instance();
    $assessment_data = $assessment->get_assessment($assessment_id);
    if (!$assessment_data) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Assessment not found')
        ], admin_url('admin.php')));
        exit;
    }
    if ($assessment_data->status === 'active') {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Cannot delete an active assessment. Please complete it first.')
        ], admin_url('admin.php')));
        exit;
    }
    $result = $assessment->update_assessment_status($assessment_id, 'deleted');
    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }
    wp_redirect(add_query_arg([
        'page' => 'assessment-360-assessments',
        'message' => urlencode('Assessment deleted successfully')
    ], admin_url('admin.php')));
    exit;
}

// Restore assessment
add_action('admin_post_restore_assessment', 'handle_restore_assessment');
function handle_restore_assessment() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    check_admin_referer('restore_assessment', 'restore_assessment_nonce');
    $assessment_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$assessment_id) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Invalid assessment ID')
        ], admin_url('admin.php')));
        exit;
    }
    $assessment = Assessment_360_Assessment::get_instance();
    if (!$assessment->assessment_exists($assessment_id)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Assessment not found')
        ], admin_url('admin.php')));
        exit;
    }
    $result = $assessment->update_assessment_status($assessment_id, 'completed');
    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }
    wp_redirect(add_query_arg([
        'page' => 'assessment-360-assessments',
        'message' => urlencode('Assessment restored successfully')
    ], admin_url('admin.php')));
    exit;
}

// Enable assessment (set to active)
add_action('admin_post_enable_assessment', 'handle_enable_assessment');
function handle_enable_assessment() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    $assessment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$assessment_id || 
        !wp_verify_nonce($_GET['_wpnonce'], 'enable_assessment_' . $assessment_id)) {
        wp_die('Invalid request');
    }
    try {
        $assessment_manager = Assessment_360_Assessment_Manager::get_instance();
        $result = $assessment_manager->update_assessment_status($assessment_id, 'active');
        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }
        wp_redirect(add_query_arg(
            'message',
            'Assessment enabled successfully',
            admin_url('admin.php?page=assessment-360-assessments')
        ));
        exit;
    } catch (Exception $e) {
        wp_redirect(add_query_arg(
            'error',
            urlencode($e->getMessage()),
            admin_url('admin.php?page=assessment-360-assessments')
        ));
        exit;
    }
}

// Activate assessment (only if no other is active)
add_action('admin_post_activate_assessment', 'handle_activate_assessment');
function handle_activate_assessment() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    check_admin_referer('activate_assessment', 'activate_assessment_nonce');
    $assessment_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$assessment_id) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Invalid assessment ID')
        ], admin_url('admin.php')));
        exit;
    }
    $assessment = Assessment_360_Assessment::get_instance();
    $assessment_data = $assessment->get_assessment($assessment_id);
    if (!$assessment_data) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Assessment not found')
        ], admin_url('admin.php')));
        exit;
    }
    $active_assessment = $assessment->get_active_assessment();
    if ($active_assessment) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Another assessment is currently active. Please complete it first.')
        ], admin_url('admin.php')));
        exit;
    }
    $result = $assessment->update_assessment_status($assessment_id, 'active');
    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }
    wp_redirect(add_query_arg([
        'page' => 'assessment-360-assessments',
        'message' => urlencode('Assessment activated successfully')
    ], admin_url('admin.php')));
    exit;
}

// Disable assessment (set to inactive)
add_action('admin_post_disable_assessment', 'handle_disable_assessment');
function handle_disable_assessment() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    $assessment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    check_admin_referer('disable_assessment_' . $assessment_id);
    try {
        $assessment_manager = Assessment_360_Assessment_Manager::get_instance();
        $result = $assessment_manager->update_assessment_status($assessment_id, 'inactive');
        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }
        wp_redirect(add_query_arg(
            'message',
            urlencode('Assessment disabled successfully'),
            admin_url('admin.php?page=assessment-360-assessments')
        ));
        exit;
    } catch (Exception $e) {
        wp_redirect(add_query_arg(
            'error',
            urlencode($e->getMessage()),
            admin_url('admin.php?page=assessment-360-assessments')
        ));
        exit;
    }
}

// Complete assessment (set to completed)
add_action('admin_post_complete_assessment', 'handle_complete_assessment');
function handle_complete_assessment() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    check_admin_referer('complete_assessment', 'complete_assessment_nonce');
    $assessment_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$assessment_id) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Invalid assessment ID')
        ], admin_url('admin.php')));
        exit;
    }
    $assessment = Assessment_360_Assessment::get_instance();
    $assessment_data = $assessment->get_assessment($assessment_id);
    if (!$assessment_data) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Assessment not found')
        ], admin_url('admin.php')));
        exit;
    }
    if ($assessment_data->status !== 'active') {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Only active assessments can be completed')
        ], admin_url('admin.php')));
        exit;
    }
    $result = $assessment->update_assessment_status($assessment_id, 'completed');
    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }
    wp_redirect(add_query_arg([
        'page' => 'assessment-360-assessments',
        'message' => urlencode('Assessment completed successfully')
    ], admin_url('admin.php')));
    exit;
}

/* Assessment Form Submission Handler */
add_action('admin_post_submit_assessment', 'handle_assessment_submission');
add_action('admin_post_nopriv_submit_assessment', 'handle_assessment_submission');
function handle_assessment_submission() {
    if (!isset($_POST['assessment_nonce']) || 
        !wp_verify_nonce($_POST['assessment_nonce'], 'submit_assessment')) {
        wp_die('Invalid security token');
    }
    try {
        $assessment_id = isset($_POST['assessment_id']) ? intval($_POST['assessment_id']) : 0;
        $assessor_id = isset($_POST['assessor_id']) ? intval($_POST['assessor_id']) : 0;
        $assessee_id = isset($_POST['assessee_id']) ? intval($_POST['assessee_id']) : 0;
        $ratings = isset($_POST['rating']) ? $_POST['rating'] : [];
        $comments = isset($_POST['comment']) ? $_POST['comment'] : [];
        if (empty($ratings)) {
            throw new Exception('No ratings provided');
        }
        $responses = [];
        foreach ($ratings as $question_id => $rating) {
            $responses[$question_id] = [
                'rating' => intval($rating),
                'comment' => isset($comments[$question_id]) ? sanitize_textarea_field($comments[$question_id]) : ''
            ];
        }
        $assessment_manager = Assessment_360_Assessment_Manager::get_instance();
        $result = $assessment_manager->save_assessment_response([
            'assessment_id' => $assessment_id,
            'assessor_id' => $assessor_id,
            'assessee_id' => $assessee_id,
            'responses' => $responses
        ]);
        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }
        wp_redirect(add_query_arg(
            'message',
            'assessment_submitted',
            home_url('/360-assessment-dashboard/')
        ));
        exit;
    } catch (Exception $e) {
        wp_redirect(add_query_arg([
            'error' => urlencode($e->getMessage()),
            'assessment_id' => $assessment_id ?? 0,
            'assessee_id' => $assessee_id ?? 0
        ], wp_get_referer()));
        exit;
    }
}

/**
 * ===========================
 * Suggestions for Improvement
 * ===========================
 * - Use AJAX for form submissions and status updates (improved UX).
 * - Add better error handling for assessment state transitions.
 * - Add logging for assessment actions.
 * - Add automated tests for all handlers.
 * - Consider moving email notifications (reminders, completion) here.
 */