<?php
if (!defined('ABSPATH')) exit;

ob_start();


/**
 * 360 Assessments - Assessment Functions and Handlers
 * Handles assessment creation, updating, status transitions, and admin actions.
 * 
 * SUGGESTIONS FOR IMPROVEMENT:
 * - Consider adding server-side validation for date ranges (e.g., end_date >= start_date).
 * - Add logging or admin notices for critical actions (activation, completion, deletion).
 * - Use WP_Error consistently for error handling in model methods.
 * - Move repeated status logic into helper functions or methods.
 * - Add capability checks for current_user_can() before allowing actions.
 * - Add unit tests for status transitions and critical logic.
 */

/**
 * Create or update assessment
 * - On creation, assessment is always 'draft'
 * - On update, only fields are changed, not status
 */
add_action('admin_post_save_assessment', function() {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'save_assessment_nonce')) {
        wp_die('Invalid security token');
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $input = [
        'name'        => trim(sanitize_text_field($_POST['name'] ?? '')),
        'description' => trim(sanitize_textarea_field($_POST['description'] ?? '')),
        'start_date'  => trim(sanitize_text_field($_POST['start_date'] ?? '')),
        'end_date'    => trim(sanitize_text_field($_POST['end_date'] ?? ''))
    ];
    $today = date('Y-m-d');

    // SUGGESTION: Add end_date >= start_date validation here.
    if (
        empty($input['name']) ||
        empty($input['start_date']) ||
        empty($input['end_date']) ||
        $input['start_date'] < $today ||
        $input['end_date'] > $input['start_date'] // <-- This is the new validation line
    ) {
        $error_message = 'Please fill in all required fields, ensure the start date is today or later, and the end date is not before the start date.';
        $redirect_url = add_query_arg('error', urlencode($error_message), wp_get_referer());
        wp_redirect($redirect_url);
        exit;
    }

    $manager = Assessment_360_Assessment::get_instance();

    if ($id) {
        // Edit existing assessment, do not change status here
        $result = $manager->update_assessment($id, $input);
        if (is_wp_error($result)) {
            $redirect_url = add_query_arg('error', urlencode($result->get_error_message()), wp_get_referer());
        } else {
            $redirect_url = add_query_arg('message', urlencode("Assessment updated successfully."), admin_url('admin.php?page=assessment-360-assessments'));
        }
        wp_redirect($redirect_url);
        exit;
    } else {
        // New assessment: always status draft
        $input['status'] = 'draft';
        $result = $manager->create_assessment($input);
        if (is_wp_error($result)) {
            $redirect_url = add_query_arg('error', urlencode($result->get_error_message()), wp_get_referer());
        } else {
            $redirect_url = add_query_arg('message', urlencode("Assessment created as draft."), admin_url('admin.php?page=assessment-360-assessments'));
        }
        wp_redirect($redirect_url);
        exit;
    }
});

/**
 * Activate an assessment (only if no other is active)
 */
add_action('admin_post_activate_assessment', function() {
    if (!isset($_POST['id']) || !isset($_POST['_wpnonce'])) {
        wp_die('Invalid request');
    }
    $id = intval($_POST['id']);
    if (!wp_verify_nonce($_POST['_wpnonce'], 'activate_assessment_nonce_' . $id)) {
        wp_die('Invalid security token');
    }

    $manager = Assessment_360_Assessment::get_instance();

    // Only allow if no active assessment exists
    foreach ($manager->get_all_assessments() as $a) {
        if ($a->status === 'active') {
            wp_redirect(add_query_arg('error', urlencode('An assessment is already active. Complete it before activating another.'), admin_url('admin.php?page=assessment-360-assessments')));
            exit;
        }
    }

    $assessment = $manager->get_assessment($id);
    if (!$assessment || !in_array($assessment->status, ['draft', 'completed'])) {
        wp_redirect(add_query_arg('error', urlencode('Only draft or completed assessments can be activated.'), admin_url('admin.php?page=assessment-360-assessments')));
        exit;
    }

    $manager->update_assessment($id, ['status' => 'active']);
    wp_redirect(add_query_arg('message', urlencode('Assessment activated.'), admin_url('admin.php?page=assessment-360-assessments')));
    exit;
});

/**
 * Mark an active assessment as completed
 */
add_action('admin_post_complete_assessment', function() {
    if (!isset($_POST['id']) || !isset($_POST['_wpnonce'])) {
        wp_die('Invalid request');
    }
    $id = intval($_POST['id']);
    if (!wp_verify_nonce($_POST['_wpnonce'], 'complete_assessment_nonce_' . $id)) {
        wp_die('Invalid security token');
    }
    $manager = Assessment_360_Assessment::get_instance();
    $assessment = $manager->get_assessment($id);

    if (!$assessment || $assessment->status !== 'active') {
        wp_redirect(add_query_arg('error', urlencode('Only active assessments can be completed.'), admin_url('admin.php?page=assessment-360-assessments')));
        exit;
    }

    // Mark as completed
    $manager->update_assessment($id, [
        'status' => 'completed',
        'completed_at' => current_time('mysql'),
    ]);
    wp_redirect(add_query_arg('message', urlencode('Assessment marked as completed.'), admin_url('admin.php?page=assessment-360-assessments')));
    exit;
});

