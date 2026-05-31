<?php
/**
 * Snippet #7: Footer CSS Override
 * Scope: global | Active: Yes | Priority: 10
 * 
 */

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'mec-nuclear',
        get_stylesheet_directory_uri() . '/mec-nuclear.css',
        [],
        null
    );
}, 9999);