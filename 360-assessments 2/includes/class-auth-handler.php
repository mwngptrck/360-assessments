<?php
/**
 * 360 Assessment: Authentication Handler (Static)
 * Handles plugin login/logout using admin-post.
 */

class Assessment_360_Auth_Handler {
    /**
     * Register hooks for admin-post login/logout (both logged-in and not-logged-in users)
     */
    public static function init() {
        add_action('admin_post_assessment_360_login',      [__CLASS__, 'handle_plugin_login']);
        add_action('admin_post_nopriv_assessment_360_login', [__CLASS__, 'handle_plugin_login']);
        add_action('admin_post_assessment_360_logout',      [__CLASS__, 'handle_plugin_logout']);
        add_action('admin_post_nopriv_assessment_360_logout', [__CLASS__, 'handle_plugin_logout']);
    }

    /**
     * Handles plugin user login (called by admin-post.php)
     */
    public static function handle_plugin_login() {

        // Start a session if not already active
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Nonce check for security
        if (
            !isset($_POST['assessment_360_login_nonce']) ||
            !wp_verify_nonce($_POST['assessment_360_login_nonce'], 'assessment_360_login_action')
        ) {
            self::redirect_with_error('Invalid security token. Please try again.');
        }

        // Get and sanitize input
        $email    = isset($_POST['email'])    ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($email) || empty($password)) {
            self::redirect_with_error('Please enter both email and password.');
        }

        global $wpdb;
        $table = $wpdb->prefix . '360_users';

        // Fetch user by email
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE email = %s", $email));
        if (!$user) {
            self::redirect_with_error('No user found with that email address.');
        }

        // Password check (assuming password_hash used to store passwords)
        if (!wp_check_password($password, $user->password)) {
            self::redirect_with_error('Invalid login credentials.');
        }

        // Optional: Check user status
        if (isset($user->status) && $user->status !== 'active') {
            self::redirect_with_error('Your account is not active.');
        }

        // Success: Set session and redirect to dashboard
        $_SESSION['assessment_360_user_id'] = $user->id;
        wp_redirect(home_url('/360-assessment-dashboard/'));
        exit;
    }

    /**
     * Handles plugin user logout
     */
    public static function handle_plugin_logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['assessment_360_user_id']);
        wp_redirect(home_url('/360-assessment-login/'));
        exit;
    }

    /**
     * Helper: Redirect to login page with an error message
     */
    private static function redirect_with_error($error_message) {
        wp_redirect(add_query_arg(
            'error',
            urlencode($error_message),
            home_url('/360-assessment-login/')
        ));
        exit;
    }

    /**
     * Helper: Check if a plugin user is logged in (for templates)
     */
    public static function is_plugin_user_logged_in() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($_SESSION['assessment_360_user_id']);
    }

    /**
     * Helper: Get the currently logged-in plugin user (from DB)
     */
    public static function get_plugin_user() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['assessment_360_user_id'])) {
            return null;
        }
        global $wpdb;
        $table = $wpdb->prefix . '360_users';
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $_SESSION['assessment_360_user_id']));
        return $user;
    }
}

// Register the handler
Assessment_360_Auth_Handler::init();