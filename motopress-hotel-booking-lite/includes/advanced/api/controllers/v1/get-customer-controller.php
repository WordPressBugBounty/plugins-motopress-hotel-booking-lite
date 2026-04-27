<?php

declare(strict_types=1);

namespace MPHB\Advanced\Api\Controllers\V1;

use MPHB\Advanced\Api\Controllers\AbstractRestCommandController;
use MPHB\Advanced\Api\RestApiSchemaHelper;
use MPHB\UsersAndRoles\Customers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Route: /customers/{id} (GET)
 */
class GetCustomerController extends AbstractRestCommandController {
	public static function get_route(): string {
		return '/customers/(?P<id>[\\d]+)';
	}

	public static function get_supported_methods(): string {
		return \WP_REST_Server::READABLE;
	}

	/**
	 * @return \WP_Error|bool
	 */
	public static function is_request_allowed( \WP_REST_Request $request ) {
		return current_user_can( 'mphb_view_customers' );
	}

	protected static function get_request_schema(): array {
		return array(
			'id' => array(
				'description'       => 'Unique identifier of the customer.',
				'type'              => 'integer',
				'minimum'           => 1,
				'required'          => true,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
		);
	}

	/**
	 * https://developer.wordpress.org/rest-api/extending-the-rest-api/schema/
	 */
	public static function get_response_schema(): array {
		return array(
			'$schema'     => 'http://json-schema.org/draft-04/schema#',
			'description' => 'Retrieve a customer',
			'title'       => 'customers',
			'type'        => 'object',
			'properties'  => RestApiSchemaHelper::getCustomerProperties(),
		);
	}

	/**
	 * @return array <code>[ id, user_id, email, ... ]</code>
	 *
	 * @throws \Exception if the customer not found.
	 */
	protected static function process_and_get_data_by_response_schema( \WP_REST_Request $request ) {
		$customer = Customers::findById( $request->get_param( 'id' ) );

		if ( is_null( $customer ) ) {
			throw new \Exception( esc_html__( 'Customer not found.', 'motopress-hotel-booking' ) );
		}

		return array(
			'id'         => $customer->getId(),
			'user_id'    => $customer->getUserId(),
			'email'      => $customer->getEmail(),
			'first_name' => $customer->getFirstName(),
			'last_name'  => $customer->getLastName(),
			'phone'      => $customer->getPhone(),
			'country'    => $customer->getCountry(),
			'state'      => $customer->getState(),
			'city'       => $customer->getCity(),
			'address1'   => $customer->getAddress1(),
			'zip'        => $customer->getZip(),
			'bookings'   => $customer->getBookings(),
		);
	}
}
