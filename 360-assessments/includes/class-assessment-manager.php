<?php
class Assessment_360_Assessment_Manager {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get single assessment
     * 
     * @param int $id Assessment ID
     * @return object|null Assessment object or null if not found
     */
    public function get_assessment($id) {
        global $wpdb;

        try {
            $query = $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}360_assessments WHERE id = %d",
                $id
            );

            $assessment = $wpdb->get_row($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            // Ensure properties are not null
            if ($assessment) {
                $assessment->name = $assessment->name ?? '';
                $assessment->description = $assessment->description ?? '';
                $assessment->start_date = $assessment->start_date ?? '';
                $assessment->end_date = $assessment->end_date ?? '';
                $assessment->status = $assessment->status ?? 'draft';
            }

            return $assessment;

        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get user's assessment status
     * 
     * @param int $user_id User ID
     * @param int $assessment_id Assessment ID
     * @return string 'completed' or 'ongoing'
     */
    public function get_user_assessment_status($user_id, $assessment_id) {
        global $wpdb;

        if (WP_DEBUG) {
            error_log("Getting assessment status for user $user_id in assessment $assessment_id");
        }

        // Get correct table names
        $relationships_table = $wpdb->prefix . '360_user_relationships';
        $responses_table = $wpdb->prefix . '360_assessment_responses';
        $users_table = $wpdb->prefix . '360_users';
        $groups_table = $wpdb->prefix . '360_user_groups';

        // Check if user is in Peers group
        $user_query = $wpdb->prepare(
            "SELECT g.group_name 
             FROM {$users_table} u
             JOIN {$groups_table} g ON u.group_id = g.id
             WHERE u.id = %d",
            $user_id
        );

        $group_name = $wpdb->get_var($user_query);
        $is_peer = strtolower($group_name) === 'peers';

        if ($is_peer) {
            // For peer users, check if all their assessors have completed their assessments
            $query = $wpdb->prepare(
                "SELECT 
                    (SELECT COUNT(DISTINCT assessor_id) 
                     FROM {$relationships_table} 
                     WHERE assessee_id = %d) as total_assessors,
                    (SELECT COUNT(DISTINCT assessor_id) 
                     FROM {$responses_table} 
                     WHERE assessee_id = %d 
                     AND assessment_id = %d 
                     AND status = 'completed') as completed_assessments",
                $user_id,
                $user_id,
                $assessment_id
            );
        } else {
            // For assessors, check if they've completed all their assigned assessments
            $query = $wpdb->prepare(
                "SELECT 
                    (SELECT COUNT(DISTINCT assessee_id) 
                     FROM {$relationships_table} 
                     WHERE assessor_id = %d) as total_assessments,
                    (SELECT COUNT(DISTINCT assessee_id) 
                     FROM {$responses_table} 
                     WHERE assessor_id = %d 
                     AND assessment_id = %d 
                     AND status = 'completed') as completed_assessments",
                $user_id,
                $user_id,
                $assessment_id
            );
        }

        if (WP_DEBUG) {
            error_log("Executing query: $query");
        }

        $results = $wpdb->get_row($query);

        if (WP_DEBUG) {
            error_log("Query results: " . print_r($results, true));
            if ($wpdb->last_error) {
                error_log("Database error: " . $wpdb->last_error);
            }
        }

        if (!$results) {
            return 'ongoing';
        }

        $total = $is_peer ? $results->total_assessors : $results->total_assessments;
        $completed = $results->completed_assessments;

        // If there are no assessments/assessors assigned, consider it completed
        if ($total == 0) {
            return 'completed';
        }

        // Status is completed only if all assessments are completed
        return ($completed >= $total) ? 'completed' : 'ongoing';
    }
    
    /**
     * Get assessment completion percentage
     * 
     * @param int $user_id User ID
     * @param int $assessment_id Assessment ID
     * @return int Percentage completed
     */
    public function get_completion_percentage($user_id, $assessment_id) {
        global $wpdb;

        $relationships_table = $wpdb->prefix . '360_user_relationships';
        $responses_table = $wpdb->prefix . '360_assessment_responses';

        $query = $wpdb->prepare(
            "SELECT 
                (SELECT COUNT(*) 
                 FROM {$relationships_table} 
                 WHERE assessor_id = %d) as total,
                (SELECT COUNT(*) 
                 FROM {$responses_table} 
                 WHERE assessor_id = %d 
                 AND assessment_id = %d 
                 AND status = 'completed') as completed",
            $user_id,
            $user_id,
            $assessment_id
        );

        if (WP_DEBUG) {
            error_log("Completion percentage query: " . $query);
        }

        $results = $wpdb->get_row($query);

        if (WP_DEBUG) {
            error_log("Results: " . print_r($results, true));
            if ($wpdb->last_error) {
                error_log("Database error: " . $wpdb->last_error);
            }
        }

        if (!$results || $results->total == 0) {
            return 100; // If no assessments assigned, consider it 100%
        }

        return round(($results->completed / $results->total) * 100);
    }
    
    public function get_user_statistics($user_id) {
        global $wpdb;

        try {
            // Get active assessment
            $active_assessment = $this->get_active_assessment();
            if (!$active_assessment) {
                return $this->get_empty_statistics();
            }

            // Validate user ID
            $user_id = absint($user_id);
            if (!$user_id) {
                return $this->get_empty_statistics();
            }

            // First, get the user's group with proper error handling
            $user_group_query = $wpdb->prepare(
                "SELECT u.group_id, g.group_name 
                 FROM {$wpdb->prefix}360_users u
                 LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
                 WHERE u.id = %d",
                $user_id
            );

            $user_group = $wpdb->get_row($user_group_query);

            if ($wpdb->last_error) {
                throw new Exception('Database error getting user group: ' . $wpdb->last_error);
            }

            // Check if user and group exist
            if (!$user_group || !isset($user_group->group_name)) {
                return $this->get_empty_statistics();
            }

            // Get statistics based on user's group
            $is_peer = strtolower(trim($user_group->group_name)) === 'peers';

            if ($is_peer) {
                // For Peers group: count all users in the same group except self
                $query = $wpdb->prepare(
                    "WITH PeerStats AS (
                        SELECT COUNT(*) as total_peers
                        FROM {$wpdb->prefix}360_users 
                        WHERE group_id = %d 
                        AND id != %d 
                        AND status = 'active'
                    ),
                    CompletedStats AS (
                        SELECT COUNT(DISTINCT ai.assessee_id) as completed_count
                        FROM {$wpdb->prefix}360_assessment_instances ai
                        WHERE ai.assessment_id = %d 
                        AND ai.assessor_id = %d
                        AND ai.status = 'completed'
                    )
                    SELECT 
                        p.total_peers as total,
                        COALESCE(c.completed_count, 0) as completed,
                        p.total_peers - COALESCE(c.completed_count, 0) as pending
                    FROM PeerStats p
                    CROSS JOIN CompletedStats c",
                    $user_group->group_id,
                    $user_id,
                    $active_assessment->id,
                    $user_id
                );
            } else {
                // For other groups: use relationships table
                $query = $wpdb->prepare(
                    "SELECT 
                        COUNT(DISTINCT ur.assessee_id) as total,
                        COUNT(DISTINCT CASE WHEN ai.status = 'completed' THEN ai.assessee_id END) as completed,
                        COUNT(DISTINCT ur.assessee_id) - 
                        COUNT(DISTINCT CASE WHEN ai.status = 'completed' THEN ai.assessee_id END) as pending
                    FROM {$wpdb->prefix}360_user_relationships ur
                    LEFT JOIN {$wpdb->prefix}360_assessment_instances ai 
                        ON ai.assessment_id = %d 
                        AND ai.assessor_id = ur.assessor_id 
                        AND ai.assessee_id = ur.assessee_id
                    WHERE ur.assessor_id = %d",
                    $active_assessment->id,
                    $user_id
                );
            }

            $stats = $wpdb->get_row($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error getting statistics: ' . $wpdb->last_error);
            }

            if (!$stats) {
                return $this->get_empty_statistics();
            }

            // Calculate statistics with proper null checks
            $total = (int)($stats->total ?? 0);
            $completed = (int)($stats->completed ?? 0);
            $pending = (int)($stats->pending ?? 0);
            $completion_rate = $total > 0 ? round(($completed / $total) * 100) : 0;

            return (object)[
                'total_assessments' => $total,
                'completed_assessments' => $completed,
                'pending_assessments' => $pending,
                'completion_rate' => $completion_rate
            ];

        } catch (Exception $e) {
            return $this->get_empty_statistics();
        }
    }

