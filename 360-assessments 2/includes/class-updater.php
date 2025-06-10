<?php
// Add to includes/class-updater.php

class Assessment_360_Updater {
    private static $instance = null;
    private $current_version;
    private $db_version_option = 'assessment_360_db_version';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->current_version = (string) get_option($this->db_version_option, '1.0.0');
    }

    public function check_updates() {
        if (version_compare($this->current_version, ASSESSMENT_360_VERSION, '<')) {
            $this->run_updates();
        }
    }

    private function run_updates() {
        global $wpdb;

        try {
            $wpdb->query('START TRANSACTION');

            // Run version-specific updates
            $methods = get_class_methods($this);
            foreach ($methods as $method) {
                if (strpos((string)$method, 'update_') === 0) {
                    $version = str_replace('update_', '', (string)$method);
                    $version = str_replace('_', '.', $version);
                    
                    if (version_compare($this->current_version, $version, '<')) {
                        $this->$method();
                    }
                }
            }

            // Update version number
            update_option($this->db_version_option, ASSESSMENT_360_VERSION);
            
            $wpdb->query('COMMIT');

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
        }
    }

    private function update_1_2_0() {
        global $wpdb;
        
        // Add indexes for better performance
        $tables = [
            '360_assessment_instances' => [
                'idx_assessment_user' => ['assessment_id', 'assessor_id', 'assessee_id'],
                'idx_status' => ['status']
            ],
            '360_assessment_responses' => [
                'idx_instance_question' => ['assessment_instance_id', 'question_id']
            ],
            '360_user_relationships' => [
                'idx_users' => ['assessor_id', 'assessee_id']
            ]
        ];

        foreach ($tables as $table => $indexes) {
            foreach ($indexes as $index_name => $columns) {
                $columns_str = implode(', ', $columns);
                $wpdb->query("ALTER TABLE {$wpdb->prefix}$table 
                            ADD INDEX $index_name ($columns_str)");
            }
        }
    }
}
