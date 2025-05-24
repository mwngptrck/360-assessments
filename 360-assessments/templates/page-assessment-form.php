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

?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>360° Assessment</title>
    <?php wp_head(); ?>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <?php 
    // Enqueue the form CSS
    wp_enqueue_style('assessment-360-form');
    ?>
</head>
<body class="assessment-page">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container">
            <?php 
            $logo_url = get_option('assessment_360_organization_logo');
            $org_name = get_option('assessment_360_organization_name', 'Organization Name');
            ?>
            <a class="navbar-brand" href="<?php echo home_url('/360-assessment-dashboard/'); ?>">
                <?php if ($logo_url): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($org_name); ?>" height="40">
                <?php else: ?>
                    <?php echo esc_html($org_name); ?>
                <?php endif; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i>
                                <?php 
                                $current_user = assessment_360_get_current_user();
                                echo esc_html($current_user->first_name . ' ' . $current_user->last_name);
                                ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="<?php echo home_url('/360-assessment-dashboard/'); ?>">
                                        <i class="bi bi-speedometer2"></i> Dashboard
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php 
                                        echo wp_nonce_url(
                                            add_query_arg(['action' => 'logout'], home_url('/')),
                                            'assessment_360_logout'
                                        ); 
                                    ?>">
                                        <i class="bi bi-box-arrow-right"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="content-wrapper">
        <div class="container">
            <div class="assessment-container">
                
                <?php if (isset($_GET['message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo esc_html($_GET['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
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

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="assessment-form">

                    <input type="hidden" name="action" value="submit_assessment">
                    <input type="hidden" name="assessment_id" value="<?php echo esc_attr($assessment_id); ?>">
                    <input type="hidden" name="assessor_id" value="<?php echo esc_attr($assessor_id); ?>">
                    <input type="hidden" name="assessee_id" value="<?php echo esc_attr($assessee_id); ?>">
                    <input type="hidden" name="self_assessment" value="<?php echo $is_self_assessment ? '1' : '0'; ?>">
                    <?php wp_nonce_field('submit_assessment', 'assessment_nonce'); ?>

                    <?php foreach ($sections as $section_id => $section): ?>
                        <div class="assessment-section">
                            <h2><?php echo esc_html($section['name']); ?></h2>

                            <?php foreach ($section['questions'] as $question): ?>
                                <div class="question-container" data-question-id="<?php echo esc_attr($question->id); ?>">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="question-text">
                                                <?php echo esc_html($question->question_text); ?>
                                            </div>
                                        </div>
                                        <div class="col-md-9">
                                            <div class="rating-container">
                                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                                    <input type="radio" 
                                                           id="rating_<?php echo $question->id; ?>_<?php echo $i; ?>" 
                                                           name="rating[<?php echo $question->id; ?>]" 
                                                           value="<?php echo $i; ?>" 
                                                           required>
                                                    <label for="rating_<?php echo $question->id; ?>_<?php echo $i; ?>">
                                                        <?php echo $i; ?>
                                                    </label>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <!-- Comment Box (if enabled) -->
                                        <?php if ($question->has_comment_box): ?>
                                            <div class="comment-container">
                                                <label for="comment_<?php echo $question->id; ?>">Comments (Optional):</label>
                                                <textarea id="comment_<?php echo $question->id; ?>" 
                                                          name="comment[<?php echo $question->id; ?>]" 
                                                          rows="3" 
                                                          class="form-control"></textarea>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"
                            onclick="return confirm('Are you sure you want to submit your assessment? Once submitted, you will not be able to edit your responses.');">
                            Submit Assessment
                        </button>
                        <a href="<?php echo esc_url(home_url('/360-assessment-dashboard/')); ?>" class="button">Cancel</a>
                    </div>
                </form>
            </div>

            <?php if (!defined('ABSPATH')) exit; ?>
        </div>
    </div><!-- .content-wrapper -->
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.getElementById('assessment-form').addEventListener('submit', function(e) {
        // Check if all required ratings are selected
        const questions = document.querySelectorAll('.question-container');
        let hasError = false;

        questions.forEach(question => {
            const ratings = question.querySelectorAll('input[type="radio"]:checked');
            if (ratings.length === 0) {
                hasError = true;
                question.classList.add('error');
            } else {
                question.classList.remove('error');
            }
        });

        if (hasError) {
            e.preventDefault();
            alert('Please provide ratings for all questions.');
            return false;
        }

        // Disable submit button to prevent double submission
        const submitButton = this.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.innerHTML = 'Submitting...';
    });
    </script>

    <?php wp_footer(); ?>
</body>
</html>

<?php
    // Load custom footer
    //require_once ASSESSMENT_360_PLUGIN_DIR . 'templates/footer-assessment.php';

} catch (Exception $e) {
    wp_redirect(add_query_arg(
        'error', 
        urlencode($e->getMessage()), 
        home_url('/360-assessment-dashboard/')
    ));
    exit;
}
?>