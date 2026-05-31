<?php
/**
 * Snippet #8: Forces WordPress/MEC to use our PHP template
 * Scope: global | Active: Yes | Priority: 10
 * 
 */

/**
 * Override MEC single event template with our custom gasf template
 *
 * MEC bypasses WordPress's standard template hierarchy for themes it
 * recognises (including Hoot Du Premium). It uses the filter
 * 'mec_single_event_template' to determine which PHP file to load.
 * We hook in at priority 99 (after MEC sets it) and point it to our
 * custom template file in the theme root.
 */
add_filter('mec_single_event_template', function($template) {
    $custom = get_template_directory() . '/single-mec-events.php';
    if (file_exists($custom)) {
        return $custom;
    }
    return $template;
}, 99);