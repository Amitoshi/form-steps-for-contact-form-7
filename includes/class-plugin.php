<?php
/**
 * Main plugin class: wires modules together.
 *
 * @package AmitShaw\FormSteps
 */

namespace AmitShaw\FormSteps;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton entry point. Each module registers its own hooks in its constructor.
 */
final class Plugin {

	/**
	 * Single instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Returns the single instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Starts the plugin. Called on plugins_loaded once requirements are met.
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$this->maybe_upgrade();

		$modules = array(
			Phase::class,
			Form_Render::class,
			Form_Tags::class,
			Examples::class,
		);
		if ( is_admin() ) {
			$modules[] = Editor_Panel::class;
			$modules[] = Admin_Page::class;
			$modules[] = Migration::class;
		}
		foreach ( $modules as $module ) {
			new $module();
		}
	}

	/**
	 * URL of a plugin asset: the minified file, or the readable source when
	 * SCRIPT_DEBUG is on. Both ship with the plugin.
	 *
	 * @param string $path Path below assets/ without extension, e.g. "js/form-steps".
	 * @param string $ext  "js" or "css".
	 * @return string
	 */
	public static function asset_url( $path, $ext ) {
		$min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		return SVFS_URL . 'assets/' . $path . $min . '.' . $ext;
	}

	/**
	 * Runs upgrade routines when the stored version differs from the code version.
	 * Settings arrays carry their own "v" key and migrate on read, so this only
	 * records the version and is the single place for future one-off upgrades.
	 */
	private function maybe_upgrade() {
		$stored = get_option( 'svfs_version', '' );
		if ( SVFS_VERSION === $stored ) {
			return;
		}
		update_option( 'svfs_version', SVFS_VERSION, false );
	}
}
