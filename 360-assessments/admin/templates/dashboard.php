<?php
if (!defined('ABSPATH')) exit;

// Instantiate managers
$assessment_manager = Assessment_360_Assessment_Manager::get_instance();
$user_manager = Assessment_360_User_Manager::get_instance();
$group_manager = Assessment_360_Group_Manager::get_instance(); // if exists

// Fetch data
$current_assessment = $assessment_manager->get_current_assessment();
$stats = $assessment_manager->get_dashboard_stats();
$recent_activities = $assessment_manager->get_recent_activities(10);
$all_assessments = $assessment_manager->get_all_assessments();
$group_stats = $assessment_manager->get_group_stats(); // e.g. by role
$user_stats = $user_manager->get_user_stats();
$pending_assessments = $assessment_manager->get_pending_assessments(10);
$top_assessors = $assessment_manager->get_top_assessors(5);
$top_assessees = $assessment_manager->get_top_assessees(5);

// ---- Completion Rate Calculation, updated to EXCLUDE self-assessments ---- //
$completed = isset($stats->completed_pairs) ? (int)$stats->completed_pairs : 0;
$total = isset($stats->total_pairs) ? (int)$stats->total_pairs : 1;
$completion_rate = ($total > 0) ? min(round(($completed / $total) * 100), 100) : 0;
$pending = max(0, $total - $completed);

