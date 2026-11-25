<?php
/**
 * Design options: built-in Form styles, button style, position, visibility and colors.
 *
 * @package AmitShaw\FormSteps
 */

namespace AmitShaw\FormSteps;

defined( 'ABSPATH' ) || exit;

/**
 * Design settings are stored in the form settings and the site-wide settings
 * under the key "design". In a form, '' means "use the site-wide value".
 * Everything is applied through classes on the form and CSS custom properties
 * in its style attribute. No user-written CSS is stored or printed.
 */
final class Design {

	const SKINS  = array( 'none', 'navy', 'teal', 'warm', 'sky', 'dark' );
	const STYLES = array( 'theme', 'filled', 'outline', 'rounded' );
	const ALIGNS = array( 'left', 'center', 'right', 'full', 'split' );
	const ORDERS = array( 'back_first', 'send_first' );
	const SHOW   = array( 'show', 'hide' );
	const COLORS = array( 'accent', 'accent_text', 'accent_hover' );

	const SKINS_HANDLE = 'svfs-skins';

	/**
	 * Site-wide defaults.
	 *
	 * @return array
	 */
	public static function site_defaults() {
		return array(
			'skin'         => 'none',
			'style'        => 'theme',
			'align'        => 'left',
			'order'        => 'back_first',
			'back'         => 'show',
			'prev'         => 'show',
			'accent'       => '',
			'accent_text'  => '',
			'accent_hover' => '',
		);
	}

	/**
	 * Per-form defaults: every value inherits from the site.
	 *
	 * @return array
	 */
	public static function form_defaults() {
		return array_fill_keys( array_keys( self::site_defaults() ), '' );
	}

	/**
	 * Names of the built-in Form styles.
	 *
	 * @return array<string,string>
	 */
	public static function skin_names() {
		return array(
			'none' => __( 'None (your theme\'s look)', 'form-steps-for-contact-form-7' ),
			'navy' => __( 'Navy card', 'form-steps-for-contact-form-7' ),
			'teal' => __( 'Teal card', 'form-steps-for-contact-form-7' ),
			'warm' => __( 'Warm card', 'form-steps-for-contact-form-7' ),
			'sky'  => __( 'Sky card', 'form-steps-for-contact-form-7' ),
			'dark' => __( 'Dark card', 'form-steps-for-contact-form-7' ),
		);
	}

	/**
	 * Cleans design values.
	 *
	 * @param mixed $raw     Unsanitized input.
	 * @param bool  $is_form True for a form (allows '' = inherit), false for the site.
	 * @return array
	 */
	public static function sanitize( $raw, $is_form ) {
		$raw   = is_array( $raw ) ? $raw : array();
		$base  = self::site_defaults();
		$clean = array();
		$lists = array(
			'skin'  => self::SKINS,
			'style' => self::STYLES,
			'align' => self::ALIGNS,
			'order' => self::ORDERS,
			'back'  => self::SHOW,
			'prev'  => self::SHOW,
		);
		foreach ( $lists as $key => $allowed ) {
			$clean[ $key ] = Text::choice( $raw[ $key ] ?? '', $is_form ? array_merge( array( '' ), $allowed ) : $allowed, $is_form ? '' : $base[ $key ] );
		}
		foreach ( self::COLORS as $key ) {
			$clean[ $key ] = Text::color( $raw[ $key ] ?? '' );
		}
		return $clean;
	}

	/**
	 * Form values with site-wide values filled in.
	 *
	 * @param array $form Form design values.
	 * @param array $site Site design values.
	 * @return array
	 */
	public static function resolve( array $form, array $site ) {
		$resolved = array();
		foreach ( self::site_defaults() as $key => $default ) {
			if ( isset( $form[ $key ] ) && '' !== $form[ $key ] ) {
				$resolved[ $key ] = $form[ $key ];
			} elseif ( isset( $site[ $key ] ) && '' !== $site[ $key ] ) {
				$resolved[ $key ] = $site[ $key ];
			} else {
				$resolved[ $key ] = $default;
			}
		}
		return $resolved;
	}

	/**
	 * Classes for the form element.
	 *
	 * @param array $design Resolved design.
	 * @return string[]
	 */
	public static function classes( array $design ) {
		$classes = array(
			'svfs-btn-' . $design['style'],
			'svfs-align-' . $design['align'],
		);
		if ( 'none' !== $design['skin'] ) {
			$classes[] = 'svfs-skin';
			$classes[] = 'svfs-skin-' . $design['skin'];
		}
		if ( 'send_first' === $design['order'] ) {
			$classes[] = 'svfs-order-send-first';
		}
		if ( 'hide' === $design['back'] ) {
			$classes[] = 'svfs-no-back';
		}
		if ( 'hide' === $design['prev'] ) {
			$classes[] = 'svfs-no-prev';
		}
		return $classes;
	}

	/**
	 * Value for the form's style attribute: the chosen colors as CSS custom
	 * properties. Only validated hex colors are used, so nothing else can be added.
	 *
	 * @param array $design Resolved design.
	 * @return string
	 */
	public static function style_attr( array $design ) {
		$names = array(
			'accent'       => '--svfs-accent',
			'accent_text'  => '--svfs-accent-text',
			'accent_hover' => '--svfs-accent-hover',
		);
		$vars  = array();
		foreach ( $names as $key => $property ) {
			$color = Text::color( $design[ $key ] ?? '' );
			if ( '' !== $color ) {
				$vars[] = $property . ':' . $color;
			}
		}
		return implode( ';', $vars );
	}

	/**
	 * Registers the built-in Form styles stylesheet.
	 */
	public static function register_skins() {
		if ( ! wp_style_is( self::SKINS_HANDLE, 'registered' ) ) {
			wp_register_style( self::SKINS_HANDLE, Plugin::asset_url( 'css/skins', 'css' ), array(), SVFS_VERSION );
		}
	}
}
