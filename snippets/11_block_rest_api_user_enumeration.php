<?php
/**
 * Snippet #11: Block REST API user enumeration
 * Scope: global | Active: Yes | Priority: 10
 * 
 */

// Block REST API user enumeration
// Controlled by GASF_BLOCK_REST_USERS constant in wp-config.php
if ( defined('GASF_BLOCK_REST_USERS') && GASF_BLOCK_REST_USERS ) {
    add_filter('rest_endpoints', function($endpoints) {
        unset($endpoints['/wp/v2/users']);
        unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
        return $endpoints;
    });
}