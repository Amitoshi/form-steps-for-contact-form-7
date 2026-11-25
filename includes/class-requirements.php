<?php
/**
 * Environment checks: WordPress, PHP and Contact Form 7 versions.
 *
 * @package AmitShaw\FormSteps
 */

namespace AmitShaw\FormSteps;

defined( 'ABSPATH' ) || exit;

/**
 * Checks that the site can run the plugin and explains clearly when it cannot.
 */
final class Requirements {

	const MIN_WP  = '6.2';
	const MIN_PHP = '7.4';
	const MIN_CF7 = '5.7';

	/**
	 * Whether every requirement is met.
	 *
	 * @return bool
	 */
	public static function met() {
		return array() === self::failed_checks();
	}

	/**
	 * Codes of failed checks. Does not translate anything, so it is safe to call
	 * before the init hook (WordPress 6.7+ warns about early translation calls).
	 *
	 * @return string[] Any of: php, wp, cf7_missing, cf7_old.
	 */
	public static function failed_checks() {
		$failed = array();
		if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) ) {
			$failed[] = 'php';
		}
		if ( version_compare( get_bloginfo( 'version' ), self::MIN_WP, '<' ) ) {
			$failed[] = 'wp';
		}
		if ( ! defined( 'WPCF7_VERSION' ) || ! class_exists( 'WPCF7_ContactForm' ) ) {
			$failed[] = 'cf7_missing';
		} elseif ( version_compare( WPCF7_VERSION, self::MIN_CF7, '<' ) ) {
			$failed[] = 'cf7_old';
		}
		return $failed;
	}

	/**
	 * Human-readable list of unmet requirements.
	 *
	 * @return string[]
	 */
	public static function problems() {
		$problems = array();
		foreach ( self::failed_checks() as $code ) {
			switch ( $code ) {
				case 'php':
					$problems[] = sprintf(
						/* translators: 1: required PHP version, 2: current PHP version. */
						__( 'PHP %1$s or newer is required. This site runs PHP %2$s.', 'form-steps-for-contact-form-7' ),
						self::MIN_PHP,
						PHP_VERSION
					);
					break;
				case 'wp':
					$problems[] = sprintf(
						/* translators: %s: required WordPress version. */
						__( 'WordPress %s or newer is required.', 'form-steps-for-contact-form-7' ),
						self::MIN_WP
					);
					break;
				case 'cf7_missing':
					$problems[] = __( 'Contact Form 7 must be installed and active.', 'form-steps-for-contact-form-7' );
					break;
				case 'cf7_old':
					$problems[] = sprintf(
						/* translators: 1: required Contact Form 7 version, 2: installed version. */
						__( 'Contact Form 7 %1$s or newer is required. Version %2$s is installed.', 'form-steps-for-contact-form-7' ),
						self::MIN_CF7,
						WPCF7_VERSION
					);
					break;
			}
		}
		return $problems;
	}

	/**
	 * Admin notice shown to users who can fix the problem.
	 */
	public static function render_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$problems = self::problems();
		if ( array() === $problems ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>';
		esc_html_e( 'Form Steps for Contact Form 7 is paused.', 'form-steps-for-contact-form-7' );
		echo '</strong></p><ul style="list-style:disc;padding-left:1.5em">';
		foreach ( $problems as $problem ) {
			echo '<li>' . esc_html( $problem ) . '</li>';
		}
		echo '</ul></div>';
	}
}
