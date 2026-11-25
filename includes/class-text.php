<?php
/**
 * Small sanitizing helpers shared by the settings classes.
 *
 * @package AmitShaw\FormSteps
 */

namespace AmitShaw\FormSteps;

defined( 'ABSPATH' ) || exit;

/**
 * Static helpers. No state.
 */
final class Text {

	/**
	 * One line of plain text, trimmed to a maximum length.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Maximum characters.
	 * @return string
	 */
	public static function line( $value, $max = 200 ) {
		$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		return self::cut( $value, $max );
	}

	/**
	 * Multi-line plain text, trimmed to a maximum length.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Maximum characters.
	 * @return string
	 */
	public static function lines( $value, $max = 2000 ) {
		$value = is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
		return self::cut( $value, $max );
	}

	/**
	 * One of the allowed values, or the fallback.
	 *
	 * @param mixed    $value    Raw value.
	 * @param string[] $allowed  Allowed values.
	 * @param string   $fallback Value used when $value is not allowed.
	 * @return string
	 */
	public static function choice( $value, array $allowed, $fallback ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * A hex color such as #1b447e, or '' when the value is not one.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function color( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		$color = '' !== $value ? sanitize_hex_color( $value ) : '';
		return is_string( $color ) ? strtolower( $color ) : '';
	}

	/**
	 * Whether a string is a valid Contact Form 7 field name.
	 *
	 * @param string $name Field name.
	 * @return bool
	 */
	public static function is_field_name( $name ) {
		return is_string( $name ) && 1 === preg_match( '/^[A-Za-z][-A-Za-z0-9_:.]*$/', $name );
	}

	/**
	 * Multibyte-safe truncation.
	 *
	 * @param string $value Text.
	 * @param int    $max   Maximum characters.
	 * @return string
	 */
	private static function cut( $value, $max ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max );
		}
		return substr( $value, 0, $max );
	}
}
