<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('Assessment_360_Data')) {
    require_once plugin_dir_path(__FILE__) . '../../includes/class-assessment-data.php';
}

$assessment_manager = Assessment_360_Assessment::get_instance();
$user_manager = Assessment_360_User_Manager::get_instance();
$assessment_id = filter_input(INPUT_GET, 'assessment_id', FILTER_VALIDATE_INT);
$user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

if (!$assessment_id || !$user_id) {
    echo '<div class="alert alert-danger">Invalid assessment or user ID.</div>';
    return;
}

$assessment = $assessment_manager->get_assessment($assessment_id);
$user = $user_manager->get_user($user_id);

if (!$assessment || !$user) {
    echo '<div class="alert alert-danger">Assessment or user not found.</div>';
    return;
}

$data_manager = new Assessment_360_Data();

// 1. Get all column "groups": Self, Peers, [other groups]
$all_groups = $data_manager->get_user_groups_for_assessment($assessment_id, $user_id);
// 2. Get all assessors count per group (Self, Peers, [others])
$assessor_counts = $data_manager->get_assessor_counts_per_group($assessment_id, $user_id);
// 3. Get per-group average for the assessee (overall, for summary)
$group_averages = $data_manager->get_average_score_per_group($assessment_id, $user_id);
// 4. Get all topics/sections/questions with per-group averages for detail
$questions_data = $data_manager->get_questions_group_averages($assessment_id, $user_id, $all_groups);
// 5. Get chart data per section/topic for Chart.js
$section_chart_data = $data_manager->get_section_chart_data($assessment_id, $user_id, $all_groups);
// 6. All individual responses (anonymous, Self, Peers, others)
$individual_responses = $data_manager->get_all_individual_responses($assessment_id, $user_id);

// Organization info
$organization_name = get_option('assessment_360_organization_name');
$organization_logo_url = get_option('assessment_360_organization_logo');

// Sample intro text, can be customized or fetched from options/settings
$summary_intro = "This report presents the feedback gathered from all assessors during the assessment period. The results are grouped by user type and summarized with graphical and tabular representations for actionable insights.";

?>
<div class="wrap">
    <div class="mb-4 d-flex justify-content-between">
        <div class="mb-0">
            <a href="<?php echo esc_url(add_query_arg(['page' => 'assessment-360-results', 'assessment_id' => $assessment_id], admin_url('admin.php'))); ?>"
               class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Results List
            </a>
            <a href="<?php echo esc_url(add_query_arg([
                'page' => 'assessment-360-results',
                'view' => 'summary',
                'assessment_id' => $assessment_id,
                'user_id' => $user_id
            ], admin_url('admin.php'))); ?>" class="btn btn-info">
                <i class="bi bi-graph-up"></i> View Summarized Results
            </a>
        </div>
        
        <button class="btn btn-danger" id="export-pdf-btn">
            <i class="bi bi-file-pdf"></i> Export PDF
        </button>
    </div>
    <h1 class="wp-heading-inline">Detailed Results: <?php echo esc_html($user->first_name . ' ' . $user->last_name); ?></h1>
    <hr class="wp-header-end">
    
    <div class="row">
        <div class="col">
            <!-- Summary: Assessor counts -->
            <div class="mb-4">
                <h3>Assessor Groups</h3>
                <table class="wp-list-table widefat fixed striped compact-results-table">
                    <thead>
                        <tr>
                            <?php foreach ($all_groups as $g): ?>
                                <th><?php echo esc_html($g); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php foreach ($all_groups as $g): ?>
                                <td><?php echo isset($assessor_counts[$g]) ? intval($assessor_counts[$g]) : 0; ?></td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col">
            <!-- Summary: Group averages -->
            <div class="mb-4">
                <h3>Average Score by Group</h3>
                <table class="wp-list-table widefat fixed striped compact-results-table">
                    <thead>
                        <tr>
                            <?php foreach ($all_groups as $g): ?>
                                <th><?php echo esc_html($g); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php foreach ($all_groups as $g): ?>
                                <td><?php echo isset($group_averages[$g]) && is_numeric($group_averages[$g]) ? number_format($group_averages[$g], 2) : ''; ?></td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Charts per section -->
    <div id="section-charts">
        <h3>Visual Summary</h3>
        <div class="charts-grid" style="display: flex; flex-wrap: wrap; gap: 24px;">
        <?php foreach ($section_chart_data as $section_label => $chart): ?>
            <div class="chart-box" style="width:220px;">
                <h4 style="font-size: 1rem; text-align:center;"><?php echo esc_html($section_label); ?></h4>
                <canvas
                    class="section-chart-canvas"
                    data-section="<?php echo esc_attr($section_label); ?>"
                    width="200"
                    height="200"
                    style="display: block; margin: 0 auto;"
                ></canvas>
                <script class="section-chart-data" type="application/json">
                    <?php echo json_encode($chart); ?>
                </script>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Detailed Table (Topic, Section, Question, group columns) -->
    <div class="table-responsive mb-4" id="results-detail-table-container">
        <h3>Detailed Results by Question and Group</h3>
        <table class="wp-list-table widefat fixed striped compact-results-table" id="results-detail-table">
            <thead>
                <tr>
                    <th>Topic</th>
                    <th>Section</th>
                    <th>Question</th>
                    <?php foreach ($all_groups as $g): ?>
                        <th><?php echo esc_html($g); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($questions_data as $row): ?>
                <tr>
                    <td><?php echo esc_html($row['topic']); ?></td>
                    <td><?php echo esc_html($row['section']); ?></td>
                    <td><?php echo esc_html($row['question']); ?></td>
                    <?php foreach ($all_groups as $g): ?>
                        <td><?php echo isset($row['group_values'][$g]) && $row['group_values'][$g] !== '' ? number_format($row['group_values'][$g], 2) : ''; ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Individual responses (ANONYMOUS)-->
    <!-- Uncomment if needed
    <div class="table-responsive mb-4">
        <h3>All Individual Responses</h3>
        <table class="wp-list-table widefat fixed striped compact-results-table" id="individual-responses-table">
            <thead>
                <tr>
                    <th>Topic</th>
                    <th>Section</th>
                    <th>Question</th>
                    <th>Group</th>
                    <th>Value</th>
                    <th>Comment</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($individual_responses as $resp): ?>
                <tr>
                    <td><?php echo esc_html($resp['topic']); ?></td>
                    <td><?php echo esc_html($resp['section']); ?></td>
                    <td><?php echo esc_html($resp['question']); ?></td>
                    <td><?php echo esc_html($resp['group']); ?></td>
                    <td><?php echo is_numeric($resp['value']) ? number_format($resp['value'], 2) : esc_html($resp['value']); ?></td>
                    <td><?php echo !empty($resp['comment']) ? esc_html($resp['comment']) : '<em>No comment</em>'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    -->

    <!-- Hidden data for JS export -->
    <script id="pdf-export-data" type="application/json">
        <?php echo json_encode([
            'assessment_id'      => $assessment_id,
            'user_id'            => $user_id,
            'organization_name'  => $organization_name,
            'organization_logo'  => $organization_logo_url,
            'assessee_name'      => $user->first_name . ' ' . $user->last_name,
            'assessment_period'  => '', // Fill if available
            'summary_intro'      => $summary_intro,
            'assessor_counts'    => $assessor_counts,
            'group_averages'     => $group_averages,
            'questions_data'     => $questions_data,
            'user_groups'        => $all_groups,
            'individual_responses' => $individual_responses,
            'section_chart_data' => $section_chart_data,
        ]); ?>
    </script>
