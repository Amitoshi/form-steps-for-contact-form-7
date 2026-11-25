<?php
/**
 * Ready-made example forms: definitions, live preview page and one-click create.
 *
 * @package AmitShaw\FormSteps
 */

namespace AmitShaw\FormSteps;

defined( 'ABSPATH' ) || exit;

/**
 * Each example is a complete Contact Form 7 form: template and Form Steps
 * settings, including one of the built-in Form styles (assets/css/skins.css). The preview page renders it unsaved, with a demo script that
 * answers Contact Form 7's requests in the browser, so nothing is stored or sent.
 */
final class Examples {

	const PREVIEW_VAR   = 'svfs_example';
	const PREVIEW_NONCE = 'svfs_example_preview';
	const CREATE_ACTION = 'svfs_create_example';
	const DEMO_HANDLE   = 'svfs-example-demo';

	/**
	 * Built-in Form style used by each example.
	 */
	const SKIN_OF = array(
		'contact'  => 'navy',
		'quote'    => 'teal',
		'booking'  => 'warm',
		'japanese' => 'sky',
		'dark'     => 'dark',
	);

	/**
	 * Registers hooks.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_preview' ), 0 );
		add_action( 'admin_post_' . self::CREATE_ACTION, array( $this, 'create' ) );
	}

	/* --------------------------------------------------------------------- */
	/* Definitions                                                           */
	/* --------------------------------------------------------------------- */

