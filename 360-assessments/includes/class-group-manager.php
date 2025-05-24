<?php
class Assessment_360_Group_Manager {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_post_assessment_360_save_group', array($this, 'handle_group_form'));
        add_action('admin_init', array($this, 'handle_group_delete'));
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_user_groups (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            group_name varchar(100) NOT NULL,
            description text,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function verify_table_structure() {
        global $wpdb;
        $table_name = $wpdb->prefix . '360_user_groups';

        // Check if the table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $this->create_tables();
            return;
        }

        // Check column names
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
        $column_names = array_column($columns, 'Field');

        // If 'name' exists but 'group_name' doesn't, rename it
        if (in_array('name', $column_names) && !in_array('group_name', $column_names)) {
            $wpdb->query("ALTER TABLE $table_name CHANGE `name` `group_name` varchar(100) NOT NULL");
        }
    }

    public function ensure_peers_group() {
        global $wpdb;
        
        $peers_group = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}360_user_groups 
            WHERE LOWER(group_name) = 'peers' AND status = 'active'"
        );
        
        if (!$peers_group) {
            $wpdb->insert(
                $wpdb->prefix . '360_user_groups',
                array(
                    'group_name' => 'Peers',
                    'status' => 'active'
                ),
                array('%s', '%s')
            );
        }
    }

    public function create_group($name) {
        global $wpdb;
        
        if ($this->group_exists($name)) {
            return new WP_Error('duplicate_group', 'A group with this name already exists.');
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . '360_user_groups',
            array(
                'group_name' => $name,
                'status' => 'active'
            ),
            array('%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('insert_failed', 'Failed to create group.');
        }

        return $wpdb->insert_id;
    }

    public function update_group($id, $name) {
        global $wpdb;
        
        if ($this->group_exists($name, $id)) {
            return new WP_Error('duplicate_group', 'A group with this name already exists.');
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . '360_user_groups',
            array('group_name' => $name),
            array('id' => $id),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to update group.');
        }

        return true;
    }

    public function delete_group($id) {
        global $wpdb;
        
        // Check if this is the Peers group
        $group = $this->get_group($id);
        if ($group && strtolower($group->group_name) === 'peers') {
            return new WP_Error('delete_failed', 'The Peers group cannot be deleted.');
        }

        // Check if group has users
        $users_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}360_users WHERE group_id = %d AND status != 'deleted'",
            $id
        ));

        if ($users_count > 0) {
            return new WP_Error('delete_failed', 'Cannot delete group that has active users.');
        }

        $result = $wpdb->update(
            $wpdb->prefix . '360_user_groups',
            array(
                'status' => 'deleted',
                'deleted_at' => current_time('mysql')
            ),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('delete_failed', 'Failed to delete group.');
        }

        return true;
    }

    public function get_group($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}360_user_groups WHERE id = %d",
            $id
        ));
    }

    public function get_all_groups() {
        global $wpdb;

        $query = "SELECT * FROM {$wpdb->prefix}360_user_groups 
                  WHERE status = 'active' 
                  ORDER BY group_name";

        $groups = $wpdb->get_results($query);

        return $groups;
    }

    private function group_exists($name, $exclude_id = null) {
        global $wpdb;
        
        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}360_user_groups 
                 WHERE group_name = %s AND status = 'active'";
        $params = array($name);
        
        if ($exclude_id) {
            $query .= " AND id != %d";
            $params[] = $exclude_id;
        }
        
        return (bool) $wpdb->get_var($wpdb->prepare($query, $params));
    }

    public function handle_group_form() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('assessment_360_group_nonce');

        $name = sanitize_text_field($_POST['group_name']);

        if (empty($name)) {
            wp_redirect(add_query_arg('error', urlencode('Group name is required.'), wp_get_referer()));
            exit;
        }

        if (isset($_POST['group_id'])) {
            $result = $this->update_group(intval($_POST['group_id']), $name);
            $message = 'User group updated successfully.';
        } else {
            $result = $this->create_group($name);
            $message = 'User group created successfully.';
        }

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('error', urlencode($result->get_error_message()), wp_get_referer()));
            exit;
        }

        wp_redirect(add_query_arg('message', urlencode($message), admin_url('admin.php?page=assessment-360-user-management&tab=groups')));
        exit;
    }

    public function handle_group_delete() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'delete' || 
            !isset($_GET['page']) || $_GET['page'] !== 'assessment-360-user-management') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $group_id = intval($_GET['id']);

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_group_' . $group_id)) {
            wp_die('Security check failed.');
        }

        $result = $this->delete_group($group_id);

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('error', urlencode($result->get_error_message()), wp_get_referer()));
        } else {
            wp_redirect(add_query_arg('message', urlencode('User group deleted successfully.'), 
                       admin_url('admin.php?page=assessment-360-user-management&tab=groups')));
        }
        exit;
    }
}
