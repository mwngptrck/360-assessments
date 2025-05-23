<?php
if (!defined('ABSPATH')) exit;

class Assessment_360_Question {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_post_assessment_360_save_question', array($this, 'handle_form_submission'));
        add_action('admin_init', array($this, 'handle_question_actions'));
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_questions (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            question_text text NOT NULL,
            section_id mediumint(9) NOT NULL,
            position_id mediumint(9) NOT NULL,
            is_mandatory tinyint(1) DEFAULT 0,
            has_comment_box tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY section_id (section_id),
            KEY position_id (position_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function get_question($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT q.*, s.name as section_name, t.name as topic_name, p.name as position_name
             FROM {$wpdb->prefix}360_questions q
             JOIN {$wpdb->prefix}360_sections s ON q.section_id = s.id
             JOIN {$wpdb->prefix}360_topics t ON s.topic_id = t.id
             JOIN {$wpdb->prefix}360_positions p ON q.position_id = p.id
             WHERE q.id = %d",
            $id
        ));
    }

    public function get_all_questions() {
        global $wpdb;

        try {
            $questions = $wpdb->get_results(
                "SELECT q.*, s.name as section_name, t.name as topic_name, p.name as position_name
                 FROM {$wpdb->prefix}360_questions q
                 JOIN {$wpdb->prefix}360_sections s ON q.section_id = s.id
                 JOIN {$wpdb->prefix}360_topics t ON s.topic_id = t.id
                 JOIN {$wpdb->prefix}360_positions p ON q.position_id = p.id
                 WHERE q.status = 'active'
                 ORDER BY t.name, s.name, q.id"
            );

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            return $questions ? $questions : array();

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error getting questions: ' . $e->getMessage());
            }
            return array();
        }
    }
    public function get_questions_by_section($section_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}360_questions 
            WHERE section_id = %d 
            AND status = 'active'
            ORDER BY id",
            $section_id
        ));
    }

    public function get_questions_by_position($position_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT q.*, s.name as section_name, t.name as topic_name
            FROM {$wpdb->prefix}360_questions q
            JOIN {$wpdb->prefix}360_sections s ON q.section_id = s.id
            JOIN {$wpdb->prefix}360_topics t ON s.topic_id = t.id
            WHERE q.position_id = %d
            AND q.status = 'active'
            ORDER BY t.name, s.name, q.id",
            $position_id
        ));
    }
    
    public function get_question_count_by_topic($topic_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(q.id)
            FROM {$wpdb->prefix}360_questions q
            JOIN {$wpdb->prefix}360_sections s ON q.section_id = s.id
            WHERE s.topic_id = %d 
            AND q.status = 'active'
            AND s.status = 'active'",
            $topic_id
        ));
    }
    
    public function get_question_count_by_section($section_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
            FROM {$wpdb->prefix}360_questions 
            WHERE section_id = %d 
            AND status = 'active'",
            $section_id
        ));
    }

    public function create_question($data) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . '360_questions',
            array(
                'question_text' => $data['question_text'],
                'section_id' => $data['section_id'],
                'position_id' => $data['position_id'],
                'is_mandatory' => isset($data['is_mandatory']) ? 1 : 0,
                'has_comment_box' => isset($data['has_comment_box']) ? 1 : 0,
                'status' => 'active'
            ),
            array('%s', '%d', '%d', '%d', '%d', '%s')
        );

        if ($result === false) {
            return new WP_Error('insert_failed', 'Failed to create question');
        }

        return $wpdb->insert_id;
    }

    public function update_question($id, $data) {
        global $wpdb;

        if (WP_DEBUG) {
            error_log('Updating question ID: ' . $id);
            error_log('Update data: ' . print_r($data, true));
        }

        // Explicitly set boolean fields
        $update_data = array(
            'question_text' => $data['question_text'],
            'section_id' => $data['section_id'],
            'position_id' => $data['position_id'],
            'is_mandatory' => (int)!empty($data['is_mandatory']),
            'has_comment_box' => (int)!empty($data['has_comment_box']),
            'updated_at' => current_time('mysql')
        );

        if (WP_DEBUG) {
            error_log('Prepared update data: ' . print_r($update_data, true));
        }

        $result = $wpdb->update(
            $wpdb->prefix . '360_questions',
            $update_data,
            array('id' => $id),
            array(
                '%s', // question_text
                '%d', // section_id
                '%d', // position_id
                '%d', // is_mandatory
                '%d', // has_comment_box
                '%s'  // updated_at
            ),
            array('%d')
        );

        if (WP_DEBUG) {
            error_log('Update result: ' . var_export($result, true));
            if ($result === false) {
                error_log('Database error: ' . $wpdb->last_error);
            }
        }

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update question: ' . $wpdb->last_error);
        }

        // Verify the update
        $updated_question = $this->get_question($id);
        if (WP_DEBUG) {
            error_log('Verified updated question: ' . print_r($updated_question, true));
        }

        return true;
    }

    public function delete_question($id) {
        global $wpdb;
        
        // Check if question has responses
        $response_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}360_assessment_responses WHERE question_id = %d",
            $id
        ));

        if ($response_count > 0) {
            return new WP_Error('delete_failed', 'Cannot delete question that has responses.');
        }

        return $wpdb->delete(
            $wpdb->prefix . '360_questions',
            array('id' => $id),
            array('%d')
        );
    }

    public function get_mandatory_questions($assessment_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT q.* FROM {$wpdb->prefix}360_questions q
            WHERE q.is_mandatory = 1
            AND q.status = 'active'
            AND EXISTS (
                SELECT 1 FROM {$wpdb->prefix}360_assessment_instances ai
                WHERE ai.assessment_id = %d
                AND ai.position_id = q.position_id
            )",
            $assessment_id
        ));
    }

    public function handle_form_submission() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('assessment_360_question_nonce');

        $data = array(
            'question_text' => sanitize_textarea_field($_POST['question_text']),
            'section_id' => intval($_POST['section_id']),
            'position_id' => intval($_POST['position_id']),
            'is_mandatory' => isset($_POST['is_mandatory']),
            'has_comment_box' => isset($_POST['has_comment_box'])
        );

        if (empty($data['question_text'])) {
            wp_redirect(add_query_arg('error', 'Question text is required.', wp_get_referer()));
            exit;
        }

        if (empty($data['section_id'])) {
            wp_redirect(add_query_arg('error', 'Section is required.', wp_get_referer()));
            exit;
        }

        if (empty($data['position_id'])) {
            wp_redirect(add_query_arg('error', 'Position is required.', wp_get_referer()));
            exit;
        }

        if (isset($_POST['question_id'])) {
            $result = $this->update_question(intval($_POST['question_id']), $data);
            $message = 'Question updated successfully.';
        } else {
            $result = $this->create_question($data);
            $message = 'Question created successfully.';
        }

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('error', $result->get_error_message(), wp_get_referer()));
            exit;
        }

        wp_redirect(add_query_arg('message', $message, admin_url('admin.php?page=assessment-360-questions')));
        exit;
    }

    public function handle_question_actions() {
        if (!isset($_GET['action']) || !isset($_GET['page']) || $_GET['page'] !== 'assessment-360-questions') {
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

        check_admin_referer('delete_question_' . $id);

        $result = $this->delete_question($id);

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('error', $result->get_error_message(), wp_get_referer()));
        } else {
            wp_redirect(add_query_arg('message', 'Question deleted successfully.', admin_url('admin.php?page=assessment-360-questions')));
        }
        exit;
    }
    private function log_debug($message, $data = null) {
        if (WP_DEBUG) {
            error_log('Question Manager: ' . $message);
            if ($data !== null) {
                error_log(print_r($data, true));
            }
        }
    }
    public function verify_table_structure() {
        global $wpdb;
        $table_name = $wpdb->prefix . '360_questions';

        // Get table structure
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");

        if (WP_DEBUG) {
            error_log('Table structure for ' . $table_name . ':');
            error_log(print_r($columns, true));
        }

        // Verify has_comment_box column
        $has_comment_box_column = array_filter($columns, function($column) {
            return $column->Field === 'has_comment_box';
        });

        if (empty($has_comment_box_column)) {
            // Add the column if it doesn't exist
            $wpdb->query(
                "ALTER TABLE $table_name 
                 ADD COLUMN has_comment_box tinyint(1) DEFAULT 0 
                 AFTER is_mandatory"
            );

            if (WP_DEBUG) {
                error_log('Added has_comment_box column');
            }
        }

        return true;
    }
    
    
}
