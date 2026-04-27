<?php

declare(strict_types=1);

namespace MPHB\Shortcodes;

use MPHB\Shortcodes\CheckoutShortcode\{ StepBooking, StepCheckout };

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CheckoutShortcode extends AbstractShortcode {
	public const CHECKOUT_ID_FIELD = 'mphb-checkout-id';

	/**
	 * Successful booking and confirmation by email or by admin manually.
	 *
	 * Page URL: "...?step=booking".
	 */
	public const STEP_BOOKING = 'booking';

	/**
	 * On the checkout page, filling the booking form.
	 */
	public const STEP_CHECKOUT = 'checkout';

	/**
	 * Successful booking and confirmation by payment, when going from checkout
	 * to the "Reservation Received" page.
	 */
	public const STEP_COMPLETE = 'complete';

	/**
	 * Not on the checkout page.
	 */
	public const STEP_NONE = 'none';

	protected $name = 'mphb_checkout';

	private string $currentStep = '';

	private bool $isCheckoutPage = false;
	private bool $isCorrectNonce = true;

	private StepBooking  $stepBooking;
	private StepCheckout $stepCheckout;

	public function __construct() {
		parent::__construct();

		$this->stepBooking  = new StepBooking();
		$this->stepCheckout = new StepCheckout();

		add_action( 'mphb_sc_checkout_errors_content', array( $this, 'showErrorsContent' ) );
		add_filter( 'mphb_sc_checkout_error', array( $this, 'filterErrorOutput' ) );

		add_action( 'wp', array( $this, 'setup' ) );

		add_action( 'template_redirect', array( $this, 'enforceSSLRedirect' ) );
	}

	/**
	 * It is better not to run this method before setup() (the "wp" action).
	 */
	public function getCurrentStep(): string {
		if ( $this->currentStep !== '' ) {
			return $this->currentStep;
		}

		$step = self::STEP_NONE;

		if ( $this->isCheckoutPage ) {
			if ( isset( $_REQUEST['step'] ) ) {
				// STEP_BOOKING
				$step = sanitize_text_field( wp_unslash( $_REQUEST['step'] ) );
			} else {
				$step = self::STEP_CHECKOUT;
			}
		} else {
			$checkoutPageUrl = MPHB()->settings()->pages()->getCheckoutPageUrl();

			if ( mphb_is_reservation_received_page() && wp_get_referer() === $checkoutPageUrl ) {
				$step = self::STEP_COMPLETE;
			}
		}

		/**
		 * @hooked SubmitCheckoutController::process_and_get_data_by_response_schema() - 10
		 * @hooked SubmitPaymentController::process_and_get_data_by_response_schema()  - 10
		 *
		 * @param string $step "none"|"checkout"|"booking"|"complete"
		 */
		return apply_filters( 'mphb_checkout_step', $step );
	}

	public function getStepUrl( string $step ): string {
		switch ( $step ) {
			case self::STEP_CHECKOUT:
			case self::STEP_NONE:
				return MPHB()->settings()->pages()->getCheckoutPageUrl();

			case self::STEP_COMPLETE:
				return MPHB()->settings()->pages()->getReservationReceivedPageUrl();

			case self::STEP_BOOKING:
			default:
				$checkoutPageUrl = MPHB()->settings()->pages()->getCheckoutPageUrl();

				$stepUrl = add_query_arg( 'step', $step, $checkoutPageUrl );
				$stepUrl = add_query_arg( 'step_nonce', wp_create_nonce( "mphb_checkout_step_{$step}" ), $stepUrl );

				return $stepUrl;
		}
	}

	/**
	 * @access private
	 */
	public function setup(): void {
		$this->isCheckoutPage = mphb_is_checkout_page();
		$this->isCorrectNonce = ! $this->isCheckoutPage || $this->checkNonce();

		$this->currentStep = $this->getCurrentStep();

		// Set up step
		if ( $this->isCheckoutPage && $this->isCorrectNonce ) {
			switch ( $this->currentStep ) {
				case self::STEP_CHECKOUT: $this->stepCheckout->setup(); break;
				case self::STEP_BOOKING:  $this->stepBooking->setup();  break;
			}
		}
	}

	/**
	 * @param array $atts
	 * @param string $content
	 * @param string $shortcodeName
	 * @return string
	 */
	public function render( $atts, $content, $shortcodeName ) {
		$defaultAtts = array(
			'class' => '',
		);

		$atts = shortcode_atts( $defaultAtts, $atts, $shortcodeName );

		ob_start();

		if ( MPHB()->settings()->main()->isBookingDisabled() ) {
			echo '<p>';
				esc_html_e( 'Bookings are disabled in the settings.', 'motopress-hotel-booking' );
			echo '</p>';
		} elseif ( $this->isCheckoutPage && $this->isCorrectNonce ) {
			switch ( $this->getCurrentStep() ) {
				case self::STEP_CHECKOUT: $this->stepCheckout->render(); break;
				case self::STEP_BOOKING:  $this->stepBooking->render();  break;
			}
		}

		$content = ob_get_clean();

		$wrapperClass  = apply_filters( 'mphb_sc_checkout_wrapper_classes', 'mphb_sc_checkout-wrapper' );
		$wrapperClass .= empty( $wrapperClass ) ? $atts['class'] : ' ' . $atts['class'];

		return '<div class="' . esc_attr( $wrapperClass ) . '">' . $content . '</div>';
	}

	public function showErrorsContent( array $errors ): void {
		foreach ( $errors as $error ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo apply_filters( 'mphb_sc_checkout_error', $error );
		}
	}

	/**
	 * @access private
	 */
	public function filterErrorOutput( string $error ): string {
		return '<br/>' . $error;
	}

	/**
	 * Handle redirections for SSL enforced checkouts.
	 */
	public function enforceSSLRedirect(): void {
		if ( is_ssl() ) {
			return;
		}

		if ( ! mphb_is_checkout_page() || ! MPHB()->settings()->payment()->isForceCheckoutSSL() ) {
			return;
		}

		$requestedURI = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( 0 === strpos( $requestedURI, 'http' ) ) {
			$url = preg_replace( '|^http://|', 'https://', $requestedURI );
		} else {
			$url = 'https://';

			if ( ! empty( $_SERVER['HTTP_X_FORWARDED_HOST'] ) ) {
				$url .= sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_HOST'] ) );
			} elseif ( isset( $_SERVER['HTTP_HOST'] ) ) {
				$url .= sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
			} else {
				$url = get_site_url();
			}

			$url .= $requestedURI;
		}

		$isRedirecting = wp_safe_redirect( $url );

		if ( $isRedirecting ) {
			exit;
		}
	}

	private function checkNonce(): bool {
		$step = $this->getCurrentStep();

		switch ( $step ) {
			case self::STEP_CHECKOUT:
				// Skip nonce verification for logged in users during checkout.
				// Because nonce fields are different before and after
				// authorization.
				return true;

			case self::STEP_COMPLETE:
			case self::STEP_NONE:
				return true; // No check needed

			case self::STEP_BOOKING:
			default:
				if ( ! isset( $_REQUEST['step_nonce'] ) ) {
					return false;
				}

				$nonce = sanitize_text_field( wp_unslash( $_REQUEST['step_nonce'] ) );

				return (bool) wp_verify_nonce( $nonce, "mphb_checkout_step_{$step}" );
		}
	}
}
