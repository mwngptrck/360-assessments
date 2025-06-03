<div class="welcome-content text-center">
    <div class="welcome-icon mb-4">
        <i class="bi bi-gear-fill" style="font-size: 4rem; color: #0d6efd;"></i>
    </div>
    
    <h2>Welcome to 360° Assessment Setup</h2>
    
    <p class="description mb-4">
        This wizard will help you configure your 360° Assessment system. You'll set up:
    </p>
    
    <div class="setup-steps-preview mb-4">
        <div class="step-preview">
            <i class="bi bi-gear"></i>
            <span>General Settings</span>
        </div>
        <div class="step-preview">
            <i class="bi bi-people"></i>
            <span>User Groups</span>
        </div>
        <div class="step-preview">
            <i class="bi bi-person-badge"></i>
            <span>User Positions</span>
        </div>
    </div>

    <p class="mb-4">
        You can skip any step and configure it later from the plugin settings.
    </p>

    <div class="setup-actions justify-content-center">
        <a href="<?php echo esc_url(add_query_arg('step', 'settings', remove_query_arg('message'))); ?>" 
           class="button button-primary button-hero">
            Start Setup
        </a>
    </div>
</div>

<style>
.welcome-content {
    text-align: center;
    padding: 40px 0;
}

.setup-steps-preview {
    display: flex;
    justify-content: center;
    gap: 30px;
    margin: 30px 0;
}

.step-preview {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    min-width: 150px;
}

.step-preview i {
    font-size: 2rem;
    color: #0d6efd;
    margin-bottom: 10px;
    display: block;
}
</style>
