<?php
/**
 * Site-wide defaults used by every form unless the form overrides them.
 *
 * @package AmitShaw\FormSteps
 */

namespace AmitShaw\FormSteps;

defined( 'ABSPATH' ) || exit;

/**
 * Stored in the option "svfs_settings" as one versioned array.
 */
final class Global_Settings {

	const OPTION  = 'svfs_settings';
	const VERSION = 1;

	/**
	 * Allowed values for choice settings.
	 */
	const PROGRESS_STYLES = array( 'numbers', 'bar', 'none' );
	const EMPTY_VALUES    = array( 'dash', 'hide' );
	const LABEL_KEYS      = array( 'review', 'back', 'send', 'next', 'prev' );

	/**
	 * Request cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default stored values. Empty label strings mean "use the translated default".
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'v'            => self::VERSION,
			'labels'       => array_fill_keys( self::LABEL_KEYS, '' ),
			'progress'     => 'numbers',
			'empty_values' => 'dash',
			'scroll'       => true,
			'design'       => Design::site_defaults(),
		);
	}

	/**
	 * Translated default button texts. Call only after init.
	 *
	 * @return array<string,string>
	 */
	public static function default_labels() {
		return array(
			'review' => __( 'Review', 'form-steps-for-contact-form-7' ),
			'back'   => __( 'Back', 'form-steps-for-contact-form-7' ),
			'send'   => __( 'Send', 'form-steps-for-contact-form-7' ),
			'next'   => __( 'Next', 'form-steps-for-contact-form-7' ),
			'prev'   => __( 'Previous', 'form-steps-for-contact-form-7' ),
		);
	}

	/**
	 * Stored settings merged with defaults and migrated to the current version.
	 *
	 * @return array
	 */
	public static function get() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = self::migrate( is_array( $stored ) ? $stored : array() );
		}
		return self::$cache;
	}

	/**
	 * Sanitizes and saves settings.
	 *
	 * @param array $raw Unsanitized input.
	 * @return array Saved settings.
	 */
	public static function save( $raw ) {
		$clean = self::sanitize( is_array( $raw ) ? $raw : array() );
		update_option( self::OPTION, $clean, true );
		self::$cache = null;
		return $clean;
	}

	/**
	 * Cleans every field. Unknown keys are dropped.
	 *
	 * @param array $raw Unsanitized input.
	 * @return array
	 */
	public static function sanitize( array $raw ) {
		$clean = self::defaults();

		$labels = isset( $raw['labels'] ) && is_array( $raw['labels'] ) ? $raw['labels'] : array();
		foreach ( self::LABEL_KEYS as $key ) {
			$clean['labels'][ $key ] = isset( $labels[ $key ] ) ? Text::line( $labels[ $key ], 60 ) : '';
		}

		$clean['progress']     = Text::choice( $raw['progress'] ?? '', self::PROGRESS_STYLES, 'numbers' );
		$clean['empty_values'] = Text::choice( $raw['empty_values'] ?? '', self::EMPTY_VALUES, 'dash' );
		$clean['scroll']       = ! empty( $raw['scroll'] );
		$clean['design']       = Design::sanitize( $raw['design'] ?? array(), false );

		return $clean;
	}

	/**
	 * Brings stored data up to the current version and fills missing keys.
	 *
	 * @param array $stored Stored array.
	 * @return array
	 */
	private static function migrate( array $stored ) {
		// Version 1 is the first schema. Future migrations go here, keyed on $stored['v'].
		return self::sanitize( array_replace_recursive( self::defaults(), $stored ) );
	}
}
