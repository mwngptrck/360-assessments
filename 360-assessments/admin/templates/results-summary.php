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

// Section averages by group (Self, Peers, dynamic groups)
$grouped = $data_manager->get_section_averages_by_group($assessment_id, $user_id);

// Collect all unique section names for table header (order as in sections table)
$all_section_names = [];
foreach ($grouped as $group) {
    if (!empty($group['sections'])) {
        foreach ($group['sections'] as $section) {
            $name = $section['section_name'] ?? 'Section';
            $all_section_names[$name] = true;
        }
    }
}
$all_section_names = array_keys($all_section_names);

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
                'view' => 'detail',
                'assessment_id' => $assessment_id,
                'user_id' => $user_id
            ], admin_url('admin.php'))); ?>" class="btn btn-primary">
                <i class="bi bi-bar-chart"></i> View Detailed Results
            </a>
        </div>
        
        <button class="btn btn-danger" id="export-pdf-btn">
            <i class="bi bi-file-pdf"></i> Export PDF
        </button>
    </div>
    <h1 class="wp-heading-inline">Summary Results: <?php echo esc_html($user->first_name . ' ' . $user->last_name); ?></h1>
    <hr class="wp-header-end">
    
    <?php if (!empty($grouped)): ?>

        <!-- Compact Table Summary -->
        <div class="mb-4">
            <h3>Section Averages by Group</h3>
            <div class="table-responsive">
            <table class="wp-list-table widefat fixed striped compact-results-table">
                <thead>
                    <tr>
                        <th>Group</th>
                        <?php foreach ($all_section_names as $section_name): ?>
                            <th><?php echo esc_html($section_name); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grouped as $group): ?>
                        <tr>
                            <td><?php echo esc_html($group['group_name']); ?></td>
                            <?php foreach ($all_section_names as $section_name): ?>
                                <?php
                                    $found = false;
                                    if (!empty($group['sections'])) {
                                        foreach ($group['sections'] as $section) {
                                            if (($section['section_name'] ?? 'Section') === $section_name) {
                                                echo '<td>' . ($section['average'] !== '' ? esc_html(number_format($section['average'], 2)) : '') . '</td>';
                                                $found = true;
                                                break;
                                            }
                                        }
                                    }
                                    if (!$found) echo '<td></td>';
                                ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Graphs per section: Each section, all groups as columns -->
        <div id="section-charts">
            <h3>Visual Summary by Section</h3>
            <div class="charts-grid" style="display: flex; flex-wrap: wrap; gap: 24px;">
                <?php foreach ($all_section_names as $section_name): ?>
                    <?php
                        // Prepare group labels and values for this section
                        $labels = [];
                        $values = [];
                        foreach ($grouped as $group) {
                            $labels[] = $group['group_name'];
                            $found = false;
                            if (!empty($group['sections'])) {
                                foreach ($group['sections'] as $section) {
                                    if (($section['section_name'] ?? 'Section') === $section_name) {
                                        $values[] = is_numeric($section['average']) ? round($section['average'], 2) : 0;
                                        $found = true;
                                        break;
                                    }
                                }
                            }
                            if (!$found) $values[] = 0;
                        }
                        $chart = [
                            "labels" => $labels,
                            "values" => $values
                        ];
                        $chart_id = 'section_chart_' . md5($section_name);
                    ?>
                    <div class="chart-box" style="width:220px;">
                        <h4 style="font-size: 1rem; text-align:center;"><?php echo esc_html($section_name); ?></h4>
                        <canvas
                            id="<?php echo esc_attr($chart_id); ?>"
                            class="section-chart-canvas"
                            data-section="<?php echo esc_attr($section_name); ?>"
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
    <?php else: ?>
        <div class="alert alert-info">No summary results found for this user/assessment.</div>
    <?php endif; ?>
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
                label: 'Section Average',
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
        // 2. Create form and submit
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

        append('assessment_id', <?php echo intval($assessment_id); ?>);
        append('user_id', <?php echo intval($user_id); ?>);
        append('type', 'summary');

        // Charts (many)
        charts.forEach(function(chart, idx) {
            append(`charts[${idx}][label]`, chart.label);
            append(`charts[${idx}][img]`, chart.img);
        });

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