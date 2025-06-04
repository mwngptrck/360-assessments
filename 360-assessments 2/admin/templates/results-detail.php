<?php
if (!defined('ABSPATH')) exit;

// Initialize managers
$assessment_manager = Assessment_360_Assessment::get_instance();
$user_manager = Assessment_360_User_Manager::get_instance();

// Get and validate parameters
$assessment_id = filter_input(INPUT_GET, 'assessment_id', FILTER_VALIDATE_INT);
$user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

if (!$assessment_id || !$user_id) {
    echo '<div class="alert alert-danger">Invalid assessment or user ID.</div>';
    return;
}

// Get assessment and user data
$assessment = $assessment_manager->get_assessment($assessment_id);
$user = $user_manager->get_user($user_id);

if (!$assessment || !$user) {
    echo '<div class="alert alert-danger">Assessment or user not found.</div>';
    return;
}

// Get results and determine if first assessment
try {
    $results = $assessment_manager->get_user_assessment_results($assessment_id, $user_id);
    $is_first_assessment = $assessment_manager->is_first_assessment($user_id);

    if (empty($results)) {
        echo '<div class="alert alert-info">No results found for this assessment.</div>';
        return;
    }
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading results: ' . esc_html($e->getMessage()) . '</div>';
    return;
}
?>

