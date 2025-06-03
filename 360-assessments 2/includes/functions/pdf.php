<?php
/**
 * 360 Assessments PDF Generation Functions & Handlers
 * 
 * Handles all logic for generating and downloading PDF assessment reports.
 * Migrated from the main plugin file.
 */

/* PDF Export Handler */
add_action('admin_post_export_pdf', 'handle_export_pdf');
function handle_export_pdf() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    $assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    if (!$assessment_id || !$user_id) {
        wp_die('Invalid parameters');
    }
    $user_manager = Assessment_360_User_Manager::get_instance();
    $assessment_manager = Assessment_360_Assessment::get_instance();
    $user = $user_manager->get_user($user_id);
    $assessment = $assessment_manager->get_assessment($assessment_id);
    if (!$user || !$assessment) {
        wp_die('User or assessment not found');
    }
    $filename = sprintf(
        '360_assessment_%s_%s_%s.pdf',
        sanitize_file_name($user->first_name . '_' . $user->last_name),
        sanitize_file_name($assessment->name),
        date('Y-m-d')
    );
    require_once plugin_dir_path(__FILE__) . '../class-pdf-generator.php';
    $pdf = new Assessment_360_PDF_Generator();
    $result = $pdf->generateAssessmentReport($assessment_id, $user_id);
    if (!$result) {
        wp_die('Failed to generate PDF');
    }
    $pdf->Output($filename, 'D');
    exit;
}

/**
 * ===========================
 * Suggestions for Improvement
 * ===========================
 * - Move PDF generation to background for large reports.
 * - Add logging for all PDF downloads/generations.
 * - Support PDF customization (branding, layout).
 * - Add tests for PDF output content.
 */