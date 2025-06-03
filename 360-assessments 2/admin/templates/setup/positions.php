<?php 
if (!defined('ABSPATH')) exit;

// Use the existing Position class
$position = Assessment_360_Position::get_instance();
$positions = $position->get_all_positions();  

?>

<h2>User Positions</h2>
<p class="description">Set up the positions in your organization.</p>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('save_setup_positions', 'setup_positions_nonce'); ?>
    <input type="hidden" name="action" value="save_setup_positions">

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Position Name</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($positions)): ?>
                <?php foreach ($positions as $pos): ?>
                    <tr>
                        <td><?php echo esc_html($pos->name); ?></td>
                        <td><?php echo esc_html($pos->description); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr>
                <td>
                    <input type="text" 
                           name="new_position_name" 
                           class="regular-text" 
                           placeholder="New position name">
                </td>
                <td>
                    <input type="text" 
                           name="new_position_desc" 
                           class="regular-text" 
                           placeholder="Description">
                </td>
            </tr>
        </tbody>
    </table>

    <div class="setup-actions">
        <a href="<?php echo esc_url(admin_url('admin.php?page=assessment-360-setup&step=complete')); ?>" 
           class="button">Skip</a>
        <button type="submit" class="button button-primary">Save and Continue</button>
    </div>
</form>
