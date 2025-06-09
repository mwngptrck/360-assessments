<?php 
if (!defined('ABSPATH')) exit;
$error = function_exists('assessment_360_get_wizard_error') ? assessment_360_get_wizard_error() : '';
$wizard_positions = function_exists('assessment_360_get_wizard_data') ? assessment_360_get_wizard_data('positions') : [];
?>

<h2>User Positions</h2>
<p class="description">Set up the positions in your organization.</p>

<?php if ($error): ?>
    <div class="notice notice-error"><strong><?php echo esc_html($error); ?></strong></div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('save_setup_positions', 'setup_positions_nonce'); ?>
    <input type="hidden" name="action" value="save_setup_positions">

    <table class="wp-list-table widefat striped" id="positions-table">
        <thead>
            <tr>
                <th>Position Name</th>
                <th>Description</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $wizard_positions = !empty($wizard_positions) ? $wizard_positions : [['name'=>'','desc'=>'']];
            foreach ($wizard_positions as $i => $pos): ?>
            <tr>
                <td>
                    <input type="text" name="position_name[]" value="<?php echo esc_attr($pos['name']); ?>" class="regular-text" placeholder="New position name" required>
                </td>
                <td>
                    <input type="text" name="position_desc[]" value="<?php echo esc_attr($pos['desc']); ?>" class="regular-text" placeholder="Description">
                </td>
                <td style="width:100px">
                    <button type="button" class="button" onclick="removePositionRow(this)">Remove</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p>
        <button type="button" class="button" onclick="addPositionRow()">Add Another Position</button>
    </p>

    <div class="setup-actions">
        <a href="<?php echo esc_url(admin_url('admin.php?page=assessment-360-setup&step=groups')); ?>" class="button"><i class="bi bi-arrow-left"></i> Back</a>
        <div>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'assessment-360-setup', 'step' => 'complete'], admin_url('admin.php'))); ?>" class="button">Skip</a>
            <button type="submit" class="button button-primary">Save and Continue</button>
        </div>
    </div>
</form>

<script>
function addPositionRow() {
    var table = document.getElementById('positions-table').getElementsByTagName('tbody')[0];
    var row = table.insertRow();
    row.innerHTML =
        '<td><input type="text" name="position_name[]" class="regular-text" placeholder="New position name" required></td>' +
        '<td><input type="text" name="position_desc[]" class="regular-text" placeholder="Description"></td>' +
        '<td><button type="button" class="button" onclick="removePositionRow(this)">Remove</button></td>';
}
function removePositionRow(btn) {
    var row = btn.closest('tr');
    row.parentNode.removeChild(row);
}
</script>