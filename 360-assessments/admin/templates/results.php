<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-12">
                <h1 class="wp-heading-inline mb-4">
                    <i class="bi bi-graph-up me-2"></i>Assessment Results
                </h1>

                <?php
                $assessment_manager = Assessment_360_Assessment_Manager::get_instance();
                $user_manager = Assessment_360_User_Manager::get_instance();
                
                // Get all assessments
                $assessments = $assessment_manager->get_all_assessments();
                
                // Get selected assessment
                $selected_assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
                $selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
                
                if ($selected_assessment_id) {
                    $assessment = $assessment_manager->get_assessment($selected_assessment_id);
                    $assessment_users = $assessment_manager->get_assessment_users($selected_assessment_id);
                }
                ?>

                <!-- Assessment Selection -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="get" action="" class="row g-3 align-items-end">
                            <input type="hidden" name="page" value="assessment-360-results">
                            
                            <div class="col-md-6">
                                <label for="assessment_id" class="form-label">Select Assessment</label>
                                <select name="assessment_id" id="assessment_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">Select Assessment</option>
                                    <?php foreach ($assessments as $a): ?>
                                        <option value="<?php echo esc_attr($a->id); ?>" 
                                                <?php selected($selected_assessment_id == $a->id); ?>>
                                            <?php echo esc_html($a->name); ?> 
                                            (<?php echo date('M j, Y', strtotime($a->start_date)); ?> - 
                                             <?php echo date('M j, Y', strtotime($a->end_date)); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if ($selected_assessment_id && !empty($assessment_users)): ?>
                                <div class="col-md-6">
                                    <label for="user_id" class="form-label">Select User</label>
                                    <select name="user_id" id="user_id" class="form-select" onchange="this.form.submit()">
                                        <option value="">Select User</option>
                                        <?php foreach ($assessment_users as $user): ?>
                                            <option value="<?php echo esc_attr($user->id); ?>" 
                                                    <?php selected($selected_user_id == $user->id); ?>>
                                                <?php echo esc_html($user->first_name . ' ' . $user->last_name); ?>
                                                (<?php echo esc_html($user->position_name ?? '—'); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <?php if ($selected_assessment_id && !$selected_user_id): ?>
                    <!-- Users Overview -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-people me-2"></i>Assessment Overview
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>User</th>
                                            <th>Position</th>
                                            <th>Group</th>
                                            <th>Progress</th>
                                            <th>Average Rating</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assessment_users as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <strong><?php echo esc_html($user->first_name . ' ' . $user->last_name); ?></strong>
                                                        <small class="text-muted"><?php echo esc_html($user->email); ?></small>
                                                    </div>
                                                </td>
                                                <td><?php echo esc_html($user->position_name ?? '—'); ?></td>
                                                <td><?php echo esc_html($user->group_name ?? '—'); ?></td>
                                                <td style="min-width: 200px;">
                                                    <?php 
                                                    $completion_rate = $user->completion_rate ?? 0;
                                                    ?>
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar" 
                                                             role="progressbar" 
                                                             style="width: <?php echo esc_attr($completion_rate); ?>%"
                                                             aria-valuenow="<?php echo esc_attr($completion_rate); ?>"
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100">
                                                        </div>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php 
                                                        echo esc_html($user->completed_assessments) . ' of ' . 
                                                             esc_html($user->total_assessors) . ' completed';
                                                        ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if (isset($user->average_rating)): ?>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <span class="badge bg-primary">
                                                                <?php echo number_format($user->average_rating, 2); ?>
                                                            </span>
                                                            <?php if (isset($user->ratings_distribution)): ?>
                                                                <div class="rating-distribution d-flex gap-1">
                                                                    <?php foreach ($user->ratings_distribution as $rating => $count): ?>
                                                                        <div class="rating-bar" 
                                                                             data-bs-toggle="tooltip" 
                                                                             title="<?php echo $count; ?> ratings of <?php echo $rating; ?>">
                                                                            <div class="bar-fill" style="height: <?php 
                                                                                echo $user->total_responses > 0 
                                                                                    ? ($count / $user->total_responses) * 100 
                                                                                    : 0; 
                                                                            ?>%"></div>
                                                                            <small class="bar-label"><?php echo $rating; ?></small>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <a href="<?php echo esc_url(add_query_arg([
                                                        'assessment_id' => $selected_assessment_id,
                                                        'user_id' => $user->id
                                                    ])); ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-eye me-1"></i>View Details
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <?php elseif ($selected_assessment_id && $selected_user_id): 
                    $results = $assessment_manager->get_assessment_results($selected_assessment_id, $selected_user_id);
                    $user = $user_manager->get_user($selected_user_id);

                    if (!$user || !$assessment) {
                        echo '<div class="alert alert-danger">Invalid assessment or user.</div>';
                        return;
                    }
                ?>
                    <!-- Detailed Results Section -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-person-badge me-2"></i>
                                    Results for <?php echo esc_html($user->first_name . ' ' . $user->last_name); ?>
                                </h5>
                                <a href="<?php echo wp_nonce_url(
                                    add_query_arg(
                                        array(
                                            'page' => 'assessment-360-results',
                                            'export' => 'pdf',
                                            'assessment_id' => $selected_assessment_id,
                                            'user_id' => $selected_user_id
                                        ),
                                        admin_url('admin.php')
                                    ),
                                    'export_results'
                                ); ?>" class="btn btn-primary btn-sm">
                                    <i class="bi bi-download me-1"></i>Export to PDF
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Assessment Info -->
                            <div class="alert alert-light border mb-4">
                                <div class="row">
                                    <div class="col-md-4">
                                        <strong><i class="bi bi-clipboard-data me-2"></i>Assessment:</strong><br>
                                        <?php echo esc_html($assessment->name); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <strong><i class="bi bi-briefcase me-2"></i>Position:</strong><br>
                                        <?php echo esc_html($user->position_name ?? '—'); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <strong><i class="bi bi-calendar-event me-2"></i>Period:</strong><br>
                                        <?php echo date('F j, Y', strtotime($assessment->start_date)); ?> - 
                                        <?php echo date('F j, Y', strtotime($assessment->end_date)); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Results Content -->
                            <?php if (!empty($results)): ?>
                                <?php 
                                // Convert object to array if needed
                                $results_data = is_object($results) ? get_object_vars($results) : $results;

                                foreach ($results_data as $topic): 
                                ?>
                                    <div class="topic-results mb-4">
                                        <div class="card">
                                            <div class="card-header bg-light">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h5 class="card-title mb-0">
                                                        <?php echo esc_html($topic->name ?? 'Unnamed Topic'); ?>
                                                    </h5>
                                                    <?php if (isset($topic->average_rating)): ?>
                                                        <span class="badge bg-primary">
                                                            Average Rating: <?php echo number_format($topic->average_rating, 1); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if (isset($topic->questions) && is_array($topic->questions)): ?>
                                                <div class="card-body">
                                                    <div class="row g-4">
                                                        <?php foreach ($topic->questions as $question): ?>
                                                            <div class="col-md-6">
                                                                <div class="question-result p-3 border rounded h-100">
                                                                    <div class="question-text mb-3">
                                                                        <?php echo esc_html($question->text ?? $question->question_text ?? ''); ?>
                                                                    </div>

                                                                    <div class="d-flex align-items-center mb-3">
                                                                        <div class="rating-stars me-2">
                                                                            <?php 
                                                                            $rating = $question->rating ?? $question->average_rating ?? 0;
                                                                            for ($i = 1; $i <= 5; $i++): 
                                                                            ?>
                                                                                <i class="bi <?php echo $i <= $rating ? 'bi-star-fill text-warning' : 'bi-star'; ?>"></i>
                                                                            <?php endfor; ?>
                                                                        </div>
                                                                        <span class="badge bg-secondary">
                                                                            <?php echo number_format($rating, 1); ?>
                                                                        </span>
                                                                    </div>

                                                                    <?php if (!empty($question->comments)): ?>
                                                                        <div class="comments-section">
                                                                            <h6 class="text-muted mb-2">
                                                                                <i class="bi bi-chat-left-text me-1"></i>Comments:
                                                                            </h6>
                                                                            <?php foreach ($question->comments as $comment): ?>
                                                                                <div class="comment-bubble mb-2 p-2 bg-light rounded border-start border-4 border-primary">
                                                                                    <?php echo esc_html($comment->comment ?? $comment); ?>
                                                                                </div>
                                                                            <?php endforeach; ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    No results available for this assessment yet.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Please select an assessment to view results.
                    </div>
                <?php endif; ?>

            </div><!-- .col-12 -->
        </div><!-- .row -->
    </div><!-- .container-fluid -->
