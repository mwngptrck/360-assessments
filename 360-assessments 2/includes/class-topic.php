<?php
if (!defined('ABSPATH')) exit;

class Assessment_360_Topic {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_post_assessment_360_save_topic', array($this, 'handle_form_submission'));
        add_action('admin_init', array($this, 'handle_topic_actions'));
    }

    public function get_topic($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}360_topics WHERE id = %d",
            $id
        ));
    }

    public function get_all_topics() {
        global $wpdb;

        try {
            $topics = $wpdb->get_results(
                "SELECT t.*, 
                        (SELECT COUNT(*) FROM {$wpdb->prefix}360_sections WHERE topic_id = t.id) as section_count
                 FROM {$wpdb->prefix}360_topics t 
                 WHERE t.status = 'active' 
                 ORDER BY t.name ASC"
            );

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            return $topics ? $topics : array();

        } catch (Exception $e) {
            return array();
        }
    }

    public function create_topic($data) {
        global $wpdb;
        
        if ($this->topic_exists($data['name'])) {
            return new WP_Error('duplicate_topic', 'A topic with this name already exists.');
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . '360_topics',
            array(
                'name' => $data['name'],
                'status' => 'active'
            ),
            array('%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('insert_failed', 'Failed to create topic');
        }

        return $wpdb->insert_id;
    }

    public function update_topic($id, $data) {
        global $wpdb;
        
        if ($this->topic_exists($data['name'], $id)) {
            return new WP_Error('duplicate_topic', 'A topic with this name already exists.');
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . '360_topics',
            array('name' => $data['name']),
            array('id' => $id),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to update topic');
        }

        return true;
    }

    public function delete_topic($id) {
        global $wpdb;
        
        // Check if topic has sections
        $section_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}360_sections WHERE topic_id = %d",
            $id
        ));

        if ($section_count > 0) {
            return new WP_Error('delete_failed', 'Cannot delete topic that has sections. Delete sections first.');
        }

        return $wpdb->delete(
            $wpdb->prefix . '360_topics',
            array('id' => $id),
            array('%d')
        );
    }

    private function topic_exists($name, $exclude_id = null) {
        global $wpdb;
        
        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}360_topics WHERE name = %s AND status = 'active'";
        $params = array($name);
        
        if ($exclude_id) {
            $query .= " AND id != %d";
            $params[] = $exclude_id;
        }
        
        return (bool) $wpdb->get_var($wpdb->prepare($query, $params));
    }

    public function handle_form_submission() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('assessment_360_topic_nonce');

        $data = array(
            'name' => sanitize_text_field($_POST['topic_name'])
        );

        if (empty($data['name'])) {
            wp_redirect(add_query_arg('error', 'Topic name is required.', wp_get_referer()));
            exit;
        }

        if (isset($_POST['topic_id'])) {
            $result = $this->update_topic(intval($_POST['topic_id']), $data);
            $message = 'Topic updated successfully.';
        } else {
            $result = $this->create_topic($data);
            $message = 'Topic created successfully.';
        }

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('error', $result->get_error_message(), wp_get_referer()));
            exit;
        }

        wp_redirect(add_query_arg('message', $message, admin_url('admin.php?page=assessment-360-topics')));
        exit;
    }

    public function handle_topic_actions() {
        if (!isset($_GET['action']) || !isset($_GET['page']) || $_GET['page'] !== 'assessment-360-topics') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $action = $_GET['action'];
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if (!$id || $action !== 'delete') {
            return;
        }

        check_admin_referer('delete_topic_' . $id);

        $result = $this->delete_topic($id);

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('error', $result->get_error_message(), wp_get_referer()));
        } else {
            wp_redirect(add_query_arg('message', 'Topic deleted successfully.', admin_url('admin.php?page=assessment-360-topics')));
        }
        exit;
    }

    public function get_topics_by_position($position_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT t.* 
            FROM {$wpdb->prefix}360_topics t
            JOIN {$wpdb->prefix}360_sections s ON t.id = s.topic_id
            WHERE s.position_id = %d
            AND t.status = 'active'
            ORDER BY t.name",
            $position_id
        ));
    }
    
    public function topic_has_questions($topic_id) {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}360_questions WHERE topic_id = %d",
            $topic_id
        ));
        return $count > 0;
    }
}
