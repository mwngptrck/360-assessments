<?php
if (!defined('ABSPATH')) exit;

// Check if we're viewing details
if (isset($_GET['view']) && $_GET['view'] === 'detail') {
    // Load the detail view
    require_once plugin_dir_path(__FILE__) . 'results-detail.php';
    return;
}

// Initialize managers
$assessment_manager = Assessment_360_Assessment::get_instance();
$user_manager = Assessment_360_User_Manager::get_instance();

// Get current assessment filter
$current_assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;

// Get all assessments for filter
$assessments = $assessment_manager->get_all_assessments();

// Get user results based on filter
if ($current_assessment_id) {
    $users_with_results = $assessment_manager->get_users_with_results($current_assessment_id);
} else {
    $users_with_results = $assessment_manager->get_all_users_with_results();
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <i class="bi bi-bar-chart me-2"></i>Assessment Results
    </h1>
    <hr class="wp-header-end">

    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row align-items-end">
                <input type="hidden" name="page" value="assessment-360-results">
                
                <div class="col-md-4">
                    <label for="assessment_filter" class="form-label">Filter by Assessment</label>
                    <select class="form-select" id="assessment_filter" name="assessment_id">
                        <option value="">All Assessments</option>
                        <?php foreach ($assessments as $assessment): ?>
                            <option value="<?php echo esc_attr($assessment->id); ?>" 
                                    <?php selected($current_assessment_id, $assessment->id); ?>>
                                <?php echo esc_html($assessment->name); ?>
                                <?php if ($assessment->status === 'active'): ?>
                                    (Active)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-filter"></i> Apply Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Results List -->
    <div class="card">
        <div class="card-body">
            <?php if (!empty($users_with_results)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Position</th>
                                <th>Department</th>
<!--                                <th>Assessment</th>-->
                                <th colspan="2">Completion</th>
                                <th>Average Rating</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users_with_results as $result): ?>
                                <tr>
                                    <td>
                                        <?php echo esc_html($result->first_name . ' ' . $result->last_name); ?>
                                    </td>
                                    <td><?php echo esc_html($result->position_name ?? '—'); ?></td>
                                    <td><?php echo esc_html($result->group_name ?? '—'); ?></td>
                                    <td>
                                        <?php //echo esc_html($result->assessment_name); ?>
                                        
                                        <?php
                                        $completion = ($result->completed_assessments / $result->total_assessors) * 100;
                                        $completion_class = $completion >= 75 ? 'success' : 
                                                         ($completion >= 50 ? 'warning' : 'danger');
                                        ?>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo $completion_class; ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $completion; ?>%"
                                                 aria-valuenow="<?php echo $completion; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?php echo round($completion); ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>                                        
                                        <small class="text-muted">
                                            <?php echo $result->completed_assessments; ?>/<?php echo $result->total_assessors; ?> completed
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo get_rating_color($result->average_rating); ?>">
                                            <?php echo number_format($result->average_rating, 1); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?php echo esc_url(add_query_arg([
                                            'page' => 'assessment-360-results',
                                            'view' => 'detail',
                                            'assessment_id' => $result->assessment_id,
                                            'user_id' => $result->user_id
                                        ], admin_url('admin.php'))); ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-bar-chart me-1"></i>View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <?php if ($current_assessment_id): ?>
                        No results found for this assessment.
                    <?php else: ?>
                        No assessment results found.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.card {max-width: 100%}    
.progress {
    margin-bottom: 0.25rem;
}

.badge {
    font-size: 1rem;
    font-weight: normal;
    min-width: 3rem;
}
</style>
