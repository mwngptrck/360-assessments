<?php 
if (!defined('ABSPATH')) exit;


global $wpdb;    

// Initialize managers with error handling

$user_manager = Assessment_360_User_Manager::get_instance();
$position_manager = Assessment_360_Position::get_instance();
$group_manager = Assessment_360_Group_Manager::get_instance();


// Initialize variables
$user = null;
$department_id = null;
$position_id = null;

// If editing user, get their data
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $user = $user_manager->get_user(intval($_GET['id']));
    if ($user) {
        $department_id = $user->department_id;
        $position_id = $user->position_id;
    }
}

// Get parameters with validation
//$current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'active';
$current_action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get current status filter
$current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'active';
$valid_statuses = ['active', 'disabled'];
$current_status = in_array($current_status, $valid_statuses) ? $current_status : 'active';

// Initialize variables
$user = null;
$positions = [];
$groups = [];
$potential_assessors = [];
$current_assessor_ids = [];

// Load data based on action
if ($current_action === 'edit' || $current_action === 'new') {

    try {
        // Load positions and groups
        $positions = $position_manager->get_all_positions();
        $groups = $group_manager->get_all_groups();

        // Get user data if editing
        if ($current_action === 'edit' && $user_id) {
            $user = $user_manager->get_user($user_id);

            // Get current assessors
            $current_assessors = $user_manager->get_user_assessors($user_id);
            $current_assessor_ids = array_map(function($assessor) {
                return $assessor->id;
            }, $current_assessors);
        }

        // Get potential assessors
        $potential_assessors = $user_manager->get_all_active_users();

        // Remove current user from potential assessors if editing
        if ($user) {
            $potential_assessors = array_filter($potential_assessors, function($assessor) use ($user) {
                return $assessor->id != $user->id;
            });
        }

    } catch (Exception $e) {
        ?>
        <div class="notice notice-error"><p><?php echo esc_html('Error loading form data: ' . $e->getMessage()); ?></p></div>
        <?php
        return;
    }
}


?>

