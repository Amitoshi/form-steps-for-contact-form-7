<?php
/**
 * Front-end form output: classes, config, hidden fields, extra blocks, assets.
 *
 * @package AmitShaw\FormSteps
 */

namespace AmitShaw\FormSteps;

defined( 'ABSPATH' ) || exit;

/**
 * Adds what the browser script needs to forms that use the confirm screen or steps.
 * Forms without either feature are left exactly as Contact Form 7 renders them,
 * and pages without such forms load no plugin assets.
 */
final class Form_Render {

	const HANDLE = 'svfs-form-steps';

	/**
	 * Resolved settings per form ID; false when the form uses no feature.
	 *
	 * @var array<int,array|false>
	 */
	private $cache = array();

	/**
	 * Registers hooks.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_filter( 'wpcf7_form_class_attr', array( $this, 'form_class' ) );
		add_filter( 'wpcf7_form_additional_atts', array( $this, 'form_atts' ) );
		add_filter( 'wpcf7_form_hidden_fields', array( $this, 'hidden_fields' ) );
		add_filter( 'wpcf7_form_elements', array( $this, 'form_elements' ), 20 );
	}

	/**
	 * Registers (does not enqueue) the front-end assets.
	 */
	public function register_assets() {
		if ( wp_script_is( self::HANDLE, 'registered' ) ) {
			return;
		}
		wp_register_script( self::HANDLE, Plugin::asset_url( 'js/form-steps', 'js' ), array(), SVFS_VERSION, true );
		wp_register_style( self::HANDLE, Plugin::asset_url( 'css/form-steps', 'css' ), array(), SVFS_VERSION );
		Design::register_skins();

		// Classic themes print styles enqueued during the content in the footer, which can
		// briefly show all steps. When the page visibly contains a form, load the small
		// stylesheet in the head instead. Other pages still load nothing.
		if ( 'wp_enqueue_scripts' === current_action() ) {
			$found = $this->page_has_form();
			if ( $found ) {
				wp_enqueue_style( self::HANDLE );
			}
			if ( 'skin' === $found ) {
				wp_enqueue_style( Design::SKINS_HANDLE );
			}
		}
	}

	/**
	 * Whether the current singular post shows a form that uses the confirm screen or steps,
	 * through a Contact Form 7 shortcode or block. Forms placed elsewhere (widgets,
	 * page builders) still get the stylesheet when they render.
	 *
	 * @return string|bool "skin" when a found form uses a built-in Form style, true
	 *                      for other forms with the features, false otherwise.
	 */
	private function page_has_form() {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_post();
		if ( ! $post instanceof \WP_Post || false === strpos( $post->post_content, 'contact-form' ) ) {
			return false;
		}

		$refs  = array();
		$regex = get_shortcode_regex( array( 'contact-form-7', 'contact-form' ) );
		if ( preg_match_all( '/' . $regex . '/', $post->post_content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $shortcode ) {
				$atts = shortcode_parse_atts( $shortcode[3] );
				if ( is_array( $atts ) && ! empty( $atts['id'] ) ) {
					$refs[] = (string) $atts['id'];
				}
			}
		}
		if ( has_block( 'contact-form-7/contact-form-selector', $post ) ) {
			$this->block_refs( parse_blocks( $post->post_content ), $refs );
		}

		$found = false;
		foreach ( array_unique( $refs ) as $ref ) {
			$form = $this->find_form( $ref );
			if ( $form && ( Form_Settings::confirm_enabled( $form ) || Form_Settings::steps_enabled( $form ) ) ) {
				$found  = true;
				$design = Design::resolve( Form_Settings::get( $form->id() )['design'], Global_Settings::get()['design'] );
				if ( 'none' !== $design['skin'] ) {
					return 'skin';
				}
			}
		}
		return $found;
	}

	/**
	 * Collects form IDs or hashes from Contact Form 7 blocks, including nested blocks.
	 *
	 * @param array    $blocks Parsed blocks.
	 * @param string[] $refs   Collected references, passed by reference.
	 */
	private function block_refs( array $blocks, array &$refs ) {
		foreach ( $blocks as $block ) {
			if ( 'contact-form-7/contact-form-selector' === $block['blockName'] ) {
				if ( ! empty( $block['attrs']['hash'] ) ) {
					$refs[] = (string) $block['attrs']['hash'];
				} elseif ( ! empty( $block['attrs']['id'] ) ) {
					$refs[] = (string) $block['attrs']['id'];
				}
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->block_refs( $block['innerBlocks'], $refs );
			}
		}
	}

