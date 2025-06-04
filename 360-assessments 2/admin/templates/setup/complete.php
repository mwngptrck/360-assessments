<?php if (!defined('ABSPATH')) exit; ?>

<div class="setup-complete">
    <i class="bi bi-check2-circle" style="font-size: 40px;"></i>
    <h2>Setup Complete!</h2>
    <p class="description">Your 360° Assessment system is now configured and ready to use.</p>

    <div class="setup-complete-actions">
        <a href="<?php echo esc_url(admin_url('admin.php?page=assessment-360')); ?>" 
           class="button button-primary">
            Go to Dashboard
        </a>
    </div>
</div>

<style>
.setup-complete {
    text-align: center;
    padding: 40px 0;
}

.setup-complete-actions {
    margin-top: 30px;
}
</style>