<div class="wrap">
    <!-- Back button -->
    <div class="mb-4">
        <a href="<?php echo esc_url(add_query_arg(['page' => 'assessment-360-results'], admin_url('admin.php'))); ?>" 
           class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Results List
        </a>
    </div>

    <h1 class="wp-heading-inline">
        Assessment Results: <?php echo esc_html($user->first_name . ' ' . $user->last_name); ?>
    </h1>
    
    <a href="<?php echo esc_url(add_query_arg([
        'action' => 'export_pdf',
        'assessment_id' => $assessment_id,
        'user_id' => $user_id
    ], admin_url('admin-post.php'))); ?>" 
       class="page-title-action">
        <i class="bi bi-file-pdf"></i> Export PDF
    </a>
    
    <hr class="wp-header-end">

    <!-- Assessment Info -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="card-title">Assessment Details</h5>
                    <table class="table table-sm">
                        <tr>
                            <th style="width: 150px;">Assessment:</th>
                            <td><?php echo esc_html($assessment->name); ?></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                <span class="badge bg-<?php echo $assessment->status === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo esc_html(ucfirst($assessment->status)); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Created:</th>
                            <td><?php echo esc_html(date('M j, Y', strtotime($assessment->created_at))); ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h5 class="card-title">User Details</h5>
                    <table class="table table-sm">
                        <tr>
                            <th style="width: 150px;">Name:</th>
                            <td><?php echo esc_html($user->first_name . ' ' . $user->last_name); ?></td>
                        </tr>
                        <tr>
                            <th>Position:</th>
                            <td><?php echo esc_html($user->position_name ?? '—'); ?></td>
                        </tr>
                        <tr>
                            <th>Department:</th>
                            <td><?php echo esc_html($user->group_name ?? '—'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- Results Section -->
    <?php if ($is_first_assessment): ?>
        <!-- First Assessment Results -->
        <?php if (!empty($results)): ?>
            <?php foreach ($results as $topic_name => $topic): ?>
                <?php if (!empty($topic['sections'])): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><?php echo esc_html($topic_name); ?></h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($topic['sections'] as $section_name => $section): ?>
                                <?php if (!empty($section['questions'])): ?>
                                    <h6 class="mb-3"><?php echo esc_html($section_name); ?></h6>
                                    <div class="table-responsive mb-4">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Question</th>
                                                    <th style="width: 100px;" class="text-center">Average Rating</th>
                                                    <th style="width: 100px;" class="text-center">Total Assessors</th>
                                                    <th>Comments</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($section['questions'] as $question): ?>
                                                    <tr>
                                                        <td><?php echo esc_html($question['text']); ?></td>
                                                        <td class="text-center">
                                                            <span class="badge bg-<?php echo get_rating_color($question['average_rating']); ?>">
                                                                <?php echo number_format($question['average_rating'], 1); ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php echo esc_html($question['total_assessors']); ?>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($question['comments'])): ?>
                                                                <ul class="list-unstyled mb-0">
                                                                    <?php foreach ($question['comments'] as $comment): ?>
                                                                        <li class="mb-2">
                                                                            <small class="text-muted">
                                                                                <strong><?php echo esc_html($comment['assessor_group']); ?>:</strong>
                                                                                <?php echo esc_html($comment['comment']); ?>
                                                                            </small>
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php else: ?>
                                                                <small class="text-muted">No comments</small>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                No results found for this assessment.
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- Comparative Results -->
        <?php if (!empty($results['topics']) && is_array($results['topics'])): ?>
            <?php foreach ($results['topics'] as $topic_name => $topic): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?php echo esc_html($topic_name); ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($topic['sections']) && is_array($topic['sections'])): ?>
                            <?php foreach ($topic['sections'] as $section_name => $section): ?>
                                <h6 class="mb-3"><?php echo esc_html($section_name); ?></h6>
                                <?php if (!empty($section['questions']) && is_array($section['questions'])): ?>
                                    <?php foreach ($section['questions'] as $question_id => $question): ?>
                                        <div class="mb-4">
                                            <p class="mb-2"><?php echo esc_html($question['text']); ?></p>
                                            <div class="chart-container" style="height: 200px;">
                                                <canvas id="chart_<?php echo esc_attr($question_id); ?>"></canvas>
                                            </div>
                                            <script>
                                                new Chart(document.getElementById('chart_<?php echo esc_attr($question_id); ?>'), {
                                                    type: 'bar',
                                                    data: {
                                                        labels: ['Self', 'Peers', 'Department', 'Others'],
                                                        datasets: [
                                                            {
                                                                label: 'Current Assessment',
                                                                data: [
                                                                    <?php echo json_encode($question['ratings']['Self'] ?? 0); ?>,
                                                                    <?php echo json_encode($question['ratings']['Peers'] ?? 0); ?>,
                                                                    <?php echo json_encode($question['ratings']['Department'] ?? 0); ?>,
                                                                    <?php echo json_encode($question['ratings']['Others'] ?? 0); ?>
                                                                ],
                                                                backgroundColor: '#0d6efd'
                                                            }
                                                        ]
                                                    },
                                                    options: {
                                                        responsive: true,
                                                        maintainAspectRatio: false,
                                                        scales: {
                                                            y: {
                                                                beginAtZero: true,
                                                                max: 5,
                                                                ticks: {
                                                                    stepSize: 1
                                                                }
                                                            }
                                                        },
                                                        plugins: {
                                                            legend: {
                                                                display: false
                                                            }
                                                        }
                                                    }
                                                });
                                            </script>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="alert alert-info">No questions found.</div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info">No sections found.</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">No comparative results found.</div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.card {
    border: none;
    border-radius: 0.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    margin-bottom: 1.5rem;
    max-width: 100%;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    padding: 1rem;
}

.card-title {
    margin-bottom: 0;
    color: #333;
}

.chart-container {
    position: relative;
    margin: auto;
    background-color: #fff;
    padding: 1rem;
    border-radius: 0.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.badge {
    font-size: 1rem;
    font-weight: normal;
    min-width: 3rem;
}

.table > :not(caption) > * > * {
    padding: 0.75rem;
}

.table th {
    background-color: #f8f9fa;
}

.list-unstyled {
    margin-bottom: 0;
}

.list-unstyled li:last-child {
    margin-bottom: 0 !important;
}

@media (max-width: 768px) {
    .card-body {
        padding: 1rem;
    }
    
    .table-responsive {
        margin: 0 -1rem;
        width: calc(100% + 2rem);
    }
}
</style>

<!-- Load Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
