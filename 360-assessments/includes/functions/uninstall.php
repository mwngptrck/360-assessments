<?php
if (!defined('ABSPATH')) exit;

function assessment_360_uninstall() {
    if (!get_option('assessment_360_allow_uninstall')) {
        return;
    }

    try {
        Assessment_360_User_Manager::get_instance()->remove_tables();
        Assessment_360_Group_Manager::get_instance()->remove_tables();
        Assessment_360_Assessment_Manager::get_instance()->remove_tables();
        Assessment_360_Settings_Manager::get_instance()->remove_settings();

        $options = [
            'assessment_360_version',
            'assessment_360_installed',
            'assessment_360_allow_uninstall',
            'assessment_360_organization_name',
            'assessment_360_organization_logo'
        ];

        foreach ($options as $option) {
            delete_option($option);
        }

        $pages = ['360-assessment-login', '360-assessment-dashboard'];
        foreach ($pages as $page_slug) {
            $page = get_page_by_path($page_slug);
            if ($page) {
                wp_delete_post($page->ID, true);
            }
        }

        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '%assessment_360_%'"
        );

    } catch (Exception $e) {
        error_log('360 Assessment uninstall error: ' . $e->getMessage());
    }
}
