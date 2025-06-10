<?php
if (!defined('ABSPATH')) exit;

class Assessment_360_Assessment {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        //add_action('admin_post_assessment_360_save_assessment', array($this, 'handle_form_submission'));
        add_action('admin_init', array($this, 'handle_assessment_actions'));
    }

    public function get_assessment($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}360_assessments WHERE id = %d",
            $id
        ));
    }

    public function get_active_assessment() {
        global $wpdb;

        return $wpdb->get_row("
            SELECT * 
            FROM {$wpdb->prefix}360_assessments 
            WHERE status = 'active' 
            LIMIT 1
        ");
    }
    
    public function is_assessment_completed($assessor_id, $assessee_id, $assessment_id) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->prefix}360_assessment_responses 
             WHERE assessor_id = %d 
             AND assessee_id = %d 
             AND assessment_id = %d 
             AND status = 'completed'",
            $assessor_id,
            $assessee_id,
            $assessment_id
        ));

        return $count > 0;
    }
    
    public function get_assessment_status($assessor_id, $assessee_id, $assessment_id) {
        global $wpdb;

        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status 
             FROM {$wpdb->prefix}360_assessment_responses 
             WHERE assessor_id = %d 
             AND assessee_id = %d 
             AND assessment_id = %d 
             LIMIT 1",
            $assessor_id,
            $assessee_id,
            $assessment_id
        ));

        return $status ?: 'pending';
    }
    
    public function get_all_assessments() {
        global $wpdb;

        $assessments = $wpdb->get_results("
            SELECT * 
            FROM {$wpdb->prefix}360_assessments 
            ORDER BY created_at DESC
        ");

        // Ensure properties exist
        foreach ($assessments as $assessment) {
            $assessment->created_at = $assessment->created_at ?? null;
            $assessment->completed_at = $assessment->completed_at ?? null;
            $assessment->status = $assessment->status ?? 'draft';
        }

        return $assessments;
    }

    public function create_assessment($data) {
        global $wpdb;
        $fields = [
            'name' => $data['name'],
            'description' => $data['description'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => isset($data['status']) ? $data['status'] : 'draft'
        ];
        $result = $wpdb->insert(
            $wpdb->prefix . '360_assessments',
            $fields,
            array('%s', '%s', '%s', '%s', '%s')
        );
        if ($result === false) {
            return new WP_Error('insert_failed', 'Failed to create assessment');
        }
        return $wpdb->insert_id;
    }
    
    public function update_assessment($id, $data) {
        global $wpdb;
        $fields = [];
        $formats = [];
        foreach ($data as $key => $value) {
            $fields[$key] = $value;
            $formats[] = '%s';
        }
        if (empty($fields)) {
            return new WP_Error('no_data', 'No data to update.');
        }
        $result = $wpdb->update(
            $wpdb->prefix . '360_assessments',
            $fields,
            ['id' => $id],
            $formats,
            ['%d']
        );
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to update assessment');
        }
        return true;
    }
    
    public function enable_assessment($id) {
        global $wpdb;
        return $wpdb->update(
            $wpdb->prefix . '360_assessments',
            array('status' => 'active'),
            array('id' => $id)
        );
    }

    public function disable_assessment($id) {
        global $wpdb;
        return $wpdb->update(
            $wpdb->prefix . '360_assessments',
            array('status' => 'disabled'),
            array('id' => $id)
        );
    }
    
    public function delete_assessment($id) {
        global $wpdb;
        return $wpdb->delete($wpdb->prefix . '360_assessments', ['id' => $id], ['%d']);
    }

    public function get_assessment_completion_rate($id) {
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(*) as total
            FROM {$wpdb->prefix}360_assessment_instances
            WHERE assessment_id = %d",
            $id
        ));

        if (!$stats->total) {
            return 0;
        }

        return round(($stats->completed / $stats->total) * 100);
    }

    public function handle_form_submission() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('assessment_360_assessment_nonce');

        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'start_date' => sanitize_text_field($_POST['start_date']),
            'end_date' => sanitize_text_field($_POST['end_date'])
        );

        if (empty($data['name'])) {
            wp_redirect(add_query_arg('error', 'Assessment name is required.', wp_get_referer()));
            exit;
        }

        if (isset($_POST['assessment_id'])) {
            $result = $this->update_assessment(intval($_POST['assessment_id']), $data);
            $message = 'Assessment updated successfully.';
        } else {
            $result = $this->create_assessment($data);
            $message = 'Assessment created successfully.';
        }

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('error', $result->get_error_message(), wp_get_referer()));
            exit;
        }

        wp_redirect(add_query_arg('message', $message, admin_url('admin.php?page=assessment-360-assessments')));
        exit;
    }

    public function handle_assessment_actions() {
        if (!isset($_GET['action']) || !isset($_GET['page']) || $_GET['page'] !== 'assessment-360-assessments') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $action = $_GET['action'];
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if (!$id) {
            return;
        }

        switch ($action) {
            case 'enable':
                check_admin_referer('enable_assessment_' . $id);
                $this->enable_assessment($id);
                $message = 'Assessment enabled successfully.';
                break;

            case 'disable':
                check_admin_referer('disable_assessment_' . $id);
                $this->disable_assessment($id);
                $message = 'Assessment disabled successfully.';
                break;

            default:
                return;
        }

        wp_redirect(add_query_arg('message', $message, admin_url('admin.php?page=assessment-360-assessments')));
        exit;
    }
    
    public function update_assessment_status($assessment_id, $status) {
        global $wpdb;

        // Validate status
        $allowed_statuses = ['active', 'completed', 'deleted'];
        if (!in_array($status, $allowed_statuses)) {
            return new WP_Error('invalid_status', 'Invalid status provided');
        }

        // Check if assessment exists
        $assessment = $this->get_assessment($assessment_id);
        if (!$assessment) {
            return new WP_Error('not_found', 'Assessment not found');
        }

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Prepare update data
            $data = ['status' => $status];
            $format = ['%s'];

            // Add completed_at date if status is completed
            if ($status === 'completed') {
                $data['completed_at'] = current_time('mysql');
                $format[] = '%s';
            }

            // Update the assessment
            $result = $wpdb->update(
                $wpdb->prefix . '360_assessments',
                $data,
                ['id' => $assessment_id],
                $format,
                ['%d']
            );

            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }

            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('Assessment status update failed: ' . $e->getMessage());
            return new WP_Error('update_failed', 'Failed to update assessment status: ' . $e->getMessage());
        }
    }

    public function can_activate_assessment($assessment_id) {
        // Get current active assessment
        $active_assessment = $this->get_active_assessment();

        // If no active assessment, can activate
        if (!$active_assessment) {
            return true;
        }

        // If this is the active assessment, it's already active
        if ($active_assessment->id == $assessment_id) {
            return new WP_Error(
                'already_active', 
                'This assessment is already active'
            );
        }

        // Otherwise, can't activate while another is active
        return new WP_Error(
            'active_exists', 
            'Another assessment is currently active. Please complete it first.'
        );
    }
    
    public function assessment_exists($assessment_id) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->prefix}360_assessments 
             WHERE id = %d",
            $assessment_id
        ));

        return $count > 0;
    }

    public function get_first_assessment_results($assessment_id, $user_id) {
        global $wpdb;

        // Get all questions and their sections for this assessment
        $query = $wpdb->prepare("
            SELECT 
                q.id as question_id,
                q.question_text,
                s.id as section_id,
                s.name as section_name,
                t.id as topic_id,
                t.name as topic_name,
                AVG(ar.rating) as average_rating,
                COUNT(DISTINCT ar.assessor_id) as total_assessors,
                GROUP_CONCAT(DISTINCT 
                    CASE 
                        WHEN ar.comment IS NOT NULL AND ar.comment != '' 
                        THEN CONCAT(ar.assessor_id, ':', ar.comment)
                        ELSE NULL 
                    END
                    SEPARATOR '||'
                ) as comments
            FROM {$wpdb->prefix}360_questions q
            LEFT JOIN {$wpdb->prefix}360_sections s ON q.section_id = s.id
            LEFT JOIN {$wpdb->prefix}360_topics t ON s.topic_id = t.id
            LEFT JOIN {$wpdb->prefix}360_assessment_responses ar 
                ON ar.question_id = q.id 
                AND ar.assessment_id = %d 
                AND ar.assessee_id = %d
            WHERE q.assessment_id = %d
            GROUP BY q.id
            ORDER BY t.id, s.id, q.id",
            $assessment_id,
            $user_id,
            $assessment_id
        );

        $results = $wpdb->get_results($query);

        // Format results into hierarchical structure
        $formatted_results = [];
        foreach ($results as $row) {
            if (!isset($formatted_results[$row->topic_name])) {
                $formatted_results[$row->topic_name] = [
                    'sections' => []
                ];
            }

            if (!isset($formatted_results[$row->topic_name]['sections'][$row->section_name])) {
                $formatted_results[$row->topic_name]['sections'][$row->section_name] = [
                    'questions' => []
                ];
            }

            // Format comments
            $comments = [];
            if ($row->comments) {
                foreach (explode('||', $row->comments) as $comment) {
                    list($assessor_id, $comment_text) = explode(':', $comment);
                    $comments[] = [
                        'assessor_id' => $assessor_id,
                        'comment' => $comment_text
                    ];
                }
            }

            $formatted_results[$row->topic_name]['sections'][$row->section_name]['questions'][] = [
                'id' => $row->question_id,
                'text' => $row->question_text,
                'average_rating' => round($row->average_rating, 2),
                'total_assessors' => $row->total_assessors,
                'comments' => $comments
            ];
        }

        return $formatted_results;
    }

    public function get_comparative_assessment_results($user_id, $current_assessment_id) {
        global $wpdb;

        // Get last 3 assessments including current
        $assessments = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT a.id, a.name, a.created_at
            FROM {$wpdb->prefix}360_assessments a
            JOIN {$wpdb->prefix}360_assessment_responses ar ON ar.assessment_id = a.id
            WHERE ar.assessee_id = %d
            ORDER BY a.created_at DESC
            LIMIT 3",
            $user_id
        ));

        if (empty($assessments)) {
            return [];
        }

        $assessment_ids = wp_list_pluck($assessments, 'id');

        // Get average ratings by user group for each assessment
        $query = $wpdb->prepare("
            SELECT 
                ar.assessment_id,
                q.id as question_id,
                q.question_text,
                s.id as section_id,
                s.name as section_name,
                t.id as topic_id,
                t.name as topic_name,
                CASE 
                    WHEN ar.assessor_id = ar.assessee_id THEN 'Self'
                    WHEN ug.is_department = 1 THEN 'Department'
                    WHEN ug.group_name = 'Peers' THEN 'Peers'
                    ELSE 'Others'
                END as assessor_group,
                AVG(ar.rating) as average_rating,
                COUNT(DISTINCT ar.assessor_id) as total_assessors
            FROM {$wpdb->prefix}360_questions q
            LEFT JOIN {$wpdb->prefix}360_sections s ON q.section_id = s.id
            LEFT JOIN {$wpdb->prefix}360_topics t ON s.topic_id = t.id
            LEFT JOIN {$wpdb->prefix}360_assessment_responses ar ON ar.question_id = q.id
            LEFT JOIN {$wpdb->prefix}360_users u ON ar.assessor_id = u.id
            LEFT JOIN {$wpdb->prefix}360_user_groups ug ON u.group_id = ug.id
            WHERE ar.assessment_id IN (" . implode(',', array_fill(0, count($assessment_ids), '%d')) . ")
            AND ar.assessee_id = %d
            GROUP BY ar.assessment_id, q.id, assessor_group
            ORDER BY t.id, s.id, q.id",
            array_merge($assessment_ids, [$user_id])
        );

        $results = $wpdb->get_results($query);

        // Format results
        $formatted_results = [
            'assessments' => $assessments,
            'topics' => []
        ];

        foreach ($results as $row) {
            if (!isset($formatted_results['topics'][$row->topic_name])) {
                $formatted_results['topics'][$row->topic_name] = [
                    'sections' => []
                ];
            }

            if (!isset($formatted_results['topics'][$row->topic_name]['sections'][$row->section_name])) {
                $formatted_results['topics'][$row->topic_name]['sections'][$row->section_name] = [
                    'questions' => []
                ];
            }

            if (!isset($formatted_results['topics'][$row->topic_name]['sections'][$row->section_name]['questions'][$row->question_id])) {
                $formatted_results['topics'][$row->topic_name]['sections'][$row->section_name]['questions'][$row->question_id] = [
                    'text' => $row->question_text,
                    'ratings' => []
                ];
            }

            $formatted_results['topics'][$row->topic_name]['sections'][$row->section_name]['questions'][$row->question_id]['ratings'][$row->assessment_id][$row->assessor_group] = [
                'average' => round($row->average_rating, 2),
                'total_assessors' => $row->total_assessors
            ];
        }

        return $formatted_results;
    }
    
    public function get_all_users_with_results() {
        global $wpdb;

        return $wpdb->get_results("
            SELECT 
                u.id as user_id,
                u.first_name,
                u.last_name,
                p.name as position_name,
                g.group_name,
                a.id as assessment_id,
                a.name as assessment_name,
                COUNT(DISTINCT ar.assessor_id) as total_assessors,
                COUNT(DISTINCT CASE WHEN ar.status = 'completed' THEN ar.assessor_id END) as completed_assessments,
                COALESCE(AVG(ar.rating), 0) as average_rating
            FROM {$wpdb->prefix}360_users u
            JOIN {$wpdb->prefix}360_assessment_responses ar ON u.id = ar.assessee_id
            JOIN {$wpdb->prefix}360_assessments a ON ar.assessment_id = a.id
            LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id
            LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
            GROUP BY u.id, a.id
            ORDER BY a.created_at DESC, u.first_name, u.last_name
        ");
    }

    public function get_users_with_results($assessment_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                u.id as user_id,
                u.first_name,
                u.last_name,
                p.name as position_name,
                g.group_name,
                a.id as assessment_id,
                a.name as assessment_name,
                COUNT(DISTINCT ar.assessor_id) as total_assessors,
                COUNT(DISTINCT CASE WHEN ar.status = 'completed' THEN ar.assessor_id END) as completed_assessments,
                COALESCE(AVG(ar.rating), 0) as average_rating
            FROM {$wpdb->prefix}360_users u
            JOIN {$wpdb->prefix}360_assessment_responses ar ON u.id = ar.assessee_id
            JOIN {$wpdb->prefix}360_assessments a ON ar.assessment_id = a.id
            LEFT JOIN {$wpdb->prefix}360_positions p ON u.position_id = p.id
            LEFT JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
            WHERE a.id = %d
            GROUP BY u.id
            ORDER BY u.first_name, u.last_name",
            $assessment_id
        ));
    }
    
    public function get_user_assessment_results($assessment_id, $user_id) {
        global $wpdb;

        // First get all questions that have responses for this assessment
        $query = $wpdb->prepare("
            SELECT DISTINCT
                q.id as question_id,
                q.question_text,
                s.id as section_id,
                s.name as section_name,
                t.id as topic_id,
                t.name as topic_name,
                (
                    SELECT GROUP_CONCAT(DISTINCT 
                        CONCAT_WS(':', ar2.rating, ar2.comment, ug2.group_name)
                        SEPARATOR '||'
                    )
                    FROM {$wpdb->prefix}360_assessment_responses ar2
                    LEFT JOIN {$wpdb->prefix}360_users u2 ON ar2.assessor_id = u2.id
                    LEFT JOIN {$wpdb->prefix}360_user_groups ug2 ON u2.group_id = ug2.id
                    WHERE ar2.question_id = q.id
                    AND ar2.assessment_id = %d
                    AND ar2.assessee_id = %d
                ) as responses
            FROM {$wpdb->prefix}360_assessment_responses ar
            JOIN {$wpdb->prefix}360_questions q ON ar.question_id = q.id
            LEFT JOIN {$wpdb->prefix}360_sections s ON q.section_id = s.id
            LEFT JOIN {$wpdb->prefix}360_topics t ON s.topic_id = t.id
            WHERE ar.assessment_id = %d
            AND ar.assessee_id = %d
            ORDER BY t.id, s.id, q.id",
            $assessment_id,
            $user_id,
            $assessment_id,
            $user_id
        );

        $rows = $wpdb->get_results($query);

        if ($wpdb->last_error) {
            return new WP_Error('db_error', $wpdb->last_error);
        }

        // Format results into hierarchical structure
        $results = [];
        foreach ($rows as $row) {
            $topic_name = $row->topic_name ?? 'General';
            $section_name = $row->section_name ?? 'General';

            if (!isset($results[$topic_name])) {
                $results[$topic_name] = [
                    'sections' => []
                ];
            }

            if (!isset($results[$topic_name]['sections'][$section_name])) {
                $results[$topic_name]['sections'][$section_name] = [
                    'questions' => []
                ];
            }

            // Initialize question data
            $question_data = [
                'text' => $row->question_text,
                'ratings' => [],
                'comments' => [],
                'average_rating' => 0,
                'total_assessors' => 0
            ];

            // Process responses
            if ($row->responses) {
                $responses = explode('||', $row->responses);
                foreach ($responses as $response) {
                    list($rating, $comment, $group_name) = array_pad(explode(':', $response), 3, '');
                    if ($rating) {
                        $question_data['ratings'][] = floatval($rating);
                    }
                    if ($comment) {
                        $question_data['comments'][] = [
                            'comment' => $comment,
                            'assessor_group' => $group_name ?? 'Unknown'
                        ];
                    }
                }
            }

            // Calculate averages
            if (!empty($question_data['ratings'])) {
                $question_data['average_rating'] = round(
                    array_sum($question_data['ratings']) / count($question_data['ratings']), 
                    2
                );
                $question_data['total_assessors'] = count($question_data['ratings']);
            }

            $results[$topic_name]['sections'][$section_name]['questions'][] = $question_data;
        }

        return $results;
    }

    public function is_first_assessment($user_id) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT assessment_id) 
             FROM {$wpdb->prefix}360_assessment_responses 
             WHERE assessee_id = %d",
            $user_id
        ));

        return $count <= 1;
    }

    public function get_rating_color($rating) {
        if ($rating >= 4.5) return 'success';
        if ($rating >= 3.5) return 'info';
        if ($rating >= 2.5) return 'warning';
        return 'danger';
    }
    
    public function has_previous_assessments($user_id) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT assessment_id) 
             FROM {$wpdb->prefix}360_assessment_responses 
             WHERE assessee_id = %d",
            $user_id
        ));

        return $count > 1; // Returns true if user has more than one assessment
    }
    
    public function get_user_previous_assessments($user_id, $current_assessment_id = null) {
        global $wpdb;

        $query = "SELECT DISTINCT 
                    a.id, 
                    a.name, 
                    a.created_at
                  FROM {$wpdb->prefix}360_assessments a
                  JOIN {$wpdb->prefix}360_assessment_responses ar 
                    ON ar.assessment_id = a.id
                  WHERE ar.assessee_id = %d";

        $params = [$user_id];

        if ($current_assessment_id) {
            $query .= " AND a.id != %d";
            $params[] = $current_assessment_id;
        }

        $query .= " ORDER BY a.created_at DESC";

        return $wpdb->get_results($wpdb->prepare($query, $params));
    }
    
    public function get_assessment_comments($assessment_id, $user_id) {
        global $wpdb;

        $query = $wpdb->prepare("
            SELECT 
                ar.comment,
                q.question_text,
                s.name as section_name,
                t.name as topic_name,
                ug.group_name as assessor_group
            FROM {$wpdb->prefix}360_assessment_responses ar
            JOIN {$wpdb->prefix}360_questions q ON ar.question_id = q.id
            LEFT JOIN {$wpdb->prefix}360_sections s ON q.section_id = s.id
            LEFT JOIN {$wpdb->prefix}360_topics t ON s.topic_id = t.id
            LEFT JOIN {$wpdb->prefix}360_users u ON ar.assessor_id = u.id
            LEFT JOIN {$wpdb->prefix}360_user_groups ug ON u.group_id = ug.id
            WHERE ar.assessment_id = %d 
            AND ar.assessee_id = %d
            AND ar.comment IS NOT NULL 
            AND ar.comment != ''
            ORDER BY t.name, s.name, q.id",
            $assessment_id,
            $user_id
        );

        return $wpdb->get_results($query);
    }

    public function get_user_assessment_stats($user_id) {
        global $wpdb;

        // Get active assessment
        $active_assessment = $this->get_active_assessment();
        if (!$active_assessment) {
            return (object)[
                'total_to_assess' => 0,
                'completed' => 0,
                'pending' => 0,
                'completion_rate' => 0
            ];
        }

        $user_group = $this->assessment_360_get_user_group($user_id); // returns a string, e.g. 'Peers'
        $is_peer = ($user_group === 'Peers');

        // Get all distinct assessee_ids assigned to this user
        $people_to_assess = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT assessee_id
             FROM {$wpdb->prefix}360_user_relationships
             WHERE assessor_id = %d",
            $user_id
        ));

        // If user is a Peer, ensure they are in the list
        if ($is_peer && !in_array($user_id, $people_to_assess)) {
            $people_to_assess[] = $user_id;
        }

        // Get all completed assessments for this user (assessee IDs)
        $completed_assessments = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT assessee_id
             FROM {$wpdb->prefix}360_assessment_responses
             WHERE assessor_id = %d
             AND assessment_id = %d
             AND status = 'completed'",
            $user_id,
            $active_assessment->id
        ));

        // If user is a Peer and has completed their self-assessment, ensure counted
        if ($is_peer && !in_array($user_id, $completed_assessments)) {
            // Check if there's a completed self-assessment record
            $self_completed = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}360_assessment_responses
                 WHERE assessor_id = %d AND assessee_id = %d AND assessment_id = %d AND status = 'completed'",
                $user_id, $user_id, $active_assessment->id
            ));
            if ($self_completed) {
                $completed_assessments[] = $user_id;
            }
        }

        $total_to_assess = count($people_to_assess);
        $completed = count(array_intersect($people_to_assess, $completed_assessments));
        $pending = $total_to_assess - $completed;
        $completion_rate = $total_to_assess > 0 ? round(($completed / $total_to_assess) * 100) : 0;

        return (object)[
            'total_to_assess' => $total_to_assess,
            'completed' => $completed,
            'pending' => $pending,
            'completion_rate' => $completion_rate,
            'assessment_id' => $active_assessment->id
        ];
    }
    
    public function assessment_360_get_user_group($user_id) {
        global $wpdb;
        $group = $wpdb->get_var($wpdb->prepare(
            "SELECT g.group_name 
             FROM {$wpdb->prefix}360_users u
             JOIN {$wpdb->prefix}360_user_groups g ON u.group_id = g.id
             WHERE u.id = %d",
            $user_id
        ));
        return $group;
    }
    
    public function get_dashboard_stats($user_id) {
        global $wpdb;

        // Get active assessment
        $active_assessment = $this->get_active_assessment();
        if (!$active_assessment) {
            return (object)[
                'total_assessors' => 0,
                'completed_assessments' => 0,
                'pending_assessments' => 0,
                'completion_rate' => 0
            ];
        }

        // Get total assessors assigned
        $total_assessors = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT assessor_id) 
             FROM {$wpdb->prefix}360_user_relationships 
             WHERE assessee_id = %d",
            $user_id
        ));

        // Get completed assessments
        $completed_assessments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT assessor_id) 
             FROM {$wpdb->prefix}360_assessment_responses 
             WHERE assessee_id = %d 
             AND assessment_id = %d 
             AND status = 'completed'",
            $user_id,
            $active_assessment->id
        ));

        // Calculate stats
        $total_assessors = (int)$total_assessors;
        $completed_assessments = (int)$completed_assessments;
        $pending_assessments = $total_assessors - $completed_assessments;
        $completion_rate = $total_assessors > 0 ? round(($completed_assessments / $total_assessors) * 100) : 0;

        return (object)[
            'total_assessors' => $total_assessors,
            'completed_assessments' => $completed_assessments,
            'pending_assessments' => $pending_assessments,
            'completion_rate' => $completion_rate
        ];
    }
}