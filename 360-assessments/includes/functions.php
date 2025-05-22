<?php
if (!defined('ABSPATH')) exit;

/**
 * Format assessment status
 */
function assessment_360_format_status($status, $with_badge = true) {
    $status_map = [
        'pending' => [
            'label' => 'Pending',
            'class' => 'warning'
        ],
        'completed' => [
            'label' => 'Completed',
            'class' => 'success'
        ],
        'active' => [
            'label' => 'Active',
            'class' => 'success'
        ],
        'inactive' => [
            'label' => 'Inactive',
            'class' => 'danger'
        ],
        'draft' => [
            'label' => 'Draft',
            'class' => 'secondary'
        ]
    ];

    $status_info = $status_map[$status] ?? [
        'label' => ucfirst($status),
        'class' => 'secondary'
    ];

    if ($with_badge) {
        return sprintf(
            '<span class="badge bg-%s">%s</span>',
            esc_attr($status_info['class']),
            esc_html($status_info['label'])
        );
    }

    return $status_info['label'];
}

/**
 * Get current user from session
 * 
 * @return object|null User object or null if not logged in
 */
function assessment_360_get_current_user() {
    // Check if session is started
    if (!session_id() && !headers_sent()) {
        session_start();
    }

    // Check if user ID exists in session
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    try {
        // Get user manager instance
        $user_manager = Assessment_360_User_Manager::get_instance();
        if (!$user_manager) {
            return null;
        }

        // Get user data
        $user = $user_manager->get_user($_SESSION['user_id']);
        
        // Verify user exists and is active
        if ($user && $user->status === 'active') {
            // Add WordPress compatibility
            if (!isset($user->ID) && isset($user->id)) {
                $user->ID = $user->id;
            }
            return $user;
        }

        return null;

    } catch (Exception $e) {
        error_log('Error getting current user: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get rating description
 */
function assessment_360_get_rating_description($rating) {
    $descriptions = [
        5 => [
            'label' => 'Exceptional',
            'description' => 'Consistently exceeds all expectations'
        ],
        4 => [
            'label' => 'Above Average',
            'description' => 'Frequently exceeds expectations'
        ],
        3 => [
            'label' => 'Meets Expectations',
            'description' => 'Consistently meets job requirements'
        ],
        2 => [
            'label' => 'Needs Improvement',
            'description' => 'Sometimes falls short of requirements'
        ],
        1 => [
            'label' => 'Unsatisfactory',
            'description' => 'Consistently falls short of requirements'
        ]
    ];

    return $descriptions[$rating] ?? null;
}

/**
 * Calculate days remaining
 */
function assessment_360_get_days_remaining($end_date) {
    $end = strtotime($end_date);
    $now = time();
    $days_left = ceil(($end - $now) / (60 * 60 * 24));
    return max(0, $days_left);
}

/**
 * Format assessment progress
 */
function assessment_360_format_progress($progress, $total) {
    if ($total === 0) return '0%';
    $percentage = round(($progress / $total) * 100);
    return sprintf(
        '<div class="progress" style="height: 8px;">
            <div class="progress-bar" role="progressbar" 
                 style="width: %d%%;" 
                 aria-valuenow="%d" 
                 aria-valuemin="0" 
                 aria-valuemax="100">
            </div>
        </div>
        <small class="text-muted">%d%%</small>',
        $percentage,
        $percentage,
        $percentage
    );
}

/**
 * Check if user can assess another user
 */
function assessment_360_can_assess($assessor_id, $assessee_id) {
    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}360_user_relationships 
         WHERE assessor_id = %d AND assessee_id = %d",
        $assessor_id,
        $assessee_id
    ));
    return (bool)$exists;
}

/**
 * Get user's assessment completion status
 */
function assessment_360_get_user_completion_status($user_id, $assessment_id) {
    $assessment_manager = Assessment_360_Assessment_Manager::get_instance();
    $progress = $assessment_manager->get_user_assessment_progress($user_id, $assessment_id);
    if (!$progress) return null;
    return (object)[
        'total' => $progress->total,
        'completed' => $progress->completed,
        'pending' => $progress->pending,
        'percentage' => $progress->percentage,
        'status' => $progress->pending === 0 ? 'completed' : 'in_progress'
    ];
}

/**
 * Format time remaining
 */
function assessment_360_format_time_remaining($end_date) {
    $days = assessment_360_get_days_remaining($end_date);
    if ($days === 0) {
        return '<span class="text-danger">Due today</span>';
    } elseif ($days < 0) {
        return '<span class="text-danger">Overdue</span>';
    }
    if ($days === 1) {
        return '<span class="text-warning">1 day left</span>';
    }
    return sprintf(
        '<span class="text-muted">%d days left</span>',
        $days
    );
}

/**
 * Check if assessment is active
 */
function assessment_360_is_assessment_active($assessment) {
    if (!$assessment) return false;
    $now = time();
    $start = strtotime($assessment->start_date);
    $end = strtotime($assessment->end_date);
    return $assessment->status === 'active' && $now >= $start && $now <= $end;
}

/**
 * Get user's group name
 */
function assessment_360_get_user_group($user_id) {
    global $wpdb;
    $group = $wpdb->get_var($wpdb->prepare(
        "SELECT g.group_name 
         FROM {$wpdb->prefix}360_users u
         JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
         WHERE u.id = %d",
        $user_id
    ));
    return $group;
}

/**
 * Check if user is in peers group
 */
function assessment_360_is_peer($user_id) {
    $group_name = assessment_360_get_user_group($user_id);
    return strtolower($group_name) === 'peers';
}

/**
 * Sanitize and validate assessment data
 */
function assessment_360_sanitize_assessment_data($data) {
    return array(
        'ratings' => array_map('intval', $data['ratings']),
        'comments' => isset($data['comments']) ? 
                     array_map('sanitize_textarea_field', $data['comments']) : 
                     array()
    );
}

/**
 * Check if assessment period is valid
 */
function assessment_360_validate_assessment_period($start_date, $end_date) {
    $start = strtotime($start_date);
    $end = strtotime($end_date);
    $now = time();

    if ($end <= $start) {
        return new WP_Error(
            'invalid_dates',
            'End date must be after start date'
        );
    }

    if ($end < $now) {
        return new WP_Error(
            'past_end_date',
            'End date cannot be in the past'
        );
    }

    return true;
}