<div class="wrap">
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-12">

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
                                                        <?php 
                                                        // Get groups excluding departments
                                                        $groups = $group_manager->get_all_groups(true);
                                                        foreach ($groups as $group): 
                                                        ?>
                                                            <option value="<?php echo esc_attr($group->id); ?>" 
                                                                    data-group-name="<?php echo esc_attr($group->group_name); ?>"
                                                                    <?php selected($user && $user->group_id == $group->id); ?>>
                                                                <?php echo esc_html($group->group_name); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <!-- Department dropdown (for Peers group) -->
                                                <div class="mb-3" id="department-section">
                                                    <label for="department_id" class="form-label">Department <span class="text-danger">*</span></label>
                                                    <select class="form-select" id="department_id" name="department_id">
                                                        <option value="">Select Department</option>
                                                        <?php 
                                                        $departments = $group_manager->get_departments();
                                                        foreach ($departments as $dept): 
                                                            $selected = ($user && isset($user->department_id) && $user->department_id == $dept->id);
                                                        ?>
                                                            <option value="<?php echo esc_attr($dept->id); ?>"
                                                                    <?php selected($selected); ?>>
                                                                <?php echo esc_html($dept->group_name); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div class="invalid-feedback">Department is required for Peers group members.</div>
                                                </div>
                                                
                                                <!-- Position dropdown -->
                                                <div class="mb-3" id="position-section">
                                                    <label for="position_id" class="form-label">Position</label>
                                                    <select class="form-select" id="position_id" name="position_id">
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
                                        <?php
                                        // Determine if user is/will be in Peers group
                                        $is_peer = false;

                                        if (isset($_GET['action'])) {
                                            if ($_GET['action'] === 'edit' && $user) {
                                                // Editing existing user
                                                $is_peer = strtolower($user->group_name) === 'peers';
                                            } else if ($_GET['action'] === 'new') {
                                                // New user - check selected group from form submission
                                                $selected_group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 
                                                                   (isset($_GET['group_id']) ? intval($_GET['group_id']) : 0);

                                                if ($selected_group_id) {
                                                    $selected_group = $group_manager->get_group($selected_group_id);
                                                    $is_peer = $selected_group && strtolower($selected_group->group_name) === 'peers';
                                                }
                                            }
                                        }

                                        // Get current assessor IDs if editing
                                        $current_assessor_ids = [];
                                        if ($user && isset($user->id)) {
                                            $current_assessor_ids = $user_manager->get_user_assessor_ids($user->id);
                                        }

                                        // Get grouped users based on user type
                                        $grouped_users = $user_manager->get_grouped_users_for_assessment($is_peer);

                                        ?>

                                        <!-- Assessors Section -->
                                        <div class="mb-4" id="assessors-section">
                                            <h4 class="section-heading mb-3">
                                                <?php echo $is_peer ? 'Select users who will assess this user' : 'Select users who this user will assess'; ?>
                                            </h4>

                                            <div class="assessors-container">
                                                <?php if ($is_peer): ?>
                                                    <!-- For Peer Users - Show all groups -->
                                                    <?php if (!empty($grouped_users['peers_by_department']) || !empty($grouped_users['other_groups'])): ?>
                                                        <!-- Selection Controls -->
                                                        <div class="selection-controls mb-3">
                                                            <button type="button" class="btn btn-outline-primary btn-sm select-all-assessors">
                                                                <i class="bi bi-check-all me-1"></i>Select All
                                                            </button>
                                                            <button type="button" class="btn btn-outline-secondary btn-sm ms-2 clear-all-assessors">
                                                                <i class="bi bi-x-lg me-1"></i>Clear All
                                                            </button>
                                                            <span class="selected-count ms-2 text-muted"></span>
                                                        </div>

                                                        <!-- Peers by Department Section -->
                                                        <?php if (!empty($grouped_users['peers_by_department'])): ?>
                                                            <div class="peers-section mb-4">
                                                                <h5 class="section-title">
                                                                    <i class="bi bi-people-fill me-2"></i>Peers by Department
                                                                </h5>
                                                                <?php foreach ($grouped_users['peers_by_department'] as $dept_name => $users): ?>
                                                                    <div class="group-container mb-3">
                                                                        <div class="group-header">
                                                                            <label class="d-flex align-items-center">
                                                                                <input type="checkbox" 
                                                                                       class="group-select form-check-input me-2" 
                                                                                       data-department="<?php echo esc_attr(sanitize_title($dept_name)); ?>">
                                                                                <div>
                                                                                    <strong><?php echo esc_html($dept_name); ?></strong>
                                                                                    <span class="group-count ms-2">(<?php echo count($users); ?>)</span>
                                                                                </div>
                                                                            </label>
                                                                        </div>
                                                                        <div class="group-users">
                                                                            <?php foreach ($users as $peer): ?>
                                                                                <div class="user-item">
                                                                                    <label class="user-label d-flex align-items-center">
                                                                                        <input type="checkbox" 
                                                                                               name="assessors[]" 
                                                                                               value="<?php echo esc_attr($peer->id); ?>"
                                                                                               class="assessor-select form-check-input me-2 department-<?php echo esc_attr(sanitize_title($dept_name)); ?>"
                                                                                               <?php checked(in_array($peer->id, $current_assessor_ids)); ?>>
                                                                                        <div class="user-info">
                                                                                            <span class="user-name">
                                                                                                <?php echo esc_html($peer->first_name . ' ' . $peer->last_name); ?>
                                                                                            </span>
                                                                                            <?php if (!empty($peer->position_name)): ?>
                                                                                                <small class="position-name text-muted">
                                                                                                    (<?php echo esc_html($peer->position_name); ?>)
                                                                                                </small>
                                                                                            <?php endif; ?>
                                                                                        </div>
                                                                                    </label>
                                                                                </div>
                                                                            <?php endforeach; ?>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Other Groups Section -->
                                                        <?php if (!empty($grouped_users['other_groups'])): ?>
                                                            <div class="other-groups-section">
                                                                <h5 class="section-title">
                                                                    <i class="bi bi-people me-2"></i>Other Groups
                                                                </h5>
                                                                <?php foreach ($grouped_users['other_groups'] as $group_name => $users): ?>
                                                                    <div class="group-container mb-3">
                                                                        <div class="group-header">
                                                                            <label class="d-flex align-items-center">
                                                                                <input type="checkbox" 
                                                                                       class="group-select form-check-input me-2" 
                                                                                       data-group="<?php echo esc_attr(sanitize_title($group_name)); ?>">
                                                                                <div>
                                                                                    <strong><?php echo esc_html($group_name); ?></strong>
                                                                                    <span class="group-count ms-2">(<?php echo count($users); ?>)</span>
                                                                                </div>
                                                                            </label>
                                                                        </div>
                                                                        <div class="group-users">
                                                                            <?php foreach ($users as $user): ?>
                                                                                <div class="user-item">
                                                                                    <label class="user-label d-flex align-items-center">
                                                                                        <input type="checkbox" 
                                                                                               name="assessors[]" 
                                                                                               value="<?php echo esc_attr($user->id); ?>"
                                                                                               class="assessor-select form-check-input me-2 group-<?php echo esc_attr(sanitize_title($group_name)); ?>"
                                                                                               <?php checked(in_array($user->id, $current_assessor_ids)); ?>>
                                                                                        <div class="user-info">
                                                                                            <span class="user-name">
                                                                                                <?php echo esc_html($user->first_name . ' ' . $user->last_name); ?>
                                                                                            </span>
                                                                                            <?php if (!empty($user->position_name)): ?>
                                                                                                <small class="position-name text-muted">
                                                                                                    (<?php echo esc_html($user->position_name); ?>)
                                                                                                </small>
                                                                                            <?php endif; ?>
                                                                                        </div>
                                                                                    </label>
                                                                                </div>
                                                                            <?php endforeach; ?>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>

                                                    <?php else: ?>
                                                        <div class="alert alert-info">
                                                            <i class="bi bi-info-circle me-2"></i>No users available for assessment.
                                                        </div>
                                                    <?php endif; ?>

                                                <?php else: ?>
                                                    <!-- For Non-Peer Users - Show only peer users -->
                                                    <?php if (!empty($grouped_users['peers_by_department'])): ?>
                                                        <!-- Selection Controls -->
                                                        <div class="selection-controls mb-3">
                                                            <button type="button" class="btn btn-outline-primary btn-sm select-all-assessors">
                                                                <i class="bi bi-check-all me-1"></i>Select All
                                                            </button>
                                                            <button type="button" class="btn btn-outline-secondary btn-sm ms-2 clear-all-assessors">
                                                                <i class="bi bi-x-lg me-1"></i>Clear All
                                                            </button>
                                                            <span class="selected-count ms-2 text-muted"></span>
                                                        </div>

                                                        <!-- Peers by Department -->
                                                        <div class="peers-section">
                                                            <?php foreach ($grouped_users['peers_by_department'] as $dept_name => $users): ?>
                                                                <div class="group-container mb-3">
                                                                    <div class="group-header">
                                                                        <label class="d-flex align-items-center">
                                                                            <input type="checkbox" 
                                                                                   class="group-select form-check-input me-2" 
                                                                                   data-department="<?php echo esc_attr(sanitize_title($dept_name)); ?>">
                                                                            <div>
                                                                                <strong><?php echo esc_html($dept_name); ?></strong>
                                                                                <span class="group-count ms-2">(<?php echo count($users); ?>)</span>
                                                                            </div>
                                                                        </label>
                                                                    </div>
                                                                    <div class="group-users">
                                                                        <?php foreach ($users as $peer): ?>
                                                                            <div class="user-item">
                                                                                <label class="user-label d-flex align-items-center">
                                                                                    <input type="checkbox" 
                                                                                           name="assessors[]" 
                                                                                           value="<?php echo esc_attr($peer->id); ?>"
                                                                                           class="assessor-select form-check-input me-2 department-<?php echo esc_attr(sanitize_title($dept_name)); ?>"
                                                                                           <?php checked(in_array($peer->id, $current_assessor_ids)); ?>>
                                                                                    <div class="user-info">
                                                                                        <span class="user-name">
                                                                                            <?php echo esc_html($peer->first_name . ' ' . $peer->last_name); ?>
                                                                                        </span>
                                                                                        <?php if (!empty($peer->position_name)): ?>
                                                                                            <small class="position-name text-muted">
                                                                                                (<?php echo esc_html($peer->position_name); ?>)
                                                                                            </small>
                                                                                        <?php endif; ?>
                                                                                    </div>
                                                                                </label>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="alert alert-info">
                                                            <i class="bi bi-info-circle me-2"></i>No peer users available to assess.
                                                        </div>
                                                    <?php endif; ?>
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
                            
                            <h5 class="card-title mb-0">
                                <i class="bi bi-person"></i> Users
                            </h5>
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
                                        <ul class="nav nav-tabs mb-3">
                                            <li class="nav-item">
                                                <a class="nav-link <?php echo $current_status === 'active' ? 'active' : ''; ?>" 
                                                   href="<?php echo esc_url(add_query_arg([
                                                       'page' => 'assessment-360-user-management',
                                                       'tab' => 'users',
                                                       'status' => 'active'
                                                   ], admin_url('admin.php'))); ?>">
                                                    <i class="bi bi-person-check me-1"></i>
                                                    Active Users
                                                    <?php if ($active_count = $user_manager->get_users_count('active')): ?>
                                                        <span class="badge bg-secondary ms-1"><?php echo esc_html($active_count); ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link <?php echo $current_status === 'disabled' ? 'active' : ''; ?>" 
                                                   href="<?php echo esc_url(add_query_arg([
                                                       'page' => 'assessment-360-user-management',
                                                       'tab' => 'users',
                                                       'status' => 'disabled'
                                                   ], admin_url('admin.php'))); ?>">
                                                    <i class="bi bi-person-x me-1"></i>
                                                    Disabled Users
                                                    <?php if ($disabled_count = $user_manager->get_users_count('disabled')): ?>
                                                        <span class="badge bg-secondary ms-1"><?php echo esc_html($disabled_count); ?></span>
                                                    <?php endif; ?>
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
                                
                                <?php
                                // Get users based on status
                                $users = $user_manager->get_users_by_status($current_status);

                                // Get counts for both statuses
                                $active_count = $user_manager->get_users_count('active');
                                $disabled_count = $user_manager->get_users_count('disabled');
                                ?>

                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Position</th>
                                                <th>Group</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($users)): ?>
                                                <?php foreach ($users as $list_user): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="<?php echo esc_url(add_query_arg([
                                                                'page' => 'assessment-360-user-management',
                                                                'action' => 'view',
                                                                'id' => $list_user->id,
                                                                'status' => $current_status
                                                            ], admin_url('admin.php'))); ?>#users" class="row-title">
                                                                <?php echo esc_html($list_user->first_name . ' ' . $list_user->last_name); ?>
                                                            </a>
                                                        </td>
                                                        <td><?php echo esc_html($list_user->email); ?></td>
                                                        <td><?php echo esc_html($list_user->position_name ?? '—'); ?></td>
                                                        <td><?php echo esc_html($list_user->group_name ?? '—'); ?></td>
                                                        <td class="text-end">
                                                            <div class="btn-group">
                                                                <a href="<?php echo esc_url(add_query_arg([
                                                                    'page' => 'assessment-360-user-management',
                                                                    'action' => 'edit',
                                                                    'tab' => 'users',
                                                                    'id' => $list_user->id,
                                                                    'status' => $current_status
                                                                ], admin_url('admin.php'))); ?>" 
                                                                   class="btn btn-sm btn-outline-primary">
                                                                    <i class="bi bi-pencil me-1"></i>Edit
                                                                </a>

                                                                <?php if ($list_user->status === 'active'): ?>
                                                                    <button type="button" 
                                                                            class="btn btn-sm btn-outline-warning disable-user"
                                                                            data-bs-toggle="modal" 
                                                                            data-bs-target="#disableUserModal"
                                                                            data-id="<?php echo esc_attr($list_user->id); ?>"
                                                                            data-name="<?php echo esc_attr($list_user->first_name . ' ' . $list_user->last_name); ?>">
                                                                        <i class="bi bi-pause me-1"></i>Disable
                                                                    </button>
                                                                <?php else: ?>
                                                                    <button type="button" 
                                                                            class="btn btn-sm btn-outline-success enable-user"
                                                                            data-bs-toggle="modal" 
                                                                            data-bs-target="#enableUserModal"
                                                                            data-id="<?php echo esc_attr($list_user->id); ?>"
                                                                            data-name="<?php echo esc_attr($list_user->first_name . ' ' . $list_user->last_name); ?>">
                                                                        <i class="bi bi-play me-1"></i>Enable
                                                                    </button>
                                                                <?php endif; ?>

                                                                <button type="button" 
                                                                        class="btn btn-sm btn-outline-danger delete-user"
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#deleteUserModal"
                                                                        data-id="<?php echo esc_attr($list_user->id); ?>"
                                                                        data-name="<?php echo esc_attr($list_user->first_name . ' ' . $list_user->last_name); ?>">
                                                                    <i class="bi bi-trash me-1"></i>Delete
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4">
                                                        <div class="text-muted">
                                                            <i class="bi bi-people h1 d-block mb-3"></i>
                                                            No <?php echo $current_status; ?> users found.
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
    
    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the user "<span id="deleteUserName"></span>"?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        This action cannot be undone. All associated assessments will also be deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('delete_user'); ?>
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="id" id="deleteUserId">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Delete User
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Disable User Modal -->
    <div class="modal fade" id="disableUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Disable User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to disable "<span id="disableUserName"></span>"?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        This user will not be able to access the system until enabled again.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('disable_user', 'disable_user_nonce'); ?>
                        <input type="hidden" name="action" value="disable_user">
                        <input type="hidden" name="id" id="disableUserId">
                        <input type="hidden" name="status" value="<?php echo esc_attr($current_status); ?>">
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-pause"></i> Disable User
                        </button>
                    </form>
                </div>
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
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    padding: 1rem;
}

