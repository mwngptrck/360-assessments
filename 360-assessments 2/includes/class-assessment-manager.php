<?php
class Assessment_360_Assessment_Manager {
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // 1. Get current assessment (the one marked as active)
    public function get_current_assessment() {
        global $wpdb;
        return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}360_assessments WHERE status = 'active' ORDER BY start_date DESC LIMIT 1");
    }

    // 2. Get all assessments
    public function get_all_assessments() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}360_assessments ORDER BY start_date DESC");
    }

    // 3. Dashboard stats: completion, users, active assessments, etc.
    public function get_dashboard_stats() {
        global $wpdb;
        $current_assessment = $this->get_current_assessment();
        if (!$current_assessment) {
            return (object)[
                'total_pairs' => 0,
                'completed_pairs' => 0,
                'active_users' => 0,
                'completion_rate' => 0
            ];
        }
        $relationships_table = $wpdb->prefix . '360_user_relationships';
        $responses_table = $wpdb->prefix . '360_assessment_responses';
        $users_table = $wpdb->prefix . '360_users';

        // Denominator: total unique (assessor, assessee) pairs assigned, excluding self
        $total_pairs = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $relationships_table
             WHERE assessment_id = %d AND assessor_id != assessee_id",
            $current_assessment->id
        ));

        // Numerator: unique (assessor, assessee) pairs completed, excluding self
        $completed_pairs = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT CONCAT(assessor_id, '-', assessee_id))
             FROM $responses_table
             WHERE assessment_id = %d AND status = 'completed' AND assessor_id != assessee_id",
            $current_assessment->id
        ));

        // Users
        $active_users = $wpdb->get_var("SELECT COUNT(*) FROM $users_table WHERE status = 'active'");

        $completion_rate = ($total_pairs > 0)
            ? min(round(($completed_pairs / $total_pairs) * 100), 100)
            : 0;

        return (object)[
            'total_pairs' => $total_pairs,
            'completed_pairs' => $completed_pairs,
            'active_users' => $active_users,
            'completion_rate' => $completion_rate,
        ];
    }

    // 4. Get stats per group/role (peers, managers, etc.)
    public function get_group_stats() {
        global $wpdb;
        $groups = $wpdb->get_results("SELECT DISTINCT group_name FROM {$wpdb->prefix}360_user_groups");
        $assessment = $this->get_current_assessment();
        $out = [];

        foreach ($groups as $group) {
            // All users in group
            $user_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}360_user_groups WHERE group_name = %s",
                $group->group_name
            ));
            $user_count = count($user_ids);

            // Completed by group
            if ($assessment && $user_count > 0) {
                $completed = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT assessor_id) FROM {$wpdb->prefix}360_assessment_responses WHERE assessment_id = %d AND assessor_id IN (" . implode(',', array_map('intval', $user_ids)) . ") AND status = 'completed'",
                    $assessment->id
                ));
                $completion_rate = $user_count > 0 ? round(($completed / $user_count) * 100) : 0;
            } else {
                $completed = 0;
                $completion_rate = 0;
            }

            $out[] = [
                'group_name' => $group->group_name,
                'user_count' => $user_count,
                'completed' => $completed,
                'completion_rate' => $completion_rate,
            ];
        }
        return $out;
    }

    // 5. Recent completed activities (limit N)
    public function get_recent_activities($limit = 10) {
        global $wpdb;
        $assessment = $this->get_current_assessment();
        if (!$assessment) return [];
        $sql = $wpdb->prepare(
            "SELECT r.*, 
                u1.first_name AS assessor_first_name, u1.last_name AS assessor_last_name, 
                u2.first_name AS assessee_first_name, u2.last_name AS assessee_last_name,
                a.name AS assessment_name
            FROM {$wpdb->prefix}360_assessment_responses r
            LEFT JOIN {$wpdb->prefix}360_users u1 ON r.assessor_id = u1.id
            LEFT JOIN {$wpdb->prefix}360_users u2 ON r.assessee_id = u2.id
            LEFT JOIN {$wpdb->prefix}360_assessments a ON r.assessment_id = a.id
            WHERE r.assessment_id = %d AND r.status = 'completed'
            ORDER BY r.completed_at DESC
            LIMIT %d
            ", $assessment->id, intval($limit)
        );
        return $wpdb->get_results($sql);
    }

    // 6. Pending (incomplete) assessments (limit N)
    public function get_pending_assessments($limit = 10) {
        global $wpdb;
        $assessment = $this->get_current_assessment();
        if (!$assessment) return [];

        // All assigned relationships for this assessment
        $sql = $wpdb->prepare(
            "SELECT rel.*, 
                u1.first_name AS assessor_first, u1.last_name AS assessor_last,
                u2.first_name AS assessee_first, u2.last_name AS assessee_last,
                a.name AS assessment_name
            FROM {$wpdb->prefix}360_user_relationships rel
            LEFT JOIN {$wpdb->prefix}360_users u1 ON rel.assessor_id = u1.id
            LEFT JOIN {$wpdb->prefix}360_users u2 ON rel.assessee_id = u2.id
            LEFT JOIN {$wpdb->prefix}360_assessments a ON rel.assessment_id = a.id
            WHERE rel.assessment_id = %d
            ORDER BY rel.id DESC
            LIMIT %d",
            $assessment->id, intval($limit * 3)
        );

        $rows = $wpdb->get_results($sql);

        // Filter to only those that have not been completed
        $pending = [];
        foreach ($rows as $row) {
            $completed = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}360_assessment_responses WHERE assessment_id = %d AND assessor_id = %d AND assessee_id = %d AND status = 'completed'",
                $assessment->id, $row->assessor_id, $row->assessee_id
            ));
            if (!$completed) {
                $pending[] = (object)[
                    'assessor_name' => $row->assessor_first . ' ' . $row->assessor_last,
                    'assessee_name' => $row->assessee_first . ' ' . $row->assessee_last,
                    'assessment_name' => $row->assessment_name,
                    'due_date' => $assessment->end_date,
                    'status' => 'pending'
                ];
                if (count($pending) >= $limit) break;
            }
        }
        return $pending;
    }

    // 7. Top assessors (users who have completed the most assessments)
    public function get_top_assessors($limit = 5) {
        global $wpdb;
        $assessment = $this->get_current_assessment();
        if (!$assessment) return [];
        $sql = $wpdb->prepare(
            "SELECT r.assessor_id, u.first_name, u.last_name, COUNT(*) as count
            FROM {$wpdb->prefix}360_assessment_responses r
            LEFT JOIN {$wpdb->prefix}360_users u ON r.assessor_id = u.id
            WHERE r.assessment_id = %d AND r.status = 'completed'
            GROUP BY r.assessor_id
            ORDER BY count DESC
            LIMIT %d",
            $assessment->id, intval($limit)
        );
        $results = $wpdb->get_results($sql);
        $out = [];
        foreach ($results as $row) {
            $out[] = (object)[
                'name' => $row->first_name . ' ' . $row->last_name,
                'count' => $row->count
            ];
        }
        return $out;
    }

    // 8. Top assessees (users who have received the most assessments)
    public function get_top_assessees($limit = 5) {
        global $wpdb;
        $assessment = $this->get_current_assessment();
        if (!$assessment) return [];
        $sql = $wpdb->prepare(
            "SELECT r.assessee_id, u.first_name, u.last_name, COUNT(*) as count
            FROM {$wpdb->prefix}360_assessment_responses r
            LEFT JOIN {$wpdb->prefix}360_users u ON r.assessee_id = u.id
            WHERE r.assessment_id = %d AND r.status = 'completed'
            GROUP BY r.assessee_id
            ORDER BY count DESC
            LIMIT %d",
            $assessment->id, intval($limit)
        );
        $results = $wpdb->get_results($sql);
        $out = [];
        foreach ($results as $row) {
            $out[] = (object)[
                'name' => $row->first_name . ' ' . $row->last_name,
                'count' => $row->count
            ];
        }
        return $out;
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
            return 'Pending';
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
            "SELECT q.*, 
                    s.name as section_name, s.id as section_id, s.display_order as section_display_order, 
                    t.id as topic_id, t.name as topic_name, t.display_order as topic_display_order
             FROM {$wpdb->prefix}360_questions q
             JOIN {$wpdb->prefix}360_sections s ON q.section_id = s.id
             JOIN {$wpdb->prefix}360_topics t ON s.topic_id = t.id
             WHERE q.position_id = %d
             ORDER BY t.display_order, s.display_order, q.display_order",
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
                      AND r.assessor_id != u.id
                ) as total_assessors,
                (
                    SELECT COUNT(DISTINCT ar2.assessor_id)
                    FROM {$responses_table} ar2
                    WHERE ar2.assessee_id = u.id
                      AND ar2.assessment_id = %d
                      AND ar2.status = 'completed'
                      AND ar2.assessor_id != u.id
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
                  AND r2.assessor_id != u.id
            )
            GROUP BY u.id, u.first_name, u.last_name, u.email, p.name, g.group_name
            ORDER BY u.first_name, u.last_name
        ", $assessment_id, $assessment_id);

        $results = $wpdb->get_results($query);
        return $results;
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
    
    public function get_user_detailed_results_grouped($assessment_id, $user_id) {
        global $wpdb;

        // Fetch all standard (non-department) groups
        $groups_table = $wpdb->prefix . '360_user_groups';
        $standard_groups = $wpdb->get_results(
            "SELECT id, group_name FROM $groups_table WHERE is_department = 0",
            ARRAY_A
        );
        $standard_group_names = [];
        foreach ($standard_groups as $group) {
            $standard_group_names[$group['id']] = $group['group_name'];
        }

        // Table names
        $responses_table = $wpdb->prefix . '360_assessment_responses';
        $questions_table = $wpdb->prefix . '360_questions';
        $sections_table = $wpdb->prefix . '360_sections';

        // Get all responses for this user on this assessment, joined with group, questions, and section info
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                r.*, 
                q.section_id, 
                q.question_text, 
                u.group_id, 
                s.name AS section_name
             FROM $responses_table r
             INNER JOIN $questions_table q ON q.id = r.question_id
             INNER JOIN {$wpdb->prefix}360_users u ON u.id = r.assessor_id
             LEFT JOIN $sections_table s ON s.id = q.section_id
             WHERE r.assessee_id = %d 
               AND r.assessment_id = %d 
               AND r.status = 'completed'
               AND r.assessor_id != r.assessee_id",
            $user_id, $assessment_id
        ));

        // Group responses by group and section
        $grouped = [];
        foreach ($results as $row) {
            $group_id = $row->group_id;
            $section_id = $row->section_id;
            $section_name = $row->section_name ?: 'Section';

            if (!isset($grouped[$group_id])) {
                $grouped[$group_id] = [
                    'group_name' => $standard_group_names[$group_id] ?? 'Other',
                    'sections' => []
                ];
            }

            if (!isset($grouped[$group_id]['sections'][$section_id])) {
                $grouped[$group_id]['sections'][$section_id] = [
                    'section_name' => $section_name,
                    'questions' => []
                ];
            }

            $grouped[$group_id]['sections'][$section_id]['questions'][] = [
                'question_text' => $row->question_text,
                'rating' => $row->rating,
                'comment' => $row->comment,
                'assessor_id' => $row->assessor_id,
                'response_id' => $row->id,
            ];
        }

        return $grouped;
    }

    public function get_user_section_summary_grouped($assessment_id, $user_id) {
        global $wpdb;

        // Fetch all standard (non-department) groups
        $groups_table = $wpdb->prefix . '360_user_groups';
        $standard_groups = $wpdb->get_results(
            "SELECT id, group_name FROM $groups_table WHERE is_department = 0",
            ARRAY_A
        );
        $standard_group_names = [];
        foreach ($standard_groups as $group) {
            $standard_group_names[$group['id']] = $group['group_name'];
        }

        // Table names
        $responses_table = $wpdb->prefix . '360_assessment_responses';
        $questions_table = $wpdb->prefix . '360_questions';
        $sections_table = $wpdb->prefix . '360_sections';

        // Get all responses for this user on this assessment, joined with group, questions, and section info
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                r.*, 
                q.section_id, 
                u.group_id, 
                s.name AS section_name
             FROM $responses_table r
             INNER JOIN $questions_table q ON q.id = r.question_id
             INNER JOIN {$wpdb->prefix}360_users u ON u.id = r.assessor_id
             LEFT JOIN $sections_table s ON s.id = q.section_id
             WHERE r.assessee_id = %d 
               AND r.assessment_id = %d 
               AND r.status = 'completed'
               AND r.assessor_id != r.assessee_id",
            $user_id, $assessment_id
        ));

        // Group and average ratings by group and section
        $grouped = [];
        foreach ($results as $row) {
            $group_id = $row->group_id;
            $section_id = $row->section_id;
            $section_name = $row->section_name ?: 'Section';
            $rating = is_null($row->rating) ? 0 : floatval($row->rating);

            if (!isset($grouped[$group_id])) {
                $grouped[$group_id] = [
                    'group_name' => $standard_group_names[$group_id] ?? 'Other',
                    'sections' => []
                ];
            }

            if (!isset($grouped[$group_id]['sections'][$section_id])) {
                $grouped[$group_id]['sections'][$section_id] = [
                    'section_name' => $section_name,
                    'total' => 0,
                    'count' => 0,
                    'average' => 0
                ];
            }

            $grouped[$group_id]['sections'][$section_id]['total'] += $rating;
            $grouped[$group_id]['sections'][$section_id]['count'] += 1;
        }

        // Calculate averages
        foreach ($grouped as $gid => &$group) {
            foreach ($group['sections'] as $sid => &$section) {
                $section['average'] = $section['count'] > 0 ? round($section['total'] / $section['count'], 2) : 0;
            }
        }
        unset($group, $section);

        return $grouped;
    }

    // 1. Get user groups present in this assessment (using relationship_type)
    public function get_user_groups_for_assessment($assessment_id, $user_id) {
        global $wpdb;
        $groups_table = $wpdb->prefix . "360_groups"; // Assuming you have a groups table
        $relationships = $wpdb->prefix . "360_user_relationships";
        // Get all group types (relationship_type) for this user/assessment where is_department = 0
        $sql = $wpdb->prepare(
            "SELECT DISTINCT rel.relationship_type
             FROM $relationships rel
             INNER JOIN $groups_table g ON rel.relationship_type = g.name
             WHERE rel.assessment_id = %d AND rel.assessee_id = %d AND g.is_department = 0",
            $assessment_id, $user_id
        );
        $groups = $wpdb->get_col($sql);

        // Optional: Make 'Self' first if it exists
        if (($key = array_search('self', $groups)) !== false) {
            unset($groups[$key]);
            array_unshift($groups, 'self');
        }
        // Capitalize for display
        $groups = array_map(function($g) {
            return ucfirst($g);
        }, $groups);
        return $groups;
    }

    // 2. Get the number of assessors in each group
    public function get_assessor_counts_per_group($assessment_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . "360_user_relationships";
        $sql = $wpdb->prepare(
            "SELECT relationship_type, COUNT(DISTINCT assessor_id) as cnt
            FROM $table
            WHERE assessment_id = %d AND assessee_id = %d
            GROUP BY relationship_type",
            $assessment_id, $user_id
        );
        $results = $wpdb->get_results($sql);
        $counts = [];
        foreach ($results as $row) {
            $counts[ucfirst($row->relationship_type)] = intval($row->cnt);
        }
        return $counts;
    }

    // 3. Get per-group average score (all questions)
    public function get_average_score_per_group($assessment_id, $user_id) {
        global $wpdb;
        $responses = $wpdb->prefix . "360_assessment_responses";
        $relationships = $wpdb->prefix . "360_user_relationships";
        $groups = $this->get_user_groups_for_assessment($assessment_id, $user_id);
        $averages = [];

        foreach ($groups as $display_group) {
            $group = strtolower($display_group);
            $sql = $wpdb->prepare(
                "SELECT AVG(rating) FROM $responses a
                INNER JOIN $relationships rel ON
                    rel.assessment_id = a.assessment_id
                    AND rel.assessee_id = a.assessee_id
                    AND rel.assessor_id = a.assessor_id
                WHERE a.assessment_id = %d AND a.assessee_id = %d AND rel.relationship_type = %s",
                $assessment_id, $user_id, $group
            );
            $avg = $wpdb->get_var($sql);
            $averages[$display_group] = is_null($avg) ? '' : round(floatval($avg), 2);
        }
        return $averages;
    }

    // 4. Get all questions with per-group averages for this assessee
    public function get_questions_group_averages($assessment_id, $user_id, $groups) {
        global $wpdb;
        $qtable = $wpdb->prefix . "360_questions";
        $sectable = $wpdb->prefix . "360_sections";
        $toptable = $wpdb->prefix . "360_topics";
        $responses = $wpdb->prefix . "360_assessment_responses";
        $relationships = $wpdb->prefix . "360_user_relationships";

        // GLOBAL: Get all active questions (with section and topic info), no assessment_id filter!
        $sql = "
            SELECT q.id as question_id, q.question_text, s.id as section_id, s.name as section_name, t.id as topic_id, t.name as topic_name
             FROM $qtable q
             INNER JOIN $sectable s ON q.section_id = s.id
             INNER JOIN $toptable t ON s.topic_id = t.id
             WHERE q.status = 'active' AND s.status = 'active' AND t.status = 'active'
             ORDER BY t.display_order, s.display_order, q.display_order
        ";
        $questions = $wpdb->get_results($sql);

        $result = [];
        foreach ($questions as $q) {
            $row = [
                'topic' => $q->topic_name,
                'section' => $q->section_name,
                'question' => $q->question_text,
                'group_values' => []
            ];
            foreach ($groups as $display_group) {
                $group = strtolower($display_group);
                $sql = $wpdb->prepare(
                    "SELECT AVG(a.rating)
                     FROM $responses a
                     INNER JOIN $relationships rel
                        ON rel.assessment_id = a.assessment_id
                        AND rel.assessee_id = a.assessee_id
                        AND rel.assessor_id = a.assessor_id
                     WHERE a.assessment_id = %d
                        AND a.assessee_id = %d
                        AND a.question_id = %d
                        AND rel.relationship_type = %s",
                    $assessment_id, $user_id, $q->question_id, $group
                );
                $val = $wpdb->get_var($sql);
                $row['group_values'][$display_group] = is_null($val) ? '' : round(floatval($val), 2);
            }
            $result[] = $row;
        }
        return $result;
    }

    // 5. Get section chart data (one per section: x=user groups, y=average)
    public function get_section_chart_data($assessment_id, $user_id, $groups) {
        global $wpdb;
        $sectable = $wpdb->prefix . "360_sections";
        $responses = $wpdb->prefix . "360_assessment_responses";
        $qtable = $wpdb->prefix . "360_questions";
        $relationships = $wpdb->prefix . "360_user_relationships";

        // GLOBAL: Get all active sections, no assessment_id filter!
        $sql = "
            SELECT s.id as section_id, s.name as section_name
             FROM $sectable s
             WHERE s.status = 'active'
             ORDER BY s.display_order
        ";
        $sections = $wpdb->get_results($sql);

        $result = [];
        foreach ($sections as $section) {
            $data = [];
            foreach ($groups as $display_group) {
                $group = strtolower($display_group);
                $sql = $wpdb->prepare(
                    "SELECT AVG(a.rating)
                     FROM $responses a
                     INNER JOIN $qtable q ON a.question_id = q.id
                     INNER JOIN $relationships rel
                        ON rel.assessment_id = a.assessment_id
                        AND rel.assessee_id = a.assessee_id
                        AND rel.assessor_id = a.assessor_id
                     WHERE a.assessment_id = %d
                        AND a.assessee_id = %d
                        AND q.section_id = %d
                        AND rel.relationship_type = %s",
                    $assessment_id, $user_id, $section->section_id, $group
                );
                $val = $wpdb->get_var($sql);
                $data[] = is_null($val) ? 0 : round(floatval($val), 2);
            }
            $result[$section->section_name] = [
                'labels' => $groups,
                'values' => $data,
            ];
        }
        return $result;
    }

    // 6. All individual responses for this user/assessment (ANONYMOUS: no assessor name/id)
    public function get_all_individual_responses($assessment_id, $user_id) {
        global $wpdb;
        $responses = $wpdb->prefix . "360_assessment_responses";
        $qtable = $wpdb->prefix . "360_questions";
        $sectable = $wpdb->prefix . "360_sections";
        $toptable = $wpdb->prefix . "360_topics";
        $relationships = $wpdb->prefix . "360_user_relationships";

        $sql = $wpdb->prepare(
            "SELECT
                t.name as topic,
                s.name as section,
                q.question_text as question,
                rel.relationship_type as `group`,
                a.rating as value,
                a.comment as comment
            FROM $responses a
            INNER JOIN $qtable q ON a.question_id = q.id
            INNER JOIN $sectable s ON q.section_id = s.id
            INNER JOIN $toptable t ON s.topic_id = t.id
            INNER JOIN $relationships rel
                ON rel.assessment_id = a.assessment_id
                AND rel.assessee_id = a.assessee_id
                AND rel.assessor_id = a.assessor_id
            WHERE a.assessment_id = %d AND a.assessee_id = %d
            ORDER BY t.display_order, s.display_order, q.display_order, rel.relationship_type",
            $assessment_id, $user_id
        );
        $results = $wpdb->get_results($sql, ARRAY_A);

        // Capitalize group for display
        foreach ($results as &$row) {
            $row['group'] = ucfirst($row['group']);
        }
        return $results;
    }
}