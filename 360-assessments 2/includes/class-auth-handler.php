<?php
class Assessment_360_Auth_Handler {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        // Plugin user login/logout
        add_action('admin_post_nopriv_assessment_360_login', array($this, 'handle_plugin_login'));
        add_action('admin_post_assessment_360_logout', array($this, 'handle_plugin_logout'));

        // WordPress user login/logout (for reference, not used in plugin forms)
        add_action('admin_post_nopriv_wp_login', array($this, 'handle_wp_login'));
        add_action('admin_post_wp_logout', array($this, 'handle_wp_logout'));
    }

    /**
     * Handles plugin user login (from plugin's login form)
     */
    public function handle_plugin_login() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Uncomment for debugging
        // die('Handler is being called!');
        error_log('Assessment 360: handle_plugin_login triggered');


        if (!isset($_POST['assessment_360_login_nonce']) ||
            !wp_verify_nonce($_POST['assessment_360_login_nonce'], 'assessment_360_login_action')) {
            $this->redirect_to_plugin_login('Invalid security token');
        }

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($email) || empty($password)) {
            $this->redirect_to_plugin_login('Please enter both email and password');
        }

        global $wpdb;
        $table = $wpdb->prefix . '360_users';
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE email = %s", $email));

        if (!$user) {
            $this->redirect_to_plugin_login('No user found with that email address');
        }

        // Make sure your password is hashed with password_hash()
        if (!password_verify($password, $user->password)) {
            $this->redirect_to_plugin_login('Invalid login credentials');
        }

        $_SESSION['assessment_360_user_id'] = $user->id;

        wp_redirect(home_url('/360-assessment-dashboard/'));
        exit;
    }

    /**
     * Handles plugin user logout
     */
    public function handle_plugin_logout() {
        $this->start_plugin_session();
        if (isset($_SESSION['assessment_360_user_id'])) {
            $user_id = $_SESSION['assessment_360_user_id'];
            $this->log_activity($user_id, 'plugin_logout', 'Plugin user logged out successfully');
        }
        unset($_SESSION['assessment_360_user_id']);
        wp_redirect(home_url('/360-assessment-login/'));
        exit;
    }

    /**
     * Handles WordPress user login (not used by plugin's login form)
     */
    public function handle_wp_login() {
        if (!isset($_POST['wp_login_nonce']) ||
            !wp_verify_nonce($_POST['wp_login_nonce'], 'wp_login_action')) {
            $this->redirect_to_wp_login('Invalid security token');
        }

        $username = isset($_POST['username']) ? sanitize_user($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($username) || empty($password)) {
            $this->redirect_to_wp_login('Please enter both username and password');
        }

        $credentials = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => true,
        );

        $user = wp_signon($credentials, is_ssl());

        if (is_wp_error($user)) {
            $this->redirect_to_wp_login('Invalid login credentials');
        }

        // Log login activity
        $this->log_activity($user->ID, 'wp_login', 'WP user logged in successfully');

        wp_redirect(admin_url());
        exit;
    }

    /**
     * Handles WordPress user logout (not used by plugin's logout button)
     */
    public function handle_wp_logout() {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $this->log_activity($user_id, 'wp_logout', 'WP user logged out successfully');
        }
        wp_logout();
        wp_redirect(wp_login_url());
        exit;
    }

    /**
     * Redirect helper for plugin login page with error messages
     */
    private function redirect_to_plugin_login($error_message) {
        wp_redirect(add_query_arg(
            'error',
            urlencode($error_message),
            home_url('/360-assessment-login/')
        ));
        exit;
    }

    /**
     * Redirect helper for WordPress login page with error messages
     * (for reference, not used by plugin forms)
     */
    private function redirect_to_wp_login($error_message) {
        wp_redirect(add_query_arg(
            'error',
            urlencode($error_message),
            wp_login_url()
        ));
        exit;
    }

    /**
     * Log activity for both WP and plugin users
     */
    private function log_activity($user_id, $action, $details = '') {
        try {
            global $wpdb;
            $data = array(
                'user_id' => $user_id,
                'action' => $action,
                'details' => $details,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'created_at' => current_time('mysql')
            );
            $wpdb->insert(
                $wpdb->prefix . '360_activity_log',
                $data,
                array('%d', '%s', '%s', '%s', '%s')
            );
        } catch (Exception $e) {
            // Optionally log this error elsewhere
        }
    }

    /**
     * Starts a session for plugin user authentication, if not already started
     */
    private function start_plugin_session() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Checks if a plugin user is logged in (utility for templates)
     */
    public static function is_plugin_user_logged_in() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($_SESSION['assessment_360_user_id']);
    }

    /**
     * Gets the currently logged-in plugin user (or null)
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

// Initialize the handler
Assessment_360_Auth_Handler::get_instance()->init();
