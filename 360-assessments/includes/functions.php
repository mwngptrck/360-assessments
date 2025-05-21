<?php
if (!defined('ABSPATH')) exit;

/**
 * Start session if not started
 */
function assessment_360_start_session() {
    if (!session_id() && !headers_sent()) {
        session_start();
    }
}
add_action('init', 'assessment_360_start_session', 1);

/**
 * Check if user is logged in (canonical, unified session key)
 */
function assessment_360_is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user from session
 */
function assessment_360_get_current_user() {
    if (!assessment_360_is_logged_in()) {
        return null;
    }
    $user_manager = Assessment_360_User_Manager::get_instance();
    return $user_manager->get_user($_SESSION['user_id']);
}

/**
 * Get user ID safely
 */
function assessment_360_get_user_id($user) {
    if (!$user) return null;
    return $user->ID ?? $user->id ?? null;
}

/**
 * Logout user (clear session, destroy, and cookies)
 */
function assessment_360_logout() {
    if (!session_id()) {
        session_start();
    }
    $_SESSION = array();
    session_destroy();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    if (WP_DEBUG) {
        error_log('User logged out successfully');
    }
}

/**
 * Asset version control (optional)
 */
// function assessment_360_asset_version($file) {
//     if (WP_DEBUG) {
//         return filemtime(ASSESSMENT_360_PLUGIN_DIR . $file);
//     }
//     return ASSESSMENT_360_VERSION;
// }

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
 * Get assessment form URL
 */
function assessment_360_get_assessment_form_url($assessment_id, $assessee_id) {
    return add_query_arg([
        'assessment_id' => $assessment_id,
        'assessee_id' => $assessee_id
    ], home_url('/assessment-form/'));
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

/**
 * Log assessment activity (for debug/audit)
 */
function assessment_360_log_activity($user_id, $action, $details = '') {
    global $wpdb;
    if (!WP_DEBUG) return;
    $wpdb->insert(
        $wpdb->prefix . '360_activity_log',
        array(
            'user_id' => $user_id,
            'action' => $action,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'created_at' => current_time('mysql')
        ),
        array('%d', '%s', '%s', '%s', '%s')
    );
}

/**
 * Verify user access to assessment
 */
function assessment_360_verify_assessment_access($assessment_id) {
    global $wpdb;
    $user = assessment_360_get_current_user();
    if (!$user) {
        return false;
    }
    $has_access = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) 
         FROM {$wpdb->prefix}360_assessment_instances 
         WHERE assessment_id = %d 
         AND (assessor_id = %d OR assessee_id = %d)",
        $assessment_id,
        $user->id,
        $user->id
    ));
    return $has_access > 0;
}

/**
 * Password verification using WP native method
 */
function assessment_360_verify_password($password, $hash) {
    if (WP_DEBUG) {
        error_log('Verifying password');
        error_log('Hash length: ' . strlen($hash));
    }
    $verified = wp_check_password($password, $hash);
    if (WP_DEBUG) {
        error_log('Password verification result: ' . ($verified ? 'Success' : 'Failed'));
    }
    return $verified;
}

/**
 * Test user login (for debug)
 */
function assessment_360_test_user_login($email) {
    global $wpdb;
    $user = $wpdb->get_row($wpdb->prepare(
        "SELECT * 
         FROM {$wpdb->prefix}360_users 
         WHERE email = %s 
         AND status = 'active'",
        $email
    ));
    if (WP_DEBUG) {
        error_log('Test user query: ' . $wpdb->last_query);
        if ($user) {
            error_log('User found: ' . print_r($user, true));
        } else {
            error_log('No user found with email: ' . $email);
        }
    }
    return $user;
}