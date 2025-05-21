<?php 
if (!defined('ABSPATH')) exit;

if (WP_DEBUG) {
    error_log('Starting users.php template');
}

try {
    // Check permissions
    if (!current_user_can('manage_options')) {
        ?>
        <div class="notice notice-error"><p><?php esc_html_e('Unauthorized access attempt'); ?></p></div>
        <?php
        return;
    }

    global $wpdb;

    // Enable error reporting for debug (optional, keep for dev only)
    if (WP_DEBUG) {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        error_log('Loading users.php page');
        error_log('REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
        error_log('GET parameters: ' . print_r($_GET, true));
    }

    // Initialize managers with error handling
    try {
        if (WP_DEBUG) error_log('Initializing managers');
        
        $user_manager = Assessment_360_User_Manager::get_instance();
        $position_manager = Assessment_360_Position::get_instance();
        $group_manager = Assessment_360_Group_Manager::get_instance();
        
        if (WP_DEBUG) error_log('Managers initialized successfully');
    } catch (Exception $e) {
        ?>
        <div class="notice notice-error"><p><?php echo esc_html('Failed to initialize managers: ' . $e->getMessage()); ?></p></div>
        <?php
        return;
    }

    // Get parameters with validation
    $current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'active';
    $current_action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
    $user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if (WP_DEBUG) {
        error_log("Parameters loaded:");
        error_log("Status: $current_status");
        error_log("Action: $current_action");
        error_log("User ID: $user_id");
    }

    // Initialize variables
    $user = null;
    $positions = [];
    $groups = [];
    $potential_assessors = [];
    $current_assessor_ids = [];

    // Load data based on action
    if ($current_action === 'edit' || $current_action === 'new') {
        if (WP_DEBUG) error_log('Loading form data');

        try {
            // Load positions and groups
            $positions = $position_manager->get_all_positions();
            $groups = $group_manager->get_all_groups();

            if (WP_DEBUG) {
                error_log('Positions loaded: ' . count($positions));
                error_log('Groups loaded: ' . count($groups));
            }

            // Get user data if editing
            if ($current_action === 'edit' && $user_id) {
                if (WP_DEBUG) error_log("Loading user data for ID: $user_id");
                
                $user = $user_manager->get_user($user_id);
                
                if (!$user) {
                    ?>
                    <div class="notice notice-error"><p><?php echo esc_html("User not found with ID: $user_id"); ?></p></div>
                    <?php
                    return;
                }

                // Get current assessors
                $current_assessors = $user_manager->get_user_assessors($user_id);
                $current_assessor_ids = array_map(function($assessor) {
                    return $assessor->id;
                }, $current_assessors);

                if (WP_DEBUG) {
                    error_log('User loaded successfully');
                    error_log('Current assessors: ' . count($current_assessor_ids));
                }
            }

            // Get potential assessors
            $potential_assessors = $user_manager->get_all_active_users();
            
            // Remove current user from potential assessors if editing
            if ($user) {
                $potential_assessors = array_filter($potential_assessors, function($assessor) use ($user) {
                    return $assessor->id != $user->id;
                });
            }

            if (WP_DEBUG) {
                error_log('Potential assessors loaded: ' . count($potential_assessors));
            }

        } catch (Exception $e) {
            ?>
            <div class="notice notice-error"><p><?php echo esc_html('Error loading form data: ' . $e->getMessage()); ?></p></div>
            <?php
            return;
        }
    }

    if (WP_DEBUG) {
        error_log('Starting template render');
    }

} catch (Exception $e) {
    if (WP_DEBUG) {
        error_log('Error in users.php initialization: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
    }
    ?>
    <div class="notice notice-error"><p><?php echo esc_html($e->getMessage()); ?></p></div>
    <?php
    return;
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline">Users</h1>
    
    <?php if (!isset($_GET['action']) || $_GET['action'] !== 'edit'): ?>
        <a href="<?php echo esc_url(add_query_arg([
            'page' => 'assessment-360-users',
            'action' => 'new',
            'status' => $current_status
        ], admin_url('admin.php'))); ?>" class="page-title-action">Add New</a>
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
        if (WP_DEBUG) {
            error_log('Rendering form section');
            error_log('Action: ' . $_GET['action']);
            error_log('User data: ' . ($user ? print_r($user, true) : 'New User'));
        }
    ?>
        <div class="user-form-container">
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="validate">
                <?php wp_nonce_field('save_user_nonce', 'save_user_nonce'); ?>
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="status" value="<?php echo esc_attr($current_status); ?>">
                <?php if ($user): ?>
                    <input type="hidden" name="id" value="<?php echo esc_attr($user->id); ?>">
                <?php endif; ?>

                

                
                <div class="row">
                    <div class="col">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="first_name">First Name *</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="first_name" 
                                           name="first_name" 
                                           class="regular-text" 
                                           value="<?php echo $user ? esc_attr($user->first_name) : ''; ?>" 
                                           required>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="last_name">Last Name *</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="last_name" 
                                           name="last_name" 
                                           class="regular-text" 
                                           value="<?php echo $user ? esc_attr($user->last_name) : ''; ?>" 
                                           required>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="email">Email Address *</label>
                                </th>
                                <td>
                                    <input type="email" 
                                           id="email" 
                                           name="email" 
                                           class="regular-text" 
                                           value="<?php echo $user ? esc_attr($user->email) : ''; ?>" 
                                           required>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="phone">Phone Number</label>
                                </th>
                                <td>
                                    <input type="tel" 
                                           id="phone" 
                                           name="phone" 
                                           class="regular-text" 
                                           value="<?php echo $user ? esc_attr($user->phone) : ''; ?>">
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="group_id">User Group *</label>
                                </th>
                                <td>
                                    <select id="group_id" name="group_id" class="regular-text" required>
                                        <option value="">Select Group</option>
                                        <?php foreach ($groups as $group): ?>
                                            <option value="<?php echo esc_attr($group->id); ?>" 
                                                    <?php selected($user && $user->group_id == $group->id); ?>
                                                    data-group-name="<?php echo esc_attr($group->group_name); ?>">
                                                <?php echo esc_html($group->group_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>

                            <tr class="position-row" style="display: none;">
                                <th scope="row">
                                    <label for="position_id">Position</label>
                                </th>
                                <td>
                                    <select id="position_id" 
                                            name="position_id" 
                                            class="regular-text" 
                                            disabled>
                                        <option value="">Select Position</option>
                                        <?php foreach ($positions as $position): ?>
                                            <option value="<?php echo esc_attr($position->id); ?>" 
                                                    <?php selected($user && $user->position_id == $position->id); ?>>
                                                <?php echo esc_html($position->name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description position-description" style="display: none;">
                                        Position is required for Peers group members.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="col">
                        <div id="assessors-section" class="assessors-section">
                            <h4>Assign Assessors</h4>
                            

                            <div class="assessors-container">
                                <?php 
                                if (!empty($potential_assessors)):
                                    // Group users by their group
                                    $grouped_users = array();
                                    foreach ($potential_assessors as $assessor) {
                                        $group_name = $assessor->group_name ?? 'Ungrouped';
                                        if (!isset($grouped_users[$group_name])) {
                                            $grouped_users[$group_name] = array();
                                        }
                                        $grouped_users[$group_name][] = $assessor;
                                    }

                                    // Sort groups alphabetically
                                    ksort($grouped_users);

                                    if (WP_DEBUG) {
                                        error_log('Grouped users for display:');
                                        error_log('Total groups: ' . count($grouped_users));
                                        foreach ($grouped_users as $group => $users) {
                                            error_log("Group '$group': " . count($users) . ' users');
                                        }
                                    }
                                ?>
                                    <div class="assessors-groups">
                                        <?php foreach ($grouped_users as $group_name => $group_users): ?>
                                            <div class="assessor-group">
                                                <div class="group-header">
                                                    <label>
                                                        <input type="checkbox" 
                                                               class="group-select" 
                                                               data-group="<?php echo esc_attr($group_name); ?>">
                                                        <?php echo esc_html($group_name); ?>
                                                        <span class="group-count">(<?php echo count($group_users); ?>)</span>
                                                    </label>
                                                </div>
                                                <div class="group-assessors">
                                                    <?php foreach ($group_users as $assessor): ?>
                                                        <div class="assessor-item">
                                                            <label class="assessor-label">
                                                                <input type="checkbox" 
                                                                       name="assessors[]" 
                                                                       value="<?php echo esc_attr($assessor->id); ?>"
                                                                       class="assessor-select group-<?php echo esc_attr($group_name); ?>"
                                                                       <?php checked(in_array($assessor->id, $current_assessor_ids)); ?>>
                                                                <span class="assessor-name">
                                                                    <?php echo esc_html($assessor->first_name . ' ' . $assessor->last_name); ?>
                                                                </span>
                                                                <?php if (!empty($assessor->position_name)): ?>
                                                                    <span class="position-label">
                                                                        (<?php echo esc_html($assessor->position_name); ?>)
                                                                    </span>
                                                                <?php endif; ?>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="assessors-actions">
                                        <button type="button" class="button select-all-assessors">Select All Groups</button>
                                        <button type="button" class="button clear-all-assessors">Clear All</button>
                                        <span class="selected-count"></span>
                                    </div>
                                <?php else: ?>
                                    <p class="description">
                                        <?php if ($user): ?>
                                            No other active users available to be assessors.
                                        <?php else: ?>
                                            Assessors can be assigned after creating the user.
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $user ? 'Update User' : 'Add User'; ?>
                    </button>
                    <a href="<?php echo esc_url(add_query_arg([
                        'page' => 'assessment-360-users',
                        'status' => $current_status
                    ], admin_url('admin.php'))); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
    <?php else: ?>
        <!-- Status Tabs -->
        <ul class="subsubsub">
            <li>
                <a href="<?php echo esc_url(add_query_arg([
                    'page' => 'assessment-360-users',
                    'status' => 'active'
                ], admin_url('admin.php'))); ?>" 
                   class="<?php echo $current_status === 'active' ? 'current' : ''; ?>">
                    Active 
                    <span class="count">(<?php echo $user_manager->get_users_count('active'); ?>)</span>
                </a> |
            </li>
            <li>
                <a href="<?php echo esc_url(add_query_arg([
                    'page' => 'assessment-360-users',
                    'status' => 'inactive'
                ], admin_url('admin.php'))); ?>" 
                   class="<?php echo $current_status === 'inactive' ? 'current' : ''; ?>">
                    Disabled 
                    <span class="count">(<?php echo $user_manager->get_users_count('inactive'); ?>)</span>
                </a>
            </li>
        </ul>

        <!-- Users List Table -->
        <form id="users-filter" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="bulk_action_users">
            <input type="hidden" name="status" value="<?php echo esc_attr($current_status); ?>">
            <?php wp_nonce_field('bulk_action_users', 'bulk_action_nonce'); ?>

            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="bulk-action">
                        <option value="">Bulk Actions</option>
                        <?php if ($current_status === 'active'): ?>
                            <option value="disable">Disable</option>
                        <?php else: ?>
                            <option value="enable">Enable</option>
                        <?php endif; ?>
                        <option value="delete">Delete</option>
                    </select>
                    <input type="submit" class="button action" value="Apply">
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-1">
                        </td>
                        <th scope="col" class="manage-column column-name">Name</th>
                        <th scope="col" class="manage-column column-email">Email</th>
                        <th scope="col" class="manage-column column-position">Position</th>
                        <th scope="col" class="manage-column column-group">Group</th>
                        <th scope="col" class="manage-column column-status">Status</th>
                        <th scope="col" class="manage-column column-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $users = $user_manager->get_users_by_status($current_status);
                    if (!empty($users)): 
                        foreach ($users as $list_user): 
                    ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="users[]" value="<?php echo esc_attr($list_user->id); ?>">
                            </th>
                            <td class="column-name">
                                <strong>
                                    <a href="<?php echo esc_url(add_query_arg([
                                        'page' => 'assessment-360-users',
                                        'action' => 'edit',
                                        'id' => $list_user->id,
                                        'status' => $current_status
                                    ], admin_url('admin.php'))); ?>" class="row-title">
                                        <?php echo esc_html($list_user->first_name . ' ' . $list_user->last_name); ?>
                                    </a>
                                </strong>
                            </td>
                            <td class="column-email"><?php echo esc_html($list_user->email); ?></td>
                            <td class="column-position"><?php echo esc_html($list_user->position_name ?? '—'); ?></td>
                            <td class="column-group"><?php echo esc_html($list_user->group_name ?? '—'); ?></td>
                            <td class="column-status">
                                <span class="status-badge status-<?php echo esc_attr($list_user->status); ?>">
                                    <?php echo esc_html(ucfirst($list_user->status)); ?>
                                </span>
                            </td>
                            <td class="column-actions">
                                <div class="actions-buttons">
                                    <a href="<?php echo esc_url(add_query_arg([
                                        'page' => 'assessment-360-users',
                                        'action' => 'edit',
                                        'id' => $list_user->id,
                                        'status' => $current_status
                                    ], admin_url('admin.php'))); ?>" class="button button-small">Edit</a>

                                    <?php if ($list_user->status === 'active'): ?>
                                        <a href="<?php echo wp_nonce_url(
                                            add_query_arg([
                                                'page' => 'assessment-360-users',
                                                'action' => 'disable_user',
                                                'id' => $list_user->id,
                                                'status' => $current_status
                                            ], admin_url('admin.php')),
                                            'user_status_' . $list_user->id
                                        ); ?>" class="button button-small">Disable</a>
                                    <?php else: ?>
                                        <a href="<?php echo wp_nonce_url(
                                            add_query_arg([
                                                'page' => 'assessment-360-users',
                                                'action' => 'enable_user',
                                                'id' => $list_user->id,
                                                'status' => $current_status
                                            ], admin_url('admin.php')),
                                            'user_status_' . $list_user->id
                                        ); ?>" class="button button-small">Enable</a>
                                    <?php endif; ?>

                                    <a href="<?php echo wp_nonce_url(
                                        add_query_arg([
                                            'page' => 'assessment-360-users',
                                            'action' => 'delete_user',
                                            'id' => $list_user->id,
                                            'status' => $current_status
                                        ], admin_url('admin.php')),
                                        'delete_user_' . $list_user->id
                                    ); ?>" class="button button-small delete-user">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                        <tr>
                            <td colspan="7">
                                <?php echo $current_status === 'active' ? 'No active users found.' : 'No disabled users found.'; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </form>
    <?php endif; ?>
