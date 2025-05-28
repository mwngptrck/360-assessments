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
<!--
                <h1 class="wp-heading-inline mb-4">
                    <i class="bi bi-person-badge"></i> Positions
                </h1>
-->
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
    <!-- Bootstrap Container -->
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-12">
<!--
                <h1 class="wp-heading-inline mb-4">
                    <i class="bi bi-person-badge"></i> Positions
                </h1>
-->
                <div class="card shadow-sm">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
<!--
                        <h5 class="card-title mb-0">
                            <i class="bi bi-briefcase me-2"></i>Positions
                        </h5>
-->
                        
                        
                        <a href="<?php echo esc_url(add_query_arg([
                            'page' => 'assessment-360-user-management',
                            'tab' => 'positions',
                            'action' => 'new'
                        ], admin_url('admin.php'))); ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add New</a>
                        
                        
                    </div>
                    <div class="card-body">
                        <?php 
                        $positions = $position_manager->get_all_positions(true);
                        $has_active = false;
                        $has_deleted = false;

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
                                        foreach ($positions as $position):
                                            if ($position->status === 'active') $has_active = true;
                                            if ($position->status === 'deleted') $has_deleted = true;

                                            // Skip deleted items on first pass
                                            if ($position->status === 'deleted') continue;
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo esc_html($position->name); ?></strong>
                                                </td>
                                                <td><?php echo !empty($position->description) ? esc_html($position->description) : '—'; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $position->status === 'active' ? 'success' : 'danger'; ?>">
                                                        <?php echo esc_html(ucfirst($position->status)); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <a href="<?php echo esc_url(add_query_arg([
                                                        'page' => 'assessment-360-user-management',
                                                        'action' => 'edit',
                                                        'tab' => 'positions',
                                                        'id' => $position->id
                                                    ], admin_url('admin.php'))); ?>#positions" 
                                                       class="btn btn-sm btn-outline-primary me-2">
                                                        <i class="bi bi-pencil me-1"></i>Edit
                                                    </a>

                                                    <form method="post" 
                                                          action="<?php echo admin_url('admin-post.php'); ?>" 
                                                          class="d-inline-block delete-form">
                                                        <?php wp_nonce_field('delete_position_' . $position->id); ?>
                                                        <input type="hidden" name="action" value="delete_position">
                                                        <input type="hidden" name="id" value="<?php echo esc_attr($position->id); ?>">
                                                        <button type="submit" 
                                                                class="btn btn-sm btn-outline-danger delete-position">
                                                            <i class="bi bi-trash me-1"></i>Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php 
                                        endforeach;

                                        if ($has_deleted):
                                        ?>
                                            <!-- Deleted Positions Section -->
                                            <tr>
                                                <td colspan="4" class="bg-light">
                                                    <h6 class="mb-0 text-muted">Deleted Positions</h6>
                                                </td>
                                            </tr>

                                            <?php
                                            foreach ($positions as $position):
                                                if ($position->status !== 'deleted') continue;
                                            ?>
                                                <tr class="table-light">
                                                    <td>
                                                        <strong><?php echo esc_html($position->name); ?></strong>
                                                    </td>
                                                    <td><?php echo !empty($position->description) ? esc_html($position->description) : '—'; ?></td>
                                                    <td>
                                                        <span class="badge bg-danger">Deleted</span>
                                                    </td>
                                                    <td class="text-end">
                                                        <form method="post" 
                                                              action="<?php echo admin_url('admin-post.php'); ?>" 
                                                              class="d-inline-block delete-form">
                                                            <?php wp_nonce_field('restore_position_' . $position->id); ?>
                                                            <input type="hidden" name="action" value="restore_position">
                                                            <input type="hidden" name="id" value="<?php echo esc_attr($position->id); ?>">
                                                            <button type="submit" 
                                                                    class="btn btn-sm btn-success restore-position">
                                                                <i class="bi bi-arrow-counterclockwise me-1"></i>Restore
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php 
                                            endforeach;
                                        endif;
                                        ?>
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
    
.delete-form {padding: 0 !important}

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

/* Loading State */
.loading {
    opacity: 0.6;
    pointer-events: none;
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

    // Use the actual form selector, or just 'form' if only one form is present.
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

    // Delete confirmation
    $('.delete-position').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this position? This action cannot be undone.')) {
            e.preventDefault();
        }
    });

    // Restore confirmation
    $('.restore-position').on('click', function(e) {
        if (!confirm('Are you sure you want to restore this position?')) {
            e.preventDefault();
        }
    });
});
</script>