?>
<div class="wrap">
    <div class="container-fluid p-0">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="wp-heading-inline">
                        <i class="bi bi-speedometer2 me-2"></i>Assessment Dashboard
                    </h1>
                    <span class="text-muted me-3">
                        <i class="bi bi-calendar3"></i> Today is <?php echo date('F j, Y'); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row g-4 mb-4">
            <!-- Quick Actions -->
            <div class="row mt-4 ">
                <div class="col-12">
                    <div class="d-flex gap-3 justify-content-end">
                        <a class="btn btn-primary" href="admin.php?page=assessment-360-assessments&action=new">
                            <i class="bi bi-plus-circle me-1"></i> New Assessment
                        </a>
                        <a class="btn btn-secondary" href="admin.php?page=assessment-360-user-management&tab=users">
                            <i class="bi bi-person-lines-fill me-1"></i> Manage Users
                        </a>
                        <a class="btn btn-info" href="admin.php?page=assessment-360-reports">
                            <i class="bi bi-bar-chart-line me-1"></i> Reports & Analytics
                        </a>
                        <a class="btn btn-outline-secondary" href="admin.php?page=assessment-360-settings">
                            <i class="bi bi-gear me-1"></i> Settings
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Active Assessment -->
            <div class="col">
                <div class="card bg-primary bg-gradient text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0">
                                <div class="avatar-lg rounded-circle bg-primary bg-opacity-10 p-4">
                                    <i class="bi bi-clipboard-check h2 text-primary mb-0"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="card-title mb-1">Current Assessment</h5>
                                <p class="mb-0 stats text-white">
                                    <?php echo $current_assessment 
                                      ? esc_html($current_assessment->name) 
                                      : '<span class="text-white">No active assessment. <a class="text-white text-underline" href="admin.php?page=assessment-360-assessments&action=new">Create one</a></span>'; ?>
                                </p>
                                <?php if ($current_assessment): ?>
                                    <small>Period: <?php echo esc_html(date('M j, Y', strtotime($current_assessment->start_date)) . ' - ' . date('M j, Y', strtotime($current_assessment->end_date))); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Completion Rate -->
            <div class="col">
                <div class="card bg-success bg-gradient text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0">
                                <div class="avatar-lg rounded-circle bg-success bg-opacity-10 p-4">
                                    <i class="bi bi-graph-up h2 text-success mb-0"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="card-title mb-1">Completion Rate</h5>
                                <p class="mb-0 stats fs-4"><?php echo $completion_rate; ?>%</p>
                                <p class="mb-0">Completed: <?php echo $completed; ?> / <?php echo $total; ?></p>
                                <small>Pending: <?php echo $pending; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Users -->
            <div class="col">
                <div class="card bg-info bg-gradient text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0">
                                <div class="avatar-lg rounded-circle bg-info bg-opacity-10 p-4">
                                    <i class="bi bi-people h2 text-info mb-0"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="card-title mb-1">Active Users</h5>
                                <p class="mb-0 stats fs-4"><?php echo $stats->active_users; ?></p>
                                <small>Total: <?php echo $user_stats->total_users; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Assessments -->
            <div class="col">
                <div class="card bg-secondary bg-gradient text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0">
                                <div class="avatar-lg rounded-circle bg-secondary bg-opacity-10 p-4">
                                    <i class="bi bi-folder2-open h2 text-secondary mb-0"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="card-title mb-1">Assessments</h5>
                                <p class="mb-0 stats fs-4"><?php echo count($all_assessments); ?></p>
                                <small>Active: <?php echo $stats->active_assessments ?? 1; ?> | Archived: <?php echo $stats->archived_assessments ?? 0; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending & Overdue Assessments -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0"><i class="bi bi-hourglass-split me-2"></i>Pending Assessments</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($pending_assessments)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Assessor</th>
                                            <th>Assessee</th>
                                            <th>Assessment</th>
                                            <th>Due</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($pending_assessments as $pending): ?>
                                            <tr>
                                                <td><?php echo esc_html($pending->assessor_name); ?></td>
                                                <td><?php echo esc_html($pending->assessee_name); ?></td>
                                                <td><?php echo esc_html($pending->assessment_name); ?></td>
                                                <td>
                                                    <span class="text-danger">
                                                    <?php echo date('M j, Y', strtotime($pending->due_date)); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning text-dark"><?php echo esc_html(ucfirst($pending->status)); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-muted">No pending assessments.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Top Assessors/Assessees -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0"><i class="bi bi-bar-chart-steps me-2"></i>Top Participants</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h6>Top Assessors</h6>
                                <ol>
                                <?php foreach($top_assessors as $user): ?>
                                    <li><?php echo esc_html($user->name); ?> <small class="text-muted">(<?php echo $user->count; ?>)</small></li>
                                <?php endforeach; ?>
                                </ol>
                            </div>
                            <div class="col">
                                <h6>Top Assessees</h6>
                                <ol>
                                <?php foreach($top_assessees as $user): ?>
                                    <li><?php echo esc_html($user->name); ?> <small class="text-muted">(<?php echo $user->count; ?>)</small></li>
                                <?php endforeach; ?>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity / Log Feed -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-activity me-2"></i>Recent Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_activities)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Assessor</th>
                                            <th>Assessee</th>
                                            <th>Assessment</th>
                                            <th>Status</th>
                                            <th>Completed</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_activities as $activity): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-circle-sm me-2">
                                                            <?php echo esc_html(substr($activity->assessor_first_name, 0, 1)); ?>
                                                        </div>
                                                        <?php echo esc_html($activity->assessor_first_name . ' ' . $activity->assessor_last_name); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-circle-sm me-2">
                                                            <?php echo esc_html(substr($activity->assessee_first_name, 0, 1)); ?>
                                                        </div>
                                                        <?php echo esc_html($activity->assessee_first_name . ' ' . $activity->assessee_last_name); ?>
                                                    </div>
                                                </td>
                                                <td><?php echo esc_html($activity->assessment_name); ?></td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        <?php echo esc_html(ucfirst($activity->status)); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="text-muted">
                                                        <?php echo human_time_diff(strtotime($activity->completed_at), current_time('timestamp')); ?> ago
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-clock h1 mb-3"></i>
                                    <p class="mb-0">No recent activity to display.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-lg {
    width: 64px;
    height: 64px;
    background-color: #ffffff !important;
    padding: 15px !important;
}

.avatar-circle-sm {
    width: 32px;
    height: 32px;
    background-color: #e9ecef;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: #495057;
}

.card {
    margin-bottom: 24px;
    box-shadow: 0 0.75rem 1.5rem rgba(18,38,63,.03);
    max-width: 100%;
    min-height: 150px
}

.card-header {
    border-bottom: 1px solid #e9ecef;
}
.stats {font-weight: bolder}

.table > :not(caption) > * > * {
    padding: 1rem;
}

.progress {
    height: 6px;
}

ol {
    padding-left: 20px;
}
</style>