<?php

declare(strict_types=1);

namespace MPHB\Advanced\Api\Controllers\V1;

use MPHB\Advanced\Api\ApiHelper;
use MPHB\PostTypes\BookingCPT\Statuses as BookingStatuses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Route: /checkout/admin (POST)
 */
class SubmitAdminCheckoutController extends SubmitCheckoutController {
	public static function get_route(): string {
		return '/checkout/admin';
	}

	/**
	 * @return \WP_Error|bool
	 */
	public static function is_request_allowed( \WP_REST_Request $request ) {
		return ApiHelper::checkPostPermissions( MPHB()->postTypes()->booking()->getPostType(), 'create' );
	}

	protected static function get_request_schema(): array {
		$requestSchema = parent::get_request_schema();

		// Make every customer field optional
		$requestSchema['customer_fields'] = array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'sanitize_callback'    => 'rest_sanitize_request_arg',
			'default'              => array(),
		);

		// Add status (optional)
		$requestSchema['status'] = array(
			'type'              => 'string',
			'enum'              => array_keys( MPHB()->postTypes()->booking()->statuses()->getStatuses() ),
			'sanitize_callback' => 'rest_sanitize_request_arg',
		);

		return $requestSchema;
	}

	public static function get_response_schema(): array {
		$responseSchema = parent::get_response_schema();

		// Add "component" for documentation
		$responseSchema['component'] = 'checkout-admin';

		// Payments disabled
		unset( $responseSchema['properties']['payment_id'] );
		unset( $responseSchema['properties']['payment_fields'] );

		$responseSchema['properties']['redirect_url']['type'] = 'string'; // No "null"

		return $responseSchema;
	}

	protected static function getSuccessMessage(): string {
		return __( 'Reservation created. Redirecting to the booking page...', 'motopress-hotel-booking' );
	}

	/**
	 * @return array <code>[ booking_id, redirect_url?, success_message ]</code>
	 *
	 * @throws \RuntimeException in case of any error.
	 */
	protected static function process_and_get_data_by_response_schema( \WP_REST_Request $request ) {
		$setStatus = $request->get_param( 'status' ) ?? BookingStatuses::STATUS_CONFIRMED;

		$addStatusFilter = function ( $bookingDetails ) use ( $setStatus ) {
			$bookingDetails['status'] = $setStatus;

			return $bookingDetails;
		};

		// Don't disable admin bookings with "Disable Booking" > "Hide
		// reservation forms and buttons" option. Some clients block checkout
		// on the frontend to limit bookings only through the admin.
		add_filter( 'mphb_block_booking', '__return_false' );

		/**
		 * @param \WP_REST_Request $request
		 */
		do_action( 'mphb_admin_checkout_rest_before_start', $request );

		add_filter( 'mphb_is_current_request_for_admin_ui', '__return_true' );
		add_filter( 'mphb_checkout_booking_details', $addStatusFilter );
		add_filter( 'mphb_checkout_doing_payment', '__return_false' );

		$responseData = parent::process_and_get_data_by_response_schema( $request );

		remove_filter( 'mphb_is_current_request_for_admin_ui', '__return_true' );
		remove_filter( 'mphb_checkout_booking_details', $addStatusFilter );
		remove_filter( 'mphb_checkout_doing_payment', '__return_false' );

		// Redirect to "Edit Booking"
		$responseData['redirect_url'] = get_edit_post_link( $responseData['booking_id'], 'raw' );

		/**
		 * @param array $responseData <code>[ booking_id, redirect_url?, success_message ]</code>
		 * @param \WP_REST_Request $request
		 */
		return apply_filters( 'mphb_admin_checkout_rest_response', $responseData, $request );
	}
}
