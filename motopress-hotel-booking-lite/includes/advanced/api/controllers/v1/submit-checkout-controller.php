<?php

declare(strict_types=1);

namespace MPHB\Advanced\Api\Controllers\V1;

use MPHB\Advanced\Api\Controllers\AbstractRestCommandController;
use MPHB\Entities\{ Booking, Customer, Payment, Rate, ReservedRoom, ReservedService };
use MPHB\PostTypes\BookingCPT\Statuses as BookingStatuses;
use MPHB\Shortcodes\CheckoutShortcode;
use MPHB\Utils\ParseUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Route: /checkout (POST)
 */
class SubmitCheckoutController extends AbstractRestCommandController {
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
		return '/checkout';
	}

	public static function get_supported_methods(): string {
		return \WP_REST_Server::CREATABLE;
	}

	/**
	 * @return \WP_Error|bool
	 */
	public static function is_request_allowed( \WP_REST_Request $request ) {
		return true; // Public request
	}

	protected static function get_request_schema(): array {
		$minAdults   = MPHB()->settings()->main()->getMinAdults();
		$minChildren = MPHB()->settings()->main()->getMinChildren();

		return array(
			'check_in_date' => array(
				'type'              => 'string',
				'pattern'           => '^\d{4}-\d{2}-\d{2}$',
				'required'          => true,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'check_out_date' => array(
				'type'              => 'string',
				'pattern'           => '^\d{4}-\d{2}-\d{2}$',
				'required'          => true,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'checkout_id' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'coupon_code' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'customer_fields' => array(
				'type'                 => 'object',
				'properties'           => array(
					'mphb_email' => array(
						'type'              => 'string',
						'format'            => 'email',
						'required'          => true,
						'sanitize_callback' => 'rest_sanitize_request_arg',
					),
				),
				'additionalProperties' => true,
				'required'             => true,
				'sanitize_callback'    => 'rest_sanitize_request_arg',
			),
			'note' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'payment_details' => array(
				'type'              => 'object',
				'properties'        => array(
					'currency' => array(
						'type'              => 'string',
						'pattern'           => '^[A-Z]{3}$',
						'sanitize_callback' => 'rest_sanitize_request_arg',
					),
					'gateway_id' => array(
						'type'              => 'string',
						// It is better to check "gateway_id" after the
						// "mphb_focus_on_booking" action
//						'enum'              => array_merge(
//							array( 'manual' ),
//							array_keys( MPHB()->gatewayManager()->getListActive() )
//						),
						'required'          => true,
						'sanitize_callback' => 'rest_sanitize_request_arg',
					),
					'payment_fields' => array(
						'type'                 => 'object',
						'additionalProperties' => true,
						'sanitize_callback'    => 'rest_sanitize_request_arg',
					),
				),
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'room_details' => array(
				'type'              => 'array',
				'items'             => array(
					'type'              => 'object',
					'properties'        => array(
						'adults' => array(
							'type'              => 'integer',
							'minimum'           => $minAdults,
							'required'          => MPHB()->settings()->main()->isAdultsAllowed(),
							'sanitize_callback' => 'rest_sanitize_request_arg',
						),
						'children' => array(
							'type'              => 'integer',
							'minimum'           => $minChildren,
							'required'          => MPHB()->settings()->main()->isChildrenAllowed(),
							'sanitize_callback' => 'rest_sanitize_request_arg',
						),
						'guest_name' => array(
							'type'              => 'string',
							'sanitize_callback' => 'rest_sanitize_request_arg',
						),
						'rate_id' => array(
							'type'              => 'integer',
							'minimum'           => 1,
							'required'          => true,
							'sanitize_callback' => 'rest_sanitize_request_arg',
						),
						'room_id' => array(
							'type'              => 'integer',
							'minimum'           => 0, // 0 is the same as no ID at all
							'sanitize_callback' => 'rest_sanitize_request_arg',
						),
						'room_type_id' => array(
							'type'              => 'integer',
							'minimum'           => 1,
							'required'          => true,
							'sanitize_callback' => 'rest_sanitize_request_arg',
						),
						'services' => array(
							'type'              => 'array',
							'items'             => array(
								'type'              => 'object',
								'properties'        => array(
									'id' => array(
										'type'              => 'integer',
										'minimum'           => 1,
										'required'          => true,
										'sanitize_callback' => 'rest_sanitize_request_arg',
									),
									'adults' => array(
										'type'              => 'integer',
										'minimum'           => 1,
										'required'          => true,
										'sanitize_callback' => 'rest_sanitize_request_arg',
									),
									'quantity' => array(
										'type'              => 'integer',
										'minimum'           => 1,
										'sanitize_callback' => 'rest_sanitize_request_arg',
									),
								),
								'sanitize_callback' => 'rest_sanitize_request_arg',
							),
							'sanitize_callback' => 'rest_sanitize_request_arg',
						),
					),
					'sanitize_callback' => 'rest_sanitize_request_arg',
				),
				'required'          => true,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
		);
	}

	public static function get_response_schema(): array {
		return array(
			'$schema'     => 'http://json-schema.org/draft-04/schema#',
			'title'       => 'checkout',
			'type'        => 'object',
			'properties'  => array(
				'booking_id'      => array(
					'type' => 'integer',
				),
				'payment_id'      => array(
					'type' => array( 'integer', 'null' ),
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

	protected static function getSuccessMessage(): string {
		return apply_filters( 'mphb_checkout_success_message', __( 'Reservation submitted', 'motopress-hotel-booking' ) );
	}

	/**
	 * @return array <code>[ booking_id, payment_details?, payment_id?,
	 *     redirect_url?, success_message ]</code>.
	 *
	 * @throws \RuntimeException in case of any error.
	 */
	protected static function process_and_get_data_by_response_schema( \WP_REST_Request $request ) {
		add_filter( 'mphb_checkout_step', fn() => CheckoutShortcode::STEP_CHECKOUT );

		if ( MPHB()->settings()->main()->isBookingDisabled() ) {
			throw new \RuntimeException( esc_html__( 'Booking is blocked due to maintenance reason. Please try again later.', 'motopress-hotel-booking' ) );
		}

		$requestArgs = $request->get_params();

		$ignoreBookingRules = MPHB()->settings()->main()->isBookingRulesForAdminDisabled();

		$checkInDate  = ParseUtils::parseCheckInDate( $requestArgs['check_in_date'] );
		$checkOutDate = ParseUtils::parseCheckOutDate(
			$requestArgs['check_out_date'],
			$checkInDate,
			$dateFormat = null,
			$ignoreBookingRules
		);

		// Create customer
		$customerFields = $requestArgs['customer_fields'];
		$customerFiles  = $request->get_file_params();

		if ( ! empty( $customerFiles ) ) {
			$customerFields = array_merge( $customerFields, $customerFiles );
		}

		$errors = array();
		$customerData = ParseUtils::parseCustomer( $customerFields, $errors );

		if ( ! empty( $errors ) ) {
			throw new \RuntimeException( implode( ' ', $errors ) );
		}

		$customer = new Customer( $customerData );

		// Create booking
		$bookingDetails = array(
			'check_in_date'  => $checkInDate,
			'check_out_date' => $checkOutDate,
			'checkout_id'    => $requestArgs['checkout_id'],
			'customer'       => $customer,
			'note'           => $requestArgs['note'],
			'reserved_rooms' => self::parseRooms( $requestArgs, $checkInDate, $checkOutDate ),
			'status'         => MPHB()->postTypes()->booking()->statuses()->getDefaultNewBookingStatus(),
		);

		/**
		 * @param array $bookingDetails
		 */
		$bookingDetails = apply_filters( 'mphb_checkout_booking_details', $bookingDetails );

		$booking = Booking::create( $bookingDetails );

		// Apply coupon
		if ( MPHB()->settings()->main()->isCouponsEnabled() && $requestArgs['coupon_code'] !== '' ) {
			$coupon = MPHB()->getCouponRepository()->findByCode( $requestArgs['coupon_code'] );

			if ( $coupon !== null ) {
				$booking->applyCoupon( $coupon );
			}
		}

		/**
		 * @param Booking $booking
		 */
		do_action( 'mphb_focus_on_booking', $booking ); // For Accommodation-Based Payments

		// Check payment fields to generate errors before saving any data
		$isDoingPayment = MPHB()->settings()->main()->isConfirmationUponPayment();

		/**
		 * @param bool $isDoingPayment
		 */
		$isDoingPayment = apply_filters( 'mphb_checkout_doing_payment', $isDoingPayment );

		if ( $isDoingPayment ) {
			$paymentDetails = $requestArgs['payment_details'] + array(
				'currency'       => MPHB()->settings()->currency()->getCurrencyCode(),
				'gateway_id'     => 'manual',
				'payment_fields' => array(),
			);

			$gatewayId = ParseUtils::parseGatewayId( $paymentDetails['gateway_id'], $booking );
			$gateway   = MPHB()->gatewayManager()->getGateway( $gatewayId );

			$errors = array();
			$gateway->parsePaymentFields( $paymentDetails['payment_fields'], $errors );

			if ( ! empty( $errors ) ) {
				throw new \RuntimeException( implode( ' ', $errors ) );
			}
		}

		// Generate price breakdown before save: save() will trigger some emails,
		// which require price breakdown in their text. See MPI-4870 for more details
		$booking->getPriceBreakdown();

		// Save customer
		$customerId = MPHB()->customers()->createCustomerOnBooking( $booking );

		if ( ! is_wp_error( $customerId ) ) {
			$booking->getCustomer()->setCustomerId( $customerId );
		}

		// Save booking
		$isSaved = MPHB()->getBookingRepository()->save( $booking );

		if ( ! $isSaved ) {
			// TODO: Change to normal message
			throw new \RuntimeException( esc_html__( 'Unable to create booking. Please try again.', 'motopress-hotel-booking' ) );
		}

		do_action( 'mphb_create_booking_by_user', $booking );

		$responseData = array(
			'booking_id'      => $booking->getId(),
			'redirect_url'    => MPHB()->getShortcodes()->getCheckout()->getStepUrl( CheckoutShortcode::STEP_BOOKING ),
			'success_message' => static::getSuccessMessage(),
		);

		// Handle payment
		$payment = null;

		if ( $isDoingPayment ) {
			// Create payment
			$paymentAmount = $booking->calcDepositAmount();

			$paymentDetails = array(
				'amount'      => $paymentAmount,
				'bookingId'   => $booking->getId(),
				'currency'    => $paymentDetails['currency'],
				'gatewayId'   => $gateway->getId(),
				'gatewayMode' => $gateway->getMode(),
				'paymentFee'  => $gateway->calculatePaymentFee( $paymentAmount ),
			);

			/**
			 * @param array $paymentDetails
			 */
			$paymentDetails = apply_filters( 'mphb_checkout_payment_details', $paymentDetails );

			$payment = Payment::create( $paymentDetails );
			$isSaved = MPHB()->getPaymentRepository()->save( $payment );

			if ( $isSaved ) {
				$gateway->storePaymentFields( $payment );

				// Re-get payment. Some gateways may update metadata without entity update.
				$payment = mphb_bookings_facade()->findPaymentById( $payment->getId(), $ignoreCache = true );

			} else {
				self::cancelBooking( $booking );

				throw new \RuntimeException( esc_html__( 'Server error. Failed to create payment for this booking.', 'motopress-hotel-booking' ) );
			}

			$responseData['payment_id'] = $payment->getId();

			$booking->setExpectPayment( $payment->getId() );

			// Process payment
			add_filter( 'wp_redirect', array( self::class, 'disableGatewayRedirectsFilter' ), PHP_INT_MAX );

			try {
				$paymentFields = $gateway->processPayment( $booking, $payment );

				// Re-get payment to update all fields that can be changed in processPayment()
				$payment = mphb_bookings_facade()->findPaymentById( $payment->getId(), $ignoreCache = true );

				if ( ! empty( $paymentFields ) && is_array( $paymentFields ) ) {
					$responseData['payment_fields'] = $paymentFields;
				}

				if ( ! $payment->isFailed() ) {
					$responseData['redirect_url'] = MPHB()->settings()->pages()->getReservationReceivedPageUrl( $payment );
				} else {
					$responseData['redirect_url'] = MPHB()->settings()->pages()->getPaymentFailedPageUrl( $payment );
				}

			} catch ( \Exception $e ) {
				$errorMessage = sprintf(
					// Translators: %s: Error message text.
					__( 'Unable to process your payment. %s', 'motopress-hotel-booking' ),
					$e->getMessage()
				);

				MPHB()->paymentManager()->failPayment( $payment, $errorMessage );
				self::cancelBooking( $booking );

				throw new \RuntimeException( esc_html( $errorMessage ) );
			}

			remove_filter( 'wp_redirect', array( self::class, 'disableGatewayRedirectsFilter' ), PHP_INT_MAX );

			if ( self::$gatewayRedirect !== '' ) {
				$responseData['redirect_url'] = self::$gatewayRedirect;
			}

			// Trigger "booking_pending_payment_capture"
			if ( ! $payment->isFinished() && $payment->hasPendingAuthedFunds() ) {
				do_action( 'mphb_booking_pending_payment_capture', $booking, $payment );
			}
		} // if $isDoingPayment

		return $responseData;
	}

	private static function cancelBooking( Booking $booking ): void {
		if ( ! in_array( $booking->getStatus(), MPHB()->postTypes()->booking()->statuses()->getFailedStatuses() ) ) {
			add_filter( 'mphb_email_customer_cancelled_booking_prevent', '__return_true' );

			$booking->setStatus( BookingStatuses::STATUS_CANCELLED );
			MPHB()->getBookingRepository()->save( $booking );

			remove_filter( 'mphb_email_customer_cancelled_booking_prevent', '__return_true' );
		}
	}

	/**
	 * @return ReservedRoom[]
	 *
	 * @throws \RuntimeException in case of any error.
	 */
	private static function parseRooms( array $requestData, \DateTime $checkInDate, \DateTime $checkOutDate ): array {
		/**
		 * @var array $requestedRooms Array of <code>[rate_id, room_type_id]</code>
		 *     with optional <code>[adults, children, guest_name, room_id,
		 *     services]</code>. Number of adults/children is optional only if
		 *     disabled in settings.
		 */
		$requestedRooms = $requestData['room_details'];

		// CHECK DATA
		if ( empty( $requestedRooms ) ) {
			throw new \RuntimeException( esc_html__( 'There are no accommodations selected for reservation.', 'motopress-hotel-booking' ) );
		}

		$ignoreBookingRules = MPHB()->settings()->main()->isBookingRulesForAdminDisabled();

		foreach ( $requestedRooms as $roomDetails ) {
			list(
				'rate_id'      => $rateId,
				'room_type_id' => $roomTypeId,
			) = $roomDetails;

			// Check entities
			$roomType = mphb_get_room_type( $roomTypeId );
			$rate     = mphb_prices_facade()->getRateById( $rateId );

			if ( $roomType === null || $roomType->getStatus() !== 'publish' ) {
				throw new \RuntimeException( esc_html__( 'Accommodation Type is not valid.', 'motopress-hotel-booking' ) );
			} elseif ( $rate === null ) {
				throw new \RuntimeException( esc_html__( 'Rate is not valid.', 'motopress-hotel-booking' ) );
			}

			// Check rate
			$allowedRates = mphb_prices_facade()->getActiveRates(
				$roomType->getOriginalId(),
				$checkInDate,
				$checkOutDate
			);

			$allowedRateIds = array_map(
				fn( Rate $rate ) => $rate->getOriginalId(),
				$allowedRates
			);

			if ( ! in_array( $rateId, $allowedRateIds ) ) {
				throw new \RuntimeException( esc_html__( 'Rate is not valid.', 'motopress-hotel-booking' ) );
			}

			// Check adults and children
			$adults   = $roomDetails['adults']   ?? null;
			$children = $roomDetails['children'] ?? null;

			if ( ( $adults === null && MPHB()->settings()->main()->isAdultsAllowed() )
				|| ( $adults !== null && $adults > $roomType->getAdultsCapacity() )
			) {
				if ( MPHB()->settings()->main()->isChildrenAllowed() ) {
					throw new \RuntimeException( esc_html__( 'Adults number is not valid.', 'motopress-hotel-booking' ) );
				} else {
					throw new \RuntimeException( esc_html__( 'The number of guests is not valid.', 'motopress-hotel-booking' ) );
				}
			}

			if ( $children === null && MPHB()->settings()->main()->isChildrenAllowed() ) {
				throw new \RuntimeException( esc_html__( 'Children number is not valid.', 'motopress-hotel-booking' ) );
			} elseif ( $children !== null ) {
				if ( $children > $roomType->getChildrenCapacity() ) {
					throw new \RuntimeException( esc_html__( 'Children number is not valid.', 'motopress-hotel-booking' ) );
				} elseif ( $roomType->hasLimitedTotalCapacity() && $adults + $children > $roomType->getTotalCapacity() ) {
					throw new \RuntimeException( esc_html__( 'The total number of guests is not valid.', 'motopress-hotel-booking' ) );
				}
			}

			// Check booking rules
			$bookingRulesViolated = mphb_availability_facade()->isBookingRulesViolated(
				$roomType->getOriginalId(),
				$checkInDate,
				$checkOutDate,
				$ignoreBookingRules
			);

			if ( $bookingRulesViolated ) {
				throw new \RuntimeException( sprintf( esc_html__( 'Selected dates do not meet booking rules for type %s', 'motopress-hotel-booking' ), $roomType->getTitle() ) );
			}
		}

		// FIND ROOMS
		// [Room type ID => Count]
		$roomsCountPerType = array_count_values( wp_list_pluck( $requestedRooms, 'room_type_id' ) );

		// [Room type ID => int[]]
		$availableRooms = array();

		foreach ( $roomsCountPerType as $roomTypeId => $roomsCount ) {
			$roomType = mphb_get_room_type( $roomTypeId );

			$lockedRooms = mphb_availability_facade()->getUnavailableRoomIds(
				$roomType->getOriginalId(),
				$checkInDate,
				$checkOutDate,
				$ignoreBookingRules
			);

			/**
			 * @param array $searchAtts
			 */
			$searchAtts = apply_filters(
				'mphb_search_available_rooms',
				array(
					'availability'      => 'free',
					'from_date'         => $checkInDate,
					'to_date'           => $checkOutDate,
					'room_type_id'      => $roomType->getOriginalId(),
					'exclude_rooms'     => $lockedRooms,
					'skip_buffer_rules' => false,
				)
			);

			$foundRooms = MPHB()->getRoomPersistence()->searchRooms( $searchAtts );

			if ( count( $foundRooms ) >= $roomsCount ) {
				$availableRooms[ $roomTypeId ] = $foundRooms;

			} else {
				$message = apply_filters( 'mphb_checkout_already_booked_message', __( 'Accommodation is already booked.', 'motopress-hotel-booking' ) );

				throw new \RuntimeException( esc_html( $message ) );
			}
		}

		// Merge $requestedRooms with $availableRooms
		$reserveRooms = array();

		foreach ( $requestedRooms as $args ) {
			$roomTypeId = $args['room_type_id'];
			$roomId = 0;

			// Try to use the selected room ID
			if ( isset( $args['room_id'] ) && $args['room_id'] !== 0 ) {
				if ( in_array( $args['room_id'], $availableRooms[ $roomTypeId ] ) ) {
					$roomId = $args['room_id'];

					mphb_array_remove( $availableRooms[ $roomTypeId ], $roomId );

				} else {
					$message = apply_filters( 'mphb_checkout_already_booked_message', __( 'Accommodation is already booked.', 'motopress-hotel-booking' ) );

					throw new \RuntimeException( esc_html( $message ) );
				}
			}

			// Otherwise just get the first one available
			if ( ! $roomId ) {
				$roomId = array_shift( $availableRooms[ $roomTypeId ] );
			}

			$reserveRooms[] = array(
				'adults'       => $args['adults'] ?? mphb_get_min_adults(),
				'children'     => $args['children'] ?? mphb_get_min_children(),
				'guest_name'   => $args['guest_name'] ?? '',
				'rate_id'      => $args['rate_id'],
				'room_id'      => $roomId,
				'room_type_id' => $roomTypeId,
				'services'     => $args['services'] ?? array(),
			);
		}

		/**
		 * @param array $roomDetails Array of <code>[adults, children,
		 *     guest_name, rate_id, room_id, services]</code>.
		 * @param array $bookingDetails
		 */
		$reserveRooms = apply_filters( 'mphb_checkout_room_details', $reserveRooms, $requestData );

		// RESERVE ROOMS
		$reservedRooms = array();

		foreach ( $reserveRooms as $roomDetails ) {
			if ( ! empty( $roomDetails['services'] ) ) {
				$reservedServices = array_map(
					fn( array $serviceDetails) => ReservedService::create( $serviceDetails ),
					$roomDetails['services']
				);

				$roomDetails['reserved_services'] = array_filter( $reservedServices );
			}

			$reservedRooms[] = ReservedRoom::create( $roomDetails );
		}

		return $reservedRooms;
	}
}
