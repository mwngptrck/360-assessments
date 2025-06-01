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

// Get existing positions
$position_manager = Assessment_360_Position::get_instance();
$existing_positions = $position_manager->get_all_positions();
?>

<h2>User Positions</h2>
<p class="description">Set up the positions in your organization.</p>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('save_setup_position', 'setup_position_nonce'); ?>
    <input type="hidden" name="action" value="save_setup_position">

    <table class="wp-list-table widefat striped" id="positions-table">
        <thead>
            <tr>
                <th>Position Name</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($existing_positions)): ?>
                <?php foreach ($existing_positions as $index => $position): ?>
                    <tr class="position-row">
                        <td>
                            <input type="text" 
                                   name="positions[<?php echo $index; ?>][name]" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($position->name); ?>"
                                   placeholder="Position name">
                        </td>
                        <td>
                            <input type="text" 
                                   name="positions[<?php echo $index; ?>][description]" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($position->description); ?>"
                                   placeholder="Description">
                        </td>
                        <td>
                            <button type="button" class="button remove-row">Remove</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr class="position-row">
                <td>
                    <input type="text" 
                           name="positions[<?php echo !empty($existing_positions) ? count($existing_positions) : 0; ?>][name]" 
                           class="regular-text" 
                           placeholder="New position name">
                </td>
                <td>
                    <input type="text" 
                           name="positions[<?php echo !empty($existing_positions) ? count($existing_positions) : 0; ?>][description]" 
                           class="regular-text" 
                           placeholder="Description">
                </td>
                <td>
                    <button type="button" class="button remove-row" style="display:none;">Remove</button>
                </td>
            </tr>
        </tbody>
    </table>

    <button type="button" class="button add-row" style="margin-top: 10px;">Add Another Position</button>

    <div class="setup-actions">
        <a href="<?php echo esc_url(add_query_arg(['page' => 'assessment-360-setup', 'step' => 'groups'], admin_url('admin.php'))); ?>" class="button">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <div>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'assessment-360-setup', 'step' => 'complete'], admin_url('admin.php'))); ?>" class="button">Skip</a>
            <button type="submit" class="button button-primary">Save and Continue</button>
        </div>
    </div>
</form>

<script>
jQuery(document).ready(function($) {
    let rowCount = <?php echo !empty($existing_positions) ? count($existing_positions) + 1 : 1; ?>;
    
    $('.add-row').click(function() {
        const newRow = `
            <tr class="position-row">
                <td>
                    <input type="text" 
                           name="positions[${rowCount}][name]" 
                           class="regular-text" 
                           placeholder="Position name">
                </td>
                <td>
                    <input type="text" 
                           name="positions[${rowCount}][description]" 
                           class="regular-text" 
                           placeholder="Description">
                </td>
                <td>
                    <button type="button" class="button remove-row">Remove</button>
                </td>
            </tr>
        `;
        $('#positions-table tbody').append(newRow);
        rowCount++;
        
        if ($('.position-row').length > 1) {
            $('.remove-row').show();
        }
    });

    $(document).on('click', '.remove-row', function() {
        $(this).closest('tr').remove();
        if ($('.position-row').length <= 1) {
            $('.remove-row').hide();
        }
    });

    // Show remove buttons if there are multiple rows initially
    if ($('.position-row').length > 1) {
        $('.remove-row').show();
    }
});
</script>
