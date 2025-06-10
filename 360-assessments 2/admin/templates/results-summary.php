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

// Section summary grouped by group/section, with peers aggregation
$grouped = Assessment_360_Assessment_Manager::get_instance()->get_user_section_summary_grouped($assessment_id, $user_id);

// Collect all unique section names for table header
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
        <a href="<?php echo esc_url(add_query_arg(['page' => 'assessment-360-results', 'assessment_id' => $assessment_id], admin_url('admin.php'))); ?>" 
           class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Results List
        </a>
        <button class="btn btn-danger" onclick="exportChartsToPDF('summary')">
            <i class="bi bi-file-pdf"></i> Export PDF
        </button>
    </div>
    <h1 class="wp-heading-inline">Summary Results: <?php echo esc_html($user->first_name . ' ' . $user->last_name); ?></h1>
    <hr class="wp-header-end">
    <div class="mb-3">
        <a href="<?php echo esc_url(add_query_arg([
            'page' => 'assessment-360-results',
            'view' => 'detail',
            'assessment_id' => $assessment_id,
            'user_id' => $user_id
        ], admin_url('admin.php'))); ?>" class="btn btn-primary">
            <i class="bi bi-bar-chart"></i> View Detailed Results
        </a>
    </div>
    <?php if (!empty($grouped)): ?>

        <!-- Compact Table Summary -->
        <div class="mb-4">
            <h3>Section Averages by Group (Compact Table)</h3>
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
                                                echo '<td>' . esc_html(number_format($section['average'], 2)) . '</td>';
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

        <!-- Graphs per group/section -->
        <?php foreach ($grouped as $group): ?>
            <div class="card mb-4">
                <div class="card-header"><h4><?php echo esc_html($group['group_name']); ?></h4></div>
                <div class="card-body">
                    <?php if (!empty($group['sections'])): ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                        <?php foreach ($group['sections'] as $section):
                            $canvas_id = 'section_summary_' . md5($group['group_name'] . '_' . $section['section_name']);
                        ?>
                            <div style="width: 220px;">
                                <h5 style="font-size: 1rem; text-align:center;"><?php echo esc_html($section['section_name'] ?? 'Section'); ?></h5>
                                <div class="chart-container" style="height: 220px;">
                                    <div class="chart-label" data-canvas="<?php echo esc_attr($canvas_id); ?>" style="display:none;">
                                        <?php
                                            echo esc_html("{$group['group_name']} - {$section['section_name']}");
                                        ?>
                                    </div>
                                    <canvas id="<?php echo esc_attr($canvas_id); ?>" width="200" height="200" style="display:block; margin:0 auto;"></canvas>
                                </div>
                                <script>
                                (function(){
                                    const ctx = document.getElementById('<?php echo esc_js($canvas_id); ?>').getContext('2d');
                                    new Chart(ctx, {
                                        type: 'bar',
                                        data: {
                                            labels: ['<?php echo esc_js($section['section_name'] ?? 'Section'); ?>'],
                                            datasets: [{
                                                label: 'Section Average',
                                                data: [<?php echo json_encode($section['average']); ?>],
                                                backgroundColor: '#3498db'
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
                                })();
                                </script>
                                <div>Average: <strong><?php echo esc_html(number_format($section['average'], 2)); ?></strong></div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">No sections found for this group.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-info">No summary results found for this user/assessment.</div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function exportChartsToPDF(type) {
    setTimeout(function() {
        const chartCanvases = document.querySelectorAll('canvas[id^="section_summary_"]');
        const images = [];
        chartCanvases.forEach(canvas => {
            let labelDiv = canvas.parentElement.querySelector('.chart-label[data-canvas="'+canvas.id+'"]');
            let label = labelDiv ? labelDiv.textContent.trim() : canvas.id;
            images.push({
                id: canvas.id,
                img: canvas.toDataURL('image/png'),
                label: label
            });
        });
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo admin_url('admin-post.php?action=export_pdf'); ?>';
        form.style.display = 'none';
        images.forEach((obj) => {
            const inputImg = document.createElement('input');
            inputImg.type = 'hidden';
            inputImg.name = 'charts[' + obj.id + '][img]';
            inputImg.value = obj.img;
            form.appendChild(inputImg);

            const inputLabel = document.createElement('input');
            inputLabel.type = 'hidden';
            inputLabel.name = 'charts[' + obj.id + '][label]';
            inputLabel.value = obj.label;
            form.appendChild(inputLabel);
        });
        form.innerHTML += `<input type="hidden" name="assessment_id" value="<?php echo $assessment_id; ?>">`;
        form.innerHTML += `<input type="hidden" name="user_id" value="<?php echo $user_id; ?>">`;
        form.innerHTML += `<input type="hidden" name="type" value="${type}">`;
        document.body.appendChild(form);
        form.submit();
    }, 500); // delay to ensure chart rendering
}
</script>
<style>
.chart-container { background: #fff; border-radius: 8px; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); padding: 1rem;}
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