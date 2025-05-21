<?php
if (!defined('ABSPATH')) exit;

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die('Sorry, you are not allowed to access this page.');
}

$assessment_manager = Assessment_360_Assessment_Manager::get_instance();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Assessments</h1>
    
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
        $assessment = null;

        if ($_GET['action'] === 'edit' && isset($_GET['id'])) {
            if (WP_DEBUG) {
                //error_log('Loading assessment for edit: ' . $_GET['id']);
            }

            $assessment = $assessment_manager->get_assessment(intval($_GET['id']));

            if (!$assessment) {
                if (WP_DEBUG) {
                    error_log('Assessment not found: ' . $_GET['id']);
                }
                ?>
                <div class="notice notice-error">
                    <p>Assessment not found.</p>
                </div>
                <?php
                return;
            }

            if (WP_DEBUG) {
                error_log('Assessment loaded: ' . print_r($assessment, true));
            }
        }
        ?>
        <div class="assessment-form-container">
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=assessment-360-assessments')); ?>">
                <?php wp_nonce_field('save_assessment_nonce'); ?>
                <input type="hidden" name="action" value="save_assessment">
                <?php if ($assessment): ?>
                    <input type="hidden" name="id" value="<?php echo esc_attr($assessment->id ?? ''); ?>">
                <?php endif; ?>
                <?php wp_nonce_field('save_assessment_nonce'); ?>
                <?php if ($assessment): ?>
                    <input type="hidden" name="id" value="<?php echo esc_attr($assessment->id ?? ''); ?>">
                <?php endif; ?>
                <?php if ($assessment): ?>
                    <input type="hidden" name="id" value="<?php echo esc_attr($assessment->id); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="name">Assessment Name</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($assessment ? $assessment->name : ''); ?>" 
                                   required>
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
                                      rows="4"><?php echo esc_textarea($assessment ? $assessment->description : ''); ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="start_date">Start Date</label>
                        </th>
                        <td>
                            <input type="date" 
                                   id="start_date" 
                                   name="start_date" 
                                   value="<?php echo esc_attr($assessment ? $assessment->start_date : ''); ?>" 
                                   <?php echo !$assessment ? 'min="' . esc_attr(date('Y-m-d')) . '"' : ''; ?>
                                   required>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="end_date">End Date</label>
                        </th>
                        <td>
                            <input type="date" 
                                   id="end_date" 
                                   name="end_date" 
                                   value="<?php echo esc_attr($assessment ? $assessment->end_date : ''); ?>" 
                                   <?php echo !$assessment ? 'min="' . esc_attr(date('Y-m-d')) . '"' : ''; ?>
                                   required>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $assessment ? 'Update Assessment' : 'Add Assessment'; ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=assessment-360-assessments'); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>

    <?php else: ?>
        <!-- Assessments List -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-name">Name</th>
                        <th scope="col" class="manage-column column-dates">Dates</th>
                        <th scope="col" class="manage-column column-status">Status</th>
                        <th scope="col" class="manage-column column-progress">Progress</th>
                        <th scope="col" class="manage-column column-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $assessments = $assessment_manager->get_all_assessments();
                    if (!empty($assessments)): 
                        foreach ($assessments as $assessment): 
                            $progress = $assessment_manager->get_assessment_progress($assessment->id);
                    ?>
                        <tr>
                            <td class="column-name">
                                <strong><?php echo esc_html($assessment->name); ?></strong>
                                <br>
                                <span class="description">
                                    <?php echo wp_trim_words($assessment->description, 10); ?>
                                </span>
                            </td>
                            <td class="column-dates">
                                <?php 
                                echo esc_html(date('M j, Y', strtotime($assessment->start_date))) . ' - ' . 
                                     esc_html(date('M j, Y', strtotime($assessment->end_date))); 
                                ?>
                            </td>
                            <td class="column-status">
                                <span class="status-badge status-<?php echo esc_attr($assessment->status); ?>">
                                    <?php echo esc_html(ucfirst($assessment->status)); ?>
                                </span>
                            </td>
                            <td class="column-progress">
                                <div class="progress-wrapper">
                                    <div class="progress">
                                        <div class="progress-bar" 
                                             role="progressbar" 
                                             style="width: <?php echo esc_attr($progress ? $progress->percentage : 0); ?>%"
                                             aria-valuenow="<?php echo esc_attr($progress ? $progress->percentage : 0); ?>"
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                        </div>
                                    </div>
                                    <span class="progress-text">
                                        <?php 
                                        if ($progress) {
                                            echo esc_html($progress->completed) . ' of ' . 
                                                 esc_html($progress->total) . ' completed';
                                        } else {
                                            echo '0 of 0 completed';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </td>
                            <td class="column-actions">
                                <div class="actions-buttons">
                                    <a href="<?php echo esc_url(add_query_arg([
                                        'page' => 'assessment-360-assessments',
                                        'action' => 'edit',
                                        'id' => $assessment->id
                                    ], admin_url('admin.php'))); ?>" 
                                       class="button button-small">Edit</a>

                                    <?php if ($assessment->status === 'draft'): ?>
                                        <?php 
                                        $active_assessment = $assessment_manager->has_active_assessment();
                                        if (!$active_assessment):
                                        ?>
                                            <a href="<?php echo wp_nonce_url(
                                                add_query_arg([
                                                    'action' => 'enable_assessment',
                                                    'id' => $assessment->id
                                                ], admin_url('admin-post.php')),
                                                'enable_assessment_' . $assessment->id
                                            ); ?>" class="button button-small">Enable</a>
                                        <?php endif; ?>
                                    <?php elseif ($assessment->status === 'active'): ?>
                                        <a href="<?php echo wp_nonce_url(
                                            add_query_arg([
                                                'action' => 'complete_assessment',
                                                'id' => $assessment->id
                                            ], admin_url('admin-post.php')),
                                            'complete_assessment_' . $assessment->id
                                        ); ?>" class="button button-small complete-assessment">Complete</a>
                                    <?php endif; ?>

                                    <?php if ($assessment->status === 'draft'): ?>
                                        <a href="<?php echo wp_nonce_url(
                                            add_query_arg([
                                                'action' => 'delete_assessment',
                                                'id' => $assessment->id
                                            ], admin_url('admin-post.php')),
                                            'delete_assessment_' . $assessment->id
                                        ); ?>" 
                                           class="button button-small delete-assessment" 
                                           onclick="return confirm('Are you sure you want to delete this assessment? This action cannot be undone.');">Delete</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                        <tr>
                            <td colspan="5">No assessments found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
    <?php endif; ?>
