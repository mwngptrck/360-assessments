<?php

class Assessment_360_Data
{
    // 1. Get user groups for this assessment/user
    public function get_user_groups_for_assessment($assessment_id, $user_id) {
        global $wpdb;
        $relationships = $wpdb->prefix . "360_user_relationships";
        $groups_table = $wpdb->prefix . "360_user_groups";
        // Get dynamic group names, aggregate is_department=1 as "Peers"
        $sql = $wpdb->prepare(
            "SELECT DISTINCT 
                CASE 
                    WHEN g.is_department = 1 THEN 'Peers'
                    ELSE rel.relationship_type
                END as group_label
            FROM $relationships rel
            INNER JOIN $groups_table g ON rel.relationship_type = g.group_name
            WHERE rel.assessment_id = %d AND rel.assessee_id = %d",
            $assessment_id, $user_id
        );
        $groups = $wpdb->get_col($sql);

        // Prevent duplicates if multiple is_department=1
        $groups = array_unique(array_map('strtolower', $groups));

        // Ensure 'self' is present and always first if it exists
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

    // 2. Get number of assessors per group (with Peers aggregation)
    public function get_assessor_counts_per_group($assessment_id, $user_id) {
        global $wpdb;
        $relationships = $wpdb->prefix . "360_user_relationships";
        $groups_table = $wpdb->prefix . "360_user_groups";
        $sql = $wpdb->prepare(
            "SELECT 
                CASE 
                    WHEN g.is_department = 1 THEN 'Peers'
                    ELSE rel.relationship_type
                END as group_label,
                COUNT(DISTINCT rel.assessor_id) as cnt
            FROM $relationships rel
            INNER JOIN $groups_table g ON rel.relationship_type = g.group_name
            WHERE rel.assessment_id = %d AND rel.assessee_id = %d
            GROUP BY group_label",
            $assessment_id, $user_id
        );
        $results = $wpdb->get_results($sql);
        $counts = [];
        foreach ($results as $row) {
            $counts[ucfirst(strtolower($row->group_label))] = intval($row->cnt);
        }
        return $counts;
    }

    // 3. Get per-group average score (all questions), with Peers aggregation
    public function get_average_score_per_group($assessment_id, $user_id) {
        global $wpdb;
        $responses = $wpdb->prefix . "360_assessment_responses";
        $relationships = $wpdb->prefix . "360_user_relationships";
        $groups_table = $wpdb->prefix . "360_user_groups";
        $groups = $this->get_user_groups_for_assessment($assessment_id, $user_id);
        $averages = [];
        foreach ($groups as $display_group) {
            $group = strtolower($display_group);
            if ($group === 'peers') {
                // All is_department=1
                $sql = $wpdb->prepare(
                    "SELECT AVG(a.rating)
                     FROM $responses a
                     INNER JOIN $relationships rel ON rel.assessment_id = a.assessment_id
                        AND rel.assessee_id = a.assessee_id AND rel.assessor_id = a.assessor_id
                     INNER JOIN $groups_table g ON rel.relationship_type = g.group_name
                     WHERE a.assessment_id = %d AND a.assessee_id = %d AND g.is_department = 1",
                    $assessment_id, $user_id
                );
            } else {
                // Specific group (is_department=0 or self)
                $sql = $wpdb->prepare(
                    "SELECT AVG(a.rating)
                     FROM $responses a
                     INNER JOIN $relationships rel ON rel.assessment_id = a.assessment_id
                        AND rel.assessee_id = a.assessee_id AND rel.assessor_id = a.assessor_id
                     INNER JOIN $groups_table g ON rel.relationship_type = g.group_name
                     WHERE a.assessment_id = %d AND a.assessee_id = %d AND g.is_department = 0 AND rel.relationship_type = %s",
                    $assessment_id, $user_id, $group
                );
            }
            $avg = $wpdb->get_var($sql);
            $averages[$display_group] = is_null($avg) ? '' : round(floatval($avg), 2);
        }
        return $averages;
    }

    // 4. Get all questions with per-group averages for this assessee (with peer aggregation)
    public function get_questions_group_averages($assessment_id, $user_id, $groups) {
        global $wpdb;
        $qtable = $wpdb->prefix . "360_questions";
        $sectable = $wpdb->prefix . "360_sections";
        $toptable = $wpdb->prefix . "360_topics";
        $responses = $wpdb->prefix . "360_assessment_responses";
        $relationships = $wpdb->prefix . "360_user_relationships";
        $groups_table = $wpdb->prefix . "360_user_groups";

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
                if ($group === 'peers') {
                    $sql = $wpdb->prepare(
                        "SELECT AVG(a.rating)
                         FROM $responses a
                         INNER JOIN $relationships rel ON rel.assessment_id = a.assessment_id
                            AND rel.assessee_id = a.assessee_id AND rel.assessor_id = a.assessor_id
                         INNER JOIN $groups_table g ON rel.relationship_type = g.group_name
                         WHERE a.assessment_id = %d AND a.assessee_id = %d AND a.question_id = %d AND g.is_department = 1",
                        $assessment_id, $user_id, $q->question_id
                    );
                } else {
                    $sql = $wpdb->prepare(
                        "SELECT AVG(a.rating)
                         FROM $responses a
                         INNER JOIN $relationships rel ON rel.assessment_id = a.assessment_id
                            AND rel.assessee_id = a.assessee_id AND rel.assessor_id = a.assessor_id
                         INNER JOIN $groups_table g ON rel.relationship_type = g.group_name
                         WHERE a.assessment_id = %d AND a.assessee_id = %d AND a.question_id = %d AND g.is_department = 0 AND rel.relationship_type = %s",
                        $assessment_id, $user_id, $q->question_id, $group
                    );
                }
                $val = $wpdb->get_var($sql);
                $row['group_values'][$display_group] = is_null($val) ? '' : round(floatval($val), 2);
            }
            $result[] = $row;
        }
        return $result;
    }

    // 5. Get section chart data (per section: x=user groups, y=average)
    public function get_section_chart_data($assessment_id, $user_id, $groups) {
        global $wpdb;
        $sectable = $wpdb->prefix . "360_sections";
        $responses = $wpdb->prefix . "360_assessment_responses";
        $qtable = $wpdb->prefix . "360_questions";
        $relationships = $wpdb->prefix . "360_user_relationships";
        $groups_table = $wpdb->prefix . "360_user_groups";

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
                if ($group === 'peers') {
                    $sql = $wpdb->prepare(
                        "SELECT AVG(a.rating)
                         FROM $responses a
                         INNER JOIN $qtable q ON a.question_id = q.id
                         INNER JOIN $relationships rel ON rel.assessment_id = a.assessment_id
                            AND rel.assessee_id = a.assessee_id AND rel.assessor_id = a.assessor_id
                         INNER JOIN $groups_table g ON rel.relationship_type = g.group_name
                         WHERE a.assessment_id = %d AND a.assessee_id = %d AND q.section_id = %d AND g.is_department = 1",
                        $assessment_id, $user_id, $section->section_id
                    );
                } else {
                    $sql = $wpdb->prepare(
                        "SELECT AVG(a.rating)
                         FROM $responses a
                         INNER JOIN $qtable q ON a.question_id = q.id
                         INNER JOIN $relationships rel ON rel.assessment_id = a.assessment_id
                            AND rel.assessee_id = a.assessee_id AND rel.assessor_id = a.assessor_id
                         INNER JOIN $groups_table g ON rel.relationship_type = g.group_name
                         WHERE a.assessment_id = %d AND a.assessee_id = %d AND q.section_id = %d AND g.is_department = 0 AND rel.relationship_type = %s",
                        $assessment_id, $user_id, $section->section_id, $group
                    );
                }
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

    // 6. All individual responses for this user/assessment (ANONYMOUS)
    public function get_all_individual_responses($assessment_id, $user_id) {
        global $wpdb;
        $responses = $wpdb->prefix . "360_assessment_responses";
        $qtable = $wpdb->prefix . "360_questions";
        $sectable = $wpdb->prefix . "360_sections";
        $toptable = $wpdb->prefix . "360_topics";
        $relationships = $wpdb->prefix . "360_user_relationships";
        $groups_table = $wpdb->prefix . "360_user_groups";

        $sql = $wpdb->prepare(
            "SELECT
                t.name as topic,
                s.name as section,
                q.question_text as question,
                CASE 
                    WHEN g.is_department = 1 THEN 'Peers'
                    ELSE rel.relationship_type
                END as `group`,
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
            INNER JOIN $groups_table g ON rel.relationship_type = g.group_name
            WHERE a.assessment_id = %d AND a.assessee_id = %d
            ORDER BY t.display_order, s.display_order, q.display_order, `group`",
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