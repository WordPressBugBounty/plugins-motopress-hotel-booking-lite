<?php

declare(strict_types=1);

namespace MPHB\Advanced\Api\Controllers\V1;

use MPHB\Advanced\Api\Controllers\AbstractRestCommandController;
use MPHB\Entities\{ Booking, Payment };
use MPHB\Shortcodes\CheckoutShortcode;
use MPHB\Utils\ParseUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Separate controller only for payments for checkouts such as Payment Request.
 *
 * Route: /checkout/payments (POST)
 */
class SubmitPaymentController extends AbstractRestCommandController {
	private static string $gatewayRedirect = '';

	/**
	 * @access private
	 *
	 * @param string $location
	 */
	public static function disableGatewayRedirectsFilter( $location ): string {
		self::$gatewayRedirect = (string) $location;

		// Disable redirect
		// https://developer.wordpress.org/reference/functions/wp_redirect/#source
		return '';
	}

	public static function get_route(): string {
		return '/checkout/payments';
	}

	public static function get_supported_methods(): string {
		return \WP_REST_Server::CREATABLE;
	}

	/**
	 * @return \WP_Error|bool
	 */
	public static function is_request_allowed( \WP_REST_Request $request ) {
		return true; // Public request, but requires a nonce check (in the body)
	}

