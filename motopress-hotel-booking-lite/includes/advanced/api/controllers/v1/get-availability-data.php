<?php

namespace MPHB\Advanced\Api\Controllers\V1;

use MPHB\Advanced\Api\Controllers\AbstractRestCommandController,
	MPHB\Utils\DateUtils;

defined( 'ABSPATH' ) || exit;


class GetAvailabilityData extends AbstractRestCommandController {

	private const REQUEST_PARAMETER_START_DATE                = 'start_date';
	private const REQUEST_PARAMETER_END_DATE                  = 'end_date';
	private const REQUEST_PARAMETER_ROOM_TYPE_IDS             = 'room_type_ids';
	private const REQUEST_PARAMETER_IS_ADD_PRICES             = 'is_add_prices';
	private const REQUEST_PARAMETER_IS_TRUNCATE_PRICES        = 'is_truncate_prices';
	private const REQUEST_PARAMETER_IS_ADD_PRICES_CURRENCY    = 'is_add_prices_currency';
	private const REQUEST_PARAMETER_IS_REQUEST_FOR_ADMIN_AREA = 'is_request_for_admin_area';

	private const MAX_REQUEST_DATES_INTERVAL_IN_DAYS = 370;


	public static function get_route(): string {
		return '/availability';
	}

	public static function get_supported_methods(): string {
		return \WP_REST_Server::READABLE;
	}

	/**
	 * Check user permissions.
	 * @return \WP_Error|bool
	 */
	public static function is_request_allowed( \WP_REST_Request $request ) {
		return parent::is_request_allowed( $request );
	}

