<?php
if (!defined('ABSPATH')) exit;

// Ensure proper step handling
$allowed_steps = ['welcome', 'settings', 'groups', 'positions', 'complete'];
$current_step = isset($_GET['step']) ? sanitize_text_field($_GET['step']) : 'welcome';
$current_step = in_array($current_step, $allowed_steps) ? $current_step : 'welcome';

// Define steps without URLs - we'll build them when needed
$steps = array(
    'welcome' => array(
        'name' => 'Welcome',
        'next' => 'settings'
    ),
    'settings' => array(
        'name' => 'General Settings',
        'next' => 'groups'
    ),
    'groups' => array(
        'name' => 'User Groups',
        'next' => 'positions'
    ),
    'positions' => array(
        'name' => 'User Positions',
        'next' => 'complete'
    ),
    'complete' => array(
        'name' => 'Complete Setup',
        'next' => ''
    )
);

// Helper function to get step URL
function get_setup_step_url($step) {
    if (empty($step)) {
        return admin_url('admin.php?page=assessment-360-dashboard');
    }
    return add_query_arg(
        array(
            'page' => 'assessment-360-setup',
            'step' => $step
        ),
        admin_url('admin.php')
    );
}

// Calculate progress
$step_index = array_search($current_step, array_keys($steps));
$total_steps = count($steps) - 1;
$progress = $current_step === 'welcome' ? 0 : 
           ($current_step === 'complete' ? 100 : 
           round(($step_index / $total_steps) * 100));
?>


<div class="wrap">
    <div class="setup-wizard">
        <!-- Progress Bar -->
        <div class="wizard-progress mb-4">
            <div class="progress" style="height: 4px;">
                <div class="progress-bar" role="progressbar" 
                     style="width: <?php echo esc_attr($progress); ?>%" 
                     aria-valuenow="<?php echo esc_attr($progress); ?>" 
                     aria-valuemin="0" 
                     aria-valuemax="100">
                </div>
            </div>
            <div class="step-indicators d-flex justify-content-between mt-2">
                <?php foreach ($steps as $step_key => $step): 
                    $step_url = get_setup_step_url($step_key);
                    $is_active = $current_step === $step_key;
                    $is_completed = array_search($step_key, array_keys($steps)) < array_search($current_step, array_keys($steps));
                ?>
                    <div class="step-indicator <?php echo $is_active ? 'active' : ''; ?> <?php echo $is_completed ? 'completed' : ''; ?>">
                        <span class="step-number">
                            <?php if ($is_completed): ?>
                                <i class="bi bi-check-lg"></i>
                            <?php else: ?>
                                <?php echo esc_html(array_search($step_key, array_keys($steps)) + 1); ?>
                            <?php endif; ?>
                        </span>
                        <span class="step-name"><?php echo esc_html($step['name']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <!-- Step Content -->
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <?php
                switch ($current_step):
                    case 'welcome':
                        include(plugin_dir_path(__FILE__) . 'setup/welcome.php');
                        break;
                    case 'settings':
                        include(plugin_dir_path(__FILE__) . 'setup/settings.php');
                        break;
                    case 'groups':
                        include(plugin_dir_path(__FILE__) . 'setup/groups.php');
                        break;
                    case 'positions':
                        include(plugin_dir_path(__FILE__) . 'setup/positions.php');
                        break;
                    case 'complete':
                        include(plugin_dir_path(__FILE__) . 'setup/complete.php');
                        break;
                endswitch;
                ?>
            </div>
        </div>
    </div>
</div>

<style>
.setup-wizard {
    max-width: 800px;
    margin: 40px auto;
}

.wizard-progress {
    position: relative;
}

.step-indicators {
    position: relative;
    padding: 0 12px;
}

.step-indicator {
    text-align: center;
    position: relative;
    flex: 1;
}

.step-number {
    width: 30px;
    height: 30px;
    background: #e9ecef;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 8px;
    color: #6c757d;
    font-weight: 600;
}

.step-name {
    font-size: 0.875rem;
    color: #6c757d;
    display: block;
}

.step-indicator.active .step-number {
    background: #0d6efd;
    color: #fff;
}

.step-indicator.active .step-name {
    color: #0d6efd;
    font-weight: 600;
}

.step-indicator.completed .step-number {
    background: #198754;
    color: #fff;
}

.setup-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid #dee2e6;
}

.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}
</style>
