<?php
/**
 * "Steps" tab in the Contact Form 7 editor, plus tag-generator buttons.
 *
 * @package AmitShaw\FormSteps
 */

namespace AmitShaw\FormSteps;

defined( 'ABSPATH' ) || exit;

/**
 * Per-form settings live next to Form, Mail and Messages. They are saved by
 * Contact Form 7's own Save button, after its nonce and capability checks.
 */
final class Editor_Panel {

	/**
	 * Registers hooks.
	 */
	public function __construct() {
		add_filter( 'wpcf7_editor_panels', array( $this, 'add_panel' ) );
		add_action( 'wpcf7_save_contact_form', array( $this, 'save' ), 10, 3 );
		add_action( 'wpcf7_admin_init', array( $this, 'add_tag_generators' ), 60 );
	}

	/**
	 * Adds the Steps tab.
	 *
	 * @param array $panels Editor panels.
	 * @return array
	 */
	public function add_panel( $panels ) {
		$panels['svfs-steps-panel'] = array(
			'title'    => __( 'Steps', 'form-steps-for-contact-form-7' ),
			'callback' => array( $this, 'render' ),
		);
		return $panels;
	}

	/**
	 * Saves the tab when Contact Form 7 saves the form.
	 *
	 * @param \WPCF7_ContactForm $form    Saved form.
	 * @param array              $data    Submitted data, already unslashed by Contact Form 7.
	 * @param string             $context Save context.
	 */
	public function save( $form, $data = array(), $context = '' ) {
		if ( 'save' !== $context || ! is_array( $data ) || empty( $data['svfs'] ) || ! is_array( $data['svfs'] ) || empty( $data['svfs']['present'] ) ) {
			return;
		}
		if ( ! $form instanceof \WPCF7_ContactForm || ! current_user_can( 'wpcf7_edit_contact_form', $form->id() ) ) {
			return;
		}
		Form_Settings::save( $form->id(), $data['svfs'] );
	}

	/**
	 * Tag-generator buttons for [step], [confirm] and [back].
	 * Uses the version 2 API (Contact Form 7 6.0+). Older versions skip the buttons;
	 * typing the tags works everywhere.
	 */
	public function add_tag_generators() {
		if ( ! class_exists( 'WPCF7_TagGenerator' ) || ! class_exists( 'WPCF7_TagGeneratorGenerator' ) ) {
			return;
		}
		$generator = \WPCF7_TagGenerator::get_instance();
		$generator->add( 'step', __( 'step', 'form-steps-for-contact-form-7' ), array( $this, 'generator_step' ), array( 'version' => '2' ) );
		$generator->add( 'confirm', __( 'confirm', 'form-steps-for-contact-form-7' ), array( $this, 'generator_confirm' ), array( 'version' => '2' ) );
		$generator->add( 'back', __( 'back', 'form-steps-for-contact-form-7' ), array( $this, 'generator_back' ), array( 'version' => '2' ) );
	}

	/**
	 * Generator dialog for [step].
	 *
	 * @param \WPCF7_ContactForm $form    Form.
	 * @param array              $options Generator options.
	 */
	public function generator_step( $form, $options ) {
		$this->generator(
			$options,
			'step',
			__( 'Step', 'form-steps-for-contact-form-7' ),
			__( 'Starts a new step. Everything after this tag, up to the next step tag, is shown as one step with Next and Previous buttons. Put each step tag on its own line, at the same level of the form.', 'form-steps-for-contact-form-7' ),
			__( 'Step title', 'form-steps-for-contact-form-7' ),
			false
		);
	}

	/**
	 * Generator dialog for [confirm].
	 *
	 * @param \WPCF7_ContactForm $form    Form.
	 * @param array              $options Generator options.
	 */
	public function generator_confirm( $form, $options ) {
		$this->generator(
			$options,
			'confirm',
			__( 'Review button', 'form-steps-for-contact-form-7' ),
			__( 'Places the Review button exactly where you want it. Optional: without it, the form\'s submit button becomes the Review button when the confirm screen is on.', 'form-steps-for-contact-form-7' ),
			__( 'Label', 'form-steps-for-contact-form-7' ),
			true
		);
	}

	/**
	 * Generator dialog for [back].
	 *
	 * @param \WPCF7_ContactForm $form    Form.
	 * @param array              $options Generator options.
	 */
	public function generator_back( $form, $options ) {
		$this->generator(
			$options,
			'back',
			__( 'Back button', 'form-steps-for-contact-form-7' ),
			__( 'Places the Back button of the confirm screen. Use it together with the Review button tag.', 'form-steps-for-contact-form-7' ),
			__( 'Label', 'form-steps-for-contact-form-7' ),
			true
		);
	}

	/**
	 * Shared generator dialog markup, following Contact Form 7's own version 2 dialogs.
	 *
	 * @param array  $options     Generator options.
	 * @param string $type        Tag type.
	 * @param string $heading     Dialog heading.
	 * @param string $description Dialog description.
	 * @param string $value_title Title of the value field.
	 * @param bool   $with_class  Whether to offer a class attribute field.
	 */
	private function generator( $options, $type, $heading, $description, $value_title, $with_class ) {
		$tgg = new \WPCF7_TagGeneratorGenerator( $options['content'] );
		?>
		<header class="description-box">
			<h3><?php echo esc_html( $heading ); ?></h3>
			<p><?php echo esc_html( $description ); ?></p>
		</header>
		<div class="control-box">
			<?php
			$tgg->print(
				'field_type',
				array(
					'select_options' => array( $type => $heading ),
				)
			);
			if ( $with_class ) {
				$tgg->print( 'class_attr' );
			}
			$tgg->print( 'default_value', array( 'title' => $value_title ) );
			?>
		</div>
		<footer class="insert-box">
			<?php $tgg->print( 'insert_box_content' ); ?>
		</footer>
		<?php
	}

