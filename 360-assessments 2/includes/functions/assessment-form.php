<?php
if (!defined('ABSPATH')) exit;

// Always start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function handle_assessment_submission() {
    
    // Custom nonce check
    if (
        empty($_POST['my_custom_nonce']) ||
        empty($_SESSION['my_custom_form_nonce']) ||
        $_POST['my_custom_nonce'] !== $_SESSION['my_custom_form_nonce']
    ) {
        error_log('Invalid custom security token');
        unset($_SESSION['my_custom_form_nonce']);
        wp_die('Invalid security token');
    }
    // Prevent reuse
    unset($_SESSION['my_custom_form_nonce']);

    $assessment_id = isset($_POST['assessment_id']) ? intval($_POST['assessment_id']) : '';
    $assessor_id = isset($_POST['assessor_id']) ? intval($_POST['assessor_id']) : '';
    $assessee_id = isset($_POST['assessee_id']) ? intval($_POST['assessee_id']) : '';

    try {
        error_log('Inside try block');
        $ratings = isset($_POST['rating']) ? $_POST['rating'] : [];
        $comments = isset($_POST['comment']) ? $_POST['comment'] : [];

        if (!$assessment_id || !$assessor_id || !$assessee_id) {
            throw new Exception('Missing required assessment information.');
        }

        if (empty($ratings)) {
            throw new Exception('No ratings provided');
        }

        $responses = [];
        foreach ($ratings as $question_id => $rating) {
            $responses[$question_id] = [
                'rating' => intval($rating),
                'comment' => isset($comments[$question_id]) ?
                    sanitize_textarea_field($comments[$question_id]) : ''
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

        error_log('Redirecting to dashboard');
        wp_redirect(add_query_arg(
            'message',
            'assessment_submitted',
            home_url('/360-assessment-dashboard/')
        ));
        exit;

    } catch (Exception $e) {
        error_log('Redirecting after error: ' . $e->getMessage());
        wp_redirect(add_query_arg([
            'error' => urlencode($e->getMessage()),
            'assessment_id' => $assessment_id,
            'assessee_id' => $assessee_id
        ], wp_get_referer() ?: home_url('/360-assessment-dashboard/')));
        exit;
    }
}

// Register for both logged-in and not-logged-in users (safe for custom session)
add_action('admin_post_nopriv_submit_assessment', 'handle_assessment_submission');
add_action('admin_post_submit_assessment', 'handle_assessment_submission');

function handle_assessment_status_change($status) {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $nonce_action = $status . '_assessment';
    $nonce_name = $status . '_assessment_nonce';

    check_admin_referer($nonce_action, $nonce_name);

    $assessment_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if (!$assessment_id) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode('Invalid assessment ID')
        ], admin_url('admin.php')));
        exit;
    }

    $assessment = Assessment_360_Assessment::get_instance();
    $result = $assessment->update_assessment_status($assessment_id, $status);

    if (is_wp_error($result)) {
        wp_redirect(add_query_arg([
            'page' => 'assessment-360-assessments',
            'error' => urlencode($result->get_error_message())
        ], admin_url('admin.php')));
        exit;
    }

    wp_redirect(add_query_arg([
        'page' => 'assessment-360-assessments',
        'message' => urlencode('Assessment ' . $status . ' successfully')
    ], admin_url('admin.php')));
    exit;
}

// Register assessment action handlers
add_action('admin_post_activate_assessment', function() { 
    handle_assessment_status_change('active'); 
});
add_action('admin_post_complete_assessment', function() { 
    handle_assessment_status_change('completed'); 
});
add_action('admin_post_delete_assessment', function() { 
    handle_assessment_status_change('deleted'); 
});



/**
 * Get assessment form URL
 */
//function assessment_360_get_form_url($assessment_id, $assessee_id, $is_self = false) {
//    return add_query_arg([
//        'assessment_id' => $assessment_id,
//        'assessee_id' => $assessee_id,
//        'self_assessment' => $is_self ? '1' : '0'
//    ], home_url('/assessment-form/'));
//}

/**
 * Get rating color based on score
 */

