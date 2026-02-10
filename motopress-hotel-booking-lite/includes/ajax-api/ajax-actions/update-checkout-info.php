<?php

namespace MPHB\AjaxApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class UpdateCheckoutInfo extends AbstractAjaxApiAction {

	private const REQUEST_DATA_BOOKING = 'booking';

	public static function getAjaxActionNameWithouPrefix() {
		return 'update_checkout_info';
	}

	/**
	 * @return array [ request_key (string) => request_value (mixed) ]
	 * @throws \Exception when validation of request parameters failed
	 */
	protected static function getValidatedRequestData() {

		$requestData = parent::getValidatedRequestData();

		$isSetRequiredFields = isset( $_REQUEST['formValues'] ) &&
			is_array( $_REQUEST['formValues'] ) &&
			! empty( $_REQUEST['formValues']['mphb_room_details'] ) &&
			is_array( $_REQUEST['formValues']['mphb_room_details'] ) &&
			! empty( $_REQUEST['formValues']['mphb_check_in_date'] ) &&
			! empty( $_REQUEST['formValues']['mphb_check_out_date'] );

		if ( $isSetRequiredFields ) {

			// phpcs:ignore
			foreach ( $_REQUEST['formValues']['mphb_room_details'] as &$roomDetails ) {

				if (
					! is_array( $roomDetails ) ||
					empty( $roomDetails['room_type_id'] ) ||
					empty( $roomDetails['rate_id'] )
				) {
					$isSetRequiredFields = false;
					break;
				}

				if ( ! isset( $roomDetails['children'] ) ) {
					$roomDetails['children'] = 0;
				}
			}
			unset( $roomDetails );
		}

		if ( ! $isSetRequiredFields ) {

			throw new \Exception( __( 'An error has occurred. Please try again later.', 'motopress-hotel-booking' ) );
		}

		// phpcs:ignore
		$atts = $_REQUEST['formValues'];

		$checkInDate  = static::getDateFromRequest(	'mphb_check_in_date', true,	null, $atts );
		$checkOutDate = static::getDateFromRequest(	'mphb_check_out_date', true, null, $atts );

		$reservedRooms = array();

		foreach ( $atts['mphb_room_details'] as $roomDetails ) {

			$roomTypeId = \MPHB\Utils\ValidateUtils::validateInt( $roomDetails['room_type_id'], 0 );
			$roomType   = $roomTypeId ? MPHB()->getRoomTypeRepository()->findById( $roomTypeId ) : null;

			if ( ! $roomType ) {

				throw new \Exception( __( 'Accommodation Type is not valid.', 'motopress-hotel-booking' ) );
			}

			$roomRateId = \MPHB\Utils\ValidateUtils::validateInt( $roomDetails['rate_id'], 0 );
			$roomRate   = $roomRateId ? mphb_prices_facade()->getRateById( $roomRateId ) : null;

			if ( ! $roomRate ) {

				throw new \Exception( __( 'Rate is not valid.', 'motopress-hotel-booking' ) );
			}

			$adults = static::getIntegerFromRequest( 'adults', false, 0, $roomDetails );

			if ( 1 > $adults ) {

				throw new \Exception(
					MPHB()->settings()->main()->isChildrenAllowed() ?
					__( 'The number of adults is not valid.', 'motopress-hotel-booking' ) :
					__( 'The number of guests is not valid.', 'motopress-hotel-booking' )
					
				);
			}

			$children = static::getIntegerFromRequest( 'children', false, -1, $roomDetails );

			if ( 0 > $children ) {

				throw new \Exception( __( 'Children number is not valid.', 'motopress-hotel-booking' ) );
			}

			if ( $roomType->hasLimitedTotalCapacity() && $adults + $children > $roomType->getTotalCapacity() ) {

				throw new \Exception( __( 'The total number of guests is not valid.', 'motopress-hotel-booking' ) );
			}

			$allowedServices = $roomType->getServices();

			$services = array();

			if ( ! empty( $roomDetails['services'] ) && is_array( $roomDetails['services'] ) ) {
				foreach ( $roomDetails['services'] as $serviceDetails ) {

					if ( empty( $serviceDetails['id'] ) || ! in_array( $serviceDetails['id'], $allowedServices ) ) {
						continue;
					}

					$serviceAdults = \MPHB\Utils\ValidateUtils::validateInt( $serviceDetails['adults'] );
					if ( $serviceAdults === false || $serviceAdults < 1 ) {
						continue;
					}

					$quantity = isset( $serviceDetails['quantity'] ) ? \MPHB\Utils\ValidateUtils::validateInt( $serviceDetails['quantity'] ) : 1;
					if ( isset( $serviceDetails['quantity'] ) && $quantity < 1 ) {
						continue;
					}

					$services[] = \MPHB\Entities\ReservedService::create(
						array(
							'id'       => (int) $serviceDetails['id'],
							'adults'   => $serviceAdults,
							'quantity' => $quantity,
						)
					);
				}
			}
			$services = array_filter( $services );

			$reservedRoomAtts = array(
				'room_type_id'      => $roomTypeId,
				'rate_id'           => $roomRateId,
				'adults'            => $adults,
				'children'          => $children,
				'reserved_services' => $services,
			);

			$reservedRooms[] = \MPHB\Entities\ReservedRoom::create( $reservedRoomAtts );
		}

		$bookingAtts = array(
			'check_in_date'  => $checkInDate,
			'check_out_date' => $checkOutDate,
			'reserved_rooms' => $reservedRooms,
		);

		$booking = \MPHB\Entities\Booking::create( $bookingAtts );

		if (
			MPHB()->settings()->main()->isCouponsEnabled() &&
			! empty( $_REQUEST['formValues']['mphb_applied_coupon_code'] )
		) {
			// phpcs:ignore
			$coupon = MPHB()->getCouponRepository()->findByCode( $_REQUEST['formValues']['mphb_applied_coupon_code'] );

			if ( $coupon ) {

				$booking->applyCoupon( $coupon );
			}
		}

		$requestData[ self::REQUEST_DATA_BOOKING ] = $booking;

		return $requestData;
	}

	/**
	 * @throws Exception when action processing failed
	 */
	protected static function doAction( array $requestData ) {

		$booking = $requestData[ self::REQUEST_DATA_BOOKING ];

		/**
		 * @param \MPHB\Entities\Booking $booking
		 */
		do_action( 'mphb_focus_on_booking', $booking );

		$total = $booking->calcPrice();

		$priceHtml = mphb_format_price( $total );

		$responseData = array(
			'newAmount'      => $total,
			'priceHtml'      => $priceHtml,
			'priceBreakdown' => \MPHB\Views\BookingView::generatePriceBreakdown( $booking ),
		);

		if ( 'payment' === MPHB()->settings()->main()->getConfirmationMode() ) {

			$responseData['depositAmount'] = $booking->calcDepositAmount();
			$responseData['depositPrice']  = mphb_format_price( $responseData['depositAmount'] );

			$responseData['gateways'] = array_map(
				function( $gateway ) use ( $booking ) {
					return $gateway->getCheckoutData( $booking );
				},
				MPHB()->gatewayManager()->getListActive()
			);

			$responseData['isFree'] = $total == 0;
		}

		wp_send_json_success( $responseData, 200 );
	}
}
