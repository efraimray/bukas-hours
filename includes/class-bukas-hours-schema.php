<?php
/**
 * LocalBusiness JSON-LD with openingHoursSpecification.
 *
 * @package BukasHours
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prints structured data so search engines can show the business hours.
 */
class Bukas_Hours_Schema {

	/**
	 * Schedule service.
	 *
	 * @var Bukas_Hours_Schedule
	 */
	private $schedule;

	/**
	 * Constructor.
	 *
	 * @param Bukas_Hours_Schedule $schedule Schedule service.
	 */
	public function __construct( Bukas_Hours_Schedule $schedule ) {
		$this->schedule = $schedule;
	}

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_action( 'wp_head', array( $this, 'print_schema' ), 30 );
	}

	/**
	 * Build the schema array. Filterable via `bukas_hours_schema`.
	 *
	 * @return array
	 */
	public function data() {
		$s     = $this->schedule->settings();
		$names = array(
			'mon' => 'Monday',
			'tue' => 'Tuesday',
			'wed' => 'Wednesday',
			'thu' => 'Thursday',
			'fri' => 'Friday',
			'sat' => 'Saturday',
			'sun' => 'Sunday',
		);

		$specs = array();
		foreach ( $names as $key => $day ) {
			$d = $s['days'][ $key ];
			if ( ! empty( $d['closed'] ) ) {
				continue;
			}
			foreach ( array( '', '2' ) as $suffix ) {
				if ( ! empty( $d[ 'open' . $suffix ] ) && ! empty( $d[ 'close' . $suffix ] ) ) {
					$specs[] = array(
						'@type'     => 'OpeningHoursSpecification',
						'dayOfWeek' => 'https://schema.org/' . $day,
						'opens'     => $d[ 'open' . $suffix ],
						'closes'    => $d[ 'close' . $suffix ],
					);
				}
			}
		}

		$special = array();
		foreach ( $this->schedule->upcoming_closures( 20 ) as $row ) {
			$has_hours = ! empty( $row['open'] ) && ! empty( $row['close'] );
			// schema.org convention: opens = closes = 00:00 means closed all day.
			$special[] = array(
				'@type'        => 'OpeningHoursSpecification',
				'validFrom'    => $row['date'],
				'validThrough' => $row['date'],
				'opens'        => $has_hours ? $row['open'] : '00:00',
				'closes'       => $has_hours ? $row['close'] : '00:00',
			);
		}

		$data = array(
			'@context'                  => 'https://schema.org',
			'@type'                     => $s['business_type'],
			'name'                      => $s['business_name'],
			'url'                       => home_url( '/' ),
			'openingHoursSpecification' => $specs,
		);
		if ( $special ) {
			$data['specialOpeningHoursSpecification'] = $special;
		}
		if ( $s['address'] ) {
			$data['address'] = $s['address'];
		}
		if ( $s['phone'] ) {
			$data['telephone'] = $s['phone'];
		}

		/**
		 * Filter the LocalBusiness schema before output.
		 *
		 * @param array $data Schema data.
		 */
		return apply_filters( 'bukas_hours_schema', $data );
	}

	/**
	 * Print JSON-LD on the front page when enabled.
	 */
	public function print_schema() {
		$s = $this->schedule->settings();
		if ( empty( $s['schema'] ) || ! ( is_front_page() || is_home() ) ) {
			return;
		}
		echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $this->data(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n";
	}
}
