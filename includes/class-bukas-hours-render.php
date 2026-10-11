<?php
/**
 * Front-end markup shared by shortcodes and the block.
 *
 * @package BukasHours
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds escaped HTML for the status badge, weekly table and closures list.
 */
class Bukas_Hours_Render {

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
	 * Translated day names keyed by day key.
	 *
	 * @return string[]
	 */
	public static function day_labels() {
		return array(
			'mon' => __( 'Monday', 'bukas-hours' ),
			'tue' => __( 'Tuesday', 'bukas-hours' ),
			'wed' => __( 'Wednesday', 'bukas-hours' ),
			'thu' => __( 'Thursday', 'bukas-hours' ),
			'fri' => __( 'Friday', 'bukas-hours' ),
			'sat' => __( 'Saturday', 'bukas-hours' ),
			'sun' => __( 'Sunday', 'bukas-hours' ),
		);
	}

	/**
	 * Enqueue registered assets the first time something renders.
	 */
	private function enqueue() {
		if ( ! wp_style_is( 'bukas-hours', 'registered' ) ) {
			bukas_hours_register_assets();
		}
		wp_enqueue_style( 'bukas-hours' );
		wp_enqueue_script( 'bukas-hours' );
	}

	/**
	 * Format "HH:MM" using the site's time format.
	 *
	 * @param string $hm Time string.
	 * @return string
	 */
	private function time_label( $hm ) {
		$dt = DateTimeImmutable::createFromFormat( '!H:i', $hm, $this->schedule->timezone() );
		return $dt ? wp_date( get_option( 'time_format', 'g:i A' ), $dt->getTimestamp(), $this->schedule->timezone() ) : $hm;
	}

	/**
	 * Human sentence for the next change, e.g. "Closes at 5:00 PM".
	 *
	 * @param array $status Result of Bukas_Hours_Schedule::status().
	 * @return string
	 */
	private function detail_text( array $status ) {
		$next = $status['next_change'];
		if ( ! $next ) {
			return __( 'No upcoming hours scheduled', 'bukas-hours' );
		}
		$tz   = $this->schedule->timezone();
		$time = wp_date( get_option( 'time_format', 'g:i A' ), $next->getTimestamp(), $tz );

		if ( 'closed' !== $status['state'] ) {
			/* translators: %s: closing time. */
			return sprintf( __( 'Closes at %s', 'bukas-hours' ), $time );
		}

		$today = $this->schedule->now()->format( 'Y-m-d' );
		if ( $next->format( 'Y-m-d' ) === $today ) {
			/* translators: %s: opening time. */
			return sprintf( __( 'Opens at %s', 'bukas-hours' ), $time );
		}
		if ( $next->format( 'Y-m-d' ) === $this->schedule->now()->modify( '+1 day' )->format( 'Y-m-d' ) ) {
			/* translators: %s: opening time. */
			return sprintf( __( 'Opens tomorrow at %s', 'bukas-hours' ), $time );
		}
		/* translators: 1: weekday name, 2: opening time. */
		return sprintf( __( 'Opens %1$s at %2$s', 'bukas-hours' ), wp_date( 'l', $next->getTimestamp(), $tz ), $time );
	}

	/**
	 * Status badge. Server renders the correct state; the script then keeps it
	 * live (and correct behind page caches) using the embedded ranges.
	 *
	 * @return string
	 */
	public function status_badge() {
		$this->enqueue();
		$s      = $this->schedule->settings();
		$status = $this->schedule->status();
		$labels = array(
			'open'         => $s['open_text'],
			'closing_soon' => $s['soon_text'],
			'closed'       => $s['closed_text'],
		);

		$config = array(
			'ranges' => $this->schedule->upcoming_ranges( 8 ),
			'tz'     => $this->schedule->timezone()->getName(),
			'soon'   => (int) $s['closing_soon'],
			'labels' => $labels,
			'i18n'   => array(
				'closesAt'    => __( 'Closes at %s', 'bukas-hours' ),
				'opensAt'     => __( 'Opens at %s', 'bukas-hours' ),
				'opensTmrw'   => __( 'Opens tomorrow at %s', 'bukas-hours' ),
				'opensOn'     => __( 'Opens %1$s at %2$s', 'bukas-hours' ),
				'noUpcoming'  => __( 'No upcoming hours scheduled', 'bukas-hours' ),
			),
		);

		return sprintf(
			'<p class="bukas-status is-%1$s" data-bukas="%2$s" role="status" aria-live="polite"><span class="bukas-status__dot" aria-hidden="true"></span><strong class="bukas-status__label">%3$s</strong> <span class="bukas-status__detail">%4$s</span></p>',
			esc_attr( str_replace( '_', '-', $status['state'] ) ),
			esc_attr( wp_json_encode( $config ) ),
			esc_html( $labels[ $status['state'] ] ),
			esc_html( $this->detail_text( $status ) )
		);
	}

