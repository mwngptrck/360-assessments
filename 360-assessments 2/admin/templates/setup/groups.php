<?php 
if (!defined('ABSPATH')) exit;
$error = function_exists('assessment_360_get_wizard_error') ? assessment_360_get_wizard_error() : '';
$wizard_groups = function_exists('assessment_360_get_wizard_data') ? assessment_360_get_wizard_data('groups') : [];
?>

<h2>User Groups</h2>
<p class="description">Set up your user groups. The Peers group is required and will be created automatically.</p>

<?php if ($error): ?>
    <div class="notice notice-error"><strong><?php echo esc_html($error); ?></strong></div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('save_setup_groups', 'setup_groups_nonce'); ?>
    <input type="hidden" name="action" value="save_setup_groups">

    <table class="wp-list-table widefat striped" id="groups-table">
        <thead>
            <tr>
                <th>Group Name</th>
                <th>Description</th>
                <th>Is Department</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $wizard_groups = !empty($wizard_groups) ? $wizard_groups : [['name'=>'','desc'=>'','is_department'=>0]];
            foreach ($wizard_groups as $i => $group): ?>
            <tr>
                <td>
                    <input type="text" name="group_name[]" value="<?php echo esc_attr($group['name']); ?>" class="regular-text" placeholder="New group name" required>
                </td>
                <td>
                    <input type="text" name="group_desc[]" value="<?php echo esc_attr($group['desc']); ?>" class="regular-text" placeholder="Description">
                </td>
                <td style="text-align:center;width:100px">
                    <input type="checkbox" name="is_department[<?php echo $i; ?>]" value="1" <?php checked($group['is_department'], 1); ?>>
                </td>
                <td style="width:100px">
                    <button type="button" class="button" onclick="removeGroupRow(this)">Remove</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p>
        <button type="button" class="button" onclick="addGroupRow()">Add Another Group</button>
    </p>

    <div class="setup-actions">
        <a href="<?php echo esc_url(admin_url('admin.php?page=assessment-360-setup&step=settings')); ?>" class="button"><i class="bi bi-arrow-left"></i> Back</a>
        <div>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'assessment-360-setup', 'step' => 'positions'], admin_url('admin.php'))); ?>" class="button">Skip</a>
            <button type="submit" class="button button-primary">Save and Continue</button>
        </div>
    </div>
</form>

<script>
function addGroupRow() {
    var table = document.getElementById('groups-table').getElementsByTagName('tbody')[0];
    var index = table.rows.length;
    var row = table.insertRow();
    row.innerHTML =
        '<td><input type="text" name="group_name[]" class="regular-text" placeholder="New group name" required></td>' +
        '<td><input type="text" name="group_desc[]" class="regular-text" placeholder="Description"></td>' +
        '<td style="text-align:center;"><input type="checkbox" name="is_department['+index+']" value="1"></td>' +
        '<td><button type="button" class="button" onclick="removeGroupRow(this)">Remove</button></td>';
}
function removeGroupRow(btn) {
    var row = btn.closest('tr');
    row.parentNode.removeChild(row);
}
</script>