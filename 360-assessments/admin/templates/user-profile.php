<?php
if (!defined('ABSPATH')) exit;

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}

// Initialize global wpdb
global $wpdb;


    // Get user ID from URL
    $user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$user_id) {
        throw new Exception('Invalid user ID');
    }

    // Initialize managers
    $user_manager = Assessment_360_User_Manager::get_instance();
    $assessment_manager = Assessment_360_Assessment_Manager::get_instance();

    // Get user data with error handling
    $user = $user_manager->get_user($user_id);
    if (!$user) {
        throw new Exception('User not found');
    }

    // Get current assessment
    $current_assessment = $assessment_manager->get_current_assessment();
    $current_assessment_id = $current_assessment ? $current_assessment->id : null;

    // Initialize variables with default values
    $is_peer = false;
    $assessors = array();
    $assessees = array();
    $assessments = array();

    // Validate user group data and set peer status
    if (!isset($user->group_id) || !isset($user->group_name)) {
        $user->group_name = 'No Group Assigned';
    } else {
        $is_peer = strtolower($user->group_name) === 'peers';
    }

    // Load data based on user type
    if ($is_peer) {
        $assessors = $user_manager->get_user_assessors($user_id);
        $assessments = $assessment_manager->get_user_assessments($user_id);
    } else {
        $assessees = $user_manager->get_user_assessees($user_id);
    }

?>

<div class="wrap">
    <div class="mb-4 d-sm-flex align-items-center justify-content-between">
<!--
        <h1 class="wp-heading-inline h3 mb-3 mb-sm-0">
            <i class="bi bi-person-circle me-2"></i>User Profile for: <mark><?php echo esc_html($user->first_name . ' ' . $user->last_name); ?></mark>
        </h1>
