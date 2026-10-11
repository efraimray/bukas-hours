<?php
/**
 * Remove plugin data when the plugin is deleted from wp-admin.
 *
 * @package BukasHours
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'bukas_hours_settings' );
