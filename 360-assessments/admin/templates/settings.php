<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <!-- Bootstrap Container -->
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-12">
                <h1 class="wp-heading-inline mb-4">
                    <i class="bi bi-gear me-2"></i>360° Assessment Settings
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
                <ul class="nav nav-tabs mb-3" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" 
                                data-bs-target="#general" type="button" role="tab">
                            <i class="bi bi-sliders me-1"></i>General Settings
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="email-tab" data-bs-toggle="tab" 
                                data-bs-target="#email" type="button" role="tab">
                            <i class="bi bi-envelope me-1"></i>Email Settings
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="database-tab" data-bs-toggle="tab" 
                                data-bs-target="#database" type="button" role="tab">
                            <i class="bi bi-sliders me-1"></i>Database
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="settingsTabsContent">
                    <!-- General Settings Tab -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel">
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" 
                              enctype="multipart/form-data" class="needs-validation" novalidate>
                            <?php wp_nonce_field('assessment_360_settings', 'assessment_360_settings_nonce'); ?>
                            <input type="hidden" name="action" value="assessment_360_save_settings">

                            <div class="row">
                                <!-- Organization Details -->
                                <div class="col-md-6">
                                    <div class="card mb-4">
                                        <div class="card-header bg-light">
                                            <h5 class="card-title mb-0">
                                                <i class="bi bi-building me-2"></i>Organization Details
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-4">
                                                <label for="organization_name" class="form-label">
                                                    Organization Name <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" 
                                                       id="organization_name" 
                                                       name="organization_name" 
                                                       class="form-control" 
                                                       value="<?php echo esc_attr(get_option('assessment_360_organization_name', '')); ?>" 
                                                       required>
                                            </div>

                                            <div class="mb-4">
                                                <label for="organization_logo" class="form-label">Organization Logo</label>
                                                <?php 
                                                $logo_url = get_option('assessment_360_organization_logo', '');
                                                if ($logo_url): 
                                                ?>
                                                    <div class="current-logo mb-3 p-3 bg-light rounded">
                                                        <img src="<?php echo esc_url($logo_url); ?>" 
                                                             alt="Organization Logo" 
                                                             class="img-fluid mb-2" 
                                                             style="max-height: 100px;">
                                                        <div class="form-check mt-2">
                                                            <input class="form-check-input" type="checkbox" 
                                                                   name="remove_logo" value="1" id="remove_logo">
                                                            <label class="form-check-label" for="remove_logo">
                                                                Remove current logo
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <input type="file" 
                                                       id="organization_logo" 
                                                       name="organization_logo" 
                                                       class="form-control upload-files" 
                                                       accept="image/*">
                                                <div class="form-text">
                                                    Recommended size: 200x50 pixels. Maximum file size: 2MB.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Contact Information -->
                                <div class="col-md-6">
                                    <div class="card mb-4">
                                        <div class="card-header bg-light">
                                            <h5 class="card-title mb-0">
                                                <i class="bi bi-person-lines-fill me-2"></i>Contact Information
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-4">
                                                <label for="contact_email" class="form-label">Contact Email</label>
                                                <input type="email" 
                                                       id="contact_email" 
                                                       name="contact_email" 
                                                       class="form-control" 
                                                       value="<?php echo esc_attr(get_option('assessment_360_contact_email', '')); ?>">
                                            </div>

                                            <div class="mb-4">
                                                <label for="contact_phone" class="form-label">Contact Phone</label>
                                                <input type="tel" 
                                                       id="contact_phone" 
                                                       name="contact_phone" 
                                                       class="form-control" 
                                                       value="<?php echo esc_attr(get_option('assessment_360_contact_phone', '')); ?>">
                                            </div>

                                            <div class="mb-4">
                                                <label for="contact_address" class="form-label">Contact Address</label>
                                                <textarea id="contact_address" 
                                                          name="contact_address" 
                                                          class="form-control" 
                                                          rows="3"><?php echo esc_textarea(get_option('assessment_360_contact_address', '')); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save me-1"></i>Save General Settings
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Email Settings Tab -->
                    <div class="tab-pane fade" id="email" role="tabpanel">
                        <?php
                        // Fetch stored templates (these should be set during plugin activation)
                        $welcome_subject = get_option('assessment_360_welcome_email_subject', 'Welcome to {org_name}');
                        $welcome_body = get_option('assessment_360_welcome_email', 'Hi {first_name},<br><br>Welcome to {org_name}! Your account has been created.<br>Email: {email}<br>Password: {password}<br><a href="{login_url}">Login here</a>');
                        $reminder_subject = get_option('assessment_360_reminder_email_subject', 'Reminder: Complete your {assessment_name} Assessment');
                        $reminder_body = get_option('assessment_360_reminder_email', 'Hi {first_name},<br><br>This is a reminder to complete your {assessment_name} assessment by {due_date}.<br>You have {days_remaining} days left.<br>Pending: {pending_list}<br><a href="{login_url}">Login here</a>');
                        ?>
                        <div class="row">
                            <!-- Left Column: Email Forms -->
                            <div class="col-md-7">
                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                    <?php wp_nonce_field('assessment_360_email_settings', 'assessment_360_email_settings_nonce'); ?>
                                    <input type="hidden" name="action" value="assessment_360_save_email_settings">

                                    <!-- Welcome Email Section -->
                                    <div class="card mb-4">
                                        <div class="card-header bg-light">
                                            <h5 class="card-title mb-0">
                                                <i class="bi bi-envelope-paper me-2"></i>Welcome Email Template
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-4">
                                                <label for="welcome_email_subject" class="form-label">Subject</label>
                                                <input type="text" 
                                                       id="welcome_email_subject" 
                                                       name="welcome_email_subject" 
                                                       class="form-control" 
                                                       value="<?php echo esc_attr($welcome_subject); ?>"
                                                       onkeyup="updateEmailPreview('welcome')">
                                            </div>

                                            <div class="mb-4">
                                                <label for="welcome_email_body" class="form-label">Body</label>
                                                <?php 
                                                wp_editor(
                                                    $welcome_body, 
                                                    'welcome_email_body',
                                                    array(
                                                        'media_buttons' => false,
                                                        'textarea_name' => 'welcome_email_body',
                                                        'textarea_rows' => 10,
                                                        'teeny' => true,
                                                        'quicktags' => true
                                                    )
                                                );
                                                ?>
                                                <div class="form-text mt-2">
                                                    Available placeholders: {first_name}, {email}, {password}, {login_url}, {org_name}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Reminder Email Section -->
                                    <div class="card mb-4">
                                        <div class="card-header bg-light">
                                            <h5 class="card-title mb-0">
                                                <i class="bi bi-bell me-2"></i>Reminder Email Template
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-4">
                                                <label for="reminder_email_subject" class="form-label">Subject</label>
                                                <input type="text" 
                                                       id="reminder_email_subject" 
                                                       name="reminder_email_subject" 
                                                       class="form-control" 
                                                       value="<?php echo esc_attr($reminder_subject); ?>"
                                                       onkeyup="updateEmailPreview('reminder')">
                                            </div>

                                            <div class="mb-4">
                                                <label for="reminder_email_body" class="form-label">Body</label>
                                                <?php 
                                                wp_editor(
                                                    $reminder_body, 
                                                    'reminder_email_body',
                                                    array(
                                                        'media_buttons' => false,
                                                        'textarea_name' => 'reminder_email_body',
                                                        'textarea_rows' => 10,
                                                        'teeny' => true,
                                                        'quicktags' => true
                                                    )
                                                );
                                                ?>
                                                <div class="form-text mt-2">
                                                    Available placeholders: {first_name}, {assessment_name}, {due_date}, 
                                                    {days_remaining}, {pending_list}, {login_url}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-save me-1"></i>Save Email Settings
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Right Column: Email Previews -->
                            <div class="col-md-5">
                                <div class="sticky-top" style="top: 20px;">
                                    <!-- Welcome Email Preview -->
                                    <div class="card mb-4">
                                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                            <h5 class="card-title mb-0">
                                                <i class="bi bi-envelope-paper me-2"></i>Welcome Email Preview
                                            </h5>
                                            <button class="btn btn-sm btn-outline-primary" onclick="refreshPreview('welcome')">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <div class="email-preview-container">
                                                <div class="email-preview-header">
                                                    <strong>Subject:</strong> 
                                                    <span id="welcome-subject-preview"></span>
                                                </div>
                                                <hr>
                                                <div class="email-preview-body" id="welcome-body-preview">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Reminder Email Preview -->
                                    <div class="card mb-4">
                                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                            <h5 class="card-title mb-0">
                                                <i class="bi bi-bell me-2"></i>Reminder Email Preview
                                            </h5>
                                            <button class="btn btn-sm btn-outline-primary" onclick="refreshPreview('reminder')">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <div class="email-preview-container">
                                                <div class="email-preview-header">
                                                    <strong>Subject:</strong> 
                                                    <span id="reminder-subject-preview"></span>
                                                </div>
                                                <hr>
                                                <div class="email-preview-body" id="reminder-body-preview">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Database Tab -->
                    <div class="tab-pane fade" id="database" role="tabpanel">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <?php
                                    $backup_success = get_transient('assessment_360_backup_success');
                                    if ($backup_success) : ?>
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <?php echo esc_html($backup_success); ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    <?php
                                        delete_transient('assessment_360_backup_success');
                                    endif;

                                    $backup_error = get_transient('assessment_360_backup_error');
                                    if ($backup_error) : ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <?php echo esc_html($backup_error); ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    <?php
                                        delete_transient('assessment_360_backup_error');
                                    endif;
                                ?>
                                
                                <!-- Database Backup Section -->
                                <div class="card shadow-sm mb-4">
                                    <div class="card-header bg-light">
                                        <h3 class="card-title h5 mb-0">
                                            <i class="bi bi-database me-2"></i>Database Backup
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <!-- Create Backup Button -->
                                            <div>
                                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="mb-4">
                                                    <?php wp_nonce_field('create_backup_nonce'); ?>
                                                    <input type="hidden" name="action" value="create_backup">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="bi bi-download me-2"></i>Create New Backup
                                                    </button>
                                                </form>
                                                <div class="form-text mb-4">
                                                    Create and manage backups of your 360 Assessment database.
                                                </div>
                                            </div>

                                            <!-- Dummy Data Import Button with Modal -->
                                            <div>
                                                <button id="openImportDummyDataModal" type="button" class="btn btn-secondary mb-3" data-bs-toggle="modal" data-bs-target="#importDummyDataModal">
                                                    <i class="bi bi-upload me-2"></i>Import Dummy Data
                                                </button>
                                                <div class="form-text mb-4">
                                                    <span class="text-danger">Warning:</span> This will remove all existing 360 assessment data and import fresh dummy data.
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Modal for confirmation -->
                                        <div class="modal fade" id="importDummyDataModal" tabindex="-1" aria-labelledby="importDummyDataModalLabel" aria-hidden="true">
                                          <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                              <div class="modal-header">
                                                <h5 class="modal-title" id="importDummyDataModalLabel">Confirm Dummy Data Import</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                              </div>
                                              <div class="modal-body">
                                                Are you sure you want to import dummy data? <br>
                                                <span class="text-danger fw-bold">This will OVERWRITE all existing 360 assessment data and cannot be undone.</span>
                                              </div>
                                              <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form id="import-dummy-data-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                                    <?php wp_nonce_field('import_dummy_data_nonce'); ?>
                                                    <input type="hidden" name="action" value="import_dummy_data_360">
                                                    <button type="submit" class="btn btn-danger">
                                                        <i class="bi bi-upload me-2"></i>Yes, Import Dummy Data
                                                    </button>
                                                </form>
                                              </div>
                                            </div>
                                          </div>
                                        </div>

                                        <!-- Existing Backups -->
                                        <?php 
                                        $backup_manager = Assessment_360_Backup_Manager::get_instance();
                                        $backups = $backup_manager->get_backups();
                                        if (!empty($backups)): 
                                        ?>
                                            <h6 class="mb-3">Recent Backups</h6>
                                            <div class="table-responsive">
                                                <table class="table table-hover align-middle">
                                                    <thead>
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Size</th>
                                                            <th>Tables</th>
                                                            <th class="text-end">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($backups as $backup): 
                                                            $delete_url = wp_nonce_url(
                                                                add_query_arg([
                                                                    'action' => 'delete_backup',
                                                                    'file' => $backup['file']
                                                                ], admin_url('admin-post.php')),
                                                                'delete_backup_' . $backup['file']
                                                            );
                                                        ?>
                                                            <tr>
                                                                <td>
                                                                    <?php echo esc_html(date('F j, Y g:i a', strtotime($backup['date']))); ?>
                                                                </td>
                                                                <td><?php echo esc_html($backup['size']); ?></td>
                                                                <td><?php echo esc_html($backup['tables']); ?></td>
                                                                <td class="text-end">
                                                                    <div class="btn-group">
                                                                        <a href="<?php echo esc_url($backup['url']); ?>" 
                                                                           class="btn btn-sm btn-outline-primary" 
                                                                           download>
                                                                            <i class="bi bi-download"></i>
                                                                        </a>
                                                                        <button 
                                                                            type="button"
                                                                            class="btn btn-sm btn-outline-danger delete-backup" 
                                                                            data-bs-toggle="modal"
                                                                            data-bs-target="#deleteBackupModal"
                                                                            data-delete-url="<?php echo esc_url($delete_url); ?>"
                                                                            data-backup-file="<?php echo esc_attr($backup['file']); ?>"
                                                                        >
                                                                            <i class="bi bi-trash"></i>
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <!-- Delete Backup Modal -->
                                            <div class="modal fade" id="deleteBackupModal" tabindex="-1" aria-labelledby="deleteBackupModalLabel" aria-hidden="true">
                                              <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                  <div class="modal-header">
                                                    <h5 class="modal-title" id="deleteBackupModalLabel">Delete Backup</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                  </div>
                                                  <div class="modal-body">
                                                    Are you sure you want to delete the backup file <span id="modalBackupFileName" class="fw-bold"></span>?
                                                  </div>
                                                  <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <a href="#" id="confirmDeleteBackup" class="btn btn-danger">Delete</a>
                                                  </div>
                                                </div>
                                              </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info mb-0">
                                                <i class="bi bi-info-circle me-2"></i>No backups available.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <style>
                                .delete-backup {
                                    cursor: pointer;
                                }
                                </style>
                                <script>
                                jQuery(document).ready(function($) {
                                    $('#deleteBackupModal').on('show.bs.modal', function (event) {
                                        var button = $(event.relatedTarget);
                                        var deleteUrl = button.data('delete-url');
                                        var backupFile = button.data('backup-file');
                                        var modal = $(this);
                                        modal.find('#confirmDeleteBackup').attr('href', deleteUrl);
                                        modal.find('#modalBackupFileName').text(backupFile);
                                    });
                                });
                                </script>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Add any additional styles from your forms page here */
