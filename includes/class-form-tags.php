<?php
/**
 * Form-tags: [confirm] and [back], compatible with "Contact Form 7 add confirm".
 *
 * @package AmitShaw\FormSteps
 */

namespace AmitShaw\FormSteps;

defined( 'ABSPATH' ) || exit;

/**
 * The tags keep the class names of the old plugin (wpcf7c-elm-step1,
 * wpcf7c-btn-confirm, wpcf7c-elm-step2, wpcf7c-btn-back), so forms and custom
 * CSS written for it keep working without edits.
 */
final class Form_Tags {

	/**
	 * Registers hooks.
	 */
	public function __construct() {
		add_action( 'wpcf7_init', array( $this, 'register' ), 20 );
	}

	/**
	 * Registers the form-tags with Contact Form 7.
	 */
	public function register() {
		wpcf7_add_form_tag( 'confirm', array( $this, 'confirm_button' ), array( 'name-attr' => false ) );
		wpcf7_add_form_tag( 'back', array( $this, 'back_button' ), array( 'name-attr' => false ) );
		// display-block keeps the marker out of the paragraph that Contact Form 7's
		// automatic formatting would otherwise share with the step's first field.
		wpcf7_add_form_tag(
			'step',
			array( $this, 'step_marker' ),
			array(
				'name-attr'     => false,
				'display-block' => true,
			)
		);
	}

	/**
	 * [step "Title"]: starts a new step. Outputs an invisible marker that the
	 * browser script uses to group the content that follows it.
	 *
	 * @param \WPCF7_FormTag $tag Form-tag.
	 * @return string
	 */
	public function step_marker( $tag ) {
		$title = isset( $tag->values[0] ) ? trim( (string) $tag->values[0] ) : '';
		return sprintf(
			'<div %s></div>',
			wpcf7_format_atts(
				array(
					'class'      => 'svfs-step-marker',
					'data-title' => $title,
					'hidden'     => true,
				)
			)
		);
	}

	/**
	 * [confirm "Label"]: the Review button. Validates without sending.
	 *
	 * @param \WPCF7_FormTag $tag Form-tag.
	 * @return string
	 */
	public function confirm_button( $tag ) {
		return $this->button(
			$tag,
			'submit',
			'wpcf7-form-control wpcf7c-elm-step1 wpcf7c-btn-confirm svfs-review has-spinner',
			'review'
		);
	}

	/**
	 * [back "Label"]: returns from the confirm screen to the form.
	 *
	 * @param \WPCF7_FormTag $tag Form-tag.
	 * @return string
	 */
	public function back_button( $tag ) {
		return $this->button(
			$tag,
			'button',
			'wpcf7-form-control wpcf7c-elm-step2 wpcf7c-btn-back svfs-back',
			'back'
		);
	}

	/**
	 * Builds a button input.
	 *
	 * @param \WPCF7_FormTag $tag       Form-tag.
	 * @param string         $type      Input type.
	 * @param string         $classes   Default classes.
	 * @param string         $label_key Settings key for the default label.
	 * @return string
	 */
	private function button( $tag, $type, $classes, $label_key ) {
		$label = isset( $tag->values[0] ) ? trim( (string) $tag->values[0] ) : '';
		if ( '' === $label ) {
			$label = $this->default_label( $label_key );
		}

		$atts = array(
			'type'     => $type,
			'value'    => $label,
			'class'    => $tag->get_class_option( $classes ),
			'id'       => $tag->get_id_option(),
			'tabindex' => $tag->get_option( 'tabindex', 'signed_int', true ),
		);

		return sprintf( '<input %s />', wpcf7_format_atts( $atts ) );
	}

	/**
	 * Label from the form's settings, the site defaults, or the translated default.
	 *
	 * @param string $key Label key.
	 * @return string
	 */
	private function default_label( $key ) {
		$form = function_exists( 'wpcf7_get_current_contact_form' ) ? wpcf7_get_current_contact_form() : null;
		if ( $form instanceof \WPCF7_ContactForm ) {
			$settings = Form_Settings::resolved( $form );
			if ( '' !== $settings['labels'][ $key ] ) {
				return $settings['labels'][ $key ];
			}
		}
		$defaults = Global_Settings::default_labels();
		return $defaults[ $key ];
	}
}
