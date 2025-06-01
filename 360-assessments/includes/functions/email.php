<?php
if (!defined('ABSPATH')) exit;

/**
 * Email Functions
 */
function handle_assessment_360_send_email() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('assessment_360_send_email', 'assessment_360_send_email_nonce');

    try {
        $template = isset($_POST['email_template']) ? $_POST['email_template'] : '';
        $recipients = isset($_POST['recipients']) ? array_map('intval', $_POST['recipients']) : [];

        if (empty($template) || empty($recipients)) {
            throw new Exception('Missing required fields');
        }

        $user_manager = Assessment_360_User_Manager::get_instance();
        $success_count = 0;
        $error_count = 0;

        foreach ($recipients as $user_id) {
            $user = $user_manager->get_user($user_id);
            if (!$user) continue;

            $subject = '';
            $body = '';
            if ($template === 'custom') {
                $subject = sanitize_text_field($_POST['custom_email_subject']);
                $body = wp_kses_post($_POST['custom_email_body']);
            } else {
                global $email_templates;
                if (!isset($email_templates[$template])) continue;
                
                $subject = $email_templates[$template]['subject'];
                $body = $email_templates[$template]['body'];
            }

            $new_password = '';
            if ($template === 'welcome') {
                $new_password = $user_manager->reset_user_password($user_id);
                if (is_wp_error($new_password)) {
                    $error_count++;
                    continue;
                }
            }

            $replacements = [
                '{first_name}' => $user->first_name,
                '{last_name}' => $user->last_name,
                '{email}' => $user->email,
                '{org_name}' => get_option('assessment_360_organization_name'),
                '{login_url}' => home_url('/360-assessment-login/'),
                '{new_password}' => $new_password
            ];

            $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
            $body = str_replace(array_keys($replacements), array_values($replacements), $body);

            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $sent = wp_mail($user->email, $subject, $body, $headers);

            if ($sent) {
                $success_count++;
            } else {
                $error_count++;
            }
        }

        $message = sprintf(
            'Emails sent successfully to %d recipient(s).%s',
            $success_count,
            $error_count > 0 ? " Failed to send to $error_count recipient(s)." : ''
        );

        wp_redirect(add_query_arg(
            $error_count > 0 ? 'error' : 'message',
            urlencode($message),
            wp_get_referer()
        ));
        exit;

    } catch (Exception $e) {
        wp_redirect(add_query_arg(
            'error',
            urlencode($e->getMessage()),
            wp_get_referer()
        ));
        exit;
    }
}
add_action('admin_post_assessment_360_send_email', 'handle_assessment_360_send_email');

function get_email_template_variables() {
    return [
        '{first_name}' => 'User\'s first name',
        '{last_name}' => 'User\'s last name',
        '{email}' => 'User\'s email address',
        '{org_name}' => 'Organization name',
        '{login_url}' => 'Login page URL',
        '{new_password}' => 'New password (for welcome emails)'
    ];
}
