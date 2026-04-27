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
 * Route: /customers (GET)
 */
class GetCustomersController extends AbstractRestCommandController {
	public static function get_route(): string {
		return '/customers';
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
			'order' => array(
				'type'              => 'string',
				'enum'              => array( 'ASC', 'DESC' ),
				'default'           => 'ASC',
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'orderby' => array(
				'type'              => 'string',
				'enum'              => array( 'address1', 'bookings', 'city', 'country', 'customer_id', 'date_registered', 'email', 'first_name', 'full_name', 'id', 'last_active', 'last_name', 'phone', 'state', 'user_id', 'zip' ),
				'default'           => 'customer_id',
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'page' => array(
				'type'              => 'integer',
				'minimum'           => 1,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'per_page' => array(
				'type'              => 'integer',
				'minimum'           => 1,
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
			'description' => 'List customers',
			'title'       => 'customers',
			'type'        => 'array',
			'items'       => array(
				'type'        => 'object',
				'properties'  => RestApiSchemaHelper::getCustomerProperties(),
			),
		);
	}

	/**
	 * @return array Customer[]
	 */
	protected static function process_and_get_data_by_response_schema( \WP_REST_Request $request ) {
		$atts = $request->get_params();

		if ( isset( $atts['orderby'] ) && $atts['orderby'] === 'id' ) {
			$atts['orderby'] = 'customer_id';
		}

		if ( isset( $atts['page'] ) ) {
			$atts['paged'] = $atts['page'];

			unset( $atts['page'] );
		}

		$customers = Customers::findCustomers( $atts );

		return array_map(
			fn( $customer ) => array(
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
			),
			$customers
		);
	}
}
