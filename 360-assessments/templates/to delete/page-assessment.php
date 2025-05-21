<?php
if (!defined('ABSPATH')) exit;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    wp_redirect(home_url('/360-assessment-login/'));
    exit;
}

add_action('wp_enqueue_scripts', function() {
    if (!is_page('360-assessment')) return;

    wp_enqueue_style(
        'assessment-360-form', 
        ASSESSMENT_360_PLUGIN_URL . 'public/css/form.css',
        array(),
        assessment_360_asset_version('public/css/form.css')
    );

    wp_enqueue_script(
        'assessment-360-form',
        ASSESSMENT_360_PLUGIN_URL . 'public/js/assessment-form.js',
        array('jquery'),
        assessment_360_asset_version('public/js/assessment-form.js'),
        true
    );

    wp_localize_script('assessment-360-form', 'assessment360Form', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('assessment_360_form'),
        'isDebug' => WP_DEBUG,
        'messages' => array(
            'unsavedChanges' => 'You have unsaved changes. Are you sure you want to leave?',
            'confirmSubmit' => 'Are you sure you want to submit this assessment? This action cannot be undone.',
            'loadDraft' => 'A saved draft was found. Would you like to load it?'
        )
    ));
});



try {
    // Initialize managers
    $assessment_manager = Assessment_360_Assessment_Manager::get_instance();
    $user_manager = Assessment_360_User_Manager::get_instance();

    // Get current user (assessor)
    $assessor_id = $_SESSION['user_id'];
    $assessor = $user_manager->get_user($assessor_id);

    if (!$assessor) {
        throw new Exception('Invalid assessor');
    }

    // Get assessee ID from URL
    $assessee_id = isset($_GET['assessee_id']) ? intval($_GET['assessee_id']) : 0;
    
    if (!$assessee_id) {
        throw new Exception('No assessee specified');
    }

    // Verify this is a valid assessment pair
    $assessee = $user_manager->get_user($assessee_id);
    if (!$assessee) {
        throw new Exception('Invalid assessee');
    }

    // Check if assessment already completed
    if ($assessment_manager->is_assessment_completed($assessor_id, $assessee_id)) {
        wp_redirect(add_query_arg('message', 'already_completed', home_url('/360-assessment-dashboard/')));
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

    if (WP_DEBUG) {
        error_log('Loading assessment page');
        error_log("Assessor ID: $assessor_id");
        error_log("Assessee ID: $assessee_id");
        error_log('Sections loaded: ' . count($sections));
    }

    get_header('360-assessment');
?>

<div class="assessment-container">
    <div class="assessment-header">
        <h1>Assessment Form</h1>
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

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="assessment-form">
        <?php wp_nonce_field('submit_assessment', 'assessment_nonce'); ?>
        <input type="hidden" name="action" value="submit_assessment">
        <input type="hidden" name="assessor_id" value="<?php echo esc_attr($assessor_id); ?>">
        <input type="hidden" name="assessee_id" value="<?php echo esc_attr($assessee_id); ?>">

        <?php foreach ($sections as $section_id => $section): ?>
            <div class="assessment-section">
                <h2><?php echo esc_html($section['name']); ?></h2>
                
                <?php foreach ($section['questions'] as $question): ?>
                    <div class="question-container">
                        <div class="question-text">
                            <?php echo esc_html($question->question_text); ?>
                        </div>
                        <div class="rating-container">
                            <div class="star-rating">
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
                                          rows="2"></textarea>
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
        error_log('Error in assessment page: ' . $e->getMessage());
    }
    wp_redirect(add_query_arg('error', urlencode($e->getMessage()), home_url('/360-assessment-dashboard/')));
    exit;
}
?>
