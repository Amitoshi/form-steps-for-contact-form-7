<?php
/**
 * Plugin Name:       Form Steps for Contact Form 7
 * Description:       Adds a confirm screen, multi-step pages and a thank-you screen to Contact Form 7 forms, inside the same form. One switch per form.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  contact-form-7
 * Author:            Amit Jyoti Shaw
 * Author URI:        https://github.com/Amitoshi
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       form-steps-for-contact-form-7
 * Domain Path:       /languages
 *
 * @package AmitShaw\FormSteps
 */

defined( 'ABSPATH' ) || exit;

define( 'SVFS_VERSION', '1.0.0' );
define( 'SVFS_FILE', __FILE__ );
define( 'SVFS_DIR', plugin_dir_path( __FILE__ ) );
define( 'SVFS_URL', plugin_dir_url( __FILE__ ) );

/*
 * Class autoloader for the AmitShaw\FormSteps namespace.
 * AmitShaw\FormSteps\Form_Settings maps to includes/class-form-settings.php.
 */
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'AmitShaw\\FormSteps\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = SVFS_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

// Shows a one-time "choose a form" notice after activation.
register_activation_hook(
	__FILE__,
	static function () {
		add_option( 'svfs_welcome', 1, '', false );
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! \AmitShaw\FormSteps\Requirements::met() ) {
			add_action( 'admin_notices', array( '\AmitShaw\FormSteps\Requirements', 'render_notice' ) );
			return;
		}
		\AmitShaw\FormSteps\Plugin::instance()->boot();
	},
	20 // After Contact Form 7 has defined its constants and classes.
);