.other-groups-section,
.peers-section {
    background-color: #f8f9fa;
    border-radius: 0.5rem;
    padding: 1.5rem;
}

.section-title {
    color: #495057;
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
}

.group-container {
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
}
    
.group-header {
    margin-bottom: 1rem;
}

.group-header label {
    margin: 0;
    cursor: pointer;
    display: flex;
    align-items: center;
}

.group-users {
    margin-left: 1.75rem;
}

.user-item {
    margin-bottom: 0.5rem;
}

.user-label {
    display: flex;
    align-items: center;
    margin: 0;
    cursor: pointer;
}

.user-info {
    margin-left: 0.5rem;
}

.user-name {
    font-weight: 500;
}

.position-name {
    margin-left: 0.5rem;
    font-size: 0.875rem;
}

.group-count {
    color: #6c757d;
    font-size: 0.875rem;
    margin-left: 0.5rem;
    font-weight: normal;
}

input[type="checkbox"] {
    cursor: pointer;
}

.alert {
    margin-bottom: 0;
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
.error {
    border-color: #dc3545 !important;
}

.error-message {
    color: #dc3545;
    font-size: 0.875rem;
    margin-top: 0.25rem;
    display: block;
}

.text-danger {
    color: #dc3545;
}
</style>

<script>
jQuery(document).ready(function($) {
    /*** INITIALIZATION AND ELEMENT REFERENCES ***/
    const $groupSelect = $('#group_id');
    const $departmentSection = $('#department-section');
    const $positionSection = $('#position-section');
    const $departmentSelect = $('#department_id');
    const $assessorsSection = $('#assessors-section');
    const $assesseesSection = $('#assessees-section');
    let initialLoad = true;

    /*** FORM VALIDATION ***/
    $('#user-form').on('submit', function(e) {
        // Clear previous errors
        $('.error-message', this).remove();
        $('.error', this).removeClass('error');
        let hasError = false;

        // Required fields validation
        const requiredFields = ['first_name', 'last_name', 'email', 'group_id'];
        requiredFields.forEach(field => {
            const input = $(`#${field}`, this);
            const value = input.val().trim();
            if (!value) {
                input.addClass('error');
                input.closest('td').append(`<span class="error-message">This field is required</span>`);
                hasError = true;
            }
        });

        // Email validation
        const emailInput = $('#email', this);
        const emailValue = emailInput.val().trim();
        if (emailValue && !/^[^\s@]+@[^\s@]+$/.test(emailValue)) {
            emailInput.addClass('error');
            emailInput.closest('td').append(`<span class="error-message">Please enter a valid email address</span>`);
            hasError = true;
        }

        // Peers group validation
        const groupName = $groupSelect.find('option:selected').data('group-name');
        const isPeers = groupName && groupName.toLowerCase() === 'peers';

        if (isPeers) {
            // Department validation for Peers
            if (!$departmentSelect.val()) {
                $departmentSelect.addClass('error');
                $departmentSelect.closest('td').append(`<span class="error-message">Department is required for Peers group members</span>`);
                hasError = true;
            }

            // Position validation for Peers
            if (!$('#position_id').val()) {
                $('#position_id').addClass('error')
                    .closest('td').append(`<span class="error-message">Position is required for Peers group members</span>`);
                hasError = true;
            }
        }

        if (hasError) {
            e.preventDefault();
            // Scroll to first error
            $('html, body').animate({
                scrollTop: $('.error', this).first().offset().top - 100
            }, 500);
            return false;
        }

        // Disable submit button to prevent double submission
        $(this).find('button[type="submit"]').prop('disabled', true);
        $(this).addClass('loading');
        return true;
    });

    /*** GROUP SELECTION HANDLING ***/
    function handleGroupChange() {
        const selectedOption = $groupSelect.find('option:selected');
        const groupName = selectedOption.data('group-name');
        const isPeers = groupName && groupName.toLowerCase() === 'peers';
        
        // Toggle sections based on group
        $departmentSection.toggle(isPeers);
        $positionSection.toggle(isPeers);
        
        // Reset and handle required fields
        if (isPeers) {
            $departmentSelect.prop('required', true);
            $('#position_id').prop('required', true);
            // Show assessors section
            $('#assessors-section').show();
            // Show both peers and other groups sections
            $('.peers-section, .other-groups-section').show();
            // Update labels to show required indicator
            $('label[for="department_id"]').html('Department <span class="text-danger">*</span>');
            $('label[for="position_id"]').html('Position <span class="text-danger">*</span>');
            // Update section title
            $('#assessors-section h4').text('Select users who will assess this user');
        } else {
            // Reset and clear fields when not Peers
            if (!initialLoad) {
                $departmentSelect.prop('required', false).val('');
                $('#position_id').prop('required', false).val('');
            }
            // Show only peers section for non-peer groups
            $('.peers-section').show();
            $('.other-groups-section').hide();
            // Reset labels
            $('label[for="department_id"]').html('Department');
            $('label[for="position_id"]').html('Position');
            // Update section title
            $('#assessors-section h4').text('Select users who this user will assess');
            // Remove any error messages
            $departmentSelect.removeClass('error');
            $('#position_id').removeClass('error');
            $('.error-message').remove();
        }
        
        // After first run, set initialLoad to false
        initialLoad = false;
        
        updateAssessorCount();
    }

    /*** ASSESSOR MANAGEMENT ***/
    function updateAssessorCount() {
        const total = $('.assessor-select').length;
        const checked = $('.assessor-select:checked').length;
        $('.selected-count').text(checked > 0 ? `${checked} of ${total} selected` : '');
    }

    function updateGroupSelection() {
        $('.group-select').each(function() {
            const department = $(this).data('department');
            const group = $(this).data('group');
            let selector;
            
            if (department) {
                selector = `.department-${department}`;
            } else if (group) {
                selector = `.group-${group}`;
            }
            
            if (selector) {
                const totalCheckboxes = $(selector).length;
                const checkedCheckboxes = $(selector + ':checked').length;
                
                $(this).prop({
                    checked: totalCheckboxes === checkedCheckboxes,
                    indeterminate: checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes
                });
            }
        });
    }

    // Group checkbox handlers
    $('.group-select').on('change', function() {
        const isChecked = $(this).prop('checked');
        const department = $(this).data('department');
        const group = $(this).data('group');
        
        if (department) {
            $(`.department-${department}`).prop('checked', isChecked);
        } else if (group) {
            $(`.group-${group}`).prop('checked', isChecked);
        }
        
        updateAssessorCount();
    });

    // Individual assessor checkbox handler
    $('.assessor-select').on('change', function() {
        updateGroupSelection();
        updateAssessorCount();
    });

    // Select All/Clear All handlers
    $('.select-all-assessors').on('click', function() {
        $('.assessor-select').prop('checked', true);
        updateGroupSelection();
        updateAssessorCount();
    });

    $('.clear-all-assessors').on('click', function() {
        $('.assessor-select').prop('checked', false);
        updateGroupSelection();
        updateAssessorCount();
    });

    /*** BULK ACTIONS HANDLING ***/
    function updateBulkActionButton() {
        const checkedCount = $('input[name="users[]"]:checked').length;
        $('.bulkactions input[type="submit"]').prop('disabled', checkedCount === 0);
    }

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

    /*** DELETE CONFIRMATION HANDLERS ***/
//    $('.delete-user').on('click', function(e) {
//        e.preventDefault();
//        const userName = $(this).closest('tr').find('.row-title').text().trim();
//        if (confirm(`Are you sure you want to delete ${userName}?`)) {
//            window.location.href = $(this).attr('href');
//        }
//    });
    
    $('.delete-user').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        
        $('#deleteUserId').val(id);
        $('#deleteUserName').text(name);
    });

    // Add loading state to delete form
    $('#deleteUserModal form').on('submit', function() {
        $(this).find('button[type="submit"]')
            .prop('disabled', true)
            .html('<i class="bi bi-hourglass-split me-2"></i>Deleting...');
    });
    
    // Disable user modal handling
    $('.disable-user').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');

        $('#disableUserId').val(id);
        $('#disableUserName').text(name);
    });

    // Add loading state to disable form
    $('#disableUserModal form').on('submit', function() {
        $(this).find('button[type="submit"]')
            .prop('disabled', true)
            .html('<i class="bi bi-hourglass-split me-2"></i>Disabling...');
    });

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

    /*** ERROR HANDLING ***/
    $('.form-table input, .form-table select').on('input change', function() {
        $(this).removeClass('error');
        $(this).closest('td').find('.error-message').remove();
    });

    /*** INITIALIZATION CALLS ***/
    // Bind group change event
    $groupSelect.on('change', handleGroupChange);
    
    // Initial setup
    handleGroupChange();
    updateGroupSelection();
    updateAssessorCount();
    updateBulkActionButton();
});

</script>