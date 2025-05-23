<?php
if (!defined('ABSPATH')) exit;

class Assessment_360_User_Manager {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        
        if (WP_DEBUG) {
            $this->check_database_tables();
        }
        // Form submission handler
        add_action('admin_post_assessment_360_save_user', array($this, 'handle_form_submission'));
        
        // User actions handler
        add_action('admin_init', array($this, 'handle_user_actions'));
        
        // AJAX handlers if needed
        add_action('wp_ajax_get_user_details', array($this, 'ajax_get_user_details'));
    }

    public function handle_form_submission() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('assessment_360_user_nonce');

        $data = array(
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'email' => sanitize_email($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'position_id' => isset($_POST['position_id']) ? intval($_POST['position_id']) : null,
            'group_id' => isset($_POST['group_id']) ? intval($_POST['group_id']) : null,
            'assessors' => isset($_POST['assessors']) ? array_map('intval', $_POST['assessors']) : array()
        );

        // Validate required fields
        if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email'])) {
            wp_redirect(add_query_arg('error', urlencode('Required fields are missing.'), wp_get_referer()));
            exit;
        }

        // Handle password for new users
        if (!isset($_POST['user_id'])) {
            $data['password'] = wp_generate_password(12, true);
        }

        try {
            if (isset($_POST['user_id'])) {
                $result = $this->update_user(intval($_POST['user_id']), $data);
                //$message = 'User updated successfully.';
            } else {
                $result = $this->create_user($data);
                //$message = 'User created successfully.';
            }

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            wp_redirect(add_query_arg('message', urlencode($message), admin_url('admin.php?page=assessment-360-user-management&tab=users')));
            exit;

        } catch (Exception $e) {
            wp_redirect(add_query_arg('error', urlencode($e->getMessage()), wp_get_referer()));
            exit;
        }
    }

    public function handle_user_actions() {
        static $already_run = false;
        if ($already_run) return;
        if (!isset($_GET['page']) || $_GET['page'] !== 'assessment-360-user-management') return;

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        $user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $current_status = isset($_GET['status']) ? $_GET['status'] : 'active';

        // Only run for actions that need handling here
        if (in_array($action, ['disable_user', 'enable_user', 'delete_user']) && $user_id) {
            try {
                $user_manager = Assessment_360_User_Manager::get_instance();
                $redirect_args = ['page' => 'assessment-360-user-management', 'status' => $current_status];
                switch ($action) {
                    case 'disable_user':
                    case 'enable_user':
                        if (!wp_verify_nonce($_GET['_wpnonce'], 'user_status_' . $user_id)) {
                            throw new Exception('Invalid security token');
                        }
                        $new_status = ($action === 'disable_user') ? 'inactive' : 'active';
                        $result = $user_manager->update_user_status($user_id, $new_status);
                        if (is_wp_error($result)) throw new Exception($result->get_error_message());
                        $redirect_args['message'] = 'User ' . ($new_status === 'active' ? 'enabled' : 'disabled') . ' successfully.';
                        break;
                    case 'delete_user':
                        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_user_' . $user_id)) {
                            throw new Exception('Invalid security token');
                        }
                        $result = $user_manager->delete_user($user_id);
                        if (is_wp_error($result)) throw new Exception($result->get_error_message());
                        $redirect_args['message'] = 'User deleted successfully.';
                        break;
                }
                $already_run = true;
                wp_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
                exit;
            } catch (Exception $e) {
                $already_run = true;
                wp_redirect(add_query_arg([
                    'page' => 'assessment-360-user-management',
                    'status' => $current_status,
                    'error' => urlencode($e->getMessage())
                ], admin_url('admin.php')));
                exit;
            }
        }
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $tables = array(
            // Users table
            
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_users (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                first_name varchar(50) NOT NULL,
                last_name varchar(50) NOT NULL,
                email varchar(100) NOT NULL,
                password varchar(255) NOT NULL,
                phone varchar(20) NULL,
                position_id bigint(20) NULL,
                group_id bigint(20) NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'active',
                reset_token varchar(255) NULL,
                reset_token_expiry datetime NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY email (email),
                KEY position_id (position_id),
                KEY group_id (group_id)
            ) $charset_collate",

            // Groups table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_user_groups (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                group_name varchar(100) NOT NULL,
                description text,
                status varchar(20) DEFAULT 'active',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $charset_collate",

            // Positions table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_positions (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                name varchar(100) NOT NULL,
                description text,
                status varchar(20) DEFAULT 'active',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $charset_collate",
            
            // User Relationships Table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_user_relationships (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                assessor_id mediumint(9) NOT NULL,
                assessee_id mediumint(9) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY assessor_id (assessor_id),
                KEY assessee_id (assessee_id),
                FOREIGN KEY (assessor_id) REFERENCES {$wpdb->prefix}360_users(id) ON DELETE CASCADE,
                FOREIGN KEY (assessee_id) REFERENCES {$wpdb->prefix}360_users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_relationship (assessor_id, assessee_id)
            ) $charset_collate",

            // Password Resets Table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_password_resets (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                user_id mediumint(9) NOT NULL,
                token varchar(255) NOT NULL,
                expiry datetime NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}360_users(id) ON DELETE CASCADE
            ) $charset_collate",

            // User Assessors table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_user_assessors (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                user_id mediumint(9) NOT NULL,
                assessor_id mediumint(9) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY user_assessor (user_id, assessor_id),
                KEY user_id (user_id),
                KEY assessor_id (assessor_id)
            ) $charset_collate"
        );
        
        $this->create_assessors_table();
        
        if (WP_DEBUG) {
            error_log('All tables created successfully');
        }

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        try {
            foreach ($tables as $sql) {
                if (WP_DEBUG) {
                    error_log('Executing SQL: ' . $sql);
                }

                $result = dbDelta($sql);

                if (WP_DEBUG) {
                    error_log('dbDelta result: ' . print_r($result, true));
                }
            }

            return true;
        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error creating tables: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
            }
            return false;
        }
    }
    
    /**
     * Create user assessors table
     */
    private function create_assessors_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_user_assessors (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            assessor_id mediumint(9) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_assessor (user_id, assessor_id),
            KEY user_id (user_id),
            KEY assessor_id (assessor_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        if ($wpdb->last_error) {
            if (WP_DEBUG) {
                error_log('Error creating assessors table: ' . $wpdb->last_error);
            }
            throw new Exception('Failed to create assessors table');
        }
    }

    /**
     * Verify database tables and their structure
     */
    public function verify_tables() {
        global $wpdb;

        if (WP_DEBUG) {
            error_log('Verifying database tables structure');
        }

        try {
            // Verify assessment_responses table
            $responses_table = $wpdb->prefix . '360_assessment_responses';
            if ($wpdb->get_var("SHOW TABLES LIKE '$responses_table'") != $responses_table) {
                $charset_collate = $wpdb->get_charset_collate();

                $sql = "CREATE TABLE $responses_table (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    assessment_instance_id mediumint(9) NOT NULL,
                    question_id mediumint(9) NOT NULL,
                    rating int(1) NOT NULL,
                    comment text,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY assessment_instance_id (assessment_instance_id),
                    KEY question_id (question_id)
                ) $charset_collate;";
                
                $sql = "CREATE TABLE $relationships_table (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    assessor_id mediumint(9) NOT NULL,
                    assessee_id mediumint(9) NOT NULL,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    UNIQUE KEY unique_relationship (assessor_id, assessee_id),
                    KEY assessor_id (assessor_id),
                    KEY assessee_id (assessee_id)
                ) $charset_collate;";

                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);

                if ($wpdb->last_error) {
                    throw new Exception('Failed to create assessment_responses table: ' . $wpdb->last_error);
                }
            }

            // Verify assessment_instances table
            $instances_table = $wpdb->prefix . '360_assessment_instances';
            if ($wpdb->get_var("SHOW TABLES LIKE '$instances_table'") != $instances_table) {
                $sql = "CREATE TABLE $instances_table (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    assessment_id mediumint(9) NOT NULL,
                    assessor_id mediumint(9) NOT NULL,
                    assessee_id mediumint(9) NOT NULL,
                    status varchar(50) DEFAULT 'pending',
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    completed_at datetime DEFAULT NULL,
                    PRIMARY KEY  (id),
                    KEY assessment_id (assessment_id),
                    KEY assessor_id (assessor_id),
                    KEY assessee_id (assessee_id)
                ) $charset_collate;";

                dbDelta($sql);

                if ($wpdb->last_error) {
                    throw new Exception('Failed to create assessment_instances table: ' . $wpdb->last_error);
                }
            }

            if (WP_DEBUG) {
                error_log('Database tables verified successfully');
            }

            return true;

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error verifying tables: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
            }
            return false;
        }
    }

    public function check_and_create_tables() {
        if (!$this->verify_tables()) {
            return $this->create_tables();
        }
        return true;
    }

    public function create_user($data) {
        global $wpdb;
        
        if ($this->email_exists($data['email'])) {
            return new WP_Error('duplicate_email', 'A user with this email already exists.');
        }
        
        $wpdb->query('START TRANSACTION');
        
        try {
            $result = $wpdb->insert(
                $wpdb->prefix . '360_users',
                array(
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'position_id' => $data['position_id'],
                    'group_id' => $data['group_id'],
                    'password' => wp_hash_password($data['password']),
                    'status' => 'active'
                ),
                array('%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s')
            );

            if ($result === false) {
                throw new Exception('Failed to create user: ' . $wpdb->last_error);
            }

            $user_id = $wpdb->insert_id;

            // Create assessor relationships
            if (!empty($data['assessors'])) {
                foreach ($data['assessors'] as $assessor_id) {
                    if ($assessor_id != $user_id) {
                        $wpdb->insert(
                            $wpdb->prefix . '360_user_relationships',
                            array(
                                'assessor_id' => $assessor_id,
                                'assessee_id' => $user_id
                            ),
                            array('%d', '%d')
                        );
                    }
                }
            }

            $wpdb->query('COMMIT');

            // Send welcome email
            $this->send_welcome_email($data['email'], $data['password'], $data['first_name']);

            return $user_id;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('insert_failed', $e->getMessage());
        }
    }

    public function update_user($id, $data) {
        global $wpdb;
        
        if ($this->email_exists($data['email'], $id)) {
            return new WP_Error('duplicate_email', 'A user with this email already exists.');
        }
        
        $wpdb->query('START TRANSACTION');
        
        try {
            $update_data = array(
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'position_id' => $data['position_id'],
                'group_id' => $data['group_id']
            );

            if (!empty($data['password'])) {
                $update_data['password'] = wp_hash_password($data['password']);
            }

            $result = $wpdb->update(
                $wpdb->prefix . '360_users',
                $update_data,
                array('id' => $id)
            );

            if ($result === false) {
                throw new Exception('Failed to update user: ' . $wpdb->last_error);
            }

            // Update assessor relationships
            $wpdb->delete(
                $wpdb->prefix . '360_user_relationships',
                array('assessee_id' => $id)
            );

            if (!empty($data['assessors'])) {
                foreach ($data['assessors'] as $assessor_id) {
                    if ($assessor_id != $id) {
                        $wpdb->insert(
                            $wpdb->prefix . '360_user_relationships',
                            array(
                                'assessor_id' => $assessor_id,
                                'assessee_id' => $id
                            ),
                            array('%d', '%d')
                        );
                    }
                }
            }

            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('update_failed', $e->getMessage());
        }
    }
    
    public function reset_user_password($user_id) {
        // Generate a random password
        $new_password = wp_generate_password(12, true, true);

        // Hash the password
        $hashed_password = wp_hash_password($new_password);

        // Update the password in the database
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . '360_users',
            array(
                'password' => $hashed_password,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $user_id),
            array('%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('password_update_failed', 'Failed to update password');
        }

        return $new_password;
    }

    public function get_users_count($status = 'active') {
        global $wpdb;

        try {
            $query = $wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$wpdb->prefix}360_users 
                 WHERE status = %s",
                $status
            );

            if (WP_DEBUG) {
                error_log('Users count query: ' . $query);
            }

            $count = $wpdb->get_var($query);

            if ($wpdb->last_error) {
                if (WP_DEBUG) {
                    error_log('Database error in get_users_count: ' . $wpdb->last_error);
                }
                return 0;
            }

            return (int)$count;

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error in get_users_count: ' . $e->getMessage());
            }
            return 0;
        }
    }
    
    public function get_users_by_status($status = 'active') {
        global $wpdb;

        try {
            $query = $wpdb->prepare(
                "SELECT u.*, 
                        p.name as position_name, 
                        g.group_name as group_name
                 FROM {$wpdb->prefix}360_users u
                 LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id
                 LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
                 WHERE u.status = %s
                 ORDER BY u.first_name, u.last_name",
                $status
            );

            if (WP_DEBUG) {
                error_log('Users query: ' . $query);
            }

            $users = $wpdb->get_results($query);

            if ($wpdb->last_error) {
                if (WP_DEBUG) {
                    error_log('Database error in get_users_by_status: ' . $wpdb->last_error);
                }
                return array();
            }

            return $users;

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error in get_users_by_status: ' . $e->getMessage());
            }
            return array();
        }
    }
    
    public function update_user_status($user_id, $status) {
        global $wpdb;

        if (WP_DEBUG) {
            error_log("Updating user status: User ID = $user_id, Status = $status");
        }

        if (!in_array($status, ['active', 'inactive'])) {
            if (WP_DEBUG) {
                error_log("Invalid status provided: $status");
            }
            return new WP_Error('invalid_status', 'Invalid status provided');
        }

        $result = $wpdb->update(
            $wpdb->prefix . '360_users',
            ['status' => $status],
            ['id' => $user_id],
            ['%s'],
            ['%d']
        );

        if ($result === false) {
            if (WP_DEBUG) {
                error_log('Database error updating user status: ' . $wpdb->last_error);
                error_log('Query: ' . $wpdb->last_query);
            }
            return new WP_Error('db_error', 'Failed to update user status: ' . $wpdb->last_error);
        }

        if (WP_DEBUG) {
            error_log("User status updated successfully");
        }

        return true;
    }

    public function delete_user($user_id) {
        global $wpdb;

        if (WP_DEBUG) {
            error_log("Attempting to delete user ID: $user_id");
        }

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // First, delete from assessment_responses table
            $responses_deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}360_assessment_responses 
                 WHERE assessment_instance_id IN (
                    SELECT id 
                    FROM {$wpdb->prefix}360_assessment_instances 
                    WHERE assessor_id = %d OR assessee_id = %d
                 )",
                $user_id, $user_id
            ));

            if ($wpdb->last_error) {
                throw new Exception('Failed to delete assessment responses: ' . $wpdb->last_error);
            }

            if (WP_DEBUG) {
                error_log("Deleted $responses_deleted response records");
            }

            // Delete from assessment_instances table
            $instances_deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}360_assessment_instances 
                 WHERE assessor_id = %d OR assessee_id = %d",
                $user_id, $user_id
            ));

            if ($wpdb->last_error) {
                throw new Exception('Failed to delete assessment instances: ' . $wpdb->last_error);
            }

            if (WP_DEBUG) {
                error_log("Deleted $instances_deleted assessment instance records");
            }

            // Delete from user_relationships table
            $relationships_deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}360_user_relationships 
                 WHERE assessor_id = %d OR assessee_id = %d",
                $user_id, $user_id
            ));

            if ($wpdb->last_error) {
                throw new Exception('Failed to delete user relationships: ' . $wpdb->last_error);
            }

            if (WP_DEBUG) {
                error_log("Deleted $relationships_deleted relationship records");
            }

            // Delete from user_assessors table
            $assessors_deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}360_user_assessors 
                 WHERE user_id = %d OR assessor_id = %d",
                $user_id, $user_id
            ));

            if ($wpdb->last_error) {
                throw new Exception('Failed to delete user assessor records: ' . $wpdb->last_error);
            }

            if (WP_DEBUG) {
                error_log("Deleted $assessors_deleted assessor records");
            }

            // Finally, delete the user
            $result = $wpdb->delete(
                $wpdb->prefix . '360_users',
                ['id' => $user_id],
                ['%d']
            );

            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }

            if (WP_DEBUG) {
                error_log("User deleted successfully");
            }

            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');

            if (WP_DEBUG) {
                error_log('Error in delete_user: ' . $e->getMessage());
                error_log('Last SQL query: ' . $wpdb->last_query);
                error_log('Database error: ' . $wpdb->last_error);
                error_log('Stack trace: ' . $e->getTraceAsString());
            }

            return new WP_Error('delete_failed', 'Failed to delete user: ' . $e->getMessage());
        }
    }
    
    public function check_table_structures() {
        global $wpdb;

        $tables = array(
            '360_users',
            '360_assessment_responses',
            '360_assessment_instances',
            '360_user_relationships',
            '360_user_assessors'
        );

        foreach ($tables as $table) {
            $full_table_name = $wpdb->prefix . $table;

            if ($wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name) {
                $columns = $wpdb->get_results("SHOW COLUMNS FROM $full_table_name");
                if (WP_DEBUG) {
                    error_log("Table $table columns:");
                    error_log(print_r($columns, true));
                }
            } else {
                if (WP_DEBUG) {
                    error_log("Table $table does not exist");
                }
            }
        }
    }
    
    // Add this method to verify table columns
    public function verify_assessment_tables() {
        global $wpdb;

        try {
            // Verify assessment_instances table
            $instances_table = $wpdb->prefix . '360_assessment_instances';
            $instances_exists = $wpdb->get_var("SHOW TABLES LIKE '$instances_table'") === $instances_table;

            if (!$instances_exists) {
                if (WP_DEBUG) {
                    error_log('Creating assessment_instances table...');
                }

                $charset_collate = $wpdb->get_charset_collate();

                $sql = "CREATE TABLE IF NOT EXISTS $instances_table (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    assessment_id mediumint(9) NOT NULL,
                    assessee_id mediumint(9) NOT NULL,
                    assessor_id mediumint(9) NOT NULL,
                    status varchar(50) DEFAULT 'pending',
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY assessment_id (assessment_id),
                    KEY assessee_id (assessee_id),
                    KEY assessor_id (assessor_id)
                ) $charset_collate;";

                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);

                if ($wpdb->last_error) {
                    throw new Exception('Failed to create assessment_instances table: ' . $wpdb->last_error);
                }
            }

            // Verify assessment_responses table
            $responses_table = $wpdb->prefix . '360_assessment_responses';
            $responses_exists = $wpdb->get_var("SHOW TABLES LIKE '$responses_table'") === $responses_table;

            if (!$responses_exists) {
                if (WP_DEBUG) {
                    error_log('Creating assessment_responses table...');
                }

                $sql = "CREATE TABLE IF NOT EXISTS $responses_table (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    instance_id mediumint(9) NOT NULL,
                    question_id mediumint(9) NOT NULL,
                    assessee_id mediumint(9) NOT NULL,
                    assessor_id mediumint(9) NOT NULL,
                    response text NOT NULL,
                    comments text,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY instance_id (instance_id),
                    KEY question_id (question_id),
                    KEY assessee_id (assessee_id),
                    KEY assessor_id (assessor_id)
                ) $charset_collate;";

                dbDelta($sql);

                if ($wpdb->last_error) {
                    throw new Exception('Failed to create assessment_responses table: ' . $wpdb->last_error);
                }
            }

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error verifying assessment tables: ' . $e->getMessage());
            }
            return false;
        }

        return true;
    }
    
    public function verify_table_structure() {
        global $wpdb;

        try {
            // Check if users table exists
            $table_name = $wpdb->prefix . '360_users';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

            if (!$table_exists) {
                if (WP_DEBUG) {
                    error_log('Users table does not exist. Creating...');
                }
                $this->create_tables();
                return;
            }

            // Check foreign key constraints
            $constraints = $wpdb->get_results("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = '{$wpdb->prefix}360_user_relationships'
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            ");

            // Remove existing foreign key constraints
            foreach ($constraints as $constraint) {
                $wpdb->query("
                    ALTER TABLE {$wpdb->prefix}360_user_relationships 
                    DROP FOREIGN KEY {$constraint->CONSTRAINT_NAME}
                ");
            }

            // Add ON DELETE CASCADE constraints
            $wpdb->query("
                ALTER TABLE {$wpdb->prefix}360_user_relationships
                ADD CONSTRAINT fk_assessor 
                FOREIGN KEY (assessor_id) 
                REFERENCES {$wpdb->prefix}360_users(id) 
                ON DELETE CASCADE
            ");

            $wpdb->query("
                ALTER TABLE {$wpdb->prefix}360_user_relationships
                ADD CONSTRAINT fk_assessee 
                FOREIGN KEY (assessee_id) 
                REFERENCES {$wpdb->prefix}360_users(id) 
                ON DELETE CASCADE
            ");

            if (WP_DEBUG) {
                error_log('Foreign key constraints updated successfully');
            }

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error verifying table structure: ' . $e->getMessage());
            }
        }
    }
    
    private function add_column_if_not_exists($column_name, $column_definition) {
        global $wpdb;

        $table_name = $this->table_name;
        $check_column = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND COLUMN_NAME = %s",
            DB_NAME,
            $table_name,
            $column_name
        ));

        if (empty($check_column)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN $column_name $column_definition");
        }
    }
    
    private function add_missing_column($table_name, $column) {
        global $wpdb;

        $sql = '';
        switch ($column) {
            case 'status':
                $sql = "ALTER TABLE $table_name ADD COLUMN status varchar(50) DEFAULT 'active'";
                break;
            case 'created_at':
                $sql = "ALTER TABLE $table_name ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP";
                break;
            case 'last_login':
                $sql = "ALTER TABLE $table_name ADD COLUMN last_login datetime DEFAULT NULL";
                break;
            // Add other columns as needed
        }

        if ($sql) {
            if (WP_DEBUG) {
                error_log('Adding column SQL: ' . $sql);
            }
            $wpdb->query($sql);
        }
    }

    /**
     * Update user's assessors
     */
    public function update_user_assessors($user_id, $assessor_ids) {
        global $wpdb;

        if (WP_DEBUG) {
            error_log("Updating assessors for user $user_id");
            error_log("New assessor IDs: " . print_r($assessor_ids, true));
        }

        try {
            // Start transaction
            $wpdb->query('START TRANSACTION');

            // Delete existing assessor relationships
            $deleted = $wpdb->delete(
                $wpdb->prefix . '360_user_assessors',
                ['user_id' => $user_id],
                ['%d']
            );

            if ($wpdb->last_error) {
                throw new Exception('Failed to delete existing assessors: ' . $wpdb->last_error);
            }

            if (WP_DEBUG) {
                error_log("Deleted $deleted existing assessor relationships");
            }

            // Insert new assessor relationships
            foreach ($assessor_ids as $assessor_id) {
                // Skip if trying to assign user as their own assessor
                if ($assessor_id == $user_id) {
                    if (WP_DEBUG) {
                        error_log("Skipping self-assignment for user $user_id");
                    }
                    continue;
                }

                $result = $wpdb->insert(
                    $wpdb->prefix . '360_user_assessors',
                    [
                        'user_id' => $user_id,
                        'assessor_id' => $assessor_id,
                        'created_at' => current_time('mysql')
                    ],
                    ['%d', '%d', '%s']
                );

                if ($wpdb->last_error) {
                    throw new Exception('Failed to insert assessor relationship: ' . $wpdb->last_error);
                }
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            if (WP_DEBUG) {
                error_log("Successfully updated assessors for user $user_id");
            }

            return true;

        } catch (Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');

            if (WP_DEBUG) {
                error_log('Error updating user assessors: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
            }

            return new WP_Error('assessor_update_failed', $e->getMessage());
        }
    }
    
    public function get_user_by_email($email) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT u.*, p.name as position_name, g.group_name 
            FROM {$wpdb->prefix}360_users u 
            LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id 
            LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id 
            WHERE u.email = %s",
            $email
        ));
    }

    public function get_all_users() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT u.*, 
                    p.name as position_name, 
                    g.group_name,
                    CONCAT(u.first_name, ' ', u.last_name) as full_name
            FROM {$wpdb->prefix}360_users u 
            LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id 
            LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id 
            WHERE u.status = 'active'
            ORDER BY u.first_name, u.last_name"
        );
    }
    
    public function get_total_users_count() {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}360_users WHERE status = 'active'"
        );
    }

    /**
     * Get users that this assessor is assigned to assess
     * 
     * @param int $assessor_id The ID of the assessor
     * @return array Array of user objects
     */
    public function get_user_assessees($assessor_id) {
        global $wpdb;

        if (WP_DEBUG) {
            error_log("Fetching assessees for assessor ID: " . $assessor_id);
        }

        // Get correct table names
        $users_table = $wpdb->prefix . '360_users';
        $groups_table = $wpdb->prefix . '360_user_groups';
        $positions_table = $wpdb->prefix . '360_positions';
        $relations_table = $wpdb->prefix . '360_user_relationships'; // Changed from user_assessor_relations

        if (WP_DEBUG) {
            error_log("Table names:");
            error_log("Users table: " . $users_table);
            error_log("Groups table: " . $groups_table);
            error_log("Positions table: " . $positions_table);
            error_log("Relations table: " . $relations_table);
        }

        $query = $wpdb->prepare(
            "SELECT DISTINCT u.*, 
                    g.group_name, 
                    p.name as position_name
             FROM {$users_table} u
             LEFT JOIN {$groups_table} g ON u.group_id = g.id
             LEFT JOIN {$positions_table} p ON u.position_id = p.id
             INNER JOIN {$relations_table} ar ON u.id = ar.assessee_id
             WHERE ar.assessor_id = %d
             AND u.status = 'active'
             ORDER BY u.first_name, u.last_name",
            $assessor_id
        );

        if (WP_DEBUG) {
            error_log("Executing query: " . $query);
        }

        $results = $wpdb->get_results($query);

        if (WP_DEBUG) {
            error_log("Found " . count($results) . " assessees");
            if ($wpdb->last_error) {
                error_log("Database error: " . $wpdb->last_error);
            }
            error_log("Results: " . print_r($results, true));
        }

        return $results;
    }

    public function verify_relationships_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . '360_user_relationships';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            assessor_id bigint(20) NOT NULL,
            assessee_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY assessor_id (assessor_id),
            KEY assessee_id (assessee_id),
            UNIQUE KEY unique_relationship (assessor_id,assessee_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        if (WP_DEBUG) {
            error_log("Verifying relationships table structure");
            error_log("Table name: " . $table_name);
            if ($wpdb->last_error) {
                error_log("Database error: " . $wpdb->last_error);
            }
        }
    }
    
    /**
     * Check if user is in Peers group
     * 
     * @param int $user_id User ID
     * @return bool True if user is in Peers group
     */
    public function is_peer_user($user_id) {
        global $wpdb;

        $users_table = $this->get_table_name();
        $groups_table = $this->get_groups_table_name();

        $query = $wpdb->prepare(
            "SELECT g.group_name 
             FROM {$users_table} u
             JOIN {$groups_table} g ON u.group_id = g.id
             WHERE u.id = %d",
            $user_id
        );

        if (WP_DEBUG) {
            error_log("Checking if user {$user_id} is in Peers group");
            error_log("Query: {$query}");
        }

        $group_name = $wpdb->get_var($query);

        if (WP_DEBUG) {
            error_log("Group name: {$group_name}");
        }

        return strtolower($group_name) === 'peers';
    }
    
    /**
     * Get assessment relationships for a user
     */
    public function get_user_relationships($user_id, $as_assessor = true) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}360_user_relationships 
             WHERE " . ($as_assessor ? "assessor_id = %d" : "assessee_id = %d"),
            $user_id
        );

        return $wpdb->get_results($query);
    }

    /**
     * Check if user relationship exists
     */
    public function check_user_relationship($assessor_id, $assessee_id) {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->prefix}360_user_relationships 
             WHERE assessor_id = %d 
             AND assessee_id = %d",
            $assessor_id,
            $assessee_id
        ));
    }
    
    public function get_user_assessment_progress($user_id, $assessment_id) {
        global $wpdb;

        try {
            $query = $wpdb->prepare(
                "SELECT 
                    COUNT(DISTINCT ai.assessee_id) as total_assessees,
                    COUNT(DISTINCT CASE WHEN ai.status = 'completed' THEN ai.assessee_id END) as completed_assessments
                 FROM {$wpdb->prefix}360_assessment_instances ai
                 WHERE ai.assessment_id = %d
                 AND ai.assessor_id = %d",
                $assessment_id,
                $user_id
            );

            if (WP_DEBUG) {
                error_log('Assessment progress query: ' . $query);
            }

            $progress = $wpdb->get_row($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            $total = (int)$progress->total_assessees;
            $completed = (int)$progress->completed_assessments;

            return (object)[
                'total' => $total,
                'completed' => $completed,
                'pending' => $total - $completed,
                'percentage' => $total > 0 ? round(($completed / $total) * 100) : 0
            ];

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error getting assessment progress: ' . $e->getMessage());
            }
            return null;
        }
    }
    
    public function update_user_to_assessees_relationships($user_id, $assessees) {
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . '360_user_relationships',
            ['assessor_id' => $user_id]
        );
        foreach ($assessees as $assessee_id) {
            if ($assessee_id == $user_id) continue;
            $wpdb->insert(
                $wpdb->prefix . '360_user_relationships',
                [
                    'assessor_id' => $user_id,
                    'assessee_id' => $assessee_id,
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%d', '%s']
            );
        }
        return true;
    }

    public function get_user($user_id) {
        global $wpdb;

        $table_name = $this->get_table_name();
        $groups_table = $this->get_groups_table_name();
        $positions_table = $this->get_positions_table_name();

        $query = $wpdb->prepare(
            "SELECT u.*, g.group_name, p.name as position_name 
             FROM {$table_name} u
             LEFT JOIN {$groups_table} g ON u.group_id = g.id
             LEFT JOIN {$positions_table} p ON u.position_id = p.id
             WHERE u.id = %d",
            $user_id
        );

        $user = $wpdb->get_row($query);

        if ($user) {
            // Ensure we use uppercase ID
            $user->ID = $user->id;
        }

        return $user;
    }

    public function create_user_relationships() {
        global $wpdb;

        try {
            // Start transaction
            $wpdb->query('START TRANSACTION');

            // Get all active users
            $users = $this->get_all_users();

            // Get peers group ID
            $peers_group = $wpdb->get_row(
                "SELECT id FROM {$wpdb->prefix}360_user_groups 
                 WHERE LOWER(group_name) = 'peers'"
            );

            if ($peers_group) {
                // Handle peer relationships
                $peer_users = array_filter($users, function($user) use ($peers_group) {
                    return $user->group_id == $peers_group->id;
                });

                // Create peer-to-peer relationships
                foreach ($peer_users as $assessor) {
                    foreach ($peer_users as $assessee) {
                        // Include self-assessment
                        $wpdb->replace(
                            $wpdb->prefix . '360_user_relationships',
                            array(
                                'assessor_id' => $assessor->id,
                                'assessee_id' => $assessee->id,
                                'created_at' => current_time('mysql')
                            ),
                            array('%d', '%d', '%s')
                        );

                        if ($wpdb->last_error) {
                            throw new Exception('Failed to create peer relationship: ' . $wpdb->last_error);
                        }
                    }
                }
            }

            // Handle other relationships based on your rules
            // Add additional relationship logic here

            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');

            if (WP_DEBUG) {
                error_log('Error creating user relationships: ' . $e->getMessage());
            }

            return false;
        }
    }
    
    public function get_users_by_group($include_counts = true) {
        global $wpdb;

        if ($include_counts) {
            return $wpdb->get_results(
                "SELECT g.*, 
                        COUNT(u.id) as user_count
                 FROM {$wpdb->prefix}360_user_groups g
                 LEFT JOIN {$wpdb->prefix}360_users u 
                    ON g.id = u.group_id 
                    AND u.status = 'active'
                 WHERE g.status = 'active'
                 GROUP BY g.id
                 ORDER BY g.group_name"
            );
        } else {
            return $wpdb->get_results(
                "SELECT * 
                 FROM {$wpdb->prefix}360_user_groups 
                 WHERE status = 'active' 
                 ORDER BY group_name"
            );
        }
    }
    
    public function get_users_grouped_by_group() {
        global $wpdb;

        $users = $wpdb->get_results(
            "SELECT u.*, 
                    p.name as position_name,
                    g.id as group_id,
                    g.group_name
             FROM {$wpdb->prefix}360_users u
             LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id
             LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
             WHERE u.status = 'active'
             ORDER BY g.group_name, u.first_name, u.last_name"
        );

        $grouped = array();

        // First, get all groups to ensure we include empty groups
        $groups = $wpdb->get_results(
            "SELECT id, group_name 
             FROM {$wpdb->prefix}360_user_groups 
             WHERE status = 'active' 
             ORDER BY group_name"
        );

        // Initialize groups
        foreach ($groups as $group) {
            $grouped[$group->id] = array(
                'name' => $group->group_name,
                'users' => array()
            );
        }

        // Add "Ungrouped" category
        $grouped[0] = array(
            'name' => 'Ungrouped',
            'users' => array()
        );

        // Group users
        foreach ($users as $user) {
            $group_id = $user->group_id ?? 0;
            if (!isset($grouped[$group_id])) {
                $grouped[$group_id] = array(
                    'name' => $user->group_name ?? 'Ungrouped',
                    'users' => array()
                );
            }

            // Add user data
            $grouped[$group_id]['users'][] = (object) array(
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'position_name' => $user->position_name,
                'group_name' => $user->group_name
            );
        }

        // Remove empty groups if they have no users
        foreach ($grouped as $group_id => $group) {
            if (empty($group['users']) && $group_id !== 0) {
                unset($grouped[$group_id]);
            }
        }

        return $grouped;
    }

    public function get_recent_users($limit = 5) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.*, p.name as position_name, g.group_name
            FROM {$wpdb->prefix}360_users u
            LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id
            LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
            WHERE u.status = 'active'
            ORDER BY u.created_at DESC
            LIMIT %d",
            $limit
        ));
    }

    public function get_users_by_position() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT p.name as position_name, 
                    COUNT(u.id) as user_count
            FROM {$wpdb->prefix}360_positions p
            LEFT JOIN {$wpdb->prefix}360_users u ON p.id = u.position_id AND u.status = 'active'
            WHERE p.status = 'active'
            GROUP BY p.id, p.name
            ORDER BY p.name"
        );
    }

    public function get_user_assessors($user_id) {
        global $wpdb;

        if (WP_DEBUG) {
            error_log("Getting assessors for user $user_id");
        }

        try {
            $query = $wpdb->prepare(
                "SELECT u.*, p.name as position_name
                 FROM {$wpdb->prefix}360_user_assessors ua
                 JOIN {$wpdb->prefix}360_users u ON ua.assessor_id = u.id
                 LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id
                 WHERE ua.user_id = %d
                 AND u.status = 'active'
                 ORDER BY u.first_name, u.last_name",
                $user_id
            );

            if (WP_DEBUG) {
                error_log('Assessors query: ' . $query);
            }

            $assessors = $wpdb->get_results($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            if (WP_DEBUG) {
                error_log('Found ' . count($assessors) . ' assessors');
            }

            return $assessors;

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error getting user assessors: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
            }

            return array();
        }
    }

    /**
     * Get potential assessors for a user
     */
    public function get_potential_assessors($user_id = null) {
        global $wpdb;

        if (WP_DEBUG) {
            error_log("Getting potential assessors" . ($user_id ? " for user $user_id" : ''));
        }

        try {
            $query = "SELECT u.*, p.name as position_name, g.group_name
                     FROM {$wpdb->prefix}360_users u
                     LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id
                     LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
                     WHERE u.status = 'active'";

            if ($user_id) {
                $query .= $wpdb->prepare(" AND u.id != %d", $user_id);
            }

            $query .= " ORDER BY u.first_name, u.last_name";

            if (WP_DEBUG) {
                error_log('Potential assessors query: ' . $query);
            }

            $assessors = $wpdb->get_results($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            if (WP_DEBUG) {
                error_log('Found ' . count($assessors) . ' potential assessors');
            }

            return $assessors;

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error getting potential assessors: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
            }

            return array();
        }
    }
    
    /**
     * Get all active users grouped by their groups
     * @return array Array of users grouped by their groups
     */
    public function get_all_active_users() {
        global $wpdb;

        try {
            if (WP_DEBUG) {
                error_log('Getting all active users');
            }

            $query = "SELECT DISTINCT u.*, g.group_name, p.name as position_name
                     FROM {$wpdb->prefix}360_users u
                     LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
                     LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id
                     WHERE u.status = %s
                     ORDER BY g.group_name, u.first_name, u.last_name";

            $users = $wpdb->get_results($wpdb->prepare($query, 'active'));

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            if (WP_DEBUG) {
                error_log('Found ' . count($users) . ' active users');
                error_log('Query: ' . $wpdb->last_query);
                error_log('Users: ' . print_r($users, true));
            }

            return $users ?: [];

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error getting active users: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
            }
            return [];
        }
    }
    
    /**
     * Check database tables
     */
    private function check_database_tables() {
        global $wpdb;

        if (WP_DEBUG) {
            error_log('Checking database tables');
        }

        $tables = [
            '360_users' => "SELECT COUNT(*) FROM {$wpdb->prefix}360_users WHERE status = 'active'",
            '360_user_groups' => "SELECT COUNT(*) FROM {$wpdb->prefix}360_user_groups WHERE status = 'active'",
            '360_positions' => "SELECT COUNT(*) FROM {$wpdb->prefix}360_positions WHERE status = 'active'"
        ];

        foreach ($tables as $table => $query) {
            $count = $wpdb->get_var($query);
            if ($wpdb->last_error) {
                error_log("Error checking $table: " . $wpdb->last_error);
            } else {
                error_log("$table count: $count");
            }
        }
    }
    
    /**
     * Get all users available for assessment
     * 
     * @param int|null $exclude_user_id User ID to exclude from the list
     * @return array Array of users
     */
    public function get_all_users_for_assessment($exclude_user_id = null) {
        global $wpdb;

        try {
            $query = "SELECT u.*, g.group_name, p.name as position_name
                     FROM {$wpdb->prefix}360_users u
                     LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
                     LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id
                     WHERE u.status = 'active'";

            if ($exclude_user_id) {
                $query .= $wpdb->prepare(" AND u.id != %d", $exclude_user_id);
            }

            $query .= " ORDER BY g.group_name, u.first_name, u.last_name";

            if (WP_DEBUG) {
                error_log('Getting users for assessment query: ' . $query);
            }

            $users = $wpdb->get_results($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            if (WP_DEBUG) {
                error_log('Found ' . count($users) . ' users for assessment');
                error_log('Users: ' . print_r($users, true));
            }

            return $users ?: [];

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error getting users for assessment: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
            }
            return [];
        }
    }

    public function enable_user($id) {
        global $wpdb;
        return $wpdb->update(
            $wpdb->prefix . '360_users',
            array('status' => 'active'),
            array('id' => $id),
            array('%s'),
            array('%d')
        );
    }

    public function disable_user($id) {
        global $wpdb;
        return $wpdb->update(
            $wpdb->prefix . '360_users',
            array('status' => 'inactive'),
            array('id' => $id),
            array('%s'),
            array('%d')
        );
    }
    
    public function verify_login($email, $password) {
        $user = $this->get_user_by_email($email);

        if (!$user) {
            return new WP_Error('invalid_email', 'Invalid email address.');
        }

        if (!wp_check_password($password, $user->password)) {
            return new WP_Error('invalid_password', 'Invalid password.');
        }

        if ($user->status !== 'active') {
            return new WP_Error('inactive_user', 'Your account is not active.');
        }

        // Update last login time
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . '360_users',
            array('last_login' => current_time('mysql')),
            array('id' => $user->id),
            array('%s'),
            array('%d')
        );

        return $user;
    }
    
    public function update_password($user_id, $new_password) {
        global $wpdb;
        return $wpdb->update(
            $wpdb->prefix . '360_users',
            array('password' => wp_hash_password($new_password)),
            array('id' => $user_id),
            array('%s'),
            array('%d')
        );
    }
    
    /**
     * Send password reset email to user
     * 
     * @param string $email User's email address
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function send_password_reset_email($email) {
        global $wpdb;

        if (WP_DEBUG) {
            error_log('Starting password reset process for: ' . $email);
        }

        $table_name = $wpdb->prefix . '360_users';

        // Get user
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE email = %s",
            $email
        ));

        if (!$user) {
            if (WP_DEBUG) error_log('User not found: ' . $email);
            return new WP_Error('invalid_email', 'Email address not found.');
        }

        if ($user->status !== 'active') {
            if (WP_DEBUG) error_log('User inactive: ' . $email);
            return new WP_Error('inactive_user', 'This account is inactive.');
        }

        // Generate token
        $token = wp_generate_password(32, false);
        $token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Update user with reset token
        $update_result = $wpdb->update(
            $table_name,
            array(
                'reset_token' => $token,
                'reset_token_expiry' => $token_expiry
            ),
            array('id' => $user->id)
        );

        if ($update_result === false) {
            if (WP_DEBUG) error_log('Failed to update reset token: ' . $wpdb->last_error);
            return new WP_Error('db_error', 'Database error occurred.');
        }

        // Prepare email
        $org_name = get_option('assessment_360_organization_name', 'Organization');
        $reset_link = add_query_arg(
            array(
                'user' => $user->id,
                'token' => $token
            ),
            home_url('/reset-password/')
        );

        $subject = sprintf('[%s] Password Reset Request', $org_name);

        $message = sprintf(
            'Hello %s,<br><br>
            A password reset has been requested for your account.<br><br>
            To reset your password, click the link below:<br>
            <a href="%s">Reset Password</a><br><br>
            This link will expire in 24 hours.<br><br>
            If you did not request this reset, please ignore this email.<br><br>
            Best regards,<br>
            %s Team',
            esc_html($user->first_name),
            esc_url($reset_link),
            esc_html($org_name)
        );

        // Set up email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $org_name . ' <' . get_option('admin_email') . '>'
        );

        if (WP_DEBUG) {
            error_log('Sending reset email to: ' . $user->email);
            error_log('Email subject: ' . $subject);
            error_log('Email message: ' . $message);
        }

        // Send email
        $sent = wp_mail($user->email, $subject, $message, $headers);

        if (!$sent) {
            if (WP_DEBUG) {
                global $phpmailer;
                if (isset($phpmailer)) {
                    error_log('PHPMailer Error: ' . $phpmailer->ErrorInfo);
                }
                error_log('Failed to send email to: ' . $user->email);
            }
            return new WP_Error('email_error', 'Failed to send reset email. Please try again later.');
        }

        if (WP_DEBUG) {
            error_log('Reset email sent successfully to: ' . $user->email);
        }

        return true;
    }

    /**
     * Verify reset token
     * 
     * @param int $user_id User ID
     * @param string $token Reset token
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function verify_reset_token($user_id, $token) {
        global $wpdb;

        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE id = %d 
             AND reset_token = %s 
             AND reset_token_expiry > %s 
             AND status = 'active'",
            $user_id,
            $token,
            current_time('mysql')
        ));

        if (!$user) {
            return new WP_Error('invalid_token', 'Invalid or expired reset token.');
        }

        return true;
    }

    /**
     * Reset user password
     * 
     * @param int $user_id User ID
     * @param string $new_password New password
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function reset_password($user_id, $new_password) {
        global $wpdb;

        // Hash the new password
        $hashed_password = wp_hash_password($new_password);

        // Update password and clear reset token
        $result = $wpdb->update(
            $this->table_name,
            array(
                'password' => $hashed_password,
                'reset_token' => null,
                'reset_token_expiry' => null
            ),
            array('id' => $user_id),
            array('%s', '%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update password.');
        }

        return true;
    }

    /**
     * Debug method to check user by email
     */
    public function debug_check_user($email) {
        global $wpdb;

        // Get the correct table name
        $table_name = $wpdb->prefix . '360_users';

        // Log the actual query
        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE email = %s",
            $email
        );

        error_log('Debug - Table name: ' . $table_name);
        error_log('Debug - Query: ' . $query);

        // Get the user without status check first
        $user = $wpdb->get_row($query);
        error_log('Debug - User found: ' . print_r($user, true));

        if ($user) {
            error_log('Debug - User status: ' . $user->status);
        }

        return $user;
    }

    /**
     * Get all active peer users
     * 
     * @return array Array of peer users
     */
    public function get_peer_users() {
        global $wpdb;

        $users_table = $wpdb->prefix . '360_users';
        $groups_table = $wpdb->prefix . '360_user_groups';
        $positions_table = $wpdb->prefix . '360_positions';

        $query = "
            SELECT u.*, g.group_name, p.name as position_name
            FROM {$users_table} u
            LEFT JOIN {$groups_table} g ON u.group_id = g.id
            LEFT JOIN {$positions_table} p ON u.position_id = p.id
            WHERE LOWER(g.group_name) = 'peers'
            AND u.status = 'active'
            ORDER BY u.first_name, u.last_name
        ";

        return $wpdb->get_results($query);
    }

    /**
     * Get all peer users for assessment
     * 
     * @param int $user_id Current user ID
     * @return array Array of peer users
     */
    public function get_users_for_assessment($user_id) {
        global $wpdb;

        $users_table = $this->get_table_name();
        $groups_table = $this->get_groups_table_name();
        $positions_table = $this->get_positions_table_name();
        $relationships_table = $this->get_relationships_table_name();

        // Get user's group
        $is_peer = $this->is_peer_user($user_id);

        if ($is_peer) {
            // If user is a peer, get all peer users (including self)
            $query = "
                SELECT DISTINCT u.*, g.group_name, p.name as position_name
                FROM {$users_table} u
                LEFT JOIN {$groups_table} g ON u.group_id = g.id
                LEFT JOIN {$positions_table} p ON u.position_id = p.id
                WHERE LOWER(g.group_name) = 'peers'
                AND u.status = 'active'
                ORDER BY u.first_name, u.last_name
            ";

            if (WP_DEBUG) {
                error_log("Getting all peer users for peer user {$user_id}");
            }

        } else {
            // If user is not a peer, get only assigned peer users
            $query = $wpdb->prepare("
                SELECT DISTINCT u.*, g.group_name, p.name as position_name
                FROM {$users_table} u
                LEFT JOIN {$groups_table} g ON u.group_id = g.id
                LEFT JOIN {$positions_table} p ON u.position_id = p.id
                INNER JOIN {$relationships_table} r ON r.assessee_id = u.id
                WHERE r.assessor_id = %d
                AND LOWER(g.group_name) = 'peers'
                AND u.status = 'active'
                ORDER BY u.first_name, u.last_name
            ", $user_id);

            if (WP_DEBUG) {
                error_log("Getting assigned peer users for non-peer user {$user_id}");
            }
        }

        $results = $wpdb->get_results($query);
    
        // Ensure all results have both id and ID properties
        foreach ($results as $result) {
            if (isset($result->id) && !isset($result->ID)) {
                $result->ID = $result->id;
            } elseif (isset($result->ID) && !isset($result->id)) {
                $result->id = $result->ID;
            }
        }

        if (WP_DEBUG) {
            error_log("Found " . count($results) . " users to assess");
            error_log("Query: " . $query);
            if ($wpdb->last_error) {
                error_log("Database error: " . $wpdb->last_error);
            }
        }

        return $results;
    }
    
    /**
     * Get user's assessee IDs
     * 
     * @param int $user_id User ID
     * @return array Array of assessee IDs
     */
    public function get_user_assessee_ids($user_id) {
        global $wpdb;

        $relationships_table = $wpdb->prefix . '360_user_relationships';

        $query = $wpdb->prepare(
            "SELECT assessee_id 
             FROM {$relationships_table}
             WHERE assessor_id = %d",
            $user_id
        );

        return $wpdb->get_col($query);
    }

    /**
     * Update user's assessees
     * 
     * @param int $user_id User ID
     * @param array $assessee_ids Array of assessee IDs
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update_user_assessees($user_id, $assessee_ids) {
        global $wpdb;

        $relationships_table = $wpdb->prefix . '360_user_relationships';

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Delete existing relationships
            $wpdb->delete(
                $relationships_table,
                ['assessor_id' => $user_id],
                ['%d']
            );

            // Insert new relationships
            foreach ($assessee_ids as $assessee_id) {
                $wpdb->insert(
                    $relationships_table,
                    [
                        'assessor_id' => $user_id,
                        'assessee_id' => $assessee_id
                    ],
                    ['%d', '%d']
                );

                if ($wpdb->last_error) {
                    throw new Exception($wpdb->last_error);
                }
            }

            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', $e->getMessage());
        }
    }

    public function get_user_stats() {
        global $wpdb;

        // Get total users
        $total = $this->get_total_users_count();

        // Get active users
        $active = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}360_users WHERE status = 'active'"
        );

        // Calculate active percentage
        $active_percentage = $total > 0 ? round(($active / $total) * 100) : 0;

        // Check if last_login column exists
        $has_last_login = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->prefix}360_users LIKE 'last_login'");

        // Get active users in last 30 days based on updated_at if last_login doesn't exist
        $active_last_30_days = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}360_users 
            WHERE " . ($has_last_login ? "last_login" : "updated_at") . " >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        return array(
            'total' => $total,
            'active' => $active,
            'inactive' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}360_users WHERE status = 'inactive'"
            ),
            'new_last_30_days' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}360_users 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            ),
            'active_last_30_days' => $active_last_30_days,
            'active_percentage' => $active_percentage
        );
    }

    public function get_user_count_by_position($position_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}360_users WHERE position_id = %d AND status = 'active'",
            $position_id
        ));
    }

    public function get_user_count_by_group($group_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}360_users WHERE group_id = %d AND status = 'active'",
            $group_id
        ));
    }
    
    /**
     * Get the table name with prefix
     * 
     * @return string Table name with prefix
     */
    private function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . '360_users';
    }

    /**
     * Get the groups table name with prefix
     * 
     * @return string Groups table name with prefix
     */
    private function get_groups_table_name() {
        global $wpdb;
        return $wpdb->prefix . '360_user_groups';
    }

    /**
     * Get the positions table name with prefix
     * 
     * @return string Positions table name with prefix
     */
    private function get_positions_table_name() {
        global $wpdb;
        return $wpdb->prefix . '360_positions';
    }

    /**
     * Get the relationships table name with prefix
     * 
     * @return string Relationships table name with prefix
     */
    private function get_relationships_table_name() {
        global $wpdb;
        return $wpdb->prefix . '360_user_relationships';
    }

    private function email_exists($email, $exclude_id = null) {
        global $wpdb;
        
        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}360_users WHERE email = %s";
        $params = array($email);
        
        if ($exclude_id) {
            $query .= " AND id != %d";
            $params[] = $exclude_id;
        }
        
        return (bool) $wpdb->get_var($wpdb->prepare($query, $params));
    }

    private function send_welcome_email($email, $password, $first_name) {
        $subject = get_option(
            'assessment_360_welcome_email_subject', 
            'Welcome to {org_name} 360° Assessment'
        );
        
        $body = get_option(
            'assessment_360_welcome_email_body', 
            $this->get_default_welcome_email()
        );
        
        $replacements = array(
            '{first_name}' => $first_name,
            '{email}' => $email,
            '{password}' => $password,
            '{login_url}' => home_url('/360-assessment-login/'),
            '{org_name}' => get_option('assessment_360_organization_name', 'Our Organization')
        );
        
        $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
        $body = str_replace(array_keys($replacements), array_values($replacements), $body);
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        return wp_mail($email, $subject, $body, $headers);
    }

    private function get_default_welcome_email() {
        return "<!DOCTYPE html>
                <html>
                <body>
                    <p>Hello {first_name},</p>
                    
                    <p>Welcome to the {org_name} 360° Assessment System!</p>
                    
                    <p>Your account has been created with the following credentials:</p>
                    
                    <p>
                    Username: {email}<br>
                    Password: {password}
                    </p>
                    
                    <p>Please login at: <a href='{login_url}'>{login_url}</a></p>
                    
                    <p>For security reasons, please change your password after your first login.</p>
                    
                    <p>
                    Best regards,<br>
                    The Assessment Team
                    </p>
                </body>
                </html>";
    }

    public function ajax_get_user_details() {
        // Verify nonce
        check_ajax_referer('assessment_360_ajax_nonce', 'nonce');
        
        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if (!$user_id) {
            wp_send_json_error('Invalid user ID');
        }
        
        $user = $this->get_user($user_id);
        if (!$user) {
            wp_send_json_error('User not found');
        }
        
        // Get user's assessors
        $assessors = $this->get_user_assessors($user_id);
        
        wp_send_json_success(array(
            'user' => $user,
            'assessors' => $assessors
        ));
    }

    public function cleanup_expired_tokens() {
        global $wpdb;
        return $wpdb->query(
            "DELETE FROM {$wpdb->prefix}360_password_resets WHERE expiry < NOW()"
        );
    }

    public function __destruct() {
        // Cleanup expired tokens periodically
        if (mt_rand(1, 100) === 1) { // 1% chance on each request
            $this->cleanup_expired_tokens();
        }
    }
}
