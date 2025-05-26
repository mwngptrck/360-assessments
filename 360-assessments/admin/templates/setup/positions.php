<?php
$position_manager = Assessment_360_Position_Manager::get_instance();
$positions = $position_manager->get_all_positions();
?>

<h2 class="h4 mb-4">User Positions</h2>

<form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
    <?php wp_nonce_field('save_setup_positions'); ?>
    <input type="hidden" name="action" value="save_setup_positions">
    <input type="hidden" name="next_step" value="<?php echo esc_attr($steps[$current_step]['next']); ?>">

    <div class="mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Position Name</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($positions)): ?>
                        <?php foreach ($positions as $position): ?>
                            <tr>
                                <td><?php echo esc_html($position->name); ?></td>
                                <td><?php echo esc_html($position->description); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <!-- New position row -->
                    <tr>
                        <td>
                            <input type="text" class="form-control" name="new_position_name" placeholder="New position name">
                        </td>
                        <td>
                            <input type="text" class="form-control" name="new_position_desc" placeholder="Description">
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
