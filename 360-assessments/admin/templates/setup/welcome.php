<h2 class="h4 mb-4">Welcome to 360° Assessment Setup</h2>
<p class="text-muted mb-4">
    This wizard will help you configure the basic settings for your 360° Assessment system. 
    You can skip any step and configure it later from the plugin settings.
</p>

<div class="setup-actions">
    <div></div>
    <div>
        <a href="<?php echo esc_url(add_query_arg('step', $steps[$current_step]['next'], remove_query_arg('message'))); ?>" 
           class="btn btn-primary">
            Get Started <i class="bi bi-arrow-right ms-2"></i>
        </a>
    </div>
</div>
