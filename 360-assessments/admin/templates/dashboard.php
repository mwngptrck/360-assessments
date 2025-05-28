<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <div class="container-fluid p-0">
        <!-- Header Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="wp-heading-inline">
                        <i class="bi bi-speedometer2 me-2"></i>Dashboard
                    </h1>
                    <div class="d-flex align-items-center">
                        <span class="text-muted me-3">
                            <i class="bi bi-calendar3"></i> 
                            <?php echo date('F j, Y'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <?php
            $stats = $assessment_manager->get_dashboard_stats();
            $current_assessment = $assessment_manager->get_current_assessment();
            ?>
            
            <!-- Active Assessment Card -->
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
                                <h3 class="mb-0">
                                    <?php echo $current_assessment ? 
                                        esc_html($current_assessment->name) : 
                                        '<span class="text-muted">None Active</span>'; ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Completion Rate Card -->
            <div class="col">
                <div class="card bg-secondary bg-gradient text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0">
                                <div class="avatar-lg rounded-circle bg-success bg-opacity-10 p-4">
                                    <i class="bi bi-graph-up h2 text-success mb-0"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="card-title mb-1">Completion Rate</h5>
                                <h3 class="mb-0">
                                    <?php echo $stats->completion_rate; ?>%
                                </h3>
                                <p class="text-muted mb-0">
                                    <?php echo $stats->completed_assessments; ?> of 
                                    <?php echo $stats->total_assessors; ?> completed
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Users Card -->
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
                                <h3 class="mb-0"><?php echo $stats->active_users; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-activity me-2"></i>Recent Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $recent_activities = $assessment_manager->get_recent_activities(10);
                        if (!empty($recent_activities)):
                        ?>
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
/* Add any additional styles you need */
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

.table > :not(caption) > * > * {
    padding: 1rem;
}
</style>

