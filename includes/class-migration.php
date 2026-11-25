<?php
/**
 * Detects other confirm-screen plugins and offers a one-click switch.
 *
 * @package AmitShaw\FormSteps
 */

namespace AmitShaw\FormSteps;

defined( 'ABSPATH' ) || exit;

/**
 * Two confirm plugins on one form clash: both register [confirm] and both react
 * to the form's events. Form Steps reads the old tags and classes, so the safe
 * fix is to deactivate the other plugin. Nothing is changed without a click.
 */
final class Migration {

	/**
	 * Plugin folder => [ display name, whether it uses [confirm] tags ].
	 */
	const KNOWN = array(
		'contact-form-7-add-confirm'                       => array( 'Contact Form 7 add confirm', true ),
		'norick-confirm-for-contact-form-7'                => array( 'Norick Confirm for Contact Form 7', true ),
		'izurutoys-confirmation-screen-for-contact-form-7' => array( 'Izurutoys Confirmation Screen for Contact Form 7', true ),
		'bpotech-confirm-step-for-contact-form-7'          => array( 'BPOTech Confirm Step for Contact Form 7', true ),
		'plugear-review-step-for-contact-form-7'           => array( 'Plugear Review Step for Contact Form 7', false ),
		'confirm-plus-contact-form-7'                      => array( 'Confirm Plus Contact Form 7', false ),
	);

	/**
	 * Registers hooks.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Active plugins from the list above, as basename => [ name, uses tags ].
	 *
	 * @return array
	 */
	public static function active_conflicts() {
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		$found = array();
		foreach ( $active as $basename ) {
			$folder = strtok( (string) $basename, '/' );
			if ( isset( self::KNOWN[ $folder ] ) ) {
				$found[ $basename ] = self::KNOWN[ $folder ];
			}
		}
		return $found;
	}

	/**
	 * Number of forms that contain [confirm] or [back] tags.
	 *
	 * @return int
	 */
	private static function forms_with_tags() {
		$count = 0;
		foreach ( \WPCF7_ContactForm::find( array( 'posts_per_page' => -1 ) ) as $form ) {
			if ( array() !== $form->scan_form_tags( array( 'type' => array( 'confirm', 'back' ) ) ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Notice on the Plugins screen and the Form Steps page only.
	 */
	public function notice() {
		if ( ! current_user_can( 'deactivate_plugins' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ( 'plugins' !== $screen->id && false === strpos( (string) $screen->id, Admin_Page::SLUG ) ) ) {
			return;
		}

		$conflicts = self::active_conflicts();
		if ( ! $conflicts ) {
			return;
		}
		$tagged = self::forms_with_tags();

		foreach ( $conflicts as $basename => $info ) {
			list( $name, $uses_tags ) = $info;

			$deactivate = wp_nonce_url(
				add_query_arg(
					array(
						'action'        => 'deactivate',
						'plugin'        => $basename,
						'plugin_status' => 'all',
					),
					admin_url( 'plugins.php' )
				),
				'deactivate-plugin_' . $basename
			);
			?>
			<div class="notice notice-warning">
				<p>
					<strong>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: name of another plugin. */
								__( 'Form Steps and "%s" are both active.', 'form-steps-for-contact-form-7' ),
								$name
							)
						);
						?>
					</strong>
					<?php esc_html_e( 'Both add a confirm step, so forms may behave twice. Keep only one.', 'form-steps-for-contact-form-7' ); ?>
				</p>
				<p>
					<?php
					if ( $uses_tags && $tagged ) {
						echo esc_html(
							sprintf(
								/* translators: %d: number of forms. */
								_n(
									'%d form uses [confirm] or [back] tags. Form Steps supports the same tags and classes, so it keeps working after you switch.',
									'%d forms use [confirm] or [back] tags. Form Steps supports the same tags and classes, so they keep working after you switch.',
									$tagged,
									'form-steps-for-contact-form-7'
								),
								$tagged
							)
						);
					} elseif ( $uses_tags ) {
						esc_html_e( 'Form Steps supports the same [confirm] and [back] tags and classes, so forms keep working after you switch.', 'form-steps-for-contact-form-7' );
					} else {
						esc_html_e( 'That plugin adds its confirm step without tags. After switching, turn on the confirm screen for your forms on the Form Steps page.', 'form-steps-for-contact-form-7' );
					}
					?>
				</p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( $deactivate ); ?>">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: name of another plugin. */
								__( 'Deactivate "%s"', 'form-steps-for-contact-form-7' ),
								$name
							)
						);
						?>
					</a>
					<?php if ( ! $uses_tags ) : ?>
						<a class="button" href="<?php echo esc_url( Admin_Page::url() ); ?>"><?php esc_html_e( 'Open Form Steps', 'form-steps-for-contact-form-7' ); ?></a>
					<?php endif; ?>
				</p>
			</div>
			<?php
		}
	}
}