	/**
	 * Weekly hours table with today highlighted.
	 *
	 * @param bool $highlight_today Whether to mark today's row.
	 * @return string
	 */
	public function hours_table( $highlight_today = true ) {
		$this->enqueue();
		$s     = $this->schedule->settings();
		$today = Bukas_Hours_Schedule::DAYS[ (int) $this->schedule->now()->format( 'N' ) - 1 ];
		$rows  = '';

		foreach ( self::day_labels() as $key => $label ) {
			$day   = $s['days'][ $key ];
			$times = array();
			if ( empty( $day['closed'] ) ) {
				foreach ( array( '', '2' ) as $suffix ) {
					if ( ! empty( $day[ 'open' . $suffix ] ) && ! empty( $day[ 'close' . $suffix ] ) ) {
						$times[] = $this->time_label( $day[ 'open' . $suffix ] ) . ' – ' . $this->time_label( $day[ 'close' . $suffix ] );
					}
				}
			}
			$is_today = $highlight_today && $key === $today;
			$rows    .= sprintf(
				'<tr%1$s><th scope="row">%2$s%3$s</th><td>%4$s</td></tr>',
				$is_today ? ' class="is-today" aria-current="date"' : '',
				esc_html( $label ),
				$is_today ? ' <span class="bukas-hours__today">' . esc_html__( 'Today', 'bukas-hours' ) . '</span>' : '',
				$times ? esc_html( implode( ', ', $times ) ) : '<span class="bukas-hours__closed">' . esc_html__( 'Closed', 'bukas-hours' ) . '</span>'
			);
		}

		return '<table class="bukas-hours"><caption class="screen-reader-text">' . esc_html__( 'Opening hours', 'bukas-hours' ) . '</caption><tbody>' . $rows . '</tbody></table>';
	}

	/**
	 * Upcoming closures / special hours list.
	 *
	 * @param int $limit Max rows.
	 * @return string
	 */
	public function closures_list( $limit = 5 ) {
		$this->enqueue();
		$rows = $this->schedule->upcoming_closures( $limit );
		if ( ! $rows ) {
			return '<p class="bukas-closures bukas-closures--empty">' . esc_html__( 'No upcoming holiday closures.', 'bukas-hours' ) . '</p>';
		}

		$tz    = $this->schedule->timezone();
		$items = '';
		foreach ( $rows as $row ) {
			$date  = DateTimeImmutable::createFromFormat( '!Y-m-d', $row['date'], $tz );
			$when  = $date ? wp_date( get_option( 'date_format', 'F j, Y' ), $date->getTimestamp(), $tz ) : $row['date'];
			$hours = ( ! empty( $row['open'] ) && ! empty( $row['close'] ) )
				? $this->time_label( $row['open'] ) . ' – ' . $this->time_label( $row['close'] )
				: __( 'Closed', 'bukas-hours' );
			$items .= sprintf(
				'<li><time datetime="%1$s">%2$s</time> <span class="bukas-closures__label">%3$s</span> <span class="bukas-closures__hours">%4$s</span></li>',
				esc_attr( $row['date'] ),
				esc_html( $when ),
				esc_html( $row['label'] ),
				esc_html( $hours )
			);
		}
		return '<ul class="bukas-closures">' . $items . '</ul>';
	}
}
