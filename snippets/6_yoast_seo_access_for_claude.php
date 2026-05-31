<?php
/**
 * Snippet #6: Yoast SEO access for Claude
 * Scope: global | Active: Yes | Priority: 10
 * 
 */

add_action('init', function() {
    $yoast_fields = [
        '_yoast_wpseo_title',
        '_yoast_wpseo_metadesc',
        '_yoast_wpseo_focuskw',
        '_yoast_wpseo_canonical',
    ];
    foreach ($yoast_fields as $field) {
        register_meta('post', $field, [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ]);
    }
});