	/**
	 * Finds a contact form by numeric ID or by Contact Form 7's short hash.
	 *
	 * @param string $ref ID or hash.
	 * @return \WPCF7_ContactForm|null
	 */
	private function find_form( $ref ) {
		if ( ctype_digit( $ref ) ) {
			$form = wpcf7_contact_form( (int) $ref );
			if ( $form ) {
				return $form;
			}
		}
		if ( function_exists( 'wpcf7_get_contact_form_by_hash' ) ) {
			$form = wpcf7_get_contact_form_by_hash( $ref );
			if ( $form ) {
				return $form;
			}
		}
		return null;
	}

	/**
	 * Settings of the form being rendered, or null when it uses no feature.
	 *
	 * @return array|null
	 */
	private function current() {
		if ( ! function_exists( 'wpcf7_get_current_contact_form' ) ) {
			return null;
		}
		$form = wpcf7_get_current_contact_form();
		if ( ! $form instanceof \WPCF7_ContactForm ) {
			return null;
		}

		$id = (int) $form->id();
		if ( ! array_key_exists( $id, $this->cache ) ) {
			$settings = Form_Settings::resolved( $form );
			if ( $settings['confirm'] || $settings['steps'] ) {
				$settings['explicit'] = array() !== $form->scan_form_tags( array( 'type' => 'confirm' ) );
				$settings['id']       = $id;
				$this->cache[ $id ]   = $settings;
			} else {
				$this->cache[ $id ] = false;
			}
		}
		return $this->cache[ $id ] ? $this->cache[ $id ] : null;
	}

	/**
	 * Adds state classes to the form element.
	 *
	 * @param string $classes Space-separated classes.
	 * @return string
	 */
	public function form_class( $classes ) {
		$settings = $this->current();
		if ( null === $settings ) {
			return $classes;
		}
		$classes .= ' svfs-form svfs-id-' . $settings['id'] . ' svfs-style-' . $settings['confirm_style'];
		$classes .= ' ' . implode( ' ', Design::classes( $settings['design'] ) );
		if ( $settings['explicit'] ) {
			$classes .= ' svfs-explicit';
		}
		if ( $settings['steps'] ) {
			$classes .= ' svfs-has-steps';
		}
		return $classes;
	}

	/**
	 * Passes settings to the browser script as a data attribute and enqueues assets.
	 *
	 * @param array $atts Extra form attributes.
	 * @return array
	 */
	public function form_atts( $atts ) {
		$settings = $this->current();
		if ( null === $settings ) {
			return $atts;
		}

		$config = array(
			'confirm'     => (bool) $settings['confirm'],
			'style'       => $settings['confirm_style'],
			'explicit'    => (bool) $settings['explicit'],
			'steps'       => (bool) $settings['steps'],
			'progress'    => $settings['progress'],
			'scroll'      => (bool) $settings['scroll'],
			'empty'       => $settings['empty_values'],
			'labels'      => $settings['labels'],
			'fieldLabels' => (object) $settings['field_labels'],
			'afterSend'   => $settings['after_send'],
			'redirect'    => 'redirect' === $settings['after_send'] ? $settings['redirect_url'] : '',
		);

		if ( $settings['steps'] ) {
			$config['i18n'] = array(
				/* translators: 1: current step number, 2: total number of steps. */
				'stepOf' => __( 'Step %1$s of %2$s', 'form-steps-for-contact-form-7' ),
				/* translators: %s: step number. */
				'step'   => __( 'Step %s', 'form-steps-for-contact-form-7' ),
				'review' => _x( 'Review', 'title of the last step in the progress display', 'form-steps-for-contact-form-7' ),
			);
		}

		// wpcf7_format_atts() escapes attribute values.
		$atts['data-svfs'] = wp_json_encode( $config );

		// Chosen colors as CSS custom properties; validated hex values only.
		$style = Design::style_attr( $settings['design'] );
		if ( '' !== $style ) {
			$atts['style'] = $style;
		}

		$this->register_assets();
		wp_enqueue_script( self::HANDLE );
		wp_enqueue_style( self::HANDLE );
		if ( 'none' !== $settings['design']['skin'] ) {
			wp_enqueue_style( Design::SKINS_HANDLE );
		}

		return $atts;
	}

