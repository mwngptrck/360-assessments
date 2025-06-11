<?php

class Assessment_360_Data
{
    // 1. Get user groups present in this assessment (using relationship_type)
    // Self is NOT a group, but we want to always show a "Self" column if self assessment exists.
    public function get_user_groups_for_assessment($assessment_id, $user_id) {
        global $wpdb;
        $groups_table = $wpdb->prefix . "360_user_groups";
        $relationships = $wpdb->prefix . "360_user_relationships";

        // Gather all distinct groups for this user/assessment, excluding 'self'
        $sql = $wpdb->prepare(
            "SELECT DISTINCT rel.relationship_type
             FROM $relationships rel
             INNER JOIN $groups_table g ON rel.relationship_type = g.group_name
             WHERE rel.assessment_id = %d AND rel.assessee_id = %d AND g.is_department = 0",
            $assessment_id, $user_id
        );
        $groups = $wpdb->get_col($sql);

        // Add 'Self' column if the user has a self assessment (not a group)
        $ordered = [];
        if ($this->has_self_assessment($assessment_id, $user_id)) {
            $ordered[] = 'Self';
        }
        // Always put 'Peers' column next if present (case-insensitive)
        foreach ($groups as $g) {
            if (strtolower($g) === 'peers' && !in_array('Peers', $ordered)) {
                $ordered[] = 'Peers';
            }
        }
        // Add all other groups except 'Peers' (case-insensitive)
        foreach ($groups as $g) {
            if (strtolower($g) !== 'peers') {
                $ordered[] = $g;
            }
        }
        return $ordered;
    }

