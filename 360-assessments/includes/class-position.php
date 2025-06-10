<?php
if (!defined('ABSPATH')) exit;

class Assessment_360_Position {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_post_assessment_360_save_position', array($this, 'handle_form_submission'));
        add_action('admin_init', array($this, 'handle_position_actions'));
    }
    
    public function position_name_exists($name, $exclude_id = null) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}360_positions 
             WHERE name = %s AND status = 'active'",
            $name
        );

        if ($exclude_id) {
            $query .= $wpdb->prepare(" AND id != %d", $exclude_id);
        }

        return (int) $wpdb->get_var($query) > 0;
    }

    public function create_position($data) {
        global $wpdb;

        // Check for duplicate name
        if ($this->position_name_exists($data['name'])) {
            return new WP_Error(
                'duplicate_name', 
                sprintf('A position with the name "%s" already exists.', $data['name'])
            );
        }

        $insert_data = array(
            'name' => $data['name'],
            'description' => !empty($data['description']) ? $data['description'] : null,
            'status' => 'active'
        );

        $insert_format = array(
            '%s',  // name
            '%s',  // description
            '%s'   // status
        );

        $result = $wpdb->insert(
            $wpdb->prefix . '360_positions',
            $insert_data,
            $insert_format
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create position: ' . $wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    public function update_position($id, $data) {
        global $wpdb;

        // Check for duplicate name
        if ($this->position_name_exists($data['name'], $id)) {
            return new WP_Error(
                'duplicate_name', 
                sprintf('A position with the name "%s" already exists.', $data['name'])
            );
        }

        $update_data = array(
            'name' => $data['name'],
            'description' => !empty($data['description']) ? $data['description'] : null
        );

        $update_format = array(
            '%s',  // name
            '%s'   // description
        );

        $result = $wpdb->update(
            $wpdb->prefix . '360_positions',
            $update_data,
            array('id' => $id),
            $update_format,
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update position: ' . $wpdb->last_error);
        }

        return true;
    }
    
    public function delete_position($id) {
        global $wpdb;

        // Soft delete - just update status
        $result = $wpdb->update(
            $wpdb->prefix . '360_positions',
            array('status' => 'deleted'),
            array('id' => $id),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to delete position: ' . $wpdb->last_error);
        }

        return true;
    }
    
    public function restore_position($position_id) {
        global $wpdb;

        // Check if position exists
        $position = $this->get_position($position_id);
        if (!$position) {
            return new WP_Error('not_found', 'Position not found.');
        }

        // Check if position is already active
        if ($position->status === 'active') {
            return new WP_Error('already_active', 'Position is already active.');
        }

        // Restore the position
        $result = $wpdb->update(
            $wpdb->prefix . '360_positions',
            ['status' => 'active'],
            ['id' => $position_id],
            ['%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('restore_failed', 'Failed to restore position: ' . $wpdb->last_error);
        }

        return true;
    }
    
    // Check if position is in use
    public function is_position_in_use($position_id) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->prefix}360_users 
             WHERE position_id = %d",
            $position_id
        ));

        return $count > 0;
    }

    // Delete position
    public function delete($position_id) {
        global $wpdb;

        // Check if position exists
        $position = $this->get($position_id);
        if (!$position) {
            return new WP_Error('not_found', 'Position not found.');
        }

        // Check if position is in use
        if ($this->is_position_in_use($position_id)) {
            return new WP_Error('in_use', 'Cannot delete position that is assigned to users.');
        }

        $result = $wpdb->delete(
            $wpdb->prefix . '360_positions',
            ['id' => $position_id],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('delete_failed', 'Failed to delete position.');
        }

        return true;
    }
    
    // Permanently delete a position
    public function permanently_delete_position($position_id) {
        global $wpdb;

        // Check if position exists
        $position = $this->get_position($position_id);
        if (!$position) {
            return new WP_Error('not_found', 'Position not found.');
        }

        // Check if position is in use
        $users_table = $wpdb->prefix . '360_users';
        $users_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$users_table} WHERE position_id = %d",
            $position_id
        ));

        if ($users_count > 0) {
            return new WP_Error(
                'position_in_use', 
                'Cannot delete position that is assigned to users. Please reassign users first.'
            );
        }

        // Delete the position
        $result = $wpdb->delete(
            $wpdb->prefix . '360_positions',
            ['id' => $position_id],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('delete_failed', 'Failed to delete position: ' . $wpdb->last_error);
        }

        return true;
    }
    
    public function get_position($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}360_positions WHERE id = %d",
            $id
        ));
    }

    public function get_all_positions($include_deleted = true) {
        global $wpdb;

        $sql = "SELECT * FROM {$wpdb->prefix}360_positions";

        if (!$include_deleted) {
            $sql .= " WHERE status = 'active'";
        }

        $sql .= " ORDER BY status = 'active' DESC, name ASC";

        return $wpdb->get_results($sql);
    }

    private function position_exists($name, $exclude_id = null) {
        global $wpdb;
        
        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}360_positions WHERE name = %s AND status = 'active'";
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

        check_admin_referer('assessment_360_position_nonce');

        $name = sanitize_text_field($_POST['position_name']);

        if (empty($name)) {
            wp_redirect(add_query_arg('error', 'Position name is required.', wp_get_referer()));
            exit;
        }

        if (isset($_POST['position_id'])) {
            $result = $this->update_position(intval($_POST['position_id']), $name);
            $message = 'Position updated successfully.';
        } else {
            $result = $this->create_position($name);
            $message = 'Position created successfully.';
        }

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('error', $result->get_error_message(), wp_get_referer()));
            exit;
        }

        wp_redirect(add_query_arg('message', $message, admin_url('admin.php?page=assessment-360-user-management')));
        exit;
    }

    public function handle_position_actions() {
        if (!isset($_GET['action']) || !isset($_GET['page']) || $_GET['page'] !== 'assessment-360-user-management') {
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

        check_admin_referer('delete_position_' . $id);

        $result = $this->delete_position($id);

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('error', $result->get_error_message(), wp_get_referer()));
        } else {
            wp_redirect(add_query_arg('message', 'Position deleted successfully.', admin_url('admin.php?page=assessment-360-user-management')));
        }
        exit;
    }

    public function get_position_count() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}360_positions WHERE status = 'active'"
        );
    }
    
//    public function verify_table_structure() {
//        global $wpdb;
//        $table_name = $wpdb->prefix . '360_positions';
//
//        // Check if description column exists
//        $description_exists = $wpdb->get_results($wpdb->prepare(
//            "SHOW COLUMNS FROM {$table_name} LIKE %s",
//            'description'
//        ));
//
//        if (empty($description_exists)) {
//            // Add description column if it doesn't exist
//            $wpdb->query(
//                "ALTER TABLE {$table_name} 
//                 ADD COLUMN description text DEFAULT NULL 
//                 AFTER name"
//            );
//        }
//    }
    
}