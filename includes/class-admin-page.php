<?php
/**
 * Contact → Form Steps admin page: forms overview, defaults, import and export, help.
 *
 * @package AmitShaw\FormSteps
 */

namespace AmitShaw\FormSteps;

defined( 'ABSPATH' ) || exit;

/**
 * The forms overview is the beginner path: one switch turns the confirm screen on
 * for a form and it is live at once.
 */
final class Admin_Page {

	const SLUG       = 'svfs';
	const AJAX_NONCE = 'svfs_admin';
	const MAX_IMPORT = 1048576; // 1 MB.

	/**
	 * Hook suffix of the page, set when the menu is added.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Registers hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_svfs_toggle', array( $this, 'ajax_toggle' ) );
		add_action( 'admin_post_svfs_save_forms', array( $this, 'save_forms' ) );
		add_action( 'admin_post_svfs_save_defaults', array( $this, 'save_defaults' ) );
		add_action( 'admin_post_svfs_import', array( $this, 'import' ) );
		add_action( 'admin_notices', array( $this, 'welcome_notice' ) );
		add_action( 'admin_notices', array( $this, 'created_notice' ) );
		add_action( 'admin_init', array( $this, 'maybe_dismiss_welcome' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SVFS_FILE ), array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	/**
	 * Adds the page under Contact Form 7's "Contact" menu.
	 */
	public function menu() {
		$hook = add_submenu_page(
			self::parent_slug(),
			__( 'Form Steps', 'form-steps-for-contact-form-7' ),
			__( 'Form Steps', 'form-steps-for-contact-form-7' ),
			'wpcf7_edit_contact_forms',
			self::SLUG,
			array( $this, 'render' )
		);
		$this->hook = $hook ? $hook : '';
	}

	/**
	 * Slug of Contact Form 7's top-level "Contact" menu. It was "wpcf7" up to
	 * 6.2.x and became "wpcf7-dashboard" when Contact Form 7 added its Dashboard,
	 * so find whichever parent holds the "Contact Forms" list.
	 *
	 * @return string
	 */
	private static function parent_slug() {
		global $submenu;
		foreach ( (array) $submenu as $parent => $items ) {
			foreach ( (array) $items as $item ) {
				if ( isset( $item[2] ) && 'wpcf7' === $item[2] ) {
					return (string) $parent;
				}
			}
		}
		return 'wpcf7';
	}

	/**
	 * URL of a tab on the page.
	 *
	 * @param string $tab  Tab key.
	 * @param array  $args Extra query args.
	 * @return string
	 */
	public static function url( $tab = 'forms', array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG, 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Loads admin assets on this page and in the Contact Form 7 editor.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function assets( $hook_suffix ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides whether to load styles.
		$page      = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$is_ours   = '' !== $this->hook && $hook_suffix === $this->hook;
		$is_editor = in_array( $page, array( 'wpcf7', 'wpcf7-new' ), true );
		if ( ! $is_ours && ! $is_editor ) {
			return;
		}
		// The front-end styles power the live button preview.
		wp_enqueue_style( 'svfs-front-preview', Plugin::asset_url( 'css/form-steps', 'css' ), array(), SVFS_VERSION );
		wp_enqueue_style( 'svfs-admin', Plugin::asset_url( 'css/admin', 'css' ), array( 'svfs-front-preview' ), SVFS_VERSION );
		wp_enqueue_script( 'svfs-admin', Plugin::asset_url( 'js/admin', 'js' ), array(), SVFS_VERSION, true );
		wp_localize_script(
			'svfs-admin',
			'svfsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::AJAX_NONCE ),
				'i18n'    => array(
					'saved'  => __( 'Saved', 'form-steps-for-contact-form-7' ),
					'failed' => __( 'Could not save. Reload the page and try again.', 'form-steps-for-contact-form-7' ),
					'live'   => __( 'Live', 'form-steps-for-contact-form-7' ),
					'off'    => __( 'Off', 'form-steps-for-contact-form-7' ),
					'copied' => __( 'Copied', 'form-steps-for-contact-form-7' ),
				),
			)
		);
	}

	/* --------------------------------------------------------------------- */
	/* Actions                                                               */
	/* --------------------------------------------------------------------- */

	/**
	 * Switch on the forms overview, saved without reloading.
	 */
	public function ajax_toggle() {
		check_ajax_referer( self::AJAX_NONCE, 'nonce' );
		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$form    = $form_id ? wpcf7_contact_form( $form_id ) : null;
		if ( ! $form || ! current_user_can( 'wpcf7_edit_contact_form', $form_id ) ) {
			wp_send_json_error( null, 403 );
		}
		$settings            = Form_Settings::get( $form_id );
		$settings['confirm'] = ! empty( $_POST['on'] );
		Form_Settings::save( $form_id, $settings );

		wp_send_json_success( array( 'live' => $this->is_live( $form ) ) );
	}

	/**
	 * Saves the overview switches when JavaScript is off, and "Turn on for all forms".
	 */
	public function save_forms() {
		if ( ! current_user_can( 'wpcf7_edit_contact_forms' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'form-steps-for-contact-form-7' ), 403 );
		}
		check_admin_referer( 'svfs_save_forms' );