	protected static function get_request_schema(): array {
		return array(
			'amount'         => array(
				'type'              => 'number',
				'minimum'           => 0.01,
				'required'          => true,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'booking_id'     => array(
				'type'              => 'integer',
				'minimum'           => 1,
				'required'          => true,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'custom_fields'  => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'sanitize_callback'    => 'rest_sanitize_request_arg',
			),
			'gateway_id'     => array(
				'type'              => 'string',
				// It is better to check "gateway_id" after the
				// "mphb_focus_on_booking" action
//				'enum'              => array_merge(
//					array( 'manual' ),
//					array_keys( MPHB()->gatewayManager()->getListActive() )
//				),
				'required'          => true,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'nonce'          => array(
				'type'              => 'string',
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'payment_fields' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'sanitize_callback'    => 'rest_sanitize_request_arg',
			),
		);
	}

	public static function get_response_schema(): array {
		return array(
			'$schema'     => 'http://json-schema.org/draft-04/schema#',
			'component'   => 'checkout-payment',
			'title'       => 'checkout',
			'type'        => 'object',
			'properties'  => array(
				'payment_id'      => array(
					'type' => 'integer',
				),
				'payment_fields'  => array(
					'type' => array( 'object', 'null' ),
				),
				'redirect_url'    => array(
					'type'        => 'string',
					'description' => 'URL to redirect from the gateway for further processing or to the results page.',
					'format'      => 'uri',
				),
				'success_message' => array(
					'type' => 'string',
				),
			),
		);
	}

	/**
	 * @return array <code>[ payment_id, payment_details?, redirect_url?,
	 *     success_message ]</code>.
	 *
	 * @throws \RuntimeException in case of any error.
	 */
	protected static function process_and_get_data_by_response_schema( \WP_REST_Request $request ) {
		add_filter( 'mphb_checkout_step', fn() => CheckoutShortcode::STEP_CHECKOUT );

		if ( MPHB()->settings()->main()->isBookingDisabled() ) {
			throw new \RuntimeException( esc_html__( 'Booking is blocked due to maintenance reason. Please try again later.', 'motopress-hotel-booking' ) );
		}

		$requestArgs = $request->get_params();

		$booking = mphb_bookings_facade()->findBookingById( $requestArgs['booking_id'] );

		if ( $booking === null ) {
			throw new \RuntimeException( esc_html__( 'The booking not found.', 'motopress-hotel-booking' ) );
		}

		// Check the nonce
		if ( ! isset( $requestArgs['nonce'] ) ) {
			// Update the Payment Request plugin so that it starts adding the nonce
			throw new \RuntimeException( esc_html__( 'The site needs a security update.', 'motopress-hotel-booking' ) );
		}

		$isNonceOk = wp_verify_nonce(
			$requestArgs['nonce'],
			$booking->getKey() . $booking->getCheckoutId()
		);

		/**
		 * @param bool $isNonceOk
		 * @param Booking $booking
		 * @param array $requestArgs
		 */
		$isNonceOk = apply_filters( 'mphb_payment_checkout_verify_nonce', $isNonceOk, $booking, $requestArgs );

		if ( ! $isNonceOk ) {
			throw new \RuntimeException( esc_html__( 'Request does not pass security verification. Please refresh the page and try one more time.', 'motopress-hotel-booking' ) );
		}

		/**
		 * @param Booking $booking
		 */
		do_action( 'mphb_focus_on_booking', $booking ); // For Accommodation-Based Payments

		$gatewayId = ParseUtils::parseGatewayId( $requestArgs['gateway_id'], $booking );

		// "manual" fallback method is OK only for /checkout
		if ( $gatewayId === 'manual' ) {
			throw new \RuntimeException( esc_html__( 'Payment method is not valid.', 'motopress-hotel-booking' ) );
		}

		$gateway = MPHB()->gatewayManager()->getGateway( $gatewayId );

		// Parse payment fields
		$paymentFields = $requestArgs['payment_fields'] ?? array();

		$errors = array();
		$gateway->parsePaymentFields( $paymentFields, $errors );

		if ( ! empty( $errors ) ) {
			throw new \RuntimeException( implode( ' ', $errors ) );
		}

		// Create payment
		$paymentFee    = $gateway->calculatePaymentFee( $requestArgs['amount'] );
		$paymentAmount = $requestArgs['amount'] - $paymentFee;

		$paymentDetails = array(
			'amount'      => $paymentAmount,
			'bookingId'   => $booking->getId(),
			'currency'    => MPHB()->settings()->currency()->getCurrencyCode(),
			'gatewayId'   => $gateway->getId(),
			'gatewayMode' => $gateway->getMode(),
			'paymentFee'  => $paymentFee,
		);

		/**
		 * @param array $paymentDetails
		 */
		$paymentDetails = apply_filters( 'mphb_checkout_payment_details', $paymentDetails );
		$payment = Payment::create( $paymentDetails );

		/**
		 * @param Payment $payment
		 * @param Booking $booking
		 * @param array $requestArgs
		 */
		do_action( 'mphb_submit_payment', $payment, $booking, $requestArgs );

		$isSaved = MPHB()->getPaymentRepository()->save( $payment );

		if ( ! $isSaved ) {
			throw new \RuntimeException( esc_html__( 'Server error. Failed to create payment for this booking.', 'motopress-hotel-booking' ) );
		}

		/**
		 * Used by Payment Request to set up request.
		 *
		 * @param Payment $payment
		 * @param Booking $booking
		 * @param array $requestArgs
		 */
		do_action( 'mphb_before_submit_payment', $payment, $booking, $requestArgs );

		$gateway->storePaymentFields( $payment );

		// Re-get payment. Some gateways may update metadata without entity update.
		$payment = mphb_bookings_facade()->findPaymentById( $payment->getId(), $ignoreCache = true );

		$responseData = array(
			'payment_id'   => $payment->getId(),
			'redirect_url' => MPHB()->settings()->pages()->getReservationReceivedPageUrl( $payment ),
		);

		// Process payment
		add_filter( 'wp_redirect', array( self::class, 'disableGatewayRedirectsFilter' ), PHP_INT_MAX );

		try {
			$paymentFields = $gateway->processPayment( $booking, $payment );

			if ( $payment->isFailed() ) {
				throw new \RuntimeException( esc_html__( 'Unable to process your payment.', 'motopress-hotel-booking' ) );
			}

			if ( ! empty( $paymentFields ) && is_array( $paymentFields ) ) {
				$responseData['payment_fields'] = $paymentFields;
			}

			if ( $payment->isFinished() ) {
				$responseData['success_message'] = __( 'Thank you for your payment. Your transaction has been completed.', 'motopress-hotel-booking' );
			} else {
				$responseData['success_message'] = __( 'Thank you for your payment. Your transaction is being processed.', 'motopress-hotel-booking' );
			}

		} catch ( \Exception $e ) {
			$errorMessage = sprintf(
				// Translators: %s: Error message text.
				__( 'Unable to process your payment. %s', 'motopress-hotel-booking' ),
				$e->getMessage()
			);

			MPHB()->paymentManager()->failPayment( $payment, $errorMessage );

			throw new \RuntimeException( esc_html( $errorMessage ) );
		}

		remove_filter( 'wp_redirect', array( self::class, 'disableGatewayRedirectsFilter' ), PHP_INT_MAX );

		if ( self::$gatewayRedirect !== '' ) {
			$responseData['redirect_url'] = self::$gatewayRedirect;
		}

		/**
		 * @param Payment $payment
		 * @param Booking $booking
		 * @param array $requestArgs
		 */
		do_action( 'mphb_payment_submitted', $payment, $booking, $requestArgs );

		return $responseData;
	}
}
