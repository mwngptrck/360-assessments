<?php
class Assessment_360_Assessment_Manager {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

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
    
    public function get_user_assessment_status($user_id, $assessment_id) {
        global $wpdb;

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

        $results = $wpdb->get_row($query);

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

        $results = $wpdb->get_row($query);

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

        // Validate required data
        $required_fields = ['assessment_id', 'assessor_id', 'assessee_id', 'responses'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                return new WP_Error('missing_field', "Missing required field: {$field}");
            }
        }

        $responses_table = $wpdb->prefix . '360_assessment_responses';

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Delete any existing responses for this assessment
            $wpdb->delete(
                $responses_table,
                [
                    'assessment_id' => $data['assessment_id'],
                    'assessor_id' => $data['assessor_id'],
                    'assessee_id' => $data['assessee_id']
                ],
                ['%d', '%d', '%d']
            );

            // Insert new responses
            foreach ($data['responses'] as $question_id => $response) {
                $result = $wpdb->insert(
                    $responses_table,
                    [
                        'assessment_id' => $data['assessment_id'],
                        'assessor_id' => $data['assessor_id'],
                        'assessee_id' => $data['assessee_id'],
                        'question_id' => $question_id,
                        'rating' => $response['rating'],
                        'comment' => $response['comment'],
                        'status' => 'completed',
                        'completed_at' => current_time('mysql'),
                        'created_at' => current_time('mysql')
                    ],
                    ['%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s']
                );

                if ($result === false) {
                    throw new Exception($wpdb->last_error);
                }
            }

            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');