	/**
	 * Renders the Steps tab.
	 *
	 * @param \WPCF7_ContactForm $form Form being edited.
	 */
	public function render( $form ) {
		$settings     = Form_Settings::get( $form->id() );
		$global       = Global_Settings::get();
		$defaults     = Global_Settings::default_labels();
		$has_confirm  = array() !== $form->scan_form_tags( array( 'type' => 'confirm' ) );
		$step_count   = count( $form->scan_form_tags( array( 'type' => 'step' ) ) );
		$has_payment  = array() !== $form->scan_form_tags( array( 'type' => 'stripe' ) );
		$field_names  = $this->field_names( $form );
		$name         = static function ( $key ) {
			return 'svfs[' . $key . ']';
		};
		$label_titles = array(
			'review' => __( 'Review button', 'form-steps-for-contact-form-7' ),
			'back'   => __( 'Back button', 'form-steps-for-contact-form-7' ),
			'send'   => __( 'Send button on the confirm screen', 'form-steps-for-contact-form-7' ),
			'next'   => __( 'Next button', 'form-steps-for-contact-form-7' ),
			'prev'   => __( 'Previous button', 'form-steps-for-contact-form-7' ),
		);
		$progress_names = array(
			'numbers' => __( 'Numbered steps', 'form-steps-for-contact-form-7' ),
			'bar'     => __( 'Progress bar', 'form-steps-for-contact-form-7' ),
			'none'    => __( 'None', 'form-steps-for-contact-form-7' ),
		);
		?>
		<div class="svfs-panel">
			<input type="hidden" name="<?php echo esc_attr( $name( 'present' ) ); ?>" value="1" />

			<div class="svfs-panel-head">
				<h2><?php esc_html_e( 'Steps', 'form-steps-for-contact-form-7' ); ?></h2>
				<a class="svfs-panel-help" href="<?php echo esc_url( Links::help( 'docs' ) ); ?>"><?php esc_html_e( 'Help & examples', 'form-steps-for-contact-form-7' ); ?></a>
			</div>
			<p class="description">
				<?php esc_html_e( 'Add a confirm screen before sending, split the form into steps, and choose what happens after sending. Changes are saved with the Save button of this form.', 'form-steps-for-contact-form-7' ); ?>
			</p>

			<?php if ( $has_payment ) : ?>
				<div class="notice notice-warning inline"><p>
					<?php esc_html_e( 'This form takes payments with Stripe. Payment forms need Contact Form 7\'s own flow, so the confirm screen and steps are not recommended here.', 'form-steps-for-contact-form-7' ); ?>
				</p></div>
			<?php endif; ?>

			<ul class="svfs-status">
				<li class="<?php echo esc_attr( $settings['confirm'] || $has_confirm ? 'is-on' : 'is-off' ); ?>">
					<?php
					if ( $has_confirm ) {
						esc_html_e( 'Confirm screen: on, because the form contains a [confirm] tag.', 'form-steps-for-contact-form-7' );
					} elseif ( $settings['confirm'] ) {
						esc_html_e( 'Confirm screen: on.', 'form-steps-for-contact-form-7' );
					} else {
						esc_html_e( 'Confirm screen: off.', 'form-steps-for-contact-form-7' );
					}
					?>
				</li>
				<li class="<?php echo esc_attr( $step_count ? 'is-on' : 'is-off' ); ?>">
					<?php
					if ( $step_count ) {
						echo esc_html(
							sprintf(
								/* translators: %d: number of [step] tags in the form. */
								_n( 'Steps: %d step tag found.', 'Steps: %d step tags found.', $step_count, 'form-steps-for-contact-form-7' ),
								$step_count
							)
						);
					} else {
						esc_html_e( 'Steps: not split. To split the form, add [step "Title"] tags in the Form tab, or use the "step" button there.', 'form-steps-for-contact-form-7' );
					}
					?>
				</li>
			</ul>

			<fieldset class="svfs-section">
				<legend><?php esc_html_e( 'Confirm screen', 'form-steps-for-contact-form-7' ); ?></legend>

				<p class="svfs-row">
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $name( 'confirm' ) ); ?>" value="1" <?php checked( $settings['confirm'] || $has_confirm ); ?> <?php disabled( $has_confirm ); ?> />
						<strong><?php esc_html_e( 'Show a confirm screen before sending', 'form-steps-for-contact-form-7' ); ?></strong>
					</label>
					<?php Help::tip( 'confirm', __( 'The submit button becomes a Review button. Visitors see their answers, then press Send or go Back to edit. Review only checks the form: no email, no captcha used.', 'form-steps-for-contact-form-7' ) ); ?>
					<?php if ( $has_confirm && $settings['confirm'] ) : ?>
						<?php // The box is disabled while a [confirm] tag forces it on, so keep the stored choice for when the tag is removed. ?>
						<input type="hidden" name="<?php echo esc_attr( $name( 'confirm' ) ); ?>" value="1" />
					<?php endif; ?>
				</p>