</div>

<style>
/* Form Container */
.assessment-form-container {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-top: 20px;
}

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.status-draft {
    background-color: #ddd;
    color: #23282d;
}

.status-active {
    background-color: #00a32a;
    color: #fff;
}

.status-completed {
    background-color: #1e1e1e;
    color: #fff;
}

/* Progress Bar */
.progress-wrapper {
    width: 200px;
}

.progress {
    height: 8px;
    background-color: #f0f0f1;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 5px;
}

.progress-bar {
    background-color: #2271b1;
    height: 100%;
    transition: width .3s ease;
}

.progress-text {
    font-size: 12px;
    color: #50575e;
}

/* Action Buttons */
.actions-buttons {
    display: flex;
    gap: 5px;
}

.button-small {
    padding: 0 8px !important;
    text-align: center !important;
    height: 24px !important;
    font-size: 11px !important;
    min-width: 60px;
}

/* Column Widths */
.column-name {
    width: 25%;
}

.column-dates {
    width: 20%;
}

.column-status {
    width: 15%;
}

.column-progress {
    width: 20%;
}

.column-actions {
    width: 20%;
}

/* Description Text */
.description {
    color: #666;
    font-size: 12px;
    margin-top: 4px;
}

/* Form Fields */
.form-table input[type="text"],
.form-table input[type="date"] {
    width: 100%;
    max-width: 25em;
}

.form-table textarea {
    width: 100%;
    max-width: 40em;
}

/* Error Highlighting */
.error {
    border-color: #cc1818 !important;
}

