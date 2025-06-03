<?php if (!defined('ABSPATH')) exit; ?>

<?php 
$group_manager = Assessment_360_Group_Manager::get_instance();
$user_manager = Assessment_360_User_Manager::get_instance();

// Get current action and group ID
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$group_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Initialize variables
$group = null;
$group_name = '';
$description = '';
$is_department = 0;

// If editing, get group data
if ($action === 'edit' && $group_id > 0) {
    $group = $group_manager->get_group($group_id);
    
    if ($group) {
        $group_name = $group->group_name;
        $description = $group->description;
        $is_department = (int)$group->is_department;
        
        // Prevent editing system groups
        if ($group_manager->is_system_group($group->group_name)) {
            wp_redirect(add_query_arg([
                'page' => 'assessment-360-user-management',
                'tab' => 'groups',
                'error' => urlencode('System groups cannot be modified.')
            ], admin_url('admin.php')));
            exit;
        }
    }
}

if ($action === 'new' || $action === 'edit'):
?>

<div class="wrap">
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-12">
                <!-- Add/Edit Form -->
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-people-fill me-2"></i>
                            <?php echo $group ? 'Edit Group' : 'Add New Group'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo esc_html($_GET['error']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="group-form">
                            <?php wp_nonce_field('save_group_nonce'); ?>
                            <input type="hidden" name="action" value="save_group">
                            <?php if ($group_id): ?>
                                <input type="hidden" name="id" value="<?php echo esc_attr($group_id); ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="group_name" class="form-label">Group Name <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       id="group_name" 
                                       name="group_name" 
                                       value="<?php echo esc_attr($group_name); ?>" 
                                       required>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" 
                                          id="description" 
                                          name="description" 
                                          rows="3"><?php echo esc_textarea($description); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" 
                                           class="form-check-input" 
                                           id="is_department" 
                                           name="is_department" 
                                           value="1"
                                           <?php checked($is_department === 1); ?>>
                                    <label class="form-check-label" for="is_department">
                                        This is a department
                                    </label>
                                    <div class="form-text">
                                        If checked, this group will be treated as a department under the Peers group.
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <?php echo $group ? 'Update Group' : 'Add Group'; ?>
                            </button>
                            
                            <a href="<?php echo esc_url(add_query_arg(['page' => 'assessment-360-user-management', 'tab' => 'groups'], admin_url('admin.php'))); ?>" 
                               class="btn btn-outline-secondary ms-2">
                                Cancel
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: ?>

<div class="wrap">
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-12">
                <!-- Groups List -->
                <div class="card shadow-sm">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-people-fill me-2"></i>User Groups
                        </h5>
                        <a href="<?php echo esc_url(add_query_arg([
                            'page' => 'assessment-360-user-management',
                            'tab' => 'groups',
                            'action' => 'new'
                        ], admin_url('admin.php'))); ?>" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i>Add New
                        </a>
                    </div>
                    <div class="card-body">
                        
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Group Name</th>
                                        <th>Description</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $groups = $group_manager->get_all_groups();
                                    if (!empty($groups)):
                                        foreach ($groups as $group):
                                            $is_system_group = $group_manager->is_system_group($group->group_name);
                                    ?>
                                        <tr>
                                            <td>
                                                <?php echo esc_html($group->group_name); ?>
                                                <?php if ($is_system_group): ?>
                                                    <span class="badge bg-primary ms-2">System Group</span>
                                                <?php endif; ?>
                                                <?php if (!empty($group->is_department)): ?>
                                                    <span class="badge bg-info ms-2">Department</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo esc_html($group->description); ?></td>
                                            <td class="text-end">
                                                <?php if (!$is_system_group): ?>
                                                    <div class="btn-group">
                                                        <a href="<?php echo esc_url(add_query_arg([
                                                            'page' => 'assessment-360-user-management',
                                                            'tab' => 'groups',
                                                            'action' => 'edit',
                                                            'id' => $group->id
                                                        ], admin_url('admin.php'))); ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-pencil"></i> Edit
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-danger delete-group"
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#deleteGroupModal"
                                                                data-id="<?php echo esc_attr($group->id); ?>"
                                                                data-name="<?php echo esc_attr($group->group_name); ?>">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">System group - cannot be modified</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php 
                                        endforeach;
                                    else:
                                    ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="bi bi-people h1 d-block mb-3"></i>
                                                    No user groups found.
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    
    <!-- Delete Modal -->
    <div class="modal fade" id="deleteGroupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the group "<span id="deleteGroupName"></span>"?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('delete_group'); ?>
                        <input type="hidden" name="action" value="delete_group">
                        <input type="hidden" name="id" id="deleteGroupId">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Delete Group
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<style>
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

.table > :not(caption) > * > * {
    padding: 1rem;
}
.alert {
    margin-bottom: 20px;
}
.badge {
    font-weight: 500;
    padding: 0.5em 0.8em;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

@media (max-width: 768px) {
    .card-body {
        padding: 1rem;
    }
    
    .btn-sm {
        padding: 0.375rem 0.75rem;
    }
}

.error-message {
    color: #dc3545;
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

.form-control.error {
    border-color: #dc3545;
}
</style>

<script>
jQuery(function($){
    // Form validation
    $('#group-form').on('submit', function(e) {
        const groupName = $('#group_name', this).val().trim();
        $('.error-message', this).remove();
        $('#group_name', this).removeClass('error');
        
        if (!groupName) {
            e.preventDefault();
            $('#group_name', this).addClass('error')
                .after('<span class="error-message">Group name is required.</span>');
            return false;
        }
        
        $(this).find('button[type="submit"]').prop('disabled', true);
        return true;
    });

    // Delete confirmation
//    $('.delete-group').on('click', function(e) {
//        e.preventDefault();
//        if (confirm('Are you sure you want to delete this group? This action cannot be undone.')) {
//            window.location.href = $(this).attr('href');
//        }
//    });
    
    // Delete modal handling
    $('.delete-group').on('click', function() {
        const groupId = $(this).data('id');
        const groupName = $(this).data('name');
        
        $('#deleteGroupId').val(groupId);
        $('#deleteGroupName').text(groupName);
    });

    // Add loading state to delete form
    $('#deleteGroupModal form').on('submit', function() {
        $(this).find('button[type="submit"]')
            .prop('disabled', true)
            .html('<i class="bi bi-hourglass-split me-2"></i>Deleting...');
    });
});
</script>
