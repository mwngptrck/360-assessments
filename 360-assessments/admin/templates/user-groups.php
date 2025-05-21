<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1 class="wp-heading-inline">User Groups</h1>
    <?php if (!isset($_GET['action']) || $_GET['action'] !== 'edit'): ?>
        <a href="?page=assessment-360-groups&action=new" class="page-title-action">Add New</a>
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
    $group_manager = Assessment_360_Group_Manager::get_instance();
    $user_manager = Assessment_360_User_Manager::get_instance();
    
    if (isset($_GET['action']) && ($_GET['action'] == 'new' || $_GET['action'] == 'edit')):
        $group = null;
        if ($_GET['action'] == 'edit' && isset($_GET['id'])) {
            $group = $group_manager->get_group(intval($_GET['id']));
            if (!$group) {
                echo '<div class="notice notice-error"><p>User group not found.</p></div>';
                return;
            }
        }
    ?>
        <div class="group-form-container">
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('assessment_360_group_nonce'); ?>
                <input type="hidden" name="action" value="assessment_360_save_group">
                <?php if ($group): ?>
                    <input type="hidden" name="group_id" value="<?php echo esc_attr($group->id); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="group_name">Group Name *</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="group_name" 
                                   name="group_name" 
                                   class="regular-text" 
                                   value="<?php echo $group ? esc_attr($group->group_name) : ''; ?>" 
                                   <?php echo ($group && strtolower($group->group_name) === 'peers') ? 'readonly' : ''; ?>
                                   required>
                            <?php if ($group && strtolower($group->group_name) === 'peers'): ?>
                                <p class="description">The Peers group is a system group and cannot be modified.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <?php if (!($group && strtolower($group->group_name) === 'peers')): ?>
                        <button type="submit" class="button button-primary">Save Group</button>
                    <?php endif; ?>
                    <a href="?page=assessment-360-groups" class="button">Cancel</a>
                </p>
            </form>
        </div>

    <?php else: 
        $groups = $group_manager->get_all_groups();
    ?>
    <div class="group-listing-container">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Users</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($groups)): ?>
                    <?php foreach ($groups as $group): 
                        $user_count = $user_manager->get_user_count_by_group($group->id);
                        $is_peers = strtolower($group->group_name) === 'peers';
                    ?>
                        <tr>
                            <td>
                                <strong>
                                    <a href="?page=assessment-360-groups&action=edit&id=<?php echo $group->id; ?>">
                                        <?php echo esc_html($group->group_name); ?>
                                    </a>
                                    <?php if ($is_peers): ?>
                                        <span class="system-group-badge">System Group</span>
                                    <?php endif; ?>
                                </strong>
                            </td>
                            <td><?php echo esc_html($user_count); ?></td>
                            <td>
                                <a href="?page=assessment-360-groups&action=edit&id=<?php echo $group->id; ?>" 
                                   class="button button-small">Edit</a>
                                
                                <?php if (!$is_peers && $user_count == 0): ?>
                                    <a href="<?php echo wp_nonce_url(
                                        add_query_arg(
                                            array(
                                                'page' => 'assessment-360-groups',
                                                'action' => 'delete',
                                                'id' => $group->id
                                            ),
                                            admin_url('admin.php')
                                        ),
                                        'delete_group_' . $group->id
                                    ); ?>" 
                                       class="button button-small"
                                       onclick="return confirm('Are you sure you want to delete this group?');">
                                        Delete
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">No user groups found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.group-form-container {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-top: 20px;
}
.group-listing-container {
    max-width: 100%;
    margin: 2rem auto;
    padding: 2rem;
    background-color: #ffffff;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}

.button-small {
    margin: 0 5px;
}

.button-small:first-child {
    margin-left: 0;
}

.system-group-badge {
    display: inline-block;
    background: #0073aa;
    color: #fff;
    font-size: 12px;
    padding: 2px 6px;
    border-radius: 3px;
    margin-left: 10px;
    vertical-align: middle;
}

.wp-list-table .column-name {
    width: 40%;
}

.wp-list-table .column-users {
    width: 30%;
}

.wp-list-table .column-actions {
    width: 30%;
}
</style>
