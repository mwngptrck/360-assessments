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
        add_action('admin_post_nopriv_assessment_360_login', array($this, 'handle_login'));
        add_action('admin_post_assessment_360_logout', array($this, 'handle_logout'));
    }

    public function handle_login() {
        
        error_log('handle_login called');

        if (!isset($_POST['assessment_360_login_nonce']) || 
            !wp_verify_nonce($_POST['assessment_360_login_nonce'], 'assessment_360_login_action')) {
            $this->redirect_to_login('Invalid security token');
        }

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($email) || empty($password)) {
            $this->redirect_to_login('Please enter both email and password');
        }

        try {
           $user_manager = Assessment_360_User_Manager::get_instance();
            $user = $user_manager->get_user_by_email($email);

            if (!$user) {
                $this->redirect_to_login('Invalid email or password');
            }

            // Verify password
            if (!$this->verify_password($password, $user->password)) {
                $this->redirect_to_login('Invalid email or password');
            }

            // Check if user is active
            if ($user->status !== 'active') {
                $this->redirect_to_login('Your account is not active. Please contact administrator.');
            }

            // Standardize user data
            $user = $this->standardize_user_data($user);

            // Store user data in session
            $_SESSION['assessment_360_user'] = $user;
            $_SESSION['last_activity'] = time();

            // Update last login
            $user_manager->update_last_login($user->id);

            // Log activity
            $this->log_activity($user->id, 'login', 'User logged in successfully');

            // Redirect to dashboard
            wp_redirect(home_url('/360-assessment-dashboard/'));
            exit;

        } catch (Exception $e) {
            $this->redirect_to_login('An error occurred. Please try again.');
        }
    }

    public function handle_logout() {
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'assessment_360_logout')) {
            wp_die('Invalid security token');
        }

        // Clear session
        if (session_id()) {
            // Log activity before clearing session
            if (isset($_SESSION['assessment_360_user'])) {
                $this->log_activity(
                    $_SESSION['assessment_360_user']->id,
                    'logout',
                    'User logged out successfully'
                );
            }

            session_destroy();
            $_SESSION = array();
            
            // Delete session cookie
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }
        }

        // Redirect to login page
        wp_redirect(home_url('/360-assessment-login/'));
        exit;
    }

    private function standardize_user_data($user) {
        if (!is_object($user)) {
            return null;
        }

        $standardized = new stdClass();
        
        // Essential properties
        $standardized->id = $user->id ?? 0;
        $standardized->ID = $standardized->id; // WordPress compatibility
        $standardized->first_name = $user->first_name ?? '';
        $standardized->firstName = $standardized->first_name; // Legacy compatibility
        $standardized->last_name = $user->last_name ?? '';
        $standardized->lastName = $standardized->last_name; // Legacy compatibility
        $standardized->email = $user->email ?? '';
        
        // Optional properties
        $standardized->phone = $user->phone ?? '';
        $standardized->position_id = $user->position_id ?? 0;
        $standardized->group_id = $user->group_id ?? 0;
        $standardized->status = $user->status ?? 'active';
        $standardized->position_name = $user->position_name ?? '';
        $standardized->group_name = $user->group_name ?? '';
        
        // System properties
        $standardized->created_at = $user->created_at ?? current_time('mysql');
        $standardized->last_login = $user->last_login ?? null;

        return $standardized;
    }

    private function verify_password($password, $hash) {
        // Use WordPress password hashing if available
        if (function_exists('wp_check_password')) {
            return wp_check_password($password, $hash);
        }
        
        // Fallback to MD5 (you might want to use a stronger hashing method)
        return md5($password) === $hash;
    }

    private function redirect_to_login($error_message) {
        wp_redirect(add_query_arg(
            'error',
            urlencode($error_message),
            home_url('/360-assessment-login/')
        ));
        exit;
    }

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
            //Error here
        }
    }
}

// Initialize the auth handler
//add_action('init', array(Assessment_360_Auth_Handler::get_instance(), 'init'));
Assessment_360_Auth_Handler::get_instance()->init();
