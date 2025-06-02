<?php
class Assessment_360_Security {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        // Add security headers
        add_action('send_headers', [$this, 'add_security_headers']);
        
        // Add CSRF protection
        add_action('init', [$this, 'start_session']);
        add_action('admin_init', [$this, 'verify_nonce']);
        
        // Add rate limiting
        add_action('init', [$this, 'check_rate_limit']);
    }

    public function add_security_headers() {
        if (!headers_sent()) {
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: strict-origin-source');
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net");
        }
    }

    public function start_session() {
        if (!session_id() && !headers_sent()) {
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => is_ssl(),
                'cookie_samesite' => 'Lax' // 'Lax' is more compatible for localhost/dev
            ]);
        }
    }

    public function verify_nonce() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nonce_actions = [
                'save_assessment_response',
                'save_user',
                'delete_user',
                'bulk_action_users'
            ];

            foreach ($nonce_actions as $action) {
                if (isset($_POST['action']) && $_POST['action'] === $action) {
                    check_admin_referer($action . '_nonce');
                    break;
                }
            }
        }
    }

    public function check_rate_limit() {
        if ($this->is_assessment_submission()) {
            $ip = $this->get_client_ip();
            $rate_key = 'assessment_rate_' . $ip;
            $limit = 10; // Max submissions per minute
            $current = get_transient($rate_key) ?: 0;

            if ($current >= $limit) {
                wp_die('Rate limit exceeded. Please try again later.');
            }

            set_transient($rate_key, $current + 1, 60);
        }
    }

    private function is_assessment_submission() {
        return isset($_POST['action']) && $_POST['action'] === 'save_assessment_response';
    }

    private function get_client_ip() {
        $ip = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }
}