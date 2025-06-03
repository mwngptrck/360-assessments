<?php if (!defined('ABSPATH')) exit; ?>

<h2>General Settings</h2>
<p class="description">Configure your organization settings and email templates.</p>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('save_setup_settings', 'setup_settings_nonce'); ?>
    <input type="hidden" name="action" value="save_setup_settings">

    <table class="form-table">
        <tr>
            <th><label for="org_name">Organization Name</label></th>
            <td>
                <input type="text" 
                       id="org_name" 
                       name="org_name" 
                       class="regular-text"
                       value="<?php echo esc_attr(get_option('assessment_360_organization_name', '')); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="org_logo_upload">Organization Logo</label></th>
            <td>
                <input type="hidden" 
                       id="org_logo" 
                       name="org_logo" 
                       value="<?php echo esc_url(get_option('assessment_360_organization_logo', '')); ?>">
                <button type="button" class="button" id="upload_logo_button">Upload Logo</button>
                <div id="org_logo_preview" style="margin-top:10px;">
                    <?php if (get_option('assessment_360_organization_logo', '')): ?>
                        <img src="<?php echo esc_url(get_option('assessment_360_organization_logo', '')); ?>" alt="Logo preview" style="max-height:80px;">
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php wp_enqueue_editor(); ?>

        <tr>
            <th><label for="welcome_email">Welcome Email Template</label></th>
            <td>
                <?php 
                wp_editor(
                    get_option('assessment_360_welcome_email', ''),
                    'welcome_email',
                    array(
                        'textarea_name' => 'welcome_email',
                        'textarea_rows' => 10,
                        'media_buttons' => false,
                        'teeny' => true,
                        'quicktags' => true
                    )
                );
                ?>
                <p class="description">Available variables: {first_name}, {last_name}, {email}, {password}, {login_url}</p>
            </td>
        </tr>

        <tr>
            <th><label for="reminder_email">Reminder Email Template</label></th>
            <td>
                <?php 
                wp_editor(
                    get_option('assessment_360_reminder_email', ''),
                    'reminder_email',
                    array(
                        'textarea_name' => 'reminder_email',
                        'textarea_rows' => 10,
                        'media_buttons' => false,
                        'teeny' => true,
                        'quicktags' => true
                    )
                );
                ?>
                <p class="description">Available variables: {first_name}, {last_name}, {login_url}</p>
            </td>
        </tr>
    </table>

    <div class="setup-actions">
        <?php if ($steps[$current_step]['prev']): ?>
            <a href="<?php echo esc_url(add_query_arg('step', $steps[$current_step]['prev'], remove_query_arg('message'))); ?>" 
               class="button">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        <?php endif; ?>

        <div>
            <a href="<?php echo esc_url(add_query_arg('step', $steps[$current_step]['next'], remove_query_arg('message'))); ?>" 
               class="button">Skip</a>
            <button type="submit" class="button button-primary">
                Save and Continue <i class="bi bi-arrow-right"></i>
            </button>
        </div>
    </div>
</form>

<script>
jQuery(document).ready(function($){
    var frame;
    $('#upload_logo_button').on('click', function(e){
        e.preventDefault();
        if (frame) {
            frame.open();
            return;
        }
        frame = wp.media({
            title: 'Select or Upload Logo',
            button: { text: 'Use this logo' },
            multiple: false
        });
        frame.on('select', function(){
            var attachment = frame.state().get('selection').first().toJSON();
            $('#org_logo').val(attachment.url);
            $('#org_logo_preview').html('<img src="' + attachment.url + '" alt="Logo preview" style="max-height:80px;">');
        });
        frame.open();
    });
});
</script>
