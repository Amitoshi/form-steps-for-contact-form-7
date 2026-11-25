<?php
/**
 * Server side of the Review and Next clicks: validate, then stop before sending.
 *
 * @package AmitShaw\FormSteps
 */

namespace AmitShaw\FormSteps;

defined( 'ABSPATH' ) || exit;

/**
 * How it works
 *
 * Contact Form 7 processes a submission in this order: validation, acceptance,
 * spam checks (reCAPTCHA, Turnstile, Akismet), file handling, the
 * wpcf7_before_send_mail hook used by CRM, Sheets and database add-ons, then mail.
 *
 * When the hidden field _svfs_phase is "confirm" or "step" and validation passed,
 * the acceptance filter returns false at the last priority. Contact Form 7 then
 * skips everything after it: no spam check consumes a captcha token, no add-on
 * runs, no mail is sent. The status is renamed so the browser script can react.
 *
 * A phase value can only prevent sending, never cause it.
 */
final class Phase {

	const FIELD = '_svfs_phase';

	const STEP_FIELDS = '_svfs_fields';

	const PHASES = array( 'confirm', 'step' );

	/**
	 * Phase that stopped the current submission, or empty.
	 *
	 * @var string
	 */
	private $stopped = '';

	/**
	 * Registers hooks.
	 */
	public function __construct() {
		add_filter( 'wpcf7_validate_captchar', array( $this, 'check_captcha_keep_answer' ), 9, 2 );
		add_filter( 'wpcf7_validate', array( $this, 'limit_to_step' ), PHP_INT_MAX, 2 );
		add_filter( 'wpcf7_acceptance', array( $this, 'stop_after_validation' ), PHP_INT_MAX, 1 );
		add_filter( 'wpcf7_submission_result', array( $this, 'rename_status' ), 10, 1 );
		add_filter( 'wpcf7_feedback_response', array( $this, 'keep_challenges' ), 20, 1 );
	}

