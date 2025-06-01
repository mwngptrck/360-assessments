<?php
if (!defined('ABSPATH')) exit;

// Check for missing tables
$missing_tables = get_option('assessment_360_missing_tables');
if ($missing_tables) {
    ?>
    <div class="notice notice-error">
        <p><strong>Error:</strong> Required database tables are missing. Please deactivate and reactivate the plugin.</p>
        <p>Missing tables: <?php echo esc_html(implode(', ', $missing_tables)); ?></p>
    </div>
    <?php
    return;
}

// Get existing groups
$group_manager = Assessment_360_Group_Manager::get_instance();
$existing_groups = $group_manager->get_all_groups();
?>

<h2>User Groups</h2>
<p class="description">Set up your user groups. The Peers group is required and will be created automatically.</p>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('save_setup_group', 'setup_group_nonce'); ?>
    <input type="hidden" name="action" value="save_setup_group">

    <table class="wp-list-table widefat striped" id="groups-table">
        <thead>
            <tr>
                <th>Group Name</th>
                <th>Description</th>
                <th>Is Department</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($existing_groups)): ?>
                <?php foreach ($existing_groups as $index => $group): ?>
                    <tr class="group-row">
                        <td>
                            <input type="text" 
                                   name="groups[<?php echo $index; ?>][name]" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($group->group_name); ?>"
                                   placeholder="Group name">
                        </td>
                        <td>
                            <input type="text" 
                                   name="groups[<?php echo $index; ?>][description]" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($group->description); ?>"
                                   placeholder="Description">
                        </td>
                        <td>
                            <input type="checkbox" 
                                   name="groups[<?php echo $index; ?>][is_department]" 
                                   value="1"
                                   <?php checked($group->is_department, 1); ?>>
                        </td>
                        <td>
                            <button type="button" class="button remove-row">Remove</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr class="group-row">
                <td>
                    <input type="text" 
                           name="groups[<?php echo !empty($existing_groups) ? count($existing_groups) : 0; ?>][name]" 
                           class="regular-text" 
                           placeholder="New group name">
                </td>
                <td>
                    <input type="text" 
                           name="groups[<?php echo !empty($existing_groups) ? count($existing_groups) : 0; ?>][description]" 
                           class="regular-text" 
                           placeholder="Description">
                </td>
                <td>
                    <input type="checkbox" 
                           name="groups[<?php echo !empty($existing_groups) ? count($existing_groups) : 0; ?>][is_department]" 
                           value="1">
                </td>
                <td>
                    <button type="button" class="button remove-row" style="display:none;">Remove</button>
                </td>
            </tr>
        </tbody>
    </table>

    <button type="button" class="button add-row" style="margin-top: 10px;">Add Another Group</button>

    <div class="setup-actions">
        <a href="<?php echo esc_url(add_query_arg(['page' => 'assessment-360-setup', 'step' => 'settings'], admin_url('admin.php'))); ?>" class="button">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <div>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'assessment-360-setup', 'step' => 'positions'], admin_url('admin.php'))); ?>" class="button">Skip</a>
            <button type="submit" class="button button-primary">Save and Continue</button>
        </div>
    </div>
</form>

<script>
jQuery(document).ready(function($) {
    let rowCount = <?php echo !empty($existing_groups) ? count($existing_groups) + 1 : 1; ?>;
    
    $('.add-row').click(function() {
        const newRow = `
            <tr class="group-row">
                <td>
                    <input type="text" 
                           name="groups[${rowCount}][name]" 
                           class="regular-text" 
                           placeholder="Group name">
                </td>
                <td>
                    <input type="text" 
                           name="groups[${rowCount}][description]" 
                           class="regular-text" 
                           placeholder="Description">
                </td>
                <td>
                    <input type="checkbox" 
                           name="groups[${rowCount}][is_department]" 
                           value="1">
                </td>
                <td>
                    <button type="button" class="button remove-row">Remove</button>
                </td>
            </tr>
        `;
        $('#groups-table tbody').append(newRow);
        rowCount++;
        
        if ($('.group-row').length > 1) {
            $('.remove-row').show();
        }
    });

    $(document).on('click', '.remove-row', function() {
        $(this).closest('tr').remove();
        if ($('.group-row').length <= 1) {
            $('.remove-row').hide();
        }
    });

    // Show remove buttons if there are multiple rows initially
    if ($('.group-row').length > 1) {
        $('.remove-row').show();
    }
});
</script>
