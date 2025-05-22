<?php if (!defined('ABSPATH')) exit; ?>

<?php 
$group_manager = Assessment_360_Group_Manager::get_instance();
$user_manager = Assessment_360_User_Manager::get_instance();

if (isset($_GET['action']) && ($_GET['action'] == 'new' || $_GET['action'] == 'edit')):
    $group = null;
    if ($_GET['action'] == 'edit' && isset($_GET['id'])) {
        $group = $group_manager->get_group(intval($_GET['id']));
        if (!$group) {
            echo '<div class="alert alert-danger">User group not found.</div>';
            return;
        }
    }
?>

<div class="wrap">
    <!-- Bootstrap Container -->
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-12">
                <h1 class="wp-heading-inline mb-4">
                    <i class="bi bi-people"></i> User Groups
                </h1>

                <!-- Add/Edit Form -->
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-people-fill me-2"></i>
                            <?php echo $group ? 'Edit Group' : 'Add New Group'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="needs-validation" novalidate>
                            <?php wp_nonce_field('assessment_360_group_nonce'); ?>
                            <input type="hidden" name="action" value="assessment_360_save_group">
                            <?php if ($group): ?>
                                <input type="hidden" name="group_id" value="<?php echo esc_attr($group->id); ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="group_name" class="form-label">Group Name <span class="text-danger">*</span></label>
                                <input type="text" 
                                       id="group_name" 
                                       name="group_name" 
                                       class="form-control" 
                                       value="<?php echo $group ? esc_attr($group->group_name) : ''; ?>" 
                                       <?php echo ($group && strtolower($group->group_name) === 'peers') ? 'readonly' : ''; ?>
                                       required>
                                <?php if ($group && strtolower($group->group_name) === 'peers'): ?>
                                    <div class="form-text text-muted">
                                        The Peers group is a system group and cannot be modified.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mt-4">
                                <?php if (!($group && strtolower($group->group_name) === 'peers')): ?>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save me-1"></i>Save Group
                                    </button>
                                <?php endif; ?>
                                <a href="<?php echo esc_url(add_query_arg(['page' => 'assessment-360-groups'], admin_url('admin.php'))); ?>#groups" 
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

            <?php else: 
                $groups = $group_manager->get_all_groups();
            ?>
<div class="wrap">
    <!-- Bootstrap Container -->
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-12">
                <h1 class="wp-heading-inline mb-4">
                    <i class="bi bi-people"></i> User Groups
                </h1>
                <!-- Groups List -->
                <div class="card shadow-sm">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
<!--
                        <h5 class="card-title mb-0">
                            <i class="bi bi-people-fill me-2"></i>User Groups
                        </h5>
-->
                        <a href="<?php echo esc_url(add_query_arg([
                            'page' => 'assessment-360-groups',
                            'action' => 'new'
                        ], admin_url('admin.php'))); ?>#groups" 
                           class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg me-1"></i>Add New
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($groups)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Users</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($groups as $group): 
                                            $user_count = $user_manager->get_user_count_by_group($group->id);
                                            $is_peers = strtolower($group->group_name) === 'peers';
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <strong>
                                                            <?php echo esc_html($group->group_name); ?>
                                                        </strong>
                                                        <?php if ($is_peers): ?>
                                                            <span class="badge bg-primary ms-2">System Group</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php echo esc_html($user_count); ?> user<?php echo $user_count !== 1 ? 's' : ''; ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <a href="<?php echo esc_url(add_query_arg([
                                                        'page' => 'assessment-360-groups',
                                                        'action' => 'edit',
                                                        'id' => $group->id
                                                    ], admin_url('admin.php'))); ?>#groups" 
                                                       class="btn btn-sm btn-outline-primary me-2">
                                                        <i class="bi bi-pencil me-1"></i>Edit
                                                    </a>

                                                    <?php if (!$is_peers && $user_count == 0): ?>
                                                        <a href="<?php echo wp_nonce_url(
                                                            add_query_arg([
                                                                'page' => 'assessment-360-groups',
                                                                'action' => 'delete',
                                                                'id' => $group->id
                                                            ], admin_url('admin.php')),
                                                            'delete_group_' . $group->id
                                                        ); ?>#groups" 
                                                           class="btn btn-sm btn-outline-danger delete-group"
                                                           onclick="return confirm('Are you sure you want to delete this group?');">
                                                            <i class="bi bi-trash me-1"></i>Delete
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle me-2"></i>No user groups found.
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

/* Table Styles */
.table > :not(caption) > * > * {
    padding: 1rem;
}

/* Form Styles */
.form-control:disabled, 
.form-control[readonly] {
    background-color: #e9ecef;
    opacity: 1;
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
    // Form validation
    $('.needs-validation').on('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        $(this).addClass('was-validated');
    });

    // Delete confirmation with Bootstrap modal
    $('.delete-group').on('click', function(e) {
        e.preventDefault();
        const link = $(this);
        
        if (confirm('Are you sure you want to delete this group? This action cannot be undone.')) {
            window.location.href = link.attr('href');
        }
    });
});
</script>
