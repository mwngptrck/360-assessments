<?php
/**
 * Template Name: 360 Assessment Dashboard
 */

if (!defined('ABSPATH')) exit;

// Start session if not started
if (!session_id()) {
    session_start();
}

// Verify user session
if (!isset($_SESSION['user_id'])) {
    wp_redirect(home_url('/360-assessment-login/'));
    exit;
}

// Get current user data
$current_user = assessment_360_get_current_user();
if (!$current_user) {
    assessment_360_logout();
    wp_redirect(home_url('/360-assessment-login/'));
    exit;
}

// Get proper user ID
$user_id = isset($current_user->ID) ? $current_user->ID : 
          (isset($current_user->id) ? $current_user->id : null);

if (!$user_id) {
    assessment_360_logout();
    wp_redirect(home_url('/360-assessment-login/'));
    exit;
}

// Initialize managers
$assessment_manager = Assessment_360_Assessment_Manager::get_instance();
$user_manager = Assessment_360_User_Manager::get_instance();

// Get current active assessment
$active_assessment = $assessment_manager->get_current_assessment();

// Check if user is in Peers group
$is_peer = $user_manager->is_peer_user($current_user->id);

// Get user assessment statistics
$user_stats = $assessment_manager->get_user_assessment_stats($current_user->id);

// Get users to assess based on user type
$users_to_assess = $user_manager->get_users_for_assessment($current_user->id);

// Ensure we have user properties
$user_first_name = isset($current_user->first_name) ? $current_user->first_name : 
                  (isset($current_user->firstName) ? $current_user->firstName : '');
$user_last_name = isset($current_user->last_name) ? $current_user->last_name : 
                 (isset($current_user->lastName) ? $current_user->lastName : '');
$user_id = $current_user->ID ?? $current_user->id ?? null;



