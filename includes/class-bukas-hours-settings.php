<?php
/**
 * Settings page (Settings → Bukas Hours) built on the Settings API.
 *
 * @package BukasHours
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers, sanitizes and renders the plugin settings.
 */
class Bukas_Hours_Settings {

	/**
	 * Schedule service (used for the live preview at the top of the page).
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
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Default settings. Mon–Fri 8–5 with a noon break, Sat half-day, Sun closed.
	 *
	 * @return array
	 */
	public static function defaults() {
		$weekday = array(
			'closed' => 0,
			'open'   => '08:00',
			'close'  => '12:00',
			'open2'  => '13:00',
			'close2' => '17:00',
		);
		return array(
			'business_name' => get_bloginfo( 'name' ),
			'business_type' => 'LocalBusiness',
			'address'       => '',
			'phone'         => '',
			'timezone'      => 'Asia/Manila',
			'open_text'     => __( 'Open now', 'bukas-hours' ),
			'soon_text'     => __( 'Closing soon', 'bukas-hours' ),
			'closed_text'   => __( 'Closed', 'bukas-hours' ),
			'closing_soon'  => 30,
			'schema'        => 1,
			'days'          => array(
				'mon' => $weekday,
				'tue' => $weekday,
				'wed' => $weekday,
				'thu' => $weekday,
				'fri' => $weekday,
				'sat' => array(
					'closed' => 0,
					'open'   => '08:00',
					'close'  => '12:00',
					'open2'  => '',
					'close2' => '',
				),
				'sun' => array(
					'closed' => 1,
					'open'   => '',
					'close'  => '',
					'open2'  => '',
					'close2' => '',
				),
			),
			'closures'      => array(),
		);
	}

	/**
	 * Saved settings merged over defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( BUKAS_HOURS_OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * Supported schema.org types (a short, useful subset of LocalBusiness).
	 *
	 * @return string[]
	 */
	public static function business_types() {
		return array(
			'LocalBusiness'   => __( 'Local business (general)', 'bukas-hours' ),
			'Store'           => __( 'Store / sari-sari / retail', 'bukas-hours' ),
			'Restaurant'      => __( 'Restaurant / carinderia', 'bukas-hours' ),
			'CafeOrCoffeeShop' => __( 'Café / coffee shop', 'bukas-hours' ),
			'Pharmacy'        => __( 'Pharmacy', 'bukas-hours' ),
			'MedicalClinic'   => __( 'Clinic', 'bukas-hours' ),
			'AutoRepair'      => __( 'Auto / motor repair shop', 'bukas-hours' ),
			'GovernmentOffice' => __( 'Government office / LGU', 'bukas-hours' ),
		);
	}

