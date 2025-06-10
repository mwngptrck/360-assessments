<?php
if (!defined('ABSPATH')) exit;

if (isset($_GET['view']) && $_GET['view'] === 'detail') {
    require_once plugin_dir_path(__FILE__) . 'results-detail.php';
    return;
}
if (isset($_GET['view']) && $_GET['view'] === 'summary') {
    require_once plugin_dir_path(__FILE__) . 'results-summary.php';
    return;
}

$assessment_manager = Assessment_360_Assessment::get_instance();
$user_manager = Assessment_360_User_Manager::get_instance();
$current_assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
$assessments = $assessment_manager->get_all_assessments();

// Use the manager class with the updated method (excludes self-assessments in completion)
$users_with_results = $current_assessment_id
    ? Assessment_360_Assessment_Manager::get_instance()->get_assessment_users($current_assessment_id)
    : [];
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
                    <select class="form-select" id="assessment_filter" name="assessment_id" onchange="this.form.submit()">
                        <option value="">Select Assessment</option>
                        <?php foreach ($assessments as $assessment): ?>
                            <option value="<?php echo esc_attr($assessment->id); ?>" <?php selected($current_assessment_id, $assessment->id); ?>>
                                <?php echo esc_html($assessment->name); ?>
                                <?php if ($assessment->status === 'active'): ?> (Active)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
                                <th colspan="2">Completion</th>
                                <th>Average Rating</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users_with_results as $result): ?>
                            <tr>
                                <td><?php echo esc_html($result->first_name . ' ' . $result->last_name); ?></td>
                                <td><?php echo esc_html($result->position_name ?? '—'); ?></td>
                                <td><?php echo esc_html($result->group_name ?? '—'); ?></td>
                                <td width="250px">
                                    <?php
                                    // Use the updated values that exclude self-assessment
                                    $completion = ($result->completed_assessments && $result->total_assessors)
                                        ? ($result->completed_assessments / $result->total_assessors) * 100 : 0;
                                    $completion = min($completion, 100); // never show above 100%
                                    $completion_class = $completion >= 75 ? 'success' : ($completion >= 50 ? 'warning' : 'danger');
                                    ?>
                                    <div class="progress" style="height:20px;">
                                        <div class="progress-bar bg-<?php echo $completion_class; ?>"
                                            role="progressbar"
                                            style="width:<?php echo $completion; ?>%"
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
                                <td width="130px" class="text-center">
                                    <span class="badge bg-<?php echo get_rating_color($result->average_rating); ?>">
                                        <?php echo number_format($result->average_rating, 1); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="<?php echo esc_url(add_query_arg([
                                        'page' => 'assessment-360-results',
                                        'view' => 'detail',
                                        'assessment_id' => $current_assessment_id,
                                        'user_id' => $result->id
                                    ], admin_url('admin.php'))); ?>"
                                       class="btn btn-sm btn-outline-primary mb-1">
                                        <i class="bi bi-bar-chart me-1"></i> Detailed Results
                                    </a>
                                    <a href="<?php echo esc_url(add_query_arg([
                                        'page' => 'assessment-360-results',
                                        'view' => 'summary',
                                        'assessment_id' => $current_assessment_id,
                                        'user_id' => $result->id
                                    ], admin_url('admin.php'))); ?>"
                                       class="btn btn-sm btn-outline-info mb-1">
                                        <i class="bi bi-graph-up me-1"></i> Summarized
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach;?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>
                    <?php echo $current_assessment_id ? 'No users were assessed in this period.' : 'Select an assessment to view users.'; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<style>
.card {max-width:100%;}
.progress {margin-bottom:0rem;}
.badge {font-size:1rem;font-weight:normal;min-width:3rem;}
</style>