-->
        <a href="<?php echo esc_url(add_query_arg(['page' => 'assessment-360-user-management'], admin_url('admin.php'))); ?>" 
           class="btn btn-outline-primary">
            <i class="bi bi-arrow-left me-1"></i>Back to Users
        </a>
    </div>

    <?php if (isset($_GET['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo esc_html($_GET['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Basic Information Card -->
        <div class="col-lg-3 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h2 class="card-title h5 mb-0">
                        <i class="bi bi-person me-2"></i>Basic Information
                    </h2>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar-circle mt-3">
                            <span class="avatar-initials">
                                <?php echo esc_html(substr($user->first_name, 0, 1) . substr($user->last_name, 0, 1)); ?>
                            </span>
                        </div>
                        <h3 class="h4 mb-1"><mark><?php echo esc_html($user->first_name . ' ' . $user->last_name); ?></mark></h3>
                        <p class="text-muted mb-2"><?php echo esc_html($user->position_name ?? 'No Position'); ?></p>
                        <span class="badge bg-<?php echo $user->status === 'active' ? 'success' : 'danger'; ?>">
                            <?php echo esc_html(ucfirst($user->status)); ?>
                        </span>
                    </div>

                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-muted">Email: </span>
                            <span><?php echo esc_html($user->email); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-muted">Phone: </span>
                            <span><?php echo esc_html($user->phone ?? 'Not provided'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-muted">User Group: </span>
                            <span class="badge bg-primary">
                                <?php echo esc_html($user->group_name ?? 'No Group Assigned'); ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="row profile-assessment">
                <?php if ($is_peer): ?>
                    <!-- Assessors Section for Peers -->
                    <div class="col-md-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-light">
                            <h2 class="card-title h5 mb-0">
                                <i class="bi bi-people me-2"></i>My Assessors
                                <?php if (!empty($assessors)): ?>
                                    <span class="badge bg-primary ms-2">
                                        <?php echo count($assessors); ?> Assessor<?php echo count($assessors) !== 1 ? 's' : ''; ?>
                                    </span>
                                <?php endif; ?>
                            </h2>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($assessors)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Assessor Name</th>
                                                <th>Group</th>
                                                <th class="text-end">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($assessors as $assessor): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-xs me-2">
                                                                <?php echo substr($assessor->first_name, 0, 1); ?>
                                                            </div>
                                                            <?php echo esc_html($assessor->first_name . ' ' . $assessor->last_name); ?>
                                                        </div>
                                                    </td>
                                                    <td><?php echo esc_html($assessor->group_name ?? 'No Group'); ?></td>
                                                    <td class="text-end">
                                                        <?php
                                                        $status = $assessment_manager->get_assessment_status(
                                                            $assessor->id,
                                                            $user_id,
                                                            $current_assessment_id
                                                        );
                                                        $status_class = $status === 'completed' ? 'success' : 'warning';
                                                        ?>
                                                        <span class="badge bg-<?php echo $status_class; ?>">
                                                            <?php echo esc_html(ucfirst($status)); ?>
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
                                        <i class="bi bi-people h1"></i>
                                        <p class="mt-2">No assessors assigned yet.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    </div>

                    <div class="col-md-8">
                        <!-- Assessment Results Section -->
                        <div class="card shadow-sm">
                            <div class="card-header bg-light">
                                <h2 class="card-title h5 mb-0">
                                    <i class="bi bi-clipboard-data me-2"></i>Assessment Results
                                </h2>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($assessments)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Assessment</th>
                                                    <th>Period</th>
                                                    <th>Status</th>
                                                    <th class="text-end"><!--Actions--></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($assessments as $assessment): ?>
                                                    <tr>
                                                        <td><?php echo esc_html($assessment->name); ?></td>
                                                        <td>
                                                            <small class="text-muted">
                                                                <?php 
                                                                echo esc_html(date('M j, Y', strtotime($assessment->start_date))) . ' - ' .
                                                                     esc_html(date('M j, Y', strtotime($assessment->end_date)));
                                                                ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $assessment_status = $assessment_manager->get_user_assessment_status($user_id, $assessment->id);
                                                            $completion_percentage = $assessment_manager->get_completion_percentage($user_id, $assessment->id);
                                                            $status_class = $assessment_status === 'completed' ? 'success' : 'warning';
                                                            ?>
                                                            <div class="d-flex align-items-center">
                                                                <span class="badge bg-<?php echo $status_class; ?> me-2">
                                                                    <?php echo esc_html(ucfirst($assessment_status)); ?>
                                                                </span>
                                                                <?php if ($assessment_status === 'ongoing'): ?>
                                                                    <small class="text-muted"><?php echo $completion_percentage; ?>%</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td class="text-end">
                                                            <?php if ($assessment_status === 'completed'): ?>
                                                                <a href="<?php echo esc_url(add_query_arg([
                                                                    'page' => 'assessment-360-results',
                                                                    'assessment_id' => $assessment->id,
                                                                    'user_id' => $user->id
                                                                ], admin_url('admin.php'))); ?>" 
                                                                   class="btn btn-sm btn-primary">
                                                                    <i class="bi bi-bar-chart"></i> View Results
                                                                </a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bi bi-clipboard-x h1"></i>
                                            <p class="mt-2">No assessment results available.</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Users to Assess Section -->
                        <div class="card shadow-sm" style="margin-top: 20px;">
                            <div class="card-header bg-light">
                                <h2 class="card-title h5 mb-0">
                                    <i class="bi bi-people-fill me-2"></i>Users to Assess
                                    <?php 
                                    $total_assessees = is_array($assessees) ? count($assessees) : 0;
                                    if ($total_assessees > 0):
                                    ?>
                                        <span class="badge bg-primary ms-2">
                                            <?php echo $total_assessees; ?> User<?php echo $total_assessees !== 1 ? 's' : ''; ?>
                                        </span>
                                    <?php endif; ?>
                                </h2>
                            </div>
                            <div class="card-body">
                                <?php 
                                if (!empty($assessees)): 
                                ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Position</th>
                                                    <th>Group</th>
                                                    <th class="text-end">Status</th>
<!--                                                    <th class="text-end">Actions</th>-->
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($assessees as $assessee): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="avatar-xs me-2">
                                                                    <?php echo substr($assessee->first_name, 0, 1); ?>
                                                                </div>
                                                                <?php echo esc_html($assessee->first_name . ' ' . $assessee->last_name); ?>
                                                            </div>
                                                        </td>
                                                        <td><?php echo esc_html($assessee->position_name ?? 'No Position'); ?></td>
                                                        <td><?php echo esc_html($assessee->group_name ?? 'No Group'); ?></td>
                                                        <td class="text-end">
                                                            <?php
                                                            $status = $assessment_manager->get_assessment_status(
                                                                $user_id,
                                                                $assessee->id,
                                                                $current_assessment_id
                                                            );
                                                            $status_class = $status === 'completed' ? 'success' : 'warning';
                                                            ?>
                                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                                <?php echo esc_html(ucfirst($status)); ?>
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
                                            <i class="bi bi-people-fill h1"></i>
                                            <p class="mt-2">No users assigned for assessment.</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div><!--col-->
                <?php endif; ?>
            </div><!--row-->
        </div>
    </div>
</div>

<style>
.wp-heading-inline, .wrap h1.wp-heading-inline {display: block}
mark {
    background-color: gold;
    text-transform: uppercase
}
.avatar-circle {
    width: 80px;
    height: 80px;
    background-color: #e9ecef;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.avatar-initials {
    font-size: 24px;
    font-weight: 600;
    color: #495057;
}

.avatar-xs {
    width: 32px;
    height: 32px;
    background-color: #e9ecef;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: #495057;
}

.card-body {
        padding: inherit
}
.card {
    border: none;
    border-radius: 0.5rem;
    padding: 0;
    max-width: 100%;
}

.card-header {
    border-bottom: 1px solid rgba(0,0,0,.125);
    background-color: #f8f9fa;
    padding: 1rem;
}

/*.profile-assessment {margin-top: 20px}*/
    
.table > :not(caption) > * > * {
    padding: 1rem;
}

.badge {
    padding: 0.5em 0.8em;
    font-weight: 500;
}

.btn-outline-primary {
    border-width: 2px;
}

.text-muted {
    color: #6c757d !important;
}

@media (max-width: 768px) {
    .table-responsive {
        margin: 0 -1rem;
    }
}
</style>
