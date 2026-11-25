<?php
/**
 * Every outside address the plugin links to, in one place.
 *
 * @package AmitShaw\FormSteps
 */

namespace AmitShaw\FormSteps;

defined( 'ABSPATH' ) || exit;

/**
 * To publish a page on github.com/Amitoshi, add its key to LIVE. Until then the
 * plugin links to its own Help tab instead, so no link ever leads to a missing page.
 */
final class Links {

	const SITE = 'https://github.com/Amitoshi';

	/**
	 * Keys of pages that exist on the website. Empty until the pages are built.
	 *
	 * @var string[]
	 */
	const LIVE = array();

	/**
	 * Website paths, relative to SITE.
	 *
	 * @var array<string,string>
	 */
	const PATHS = array(
		'demo'          => 'form-steps/demo/',
		'docs'          => 'form-steps/docs/',
		'confirm'       => 'form-steps/docs/confirm-screen/',
		'confirm_style' => 'form-steps/docs/confirm-screen/#style',
		'field_labels'  => 'form-steps/docs/confirm-screen/#labels',
		'buttons'       => 'form-steps/docs/buttons/',
		'design'        => 'form-steps/docs/buttons/#design',
		'styles'        => 'form-steps/docs/css-classes/#styles',
		'progress'      => 'form-steps/docs/steps/#progress',
		'steps'         => 'form-steps/docs/steps/',
		'after_send'    => 'form-steps/docs/after-sending/',
		'thanks'        => 'form-steps/docs/after-sending/#thank-you-screen',
		'classes'       => 'form-steps/docs/css-classes/',
		'conditional'   => 'conditional-steps/',
	);

	const SUPPORT = 'https://wordpress.org/support/plugin/form-steps-for-contact-form-7/';
	const REVIEW  = 'https://wordpress.org/support/plugin/form-steps-for-contact-form-7/reviews/#new-post';

	/**
	 * Whether the website page for a key exists.
	 *
	 * @param string $key Page key.
	 * @return bool
	 */
	public static function is_live( $key ) {
		return in_array( $key, self::LIVE, true ) && isset( self::PATHS[ $key ] );
	}

	/**
	 * Website address of a page, or '' when the page is not live yet.
	 *
	 * @param string $key Page key.
	 * @return string
	 */
	public static function online( $key ) {
		return self::is_live( $key ) ? self::SITE . self::PATHS[ $key ] : '';
	}

	/**
	 * Best help address for a topic: the website page when it exists, otherwise
	 * the matching section of the plugin's own Help tab.
	 *
	 * @param string $topic Topic key.
	 * @return string
	 */
	public static function help( $topic ) {
		$online = self::online( $topic );
		return '' !== $online ? $online : Admin_Page::url( 'help' ) . '#svfs-help-' . sanitize_key( $topic );
	}
}
