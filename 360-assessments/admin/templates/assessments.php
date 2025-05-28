<?php
if (!defined('ABSPATH')) exit;

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die('Sorry, you are not allowed to access this page.');
}

$assessment_manager = Assessment_360_Assessment_Manager::get_instance();
?>

<div class="wrap">
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-12">
                <h1 class="wp-heading-inline mb-4">
                    <i class="bi bi-clipboard-data me-2"></i>Assessments
                </h1>

                <?php if (!isset($_GET['action']) || $_GET['action'] !== 'edit'): ?>
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <a href="<?php echo esc_url(add_query_arg([
                        'page' => 'assessment-360-assessments',
                        'action' => 'new'
                    ], admin_url('admin.php'))); ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i>Add New
                    </a>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                        <?php echo esc_html($_GET['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                        <?php echo esc_html($_GET['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php 
                // Show form for add/edit
                if (isset($_GET['action']) && ($_GET['action'] === 'new' || $_GET['action'] === 'edit')):
                    $assessment = null;

                    if ($_GET['action'] === 'edit' && isset($_GET['id'])) {
                        $assessment = $assessment_manager->get_assessment(intval($_GET['id']));

                        if (!$assessment) {
                            echo '<div class="alert alert-danger">Assessment not found.</div>';
                            return;
                        }
                    }
                ?>
                    <!-- Add/Edit Form -->
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-pencil-square me-2"></i>
                                <?php echo $assessment ? 'Edit Assessment' : 'New Assessment'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="post" 
                                  action="<?php echo esc_url(admin_url('admin.php?page=assessment-360-assessments')); ?>" 
                                  class="needs-validation" 
                                  novalidate>
                                <?php wp_nonce_field('save_assessment_nonce'); ?>
                                <input type="hidden" name="action" value="save_assessment">
                                <?php if ($assessment): ?>
                                    <input type="hidden" name="id" value="<?php echo esc_attr($assessment->id); ?>">
                                <?php endif; ?>

                                <div class="row g-4">
                                    <!-- Assessment Name -->
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="name" class="form-label">Assessment Name <span class="text-danger">*</span></label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="name" 
                                                   name="name" 
                                                   value="<?php echo $assessment ? esc_attr($assessment->name) : ''; ?>" 
                                                   required>
                                        </div>
                                    </div>

                                    <!-- Description -->
                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea class="form-control" 
                                                      id="description" 
                                                      name="description" 
                                                      rows="4"><?php echo $assessment ? esc_textarea($assessment->description) : ''; ?></textarea>
                                        </div>
                                    </div>

                                    <!-- Dates -->
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                                            <input type="date" 
                                                   class="form-control" 
                                                   id="start_date" 
                                                   name="start_date" 
                                                   value="<?php echo $assessment ? esc_attr($assessment->start_date) : ''; ?>" 
                                                   <?php echo !$assessment ? 'min="' . esc_attr(date('Y-m-d')) . '"' : ''; ?>
                                                   required>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                                            <input type="date" 
                                                   class="form-control" 
                                                   id="end_date" 
                                                   name="end_date" 
                                                   value="<?php echo $assessment ? esc_attr($assessment->end_date) : ''; ?>" 
                                                   <?php echo !$assessment ? 'min="' . esc_attr(date('Y-m-d')) . '"' : ''; ?>
                                                   required>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save me-1"></i>
                                        <?php echo $assessment ? 'Update Assessment' : 'Create Assessment'; ?>
                                    </button>
                                    <a href="<?php echo admin_url('admin.php?page=assessment-360-assessments'); ?>" 
                                       class="btn btn-secondary">
                                        <i class="bi bi-x-circle me-1"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Assessments List -->
                    <div class="card shadow-sm mt-4">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Dates</th>
                                        <th>Status</th>
                                        <th>Progress</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $assessments = $assessment_manager->get_all_assessments();
                                    if (!empty($assessments)): 
                                        foreach ($assessments as $assessment): 
                                            $progress = $assessment_manager->get_assessment_progress($assessment->id);
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <strong><?php echo esc_html($assessment->name); ?></strong>
                                                    <small class="text-muted">
                                                        <?php echo wp_trim_words($assessment->description, 10); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-calendar3 me-2 text-muted"></i>
                                                    <small>
                                                        <?php 
                                                        echo esc_html(date('M j, Y', strtotime($assessment->start_date))) . ' - ' . 
                                                             esc_html(date('M j, Y', strtotime($assessment->end_date))); 
                                                        ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo esc_attr(
                                                        $assessment->status === 'draft' ? 'secondary' : 
                                                        ($assessment->status === 'active' ? 'success' : 'dark')
                                                    ); 
                                                ?>">
                                                    <?php echo esc_html(ucfirst($assessment->status)); ?>
                                                </span>
                                            </td>
                                            <td style="min-width: 200px;">
                                                <div class="d-flex flex-column">
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar" 
                                                             role="progressbar" 
                                                             style="width: <?php echo esc_attr($progress ? $progress->percentage : 0); ?>%"
                                                             aria-valuenow="<?php echo esc_attr($progress ? $progress->percentage : 0); ?>"
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100">
                                                        </div>
                                                    </div>
                                                    <small class="text-muted mt-1">
                                                        <?php 
                                                        if ($progress) {
                                                            echo esc_html($progress->completed) . ' of ' . 
                                                                 esc_html($progress->total) . ' completed';
                                                        } else {
                                                            echo '0 of 0 completed';
                                                        }
                                                        ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group">
                                                    <a href="<?php echo esc_url(add_query_arg([
                                                        'page' => 'assessment-360-assessments',
                                                        'action' => 'edit',
                                                        'id' => $assessment->id
                                                    ], admin_url('admin.php'))); ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-pencil me-1"></i>Edit
                                                    </a>

                                                    <?php if ($assessment->status === 'draft'): ?>
                                                        <?php 
                                                        $active_assessment = $assessment_manager->has_active_assessment();
                                                        if (!$active_assessment):
                                                        ?>
                                                            <a href="<?php echo wp_nonce_url(
                                                                add_query_arg([
                                                                    'action' => 'enable_assessment',
                                                                    'id' => $assessment->id
                                                                ], admin_url('admin-post.php')),
                                                                'enable_assessment_' . $assessment->id
                                                            ); ?>" class="btn btn-sm btn-outline-success">
                                                                <i class="bi bi-play me-1"></i>Enable
                                                            </a>
                                                        <?php endif; ?>

                                                        <a href="<?php echo wp_nonce_url(
                                                            add_query_arg([
                                                                'action' => 'delete_assessment',
                                                                'id' => $assessment->id
                                                            ], admin_url('admin-post.php')),
                                                            'delete_assessment_' . $assessment->id
                                                        ); ?>" 
                                                           class="btn btn-sm btn-outline-danger delete-assessment">
                                                            <i class="bi bi-trash me-1"></i>Delete
                                                        </a>
                                                    <?php elseif ($assessment->status === 'active'): ?>
                                                        <a href="<?php echo wp_nonce_url(
                                                            add_query_arg([
                                                                'action' => 'complete_assessment',
                                                                'id' => $assessment->id
                                                            ], admin_url('admin-post.php')),
                                                            'complete_assessment_' . $assessment->id
                                                        ); ?>" class="btn btn-sm btn-outline-dark complete-assessment">
                                                            <i class="bi bi-check-lg me-1"></i>Complete
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php 
                                        endforeach;
                                    else: 
                                    ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                <i class="bi bi-inbox display-6 d-block mb-3"></i>
                                                No assessments found.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    
