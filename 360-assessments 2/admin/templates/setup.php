<?php
if (!defined('ABSPATH')) exit;

// Define steps
$steps = [
    'welcome' => [
        'number' => 1,
        'title' => 'Welcome',
        'next' => 'settings',
        'prev' => ''
    ],
    'settings' => [
        'number' => 2,
        'title' => 'General Settings',
        'next' => 'groups',
        'prev' => 'welcome'
    ],
    'groups' => [
        'number' => 3,
        'title' => 'User Groups',
        'next' => 'positions',
        'prev' => 'settings'
    ],
    'positions' => [
        'number' => 4,
        'title' => 'User Positions',
        'next' => 'complete',
        'prev' => 'groups'
    ],
    'complete' => [
        'number' => 5,
        'title' => 'Complete Setup',
        'next' => '',
        'prev' => 'positions'
    ]
];

// Get current step
$allowed_steps = array_keys($steps);
$current_step = isset($_GET['step']) ? sanitize_key($_GET['step']) : 'welcome';
$current_step = in_array($current_step, $allowed_steps) ? $current_step : 'welcome';

// Calculate progress
$step_index = array_search($current_step, array_keys($steps));
$progress = ($step_index / (count($steps) - 1)) * 100;
?>

<div class="wrap">
    <h1>360° Assessment Setup</h1>

    <!-- Progress Bar and Steps -->
    <div class="setup-progress">
        <div class="progress-bar">
            <div class="progress" style="width: <?php echo esc_attr($progress); ?>%"></div>
        </div>
        <div class="step-indicators">
            <?php foreach ($steps as $step_key => $step): 
                $is_active = $current_step === $step_key;
                $is_completed = array_search($step_key, array_keys($steps)) < array_search($current_step, array_keys($steps));
            ?>
                <div class="step <?php echo $is_active ? 'active' : ''; ?> <?php echo $is_completed ? 'completed' : ''; ?>">
                    <span class="step-number"><?php echo esc_html($step['number']); ?></span>
                    <span class="step-title"><?php echo esc_html($step['title']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Content -->
    <div class="setup-content">
        <?php
        $template_path = plugin_dir_path(__FILE__) . 'setup/' . $current_step . '.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
        ?>
    </div>
</div>

<style>
.wrap h1 {
    text-align: center;
}
.setup-progress {
/*    margin: 40px 0;*/
    max-width: 80%;
    margin: 0 auto;
}

.progress-bar {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    margin-bottom: 30px;
}

.progress-bar .progress {
    height: 100%;
    background: #0d6efd;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.step-indicators {
    display: flex;
    justify-content: space-between;
    position: relative;
    width: 80%;
    margin: 0 auto;
}

.step {
    text-align: center;
    flex: 1;
    position: relative;
}

.step-number {
    width: 32px;
    height: 32px;
    background: #e9ecef;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 8px;
    font-weight: 600;
    color: #6c757d;
}

.step.active .step-number {
    background: #0d6efd;
    color: white;
}

.setup-complete .step-number {
    background: #198754;
    color: white;
}

.step-title {
    font-size: 0.875rem;
    color: #6c757d;
    display: block;
}

.setup-content {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin: 0 auto;
    max-width: 80%;
}

.setup-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
}
    
.regular-text {
    width: 100%;
}

.setup-actions .button {
    min-width: 100px;
    text-align: center;
}
</style>