				<h3 class="svfs-label">
					<?php esc_html_e( 'How entries are shown', 'form-steps-for-contact-form-7' ); ?>
					<?php Help::tip( 'confirm_style', __( 'In place keeps your own form design and swaps each field for its answer. Summary table hides the form and shows a two-column table of labels and answers.', 'form-steps-for-contact-form-7' ) ); ?>
				</h3>
				<?php
				$this->cards(
					$name( 'confirm_style' ),
					$settings['confirm_style'],
					array(
						'inplace' => array( __( 'In place', 'form-steps-for-contact-form-7' ), __( 'Each field is replaced by what the visitor typed. Your layout stays as designed. Recommended.', 'form-steps-for-contact-form-7' ), 'inplace' ),
						'table'   => array( __( 'Summary table', 'form-steps-for-contact-form-7' ), __( 'A clean table of labels and answers replaces the form. Labels can be renamed below.', 'form-steps-for-contact-form-7' ), 'table' ),
					)
				);
				?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="svfs-heading"><?php esc_html_e( 'Heading', 'form-steps-for-contact-form-7' ); ?></label></th>
						<td><input type="text" id="svfs-heading" class="large-text" name="<?php echo esc_attr( $name( 'confirm_heading' ) ); ?>" value="<?php echo esc_attr( $settings['confirm_heading'] ); ?>" placeholder="<?php esc_attr_e( 'Optional, for example: Check your entries', 'form-steps-for-contact-form-7' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="svfs-message"><?php esc_html_e( 'Message', 'form-steps-for-contact-form-7' ); ?></label></th>
						<td>
							<textarea id="svfs-message" class="large-text" rows="2" name="<?php echo esc_attr( $name( 'confirm_message' ) ); ?>" placeholder="<?php esc_attr_e( 'Please check your entries. If everything is correct, press Send.', 'form-steps-for-contact-form-7' ); ?>"><?php echo esc_textarea( $settings['confirm_message'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Leave empty to use the text shown in grey.', 'form-steps-for-contact-form-7' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Empty optional fields', 'form-steps-for-contact-form-7' ); ?></th>
						<td>
							<?php
							$this->radios(
								$name( 'empty_values' ),
								$settings['empty_values'],
								array(
									''     => sprintf(
										/* translators: %s: the site-wide choice, for example "Show a dash". */
										__( 'Site default (%s)', 'form-steps-for-contact-form-7' ),
										'hide' === $global['empty_values'] ? __( 'Hide in the table', 'form-steps-for-contact-form-7' ) : __( 'Show a dash', 'form-steps-for-contact-form-7' )
									),
									'dash' => __( 'Show a dash', 'form-steps-for-contact-form-7' ),
									'hide' => __( 'Hide in the table', 'form-steps-for-contact-form-7' ),
								)
							);
							?>
						</td>
					</tr>
				</table>

				<?php if ( $field_names ) : ?>
					<div class="svfs-subsection" data-svfs-active-when="<?php echo esc_attr( $name( 'confirm_style' ) ); ?>=table">
						<h3 class="svfs-label">
							<?php esc_html_e( 'Rename labels in the summary table', 'form-steps-for-contact-form-7' ); ?>
							<?php Help::tip( 'field_labels', __( 'The table uses the label found next to each field in your form. Type a new name to show something else, for example "Your question" instead of "Message". Leave a box empty to keep the label found.', 'form-steps-for-contact-form-7' ) ); ?>
						</h3>
						<p class="svfs-inactive-note"><?php esc_html_e( 'Used only when "Summary table" is chosen above.', 'form-steps-for-contact-form-7' ); ?></p>
						<table class="widefat striped svfs-labels-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Field', 'form-steps-for-contact-form-7' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Label found in your form', 'form-steps-for-contact-form-7' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Show in the table as', 'form-steps-for-contact-form-7' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $field_names as $field_name ) : ?>
									<tr data-svfs-field="<?php echo esc_attr( $field_name ); ?>">
										<td><code><?php echo esc_html( $field_name ); ?></code></td>
										<td class="svfs-found">&mdash;</td>
										<td>
											<label class="screen-reader-text" for="<?php echo esc_attr( 'svfs-fl-' . $field_name ); ?>">
												<?php
												/* translators: %s: field name. */
												echo esc_html( sprintf( __( 'New label for %s', 'form-steps-for-contact-form-7' ), $field_name ) );
												?>
											</label>
											<input type="text" class="regular-text" id="<?php echo esc_attr( 'svfs-fl-' . $field_name ); ?>" name="<?php echo esc_attr( 'svfs[field_labels][' . $field_name . ']' ); ?>" value="<?php echo esc_attr( isset( $settings['field_labels'][ $field_name ] ) ? $settings['field_labels'][ $field_name ] : '' ); ?>" maxlength="200" />
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</fieldset>

			<fieldset class="svfs-section">
				<legend><?php esc_html_e( 'Button texts', 'form-steps-for-contact-form-7' ); ?></legend>
				<p class="description">
					<?php esc_html_e( 'Leave empty to use the site defaults shown in grey. The Send button keeps the text of your form\'s submit button unless you set one here.', 'form-steps-for-contact-form-7' ); ?>
					<?php Help::tip( 'buttons', __( 'Review and Back appear with the confirm screen. Next and Previous appear on forms split with step tags. Site-wide texts are under Contact → Form Steps → Defaults.', 'form-steps-for-contact-form-7' ) ); ?>
				</p>
				<table class="form-table" role="presentation">
					<?php foreach ( Global_Settings::LABEL_KEYS as $key ) : ?>
						<?php
						$placeholder = '' !== $global['labels'][ $key ] ? $global['labels'][ $key ] : ( 'send' === $key ? __( 'Your submit button\'s text', 'form-steps-for-contact-form-7' ) : $defaults[ $key ] );
						?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( 'svfs-label-' . $key ); ?>"><?php echo esc_html( $label_titles[ $key ] ); ?></label></th>
							<td><input type="text" class="regular-text" id="<?php echo esc_attr( 'svfs-label-' . $key ); ?>" name="<?php echo esc_attr( 'svfs[labels][' . $key . ']' ); ?>" value="<?php echo esc_attr( $settings['labels'][ $key ] ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" maxlength="60" /></td>
						</tr>
					<?php endforeach; ?>
				</table>
			</fieldset>

			<fieldset class="svfs-section">
				<legend><?php esc_html_e( 'Design', 'form-steps-for-contact-form-7' ); ?></legend>
				<p class="description">
					<?php esc_html_e( 'A built-in Form style for the whole form, and the style, position and visibility of its buttons. The button preview updates as you change them.', 'form-steps-for-contact-form-7' ); ?>
					<?php Help::tip( 'design', __( 'Theme default keeps your theme\'s buttons. Filled, Outline and Rounded use your colors. Position and order apply to the row that holds the buttons, when that row contains only buttons.', 'form-steps-for-contact-form-7' ) ); ?>
				</p>
				<?php
				$resolved = Form_Settings::resolved( $form );
				self::design_controls(
					'svfs[design]',
					$settings['design'],
					true,
					array(
						'back' => $resolved['labels']['back'],
						'send' => '' !== $resolved['labels']['send'] ? $resolved['labels']['send'] : $defaults['send'],
						'prev' => $resolved['labels']['prev'],
						'next' => $resolved['labels']['next'],
					)
				);
				?>
			</fieldset>

			<fieldset class="svfs-section">
				<legend><?php esc_html_e( 'Steps', 'form-steps-for-contact-form-7' ); ?></legend>
				<h3 class="svfs-label">
					<?php esc_html_e( 'Progress display', 'form-steps-for-contact-form-7' ); ?>
					<?php Help::tip( 'progress', __( 'Shown above forms split with [step] tags. Numbered steps list every step title; the bar shows "Step 2 of 4". On phones the numbers shrink to fit.', 'form-steps-for-contact-form-7' ) ); ?>
				</h3>
				<?php
				$this->cards(
					$name( 'progress' ),
					$settings['progress'],
					array(
						''        => array(
							__( 'Site default', 'form-steps-for-contact-form-7' ),
							/* translators: %s: the site-wide progress choice, for example "Progress bar". */
							sprintf( __( 'Currently: %s', 'form-steps-for-contact-form-7' ), isset( $progress_names[ $global['progress'] ] ) ? $progress_names[ $global['progress'] ] : $progress_names['numbers'] ),
							isset( $progress_names[ $global['progress'] ] ) ? $global['progress'] : 'numbers',
						),
						'numbers' => array( $progress_names['numbers'], __( 'Numbers with step titles.', 'form-steps-for-contact-form-7' ), 'numbers' ),
						'bar'     => array( $progress_names['bar'], __( 'A bar with "Step 1 of 3".', 'form-steps-for-contact-form-7' ), 'bar' ),
						'none'    => array( $progress_names['none'], __( 'No progress display.', 'form-steps-for-contact-form-7' ), 'none' ),
					),
					'is-small'
				);
				?>
			</fieldset>

			<fieldset class="svfs-section">
				<legend><?php esc_html_e( 'After sending', 'form-steps-for-contact-form-7' ); ?></legend>
				<h3 class="svfs-label">
					<?php esc_html_e( 'What the visitor sees after pressing Send', 'form-steps-for-contact-form-7' ); ?>
					<?php Help::tip( 'after_send', __( 'The email is always sent first. Then Contact Form 7\'s green message appears, or the form is replaced by your thank-you screen, or the visitor goes to another page.', 'form-steps-for-contact-form-7' ) ); ?>
				</h3>
				<?php
				$this->cards(
					$name( 'after_send' ),
					$settings['after_send'],
					array(
						'message'  => array( __( 'Message below the form', 'form-steps-for-contact-form-7' ), __( 'Contact Form 7\'s normal message. Default.', 'form-steps-for-contact-form-7' ), 'message' ),
						'screen'   => array( __( 'Thank-you screen', 'form-steps-for-contact-form-7' ), __( 'The whole form is replaced by your own text.', 'form-steps-for-contact-form-7' ), 'screen' ),
						'redirect' => array( __( 'Go to another page', 'form-steps-for-contact-form-7' ), __( 'For example a thank-you page.', 'form-steps-for-contact-form-7' ), 'redirect' ),
					)
				);
				?>
				<div class="svfs-subsection svfs-thanks-builder" data-svfs-when="<?php echo esc_attr( $name( 'after_send' ) ); ?>=screen" data-svfs-thanks-builder="<?php echo esc_attr( (string) wp_json_encode( $this->thanks_builder_data( $form, $field_names ) ) ); ?>">
					<h3 class="svfs-label">
						<?php esc_html_e( 'Thank-you screen', 'form-steps-for-contact-form-7' ); ?>
						<?php Help::tip( 'thanks', __( 'Start from a template, change the text, and see the result below. Answers such as [your-name] are filled in with what the visitor sent. The templates use the button colors from Button design.', 'form-steps-for-contact-form-7' ) ); ?>
					</h3>

					<p class="svfs-small-label"><?php esc_html_e( '1. Start from a template', 'form-steps-for-contact-form-7' ); ?></p>
					<div class="svfs-templates">
						<?php foreach ( self::thanks_templates() as $key => $template ) : ?>
							<button type="button" class="svfs-template" data-svfs-template="<?php echo esc_attr( $key ); ?>">
								<?php self::picture( $template['picture'] ); ?>
								<span class="svfs-card-title"><?php echo esc_html( $template['title'] ); ?></span>
								<span class="svfs-card-text"><?php esc_html_e( 'Use this template', 'form-steps-for-contact-form-7' ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>

					<p class="svfs-small-label"><label for="svfs-thanks"><?php esc_html_e( '2. Edit the HTML', 'form-steps-for-contact-form-7' ); ?></label></p>
					<div class="svfs-editor-bar">
						<?php if ( $field_names ) : ?>
							<label class="screen-reader-text" for="svfs-insert-tag"><?php esc_html_e( 'Insert an answer', 'form-steps-for-contact-form-7' ); ?></label>
							<select id="svfs-insert-tag" class="svfs-insert-tag">
								<option value=""><?php esc_html_e( 'Insert an answer…', 'form-steps-for-contact-form-7' ); ?></option>
								<?php foreach ( $field_names as $field_name ) : ?>
									<option value="<?php echo esc_attr( '[' . $field_name . ']' ); ?>"><?php echo esc_html( '[' . $field_name . ']' ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
						<button type="button" class="button svfs-ai-open" aria-expanded="false" aria-controls="svfs-ai-box"><?php esc_html_e( 'Create with AI', 'form-steps-for-contact-form-7' ); ?></button>
					</div>

					<div class="svfs-ai-box" id="svfs-ai-box" hidden>
						<p><strong><?php esc_html_e( 'Let ChatGPT, Claude or Gemini write it', 'form-steps-for-contact-form-7' ); ?></strong></p>
						<ol class="svfs-ai-steps">
							<li><?php esc_html_e( 'Describe what you want (optional).', 'form-steps-for-contact-form-7' ); ?></li>
							<li><?php esc_html_e( 'Copy the prompt and paste it into your AI tool.', 'form-steps-for-contact-form-7' ); ?></li>
							<li><?php esc_html_e( 'Paste the HTML it returns into the box below. If it also returns CSS, paste that into WordPress\'s Additional CSS: Appearance → Customize, or Appearance → Editor → Styles in block themes.', 'form-steps-for-contact-form-7' ); ?></li>
						</ol>
						<label class="screen-reader-text" for="svfs-ai-wish"><?php esc_html_e( 'What you want', 'form-steps-for-contact-form-7' ); ?></label>
						<textarea id="svfs-ai-wish" class="large-text svfs-ai-wish" rows="2" placeholder="<?php esc_attr_e( 'For example: friendly tone, dark green, show the next three steps and a button to our pricing page /pricing/', 'form-steps-for-contact-form-7' ); ?>"></textarea>
						<p>
							<button type="button" class="button button-primary svfs-ai-copy"><?php esc_html_e( 'Copy AI prompt', 'form-steps-for-contact-form-7' ); ?></button>
							<span class="svfs-saved" aria-live="polite"></span>
						</p>
					</div>

					<textarea id="svfs-thanks" class="large-text code svfs-thanks-html" rows="9" spellcheck="false" name="<?php echo esc_attr( $name( 'thanks_html' ) ); ?>" placeholder="<?php esc_attr_e( 'Thank you! We received your message and will reply within one business day.', 'form-steps-for-contact-form-7' ); ?>"><?php echo esc_textarea( $settings['thanks_html'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Plain text or HTML. HTML allowed in posts is allowed here; scripts and forms are removed when you save.', 'form-steps-for-contact-form-7' ); ?></p>

					<p class="svfs-small-label"><?php esc_html_e( '3. Preview', 'form-steps-for-contact-form-7' ); ?> <span class="svfs-muted"><?php esc_html_e( '(answers shown with sample values)', 'form-steps-for-contact-form-7' ); ?></span></p>
					<?php // Contact Form 7 filters panel markup, so the script adds the sandboxed preview frame here. ?>
					<div class="svfs-thanks-preview-slot" data-svfs-title="<?php esc_attr_e( 'Thank-you screen preview', 'form-steps-for-contact-form-7' ); ?>"></div>
				</div>
				<div class="svfs-subsection" data-svfs-when="<?php echo esc_attr( $name( 'after_send' ) ); ?>=redirect">
					<p><label for="svfs-redirect"><strong><?php esc_html_e( 'Page address', 'form-steps-for-contact-form-7' ); ?></strong></label></p>
					<input type="url" id="svfs-redirect" class="large-text" name="<?php echo esc_attr( $name( 'redirect_url' ) ); ?>" value="<?php echo esc_attr( $settings['redirect_url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/thank-you/' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'The email is sent first, then the visitor goes to this page.', 'form-steps-for-contact-form-7' ); ?></p>
				</div>
			</fieldset>

			<p class="svfs-help-link">
				<a href="<?php echo esc_url( Admin_Page::url() ); ?>"><?php esc_html_e( 'All forms and site defaults: Contact → Form Steps', 'form-steps-for-contact-form-7' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Button design controls with a live preview. Shared by the Steps tab (per form,
	 * with "Site default" choices) and the Defaults tab (site-wide).
	 *
	 * @param string $prefix  Input name prefix, e.g. "svfs[design]".
	 * @param array  $values  Stored values.
	 * @param bool   $is_form Whether this is a form (offers "Site default").
	 * @param array  $labels  Resolved button texts for the preview: back, send, prev, next.
	 */
	public static function design_controls( $prefix, array $values, $is_form, array $labels ) {
		$site    = Global_Settings::get()['design'];
		$options = array(
			'skin'  => array(
				__( 'Form style', 'form-steps-for-contact-form-7' ),
				Design::skin_names(),
			),
			'style' => array(
				__( 'Button style', 'form-steps-for-contact-form-7' ),
				array(
					'theme'   => __( 'Theme default', 'form-steps-for-contact-form-7' ),
					'filled'  => __( 'Filled', 'form-steps-for-contact-form-7' ),
					'outline' => __( 'Outline', 'form-steps-for-contact-form-7' ),
					'rounded' => __( 'Rounded', 'form-steps-for-contact-form-7' ),
				),
			),
			'align' => array(
				__( 'Position', 'form-steps-for-contact-form-7' ),
				array(
					'left'   => __( 'Left', 'form-steps-for-contact-form-7' ),
					'center' => __( 'Centre', 'form-steps-for-contact-form-7' ),
					'right'  => __( 'Right', 'form-steps-for-contact-form-7' ),
					'full'   => __( 'Full width', 'form-steps-for-contact-form-7' ),
					'split'  => __( 'Split: Back left, Send right', 'form-steps-for-contact-form-7' ),
				),
			),
			'order' => array(
				__( 'Order', 'form-steps-for-contact-form-7' ),
				array(
					'back_first' => __( 'Back, then Send', 'form-steps-for-contact-form-7' ),
					'send_first' => __( 'Send, then Back', 'form-steps-for-contact-form-7' ),
				),
			),
			'back'  => array(
				__( 'Back button on the confirm screen', 'form-steps-for-contact-form-7' ),
				array(
					'show' => __( 'Show', 'form-steps-for-contact-form-7' ),
					'hide' => __( 'Hide', 'form-steps-for-contact-form-7' ),
				),
			),
			'prev'  => array(
				__( 'Previous buttons in steps', 'form-steps-for-contact-form-7' ),
				array(
					'show' => __( 'Show', 'form-steps-for-contact-form-7' ),
					'hide' => __( 'Hide', 'form-steps-for-contact-form-7' ),
				),
			),
		);
		$colors  = array(
			'accent'       => array( __( 'Button color', 'form-steps-for-contact-form-7' ), '#2563eb', __( 'Also used by the progress display.', 'form-steps-for-contact-form-7' ) ),
			'accent_text'  => array( __( 'Text on buttons', 'form-steps-for-contact-form-7' ), '#ffffff', '' ),
			'accent_hover' => array( __( 'Hover color', 'form-steps-for-contact-form-7' ), '#1d4ed8', '' ),
		);
		$preview = array(
			'site'   => Design::resolve( array(), $site ),
			'isForm' => (bool) $is_form,
		);
		?>
		<div class="svfs-design" data-svfs-design-site="<?php echo esc_attr( (string) wp_json_encode( $preview['site'] ) ); ?>" data-svfs-design-form="<?php echo $is_form ? '1' : '0'; ?>">
			<div class="svfs-design-grid">
				<div class="svfs-design-controls">
					<?php foreach ( $options as $key => $option ) : ?>
						<?php $id = 'svfs-design-' . ( $is_form ? 'f-' : 's-' ) . $key; ?>
						<p class="svfs-field">
							<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $option[0] ); ?></label>
							<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $prefix . '[' . $key . ']' ); ?>" data-svfs-design="<?php echo esc_attr( $key ); ?>">
								<?php if ( $is_form ) : ?>
									<option value="" <?php selected( '', $values[ $key ] ?? '' ); ?>>
										<?php
										/* translators: %s: the site-wide choice, for example "Filled". */
										echo esc_html( sprintf( __( 'Site default (%s)', 'form-steps-for-contact-form-7' ), $option[1][ $preview['site'][ $key ] ] ?? '' ) );
										?>
									</option>
								<?php endif; ?>
								<?php foreach ( $option[1] as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $values[ $key ] ?? '' ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
					<?php endforeach; ?>

					<div class="svfs-colors" data-svfs-colors>
						<?php foreach ( $colors as $key => $color ) : ?>
							<?php
							$id          = 'svfs-design-' . ( $is_form ? 'f-' : 's-' ) . $key;
							$placeholder = '' !== $preview['site'][ $key ] && $is_form ? $preview['site'][ $key ] : $color[1];
							?>
							<p class="svfs-field">
								<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $color[0] ); ?></label>
								<span class="svfs-color">
									<input type="color" class="svfs-color-pick" value="<?php echo esc_attr( '' !== ( $values[ $key ] ?? '' ) ? $values[ $key ] : $placeholder ); ?>" aria-label="<?php echo esc_attr( $color[0] ); ?>" tabindex="-1" />
									<input type="text" id="<?php echo esc_attr( $id ); ?>" class="svfs-color-text code" name="<?php echo esc_attr( $prefix . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $values[ $key ] ?? '' ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" maxlength="7" pattern="#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?" data-svfs-design="<?php echo esc_attr( $key ); ?>" data-svfs-default="<?php echo esc_attr( $color[1] ); ?>" />
									<button type="button" class="button-link svfs-color-clear"><?php esc_html_e( 'Clear', 'form-steps-for-contact-form-7' ); ?></button>
								</span>
							</p>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Colors apply to the Filled, Outline and Rounded styles and to the progress display. Leave empty for the default.', 'form-steps-for-contact-form-7' ); ?></p>
					</div>
				</div>

				<div class="svfs-design-preview">
					<p class="svfs-preview-title"><?php esc_html_e( 'Live preview', 'form-steps-for-contact-form-7' ); ?></p>
					<div class="svfs-preview-stage">
						<div class="svfs-form svfs-preview" data-svfs-preview>
							<p class="svfs-preview-caption"><?php esc_html_e( 'Confirm screen', 'form-steps-for-contact-form-7' ); ?></p>
							<p class="svfs-actions">
								<button type="button" class="wpcf7-form-control svfs-back" tabindex="-1"><?php echo esc_html( $labels['back'] ); ?></button>
								<button type="button" class="wpcf7-form-control wpcf7-submit" tabindex="-1"><?php echo esc_html( $labels['send'] ); ?></button>
							</p>
							<p class="svfs-preview-caption"><?php esc_html_e( 'A step in a multi-step form', 'form-steps-for-contact-form-7' ); ?></p>
							<p class="svfs-step-nav">
								<button type="button" class="wpcf7-form-control svfs-prev" tabindex="-1"><?php echo esc_html( $labels['prev'] ); ?></button>
								<button type="button" class="wpcf7-form-control svfs-next" tabindex="-1"><?php echo esc_html( $labels['next'] ); ?></button>
							</p>
						</div>
					</div>
					<p class="description svfs-preview-note"><?php esc_html_e( '"Theme default" keeps your theme\'s own buttons, which look plain here because the admin does not load your theme.', 'form-steps-for-contact-form-7' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Ready-made thank-you screens. [your-name] and [your-email] are swapped for
	 * the form's own fields when a template is inserted.
	 *
	 * @return array<string,array{title:string,picture:string,html:string}>
	 */
	public static function thanks_templates() {
		$icon = '<span class="svfs-ty-icon" aria-hidden="true"></span>';
		return array(
			'simple' => array(
				'title'   => __( 'Simple', 'form-steps-for-contact-form-7' ),
				'picture' => 'ty_simple',
				'html'    => implode(
					"\n",
					array(
						'<div class="svfs-ty">',
						'<h2 class="svfs-ty-title">' . esc_html__( 'Thank you!', 'form-steps-for-contact-form-7' ) . '</h2>',
						'<p class="svfs-ty-text">' . esc_html__( 'We received your message and will reply within one business day.', 'form-steps-for-contact-form-7' ) . '</p>',
						'</div>',
					)
				),
			),
			'check'  => array(
				'title'   => __( 'Check icon', 'form-steps-for-contact-form-7' ),
				'picture' => 'screen',
				'html'    => implode(
					"\n",
					array(
						'<div class="svfs-ty svfs-ty-card">',
						$icon,
						/* translators: [your-name] is replaced with the visitor's name; keep it as is. */
						'<h2 class="svfs-ty-title">' . esc_html__( 'Thanks, [your-name]!', 'form-steps-for-contact-form-7' ) . '</h2>',
						/* translators: [your-email] is replaced with the visitor's email; keep it and the <strong> tags as they are. */
						'<p class="svfs-ty-text">' . wp_kses( __( 'Your message is on its way. We will reply to <strong>[your-email]</strong> soon.', 'form-steps-for-contact-form-7' ), array( 'strong' => array() ) ) . '</p>',
						'</div>',
					)
				),
			),
			'steps'  => array(
				'title'   => __( 'What happens next', 'form-steps-for-contact-form-7' ),
				'picture' => 'ty_steps',
				'html'    => implode(
					"\n",
					array(
						'<div class="svfs-ty svfs-ty-card">',
						$icon,
						'<h2 class="svfs-ty-title">' . esc_html__( 'Request received', 'form-steps-for-contact-form-7' ) . '</h2>',
						/* translators: [your-name] is replaced with the visitor's name; keep it as is. */
						'<p class="svfs-ty-text">' . esc_html__( 'Thanks, [your-name]. Here is what happens next:', 'form-steps-for-contact-form-7' ) . '</p>',
						'<ol class="svfs-ty-steps">',
						'<li><strong>' . esc_html__( 'Today', 'form-steps-for-contact-form-7' ) . '</strong> ' . esc_html__( 'We read your request.', 'form-steps-for-contact-form-7' ) . '</li>',
						/* translators: [your-email] is replaced with the visitor's email; keep it as is. */
						'<li><strong>' . esc_html__( 'Within one business day', 'form-steps-for-contact-form-7' ) . '</strong> ' . esc_html__( 'We email you at [your-email].', 'form-steps-for-contact-form-7' ) . '</li>',
						'<li><strong>' . esc_html__( 'Then', 'form-steps-for-contact-form-7' ) . '</strong> ' . esc_html__( 'We agree on the next steps together.', 'form-steps-for-contact-form-7' ) . '</li>',
						'</ol>',
						'</div>',
					)
				),
			),
			'button' => array(
				'title'   => __( 'With a button', 'form-steps-for-contact-form-7' ),
				'picture' => 'ty_button',
				'html'    => implode(
					"\n",
					array(
						'<div class="svfs-ty svfs-ty-card">',
						$icon,
						'<h2 class="svfs-ty-title">' . esc_html__( 'All done!', 'form-steps-for-contact-form-7' ) . '</h2>',
						'<p class="svfs-ty-text">' . esc_html__( 'Thank you for getting in touch. We will be in touch shortly.', 'form-steps-for-contact-form-7' ) . '</p>',
						'<p class="svfs-ty-actions"><a class="svfs-ty-button" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Back to home', 'form-steps-for-contact-form-7' ) . '</a></p>',
						'</div>',
					)
				),
			),
		);
	}

	/**
	 * Data for the thank-you builder script: templates, fields, sample values,
	 * colors for the preview, and the AI prompt.
	 *
	 * @param \WPCF7_ContactForm $form        Form.
	 * @param string[]           $field_names Input field names.
	 * @return array
	 */
	private function thanks_builder_data( $form, array $field_names ) {
		$name_field  = '';
		$email_field = '';
		$samples     = array();
		foreach ( $form->scan_form_tags() as $tag ) {
			if ( '' === $tag->name || ! in_array( $tag->name, $field_names, true ) ) {
				continue;
			}
			if ( '' === $email_field && 'email' === $tag->basetype ) {
				$email_field = $tag->name;
			}
			if ( '' === $name_field && 'text' === $tag->basetype && false !== stripos( $tag->name, 'name' ) ) {
				$name_field = $tag->name;
			}
			if ( 'email' === $tag->basetype ) {
				$samples[ $tag->name ] = 'hana@example.com';
			} elseif ( false !== stripos( $tag->name, 'name' ) ) {
				$samples[ $tag->name ] = 'Hana Tanaka';
			} elseif ( in_array( $tag->basetype, array( 'tel' ), true ) ) {
				$samples[ $tag->name ] = '+81 90 1234 5678';
			} else {
				$samples[ $tag->name ] = '(' . $tag->name . ')';
			}
		}

		$templates = array();
		foreach ( self::thanks_templates() as $key => $template ) {
			$templates[ $key ] = $template['html'];
		}

		$settings = Form_Settings::resolved( $form );

		return array(
			'templates'      => $templates,
			'nameField'      => $name_field,
			'emailField'     => $email_field,
			'samples'        => (object) $samples,
			'cssUrl'         => Plugin::asset_url( 'css/form-steps', 'css' ) . '?ver=' . SVFS_VERSION,
			'skinsUrl'       => Plugin::asset_url( 'css/skins', 'css' ) . '?ver=' . SVFS_VERSION,
			'design'         => $settings['design'],
			'confirmReplace' => __( 'Replace the current thank-you text with this template?', 'form-steps-for-contact-form-7' ),
			'copied'         => __( 'Copied. Paste it into your AI tool.', 'form-steps-for-contact-form-7' ),
			'prompt'         => self::ai_prompt( $field_names, $settings['design'], (int) $form->id() ),
		);
	}

	/**
	 * AI prompt for the thank-you screen. {current} and {wish} are filled in by the script.
	 * Kept in English on purpose: AI tools follow English instructions best, and the
	 * prompt asks for the text in the site's language.
	 *
	 * @param string[] $field_names Input field names.
	 * @param array    $design      Resolved design.
	 * @param int      $form_id     Form ID, for CSS selectors.
	 * @return string
	 */
	private static function ai_prompt( array $field_names, array $design, $form_id ) {
		$fields = $field_names ? '[' . implode( '], [', $field_names ) . ']' : '(none)';
		$accent   = '' !== $design['accent'] ? $design['accent'] : '#2563eb';
		$form_sel = '.wpcf7 form.svfs-id-' . (int) $form_id;
		return implode(
			"\n",
			array(
				'Write the thank-you screen of a WordPress contact form (Contact Form 7 with the "Form Steps" plugin). It replaces the form after the visitor presses Send.',
				'',
				'Return two parts:',
				'1. HTML: one block, ready to paste.',
				'2. CSS for WordPress\'s Appearance → Additional CSS: only if the ready-made classes are not enough.',
				'',
				'HTML rules:',
				'- Allowed tags: div, section, p, h2, h3, h4, strong, em, span, a, ul, ol, li, br, img. Scripts, style tags, forms, iframes and event attributes are removed.',
				'- Write the text in this language: ' . get_bloginfo( 'language' ) . '.',
				'- To show what the visitor sent, write the field name in square brackets. Available fields: ' . $fields . '.',
				'- Ready-made classes, already styled by the plugin:',
				'  .svfs-ty  centered block with padding',
				'  .svfs-ty-card  adds a border and rounded corners (use with .svfs-ty)',
				'  .svfs-ty-icon  green check circle; an empty <span class="svfs-ty-icon" aria-hidden="true"></span>',
				'  .svfs-ty-title  heading (use on <h2>)',
				'  .svfs-ty-text  paragraph',
				'  .svfs-ty-steps  numbered "next steps" list; an <ol>, each <li> starts with <strong>title</strong>',
				'  .svfs-ty-actions  row for buttons and links (use on <p>)',
				'  .svfs-ty-button  main button (use on <a>)',
				'  .svfs-ty-link  secondary text link (use on <a>)',
				'',
				'CSS rules:',
				'- Start every selector with ' . $form_sel . ' so it only affects this form. Example: ' . $form_sel . ' .svfs-ty-title { color: #0f766e; }',
				'- Colors are CSS variables you can set on ' . $form_sel . ': --svfs-accent (main color, now ' . $accent . '), --svfs-accent-text, --svfs-accent-hover, --svfs-success (check icon), --svfs-card-bg (card background), --svfs-radius.',
				'- No @import and no external fonts.',
				'',
				'Current HTML to improve (may be empty):',
				'---',
				'{current}',
				'---',
				'',
				'What I want: {wish}',
			)
		);
	}

	/**
	 * Prints radio choices as cards with a small picture of the result.
	 *
	 * @param string $name    Input name.
	 * @param string $current Selected value.
	 * @param array  $choices Value => array( title, text, picture key ).
	 * @param string $extra   Extra class for the card grid.
	 */
	private function cards( $name, $current, array $choices, $extra = '' ) {
		echo '<div class="svfs-cards ' . esc_attr( $extra ) . '">';
		foreach ( $choices as $value => $choice ) {
			?>
			<label class="svfs-card">
				<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" <?php checked( (string) $value, (string) $current ); ?> />
				<?php self::picture( $choice[2] ); ?>
				<span class="svfs-card-title"><?php echo esc_html( $choice[0] ); ?></span>
				<span class="svfs-card-text"><?php echo esc_html( $choice[1] ); ?></span>
			</label>
			<?php
		}
		echo '</div>';
	}

	/**
	 * A small drawing, made of plain elements and CSS, of what a choice looks like.
	 *
	 * @param string $key Picture key.
	 */
	public static function picture( $key ) {
		$field   = '<span class="svfs-m-label"></span><span class="svfs-m-input"></span>';
		$answer  = '<span class="svfs-m-label"></span><span class="svfs-m-answer"></span>';
		$buttons = '<span class="svfs-m-buttons"><span class="svfs-m-btn is-ghost"></span><span class="svfs-m-btn"></span></span>';
		$parts   = array(
			'inplace'   => $answer . $answer . $buttons,
			'table'     => '<span class="svfs-m-row"><span></span><span></span></span><span class="svfs-m-row"><span></span><span></span></span><span class="svfs-m-row"><span></span><span></span></span>' . $buttons,
			'numbers'   => '<span class="svfs-m-steps"><span class="is-on">1</span><span>2</span><span>3</span></span>' . $field,
			'bar'       => '<span class="svfs-m-bar"><span></span></span>' . $field,
			'none'      => $field . $field,
			'message'   => $field . '<span class="svfs-m-btn is-solo"></span><span class="svfs-m-ok"></span>',
			'screen'    => '<span class="svfs-m-check"></span><span class="svfs-m-line"></span><span class="svfs-m-line is-short"></span>',
			'redirect'  => '<span class="svfs-m-page"><span class="svfs-m-arrow"></span><span class="svfs-m-sheet"></span></span>',
			'ty_simple' => '<span class="svfs-m-line is-title"></span><span class="svfs-m-line"></span><span class="svfs-m-line is-short"></span>',
			'ty_steps'  => '<span class="svfs-m-check is-small"></span><span class="svfs-m-li"></span><span class="svfs-m-li"></span><span class="svfs-m-li"></span>',
			'ty_button' => '<span class="svfs-m-check is-small"></span><span class="svfs-m-line"></span><span class="svfs-m-btn is-center"></span>',
		);
		if ( ! isset( $parts[ $key ] ) ) {
			return;
		}
		echo wp_kses(
			'<span class="svfs-mock" aria-hidden="true">' . $parts[ $key ] . '</span>',
			array(
				'span' => array(
					'class'       => true,
					'aria-hidden' => true,
				),
			)
		);
	}

	/**
	 * Names of the input fields in a form, for the label overrides.
	 *
	 * @param \WPCF7_ContactForm $form Form.
	 * @return string[]
	 */
	private function field_names( $form ) {
		$skip  = array( 'submit', 'hidden', 'acceptance', 'quiz', 'captchac', 'captchar', 'turnstile', 'stripe', 'confirm', 'back', 'step', 'response' );
		$names = array();
		foreach ( $form->scan_form_tags() as $tag ) {
			if ( '' === $tag->name || in_array( $tag->basetype, $skip, true ) ) {
				continue;
			}
			$names[ $tag->name ] = $tag->name;
		}
		return array_values( $names );
	}

	/**
	 * Prints a vertical list of radio buttons.
	 *
	 * @param string $name     Input name.
	 * @param string $current  Selected value.
	 * @param array  $choices  Value => label.
	 */
	private function radios( $name, $current, array $choices ) {
		echo '<fieldset class="svfs-radios">';
		foreach ( $choices as $value => $label ) {
			printf(
				'<label><input type="radio" name="%1$s" value="%2$s" %3$s /> %4$s</label><br />',
				esc_attr( $name ),
				esc_attr( $value ),
				checked( (string) $value, (string) $current, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset>';
	}
}
