<?php 
if (!defined('ABSPATH')) exit;

$group_manager = Assessment_360_Group_Manager::get_instance();
$groups = $group_manager->get_all_groups();
?>

<h2>User Groups</h2>
<p class="description">Set up your user groups. The Peers group is required and will be created automatically.</p>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('save_setup_groups', 'setup_groups_nonce'); ?>
    <input type="hidden" name="action" value="save_setup_groups">

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Group Name</th>
                <th>Description</th>
                <th>Is Department</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <input type="text" 
                           name="new_group_name" 
                           class="regular-text" 
                           placeholder="New group name">
                </td>
                <td>
                    <input type="text" 
                           name="new_group_desc" 
                           class="regular-text" 
                           placeholder="Description">
                </td>
                <td>
                    <input type="checkbox" 
                           name="new_group_is_department" 
                           value="1">
                </td>
            </tr>
        </tbody>
    </table>

    <div class="setup-actions">
        <a href="<?php echo esc_url(admin_url('admin.php?page=assessment-360-setup&step=positions')); ?>" 
           class="button">Skip</a>
        <button type="submit" class="button button-primary">Save and Continue</button>
    </div>
</form>