	/**
	 * Phase requested by the browser for the current request.
	 *
	 * @return string "confirm", "step" or empty for a normal submission.
	 */
	public static function requested() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Read inside Contact Form 7's own submission handling, which applies its own checks. This flag can only stop a submission, never send one.
		if ( ! isset( $_POST[ self::FIELD ] ) || ! is_string( $_POST[ self::FIELD ] ) ) {
			return '';
		}
		$phase = sanitize_key( wp_unslash( $_POST[ self::FIELD ] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return in_array( $phase, self::PHASES, true ) ? $phase : '';
	}

	/**
	 * Names of the fields on the step the visitor is leaving.
	 *
	 * @return string[]
	 */
	public static function step_fields() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- See requested().
		if ( ! isset( $_POST[ self::STEP_FIELDS ] ) || ! is_string( $_POST[ self::STEP_FIELDS ] ) ) {
			return array();
		}
		$raw = sanitize_text_field( wp_unslash( $_POST[ self::STEP_FIELDS ] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$names = array_map( 'trim', explode( ',', $raw ) );
		return array_values( array_unique( array_filter( $names, array( Text::class, 'is_field_name' ) ) ) );
	}

	/**
	 * Image captcha (Really Simple CAPTCHA): Contact Form 7 deletes the answer as soon
	 * as it checks it, which would make the final Send fail after Review or Next.
	 * During Review and Next this runs the same check without deleting the answer,
	 * and switches off Contact Form 7's own check for this request only. The final
	 * Send uses Contact Form 7's normal check, which then deletes the answer.
	 *
	 * @param \WPCF7_Validation $result Validation result.
	 * @param \WPCF7_FormTag    $tag    Captcha answer tag.
	 * @return \WPCF7_Validation
	 */
	public function check_captcha_keep_answer( $result, $tag ) {
		$phase = self::requested();
		if ( '' === $phase || ! $tag instanceof \WPCF7_FormTag || ! function_exists( 'wpcf7_check_captcha' ) ) {
			return $result;
		}

		remove_filter( 'wpcf7_validate_captchar', 'wpcf7_captcha_validation_filter', 10 );

		if ( 'step' === $phase && ! in_array( $tag->name, self::step_fields(), true ) ) {
			return $result; // The captcha is on a later step.
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- See requested().
		$prefix_key = '_wpcf7_captcha_challenge_' . $tag->name;
		$prefix     = isset( $_POST[ $prefix_key ] ) && is_string( $_POST[ $prefix_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $prefix_key ] ) ) : '';
		$response   = isset( $_POST[ $tag->name ] ) && is_string( $_POST[ $tag->name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $tag->name ] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( function_exists( 'wpcf7_canonicalize' ) ) {
			$response = wpcf7_canonicalize( $response );
		}
		if ( ! wpcf7_check_captcha( $prefix, $response ) ) {
			$result->invalidate( $tag, wpcf7_get_message( 'captcha_not_match' ) );
		}
		return $result;
	}

	/**
	 * On Next, keeps only the errors of fields on the current step, so required
	 * fields on later steps do not block the visitor.
	 *
	 * @param \WPCF7_Validation $result Validation result.
	 * @param array             $tags   Form-tags of the form.
	 * @return \WPCF7_Validation
	 */
	public function limit_to_step( $result, $tags = array() ) {
		if ( 'step' !== self::requested() || ! $result instanceof \WPCF7_Validation || $result->is_valid() ) {
			return $result;
		}

		$allowed = self::step_fields();
		$by_name = array();
		foreach ( (array) $tags as $tag ) {
			if ( $tag instanceof \WPCF7_FormTag && '' !== $tag->name ) {
				$by_name[ $tag->name ] = $tag;
			}
		}

		$limited = new \WPCF7_Validation();
		foreach ( $result->get_invalid_fields() as $name => $field ) {
			if ( in_array( $name, $allowed, true ) && isset( $by_name[ $name ] ) ) {
				$limited->invalidate( $by_name[ $name ], isset( $field['reason'] ) ? $field['reason'] : '' );
			}
		}
		return $limited;
	}

	/**
	 * Stops a valid Review or Next submission before spam checks and sending.
	 * Contact Form 7 only calls this filter when validation has passed.
	 *
	 * @param bool $accepted Whether acceptance checkboxes were ticked.
	 * @return bool
	 */
	public function stop_after_validation( $accepted ) {
		$phase = self::requested();
		if ( '' === $phase ) {
			return $accepted;
		}
		if ( 'step' === $phase ) {
			// Contact Form 7 checked every acceptance box in the form. On Next,
			// only boxes on the current step count.
			$accepted = $this->step_accepted();
		}
		if ( ! $accepted ) {
			return false;
		}
		$this->stopped = $phase;
		return false;
	}

	/**
	 * Same rule as Contact Form 7's acceptance filter, limited to the current step.
	 *
	 * @return bool
	 */
	private function step_accepted() {
		$allowed = self::step_fields();
		foreach ( wpcf7_scan_form_tags( array( 'type' => 'acceptance' ) ) as $tag ) {
			if ( '' === $tag->name || ! in_array( $tag->name, $allowed, true ) || $tag->has_option( 'optional' ) ) {
				continue;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- See requested().
			$checked = ! empty( $_POST[ $tag->name ] );
			if ( $tag->has_option( 'invert' ) === $checked ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Gives a stopped submission its own status and an empty message.
	 *
	 * @param array $result Submission result.
	 * @return array
	 */
	public function rename_status( $result ) {
		if ( '' === $this->stopped || ! is_array( $result ) ) {
			return $result;
		}
		$result['status']  = 'svfs_' . $this->stopped;
		$result['message'] = '';
		return $result;
	}

	/**
	 * Keeps the quiz question and image captcha unchanged after Review or Next,
	 * so the visitor's answer is still valid when they finally send.
	 *
	 * @param array $response REST feedback response.
	 * @return array
	 */
	public function keep_challenges( $response ) {
		if ( '' === $this->stopped || ! is_array( $response ) ) {
			return $response;
		}
		unset( $response['quiz'], $response['captcha'] );
		return $response;
	}
}
