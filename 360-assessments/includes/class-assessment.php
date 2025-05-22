<?php
if (!defined('ABSPATH')) exit;

class Assessment_360_Assessment {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        //add_action('admin_post_assessment_360_save_assessment', array($this, 'handle_form_submission'));
        add_action('admin_init', array($this, 'handle_assessment_actions'));
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_assessments (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            start_date date NOT NULL,
            end_date date NOT NULL,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function get_assessment($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}360_assessments WHERE id = %d",
            $id
        ));
    }

    public function get_active_assessments() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}360_assessments 
            WHERE status = 'active' 
            AND end_date >= CURDATE() 
            ORDER BY start_date ASC"
        );
    }

    public function get_all_assessments($include_completed = false) {
        global $wpdb;
        $sql = "SELECT * FROM {$wpdb->prefix}360_assessments";
        
        if (!$include_completed) {
            $sql .= " WHERE end_date >= CURDATE()";
        }
        
        $sql .= " ORDER BY start_date DESC";
        
        return $wpdb->get_results($sql);
    }

    public function create_assessment($data) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . '360_assessments',
            array(
                'name' => $data['name'],
                'description' => $data['description'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => 'active'
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('insert_failed', 'Failed to create assessment');
        }

        return $wpdb->insert_id;
    }

    public function update_assessment($id, $data) {
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . '360_assessments',
            array(
                'name' => $data['name'],
                'description' => $data['description'],
                'end_date' => $data['end_date']
            ),
            array('id' => $id),
            array('%s', '%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to update assessment');
        }

        return true;
    }

    public function enable_assessment($id) {
        global $wpdb;
        return $wpdb->update(
            $wpdb->prefix . '360_assessments',
            array('status' => 'active'),
            array('id' => $id)
        );
    }

    public function disable_assessment($id) {
        global $wpdb;
        return $wpdb->update(
            $wpdb->prefix . '360_assessments',
            array('status' => 'disabled'),
            array('id' => $id)
        );
    }

    public function get_assessment_completion_rate($id) {
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(*) as total
            FROM {$wpdb->prefix}360_assessment_instances
            WHERE assessment_id = %d",
            $id
        ));

        if (!$stats->total) {
            return 0;
        }

        return round(($stats->completed / $stats->total) * 100);
    }

    public function handle_form_submission() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('assessment_360_assessment_nonce');

        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'start_date' => sanitize_text_field($_POST['start_date']),
            'end_date' => sanitize_text_field($_POST['end_date'])
        );

        if (empty($data['name'])) {
            wp_redirect(add_query_arg('error', 'Assessment name is required.', wp_get_referer()));
            exit;
        }

        if (isset($_POST['assessment_id'])) {
            $result = $this->update_assessment(intval($_POST['assessment_id']), $data);
            $message = 'Assessment updated successfully.';
        } else {
            $result = $this->create_assessment($data);
            $message = 'Assessment created successfully.';
        }

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('error', $result->get_error_message(), wp_get_referer()));
            exit;
        }

        wp_redirect(add_query_arg('message', $message, admin_url('admin.php?page=assessment-360-assessments')));
        exit;
    }

    public function handle_assessment_actions() {
        if (!isset($_GET['action']) || !isset($_GET['page']) || $_GET['page'] !== 'assessment-360-assessments') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $action = $_GET['action'];
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if (!$id) {
            return;
        }

        switch ($action) {
            case 'enable':
                check_admin_referer('enable_assessment_' . $id);
                $this->enable_assessment($id);
                $message = 'Assessment enabled successfully.';
                break;

            case 'disable':
                check_admin_referer('disable_assessment_' . $id);
                $this->disable_assessment($id);
                $message = 'Assessment disabled successfully.';
                break;

            default:
                return;
        }

        wp_redirect(add_query_arg('message', $message, admin_url('admin.php?page=assessment-360-assessments')));
        exit;
    }
}
