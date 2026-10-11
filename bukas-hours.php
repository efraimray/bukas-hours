<?php
/**
 * Plugin Name:       Bukas Hours
 * Plugin URI:        https://www.efraimray.com/
 * Description:       Business hours, a cache-safe "Open now" badge, holiday closures and LocalBusiness schema for small businesses. Shortcodes and a block included.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Efraim Ray
 * Author URI:        https://www.efraimray.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bukas-hours
 * Domain Path:       /languages
 *
 * @package BukasHours
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BUKAS_HOURS_VERSION', '1.0.0' );
define( 'BUKAS_HOURS_FILE', __FILE__ );
define( 'BUKAS_HOURS_DIR', plugin_dir_path( __FILE__ ) );
define( 'BUKAS_HOURS_URL', plugin_dir_url( __FILE__ ) );
define( 'BUKAS_HOURS_OPTION', 'bukas_hours_settings' );

require_once BUKAS_HOURS_DIR . 'includes/class-bukas-hours-schedule.php';
require_once BUKAS_HOURS_DIR . 'includes/class-bukas-hours-render.php';
require_once BUKAS_HOURS_DIR . 'includes/class-bukas-hours-settings.php';
require_once BUKAS_HOURS_DIR . 'includes/class-bukas-hours-shortcodes.php';
require_once BUKAS_HOURS_DIR . 'includes/class-bukas-hours-schema.php';
require_once BUKAS_HOURS_DIR . 'includes/class-bukas-hours-block.php';

/**
 * Boot the plugin once all plugins are loaded.
 */
function bukas_hours_init() {
	load_plugin_textdomain( 'bukas-hours', false, dirname( plugin_basename( BUKAS_HOURS_FILE ) ) . '/languages' );

	$schedule = new Bukas_Hours_Schedule( Bukas_Hours_Settings::get() );
	$render   = new Bukas_Hours_Render( $schedule );

	( new Bukas_Hours_Settings( $schedule ) )->hooks();
	( new Bukas_Hours_Shortcodes( $render ) )->hooks();
	( new Bukas_Hours_Schema( $schedule ) )->hooks();
	( new Bukas_Hours_Block( $render ) )->hooks();

	add_action( 'wp_enqueue_scripts', 'bukas_hours_register_assets' );
}
add_action( 'plugins_loaded', 'bukas_hours_init' );

/**
 * Register (not enqueue) front-end assets. They are enqueued only when
 * a shortcode or block actually renders, so pages without hours stay lean.
 */
function bukas_hours_register_assets() {
	wp_register_style( 'bukas-hours', BUKAS_HOURS_URL . 'assets/css/bukas-hours.css', array(), BUKAS_HOURS_VERSION );
	wp_register_script( 'bukas-hours', BUKAS_HOURS_URL . 'assets/js/bukas-hours.js', array(), BUKAS_HOURS_VERSION, true );
}

/**
 * Seed default settings on activation.
 */
function bukas_hours_activate() {
	if ( false === get_option( BUKAS_HOURS_OPTION ) ) {
		add_option( BUKAS_HOURS_OPTION, Bukas_Hours_Settings::defaults() );
	}
}
register_activation_hook( __FILE__, 'bukas_hours_activate' );

/**
 * Add a "Settings" link on the Plugins screen.
 *
 * @param array $links Existing action links.
 * @return array
 */
function bukas_hours_action_links( $links ) {
	$url = admin_url( 'options-general.php?page=bukas-hours' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'bukas-hours' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'bukas_hours_action_links' );
