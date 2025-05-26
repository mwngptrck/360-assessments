<?php
$group_manager = Assessment_360_Group_Manager::get_instance();
$groups = $group_manager->get_all_groups();
?>

<h2 class="h4 mb-4">User Groups</h2>

<form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
    <?php wp_nonce_field('save_setup_groups'); ?>
    <input type="hidden" name="action" value="save_setup_groups">
    <input type="hidden" name="next_step" value="<?php echo esc_attr($steps[$current_step]['next']); ?>">

    <div class="mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Group Name</th>
                        <th>Description</th>
                        <th>Is Department</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Peers group (system group) -->
                    <tr>
                        <td>Peers</td>
                        <td>
                            <input type="text" class="form-control" name="group_desc[peers]" 
                                   value="Main peer group for assessments" readonly>
                        </td>
                        <td>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" disabled>
                            </div>
                        </td>
                    </tr>
                    <!-- New group row -->
                    <tr>
                        <td>
                            <input type="text" class="form-control" name="new_group_name" placeholder="New group name">
                        </td>
                        <td>
                            <input type="text" class="form-control" name="new_group_desc" placeholder="Description">
                        </td>
                        <td>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="new_group_is_department" value="1">
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Navigation buttons -->
    <div class="setup-actions">
        <div>
            <?php if ($current_step !== 'welcome' && $current_step !== 'complete'): ?>
                <a href="<?php echo esc_url(get_setup_step_url($steps[$current_step]['next'])); ?>" 
                   class="btn btn-outline-secondary">
                    Skip this step
                </a>
            <?php endif; ?>
        </div>
        <div>
            <?php if ($current_step === 'complete'): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=assessment-360-dashboard')); ?>" 
                   class="btn btn-primary">
                    Go to Dashboard <i class="bi bi-arrow-right ms-2"></i>
                </a>
            <?php else: ?>
                <button type="submit" class="btn btn-primary">
                    <?php echo $current_step === 'welcome' ? 'Get Started' : 'Save and Continue'; ?> 
                    <i class="bi bi-arrow-right ms-2"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>
</form>
