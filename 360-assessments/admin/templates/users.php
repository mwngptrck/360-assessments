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
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-12">
<!--
                <h1 class="wp-heading-inline mb-4">
                    <i class="bi bi-people me-2"></i> Users
                </h1>
-->

                

                <?php if (isset($_GET['message'])): ?>
<!--
                    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                        <?php echo esc_html($_GET['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
-->
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                        <?php echo esc_html($_GET['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($current_action === 'edit' || $current_action === 'new'): ?>
                    <!-- Add/Edit Form -->
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-person-<?php echo $current_action === 'edit' ? 'gear' : 'plus'; ?> me-2"></i>
                                <?php echo $current_action === 'edit' ? 'Edit User' : 'Add New User'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form id="user-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="validate">
                                <?php wp_nonce_field('save_user_nonce', 'save_user_nonce'); ?>
                                <input type="hidden" name="action" value="save_user">
                                <input type="hidden" name="status" value="<?php echo esc_attr($current_status); ?>">
                                <?php if ($user): ?>
                                    <input type="hidden" name="id" value="<?php echo esc_attr($user->id); ?>">
                                <?php endif; ?>

                                <div class="row">
                                    <!-- User Details Column -->
                                    <div class="col-md-6">
                                        <div class="card mb-4">
                                            <div class="card-header bg-light">
                                                <h6 class="card-title mb-0">
                                                    <i class="bi bi-person-vcard me-2"></i>User Details
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <!-- First Name -->
                                                <div class="mb-3">
                                                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           id="first_name" 
                                                           name="first_name" 
                                                           value="<?php echo $user ? esc_attr($user->first_name) : ''; ?>" 
                                                           required>
                                                </div>

                                                <!-- Last Name -->
                                                <div class="mb-3">
                                                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           id="last_name" 
                                                           name="last_name" 
                                                           value="<?php echo $user ? esc_attr($user->last_name) : ''; ?>" 
                                                           required>
                                                </div>

                                                <!-- Email -->
                                                <div class="mb-3">
                                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                                    <input type="email" 
                                                           class="form-control" 
                                                           id="email" 
                                                           name="email" 
                                                           value="<?php echo $user ? esc_attr($user->email) : ''; ?>" 
                                                           required>
                                                </div>

                                                <!-- Phone -->
                                                <div class="mb-3">
                                                    <label for="phone" class="form-label">Phone Number</label>
                                                    <input type="tel" 
                                                           class="form-control" 
                                                           id="phone" 
                                                           name="phone" 
                                                           value="<?php echo $user ? esc_attr($user->phone) : ''; ?>">
                                                </div>

                                                <!-- Group -->
                                                <div class="mb-3">
                                                    <label for="group_id" class="form-label">User Group <span class="text-danger">*</span></label>
                                                    <select class="form-select" id="group_id" name="group_id" required>
                                                        <option value="">Select Group</option>
                                                        <?php foreach ($groups as $group): ?>
                                                            <option value="<?php echo esc_attr($group->id); ?>" 
                                                                    <?php selected($user && $user->group_id == $group->id); ?>
                                                                    data-group-name="<?php echo esc_attr($group->group_name); ?>">
                                                                <?php echo esc_html($group->group_name); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <!-- Position (initially hidden) -->
                                                <div class="mb-3 position-row" style="display: none;">
                                                    <label for="position_id" class="form-label">Position</label>
                                                    <select class="form-select" 
                                                            id="position_id" 
                                                            name="position_id" 
                                                            disabled>
                                                        <option value="">Select Position</option>
                                                        <?php foreach ($positions as $position): ?>
                                                            <option value="<?php echo esc_attr($position->id); ?>" 
                                                                    <?php selected($user && $user->position_id == $position->id); ?>>
                                                                <?php echo esc_html($position->name); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div class="form-text position-description" style="display: none;">
                                                        Position is required for Peers group members.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Assessors Column -->
                                    <div class="col-md-6">
                                        
                                        <!-- Assign Assessors -->
                                        <div id="assessors-section" class="card mb-4">
                                            <div class="card-header bg-light">
                                                <h6 class="card-title mb-0">
                                                    <i class="bi bi-people-fill me-2"></i>Assign Assessors
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <?php if (!empty($potential_assessors)): 
                                                    // Group users by their group
                                                    $grouped_users = array();
                                                    foreach ($potential_assessors as $assessor) {
                                                        $group_name = $assessor->group_name ?? 'Ungrouped';
                                                        if (!isset($grouped_users[$group_name])) {
                                                            $grouped_users[$group_name] = array();
                                                        }
                                                        $grouped_users[$group_name][] = $assessor;
                                                    }
                                                    ksort($grouped_users);
                                                ?>
                                                    <div class="assessors-container border rounded">
                                                        <?php foreach ($grouped_users as $group_name => $group_users): ?>
                                                            <div class="assessor-group">
                                                                <div class="p-2 bg-light border-bottom">
                                                                    <div class="form-check">
                                                                        <input type="checkbox" 
                                                                               class="form-check-input group-select" 
                                                                               data-group="<?php echo esc_attr($group_name); ?>"
                                                                               id="group-<?php echo sanitize_title($group_name); ?>">
                                                                        <label class="form-check-label" for="group-<?php echo sanitize_title($group_name); ?>">
                                                                            <?php echo esc_html($group_name); ?>
                                                                            <span class="badge bg-secondary ms-2">
                                                                                <?php echo count($group_users); ?>
                                                                            </span>
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                                <div class="p-3">
                                                                    <?php foreach ($group_users as $assessor): ?>
                                                                        <div class="form-check mb-2">
                                                                            <input type="checkbox" 
                                                                                   class="form-check-input assessor-select group-<?php echo esc_attr($group_name); ?>"
                                                                                   name="assessors[]" 
                                                                                   value="<?php echo esc_attr($assessor->id); ?>"
                                                                                   id="assessor-<?php echo esc_attr($assessor->id); ?>"
                                                                                   <?php checked(in_array($assessor->id, $current_assessor_ids)); ?>>
                                                                            <label class="form-check-label" for="assessor-<?php echo esc_attr($assessor->id); ?>">
                                                                                <?php echo esc_html($assessor->first_name . ' ' . $assessor->last_name); ?>
                                                                                <?php if (!empty($assessor->position_name)): ?>
                                                                                    <small class="text-muted">
                                                                                        (<?php echo esc_html($assessor->position_name); ?>)
                                                                                    </small>
                                                                                <?php endif; ?>
                                                                            </label>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <div class="mt-3 d-flex align-items-center">
                                                        <button type="button" class="btn btn-outline-primary btn-sm select-all-assessors">
                                                            <i class="bi bi-check-all me-1"></i>Select All
                                                        </button>
                                                        <button type="button" class="btn btn-outline-secondary btn-sm ms-2 clear-all-assessors">
                                                            <i class="bi bi-x-lg me-1"></i>Clear All
                                                        </button>
                                                        <span class="selected-count ms-auto text-muted"></span>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="alert alert-info mb-0">
                                                        <?php if ($user): ?>
                                                            No other active users available to be assessors.
                                                        <?php else: ?>
                                                            Assessors can be assigned after creating the user.
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Peers to Assess Section -->
                                        <div id="assessees-section" class="assessees-section" style="display: none;">
                                            <div class="card mb-4">
                                                <div class="card-header bg-light">
                                                    <h6 class="card-title mb-0">
                                                        <i class="bi bi-people-fill me-2"></i>Peers to Assess
                                                    </h6>
                                                </div>
                                                <div class="card-body">
                                                    <?php
                                                    // Get all active peer users
                                                    $peer_users = $user_manager->get_peer_users();

                                                    // Get current assessees if editing
                                                    $current_assessee_ids = [];
                                                    if ($user && isset($user->id)) {
                                                        $current_assessee_ids = $user_manager->get_user_assessee_ids($user->id);
                                                    }

                                                    if (!empty($peer_users)):
                                                    ?>
                                                        <div class="peers-container border rounded">
                                                            <div class="p-3">
                                                                <?php foreach ($peer_users as $peer): ?>
                                                                    <div class="form-check mb-2">
                                                                        <input type="checkbox" 
                                                                               class="form-check-input peer-select" 
                                                                               name="assessees[]" 
                                                                               value="<?php echo esc_attr($peer->id); ?>"
                                                                               id="peer-<?php echo esc_attr($peer->id); ?>"
                                                                               <?php checked(in_array($peer->id, $current_assessee_ids)); ?>>
                                                                        <label class="form-check-label" for="peer-<?php echo esc_attr($peer->id); ?>">
                                                                            <?php echo esc_html($peer->first_name . ' ' . $peer->last_name); ?>
                                                                            <?php if (!empty($peer->position_name)): ?>
                                                                                <small class="text-muted">
                                                                                    (<?php echo esc_html($peer->position_name); ?>)
                                                                                </small>
                                                                            <?php endif; ?>
                                                                        </label>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                        <div class="mt-3 d-flex align-items-center">
                                                            <button type="button" class="btn btn-outline-primary btn-sm select-all-peers">
                                                                <i class="bi bi-check-all me-1"></i>Select All
                                                            </button>
                                                            <button type="button" class="btn btn-outline-secondary btn-sm ms-2 clear-all-peers">
                                                                <i class="bi bi-x-lg me-1"></i>Clear All
                                                            </button>
                                                            <span class="selected-peers-count ms-auto text-muted"></span>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="alert alert-info mb-0">
                                                            <i class="bi bi-info-circle me-2"></i>No peer users available for assessment.
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save me-1"></i>
                                        <?php echo $user ? 'Update User' : 'Add User'; ?>
                                    </button>
                                    <a href="<?php echo esc_url(add_query_arg([
                                        'page' => 'assessment-360-user-management',
                                        'status' => $current_status
                                    ], admin_url('admin.php'))); ?>#users" class="btn btn-secondary ms-2">
                                        <i class="bi bi-x-circle me-1"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Users List -->
                    <div class="card shadow-sm mt-4">
                        
                        <?php if (!isset($_GET['action']) || $_GET['action'] !== 'edit'): ?>
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">


                            <a href="<?php echo esc_url(add_query_arg([
                                'page' => 'assessment-360-user-management',
                                'tab' => 'users',
                                'action' => 'new',
                                'status' => $current_status
                            ], admin_url('admin.php'))); ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add New</a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            

                            <!-- Bulk Actions -->
                            <form id="users-filter" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                <input type="hidden" name="action" value="bulk_action_users">
                                <input type="hidden" name="status" value="<?php echo esc_attr($current_status); ?>">
                                <?php wp_nonce_field('bulk_action_users', 'bulk_action_nonce'); ?>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <!-- Status Tabs -->
                                        <ul class="nav nav-pills">
                                            <li class="nav-item">
                                                <a class="nav-link <?php echo $current_status === 'active' ? 'active' : ''; ?>" 
                                                   href="<?php echo esc_url(add_query_arg([
                                                       'page' => 'assessment-360-user-management',
                                                       'status' => 'active'
                                                   ], admin_url('admin.php'))); ?>#users">
                                                    Active 
                                                    <span class="badge bg-secondary ms-1">
                                                        <?php echo $user_manager->get_users_count('active'); ?>
                                                    </span>
                                                </a>
                                            </li>
                                            <li class="nav-item ms-2">
                                                <a class="nav-link <?php echo $current_status === 'inactive' ? 'active' : ''; ?>" 
                                                   href="<?php echo esc_url(add_query_arg([
                                                       'page' => 'assessment-360-user-management',
                                                       'status' => 'inactive'
                                                   ], admin_url('admin.php'))); ?>#users">
                                                    Disabled 
                                                    <span class="badge bg-secondary ms-1">
                                                        <?php echo $user_manager->get_users_count('inactive'); ?>
                                                    </span>
                                                </a>
                                            </li>
                                        </ul>
                                        <!-- Status Tabs -->
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex mb-3 float-end">
                                            <div class="d-flex gap-2">
                                                <select name="bulk-action" class="form-select form-select-sm" style="width: auto;">
                                                    <option value="">Bulk Actions</option>
                                                    <?php if ($current_status === 'active'): ?>
                                                        <option value="disable">Disable</option>
                                                    <?php else: ?>
                                                        <option value="enable">Enable</option>
                                                    <?php endif; ?>
                                                    <option value="delete">Delete</option>
                                                </select>
                                                <button type="submit" class="btn btn-secondary btn-sm">Apply</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="30">
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input" id="cb-select-all-1">
                                                    </div>
                                                </th>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Position</th>
                                                <th>Group</th>
                                                <th>Status</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $users = $user_manager->get_users_by_status($current_status);
                                            if (!empty($users)): 
                                                foreach ($users as $list_user): 
                                            ?>
                                                <tr>
                                                    <td>
                                                        <div class="form-check">
                                                            <input type="checkbox" 
                                                                   class="form-check-input" 
                                                                   name="users[]" 
                                                                   value="<?php echo esc_attr($list_user->id); ?>">
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-circle me-2">
                                                                <?php echo esc_html(substr($list_user->first_name, 0, 1)); ?>
                                                            </div>
                                                            <div>
                                                                <a href="<?php echo esc_url(add_query_arg([
                                                                    'page' => 'assessment-360-user-management',
                                                                    'action' => 'view',
                                                                    'id' => $list_user->id,
                                                                    'status' => $current_status
                                                                ], admin_url('admin.php'))); ?>#users" class="row-title">
                                                                    <?php echo esc_html($list_user->first_name . ' ' . $list_user->last_name); ?>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo esc_html($list_user->email); ?></td>
                                                    <td><?php echo esc_html($list_user->position_name ?? '—'); ?></td>
                                                    <td><?php echo esc_html($list_user->group_name ?? '—'); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $list_user->status === 'active' ? 'success' : 'danger'; ?>">
                                                            <?php echo esc_html(ucfirst($list_user->status)); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-end">
                                                        <div class="btn-group">
                                                            <a href="<?php echo esc_url(add_query_arg([
                                                                'page' => 'assessment-360-user-management',
                                                                'action' => 'edit',
                                                                'tab' => 'users',
                                                                'id' => $list_user->id,
                                                                'status' => $current_status
                                                            ], admin_url('admin.php'))); ?>#users" 
                                                               class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-pencil me-1"></i>Edit
                                                            </a>

                                                            <?php if ($list_user->status === 'active'): ?>
                                                                <a href="<?php echo wp_nonce_url(
                                                                    add_query_arg([
                                                                        'page' => 'assessment-360-user-management',
                                                                        'action' => 'disable_user',
                                                                        'tab' => 'users',
                                                                        'id' => $list_user->id,
                                                                        'status' => $current_status
                                                                    ], admin_url('admin.php')),
                                                                    'user_status_' . $list_user->id
                                                                ); ?>#users" 
                                                                   class="btn btn-sm btn-outline-warning">
                                                                    <i class="bi bi-pause me-1"></i>Disable
                                                                </a>
                                                            <?php else: ?>
                                                                <a href="<?php echo wp_nonce_url(
                                                                    add_query_arg([
                                                                        'page' => 'assessment-360-user-management',
                                                                        'action' => 'enable_user',
                                                                        'tab' => 'users',
                                                                        'id' => $list_user->id,
                                                                        'status' => $current_status
                                                                    ], admin_url('admin.php')),
                                                                    'user_status_' . $list_user->id
                                                                ); ?>#users" 
                                                                   class="btn btn-sm btn-outline-success">
                                                                    <i class="bi bi-play me-1"></i>Enable
                                                                </a>
                                                            <?php endif; ?>

                                                            <a href="<?php echo wp_nonce_url(
                                                                add_query_arg([
                                                                    'page' => 'assessment-360-user-management',
                                                                    'action' => 'delete_user',
                                                                    'tab' => 'users',
                                                                    'id' => $list_user->id,
                                                                    'status' => $current_status
                                                                ], admin_url('admin.php')),
                                                                'delete_user_' . $list_user->id
                                                            ); ?>#users" 
                                                               class="btn btn-sm btn-outline-danger delete-user">
                                                                <i class="bi bi-trash me-1"></i>Delete
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php 
                                                endforeach;
                                            else: 
                                            ?>
                                                <tr>
                                                    <td colspan="7" class="text-center py-4">
                                                        <div class="text-muted">
                                                            <i class="bi bi-people h1 d-block mb-3"></i>
                                                            <?php echo $current_status === 'active' ? 
                                                                'No active users found.' : 
                                                                'No disabled users found.'; 
                                                            ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.wrap h1.wp-heading-inline {display: block}