?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>360° Assessment Dashboard</title>
    <?php wp_head(); ?>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }

        body {
            background-color: var(--light-color);
            padding-top: 100px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            font-size: 14px;
        }
        
        .container-fluid {
            margin: 0 12%;
        }

        .dashboard-container {
            padding: 20px;
        }
        
        .navbar-brand img {
            height: 60px;
        }
        
        .stats {
            margin-top: 10px;
            padding: 10px;
        }

        /* Assessment Status Cards */
        .status-card, .stat-item {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
            transition: transform 0.2s;
            margin-bottom: 20px;
        }

        .status-card:hover, .stat-item:hover {
            transform: translateY(-5px);
        }

        .status-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-item {
            padding: 15px;
            text-align: center;
        }

        .stat-item label {
            display: block;
        }

        /* Rating Scale Card */
        .rating-scale {
            background: #fff;
            border-radius: 10px;
            padding: 0 20px;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .rating-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
            padding: 5px;
            border-radius: 5px;
            background: var(--light-color);
        }

        .rating-number {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-right: 15px;
            min-width: 30px;
        }

        /* Assessees List */
        .assessees-list {
            background: #fff;
            border-radius: 10px;
            padding: 20px 0;
        }

        .assessee-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid var(--light-color);
            transition: background-color 0.2s;
        }

        .assessee-item:hover {
            background-color: rgba(0,0,0,0.02);
        }

        .assessee-info {
            flex-grow: 1;
        }

        .assessee-name {
            font-weight: 600;
            color: var(--dark-color);
            font-size: 1.3em;
            margin-bottom: 0;
        }

        .assessee-position {
            font-size: 0.9em;
            color: var(--secondary-color);
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-badge.completed {
            background-color: var(--success-color);
            color: #fff;
        }

        .status-badge.warning {
            background-color: var(--warning-color);
            color: var(--dark-color);
        }

        /* Progress Bar */
        .progress {
            height: 15px;
            margin-top: 13px;
        }

        .progress-bar {
            background-color: var(--primary-color);
            transition: width .6s ease;
        }

        .progress-bar.bg-success {
            background-color: var(--success-color) !important;
        }

        /* Action Buttons */
        .start-assessment {
            display: inline-block;
            padding: 8px 16px;
            background-color: var(--primary-color);
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .start-assessment:hover {
            background-color: #0056b3;
            color: #fff;
            text-decoration: none;
        }

        /* Stats Cards */
        .stats-card {
            transition: transform .2s;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 600;
            line-height: 1.2;
        }

        .text-success {
            color: var(--success-color) !important;
        }

        .text-warning {
            color: var(--warning-color) !important;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container-fluid {
                margin: 0 5%;
            }

            .status-card {
                margin-bottom: 15px;
            }

            .assessee-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .assessment-status {
                margin-top: 10px;
            }

            .start-assessment {
                width: 100%;
                text-align: center;
                margin-top: 10px;
            }
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
            font-size: 14px;
        }

        .fw-medium {
            font-weight: 500;
        }

        .bg-success-subtle {
            background-color: rgba(25, 135, 84, 0.1);
        }

        .bg-warning-subtle {
            background-color: rgba(255, 193, 7, 0.1);
        }

        /* Table Styles */
        .table > :not(caption) > * > * {
            padding: 1rem;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        /* Button Styles */
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.2rem;
        }

        /* Badge Styles */
        .badge {
            padding: 0.5em 0.8em;
            font-weight: 500;
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .table-responsive {
                margin: 0 -1rem;
            }

            .btn-sm {
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <?php 
            $logo_url = get_option('assessment_360_organization_logo');
            $org_name = get_option('assessment_360_organization_name');
            ?>
            <a class="navbar-brand" href="#">
                <?php if ($logo_url): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($org_name); ?>">
                <?php else: ?>
                    <?php echo esc_html($org_name); ?>
                <?php endif; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                            <?php echo esc_html($user_first_name . ' ' . $user_last_name); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="<?php 
                                    echo wp_nonce_url(
                                        add_query_arg(
                                            ['action' => 'logout'], 
                                            home_url('/')
                                        ),
                                        'assessment_360_logout'  // This should match the nonce check in the handler
                                    ); 
                                ?>">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="container">
            <!-- Welcome Section -->
            <div class="row mb-4">
                <div class="col">
                    <h2>Welcome, <?php echo esc_html($user_first_name); ?>!</h2>
                    <p class="text-muted">A 360-degree feedback (also known as multi-rater feedback, multi source feedback, or multi source assessment) is a process through which feedback from an employee's subordinates, colleagues, and supervisor(s), as well as a self-evaluation by the employee themselves is gathered. Such feedback can also include, when relevant, feedback from external sources who interact with the employee, such as customers and suppliers or other interested stakeholders.</p>
                </div>
            </div>

            <?php if ($active_assessment): ?>
                <!-- Active Assessment Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title">
                                    <i class="bi bi-clipboard-check"></i>
                                    Current Assessment: <?php echo esc_html($active_assessment->name); ?>
                                </h4>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div>
                                        <span class="text-muted">Assessment Period:</span>
                                        <strong>
                                            <?php 
                                            echo date('F j, Y', strtotime($active_assessment->start_date)) . ' - ' .
                                                 date('F j, Y', strtotime($active_assessment->end_date));
                                            ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assessment Statistics -->
                <div class="row mb-4">
                    <div class="col-md-7">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-4">
                                    <i class="bi bi-card-checklist"></i>
                                    Your Assessment Progress
                                </h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="stat-item">
                                            <label>People to Assess</label>
                                            <span class="stats-number">
                                                <?php echo esc_html($user_stats->total_to_assess ?? 0); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="stat-item">
                                            <label>Completed</label>
                                            <span class="stats-number text-success">
                                                <?php echo esc_html($user_stats->completed ?? 0); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="stat-item">
                                            <label>Pending</label>
                                            <span class="stats-number text-warning">
                                                <?php echo esc_html($user_stats->pending ?? 0); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <div class="stat-item">
                                            <label>Completion Rate</label>
                                            <div class="progress">
                                                <div class="progress-bar" 
                                                     role="progressbar" 
                                                     style="width: <?php echo esc_attr($user_stats->completion_rate ?? 0); ?>%"
                                                     aria-valuenow="<?php echo esc_attr($user_stats->completion_rate ?? 0); ?>"
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    <?php echo esc_html($user_stats->completion_rate ?? 0); ?>%
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-4">
                                    <i class="bi bi-info-circle"></i>
                                    Rating Scale Guide
                                </h5>
                                <div class="rating-scale">
                                    <div class="rating-item">
                                        <span class="rating-number">5</span>
                                        <div>
                                            <div class="text-muted"><strong>Exceptional</strong> Consistently exceeds all expectations</div>
                                        </div>
                                    </div>
                                    <div class="rating-item">
                                        <span class="rating-number">4</span>
                                        <div>
                                            <div class="text-muted"><strong>Above Average</strong> Frequently exceeds expectations</div>
                                        </div>
                                    </div>
                                    <div class="rating-item">
                                        <span class="rating-number">3</span>
                                        <div>
                                            <div class="text-muted"><strong>Meets Expectations</strong> Consistently meets job requirements</div>
                                        </div>
                                    </div>
                                    <div class="rating-item">
                                        <span class="rating-number">2</span>
                                        <div>
                                            <div class="text-muted"><strong>Needs Improvement</strong> Sometimes falls short of requirements</div>
                                        </div>
                                    </div>
                                    <div class="rating-item">
                                        <span class="rating-number">1</span>
                                        <div>
                                            <div class="text-muted"><strong>Unsatisfactory</strong> Consistently falls short of requirements</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>                    
                </div>

                <!-- People to Assess List -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-4">
                                    <i class="bi bi-people"></i>
                                    <?php echo $is_peer ? 'Peer Assessment List' : 'People You Are To Assess'; ?>
                                    <?php if (!empty($users_to_assess)): ?>
                                        <span class="badge bg-primary ms-2">
                                            <?php echo count($users_to_assess); ?> User<?php echo count($users_to_assess) !== 1 ? 's' : ''; ?>
                                        </span>
                                    <?php endif; ?>
                                </h5>
                                <?php if (!empty($users_to_assess)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Position</th>
                                                    <th>Status</th>
                                                    <th class="text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($users_to_assess as $assessee): 
                                                    // Handle both id and ID properties
                                                    $assessee_id = isset($assessee->ID) ? $assessee->ID : 
                                                                (isset($assessee->id) ? $assessee->id : null);

                                                    if (!$assessee_id) {
                                                        if (WP_DEBUG) {
                                                            error_log('Warning: Assessee has no ID property');
                                                            error_log(print_r($assessee, true));
                                                        }
                                                        continue;
                                                    }

                                                    $is_self = $assessee_id == $user_id;
                                                ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="avatar-circle-sm me-2">
                                                                    <?php echo esc_html(substr($assessee->first_name, 0, 1)); ?>
                                                                </div>
                                                                <div>
                                                                    <div class="fw-medium">
                                                                        <?php echo esc_html($assessee->first_name . ' ' . $assessee->last_name); ?>
                                                                        <?php if ($is_self): ?>
                                                                            <span class="badge bg-info ms-2">Self</span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <small class="text-muted">
                                                                        <?php echo esc_html($assessee->email ?? ''); ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($assessee->position_name)): ?>
                                                                <span class="badge bg-light text-dark">
                                                                    <?php echo esc_html($assessee->position_name); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $status = $assessment_manager->get_assessment_status(
                                                                $user_id,
                                                                $assessee_id,
                                                                $active_assessment ? $active_assessment->id : null
                                                            );
                                                            $status_class = $status === 'completed' ? 'success' : 'warning';
                                                            $status_icon = $status === 'completed' ? 'check-circle' : 'clock';
                                                            ?>
                                                            <div class="d-flex align-items-center">
                                                                <i class="bi bi-<?php echo $status_icon; ?> text-<?php echo $status_class; ?> me-2"></i>
                                                                <span class="badge bg-<?php echo $status_class; ?>-subtle text-<?php echo $status_class; ?>">
                                                                    <?php echo esc_html(ucfirst($status)); ?>
                                                                </span>
                                                            </div>
                                                        </td>
                                                        <td class="text-end">
                                                            <?php if ($active_assessment && $status !== 'completed'): ?>
                                                                <a href="<?php echo esc_url(assessment_360_get_form_url(
                                                                    $active_assessment->id,
                                                                    $assessee_id,
                                                                    $is_self
                                                                )); ?>" class="btn btn-primary btn-sm">
                                                                    <i class="bi bi-pencil-square me-1"></i>
                                                                    <?php echo $is_self ? 'Self Assessment' : 'Assess'; ?>
                                                                </a>
                                                            <?php elseif ($status === 'completed'): ?>
<!--
                                                                <a href="<?php echo esc_url(add_query_arg([
                                                                    'assessment_id' => $active_assessment->id,
                                                                    'assessee_id' => $assessee_id,
                                                                    'view' => 'responses'
                                                                ])); ?>" class="btn btn-outline-primary btn-sm">
                                                                    <i class="bi bi-eye me-1"></i>View Response
                                                                </a>
-->
                                                            <span class="badge bg-success">Done</span>
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
                                            <i class="bi bi-people h1 mb-3"></i>
                                            <p class="mb-0">
                                                <?php echo $is_peer ? 
                                                    'No peer users available for assessment.' : 
                                                    'No users assigned for assessment.'; ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <h3>No Active Assessment</h3>
                    <p class="text-muted">There are no active assessments at this time.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php wp_footer(); ?>
</body>
</html>
