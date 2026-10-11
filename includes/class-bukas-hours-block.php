<?php
/**
 * Dynamic "Bukas Hours" block (server-rendered, no build step).
 *
 * @package BukasHours
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the block from block.json and renders it with PHP.
 */
class Bukas_Hours_Block {

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
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register block type.
	 */
	public function register() {
		register_block_type(
			BUKAS_HOURS_DIR . 'blocks/status',
			array( 'render_callback' => array( $this, 'render_block' ) )
		);
	}

	/**
	 * Render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		$show = isset( $attributes['show'] ) ? $attributes['show'] : 'both';
		$html = '';
		if ( in_array( $show, array( 'status', 'both', 'all' ), true ) ) {
			$html .= $this->render->status_badge();
		}
		if ( in_array( $show, array( 'hours', 'both', 'all' ), true ) ) {
			$html .= $this->render->hours_table( true );
		}
		if ( 'all' === $show ) {
			$html .= $this->render->closures_list( 5 );
		}
		return '<div ' . get_block_wrapper_attributes( array( 'class' => 'bukas-block' ) ) . '>' . $html . '</div>';
	}
}
