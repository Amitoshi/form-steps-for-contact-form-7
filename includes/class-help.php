<?php
/**
 * Small "i" help buttons shown next to settings.
 *
 * @package AmitShaw\FormSteps
 */

namespace AmitShaw\FormSteps;

defined( 'ABSPATH' ) || exit;

/**
 * A tip opens on click, hover or keyboard focus and holds a short explanation
 * plus a link to the full example. Works without JavaScript through :focus-within.
 */
final class Help {

	/**
	 * Counter for unique element ids.
	 *
	 * @var int
	 */
	private static $count = 0;

	/**
	 * Prints a tip.
	 *
	 * @param string $topic Topic key from Links::PATHS.
	 * @param string $text  Plain-text explanation.
	 */
	public static function tip( $topic, $text ) {
		++self::$count;
		$id = 'svfs-tip-' . self::$count;
		?>
		<span class="svfs-tip">
			<button type="button" class="svfs-tip-btn" aria-expanded="false" aria-controls="<?php echo esc_attr( $id ); ?>">
				<span aria-hidden="true">i</span>
				<span class="screen-reader-text"><?php esc_html_e( 'More information', 'form-steps-for-contact-form-7' ); ?></span>
			</button>
			<span class="svfs-tip-pop" id="<?php echo esc_attr( $id ); ?>" role="note">
				<?php echo esc_html( $text ); ?>
				<a class="svfs-tip-link" href="<?php echo esc_url( Links::help( $topic ) ); ?>" <?php echo Links::is_live( $topic ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?php esc_html_e( 'See example', 'form-steps-for-contact-form-7' ); ?></a>
			</span>
		</span>
		<?php
	}
}