.nav-link {color: #999}
.nav-pills .nav-link.active  {background: none ;color:#0C6DFD}
/* Card Styles */
.card {
    border: none;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    padding: 0;
    max-width: 100%;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0,0,0,.125);
    padding: 1rem;
}

.card-title {
    margin-bottom: 0;
    color: #333;
}

/* Avatar Circle */
.avatar-circle {
    width: 32px;
    height: 32px;
    background-color: #e9ecef;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: #495057;
}

/* Form Styles */
.form-control:focus,
.form-select:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

/* Assessors Section */
.assessors-container {
    max-height: 400px;
    overflow-y: auto;
}

.assessor-group {
    border-bottom: 1px solid #dee2e6;
}

.assessor-group:last-child {
    border-bottom: none;
}

.form-check-label {
    user-select: none;
    cursor: pointer;
}
.wp-core-ui select {max-width: 100%}

/* Loading States */
.btn.loading {
    position: relative;
    color: transparent !important;
}

.btn.loading::after {
    content: '';
    position: absolute;
    left: 50%;
    top: 50%;
    width: 16px;
    height: 16px;
    margin-left: -8px;
    margin-top: -8px;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top-color: currentColor;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Validation Styles */
.was-validated .form-control:invalid,
.was-validated .form-select:invalid {
    border-color: var(--bs-danger);
}

.was-validated .form-control:valid,
.was-validated .form-select:valid {
    border-color: var(--bs-success);
}

/* Custom Scrollbar */
.assessors-container::-webkit-scrollbar {
    width: 8px;
}

.assessors-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.assessors-container::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

.assessors-container::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Responsive Design */
@media (max-width: 768px) {
    .btn-group {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .btn-group .btn {
        width: 100%;
    }

    .assessors-container {
        max-height: 300px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Form Validation
    // Only apply to user form
    $('#user-form').on('submit', function(e) {
        const requiredFields = ['first_name', 'last_name', 'email', 'group_id'];
        let hasError = false;
        $('.error-message', this).remove();
        $('.error', this).removeClass('error');
        requiredFields.forEach(field => {
            const input = $(`#${field}`, this);
            const value = input.val().trim();
            if (!value) {
                input.addClass('error');
                input.closest('td').append(`<span class="error-message">This field is required</span>`);
                hasError = true;
            }
        });
        // Validate email
        const emailInput = $('#email', this);
        const emailValue = emailInput.val().trim();
        if (emailValue && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue)) {
            emailInput.addClass('error');
            emailInput.closest('td').append(`<span class="error-message">Please enter a valid email address</span>`);
            hasError = true;
        }
        // Peers group position validation
        const groupSelect = $('#group_id', this);
        const groupName = groupSelect.find('option:selected').data('group-name');
        if (groupName && groupName.toLowerCase() === 'peers' && !$('#position_id', this).val()) {
            $('#position_id', this).addClass('error')
                .closest('td').append(`<span class="error-message">Position is required for Peers group members</span>`);
            hasError = true;
        }
        if (hasError) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: $('.error', this).first().offset().top - 100
            }, 500);
            return false;
        }
        $(this).find('button[type="submit"]').prop('disabled', true);
        $(this).addClass('loading');
        return true;
    });

    // Email validation helper
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    //if not peers
    const assessorsSection = $('#assessors-section');
    const assesseesSection = $('#assessees-section');
    
    function toggleAssessmentSections() {
        const groupSelect = $('#group_id');
        const selectedOption = groupSelect.find('option:selected');
        const groupName = selectedOption.data('group-name');
        
        if (groupName && groupName.toLowerCase() === 'peers') {
            assessorsSection.show();
            assesseesSection.hide();
        } else {
            assessorsSection.hide();
            assesseesSection.show();
        }
    }

    // Initial check
    toggleAssessmentSections();

    // On group change
    $('#group_id').on('change', function() {
        toggleAssessmentSections();
    });

    // Handle "Select All Peers" functionality
    $('.select-all-peers').on('click', function() {
        $('.peer-select').prop('checked', true);
        updatePeerCount();
    });

    // Handle "Clear All" functionality
    $('.clear-all-peers').on('click', function() {
        $('.peer-select').prop('checked', false);
        updatePeerCount();
    });

    // Update peer count
    function updatePeerCount() {
        const total = $('.peer-select').length;
        const checked = $('.peer-select:checked').length;
        $('.selected-peers-count').text(
            checked > 0 ? `${checked} of ${total} selected` : ''
        );
    }

    // Initialize peer count
    updatePeerCount();

    // Handle individual peer selection
    $('.peer-select').on('change', function() {
        updatePeerCount();
    });
    //if not peers

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

