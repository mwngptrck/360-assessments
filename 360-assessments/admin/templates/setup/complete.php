<?php if (!defined('ABSPATH')) exit; ?>

<div class="setup-step-content">
    <h2>Setup Complete!</h2>
    
    <div class="notice notice-success">
        <p>
            <strong>Congratulations!</strong> You have successfully completed the setup wizard.
        </p>
    </div>

    <div class="setup-complete-summary">
        <h3>Setup Summary</h3>
        <ul class="setup-checklist">
            <li>
                <i class="bi bi-check-circle-fill text-success"></i>
                Organization settings configured
            </li>
            <li>
                <i class="bi bi-check-circle-fill text-success"></i>
                User groups created
            </li>
            <li>
                <i class="bi bi-check-circle-fill text-success"></i>
                Positions defined
            </li>
        </ul>

        <h3>Next Steps</h3>
        <ul>
            <li>Add users to the system</li>
            <li>Create your first assessment</li>
            <li>Set up user relationships</li>
            <li>Start collecting feedback</li>
        </ul>
    </div>

    <div class="setup-actions">
        <a href="<?php echo esc_url(add_query_arg('step', 'positions')); ?>" class="button">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=assessment-360')); ?>" 
           class="button button-primary">
            Go to Dashboard <i class="bi bi-arrow-right"></i>
        </a>
    </div>
</div>

<style>
.setup-complete-summary {
    max-width: 600px;
    margin: 30px 0;
}

.setup-checklist {
    list-style: none;
    padding: 0;
    margin: 20px 0;
}

.setup-checklist li {
    margin-bottom: 10px;
    display: flex;
    align-items: center;
}

.setup-checklist li i {
    margin-right: 10px;
}

.text-success {
    color: #198754;
}
</style>
