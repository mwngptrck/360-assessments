<?php if (!defined('ABSPATH')) exit; ?>

<?php
// Initialize managers
$assessment_manager = Assessment_360_Assessment::get_instance();
$user_manager = Assessment_360_User_Manager::get_instance();

// Get current action and assessment ID
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$assessment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get all assessments and separate active/completed from deleted
$assessments = $assessment_manager->get_all_assessments();
$active_assessments = array_filter($assessments, function($assessment) {
    return $assessment->status !== 'deleted';
});
$deleted_assessments = array_filter($assessments, function($assessment) {
    return $assessment->status === 'deleted';
});

// Check if there's an active assessment
$has_active_assessment = !empty(array_filter($assessments, function($assessment) {
    return $assessment->status === 'active';
}));

// Initialize variables for edit mode
$assessment = null;
$assessment_name = '';
$assessment_description = '';

// If editing, get assessment data
if ($action === 'edit' && $assessment_id > 0) {
    $assessment = $assessment_manager->get_assessment($assessment_id);
    if ($assessment) {
        $assessment_name = $assessment->name;
        $assessment_description = $assessment->description;
    }
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
                            <?php if (isset($_GET['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?php echo esc_html($_GET['error']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="assessment-form">
                                <?php wp_nonce_field('save_assessment'); ?>
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
                                           value="<?php echo esc_attr($assessment_name); ?>" 
                                           required>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" 
                                              id="description" 
                                              name="description" 
                                              rows="3"><?php echo esc_textarea($assessment_description); ?></textarea>
                                </div>

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
        <!-- Active and Completed Assessments List -->
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

                    <!-- Active & Completed Assessments Card -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-clipboard-check me-2"></i>Active & Completed Assessments
                            </h5>
                            <a href="<?php echo esc_url(add_query_arg(['action' => 'new'], admin_url('admin.php?page=assessment-360-assessments'))); ?>" 
                               class="btn btn-primary">
                                <i class="bi bi-plus-lg me-1"></i>Add New
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($active_assessments)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                                <th>Completed</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($active_assessments as $assessment): ?>
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
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Completed</span>
                                                        <?php endif; ?>
                                                    </td>
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
                                                        <div class="btn-group">
                                                            <a href="<?php echo esc_url(add_query_arg([
                                                                'page' => 'assessment-360-assessments',
                                                                'action' => 'edit',
                                                                'id' => $assessment->id
                                                            ], admin_url('admin.php'))); ?>" 
                                                               class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-pencil"></i> Edit
                                                            </a>

                                                            <?php if ($assessment->status === 'active'): ?>
                                                                <button type="button" 
                                                                        class="btn btn-sm btn-outline-success complete-assessment"
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#completeAssessmentModal"
                                                                        data-id="<?php echo esc_attr($assessment->id); ?>"
                                                                        data-name="<?php echo esc_attr($assessment->name); ?>">
                                                                    <i class="bi bi-check-circle"></i> Complete
                                                                </button>
                                                            <?php elseif ($assessment->status === 'completed'): ?>
                                                                <?php if ($assessment->status === 'completed' && !$has_active_assessment): ?>
                                                                    <button type="button" 
                                                                            class="btn btn-sm btn-outline-primary activate-assessment"
                                                                            data-bs-toggle="modal" 
                                                                            data-bs-target="#activateAssessmentModal"
                                                                            data-id="<?php echo esc_attr($assessment->id); ?>"
                                                                            data-name="<?php echo esc_attr($assessment->name); ?>">
                                                                        <i class="bi bi-play-circle"></i> Activate
                                                                    </button>
                                                                <?php endif; ?>

                                                                <!-- Only show delete button for completed assessments -->
                                                                <button type="button" 
                                                                        class="btn btn-sm btn-outline-danger delete-assessment"
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#deleteAssessmentModal"
                                                                        data-id="<?php echo esc_attr($assessment->id); ?>"
                                                                        data-name="<?php echo esc_attr($assessment->name); ?>">
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
                                    <i class="bi bi-info-circle me-2"></i>No active or completed assessments found.
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
                    Note: Active assessments cannot be deleted. They must be completed first.
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
                    <?php wp_nonce_field('complete_assessment', 'complete_assessment_nonce'); ?>
                    <input type="hidden" name="action" value="complete_assessment">
                    <input type="hidden" name="id" id="completeAssessmentId">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Complete Assessment
                    </button>
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
                    <?php wp_nonce_field('activate_assessment', 'activate_assessment_nonce'); ?>
                    <input type="hidden" name="action" value="activate_assessment">
                    <input type="hidden" name="id" id="activateAssessmentId">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-play-circle"></i> Activate Assessment
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<style>
    
.modal-content {width: 100%}
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
        
        $('#completeAssessmentId').val(id);
        $('#completeAssessmentName').text(name);
    });

    // Add loading state to complete form
    $('#completeAssessmentModal form').on('submit', function() {
        $(this).find('button[type="submit"]')
            .prop('disabled', true)
            .html('<i class="bi bi-hourglass-split me-2"></i>Completing...');
    });

    // Activate assessment modal handling
    $('.activate-assessment').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        
        $('#activateAssessmentId').val(id);
        $('#activateAssessmentName').text(name);
    });

    // Add loading state to activate form
    $('#activateAssessmentModal form').on('submit', function() {
        $(this).find('button[type="submit"]')
            .prop('disabled', true)
            .html('<i class="bi bi-hourglass-split me-2"></i>Activating...');
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
