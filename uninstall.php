<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 * Removes the saved settings so nothing is left in the database.
 *
 * @package RingCursor
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'ring_cursor_options' );