.wrap h1.wp-heading-inline {display: block}
/* Card Styles */
.card {
    border: none;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    max-width: 100%;
    padding: 0
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

/* Progress Bar */
.progress {
    background-color: #e9ecef;
    border-radius: 0.25rem;
    overflow: hidden;
}

.progress-bar {
    background-color: var(--bs-primary);
    transition: width 0.3s ease;
}

/* Button States */
.btn.loading {
    position: relative;
    color: transparent !important;
}

.btn.loading::after {
    content: '';
    position: absolute;
    left: 50%;
    top: 50%;
    width: 16px;
    height: 16px;
    margin-left: -8px;
    margin-top: -8px;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top-color: currentColor;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Form Validation */
.was-validated .form-control:invalid {
    border-color: var(--bs-danger);
}

.was-validated .form-control:valid {
    border-color: var(--bs-success);
}

/* Responsive Design */
@media (max-width: 768px) {
    .btn-group {
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
    // Form validation
    $('form.needs-validation').on('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        const startDate = new Date($('#start_date').val());
        const endDate = new Date($('#end_date').val());
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        // Validate dates
        if (!$('input[name="id"]').length && startDate < today) {
            e.preventDefault();
            alert('Start date cannot be earlier than today');
            return false;
        }
        
        if (endDate < startDate) {
            e.preventDefault();
            alert('End date cannot be earlier than start date');
            return false;
        }

        // Add loading state to submit button
        if (this.checkValidity()) {
            $(this).find('button[type="submit"]')
                .addClass('loading')
                .prop('disabled', true);
        }
        
        $(this).addClass('was-validated');
    });

    // Date validation on change
    $('#end_date').on('change', function() {
        const startDate = new Date($('#start_date').val());
        const endDate = new Date($(this).val());

        if (endDate < startDate) {
            alert('End date cannot be earlier than start date');
            $(this).val('');
        }
    });

    // Action confirmations
    $('.delete-assessment').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this assessment? This action cannot be undone.')) {
            e.preventDefault();
            return false;
        }
        $(this).addClass('loading').prop('disabled', true);
    });

    $('.complete-assessment').on('click', function(e) {
        if (!confirm('Are you sure you want to complete this assessment? This will end the assessment period.')) {
            e.preventDefault();
            return false;
        }
        $(this).addClass('loading').prop('disabled', true);
    });

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