.card {
    border: none;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    max-width: 100%;
    padding: 0
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
form{
    box-shadow: none;
    padding: 0;
    margin: 0
}
.form-label {
    font-weight: 500;
    margin-bottom: 0.5rem;
}
    
.upload-files {padding:5px 10px !important}

.current-logo img {
    max-width: 200px;
    height: auto;
}

/* WP Editor Adjustments */
.wp-editor-container {
    border: 1px solid #ddd;
    border-radius: 4px;
}

.wp-editor-area {
    border: none !important;
}

/* Bootstrap Overrides */
.nav-tabs .nav-link {
    color: #2271b1;
}

.nav-tabs .nav-link.active {
    color: #1d2327;
    border-color: #c3c4c7 #c3c4c7 #fff;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .card-body {
        padding: 1rem;
    }
}
.email-preview-container {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 15px;
}

.email-preview-header {
    padding-bottom: 10px;
    color: #666;
}

.email-preview-body {
    min-height: 200px;
    font-size: 0.9em;
    line-height: 1.5;
}

.sticky-top {
    z-index: 100;
}

/* Preview placeholder styles */
.placeholder-text {
    color: #6c757d;
    font-style: italic;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Handle URL hash for tabs
    let hash = window.location.hash;
    if (hash) {
        const tab = new bootstrap.Tab(document.querySelector(`button[data-bs-target="${hash}"]`));
        tab.show();
    }

    // Update URL hash when tab changes
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        history.pushState(null, null, $(e.target).data('bs-target'));
    });

    // File input validation
    $('#organization_logo').on('change', function() {
        const file = this.files[0];
        if (file) {
            if (file.size > 2 * 1024 * 1024) { // 2MB
                alert('File size exceeds 2MB limit.');
                this.value = '';
            }
        }
    });

    // Remove logo checkbox handling
    $('#remove_logo').on('change', function() {
        if ($(this).is(':checked')) {
            $('#organization_logo').prop('disabled', true);
        } else {
            $('#organization_logo').prop('disabled', false);
        }
    });

    // Form validation
    $('.needs-validation').on('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        $(this).addClass('was-validated');
    });

    // Email Preview Functions
    function updateEmailPreview(type) {
        // Get subject
        const subject = $('#' + type + '_email_subject').val();
        $('#' + type + '-subject-preview').text(replacePlaceholders(subject, type));

        // Get body from TinyMCE
        if (typeof tinyMCE !== 'undefined') {
            const editor = tinyMCE.get(type + '_email_body');
            if (editor) {
                const content = editor.getContent();
                $('#' + type + '-body-preview').html(replacePlaceholders(content, type));
            }
        }
    }

    function replacePlaceholders(text, type) {
        const sampleData = {
            first_name: 'John',
            email: 'john.doe@example.com',
            password: '********',
            login_url: window.location.origin + '/360-assessment-login/',
            org_name: $('#organization_name').val() || 'Your Organization',
            assessment_name: 'Q4 2025 Assessment',
            due_date: '2025-12-31',
            days_remaining: '5',
            pending_list: '3 assessments pending'
        };

        return text.replace(/\{(\w+)\}/g, function(match, key) {
            return sampleData[key] || match;
        });
    }

    // Initialize email previews
    if (typeof tinyMCE !== 'undefined') {
        tinyMCE.on('AddEditor', function(e) {
            e.editor.on('Change KeyUp', function() {
                const type = e.editor.id.replace('_email_body', '');
                updateEmailPreview(type);
            });
        });

        // Handle subject input changes
        $('#welcome_email_subject, #reminder_email_subject').on('input', function() {
            const type = $(this).attr('id').replace('_email_subject', '');
            updateEmailPreview(type);
        });
    }

    // Refresh preview button handler
    window.refreshPreview = function(type) {
        updateEmailPreview(type);
    };

    // Initial preview update with delay to ensure TinyMCE is ready
    setTimeout(function() {
        updateEmailPreview('welcome');
        updateEmailPreview('reminder');
    }, 1000);

    // Update preview when switching tabs
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        if ($(e.target).attr('id') === 'email-tab') {
            updateEmailPreview('welcome');
            updateEmailPreview('reminder');
        }
    });

    // Handle TinyMCE initialization
    $(document).on('tinymce-editor-init', function(event, editor) {
        editor.on('Change KeyUp', function() {
            const type = editor.id.replace('_email_body', '');
            updateEmailPreview(type);
        });
    });
});
</script>

