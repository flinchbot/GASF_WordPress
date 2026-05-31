<?php
/**
 * Snippet #9: MEC Single Event PHP
 * Scope: global | Active: Yes | Priority: 10
 * 
 */

add_action("wp_enqueue_scripts", function() {
    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();

    if (is_singular("mec-events")) {
        wp_enqueue_style(
            "gasf-event-styles",
            $theme_uri . "/gasf-events.css",
            [],
            filemtime($theme_dir . "/gasf-events.css")
        );
    }

    if (is_page(4887)) {
        wp_enqueue_style(
            "gasf-maifest-styles",
            $theme_uri . "/gasf-maifest.css",
            [],
            filemtime($theme_dir . "/gasf-maifest.css")
        );
    }
});