	/**
	 * https://developer.wordpress.org/rest-api/extending-the-rest-api/schema/
	 */
	protected static function get_request_schema(): array {

		return array(
			static::REQUEST_PARAMETER_START_DATE => array(
				'description'       => 'Date in WordPress timezone (YYYY-MM-DD) (inclusive)',
				'type'              => 'string',
				'format'            => 'date',
				'required'          => true,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			static::REQUEST_PARAMETER_END_DATE => array(
				'description'       => 'Date in WordPress timezone (YYYY-MM-DD) (inclusive)',
				'type'              => 'string',
				'format'            => 'date',
				'required'          => true,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			static::REQUEST_PARAMETER_ROOM_TYPE_IDS => array(
				'description'       => 'If it is empty, the API will return common availability data for all accommodation types.',
				'type'              => 'array',
				'items'             => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			static::REQUEST_PARAMETER_IS_ADD_PRICES => array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			static::REQUEST_PARAMETER_IS_TRUNCATE_PRICES => array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			static::REQUEST_PARAMETER_IS_ADD_PRICES_CURRENCY => array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			static::REQUEST_PARAMETER_IS_REQUEST_FOR_ADMIN_AREA => array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
		);
	}

	/**
	 * https://developer.wordpress.org/rest-api/extending-the-rest-api/schema/
	 */
	public static function get_response_schema(): array {
		return array(
			'$schema' => 'http://json-schema.org/draft-04/schema#',
			'title'   => 'Availability data',
			'type'    => 'object',
			'patternProperties' => array(
				'^[0-9]+$' => array(
					'type' => 'object',
					'description' => 'Room type availability data keyed by room type ID (numeric string)',
					'patternProperties' => array(
						'^\d{4}-\d{2}-\d{2}$' => array(
							'type' => 'object',
							'description' => 'Availability data keyed by date YYYY-MM-DD (string)',
							'properties' => array(
								'roomTypeStatus' => array(
									'type' => 'string',
									'enum' => array( 'available', 'unavailable', 'booked' ),
								),
								'availableRoomsCount' => array(
									'type' => 'integer',
									'minimum' => 0,
								),
								'isCheckInDate' => array( 'type' => 'boolean' ),
								'isCheckOutDate' => array( 'type' => 'boolean' ),
								'isStayInNotAllowed' => array( 'type' => 'boolean' ),
								'isCheckInNotAllowed' => array( 'type' => 'boolean' ),
								'isCheckOutNotAllowed' => array( 'type' => 'boolean' ),
								'isEarlierThanMinAdvanceDate' => array( 'type' => 'boolean' ),
								'isLaterThanMaxAdvanceDate' => array( 'type' => 'boolean' ),
								'minStayNights' => array( 'type' => 'boolean' ),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * @return \WP_Error|null - returns null if all parameters are valid.
	 */
	public static function validate_request( \WP_REST_Request $request ): ?\WP_Error {

		$result = parent::validate_request( $request );

		if ( null !== $result ) {
			return $result;
		}

		$startDate = \DateTime::createFromFormat(
			'Y-m-d',
			$request->get_param( static::REQUEST_PARAMETER_START_DATE ),
			DateUtils::getSiteTimeZone()
		);

		$endDate = \DateTime::createFromFormat(
			'Y-m-d',
			$request->get_param( static::REQUEST_PARAMETER_END_DATE ),
			DateUtils::getSiteTimeZone()
		);

		if ( false === $startDate || false === $endDate	) {
			return new \WP_Error(
				'',
				'Wrong ' . static::REQUEST_PARAMETER_START_DATE . ' or ' . static::REQUEST_PARAMETER_END_DATE
			);
		}

		if ( $startDate > $endDate ) {

			return new \WP_Error(
				'',
				'Parameter ' . static::REQUEST_PARAMETER_START_DATE . ' (' .
				$startDate->format( 'Y-m-d' ) .
				') can not be after ' .
				static::REQUEST_PARAMETER_END_DATE . ' (' .
				$endDate->format( 'Y-m-d' ) .
				')'
			);
		}

		return null;
	}

	/**
	 * @return mixed|\WP_Error data or error if needed to send some additional error data
	 * @throws Exception when processing failed
	 */
	protected static function process_and_get_data_by_response_schema( \WP_REST_Request $request ) {

		$requestData = $request->get_params();

		$roomTypes = array( null );

		if ( ! empty( $requestData[ static::REQUEST_PARAMETER_ROOM_TYPE_IDS ] ) ) {

			$result_ids = array();
			foreach ( $requestData[ static::REQUEST_PARAMETER_ROOM_TYPE_IDS ] as $id ) {

				if ( 0 < $id ) {
					$result_ids = $id;
				}
			}

			if ( ! empty( $result_ids ) ) {

				$roomTypes = mphb_rooms_facade()->findRoomTypesByIdsOrSlugs( $requestData[ static::REQUEST_PARAMETER_ROOM_TYPE_IDS ] );
			}
		}

		$result = array();

		$startDate = \DateTime::createFromFormat(
			'Y-m-d',
			$requestData[ static::REQUEST_PARAMETER_START_DATE ],
			DateUtils::getSiteTimeZone()
		);

		$endDate = \DateTime::createFromFormat(
			'Y-m-d',
			$requestData[ static::REQUEST_PARAMETER_END_DATE ],
			DateUtils::getSiteTimeZone()
		);

		$maxEndDate = clone $startDate;
		$maxEndDate->modify('+' . static::MAX_REQUEST_DATES_INTERVAL_IN_DAYS . ' days');

		if ( $endDate > $maxEndDate ) {
			$endDate = $maxEndDate;
		}

		foreach ( $roomTypes as $roomType ) {

			$result[ $roomType ? $roomType->getId() : 0 ] = static::get_availability_data_for_room_type(
				$startDate,
				$endDate,
				$roomType ? $roomType->getOriginalRoomType() : null,
				$requestData
			);
		}

		return $result;
	}


	/**
	 * @param \MPHB\Entities\RoomType|null $roomType - when null calculate common availability data for all room types
	 */
	private static function get_availability_data_for_room_type(
		\DateTime $startDate,
		\DateTime $endDate,
		?\MPHB\Entities\RoomType $roomType,
		array $requestData
	): array {

		$result = array();

		$roomTypeOriginalId = null !== $roomType ? $roomType->getOriginalId() : 0;

		$processingDate = clone $startDate;


		$isRestAPIRequestForAdminUI = $requestData[ static::REQUEST_PARAMETER_IS_REQUEST_FOR_ADMIN_AREA ] &&
				current_user_can( 'edit_' . MPHB()->postTypes()->booking()->getCapabilityType()[ 1 ] );
		add_filter(
			'mphb_is_current_request_for_admin_ui',
			function ( bool $isCurrentRequestForAdminUI ) use ( $isRestAPIRequestForAdminUI ): bool {
				return $isRestAPIRequestForAdminUI;
			}
		);

		do {
			$dateData = mphb_availability_facade()->getRoomTypeAvailabilityData(
				$roomTypeOriginalId,
				$processingDate,
				MPHB()->settings()->main()->isBookingRulesForAdminDisabled()
			)->toArray();

			$result[ $processingDate->format( 'Y-m-d' ) ] = $dateData;

			$processingDate->modify( '+1 day' );

		} while ( $processingDate <= $endDate );


		if ( null !== $roomType && $requestData[ static::REQUEST_PARAMETER_IS_ADD_PRICES ] ) {

			$processingDate = clone $startDate;

			$priceFormatAtts = array(
				'is_truncate_price' => $requestData[ static::REQUEST_PARAMETER_IS_TRUNCATE_PRICES ],
				'decimals'          => 0,
				'currency_symbol'   => $requestData[ static::REQUEST_PARAMETER_IS_ADD_PRICES_CURRENCY ] ? MPHB()->
					settings()->currency()->getCurrencySymbol() : '',
			);

			do {

				$processingFormattedDate = $processingDate->format( 'Y-m-d' );
				$dateData                = $result[ $processingFormattedDate ];

				$nextProcessingDate = clone $processingDate;
				$nextProcessingDate->modify( '+1 day' );
				$nextProcessingFormattedDate = $nextProcessingDate->format( 'Y-m-d' );

				if ( ( \MPHB\Core\RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_AVAILABLE == $dateData['roomTypeStatus'] ||
					\MPHB\Core\RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_EARLIER_MIN_ADVANCE == $dateData['roomTypeStatus'] ||
					\MPHB\Core\RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_LATER_MAX_ADVANCE == $dateData['roomTypeStatus'] ) &&
					( ! $dateData['isCheckInNotAllowed'] ||
					( ! empty( $result[ $nextProcessingFormattedDate ] ) &&
						\MPHB\Core\RoomTypeAvailabilityStatus::ROOM_TYPE_AVAILABILITY_STATUS_NOT_AVAILABLE != $result[ $nextProcessingFormattedDate ]['roomTypeStatus'] ) )
				) {

					$dateData['price'] = mphb_prices_facade()->formatPrice(
						mphb_prices_facade()->getRoomTypeMinBasePriceForDate(
							$roomTypeOriginalId,
							$processingDate
						),
						$priceFormatAtts
					);

				} else {
					$dateData['price'] = '<span class="mphb-price">&nbsp;</span>';
				}

				$result[ $processingFormattedDate ] = $dateData;

				$processingDate = $nextProcessingDate;

			} while ( $processingDate <= $endDate );
		}

		return $result;
	}
}
