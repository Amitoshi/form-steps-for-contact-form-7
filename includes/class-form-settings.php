<?php
/**
 * Per-form settings, stored as post meta on the contact form.
 *
 * @package AmitShaw\FormSteps
 */

namespace AmitShaw\FormSteps;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, sanitizes, migrates and resolves the settings of one form.
 */
final class Form_Settings {

	const META_KEY = '_svfs_form';
	const VERSION  = 1;

	const CONFIRM_STYLES   = array( 'inplace', 'table' );
	const AFTER_SEND       = array( 'message', 'screen', 'redirect' );
	const MAX_FIELD_LABELS = 200;

	/**
	 * Settings of the unsaved example form shown on an example preview page.
	 *
	 * @var array|null
	 */
	private static $preview = null;

	/**
	 * Sets the settings used for the unsaved form (ID 0) on an example preview page.
	 *
	 * @param array $settings Settings.
	 */
	public static function set_preview( array $settings ) {
		self::$preview = self::sanitize( $settings );
	}

	/**
	 * Default stored values. Empty strings mean "use the site-wide default".
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'v'               => self::VERSION,
			'confirm'         => false,
			'confirm_style'   => 'inplace',
			'confirm_heading' => '',
			'confirm_message' => '',
			'labels'          => array_fill_keys( Global_Settings::LABEL_KEYS, '' ),
			'empty_values'    => '',
			'progress'        => '',
			'field_labels'    => array(),
			'after_send'      => 'message',
			'thanks_html'     => '',
			'redirect_url'    => '',
			'design'          => Design::form_defaults(),
		);
	}

	/**
	 * Stored settings for a form, migrated and merged with defaults.
	 *
	 * @param int $form_id Contact form post ID.
	 * @return array
	 */
	public static function get( $form_id ) {
		if ( 0 === (int) $form_id && null !== self::$preview ) {
			return self::$preview;
		}
		$stored = get_post_meta( (int) $form_id, self::META_KEY, true );
		return self::migrate( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Sanitizes and saves settings for a form.
	 *
	 * @param int   $form_id Contact form post ID.
	 * @param array $raw     Unsanitized input.
	 * @return array Saved settings.
	 */
	public static function save( $form_id, $raw ) {
		$clean = self::sanitize( is_array( $raw ) ? $raw : array() );
		update_post_meta( (int) $form_id, self::META_KEY, $clean );
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

		$clean['confirm']         = ! empty( $raw['confirm'] );
		$clean['confirm_style']   = Text::choice( $raw['confirm_style'] ?? '', self::CONFIRM_STYLES, 'inplace' );
		$clean['confirm_heading'] = Text::line( $raw['confirm_heading'] ?? '', 120 );
		$clean['confirm_message'] = Text::lines( $raw['confirm_message'] ?? '', 1000 );

		$labels = isset( $raw['labels'] ) && is_array( $raw['labels'] ) ? $raw['labels'] : array();
		foreach ( Global_Settings::LABEL_KEYS as $key ) {
			$clean['labels'][ $key ] = isset( $labels[ $key ] ) ? Text::line( $labels[ $key ], 60 ) : '';
		}

		$clean['empty_values'] = Text::choice( $raw['empty_values'] ?? '', Global_Settings::EMPTY_VALUES, '' );
		$clean['progress']     = Text::choice( $raw['progress'] ?? '', Global_Settings::PROGRESS_STYLES, '' );

		$field_labels = isset( $raw['field_labels'] ) && is_array( $raw['field_labels'] ) ? $raw['field_labels'] : array();
		foreach ( $field_labels as $name => $label ) {
			if ( count( $clean['field_labels'] ) >= self::MAX_FIELD_LABELS ) {
				break;
			}
			$label = Text::line( $label, 200 );
			if ( Text::is_field_name( $name ) && '' !== $label ) {
				$clean['field_labels'][ $name ] = $label;
			}
		}

		$clean['after_send']   = Text::choice( $raw['after_send'] ?? '', self::AFTER_SEND, 'message' );
		$clean['thanks_html']  = isset( $raw['thanks_html'] ) && is_string( $raw['thanks_html'] ) ? wp_kses_post( $raw['thanks_html'] ) : '';
		$clean['redirect_url'] = isset( $raw['redirect_url'] ) && is_string( $raw['redirect_url'] )
			? esc_url_raw( trim( $raw['redirect_url'] ), array( 'http', 'https' ) )
			: '';

		$clean['design'] = Design::sanitize( $raw['design'] ?? array(), true );

		if ( 'redirect' === $clean['after_send'] && '' === $clean['redirect_url'] ) {
			$clean['after_send'] = 'message';
		}

		return $clean;
	}

	/**
	 * Whether the confirm screen is on for a form: by switch, or by a [confirm] tag
	 * left over from "Contact Form 7 add confirm" and similar plugins.
	 *
	 * @param \WPCF7_ContactForm $form Contact form.
	 * @return bool
	 */
	public static function confirm_enabled( \WPCF7_ContactForm $form ) {
		$settings = self::get( $form->id() );
		if ( $settings['confirm'] ) {
			return true;
		}
		return array() !== $form->scan_form_tags( array( 'type' => 'confirm' ) );
	}

	/**
	 * Whether the form is split into steps with [step] tags.
	 *
	 * @param \WPCF7_ContactForm $form Contact form.
	 * @return bool
	 */
	public static function steps_enabled( \WPCF7_ContactForm $form ) {
		return array() !== $form->scan_form_tags( array( 'type' => 'step' ) );
	}

	/**
	 * Final settings for the front end: form values, then site-wide values,
	 * then translated defaults. Call only after init.
	 *
	 * @param \WPCF7_ContactForm $form Contact form.
	 * @return array
	 */
	public static function resolved( \WPCF7_ContactForm $form ) {
		$settings = self::get( $form->id() );
		$global   = Global_Settings::get();
		$defaults = Global_Settings::default_labels();

		foreach ( Global_Settings::LABEL_KEYS as $key ) {
			if ( '' !== $settings['labels'][ $key ] ) {
				continue;
			}
			// An empty Send label means "keep the text of the form's own submit button".
			$fallback                   = 'send' === $key ? '' : $defaults[ $key ];
			$settings['labels'][ $key ] = '' !== $global['labels'][ $key ] ? $global['labels'][ $key ] : $fallback;
		}

		if ( '' === $settings['empty_values'] ) {
			$settings['empty_values'] = $global['empty_values'];
		}
		if ( '' === $settings['progress'] ) {
			$settings['progress'] = $global['progress'];
		}
		$settings['scroll']  = $global['scroll'];
		$settings['design']  = Design::resolve( $settings['design'], $global['design'] );
		$settings['confirm'] = self::confirm_enabled( $form );
		$settings['steps']   = self::steps_enabled( $form );

		return $settings;
	}

	/**
	 * Brings stored data up to the current version and fills missing keys.
	 *
	 * @param array $stored Stored array.
	 * @return array
	 */
	private static function migrate( array $stored ) {
		// Version 1 is the first schema. Future migrations go here, keyed on $stored['v'].
		$field_labels = isset( $stored['field_labels'] ) && is_array( $stored['field_labels'] ) ? $stored['field_labels'] : array();
		$merged       = array_replace_recursive( self::defaults(), $stored );
		// field_labels is a free map, so replace it whole instead of merging key by key.
		$merged['field_labels'] = $field_labels;
		return self::sanitize( $merged );
	}
}