    /**
     * Get empty statistics object
     * 
     * @return stdClass
     */
    private function get_empty_statistics() {
        return (object)[
            'total_assessments' => 0,
            'completed_assessments' => 0,
            'pending_assessments' => 0,
            'completion_rate' => 0
        ];
    }



    public function save_assessment_response($data) {
        global $wpdb;
        
        try {
            $wpdb->query('START TRANSACTION');

            // Create or update assessment instance
            $instance_data = array(
                'assessment_id' => $data['assessment_id'],
                'assessor_id' => $data['assessor_id'],
                'assessee_id' => $data['assessee_id'],
                'status' => 'completed',
                'completed_at' => current_time('mysql')
            );

            $instance_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}360_assessment_instances 
                 WHERE assessment_id = %d AND assessor_id = %d AND assessee_id = %d",
                $data['assessment_id'],
                $data['assessor_id'],
                $data['assessee_id']
            ));

            if ($instance_exists) {
                // Update existing instance
                $wpdb->update(
                    $wpdb->prefix . '360_assessment_instances',
                    $instance_data,
                    array('id' => $instance_exists)
                );
                $instance_id = $instance_exists;
            } else {
                // Create new instance
                $wpdb->insert(
                    $wpdb->prefix . '360_assessment_instances',
                    $instance_data
                );
                $instance_id = $wpdb->insert_id;
            }

            if ($wpdb->last_error) {
                throw new Exception('Failed to save assessment instance: ' . $wpdb->last_error);
            }

            // Save responses
            foreach ($data['ratings'] as $question_id => $rating) {
                $response_data = array(
                    'assessment_instance_id' => $instance_id,
                    'question_id' => $question_id,
                    'rating' => $rating,
                    'comment' => $data['comments'][$question_id] ?? null
                );

                $wpdb->insert(
                    $wpdb->prefix . '360_assessment_responses',
                    $response_data
                );

                if ($wpdb->last_error) {
                    throw new Exception('Failed to save response: ' . $wpdb->last_error);
                }
            }

            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            
            return new WP_Error('save_failed', $e->getMessage());
        }
    }

    /**
     * Get assessment status between users
     * 
     * @param int $assessor_id The assessor's ID
     * @param int $assessee_id The assessee's ID
     * @param int|null $assessment_id Optional assessment ID
     * @return string Status ('completed', 'pending', etc.)
     */
    public function get_assessment_status($assessor_id, $assessee_id, $assessment_id = null) {
        global $wpdb;

        $query = "SELECT status 
                  FROM {$wpdb->prefix}360_assessment_instances 
                  WHERE assessor_id = %d 
                  AND assessee_id = %d";

        $params = [$assessor_id, $assessee_id];

        if ($assessment_id) {
            $query .= " AND assessment_id = %d";
            $params[] = $assessment_id;
        }

        $query .= " ORDER BY created_at DESC LIMIT 1";

        $status = $wpdb->get_var($wpdb->prepare($query, $params));

        return $status ?: 'pending';
    }
    
    /**
     * Get current active assessment ID
     * 
     * @return int|null Assessment ID or null if no active assessment
     */
    public function get_current_assessment_id() {
        $assessment = $this->get_current_assessment();
        return $assessment ? $assessment->id : null;
    }
    
    /**
     * Get user's assessments
     */
    public function get_user_assessments($user_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT a.*, 
                    (SELECT status FROM {$wpdb->prefix}360_assessment_instances 
                     WHERE assessment_id = a.id AND assessee_id = %d 
                     ORDER BY created_at DESC LIMIT 1) as status
             FROM {$wpdb->prefix}360_assessments a
             JOIN {$wpdb->prefix}360_assessment_instances ai ON a.id = ai.assessment_id
             WHERE ai.assessee_id = %d
             ORDER BY a.start_date DESC",
            $user_id,
            $user_id
        ));
    }
    
    public function save_assessment($data) {
        global $wpdb;

        if (WP_DEBUG) {
            error_log('Saving assessment: ' . print_r($data, true));
        }

        try {
            $wpdb->query('START TRANSACTION');

            // Insert assessment instance with correct columns
            $result = $wpdb->insert(
                $wpdb->prefix . '360_assessment_instances',
                array(
                    'assessment_id' => $data['assessment_id'],
                    'assessor_id'   => $data['assessor_id'],
                    'assessee_id'   => $data['assessee_id'],
                    'status'        => 'completed',
                    'completed_at'  => $data['submitted_at'],
                ),
                array('%d', '%d', '%d', '%s', '%s')
            );

            if ($result === false) {
                throw new Exception('Failed to create assessment instance');
            }

            $instance_id = $wpdb->insert_id;

            // Save individual ratings
            foreach ($data['ratings'] as $question_id => $rating) {
                $comment = isset($data['comments'][$question_id]) ? $data['comments'][$question_id] : '';

                $result = $wpdb->insert(
                    $wpdb->prefix . '360_assessment_responses',
                    array(
                        'assessment_instance_id' => $instance_id, // Correct column name!
                        'question_id' => $question_id,
                        'rating'      => $rating,
                        'comment'     => $comment,
                        'created_at'  => current_time('mysql')
                    ),
                    array('%d', '%d', '%d', '%s', '%s')
                );
                if ($result === false) {
                    throw new Exception('Failed to save response');
                }
            }

            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');

            if (WP_DEBUG) {
                error_log('Error saving assessment: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
            }

            return new WP_Error('save_failed', $e->getMessage());
        }
    }

    public function is_assessment_completed($assessor_id, $assessee_id) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->prefix}360_assessment_instances 
             WHERE assessor_id = %d 
             AND assessee_id = %d 
             AND status = 'completed'",
            $assessor_id,
            $assessee_id
        ));

        return $count > 0;
    }

    public function get_assessment_questions($position_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT q.*, s.name as section_name
             FROM {$wpdb->prefix}360_questions q
             JOIN {$wpdb->prefix}360_sections s ON q.section_id = s.id
             WHERE q.position_id = %d
             ORDER BY s.display_order, q.display_order",
            $position_id
        ));
    }

    public function get_user_assessment_progress($user_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT ai.assessee_id) as total_assessees,
                COUNT(DISTINCT CASE WHEN ai.status = 'completed' 
                    THEN ai.assessee_id END) as completed_assessments
             FROM {$wpdb->prefix}360_assessment_instances ai
             WHERE ai.assessor_id = %d",
            $user_id
        ));
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Assessments table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_assessments (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            description text,
            start_date date NOT NULL,
            end_date date NOT NULL,
            status varchar(20) DEFAULT 'draft',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY date_range (start_date, end_date)
        ) $charset_collate;";
        dbDelta($sql);

        // Questions table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_questions (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            assessment_id mediumint(9) NOT NULL,
            question_text text NOT NULL,
            question_order int NOT NULL DEFAULT 0,
            is_required tinyint(1) DEFAULT 1,
            has_comments tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY assessment_id (assessment_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Assessment instances table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_assessment_instances (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            assessment_id mediumint(9) NOT NULL,
            assessor_id mediumint(9) NOT NULL,
            assessee_id mediumint(9) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_assessment (assessment_id, assessor_id, assessee_id),
            KEY assessment_id (assessment_id),
            KEY assessor_id (assessor_id),
            KEY assessee_id (assessee_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Assessment responses table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}360_assessment_responses (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            assessment_instance_id mediumint(9) NOT NULL,
            question_id mediumint(9) NOT NULL,
            rating int(1) NOT NULL,
            comment text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY assessment_instance_id (assessment_instance_id),
            KEY question_id (question_id)
        ) $charset_collate;";
        dbDelta($sql);
    }

    public function get_active_assessment() {
        global $wpdb;

        try {
            $query = "SELECT * 
                     FROM {$wpdb->prefix}360_assessments 
                     WHERE status = 'active' 
                     AND start_date <= CURRENT_DATE 
                     AND end_date >= CURRENT_DATE 
                     ORDER BY created_at DESC 
                     LIMIT 1";

            if (WP_DEBUG) {
                error_log('Getting active assessment query: ' . $query);
            }

            $assessment = $wpdb->get_row($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            if (WP_DEBUG && $assessment) {
                error_log('Found active assessment: ' . print_r($assessment, true));
            }

            return $assessment;

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error getting active assessment: ' . $e->getMessage());
            }
            return null;
        }
    }
    public function verify_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        try {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            // Verify assessments table
            $assessment_questions_table = $wpdb->prefix . '360_assessment_questions';
            if ($wpdb->get_var("SHOW TABLES LIKE '$assessment_questions_table'") != $assessment_questions_table) {
                $sql = "CREATE TABLE $assessment_questions_table (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    assessment_id mediumint(9) NOT NULL,
                    question_id mediumint(9) NOT NULL,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    UNIQUE KEY unique_assessment_question (assessment_id, question_id),
                    KEY assessment_id (assessment_id),
                    KEY question_id (question_id)
                ) $charset_collate;";

                dbDelta($sql);

                if ($wpdb->last_error) {
                    throw new Exception('Failed to create assessment questions table: ' . $wpdb->last_error);
                }
            }
            
            // Verify assessments table
            $assessments_table = $wpdb->prefix . '360_assessments';
            if ($wpdb->get_var("SHOW TABLES LIKE '$assessments_table'") != $assessments_table) {
                $sql = "CREATE TABLE $assessments_table (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    name varchar(100) NOT NULL,
                    description text,
                    start_date date NOT NULL,
                    end_date date NOT NULL,
                    status varchar(20) DEFAULT 'draft',
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY status (status),
                    KEY date_range (start_date, end_date)
                ) $charset_collate;";

                dbDelta($sql);

                if ($wpdb->last_error) {
                    throw new Exception('Failed to create assessments table: ' . $wpdb->last_error);
                }
            }

            // Verify sections table
            $sections_table = $wpdb->prefix . '360_sections';
            if ($wpdb->get_var("SHOW TABLES LIKE '$sections_table'") != $sections_table) {
                $sql = "CREATE TABLE $sections_table (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    name varchar(100) NOT NULL,
                    description text,
                    display_order int DEFAULT 0,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY display_order (display_order)
                ) $charset_collate;";

                dbDelta($sql);

                if ($wpdb->last_error) {
                    throw new Exception('Failed to create sections table: ' . $wpdb->last_error);
                }
            }

            // Verify questions table
            $questions_table = $wpdb->prefix . '360_questions';
            if ($wpdb->get_var("SHOW TABLES LIKE '$questions_table'") != $questions_table) {
                $sql = "CREATE TABLE $questions_table (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    section_id mediumint(9),
                    question_text text NOT NULL,
                    display_order int DEFAULT 0,
                    is_required tinyint(1) DEFAULT 1,
                    has_comments tinyint(1) DEFAULT 1,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY section_id (section_id),
                    KEY display_order (display_order)
                ) $charset_collate;";

                dbDelta($sql);

                if ($wpdb->last_error) {
                    throw new Exception('Failed to create questions table: ' . $wpdb->last_error);
                }
            }

            // Verify assessment_questions junction table
            $assessment_questions_table = $wpdb->prefix . '360_assessment_questions';
            if ($wpdb->get_var("SHOW TABLES LIKE '$assessment_questions_table'") != $assessment_questions_table) {
                $sql = "CREATE TABLE $assessment_questions_table (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    assessment_id mediumint(9) NOT NULL,
                    question_id mediumint(9) NOT NULL,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    UNIQUE KEY unique_assessment_question (assessment_id, question_id),
                    KEY assessment_id (assessment_id),
                    KEY question_id (question_id)
                ) $charset_collate;";

                dbDelta($sql);

                if ($wpdb->last_error) {
                    throw new Exception('Failed to create assessment questions table: ' . $wpdb->last_error);
                }
            }

            // Verify assessment instances table
            $instances_table = $wpdb->prefix . '360_assessment_instances';
            if ($wpdb->get_var("SHOW TABLES LIKE '$instances_table'") != $instances_table) {
                $sql = "CREATE TABLE $instances_table (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    assessment_id mediumint(9) NOT NULL,
                    assessor_id mediumint(9) NOT NULL,
                    assessee_id mediumint(9) NOT NULL,
                    status varchar(20) DEFAULT 'pending',
                    completed_at datetime DEFAULT NULL,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    UNIQUE KEY unique_assessment (assessment_id, assessor_id, assessee_id),
                    KEY assessment_id (assessment_id),
                    KEY assessor_id (assessor_id),
                    KEY assessee_id (assessee_id),
                    KEY status (status)
                ) $charset_collate;";

                dbDelta($sql);

                if ($wpdb->last_error) {
                    throw new Exception('Failed to create assessment instances table: ' . $wpdb->last_error);
                }
            }

            // Verify assessment responses table
            $responses_table = $wpdb->prefix . '360_assessment_responses';
            if ($wpdb->get_var("SHOW TABLES LIKE '$responses_table'") != $responses_table) {
                // Assessment responses table
                $responses_table = $wpdb->prefix . '360_assessment_responses';
                $sql = "CREATE TABLE IF NOT EXISTS $responses_table (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    assessment_id bigint(20) NOT NULL,
                    assessor_id bigint(20) NOT NULL,
                    assessee_id bigint(20) NOT NULL,
                    status varchar(20) NOT NULL DEFAULT 'pending',
                    completed_at datetime NULL,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY assessment_id (assessment_id),
                    KEY assessor_id (assessor_id),
                    KEY assessee_id (assessee_id),
                    UNIQUE KEY unique_response (assessment_id,assessor_id,assessee_id)
                ) $charset_collate;";

                dbDelta($sql);

                if ($wpdb->last_error) {
                    throw new Exception('Failed to create assessment responses table: ' . $wpdb->last_error);
                }
            }
            
            // User relationships table
            $relationships_table = $wpdb->prefix . '360_user_relationships';
            if ($wpdb->get_var("SHOW TABLES LIKE '$relationships_table'") != $relationships_table) {
                // Assessment relationships table
                $relationships_table = $wpdb->prefix . '360_user_relationships';
                $sql = "CREATE TABLE IF NOT EXISTS $relationships_table (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    assessor_id bigint(20) NOT NULL,
                    assessee_id bigint(20) NOT NULL,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY assessor_id (assessor_id),
                    KEY assessee_id (assessee_id),
                    UNIQUE KEY unique_relationship (assessor_id,assessee_id)
                ) $charset_collate;";

                dbDelta($sql);

                if ($wpdb->last_error) {
                    throw new Exception('Failed to create assessment relationships table: ' . $wpdb->last_error);
                }
            }

            // Update existing questions table structure if needed
            $columns = $wpdb->get_results("SHOW COLUMNS FROM $questions_table");
            $column_names = array_column($columns, 'Field');

            // Add section_id column if it doesn't exist
            if (!in_array('section_id', $column_names)) {
                $wpdb->query("ALTER TABLE $questions_table ADD COLUMN section_id mediumint(9) AFTER id");
            }

            // Add display_order column if it doesn't exist
            if (!in_array('display_order', $column_names)) {
                $wpdb->query("ALTER TABLE $questions_table ADD COLUMN display_order int DEFAULT 0 AFTER question_text");
            }
            
            // Verify sections table structure
            $sections_table = $wpdb->prefix . '360_sections';
            $sections_columns = $wpdb->get_results("SHOW COLUMNS FROM $sections_table");
            $sections_column_names = array_column($sections_columns, 'Field');

            // Add display_order column if it doesn't exist
            if (!in_array('display_order', $sections_column_names)) {
                $wpdb->query("ALTER TABLE $sections_table ADD COLUMN display_order int DEFAULT 0");
            }

            // Update indexes
            $indexes = $wpdb->get_results("SHOW INDEX FROM $questions_table");
            $index_names = array_column($indexes, 'Key_name');

            if (!in_array('section_id', $index_names)) {
                $wpdb->query("ALTER TABLE $questions_table ADD INDEX section_id (section_id)");
            }

            if (!in_array('display_order', $index_names)) {
                $wpdb->query("ALTER TABLE $questions_table ADD INDEX display_order (display_order)");
            }

            // Migrate existing questions if needed
            if (in_array('assessment_id', $column_names)) {
                // Check if migration is needed
                $needs_migration = $wpdb->get_var("SELECT COUNT(*) FROM $questions_table WHERE assessment_id IS NOT NULL");

                if ($needs_migration > 0) {
                    // Migrate questions to junction table
                    $wpdb->query("INSERT IGNORE INTO $assessment_questions_table (assessment_id, question_id)
                                 SELECT assessment_id, id FROM $questions_table WHERE assessment_id IS NOT NULL");

                    // Remove assessment_id column
                    $wpdb->query("ALTER TABLE $questions_table DROP COLUMN assessment_id");
                }
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
    
    /**
     * Get Peers group statistics
     */
    public function get_peers_group_stats() {
        global $wpdb;

        try {
            // Get total active users in Peers group
            $total_query = "SELECT COUNT(*) as total_users
                FROM {$wpdb->prefix}360_users u
                JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
                WHERE g.group_name = %s 
                AND u.status = %s";

            $total_users = (int)$wpdb->get_var(
                $wpdb->prepare(
                    $total_query,
                    'Peers',
                    'active'
                )
            );

            // Get number of users being assessed (excluding self-assessments)
            $assessable_query = "SELECT COUNT(DISTINCT u1.id) as assessable_users
                FROM {$wpdb->prefix}360_users u1
                JOIN {$wpdb->prefix}360_user_groups g ON u1.group_id = g.id
                WHERE g.group_name = %s 
                AND u1.status = %s";

            $assessable_users = (int)$wpdb->get_var(
                $wpdb->prepare(
                    $assessable_query,
                    'Peers',
                    'active'
                )
            );

            if (WP_DEBUG) {
                error_log("Peers group stats - Total users: $total_users");
                error_log("Peers group stats - Assessable users: $assessable_users");
            }

            return (object)[
                'total_users' => $total_users,
                'assessable_users' => $assessable_users
            ];

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error getting peers group stats: ' . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Get 360 user by WordPress user ID
     * 
     * @param int $wp_user_id WordPress user ID
     * @return object|null User object or null if not found
     */
    public function get_user_by_wp_id($wp_user_id) {
        global $wpdb;

        try {
            if (WP_DEBUG) {
                error_log("Getting 360 user by WordPress ID: $wp_user_id");
            }

            $query = $wpdb->prepare(
                "SELECT u.*, 
                        p.name as position_name,
                        g.group_name
                 FROM {$wpdb->prefix}360_users u
                 LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id
                 LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
                 WHERE u.wp_user_id = %d
                 AND u.status = %s
                 LIMIT 1",
                $wp_user_id,
                'active'
            );

            if (WP_DEBUG) {
                error_log('User query: ' . $query);
            }

            $user = $wpdb->get_row($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            if ($user) {
                // Ensure ID property exists for compatibility
                $user->ID = $user->id;

                if (WP_DEBUG) {
                    error_log('Found 360 user: ' . print_r($user, true));
                }
            } else {
                if (WP_DEBUG) {
                    error_log("No 360 user found for WordPress ID: $wp_user_id");
                }
            }

            return $user;

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error getting user by WordPress ID: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
            }
            return null;
        }
    }

    /**
     * Get user's assessment statistics for current assessment
     */
    public function get_user_assessment_stats($user_id) {
    global $wpdb;
    
    // Get current assessment
    $current_assessment = $this->get_current_assessment();
    if (!$current_assessment) {
        return null;
    }

    // Get table names
    $relationships_table = $wpdb->prefix . '360_user_relationships';
    $responses_table = $wpdb->prefix . '360_assessment_responses';

    // Get total assessments assigned
    $total_query = $wpdb->prepare(
        "SELECT COUNT(*) 
         FROM {$relationships_table} 
         WHERE assessor_id = %d",
        $user_id
    );
    $total_to_assess = (int)$wpdb->get_var($total_query);

    // Get completed assessments
    $completed_query = $wpdb->prepare(
        "SELECT COUNT(*) 
         FROM {$responses_table} 
         WHERE assessor_id = %d 
         AND assessment_id = %d 
         AND status = 'completed'",
        $user_id,
        $current_assessment->id
    );
    $completed = (int)$wpdb->get_var($completed_query);

    // Calculate pending and completion rate
    $pending = $total_to_assess - $completed;
    $completion_rate = $total_to_assess > 0 ? round(($completed / $total_to_assess) * 100) : 0;

    if (WP_DEBUG) {
        error_log("User Assessment Stats:");
        error_log("Total to assess: " . $total_to_assess);
        error_log("Completed: " . $completed);
        error_log("Pending: " . $pending);
        error_log("Completion rate: " . $completion_rate . "%");
    }

    return (object)[
        'total_to_assess' => $total_to_assess,
        'completed' => $completed,
        'pending' => $pending,
        'completion_rate' => $completion_rate
    ];
}

    /**
     * Get empty user stats object
     */
    private function get_empty_user_stats() {
        return (object)[
            'total_to_assess' => 0,
            'completed' => 0,
            'pending' => 0,
            'completion_rate' => 0
        ];
    }

    /**
     * Get users to be assessed by current user
     */
    public function get_users_to_assess($user_id) {
        global $wpdb;

        try {
            // Get current user's group
            $user_query = $wpdb->prepare(
                "SELECT u.*, g.group_name 
                 FROM {$wpdb->prefix}360_users u
                 JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
                 WHERE u.id = %d",
                $user_id
            );

            $current_user = $wpdb->get_row($user_query);

            if (!$current_user) {
                throw new Exception('User not found');
            }

            if (WP_DEBUG) {
                error_log("Getting users to assess for user $user_id in group: {$current_user->group_name}");
            }

            // If user is in Peers group, include self in the query
            $include_self = strtolower($current_user->group_name) === 'peers';

            $query = "SELECT DISTINCT 
                        u2.id,
                        u2.first_name,
                        u2.last_name,
                        u2.email,
                        p.name as position_name,
                        g.group_name
                    FROM {$wpdb->prefix}360_users u1
                    JOIN {$wpdb->prefix}360_user_groups g ON u1.group_id = g.id
                    JOIN {$wpdb->prefix}360_users u2 ON u2.group_id = g.id
                    LEFT JOIN {$wpdb->prefix}360_positions p ON u2.position_id = p.id
                    WHERE u1.id = %d 
                    AND u2.status = %s 
                    AND g.group_name = %s";

            if (!$include_self) {
                $query .= " AND u2.id != %d";
            }

            $query .= " ORDER BY u2.first_name, u2.last_name";

            $params = $include_self ? 
                [$user_id, 'active', 'Peers'] :
                [$user_id, 'active', 'Peers', $user_id];

            $users = $wpdb->get_results($wpdb->prepare($query, $params));

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            if (WP_DEBUG) {
                error_log("Found " . count($users) . " users to assess");
                error_log("Include self assessment: " . ($include_self ? 'Yes' : 'No'));
            }

            return $users;

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error getting users to assess: ' . $e->getMessage());
            }
            return array();
        }
    }
    
    /**
     * Check if there is an active assessment
     */
    public function has_active_assessment() {
        global $wpdb;

        try {
            $query = "SELECT * FROM {$wpdb->prefix}360_assessments 
                     WHERE status = 'active' 
                     LIMIT 1";

            if (WP_DEBUG) {
                error_log('Checking for active assessment');
            }

            $assessment = $wpdb->get_row($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            if (WP_DEBUG && $assessment) {
                error_log('Found active assessment: ' . print_r($assessment, true));
            }

            return $assessment ?: false;

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error checking active assessment: ' . $e->getMessage());
            }
            return false;
        }
    }
    
    private function validate_status_transition($current_status, $new_status) {
        $allowed_transitions = [
            'draft' => ['active'],
            'active' => ['completed'],
            'completed' => []  // No transitions allowed from completed
        ];

        if (!isset($allowed_transitions[$current_status]) || 
            !in_array($new_status, $allowed_transitions[$current_status])) {
            throw new Exception('Invalid status transition');
        }

        return true;
    }

    /**
     * Update assessment status with validation
     */
    public function update_assessment_status($id, $status) {
        global $wpdb;

        try {
            if (WP_DEBUG) {
                error_log("Attempting to update assessment $id to status: $status");
            }

            // Validate status
            $valid_statuses = ['active', 'draft', 'completed', 'inactive'];
            if (!in_array($status, $valid_statuses)) {
                throw new Exception('Invalid status. Must be one of: ' . implode(', ', $valid_statuses));
            }

            // Get current assessment
            $assessment = $this->get_assessment($id);
            if (!$assessment) {
                throw new Exception('Assessment not found');
            }

            if (WP_DEBUG) {
                error_log("Current assessment status: {$assessment->status}");
                error_log("Requested status change to: {$status}");
            }

            // Define allowed status transitions
            $allowed_transitions = [
                'draft' => ['active'],
                'active' => ['completed', 'inactive'],
                'inactive' => ['draft'],
                'completed' => [] // No transitions allowed from completed
            ];

            // Validate status transition
            if (!isset($allowed_transitions[$assessment->status]) || 
                !in_array($status, $allowed_transitions[$assessment->status])) {
                throw new Exception(sprintf(
                    'Cannot transition assessment from %s to %s status',
                    $assessment->status,
                    $status
                ));
            }

            // Additional validations based on status
            if ($status === 'active') {
                // Check for other active assessments
                $active_assessment = $this->has_active_assessment();
                if ($active_assessment && $active_assessment->id !== $id) {
                    throw new Exception('Another assessment is currently active');
                }

                // Validate dates
                $today = date('Y-m-d');
                if ($assessment->end_date < $today) {
                    throw new Exception('Cannot activate assessment that has already ended');
                }
            }

            // Update status
            $result = $wpdb->update(
                $wpdb->prefix . '360_assessments',
                ['status' => $status],
                ['id' => $id],
                ['%s'],
                ['%d']
            );

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            if ($result === false) {
                throw new Exception('Failed to update assessment status');
            }

            // Log the successful status change
            if (WP_DEBUG) {
                error_log("Successfully updated assessment $id status from {$assessment->status} to $status");
            }

            return true;

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error updating assessment status: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
            }
            return new WP_Error('update_failed', $e->getMessage());
        }
    }

    /**
     * Get assessment progress
     */
    public function get_assessment_progress($assessment_id) {
        global $wpdb;

        try {
            // Get total users in Peers group (excluding the user being assessed)
            $total_query = "SELECT COUNT(DISTINCT u1.id) as total_peers
                 FROM {$wpdb->prefix}360_users u1
                 JOIN {$wpdb->prefix}360_user_groups g ON u1.group_id = g.id
                 WHERE g.group_name = %s 
                 AND u1.status = %s";

            $total_users = (int)$wpdb->get_var(
                $wpdb->prepare(
                    $total_query,
                    'Peers',
                    'active'
                )
            );

            // Get completed assessments count
            $completed_query = "SELECT COUNT(DISTINCT ai.assessor_id) 
                 FROM {$wpdb->prefix}360_assessment_instances ai
                 JOIN {$wpdb->prefix}360_users u ON ai.assessor_id = u.id
                 JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
                 WHERE ai.assessment_id = %d 
                 AND ai.status = %s
                 AND g.group_name = %s";

            $completed_count = (int)$wpdb->get_var(
                $wpdb->prepare(
                    $completed_query,
                    $assessment_id,
                    'completed',
                    'Peers'
                )
            );

            if (WP_DEBUG) {
                error_log("Assessment Progress - Total Users: $total_users");
                error_log("Assessment Progress - Completed Count: $completed_count");
            }

            return (object)[
                'total' => $total_users,
                'completed' => $completed_count,
                'percentage' => $total_users > 0 ? round(($completed_count / $total_users) * 100) : 0
            ];

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error getting assessment progress: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
            }
            return null;
        }
    }
    /**
     * Delete assessment with validation
     */
    public function delete_assessment($id) {
        global $wpdb;

        $wpdb->query('SET autocommit=0');
        $wpdb->query('START TRANSACTION');
        try {
            // Get assessment
            $assessment = $this->get_assessment($id);
            if (!$assessment) {
                $wpdb->query('ROLLBACK');
                if (WP_DEBUG) error_log("Assessment not found for deletion: ID $id");
                return new WP_Error('assessment_not_found', 'Assessment not found');
            }

            // Validate deletion
            if ($assessment->status === 'completed') {
                $wpdb->query('ROLLBACK');
                if (WP_DEBUG) error_log("Attempt to delete completed assessment: ID $id");
                return new WP_Error('cannot_delete_completed', 'Completed assessments cannot be deleted');
            }
            if ($assessment->status === 'active') {
                $wpdb->query('ROLLBACK');
                if (WP_DEBUG) error_log("Attempt to delete active assessment: ID $id");
                return new WP_Error('cannot_delete_active', 'Active assessments cannot be deleted');
            }

            if (WP_DEBUG) error_log("Starting deletion of assessment ID: $id");

            // Get all assessment instances for this assessment
            $instance_ids = $wpdb->get_col(
                $wpdb->prepare("SELECT id FROM {$wpdb->prefix}360_assessment_instances WHERE assessment_id = %d", $id)
            );

            if (WP_DEBUG) error_log("Found " . count($instance_ids) . " assessment instances to delete");

            // Delete responses using instance IDs
            if (!empty($instance_ids)) {
                $instance_ids_string = implode(',', array_map('intval', $instance_ids));
                $wpdb->query(
                    "DELETE FROM {$wpdb->prefix}360_assessment_responses 
                     WHERE assessment_instance_id IN ($instance_ids_string)"
                );
                if ($wpdb->last_error) {
                    $wpdb->query('ROLLBACK');
                    if (WP_DEBUG) error_log("Error deleting responses: " . $wpdb->last_error);
                    return new WP_Error('delete_failed', "Error deleting responses: " . $wpdb->last_error);
                }
                if (WP_DEBUG) error_log("Deleted responses for instances: $instance_ids_string");
            }

            // Delete assessment instances
            $wpdb->delete(
                $wpdb->prefix . '360_assessment_instances',
                ['assessment_id' => $id],
                ['%d']
            );
            if ($wpdb->last_error) {
                $wpdb->query('ROLLBACK');
                if (WP_DEBUG) error_log("Error deleting assessment instances: " . $wpdb->last_error);
                return new WP_Error('delete_failed', "Error deleting assessment instances: " . $wpdb->last_error);
            }
            if (WP_DEBUG) error_log("Deleted assessment instances for assessment ID: $id");

            // Delete assessment
            $result = $wpdb->delete(
                $wpdb->prefix . '360_assessments',
                ['id' => $id],
                ['%d']
            );
            if ($wpdb->last_error) {
                $wpdb->query('ROLLBACK');
                if (WP_DEBUG) error_log("Error deleting assessment: " . $wpdb->last_error);
                return new WP_Error('delete_failed', 'Error deleting assessment: ' . $wpdb->last_error);
            }
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                if (WP_DEBUG) error_log("Database delete returned false for assessment ID: $id");
                return new WP_Error('delete_failed', 'Database delete failed.');
            }
            if (WP_DEBUG) error_log("Successfully deleted assessment ID: $id");

            $wpdb->query('COMMIT');
            $wpdb->query('SET autocommit=1');
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            $wpdb->query('SET autocommit=1');
            if (WP_DEBUG) {
                error_log('Exception in delete_assessment: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
            }
            return new WP_Error('delete_failed', $e->getMessage());
        }
    }

    /**
     * Get current active assessment
     */
    public function get_current_assessment() {
        global $wpdb;

        try {
            if (WP_DEBUG) {
                error_log('Getting current active assessment');
            }

            $query = "SELECT * 
                     FROM {$wpdb->prefix}360_assessments 
                     WHERE status = %s 
                     ORDER BY created_at DESC 
                     LIMIT 1";

            $assessment = $wpdb->get_row($wpdb->prepare($query, 'active'));

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            if (WP_DEBUG && $assessment) {
                error_log('Found active assessment: ' . print_r($assessment, true));
            }

            return $assessment;

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error getting current assessment: ' . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Debug assessment status
     */
    public function debug_assessment_status() {
        global $wpdb;

        try {
            $query = "SELECT id, name, status, created_at 
                     FROM {$wpdb->prefix}360_assessments 
                     ORDER BY created_at DESC";

            $assessments = $wpdb->get_results($query);

            if (WP_DEBUG) {
                error_log('All assessments status:');
                error_log(print_r($assessments, true));
            }

            return $assessments;

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error debugging assessment status: ' . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Fix assessment status
     */
    public function fix_assessment_status($assessment_id) {
        global $wpdb;

        try {
            $result = $wpdb->update(
                $wpdb->prefix . '360_assessments',
                ['status' => 'active'],
                ['id' => $assessment_id],
                ['%s'],
                ['%d']
            );

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            if (WP_DEBUG) {
                error_log("Fixed status for assessment ID: $assessment_id");
            }

            return true;

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error fixing assessment status: ' . $e->getMessage());
            }
            return false;
        }
    }

    
    /**
     * Get count of active assessments
     */
    public function get_active_assessments_count() {
        global $wpdb;
        
        try {
            $query = "SELECT COUNT(*) FROM {$wpdb->prefix}360_assessments 
                     WHERE status = 'active' 
                     AND start_date <= CURRENT_DATE 
                     AND end_date >= CURRENT_DATE";

            if (WP_DEBUG) {
                error_log('Getting active assessments count query: ' . $query);
            }

            $count = (int)$wpdb->get_var($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            return $count;

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error getting active assessments count: ' . $e->getMessage());
            }
            return 0;
        }
    }

    /**
     * Get all assessments with optional filters
     */
    public function get_all_assessments($args = array()) {
        global $wpdb;

        try {
            $defaults = array(
                'status' => '',
                'orderby' => 'created_at',
                'order' => 'DESC',
                'limit' => 0,
                'offset' => 0
            );

            $args = wp_parse_args($args, $defaults);

            $where = array('1=1');
            $values = array();

            if (!empty($args['status'])) {
                $where[] = 'a.status = %s';
                $values[] = $args['status'];
            }

            $query = "SELECT 
                        a.*,
                        COUNT(DISTINCT ai.id) as total_instances,
                        COUNT(DISTINCT CASE WHEN ai.status = 'completed' THEN ai.id END) as completed_instances,
                        COUNT(DISTINCT ai.assessee_id) as total_participants
                     FROM {$wpdb->prefix}360_assessments a
                     LEFT JOIN {$wpdb->prefix}360_assessment_instances ai ON a.id = ai.assessment_id
                     WHERE " . implode(' AND ', $where) . "
                     GROUP BY a.id
                     ORDER BY a." . esc_sql($args['orderby']) . " " . esc_sql($args['order']);

            if ($args['limit'] > 0) {
                $query .= " LIMIT %d";
                $values[] = $args['limit'];

                if ($args['offset'] > 0) {
                    $query .= " OFFSET %d";
                    $values[] = $args['offset'];
                }
            }

            if (!empty($values)) {
                $query = $wpdb->prepare($query, $values);
            }

            if (WP_DEBUG) {
                //error_log('Getting all assessments query: ' . $query);
            }

            $assessments = $wpdb->get_results($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            // Ensure properties exist for all assessments
            foreach ($assessments as $assessment) {
                $assessment->total_instances = $assessment->total_instances ?? 0;
                $assessment->completed_instances = $assessment->completed_instances ?? 0;
                $assessment->total_participants = $assessment->total_participants ?? 0;
                $assessment->completion_rate = $this->calculate_completion_rate(
                    $assessment->completed_instances,
                    $assessment->total_instances
                );
            }

            return $assessments;

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error getting all assessments: ' . $e->getMessage());
            }
            return array();
        }
    }

    /**
     * Get assessment questions count
     */
    private function get_assessment_questions_count($assessment_id) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}360_questions WHERE assessment_id = %d",
            $assessment_id
        );
        
        return (int)$wpdb->get_var($query);
    }

    /**
     * Get assessment completion rate
     */
    private function get_assessment_completion_rate($assessment_id) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT CASE WHEN status = 'completed' THEN id END) * 100.0 / COUNT(*) 
             FROM {$wpdb->prefix}360_assessment_instances 
             WHERE assessment_id = %d",
            $assessment_id
        );
        
        $rate = $wpdb->get_var($query);
        return round($rate ?? 0, 2);
    }

    /**
     * Get assessment participants count
     */
    private function get_assessment_participants_count($assessment_id) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT assessee_id) 
             FROM {$wpdb->prefix}360_assessment_instances 
             WHERE assessment_id = %d",
            $assessment_id
        );
        
        return (int)$wpdb->get_var($query);
    }

    /**
     * Get assessment results
     */
    public function get_assessment_results($assessment_id, $user_id) {
        global $wpdb;

        try {
            // First, get all questions for this assessment
            $query = $wpdb->prepare(
                "SELECT DISTINCT
                    q.*,
                    ar.rating,
                    ar.comment,
                    COALESCE(s.name, 'General') as section_name,
                    COALESCE(s.id, 0) as section_id
                 FROM {$wpdb->prefix}360_assessment_instances ai
                 JOIN {$wpdb->prefix}360_questions q 
                    ON q.id IN (
                        SELECT question_id 
                        FROM {$wpdb->prefix}360_assessment_responses 
                        WHERE assessment_instance_id = ai.id
                    )
                 LEFT JOIN {$wpdb->prefix}360_sections s 
                    ON q.section_id = s.id
                 LEFT JOIN {$wpdb->prefix}360_assessment_responses ar 
                    ON ar.assessment_instance_id = ai.id 
                    AND ar.question_id = q.id
                 WHERE ai.assessment_id = %d 
                 AND ai.assessee_id = %d
                 ORDER BY s.id, q.id",
                $assessment_id,
                $user_id
            );

            if (WP_DEBUG) {
                error_log('Assessment results query: ' . $query);
            }

            $rows = $wpdb->get_results($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            // If no results found, try to get all questions for this assessment
            if (empty($rows)) {
                $query = $wpdb->prepare(
                    "SELECT DISTINCT
                        q.*,
                        NULL as rating,
                        NULL as comment,
                        COALESCE(s.name, 'General') as section_name,
                        COALESCE(s.id, 0) as section_id
                     FROM {$wpdb->prefix}360_assessment_responses ar
                     JOIN {$wpdb->prefix}360_assessment_instances ai 
                        ON ar.assessment_instance_id = ai.id
                     JOIN {$wpdb->prefix}360_questions q 
                        ON ar.question_id = q.id
                     LEFT JOIN {$wpdb->prefix}360_sections s 
                        ON q.section_id = s.id
                     WHERE ai.assessment_id = %d
                     GROUP BY q.id
                     ORDER BY s.id, q.id",
                    $assessment_id
                );

                if (WP_DEBUG) {
                    error_log('Fallback query: ' . $query);
                }

                $rows = $wpdb->get_results($query);

                if ($wpdb->last_error) {
                    throw new Exception('Database error in fallback query: ' . $wpdb->last_error);
                }
            }

            // Organize results by section
            $results = [];
            foreach ($rows as $row) {
                $section_id = $row->section_id ?? 0;

                // Initialize section if not exists
                if (!isset($results[$section_id])) {
                    $results[$section_id] = (object)[
                        'name' => $row->section_name ?? 'General',
                        'questions' => [],
                        'total_ratings' => 0,
                        'rating_sum' => 0
                    ];
                }

                // Add question if not already added
                $question_id = $row->id;
                if (!isset($results[$section_id]->questions[$question_id])) {
                    $results[$section_id]->questions[$question_id] = (object)[
                        'id' => $question_id,
                        'text' => $row->question_text,
                        'ratings' => [],
                        'comments' => []
                    ];
                }

                // Add rating and comment if they exist
                if (isset($row->rating) && $row->rating > 0) {
                    $results[$section_id]->questions[$question_id]->ratings[] = $row->rating;
                    $results[$section_id]->total_ratings++;
                    $results[$section_id]->rating_sum += $row->rating;
                }
                if (!empty($row->comment)) {
                    $results[$section_id]->questions[$question_id]->comments[] = $row->comment;
                }
            }

            // Calculate averages and format results
            foreach ($results as $section) {
                // Calculate section average
                $section->average_rating = $section->total_ratings > 0 
                    ? round($section->rating_sum / $section->total_ratings, 2) 
                    : 0;

                // Calculate question averages and format
                foreach ($section->questions as $question) {
                    $question->average_rating = !empty($question->ratings) 
                        ? round(array_sum($question->ratings) / count($question->ratings), 2) 
                        : 0;

                    // Get rating distribution
                    $question->rating_distribution = array_fill(1, 5, 0);
                    foreach ($question->ratings as $rating) {
                        if ($rating >= 1 && $rating <= 5) {
                            $question->rating_distribution[$rating]++;
                        }
                    }
                }

                // Convert questions array to numeric array
                $section->questions = array_values((array)$section->questions);
            }

            if (WP_DEBUG) {
                error_log('Processed results count: ' . count($results));
                if (empty($results)) {
                    error_log('No results found for assessment_id: ' . $assessment_id . ' and user_id: ' . $user_id);
                }
            }

            return array_values($results);

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error getting assessment results: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
            }
            return array();
        }
    }
    
    /**
     * Migrate questions from old structure to new junction table
     */
    private function migrate_assessment_questions($assessment_id) {
        global $wpdb;

        try {
            // Check if old column exists
            $columns = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}360_questions");
            $has_old_column = false;
            foreach ($columns as $column) {
                if ($column->Field === 'assessment_id') {
                    $has_old_column = true;
                    break;
                }
            }

            if (!$has_old_column) {
                return;
            }

            if (WP_DEBUG) {
                error_log("Migrating questions for assessment $assessment_id");
            }

            // Get questions with old structure
            $questions = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}360_questions 
                 WHERE assessment_id = %d",
                $assessment_id
            ));

            if (!empty($questions)) {
                foreach ($questions as $question) {
                    // Insert into junction table
                    $wpdb->insert(
                        $wpdb->prefix . '360_assessment_questions',
                        array(
                            'assessment_id' => $assessment_id,
                            'question_id' => $question->id,
                            'created_at' => current_time('mysql')
                        ),
                        array('%d', '%d', '%s')
                    );
                }

                if (WP_DEBUG) {
                    error_log("Migrated " . count($questions) . " questions");
                }
            }

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error migrating questions: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Get overall completion rate across all assessments
     */
    public function get_overall_completion_rate() {
        global $wpdb;

        try {
            $query = "SELECT 
                        COUNT(DISTINCT CASE WHEN status = 'completed' THEN id END) * 100.0 / NULLIF(COUNT(*), 0) as completion_rate
                     FROM {$wpdb->prefix}360_assessment_instances
                     WHERE assessment_id IN (
                         SELECT id 
                         FROM {$wpdb->prefix}360_assessments 
                         WHERE status = 'active'
                     )";

            if (WP_DEBUG) {
                error_log('Getting overall completion rate query: ' . $query);
            }

            $rate = $wpdb->get_var($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            return round($rate ?? 0, 2);

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error getting overall completion rate: ' . $e->getMessage());
            }
            return 0;
        }
    }

    /**
     * Get assessment statistics
     */
    public function get_assessment_stats($assessment_id) {
        global $wpdb;

        try {
            $query = $wpdb->prepare(
                "SELECT 
                    a.*,
                    COUNT(DISTINCT ai.id) as total_instances,
                    COUNT(DISTINCT CASE WHEN ai.status = 'completed' THEN ai.id END) as completed_instances,
                    COUNT(DISTINCT ai.assessee_id) as total_participants,
                    COUNT(DISTINCT ai.assessor_id) as total_assessors,
                    AVG(ar.rating) as average_rating
                 FROM {$wpdb->prefix}360_assessments a
                 LEFT JOIN {$wpdb->prefix}360_assessment_instances ai ON a.id = ai.assessment_id
                 LEFT JOIN {$wpdb->prefix}360_assessment_responses ar ON ai.id = ar.assessment_instance_id
                 WHERE a.id = %d
                 GROUP BY a.id",
                $assessment_id
            );

            if (WP_DEBUG) {
                //error_log('Getting assessment stats query: ' . $query);
            }

            $stats = $wpdb->get_row($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            if ($stats) {
                // Ensure these properties exist even if null
                $stats->total_instances = $stats->total_instances ?? 0;
                $stats->completed_instances = $stats->completed_instances ?? 0;
                $stats->total_participants = $stats->total_participants ?? 0;
                $stats->total_assessors = $stats->total_assessors ?? 0;
                $stats->average_rating = round($stats->average_rating ?? 0, 2);

                // Calculate completion rate
                $stats->completion_rate = $this->calculate_completion_rate(
                    $stats->completed_instances,
                    $stats->total_instances
                );
            }

            return $stats;

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error getting assessment stats: ' . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Get current assessment (most recent active assessment)
     */
//    public function get_current_assessment() {
//        global $wpdb;
//
//        try {
//            $query = "SELECT a.*, 
//                        (SELECT COUNT(*) FROM {$wpdb->prefix}360_assessment_instances 
//                         WHERE assessment_id = a.id) as total_instances,
//                        (SELECT COUNT(*) FROM {$wpdb->prefix}360_assessment_instances 
//                         WHERE assessment_id = a.id AND status = 'completed') as completed_instances
//                     FROM {$wpdb->prefix}360_assessments a
//                     WHERE a.status = 'active' 
//                     AND a.start_date <= CURRENT_DATE 
//                     AND a.end_date >= CURRENT_DATE 
//                     ORDER BY a.created_at DESC 
//                     LIMIT 1";
//
//            if (WP_DEBUG) {
//                error_log('Getting current assessment query: ' . $query);
//            }
//
//            $assessment = $wpdb->get_row($query);
//
//            if ($wpdb->last_error) {
//                throw new Exception('Database error: ' . $wpdb->last_error);
//            }
//
//            if ($assessment) {
//                // Add additional statistics
//                $assessment->completion_rate = $this->calculate_completion_rate(
//                    $assessment->completed_instances,
//                    $assessment->total_instances
//                );
//
//                // Ensure these properties exist even if null
//                $assessment->total_instances = $assessment->total_instances ?? 0;
//                $assessment->completed_instances = $assessment->completed_instances ?? 0;
//            }
//
//            return $assessment;
//
//        } catch (Exception $e) {
//            if (WP_DEBUG) {
//                error_log('Error getting current assessment: ' . $e->getMessage());
//            }
//            return null;
//        }
//    }
    
    /**
     * Calculate completion rate
     */
    private function calculate_completion_rate($completed, $total) {
        if (!$total) return 0;
        return round(($completed / $total) * 100, 2);
    }

    /**
     * Get assessment users with their results
     */
    public function get_assessment_users($assessment_id) {
        global $wpdb;

        try {
            $query = $wpdb->prepare(
                "SELECT 
                    u.id,
                    u.first_name,
                    u.last_name,
                    u.email,
                    p.name as position_name,
                    g.group_name,
                    COUNT(DISTINCT ai.assessor_id) as total_assessors,
                    COUNT(DISTINCT CASE WHEN ai.status = 'completed' THEN ai.assessor_id END) as completed_assessments,
                    AVG(ar.rating) as average_rating,
                    MIN(ar.rating) as min_rating,
                    MAX(ar.rating) as max_rating,
                    COUNT(DISTINCT ar.id) as total_responses
                FROM {$wpdb->prefix}360_assessment_instances ai
                JOIN {$wpdb->prefix}360_users u ON ai.assessee_id = u.id
                LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id
                LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
                LEFT JOIN {$wpdb->prefix}360_assessment_responses ar ON ai.id = ar.assessment_instance_id
                WHERE ai.assessment_id = %d
                GROUP BY u.id, u.first_name, u.last_name, u.email, p.name, g.group_name
                ORDER BY u.first_name, u.last_name",
                $assessment_id
            );

            if (WP_DEBUG) {
                error_log('Getting assessment users query: ' . $query);
            }

            $users = $wpdb->get_results($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            // Process and enhance the results
            foreach ($users as $user) {
                // Ensure properties exist
                $user->total_assessors = $user->total_assessors ?? 0;
                $user->completed_assessments = $user->completed_assessments ?? 0;
                $user->total_responses = $user->total_responses ?? 0;

                // Calculate completion rate
                $user->completion_rate = $this->calculate_completion_rate(
                    $user->completed_assessments,
                    $user->total_assessors
                );

                // Format ratings
                $user->average_rating = $user->average_rating ? round($user->average_rating, 2) : 0;
                $user->min_rating = $user->min_rating ?? 0;
                $user->max_rating = $user->max_rating ?? 0;

                // Get detailed ratings distribution
                $ratings_query = $wpdb->prepare(
                    "SELECT 
                        ar.rating,
                        COUNT(*) as count
                    FROM {$wpdb->prefix}360_assessment_instances ai
                    JOIN {$wpdb->prefix}360_assessment_responses ar ON ai.id = ar.assessment_instance_id
                    WHERE ai.assessment_id = %d 
                    AND ai.assessee_id = %d
                    GROUP BY ar.rating
                    ORDER BY ar.rating",
                    $assessment_id,
                    $user->id
                );

                $ratings_distribution = $wpdb->get_results($ratings_query);

                // Format ratings distribution
                $user->ratings_distribution = array_fill(1, 5, 0); // Initialize with 0 for ratings 1-5
                foreach ($ratings_distribution as $rating) {
                    $user->ratings_distribution[$rating->rating] = (int)$rating->count;
                }

                // Get comments
                $comments_query = $wpdb->prepare(
                    "SELECT ar.comment, u2.first_name, u2.last_name
                    FROM {$wpdb->prefix}360_assessment_instances ai
                    JOIN {$wpdb->prefix}360_assessment_responses ar ON ai.id = ar.assessment_instance_id
                    JOIN {$wpdb->prefix}360_users u2 ON ai.assessor_id = u2.id
                    WHERE ai.assessment_id = %d 
                    AND ai.assessee_id = %d
                    AND ar.comment IS NOT NULL
                    AND ar.comment != ''",
                    $assessment_id,
                    $user->id
                );

                $user->comments = $wpdb->get_results($comments_query);
            }

            return $users;

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error getting assessment users: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
            }
            return array();
        }
    }
    
    /**
     * Get user assessment details
     */
    public function get_user_assessment_details($assessment_id, $user_id) {
        global $wpdb;

        try {
            // Get basic user info with assessment stats
            $query = $wpdb->prepare(
                "SELECT 
                    u.*,
                    p.name as position_name,
                    g.group_name,
                    COUNT(DISTINCT ai.assessor_id) as total_assessors,
                    COUNT(DISTINCT CASE WHEN ai.status = 'completed' THEN ai.assessor_id END) as completed_assessments,
                    AVG(ar.rating) as average_rating
                FROM {$wpdb->prefix}360_users u
                LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id
                LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
                LEFT JOIN {$wpdb->prefix}360_assessment_instances ai ON u.id = ai.assessee_id
                LEFT JOIN {$wpdb->prefix}360_assessment_responses ar ON ai.id = ar.assessment_instance_id
                WHERE u.id = %d
                AND ai.assessment_id = %d
                GROUP BY u.id",
                $user_id,
                $assessment_id
            );

            $user = $wpdb->get_row($query);

            if (!$user) {
                throw new Exception('User not found');
            }

            // Get responses by question
            $responses_query = $wpdb->prepare(
                "SELECT 
                    q.id as question_id,
                    q.question_text,
                    COUNT(ar.id) as total_responses,
                    AVG(ar.rating) as average_rating,
                    MIN(ar.rating) as min_rating,
                    MAX(ar.rating) as max_rating
                FROM {$wpdb->prefix}360_questions q
                LEFT JOIN {$wpdb->prefix}360_assessment_instances ai 
                    ON ai.assessment_id = %d AND ai.assessee_id = %d
                LEFT JOIN {$wpdb->prefix}360_assessment_responses ar 
                    ON ai.id = ar.assessment_instance_id AND ar.question_id = q.id
                WHERE q.assessment_id = %d
                GROUP BY q.id
                ORDER BY q.question_order",
                $assessment_id,
                $user_id,
                $assessment_id
            );

            $user->questions = $wpdb->get_results($responses_query);

            // Format the results
            $user->completion_rate = $this->calculate_completion_rate(
                $user->completed_assessments,
                $user->total_assessors
            );

            $user->average_rating = round($user->average_rating ?? 0, 2);

            foreach ($user->questions as $question) {
                $question->average_rating = round($question->average_rating ?? 0, 2);
                $question->min_rating = $question->min_rating ?? 0;
                $question->max_rating = $question->max_rating ?? 0;
            }

            return $user;

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error getting user assessment details: ' . $e->getMessage());
            }
            return null;
        }
    }
    
    public function update_assessment($id, $data) {
        global $wpdb;

        try {
            if (WP_DEBUG) {
                error_log("Updating assessment $id with data: " . print_r($data, true));
            }

            // Remove updated_at from the data array since the column doesn't exist
            $update_data = array(
                'name' => $data['name'],
                'description' => $data['description'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date']
            );

            $result = $wpdb->update(
                $wpdb->prefix . '360_assessments',
                $update_data,
                array('id' => $id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            if ($result === false) {
                throw new Exception('Failed to update assessment');
            }

            if (WP_DEBUG) {
                error_log("Successfully updated assessment $id");
            }

            return true;

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error updating assessment: ' . $e->getMessage());
            }
            return new WP_Error('update_failed', $e->getMessage());
        }
    }
    
    public function create_assessment($data) {
        global $wpdb;

        try {
            if (WP_DEBUG) {
                error_log('Creating assessment with data: ' . print_r($data, true));
            }

            // Remove updated_at and set only the required fields
            $insert_data = array(
                'name' => $data['name'],
                'description' => $data['description'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => 'draft',
                'created_at' => current_time('mysql')
            );

            $result = $wpdb->insert(
                $wpdb->prefix . '360_assessments',
                $insert_data,
                array('%s', '%s', '%s', '%s', '%s', '%s')
            );

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            if ($result === false) {
                throw new Exception('Failed to create assessment');
            }

            $new_id = $wpdb->insert_id;

            if (WP_DEBUG) {
                error_log("Successfully created assessment with ID: $new_id");
            }

            return $new_id;

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error creating assessment: ' . $e->getMessage());
            }
            return new WP_Error('create_failed', $e->getMessage());
        }
    }
    
    /**
     * Update assessment status
     * 
     * @param int $id Assessment ID
     * @param string $status New status ('active', 'inactive', 'draft', 'completed')
     * @return bool|WP_Error True on success, WP_Error on failure
     */
//    public function update_assessment_status($id, $status) {
//        global $wpdb;
//
//        try {
//            if (WP_DEBUG) {
//                error_log("Updating assessment status: ID = $id, Status = $status");
//            }
//
//            // Validate status
//            $valid_statuses = ['active', 'inactive', 'draft', 'completed'];
//            if (!in_array($status, $valid_statuses)) {
//                throw new Exception('Invalid status. Must be one of: ' . implode(', ', $valid_statuses));
//            }
//
//            // Get current assessment
//            $assessment = $this->get_assessment($id);
//            if (!$assessment) {
//                throw new Exception('Assessment not found');
//            }
//
//            // Check if status is actually changing
//            if ($assessment->status === $status) {
//                if (WP_DEBUG) {
//                    error_log("Assessment $id status is already $status");
//                }
//                return true;
//            }
//
//            // If activating, check for date validity
//            if ($status === 'active') {
//                $today = date('Y-m-d');
//                $end_date = $assessment->end_date;
//
//                if ($end_date < $today) {
//                    throw new Exception('Cannot activate assessment that has already ended');
//                }
//            }
//
//            // Update status - removed updated_at from the update
//            $result = $wpdb->update(
//                $wpdb->prefix . '360_assessments',
//                ['status' => $status],
//                ['id' => $id],
//                ['%s'],
//                ['%d']
//            );
//
//            if ($wpdb->last_error) {
//                throw new Exception('Database error: ' . $wpdb->last_error);
//            }
//
//            if ($result === false) {
//                throw new Exception('Failed to update assessment status');
//            }
//
//            // Log the status change
//            if (WP_DEBUG) {
//                error_log("Successfully updated assessment $id status to $status");
//            }
//
//            return true;
//
//        } catch (Exception $e) {
//            if (WP_DEBUG) {
//                error_log('Error updating assessment status: ' . $e->getMessage());
//                error_log('Stack trace: ' . $e->getTraceAsString());
//            }
//            return new WP_Error('update_failed', $e->getMessage());
//        }
//    }
    
//    public function delete_assessment($id) {
//        global $wpdb;
//
//        try {
//            if (WP_DEBUG) {
//                error_log("Deleting assessment $id");
//            }
//
//            // Start transaction
//            $wpdb->query('START TRANSACTION');
//
//            // Delete responses
//            $wpdb->delete(
//                $wpdb->prefix . '360_assessment_responses',
//                ['assessment_instance_id' => $wpdb->get_col($wpdb->prepare(
//                    "SELECT id FROM {$wpdb->prefix}360_assessment_instances WHERE assessment_id = %d",
//                    $id
//                ))],
//                ['%d']
//            );
//
//            // Delete instances
//            $wpdb->delete(
//                $wpdb->prefix . '360_assessment_instances',
//                ['assessment_id' => $id],
//                ['%d']
//            );
//
//            // Delete questions
//            $wpdb->delete(
//                $wpdb->prefix . '360_questions',
//                ['assessment_id' => $id],
//                ['%d']
//            );
//
//            // Delete assessment
//            $result = $wpdb->delete(
//                $wpdb->prefix . '360_assessments',
//                ['id' => $id],
//                ['%d']
//            );
//
//            if ($wpdb->last_error) {
//                throw new Exception('Database error: ' . $wpdb->last_error);
//            }
//
//            if ($result === false) {
//                throw new Exception('Failed to delete assessment');
//            }
//
//            $wpdb->query('COMMIT');
//            return true;
//
//        } catch (Exception $e) {
//            $wpdb->query('ROLLBACK');
//
//            if (WP_DEBUG) {
//                error_log('Error deleting assessment: ' . $e->getMessage());
//            }
//            return new WP_Error('delete_failed', $e->getMessage());
//        }
//    }

    public function get_assessment_completion_stats($assessment_id) {
        global $wpdb;

        try {
            $query = $wpdb->prepare(
                "SELECT 
                    COUNT(DISTINCT ai.assessee_id) as total_users,
                    COUNT(DISTINCT CASE WHEN ai.status = 'completed' THEN ai.assessee_id END) as completed_users
                 FROM {$wpdb->prefix}360_assessment_instances ai
                 WHERE ai.assessment_id = %d",
                $assessment_id
            );

            if (WP_DEBUG) {
                //error_log('Getting assessment completion stats query: ' . $query);
            }

            $stats = $wpdb->get_row($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            $total = (int)$stats->total_users;
            $completed = (int)$stats->completed_users;

            return (object)[
                'total_users' => $total,
                'completed_users' => $completed,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0
            ];

        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error getting assessment completion stats: ' . $e->getMessage());
            }
            return null;
        }
    }
    
}