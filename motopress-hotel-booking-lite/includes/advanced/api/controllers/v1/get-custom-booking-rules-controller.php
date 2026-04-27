<?php

declare(strict_types=1);

namespace MPHB\Advanced\Api\Controllers\V1;

use MPHB\Advanced\Api\Controllers\AbstractRestCommandController;
use MPHB\Advanced\Api\RestApiSchemaHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Route: /booking_rules/custom (GET)
 */
class GetCustomBookingRulesController extends AbstractRestCommandController {
	public static function get_route(): string {
		return '/booking_rules/custom';
	}

	public static function get_supported_methods(): string {
		return \WP_REST_Server::READABLE;
	}

	/**
	 * @return \WP_Error|bool
	 */
	public static function is_request_allowed( \WP_REST_Request $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @return \WP_Error|null Returns null if all parameters are valid.
	 */
	public static function validate_request( \WP_REST_Request $request ): ?\WP_Error {
		$error = parent::validate_request( $request );

		if ( $error !== null ) {
			return $error;
		}

		$roomType = mphb_rooms_facade()->getRoomTypeById( (int) $request->get_param( 'room_type_id' ) );

		if ( $roomType === null ) {
			return new \WP_Error( '', 'Accommodation type not found.' );
		}

		$startDate = $request->get_param( 'start_date' );
		$endDate   = $request->get_param( 'end_date' );

		if ( $startDate > $endDate ) {
			return new \WP_Error(
				'',
				sprintf(
					'Parameter start_date ("%s") can not be after end_date ("%s").',
					$startDate,
					$endDate
				)
			);
		}

		return null;
	}

	protected static function get_request_schema(): array {
		return array(
			'room_type_id' => array(
				'description'       => 'Unique identifier of the accommodation type.',
				'type'              => 'integer',
				'minimum'           => 1,
				'required'          => true,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'start_date' => array(
				'description'       => 'Date in WordPress timezone (YYYY-MM-DD) (inclusive)',
				'type'              => 'string',
				'format'            => 'date',
				'required'          => true,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'end_date' => array(
				'description'       => 'Date in WordPress timezone (YYYY-MM-DD) (inclusive)',
				'type'              => 'string',
				'format'            => 'date',
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
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'component'            => 'custom_booking_rules',
			'title'                => 'settings',
			'type'                 => 'object',
			'patternProperties'    => array(
				'^\d{4}-\d{2}-\d{2}$' => array(
					'type'       => 'object',
					'properties' => RestApiSchemaHelper::getCustomBookingRuleProperties(),
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * @return array <code>[ Date string ("Y-m-d") => [ buffer_days, allow_check_in, ... ] ]</code>
	 */
	protected static function process_and_get_data_by_response_schema( \WP_REST_Request $request ) {
		list(
			'room_type_id' => $roomTypeId,
			'start_date'   => $startDateStr,
			'end_date'     => $endDateStr,
		) = $request->get_params();

		$bookingRules = MPHB()->getCustomBookingRulesRepository()->getRulesForPeriod(
			$roomTypeId,
			$startDateStr,
			$endDateStr
		);

		return $bookingRules;
	}
}
