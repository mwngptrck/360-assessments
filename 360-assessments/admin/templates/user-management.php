<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <!-- Bootstrap Container -->
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-12">
                <h1 class="wp-heading-inline mb-4">
                    <i class="bi bi-people me-2"></i>User Management
                </h1>

                <?php if (isset($_GET['message'])): ?>
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

                <!-- Bootstrap Tabs -->
                <ul class="nav nav-tabs mb-3" id="userManagementTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="users-tab" data-bs-toggle="tab" 
                                data-bs-target="#users" type="button" role="tab">
                            <i class="bi bi-person me-1"></i>Users
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="positions-tab" data-bs-toggle="tab" 
                                data-bs-target="#positions" type="button" role="tab">
                            <i class="bi bi-briefcase me-1"></i>Positions
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="groups-tab" data-bs-toggle="tab" 
                                data-bs-target="#groups" type="button" role="tab">
                            <i class="bi bi-people-fill me-1"></i>User Groups
                        </button>
                    </li>
                </ul>
                
                <?php 
                    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'users';
                    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';

                    // Pass these variables to the included templates
                    $_GET['active_tab'] = $current_tab;
                ?>

                <div class="tab-content" id="userManagementTabsContent">
                    <!-- Users Tab -->
                    <div class="tab-pane fade show active" id="users" role="tabpanel">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <?php include(ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/users.php'); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Positions Tab -->
                    <div class="tab-pane fade" id="positions" role="tabpanel">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <?php include(ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/positions.php'); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Groups Tab -->
                    <div class="tab-pane fade" id="groups" role="tabpanel">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <?php include(ASSESSMENT_360_PLUGIN_DIR . 'admin/templates/user-groups.php'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Card Styles */
.card {
    border: none;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    max-width: 100%
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

/* Tab Styles */
.nav-tabs {
    border-bottom: 1px solid #dee2e6;
}

.nav-tabs .nav-link {
    color: #495057;
    border: 1px solid transparent;
    border-top-left-radius: 0.25rem;
    border-top-right-radius: 0.25rem;
    padding: 0.75rem 1.25rem;
}

.nav-tabs .nav-link:hover {
    border-color: #e9ecef #e9ecef #dee2e6;
}

.nav-tabs .nav-link.active {
    color: #495057;
    background-color: #fff;
    border-color: #dee2e6 #dee2e6 #fff;
}

/* Remove duplicate headers and notices */
.tab-pane .wrap > h1,
.tab-pane .wrap > .notice,
.tab-pane .wrap > .updated,
.tab-pane .wrap > .error {
    display: none;
}

/* Adjust inner padding */
.tab-pane .wrap {
    margin: 0;
    padding: 0;
}

/* Responsive Design */
@media screen and (max-width: 782px) {
    .nav-tabs {
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .nav-tabs .nav-link {
        white-space: nowrap;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle URL hash for tabs
    let hash = window.location.hash;
    if (hash) {
        const tab = new bootstrap.Tab(document.querySelector(`button[data-bs-target="${hash}"]`));
        tab.show();
    }

    // Update URL hash when tab changes
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        history.pushState(null, null, $(e.target).data('bs-target'));
    });

    // Handle form submissions to maintain active tab
    $('form').on('submit', function(e) {
        const activeTab = $('.nav-link.active').attr('id').replace('-tab', '');
        $(this).append(
            $('<input>')
                .attr('type', 'hidden')
                .attr('name', 'active_tab')
                .val('#' + activeTab)
        );
    });
    
    // Handle links to maintain active tab
    $('a[href*="assessment-360-user-management"]').each(function() {
        const href = $(this).attr('href');
        if (!href.includes('#')) {
            const activeTab = $('.nav-link.active').attr('id').replace('-tab', '');
            $(this).attr('href', href + '#' + activeTab);
        }
    });

    // Restore active tab after form submission
    const activeTab = '<?php echo isset($_POST['active_tab']) ? $_POST['active_tab'] : ''; ?>';
    if (activeTab) {
        const tab = new bootstrap.Tab(document.querySelector(`button[data-bs-target="${activeTab}"]`));
        tab.show();
    }
});
</script>