</div>
<script>
// Chart column color palette (10 distinct)
const assessment360BarColors = [
    "#3498db", // blue
    "#f39c12", // orange
    "#2ecc71", // green
    "#e74c3c", // red
    "#9b59b6", // purple
    "#1abc9c", // turquoise
    "#e67e22", // carrot
    "#34495e", // dark blue
    "#7f8c8d", // gray
    "#95a5a6"  // light gray
];

// Render chart with distinct colors for each column
function renderSectionChart(canvas, chartData) {
    const barColors = chartData.labels.map((_, i) =>
        assessment360BarColors[i % assessment360BarColors.length]
    );
    return new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Average Score',
                data: chartData.values,
                backgroundColor: barColors
            }]
        },
        options: {
            responsive: false,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }},
            scales: {
                y: { min: 0, max: 5, ticks: { stepSize: 1 } }
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Render charts with colored columns
    document.querySelectorAll('.section-chart-canvas').forEach(function(canvas) {
        let chartDataScript = canvas.parentElement.querySelector('.section-chart-data');
        if (chartDataScript) {
            let chartData = JSON.parse(chartDataScript.textContent);
            renderSectionChart(canvas, chartData);
        }
    });

    // PDF Export
    document.getElementById('export-pdf-btn').addEventListener('click', function() {
        // 1. Gather chart images
        let charts = [];
        document.querySelectorAll('.section-chart-canvas').forEach(function(canvas) {
            let label = canvas.parentElement.querySelector('h4')?.innerText || canvas.dataset.section;
            let img = canvas.toDataURL('image/png');
            charts.push({ label: label, img: img });
        });
        // 2. Gather rest of the data
        let exportData = JSON.parse(document.getElementById('pdf-export-data').textContent);

        // 3. Create form and submit
        let form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo admin_url('admin-post.php?action=export_pdf'); ?>';
        form.style.display = 'none';

        function append(name, value) {
            let input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = typeof value === 'string' ? value : JSON.stringify(value);
            form.appendChild(input);
        }

        append('assessment_id', exportData.assessment_id);
        append('user_id', exportData.user_id);
        append('organization_name', exportData.organization_name);
        append('organization_logo', exportData.organization_logo);
        append('assessee_name', exportData.assessee_name);
        append('assessment_period', exportData.assessment_period);
        append('summary_intro', exportData.summary_intro);
        append('assessor_counts', exportData.assessor_counts);
        append('group_averages', exportData.group_averages);
        append('questions_data', exportData.questions_data);
        append('user_groups', exportData.user_groups);
        append('individual_responses', exportData.individual_responses);

        // Charts (many)
        charts.forEach(function(chart, idx) {
            append(`charts[${idx}][label]`, chart.label);
            append(`charts[${idx}][img]`, chart.img);
        });

        append('type', 'full'); // For handler logic

        document.body.appendChild(form);
        form.submit();
    });
});
</script>
<style>
.compact-results-table {
    font-size: 13px;
    margin-bottom: 0;
    border-collapse: collapse;
}
.compact-results-table th,
.compact-results-table td {
    padding: 4px 8px !important;
    vertical-align: top;
}
.compact-results-table thead th {
    background: #f3f4f5;
    border-bottom: 1px solid #ccc;
}
#section-charts .chart-box {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
    padding: 1rem;
    margin-bottom: 2rem;
}
@media print {
    .wrap .mb-4,
    .wrap .mb-3,
    .wrap .btn,
    .wrap a.btn,
    .wrap hr.wp-header-end {
        display: none !important;
    }
    .wrap { background: #fff; }
    table { page-break-inside: auto; }
    tr    { page-break-inside: avoid; page-break-after: auto; }
    .compact-results-table { width: 100%; font-size: 11px; }
}
</style>