<?php
if (!defined('ABSPATH')) exit;

/**
 * Assessment Form Functions
 */
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

        wp_redirect(add_query_arg(
            'message',
            'assessment_submitted',
            home_url('/360-assessment-dashboard/')
        ));
        exit;

    } catch (Exception $e) {
        wp_redirect(add_query_arg([
            'error' => urlencode($e->getMessage()),
            'assessment_id' => $assessment_id,
            'assessee_id' => $assessee_id
        ], wp_get_referer()));
        exit;
    }
}
add_action('admin_post_submit_assessment', 'handle_assessment_submission');
add_action('admin_post_nopriv_submit_assessment', 'handle_assessment_submission');

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

function assessment_360_generate_pdf_report($assessment_id, $user_id, $report_type = 'detailed') {
    require_once plugin_dir_path(__FILE__) . '../classes/class-pdf-reports.php';
    
    try {
        $user_manager = Assessment_360_User_Manager::get_instance();
        $user = $user_manager->get_user($user_id);
        
        if (!$user) {
            throw new Exception('User not found');
        }

        $filename = sprintf(
            '360_assessment_%s_%s_%s_%s.pdf',
            $report_type,
            sanitize_file_name($user->first_name),
            sanitize_file_name($user->last_name),
            date('Y-m-d')
        );

        $pdf = new Assessment_360_PDF_Report($report_type);
        
        switch ($report_type) {
            case 'detailed':
                $pdf->generateDetailedReport($assessment_id, $user_id);
                break;
            case 'summary':
                $pdf->generateSummaryReport($assessment_id, $user_id);
                break;
            case 'comparative':
                $pdf->generateComparativeReport($assessment_id, $user_id);
                break;
            case 'overall_performance':
                $pdf->generateOverallReport($user_id);
                break;
            default:
                throw new Exception('Invalid report type');
        }
        
        $pdf->Output($filename, 'D');
        exit;

    } catch (Exception $e) {
        error_log('PDF Generation Error: ' . $e->getMessage());
        wp_die('Error generating PDF: ' . $e->getMessage());
    }
}

function assessment_360_handle_pdf_generation() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // Verify nonce
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'generate_pdf_report')) {
        wp_die('Security check failed');
    }

    // Get parameters
    $assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $report_type = isset($_GET['report_type']) ? sanitize_text_field($_GET['report_type']) : 'detailed';

    if (!$assessment_id || !$user_id) {
        wp_die('Invalid parameters');
    }

    assessment_360_generate_pdf_report($assessment_id, $user_id, $report_type);
}
add_action('admin_post_generate_pdf_report', 'assessment_360_handle_pdf_generation');

/**
 * Get assessment form URL
 */
function assessment_360_get_form_url($assessment_id, $assessee_id, $is_self = false) {
    return add_query_arg([
        'assessment_id' => $assessment_id,
        'assessee_id' => $assessee_id,
        'self_assessment' => $is_self ? '1' : '0'
    ], home_url('/assessment-form/'));
}

/**
 * Get rating color based on score
 */
function get_rating_color($rating) {
    if ($rating >= 4.5) return 'success';
    if ($rating >= 3.5) return 'info';
    if ($rating >= 2.5) return 'warning';
    return 'danger';
}
