<?php
class Assessment_360_Template_Loader {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('template_include', array($this, 'load_template'));
        add_action('init', array($this, 'register_endpoints'));
        add_action('template_redirect', array($this, 'protect_pages'));
    }

    public function register_endpoints() {
        add_rewrite_endpoint('assessment-dashboard', EP_ROOT);
        add_rewrite_endpoint('assessment-form', EP_ROOT);
        
        if (get_option('assessment_360_flush_rewrite') != true) {
            flush_rewrite_rules();
            update_option('assessment_360_flush_rewrite', true);
        }
    }

    public function load_template($template) {
        global $post;
        
        $current_slug = $post ? $post->post_name : '';
        
        switch ($current_slug) {
            case 'assessment-dashboard':
                return $this->load_dashboard_template();
                
            case 'assessment-form':
                return $this->load_form_template();
                
            case '360-assessment-login':
                return $this->load_login_template();
        }
        
        return $template;
    }

    private function load_dashboard_template() {
        if (!assessment_360_check_user_session()) {
            wp_redirect(add_query_arg(
                'redirect_to',
                urlencode($_SERVER['REQUEST_URI']),
                assessment_360_get_login_url()
            ));
            exit;
        }

        $template = ASSESSMENT_360_PLUGIN_DIR . 'templates/page-assessment-dashboard.php';
        if (file_exists($template)) {
            return $template;
        }

        return get_page_template();
    }

    private function load_form_template() {
        if (!assessment_360_check_user_session()) {
            wp_redirect(add_query_arg(
                'redirect_to',
                urlencode($_SERVER['REQUEST_URI']),
                assessment_360_get_login_url()
            ));
            exit;
        }

        // Verify required parameters
        $required_params = array('action', 'assessment_id', 'user_id', 'instance_id');
        foreach ($required_params as $param) {
            if (!isset($_GET[$param])) {
                wp_redirect(home_url('/assessment-dashboard/'));
                exit;
            }
        }

        $template = ASSESSMENT_360_PLUGIN_DIR . 'templates/page-assessment-form.php';
        if (file_exists($template)) {
            return $template;
        }

        return get_page_template();
    }

    private function load_login_template() {
        $template = ASSESSMENT_360_PLUGIN_DIR . 'templates/page-login.php';
        if (file_exists($template)) {
            return $template;
        }

        return get_page_template();
    }

    public function protect_pages() {
        $protected_pages = array('assessment-dashboard', 'assessment-form');
        
        if (is_page($protected_pages) && !assessment_360_check_user_session()) {
            wp_redirect(add_query_arg(
                'redirect_to',
                urlencode($_SERVER['REQUEST_URI']),
                assessment_360_get_login_url()
            ));
            exit;
        }
    }

    public function create_pages() {
        $this->create_dashboard_page();
        $this->create_form_page();
        $this->create_login_page();
    }

    private function create_dashboard_page() {
        $dashboard_page = get_page_by_path('assessment-dashboard');
        if (!$dashboard_page) {
            $page_id = wp_insert_post(array(
                'post_title' => 'Assessment Dashboard',
                'post_name' => 'assessment-dashboard',
                'post_content' => '[assessment_360_dashboard]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed'
            ));

            if ($page_id) {
                update_post_meta($page_id, '_wp_page_template', 'page-assessment-dashboard.php');
            }
        }
    }

    private function create_form_page() {
        $form_page = get_page_by_path('assessment-form');
        if (!$form_page) {
            $page_id = wp_insert_post(array(
                'post_title' => 'Assessment Form',
                'post_name' => 'assessment-form',
                'post_content' => '[assessment_360_form]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed'
            ));

            if ($page_id) {
                update_post_meta($page_id, '_wp_page_template', 'page-assessment-form.php');
            }
        }
    }

    private function create_login_page() {
        $login_page = get_page_by_path('360-assessment-login');
        if (!$login_page) {
            $page_id = wp_insert_post(array(
                'post_title' => '360 Assessment Login',
                'post_name' => '360-assessment-login',
                'post_content' => '[assessment_360_login]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed'
            ));

            if ($page_id) {
                update_option('assessment_360_login_page_id', $page_id);
            }
        }
    }

    public function copy_templates() {
        $templates = array(
            'page-assessment-dashboard.php',
            'page-assessment-form.php',
            'page-login.php'
        );

        $theme_dir = get_stylesheet_directory();
        foreach ($templates as $template) {
            $source = ASSESSMENT_360_PLUGIN_DIR . 'templates/' . $template;
            $dest = $theme_dir . '/' . $template;
            
            if (!file_exists($dest) && file_exists($source)) {
                copy($source, $dest);
            }
        }
    }
}