		$ids        = isset( $_POST['form_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['form_ids'] ) ) : array();
		$checked    = isset( $_POST['confirm'] ) ? array_map( 'absint', array_keys( (array) wp_unslash( $_POST['confirm'] ) ) ) : array();
		$enable_all = ! empty( $_POST['enable_all'] );

		foreach ( $ids as $id ) {
			$form = $id ? wpcf7_contact_form( $id ) : null;
			if ( ! $form || ! current_user_can( 'wpcf7_edit_contact_form', $id ) ) {
				continue;
			}
			// Switches of forms with [confirm] tags are disabled and not posted; keep their stored choice.
			if ( ! $enable_all && array() !== $form->scan_form_tags( array( 'type' => 'confirm' ) ) ) {
				continue;
			}
			$settings            = Form_Settings::get( $id );
			$settings['confirm'] = $enable_all || in_array( $id, $checked, true );
			Form_Settings::save( $id, $settings );
		}

		$this->redirect( 'forms', 'saved' );
	}

	/**
	 * Saves the site-wide defaults.
	 */
	public function save_defaults() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'form-steps-for-contact-form-7' ), 403 );
		}
		check_admin_referer( 'svfs_save_defaults' );
		// Sanitized field by field in Global_Settings::sanitize().
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw = isset( $_POST['svfs_defaults'] ) ? (array) wp_unslash( $_POST['svfs_defaults'] ) : array();
		Global_Settings::save( $raw );
		$this->redirect( 'defaults', 'saved' );
	}

	/**
	 * Imports settings exported from this or another site.
	 */
	public function import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'form-steps-for-contact-form-7' ), 403 );
		}
		check_admin_referer( 'svfs_import' );

		// Raw JSON: decoded below, then every value goes through the settings sanitizers.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$json = isset( $_POST['svfs_import'] ) && is_string( $_POST['svfs_import'] ) ? wp_unslash( $_POST['svfs_import'] ) : '';
		$data = strlen( $json ) <= self::MAX_IMPORT ? json_decode( $json, true ) : null;

		if ( ! is_array( $data ) || ! isset( $data['plugin'] ) || 'form-steps-for-contact-form-7' !== $data['plugin'] ) {
			$this->redirect( 'transfer', 'import_invalid' );
		}

		if ( isset( $data['defaults'] ) && is_array( $data['defaults'] ) ) {
			Global_Settings::save( $data['defaults'] );
		}

		$matched = 0;
		if ( ! empty( $_POST['svfs_import_forms'] ) && isset( $data['forms'] ) && is_array( $data['forms'] ) ) {
			foreach ( $data['forms'] as $entry ) {
				if ( ! is_array( $entry ) || empty( $entry['title'] ) || ! is_string( $entry['title'] ) || ! isset( $entry['settings'] ) || ! is_array( $entry['settings'] ) ) {
					continue;
				}
				$found = \WPCF7_ContactForm::find(
					array(
						'title'          => $entry['title'],
						'posts_per_page' => 1,
					)
				);
				if ( $found && current_user_can( 'wpcf7_edit_contact_form', $found[0]->id() ) ) {
					Form_Settings::save( $found[0]->id(), $entry['settings'] );
					++$matched;
				}
			}
		}

		$this->redirect( 'transfer', 'imported', array( 'svfs_count' => $matched ) );
	}

	/**
	 * Redirects back to a tab with a result message, then stops.
	 *
	 * @param string $tab     Tab key.
	 * @param string $message Message key.
	 * @param array  $extra   Extra query args.
	 */
	private function redirect( $tab, $message, array $extra = array() ) {
		wp_safe_redirect( self::url( $tab, array_merge( array( 'svfs_msg' => $message ), $extra ) ) );
		exit;
	}

	/* --------------------------------------------------------------------- */
	/* Welcome notice and plugin links                                       */
	/* --------------------------------------------------------------------- */

	/**
	 * One-time notice after activation, on the Plugins screen only. It disappears
	 * once dismissed or once the Form Steps page is opened.
	 */
	public function welcome_notice() {
		if ( ! get_option( 'svfs_welcome' ) || ! current_user_can( 'wpcf7_edit_contact_forms' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		if ( 'plugins' !== $screen->id ) {
			return;
		}
		$dismiss = wp_nonce_url( add_query_arg( 'svfs_dismiss', '1' ), 'svfs_dismiss' );
		?>
		<div class="notice notice-success svfs-welcome">
			<p>
				<strong><?php esc_html_e( 'Form Steps is ready.', 'form-steps-for-contact-form-7' ); ?></strong>
				<?php esc_html_e( 'Its settings are under Contact → Form Steps. Turn on a confirm screen for any form with one switch.', 'form-steps-for-contact-form-7' ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Open Form Steps', 'form-steps-for-contact-form-7' ); ?></a>
				<a class="button" href="<?php echo esc_url( self::url( 'help' ) ); ?>"><?php esc_html_e( 'Quick start', 'form-steps-for-contact-form-7' ); ?></a>
				<a class="button-link" href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'Dismiss', 'form-steps-for-contact-form-7' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Notice in the form editor after "Create this form" on the Examples tab.
	 */
	public function created_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
		$key     = isset( $_GET['svfs_created'] ) ? sanitize_key( wp_unslash( $_GET['svfs_created'] ) ) : '';
		$page_id = isset( $_GET['svfs_page'] ) ? absint( $_GET['svfs_page'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( '' === $key || ! Examples::get( $key ) ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Example form created.', 'form-steps-for-contact-form-7' ); ?></strong>
				<?php esc_html_e( 'Its Form Steps settings and Form style are set. Edit anything you like, then save.', 'form-steps-for-contact-form-7' ); ?>
			</p>
			<?php if ( $page_id && get_post( $page_id ) ) : ?>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( (string) get_edit_post_link( $page_id ) ); ?>"><?php esc_html_e( 'Open the draft page', 'form-steps-for-contact-form-7' ); ?></a>
					<a class="button" href="<?php echo esc_url( (string) get_preview_post_link( $page_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview the page', 'form-steps-for-contact-form-7' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handles the Dismiss link of the welcome notice.
	 */
	public function maybe_dismiss_welcome() {
		if ( ! isset( $_GET['svfs_dismiss'] ) ) {
			return;
		}
		check_admin_referer( 'svfs_dismiss' );
		delete_option( 'svfs_welcome' );
		wp_safe_redirect( remove_query_arg( array( 'svfs_dismiss', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param array $links Action links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'form-steps-for-contact-form-7' ) . '</a>',
			'<a href="' . esc_url( self::url( 'examples' ) ) . '">' . esc_html__( 'Examples', 'form-steps-for-contact-form-7' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Help, Live demo and Support links under the plugin description.
	 *
	 * @param array  $meta Row meta links.
	 * @param string $file Plugin file of the row.
	 * @return array
	 */
	public function row_meta( $meta, $file ) {
		if ( plugin_basename( SVFS_FILE ) !== $file ) {
			return $meta;
		}
		$meta[] = '<a href="' . esc_url( Links::help( 'docs' ) ) . '">' . esc_html__( 'Help & examples', 'form-steps-for-contact-form-7' ) . '</a>';
		if ( Links::is_live( 'demo' ) ) {
			$meta[] = '<a href="' . esc_url( Links::online( 'demo' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Live demo', 'form-steps-for-contact-form-7' ) . '</a>';
		}
		$meta[] = '<a href="' . esc_url( Links::SUPPORT ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Support', 'form-steps-for-contact-form-7' ) . '</a>';
		return $meta;
	}

	/* --------------------------------------------------------------------- */
	/* Page                                                                  */
	/* --------------------------------------------------------------------- */

	/**
	 * Renders the page.
	 */
	public function render() {
		if ( ! current_user_can( 'wpcf7_edit_contact_forms' ) ) {
			return;
		}
		delete_option( 'svfs_welcome' );

		$tabs = array(
			'forms'    => __( 'Forms', 'form-steps-for-contact-form-7' ),
			'examples' => __( 'Examples', 'form-steps-for-contact-form-7' ),
			'defaults' => __( 'Defaults', 'form-steps-for-contact-form-7' ),
			'transfer' => __( 'Import & export', 'form-steps-for-contact-form-7' ),
			'help'     => __( 'Help & About', 'form-steps-for-contact-form-7' ),
		);
		if ( ! current_user_can( 'manage_options' ) ) {
			unset( $tabs['defaults'], $tabs['transfer'] );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab selection only.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'forms';
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'forms';
		}
		?>
		<div class="wrap svfs-admin">
			<h1 class="svfs-title">
				<?php esc_html_e( 'Form Steps for Contact Form 7', 'form-steps-for-contact-form-7' ); ?>
				<span class="svfs-version"><?php echo esc_html( SVFS_VERSION ); ?></span>
			</h1>
			<p class="svfs-tagline"><?php esc_html_e( 'Confirm screen, multi-step forms and thank-you screen for Contact Form 7.', 'form-steps-for-contact-form-7' ); ?></p>

			<?php $this->result_notice(); ?>

			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Form Steps sections', 'form-steps-for-contact-form-7' ); ?>">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( self::url( $key ) ); ?>" class="nav-tab <?php echo esc_attr( $key === $tab ? 'nav-tab-active' : '' ); ?>" <?php echo $key === $tab ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<div class="svfs-tab">
				<?php
				switch ( $tab ) {
					case 'defaults':
						$this->tab_defaults();
						break;
					case 'transfer':
						$this->tab_transfer();
						break;
					case 'help':
						$this->tab_help();
						break;
					case 'examples':
						$this->tab_examples();
						break;
					default:
						$this->tab_forms();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Message after a save or import.
	 */
	private function result_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
		$message = isset( $_GET['svfs_msg'] ) ? sanitize_key( wp_unslash( $_GET['svfs_msg'] ) ) : '';
		$count   = isset( $_GET['svfs_count'] ) ? absint( $_GET['svfs_count'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$texts = array(
			'saved'          => array( 'success', __( 'Settings saved.', 'form-steps-for-contact-form-7' ) ),
			'import_invalid' => array( 'error', __( 'Nothing was imported. Paste the full text exported from Form Steps.', 'form-steps-for-contact-form-7' ) ),
			'imported'       => array(
				'success',
				sprintf(
					/* translators: %d: number of forms whose settings were imported. */
					_n( 'Settings imported. %d form matched by title.', 'Settings imported. %d forms matched by title.', $count, 'form-steps-for-contact-form-7' ),
					$count
				),
			),
		);
		if ( ! isset( $texts[ $message ] ) ) {
			return;
		}
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $texts[ $message ][0] ),
			esc_html( $texts[ $message ][1] )
		);
	}

	/**
	 * Whether the form currently uses any feature.
	 *
	 * @param \WPCF7_ContactForm $form Form.
	 * @return bool
	 */
	private function is_live( $form ) {
		return Form_Settings::confirm_enabled( $form ) || Form_Settings::steps_enabled( $form );
	}

	/**
	 * Forms overview with one switch per form.
	 */
	private function tab_forms() {
		$forms = \WPCF7_ContactForm::find(
			array(
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		if ( ! $forms ) {
			?>
			<div class="svfs-empty">
				<p><?php esc_html_e( 'There are no contact forms yet.', 'form-steps-for-contact-form-7' ); ?></p>
				<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpcf7-new' ) ); ?>"><?php esc_html_e( 'Create a contact form', 'form-steps-for-contact-form-7' ); ?></a></p>
			</div>
			<?php
			return;
		}
		?>
		<p class="svfs-lead"><?php esc_html_e( 'Turn on the confirm screen for a form and it is live at once. For steps, button texts and the thank-you screen, open the form and use its Steps tab.', 'form-steps-for-contact-form-7' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="svfs-forms-form">
			<input type="hidden" name="action" value="svfs_save_forms" />
			<?php wp_nonce_field( 'svfs_save_forms' ); ?>

			<table class="widefat striped svfs-forms">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Form', 'form-steps-for-contact-form-7' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Confirm screen', 'form-steps-for-contact-form-7' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Steps', 'form-steps-for-contact-form-7' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'form-steps-for-contact-form-7' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $forms as $form ) : ?>
					<?php
					$id          = (int) $form->id();
					$settings    = Form_Settings::get( $id );
					$by_tag      = array() !== $form->scan_form_tags( array( 'type' => 'confirm' ) );
					$step_tags   = count( $form->scan_form_tags( array( 'type' => 'step' ) ) );
					$live        = $this->is_live( $form );
					$can_edit    = current_user_can( 'wpcf7_edit_contact_form', $id );
					$edit_url    = admin_url( 'admin.php?page=wpcf7&post=' . $id . '&action=edit&active-tab=svfs-steps-panel' );
					$switch_text = sprintf(
						/* translators: %s: form title. */
						__( 'Confirm screen for “%s”', 'form-steps-for-contact-form-7' ),
						$form->title()
					);
					?>
					<tr>
						<td>
							<input type="hidden" name="form_ids[]" value="<?php echo esc_attr( (string) $id ); ?>" />
							<strong><?php echo $can_edit ? '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $form->title() ) . '</a>' : esc_html( $form->title() ); ?></strong>
						</td>
						<td>
							<label class="svfs-switch">
								<input type="checkbox" class="svfs-toggle" name="<?php echo esc_attr( 'confirm[' . $id . ']' ); ?>" value="1" data-form="<?php echo esc_attr( (string) $id ); ?>" <?php checked( $settings['confirm'] || $by_tag ); ?> <?php disabled( $by_tag || ! $can_edit ); ?> />
								<span class="svfs-switch-ui" aria-hidden="true"></span>
								<span class="screen-reader-text"><?php echo esc_html( $switch_text ); ?></span>
							</label>
							<?php if ( $by_tag ) : ?>
								<span class="svfs-note"><?php esc_html_e( 'On by [confirm] tag', 'form-steps-for-contact-form-7' ); ?></span>
							<?php endif; ?>
							<span class="svfs-saved" aria-live="polite"></span>
						</td>
						<td>
							<?php
							if ( $step_tags ) {
								echo esc_html(
									sprintf(
										/* translators: %d: number of [step] tags. */
										_n( '%d step tag', '%d step tags', $step_tags, 'form-steps-for-contact-form-7' ),
										$step_tags
									)
								);
							} else {
								echo '<span class="svfs-muted">' . esc_html__( 'One page', 'form-steps-for-contact-form-7' ) . '</span>';
							}
							?>
						</td>
						<td>
							<span class="svfs-pill <?php echo esc_attr( $live ? 'is-live' : 'is-off' ); ?>"><?php echo $live ? esc_html__( 'Live', 'form-steps-for-contact-form-7' ) : esc_html__( 'Off', 'form-steps-for-contact-form-7' ); ?></span>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<p class="svfs-actions">
				<button type="submit" class="button button-primary svfs-nojs-save"><?php esc_html_e( 'Save changes', 'form-steps-for-contact-form-7' ); ?></button>
				<button type="submit" class="button" name="enable_all" value="1"><?php esc_html_e( 'Turn on the confirm screen for all forms', 'form-steps-for-contact-form-7' ); ?></button>
			</p>
		</form>
		<?php
	}

	/**
	 * Site-wide defaults.
	 */
	private function tab_defaults() {
		$global   = Global_Settings::get();
		$defaults = Global_Settings::default_labels();
		$titles   = array(
			'review' => __( 'Review button', 'form-steps-for-contact-form-7' ),
			'back'   => __( 'Back button', 'form-steps-for-contact-form-7' ),
			'send'   => __( 'Send button on the confirm screen', 'form-steps-for-contact-form-7' ),
			'next'   => __( 'Next button', 'form-steps-for-contact-form-7' ),
			'prev'   => __( 'Previous button', 'form-steps-for-contact-form-7' ),
		);
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="svfs-card-box">
			<input type="hidden" name="action" value="svfs_save_defaults" />
			<?php wp_nonce_field( 'svfs_save_defaults' ); ?>
			<p><?php esc_html_e( 'These defaults apply to every form. Each form can override them in its Steps tab.', 'form-steps-for-contact-form-7' ); ?></p>

			<h2><?php esc_html_e( 'Button texts', 'form-steps-for-contact-form-7' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php foreach ( Global_Settings::LABEL_KEYS as $key ) : ?>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( 'svfs-d-' . $key ); ?>"><?php echo esc_html( $titles[ $key ] ); ?></label></th>
						<td>
							<input type="text" class="regular-text" maxlength="60" id="<?php echo esc_attr( 'svfs-d-' . $key ); ?>" name="<?php echo esc_attr( 'svfs_defaults[labels][' . $key . ']' ); ?>" value="<?php echo esc_attr( $global['labels'][ $key ] ); ?>" placeholder="<?php echo esc_attr( 'send' === $key ? __( 'Your submit button\'s text', 'form-steps-for-contact-form-7' ) : $defaults[ $key ] ); ?>" />
						</td>
					</tr>
				<?php endforeach; ?>
			</table>

			<h2><?php esc_html_e( 'Display', 'form-steps-for-contact-form-7' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Progress display for steps', 'form-steps-for-contact-form-7' ); ?></th>
					<td>
						<fieldset>
							<?php
							$progress = array(
								'numbers' => __( 'Numbered steps with titles', 'form-steps-for-contact-form-7' ),
								'bar'     => __( 'Progress bar', 'form-steps-for-contact-form-7' ),
								'none'    => __( 'None', 'form-steps-for-contact-form-7' ),
							);
							foreach ( $progress as $value => $label ) {
								printf(
									'<label><input type="radio" name="svfs_defaults[progress]" value="%1$s" %2$s /> %3$s</label><br />',
									esc_attr( $value ),
									checked( $value, $global['progress'], false ),
									esc_html( $label )
								);
							}
							?>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Empty optional fields on the confirm screen', 'form-steps-for-contact-form-7' ); ?></th>
					<td>
						<fieldset>
							<label><input type="radio" name="svfs_defaults[empty_values]" value="dash" <?php checked( 'dash', $global['empty_values'] ); ?> /> <?php esc_html_e( 'Show a dash', 'form-steps-for-contact-form-7' ); ?></label><br />
							<label><input type="radio" name="svfs_defaults[empty_values]" value="hide" <?php checked( 'hide', $global['empty_values'] ); ?> /> <?php esc_html_e( 'Hide in the table', 'form-steps-for-contact-form-7' ); ?></label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Scrolling', 'form-steps-for-contact-form-7' ); ?></th>
					<td>
						<label><input type="checkbox" name="svfs_defaults[scroll]" value="1" <?php checked( $global['scroll'] ); ?> /> <?php esc_html_e( 'Scroll to the top of the form when the screen or step changes', 'form-steps-for-contact-form-7' ); ?></label>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Button design', 'form-steps-for-contact-form-7' ); ?></h2>
			<p><?php esc_html_e( 'Used by every form whose Steps tab says "Site default".', 'form-steps-for-contact-form-7' ); ?></p>
			<?php
			Editor_Panel::design_controls(
				'svfs_defaults[design]',
				$global['design'],
				false,
				array(
					'back' => '' !== $global['labels']['back'] ? $global['labels']['back'] : $defaults['back'],
					'send' => '' !== $global['labels']['send'] ? $global['labels']['send'] : $defaults['send'],
					'prev' => '' !== $global['labels']['prev'] ? $global['labels']['prev'] : $defaults['prev'],
					'next' => '' !== $global['labels']['next'] ? $global['labels']['next'] : $defaults['next'],
				)
			);
			?>

			<?php submit_button( __( 'Save defaults', 'form-steps-for-contact-form-7' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Import and export.
	 */
	private function tab_transfer() {
		$export = array(
			'plugin'   => 'form-steps-for-contact-form-7',
			'version'  => SVFS_VERSION,
			'exported' => gmdate( 'c' ),
			'defaults' => Global_Settings::get(),
			'forms'    => array(),
		);
		$forms  = \WPCF7_ContactForm::find( array( 'posts_per_page' => -1 ) );
		foreach ( $forms as $form ) {
			$export['forms'][] = array(
				'title'    => $form->title(),
				'settings' => Form_Settings::get( $form->id() ),
			);
		}
		?>
		<div class="svfs-columns">
			<section class="svfs-card-box">
				<h2><?php esc_html_e( 'Export', 'form-steps-for-contact-form-7' ); ?></h2>
				<p><?php esc_html_e( 'Copy this text to move the site defaults and every form\'s Steps settings to another site.', 'form-steps-for-contact-form-7' ); ?></p>
				<textarea class="large-text code svfs-export" rows="12" readonly><?php echo esc_textarea( (string) wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
				<p><button type="button" class="button svfs-copy"><?php esc_html_e( 'Copy to clipboard', 'form-steps-for-contact-form-7' ); ?></button> <span class="svfs-saved" aria-live="polite"></span></p>
			</section>

			<section class="svfs-card-box">
				<h2><?php esc_html_e( 'Import', 'form-steps-for-contact-form-7' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="svfs_import" />
					<?php wp_nonce_field( 'svfs_import' ); ?>
					<p><label for="svfs-import"><?php esc_html_e( 'Paste exported text here.', 'form-steps-for-contact-form-7' ); ?></label></p>
					<textarea id="svfs-import" class="large-text code" rows="10" name="svfs_import" required></textarea>
					<p><label><input type="checkbox" name="svfs_import_forms" value="1" checked /> <?php esc_html_e( 'Also import form settings, matched to forms with the same title', 'form-steps-for-contact-form-7' ); ?></label></p>
					<?php submit_button( __( 'Import', 'form-steps-for-contact-form-7' ), 'secondary' ); ?>
				</form>
			</section>
		</div>
		<?php
	}

	/**
	 * Styling reference: variables, state classes, elements and the added markup.
	 */
	private function class_reference() {
		$groups = array(
			array(
				__( 'Colour and shape variables', 'form-steps-for-contact-form-7' ),
				__( 'Set them in WordPress\'s Additional CSS: on .wpcf7 form.svfs-id-12 for the form with ID 12, or on .svfs-form for every form.', 'form-steps-for-contact-form-7' ),
				array(
					array( '--svfs-accent', __( 'Button and progress color.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 { --svfs-accent: #1b447e; }' ),
					array( '--svfs-accent-text', __( 'Text on filled buttons.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 { --svfs-accent-text: #fff; }' ),
					array( '--svfs-accent-hover', __( 'Filled button hover color.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 { --svfs-accent-hover: #14335f; }' ),
					array( '--svfs-accent-soft', __( 'Hover background of Back and Previous.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 { --svfs-accent-soft: #eef2ff; }' ),
					array( '--svfs-radius', __( 'Button corners (Filled and Outline).', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 { --svfs-radius: 10px; }' ),
					array( '--svfs-success', __( 'Check icon on the thank-you templates.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 { --svfs-success: #0f766e; }' ),
					array( '--svfs-card-bg', __( 'Background of thank-you template cards.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 { --svfs-card-bg: #f8fafc; }' ),
				),
			),
			array(
				__( 'Built-in Form styles', 'form-steps-for-contact-form-7' ),
				__( 'Chosen in the Steps tab → Design → Form style. Each style takes its colors from these variables.', 'form-steps-for-contact-form-7' ),
				array(
					array( '.svfs-skin / .svfs-skin-navy, -teal, -warm, -sky, -dark', __( 'Classes added when a Form style is chosen.', 'form-steps-for-contact-form-7' ), '' ),
					array( '--svfs-p-head / --svfs-p-head-text', __( 'Header band and its text.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-skin-teal { --svfs-p-head: #134e4a; }' ),
					array( '--svfs-p-card / --svfs-p-page', __( 'Card background and the area around it in previews.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-skin-navy { --svfs-p-card: #f8fafc; }' ),
					array( '--svfs-p-text / --svfs-p-muted', __( 'Main text and secondary text.', 'form-steps-for-contact-form-7' ), '' ),
					array( '--svfs-p-border / --svfs-p-input / --svfs-p-soft', __( 'Borders, field background, light tint.', 'form-steps-for-contact-form-7' ), '' ),
					array( '.ex-head / .ex-title / .ex-sub / .ex-row / .ex-req', __( 'Header, two-column rows and required badges used in the example forms.', 'form-steps-for-contact-form-7' ), '' ),
				),
			),
			array(
				__( 'Classes on the form element', 'form-steps-for-contact-form-7' ),
				__( 'Use them to style a screen or a choice made in the Steps tab.', 'form-steps-for-contact-form-7' ),
				array(
					array( '.svfs-form', __( 'Every form that uses the confirm screen or steps.', 'form-steps-for-contact-form-7' ), '.svfs-form { max-width: 640px; }' ),
					array( '.svfs-id-{ID}', __( 'One form, by its ID. Use it in Additional CSS to style only that form.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 { }' ),
					array( '.svfs-state-input', __( 'The visitor is filling in the form.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12.svfs-state-input { }' ),
					array( '.svfs-state-review', __( 'The confirm screen is showing.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12.svfs-state-review { background: #f8fafc; }' ),
					array( '.svfs-state-done', __( 'The form was sent.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12.svfs-state-done { }' ),
					array( '.svfs-has-steps', __( 'The form is split with [step] tags.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12.svfs-has-steps { }' ),
					array( '.svfs-style-inplace / .svfs-style-table', __( 'Confirm style chosen.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12.svfs-style-table .svfs-summary { }' ),
					array( '.svfs-btn-theme / -filled / -outline / -rounded', __( 'Button style chosen.', 'form-steps-for-contact-form-7' ), '' ),
					array( '.svfs-align-left / -center / -right / -full / -split', __( 'Button position chosen.', 'form-steps-for-contact-form-7' ), '' ),
				),
			),
			array(
				__( 'Buttons', 'form-steps-for-contact-form-7' ),
				'',
				array(
					array( '.wpcf7-submit', __( 'Contact Form 7\'s submit button: Review while filling in, Send on the confirm screen.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .wpcf7-submit { background: #e11d48; }' ),
					array( '.svfs-back', __( 'Back on the confirm screen.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-back { }' ),
					array( '.svfs-next / .svfs-prev', __( 'Next and Previous in steps.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-next { }' ),
					array( '.svfs-review', __( 'Review button placed with a [confirm] tag.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-review { }' ),
					array( '.svfs-actions', __( 'Row holding Review/Send and Back, when it holds only buttons.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-actions { gap: 1em; }' ),
					array( '.svfs-step-nav', __( 'Row holding Previous and Next.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-step-nav { margin-top: 2em; }' ),
				),
			),
			array(
				__( 'Progress display', 'form-steps-for-contact-form-7' ),
				'',
				array(
					array( '.svfs-progress', __( 'The whole progress display.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-progress { margin-bottom: 2em; }' ),
					array( '.svfs-progress-numbers li', __( 'One step; also .is-current and .is-done.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-progress-numbers li.is-done { opacity: .8; }' ),
					array( '.svfs-progress-num', __( 'The number circle.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .is-current .svfs-progress-num { background: var(--svfs-accent); color: #fff; }' ),
					array( '.svfs-progress-title', __( 'The step title.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-progress-title { font-size: .9em; }' ),
					array( '.svfs-progress-track / .svfs-progress-fill', __( 'The bar and its filled part.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-progress-track { height: 10px; }' ),
					array( '.svfs-progress-label', __( 'Text under the bar, "Step 1 of 3".', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-progress-label { }' ),
				),
			),
			array(
				__( 'Confirm screen and thank-you screen', 'form-steps-for-contact-form-7' ),
				'',
				array(
					array( '.svfs-intro', __( 'Heading and message above the answers.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-intro { padding: 1em; background: #eff6ff; }' ),
					array( '.svfs-intro-title / .svfs-intro-text', __( 'The heading and the message.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-intro-title { font-size: 1.4em; }' ),
					array( '.svfs-value', __( 'An answer shown in place of its field (In place style).', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-value { border-bottom-style: solid; }' ),
					array( '.svfs-summary table / th / td', __( 'The summary table, its labels and answers.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-summary th { color: #475569; }' ),
					array( '.svfs-thanks', __( 'The thank-you screen.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-thanks { text-align: center; }' ),
				),
			),
			array(
				__( 'Thank-you templates', 'form-steps-for-contact-form-7' ),
				__( 'Ready-made classes for your thank-you HTML. [field-name] shows what the visitor sent.', 'form-steps-for-contact-form-7' ),
				array(
					array( '.svfs-ty', __( 'Centered block with padding.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-ty { padding: 3em 2em; }' ),
					array( '.svfs-ty-card', __( 'Adds a border and rounded corners.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-ty-card { border: 0; box-shadow: 0 10px 30px rgba(0,0,0,.08); }' ),
					array( '.svfs-ty-icon', __( 'Green check circle (an empty span).', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-ty-icon { width: 4em; height: 4em; }' ),
					array( '.svfs-ty-title / .svfs-ty-text', __( 'Heading and paragraph.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-ty-title { font-size: 2em; }' ),
					array( '.svfs-ty-steps', __( 'Numbered list of next steps (an ol).', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-ty-steps li::before { border-radius: 4px; }' ),
					array( '.svfs-ty-actions / .svfs-ty-button / .svfs-ty-link', __( 'Row of links, main button, text link.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-ty-button { padding: 1em 2em; }' ),
					array( '.svfs-tag', __( 'An answer filled in from the sent form.', 'form-steps-for-contact-form-7' ), '.wpcf7 form.svfs-id-12 .svfs-tag { font-weight: 700; }' ),
				),
			),
		);

		foreach ( $groups as $group ) :
			?>
			<h3><?php echo esc_html( $group[0] ); ?></h3>
			<?php if ( '' !== $group[1] ) : ?>
				<p class="description"><?php echo esc_html( $group[1] ); ?></p>
			<?php endif; ?>
			<table class="widefat striped svfs-ref svfs-ref-wide">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Class or variable', 'form-steps-for-contact-form-7' ); ?></th>
						<th scope="col"><?php esc_html_e( 'What it is', 'form-steps-for-contact-form-7' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Example', 'form-steps-for-contact-form-7' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $group[2] as $row ) : ?>
						<tr>
							<td><code><?php echo esc_html( $row[0] ); ?></code></td>
							<td><?php echo esc_html( $row[1] ); ?></td>
							<td><?php echo '' !== $row[2] ? '<code class="svfs-example">' . esc_html( $row[2] ) . '</code>' : ''; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		endforeach;
		?>
		<h3><?php esc_html_e( 'What the plugin adds to your form', 'form-steps-for-contact-form-7' ); ?></h3>
		<p class="description"><?php esc_html_e( 'A multi-step form with the confirm screen on, simplified. Your own fields and markup stay as you wrote them.', 'form-steps-for-contact-form-7' ); ?></p>
		<pre class="svfs-code"><code><?php echo esc_html( self::markup_sample() ); ?></code></pre>
		<?php
	}

	/**
	 * Simplified markup of a form using every feature, for the class reference.
	 *
	 * @return string
	 */
	private static function markup_sample() {
		return implode(
			"\n",
			array(
				'<form class="wpcf7-form svfs-form svfs-id-12 svfs-has-steps svfs-style-inplace',
				'             svfs-btn-filled svfs-align-split svfs-state-input">',
				'',
				'  <div class="svfs-intro" hidden>           <!-- shown on the confirm screen -->',
				'    <p class="svfs-intro-title">Check your entries</p>',
				'    <p class="svfs-intro-text">Please check your entries…</p>',
				'  </div>',
				'',
				'  <ol class="svfs-progress svfs-progress-numbers">',
				'    <li class="is-current"><span class="svfs-progress-num">1</span>',
				'                           <span class="svfs-progress-title">Contact</span></li>',
				'    <li><span class="svfs-progress-num">2</span> …</li>',
				'  </ol>',
				'',
				'  <!-- your fields for step 1 -->',
				'  <p class="svfs-step-nav">',
				'    <button class="svfs-next">Next</button>',
				'  </p>',
				'',
				'  <!-- your fields for the last step -->',
				'  <p class="svfs-actions">',
				'    <button class="svfs-back" hidden>Back</button>   <!-- confirm screen only -->',
				'    <button class="svfs-prev svfs-prev-last">Previous</button>',
				'    <input class="wpcf7-submit" type="submit" value="Review">',
				'  </p>',
				'',
				'  <!-- confirm screen, In place style: each answer replaces its field -->',
				'  <span class="wpcf7-form-control-wrap svfs-has-value">',
				'    <span class="svfs-value">Hana Tanaka</span>',
				'  </span>',
				'',
				'  <!-- confirm screen, Summary table style -->',
				'  <div class="svfs-summary"><table><tr><th>Name</th><td>Hana Tanaka</td></tr></table></div>',
				'',
				'  <div class="svfs-thanks" hidden>…</div>     <!-- after sending -->',
				'</form>',
			)
		);
	}

	/**
	 * Examples: complete forms to try live, copy, or create with one click.
	 */
	private function tab_examples() {
		$examples = Examples::all();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Selection only.
		$key = isset( $_GET['example'] ) ? sanitize_key( wp_unslash( $_GET['example'] ) ) : '';
		if ( ! isset( $examples[ $key ] ) ) {
			$key = (string) key( $examples );
		}
		$example = $examples[ $key ];
		$labels  = array(
			'inplace'  => __( 'In place', 'form-steps-for-contact-form-7' ),
			'table'    => __( 'Summary table', 'form-steps-for-contact-form-7' ),
			'numbers'  => __( 'Numbered steps', 'form-steps-for-contact-form-7' ),
			'bar'      => __( 'Progress bar', 'form-steps-for-contact-form-7' ),
			'none'     => __( 'None', 'form-steps-for-contact-form-7' ),
			'message'  => __( 'Message below the form', 'form-steps-for-contact-form-7' ),
			'screen'   => __( 'Thank-you screen', 'form-steps-for-contact-form-7' ),
			'redirect' => __( 'Go to another page', 'form-steps-for-contact-form-7' ),
		);
		$settings = $example['settings'];
		$has_steps = false !== strpos( $example['form'], '[step ' );
		?>
		<p class="svfs-lead"><?php esc_html_e( 'Complete forms with their design. Try one live, copy its parts, or create it with one click and edit it like any other form.', 'form-steps-for-contact-form-7' ); ?></p>

		<div class="svfs-examples">
			<nav class="svfs-ex-list" aria-label="<?php esc_attr_e( 'Examples', 'form-steps-for-contact-form-7' ); ?>">
				<?php foreach ( $examples as $slug => $item ) : ?>
					<a class="svfs-ex-item <?php echo esc_attr( $slug === $key ? 'is-current' : '' ); ?>" href="<?php echo esc_url( self::url( 'examples', array( 'example' => $slug ) ) ); ?>" <?php echo $slug === $key ? 'aria-current="page"' : ''; ?>>
						<span class="svfs-ex-swatch" aria-hidden="true">
							<span style="<?php echo esc_attr( 'background:' . $item['palette']['head'] ); ?>"></span>
							<span style="<?php echo esc_attr( 'background:' . $item['palette']['card'] ); ?>"></span>
							<span style="<?php echo esc_attr( 'background:' . $item['palette']['accent'] ); ?>"></span>
						</span>
						<span class="svfs-ex-name"><?php echo esc_html( $item['title'] ); ?></span>
						<span class="svfs-ex-feats"><?php echo esc_html( implode( ' · ', $item['features'] ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>

			<section class="svfs-ex-main" data-svfs-example="<?php echo esc_attr( $key ); ?>">
				<div class="svfs-ex-head">
					<div>
						<h2><?php echo esc_html( $example['title'] ); ?></h2>
						<p class="description"><?php echo esc_html( $example['summary'] ); ?></p>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="svfs-ex-create">
						<input type="hidden" name="action" value="<?php echo esc_attr( Examples::CREATE_ACTION ); ?>" />
						<input type="hidden" name="example" value="<?php echo esc_attr( $key ); ?>" />
						<?php wp_nonce_field( Examples::CREATE_ACTION ); ?>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Create this form', 'form-steps-for-contact-form-7' ); ?></button>
						<?php if ( current_user_can( 'edit_pages' ) ) : ?>
							<label><input type="checkbox" name="with_page" value="1" checked /> <?php esc_html_e( 'Also create a draft page with it', 'form-steps-for-contact-form-7' ); ?></label>
						<?php endif; ?>
					</form>
				</div>

				<div class="svfs-ex-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Example parts', 'form-steps-for-contact-form-7' ); ?>">
					<?php
					$parts = array(
						'preview'  => __( 'Live preview', 'form-steps-for-contact-form-7' ),
						'form'     => __( 'Form', 'form-steps-for-contact-form-7' ),
						'css'      => __( 'CSS', 'form-steps-for-contact-form-7' ),
						'html'     => __( 'HTML output', 'form-steps-for-contact-form-7' ),
						'settings' => __( 'Settings', 'form-steps-for-contact-form-7' ),
						'ai'       => __( 'Restyle with AI', 'form-steps-for-contact-form-7' ),
					);
					foreach ( $parts as $part => $label ) :
						?>
						<button type="button" role="tab" class="svfs-ex-tab" id="<?php echo esc_attr( 'svfs-ex-tab-' . $part ); ?>" aria-controls="<?php echo esc_attr( 'svfs-ex-panel-' . $part ); ?>" aria-selected="<?php echo 'preview' === $part ? 'true' : 'false'; ?>" <?php echo 'preview' === $part ? '' : 'tabindex="-1"'; ?>><?php echo esc_html( $label ); ?></button>
					<?php endforeach; ?>
				</div>

				<div class="svfs-ex-panel" role="tabpanel" id="svfs-ex-panel-preview" aria-labelledby="svfs-ex-tab-preview">
					<div class="svfs-ex-bar">
						<span class="svfs-muted"><?php esc_html_e( 'This is the real form. Press Review, Next, Back and Send: nothing is saved or sent.', 'form-steps-for-contact-form-7' ); ?></span>
						<span class="svfs-ex-sizes">
							<button type="button" class="button svfs-ex-size is-active" data-width=""><?php esc_html_e( 'Desktop', 'form-steps-for-contact-form-7' ); ?></button>
							<button type="button" class="button svfs-ex-size" data-width="390"><?php esc_html_e( 'Phone', 'form-steps-for-contact-form-7' ); ?></button>
							<button type="button" class="button svfs-ex-reload"><?php esc_html_e( 'Start over', 'form-steps-for-contact-form-7' ); ?></button>
						</span>
					</div>
					<div class="svfs-ex-stage">
						<iframe class="svfs-ex-frame" title="<?php echo esc_attr( $example['title'] ); ?>" src="<?php echo esc_url( Examples::preview_url( $key ) ); ?>"></iframe>
					</div>
				</div>

				<div class="svfs-ex-panel" role="tabpanel" id="svfs-ex-panel-form" aria-labelledby="svfs-ex-tab-form" hidden>
					<?php $this->code_box( $example['form'], __( 'Paste into Contact Form 7 → your form → Form tab.', 'form-steps-for-contact-form-7' ) ); ?>
				</div>

				<div class="svfs-ex-panel" role="tabpanel" id="svfs-ex-panel-css" aria-labelledby="svfs-ex-tab-css" hidden>
					<?php $this->code_box( $example['css'], __( 'This design is built in: "Create this form" selects it, or choose it in any form\'s Steps tab → Design → Form style. To change its colors, paste this into WordPress\'s Additional CSS and edit the values.', 'form-steps-for-contact-form-7' ) ); ?>
				</div>

				<div class="svfs-ex-panel" role="tabpanel" id="svfs-ex-panel-html" aria-labelledby="svfs-ex-tab-html" hidden>
					<div class="svfs-ex-bar">
						<span class="svfs-muted"><?php esc_html_e( 'The markup in the live preview right now, with the plugin\'s classes highlighted. Go to the confirm screen or another step in the preview, then refresh to see that markup.', 'form-steps-for-contact-form-7' ); ?></span>
						<span class="svfs-ex-sizes">
							<button type="button" class="button svfs-ex-html-refresh"><?php esc_html_e( 'Refresh from preview', 'form-steps-for-contact-form-7' ); ?></button>
							<button type="button" class="button svfs-copy-code"><?php esc_html_e( 'Copy', 'form-steps-for-contact-form-7' ); ?></button>
							<span class="svfs-saved" aria-live="polite"></span>
						</span>
					</div>
					<pre class="svfs-code svfs-ex-html"><code></code></pre>
				</div>

				<div class="svfs-ex-panel" role="tabpanel" id="svfs-ex-panel-settings" aria-labelledby="svfs-ex-tab-settings" hidden>
					<p class="description"><?php esc_html_e( '"Create this form" sets all of these for you. To build it by hand, set them in the form\'s Steps tab.', 'form-steps-for-contact-form-7' ); ?></p>
					<table class="widefat striped svfs-ref">
						<tbody>
							<tr><th scope="row"><?php esc_html_e( 'Confirm screen', 'form-steps-for-contact-form-7' ); ?></th><td><?php echo esc_html( $settings['confirm'] ? $labels[ $settings['confirm_style'] ] : __( 'Off', 'form-steps-for-contact-form-7' ) ); ?></td></tr>
							<?php if ( '' !== $settings['confirm_heading'] ) : ?>
								<tr><th scope="row"><?php esc_html_e( 'Heading', 'form-steps-for-contact-form-7' ); ?></th><td><?php echo esc_html( $settings['confirm_heading'] ); ?></td></tr>
							<?php endif; ?>
							<?php if ( $has_steps ) : ?>
								<tr><th scope="row"><?php esc_html_e( 'Progress display', 'form-steps-for-contact-form-7' ); ?></th><td><?php echo esc_html( isset( $labels[ $settings['progress'] ] ) ? $labels[ $settings['progress'] ] : $labels['numbers'] ); ?></td></tr>
							<?php endif; ?>
							<tr><th scope="row"><?php esc_html_e( 'After sending', 'form-steps-for-contact-form-7' ); ?></th><td><?php echo esc_html( $labels[ $settings['after_send'] ] ); ?></td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Button design', 'form-steps-for-contact-form-7' ); ?></th><td><code><?php echo esc_html( $settings['design']['style'] . ' · ' . $settings['design']['align'] . ' · ' . $settings['design']['accent'] ); ?></code></td></tr>
							<?php foreach ( array_filter( $settings['labels'] ) as $label_key => $label_text ) : ?>
								<tr><th scope="row"><?php echo esc_html( ucfirst( $label_key ) ); ?></th><td><?php echo esc_html( $label_text ); ?></td></tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php if ( '' !== $settings['thanks_html'] ) : ?>
						<h3><?php esc_html_e( 'Thank-you screen HTML', 'form-steps-for-contact-form-7' ); ?></h3>
						<?php $this->code_box( $settings['thanks_html'], __( 'Paste into Steps tab → After sending → Thank-you screen.', 'form-steps-for-contact-form-7' ) ); ?>
					<?php endif; ?>
				</div>

				<div class="svfs-ex-panel" role="tabpanel" id="svfs-ex-panel-ai" aria-labelledby="svfs-ex-tab-ai" hidden>
					<ol class="svfs-ai-steps">
						<li><?php esc_html_e( 'Describe the look you want: brand colors, rounded or sharp, light or dark, font feel.', 'form-steps-for-contact-form-7' ); ?></li>
						<li><?php esc_html_e( 'Copy the prompt and paste it into ChatGPT, Claude or Gemini.', 'form-steps-for-contact-form-7' ); ?></li>
						<li><?php esc_html_e( 'Create this form, then paste the CSS you get into WordPress\'s Additional CSS (Appearance → Customize, or Appearance → Editor → Styles).', 'form-steps-for-contact-form-7' ); ?></li>
					</ol>
					<label class="screen-reader-text" for="svfs-ex-wish"><?php esc_html_e( 'The look you want', 'form-steps-for-contact-form-7' ); ?></label>
					<textarea id="svfs-ex-wish" class="large-text" rows="2" placeholder="<?php esc_attr_e( 'For example: our brand color #ff6a00, white card, soft shadows, rounded inputs, friendly and modern', 'form-steps-for-contact-form-7' ); ?>"></textarea>
					<p>
						<button type="button" class="button button-primary svfs-ex-ai-copy" data-svfs-prompt="<?php echo esc_attr( self::restyle_prompt( $example ) ); ?>"><?php esc_html_e( 'Copy AI prompt', 'form-steps-for-contact-form-7' ); ?></button>
						<span class="svfs-saved" aria-live="polite"></span>
					</p>
				</div>
			</section>
		</div>
		<?php
	}

	/**
	 * Read-only code block with a Copy button.
	 *
	 * @param string $code Code.
	 * @param string $hint Where to paste it.
	 */
	private function code_box( $code, $hint ) {
		?>
		<div class="svfs-code-box">
			<div class="svfs-ex-bar">
				<span class="svfs-muted"><?php echo esc_html( $hint ); ?></span>
				<span class="svfs-ex-sizes">
					<button type="button" class="button svfs-copy-code"><?php esc_html_e( 'Copy', 'form-steps-for-contact-form-7' ); ?></button>
					<span class="svfs-saved" aria-live="polite"></span>
				</span>
			</div>
			<pre class="svfs-code"><code><?php echo esc_html( $code ); ?></code></pre>
		</div>
		<?php
	}

	/**
	 * AI prompt to restyle an example. {wish} is filled in by the script.
	 *
	 * @param array $example Example.
	 * @return string
	 */
	private static function restyle_prompt( array $example ) {
		$sel = '.wpcf7 form.svfs-skin-' . $example['skin'];
		return implode(
			"\n",
			array(
				'Restyle a WordPress contact form (Contact Form 7 with the "Form Steps" plugin). Return only CSS, for WordPress\'s Additional CSS.',
				'The form already uses a built-in style; your CSS is added on top of it.',
				'',
				'Rules:',
				'- Start every selector with ' . $sel . ' so it affects only this style. Example: ' . $sel . ' .ex-title { font-size: 1.75em; }',
				'- Style the whole card: background, header band (.ex-head, .ex-title, .ex-sub), labels, inputs, selects, textareas, checkboxes and radios, errors (.wpcf7-not-valid-tip), and the response message (.wpcf7-response-output).',
				'- Buttons use CSS variables. Set them on ' . $sel . ': --svfs-accent (button and progress color), --svfs-accent-hover, --svfs-accent-text, --svfs-accent-soft (light tint), --svfs-radius, --svfs-card-bg, --svfs-success.',
				'- Plugin parts you can style: .svfs-progress, .svfs-progress-num, .is-current .svfs-progress-num, .svfs-progress-track, .svfs-progress-fill, .svfs-progress-label, .svfs-intro, .svfs-intro-title, .svfs-intro-text, .svfs-value (answer on the confirm screen), .svfs-summary th / td (summary table), .svfs-actions and .svfs-step-nav (button rows), .svfs-thanks and .svfs-ty-* (thank-you screen).',
				'- Keep it accessible: text contrast at least 4.5:1, visible focus outlines. Make it work at 360px wide.',
				'- No @import, no external fonts, no !important unless needed to beat a theme.',
				'',
				'The form (Contact Form 7 template):',
				'---',
				$example['form'],
				'---',
				'',
				'Current colors (CSS variables of the built-in style):',
				'---',
				$example['css'],
				'---',
				'',
				'The look I want: {wish}',
			)
		);
	}

	/**
	 * Small card about the upcoming Conditional Steps add-on. Shown only on this
	 * plugin's own page. The link appears once its page exists (see Links::LIVE).
	 */
	private function promo_card() {
		$url = Links::online( 'conditional' );
		?>
		<aside class="svfs-promo" aria-label="<?php esc_attr_e( 'From the same developer', 'form-steps-for-contact-form-7' ); ?>">
			<span class="svfs-promo-icon" aria-hidden="true"></span>
			<div class="svfs-promo-text">
				<p class="svfs-promo-title">
					<?php esc_html_e( 'Conditional Steps', 'form-steps-for-contact-form-7' ); ?>
					<span class="svfs-badge"><?php esc_html_e( 'Coming soon', 'form-steps-for-contact-form-7' ); ?></span>
				</p>
				<p><?php esc_html_e( 'Show or hide fields and whole steps based on earlier answers. A free add-on for Form Steps, from Amit Jyoti Shaw.', 'form-steps-for-contact-form-7' ); ?></p>
			</div>
			<?php if ( '' !== $url ) : ?>
				<a class="button" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Learn more', 'form-steps-for-contact-form-7' ); ?></a>
			<?php endif; ?>
		</aside>
		<?php
	}

	/**
	 * Help entries for the "See example" links of the tips: title, text, picture.
	 *
	 * @return array<string,array>
	 */
	private function help_topics() {
		return array(
			'confirm'       => array(
				__( 'Confirm screen', 'form-steps-for-contact-form-7' ),
				__( 'Turn it on in Contact → Form Steps or in the form\'s Steps tab. The submit button becomes Review. After Review the visitor sees the answers with Back and Send buttons. Required fields are checked first, exactly as Contact Form 7 does.', 'form-steps-for-contact-form-7' ),
				'inplace',
			),
			'confirm_style' => array(
				__( 'In place or summary table', 'form-steps-for-contact-form-7' ),
				__( 'In place keeps your form and theme design and swaps every field for its answer. Summary table hides the form and shows the answers as a two-column table. Choose it in the form\'s Steps tab.', 'form-steps-for-contact-form-7' ),
				'table',
			),
			'field_labels'  => array(
				__( 'Renaming labels in the summary table', 'form-steps-for-contact-form-7' ),
				__( 'The table finds the label written next to each field, for example "Your name" in <label> Your name [text your-name] </label>. In the Steps tab, the table "Rename labels in the summary table" lists every field with the label found. Type a new name to replace it.', 'form-steps-for-contact-form-7' ),
				'',
			),
			'buttons'       => array(
				__( 'Button texts', 'form-steps-for-contact-form-7' ),
				__( 'Set site-wide texts in the Defaults tab. Each form can override them in its Steps tab. An empty box always means "use the default".', 'form-steps-for-contact-form-7' ),
				'',
			),
			'design'        => array(
				__( 'Button design', 'form-steps-for-contact-form-7' ),
				__( 'Choose Theme default, Filled, Outline or Rounded buttons, their colors, their position (left, centre, right, full width, or Back left and Send right), their order, and whether Back and Previous are shown. Set site-wide choices in the Defaults tab; each form can override them in its Steps tab.', 'form-steps-for-contact-form-7' ),
				'',
			),
			'styles'        => array(
				__( 'Form styles and your own CSS', 'form-steps-for-contact-form-7' ),
				__( 'Choose a built-in Form style (Navy, Teal, Warm, Sky or Dark card) in the form\'s Steps tab → Design, and set the button colors there. For anything more, use WordPress\'s Additional CSS: .wpcf7 form.svfs-id-12 targets the form with ID 12. The reference below lists every class.', 'form-steps-for-contact-form-7' ),
				'',
			),
			'thanks'        => array(
				__( 'Thank-you screen', 'form-steps-for-contact-form-7' ),
				__( 'Choose "Thank-you screen" under After sending. Start from one of four templates, edit the HTML, and check the live preview. Write a field name in square brackets, for example [your-name], to show what the visitor sent. "Create with AI" copies a prompt with all classes and fields for ChatGPT, Claude or Gemini.', 'form-steps-for-contact-form-7' ),
				'screen',
			),
			'progress'      => array(
				__( 'Progress display', 'form-steps-for-contact-form-7' ),
				__( 'Forms split with [step] tags show their progress at the top: numbered steps with titles, a bar with "Step 1 of 3", or nothing. When the confirm screen is on, it is the last step.', 'form-steps-for-contact-form-7' ),
				'numbers',
			),
			'after_send'    => array(
				__( 'After sending', 'form-steps-for-contact-form-7' ),
				__( 'Keep Contact Form 7\'s message below the form, replace the whole form with a thank-you screen, or send the visitor to another page. The email is always sent first.', 'form-steps-for-contact-form-7' ),
				'screen',
			),
		);
	}

	/**
	 * Quick guide, tag reference and developer card.
	 */
	private function tab_help() {
		$tags = array(
			array( '[step "Contact details"]', __( 'Starts a new step with this title. Put it on its own line. All step tags must be at the same level of the form.', 'form-steps-for-contact-form-7' ) ),
			array( '[confirm "Review"]', __( 'Optional Review button at an exact place. Same tag as "Contact Form 7 add confirm".', 'form-steps-for-contact-form-7' ) ),
			array( '[back "Back"]', __( 'Optional Back button for the confirm screen. Same tag as "Contact Form 7 add confirm".', 'form-steps-for-contact-form-7' ) ),
		);
		$classes = array(
			array( 'svfs-skip', __( 'Field is not shown on the confirm screen. "avoid-confirm" from Confirm Plus works too.', 'form-steps-for-contact-form-7' ) ),
			array( 'svfs-keep', __( 'Element stays visible on every screen and belongs to no step, for example a form header.', 'form-steps-for-contact-form-7' ) ),
			array( 'wpcf7c-elm-step1', __( 'Shown only while filling in the form.', 'form-steps-for-contact-form-7' ) ),
			array( 'wpcf7c-elm-step2', __( 'Shown only on the confirm screen.', 'form-steps-for-contact-form-7' ) ),
			array( 'wpcf7c-elm-step3', __( 'Shown only after sending.', 'form-steps-for-contact-form-7' ) ),
		);
		?>
		<div class="svfs-columns">
			<section class="svfs-card-box">
				<h2 id="svfs-help-docs"><?php esc_html_e( 'Quick start', 'form-steps-for-contact-form-7' ); ?></h2>
				<ol class="svfs-steps-list">
					<li><?php esc_html_e( 'Open the Forms tab and turn on the confirm screen for a form. It is live at once.', 'form-steps-for-contact-form-7' ); ?></li>
					<li><?php esc_html_e( 'For several steps, open the form, click the "step" button in the Form tab, and add a step tag where each new step begins.', 'form-steps-for-contact-form-7' ); ?></li>
					<li><?php esc_html_e( 'Fine-tune button texts, the confirm style and the thank-you screen in the form\'s Steps tab.', 'form-steps-for-contact-form-7' ); ?></li>
				</ol>
				<p><?php esc_html_e( 'Review and Next only check the entries. No email is sent, no captcha is used up, and add-ons that save or forward entries run once, when the visitor presses Send.', 'form-steps-for-contact-form-7' ); ?></p>

				<h2><?php esc_html_e( 'Settings explained', 'form-steps-for-contact-form-7' ); ?></h2>
				<?php foreach ( $this->help_topics() as $topic => $entry ) : ?>
					<div class="svfs-help-topic" id="<?php echo esc_attr( 'svfs-help-' . $topic ); ?>">
						<?php
						if ( $entry[2] ) {
							Editor_Panel::picture( $entry[2] );
						}
						?>
						<div>
							<h3><?php echo esc_html( $entry[0] ); ?></h3>
							<p><?php echo esc_html( $entry[1] ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>

				<h2 id="svfs-help-steps"><?php esc_html_e( 'Form tags', 'form-steps-for-contact-form-7' ); ?></h2>
				<table class="widefat striped svfs-ref">
					<?php foreach ( $tags as $row ) : ?>
						<tr><td><code><?php echo esc_html( $row[0] ); ?></code></td><td><?php echo esc_html( $row[1] ); ?></td></tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'Helper classes for your form', 'form-steps-for-contact-form-7' ); ?></h2>
				<table class="widefat striped svfs-ref">
					<?php foreach ( $classes as $row ) : ?>
						<tr><td><code><?php echo esc_html( $row[0] ); ?></code></td><td><?php echo esc_html( $row[1] ); ?></td></tr>
					<?php endforeach; ?>
				</table>

				<h2 id="svfs-help-classes"><?php esc_html_e( 'Styling reference', 'form-steps-for-contact-form-7' ); ?></h2>
				<p><?php esc_html_e( 'Every class and variable the plugin uses, with a rule you can copy into WordPress\'s Additional CSS (Appearance → Customize, or Appearance → Editor → Styles). In the examples, 12 is the form ID.', 'form-steps-for-contact-form-7' ); ?></p>
				<?php $this->class_reference(); ?>

				<h2><?php esc_html_e( 'Switching from another confirm plugin', 'form-steps-for-contact-form-7' ); ?></h2>
				<p><?php esc_html_e( 'Forms built for "Contact Form 7 add confirm" and its successors work without edits: the same tags and classes are supported. Deactivate the old plugin; Form Steps shows a button for it when it detects one.', 'form-steps-for-contact-form-7' ); ?></p>
			</section>

			<aside class="svfs-card-box svfs-dev">
				<h2><?php esc_html_e( 'About', 'form-steps-for-contact-form-7' ); ?></h2>
				<p class="svfs-dev-name">Amit Shaw · Amit Jyoti Shaw</p>
				<p><?php esc_html_e( 'WordPress developer for about four years, with more than 300 website projects delivered.', 'form-steps-for-contact-form-7' ); ?></p>
				<ul class="svfs-dev-links">
					<li><a href="https://github.com/Amitoshi" target="_blank" rel="noopener noreferrer">github.com/Amitoshi</a></li>
					<li><a href="mailto:amitshaw9818@gmail.com">amitshaw9818@gmail.com</a></li>
				</ul>
				<p><?php esc_html_e( 'Free plugin. No tracking, no ads, no account needed.', 'form-steps-for-contact-form-7' ); ?></p>
				<p>
					<a class="button" href="<?php echo esc_url( Links::SUPPORT ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get support', 'form-steps-for-contact-form-7' ); ?></a>
					<a class="button" href="<?php echo esc_url( Links::REVIEW ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Leave a review', 'form-steps-for-contact-form-7' ); ?></a>
				</p>
				<p class="svfs-muted"><?php esc_html_e( 'Need a custom form flow or a WordPress site built? Amit Jyoti Shaw takes on client projects.', 'form-steps-for-contact-form-7' ); ?></p>
				<?php $this->promo_card(); ?>
			</aside>
		</div>
		<?php
	}
}
