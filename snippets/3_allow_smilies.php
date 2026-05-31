<?php
/**
 * Snippet #3: Allow smilies
 * Scope: global | Active: Yes | Priority: 10
 * Allows smiley conversion in obscure places.

This is a sample snippet. Feel free to use it, edit it, or remove it.
 */

add_filter( 'widget_text', 'convert_smilies' );
add_filter( 'the_title', 'convert_smilies' );
add_filter( 'wp_title', 'convert_smilies' );
add_filter( 'get_bloginfo', 'convert_smilies' );