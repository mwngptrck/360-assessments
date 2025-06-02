<?php
if (!defined('ABSPATH')) exit;

// Initialize managers
$assessment_manager = Assessment_360_Assessment::get_instance();
$user_manager = Assessment_360_User_Manager::get_instance();

// At the top of results-detail.php
$assessment_id = filter_input(INPUT_GET, 'assessment_id', FILTER_VALIDATE_INT);
$user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

// Debug output
error_log('Page Parameters:');
error_log(print_r([
    'assessment_id' => $assessment_id,
    'user_id' => $user_id,
    'GET' => $_GET
], true));

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
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'assessment-360-results'], admin_url('admin.php'))); ?>" 
               class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Results List
            </a>
        </div>
    </div>

    <h1 class="wp-heading-inline">
        Assessment Results: <?php echo esc_html($user->first_name . ' ' . $user->last_name); ?>
    </h1>
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
                                                    <th style="width: 100px;">Average Rating</th>
                                                    <th style="width: 100px;">Total Assessors</th>
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
                                                        <td class="text-center"><?php echo esc_html($question['total_assessors']); ?></td>
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

    <!-- Export Options -->
    <div class="card mt-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Export Options</h5>
            <div class="row g-3">
                <!-- Overall Performance Report -->
                <div class="col-md-6 col-lg-3">
                    <?php 
                    $overall_url = wp_nonce_url(
                        add_query_arg([
                            'action' => 'generate_pdf_report',
                            'user_id' => $user_id,
                            'report_type' => 'overall_performance'
                        ], admin_url('admin-post.php')),
                        'generate_pdf_report'
                    );
                    ?>
                    <a href="<?php echo esc_url($overall_url); ?>" 
                       class="btn btn-success w-100 h-100 pdf-export-btn">
                        <div class="d-flex flex-column align-items-center">
                            <i class="bi bi-graph-up mb-2"></i>
                            <span>Overall Performance</span>
                            <small class="d-block text-white-50">View all assessments</small>
                        </div>
                    </a>
                </div>

                <!-- Individual Reports -->
                <?php
                $report_types = [
                    'detailed' => [
                        'name' => 'Detailed Report',
                        'class' => 'primary',
                        'icon' => 'file-pdf',
                        'desc' => 'Complete assessment details'
                    ],
                    'summary' => [
                        'name' => 'Summary Report',
                        'class' => 'secondary',
                        'icon' => 'file-text',
                        'desc' => 'Key findings overview'
                    ],
                    'comparative' => [
                        'name' => 'Comparative Report',
                        'class' => 'info',
                        'icon' => 'bar-chart',
                        'desc' => 'Compare with previous'
                    ]
                ];

                foreach ($report_types as $type => $details): 
                    $report_url = wp_nonce_url(
                        add_query_arg([
                            'action' => 'generate_pdf_report',
                            'assessment_id' => $assessment_id,
                            'user_id' => $user_id,
                            'report_type' => $type
                        ], admin_url('admin-post.php')),
                        'generate_pdf_report'
                    );
                ?>
                    <div class="col-md-6 col-lg-3">
                        <a href="<?php echo esc_url($report_url); ?>" 
                           class="btn btn-<?php echo esc_attr($details['class']); ?> w-100 h-100 pdf-export-btn">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-<?php echo esc_attr($details['icon']); ?> mb-2"></i>
                                <span><?php echo esc_html($details['name']); ?></span>
                                <small class="d-block text-white-50"><?php echo esc_html($details['desc']); ?></small>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <style>
.card {max-width: 100%}    
.pdf-export-btn {
    min-height: 100px;
    padding: 1rem;
    transition: all 0.3s ease;
}

.pdf-export-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.pdf-export-btn i {
    font-size: 1.5rem;
}

.pdf-export-btn small {
    font-size: 0.75rem;
    opacity: 0.85;
}

@media (max-width: 768px) {
    .pdf-export-btn {
        min-height: 80px;
    }
}
</style>
<script>
jQuery(document).ready(function($) {
    $('.pdf-export-btn').on('click', function(e) {
        const $btn = $(this);
        const originalHtml = $btn.html();
        
        $btn.addClass('disabled')
            .html('<div class="d-flex flex-column align-items-center">' +
                  '<i class="bi bi-hourglass-split mb-2"></i>' +
                  '<span>Generating...</span>' +
                  '</div>');
        
        // Re-enable after 3 seconds if no response
        setTimeout(() => {
            if ($btn.hasClass('disabled')) {
                $btn.removeClass('disabled').html(originalHtml);
            }
        }, 3000);
    });
});
</script>


