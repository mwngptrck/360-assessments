<?php
/**
 * Template Name: Assessment Dashboard
 */

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('/360-assessment-login/'));
    exit;
}

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <div class="assessment-360-container">
            <?php 
            // Get current user
            $user_id = assessment_360_get_current_user_id();
            $user = Assessment_360_User::get_instance()->get_user($user_id);
            
            if (!$user) {
                echo '<div class="assessment-360-error">User not found.</div>';
                get_footer();
                exit;
            }

            // Get active assessment
            $assessment = Assessment_360_Assessment::get_instance();
            $active_assessment = $assessment->get_active_assessments()[0] ?? null;

            if (!$active_assessment) {
                echo '<div class="assessment-360-notice">No active assessments at this time.</div>';
                get_footer();
                exit;
            }

            // Get users to assess
            $users_to_assess = $assessment->get_users_to_assess($active_assessment->id, $user_id);
            
            // Calculate progress
            $completed_assessments = array_filter($users_to_assess, function($user) {
                return isset($user->assessment_status) && $user->assessment_status === 'completed';
            });
            
            $completed_count = count($completed_assessments);
            $total_count = count($users_to_assess);
            $pending_count = $total_count - $completed_count;
            $completion_percentage = $total_count > 0 ? ($completed_count / $total_count) * 100 : 0;

            // Check if user is in peer group
            $is_peer = strtolower($user->group_name) === 'peers';

            // Get assessors if user is in peer group
            $my_assessors = [];
            if ($is_peer) {
                $my_assessors = $assessment->get_assessment_assessors($active_assessment->id, $user_id);
            }
            ?>

            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <?php do_action('assessment_360_dashboard_header'); ?>
                
                <div class="user-welcome">
                    <h2>Welcome, <?php echo esc_html($user->first_name); ?></h2>
                    <span class="user-role">
                        <?php echo esc_html($user->position_name ? $user->position_name : $user->group_name); ?>
                    </span>
                </div>

                <div class="assessment-info">
                    <h3><?php echo esc_html($active_assessment->name); ?></h3>
                    <div class="assessment-dates">
                        <span>Start: <?php echo date('F j, Y', strtotime($active_assessment->start_date)); ?></span>
                        <span>End: <?php echo date('F j, Y', strtotime($active_assessment->end_date)); ?></span>
                    </div>
                </div>
            </div>

            <!-- Progress Summary -->
            <div class="dashboard-section progress-section">
                <h3>Your Assessment Progress</h3>
                <div class="progress-summary">
                    <div class="progress-stats">
                        <div class="stat-item">
                            <span class="stat-label">Completed</span>
                            <span class="stat-value completed"><?php echo $completed_count; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Pending</span>
                            <span class="stat-value pending"><?php echo $pending_count; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Total</span>
                            <span class="stat-value"><?php echo $total_count; ?></span>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar">
                            <div class="progress" style="width: <?php echo esc_attr($completion_percentage); ?>%">
                                <span class="progress-text"><?php echo round($completion_percentage); ?>% Complete</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users to Assess -->
            <div class="dashboard-section assessments-section">
                <h3>Users to Assess</h3>
                <?php if (!empty($users_to_assess)): ?>
                    <div class="assessment-grid">
                        <?php foreach ($users_to_assess as $assess_user): ?>
                            <div class="assessment-card <?php echo esc_attr($assess_user->assessment_status); ?>">
                                <div class="assessment-info">
                                    <h4><?php echo esc_html($assess_user->first_name . ' ' . $assess_user->last_name); ?></h4>
                                    <?php if (!empty($assess_user->position_name)): ?>
                                        <span class="position"><?php echo esc_html($assess_user->position_name); ?></span>
                                    <?php endif; ?>
                                    <?php if ($assess_user->id === $user_id): ?>
                                        <span class="self-assessment-badge">Self Assessment</span>
                                    <?php endif; ?>
                                </div>
                                <div class="assessment-meta">
                                    <?php if ($assess_user->assessment_status === 'completed'): ?>
                                        <span class="status-badge completed">Completed</span>
                                        <?php if (!empty($assess_user->completed_at)): ?>
                                            <span class="completion-date">
                                                Completed: <?php echo date('F j, Y', strtotime($assess_user->completed_at)); ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="status-badge pending">Pending</span>
                                        <a href="<?php 
                                            echo esc_url(add_query_arg(
                                                array(
                                                    'action' => 'assess',
                                                    'assessment_id' => $active_assessment->id,
                                                    'user_id' => $assess_user->id,
                                                    'instance_id' => $assess_user->instance_id
                                                ),
                                                home_url('/assessment-form/')
                                            )); 
                                        ?>" class="button button-primary">
                                            Start Assessment
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="no-assessments">You have no assessments assigned at this time.</p>
                <?php endif; ?>
            </div>

            <?php if ($is_peer): ?>
                <!-- My Assessors Section -->
                <div class="dashboard-section my-assessors-section">
                    <h3>My Assessors</h3>
                    <?php if (!empty($my_assessors)): ?>
                        <div class="assessors-grid">
                            <?php foreach ($my_assessors as $assessor): ?>
                                <div class="assessor-card">
                                    <div class="assessor-info">
                                        <h4><?php echo esc_html($assessor->first_name . ' ' . $assessor->last_name); ?></h4>
                                        <?php if (!empty($assessor->position_name)): ?>
                                            <span class="position"><?php echo esc_html($assessor->position_name); ?></span>
                                        <?php endif; ?>
                                        <?php if ($assessor->id === $user_id): ?>
                                            <span class="self-assessment-badge">Self Assessment</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="assessor-status">
                                        <span class="status-badge <?php echo esc_attr($assessor->assessment_status ?? 'pending'); ?>">
                                            <?php echo ucfirst($assessor->assessment_status ?? 'pending'); ?>
                                        </span>
                                        <?php if (!empty($assessor->completed_at)): ?>
                                            <span class="completion-date">
                                                Completed: <?php echo date('F j, Y', strtotime($assessor->completed_at)); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="no-assessors">No assessors assigned yet.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Logout Link -->
            <div class="dashboard-footer">
                <a href="<?php echo wp_nonce_url(add_query_arg('action', 'logout', home_url('/360-assessment-login/')), 'assessment_360_logout'); ?>" 
                   class="logout-link">
                    Logout
                </a>
            </div>
        </div>
    </main>
</div>

<?php get_footer(); ?>
