<?php

namespace MPHB\Utils;

use MPHB\Entities\{ Booking, RoomType };
use DateTime;
use RuntimeException as Error;

/**
 * @since 3.7.2
 */
class ParseUtils {

	/**
	 * @since 5.0.0
	 *
	 * @param mixed $value
	 * @param float|null $min
	 * @param float|null $max
	 * @return float
	 */
	public static function parseFloat( $value, $min = null, $max = null ) {
		$validValue = ValidateUtils::validateFloat( $value, $min, $max );

		if ( $validValue !== false ) {
			return $validValue;
		} elseif ( ! is_null( $min ) ) {
			return $min;
		} else {
			return 0.0;
		}
	}

	/**
	 * @since 5.0.0
	 *
	 * @param mixed $value
	 * @param int|null $min
	 * @param int|null $max
	 * @return int
	 */
	public static function parseInt( $value, $min = null, $max = null ) {
		$validValue = ValidateUtils::validateInt( $value, $min, $max );

		if ( $validValue !== false ) {
			return $validValue;
		} elseif ( ! is_null( $min ) ) {
			return $min;
		} else {
			return 0;
		}
	}

	/**
	 * @since 5.0.0
	 *
	 * @param mixed $value
	 * @return int
	 */
	public static function parseId( $value ) {
		return static::parseInt( $value, 0 );
	}

