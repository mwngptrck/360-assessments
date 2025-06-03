<?php
if (!defined('ABSPATH')) exit;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    wp_redirect(home_url('/360-assessment-login/'));
    exit;
}

wp_enqueue_script('jquery');


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
                    <?php echo esc_html((string)$org_name); ?>
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
                                echo esc_html((string)$current_user->first_name . ' ' . $current_user->last_name);
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
    <div class="content-wrapper mt-5 pt-4">
        <div class="container py-4">
            <!-- Assessment Header -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="card-title mb-1">
                                <?php echo $is_self_assessment ? 'Self Assessment' : 'Assessment Form'; ?>
                            </h4>
                            <p class="text-muted mb-0">
                                Assessing: <strong><?php echo esc_html($assessee->first_name . ' ' . $assessee->last_name); ?></strong>
                                <?php if ($is_self_assessment): ?>
                                    <span class="badge bg-info ms-2">Self Assessment</span>
                                <?php endif; ?>
                            </p>
                            <p class="text-muted mb-0">
                                Position: <strong><?php echo esc_html($assessee->position_name); ?></strong>
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar" role="progressbar" style="width: 0%;" 
                                     aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                </div>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <span id="completed-questions">0</span> of <span id="total-questions"><?php echo count($questions); ?></span> questions answered
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (isset($_GET['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo esc_html($_GET['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo esc_html($_GET['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Rating Scale Guide -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>Rating Scale Guide
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php
                        $ratings = [
                            5 => ['Exceptional', 'Consistently exceeds all expectations', 'success'],
                            4 => ['Above Average', 'Frequently exceeds expectations', 'info'],
                            3 => ['Meets Expectations', 'Consistently meets job requirements', 'primary'],
                            2 => ['Needs Improvement', 'Sometimes falls short of requirements', 'warning'],
                            1 => ['Unsatisfactory', 'Consistently falls short of requirements', 'danger']
                        ];
                        foreach ($ratings as $rating => $details): ?>
                            <div class="col-md-4 col-lg">
                                <div class="p-3 border rounded bg-light h-100">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="badge bg-<?php echo $details[2]; ?> me-2"><?php echo $rating; ?></span>
                                        <strong><?php echo $details[0]; ?></strong>
                                    </div>
                                    <small class="text-muted"><?php echo $details[1]; ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Assessment Form -->
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="assessment-form">
                <?php wp_nonce_field('submit_assessment', 'assessment_nonce'); ?>
                <input type="hidden" name="action" value="submit_assessment">
                <input type="hidden" name="assessment_id" value="<?php echo esc_attr($assessment_id); ?>">
                <input type="hidden" name="assessor_id" value="<?php echo esc_attr($assessor_id); ?>">
                <input type="hidden" name="assessee_id" value="<?php echo esc_attr($assessee_id); ?>">
                <input type="hidden" name="self_assessment" value="<?php echo $is_self_assessment ? '1' : '0'; ?>">

                <?php
                $current_topic = null;
                foreach ($questions as $question):
                    // Get topic name from section (assuming sections are organized by topics)
                    $topic_name = $question->topic_name ?? 'General';

                    // Start new topic card if topic changes
                    if ($topic_name !== $current_topic):
                        if ($current_topic !== null) {
                            echo '</div></div></div>'; // Close previous topic card
                        }
                        $current_topic = $topic_name;
                ?>
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0"><?php echo esc_html($topic_name); ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                <?php endif; ?>

                <!-- Question Card -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">
                                    <?php echo esc_html($question->question_text); ?>
                                    <?php if ($question->is_mandatory): ?>
                                        <span class="text-danger">*</span>
                                    <?php endif; ?>
                                </label>

                                <div class="btn-group w-100" role="group">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" 
                                               class="btn-check" 
                                               name="rating[<?php echo $question->id; ?>]" 
                                               id="rating_<?php echo $question->id; ?>_<?php echo $i; ?>" 
                                               value="<?php echo $i; ?>"
                                               required> <!-- Make sure this is here -->
                                        <label class="btn btn-outline-primary" 
                                               for="rating_<?php echo $question->id; ?>_<?php echo $i; ?>">
                                            <?php echo $i; ?>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <?php if ($question->has_comment_box): ?>
                                <div class="mt-3">
                                    <label class="form-label">Comments</label>
                                    <textarea class="form-control" 
                                              name="comment[<?php echo $question->id; ?>]" 
                                              rows="3" 
                                              placeholder="Add your comments here..."></textarea>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>
                </div></div></div> <!-- Close last topic card -->

                <!-- Submit Section -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirmSubmit" required>
                                <label class="form-check-label" for="confirmSubmit">
                                    I confirm that my assessment is complete and accurate
                                </label>
                            </div>
                            <div>
                                <a href="<?php echo esc_url(home_url('/360-assessment-dashboard/')); ?>" 
                                   class="btn btn-outline-secondary me-2">Cancel</a>
                                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                    <i class="bi bi-check-circle me-1"></i>Submit Assessment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
<style>
/* General Styles */
body {
    background-color: #f8f9fa;
    padding-top: 70px;
}

.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
    margin-bottom: 1rem;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0,0,0,0.125);
}
.card.error {
    border: 1px solid #dc3545;
    box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
}

.card.error .card-body {
    background-color: rgba(220, 53, 69, 0.05);
}

.progress-bar {
    transition: width 0.3s ease;
}
/* Rating Buttons */
.btn-check:checked + .btn-outline-primary {
    background-color: #0d6efd !important;
    color: #fff !important;
}

.btn-group {
    flex-wrap: nowrap;
}

.btn-group .btn {
    flex: 1;
    padding: 0.5rem;
}

/* Progress Bar */
.progress {
    background-color: #e9ecef;
    border-radius: 0.25rem;
}

.progress-bar {
    transition: width 0.3s ease;
}

/* Form Elements */
.form-check-input {
    margin-top: 0;
    margin-right: 10px;
    padding-right: 5px;
}
.form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

textarea.form-control {
    min-height: 100px;
}

/* Question Cards */
.question-container {
    transition: all 0.3s ease;
}

.question-container.error {
    border: 1px solid #dc3545;
    border-radius: 0.25rem;
    padding: 1rem;
    margin: -1rem;
    background-color: rgba(220, 53, 69, 0.05);
}

/* Rating Scale Guide */
.rating-guide .badge {
    font-size: 1rem;
    padding: 0.5rem 1rem;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .btn-group {
        flex-direction: row !important;
    }
    
    .btn-group .btn {
        padding: 0.375rem 0.75rem;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    .container {
        padding: 0 1rem;
    }
}

/* Animations */
.alert {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- First, make sure jQuery is properly loaded -->
<?php wp_enqueue_script('jquery'); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get elements
    const form = document.getElementById('assessment-form');
    const submitBtn = document.getElementById('submitBtn');
    const confirmCheck = document.getElementById('confirmSubmit');
    const progressBar = document.querySelector('.progress-bar');
    const completedCounter = document.getElementById('completed-questions');
    const totalCounter = document.getElementById('total-questions');
    const radioButtons = document.querySelectorAll('input[type="radio"]');

    // Check if all questions are answered
    function areAllQuestionsAnswered() {
        const questionGroups = {};
        
        radioButtons.forEach(radio => {
            const name = radio.getAttribute('name');
            questionGroups[name] = questionGroups[name] || false;
            if (radio.checked) {
                questionGroups[name] = true;
            }
        });
        
        return !Object.values(questionGroups).includes(false);
    }

    // Update form state
    function updateFormState() {
        const allAnswered = areAllQuestionsAnswered();
        const isConfirmed = confirmCheck.checked;
        
        // Update submit button
        submitBtn.disabled = !(allAnswered && isConfirmed);
        
        // Count unique question groups
        const questionGroups = new Set();
        const answeredGroups = new Set();
        
        radioButtons.forEach(radio => {
            const name = radio.getAttribute('name');
            questionGroups.add(name);
            if (document.querySelector(`input[name="${name}"]:checked`)) {
                answeredGroups.add(name);
            }
        });
        
        // Update progress
        const totalQuestions = questionGroups.size;
        const answeredQuestions = answeredGroups.size;
        const percentage = (answeredQuestions / totalQuestions) * 100;
        
        progressBar.style.width = percentage + '%';
        progressBar.setAttribute('aria-valuenow', percentage);
        progressBar.classList.remove('bg-danger', 'bg-warning', 'bg-success');
        progressBar.classList.add(
            percentage < 50 ? 'bg-danger' : 
            percentage < 80 ? 'bg-warning' : 'bg-success'
        );
        
        completedCounter.textContent = answeredQuestions;
        totalCounter.textContent = totalQuestions;
    }

    // Event Listeners
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            const card = this.closest('.card');
            if (card) card.classList.remove('error');
            updateFormState();
        });
    });

    confirmCheck.addEventListener('change', updateFormState);

    // Form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Check all questions are answered
        if (!areAllQuestionsAnswered()) {
            alert('Please answer all questions.');
            
            // Highlight unanswered questions
            radioButtons.forEach(radio => {
                const name = radio.getAttribute('name');
                if (!document.querySelector(`input[name="${name}"]:checked`)) {
                    const card = radio.closest('.card');
                    if (card) card.classList.add('error');
                }
            });
            
            return false;
        }

        // Check confirmation
        if (!confirmCheck.checked) {
            alert('Please confirm your assessment is complete and accurate.');
            return false;
        }

        // Final confirmation
        if (confirm('Are you sure you want to submit your assessment? This action cannot be undone.')) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Submitting...';
            form.submit();
        }
    });

    // Initialize form state
    updateFormState();
});
</script>
</body>
</html>