	/**
	 * All examples, keyed by slug.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		$examples = array(
			'contact' => array(
				'title'    => __( 'Contact form with confirm screen', 'form-steps-for-contact-form-7' ),
				'summary'  => __( 'A classic contact form. Review shows every answer in place, then Send.', 'form-steps-for-contact-form-7' ),
				'features' => array( __( 'Confirm screen in place', 'form-steps-for-contact-form-7' ), __( 'Split buttons', 'form-steps-for-contact-form-7' ), __( 'Message after sending', 'form-steps-for-contact-form-7' ) ),
				'palette'  => array(
					'page'        => '#f1f5f9',
					'card'        => '#ffffff',
					'head'        => '#1b447e',
					'head_text'   => '#ffffff',
					'text'        => '#1e293b',
					'muted'       => '#64748b',
					'border'      => '#cbd5e1',
					'input'       => '#ffffff',
					'soft'        => '#eef2f9',
					'accent'      => '#1b447e',
					'hover'       => '#14335f',
					'accent_text' => '#ffffff',
				),
				'form'     => implode(
					"\n",
					array(
						'<div class="ex-head svfs-keep">',
						'<h3 class="ex-title">' . esc_html__( 'Get in touch', 'form-steps-for-contact-form-7' ) . '</h3>',
						'<p class="ex-sub">' . esc_html__( 'We reply within one business day.', 'form-steps-for-contact-form-7' ) . '</p>',
						'</div>',
						'',
						'<div class="ex-row">',
						'<label> ' . esc_html__( 'Your name', 'form-steps-for-contact-form-7' ) . '',
						'    [text* your-name autocomplete:name] </label>',
						'',
						'<label> ' . esc_html__( 'Your email', 'form-steps-for-contact-form-7' ) . '',
						'    [email* your-email autocomplete:email] </label>',
						'</div>',
						'',
						'<label> ' . esc_html__( 'Topic', 'form-steps-for-contact-form-7' ) . '',
						'    [select topic "' . esc_html__( 'General question', 'form-steps-for-contact-form-7' ) . '" "' . esc_html__( 'Sales', 'form-steps-for-contact-form-7' ) . '" "' . esc_html__( 'Support', 'form-steps-for-contact-form-7' ) . '"] </label>',
						'',
						'<label> ' . esc_html__( 'Message', 'form-steps-for-contact-form-7' ) . '',
						'    [textarea* your-message x5] </label>',
						'',
						'[acceptance consent] ' . esc_html__( 'I agree that my data is used to answer my request.', 'form-steps-for-contact-form-7' ) . ' [/acceptance]',
						'',
						'[submit "' . esc_html__( 'Send', 'form-steps-for-contact-form-7' ) . '"]',
					)
				),
				'settings' => array(
					'confirm'         => true,
					'confirm_style'   => 'inplace',
					'confirm_heading' => __( 'Please check your message', 'form-steps-for-contact-form-7' ),
					'design'          => array(
						'style' => 'filled',
						'align' => 'split',
					),
				),
			),
			'quote'   => array(
				'title'    => __( 'Multi-step quote request', 'form-steps-for-contact-form-7' ),
				'summary'  => __( 'Three short steps with a progress bar, a summary table, then a thank-you screen with next steps.', 'form-steps-for-contact-form-7' ),
				'features' => array( __( '3 steps + progress bar', 'form-steps-for-contact-form-7' ), __( 'Summary table', 'form-steps-for-contact-form-7' ), __( 'Thank-you screen', 'form-steps-for-contact-form-7' ) ),
				'palette'  => array(
					'page'        => '#ecfdf5',
					'card'        => '#ffffff',
					'head'        => '#0f766e',
					'head_text'   => '#ffffff',
					'text'        => '#134e4a',
					'muted'       => '#5b7b78',
					'border'      => '#b6d8d3',
					'input'       => '#ffffff',
					'soft'        => '#e6f6f3',
					'accent'      => '#0f766e',
					'hover'       => '#115e59',
					'accent_text' => '#ffffff',
				),
				'form'     => implode(
					"\n",
					array(
						'<div class="ex-head svfs-keep">',
						'<h3 class="ex-title">' . esc_html__( 'Request a quote', 'form-steps-for-contact-form-7' ) . '</h3>',
						'<p class="ex-sub">' . esc_html__( 'Three quick steps. It takes about a minute.', 'form-steps-for-contact-form-7' ) . '</p>',
						'</div>',
						'',
						'[step "' . esc_html__( 'About you', 'form-steps-for-contact-form-7' ) . '"]',
						'',
						'<div class="ex-row">',
						'<label> ' . esc_html__( 'Your name', 'form-steps-for-contact-form-7' ) . '',
						'    [text* your-name autocomplete:name] </label>',
						'',
						'<label> ' . esc_html__( 'Company', 'form-steps-for-contact-form-7' ) . '',
						'    [text company autocomplete:organization] </label>',
						'</div>',
						'',
						'<label> ' . esc_html__( 'Your email', 'form-steps-for-contact-form-7' ) . '',
						'    [email* your-email autocomplete:email] </label>',
						'',
						'[step "' . esc_html__( 'Project', 'form-steps-for-contact-form-7' ) . '"]',
						'',
						'<label> ' . esc_html__( 'What do you need?', 'form-steps-for-contact-form-7' ) . '',
						'    [select* service include_blank "' . esc_html__( 'New website', 'form-steps-for-contact-form-7' ) . '" "' . esc_html__( 'Redesign', 'form-steps-for-contact-form-7' ) . '" "' . esc_html__( 'Online shop', 'form-steps-for-contact-form-7' ) . '" "' . esc_html__( 'Maintenance', 'form-steps-for-contact-form-7' ) . '"] </label>',
						'',
						'<label> ' . esc_html__( 'Tell us about the project', 'form-steps-for-contact-form-7' ) . '',
						'    [textarea* details] </label>',
						'',
						'[step "' . esc_html__( 'Budget', 'form-steps-for-contact-form-7' ) . '"]',
						'',
						'<p class="ex-label">' . esc_html__( 'Budget', 'form-steps-for-contact-form-7' ) . '</p>',
						'[radio budget use_label_element default:1 "' . esc_html__( 'Under $2,000', 'form-steps-for-contact-form-7' ) . '" "$2,000 – $5,000" "' . esc_html__( 'Over $5,000', 'form-steps-for-contact-form-7' ) . '"]',
						'',
						'<label> ' . esc_html__( 'When should it be ready?', 'form-steps-for-contact-form-7' ) . '',
						'    [date deadline] </label>',
						'',
						'[submit "' . esc_html__( 'Send request', 'form-steps-for-contact-form-7' ) . '"]',
					)
				),
				'settings' => array(
					'confirm'         => true,
					'confirm_style'   => 'table',
					'confirm_heading' => __( 'Almost done: check your request', 'form-steps-for-contact-form-7' ),
					'progress'        => 'bar',
					'after_send'      => 'screen',
					'thanks'          => 'steps',
					'design'          => array(
						'style' => 'rounded',
						'align' => 'split',
					),
				),
			),
			'booking' => array(
				'title'    => __( 'Booking request', 'form-steps-for-contact-form-7' ),
				'summary'  => __( 'Date, time and guests side by side, a summary table, full-width buttons and a check-mark thank-you screen.', 'form-steps-for-contact-form-7' ),
				'features' => array( __( 'Summary table', 'form-steps-for-contact-form-7' ), __( 'Full-width buttons', 'form-steps-for-contact-form-7' ), __( 'Thank-you screen', 'form-steps-for-contact-form-7' ) ),
				'palette'  => array(
					'page'        => '#fff7ed',
					'card'        => '#ffffff',
					'head'        => '#c2410c',
					'head_text'   => '#ffffff',
					'text'        => '#431407',
					'muted'       => '#9a6b55',
					'border'      => '#f3cdb4',
					'input'       => '#fffbf7',
					'soft'        => '#fff1e6',
					'accent'      => '#c2410c',
					'hover'       => '#9a3412',
					'accent_text' => '#ffffff',
				),
				'form'     => implode(
					"\n",
					array(
						'<div class="ex-head svfs-keep">',
						'<h3 class="ex-title">' . esc_html__( 'Book a table', 'form-steps-for-contact-form-7' ) . '</h3>',
						'<p class="ex-sub">' . esc_html__( 'We confirm every booking by email.', 'form-steps-for-contact-form-7' ) . '</p>',
						'</div>',
						'',
						'<div class="ex-row ex-row-3">',
						'<label> ' . esc_html__( 'Date', 'form-steps-for-contact-form-7' ) . '',
						'    [date* booking-date] </label>',
						'',
						'<label> ' . esc_html__( 'Time', 'form-steps-for-contact-form-7' ) . '',
						'    [select* booking-time "18:00" "18:30" "19:00" "19:30" "20:00" "20:30"] </label>',
						'',
						'<label> ' . esc_html__( 'Guests', 'form-steps-for-contact-form-7' ) . '',
						'    [number* guests min:1 max:12 "2"] </label>',
						'</div>',
						'',
						'<div class="ex-row">',
						'<label> ' . esc_html__( 'Your name', 'form-steps-for-contact-form-7' ) . '',
						'    [text* your-name autocomplete:name] </label>',
						'',
						'<label> ' . esc_html__( 'Phone', 'form-steps-for-contact-form-7' ) . '',
						'    [tel your-phone autocomplete:tel] </label>',
						'</div>',
						'',
						'<label> ' . esc_html__( 'Your email', 'form-steps-for-contact-form-7' ) . '',
						'    [email* your-email autocomplete:email] </label>',
						'',
						'<label> ' . esc_html__( 'Special requests', 'form-steps-for-contact-form-7' ) . '',
						'    [textarea notes x3] </label>',
						'',
						'[submit "' . esc_html__( 'Book now', 'form-steps-for-contact-form-7' ) . '"]',
					)
				),
				'settings' => array(
					'confirm'         => true,
					'confirm_style'   => 'table',
					'confirm_heading' => __( 'Your booking', 'form-steps-for-contact-form-7' ),
					'after_send'      => 'screen',
					'thanks'          => 'check',
					'labels'          => array(
						'review' => __( 'Check booking', 'form-steps-for-contact-form-7' ),
						'send'   => __( 'Confirm booking', 'form-steps-for-contact-form-7' ),
						'back'   => __( 'Change', 'form-steps-for-contact-form-7' ),
					),
					'design'          => array(
						'style' => 'filled',
						'align' => 'full',
					),
				),
			),
			'japanese' => array(
				'title'    => __( 'Japanese contact form (確認画面)', 'form-steps-for-contact-form-7' ),
				'summary'  => __( 'A Japanese form in the familiar style: input, confirm, done. Required marks are removed on the confirm screen.', 'form-steps-for-contact-form-7' ),
				'features' => array( __( 'Confirm screen in place', 'form-steps-for-contact-form-7' ), __( 'Japanese buttons', 'form-steps-for-contact-form-7' ), __( 'Thank-you screen', 'form-steps-for-contact-form-7' ) ),
				'palette'  => array(
					'page'        => '#f4f7fb',
					'card'        => '#ffffff',
					'head'        => '#33c6f4',
					'head_text'   => '#0b2545',
					'text'        => '#1f2937',
					'muted'       => '#6b7280',
					'border'      => '#d1d9e6',
					'input'       => '#ffffff',
					'soft'        => '#eaf7fd',
					'accent'      => '#1b447e',
					'hover'       => '#14335f',
					'accent_text' => '#ffffff',
				),
				// Japanese on purpose: this example shows a Japanese form on any site.
				'form'     => implode(
					"\n",
					array(
						'<div class="ex-head svfs-keep">',
						'<h3 class="ex-title">お問い合わせ</h3>',
						'<p class="ex-sub"><span class="ex-req">必須</span> は必ずご入力ください。</p>',
						'</div>',
						'',
						'<label> お名前 <span class="ex-req">必須</span>',
						'    [text* your-name autocomplete:name placeholder "山田 花子"] </label>',
						'',
						'<label> メールアドレス <span class="ex-req">必須</span>',
						'    [email* your-email autocomplete:email placeholder "hanako@example.jp"] </label>',
						'',
						'<label> 電話番号',
						'    [tel your-phone autocomplete:tel] </label>',
						'',
						'<label> お問い合わせ内容 <span class="ex-req">必須</span>',
						'    [textarea* your-message x5] </label>',
						'',
						'[acceptance consent] プライバシーポリシーに同意する [/acceptance]',
						'',
						'[submit "送信する"]',
					)
				),
				'settings' => array(
					'confirm'         => true,
					'confirm_style'   => 'inplace',
					'confirm_heading' => '入力内容の確認',
					'confirm_message' => '以下の内容で送信します。よろしければ「送信する」を押してください。',
					'after_send'      => 'screen',
					'thanks'          => 'ja',
					'labels'          => array(
						'review' => '確認画面へ',
						'back'   => '戻る',
						'send'   => '送信する',
					),
					'design'          => array(
						'style' => 'filled',
						'align' => 'center',
					),
				),
			),
			'dark'    => array(
				'title'    => __( 'Dark theme, two steps', 'form-steps-for-contact-form-7' ),
				'summary'  => __( 'A dark card with numbered steps and violet buttons. Shows how far the CSS variables go.', 'form-steps-for-contact-form-7' ),
				'features' => array( __( 'Numbered steps', 'form-steps-for-contact-form-7' ), __( 'Dark colors', 'form-steps-for-contact-form-7' ), __( 'Confirm screen in place', 'form-steps-for-contact-form-7' ) ),
				'palette'  => array(
					'page'        => '#0b1020',
					'card'        => '#141b2d',
					'head'        => '#1c2540',
					'head_text'   => '#f8fafc',
					'text'        => '#e2e8f0',
					'muted'       => '#94a3b8',
					'border'      => '#2e3a59',
					'input'       => '#0f1626',
					'soft'        => '#1d2742',
					'accent'      => '#8b5cf6',
					'hover'       => '#7c3aed',
					'accent_text' => '#ffffff',
				),
				'form'     => implode(
					"\n",
					array(
						'<div class="ex-head svfs-keep">',
						'<h3 class="ex-title">' . esc_html__( 'Join the beta', 'form-steps-for-contact-form-7' ) . '</h3>',
						'<p class="ex-sub">' . esc_html__( 'Two steps, no spam.', 'form-steps-for-contact-form-7' ) . '</p>',
						'</div>',
						'',
						'[step "' . esc_html__( 'You', 'form-steps-for-contact-form-7' ) . '"]',
						'',
						'<label> ' . esc_html__( 'Your name', 'form-steps-for-contact-form-7' ) . '',
						'    [text* your-name autocomplete:name] </label>',
						'',
						'<label> ' . esc_html__( 'Your email', 'form-steps-for-contact-form-7' ) . '',
						'    [email* your-email autocomplete:email] </label>',
						'',
						'[step "' . esc_html__( 'Interests', 'form-steps-for-contact-form-7' ) . '"]',
						'',
						'<p class="ex-label">' . esc_html__( 'What will you use it for?', 'form-steps-for-contact-form-7' ) . '</p>',
						'[checkbox interests use_label_element "' . esc_html__( 'Work', 'form-steps-for-contact-form-7' ) . '" "' . esc_html__( 'Side project', 'form-steps-for-contact-form-7' ) . '" "' . esc_html__( 'Learning', 'form-steps-for-contact-form-7' ) . '"]',
						'',
						'<label> ' . esc_html__( 'Anything else?', 'form-steps-for-contact-form-7' ) . '',
						'    [textarea note x3] </label>',
						'',
						'[submit "' . esc_html__( 'Join', 'form-steps-for-contact-form-7' ) . '"]',
					)
				),
				'settings' => array(
					'confirm'       => true,
					'confirm_style' => 'inplace',
					'progress'      => 'numbers',
					'design'        => array(
						'style' => 'filled',
						'align' => 'right',
					),
				),
			),
		);

		foreach ( $examples as $key => $example ) {
			$examples[ $key ]['skin']     = self::SKIN_OF[ $key ];
			$examples[ $key ]['css']      = self::palette_css( self::SKIN_OF[ $key ], $example['palette'] );
			$examples[ $key ]['settings'] = self::full_settings( $example['settings'], $example['palette'], self::SKIN_OF[ $key ] );
		}
		return $examples;
	}

	/**
	 * One example, or null.
	 *
	 * @param string $key Example slug.
	 * @return array|null
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Complete Form Steps settings for an example.
	 *
	 * @param array  $settings Partial settings.
	 * @param array  $palette  Colors.
	 * @param string $skin     Built-in Form style.
	 * @return array
	 */
	private static function full_settings( array $settings, array $palette, $skin ) {
		$thanks = '';
		if ( ! empty( $settings['thanks'] ) ) {
			if ( 'ja' === $settings['thanks'] ) {
				$thanks = implode(
					"\n",
					array(
						'<div class="svfs-ty svfs-ty-card">',
						'<span class="svfs-ty-icon" aria-hidden="true"></span>',
						'<h2 class="svfs-ty-title">送信が完了しました</h2>',
						'<p class="svfs-ty-text">[your-name] 様、お問い合わせありがとうございます。<br>内容を確認のうえ、2営業日以内に [your-email] へご連絡いたします。</p>',
						'</div>',
					)
				);
			} else {
				$templates = Editor_Panel::thanks_templates();
				$thanks    = isset( $templates[ $settings['thanks'] ] ) ? $templates[ $settings['thanks'] ]['html'] : '';
			}
		}
		unset( $settings['thanks'] );

		$design = array_merge(
			Design::form_defaults(),
			array(
				'skin'         => $skin,
				'accent'       => $palette['accent'],
				'accent_text'  => $palette['accent_text'],
				'accent_hover' => $palette['hover'],
			),
			isset( $settings['design'] ) ? $settings['design'] : array()
		);
		unset( $settings['design'] );

		$labels = array_merge( array_fill_keys( Global_Settings::LABEL_KEYS, '' ), isset( $settings['labels'] ) ? $settings['labels'] : array() );
		unset( $settings['labels'] );

		return Form_Settings::sanitize(
			array_merge(
				Form_Settings::defaults(),
				$settings,
				array(
					'thanks_html' => $thanks,
					'design'      => $design,
					'labels'      => $labels,
				)
			)
		);
	}

