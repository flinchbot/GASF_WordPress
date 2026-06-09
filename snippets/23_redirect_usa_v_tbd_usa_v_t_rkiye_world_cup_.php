<?php
/**
 * Snippet #23: Redirect: USA v TBD -> USA v Türkiye (World Cup)
 * Scope: front-end | Active: Yes | Priority: 1
 * 301 redirect from the old usa-v-tbd event slug to the renamed usa-v-turkiye slug.
 */

/**
 * GASF: 301 redirect for the renamed World Cup event slug.
 * Old: /events/world-cup-watch-party-usa-v-tbd/  ->  new: .../usa-v-turkiye/
 * Keeps any existing shared links (Facebook, calendar) from 404ing.
 */
add_action('template_redirect', function() {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, 'world-cup-watch-party-usa-v-tbd') !== false) {
        wp_redirect('https://germantampabay.com/events/world-cup-watch-party-usa-v-turkiye/', 301);
        exit;
    }
}, 1);