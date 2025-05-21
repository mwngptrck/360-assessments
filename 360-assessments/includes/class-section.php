<?php
if (!defined('ABSPATH')) exit;

class Assessment_360_Section {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_post_assessment_360_save_section', array($this, 'handle_form_submission'));
        add_action('admin_init', array($this, 'handle_section_actions'));
        add_action('wp_ajax_get_sections_by_position', array($this, 'ajax_get_sections_by_position'));
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_sections (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            topic_id mediumint(9) NOT NULL,
            position_id mediumint(9) NOT NULL,
            status varchar(50) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY topic_id (topic_id),
            KEY position_id (position_id),
            FOREIGN KEY (topic_id) REFERENCES {$wpdb->prefix}360_topics(id),
            FOREIGN KEY (position_id) REFERENCES {$wpdb->prefix}360_positions(id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function get_section($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, t.name as topic_name, p.name as position_name 
            FROM {$wpdb->prefix}360_sections s
            JOIN {$wpdb->prefix}360_topics t ON s.topic_id = t.id
            JOIN {$wpdb->prefix}360_positions p ON s.position_id = p.id
            WHERE s.id = %d",
            $id
        ));
    }

    public function get_all_sections() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT s.*, 
                    t.name as topic_name, 
                    p.name as position_name
             FROM {$wpdb->prefix}360_sections s
             JOIN {$wpdb->prefix}360_topics t ON s.topic_id = t.id
             JOIN {$wpdb->prefix}360_positions p ON s.position_id = p.id
             WHERE s.status = 'active'
             ORDER BY p.name, t.name, s.name"
        );
    }

    public function get_sections_by_position($position_id) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT s.*, 
                    t.name as topic_name
             FROM {$wpdb->prefix}360_sections s
             JOIN {$wpdb->prefix}360_topics t ON s.topic_id = t.id
             WHERE s.position_id = %d 
             AND s.status = 'active'
             ORDER BY t.name, s.name",
            $position_id
        );

        if (WP_DEBUG) {
            error_log('Executing position sections query: ' . $query);
        }

        return $wpdb->get_results($query);
    }  
    
    public function verify_tables() {
        global $wpdb;

        $tables = array(
            'sections' => '360_360_sections',
            'topics' => '360_360_topics',
            'positions' => '360_360_positions'
        );

        $results = array();
        foreach ($tables as $name => $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
            $results[$name] = array(
                'table' => $table,
                'exists' => ($exists === $table)
            );

            if ($exists === $table) {
                // Get sample row count
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
                $results[$name]['count'] = $count;
            }
        }

        if (WP_DEBUG) {
            error_log('Table verification results:');
            error_log(print_r($results, true));
        }

        return $results;
    }
    
    public function get_sections_by_topic($topic_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, t.name as topic_name, p.name as position_name
            FROM {$wpdb->prefix}360_sections s
            JOIN {$wpdb->prefix}360_topics t ON s.topic_id = t.id
            JOIN {$wpdb->prefix}360_positions p ON s.position_id = p.id
            WHERE s.topic_id = %d 
            AND s.status = 'active'
            ORDER BY s.name",
            $topic_id
        ));
    }
    
    public function get_sections_with_topics() {
        global $wpdb;

        // Debug log the table prefix
        if (WP_DEBUG) {
            error_log('Using table prefix: ' . $wpdb->prefix); // Should show 360_
        }

        $query = "SELECT s.*, 
                         t.name as topic_name, 
                         p.name as position_name
                  FROM {$wpdb->prefix}360_sections s
                  JOIN {$wpdb->prefix}360_topics t ON s.topic_id = t.id
                  JOIN {$wpdb->prefix}360_positions p ON s.position_id = p.id
                  WHERE s.status = 'active'
                  ORDER BY p.name, t.name, s.name";

        // Debug the actual query
        if (WP_DEBUG) {
            error_log('Executing query: ' . $query);
        }

        $sections = $wpdb->get_results($query);

        if ($wpdb->last_error) {
            error_log('Database error: ' . $wpdb->last_error);
        }

        if (WP_DEBUG) {
            error_log('Found ' . count($sections) . ' sections');
        }

        return $sections;
    }
    public function get_section_positions($section_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT position_id 
             FROM {$wpdb->prefix}360_sections 
             WHERE id = %d",
            $section_id
        ));
    }

    public function create_section($data) {
        global $wpdb;
        $success = true;
        $position_ids = isset($data['position_ids']) ? (array)$data['position_ids'] : [];
        unset($data['position_ids']);

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            foreach ($position_ids as $position_id) {
                $insert_data = array_merge($data, [
                    'position_id' => $position_id,
                    'status' => 'active',
                    'created_at' => current_time('mysql')
                ]);

                $result = $wpdb->insert(
                    $wpdb->prefix . '360_sections',
                    $insert_data,
                    ['%s', '%d', '%d', '%s', '%s']
                );

                if ($result === false) {
                    throw new Exception($wpdb->last_error);
                }
            }

            // Commit transaction
            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            if (WP_DEBUG) {
                error_log('Error creating section: ' . $e->getMessage());
            }
            return new WP_Error('db_error', 'Failed to create section');
        }
    }

    public function update_section($id, $data) {
        global $wpdb;
        $position_ids = isset($data['position_ids']) ? (array)$data['position_ids'] : [];
        unset($data['position_ids']);

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Delete existing section entries
            $wpdb->delete(
                $wpdb->prefix . '360_sections',
                ['id' => $id],
                ['%d']
            );

            // Create new entries for each position
            foreach ($position_ids as $position_id) {
                $insert_data = array_merge($data, [
                    'id' => $id,
                    'position_id' => $position_id,
                    'status' => 'active',
                    'updated_at' => current_time('mysql')
                ]);

                $result = $wpdb->insert(
                    $wpdb->prefix . '360_sections',
                    $insert_data,
                    ['%d', '%s', '%d', '%d', '%s', '%s']
                );

                if ($result === false) {
                    throw new Exception($wpdb->last_error);
                }
            }

            // Commit transaction
            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            if (WP_DEBUG) {
                error_log('Error updating section: ' . $e->getMessage());
            }
            return new WP_Error('db_error', 'Failed to update section');
        }
    }

    public function delete_section($id) {
        global $wpdb;
        
        // Check if section has questions
        $question_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}360_questions WHERE section_id = %d",
            $id
        ));

        if ($question_count > 0) {
            return new WP_Error('delete_failed', 'Cannot delete section that has questions. Delete questions first.');
        }

        return $wpdb->delete(
            $wpdb->prefix . '360_sections',
            array('id' => $id),
            array('%d')
        );
    }

    private function section_exists($name, $position_id, $exclude_id = null) {
        global $wpdb;
        
        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}360_sections 
                 WHERE name = %s AND position_id = %d AND status = 'active'";
        $params = array($name, $position_id);
        
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

        check_admin_referer('assessment_360_section_nonce');

        $data = array(
            'name' => sanitize_text_field($_POST['section_name']),
            'topic_id' => intval($_POST['topic_id']),
            'position_id' => intval($_POST['position_id'])
        );

        if (empty($data['name'])) {
            wp_redirect(add_query_arg('error', 'Section name is required.', wp_get_referer()));
            exit;
        }

        if (empty($data['topic_id'])) {
            wp_redirect(add_query_arg('error', 'Topic is required.', wp_get_referer()));
            exit;
        }

        if (empty($data['position_id'])) {
            wp_redirect(add_query_arg('error', 'Position is required.', wp_get_referer()));
            exit;
        }

        if (isset($_POST['section_id'])) {
            $result = $this->update_section(intval($_POST['section_id']), $data);
            $message = 'Section updated successfully.';
        } else {
            $result = $this->create_section($data);
            $message = 'Section created successfully.';
        }

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('error', $result->get_error_message(), wp_get_referer()));
            exit;
        }

        wp_redirect(add_query_arg('message', $message, admin_url('admin.php?page=assessment-360-sections')));
        exit;
    }

    public function handle_section_actions() {
        if (!isset($_GET['action']) || !isset($_GET['page']) || $_GET['page'] !== 'assessment-360-sections') {
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

        check_admin_referer('delete_section_' . $id);

        $result = $this->delete_section($id);

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('error', $result->get_error_message(), wp_get_referer()));
        } else {
            wp_redirect(add_query_arg('message', 'Section deleted successfully.', admin_url('admin.php?page=assessment-360-sections')));
        }
        exit;
    }

    public function ajax_get_sections_by_position() {
        check_ajax_referer('assessment_360_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $position_id = isset($_POST['position_id']) ? intval($_POST['position_id']) : 0;
        if (!$position_id) {
            wp_send_json_error('Invalid position ID');
        }
        
        $sections = $this->get_sections_by_position($position_id);
        wp_send_json_success($sections);
    }
    // Add this function to your Section Manager class
    public function verify_sections_data() {
        global $wpdb;

        $issues = array();

        // Check for sections without topics
        $orphaned_sections = $wpdb->get_results(
            "SELECT s.* 
             FROM {$wpdb->prefix}360_sections s
             LEFT JOIN {$wpdb->prefix}360_topics t ON s.topic_id = t.id
             WHERE t.id IS NULL"
        );

        if (!empty($orphaned_sections)) {
            $issues[] = 'Found sections without topics: ' . count($orphaned_sections);
        }

        // Check for sections without positions
        $no_position_sections = $wpdb->get_results(
            "SELECT s.* 
             FROM {$wpdb->prefix}360_sections s
             LEFT JOIN {$wpdb->prefix}360_positions p ON s.position_id = p.id
             WHERE p.id IS NULL"
        );

        if (!empty($no_position_sections)) {
            $issues[] = 'Found sections without positions: ' . count($no_position_sections);
        }

        if (WP_DEBUG && !empty($issues)) {
            error_log('Section data issues found: ' . print_r($issues, true));
        }

        return empty($issues);
    }
    
    public function verify_table_names() {
        global $wpdb;

        $tables = array(
            'sections' => $wpdb->prefix . '360_sections',
            'topics' => $wpdb->prefix . '360_topics',
            'positions' => $wpdb->prefix . '360_positions'
        );

        $results = array();
        foreach ($tables as $name => $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
            $results[$name] = array(
                'table_name' => $table,
                'exists' => ($exists === $table)
            );
        }

        if (WP_DEBUG) {
            error_log('Table verification results: ' . print_r($results, true));
        }

        return $results;
    }

}
