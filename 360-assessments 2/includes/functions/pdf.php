<?php

add_action('admin_post_export_pdf', 'assessment_360_export_pdf_handler');

function assessment_360_export_pdf_handler() {
    // Handler for admin-post.php?action=export_pdf
    if (!defined('ABSPATH')) require_once(dirname(__FILE__, 4) . '/wp-load.php');
    require_once(ABSPATH . 'wp-admin/includes/admin.php');
    require_once(plugin_dir_path(__FILE__) . '../includes/class-pdf-generator.php');

    // Gather POSTed data
    $assessment_id = intval($_POST['assessment_id'] ?? 0);
    $user_id = intval($_POST['user_id'] ?? 0);
    $type = $_POST['type'] ?? '';
    $organization_name = $_POST['organization_name'] ?? '';
    $organization_logo_url = $_POST['organization_logo'] ?? '';
    $assessee_name = $_POST['assessee_name'] ?? '';
    $assessment_period = $_POST['assessment_period'] ?? '';
    $summary_intro = $_POST['summary_intro'] ?? '';

    // Decode arrays
    $assessor_counts = json_decode(stripslashes($_POST['assessor_counts'] ?? '[]'), true);
    $group_averages = json_decode(stripslashes($_POST['group_averages'] ?? '[]'), true);
    $questions_data = json_decode(stripslashes($_POST['questions_data'] ?? '[]'), true);
    $user_groups = json_decode(stripslashes($_POST['user_groups'] ?? '[]'), true);
    $individual_responses = json_decode(stripslashes($_POST['individual_responses'] ?? '[]'), true);

    // Charts
    $charts = [];
    if (isset($_POST['charts'])) {
        foreach ($_POST['charts'] as $c) {
            $charts[] = [
                'label' => $c['label'],
                'img'   => $c['img'],
            ];
        }
    }

    // Convert logo URL to a temp file if necessary (TCPDF prefers file path)
    $organization_logo_path = '';
    if ($organization_logo_url) {
        // Try as file path first
        $try_path = ABSPATH . str_replace(site_url().'/', '', $organization_logo_url);
        if (file_exists($try_path)) {
            $organization_logo_path = $try_path;
        } else {
            // Download to temp file
            $tmp_logo = tempnam(sys_get_temp_dir(), 'orglogo_');
            file_put_contents($tmp_logo, file_get_contents($organization_logo_url));
            $organization_logo_path = $tmp_logo;
        }
    }

    // Generate PDF
    $pdf = new Assessment_360_PDF_Generator(
        'P', 'mm', 'A4', $organization_name, $organization_logo_path
    );

    // 1. Cover
    $pdf->CoverPage(
        '360° Assessment Report',
        $assessment_period,
        $assessee_name
    );

    // 2. Summary page
    $pdf->SummaryPage(
        $summary_intro,
        $assessor_counts,
        $group_averages
    );

    // 3. Detailed Table
    $pdf->DetailedTable($questions_data, $user_groups);

    // 4. Charts: Use Grid Layout
    if (!empty($charts)) {
        $pdf->AddChartsGrid($charts); // <--- updated!
    }

    // 5. Detailed Individual Responses
    $pdf->IndividualResponsesTable($individual_responses);

    $pdf->Output('assessment_report.pdf', 'I');

    // Clean up temp logo if used
    if (isset($tmp_logo) && file_exists($tmp_logo)) {
        unlink($tmp_logo);
    }
    exit;
}

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

function get_rating_color($rating) {
    if ($rating >= 4.5) return 'success';
    if ($rating >= 3.5) return 'info';
    if ($rating >= 2.5) return 'warning';
    return 'danger';
}