<?php if (!defined('ABSPATH')) exit; ?>

<?php
// Initialize managers
$assessment_manager = Assessment_360_Assessment::get_instance();
$user_manager = Assessment_360_User_Manager::get_instance();

// Get current action and assessment ID
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$assessment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get all assessments and separate by status
$assessments = $assessment_manager->get_all_assessments();
$active_assessments = array_filter($assessments, fn($a) => $a->status === 'active');
$draft_assessments = array_filter($assessments, fn($a) => $a->status === 'draft');
$completed_assessments = array_filter($assessments, fn($a) => $a->status === 'completed');
$deleted_assessments = array_filter($assessments, fn($a) => $a->status === 'deleted');
$visible_assessments = array_filter($assessments, function($a) {return $a->status !== 'deleted'; });

// Only one can be active
$has_active_assessment = !empty($active_assessments);
$active_assessment_id = $has_active_assessment ? current($active_assessments)->id : 0;

// Initialize variables for edit mode
$assessment = null;
$assessment_name = '';
$assessment_description = '';
$assessment_start_date = '';
$assessment_end_date = '';

// If editing, get assessment data
if ($action === 'edit' && $assessment_id > 0) {
    $assessment = $assessment_manager->get_assessment($assessment_id);
    if ($assessment) {
        $assessment_name = $assessment->name;
        $assessment_description = $assessment->description;
        $assessment_start_date = $assessment->start_date ?? '';
        $assessment_end_date = $assessment->end_date ?? '';
    }
}

// --- Add/Edit logic: always set status to draft on new ---
$error_message = '';
$input = [
    'name' => '',
    'description' => '',
    'start_date' => '',
    'end_date' => ''
];

$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_assessment') {
    // Use trim to avoid whitespace issues
    $input['name'] = trim(sanitize_text_field($_POST['name'] ?? ''));
    $input['description'] = trim(sanitize_textarea_field($_POST['description'] ?? ''));
    $input['start_date'] = trim(sanitize_text_field($_POST['start_date'] ?? ''));
    $input['end_date'] = trim(sanitize_text_field($_POST['end_date'] ?? ''));

    if (
        empty($input['name']) ||
        empty($input['start_date']) ||
        empty($input['end_date']) ||
        $input['start_date'] < $today
    ) {
        $error_message = 'Please fill in all required fields and ensure the start date is today or later.';
    } else {
        // Save logic: always set status to 'draft' on add
        if (empty($_POST['id'])) {
            // Create new assessment
            $result = $assessment_manager->create_assessment([
                'name' => $input['name'],
                'description' => $input['description'],
                'start_date' => $input['start_date'],
                'end_date' => $input['end_date'],
                'status' => 'draft'
            ]);
            if (is_wp_error($result)) {
                $error_message = $result->get_error_message();
            } else {
                // Redirect to listing with success
                wp_redirect(add_query_arg('message', 'Assessment created as draft.', admin_url('admin.php?page=assessment-360-assessments')));
                exit;
            }
        } else {
            // Edit existing assessment: do not change status here
            $result = $assessment_manager->update_assessment(intval($_POST['id']), [
                'name' => $input['name'],
                'description' => $input['description'],
                'start_date' => $input['start_date'],
                'end_date' => $input['end_date'],
            ]);
            if (is_wp_error($result)) {
                $error_message = $result->get_error_message();
            } else {
                wp_redirect(add_query_arg('message', 'Assessment updated successfully.', admin_url('admin.php?page=assessment-360-assessments')));
                exit;
            }
        }
    }
} elseif ($assessment) {
    $input['name'] = $assessment->name ?? '';
    $input['description'] = $assessment->description ?? '';
    $input['start_date'] = $assessment->start_date ?? '';
    $input['end_date'] = $assessment->end_date ?? '';
}
?>