/* Required Field Indicator */
label[for] {
    display: inline-block;
}

label[for]::after {
    content: " *";
    color: #cc1818;
}

label[for="description"]::after {
    content: "";
}
.button-primary.loading {
    position: relative;
    color: transparent !important;
}

.button-primary.loading::after {
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
    border-top-color: #fff;
    animation: spin 1s infinite linear;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
.button.loading {
    position: relative;
    color: transparent !important;
}

.button.loading::after {
    content: '';
    position: absolute;
    left: 50%;
    top: 50%;
    width: 12px;
    height: 12px;
    margin-left: -6px;
    margin-top: -6px;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 1s infinite linear;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
    
    /* Status Badges */
.status-completed {
    background-color: #2271b1;
    color: #fff;
}

/* Loading State */
.button.loading {
    position: relative;
    color: transparent !important;
}

.button.loading::after {
    content: '';
    position: absolute;
    left: 50%;
    top: 50%;
    width: 12px;
    height: 12px;
    margin-left: -6px;
    margin-top: -6px;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 1s infinite linear;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>



<script>
jQuery(document).ready(function($) {
    
    $('form').on('submit', function() {
        $(this).find('button[type="submit"]')
            .addClass('loading')
            .prop('disabled', true);
    });
    
    // Delete confirmation
//    $('.delete-assessment').on('click', function(e) {
//        if (!confirm('Are you sure you want to delete this assessment? This action cannot be undone.')) {
//            e.preventDefault();
//        }
//    });

    // Start assessment confirmation
    $('a[href*="start_assessment"]').on('click', function(e) {
        if (!confirm('Are you sure you want to start this assessment? Once started, it cannot be deleted.')) {
            e.preventDefault();
        }
    });

    // Complete assessment confirmation
    $('a[href*="complete_assessment"]').on('click', function(e) {
        if (!confirm('Are you sure you want to complete this assessment? This will end the assessment period.')) {
            e.preventDefault();
        }
    });

    $('form').on('submit', function(e) {
        // Prevent default submission
        e.preventDefault();
        
        const startDate = new Date($('#start_date').val());
        const endDate = new Date($('#end_date').val());
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        // Validate required fields
        if (!$('#name').val().trim()) {
            alert('Please enter an assessment name');
            return false;
        }
        
        if (!$('#start_date').val()) {
            alert('Please select a start date');
            return false;
        }
        
        if (!$('#end_date').val()) {
            alert('Please select an end date');
            return false;
        }
        
        // Validate dates
        if (!$('input[name="id"]').length && startDate < today) {
            alert('Start date cannot be earlier than today');
            return false;
        }
        
        if (endDate < startDate) {
            alert('End date cannot be earlier than start date');
            return false;
        }
        
        // If all validation passes, submit the form
        this.submit();
    });

    // Date validation on change
    $('#end_date').on('change', function() {
        const startDate = new Date($('#start_date').val());
        const endDate = new Date($(this).val());

        if (endDate < startDate) {
            alert('End date cannot be earlier than start date');
            $(this).val('');
        }
    });
    
//    $('.delete-assessment').on('click', function(e) {
//        if (!confirm('Are you sure you want to delete this assessment? This action cannot be undone.')) {
//            e.preventDefault();
//            return false;
//        }
//        
//        // Add loading state
//        $(this).addClass('loading').prop('disabled', true);
//        return true;
//    });
    
    $('#start_date').attr('min', new Date().toISOString().split('T')[0]);
    $('#end_date').attr('min', new Date().toISOString().split('T')[0]);
    
    // Complete assessment confirmation
    $('.complete-assessment').on('click', function(e) {
        if (!confirm('Are you sure you want to complete this assessment? This action cannot be undone and will end the assessment period.')) {
            e.preventDefault();
            return false;
        }
        
        // Add loading state
        $(this).addClass('loading').prop('disabled', true);
    });

    // Delete assessment confirmation
    $('.delete-assessment').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this assessment? This action cannot be undone.')) {
            e.preventDefault();
            return false;
        }
        
        // Add loading state
        $(this).addClass('loading').prop('disabled', true);
    });

    
});
</script>
