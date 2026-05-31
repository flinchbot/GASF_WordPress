<?php
/**
 * Snippet #10: Single MEC Events
 * Scope: global | Active: Yes | Priority: 10
 * 
 */

add_filter('mec_single_event_template', function($template) {
    $custom = get_template_directory() . '/single-mec-events.php';
    if (file_exists($custom)) {
        return $custom;
    }
    return $template;
}, 99);