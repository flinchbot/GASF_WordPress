<?php
/**
 * Snippet #2: Disable admin bar
 * Scope: front-end | Active: Yes | Priority: 10
 * Turns off the WordPress admin bar for everyone except administrators.

This is a sample snippet. Feel free to use it, edit it, or remove it.
 */

add_action( 'wp', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		show_admin_bar( false );
	}
} );