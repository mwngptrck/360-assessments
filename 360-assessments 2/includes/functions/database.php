<?php
/**
 * 360 Assessments Database Functions
 * 
 * Contains all logic for creating, updating, and uninstalling plugin-specific database tables and options.
 */

function assessment_360_activate($network_wide) {
    global $wpdb;
    
    $user_groups_table = $wpdb->prefix . '360_user_groups';

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $charset_collate = $wpdb->get_charset_collate();
    
    $tables = [
        // User Groups Table
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_user_groups (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            group_name varchar(255) NOT NULL,
            description text,
            is_department tinyint(1) NOT NULL DEFAULT 0,
            status enum('active','inactive') NOT NULL DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY is_department (is_department)
        ) $charset_collate;",
        
        //user instances
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_assessment_instances (
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
        ) $charset_collate;",

        // Positions Table
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_positions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            status enum('active','inactive') NOT NULL DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status)
        ) $charset_collate;",

        // Users Table
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_users (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(255) NOT NULL,
            password varchar(255) NOT NULL,
            position_id bigint(20) DEFAULT NULL,
            group_id bigint(20) DEFAULT NULL,
            department_id bigint(20) DEFAULT NULL,
            status enum('active','inactive','deleted') NOT NULL DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY position_id (position_id),
            KEY group_id (group_id),
            KEY status (status)
        ) $charset_collate;",

        // Topics Table
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_topics (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            status enum('active','inactive') NOT NULL DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status)
        ) $charset_collate;",

        // Sections Table
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_sections (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            topic_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            status enum('active','inactive') NOT NULL DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY topic_id (topic_id),
            KEY status (status)
        ) $charset_collate;",

        // Questions Table
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_questions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            section_id bigint(20) NOT NULL,
            question_text text NOT NULL,
            question_order int(11) NOT NULL DEFAULT 0,
            is_mandatory tinyint(1) NOT NULL DEFAULT 1,
            has_comment_box tinyint(1) NOT NULL DEFAULT 0,
            status enum('active','inactive') NOT NULL DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY section_id (section_id),
            KEY status (status)
        ) $charset_collate;",

        // Assessments Table
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_assessments (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            status enum('draft','active','completed','deleted') NOT NULL DEFAULT 'draft',
            start_date date DEFAULT NULL,
            end_date date DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            created_by bigint(20) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status (status)
        ) $charset_collate;",

        // Assessment Responses Table
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_assessment_responses (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            assessment_id bigint(20) NOT NULL,
            assessor_id bigint(20) NOT NULL,
            assessee_id bigint(20) NOT NULL,
            question_id bigint(20) NOT NULL,
            rating int(11) DEFAULT NULL,
            comment text,
            status enum('pending','completed') NOT NULL DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY assessment_id (assessment_id),
            KEY assessor_id (assessor_id),
            KEY assessee_id (assessee_id),
            KEY question_id (question_id),
            KEY status (status)
        ) $charset_collate;",

        // User Relationships Table
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_user_relationships (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            assessor_id bigint(20) NOT NULL,
            assessee_id bigint(20) NOT NULL,
            relationship_type enum('peer','supervisor','subordinate','self') NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_relationship (assessor_id, assessee_id),
            KEY assessor_id (assessor_id),
            KEY assessee_id (assessee_id)
        ) $charset_collate;",
        
        // Password Resets Table
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_password_resets (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            token varchar(255) NOT NULL,
            expiry datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}360_users(id) ON DELETE CASCADE
        ) $charset_collate;",

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
        ) $charset_collate;",
        
        //User activity log
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_activity_log (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            action varchar(50) NOT NULL,
            details text,
            ip_address varchar(45),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;",

    ];

    // Create all tables
    foreach ($tables as $sql) {
        dbDelta($sql);
    }

    // Set default email templates if not set
    if (!get_option('assessment_360_welcome_email')) {
        update_option('assessment_360_welcome_email', 
            "Welcome to the 360° Assessment System!\n\n" .
            "Your account has been created with the following credentials:\n" .
            "Email: {email}\n" .
            "Password: {password}\n\n" .
            "Please login at: {login_url}"
        );
    }
    if (!get_option('assessment_360_reminder_email')) {
        update_option('assessment_360_reminder_email',
            "Hello {first_name},\n\n" .
            "This is a reminder that you have pending assessments to complete.\n" .
            "Please login at {login_url} to complete your assessments.\n\n" .
            "Thank you."
        );
    }
    add_option('assessment_360_version', '1.0.0');
    add_option('assessment_360_do_setup', 'yes');
    assessment_360_create_pages();
    if ($wpdb->get_var("SHOW TABLES LIKE '{$user_groups_table}'") == $user_groups_table) {
        // Table exists
        assessment_360_insert_default_peers_group();
    } else {
        error_log("Table $user_groups_table does not exist after dbDelta.");
    }
}

