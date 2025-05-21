<?php
if (!defined('ABSPATH')) exit;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    wp_redirect(home_url('/360-assessment-login/'));
    exit;
}

try {
    // Get parameters
    $assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
    $assessee_id = isset($_GET['assessee_id']) ? intval($_GET['assessee_id']) : 0;
    $is_self_assessment = isset($_GET['self_assessment']) ? (bool)$_GET['self_assessment'] : false;

    if (WP_DEBUG) {
        error_log('Loading assessment form');
        error_log("Assessment ID: $assessment_id");
        error_log("Assessee ID: $assessee_id");
        error_log("Self Assessment: " . ($is_self_assessment ? 'Yes' : 'No'));
    }

    // Validate parameters
    if (!$assessment_id || !$assessee_id) {
        throw new Exception('Invalid assessment parameters');
    }

    // Initialize managers
    $assessment_manager = Assessment_360_Assessment_Manager::get_instance();
    $user_manager = Assessment_360_User_Manager::get_instance();

    // Get current user (assessor)
    $assessor_id = $_SESSION['user_id'];
    $assessor = $user_manager->get_user($assessor_id);

    if (!$assessor) {
        throw new Exception('Invalid assessor');
    }

    // Get assessee
    $assessee = $user_manager->get_user($assessee_id);
    if (!$assessee) {
        throw new Exception('Invalid assessee');
    }

    // Check if assessment already completed
    if ($assessment_manager->is_assessment_completed($assessor_id, $assessee_id)) {
        wp_redirect(add_query_arg(
            'message', 
            'already_completed', 
            home_url('/360-assessment-dashboard/')
        ));
        exit;
    }

    // Get assessment questions
    $questions = $assessment_manager->get_assessment_questions($assessee->position_id);
    
    if (empty($questions)) {
        throw new Exception('No questions found for this assessment');
    }

    // Group questions by section
    $sections = [];
    foreach ($questions as $question) {
        if (!isset($sections[$question->section_id])) {
            $sections[$question->section_id] = [
                'name' => $question->section_name,
                'questions' => []
            ];
        }
        $sections[$question->section_id]['questions'][] = $question;
    }

    // Get the header
    get_header('360-assessment');
?>

<div class="assessment-container">
    <div class="assessment-header">
        <h1><?php echo $is_self_assessment ? 'Self Assessment' : 'Assessment Form'; ?></h1>
        <div class="assessment-info">
            <p><strong>Assessing:</strong> <?php echo esc_html($assessee->first_name . ' ' . $assessee->last_name); ?></p>
            <p><strong>Position:</strong> <?php echo esc_html($assessee->position_name); ?></p>
        </div>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($_GET['error']); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" 
          action="<?php echo esc_url(admin_url('admin-post.php')); ?>" 
          class="assessment-form"
          data-assessment-id="<?php echo esc_attr($assessment_id); ?>"
          data-enable-drafts="1">
        
        <?php wp_nonce_field('submit_assessment', 'assessment_nonce'); ?>
        <input type="hidden" name="action" value="submit_assessment">
        <input type="hidden" name="assessment_id" value="<?php echo esc_attr($assessment_id); ?>">
        <input type="hidden" name="assessor_id" value="<?php echo esc_attr($assessor_id); ?>">
        <input type="hidden" name="assessee_id" value="<?php echo esc_attr($assessee_id); ?>">
        <input type="hidden" name="self_assessment" value="<?php echo $is_self_assessment ? '1' : '0'; ?>">

        <?php foreach ($sections as $section_id => $section): ?>
            <div class="assessment-section">
                <h2><?php echo esc_html($section['name']); ?></h2>
                
                <?php foreach ($section['questions'] as $question): ?>
                    <div class="question-container" data-question-id="<?php echo esc_attr($question->id); ?>">
                        <div class="question-text">
                            <?php echo esc_html($question->question_text); ?>
                        </div>
                        <div class="rating-container">
                            <div class="star-rating" data-question-id="<?php echo esc_attr($question->id); ?>">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" 
                                           id="rating-<?php echo esc_attr($question->id); ?>-<?php echo $i; ?>" 
                                           name="ratings[<?php echo esc_attr($question->id); ?>]" 
                                           value="<?php echo $i; ?>" 
                                           required>
                                    <label for="rating-<?php echo esc_attr($question->id); ?>-<?php echo $i; ?>">
                                        ★
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php if ($question->has_comment_box): ?>
                            <div class="comment-container">
                                <textarea name="comments[<?php echo esc_attr($question->id); ?>]" 
                                          placeholder="Additional comments (optional)"
                                          rows="2"
                                          data-question-id="<?php echo esc_attr($question->id); ?>"
                                          maxlength="500"></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <div class="form-actions">
            <button type="submit" class="button button-primary">Submit Assessment</button>
            <a href="<?php echo esc_url(home_url('/360-assessment-dashboard/')); ?>" class="button">Cancel</a>
        </div>
    </form>
</div>

<?php
    get_footer('360-assessment');

} catch (Exception $e) {
    if (WP_DEBUG) {
        error_log('Error in assessment form: ' . $e->getMessage());
    }
    wp_redirect(add_query_arg(
        'error', 
        urlencode($e->getMessage()), 
        home_url('/360-assessment-dashboard/')
    ));
    exit;
}
?>
