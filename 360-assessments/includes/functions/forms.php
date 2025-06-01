<?php
if (!defined('ABSPATH')) exit;

/**
 * Form Management Functions
 * 
 * This file handles general form functionality that spans across
 * topics, sections, and questions, such as:
 * - Form validation helpers
 * - Common form processing functions
 * - Form utility functions
 * - Form display helpers
 */

/**
 * Validate form fields
 */
function assessment_360_validate_form_fields($required_fields, $data) {
    $errors = [];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[] = sprintf('%s is required', str_replace('_', ' ', ucfirst($field)));
        }
    }
    return $errors;
}

/**
 * Get form field value
 */
function assessment_360_get_form_field($field_name, $default = '') {
    return isset($_POST[$field_name]) ? $_POST[$field_name] : $default;
}

/**
 * Display form error messages
 */
function assessment_360_display_form_errors($errors) {
    if (!empty($errors)) {
        echo '<div class="notice notice-error"><p>' . 
             implode('</p><p>', array_map('esc_html', $errors)) . 
             '</p></div>';
    }
}

// Add other common form functionality as needed