	/**
	 * Register the option with a sanitize callback.
	 */
	public function register() {
		register_setting(
			'bukas_hours',
			BUKAS_HOURS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Validate a "HH:MM" 24h string.
	 *
	 * @param mixed $value Raw value.
	 * @return string Valid time or empty string.
	 */
	private static function clean_time( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : '';
	}

	/**
	 * Sanitize the whole settings array.
	 *
	 * @param mixed $input Raw POSTed value.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? wp_unslash( $input ) : array();
		$d     = self::defaults();
		$out   = array();

		$out['business_name'] = sanitize_text_field( isset( $input['business_name'] ) ? $input['business_name'] : $d['business_name'] );
		$type                 = isset( $input['business_type'] ) ? $input['business_type'] : '';
		$out['business_type'] = array_key_exists( $type, self::business_types() ) ? $type : 'LocalBusiness';
		$out['address']       = sanitize_text_field( isset( $input['address'] ) ? $input['address'] : '' );
		$out['phone']         = sanitize_text_field( isset( $input['phone'] ) ? $input['phone'] : '' );

		$tz              = isset( $input['timezone'] ) ? $input['timezone'] : '';
		$out['timezone'] = in_array( $tz, timezone_identifiers_list(), true ) ? $tz : 'Asia/Manila';

		foreach ( array( 'open_text', 'soon_text', 'closed_text' ) as $key ) {
			$val         = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
			$out[ $key ] = '' !== $val ? $val : $d[ $key ];
		}

		$out['closing_soon'] = min( 180, absint( isset( $input['closing_soon'] ) ? $input['closing_soon'] : 30 ) );
		$out['schema']       = empty( $input['schema'] ) ? 0 : 1;

		$out['days'] = array();
		foreach ( Bukas_Hours_Schedule::DAYS as $day ) {
			$raw                 = isset( $input['days'][ $day ] ) ? (array) $input['days'][ $day ] : array();
			$out['days'][ $day ] = array(
				'closed' => empty( $raw['closed'] ) ? 0 : 1,
				'open'   => self::clean_time( isset( $raw['open'] ) ? $raw['open'] : '' ),
				'close'  => self::clean_time( isset( $raw['close'] ) ? $raw['close'] : '' ),
				'open2'  => self::clean_time( isset( $raw['open2'] ) ? $raw['open2'] : '' ),
				'close2' => self::clean_time( isset( $raw['close2'] ) ? $raw['close2'] : '' ),
			);
		}

		$out['closures'] = array();
		$seen            = array();
		$rows            = isset( $input['closures'] ) && is_array( $input['closures'] ) ? $input['closures'] : array();
		foreach ( $rows as $row ) {
			$date = isset( $row['date'] ) ? sanitize_text_field( $row['date'] ) : '';
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || isset( $seen[ $date ] ) ) {
				continue; // Skip blanks, malformed dates and duplicates.
			}
			$seen[ $date ]     = true;
			$out['closures'][] = array(
				'date'  => $date,
				'label' => sanitize_text_field( isset( $row['label'] ) ? $row['label'] : '' ),
				'open'  => self::clean_time( isset( $row['open'] ) ? $row['open'] : '' ),
				'close' => self::clean_time( isset( $row['close'] ) ? $row['close'] : '' ),
			);
		}
		usort(
			$out['closures'],
			static function ( $a, $b ) {
				return strcmp( $a['date'], $b['date'] );
			}
		);

		return $out;
	}

	/**
	 * Add the page under Settings.
	 */
	public function menu() {
		add_options_page(
			__( 'Bukas Hours', 'bukas-hours' ),
			__( 'Bukas Hours', 'bukas-hours' ),
			'manage_options',
			'bukas-hours',
			array( $this, 'page' )
		);
	}

	/**
	 * Admin assets, only on our page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function assets( $hook ) {
		if ( 'settings_page_bukas-hours' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'bukas-hours-admin', BUKAS_HOURS_URL . 'assets/css/admin.css', array(), BUKAS_HOURS_VERSION );
		wp_enqueue_script( 'bukas-hours-admin', BUKAS_HOURS_URL . 'assets/js/admin.js', array( 'wp-i18n' ), BUKAS_HOURS_VERSION, true );
		wp_set_script_translations( 'bukas-hours-admin', 'bukas-hours', BUKAS_HOURS_DIR . 'languages' );
	}

	/**
	 * Render one closure row. Used for saved rows and as the JS template.
	 *
	 * @param int|string $i   Row index (or "__i__" placeholder).
	 * @param array      $row Row values.
	 */
	private function closure_row( $i, array $row ) {
		$name = BUKAS_HOURS_OPTION . '[closures][' . $i . ']';
		?>
		<tr class="bukas-closure-row">
			<td><input type="date" name="<?php echo esc_attr( $name ); ?>[date]" value="<?php echo esc_attr( $row['date'] ); ?>" aria-label="<?php esc_attr_e( 'Date', 'bukas-hours' ); ?>"></td>
			<td><input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( $row['label'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Bonifacio Day', 'bukas-hours' ); ?>" aria-label="<?php esc_attr_e( 'Label', 'bukas-hours' ); ?>"></td>
			<td><input type="time" name="<?php echo esc_attr( $name ); ?>[open]" value="<?php echo esc_attr( $row['open'] ); ?>" aria-label="<?php esc_attr_e( 'Special opening time', 'bukas-hours' ); ?>"></td>
			<td><input type="time" name="<?php echo esc_attr( $name ); ?>[close]" value="<?php echo esc_attr( $row['close'] ); ?>" aria-label="<?php esc_attr_e( 'Special closing time', 'bukas-hours' ); ?>"></td>
			<td><button type="button" class="button-link button-link-delete bukas-remove-row"><?php esc_html_e( 'Remove', 'bukas-hours' ); ?></button></td>
		</tr>
		<?php
	}

	/**
	 * Render the settings page.
	 */
	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s      = self::get();
		$opt    = BUKAS_HOURS_OPTION;
		$render = new Bukas_Hours_Render( $this->schedule );
		$empty  = array(
			'date'  => '',
			'label' => '',
			'open'  => '',
			'close' => '',
		);
		?>
		<div class="wrap bukas-admin">
			<h1><?php esc_html_e( 'Bukas Hours', 'bukas-hours' ); ?></h1>

			<div class="bukas-admin__preview">
				<h2><?php esc_html_e( 'Live preview', 'bukas-hours' ); ?></h2>
				<?php echo $render->status_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in renderer. ?>
				<p class="description">
					<?php esc_html_e( 'Shortcodes:', 'bukas-hours' ); ?>
					<code>[bukas_status]</code> <code>[bukas_hours]</code> <code>[bukas_closures limit="5"]</code>
					<?php esc_html_e( 'or add the "Bukas Hours" block.', 'bukas-hours' ); ?>
				</p>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'bukas_hours' ); // Outputs the nonce and option_page fields. ?>

				<h2><?php esc_html_e( 'Business', 'bukas-hours' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bukas-name"><?php esc_html_e( 'Business name', 'bukas-hours' ); ?></label></th>
						<td><input id="bukas-name" class="regular-text" type="text" name="<?php echo esc_attr( $opt ); ?>[business_name]" value="<?php echo esc_attr( $s['business_name'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="bukas-type"><?php esc_html_e( 'Business type', 'bukas-hours' ); ?></label></th>
						<td>
							<select id="bukas-type" name="<?php echo esc_attr( $opt ); ?>[business_type]">
								<?php foreach ( self::business_types() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['business_type'], $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bukas-address"><?php esc_html_e( 'Address', 'bukas-hours' ); ?></label></th>
						<td><input id="bukas-address" class="large-text" type="text" name="<?php echo esc_attr( $opt ); ?>[address]" value="<?php echo esc_attr( $s['address'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="bukas-phone"><?php esc_html_e( 'Phone', 'bukas-hours' ); ?></label></th>
						<td><input id="bukas-phone" type="text" name="<?php echo esc_attr( $opt ); ?>[phone]" value="<?php echo esc_attr( $s['phone'] ); ?>" placeholder="+63 9xx xxx xxxx"></td>
					</tr>
					<tr>
						<th scope="row"><label for="bukas-tz"><?php esc_html_e( 'Timezone', 'bukas-hours' ); ?></label></th>
						<td>
							<select id="bukas-tz" name="<?php echo esc_attr( $opt ); ?>[timezone]">
								<?php foreach ( timezone_identifiers_list() as $tz ) : ?>
									<option value="<?php echo esc_attr( $tz ); ?>" <?php selected( $s['timezone'], $tz ); ?>><?php echo esc_html( $tz ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Weekly hours', 'bukas-hours' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Use the second shift for a noon break (e.g. 8:00–12:00 and 13:00–17:00). Leave it blank for continuous hours.', 'bukas-hours' ); ?></p>
				<table class="widefat striped bukas-week">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Day', 'bukas-hours' ); ?></th>
							<th><?php esc_html_e( 'Closed', 'bukas-hours' ); ?></th>
							<th><?php esc_html_e( 'Opens', 'bukas-hours' ); ?></th>
							<th><?php esc_html_e( 'Closes', 'bukas-hours' ); ?></th>
							<th><?php esc_html_e( 'Reopens', 'bukas-hours' ); ?></th>
							<th><?php esc_html_e( 'Closes', 'bukas-hours' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php
					foreach ( Bukas_Hours_Render::day_labels() as $key => $label ) :
						$d    = $s['days'][ $key ];
						$base = $opt . '[days][' . $key . ']';
						?>
						<tr class="<?php echo $d['closed'] ? 'is-closed' : ''; ?>">
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td><input type="checkbox" class="bukas-closed-toggle" name="<?php echo esc_attr( $base ); ?>[closed]" value="1" <?php checked( $d['closed'], 1 ); ?> aria-label="<?php /* translators: %s: day name. */ echo esc_attr( sprintf( __( 'Closed on %s', 'bukas-hours' ), $label ) ); ?>"></td>
							<?php foreach ( array( 'open', 'close', 'open2', 'close2' ) as $field ) : ?>
								<td><input type="time" name="<?php echo esc_attr( $base . '[' . $field . ']' ); ?>" value="<?php echo esc_attr( $d[ $field ] ); ?>" aria-label="<?php echo esc_attr( $label . ' ' . $field ); ?>"></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="bukas-copy-monday"><?php esc_html_e( 'Copy Monday to Tue–Fri', 'bukas-hours' ); ?></button></p>

				<h2><?php esc_html_e( 'Holidays & special hours', 'bukas-hours' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Leave times blank to close all day. Fill them in for shortened hours on that date.', 'bukas-hours' ); ?></p>
				<table class="widefat bukas-closures-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'bukas-hours' ); ?></th>
							<th><?php esc_html_e( 'Label', 'bukas-hours' ); ?></th>
							<th><?php esc_html_e( 'Opens', 'bukas-hours' ); ?></th>
							<th><?php esc_html_e( 'Closes', 'bukas-hours' ); ?></th>
							<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'bukas-hours' ); ?></span></th>
						</tr>
					</thead>
					<tbody id="bukas-closures-body">
						<?php
						foreach ( $s['closures'] as $i => $row ) {
							$this->closure_row( (int) $i, wp_parse_args( $row, $empty ) );
						}
						?>
					</tbody>
				</table>
				<template id="bukas-closure-template"><?php $this->closure_row( '__i__', $empty ); ?></template>
				<p class="bukas-closure-actions">
					<button type="button" class="button" id="bukas-add-closure"><?php esc_html_e( 'Add date', 'bukas-hours' ); ?></button>
					<label for="bukas-ph-year" class="screen-reader-text"><?php esc_html_e( 'Year', 'bukas-hours' ); ?></label>
					<input type="number" id="bukas-ph-year" min="2024" max="2100" value="<?php echo esc_attr( $this->schedule->now()->format( 'Y' ) ); ?>">
					<button type="button" class="button" id="bukas-add-ph"><?php esc_html_e( 'Add PH fixed-date regular holidays', 'bukas-hours' ); ?></button>
					<span class="description"><?php esc_html_e( 'Always check the year\'s official proclamation; movable holidays (Holy Week, Eid) are not added.', 'bukas-hours' ); ?></span>
				</p>

				<h2><?php esc_html_e( 'Badge & SEO', 'bukas-hours' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Badge text', 'bukas-hours' ); ?></th>
						<td class="bukas-texts">
							<label><?php esc_html_e( 'Open', 'bukas-hours' ); ?> <input type="text" name="<?php echo esc_attr( $opt ); ?>[open_text]" value="<?php echo esc_attr( $s['open_text'] ); ?>"></label>
							<label><?php esc_html_e( 'Closing soon', 'bukas-hours' ); ?> <input type="text" name="<?php echo esc_attr( $opt ); ?>[soon_text]" value="<?php echo esc_attr( $s['soon_text'] ); ?>"></label>
							<label><?php esc_html_e( 'Closed', 'bukas-hours' ); ?> <input type="text" name="<?php echo esc_attr( $opt ); ?>[closed_text]" value="<?php echo esc_attr( $s['closed_text'] ); ?>"></label>
							<p class="description"><?php esc_html_e( 'Tip: use "Bukas na!" and "Sarado" for a Filipino-language site.', 'bukas-hours' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bukas-soon"><?php esc_html_e( '"Closing soon" window', 'bukas-hours' ); ?></label></th>
						<td><input id="bukas-soon" type="number" min="0" max="180" name="<?php echo esc_attr( $opt ); ?>[closing_soon]" value="<?php echo esc_attr( $s['closing_soon'] ); ?>"> <?php esc_html_e( 'minutes', 'bukas-hours' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Structured data', 'bukas-hours' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[schema]" value="1" <?php checked( $s['schema'], 1 ); ?>> <?php esc_html_e( 'Output LocalBusiness JSON-LD with opening hours on the front page', 'bukas-hours' ); ?></label></td>
					</tr>
				</table>

				<?php submit_button( __( 'Save hours', 'bukas-hours' ) ); ?>
			</form>
		</div>
		<?php
	}
}
