<?php
/**
 * Schedule logic: turns saved settings into concrete open/close ranges.
 *
 * This class has no WordPress output side-effects, which keeps it easy to
 * reason about and to unit test.
 *
 * @package BukasHours
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes opening ranges, status and upcoming closures.
 */
class Bukas_Hours_Schedule {

	/**
	 * Day keys in ISO-8601 order (Monday = 1).
	 *
	 * @var string[]
	 */
	const DAYS = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );

	/**
	 * Sanitized settings array.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Sanitized settings (see Bukas_Hours_Settings::defaults()).
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Read access to settings for renderers.
	 *
	 * @return array
	 */
	public function settings() {
		return $this->settings;
	}

	/**
	 * Business timezone. Falls back to the site timezone, then Asia/Manila.
	 *
	 * @return DateTimeZone
	 */
	public function timezone() {
		$tz = ! empty( $this->settings['timezone'] ) ? $this->settings['timezone'] : wp_timezone_string();
		try {
			return new DateTimeZone( $tz );
		} catch ( Exception $e ) {
			return new DateTimeZone( 'Asia/Manila' );
		}
	}

	/**
	 * Current time in the business timezone.
	 *
	 * @return DateTimeImmutable
	 */
	public function now() {
		return new DateTimeImmutable( 'now', $this->timezone() );
	}

	/**
	 * Find a closure/special-hours entry for a given date, if any.
	 *
	 * @param DateTimeImmutable $date Any moment on the date.
	 * @return array|null Closure row or null.
	 */
	public function closure_for( DateTimeImmutable $date ) {
		$ymd = $date->format( 'Y-m-d' );
		foreach ( (array) $this->settings['closures'] as $row ) {
			if ( isset( $row['date'] ) && $row['date'] === $ymd ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * Raw "HH:MM" shift pairs that apply on a date (closures override weekdays).
	 *
	 * @param DateTimeImmutable $date Any moment on the date.
	 * @return array[] List of array( 'open' => 'HH:MM', 'close' => 'HH:MM' ).
	 */
	public function shifts_for( DateTimeImmutable $date ) {
		$closure = $this->closure_for( $date );
		if ( $closure ) {
			// A closure with times means "special hours"; without times, closed all day.
			if ( ! empty( $closure['open'] ) && ! empty( $closure['close'] ) ) {
				return array( array( 'open' => $closure['open'], 'close' => $closure['close'] ) );
			}
			return array();
		}

		$key = self::DAYS[ (int) $date->format( 'N' ) - 1 ];
		$day = isset( $this->settings['days'][ $key ] ) ? $this->settings['days'][ $key ] : array();
		if ( empty( $day ) || ! empty( $day['closed'] ) ) {
			return array();
		}

		$shifts = array();
		foreach ( array( '', '2' ) as $suffix ) {
			$open  = isset( $day[ 'open' . $suffix ] ) ? $day[ 'open' . $suffix ] : '';
			$close = isset( $day[ 'close' . $suffix ] ) ? $day[ 'close' . $suffix ] : '';
			if ( $open && $close && $close > $open ) {
				$shifts[] = array( 'open' => $open, 'close' => $close );
			}
		}
		return $shifts;
	}

	/**
	 * Concrete DateTime ranges for a date.
	 *
	 * @param DateTimeImmutable $date Any moment on the date.
	 * @return array[] List of array( DateTimeImmutable $start, DateTimeImmutable $end ).
	 */
	public function ranges_for( DateTimeImmutable $date ) {
		$ranges = array();
		$day    = $date->setTime( 0, 0 );
		foreach ( $this->shifts_for( $date ) as $shift ) {
			list( $oh, $om ) = array_map( 'intval', explode( ':', $shift['open'] ) );
			list( $ch, $cm ) = array_map( 'intval', explode( ':', $shift['close'] ) );
			$ranges[]        = array( $day->setTime( $oh, $om ), $day->setTime( $ch, $cm ) );
		}
		return $ranges;
	}

	/**
	 * Ranges for the next N days starting today, as Unix timestamps.
	 * Used by the front-end script so the badge stays correct behind page caches.
	 *
	 * @param int $days Number of days to include.
	 * @return int[][] List of array( start, end ).
	 */
	public function upcoming_ranges( $days = 8 ) {
		$out   = array();
		$today = $this->now()->setTime( 0, 0 );
		for ( $i = 0; $i < $days; $i++ ) {
			foreach ( $this->ranges_for( $today->modify( "+{$i} day" ) ) as $r ) {
				$out[] = array( $r[0]->getTimestamp(), $r[1]->getTimestamp() );
			}
		}
		return $out;
	}

	/**
	 * Current status.
	 *
	 * @param DateTimeImmutable|null $now Optional moment (for testing).
	 * @return array {
	 *     @type string                 $state       open | closing_soon | closed.
	 *     @type DateTimeImmutable|null $next_change When the state flips next.
	 * }
	 */
	public function status( ?DateTimeImmutable $now = null ) {
		$now  = $now ? $now : $this->now();
		$soon = max( 0, (int) $this->settings['closing_soon'] ) * MINUTE_IN_SECONDS;
		$ts   = $now->getTimestamp();

		foreach ( $this->ranges_for( $now ) as $r ) {
			if ( $ts >= $r[0]->getTimestamp() && $ts < $r[1]->getTimestamp() ) {
				$left = $r[1]->getTimestamp() - $ts;
				return array(
					'state'       => ( $left <= $soon ) ? 'closing_soon' : 'open',
					'next_change' => $r[1],
				);
			}
		}

		return array(
			'state'       => 'closed',
			'next_change' => $this->next_opening( $now ),
		);
	}

	/**
	 * Next opening moment within two weeks.
	 *
	 * @param DateTimeImmutable $now Reference moment.
	 * @return DateTimeImmutable|null
	 */
	public function next_opening( DateTimeImmutable $now ) {
		$day = $now->setTime( 0, 0 );
		for ( $i = 0; $i < 14; $i++ ) {
			foreach ( $this->ranges_for( $day->modify( "+{$i} day" ) ) as $r ) {
				if ( $r[0] > $now ) {
					return $r[0];
				}
			}
		}
		return null;
	}

	/**
	 * Upcoming closures and special-hours days, soonest first.
	 *
	 * @param int $limit Max rows.
	 * @return array[]
	 */
	public function upcoming_closures( $limit = 5 ) {
		$today = $this->now()->format( 'Y-m-d' );
		$rows  = array_filter(
			(array) $this->settings['closures'],
			static function ( $row ) use ( $today ) {
				return ! empty( $row['date'] ) && $row['date'] >= $today;
			}
		);
		usort(
			$rows,
			static function ( $a, $b ) {
				return strcmp( $a['date'], $b['date'] );
			}
		);
		return array_slice( $rows, 0, max( 1, (int) $limit ) );
	}
}
