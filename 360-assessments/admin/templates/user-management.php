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
            <button class="nav-link active" 
                    id="users-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#users" 
                    type="button" 
                    role="tab">
                Users
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" 
                    id="groups-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#groups" 
                    type="button" 
                    role="tab">
                User Groups
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" 
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
        <div class="tab-pane fade show active" id="users" role="tabpanel">
            <?php 
            // Include existing users page content
            include_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/users.php';
            ?>
        </div>

        <!-- Groups Tab -->
        <div class="tab-pane fade" id="groups" role="tabpanel">
            <?php 
            // Include existing groups page content
            include_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/user-groups.php';
            ?>
        </div>

        <!-- Positions Tab -->
        <div class="tab-pane fade" id="positions" role="tabpanel">
            <?php 
            // Include existing positions page content
            include_once ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/positions.php';
            ?>
        </div>
    </div>
</div>

<style>
/* Tab Styling */
.nav-tabs {
    border-bottom: 1px solid #dee2e6;
    margin-bottom: 20px;
}

.nav-tabs .nav-link {
    margin-bottom: -1px;
    border: 1px solid transparent;
    border-top-left-radius: 4px;
    border-top-right-radius: 4px;
    color: #1d2327;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 500;
    background: transparent;
    cursor: pointer;
}

.nav-tabs .nav-link:hover {
    border-color: #e9ecef #e9ecef #dee2e6;
    isolation: isolate;
}

.nav-tabs .nav-link.active {
    color: #2271b1;
    background-color: #fff;
    border-color: #dee2e6 #dee2e6 #fff;
}

/* Content Area */
.tab-content {
    background: #fff;
    border: 1px solid #dee2e6;
    border-top: none;
    padding: 20px;
    min-height: 400px;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

/* Ensure proper spacing for included pages */
.tab-pane > .wrap {
    margin: 0;
    padding: 0;
}

.tab-pane > .wrap > h1 {
    display: none; /* Hide individual page titles */
}

/* Responsive Design */
@media screen and (max-width: 782px) {
    .nav-tabs {
        flex-direction: column;
        border-bottom: none;
    }

    .nav-tabs .nav-item {
        margin-bottom: 5px;
    }

    .nav-tabs .nav-link {
        border: 1px solid #dee2e6;
        border-radius: 4px;
        margin-bottom: 0;
    }

    .nav-tabs .nav-link.active {
        border-color: #2271b1;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle tab switching
    $('#userManagementTabs button[data-bs-toggle="tab"]').on('click', function(e) {
        e.preventDefault();
        
        // Update tabs
        $('#userManagementTabs button').removeClass('active');
        $(this).addClass('active');
        
        // Update content
        $('.tab-pane').removeClass('show active');
        $($(this).data('bs-target')).addClass('show active');
        
        // Update URL hash
        history.pushState(null, null, $(this).data('bs-target'));
    });

    // Handle URL hash on page load
    const hash = window.location.hash || '#users';
    $(`#userManagementTabs button[data-bs-target="${hash}"]`).trigger('click');

    // Adjust included page content
    $('.tab-pane > .wrap').each(function() {
        // Remove margin/padding from included pages
        $(this).css({
            'margin': '0',
            'padding': '0'
        });
        
        // Hide original page titles
        $(this).find('> h1').hide();
    });
});
</script>
