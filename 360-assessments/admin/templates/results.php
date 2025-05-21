<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1 class="wp-heading-inline">Assessment Results</h1>

    <?php
    $assessment_manager = Assessment_360_Assessment_Manager::get_instance();
    $user_manager = Assessment_360_User_Manager::get_instance();
    
    // Get all assessments
    $assessments = $assessment_manager->get_all_assessments();
    
    // Get selected assessment
    $selected_assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
    $selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    
    if ($selected_assessment_id) {
        $assessment = $assessment_manager->get_assessment($selected_assessment_id);
        $assessment_users = $assessment_manager->get_assessment_users($selected_assessment_id);
    }
    ?>

    <!-- Assessment Selection -->
    <div class="results-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="assessment-360-results">
            
            <select name="assessment_id" onchange="this.form.submit()">
                <option value="">Select Assessment</option>
                <?php foreach ($assessments as $a): ?>
                    <option value="<?php echo esc_attr($a->id); ?>" 
                            <?php selected($selected_assessment_id == $a->id); ?>>
                        <?php echo esc_html($a->name); ?> 
                        (<?php echo date('M j, Y', strtotime($a->start_date)); ?> - 
                         <?php echo date('M j, Y', strtotime($a->end_date)); ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if ($selected_assessment_id && !empty($assessment_users)): ?>
                <select name="user_id" onchange="this.form.submit()">
                    <option value="">Select User</option>
                    <?php foreach ($assessment_users as $user): ?>
                        <option value="<?php echo esc_attr($user->id); ?>" 
                                <?php selected($selected_user_id == $user->id); ?>>
                            <?php echo esc_html($user->first_name . ' ' . $user->last_name); ?>
                            (<?php echo esc_html($user->position_name ?? '—'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($selected_assessment_id && !$selected_user_id): ?>
        <!-- Users Overview Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col">User</th>
                    <th scope="col">Position</th>
                    <th scope="col">Group</th>
                    <th scope="col">Progress</th>
                    <th scope="col">Average Rating</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assessment_users as $user): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($user->first_name . ' ' . $user->last_name); ?></strong>
                            <br>
                            <span class="description"><?php echo esc_html($user->email); ?></span>
                        </td>
                        <td><?php echo esc_html($user->position_name ?? '—'); ?></td>
                        <td><?php echo esc_html($user->group_name ?? '—'); ?></td>
                        <td>
                            <div class="progress-wrapper">
                                <?php 
                                $completion_rate = $user->completion_rate ?? 0;
                                ?>
                                <div class="progress">
                                    <div class="progress-bar" 
                                         role="progressbar" 
                                         style="width: <?php echo esc_attr($completion_rate); ?>%"
                                         aria-valuenow="<?php echo esc_attr($completion_rate); ?>"
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
                                <span class="progress-text">
                                    <?php 
                                    echo esc_html($user->completed_assessments) . ' of ' . 
                                         esc_html($user->total_assessors) . ' completed';
                                    ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <?php if (isset($user->average_rating)): ?>
                                <div class="rating-display">
                                    <span class="rating-number">
                                        <?php echo number_format($user->average_rating, 2); ?>
                                    </span>
                                    <?php if (isset($user->ratings_distribution)): ?>
                                        <span class="rating-distribution">
                                            <?php foreach ($user->ratings_distribution as $rating => $count): ?>
                                                <span class="rating-bar" title="<?php echo $count; ?> ratings of <?php echo $rating; ?>">
                                                    <span class="bar-fill" style="height: <?php 
                                                        echo $user->total_responses > 0 
                                                            ? ($count / $user->total_responses) * 100 
                                                            : 0; 
                                                    ?>%"></span>
                                                    <span class="bar-label"><?php echo $rating; ?></span>
                                                </span>
                                            <?php endforeach; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg([
                                'assessment_id' => $selected_assessment_id,
                                'user_id' => $user->id
                            ])); ?>" class="button button-small">View Details</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif ($selected_assessment_id && $selected_user_id): 
        $results = $assessment_manager->get_assessment_results($selected_assessment_id, $selected_user_id);
        $user = $user_manager->get_user($selected_user_id);
        
        if (!$user || !$assessment) {
            echo '<div class="notice notice-error"><p>Invalid assessment or user.</p></div>';
            return;
        }
    ?>
        <div class="results-container">
            <!-- Results Header -->
            <div class="results-header">
                <h2>Results for <?php echo esc_html($user->first_name . ' ' . $user->last_name); ?></h2>
                <div class="assessment-info">
                    <p>
                        <strong>Assessment:</strong> <?php echo esc_html($assessment->name); ?><br>
                        <strong>Position:</strong> <?php echo esc_html($user->position_name ?? '—'); ?><br>
                        <strong>Period:</strong> 
                        <?php echo date('F j, Y', strtotime($assessment->start_date)); ?> - 
                        <?php echo date('F j, Y', strtotime($assessment->end_date)); ?>
                    </p>
                </div>

                <!-- Export Button -->
                <div class="export-actions">
                    <a href="<?php echo wp_nonce_url(
                        add_query_arg(
                            array(
                                'page' => 'assessment-360-results',
                                'export' => 'pdf',
                                'assessment_id' => $selected_assessment_id,
                                'user_id' => $selected_user_id
                            ),
                            admin_url('admin.php')
                        ),
                        'export_results'
                    ); ?>" class="button button-primary">
                        Export to PDF
                    </a>
                </div>
            </div>

            <!-- Results Content -->
            <?php if (!empty($results)): ?>
                <div class="results-content">
                    <?php 
                    // Convert object to array if needed
                    $results_data = is_object($results) ? get_object_vars($results) : $results;

                    foreach ($results_data as $topic): ?>
                        <div class="topic-results">
                            <h3><?php echo esc_html($topic->name ?? 'Unnamed Topic'); ?></h3>

                            <?php if (isset($topic->questions) && is_array($topic->questions)): ?>
                                <div class="section-results">
                                    <h4>
                                        Questions
                                        <?php if (isset($topic->average_rating)): ?>
                                            <span class="section-average">
                                                Average Rating: <?php echo number_format($topic->average_rating, 1); ?>
                                            </span>
                                        <?php endif; ?>
                                    </h4>

                                    <div class="questions-grid">
                                        <?php foreach ($topic->questions as $question): ?>
                                            <div class="question-result">
                                                <div class="question-text">
                                                    <?php echo esc_html($question->text ?? $question->question_text ?? ''); ?>
                                                </div>
                                                <div class="rating-display">
                                                    <div class="rating-stars">
                                                        <?php 
                                                        $rating = $question->rating ?? $question->average_rating ?? 0;
                                                        for ($i = 1; $i <= 5; $i++): 
                                                        ?>
                                                            <span class="star <?php echo $i <= $rating ? 'filled' : ''; ?>">
                                                                ★
                                                            </span>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <span class="rating-value">
                                                        <?php echo number_format($rating, 1); ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($question->comments)): ?>
                                                    <div class="comments-section">
                                                        <h5>Comments:</h5>
                                                        <?php foreach ($question->comments as $comment): ?>
                                                            <div class="question-comment">
                                                                <?php echo esc_html($comment->comment ?? $comment); ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p>No results available for this assessment yet.</p>
                </div>
            <?php endif; ?>

    <?php else: ?>
        <div class="notice notice-info">
            <p>Please select an assessment to view results.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.results-filters {
    margin: 20px 0;
    padding: 15px;
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.results-filters select {
    margin-right: 15px;
    min-width: 200px;
}

.results-container {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.results-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.assessment-info {
    margin: 15px 0;
    color: #666;
}

.export-actions {
    margin-top: 15px;
}

/* Progress Bar */
.progress-wrapper {
    width: 150px;
}

.progress {
    height: 8px;
    background-color: #f0f0f1;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 5px;
}

.progress-bar {
    background-color: #2271b1;
    height: 100%;
    transition: width .3s ease;
}

.progress-text {
    font-size: 12px;
    color: #50575e;
}

/* Rating Display */
.rating-display {
    display: flex;
    align-items: center;
    gap: 10px;
}

.rating-number {
    font-size: 16px;
    font-weight: 600;
    min-width: 45px;
}

.rating-distribution {
    display: flex;
    gap: 3px;
    height: 30px;
    align-items: flex-end;
}

.rating-bar {
    width: 20px;
    height: 100%;
    position: relative;
    background: #f0f0f1;
    border-radius: 2px;
    overflow: hidden;
}

.bar-fill {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    background: #2271b1;
    transition: height .3s ease;
}

.bar-label {
    position: absolute;
    bottom: -20px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 10px;
    color: #50575e;
}

/* Results Content */
.topic-results {
    margin-bottom: 40px;
}

.section-results {
    margin: 20px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 4px;
}

.section-average {
    float: right;
    color: #666;
    font-size: 0.9em;
    font-weight: normal;
}

.questions-grid {
    display: grid;
    gap: 20px;
}

.question-result {
    padding: 15px;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}

.question-text {
    margin-bottom: 10px;
}

.rating-stars {
    display: flex;
    gap: 2px;
}

.star {
    color: #ddd;
    font-size: 18px;
}

.star.filled {
    color: #ffc107;
}

.rating-value {
    font-weight: 500;
    color: #666;
}

.question-comment {
    margin-top: 10px;
    padding: 10px;
    background: #f8f9fa;
    border-left: 3px solid #0073aa;
    font-style: italic;
    color: #666;
}

/* Action Buttons */
.button-small {
    min-width: 80px;
    text-align: center;
}

/* Description Text */
.description {
    color: #666;
    font-size: 12px;
}

@media print {
    .results-filters,
    .export-actions,
    #adminmenumain,
    #wpadminbar {
        display: none !important;
    }

    .results-container {
        border: none;
        box-shadow: none;
    }
}
    .comments-section {
    margin-top: 15px;
    border-top: 1px solid #eee;
    padding-top: 10px;
}

.comments-section h5 {
    font-size: 14px;
    margin: 0 0 10px 0;
    color: #666;
}

.question-comment {
    margin-bottom: 8px;
    padding: 8px 12px;
    background: #f8f9fa;
    border-left: 3px solid #0073aa;
    font-style: italic;
    color: #666;
    font-size: 13px;
}

.question-comment:last-child {
    margin-bottom: 0;
}

/* Rating Stars Enhancement */
.rating-stars {
    display: flex;
    gap: 2px;
    margin-right: 10px;
}

.star {
    color: #ddd;
    font-size: 18px;
    transition: color 0.2s ease;
}

.star.filled {
    color: #ffc107;
}

.rating-value {
    font-weight: 600;
    color: #444;
    min-width: 35px;
    text-align: center;
}

/* Questions Grid Enhancement */
.questions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.question-result {
    background: #fff;
    border: 1px solid #e2e4e7;
    border-radius: 4px;
    padding: 15px;
    transition: box-shadow 0.2s ease;
}

.question-result:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.question-text {
    font-size: 14px;
    line-height: 1.4;
    margin-bottom: 12px;
    color: #23282d;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize tooltips
    $('.rating-bar').tooltip({
        position: {
            my: "center bottom-20",
            at: "center top"
        }
    });
});
</script>
