<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <div class="container-fluid p-0">
        <!-- Organization Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <?php 
                            $logo_url = get_option('assessment_360_organization_logo');
                            $org_name = get_option('assessment_360_organization_name', 'Organization Name');
                            if ($logo_url): 
                            ?>
                                <img src="<?php echo esc_url($logo_url); ?>" 
                                     alt="<?php echo esc_attr($org_name); ?>" 
                                     class="me-3" 
                                     style="max-height: 50px;">
                            <?php endif; ?>
                            <h1 class="h3 mb-0"><?php echo esc_html($org_name); ?> Dashboard</h1>
                        </div>
                        <div class="text-end">
                            <div class="text-muted">
                                <i class="bi bi-calendar3"></i> 
                                Today is:  <?php echo date('F j, Y'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php
        // Get current active assessment
        $current_assessment = $assessment_manager->get_current_assessment();
        
        // Get Peers group statistics
        $peers_stats = $assessment_manager->get_peers_group_stats();
        
        // Get overall assessment progress
        $assessment_progress = $current_assessment ? 
            $assessment_manager->get_assessment_progress($current_assessment->id) : null;
        ?>

        

        <!-- Main Content Row -->
        <div class="row g-4">
            <!-- Current Assessment Card -->
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clipboard-data me-2"></i>Current Assessment <span class="badge bg-success badge-active">Active</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($current_assessment): ?>
                            <h4 class="text-primary mb-3"><?php echo esc_html($current_assessment->name); ?></h4>
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-calendar3 me-2 text-muted"></i>
                                    <span class="text-muted">Assessment Period: </span> <?php echo date('F j, Y', strtotime($current_assessment->start_date)) . ' - ' . date('F j, Y', strtotime($current_assessment->end_date)); ?>
                                </div>
                                
                            </div>
                            
                            <?php if ($assessment_progress): ?>
                                <div class="mt-4">
                                    <h6 class="mb-3">Progress Overview</h6>
                                    <div class="progress mb-2" style="height: 10px;">
                                        <div class="progress-bar" 
                                             role="progressbar" 
                                             style="width: <?php echo esc_attr($assessment_progress->percentage); ?>%"
                                             aria-valuenow="<?php echo esc_attr($assessment_progress->percentage); ?>"
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between text-muted small">
                                        <span><?php echo esc_html($assessment_progress->completed); ?> Completed</span>
                                        <span><?php echo esc_html($assessment_progress->percentage); ?>%</span>
                                        <span><?php echo esc_html($assessment_progress->total); ?> Total</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-clipboard-x display-4 text-muted mb-3"></i>
                                <p class="text-muted">No active assessment at this time.</p>
                                <a href="<?php echo admin_url('admin.php?page=assessment-360-assessments'); ?>" 
                                   class="btn btn-primary btn-sm">
                                    <i class="bi bi-plus-lg me-1"></i>Create Assessment
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Assessment Statistics -->
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-graph-up me-2"></i>Assessment Statistics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-sm-6">
                                <div class="p-3 bg-light rounded">
                                    <h6 class="mb-2">Completed Assessments</h6>
                                    <h3 class="mb-0">
                                        <?php echo $assessment_progress ? 
                                            esc_html($assessment_progress->completed) : '0'; ?>
                                    </h3>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-3 bg-light rounded">
                                    <h6 class="mb-2">Pending Assessments</h6>
                                    <h3 class="mb-0">
                                        <?php 
                                        if ($assessment_progress) {
                                            echo esc_html($assessment_progress->total - $assessment_progress->completed);
                                        } else {
                                            echo '0';
                                        }
                                        ?>
                                    </h3>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-3 bg-light rounded">
                                    <h6 class="mb-2">Active Users</h6>
                                    <h3 class="mb-0"><?php echo esc_html($peers_stats->total_users ?? 0); ?></h3>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-3 bg-light rounded">
                                    <h6 class="mb-2">Users Being Assessed</h6>
                                    <h3 class="mb-0"><?php echo esc_html($peers_stats->assessable_users ?? 0); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats Row -->
        <div class="row g-4 mb-4">
            <!-- Total Users Card -->
            <div class="col-sm-6 col-xl-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 rounded-circle bg-primary bg-opacity-10 p-3">
                                <i class="bi bi-people h4 mb-0 text-primary"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1">Total Users</h6>
                                <h3 class="mb-0"><?php echo esc_html($peers_stats->total_users ?? 0); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Assessments Card -->
            <div class="col-sm-6 col-xl-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 rounded-circle bg-success bg-opacity-10 p-3">
                                <i class="bi bi-clipboard-check h4 mb-0 text-success"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1">Users Being Assessed</h6>
                                <h3 class="mb-0"><?php echo esc_html($peers_stats->assessable_users ?? 0); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Completion Rate Card -->
            <div class="col-sm-6 col-xl-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 rounded-circle bg-warning bg-opacity-10 p-3">
                                <i class="bi bi-graph-up h4 mb-0 text-warning"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1">Completion Rate</h6>
                                <h3 class="mb-0">
                                    <?php echo $assessment_progress ? 
                                        esc_html($assessment_progress->percentage) . '%' : 
                                        'N/A'; ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Time Remaining Card -->
            <div class="col-sm-6 col-xl-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 rounded-circle bg-info bg-opacity-10 p-3">
                                <i class="bi bi-clock h4 mb-0 text-info"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1">Days Remaining</h6>
                                <h3 class="mb-0">
                                    <?php
                                    if ($current_assessment) {
                                        $days = ceil((strtotime($current_assessment->end_date) - time()) / (60 * 60 * 24));
                                        echo max(0, $days);
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

            <!-- Recent Activity -->
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-activity me-2"></i>Recent Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Add your recent activity data here
                                    // This is a placeholder for demonstration
                                    $activities = [
                                        ['John Doe', 'Completed Assessment', '2 hours ago', 'success'],
                                        ['Jane Smith', 'Started Assessment', '3 hours ago', 'warning'],
                                        ['Mike Johnson', 'Submitted Feedback', '5 hours ago', 'info'],
                                    ];
                                    
                                    foreach ($activities as $activity):
                                    ?>
                                        <tr>
                                            <td><?php echo $activity[0]; ?></td>
                                            <td><?php echo $activity[1]; ?></td>
                                            <td><?php echo $activity[2]; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $activity[3]; ?>">
                                                    <?php echo ucfirst($activity[3]); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Card Styles */
.card {
    border: none;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    padding: 0;
    max-width: 100%
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0,0,0,.125);
    padding: 1rem;
}

.card-title {
    margin-bottom: 0;
    color: #333;
}
.badge-active { 
    position: absolute;
    margin-left: 20px;
}
/* Progress Bar */
.progress {
    background-color: #e9ecef;
    border-radius: 0.25rem;
    overflow: hidden;
}

.progress-bar {
    background-color: var(--bs-primary);
    transition: width 0.3s ease;
}

/* Stats Cards */
.rounded-circle {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Responsive Design */
@media (max-width: 768px) {
    .card-body {
        padding: 1rem;
    }
}
</style>