            return new WP_Error('save_failed', 'Failed to save assessment: ' . $e->getMessage());
        }
    }

    public function get_assessment_status($assessor_id, $assessee_id, $assessment_id) {
        global $wpdb;

        if (!$assessment_id) {
            return 'not_started';
        }

        $query = $wpdb->prepare("
            SELECT status 
            FROM {$wpdb->prefix}360_assessment_responses 
            WHERE assessor_id = %d 
            AND assessee_id = %d 
            AND assessment_id = %d 
            LIMIT 1",
            $assessor_id,
            $assessee_id,
            $assessment_id
        );

        $status = $wpdb->get_var($query);

        return $status ?: 'pending';
    }
    
    public function get_assessor_progress($assessor_id, $assessment_id) {
        global $wpdb;

        $query = $wpdb->prepare("
            SELECT 
                COUNT(DISTINCT ar.assessee_id) as completed,
                (
                    SELECT COUNT(DISTINCT assessee_id) 
                    FROM {$wpdb->prefix}360_user_relationships 
                    WHERE assessor_id = %d
                ) as total
            FROM {$wpdb->prefix}360_assessment_responses ar
            WHERE ar.assessor_id = %d 
            AND ar.assessment_id = %d 
            AND ar.status = 'completed'",
            $assessor_id,
            $assessor_id,
            $assessment_id
        );

        $stats = $wpdb->get_row($query);

        return (object)[
            'completed' => (int)$stats->completed,
            'total' => (int)$stats->total,
            'pending' => (int)$stats->total - (int)$stats->completed,
            'percentage' => $stats->total > 0 ? 
                round(($stats->completed / $stats->total) * 100) : 0
        ];
    }
    
    public function get_current_assessment_id() {
        $assessment = $this->get_current_assessment();
        return $assessment ? $assessment->id : null;
    }
    
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
                        'assessment_instance_id' => $instance_id, 
                        'question_id' => $question_id,
                        'rating'      => $rating,
                        'comment'     => $comment,
                        'created_at'  => current_time('mysql')
                    ),
                    array('%d', '%d', '%d', '%s', '%s')
                );
                if ($result === false) {
                    //throw new Exception('Failed to save response');
                     $message = 'Failed to save response';
                }
            }

            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');

            return new WP_Error('save_failed', $e->getMessage());
        }
    }

    public function is_assessment_completed($assessor_id, $assessee_id, $assessment_id = null) {
        global $wpdb;

        if (!$assessment_id) {
            // Try to get current active assessment if none provided
            $current_assessment = $this->get_current_assessment();
            $assessment_id = $current_assessment ? $current_assessment->id : null;

            if (!$assessment_id) {
                return false;
            }
        }

        $query = $wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->prefix}360_assessment_responses 
             WHERE assessor_id = %d 
             AND assessee_id = %d 
             AND assessment_id = %d 
             AND status = 'completed'",
            $assessor_id,
            $assessee_id,
            $assessment_id
        );

        $count = (int)$wpdb->get_var($query);

        return $count > 0;
    }
    
    public function get_assessment_completion_details($assessor_id, $assessee_id, $assessment_id = null) {
        global $wpdb;

        if (!$assessment_id) {
            $current_assessment = $this->get_current_assessment();
            $assessment_id = $current_assessment ? $current_assessment->id : null;

            if (!$assessment_id) {
                return null;
            }
        }

        $query = $wpdb->prepare(
            "SELECT * 
             FROM {$wpdb->prefix}360_assessment_responses 
             WHERE assessor_id = %d 
             AND assessee_id = %d 
             AND assessment_id = %d 
             AND status = 'completed'
             ORDER BY completed_at DESC 
             LIMIT 1",
            $assessor_id,
            $assessee_id,
            $assessment_id
        );

        return $wpdb->get_row($query);
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

    public function get_assessment_results_overview($assessment_id) {
        global $wpdb;

        // Get correct table names
        $users_table = $wpdb->prefix . '360_users';
        $positions_table = $wpdb->prefix . '360_positions';
        $groups_table = $wpdb->prefix . '360_user_groups';
        $responses_table = $wpdb->prefix . '360_assessment_responses';
        $relationships_table = $wpdb->prefix . '360_user_relationships';

        $query = $wpdb->prepare("
            SELECT 
                u.id,
                u.first_name,
                u.last_name,
                u.email,
                p.name as position_name,
                g.group_name,
                (
                    SELECT COUNT(DISTINCT r.assessor_id)
                    FROM {$relationships_table} r
                    WHERE r.assessee_id = u.id
                ) as total_assessors,
                (
                    SELECT COUNT(DISTINCT ar2.assessor_id)
                    FROM {$responses_table} ar2
                    WHERE ar2.assessee_id = u.id
                    AND ar2.assessment_id = %d
                    AND ar2.status = 'completed'
                ) as completed_assessments,
                COALESCE(AVG(ar.rating), 0) as average_rating,
                COALESCE(MIN(ar.rating), 0) as min_rating,
                COALESCE(MAX(ar.rating), 0) as max_rating,
                COUNT(DISTINCT ar.id) as total_responses
            FROM {$users_table} u
            LEFT JOIN {$positions_table} p ON u.position_id = p.id
            LEFT JOIN {$groups_table} g ON u.group_id = g.id
            LEFT JOIN {$responses_table} ar ON u.id = ar.assessee_id 
                AND ar.assessment_id = %d
                AND ar.status = 'completed'
            WHERE EXISTS (
                SELECT 1 
                FROM {$relationships_table} r2
                WHERE r2.assessee_id = u.id
            )
            AND LOWER(g.group_name) = 'peers'
            GROUP BY u.id, u.first_name, u.last_name, u.email, p.name, g.group_name
            ORDER BY u.first_name, u.last_name
        ", $assessment_id, $assessment_id);

        $results = $wpdb->get_results($query);

        return $results;
    }

    public function get_detailed_assessment_results($assessment_id, $assessee_id) {
        global $wpdb;

        $responses_table = $wpdb->prefix . '360_assessment_responses';
        $users_table = $wpdb->prefix . '360_users';
        $questions_table = $wpdb->prefix . '360_questions';

        $query = $wpdb->prepare("
            SELECT 
                ar.*,
                q.text as question_text,
                q.topic_id,
                u.first_name as assessor_first_name,
                u.last_name as assessor_last_name
            FROM {$responses_table} ar
            JOIN {$questions_table} q ON ar.question_id = q.id
            LEFT JOIN {$users_table} u ON ar.assessor_id = u.id
            WHERE ar.assessment_id = %d 
            AND ar.assessee_id = %d
            AND ar.status = 'completed'
            ORDER BY q.topic_id, q.id, ar.created_at
        ", $assessment_id, $assessee_id);

        return $wpdb->get_results($query);
    }
    
    public function get_assessment_summary($assessment_id, $assessee_id) {
        global $wpdb;

        $responses_table = $wpdb->prefix . '360_assessment_responses';

        $query = $wpdb->prepare("
            SELECT 
                COUNT(DISTINCT assessor_id) as total_assessors,
                COUNT(DISTINCT CASE WHEN status = 'completed' THEN assessor_id END) as completed_assessments,
                COALESCE(AVG(CASE WHEN status = 'completed' THEN rating END), 0) as average_rating,
                COALESCE(MIN(CASE WHEN status = 'completed' THEN rating END), 0) as min_rating,
                COALESCE(MAX(CASE WHEN status = 'completed' THEN rating END), 0) as max_rating
            FROM {$responses_table}
            WHERE assessment_id = %d 
            AND assessee_id = %d
        ", $assessment_id, $assessee_id);

        return $wpdb->get_row($query);
    }
    
    public function check_and_update_table_structure() {
        global $wpdb;

        $table = $wpdb->prefix . '360_assessment_responses';

        // Check if rating column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME 
             FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND COLUMN_NAME = 'rating'",
            DB_NAME,
            $table
        ));

        if (empty($column_exists)) {
            // Add rating column if it doesn't exist
            $wpdb->query("ALTER TABLE $table ADD COLUMN rating int(11) NOT NULL AFTER question_id");
        }
    }
    
    public function get_user_assessment_results($assessment_id, $user_id) {
        global $wpdb;

        $responses_table = $wpdb->prefix . '360_assessment_responses';
        $users_table = $wpdb->prefix . '360_users';
        $questions_table = $wpdb->prefix . '360_questions';
        $topics_table = $wpdb->prefix . '360_topics';

        $query = $wpdb->prepare("
            SELECT 
                t.id as topic_id,
                t.name as topic_name,
                q.id as question_id,
                q.text as question_text,
                COUNT(DISTINCT ar.assessor_id) as total_responses,
                COALESCE(AVG(ar.rating), 0) as average_rating,
                COALESCE(MIN(ar.rating), 0) as min_rating,
                COALESCE(MAX(ar.rating), 0) as max_rating,
                GROUP_CONCAT(ar.comment SEPARATOR '||') as comments
            FROM {$topics_table} t
            JOIN {$questions_table} q ON q.topic_id = t.id
            LEFT JOIN {$responses_table} ar ON ar.question_id = q.id
                AND ar.assessment_id = %d
                AND ar.assessee_id = %d
                AND ar.status = 'completed'
            GROUP BY t.id, t.name, q.id, q.text
            ORDER BY t.id, q.id
        ", $assessment_id, $user_id);

        $results = $wpdb->get_results($query);

        // Process the results into a hierarchical structure
        $processed_results = array();
        foreach ($results as $row) {
            if (!isset($processed_results[$row->topic_id])) {
                $processed_results[$row->topic_id] = array(
                    'name' => $row->topic_name,
                    'questions' => array()
                );
            }

            $comments = $row->comments ? explode('||', $row->comments) : array();
            $processed_results[$row->topic_id]['questions'][] = array(
                'text' => $row->question_text,
                'total_responses' => $row->total_responses,
                'average_rating' => $row->average_rating,
                'min_rating' => $row->min_rating,
                'max_rating' => $row->max_rating,
                'comments' => array_filter($comments) // Remove empty comments
            );
        }

        return $processed_results;
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

            $assessment = $wpdb->get_row($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            return $assessment;

        } catch (Exception $e) {
            return null;
        }
    }
    
//    public function verify_tables() {
//        global $wpdb;
//        $charset_collate = $wpdb->get_charset_collate();
//
//        try {
//            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
//
//            // Verify assessments table
//            $assessment_questions_table = $wpdb->prefix . '360_assessment_questions';
//            if ($wpdb->get_var("SHOW TABLES LIKE '$assessment_questions_table'") != $assessment_questions_table) {
//                $sql = "CREATE TABLE $assessment_questions_table (
//                    id mediumint(9) NOT NULL AUTO_INCREMENT,
//                    assessment_id mediumint(9) NOT NULL,
//                    question_id mediumint(9) NOT NULL,
//                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
//                    PRIMARY KEY  (id),
//                    UNIQUE KEY unique_assessment_question (assessment_id, question_id),
//                    KEY assessment_id (assessment_id),
//                    KEY question_id (question_id)
//                ) $charset_collate;";
//
//                dbDelta($sql);
//
//                if ($wpdb->last_error) {
//                    throw new Exception('Failed to create assessment questions table: ' . $wpdb->last_error);
//                }
//            }
//            
//            // Verify assessments table
//            $assessments_table = $wpdb->prefix . '360_assessments';
//            if ($wpdb->get_var("SHOW TABLES LIKE '$assessments_table'") != $assessments_table) {
//                $sql = "CREATE TABLE $assessments_table (
//                    id mediumint(9) NOT NULL AUTO_INCREMENT,
//                    name varchar(100) NOT NULL,
//                    description text,
//                    start_date date NOT NULL,
//                    end_date date NOT NULL,
//                    status varchar(20) DEFAULT 'draft',
//                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
//                    PRIMARY KEY  (id),
//                    KEY status (status),
//                    KEY date_range (start_date, end_date)
//                ) $charset_collate;";
//
//                dbDelta($sql);
//
//                if ($wpdb->last_error) {
//                    throw new Exception('Failed to create assessments table: ' . $wpdb->last_error);
//                }
//            }
//
//            // Verify sections table
//            $sections_table = $wpdb->prefix . '360_sections';
//            if ($wpdb->get_var("SHOW TABLES LIKE '$sections_table'") != $sections_table) {
//                $sql = "CREATE TABLE $sections_table (
//                    id mediumint(9) NOT NULL AUTO_INCREMENT,
//                    name varchar(100) NOT NULL,
//                    description text,
//                    display_order int DEFAULT 0,
//                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
//                    PRIMARY KEY  (id),
//                    KEY display_order (display_order)
//                ) $charset_collate;";
//
//                dbDelta($sql);
//
//                if ($wpdb->last_error) {
//                    throw new Exception('Failed to create sections table: ' . $wpdb->last_error);
//                }
//            }
//
//            // Verify questions table
//            $questions_table = $wpdb->prefix . '360_questions';
//            if ($wpdb->get_var("SHOW TABLES LIKE '$questions_table'") != $questions_table) {
//                $sql = "CREATE TABLE $questions_table (
//                    id mediumint(9) NOT NULL AUTO_INCREMENT,
//                    section_id mediumint(9),
//                    question_text text NOT NULL,
//                    display_order int DEFAULT 0,
//                    is_required tinyint(1) DEFAULT 1,
//                    has_comments tinyint(1) DEFAULT 1,
//                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
//                    PRIMARY KEY  (id),
//                    KEY section_id (section_id),
//                    KEY display_order (display_order)
//                ) $charset_collate;";
//
//                dbDelta($sql);
//
//                if ($wpdb->last_error) {
//                    throw new Exception('Failed to create questions table: ' . $wpdb->last_error);
//                }
//            }
//
//            // Verify assessment_questions junction table
//            $assessment_questions_table = $wpdb->prefix . '360_assessment_questions';
//            if ($wpdb->get_var("SHOW TABLES LIKE '$assessment_questions_table'") != $assessment_questions_table) {
//                $sql = "CREATE TABLE $assessment_questions_table (
//                    id mediumint(9) NOT NULL AUTO_INCREMENT,
//                    assessment_id mediumint(9) NOT NULL,
//                    question_id mediumint(9) NOT NULL,
//                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
//                    PRIMARY KEY  (id),
//                    UNIQUE KEY unique_assessment_question (assessment_id, question_id),
//                    KEY assessment_id (assessment_id),
//                    KEY question_id (question_id)
//                ) $charset_collate;";
//
//                dbDelta($sql);
//
//                if ($wpdb->last_error) {
//                    throw new Exception('Failed to create assessment questions table: ' . $wpdb->last_error);
//                }
//            }
//
//            // Verify assessment instances table
//            $instances_table = $wpdb->prefix . '360_assessment_instances';
//            if ($wpdb->get_var("SHOW TABLES LIKE '$instances_table'") != $instances_table) {
//                $sql = "CREATE TABLE $instances_table (
//                    id mediumint(9) NOT NULL AUTO_INCREMENT,
//                    assessment_id mediumint(9) NOT NULL,
//                    assessor_id mediumint(9) NOT NULL,
//                    assessee_id mediumint(9) NOT NULL,
//                    status varchar(20) DEFAULT 'pending',
//                    completed_at datetime DEFAULT NULL,
//                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
//                    PRIMARY KEY  (id),
//                    UNIQUE KEY unique_assessment (assessment_id, assessor_id, assessee_id),
//                    KEY assessment_id (assessment_id),
//                    KEY assessor_id (assessor_id),
//                    KEY assessee_id (assessee_id),
//                    KEY status (status)
//                ) $charset_collate;";
//
//                dbDelta($sql);
//
//                if ($wpdb->last_error) {
//                    throw new Exception('Failed to create assessment instances table: ' . $wpdb->last_error);
//                }
//            }
//
//            // Verify assessment responses table
//            $responses_table = $wpdb->prefix . '360_assessment_responses';
//            if ($wpdb->get_var("SHOW TABLES LIKE '$responses_table'") != $responses_table) {
//                // Assessment responses table
//                $responses_table = $wpdb->prefix . '360_assessment_responses';
//                $sql = "CREATE TABLE IF NOT EXISTS $responses_table (
//                    id bigint(20) NOT NULL AUTO_INCREMENT,
//                    assessment_id bigint(20) NOT NULL,
//                    assessor_id bigint(20) NOT NULL,
//                    assessee_id bigint(20) NOT NULL,
//                    question_id bigint(20) NOT NULL,
//                    rating int(11) NOT NULL,
//                    comment text NULL,
//                    status varchar(20) NOT NULL DEFAULT 'pending',
//                    completed_at datetime NULL,
//                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
//                    PRIMARY KEY (id),
//                    KEY assessment_id (assessment_id),
//                    KEY assessor_id (assessor_id),
//                    KEY assessee_id (assessee_id),
//                    KEY question_id (question_id)
//                ) $charset_collate;";
//
//                dbDelta($sql);
//
//                if ($wpdb->last_error) {
//                    throw new Exception('Failed to create assessment responses table: ' . $wpdb->last_error);
//                }
//            }
//            
//            // User relationships table
//            $relationships_table = $wpdb->prefix . '360_user_relationships';
//            if ($wpdb->get_var("SHOW TABLES LIKE '$relationships_table'") != $relationships_table) {
//                // Assessment relationships table
//                $relationships_table = $wpdb->prefix . '360_user_relationships';
//                $sql = "CREATE TABLE IF NOT EXISTS $relationships_table (
//                    id bigint(20) NOT NULL AUTO_INCREMENT,
//                    assessor_id bigint(20) NOT NULL,
//                    assessee_id bigint(20) NOT NULL,
//                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
//                    PRIMARY KEY  (id),
//                    KEY assessor_id (assessor_id),
//                    KEY assessee_id (assessee_id),
//                    UNIQUE KEY unique_relationship (assessor_id,assessee_id)
//                ) $charset_collate;";
//
//                dbDelta($sql);
//
//                if ($wpdb->last_error) {
//                    throw new Exception('Failed to create assessment relationships table: ' . $wpdb->last_error);
//                }
//            }
//
//            // Update existing questions table structure if needed
//            $columns = $wpdb->get_results("SHOW COLUMNS FROM $questions_table");
//            $column_names = array_column($columns, 'Field');
//
//            // Add section_id column if it doesn't exist
//            if (!in_array('section_id', $column_names)) {
//                $wpdb->query("ALTER TABLE $questions_table ADD COLUMN section_id mediumint(9) AFTER id");
//            }
//
//            // Add display_order column if it doesn't exist
//            if (!in_array('display_order', $column_names)) {
//                $wpdb->query("ALTER TABLE $questions_table ADD COLUMN display_order int DEFAULT 0 AFTER question_text");
//            }
//            
//            // Verify sections table structure
//            $sections_table = $wpdb->prefix . '360_sections';
//            $sections_columns = $wpdb->get_results("SHOW COLUMNS FROM $sections_table");
//            $sections_column_names = array_column($sections_columns, 'Field');
//
//            // Add display_order column if it doesn't exist
//            if (!in_array('display_order', $sections_column_names)) {
//                $wpdb->query("ALTER TABLE $sections_table ADD COLUMN display_order int DEFAULT 0");
//            }
//
//            // Update indexes
//            $indexes = $wpdb->get_results("SHOW INDEX FROM $questions_table");
//            $index_names = array_column($indexes, 'Key_name');
//
//            if (!in_array('section_id', $index_names)) {
//                $wpdb->query("ALTER TABLE $questions_table ADD INDEX section_id (section_id)");
//            }
//
//            if (!in_array('display_order', $index_names)) {
//                $wpdb->query("ALTER TABLE $questions_table ADD INDEX display_order (display_order)");
//            }
//
//            // Migrate existing questions if needed
//            if (in_array('assessment_id', $column_names)) {
//                // Check if migration is needed
//                $needs_migration = $wpdb->get_var("SELECT COUNT(*) FROM $questions_table WHERE assessment_id IS NOT NULL");
//
//                if ($needs_migration > 0) {
//                    // Migrate questions to junction table
//                    $wpdb->query("INSERT IGNORE INTO $assessment_questions_table (assessment_id, question_id)
//                                 SELECT assessment_id, id FROM $questions_table WHERE assessment_id IS NOT NULL");
//
//                    // Remove assessment_id column
//                    $wpdb->query("ALTER TABLE $questions_table DROP COLUMN assessment_id");
//                }
//            }
//
//            return true;
//
//        } catch (Exception $e) {
//            return false;
//        }
//    }
    
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

            return (object)[
                'total_users' => $total_users,
                'assessable_users' => $assessable_users
            ];

        } catch (Exception $e) {
            return null;
        }
    }
    
    public function get_user_by_wp_id($wp_user_id) {
        global $wpdb;

        try {
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

            $user = $wpdb->get_row($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            if ($user) {
                // Ensure ID property exists for compatibility
                $user->ID = $user->id;
            } 

            return $user;

        } catch (Exception $e) {
            return null;
        }
    }

    public function is_peer_user($user_id) {
        global $wpdb;

        $peers_group = $wpdb->get_row("
            SELECT id 
            FROM {$wpdb->prefix}360_user_groups 
            WHERE group_name = 'Peers'
        ");

        if (!$peers_group) return false;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->prefix}360_users 
             WHERE id = %d 
             AND group_id = %d",
            $user_id,
            $peers_group->id
        ));

        return $count > 0;
    }
    
    public function get_user_assessees($user_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT u.*, 
                    p.name as position_name, 
                    g.group_name
             FROM {$wpdb->prefix}360_users u
             LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id
             LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
             INNER JOIN {$wpdb->prefix}360_user_relationships r ON u.id = r.assessee_id
             WHERE r.assessor_id = %d
             ORDER BY u.first_name, u.last_name",
            $user_id
        ));
    }
    
    public function get_user_assessors($user_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT u.*, 
                    p.name as position_name, 
                    g.group_name
             FROM {$wpdb->prefix}360_users u
             LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id
             LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
             INNER JOIN {$wpdb->prefix}360_user_relationships r ON u.id = r.assessor_id
             WHERE r.assessee_id = %d
             ORDER BY u.first_name, u.last_name",
            $user_id
        ));
    }
    
    public function get_user_assessment_stats($user_id) {
        global $wpdb;

        // Get current assessment
        $current_assessment = $this->get_current_assessment();
        if (!$current_assessment) {
            return (object)[
                'total_to_assess' => 0,
                'completed' => 0,
                'pending' => 0,
                'completion_rate' => 0
            ];
        }

        // Get table names
        $relationships_table = $wpdb->prefix . '360_user_relationships';
        $responses_table = $wpdb->prefix . '360_assessment_responses';
        $users_table = $wpdb->prefix . '360_users';
        $groups_table = $wpdb->prefix . '360_user_groups';

        // First, check if user is in Peers group
        $user_manager = Assessment_360_User_Manager::get_instance();
        $is_peer = $user_manager->is_peer_user($user_id);

        if ($is_peer) {
            // For peer users, count all peer users (including self)
            $query = $wpdb->prepare("
                SELECT 
                    (
                        SELECT COUNT(*)
                        FROM {$users_table} u2
                        JOIN {$groups_table} g2 ON u2.group_id = g2.id
                        WHERE LOWER(g2.group_name) = 'peers'
                        AND u2.status = 'active'
                    ) as total_to_assess,
                    (
                        SELECT COUNT(DISTINCT ar.assessee_id)
                        FROM {$responses_table} ar
                        JOIN {$users_table} u2 ON ar.assessee_id = u2.id
                        JOIN {$groups_table} g2 ON u2.group_id = g2.id
                        WHERE ar.assessor_id = %d
                        AND ar.assessment_id = %d
                        AND ar.status = 'completed'
                        AND LOWER(g2.group_name) = 'peers'
                    ) as completed
                FROM DUAL",
                $user_id,
                $current_assessment->id
            );
        } else {
            // For non-peer users, count only assigned users
            $query = $wpdb->prepare("
                SELECT 
                    (
                        SELECT COUNT(DISTINCT assessee_id)
                        FROM {$relationships_table}
                        WHERE assessor_id = %d
                    ) as total_to_assess,
                    (
                        SELECT COUNT(DISTINCT assessee_id)
                        FROM {$responses_table}
                        WHERE assessor_id = %d
                        AND assessment_id = %d
                        AND status = 'completed'
                    ) as completed
                FROM DUAL",
                $user_id,
                $user_id,
                $current_assessment->id
            );
        }

        $stats = $wpdb->get_row($query);

        // Calculate statistics
        $stats->total_to_assess = (int)$stats->total_to_assess;
        $stats->completed = (int)$stats->completed;
        $stats->pending = $stats->total_to_assess - $stats->completed;
        $stats->completion_rate = $stats->total_to_assess > 0 ? 
            round(($stats->completed / $stats->total_to_assess) * 100) : 0;

        return $stats;
    }
    
    public function get_assigned_users($assessor_id) {
        global $wpdb;

        $relationships_table = $wpdb->prefix . '360_user_relationships';
        $users_table = $wpdb->prefix . '360_users';
        $responses_table = $wpdb->prefix . '360_assessment_responses';

        $current_assessment = $this->get_current_assessment();
        $assessment_id = $current_assessment ? $current_assessment->id : 0;

        $query = $wpdb->prepare(
            "SELECT 
                u.*,
                CASE WHEN ar.status = 'completed' THEN 1 ELSE 0 END as is_completed
            FROM {$relationships_table} r
            JOIN {$users_table} u ON r.assessee_id = u.id
            LEFT JOIN {$responses_table} ar ON ar.assessee_id = r.assessee_id 
                AND ar.assessor_id = r.assessor_id
                AND ar.assessment_id = %d
            WHERE r.assessor_id = %d
            ORDER BY u.first_name, u.last_name",
            $assessment_id,
            $assessor_id
        );

        return $wpdb->get_results($query);
    }

    private function get_empty_user_stats() {
        return (object)[
            'total_to_assess' => 0,
            'completed' => 0,
            'pending' => 0,
            'completion_rate' => 0
        ];
    }

    public function get_users_to_assess($user_id, $assessment_id = null) {
        global $wpdb;

        // If assessment_id is not provided, get the current one
        if (!$assessment_id) {
            $assessment = $this->get_current_assessment();
            if (!$assessment) return [];
            $assessment_id = $assessment->id;
        }

        // Always use the relationships table: all assessees assigned to this assessor, regardless of group
        $query = $wpdb->prepare(
            "SELECT u.*, 
                    p.name as position_name, 
                    g.group_name 
            FROM {$wpdb->prefix}360_user_relationships ur
            INNER JOIN {$wpdb->prefix}360_users u ON ur.assessee_id = u.id
            LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id
            LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
            WHERE ur.assessor_id = %d
              AND u.status = 'active'
            ORDER BY u.first_name, u.last_name",
            $user_id
        );

        $users = $wpdb->get_results($query);

        return $users ?: [];
    }

    public function has_active_assessment() {
        global $wpdb;

        try {
            $query = "SELECT * FROM {$wpdb->prefix}360_assessments 
                     WHERE status = 'active' 
                     LIMIT 1";

            $assessment = $wpdb->get_row($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            return $assessment ?: false;

        } catch (Exception $e) {
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

    public function update_assessment_status($id, $status) {
        global $wpdb;

        try {
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
            return true;

        } catch (Exception $e) {
            return new WP_Error('update_failed', $e->getMessage());
        }
    }

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

            return (object)[
                'total' => $total_users,
                'completed' => $completed_count,
                'percentage' => $total_users > 0 ? round(($completed_count / $total_users) * 100) : 0
            ];

        } catch (Exception $e) {
            return null;
        }
    }
    
    public function delete_assessment($id) {
        global $wpdb;

        $wpdb->query('SET autocommit=0');
        $wpdb->query('START TRANSACTION');
        try {
            // Get assessment
            $assessment = $this->get_assessment($id);
            if (!$assessment) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('assessment_not_found', 'Assessment not found');
            }

            // Validate deletion
            if ($assessment->status === 'completed') {
                $wpdb->query('ROLLBACK');
                return new WP_Error('cannot_delete_completed', 'Completed assessments cannot be deleted');
            }
            if ($assessment->status === 'active') {
                $wpdb->query('ROLLBACK');
                return new WP_Error('cannot_delete_active', 'Active assessments cannot be deleted');
            }


            // Get all assessment instances for this assessment
            $instance_ids = $wpdb->get_col(
                $wpdb->prepare("SELECT id FROM {$wpdb->prefix}360_assessment_instances WHERE assessment_id = %d", $id)
            );


            // Delete responses using instance IDs
            if (!empty($instance_ids)) {
                $instance_ids_string = implode(',', array_map('intval', $instance_ids));
                $wpdb->query(
                    "DELETE FROM {$wpdb->prefix}360_assessment_responses 
                     WHERE assessment_instance_id IN ($instance_ids_string)"
                );
                if ($wpdb->last_error) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('delete_failed', "Error deleting responses: " . $wpdb->last_error);
                }
            }

            // Delete assessment instances
            $wpdb->delete(
                $wpdb->prefix . '360_assessment_instances',
                ['assessment_id' => $id],
                ['%d']
            );
            if ($wpdb->last_error) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('delete_failed', "Error deleting assessment instances: " . $wpdb->last_error);
            }

            // Delete assessment
            $result = $wpdb->delete(
                $wpdb->prefix . '360_assessments',
                ['id' => $id],
                ['%d']
            );
            if ($wpdb->last_error) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('delete_failed', 'Error deleting assessment: ' . $wpdb->last_error);
            }
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('delete_failed', 'Database delete failed.');
            }

            $wpdb->query('COMMIT');
            $wpdb->query('SET autocommit=1');
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            $wpdb->query('SET autocommit=1');
            return new WP_Error('delete_failed', $e->getMessage());
        }
    }

    public function get_current_assessment() {
        global $wpdb;

        $query = "
            SELECT * 
            FROM {$wpdb->prefix}360_assessments 
            WHERE status = 'active' 
            AND start_date <= CURRENT_DATE() 
            AND end_date >= CURRENT_DATE() 
            ORDER BY id DESC 
            LIMIT 1
        ";

        return $wpdb->get_row($query);
    }
    
    public function debug_assessment_status() {
        global $wpdb;

        try {
            $query = "SELECT id, name, status, created_at 
                     FROM {$wpdb->prefix}360_assessments 
                     ORDER BY created_at DESC";

            $assessments = $wpdb->get_results($query);

            return $assessments;

        } catch (Exception $e) {
            return null;
        }
    }
    
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

            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    public function get_active_assessments_count() {
        global $wpdb;
        
        try {
            $query = "SELECT COUNT(*) FROM {$wpdb->prefix}360_assessments 
                     WHERE status = 'active' 
                     AND start_date <= CURRENT_DATE 
                     AND end_date >= CURRENT_DATE";

            $count = (int)$wpdb->get_var($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            return $count;

        } catch (Exception $e) {
            return 0;
        }
    }

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
            return array();
        }
    }

    private function get_assessment_questions_count($assessment_id) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}360_questions WHERE assessment_id = %d",
            $assessment_id
        );
        
        return (int)$wpdb->get_var($query);
    }

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

    public function get_assessment_results($assessment_id) {
        global $wpdb;

        $query = $wpdb->prepare("
            SELECT 
                u.id,
                u.first_name,
                u.last_name,
                u.email,
                p.name as position_name,
                g.group_name,
                (
                    SELECT COUNT(DISTINCT assessor_id)
                    FROM {$wpdb->prefix}360_assessment_responses
                    WHERE assessment_id = %d
                    AND assessee_id = u.id
                ) as total_assessors,
                (
                    SELECT COUNT(DISTINCT assessor_id)
                    FROM {$wpdb->prefix}360_assessment_responses
                    WHERE assessment_id = %d
                    AND assessee_id = u.id
                    AND status = 'completed'
                ) as completed_assessments,
                COALESCE(AVG(ar.rating), 0) as average_rating,
                COALESCE(MIN(ar.rating), 0) as min_rating,
                COALESCE(MAX(ar.rating), 0) as max_rating,
                COUNT(DISTINCT ar.id) as total_responses
            FROM {$wpdb->prefix}360_users u
            LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id
            LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
            LEFT JOIN {$wpdb->prefix}360_assessment_responses ar 
                ON u.id = ar.assessee_id 
                AND ar.assessment_id = %d
            WHERE EXISTS (
                SELECT 1 
                FROM {$wpdb->prefix}360_user_relationships r
                WHERE r.assessee_id = u.id
                AND a.status = 'deleted'
            )
            GROUP BY 
                u.id, 
                u.first_name, 
                u.last_name, 
                u.email, 
                p.name, 
                g.group_name
            ORDER BY 
                u.first_name, 
                u.last_name",
            $assessment_id,
            $assessment_id,
            $assessment_id
        );

        if (WP_DEBUG) {
            error_log("Assessment results query: " . $query);
        }

        $results = $wpdb->get_results($query);

        if ($wpdb->last_error) {
            error_log("Database error in get_assessment_results: " . $wpdb->last_error);
            return new WP_Error('db_error', 'Failed to get assessment results');
        }

        return $results;
    }
    
    private function get_individual_results($assessment_id, $user_id) {
        global $wpdb;

        // Get all questions and responses
        $query = $wpdb->prepare(
            "SELECT DISTINCT
                q.*,
                ar.rating,
                ar.comment,
                COALESCE(s.name, 'General') as section_name,
                COALESCE(s.id, 0) as section_id
             FROM {$wpdb->prefix}360_questions q 
             LEFT JOIN {$wpdb->prefix}360_sections s 
                ON q.section_id = s.id
             LEFT JOIN {$wpdb->prefix}360_assessment_responses ar 
                ON ar.question_id = q.id
                AND ar.assessment_id = %d
                AND ar.assessee_id = %d
             WHERE EXISTS (
                SELECT 1 
                FROM {$wpdb->prefix}360_assessment_responses 
                WHERE assessment_id = %d 
                AND question_id = q.id
             )
             ORDER BY s.id, q.id",
            $assessment_id,
            $user_id,
            $assessment_id
        );

        $rows = $wpdb->get_results($query);

        if ($wpdb->last_error) {
            throw new Exception('Database error: ' . $wpdb->last_error);
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

        return array_values($results);
    }
    
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
            }

        } catch (Exception $e) {
            //Error notice here
        }
    }
    
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

            $rate = $wpdb->get_var($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            return round($rate ?? 0, 2);

        } catch (Exception $e) {
            return 0;
        }
    }

    public function get_assessment_stats($assessment_id) {
        global $wpdb;

        try {
            $query = $wpdb->prepare(
                "SELECT 
                    a.*,
                    (
                        SELECT COUNT(DISTINCT assessee_id) 
                        FROM {$wpdb->prefix}360_assessment_responses 
                        WHERE assessment_id = a.id
                    ) as total_participants,
                    (
                        SELECT COUNT(DISTINCT assessor_id) 
                        FROM {$wpdb->prefix}360_assessment_responses 
                        WHERE assessment_id = a.id
                    ) as total_assessors,
                    (
                        SELECT COUNT(DISTINCT assessor_id) 
                        FROM {$wpdb->prefix}360_assessment_responses 
                        WHERE assessment_id = a.id 
                        AND status = 'completed'
                    ) as completed_assessments,
                    AVG(ar.rating) as average_rating
                FROM {$wpdb->prefix}360_assessments a
                LEFT JOIN {$wpdb->prefix}360_assessment_responses ar 
                    ON a.id = ar.assessment_id
                WHERE a.id = %d
                GROUP BY a.id",
                $assessment_id
            );

            $stats = $wpdb->get_row($query);

            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            if ($stats) {
                // Ensure these properties exist even if null
                $stats->total_participants = $stats->total_participants ?? 0;
                $stats->total_assessors = $stats->total_assessors ?? 0;
                $stats->completed_assessments = $stats->completed_assessments ?? 0;
                $stats->average_rating = round($stats->average_rating ?? 0, 2);

                // Calculate completion rate
                $stats->completion_rate = $this->calculate_completion_rate(
                    $stats->completed_assessments,
                    $stats->total_assessors
                );
            }

            return $stats;

        } catch (Exception $e) {
            return null;
        }
    }
    
    private function calculate_completion_rate($completed, $total) {
        if (!$total) return 0;
        return round(($completed / $total) * 100, 2);
    }

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
                    (
                        SELECT COUNT(DISTINCT r.assessor_id)
                        FROM {$wpdb->prefix}360_user_relationships r
                        WHERE r.assessee_id = u.id
                    ) as total_assessors,
                    (
                        SELECT COUNT(DISTINCT ar2.assessor_id)
                        FROM {$wpdb->prefix}360_assessment_responses ar2
                        WHERE ar2.assessment_id = %d
                        AND ar2.assessee_id = u.id
                        AND ar2.status = 'completed'
                    ) as completed_assessments,
                    AVG(ar.rating) as average_rating,
                    MIN(ar.rating) as min_rating,
                    MAX(ar.rating) as max_rating,
                    COUNT(DISTINCT ar.id) as total_responses
                FROM {$wpdb->prefix}360_users u
                INNER JOIN {$wpdb->prefix}360_user_relationships ur ON u.id = ur.assessee_id
                LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id
                LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
                LEFT JOIN {$wpdb->prefix}360_assessment_responses ar 
                    ON u.id = ar.assessee_id 
                    AND ar.assessment_id = %d
                GROUP BY 
                    u.id, u.first_name, u.last_name, u.email, p.name, g.group_name
                ORDER BY u.first_name, u.last_name",
                $assessment_id,
                $assessment_id
            );

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
                    FROM {$wpdb->prefix}360_assessment_responses ar
                    WHERE ar.assessment_id = %d 
                    AND ar.assessee_id = %d
                    GROUP BY ar.rating
                    ORDER BY ar.rating",
                    $assessment_id,
                    $user->id
                );

                $ratings_distribution = $wpdb->get_results($ratings_query);

                // Format ratings distribution
                $user->ratings_distribution = array_fill(1, 5, 0);
                foreach ($ratings_distribution as $rating) {
                    $user->ratings_distribution[$rating->rating] = (int)$rating->count;
                }

                // Get comments
                $comments_query = $wpdb->prepare(
                    "SELECT ar.comment, u2.first_name, u2.last_name
                    FROM {$wpdb->prefix}360_assessment_responses ar
                    JOIN {$wpdb->prefix}360_users u2 ON ar.assessor_id = u2.id
                    WHERE ar.assessment_id = %d 
                    AND ar.assessee_id = %d
                    AND ar.comment IS NOT NULL
                    AND ar.comment != ''",
                    $assessment_id,
                    $user->id
                );

                $user->comments = $wpdb->get_results($comments_query);
            }

            return $users;

        } catch (Exception $e) {
            return array();
        }
    }
    
    public function get_user_assessment_details($assessment_id, $user_id) {
        global $wpdb;

        try {
            // Get basic user info with assessment stats
            $query = $wpdb->prepare(
                "SELECT 
                    u.*,
                    p.name as position_name,
                    g.group_name,
                    (
                        SELECT COUNT(DISTINCT r.assessor_id)
                        FROM {$wpdb->prefix}360_user_relationships r
                        WHERE r.assessee_id = u.id
                    ) as total_assessors,
                    (
                        SELECT COUNT(DISTINCT ar2.assessor_id)
                        FROM {$wpdb->prefix}360_assessment_responses ar2
                        WHERE ar2.assessment_id = %d
                        AND ar2.assessee_id = u.id
                        AND ar2.status = 'completed'
                    ) as completed_assessments,
                    AVG(ar.rating) as average_rating
                FROM {$wpdb->prefix}360_users u
                LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id
                LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
                LEFT JOIN {$wpdb->prefix}360_assessment_responses ar 
                    ON u.id = ar.assessee_id 
                    AND ar.assessment_id = %d
                WHERE u.id = %d
                GROUP BY u.id",
                $assessment_id,
                $assessment_id,
                $user_id
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
                LEFT JOIN {$wpdb->prefix}360_assessment_responses ar 
                    ON ar.question_id = q.id 
                    AND ar.assessment_id = %d 
                    AND ar.assessee_id = %d
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
            return null;
        }
    }
    
    public function update_assessment($id, $data) {
        global $wpdb;

        try {
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

            return true;

        } catch (Exception $e) {
            return new WP_Error('update_failed', $e->getMessage());
        }
    }
    
    public function create_assessment($data) {
        global $wpdb;

        try {
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

            return $new_id;

        } catch (Exception $e) {
            return new WP_Error('create_failed', $e->getMessage());
        }
    }

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
            return null;
        }
    }
    
    public function get_recent_activities($limit = 10) {
        global $wpdb;

        $responses_table = $wpdb->prefix . '360_assessment_responses';
        $users_table = $wpdb->prefix . '360_users';
        $assessments_table = $wpdb->prefix . '360_assessments';

        $query = $wpdb->prepare("
            SELECT 
                r.id,
                r.assessment_id,
                r.assessor_id,
                r.assessee_id,
                r.status,
                r.completed_at,
                r.created_at,
                a.name as assessment_name,
                assessor.first_name as assessor_first_name,
                assessor.last_name as assessor_last_name,
                assessee.first_name as assessee_first_name,
                assessee.last_name as assessee_last_name
            FROM {$responses_table} r
            JOIN {$users_table} assessor ON r.assessor_id = assessor.id
            JOIN {$users_table} assessee ON r.assessee_id = assessee.id
            JOIN {$assessments_table} a ON r.assessment_id = a.id
            WHERE r.status = 'completed'
            ORDER BY r.completed_at DESC
            LIMIT %d",
            $limit
        );

        return $wpdb->get_results($query);
    }

    public function get_dashboard_stats() {
        global $wpdb;
        
        $stats = new stdClass();
        $stats->total_assessors = 0; // default value

        $responses_table = $wpdb->prefix . '360_assessment_responses';
        $users_table = $wpdb->prefix . '360_users';
        $assessments_table = $wpdb->prefix . '360_assessments';

        // Get current assessment
        $current_assessment = $this->get_current_assessment();

        if (!$current_assessment) {
            return (object)[
                'total_assessments' => 0,
                'completed_assessments' => 0,
                'pending_assessments' => 0,
                'completion_rate' => 0,
                'total_users' => 0,
                'active_users' => 0
            ];
        }

        $query = "
            SELECT 
                (
                    SELECT COUNT(DISTINCT ar.assessor_id) 
                    FROM {$responses_table} ar 
                    WHERE ar.assessment_id = {$current_assessment->id}
                ) as total_assessors,
                (
                    SELECT COUNT(*) 
                    FROM {$responses_table} ar 
                    WHERE ar.assessment_id = {$current_assessment->id} 
                    AND ar.status = 'completed'
                ) as completed_assessments,
                (
                    SELECT COUNT(*) 
                    FROM {$users_table} u 
                    WHERE u.status = 'active'
                ) as active_users
        ";

        $stats = $wpdb->get_row($query);

        // Calculate additional statistics
        $stats->completion_rate = $stats->total_assessors > 0 ? 
            round(($stats->completed_assessments / $stats->total_assessors) * 100) : 0;

        return $stats;
    }

    
}