</div>
<style>
/* Form Container */
.user-form-container {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-top: 20px;
}

.subsubsub {float:none}

/* Table Layout */
.wp-list-table {
    margin-top: 1rem;
}

.wp-list-table th {
    font-weight: 600;
}

.wp-list-table td, 
.wp-list-table th {
    vertical-align: middle;
}

/* Column Widths */
.column-cb {
    width: 3%;
}

.column-name {
    width: 20%;
}

.column-email {
    width: 25%;
}

.column-position,
.column-group {
    width: 15%;
}

.column-status {
    width: 10%;
}

.column-actions {
    width: 200px;
    padding: 8px !important;
}

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
    text-transform: capitalize;
}

.status-badge.status-active {
    background-color: #00a32a;
    color: #fff;
}

.status-badge.status-inactive {
    background-color: #cc1818;
    color: #fff;
}

/* Action Buttons */
.actions-buttons {
    display: flex;
    gap: 5px;
    white-space: nowrap;
    min-width: 180px;
}

.button-small {
    padding: 0 8px !important;
    line-height: 22px !important;
    height: 24px !important;
    font-size: 11px !important;
    margin: 0 !important;
}

/* Assessors Section */
.assessors-section {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-top: 20px;
}

.assessors-container {
    margin-top: 15px;
    max-height: 500px;
    overflow-y: auto;
}

