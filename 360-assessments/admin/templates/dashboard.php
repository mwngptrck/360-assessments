<?php
if (!defined('ABSPATH')) exit;

// Get current user data
//$current_user = assessment_360_get_current_user();
//if (!$current_user) {
//    assessment_360_logout();
//    wp_redirect(home_url('/360-assessment-login/'));
//    exit;
//}

// Get proper user ID
$user_id = isset($current_user->ID) ? $current_user->ID : 
          (isset($current_user->id) ? $current_user->id : null);

if (!$user_id) {
    wp_die('Invalid user');
}

// Initialize managers
$assessment_manager = Assessment_360_Assessment::get_instance();
$user_manager = Assessment_360_User_Manager::get_instance();

// Get dashboard statistics
$stats = $assessment_manager->get_dashboard_stats($user_id);

// Get active assessment
$active_assessment = $assessment_manager->get_active_assessment();

// Get organization details
$org_name = get_option('assessment_360_organization_name', '');
$org_logo = get_option('assessment_360_organization_logo', '');
$org_email = get_option('assessment_360_organization_email', '');
$org_phone = get_option('assessment_360_organization_phone', '');
$org_address = get_option('assessment_360_organization_address', '');
?>

<div class="wrap">
    <!-- Organization Header -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <?php if ($org_logo): ?>
                        <img src="<?php echo esc_url($org_logo); ?>" 
                             alt="<?php echo esc_attr($org_name); ?>" 
                             class="img-fluid" 
                             style="max-height: 100px;">
                    <?php endif; ?>
                </div>
                <div class="col-md-7">
                    <h2 class="mb-1"><?php echo esc_html($org_name); ?></h2>
                    <?php if ($org_address): ?>
                        <p class="text-muted mb-1">
                            <i class="bi bi-geo-alt me-2"></i><?php echo esc_html($org_address); ?>
                        </p>
                    <?php endif; ?>
                    <div class="d-flex gap-3">
                        <?php if ($org_email): ?>
                            <span class="text-muted">
                                <i class="bi bi-envelope me-2"></i><?php echo esc_html($org_email); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($org_phone): ?>
                            <span class="text-muted">
                                <i class="bi bi-telephone me-2"></i><?php echo esc_html($org_phone); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3 text-end">
                    <div class="d-flex flex-column align-items-end">
                        <span class="text-muted">Today's Date</span>
                        <strong><?php echo date('F j, Y'); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Active Assessment</h6>
                    <h3 class="card-title mb-0">
                        <?php echo $active_assessment ? esc_html($active_assessment->name) : 'None'; ?>
                    </h3>
                    <?php if ($active_assessment): ?>
                        <small class="text-white-50">
                            Started: <?php echo date('M j, Y', strtotime($active_assessment->created_at)); ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Completed Assessments</h6>
                    <h3 class="card-title mb-0">
                        <?php echo esc_html($stats->completed_assessments); ?> / <?php echo esc_html($stats->total_assessors); ?>
                    </h3>
                    <small class="text-white-50">
                        <?php echo esc_html($stats->completion_rate); ?>% Complete
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-warning h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Pending Assessments</h6>
                    <h3 class="card-title mb-0">
                        <?php echo esc_html($stats->pending_assessments); ?>
                    </h3>
                    <small class="text-black-50">
                        Requiring Attention
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <?php 
                    $user_stats = $user_manager->get_user_stats();
                    ?>
                    <h6 class="card-subtitle mb-2">Total Users</h6>
                    <h3 class="card-title mb-0">
                        <?php echo esc_html($user_stats->active_users); ?>
                    </h3>
                    <small class="text-white-50">
                        Active Users (<?php echo esc_html($user_stats->total_groups); ?> Groups)
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Section -->
    <div class="row g-4 mb-4">
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-graph-up me-2"></i>Assessment Progress
                    </h5>
                </div>
                <div class="card-body">
                    <div class="progress mb-3" style="height: 25px;">
                        <div class="progress-bar bg-success" 
                             role="progressbar" 
                             style="width: <?php echo esc_attr($stats->completion_rate); ?>%" 
                             aria-valuenow="<?php echo esc_attr($stats->completion_rate); ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            <?php echo esc_html($stats->completion_rate); ?>% Complete
                        </div>
                    </div>
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="p-3 border rounded bg-light">
                                <h4 class="mb-1"><?php echo esc_html($stats->total_assessors); ?></h4>
                                <small class="text-muted">Total Assessors</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 border rounded bg-light">
                                <h4 class="mb-1"><?php echo esc_html($stats->completed_assessments); ?></h4>
                                <small class="text-muted">Completed</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 border rounded bg-light">
                                <h4 class="mb-1"><?php echo esc_html($stats->pending_assessments); ?></h4>
                                <small class="text-muted">Pending</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?php echo admin_url('admin.php?page=assessment-360-assessments'); ?>" 
                           class="btn btn-outline-primary">
                            <i class="bi bi-clipboard-check me-2"></i>Manage Assessments
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=assessment-360-user-management'); ?>" 
                           class="btn btn-outline-secondary">
                            <i class="bi bi-people me-2"></i>Manage Users
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=assessment-360-forms'); ?>" 
                           class="btn btn-outline-info">
                            <i class="bi bi-file-text me-2"></i>Manage Forms
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=assessment-360-results'); ?>" 
                           class="btn btn-outline-success">
                            <i class="bi bi-bar-chart me-2"></i>View Results
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="card-title mb-0">
            <i class="bi bi-people me-2"></i>User Distribution
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <!-- Groups Distribution -->
            <div class="col-md-6">
                <h6>Users by Group</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Group</th>
                                <th class="text-end">Users</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_stats->groups as $group): ?>
                                <tr>
                                    <td><?php echo esc_html($group->group_name); ?></td>
                                    <td class="text-end"><?php echo esc_html($group->user_count); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Positions Distribution -->
            <div class="col-md-6">
                <h6>Users by Position</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Position</th>
                                <th class="text-end">Users</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_stats->positions as $position): ?>
                                <tr>
                                    <td><?php echo esc_html($position->position_name); ?></td>
                                    <td class="text-end"><?php echo esc_html($position->user_count); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- System Status -->
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="card-title mb-0">
                <i class="bi bi-gear me-2"></i>System Status
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <h6>Active Assessment</h6>
                    <?php if ($active_assessment): ?>
                        <span class="badge bg-success">Active</span>
                        <?php echo esc_html($active_assessment->name); ?>
                    <?php else: ?>
                        <span class="badge bg-warning">No Active Assessment</span>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <h6>Database Status</h6>
                    <span class="badge bg-success">Connected</span>
                    WordPress <?php echo get_bloginfo('version'); ?>
                </div>
                <div class="col-md-4">
                    <h6>Plugin Version</h6>
                    <span class="badge bg-info">v<?php echo ASSESSMENT_360_VERSION; ?></span>
                    Last Updated: <?php echo date('M j, Y'); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.wrap {padding:0 50px;}
.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
    max-width: 100%;
}

.progress {
    box-shadow: inset 0 1px 2px rgba(0,0,0,.1);
}

.bg-light {
    background-color: #f8f9fa !important;
}

.card-header {
    border-bottom: 1px solid rgba(0,0,0,.125);
}

.badge {
    font-weight: 500;
}

.btn-outline-primary:hover,
.btn-outline-secondary:hover,
.btn-outline-info:hover,
.btn-outline-success:hover {
    color: #fff;
}
</style>