/**
 * Database schema and option updates.
 */
function assessment_360_update_db() {
    global $wpdb;
    $current_version = get_option('assessment_360_version', '0');
    if (version_compare($current_version, '1.0.0', '<')) {
        try {
            $wpdb->query("
                ALTER TABLE {$wpdb->prefix}360_assessments 
                MODIFY COLUMN status enum('draft','active','completed','deleted') 
                NOT NULL DEFAULT 'draft'
            ");
            $wpdb->query("
                ALTER TABLE {$wpdb->prefix}360_assessment_responses 
                MODIFY COLUMN status enum('pending','completed') 
                NOT NULL DEFAULT 'pending'
            ");
            $assessments_columns = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}360_assessments");
            if (!in_array('completed_at', $assessments_columns)) {
                $wpdb->query("
                    ALTER TABLE {$wpdb->prefix}360_assessments 
                    ADD COLUMN completed_at datetime DEFAULT NULL
                ");
            }
            $responses_columns = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}360_assessment_responses");
            if (!in_array('completed_at', $responses_columns)) {
                $wpdb->query("
                    ALTER TABLE {$wpdb->prefix}360_assessment_responses 
                    ADD COLUMN completed_at datetime DEFAULT NULL
                ");
            }
            update_option('assessment_360_version', '1.0.0');
        } catch (Exception $e) {
            error_log('360 Assessment DB Update Error: ' . $e->getMessage());
        }
    }
}

/**
 * Plugin uninstall: remove tables, pages, options.
 */
function assessment_360_uninstall() {
    if (!get_option('assessment_360_allow_uninstall')) {
        return;
    }
    try {
        Assessment_360_User_Manager::get_instance()->remove_tables();
        Assessment_360_Group_Manager::get_instance()->remove_tables();
        Assessment_360_Assessment_Manager::get_instance()->remove_tables();
        Assessment_360_Settings_Manager::get_instance()->remove_settings();
        $options = [
            'assessment_360_version',
            'assessment_360_installed',
            'assessment_360_allow_uninstall',
            'assessment_360_organization_name',
            'assessment_360_organization_logo'
        ];
        foreach ($options as $option) {
            delete_option($option);
        }
        $pages = ['360-assessment-login', '360-assessment-dashboard', 'forgot-password', 'reset-password'];
        foreach ($pages as $page_slug) {
            $page = get_page_by_path($page_slug);
            if ($page) {
                wp_delete_post($page->ID, true);
            }
        }
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '%assessment_360_%'"
        );
    } catch (Exception $e) {
        // Silent fail for uninstall.
    }
}
register_uninstall_hook(ASSESSMENT_360_PLUGIN_FILE, 'assessment_360_uninstall');

/**
 * Helper: create required WP pages if not exist.
 */
function assessment_360_create_pages() {
    if (!get_page_by_path('360-assessment-login')) {
        wp_insert_post([
            'post_title' => '360° Assessment Login',
            'post_name' => '360-assessment-login',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => ''
        ]);
    }
    if (!get_page_by_path('360-assessment-dashboard')) {
        wp_insert_post([
            'post_title' => '360° Assessment Dashboard',
            'post_name' => '360-assessment-dashboard',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => ''
        ]);
    }
    if (!get_page_by_path('forgot-password')) {
        wp_insert_post([
            'post_title' => 'Forgot Password',
            'post_name' => 'forgot-password',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => ''
        ]);
    }
    if (!get_page_by_path('reset-password')) {
        wp_insert_post([
            'post_title' => 'Reset Password',
            'post_name' => 'reset-password',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => ''
        ]);
    }
}

/**
 * Insert default user group "Peers" for all assessees upon activation.
 * Call this after creating the user groups table.
 */
function assessment_360_insert_default_peers_group() {
    global $wpdb;
    $table_name = $wpdb->prefix . '360_user_groups';

    // Check table existence
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
        error_log("Table $table_name not found when trying to insert Peers group.");
        return;
    }

    // Check if "Peers" already exists (case-insensitive)
    $exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE LOWER(name) = %s",
            strtolower('Peers')
        )
    );

    if (!$exists) {
        $wpdb->insert(
            $table_name,
            array(
                'group_name'        => 'Peers',
                'description' => 'Default user group for all assessees',
                'created_at'  => current_time('mysql')
            ),
            array('%s', '%s', '%s')
        );
        if ($wpdb->last_error) {
            error_log("Error inserting Peers group: " . $wpdb->last_error);
        }
    }
}

/**
 * ===========================
 * Suggestions for Improvement
 * ===========================
 * - Consider using dedicated schema versioning for easier upgrades.
 * - Use batch processing for large uninstall/cleanup tasks.
 * - Add logging for uninstall and activation events.
 * - Use WP-CLI commands for database/table management.
 * - Add automated tests for activation/uninstall routines.
 */