</div><!-- .wrap -->

<style>
/* Rating Distribution */
.rating-distribution {
    height: 30px;
    align-items: flex-end;
}

.rating-bar {
    width: 20px;
    height: 100%;
    position: relative;
    background: #f0f0f1;
    border-radius: 2px;
    overflow: hidden;
}

.bar-fill {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    background: var(--bs-primary);
    transition: height .3s ease;
}

.bar-label {
    position: absolute;
    bottom: -20px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 10px;
    color: #6c757d;
}

/* Progress Bar */
.progress {
    height: 8px;
    border-radius: 4px;
}

/* Question Results */
.question-result {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.question-result:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.rating-stars {
    display: flex;
    gap: 2px;
}

.rating-stars i {
    font-size: 1.1rem;
}

.comment-bubble {
    font-size: 0.9rem;
    color: #666;
}

/* Card Styles */
.card {
    border: none;
    border-radius: 0.5rem;
    padding: 0;
    max-width: 100%;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0,0,0,.125);
    padding: 1rem;
}

/* Print Styles */
@media print {
    .card {
        border: none !important;
        box-shadow: none !important;
    }

    .btn-primary {
        display: none !important;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Handle form submissions
    $('form').on('submit', function() {
        $(this).find('button[type="submit"]').prop('disabled', true);
    });
});
</script>
