<?php 
if (!defined('ABSPATH')) exit;

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die('Sorry, you are not allowed to access this page.');
}

// Initialize manager
$position_manager = Assessment_360_Position::get_instance();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Positions</h1>
    
    <?php if (!isset($_GET['action']) || $_GET['action'] !== 'edit'): ?>
        <a href="<?php echo esc_url(add_query_arg('action', 'new')); ?>" class="page-title-action">Add New</a>
    <?php endif; ?>

    <?php if (isset($_GET['message'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($_GET['message']); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($_GET['error']); ?></p>
        </div>
    <?php endif; ?>

    <?php 
    // Show form for add/edit
    if (isset($_GET['action']) && ($_GET['action'] === 'new' || $_GET['action'] === 'edit')):
        $position = null;
        if ($_GET['action'] === 'edit' && isset($_GET['id'])) {
            $position = $position_manager->get_position(intval($_GET['id']));
            if (!$position) {
                echo '<div class="notice notice-error"><p>Position not found.</p></div>';
                return;
            }
        }
    ?>
        <div class="position-form-container">
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('save_position'); ?>
                <input type="hidden" name="action" value="save_position">
                <?php if ($position): ?>
                    <input type="hidden" name="id" value="<?php echo esc_attr($position->id); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="name">Position Name *</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   class="regular-text" 
                                   value="<?php echo $position ? esc_attr($position->name) : ''; ?>" 
                                   required>
                            <p class="description">
                                Position names must be unique. This will be used to identify the position throughout the system.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="description">Description</label>
                        </th>
                        <td>
                            <textarea id="description" 
                                      name="description" 
                                      class="large-text" 
                                      rows="5"><?php echo $position ? esc_textarea($position->description ?? '') : ''; ?></textarea>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $position ? 'Update Position' : 'Add Position'; ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=assessment-360-positions'); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>

    <?php else: ?>
        <!-- Positions List -->
    <div class="position-listing-container">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $positions = $position_manager->get_all_positions(true);
                $has_active = false;
                $has_deleted = false;

                if (!empty($positions)): 
                    foreach ($positions as $position):
                        if ($position->status === 'active') $has_active = true;
                        if ($position->status === 'deleted') $has_deleted = true;

                        // Skip deleted items on first pass
                        if ($position->status === 'deleted') continue;
                ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($position->name); ?></strong>
                        </td>
                        <td><?php echo !empty($position->description) ? esc_html($position->description) : '—'; ?></td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($position->status); ?>">
                                <?php echo esc_html(ucfirst($position->status)); ?>
                            </span>
                        </td>
                        <td class="actions-column">
                            <a href="<?php echo esc_url(add_query_arg(['action' => 'edit', 'id' => $position->id])); ?>" 
                               class="button button-small">Edit</a>

                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" 
                                  style="display:inline-block;"
                                  onsubmit="return confirm('Are you sure you want to delete this position?');">
                                <?php wp_nonce_field('delete_position_' . $position->id); ?>
                                <input type="hidden" name="action" value="delete_position">
                                <input type="hidden" name="id" value="<?php echo esc_attr($position->id); ?>">
                                <button type="submit" class="button button-small">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php 
                    endforeach;

                    if ($has_deleted):
                        // Add a separator row
                        echo '<tr class="deleted-separator"><td colspan="4"><h3>Deleted Positions</h3></td></tr>';

                        // Show deleted positions
                        foreach ($positions as $position):
                            if ($position->status !== 'deleted') continue;
                ?>
                    <tr class="deleted-row">
                        <td>
                            <strong><?php echo esc_html($position->name); ?></strong>
                        </td>
                        <td><?php echo !empty($position->description) ? esc_html($position->description) : '—'; ?></td>
                        <td>
                            <span class="status-badge status-deleted">
                                Deleted
                            </span>
                        </td>
                        <td class="actions-column">
                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" 
                                  style="display:inline-block;"
                                  onsubmit="return confirm('Are you sure you want to restore this position?');">
                                <?php wp_nonce_field('restore_position_' . $position->id); ?>
                                <input type="hidden" name="action" value="restore_position">
                                <input type="hidden" name="id" value="<?php echo esc_attr($position->id); ?>">
                                <button type="submit" class="button button-small button-primary">Restore</button>
                            </form>
                        </td>
                    </tr>
                <?php 
                        endforeach;
                    endif;

                    if (!$has_active && !$has_deleted):
                ?>
                    <tr>
                        <td colspan="4">No positions found.</td>
                    </tr>
                <?php 
                    endif;
                endif; 
                ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.actions-column form {
    padding: 0 !important;
}

.position-form-container {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-top: 20px;
}
.position-listing-container {
    max-width: 100%;
    margin: 2rem auto;
    padding: 2rem;
    background-color: #ffffff;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}

.actions-column {
    width: 200px;
}

.actions-column .button {
    margin-right: 5px;
}

.actions-column form {
    margin: 0;
}

.wp-list-table td {
    vertical-align: middle;
}

.button-small {
    min-height: 25px;
    padding: 0 10px;
    line-height: 23px;
}

.large-text {
    width: 100%;
    max-width: 500px;
}

@media screen and (max-width: 782px) {
    .actions-column {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }
    
    .actions-column .button,
    .actions-column form {
        width: auto;
    }
    
    .button-small {
        min-height: 30px;
        line-height: 28px;
    }
}
    
.loading {
    opacity: 0.6;
    pointer-events: none;
}

.form-table input[type="text"]:invalid,
.form-table textarea:invalid {
    border-color: #dc3232;
}

.form-table input[type="text"]:valid,
.form-table textarea:valid {
    border-color: #46b450;
}

.deleted-separator td {
    padding: 20px 10px 10px !important;
    background-color: #f8f9fa;
}

.deleted-separator h3 {
    margin: 0;
    color: #666;
    font-size: 1.1em;
    font-weight: 500;
}

.deleted-row {
    opacity: 0.8;
    background-color: #f8f9fa !important;
}

.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge.status-active {
    background-color: #00a32a;
    color: #fff;
}

.status-badge.status-deleted {
    background-color: #cc1818;
    color: #fff;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Store initial name for edit form
    const initialName = $('#name').val();
    
    $('form').on('submit', function(e) {
        const name = $('#name').val().trim();
        
        // Check if name is empty
        if (!name) {
            e.preventDefault();
            alert('Position name is required.');
            return false;
        }
        
        // If editing and name hasn't changed, continue
        if (initialName && name === initialName) {
            return true;
        }
        
        // Add loading state
        $(this).addClass('loading');
        $('button[type="submit"]').prop('disabled', true);
    });
});
</script>
