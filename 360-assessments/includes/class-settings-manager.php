<?php
class Assessment_360_Settings_Manager {
    private static $instance = null;
    private $settings = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        //add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_post_assessment_360_save_settings', array($this, 'handle_settings_save'));
        add_filter('admin_title', array($this, 'modify_admin_title'));
        add_action('assessment_360_login_form_header', array($this, 'add_logo_to_login'));
        add_action('assessment_360_dashboard_header', array($this, 'add_dashboard_header'));
        add_action('admin_post_assessment_360_save_email_settings', array($this, 'handle_save_email_settings')); 
    }

    public function init_settings() {
        $default_settings = array(
            'assessment_360_organization_name' => '',
            'assessment_360_organization_logo' => '',
            'assessment_360_contact_email' => '',
            'assessment_360_contact_phone' => '',
            'assessment_360_contact_address' => '',
            'assessment_360_welcome_email_subject' => 'Welcome to {org_name} 360° Assessment',
            'assessment_360_welcome_email_body' => $this->get_default_welcome_email(),
            'assessment_360_reminder_email_subject' => '360° Assessment Reminder',
            'assessment_360_reminder_email_body' => $this->get_default_reminder_email()
        );

        foreach ($default_settings as $option_name => $default_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $default_value);
            }
        }
    }

    public function handle_settings_save() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('assessment_360_settings', 'assessment_360_settings_nonce');

        // Save organization name
        $org_name = sanitize_text_field($_POST['organization_name']);
        if (empty($org_name)) {
            wp_redirect(add_query_arg('error', 'Organization name is required.', wp_get_referer()));
            exit;
        }
        update_option('assessment_360_organization_name', $org_name);

        // Handle logo upload
        if (isset($_FILES['organization_logo']) && $_FILES['organization_logo']['size'] > 0) {
            $this->handle_logo_upload();
        } elseif (isset($_POST['remove_logo'])) {
            delete_option('assessment_360_organization_logo');
        }

        // Save contact details
        update_option('assessment_360_contact_email', sanitize_email($_POST['contact_email']));
        update_option('assessment_360_contact_phone', sanitize_text_field($_POST['contact_phone']));
        update_option('assessment_360_contact_address', sanitize_textarea_field($_POST['contact_address']));

        // Save email templates
        update_option('assessment_360_welcome_email_subject', 
            sanitize_text_field($_POST['welcome_email_subject']));
        update_option('assessment_360_welcome_email_body', 
            sanitize_textarea_field($_POST['welcome_email_body']));
        update_option('assessment_360_reminder_email_subject', 
            sanitize_text_field($_POST['reminder_email_subject']));
        update_option('assessment_360_reminder_email_body', 
            sanitize_textarea_field($_POST['reminder_email_body']));

        wp_redirect(add_query_arg('message', 'Settings saved successfully.', wp_get_referer()));
        exit;
    }

    private function handle_logo_upload() {
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
        $max_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($_FILES['organization_logo']['type'], $allowed_types)) {
            wp_redirect(add_query_arg('error', 'Invalid file type. Please upload an image.', wp_get_referer()));
            exit;
        }

        if ($_FILES['organization_logo']['size'] > $max_size) {
            wp_redirect(add_query_arg('error', 'File is too large. Maximum size is 2MB.', wp_get_referer()));
            exit;
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('organization_logo', 0);

        if (is_wp_error($attachment_id)) {
            wp_redirect(add_query_arg('error', $attachment_id->get_error_message(), wp_get_referer()));
            exit;
        }

        $logo_url = wp_get_attachment_url($attachment_id);
        update_option('assessment_360_organization_logo', $logo_url);
    }

    public function modify_admin_title($admin_title) {
        $org_name = get_option('assessment_360_organization_name', '');
        if (!empty($org_name) && strpos($_SERVER['REQUEST_URI'], 'assessment-360') !== false) {
            return $org_name . ' - 360° Assessment';
        }
        return $admin_title;
    }

    public function add_logo_to_login() {
        $logo_url = get_option('assessment_360_organization_logo', '');
        if ($logo_url) {
            echo '<div class="organization-logo">';
            echo '<img src="' . esc_url($logo_url) . '" alt="Organization Logo">';
            echo '</div>';
        }
    }

    public function add_dashboard_header() {
        $org_name = get_option('assessment_360_organization_name', '');
        $logo_url = get_option('assessment_360_organization_logo', '');
        ?>
        <div class="dashboard-header">
            <?php if ($logo_url): ?>
                <div class="organization-logo">
                    <img src="<?php echo esc_url($logo_url); ?>" 
                         alt="<?php echo esc_attr($org_name); ?>">
                </div>
            <?php endif; ?>
            <h1><?php echo esc_html($org_name); ?> - 360° Assessment</h1>
        </div>
        <?php
    }

    private function get_default_welcome_email() {
        return "Hello {first_name},

Welcome to the {org_name} 360° Assessment System!

Your account has been created with the following credentials:

Username: {email}
Password: {password}

Please login at: {login_url}

For security reasons, please change your password after your first login.

Best regards,
The Assessment Team";
    }

    private function get_default_reminder_email() {
        return "Hello {first_name},

This is a reminder that you have pending assessments that need to be completed.

Assessment: {assessment_name}
Due Date: {due_date}
Days Remaining: {days_remaining}

Pending Assessments:
{pending_list}

Please login at {login_url} to complete your assessments.

Best regards,
The Assessment Team";
    }

    public function get_setting($key, $default = '') {
        return get_option('assessment_360_' . $key, $default);
    }

    public function update_setting($key, $value) {
        return update_option('assessment_360_' . $key, $value);
    }

    public function delete_setting($key) {
        return delete_option('assessment_360_' . $key);
    }

    public function remove_settings() {
        $settings = array(
            'organization_name',
            'organization_logo',
            'contact_email',
            'contact_phone',
            'contact_address',
            'welcome_email_subject',
            'welcome_email_body',
            'reminder_email_subject',
            'reminder_email_body'
        );

        foreach ($settings as $setting) {
            delete_option('assessment_360_' . $setting);
        }
    }
    
    public function assessment_360_handle_email_send() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('assessment_360_send_email', 'assessment_360_send_email_nonce');

        $template = sanitize_text_field($_POST['email_template']);
        $recipients = isset($_POST['recipients']) ? array_map('intval', $_POST['recipients']) : array();

        if (empty($recipients)) {
            wp_redirect(add_query_arg(
                'error',
                urlencode('No recipients selected.'),
                admin_url('admin.php?page=assessment-360-settings#email-templates')
            ));
            exit;
        }

        $user_manager = Assessment_360_User_Manager::get_instance();
        $group_manager = Assessment_360_Group_Manager::get_instance();

        // Group recipients by user group
        $grouped_recipients = array();
        foreach ($recipients as $user_id) {
            $user = $user_manager->get_user($user_id);
            if (!$user) continue;

            $group_name = $user->group_name ?? 'Ungrouped';
            if (!isset($grouped_recipients[$group_name])) {
                $grouped_recipients[$group_name] = array();
            }
            $grouped_recipients[$group_name][] = $user;
        }

        $results = array(
            'success' => 0,
            'failed' => 0,
            'groups' => array()
        );

        // Process each group
        foreach ($grouped_recipients as $group_name => $users) {
            $group_results = array(
                'total' => count($users),
                'success' => 0,
                'failed' => 0
            );

            foreach ($users as $user) {
                switch ($template) {
                    case 'welcome':
                        $subject = get_option('assessment_360_welcome_email_subject');
                        $body = get_option('assessment_360_welcome_email_body');
                        break;

                    case 'reminder':
                        $subject = get_option('assessment_360_reminder_email_subject');
                        $body = get_option('assessment_360_reminder_email_body');
                        break;

                    case 'custom':
                        $subject = sanitize_text_field($_POST['custom_email_subject']);
                        $body = wp_kses_post($_POST['custom_email_body']);
                        break;

                    default:
                        continue 2;
                }

                // Replace placeholders
                $replacements = array(
                    '{first_name}' => $user->first_name,
                    '{email}' => $user->email,
                    '{login_url}' => home_url('/360-assessment-login/'),
                    '{org_name}' => get_option('assessment_360_organization_name')
                );

                // Add assessment-specific placeholders for reminder emails
                if ($template === 'reminder') {
                    $active_assessment = Assessment_360_Assessment_Manager::get_instance()->get_current_assessment();
                    if ($active_assessment) {
                        $replacements['{assessment_name}'] = $active_assessment->name;
                        $replacements['{due_date}'] = date('F j, Y', strtotime($active_assessment->end_date));
                        $replacements['{days_remaining}'] = max(0, floor((strtotime($active_assessment->end_date) - time()) / 86400));

                        // Get pending assessments list
                        $pending_assessments = Assessment_360_Assessment_Manager::get_instance()
                            ->get_pending_assessments_for_user($user->id, $active_assessment->id);
                        $pending_list = '';
                        foreach ($pending_assessments as $pending) {
                            $pending_list .= "- {$pending->first_name} {$pending->last_name} ({$pending->position_name})\n";
                        }
                        $replacements['{pending_list}'] = $pending_list;
                    }
                }

                $email_subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
                $email_body = str_replace(array_keys($replacements), array_values($replacements), $body);

                $headers = array('Content-Type: text/html; charset=UTF-8');

                if (wp_mail($user->email, $email_subject, $email_body, $headers)) {
                    $results['success']++;
                    $group_results['success']++;
                } else {
                    $results['failed']++;
                    $group_results['failed']++;
                }
            }

            $results['groups'][$group_name] = $group_results;
        }

        // Build detailed message
        $message = 'Email sending results:<br>';
        $message .= "Total Success: {$results['success']}<br>";
        $message .= "Total Failed: {$results['failed']}<br><br>";

        foreach ($results['groups'] as $group_name => $group_results) {
            $message .= "$group_name: {$group_results['success']}/{$group_results['total']} sent successfully<br>";
        }

        wp_redirect(add_query_arg(
            $results['failed'] > 0 ? 'error' : 'message',
            urlencode($message),
            admin_url('admin.php?page=assessment-360-settings#email-templates')
        ));
        exit;
    }
    
    // Add these action handlers if not already present
    //add_action('admin_post_assessment_360_save_settings', 'handle_save_settings');
    //add_action('admin_post_assessment_360_save_email_settings', 'handle_save_email_settings');

    public function handle_save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('assessment_360_settings', 'assessment_360_settings_nonce');

        // Handle logo upload
        if (!empty($_FILES['organization_logo']['tmp_name'])) {
            $upload = wp_handle_upload($_FILES['organization_logo'], array('test_form' => false));
            if (!isset($upload['error'])) {
                update_option('assessment_360_organization_logo', $upload['url']);
            }
        }

        // Handle logo removal
        if (isset($_POST['remove_logo'])) {
            delete_option('assessment_360_organization_logo');
        }

        // Save other settings
        $settings = array(
            'organization_name',
            'contact_email',
            'contact_phone',
            'contact_address'
        );

        foreach ($settings as $setting) {
            if (isset($_POST[$setting])) {
                update_option('assessment_360_' . $setting, sanitize_text_field($_POST[$setting]));
            }
        }

        wp_redirect(add_query_arg('message', 'Settings saved successfully.', wp_get_referer()));
        exit;
    }

    public function handle_save_email_settings() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('assessment_360_email_settings', 'assessment_360_email_settings_nonce');

        $settings = array(
            'welcome_email_subject'   => 'sanitize_text_field',
            'welcome_email_body'      => 'wp_kses_post',
            'reminder_email_subject'  => 'sanitize_text_field',
            'reminder_email_body'     => 'wp_kses_post'
        );

        foreach ($settings as $setting => $sanitize_callback) {
            if (isset($_POST[$setting])) {
                update_option('assessment_360_' . $setting, $sanitize_callback($_POST[$setting]));
            }
        }

        wp_redirect(add_query_arg('message', 'Email settings saved successfully.', wp_get_referer()));
        exit;
    }


}
