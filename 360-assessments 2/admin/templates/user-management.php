<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1>User Management</h1>
    
    <?php 
        // Get current action and user ID
        $current_action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if (isset($_GET['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo esc_html($_GET['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo esc_html($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php 
        // Show user profile if viewing user
        if ($current_action === 'view' && $user_id): 
            // Initialize User Manager
            $user_manager = Assessment_360_User_Manager::get_instance();
            $user = $user_manager->get_user($user_id);

            if (!$user) {
                echo '<div class="alert alert-danger">User not found.</div>';
            } else {
                include(ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/user-profile.php');
            }
        else:
        ?>

    <?php
        // Determine if in add/edit mode for user, group, or position
        $is_form_mode = false;
        $tab = isset($_GET['tab']) ? $_GET['tab'] : 'users';
        $action = isset($_GET['action']) ? $_GET['action'] : '';

        // Users: action=new or edit
        if (
            ($tab === 'users' && in_array($action, ['new', 'edit'])) ||
            ($tab === 'groups' && in_array($action, ['new', 'edit'])) ||
            ($tab === 'positions' && in_array($action, ['new', 'edit']))
        ) {
            $is_form_mode = true;
        }
    ?>

    <!-- Bootstrap Tabs -->
    <ul class="nav nav-tabs mb-3" id="userManagementTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link<?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'users') ? ' active' : ''; ?>"
                    id="users-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#users"
                    type="button"
                    role="tab"
                    <?php echo $is_form_mode ? 'disabled style="pointer-events:none;opacity:0.6;"' : ''; ?>>
                <i class="bi bi-person"></i> Users
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link<?php echo (isset($_GET['tab']) && $_GET['tab'] === 'groups') ? ' active' : ''; ?>"
                    id="groups-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#groups"
                    type="button"
                    role="tab"
                    <?php echo $is_form_mode ? 'disabled style="pointer-events:none;opacity:0.6;"' : ''; ?>>
                <i class="bi bi-people"></i> User Groups
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link<?php echo (isset($_GET['tab']) && $_GET['tab'] === 'positions') ? ' active' : ''; ?>"
                    id="positions-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#positions"
                    type="button"
                    role="tab"
                    <?php echo $is_form_mode ? 'disabled style="pointer-events:none;opacity:0.6;"' : ''; ?>>
                <i class="bi bi-briefcase"></i> Positions
            </button>
        </li>
    </ul>

    <div class="tab-content" id="userManagementContent">
        <!-- Users Tab -->
        <div class="tab-pane fade<?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'users') ? ' show active' : ''; ?>" id="users" role="tabpanel">
            <?php include ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/users.php'; ?>
        </div>
        <!-- Groups Tab -->
        <div class="tab-pane fade<?php echo (isset($_GET['tab']) && $_GET['tab'] === 'groups') ? ' show active' : ''; ?>" id="groups" role="tabpanel">
            <?php include ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/user-groups.php'; ?>
        </div>
        <!-- Positions Tab -->
        <div class="tab-pane fade<?php echo (isset($_GET['tab']) && $_GET['tab'] === 'positions') ? ' show active' : ''; ?>" id="positions" role="tabpanel">
            <?php include ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/positions.php'; ?>
        </div>
    </div>
    
    <?php endif; ?>
    
</div>

<script>
jQuery(function($){
    // Handle tab switching and URL hash
    $('#userManagementTabs button[data-bs-toggle="tab"]').on('click', function(e) {
        // If disabled, prevent tab switch
        if ($(this).is(':disabled')) {
            e.preventDefault();
            return false;
        }
        e.preventDefault();
        // Activate tab
        $('#userManagementTabs button').removeClass('active');
        $(this).addClass('active');
        $('.tab-pane').removeClass('show active');
        $($(this).data('bs-target')).addClass('show active');
        // Update URL
        const tab = $(this).data('bs-target').replace('#','');
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url.toString());
    });
    // On load, show correct tab
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab') || 'users';
    $(`#userManagementTabs button[data-bs-target="#${tab}"]`).trigger('click');
});
</script>