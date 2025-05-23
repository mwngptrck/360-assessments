<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1>User Management</h1>

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

    <!-- Bootstrap Tabs -->
    <ul class="nav nav-tabs mb-3" id="userManagementTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link<?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'users') ? ' active' : ''; ?>"
                    id="users-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#users"
                    type="button"
                    role="tab">
                Users
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link<?php echo (isset($_GET['tab']) && $_GET['tab'] === 'groups') ? ' active' : ''; ?>"
                    id="groups-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#groups"
                    type="button"
                    role="tab">
                User Groups
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link<?php echo (isset($_GET['tab']) && $_GET['tab'] === 'positions') ? ' active' : ''; ?>"
                    id="positions-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#positions"
                    type="button"
                    role="tab">
                Positions
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
</div>

<script>
jQuery(function($){
    // Handle tab switching and URL hash
    $('#userManagementTabs button[data-bs-toggle="tab"]').on('click', function(e) {
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