	/**
	 * Copy-ready snippet that changes the colors of an example's Form style. It is
	 * meant for WordPress's own Additional CSS; the plugin itself stores no CSS.
	 *
	 * @param string $skin Form style.
	 * @param array  $p    Colors.
	 * @return string
	 */
	public static function palette_css( $skin, array $p ) {
		$lines = array(
			'/* Colors of the "' . $skin . '" Form style. Paste into Additional CSS and change any value. */',
			'.wpcf7 form.svfs-skin-' . $skin . ' {',
		);
		foreach ( array( 'page', 'card', 'head', 'head_text', 'text', 'muted', 'border', 'input', 'soft', 'accent', 'hover', 'accent_text' ) as $name ) {
			if ( isset( $p[ $name ] ) ) {
				$lines[] = "\t--svfs-p-" . str_replace( '_', '-', $name ) . ': ' . $p[ $name ] . ';';
			}
		}
		$lines[] = '}';
		return implode( "\n", $lines );
	}

	/* --------------------------------------------------------------------- */
	/* Preview page                                                          */
	/* --------------------------------------------------------------------- */

	/**
	 * Address of an example's preview page.
	 *
	 * @param string $key Example slug.
	 * @return string
	 */
	public static function preview_url( $key ) {
		return add_query_arg(
			array(
				self::PREVIEW_VAR => $key,
				'_wpnonce'        => wp_create_nonce( self::PREVIEW_NONCE ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Prints a bare page with one example form, for the preview frame in the
	 * Examples tab. Only for users who can edit contact forms.
	 */
	public function maybe_preview() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checked below.
		if ( ! isset( $_GET[ self::PREVIEW_VAR ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checked below.
		$key     = sanitize_key( wp_unslash( $_GET[ self::PREVIEW_VAR ] ) );
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		$example = self::get( $key );
		if ( ! $example || ! current_user_can( 'wpcf7_edit_contact_forms' ) || ! wp_verify_nonce( $nonce, self::PREVIEW_NONCE ) ) {
			wp_die( esc_html__( 'This preview is not available.', 'form-steps-for-contact-form-7' ), 403 );
		}

		$form = \WPCF7_ContactForm::get_template( array( 'title' => $example['title'] ) );
		$form->set_properties( array( 'form' => $example['form'] ) );
		Form_Settings::set_preview( $example['settings'] );

		// Register Contact Form 7's and this plugin's assets, then print only those.
		do_action( 'wp_enqueue_scripts' );
		wp_register_script( self::DEMO_HANDLE, Plugin::asset_url( 'js/example-demo', 'js' ), array(), SVFS_VERSION, false );
		wp_localize_script(
			self::DEMO_HANDLE,
			'svfsDemo',
			array(
				'required' => __( 'Please fill out this field.', 'form-steps-for-contact-form-7' ),
				'email'    => __( 'Please enter an email address.', 'form-steps-for-contact-form-7' ),
				'invalid'  => __( 'One or more fields have an error. Please check and try again.', 'form-steps-for-contact-form-7' ),
				'sent'     => __( 'Thank you for your message. It has been sent. (Demo: nothing was sent.)', 'form-steps-for-contact-form-7' ),
			)
		);

		$html = $form->form_html();

		wp_add_inline_style(
			Form_Render::HANDLE,
			'html{background:' . sanitize_hex_color( $example['palette']['page'] ) . '}'
			. 'body{margin:0;padding:32px 16px 48px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Hiragino Sans","Yu Gothic",sans-serif}'
		);

		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $example['title'] ); ?></title>
		<?php wp_print_styles( array( 'contact-form-7', Form_Render::HANDLE, Design::SKINS_HANDLE ) ); ?>
		<?php wp_print_scripts( array( self::DEMO_HANDLE ) ); ?>
</head>
<body>
		<?php
		// Contact Form 7 builds and escapes this markup.
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_print_scripts( array( 'contact-form-7', Form_Render::HANDLE ) );
		?>
</body>
</html>
		<?php
		exit;
	}

	/* --------------------------------------------------------------------- */
	/* Create                                                                */
	/* --------------------------------------------------------------------- */

	/**
	 * Creates a real Contact Form 7 form from an example, optionally with a draft page.
	 */
	public function create() {
		if ( ! current_user_can( 'wpcf7_edit_contact_forms' ) ) {
			wp_die( esc_html__( 'You are not allowed to create contact forms.', 'form-steps-for-contact-form-7' ), 403 );
		}
		check_admin_referer( self::CREATE_ACTION );

		$key     = isset( $_POST['example'] ) ? sanitize_key( wp_unslash( $_POST['example'] ) ) : '';
		$example = self::get( $key );
		if ( ! $example ) {
			wp_safe_redirect( Admin_Page::url( 'examples' ) );
			exit;
		}

		$form = \WPCF7_ContactForm::get_template( array( 'title' => $example['title'] ) );
		$mail = $form->prop( 'mail' );
		if ( is_array( $mail ) ) {
			$mail['body'] = self::mail_body( $example['form'] );
			if ( false === strpos( $example['form'], 'your-email' ) ) {
				$mail['additional_headers'] = '';
			}
		}
		$form->set_properties(
			array(
				'form' => $example['form'],
				'mail' => $mail,
			)
		);
		$form->save();
		$form_id = (int) $form->id();
		if ( ! $form_id ) {
			wp_safe_redirect( Admin_Page::url( 'examples', array( 'example' => $key ) ) );
			exit;
		}

		Form_Settings::save( $form_id, $example['settings'] );

		$page_id = 0;
		if ( ! empty( $_POST['with_page'] ) && current_user_can( 'edit_pages' ) ) {
			$page_id = (int) wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'draft',
					'post_title'   => $example['title'],
					'post_content' => '<!-- wp:shortcode -->' . "\n" . $form->shortcode() . "\n" . '<!-- /wp:shortcode -->',
				)
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'wpcf7',
					'post'         => $form_id,
					'action'       => 'edit',
					'active-tab'   => 'svfs-steps-panel',
					'svfs_created' => $key,
					'svfs_page'    => $page_id,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Mail body listing every field of the example.
	 *
	 * @param string $template Form template.
	 * @return string
	 */
	private static function mail_body( $template ) {
		$lines = array();
		if ( preg_match_all( '/\[(?:text|email|tel|url|number|date|select|radio|checkbox|textarea)\*?\s+([A-Za-z][-A-Za-z0-9_]*)/', $template, $matches ) ) {
			foreach ( array_unique( $matches[1] ) as $name ) {
				$lines[] = $name . ': [' . $name . ']';
			}
		}
		$lines[] = '';
		$lines[] = '--';
		/* translators: [_site_title] and [_site_url] are Contact Form 7 mail tags; keep them as they are. */
		$lines[] = __( 'Sent from [_site_title] ([_site_url])', 'form-steps-for-contact-form-7' );
		return implode( "\n", $lines );
	}
}
