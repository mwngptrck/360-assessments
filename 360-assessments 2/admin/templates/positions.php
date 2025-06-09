<?php 
if (!defined('ABSPATH')) exit;

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die('Sorry, you are not allowed to access this page.');
}

// Initialize manager
$position_manager = Assessment_360_Position::get_instance();

// Show form for add/edit
if (isset($_GET['action']) && ($_GET['action'] === 'new' || $_GET['action'] === 'edit')):
    $position = null;
    if ($_GET['action'] === 'edit' && isset($_GET['id'])) {
        $position = $position_manager->get_position(intval($_GET['id']));
        if (!$position) {
            echo '<div class="alert alert-danger">Position not found.</div>';
            return;
        }
    }
?>

    <!-- Add/Edit Form -->
    <div class="wrap">
        <!-- Bootstrap Container -->
        <div class="container-fluid p-0">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-briefcase me-2"></i>
                                <?php echo $position ? 'Edit Position' : 'Add New Position'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form id="position-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                <?php wp_nonce_field('save_position'); ?>
                                <input type="hidden" name="action" value="save_position">
                                <?php if ($position): ?>
                                    <input type="hidden" name="id" value="<?php echo esc_attr($position->id); ?>">
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label for="name" class="form-label">Position Name <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           id="name" 
                                           name="name" 
                                           class="form-control" 
                                           value="<?php echo $position ? esc_attr($position->name) : ''; ?>" 
                                           required>
                                    <div class="form-text">
                                        Position names must be unique. This will be used to identify the position throughout the system.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea id="description" 
                                              name="description" 
                                              class="form-control" 
                                              rows="5"><?php echo $position ? esc_textarea($position->description ?? '') : ''; ?></textarea>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save me-1"></i>
                                        <?php echo $position ? 'Update Position' : 'Add Position'; ?>
                                    </button>
                                    <a href="<?php echo esc_url(add_query_arg(['page' => 'assessment-360-user-management'], admin_url('admin.php'))); ?>#positions" 
                                       class="btn btn-secondary">
                                        <i class="bi bi-x-circle me-1"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>

    <!-- Positions List -->
    <div class="wrap">
        <div class="container-fluid p-0">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-briefcase"></i> Positions
                            </h5>
                            <a href="<?php echo esc_url(add_query_arg([
                                'page' => 'assessment-360-user-management',
                                'tab' => 'positions',
                                'action' => 'new'
                            ], admin_url('admin.php'))); ?>" class="btn btn-primary">
                                <i class="bi bi-plus-lg me-1"></i> Add New
                            </a>
                        </div>
                        <div class="card-body">
                            <?php 
                            $positions = $position_manager->get_all_positions(true);
                            if (!empty($positions)): 
                            ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Name</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $has_active = false;
                                            $has_deleted = false;

                                            // First show active positions
                                            foreach ($positions as $position):
                                                if ($position->status !== 'active') continue;
                                                $has_active = true;
                                            ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo esc_html($position->name); ?></strong>
                                                    </td>
                                                    <td><?php echo !empty($position->description) ? esc_html($position->description) : '—'; ?></td>
                                                    <td>
                                                        <span class="badge bg-success">Active</span>
                                                    </td>
                                                    <td class="text-end">
                                                        <div class="btn-group">
                                                            <a href="<?php echo esc_url(add_query_arg([
                                                                'page' => 'assessment-360-user-management',
                                                                'action' => 'edit',
                                                                'tab' => 'positions',
                                                                'id' => $position->id
                                                            ], admin_url('admin.php'))); ?>#positions" 
                                                               class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-pencil"></i> Edit
                                                            </a>
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-outline-danger delete-position"
                                                                    data-id="<?php echo esc_attr($position->id); ?>"
                                                                    data-name="<?php echo esc_attr($position->name); ?>"
                                                                    data-permanent="0"
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#deletePositionModal">
                                                                <i class="bi bi-trash"></i> Delete
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <?php
                                            // Then show deleted positions
                                            foreach ($positions as $position):
                                                if ($position->status !== 'deleted') continue;

                                                // Show deleted positions header if this is the first deleted position
                                                if (!$has_deleted):
                                                    $has_deleted = true;
                                            ?>
                                                    <tr class="table-secondary">
                                                        <td colspan="4" class="bg-light">
                                                            <h6 class="mb-0 text-muted">
                                                                <i class="bi bi-trash me-2"></i>Deleted Positions
                                                            </h6>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>

                                                <tr class="table-light deleted-position">
                                                    <td>
                                                        <strong><?php echo esc_html($position->name); ?></strong>
                                                    </td>
                                                    <td><?php echo !empty($position->description) ? esc_html($position->description) : '—'; ?></td>
                                                    <td>
                                                        <span class="badge bg-danger">Deleted</span>
                                                    </td>
                                                    <td class="text-end">
                                                        <div class="btn-group">
                                                            <form method="post" 
                                                                  action="<?php echo admin_url('admin-post.php'); ?>" 
                                                                  class="d-inline-block restore-form">
                                                                <?php wp_nonce_field('restore_position', 'restore_position_nonce'); ?>
                                                                <input type="hidden" name="action" value="restore_position">
                                                                <input type="hidden" name="position_id" value="<?php echo esc_attr($position->id); ?>">
                                                                <button type="submit" 
                                                                        class="btn btn-sm btn-outline-success restore-position"
                                                                        data-name="<?php echo esc_attr($position->name); ?>">
                                                                    <i class="bi bi-arrow-counterclockwise"></i> Restore
                                                                </button>
                                                            </form>
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-danger delete-position"
                                                                    data-id="<?php echo esc_attr($position->id); ?>"
                                                                    data-name="<?php echo esc_attr($position->name); ?>"
                                                                    data-permanent="1"
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#deletePositionModal">
                                                                <i class="bi bi-trash"></i> Delete Permanently
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <?php if (!$has_active && !$has_deleted): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-4">
                                                        <div class="text-muted">
                                                            <i class="bi bi-briefcase h1 d-block mb-3"></i>
                                                            No positions found.
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    <i class="bi bi-info-circle me-2"></i>No positions found.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Position Modal -->
    <div class="modal fade" id="deletePositionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalTitle">Delete Position</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to <span class="delete-type">delete</span> the position "<span id="deletePositionName"></span>"?</p>

                    <div id="regularDeleteWarning" class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        This will mark the position as deleted. Users with this position will not be affected.
                    </div>

                    <div id="permanentDeleteWarning" class="alert alert-danger" style="display: none;">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Warning:</strong> This will permanently delete the position from the database. 
                        This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('permanent_delete_position', 'permanent_delete_position_nonce'); ?>
                        <input type="hidden" name="action" value="permanent_delete_position">
                        <input type="hidden" name="position_id" id="deletePositionId">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> <span class="delete-button-text">Delete</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
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