.assessors-groups {
    border: 1px solid #ddd;
    background: #f8f9fa;
    border-radius: 4px;
}

.assessor-group {
    border-bottom: 1px solid #ddd;
}

.assessor-group:last-child {
    border-bottom: none;
}

.group-header {
    padding: 12px 15px;
    background: #fff;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    user-select: none;
}

.group-header:hover {
    background: #f8f9fa;
}

.group-header label {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    margin: 0;
    cursor: pointer;
}

.group-count {
    color: #666;
    font-weight: normal;
    font-size: 0.9em;
    margin-left: 5px;
}

.group-assessors {
    padding: 5px 15px 15px 35px;
    background: #f8f9fa;
}

.assessor-item {
    padding: 5px 0;
}

.assessor-label {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px;
    border-radius: 4px;
    cursor: pointer;
}

.assessor-label:hover {
    background-color: #f0f0f1;
}

.assessor-name {
    font-weight: normal;
}

.position-label {
    color: #666;
    font-size: 0.9em;
}

.assessors-actions {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
    display: flex;
    gap: 10px;
    align-items: center;
}

.selected-count {
    color: #666;
    font-style: italic;
    margin-left: auto;
}

/* Form Validation */
.error {
    border-color: #cc1818 !important;
}

.form-table td {
    position: relative;
}