/**
 * Deaactivare an assessment
 */
add_action('admin_post_deactivate_assessment', function() {
    if (!isset($_POST['id']) || !isset($_POST['_wpnonce'])) {
        wp_die('Invalid request');
    }
    $id = intval($_POST['id']);
    if (!wp_verify_nonce($_POST['_wpnonce'], 'deactivate_assessment_nonce_' . $id)) {
        wp_die('Invalid security token');
    }
    $manager = Assessment_360_Assessment::get_instance();
    $assessment = $manager->get_assessment($id);

    if (!$assessment || $assessment->status !== 'active') {
        wp_redirect(add_query_arg('error', urlencode('Only active assessments can be deactivated.'), admin_url('admin.php?page=assessment-360-assessments')));
        exit;
    }
    $manager->update_assessment($id, ['status' => 'draft']);
    wp_redirect(add_query_arg('message', urlencode('Assessment deactivated and set to draft.'), admin_url('admin.php?page=assessment-360-assessments')));
    exit;
});

/**
 * Restore a deleted assessment (returns to draft)
 */
add_action('admin_post_restore_assessment', function() {
    if (!isset($_POST['id']) || !isset($_POST['restore_assessment_nonce'])) {
        wp_die('Invalid request');
    }
    $id = intval($_POST['id']);
    if (!wp_verify_nonce($_POST['restore_assessment_nonce'], 'restore_assessment')) {
        wp_die('Invalid security token');
    }
    $manager = Assessment_360_Assessment::get_instance();
    $assessment = $manager->get_assessment($id);
    if (!$assessment || $assessment->status !== 'deleted') {
        wp_redirect(add_query_arg('error', urlencode('Only deleted assessments can be restored.'), admin_url('admin.php?page=assessment-360-assessments')));
        exit;
    }
    // Restore as completed (not draft)
    $manager->update_assessment($id, ['status' => 'completed']);
    wp_redirect(add_query_arg('message', urlencode('Assessment restored as completed.'), admin_url('admin.php?page=assessment-360-assessments')));
    exit;
});

/**
 * Mark an assessment as deleted (soft delete)
 */
add_action('admin_post_delete_assessment', function() {
    if (!isset($_POST['id']) || !isset($_POST['delete_assessment_nonce'])) {
        wp_die('Invalid request');
    }
    $id = intval($_POST['id']);
    if (!wp_verify_nonce($_POST['delete_assessment_nonce'], 'delete_assessment')) {
        wp_die('Invalid security token');
    }

    $manager = Assessment_360_Assessment::get_instance();
    $assessment = $manager->get_assessment($id);

    if (!$assessment) {
        wp_redirect(add_query_arg('error', urlencode('Assessment not found.'), admin_url('admin.php?page=assessment-360-assessments')));
        exit;
    }

    if ($assessment->status === 'active') {
        wp_redirect(add_query_arg('error', urlencode('Active assessments cannot be deleted. Complete or deactivate it first.'), admin_url('admin.php?page=assessment-360-assessments')));
        exit;
    }

    if ($assessment->status === 'draft') {
        // Hard delete from DB
        $manager->delete_assessment($id);
        wp_redirect(add_query_arg('message', urlencode('Assessment deleted permanently.'), admin_url('admin.php?page=assessment-360-assessments')));
        exit;
    }

    if ($assessment->status === 'completed') {
        // Soft delete (mark as deleted)
        $manager->update_assessment($id, ['status' => 'deleted']);
        wp_redirect(add_query_arg('message', urlencode('Assessment soft deleted. You can restore it later.'), admin_url('admin.php?page=assessment-360-assessments')));
        exit;
    }

    // If already deleted or unknown status
    wp_redirect(add_query_arg('error', urlencode('This assessment cannot be deleted.'), admin_url('admin.php?page=assessment-360-assessments')));
    exit;
});
/**
 * Helper: Get the current active assessment.
 * For use in forms or anywhere "active" assessment context is needed.
 */
function assessment_360_get_active_assessment() {
    $manager = Assessment_360_Assessment::get_instance();
    $assessments = $manager->get_all_assessments();
    foreach ($assessments as $a) {
        if ($a->status === 'active') return $a;
    }
    return null;
}

/**
 * SUGGESTION: Consider adding helper functions like:
 * - assessment_360_can_activate() : bool
 * - assessment_360_can_complete($id) : bool
 * - assessment_360_can_delete($id) : bool
 * To avoid repeating status logic and make code more readable/testable.
 */