    // Helper: check if user has a self assessment (assessor_id == assessee_id)
    public function has_self_assessment($assessment_id, $user_id) {
        global $wpdb;
        $responses = $wpdb->prefix . "360_assessment_responses";
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM $responses WHERE assessment_id = %d AND assessee_id = %d AND assessor_id = %d",
            $assessment_id, $user_id, $user_id
        );
        $count = $wpdb->get_var($sql);
        return $count > 0;
    }

    // 2. Get the number of assessors in each group (special handling for Self/Peers)
    public function get_assessor_counts_per_group($assessment_id, $user_id) {
        global $wpdb;
        $relationships = $wpdb->prefix . "360_user_relationships";

        $counts = [];

        // Self
        $self_count = $this->has_self_assessment($assessment_id, $user_id) ? 1 : 0;
        if ($self_count) {
            $counts['Self'] = $self_count;
        }

        // Peers (excluding self)
        $sql = $wpdb->prepare(
            "SELECT COUNT(DISTINCT assessor_id) FROM $relationships
             WHERE assessment_id = %d AND assessee_id = %d AND LOWER(relationship_type) = 'peers' AND assessor_id != %d",
            $assessment_id, $user_id, $user_id
        );
        $peers_count = intval($wpdb->get_var($sql));
        if ($peers_count > 0) {
            $counts['Peers'] = $peers_count;
        }

        // Other groups (excluding 'Peers')
        $sql = $wpdb->prepare(
            "SELECT relationship_type, COUNT(DISTINCT assessor_id) as cnt
            FROM $relationships
            WHERE assessment_id = %d AND assessee_id = %d AND LOWER(relationship_type) NOT IN ('peers')
            GROUP BY relationship_type",
            $assessment_id, $user_id
        );
        $results = $wpdb->get_results($sql);
        foreach ($results as $row) {
            // Don't overwrite Self/Peers
            if (strtolower($row->relationship_type) !== 'peers') {
                $counts[$row->relationship_type] = intval($row->cnt);
            }
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

        foreach ($groups as $group) {
            if ($group === 'Self') {
                // Self: only self assessment
                $sql = $wpdb->prepare(
                    "SELECT AVG(rating) FROM $responses
                    WHERE assessment_id = %d AND assessee_id = %d AND assessor_id = %d",
                    $assessment_id, $user_id, $user_id
                );
            } elseif ($group === 'Peers') {
                // Peers: All assessors in Peers group, excluding self
                $sql = $wpdb->prepare(
                    "SELECT AVG(a.rating)
                     FROM $responses a
                     INNER JOIN $relationships rel ON
                        rel.assessment_id = a.assessment_id
                        AND rel.assessee_id = a.assessee_id
                        AND rel.assessor_id = a.assessor_id
                     WHERE a.assessment_id = %d AND a.assessee_id = %d AND LOWER(rel.relationship_type) = 'peers' AND a.assessor_id != %d",
                    $assessment_id, $user_id, $user_id
                );
            } else {
                // Other groups (not self, not peers)
                $sql = $wpdb->prepare(
                    "SELECT AVG(a.rating)
                     FROM $responses a
                     INNER JOIN $relationships rel ON
                        rel.assessment_id = a.assessment_id
                        AND rel.assessee_id = a.assessee_id
                        AND rel.assessor_id = a.assessor_id
                     WHERE a.assessment_id = %d AND a.assessee_id = %d AND rel.relationship_type = %s AND a.assessor_id != %d",
                    $assessment_id, $user_id, $group, $user_id
                );
            }
            $avg = $wpdb->get_var($sql);
            $averages[$group] = is_null($avg) ? '' : round(floatval($avg), 2);
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
            foreach ($groups as $group) {
                if ($group === 'Self') {
                    $sql = $wpdb->prepare(
                        "SELECT AVG(rating)
                         FROM $responses
                         WHERE assessment_id = %d AND assessee_id = %d AND assessor_id = %d AND question_id = %d",
                        $assessment_id, $user_id, $user_id, $q->question_id
                    );
                } elseif ($group === 'Peers') {
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
                            AND LOWER(rel.relationship_type) = 'peers'
                            AND a.assessor_id != %d",
                        $assessment_id, $user_id, $q->question_id, $user_id
                    );
                } else {
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
                            AND rel.relationship_type = %s
                            AND a.assessor_id != %d",
                        $assessment_id, $user_id, $q->question_id, $group, $user_id
                    );
                }
                $val = $wpdb->get_var($sql);
                $row['group_values'][$group] = is_null($val) ? '' : round(floatval($val), 2);
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
            foreach ($groups as $group) {
                if ($group === 'Self') {
                    $sql = $wpdb->prepare(
                        "SELECT AVG(a.rating)
                         FROM $responses a
                         INNER JOIN $qtable q ON a.question_id = q.id
                         WHERE a.assessment_id = %d
                            AND a.assessee_id = %d
                            AND q.section_id = %d
                            AND a.assessor_id = %d",
                        $assessment_id, $user_id, $section->section_id, $user_id
                    );
                } elseif ($group === 'Peers') {
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
                            AND LOWER(rel.relationship_type) = 'peers'
                            AND a.assessor_id != %d",
                        $assessment_id, $user_id, $section->section_id, $user_id
                    );
                } else {
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
                            AND rel.relationship_type = %s
                            AND a.assessor_id != %d",
                        $assessment_id, $user_id, $section->section_id, $group, $user_id
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

    // 6. All individual responses for this user/assessment (ANONYMOUS: no assessor name/id)
    public function get_all_individual_responses($assessment_id, $user_id) {
        global $wpdb;
        $responses = $wpdb->prefix . "360_assessment_responses";
        $qtable = $wpdb->prefix . "360_questions";
        $sectable = $wpdb->prefix . "360_sections";
        $toptable = $wpdb->prefix . "360_topics";
        $relationships = $wpdb->prefix . "360_user_relationships";

        // Self responses
        $self_sql = $wpdb->prepare(
            "SELECT
                t.name as topic,
                s.name as section,
                q.question_text as question,
                'Self' as `group`,
                a.rating as value,
                a.comment as comment
            FROM $responses a
            INNER JOIN $qtable q ON a.question_id = q.id
            INNER JOIN $sectable s ON q.section_id = s.id
            INNER JOIN $toptable t ON s.topic_id = t.id
            WHERE a.assessment_id = %d AND a.assessee_id = %d AND a.assessor_id = %d
            ORDER BY t.display_order, s.display_order, q.display_order",
            $assessment_id, $user_id, $user_id
        );
        $self_results = $wpdb->get_results($self_sql, ARRAY_A);

        // Others
        $other_sql = $wpdb->prepare(
            "SELECT
                t.name as topic,
                s.name as section,
                q.question_text as question,
                CASE WHEN LOWER(rel.relationship_type) = 'peers' THEN 'Peers' ELSE rel.relationship_type END as `group`,
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
            WHERE a.assessment_id = %d AND a.assessee_id = %d AND a.assessor_id != %d
            ORDER BY t.display_order, s.display_order, q.display_order, rel.relationship_type",
            $assessment_id, $user_id, $user_id
        );
        $other_results = $wpdb->get_results($other_sql, ARRAY_A);

        // Merge, Self always first if present
        return array_merge($self_results, $other_results);
    }
    
    //7. For results summary
    public function get_section_averages_by_group($assessment_id, $user_id) {
        global $wpdb;
        $sectable = $wpdb->prefix . "360_sections";
        $qtable = $wpdb->prefix . "360_questions";
        $responses = $wpdb->prefix . "360_assessment_responses";
        $relationships = $wpdb->prefix . "360_user_relationships";

        // Get all sections
        $sections = $wpdb->get_results("SELECT id, name FROM $sectable WHERE status = 'active' ORDER BY display_order");
        $groups = $this->get_user_groups_for_assessment($assessment_id, $user_id);

        $result = [];
        foreach ($groups as $group) {
            $row = ['group_name' => $group, 'sections' => []];
            foreach ($sections as $section) {
                if ($group === 'Self') {
                    // Self: only self assessment (assessor_id == assessee_id)
                    $sql = $wpdb->prepare(
                        "SELECT AVG(a.rating)
                         FROM $responses a
                         INNER JOIN $qtable q ON a.question_id = q.id
                         WHERE a.assessment_id = %d AND a.assessee_id = %d AND q.section_id = %d AND a.assessor_id = %d",
                        $assessment_id, $user_id, $section->id, $user_id
                    );
                } elseif ($group === 'Peers') {
                    // Peers: all assessors in Peers, exclude self
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
                            AND LOWER(rel.relationship_type) = 'peers'
                            AND a.assessor_id != %d",
                        $assessment_id, $user_id, $section->id, $user_id
                    );
                } else {
                    // Other groups: exclude self
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
                            AND rel.relationship_type = %s
                            AND a.assessor_id != %d",
                        $assessment_id, $user_id, $section->id, $group, $user_id
                    );
                }
                $avg = $wpdb->get_var($sql);
                $row['sections'][] = [
                    'section_name' => $section->name,
                    'average' => is_null($avg) ? '' : round(floatval($avg), 2)
                ];
            }
            $result[] = $row;
        }
        return $result;
    }
}