.error-message {
    color: #cc1818;
    font-size: 12px;
    margin-top: 5px;
    display: block;
}

/* Responsive Design */
@media screen and (max-width: 782px) {
    .column-position,
    .column-group {
        display: none;
    }

    .actions-buttons {
        flex-wrap: wrap;
        gap: 4px;
    }
    
    .button-small {
        min-height: 30px;
        line-height: 28px !important;
        padding: 0 10px !important;
        width: auto;
        text-align: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Form Validation
    $('form').on('submit', function(e) {
        // Skip validation for bulk actions form
        if ($(this).attr('id') === 'users-filter') {
            return true;
        }

        const requiredFields = ['first_name', 'last_name', 'email', 'group_id'];
        let hasError = false;
        
        // Clear previous error messages
        $('.error-message').remove();
        $('.error').removeClass('error');
        
        // Validate required fields
        requiredFields.forEach(field => {
            const input = $(`#${field}`);
            const value = input.val().trim();
            
            if (!value) {
                input.addClass('error');
                input.closest('td').append(
                    `<span class="error-message">This field is required</span>`
                );
                hasError = true;
            }
        });
        
        // Validate email format
        const emailInput = $('#email');
        const emailValue = emailInput.val().trim();
        if (emailValue && !isValidEmail(emailValue)) {
            emailInput.addClass('error');
            emailInput.closest('td').append(
                `<span class="error-message">Please enter a valid email address</span>`
            );
            hasError = true;
        }

        // Validate position for Peers group
        const groupSelect = $('#group_id');
        const selectedOption = groupSelect.find('option:selected');
        const groupName = selectedOption.data('group-name');
        
        if (groupName && groupName.toLowerCase() === 'peers' && !$('#position_id').val()) {
            $('#position_id').addClass('error');
            $('#position_id').closest('td').append(
                `<span class="error-message">Position is required for Peers group members</span>`
            );
            hasError = true;
        }
        
        if (hasError) {
            e.preventDefault();
            // Scroll to first error
            $('html, body').animate({
                scrollTop: $('.error').first().offset().top - 100
            }, 500);
            return false;
        }

        // Disable submit button to prevent double submission
        $(this).find('button[type="submit"]').prop('disabled', true);
        
        // Add loading indicator
        $(this).addClass('loading');
        
        return true;
    });

    // Email validation helper
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // Handle group selection and dependent fields
    function toggleGroupDependentFields() {
        const groupSelect = $('#group_id');
        const selectedOption = groupSelect.find('option:selected');
        const groupName = selectedOption.data('group-name');
        const positionRow = $('.position-row');
        const positionField = $('#position_id');
        const positionDescription = $('.position-description');
        const assessorsSection = $('#assessors-section');
        
        // Handle position field visibility and state
        if (groupName && groupName.toLowerCase() === 'peers') {
            // Show and enable position field for Peers
            positionRow.show();
            positionField.prop('disabled', false).prop('required', true);
            positionDescription.show();
            
            // Show assessors section
            assessorsSection.show();
        } else {
            // Hide and disable position field for non-Peers
            positionRow.hide();
            positionField.prop('disabled', true).prop('required', false).val('');
            positionDescription.hide();
            
            // Hide assessors section for non-Peers
            assessorsSection.hide();
            assessorsSection.find('input[type="checkbox"]').prop('checked', false);
            updateAssessorCount();
        }

        // Add visual indication for required field
        if (positionField.prop('required')) {
            $('label[for="position_id"]').html('Position *');
        } else {
            $('label[for="position_id"]').html('Position');
        }
    }

    // Handle assessors section
    function updateGroupSelection() {
        $('.group-select').each(function() {
            const groupName = $(this).data('group');
            const groupCheckboxes = $(`.assessor-select.group-${groupName}`);
            const checkedCount = groupCheckboxes.filter(':checked').length;
            const totalCount = groupCheckboxes.length;
            
            $(this).prop({
                checked: checkedCount === totalCount,
                indeterminate: checkedCount > 0 && checkedCount < totalCount
            });
        });
    }

    function updateAssessorCount() {
        const total = $('.assessor-select').length;
        const checked = $('.assessor-select:checked').length;
        $('.selected-count').text(
            checked > 0 ? `${checked} of ${total} selected` : ''
        );
    }

    // Group checkbox handler
    $('.group-select').on('change', function() {
        const isChecked = $(this).prop('checked');
        const groupName = $(this).data('group');
        
        $(`.assessor-select.group-${groupName}`).prop('checked', isChecked);
        updateAssessorCount();
    });

    // Individual assessor checkbox handler
    $('.assessor-select').on('change', function() {
        updateGroupSelection();
        updateAssessorCount();
    });

    // Select All Groups button
    $('.select-all-assessors').on('click', function() {
        $('.assessor-select').prop('checked', true);
        updateGroupSelection();
        updateAssessorCount();
    });

    // Clear All button
    $('.clear-all-assessors').on('click', function() {
        $('.assessor-select').prop('checked', false);
        updateGroupSelection();
        updateAssessorCount();
    });

    // Handle bulk actions
    $('#cb-select-all-1').on('change', function() {
        const isChecked = $(this).prop('checked');
        $('input[name="users[]"]').prop('checked', isChecked);
        updateBulkActionButton();
    });

    $('input[name="users[]"]').on('change', function() {
        updateBulkActionButton();
        const allChecked = $('input[name="users[]"]').length === $('input[name="users[]"]:checked').length;
        $('#cb-select-all-1').prop('checked', allChecked);
    });

    function updateBulkActionButton() {
        const checkedCount = $('input[name="users[]"]:checked').length;
        $('.bulkactions input[type="submit"]').prop('disabled', checkedCount === 0);
    }

    // Handle delete user confirmation
    $('.delete-user').on('click', function(e) {
        e.preventDefault();
        const userName = $(this).closest('tr').find('.row-title').text().trim();
        if (confirm(`Are you sure you want to delete ${userName}?`)) {
            window.location.href = $(this).attr('href');
        }
    });

    // Handle bulk action confirmation
    $('#users-filter').on('submit', function(e) {
        const action = $('select[name="bulk-action"]').val();
        const selectedUsers = $('input[name="users[]"]:checked').length;

        if (!action) {
            e.preventDefault();
            alert('Please select an action');
            return false;
        }

        if (selectedUsers === 0) {
            e.preventDefault();
            alert('Please select at least one user');
            return false;
        }

        if (action === 'delete') {
            if (!confirm(`Are you sure you want to delete ${selectedUsers} user(s)?`)) {
                e.preventDefault();
                return false;
            }
        }
    });

    // Initialize
    $('#group_id').on('change', toggleGroupDependentFields);
    toggleGroupDependentFields();
    updateGroupSelection();
    updateAssessorCount();
    updateBulkActionButton();

    // Remove error styling on input
    $('.form-table input, .form-table select').on('input change', function() {
        $(this).removeClass('error');
        $(this).closest('td').find('.error-message').remove();
    });
});
</script>