/* Form Styles */
.form-control:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

.delete-form {
    padding: 0 !important;
}

/* Badge Styles */
.badge {
    font-weight: 500;
    padding: 0.5em 0.8em;
}

/* Button Styles */
.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

/* Modal Styles */
.modal-dialog{    
        max-width: 80% !important;
}
.modal-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.modal-footer {
    background-color: #f8f9fa;
    border-top: 1px solid #dee2e6;
}
.modal-footer form {
    box-shadow: none;
}
.modal-body {
    padding: 1.5rem;
}

.modal-footer .btn {
    min-width: 80px;
}

/* Loading State */
.loading {
    opacity: 0.6;
    pointer-events: none;
}
.table-secondary td {
    background-color: #f8f9fa;
}

.deleted-position {
    opacity: 0.8;
}

.deleted-position:hover {
    opacity: 1;
}

#permanentDeleteWarning {
    border-left: 4px solid #dc3545;
}

/* Responsive Design */
@media (max-width: 768px) {
    .card-body {
        padding: 1rem;
    }
    
    .btn-sm {
        padding: 0.375rem 0.75rem;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Store initial name for edit form
    const initialName = $('#name').val();

    // Form validation
    $('#position-form').on('submit', function(e) {
        const name = $('#name', this).val().trim();
        $('.error-message', this).remove();
        $('#name', this).removeClass('error');
        if (!name) {
            e.preventDefault();
            $('#name', this).addClass('error')
                .after('<span class="error-message">Position name is required.</span>');
            $('html, body').animate({
                scrollTop: $('#name', this).offset().top - 100
            }, 500);
            return false;
        }
        $(this).find('button[type="submit"]').prop('disabled', true);
        $(this).addClass('loading');
        return true;
    });

    // Delete position modal handling
    $('.delete-position').on('click', function() {
        const positionId = $(this).data('id');
        const positionName = $(this).data('name');
        const isPermanent = $(this).data('permanent') === 1;

        $('#deletePositionId').val(positionId);
        $('#deletePositionName').text(positionName);

        // Update modal content based on deletion type
        if (isPermanent) {
            $('#deleteModalTitle').text('Permanently Delete Position');
            $('.delete-type').text('permanently delete');
            $('.delete-button-text').text('Delete Permanently');
            $('#regularDeleteWarning').hide();
            $('#permanentDeleteWarning').show();
        } else {
            $('#deleteModalTitle').text('Delete Position');
            $('.delete-type').text('delete');
            $('.delete-button-text').text('Delete');
            $('#regularDeleteWarning').show();
            $('#permanentDeleteWarning').hide();
        }
    });

    // Add loading state to delete form
    $('#deletePositionModal form').on('submit', function() {
        $(this).find('button[type="submit"]')
            .prop('disabled', true)
            .html('<i class="bi bi-hourglass-split me-2"></i>Deleting...');
    });
    
    // Handle permanent delete checkbox
    $('#permanentDelete').on('change', function() {
        const isPermanent = $(this).is(':checked');
        const positionId = $('#deletePositionId').val();
        
        if (isPermanent) {
            $('#deletePositionForm').hide();
            $('#permanentDeletePositionForm').show();
            $('#permanentDeletePositionId').val(positionId);
        } else {
            $('#deletePositionForm').show();
            $('#permanentDeletePositionForm').hide();
        }
    });
    
    // Restore confirmation
    $('.restore-position').on('click', function(e) {
        e.preventDefault();
        const button = $(this);
        const positionName = button.data('name');
        
        if (confirm('Are you sure you want to restore the position "' + positionName + '"?')) {
            // Add loading state
            button.prop('disabled', true)
                .html('<i class="bi bi-hourglass-split me-2"></i>Restoring...');
            
            // Submit the form
            button.closest('form').submit();
        }
    });

    // Add loading state to both forms
    $('#deletePositionForm, #permanentDeletePositionForm').on('submit', function() {
        $(this).find('button[type="submit"]').prop('disabled', true)
            .html('<i class="bi bi-hourglass-split me-2"></i>Deleting...');
    });
});
</script>
