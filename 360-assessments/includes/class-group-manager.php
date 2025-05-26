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
        $charset_collate = $wpdb->get_charset_collate();

        $table_name = $wpdb->prefix . '360_user_groups';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            group_name varchar(100) NOT NULL,
            description text NULL,
            is_department tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY group_name (group_name)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }


    /**
     * Update database to add is_department column
     */
    public function update_database_structure() {
        global $wpdb;

        $table_name = $wpdb->prefix . '360_user_groups';

        // Check if column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME 
             FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND COLUMN_NAME = 'is_department'",
            DB_NAME,
            $table_name
        ));

        // Add column if it doesn't exist
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table_name} 
                         ADD COLUMN is_department tinyint(1) NOT NULL DEFAULT 0 
                         AFTER description");

            // Set Peers group departments to 0 by default
            $wpdb->query("UPDATE {$table_name} SET is_department = 0");
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
    
    public function is_system_group($group_name) {
        return strtolower($group_name) === 'peers';
    }

    public function get_peers_group() {
        global $wpdb;

        $table_name = $wpdb->prefix . '360_user_groups';

        return $wpdb->get_row(
            "SELECT id, group_name, description, is_department 
             FROM {$table_name} 
             WHERE LOWER(group_name) = 'peers' 
             LIMIT 1"
        );
    }

    public function create_group($data) {
        global $wpdb;

        $table_name = $wpdb->prefix . '360_user_groups';

        // Ensure is_department is explicitly set to 0 or 1
        $data['is_department'] = isset($data['is_department']) ? (int)$data['is_department'] : 0;

        $result = $wpdb->insert(
            $table_name,
            [
                'group_name' => $data['group_name'],
                'description' => $data['description'],
                'is_department' => $data['is_department']
            ],
            ['%s', '%s', '%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create group');
        }

        return $wpdb->insert_id;
    }

    public function update_group($id, $data) {
        global $wpdb;

        $table_name = $wpdb->prefix . '360_user_groups';

        // Ensure is_department is explicitly set to 0 or 1
        $data['is_department'] = isset($data['is_department']) ? (int)$data['is_department'] : 0;

        $result = $wpdb->update(
            $table_name,
            [
                'group_name' => $data['group_name'],
                'description' => $data['description'],
                'is_department' => $data['is_department']
            ],
            ['id' => $id],
            ['%s', '%s', '%d'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update group');
        }

        return true;
    }

    /**
     * Delete a group
     * 
     * @param int $group_id
     * @return bool|WP_Error
     */
    public function delete_group($group_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . '360_user_groups';

        // Check if it's a system group
        $group = $this->get_group($group_id);
        if ($group && $this->is_system_group($group->group_name)) {
            return new WP_Error('system_group', 'System groups cannot be deleted.');
        }

        // Check if group has users
        if ($this->group_has_users($group_id)) {
            return new WP_Error('has_users', 'Cannot delete group that has users assigned to it.');
        }

        $result = $wpdb->delete(
            $table_name,
            ['id' => $group_id],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('delete_failed', 'Failed to delete group: ' . $wpdb->last_error);
        }

        return true;
    }
    
    /**
     * Check if group has users assigned
     * 
     * @param int $group_id
     * @return bool
     */
    public function group_has_users($group_id) {
        global $wpdb;

        $users_table = $wpdb->prefix . '360_users';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$users_table} 
             WHERE group_id = %d",
            $group_id
        ));

        return $count > 0;
    }

    public function get_group($id) {
        global $wpdb;

        $table_name = $wpdb->prefix . '360_user_groups';

        $group = $wpdb->get_row($wpdb->prepare(
            "SELECT id, group_name, description, is_department 
             FROM {$table_name} 
             WHERE id = %d",
            $id
        ));

        if (!$group) {
            return null; // Return null instead of error
        }

        return $group;
    }

    public function get_all_groups($exclude_departments = false) {
        global $wpdb;

        $table_name = $wpdb->prefix . '360_user_groups';

        $query = "SELECT id, group_name, description, is_department 
                  FROM {$table_name}";

        if ($exclude_departments) {
            $query .= " WHERE is_department = 0";
        }

        $query .= " ORDER BY 
                    CASE WHEN LOWER(group_name) = 'peers' THEN 0 ELSE 1 END,
                    group_name ASC";

        return $wpdb->get_results($query);
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
    
    /**
     * Get all departments
     * 
     * @return array Array of department groups
     */
    public function get_departments() {
        global $wpdb;

        $table_name = $wpdb->prefix . '360_user_groups';

        return $wpdb->get_results(
            "SELECT id, group_name, description 
             FROM {$table_name} 
             WHERE is_department = 1 
             ORDER BY group_name ASC"
        );
    }

    /**
     * Get users grouped by department
     * 
     * @return array Array of users grouped by department
     */
    public function get_users_by_department() {
        global $wpdb;

        $users_table = $wpdb->prefix . '360_users';
        $groups_table = $wpdb->prefix . '360_user_groups';
        $positions_table = $wpdb->prefix . '360_positions';

        // Updated query to use department_id and get department name from groups table
        $query = "
            SELECT 
                u.*, 
                g.group_name,
                dg.group_name as department_name,
                p.name as position_name
            FROM {$users_table} u
            LEFT JOIN {$groups_table} g ON u.group_id = g.id
            LEFT JOIN {$groups_table} dg ON u.department_id = dg.id
            LEFT JOIN {$positions_table} p ON u.position_id = p.id
            WHERE u.status = 'active'
            ORDER BY COALESCE(dg.group_name, 'No Department'), u.first_name, u.last_name
        ";

        $results = $wpdb->get_results($query);

        // Group users by department
        $grouped = array();
        foreach ($results as $user) {
            $dept = $user->department_name ?: 'No Department';
            if (!isset($grouped[$dept])) {
                $grouped[$dept] = array();
            }
            $grouped[$dept][] = $user;
        }

        return $grouped;
    }
}
