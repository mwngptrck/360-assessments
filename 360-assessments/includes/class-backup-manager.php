<?php
/**
 * Backup Manager Class
 */
class Assessment_360_Backup_Manager {
    private static $instance = null;

    /**
     * Get class instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Create database backup
     * 
     * @return array|WP_Error Array with backup details on success, WP_Error on failure
     */
    public function create_backup() {
        try {
            global $wpdb;
            
            // Get WordPress upload directory
            $upload_dir = wp_upload_dir();
            $backup_dir = $upload_dir['basedir'] . '/360-assessment-backups';
            
            // Create backup directory if it doesn't exist
            if (!file_exists($backup_dir)) {
                wp_mkdir_p($backup_dir);
                
                // Create .htaccess to prevent direct access
                file_put_contents($backup_dir . '/.htaccess', 'deny from all');
                
                // Create index.php for extra security
                file_put_contents($backup_dir . '/index.php', '<?php // Silence is golden');
            }

            // Create backup filename
            $timestamp = current_time('Y-m-d-His');
            $filename = "360-assessment-backup-{$timestamp}.sql";
            $filepath = $backup_dir . '/' . $filename;

            // Get all tables with prefix
            $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}360_%'", ARRAY_N);
            
            if (empty($tables)) {
                return new WP_Error('no_tables', 'No tables found to backup');
            }

            $handle = fopen($filepath, 'w');
            if ($handle === false) {
                return new WP_Error('file_error', 'Could not create backup file');
            }

            // Add header information
            fwrite($handle, "-- 360 Assessment Database Backup\n");
            fwrite($handle, "-- Generation Time: " . current_time('mysql') . "\n");
            fwrite($handle, "-- WordPress Prefix: " . $wpdb->prefix . "\n\n");

            // Process each table
            foreach ($tables as $table) {
                $table_name = $table[0];
                
                // Get create table syntax
                $create_table = $wpdb->get_row("SHOW CREATE TABLE `{$table_name}`", ARRAY_N);
                fwrite($handle, "\n\n-- Table structure for {$table_name}\n\n");
                fwrite($handle, $create_table[1] . ";\n\n");
                
                // Get table data
                $rows = $wpdb->get_results("SELECT * FROM `{$table_name}`", ARRAY_A);
                if (!empty($rows)) {
                    fwrite($handle, "-- Dumping data for {$table_name}\n");
                    
                    foreach ($rows as $row) {
                        $values = array_map(function($value) use ($wpdb) {
                            if (is_null($value)) {
                                return 'NULL';
                            }
                            return "'" . $wpdb->_real_escape($value) . "'";
                        }, $row);
                        
                        fwrite($handle, "INSERT INTO `{$table_name}` VALUES (" . implode(', ', $values) . ");\n");
                    }
                }
            }

            fclose($handle);

            // Create metadata
            $backup_info = array(
                'file' => $filename,
                'path' => $filepath,
                'url' => $upload_dir['baseurl'] . '/360-assessment-backups/' . $filename,
                'size' => size_format(filesize($filepath), 2),
                'date' => current_time('mysql'),
                'tables' => count($tables)
            );

            // Store backup info in options
            $backups = get_option('assessment_360_backups', array());
            array_unshift($backups, $backup_info);
            
            // Keep only last 5 backups in the list
            $backups = array_slice($backups, 0, 5);
            update_option('assessment_360_backups', $backups);

            return $backup_info;

        } catch (Exception $e) {
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }
            return new WP_Error('backup_failed', $e->getMessage());
        }
    }

    /**
     * Get list of backups
     * 
     * @return array Array of backup information
     */
    public function get_backups() {
        return get_option('assessment_360_backups', array());
    }

    /**
     * Delete a backup
     * 
     * @param string $filename Backup filename
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function delete_backup($filename) {
        $upload_dir = wp_upload_dir();
        $backup_path = $upload_dir['basedir'] . '/360-assessment-backups/' . $filename;
        
        if (!file_exists($backup_path)) {
            return new WP_Error('file_not_found', 'Backup file not found');
        }

        if (!unlink($backup_path)) {
            return new WP_Error('delete_failed', 'Could not delete backup file');
        }

        // Update backup list
        $backups = get_option('assessment_360_backups', array());
        $backups = array_filter($backups, function($backup) use ($filename) {
            return $backup['file'] !== $filename;
        });
        update_option('assessment_360_backups', array_values($backups));

        return true;
    }
}
