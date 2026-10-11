<?php
/**
 * Shortcodes: [bukas_status], [bukas_hours], [bukas_closures].
 *
 * @package BukasHours
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers shortcodes that delegate to the shared renderer.
 */
class Bukas_Hours_Shortcodes {

	/**
	 * Renderer.
	 *
	 * @var Bukas_Hours_Render
	 */
	private $render;

	/**
	 * Constructor.
	 *
	 * @param Bukas_Hours_Render $render Renderer.
	 */
	public function __construct( Bukas_Hours_Render $render ) {
		$this->render = $render;
	}

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_shortcode( 'bukas_status', array( $this, 'status' ) );
		add_shortcode( 'bukas_hours', array( $this, 'hours' ) );
		add_shortcode( 'bukas_closures', array( $this, 'closures' ) );
	}

	/**
	 * [bukas_status] — live open/closed badge.
	 *
	 * @return string
	 */
	public function status() {
		return $this->render->status_badge();
	}

	/**
	 * [bukas_hours highlight_today="yes"] — weekly table.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function hours( $atts ) {
		$atts = shortcode_atts( array( 'highlight_today' => 'yes' ), $atts, 'bukas_hours' );
		return $this->render->hours_table( 'no' !== strtolower( $atts['highlight_today'] ) );
	}

	/**
	 * [bukas_closures limit="5"] — upcoming holidays and special hours.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function closures( $atts ) {
		$atts = shortcode_atts( array( 'limit' => 5 ), $atts, 'bukas_closures' );
		return $this->render->closures_list( absint( $atts['limit'] ) );
	}
}
