<?php

namespace MPHB\AjaxApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateStripePaymentIntent extends AbstractAjaxApiAction {

	const REQUEST_DATA_AMOUNT = 'amount';
	const REQUEST_DATA_DESCRIPTION = 'description';
	const REQUEST_DATA_PAYMENT_METHOD_TYPE = 'paymentMethodType'; // for example: card
	const REQUEST_DATA_PAYMENT_METHOD_ID = 'paymentMethodId';
	const REQUEST_DATA_IDEMPOTENCY_KEY = 'idempotencyKey';
	const REQUEST_DATA_ROOM_TYPE_IDS = 'roomTypeIds';
	const REQUEST_DATA_CUSTOMER_EMAIL = 'customerEmail';

	public static function getAjaxActionNameWithouPrefix() {
		return 'create_stripe_payment_intent';
	}

	/**
	 * @return array [ request_key (string) => request_value (mixed) ]
	 * @throws Exception when validation of request parameters failed
	 */
	protected static function getValidatedRequestData() {

		$requestData = parent::getValidatedRequestData();

		$requestData[ static::REQUEST_DATA_AMOUNT ] = static::getFloatFromRequest( static::REQUEST_DATA_AMOUNT );

		if ( 0 >= $requestData[ static::REQUEST_DATA_AMOUNT ] ) {
			throw new \Exception( __( 'Please complete all required fields and try again.', 'motopress-hotel-booking' ) );
		}

		$requestData[ static::REQUEST_DATA_DESCRIPTION ] = mphb_clean(
			static::getStringFromRequest( static::REQUEST_DATA_DESCRIPTION )
		);
		$requestData[ static::REQUEST_DATA_PAYMENT_METHOD_TYPE ] = static::getStringFromRequest( static::REQUEST_DATA_PAYMENT_METHOD_TYPE, true );
		$requestData[ static::REQUEST_DATA_PAYMENT_METHOD_ID ] = static::getStringFromRequest( static::REQUEST_DATA_PAYMENT_METHOD_ID, true );
		$requestData[ static::REQUEST_DATA_IDEMPOTENCY_KEY ] = static::getStringFromRequest( static::REQUEST_DATA_IDEMPOTENCY_KEY );
		$requestData[ static::REQUEST_DATA_ROOM_TYPE_IDS ] = static::getIdListFromRequest( static::REQUEST_DATA_ROOM_TYPE_IDS );
		$requestData[ static::REQUEST_DATA_CUSTOMER_EMAIL ] = static::getEmailFromRequest( static::REQUEST_DATA_CUSTOMER_EMAIL );

		return $requestData;
	}

	protected static function doAction( array $requestData ) {

		$currency  = MPHB()->settings()->currency()->getCurrencyCode();
		$gateway   = MPHB()->gatewayManager()->getStripeGateway();
		$stripeApi = $gateway->getApi();

		// Check allowed payment methods
		$paymentMethod = $requestData[ static::REQUEST_DATA_PAYMENT_METHOD_TYPE ];
		$allowedPaymentMethods = $gateway->getAllowedPaymentMethods();

		if ( ! in_array( $paymentMethod, $allowedPaymentMethods, true ) ) {
			throw new \Exception(
				sprintf(
					// Translators: %s: Payment method type, like "card".
					esc_html__( 'Could not create PaymentIntent for a not allowed payment type: %s', 'motopress-hotel-booking' ),
					$paymentMethod
				)
			);
		}

		/**
		 * @param int[] $roomTypeIds
		 */
		do_action( 'mphb_create_stripe_payment_intent_for_room_types', $requestData[ static::REQUEST_DATA_ROOM_TYPE_IDS ] );

		$receiptEmail = $gateway->isSendReceiptEmail()
			? $requestData[ static::REQUEST_DATA_CUSTOMER_EMAIL ]
			: '';

		$paymentIntent = $stripeApi->createPaymentIntentForLegacyMethods(
			$paymentMethod,
			$requestData[ static::REQUEST_DATA_PAYMENT_METHOD_ID ],
			$requestData[ static::REQUEST_DATA_AMOUNT ],
			$currency,
			$requestData[ static::REQUEST_DATA_DESCRIPTION ],
			$receiptEmail,
			// we do not send idempotency_key because in case when card payment is failed
			// we do not refresh page and idempotency_key but we can not use it twise
			// so as quick fix we just do not send it at all!
			// array(
			// 	'idempotency_key' => $requestData[ static::REQUEST_DATA_IDEMPOTENCY_KEY ]
			// ),
		);

		wp_send_json_success(
			array(
				'id'            => $paymentIntent->id,
				'client_secret' => $paymentIntent->client_secret,
			),
			200
		);
	}
}
