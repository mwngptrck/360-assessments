<?php
if (!defined('ABSPATH')) exit;

/**
 * Authentication Functions
 */
add_action('init', function() {
    // Login Handler
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
        isset($_POST['action']) && 
        $_POST['action'] === 'assessment_360_login') {

        if (!isset($_POST['login_nonce']) || 
            !wp_verify_nonce($_POST['login_nonce'], 'assessment_360_login')) {
            wp_redirect(add_query_arg(
                'error', 
                'Invalid security token', 
                home_url('/360-assessment-login/')
            ));
            exit;
        }

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($email) || empty($password)) {
            wp_redirect(add_query_arg(
                'error', 
                'Please enter both email and password.', 
                home_url('/360-assessment-login/')
            ));
            exit;
        }

        $user_manager = Assessment_360_User_Manager::get_instance();
        $user = $user_manager->verify_login($email, $password);

        if (is_wp_error($user)) {
            wp_redirect(add_query_arg(
                'error', 
                $user->get_error_message(), 
                home_url('/360-assessment-login/')
            ));
            exit;
        }

        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['login_time'] = time();

        wp_redirect(home_url('/360-assessment-dashboard/'));
        exit;
    }

    // Logout Handler
    if (isset($_GET['action']) && $_GET['action'] === 'logout') {
        assessment_360_logout();
        wp_redirect(home_url('/360-assessment-login/'));
        exit;
    }
});

/**
 * Login Handler (if needed elsewhere)
 */
function assessment_360_handle_login() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || 
        !isset($_POST['action']) || 
        $_POST['action'] !== 'assessment_360_login') {
        return;
    }

    if (!isset($_POST['login_nonce']) || 
        !wp_verify_nonce($_POST['login_nonce'], 'assessment_360_login')) {
        wp_redirect(add_query_arg(
            'error', 
            'Invalid security token', 
            home_url('/360-assessment-login/')
        ));
        exit;
    }

    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($email) || empty($password)) {
        wp_redirect(add_query_arg(
            'error', 
            'Please enter both email and password.', 
            home_url('/360-assessment-login/')
        ));
        exit;
    }

    $user_manager = Assessment_360_User_Manager::get_instance();
    $user = $user_manager->verify_login($email, $password);

    if (is_wp_error($user)) {
        wp_redirect(add_query_arg(
            'error', 
            $user->get_error_message(), 
            home_url('/360-assessment-login/')
        ));
        exit;
    }

    $_SESSION['user_id'] = $user->id;
    $_SESSION['user_email'] = $user->email;
    $_SESSION['login_time'] = time();

    wp_redirect(home_url('/360-assessment-dashboard/'));
    exit;
}
add_action('init', 'assessment_360_handle_login');

/**
 * Logout Handler
 */
function assessment_360_handle_logout() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'logout') {
        return;
    }

    assessment_360_logout();
    wp_redirect(home_url('/360-assessment-login/'));
    exit;
}
add_action('init', 'assessment_360_handle_logout');

/**
 * Logout Function
 */
function assessment_360_logout() {
    // DO NOT call session_destroy() or clear global session cookies!
    // Only clear the plugin's session variables.
    unset($_SESSION['user_id'], $_SESSION['user_email'], $_SESSION['login_time']);
}

/**
 * Password Reset Handler
 */
add_action('init', function() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
        isset($_POST['action']) && 
        $_POST['action'] === 'assessment_360_forgot_password') {
        
        if (!isset($_POST['assessment_360_forgot_password_nonce']) || 
            !wp_verify_nonce($_POST['assessment_360_forgot_password_nonce'], 'assessment_360_forgot_password')) {
            wp_redirect(add_query_arg('error', 'Invalid security token', wp_get_referer()));
            exit;
        }

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        if (empty($email)) {
            wp_redirect(add_query_arg('error', 'Please enter your email address.', wp_get_referer()));
            exit;
        }

        $user_manager = Assessment_360_User_Manager::get_instance();
        $result = $user_manager->send_password_reset_email($email);

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('error', $result->get_error_message(), wp_get_referer()));
        } else {
            wp_redirect(add_query_arg(
                'message', 
                'If the email exists in our system, password reset instructions will be sent.',
                wp_get_referer()
            ));
        }
        exit;
    }
});