	/**
	 * Adds the phase field. Fields starting with an underscore never reach emails.
	 *
	 * @param array $fields Hidden fields.
	 * @return array
	 */
	public function hidden_fields( $fields ) {
		if ( null === $this->current() ) {
			return $fields;
		}
		$fields[ Phase::FIELD ]       = '';
		$fields[ Phase::STEP_FIELDS ] = '';
		return $fields;
	}

	/**
	 * Adds the confirm-screen intro and the thank-you screen, both hidden until used.
	 *
	 * @param string $html Form markup.
	 * @return string
	 */
	public function form_elements( $html ) {
		$settings = $this->current();
		if ( null === $settings ) {
			return $html;
		}

		$before = '';
		if ( $settings['confirm'] ) {
			$before = $this->intro_html( $settings );
		}

		$after = '';
		if ( 'screen' === $settings['after_send'] && '' !== trim( wp_strip_all_tags( $settings['thanks_html'] ) ) ) {
			$after = '<div class="svfs-thanks" hidden tabindex="-1">' . self::thanks_html( $settings['thanks_html'] ) . '</div>';
		}

		return $before . $html . $after;
	}

	/**
	 * Thank-you screen markup. Plain text gets paragraphs; HTML with its own blocks
	 * is kept as written. Each [field-name] of the form becomes an empty
	 * <span class="svfs-tag">, which the script fills with the sent answer as text.
	 *
	 * @param string $html Stored thank-you HTML.
	 * @return string
	 */
	public static function thanks_html( $html ) {
		$html = wp_kses_post( $html );
		if ( ! preg_match( '#<(p|div|h[1-6]|ul|ol|table|section|figure|blockquote)[\s>]#i', $html ) ) {
			$html = wpautop( $html );
		}

		$form  = function_exists( 'wpcf7_get_current_contact_form' ) ? wpcf7_get_current_contact_form() : null;
		$names = array();
		if ( $form instanceof \WPCF7_ContactForm ) {
			foreach ( $form->scan_form_tags() as $tag ) {
				if ( '' !== $tag->name ) {
					$names[ $tag->name ] = true;
				}
			}
		}
		if ( ! $names ) {
			return $html;
		}

		// Replace only in text, never inside a tag's attributes.
		$parts = preg_split( '/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $parts as $i => $part ) {
			if ( '' === $part || '<' === $part[0] ) {
				continue;
			}
			$parts[ $i ] = preg_replace_callback(
				'/\[([A-Za-z][-A-Za-z0-9_:.]*)\]/',
				static function ( $match ) use ( $names ) {
					return isset( $names[ $match[1] ] ) ? '<span class="svfs-tag" data-tag="' . esc_attr( $match[1] ) . '"></span>' : $match[0];
				},
				$part
			);
		}
		return implode( '', $parts );
	}

	/**
	 * Heading and message shown at the top of the confirm screen.
	 *
	 * @param array $settings Resolved settings.
	 * @return string
	 */
	private function intro_html( array $settings ) {
		$inner = '';
		if ( '' !== $settings['confirm_heading'] ) {
			$inner .= '<p class="svfs-intro-title">' . esc_html( $settings['confirm_heading'] ) . '</p>';
		}
		if ( '' !== $settings['confirm_message'] ) {
			$inner .= '<p class="svfs-intro-text">' . nl2br( esc_html( $settings['confirm_message'] ) ) . '</p>';
		}
		if ( '' === $inner ) {
			// Forms built for the old confirm plugins usually carry their own message,
			// so the default text is visible only on forms without [confirm] tags.
			$class  = $settings['explicit'] ? 'svfs-intro-text svfs-sr-only' : 'svfs-intro-text';
			$inner .= '<p class="' . esc_attr( $class ) . '">' . esc_html__( 'Please check your entries. If everything is correct, press Send.', 'form-steps-for-contact-form-7' ) . '</p>';
		}
		return '<div class="svfs-intro" hidden tabindex="-1">' . $inner . '</div>';
	}
}