	/**
	 * @param mixed $values
	 * @return int[] Does not allow 0, unlike ValidateUtils::parseIds().
	 */
	public static function parseIds( array $values ): array {
		$ids = array();

		foreach ( $values as $value ) {
			$id = ValidateUtils::validateInt( $value, 0 );

			if ( $id !== false && $id !== 0 ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * @since 3.8
	 * @since 6.0.0 Added <code>$dateFormat</code> and <code>$allowPastDate</code>
	 *     parameters.
	 *
	 * @param string|null $dateFormat Custom date format. "Y-m-d" by default.
	 *
	 * @throws \RuntimeException If check-in date is not valid or earlier than
	 *     today and <code>$allowPastDate</code> is false.
	 */
	public static function parseCheckInDate(
		string $checkInDateStr,
		?string $dateFormat = null,
		bool $allowPastDate = false
	): DateTime {
		if ( $dateFormat === null ) {
			$dateFormat = MPHB()->settings()->dateTime()->getDateTransferFormat(); // "Y-m-d"
		}

		$checkInDate = DateUtils::createCheckInDate( $dateFormat, $checkInDateStr );

		if ( $checkInDate === null ) {
			throw new \RuntimeException( esc_html__( 'Check-in date is not valid.', 'motopress-hotel-booking' ) );
		}

		if ( ! $allowPastDate ) {
			$today = new DateTime( 'today', DateUtils::getSiteTimeZone() );

			if ( DateUtils::calcNights( $today, $checkInDate ) < 0 ) {
				throw new \RuntimeException( esc_html__( 'Check-in date cannot be earlier than today.', 'motopress-hotel-booking' ) );
			}
		}

		return $checkInDate;
	}

	/**
	 * @since 3.8
	 * @since 6.0.0 Added <code>$checkInDate</code> and <code>$dateFormat</code>
	 *     parameters.
	 *
	 * @param string|null $dateFormat Custom date format. "Y-m-d" by default.
	 *
	 * @throws \RuntimeException
	 */
	public static function parseCheckOutDate(
		string $checkOutDateStr,
		?DateTime $checkInDate = null,
		?string $dateFormat = null
	): DateTime {
		if ( $dateFormat === null ) {
			$dateFormat = MPHB()->settings()->dateTime()->getDateTransferFormat(); // "Y-m-d"
		}

		$checkOutDate = DateUtils::createCheckOutDate( $dateFormat, $checkOutDateStr );

		if ( ! $checkOutDate ) {
			throw new \RuntimeException( esc_html__( 'Check-out date is not valid.', 'motopress-hotel-booking' ) );
		}

		if ( $checkInDate !== null ) {
			if ( DateUtils::calcNights( $checkInDate, $checkOutDate ) < 0 ) {
				throw new \RuntimeException( esc_html__( 'Check-out date cannot be earlier than check-in date.', 'motopress-hotel-booking' ) );
			}

			// Better not check booking rules here (as it was before). The rules
			// for $roomTypeId = 0 may be more restrictive than the rules for
			// specific room types. It's better to check isBookingRulesViolated()
			// later, knowing the specific room types.
			// mphb_availability_facade()->isBookingRulesViolated( $roomTypeId = 0, ... )
		}

		return $checkOutDate;
	}

	/**
	 * @since 3.8
	 *
	 * @throws \RuntimeException If adults number is not valid.
	 */
	public static function parseAdults( string $adultsStr ): int {
		$minAdults = mphb_get_min_adults();
		$adults    = ValidateUtils::validateInt( $adultsStr, $minAdults );

		if ( $adults !== false ) {
			return $adults;
		} else {
			if ( MPHB()->settings()->main()->isChildrenAllowed() ) {
				throw new \RuntimeException( esc_html__( 'Adults number is not valid.', 'motopress-hotel-booking' ) );
			} else {
				throw new \RuntimeException( esc_html__( 'The number of guests is not valid.', 'motopress-hotel-booking' ) );
			}
		}
	}

	/**
	 * @since 3.8
	 *
	 * @throws \RuntimeException If children number is not valid.
	 */
	public static function parseChildren( string $childrenStr ): int {
		$minChildren = mphb_get_min_children();
		$children    = ValidateUtils::validateInt( $childrenStr, $minChildren );

		if ( $children !== false ) {
			return $children;
		} else {
			throw new \RuntimeException( esc_html__( 'Children number is not valid.', 'motopress-hotel-booking' ) );
		}
	}

	/**
	 * @param mixed     $rawData Raw [mphb_room_details => ...] data.
	 * @param array     $args
	 *     @param DateTime  $args['check_in_date'] Required if "edit_booking" is not set.
	 *     @param DateTime  $args['check_out_date'] Required if "edit_booking" is not set.
	 *     @param bool      $args['check_booking_rules'] Optional. TRUE by default. FALSE if
	 *              "edit_booking" is set.
	 *     @param int|int[] $args['exclude_bookings'] Optional.
	 *     @param Booking   $args['edit_booking'] Optional.
	 * @return array Array of [room_id, room_type_id, rate_id, adults, children,
	 *     guest_name, allowed_rates, services], where all IDs and objects -
	 *     original values (not translated).
	 *
	 * @throws Error
	 *
	 * @since 3.8
	 */
	public static function parseRooms( $rawData, $args ) {
		if ( ! is_array( $rawData ) ) {
			throw new Error( __( 'Selected accommodations are not valid.', 'motopress-hotel-booking' ) );
		}

		$defaultArgs = array(
			'check_in_date'       => null,
			'check_out_date'      => null,
			'check_booking_rules' => true,
			'exclude_bookings'    => array(),
		);

		if ( ! empty( $args['edit_booking'] ) ) {
			$editBooking = $args['edit_booking'];

			$defaultArgs['check_in_date']       = $editBooking->getCheckInDate();
			$defaultArgs['check_out_date']      = $editBooking->getCheckOutDate();
			$defaultArgs['check_booking_rules'] = false;
			$defaultArgs['exclude_bookings']    = $editBooking->getId();
		}

		$args = array_merge( $defaultArgs, $args );

		// Check check-in/check-out dates
		if ( ! $args['check_in_date'] ) {
			throw new Error( __( 'Check-in date is not set.', 'motopress-hotel-booking' ) );
		} elseif ( ! $args['check_out_date'] ) {
			throw new Error( __( 'Check-out date is not set.', 'motopress-hotel-booking' ) );
		}

		// Parse rooms
		$rooms       = array();
		$roomsByType = array(); // [Room type ID => [Room IDs]]

		foreach ( $rawData as $roomData ) {
			$room = static::parseRoom( $roomData, $args );

			$rooms[]                                = $room;
			$roomsByType[ $room['room_type_id'] ][] = $room['room_id'];
		}

		if ( empty( $rooms ) ) {
			throw new Error( __( 'There are no accommodations selected for reservation.', 'motopress-hotel-booking' ) );
		}

		// Check available rooms
		foreach ( $roomsByType as $roomTypeId => $roomIds ) {
			if ( ! MPHB()->getRoomPersistence()->isRoomsFree(
				$args['check_in_date'],
				$args['check_out_date'],
				$roomIds,
				array(
					'room_type_id'     => $roomTypeId,
					'exclude_bookings' => $args['exclude_bookings'],
				)
			) ) {
				throw new Error( __( 'Accommodations are not available.', 'motopress-hotel-booking' ) );
			}
		}

		return $rooms;
	}

	/**
	 * @param mixed    $roomData
	 * @param array    $args
	 *     @param DateTime $args['check_in_date']
	 *     @param DateTime $args['check_out_date']
	 *     @param bool     $args['check_booking_rules']
	 * @return array
	 *
	 * @throws Error
	 *
	 * @since 3.8
	 */
	protected static function parseRoom( $roomData, $args ) {
		if ( ! is_array( $roomData ) ) {
			throw new Error( __( 'Selected accommodations are not valid.', 'motopress-hotel-booking' ) );
		}

		$minAdults   = mphb_get_min_adults();
		$minChildren = mphb_get_min_children();

		$roomId     = isset( $roomData['room_id'] ) ? mphb_posint( $roomData['room_id'] ) : 0;
		$roomTypeId = isset( $roomData['room_type_id'] ) ? mphb_posint( $roomData['room_type_id'] ) : 0;
		$adults     = isset( $roomData['adults'] ) ? ValidateUtils::validateInt( $roomData['adults'], $minAdults ) : 0;
		$children   = isset( $roomData['children'] ) ? ValidateUtils::validateInt( $roomData['children'], $minChildren ) : 0;
		$guestName  = isset( $roomData['guest_name'] ) ? sanitize_text_field( $roomData['guest_name'] ) : '';
		$rateId     = isset( $roomData['rate_id'] ) ? mphb_posint( $roomData['rate_id'] ) : 0;

		$roomType = mphb_get_room_type( $roomTypeId );

		if ( is_null( $roomType ) || $roomType->getStatus() != 'publish' ) {
			throw new Error( __( 'Accommodation Type is not valid.', 'motopress-hotel-booking' ) );
		}

		if ( $roomId == 0 ) {
			throw new Error( __( 'Selected accommodations are not valid.', 'motopress-hotel-booking' ) );
		}

		$allowedRates = mphb_prices_facade()->getActiveRates(
			$roomTypeId,
			$args['check_in_date'],
			$args['check_out_date']
		);

		$rateIds = array_map(
			function ( $rate ) {
				return $rate->getId();
			},
			$allowedRates
		);

		if ( $rateId == 0 || ! in_array( $rateId, $rateIds ) ) {
			throw new Error( __( 'Rate is not valid.', 'motopress-hotel-booking' ) );
		}

		if ( $adults === false || $adults > $roomType->getAdultsCapacity() ) {
			throw new Error( __( 'Adults number is not valid.', 'motopress-hotel-booking' ) );
		}

		if ( $children === false || $children > $roomType->getChildrenCapacity() ) {
			throw new Error( __( 'Children number is not valid.', 'motopress-hotel-booking' ) );
		}

		if ( $roomType->hasLimitedTotalCapacity() && $adults + $children > $roomType->getTotalCapacity() ) {
			throw new Error( __( 'The total number of guests is not valid.', 'motopress-hotel-booking' ) );
		}

		if ( $args['check_booking_rules'] &&
			mphb_availability_facade()->isBookingRulesViolated(
				$roomType->getOriginalId(),
				$args['check_in_date'],
				$args['check_out_date'],
				MPHB()->settings()->main()->isBookingRulesForAdminDisabled()
			)
		) {
			throw new Error( sprintf( __( 'Selected dates do not meet booking rules for type %s', 'motopress-hotel-booking' ), $roomType->getTitle() ) );
		}

		if ( isset( $roomData['services'] ) ) {
			$services = static::parseServices( $roomData['services'], array( 'room_type' => $roomType ) );
		} else {
			$services = array();
		}

		return array(
			'room_id'       => $roomId,
			'room_type_id'  => $roomTypeId,
			'rate_id'       => $rateId,
			'adults'        => $adults,
			'children'      => $children,
			'guest_name'    => $guestName,
			'allowed_rates' => $rateIds,
			'services'      => $services,
		);
	}

	/**
	 * @param mixed    $servicesData
	 * @param array    $args
	 *     @param RoomType $args['room_type']
	 * @return array
	 *
	 * @since 3.8
	 */
	protected static function parseServices( $servicesData, $args ) {
		if ( ! is_array( $servicesData ) ) {
			return array();
		}

		$services = array();

		foreach ( $servicesData as $serviceData ) {
			if ( ! isset( $serviceData['id'], $serviceData['adults'] ) ) {
				continue;
			}

			$serviceId = mphb_posint( $serviceData['id'] );
			$adults    = ValidateUtils::validateInt( $serviceData['adults'], mphb_get_min_adults() );
			$quantity  = isset( $serviceData['quantity'] ) ? ValidateUtils::validateInt( $serviceData['quantity'], 1 ) : 1;

			if ( $serviceId > 0 && $adults !== false && $quantity !== false && in_array( $serviceId, $args['room_type']->getServices() ) ) {
				$services[ $serviceId ] = array(
					// Data enough/valid for ReservedService::create()
					'id'       => $serviceId,
					'adults'   => $adults,
					'quantity' => $quantity,
				);
			}
		}

		return $services;
	}

	/**
	 * @since 3.7.2
	 *
	 * @param array $rawData
	 * @param array $errors Optional. An array to add the errors to.
	 * @return array|false Customer data or FALSE.
	 */
	public static function parseCustomer( $rawData, ?array &$errors = null ) {
		if ( is_null( $errors ) ) {
			$errors = array();
		}

		$isAdmin = is_admin() || apply_filters( 'mphb_is_current_request_for_admin_ui', false );

		if ( ! $isAdmin ) {
			$customerFields = mphb_get_customer_fields();
		} else {
			$customerFields = mphb_get_admin_checkout_customer_fields();
		}

		// [Field name => '']
		$customerData = array_combine(
			array_keys( $customerFields ),
			array_fill( 0, count( $customerFields ), '' )
		);

		// Parse inputs
		foreach ( $customerFields as $fieldName => $field ) {
			$fullName = MPHB()->addPrefix( $fieldName, '_' ); // 'mphb_first_name'

			if ( isset( $rawData[ $fullName ] ) ) {
				$value = $rawData[ $fullName ];

				if ( $field['type'] == 'email' ) {
					$value = sanitize_email( $value );
				} elseif ( $field['type'] == 'textarea' ) {
					$value = sanitize_textarea_field( $value );
				} else {
					$validValue = apply_filters( 'mphb_sanitize_customer_field', null, $value, $field['type'], $fieldName );

					if ( is_null( $validValue ) ) {
						$value = sanitize_text_field( $value );
					} else {
						$value = $validValue;
					}
				}

				$customerData[ $fieldName ] = $value;
			}
		}

		/**
		 * @since 4.3.0 $rawData
		 * @since 4.3.0 $customerFields
		 */
		$customerData = apply_filters( 'mphb_parse_customer_data', $customerData, $rawData, $customerFields );

		/**
		 * @since 4.3.0
		 *
		 * @param array $errors
		 */
		$errors = apply_filters( 'mphb_parse_customer_errors', $errors );

		// Check for errors
		foreach ( $customerFields as $fieldName => $field ) {
			$value = $customerData[ $fieldName ];

			if ( empty( $value ) && $field['required'] ) {
				$errors[] = $field['labels']['required_error'];
			}
		}

		// Return the results
		if ( empty( $errors ) ) {
			return $customerData;
		} else {
			return false;
		}
	}

	/**
	 * @since 6.0.0
	 *
	 * @throws \RuntimeException in case of any error.
	 */
	public static function parseGatewayId( string $gatewayId, ?Booking $booking = null ): string {
		$gatewayId = sanitize_text_field( wp_unslash( $gatewayId ) );
		$activeGateways = array_keys( MPHB()->gatewayManager()->getListActive() );

		if ( empty( $activeGateways ) || ( $booking !== null && $booking->calcDepositAmount() == 0 ) ) {
			return 'manual';
		} elseif ( ! in_array( $gatewayId, $activeGateways ) ) {
			throw new \RuntimeException( esc_html__( 'Payment method is not valid.', 'motopress-hotel-booking' ) );
		}

		return $gatewayId;
	}
}