<div class="wrap">
    <?php if ($action === 'new' || $action === 'edit'): ?>
        <!-- Add/Edit Form -->
        <div class="container-fluid p-0">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-clipboard-check me-2"></i>
                                <?php echo $assessment ? 'Edit Assessment' : 'Add New Assessment'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?php echo esc_html($error_message); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <form method="post" action="" id="assessment-form">
                                <?php wp_nonce_field('save_assessment_nonce'); ?>
                                <input type="hidden" name="action" value="save_assessment">
                                <?php if ($assessment_id): ?>
                                    <input type="hidden" name="id" value="<?php echo esc_attr($assessment_id); ?>">
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label for="name" class="form-label">Assessment Name <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="name" 
                                           name="name" 
                                           value="<?php echo esc_attr($input['name']); ?>" 
                                           required>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" 
                                              id="description" 
                                              name="description" 
                                              rows="3"><?php echo esc_textarea($input['description']); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                                        <input type="date"
                                               class="form-control"
                                               id="start_date"
                                               name="start_date"
                                               value="<?php echo esc_attr($input['start_date']); ?>"
                                               min="<?php echo esc_attr($today); ?>"
                                               required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                                        <input type="date"
                                               class="form-control"
                                               id="end_date"
                                               name="end_date"
                                               value="<?php echo esc_attr($input['end_date']); ?>"
                                               min="<?php echo esc_attr($input['start_date'] ?: $today); ?>"
                                               required>
                                    </div>
                                </div>
                                <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const startInput = document.getElementById('start_date');
                                    const endInput = document.getElementById('end_date');

                                    function updateEndDateMin() {
                                        endInput.min = startInput.value;
                                        // If end date is before new start date, clear it
                                        if (endInput.value && endInput.value < startInput.value) {
                                            endInput.value = '';
                                        }
                                    }

                                    startInput.addEventListener('change', updateEndDateMin);

                                    // On page load, set initial min for end date
                                    updateEndDateMin();
                                });
                                </script>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <?php echo $assessment ? 'Update Assessment' : 'Add Assessment'; ?>
                                    </button>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=assessment-360-assessments')); ?>" 
                                       class="btn btn-outline-secondary ms-2">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Assessments List -->
        <div class="container-fluid p-0">
            <div class="row">
                <div class="col-12">
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

                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-clipboard-check me-2"></i>Assessments
                            </h5>
                            <a href="<?php echo esc_url(add_query_arg(['action' => 'new'], admin_url('admin.php?page=assessment-360-assessments'))); ?>" 
                               class="btn btn-primary">
                                <i class="bi bi-plus-lg me-1"></i>Add New Assessment
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($assessments)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Status</th>
                                                <th>Start</th>
                                                <th>End</th>
                                                <th>Created</th>
                                                <th>Completed</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($visible_assessments as $assessment): ?>
                                                <?php
                                                    $complete_nonce = wp_create_nonce('complete_assessment_nonce_' . $assessment->id);
                                                    $deactivate_nonce = wp_create_nonce('deactivate_assessment_nonce_' . $assessment->id);
                                                ?>
                                                <tr>
                                                    <td>
                                                        <a href="<?php echo esc_url(add_query_arg([
                                                            'page' => 'assessment-360-assessments',
                                                            'action' => 'edit',
                                                            'id' => $assessment->id
                                                        ], admin_url('admin.php'))); ?>">
                                                            <?php echo esc_html($assessment->name); ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <?php if ($assessment->status === 'active'): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php elseif ($assessment->status === 'completed'): ?>
                                                            <span class="badge bg-secondary">Completed</span>
                                                        <?php elseif ($assessment->status === 'draft'): ?>
                                                            <span class="badge bg-warning text-dark">Draft</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-light text-dark">Deleted</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo esc_html($assessment->start_date ?? '—'); ?></td>
                                                    <td><?php echo esc_html($assessment->end_date ?? '—'); ?></td>
                                                    <td>
                                                        <?php if (isset($assessment->created_at) && $assessment->created_at) {
                                                            echo esc_html(date('M j, Y', strtotime($assessment->created_at)));
                                                        } else { echo '—'; } ?>
                                                    </td>
                                                    <td>
                                                        <?php if (isset($assessment->completed_at) && $assessment->completed_at) {
                                                            echo esc_html(date('M j, Y', strtotime($assessment->completed_at)));
                                                        } else { echo '—'; } ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <div class="btn-group">
                                                            <a href="<?php echo esc_url(add_query_arg([
                                                                'page' => 'assessment-360-assessments',
                                                                'action' => 'edit',
                                                                'id' => $assessment->id
                                                            ], admin_url('admin.php'))); ?>" 
                                                               class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-pencil"></i> Edit
                                                            </a>
                                                            
                                                            <?php
                                                            // Activate button: only show if no other active assessment
                                                            $activate_nonce = wp_create_nonce('activate_assessment_nonce_' . $assessment->id);
                                                            if (($assessment->status === 'draft' || $assessment->status === 'completed') && !$has_active_assessment): ?>
                                                                <button 
                                                                    type="button"
                                                                    class="btn btn-sm btn-outline-success activate-assessment"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#activateAssessmentModal"
                                                                    data-id="<?php echo esc_attr($assessment->id); ?>"
                                                                    data-name="<?php echo esc_attr($assessment->name); ?>"
                                                                    data-nonce="<?php echo esc_attr($activate_nonce); ?>"
                                                                >
                                                                    <i class="bi bi-play-circle"></i> Activate
                                                                </button>
                                                            <?php elseif ($assessment->status === 'draft' || $assessment->status === 'completed'): ?>
                                                                <button class="btn btn-sm btn-outline-secondary" disabled title="There is already an active assessment. Complete or deactivate it before activating another.">
                                                                    <i class="bi bi-play-circle"></i> Activate
                                                                </button>
                                                            <?php endif; ?>

                                                            <!-- Deactivate button: only for active -->
                                                            <?php if ($assessment->status === 'active'): ?>
                                                                <button 
                                                                    type="button"
                                                                    class="btn btn-sm btn-outline-warning deactivate-assessment"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#deactivateAssessmentModal"
                                                                    data-id="<?php echo esc_attr($assessment->id); ?>"
                                                                    data-name="<?php echo esc_attr($assessment->name); ?>"
                                                                    data-nonce="<?php echo esc_attr($deactivate_nonce); ?>"
                                                                >
                                                                    <i class="bi bi-arrow-left-circle"></i> Deactivate
                                                                </button>
                                                            <?php endif; ?>

                                                            <!-- Complete button: only for active -->
                                                            <?php if ($assessment->status === 'active'): ?>
                                                                <button 
                                                                    type="button"
                                                                    class="btn btn-sm btn-outline-info complete-assessment"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#completeAssessmentModal"
                                                                    data-id="<?php echo esc_attr($assessment->id); ?>"
                                                                    data-name="<?php echo esc_attr($assessment->name); ?>"
                                                                    data-nonce="<?php echo esc_attr($complete_nonce); ?>"
                                                                >
                                                                    <i class="bi bi-check-circle"></i> Complete
                                                                </button>
                                                            <?php endif; ?>

                                                            <!-- Delete button: only for non-active assessments -->
                                                            <?php if ($assessment->status !== 'active'): ?>
                                                                <button 
                                                                    type="button"
                                                                    class="btn btn-sm btn-outline-danger delete-assessment"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#deleteAssessmentModal"
                                                                    data-id="<?php echo esc_attr($assessment->id); ?>"
                                                                    data-name="<?php echo esc_attr($assessment->name); ?>"
                                                                >
                                                                    <i class="bi bi-trash"></i> Delete
                                                                </button>
                                                            <?php else: ?>
                                                                <button 
                                                                    class="btn btn-sm btn-outline-danger"
                                                                    disabled
                                                                    title="Active assessments cannot be deleted."
                                                                >
                                                                    <i class="bi bi-trash"></i> Delete
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    <i class="bi bi-info-circle me-2"></i>No assessments found.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Deleted Assessments Card -->
                    <?php if (!empty($deleted_assessments)): ?>
                        <div class="card shadow-sm">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-trash me-2"></i>Deleted Assessments
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle table-secondary">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Created</th>
                                                <th>Deleted</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($deleted_assessments as $assessment): ?>
                                                <tr>
                                                    <td><?php echo esc_html($assessment->name); ?></td>
                                                    <td>
                                                        <?php 
                                                        if (isset($assessment->created_at) && $assessment->created_at) {
                                                            echo esc_html(date('M j, Y', strtotime($assessment->created_at)));
                                                        } else {
                                                            echo '—';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        if (isset($assessment->completed_at) && $assessment->completed_at) {
                                                            echo esc_html(date('M j, Y', strtotime($assessment->completed_at)));
                                                        } else {
                                                            echo '—';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-success restore-assessment"
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#restoreAssessmentModal"
                                                                data-id="<?php echo esc_attr($assessment->id); ?>"
                                                                data-name="<?php echo esc_attr($assessment->name); ?>">
                                                            <i class="bi bi-arrow-counterclockwise"></i> Restore
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    <?php endif; ?>

<!-- Modals Section -->
<!-- Delete Assessment Modal -->
<div class="modal fade" id="deleteAssessmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Assessment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the assessment "<span id="deleteAssessmentName"></span>"?</p>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    The assessment will be marked as deleted but can be restored later. Results will not be included in user reports while deleted.
                </div>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Note: Active assessments cannot be deleted. They must be completed or deactivated first.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('delete_assessment', 'delete_assessment_nonce'); ?>
                    <input type="hidden" name="action" value="delete_assessment">
                    <input type="hidden" name="id" id="deleteAssessmentId">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete Assessment
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Restore Assessment Modal -->
<div class="modal fade" id="restoreAssessmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Restore Assessment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to restore the assessment "<span id="restoreAssessmentName"></span>"?</p>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    The assessment will be restored and results will be included in user reports.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('restore_assessment', 'restore_assessment_nonce'); ?>
                    <input type="hidden" name="action" value="restore_assessment">
                    <input type="hidden" name="id" id="restoreAssessmentId">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-arrow-counterclockwise"></i> Restore Assessment
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Complete Assessment Modal -->
<div class="modal fade" id="completeAssessmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Complete Assessment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to complete the assessment "<span id="completeAssessmentName"></span>"?</p>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    This will prevent any further responses from being submitted. This action cannot be undone.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="complete_assessment">
                    <input type="hidden" name="id" id="completeAssessmentId">
                    <input type="hidden" name="_wpnonce" id="completeAssessmentNonce">
                    <button type="submit" class="btn btn-info">Complete Assessment</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Activate Assessment Modal -->
<div class="modal fade" id="activateAssessmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Activate Assessment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to activate "<span id="activateAssessmentName"></span>"?</p>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    This will allow users to start submitting their assessments.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="activate_assessment">
                    <input type="hidden" name="id" id="activateAssessmentId">
                    <input type="hidden" name="_wpnonce" id="activateAssessmentNonce">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-play-circle"></i> Activate Assessment
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Deactivate Assessment Modal -->
<div class="modal fade" id="deactivateAssessmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Deactivate Assessment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to deactivate the assessment "<span id="deactivateAssessmentName"></span>"? This will set it back to draft status.</p>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Deactivating will allow you to make changes or reactivate later. Users will not be able to submit responses while the assessment is in draft status.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="deactivate_assessment">
                    <input type="hidden" name="id" id="deactivateAssessmentId">
                    <input type="hidden" name="_wpnonce" id="deactivateAssessmentNonce">
                    <button type="submit" class="btn btn-warning">Deactivate Assessment</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.modal-content {width: 100%}
.modal-footer form {
    box-shadow: none;
}
/* Card Styles */
.card {
    border: none;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    max-width: 100%;
    padding: 0;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0,0,0,.125);
    padding: 1rem;
}

.card-title {
    margin-bottom: 0;
    color: #333;
}

/* Table Styles */
.table > :not(caption) > * > * {
    padding: 1rem;
}

.table-secondary {
    --bs-table-bg: rgba(0,0,0,0.02);
}

.table-secondary tbody tr:hover {
    --bs-table-hover-bg: rgba(0,0,0,0.04);
}

/* Button Styles */
.btn-group {
    gap: 0.25rem;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

/* Badge Styles */
.badge {
    font-weight: 500;
    padding: 0.5em 0.8em;
}

/* Form Styles */
.form-control:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

/* Loading States */
.loading {
    opacity: 0.6;
    pointer-events: none;
}

/* Responsive Design */
@media (max-width: 768px) {
    .card-body {
        padding: 1rem;
    }
    
    .btn-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .btn-group .btn {
        width: 100%;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Delete assessment modal handling
    $('.delete-assessment').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        $('#deleteAssessmentId').val(id);
        $('#deleteAssessmentName').text(name);
    });

    // Restore assessment modal handling
    $('.restore-assessment').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        $('#restoreAssessmentId').val(id);
        $('#restoreAssessmentName').text(name);
    });

    // Complete assessment modal handling
    $('.complete-assessment').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const nonce = $(this).data('nonce');
        $('#completeAssessmentId').val(id);
        $('#completeAssessmentName').text(name);
        $('#completeAssessmentNonce').val(nonce);
    });

    // Activate assessment modal handling
    $('.activate-assessment').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const nonce = $(this).data('nonce');
        $('#activateAssessmentId').val(id);
        $('#activateAssessmentName').text(name);
        $('#activateAssessmentNonce').val(nonce);
    });

    // Deactivate assessment modal handling
    $('.deactivate-assessment').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const nonce = $(this).data('nonce');
        $('#deactivateAssessmentId').val(id);
        $('#deactivateAssessmentName').text(name);
        $('#deactivateAssessmentNonce').val(nonce);
    });

    // Add loading state to all modal forms
    $('.modal form').on('submit', function() {
        $(this).find('button[type="submit"]')
            .prop('disabled', true)
            .html('<i class="bi bi-hourglass-split me-2"></i>Processing...');
    });

    // Form validation
    $('#assessment-form').on('submit', function() {
        $(this).find('button[type="submit"]').prop('disabled', true);
        $(this).addClass('loading');
    });
});
</script>

</div><!-- Close wrap div -->