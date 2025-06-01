<?php
class Assessment_360_Activator {
    /**
     * Run activation tasks
     */
        public static function activate() {
        // Create database tables
        self::setup_database();

        // Create required pages - fix method name
        self::create_required_pages();

        // Set default options
        self::set_default_options();

        // Set activation flag for redirect
        //set_transient('assessment_360_activation_redirect', true, 30);
    }


    /**
     * Create or update database tables
     */
    public static function setup_database() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Create tables array
        $tables = array(
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
            )",

            // Positions Table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_positions (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                description text,
                status enum('active','inactive') NOT NULL DEFAULT 'active',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY status (status)
            )",

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
            )",

            // Topics Table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_topics (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                description text,
                status enum('active','inactive') NOT NULL DEFAULT 'active',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY status (status)
            )",

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
            )",

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
            )",

            // Assessments Table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_assessments (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                description text,
                status enum('active','completed','deleted') NOT NULL DEFAULT 'active',
                start_date date DEFAULT NULL,
                end_date date DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                completed_at datetime DEFAULT NULL,
                created_by bigint(20) DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY status (status)
            )",

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
            )",

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
            )"
        );

        // Create each table
        foreach ($tables as $sql) {
            dbDelta($sql . " $charset_collate;");
        }

        // Verify tables were created
        self::verify_tables();
    }

    /**
     * Verify tables exist
     */
    private static function verify_tables() {
        global $wpdb;
        
        $required_tables = array(
            '360_user_groups',
            '360_positions',
            '360_users',
            '360_topics',
            '360_sections',
            '360_questions',
            '360_assessments',
            '360_assessment_responses',
            '360_user_relationships'
        );

        $missing_tables = array();
        
        foreach ($required_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                $missing_tables[] = $table;
            }
        }

        if (!empty($missing_tables)) {
            // Log error and set flag
            update_option('assessment_360_missing_tables', $missing_tables);
            error_log('360 Assessment missing tables: ' . implode(', ', $missing_tables));
        } else {
            delete_option('assessment_360_missing_tables');
        }
    }

    /**
     * Create required front-end pages
     */
    private static function create_required_pages() {
        $pages = [
            'login' => [
                'title' => '360° Assessment Login',
                'slug' => '360-assessment-login',
                'content' => '<!-- wp:shortcode -->[assessment_360_login_form]<!-- /wp:shortcode -->'
            ],
            'dashboard' => [
                'title' => '360° Assessment Dashboard',
                'slug' => '360-assessment-dashboard',
                'content' => '<!-- wp:shortcode -->[assessment_360_dashboard]<!-- /wp:shortcode -->'
            ],
            'forgot-password' => [
                'title' => 'Forgot Password',
                'slug' => 'forgot-password',
                'content' => '<!-- wp:shortcode -->[assessment_360_forgot_password_form]<!-- /wp:shortcode -->'
            ],
            'reset-password' => [
                'title' => 'Reset Password',
                'slug' => 'reset-password',
                'content' => '<!-- wp:shortcode -->[assessment_360_reset_password_form]<!-- /wp:shortcode -->'
            ],
            'assessment-form' => [
                'title' => 'Assessment Form',
                'slug' => 'assessment-form',
                'content' => '<!-- wp:shortcode -->[assessment_360_form]<!-- /wp:shortcode -->'
            ]
        ];

        foreach ($pages as $key => $page) {
            // Check if page exists
            $existing_page = get_page_by_path($page['slug']);
            
            if (!$existing_page) {
                // Create the page
                $page_id = wp_insert_post([
                    'post_title' => $page['title'],
                    'post_name' => $page['slug'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_content' => $page['content']
                ]);

                if ($page_id) {
                    // Store page ID in options for future reference
                    update_option('assessment_360_page_' . $key, $page_id);
                }
            } else {
                // Store existing page ID
                update_option('assessment_360_page_' . $key, $existing_page->ID);
            }
        }

        // Flush rewrite rules after creating new pages
        flush_rewrite_rules();
    }
    
    /**
     * Set default options
     */
    private static function set_default_options() {
        // Store page slugs in options
        $page_slugs = [
            'login_page' => '360-assessment-login',
            'dashboard_page' => '360-assessment-dashboard',
            'forgot_password_page' => 'forgot-password',
            'reset_password_page' => 'reset-password',
            'assessment_form_page' => 'assessment-form'
        ];

        foreach ($page_slugs as $key => $slug) {
            if (!get_option('assessment_360_' . $key . '_slug')) {
                update_option('assessment_360_' . $key . '_slug', $slug);
            }
        }

        // Set other default options
        $default_options = [
            'assessment_360_version' => '1.0.0',
            'assessment_360_do_setup' => 'yes',
            'assessment_360_welcome_email_subject' => 'Welcome to {org_name} 360° Assessment',
            'assessment_360_welcome_email_body' => self::get_default_welcome_email(),
            'assessment_360_reminder_email_subject' => 'Reminder: Complete Your 360° Assessment',
            'assessment_360_reminder_email_body' => self::get_default_reminder_email()
        ];

        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
    
    /**
     * Get default welcome email template
     */
    private static function get_default_welcome_email() {
        return "Dear {first_name},

    Welcome to the {org_name} 360° Assessment System!

    Your account has been created with the following credentials:
    Email: {email}
    Password: {password}

    Please login at: {login_url}

    For security reasons, we recommend changing your password after your first login.

    Best regards,
    The Assessment Team";
    }
    
    /**
     * Get default reminder email template
     */
    private static function get_default_reminder_email() {
        return "Dear {first_name},

    This is a friendly reminder that you have pending assessments to complete.

    Assessment Details:
    - Name: {assessment_name}
    - Due Date: {due_date}
    - Days Remaining: {days_remaining}

    Your pending assessments:
    {pending_list}

    Please login at {login_url} to complete your assessments.

    Best regards,
    The Assessment Team";
    }

}
