<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1>360° Assessment Dashboard</h1>

    <?php
    // Get current active assessment
    $current_assessment = $assessment_manager->get_current_assessment();

    if (WP_DEBUG) {
        error_log('Backend Dashboard - Current Assessment:');
        error_log(print_r($current_assessment, true));
    }
    
    if (WP_DEBUG) {
        $debug_assessments = $assessment_manager->debug_assessment_status();
        error_log('Debug - All Assessments:');
        error_log(print_r($debug_assessments, true));
    }
    // Get Peers group statistics
    $peers_stats = $assessment_manager->get_peers_group_stats();
    
    // Get overall assessment progress
    $assessment_progress = $current_assessment ? 
        $assessment_manager->get_assessment_progress($current_assessment->id) : null;
    ?>

    <div class="dashboard-grid">
        <!-- Current Assessment Card -->
        <div class="status-card current-assessment">
            <h2>
                <i class="bi bi-clipboard-check"></i>
                Current Assessment
            </h2>
            <?php if ($current_assessment): ?>
                <div class="assessment-info">
                    <h3><?php echo esc_html($current_assessment->name); ?></h3>
                    <div class="assessment-period">
                        <span class="label">Period:</span>
                        <span class="dates">
                            <?php 
                            echo date('F j, Y', strtotime($current_assessment->start_date)) . ' - ' . 
                                 date('F j, Y', strtotime($current_assessment->end_date)); 
                            ?>
                        </span>
                    </div>
                    <div class="assessment-status">
                        <span class="status-badge status-active">Active</span>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-assessment">
                    <p>No active assessment at this time.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Peers Group Statistics -->
        <div class="status-card peers-stats">
            <h2>Peers Group Statistics</h2>
            <div class="row">
                <div class="col-5">
                    <span class="stat-label">Active Users in Peers Group</span>
                    <span class="stat-value"><?php echo esc_html($peers_stats->total_users ?? 0); ?></span>
                </div>
                <div class="col-5">
                    <span class="stat-label">Users Being Assessed</span>
                    <span class="stat-value"><?php echo esc_html($peers_stats->assessable_users ?? 0); ?></span>
                </div>
            </div>
        </div>

        <!-- Assessment Progress -->
        <?php if ($current_assessment && $assessment_progress): ?>
        <div class="status-card assessment-progress">
            <h2>Assessment Progress</h2>
            <div class="progress-stats">
                <div class="progress-wrapper">
                    <div class="progress">
                        <div class="progress-bar" 
                             role="progressbar" 
                             style="width: <?php echo esc_attr($assessment_progress->percentage); ?>%"
                             aria-valuenow="<?php echo esc_attr($assessment_progress->percentage); ?>"
                             aria-valuemin="0" 
                             aria-valuemax="100">
                        </div>
                    </div>
                    <div class="progress-numbers">
                        <span class="completed">
                            <?php echo esc_html($assessment_progress->completed); ?> Completed
                        </span>
                        <span class="percentage">
                            <?php echo esc_html($assessment_progress->percentage); ?>%
                        </span>
                        <span class="total">
                            of <?php echo esc_html($assessment_progress->total); ?> Total
                        </span>
                    </div>
                </div>
                <div class="progress-details">
                    <div class="detail-item">
                        <span class="label">Completed Assessments:</span>
                        <span class="value"><?php echo esc_html($assessment_progress->completed); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Pending Assessments:</span>
                        <span class="value"><?php echo esc_html($assessment_progress->total - $assessment_progress->completed); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Completion Rate:</span>
                        <span class="value"><?php echo esc_html($assessment_progress->percentage); ?>%</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
body {
    background-color: var(--light-color);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    font-size: 14px;
}
/* Dashboard Grid Layout */
.dashboard-grid {
    display: grid;
    gap: 24px;
    padding: 20px 0;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
}

/* Status Cards */
.status-card {
    background: #fff;
    padding: 24px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
}

.status-card h2 {
    margin: 0 0 20px 0;
    padding-bottom: 12px;
    border-bottom: 1px solid #f0f0f1;
    
    color: #1d2327;
}
    
.peers-stats .col-5{
    background: #f0f6fc;;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
    transition: transform 0.2s;
    margin: 18px;
    text-align: center;
    padding: 20px;
}

/* Current Assessment Styling */
.assessment-info h3 {
    margin: 0 0 15px 0;
    color: #2271b1;
    font-size: 18px;
}

.assessment-period {
    margin-bottom: 15px;
    color: #50575e;
    font-size: 12px;
}

.assessment-period .label {
    font-weight: 500;
    margin-right: 8px;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge.status-active {
    background-color: #00a32a;
    color: #fff;
}

/* Peers Stats Styling */
.stats-grid {
    display: grid;
    gap: 15px;
}

.stat-item {
    padding: 15px;
    background: #f0f6fc;
    border-radius: 6px;
}

.stat-item .stat-label {
    display: block;
    margin-bottom: 8px;
    color: #50575e;
    font-size: 18px;
    font-weight: 500
}

.stat-item .stat-value {
    font-size: 2.5rem;
    font-weight: 600;
    line-height: 1.2;
    color: #0073aa;
}

/* Progress Styling */
.progress-wrapper {
    margin-bottom: 20px;
}

.progress {
    height: 8px;
    background: #f0f0f1;
    border-radius: 4px;
    margin-bottom: 8px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background: #2271b1;
    transition: width 0.3s ease;
}

.progress-numbers {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    color: #50575e;
}

.progress-details {
    display: grid;
    gap: 12px;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f1;
}

.detail-item:last-child {
    border-bottom: none;
}

.detail-item .label {
    color: #50575e;
}

.detail-item .value {
    font-weight: 500;
    color: #2271b1;
}

/* Responsive Design */
@media screen and (max-width: 782px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}
</style>
