<?php

namespace MPHB\Shortcodes\CheckoutShortcode;

use MPHB\Entities\{ Booking, ReservedRoom, RoomType };
use MPHB\Views\Shortcodes\CheckoutView;

defined( 'ABSPATH' ) || exit;


class StepCheckout extends Step {
	/**
	 * @var Booking
	 */
	protected $booking;

	/**
	 * @var ReservedRoom[]
	 */
	protected $reservedRooms;

	/**
	 * @var boolean
	 */
	protected $alreadyBooked = false;

	/**
	 * @var array
	 */
	protected $roomDetails = array();

	/**
	 * @var \MPHB\UsersAndRoles\Customer|null
	 */
	protected $customer = null;

	private $isCorrectBookingData = true;

	/**
	 * Guests of every room from the checkout link (?mphb_action=checkout).
	 *
	 * @var array [ room_type_id => [ [ adults, children ], ... ] ]
	 */
	private $linkOccupancies = array();

	/**
	 * Rate of every room from the checkout link, 0 when the link gives none.
	 *
	 * @var array [ room_type_id => [ rate_id, ... ] ]
	 */
	private $linkRateIds = array();

	public function __construct() {
		add_action( 'mphb_before_register_public_scripts', array( $this, 'addScriptDependencies' ) );

		add_action( 'init', array( $this, 'addInitActions' ) );
		add_action( 'wp_login_failed', array( $this, 'redirectOnFailedLogin' ) );
		add_action( 'wp_logout', array( $this, 'redirectAfterLogout' ) );
	}

	public function addInitActions() {
		add_action( 'mphb_sc_checkout_before_errors', array( $this, 'showErrorsMessage' ) );

		add_action( 'mphb_sc_checkout_before_form', array( '\MPHB\Views\Shortcodes\CheckoutView', 'renderCustomerErrors' ), 10 );
		add_action( 'mphb_sc_checkout_before_form', array( '\MPHB\Views\Shortcodes\CheckoutView', 'renderLoginForm' ), 10 );

		// Template hooks
		add_action( 'mphb_sc_checkout_form', array( '\MPHB\Views\Shortcodes\CheckoutView', 'renderBookingDetails' ), 10, 2 );

		add_action( 'mphb_sc_checkout_room_details', array( '\MPHB\Views\Shortcodes\CheckoutView', 'renderRoomTypeTitle' ), 10, 3 );
		add_action( 'mphb_sc_checkout_room_details', array( '\MPHB\Views\Shortcodes\CheckoutView', 'renderGuestsChooser' ), 20, 4 );
		add_action( 'mphb_sc_checkout_room_details', array( '\MPHB\Views\Shortcodes\CheckoutView', 'renderRateChooser' ), 30, 5 );
		add_action( 'mphb_sc_checkout_room_details', array( '\MPHB\Views\Shortcodes\CheckoutView', 'renderServiceChooser' ), 40, 4 );

		if ( MPHB()->settings()->main()->isCouponsEnabled() ) {
			add_action( 'mphb_sc_checkout_form', array( '\MPHB\Views\Shortcodes\CheckoutView', 'renderCoupon' ), 20 );
		}

		add_action( 'mphb_sc_checkout_form', array( '\MPHB\Views\Shortcodes\CheckoutView', 'renderPriceBreakdown' ), 30 );
		add_action( 'mphb_sc_checkout_form', array( '\MPHB\Views\Shortcodes\CheckoutView', 'renderCheckoutText' ), 35 );
		add_action( 'mphb_sc_checkout_form', array( '\MPHB\Views\Shortcodes\CheckoutView', 'renderCustomerDetails' ), 40, 3 );

		if ( MPHB()->settings()->main()->getConfirmationMode() === 'payment' ) {
			$gateways = MPHB()->gatewayManager()->getListActive();

			// Filter used in mphb-woocommerce to hide WooCommerce gateway, if it is the only one
			if ( 1 === count( $gateways ) && apply_filters( 'mphb_sc_checkout_single_gateway_hide_billing_details', false, reset( $gateways ) ) ) {
				add_action( 'mphb_sc_checkout_form', array( '\MPHB\Views\Shortcodes\CheckoutView', 'renderBillingDetailsHidden' ), 5 );
			} else {
				add_action( 'mphb_sc_checkout_form', array( '\MPHB\Views\Shortcodes\CheckoutView', 'renderBillingDetails' ), 45 );
			}
		}

		add_action( 'mphb_sc_checkout_form', array( '\MPHB\Views\Shortcodes\CheckoutView', 'renderTotalPrice' ), 50 );
		add_action( 'mphb_sc_checkout_form', array( '\MPHB\Views\Shortcodes\CheckoutView', 'renderTermsAndConditions' ), 60 );

		// Booking Details
		add_action( 'mphb_sc_checkout_form_booking_details', array( '\MPHB\Views\Shortcodes\CheckoutView', 'renderCheckInDate' ) );
		add_action( 'mphb_sc_checkout_form_booking_details', array( '\MPHB\Views\Shortcodes\CheckoutView', 'renderCheckOutDate' ) );
		add_action( 'mphb_sc_checkout_form_booking_details', array( '\MPHB\Views\Shortcodes\CheckoutView', 'renderBookingDetailsInner' ), 10, 2 );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueScripts' ) );
	}

	/**
	 * @since 4.11.1
	 *
	 * @access protected
	 */
	public function addScriptDependencies() {
		MPHB()->getPublicScriptManager()->addDependency( 'mphb-jquery-serialize-json' );
	}

	/**
	 * @since 3.7.0 added new filter - "mphb_sc_checkout_step_checkout_booking_object".
	 */
	public function setup() {
		$this->isCorrectBookingData = $this->parseBookingData();

		$this->parseCustomerData();

		if ( $this->isCorrectBookingData ) {
			$bookingAtts = array(
				'check_in_date'  => $this->checkInDate,
				'check_out_date' => $this->checkOutDate,
				'reserved_rooms' => $this->reservedRooms,
			);

			MPHB()->reservationRequest()->setupParameter( 'pricing_strategy', 'base-price' );
			$this->booking = apply_filters( 'mphb_sc_checkout_step_checkout_booking_object', Booking::create( $bookingAtts ) );
			MPHB()->reservationRequest()->resetDefaults( array( 'pricing_strategy' ) );

			$this->stepValid();

			/**
			 * @param Booking $booking
			 */
			do_action( 'mphb_focus_on_booking', $this->booking );
		}
	}

	/**
	 * @since 5.0.0
	 *
	 * @return int[] [ 0 => Adults, 1 => Children ]
	 */
	protected function getDefaultOccupancyForSingleRoom( RoomType $roomType ): array {
		list( $adultsPreset, $childrenPreset ) = mphb_rooms_facade()->getRoomTypeOccupancyPresetsFromSearch( $roomType );

		if ( '' === $adultsPreset || '' === $childrenPreset ) {
			// Default old logic with rates: base price = price for max capacity
			$adultsPreset   = $roomType->getAdultsCapacity();
			$childrenPreset = $roomType->getChildrenCapacity();
		}

		return array( $adultsPreset, $childrenPreset );
	}

	/**
	 * @since 3.7.0 added new filter - "mphb_sc_checkout_step_checkout_selected_rooms".
	 * @since 3.7.0 added new filter - "mphb_sc_checkout_step_checkout_room_to_reserve".
	 * @since 3.7.0 added new filter - "mphb_sc_checkout_step_checkout_rooms_details".
	 * @since 3.7.0 added new filter - "mphb_sc_checkout_step_checkout_reserved_rooms".
	 */
	protected function parseBookingData(): bool {
		$isCorrectCheckInDate  = $this->parseCheckInDate();
		$isCorrectCheckOutDate = $this->parseCheckOutDate();

		if ( ! $isCorrectCheckInDate || ! $isCorrectCheckOutDate ) {
			return false;
		}

		$roomDetails = null;
		$isLinkRooms = false;

		if ( ! empty( $_POST['mphb_rooms_details'] ) && is_array( $_POST['mphb_rooms_details'] ) ) { // phpcs:ignore

			$roomDetails = (array) wp_unslash( $_POST['mphb_rooms_details'] ); // phpcs:ignore

		} elseif ( $this->isCheckoutLinkRequest() ) {

			$roomDetails = $this->parseLinkRooms( (array) wp_unslash( $_GET['mphb_room_details'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$isLinkRooms = true;

		} elseif ( ! empty( mphb_get_cookie( 'mphb_rooms_details' ) ) ) {

			$roomDetails = json_decode( mphb_get_cookie( 'mphb_rooms_details' ), true );
		}

		mphb_set_cookie( 'mphb_rooms_details', wp_json_encode( $roomDetails ) );

		if ( empty( $roomDetails ) || ! is_array( $roomDetails ) ) {
			$this->errors[] = __( 'There are no accommodations selected for reservation.', 'motopress-hotel-booking' );

			return false;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$selectedRooms = apply_filters( 'mphb_sc_checkout_step_checkout_selected_rooms', $roomDetails );

		$this->errors = apply_filters( 'mphb_sc_checkout_step_checkout_pre_validate_selected_rooms', $this->errors, $selectedRooms );

		if ( ! empty( $this->errors ) ) {
			return false;
		}

		$this->reservedRooms = array();
		$this->roomDetails   = array();

		$reservedRooms = array();
		$roomDetails   = array();

		$isIgnoreBookingRules = MPHB()->settings()->main()->isBookingRulesForAdminDisabled();

		foreach ( $selectedRooms as $roomTypeId => $roomsCount ) {

			$roomTypeId = filter_var( $roomTypeId, FILTER_VALIDATE_INT );
			if ( ! $roomTypeId ) {
				$this->errors[] = __( 'Accommodation Type is not valid.', 'motopress-hotel-booking' );
				continue;
			}

			$roomsCount = filter_var( $roomsCount, FILTER_VALIDATE_INT );
			if ( ! $roomsCount ) {
				$this->errors[] = __( 'Accommodation count is not valid.', 'motopress-hotel-booking' );
				continue;
			}

			$roomType = MPHB()->getRoomTypeRepository()->findById( $roomTypeId );
			// A trashed or draft type keeps its published accommodations bookable otherwise.
			if ( ! $roomType || 'publish' !== $roomType->getStatus() ) {
				$this->errors[] = __( 'Accommodation Type is not valid.', 'motopress-hotel-booking' );
				continue;
			}

			$unavailableRooms = mphb_availability_facade()->getUnavailableRoomIds(
				$roomType->getOriginalId(),
				$this->checkInDate,
				$this->checkOutDate,
				$isIgnoreBookingRules
			);

			$roomsExist = MPHB()->getRoomPersistence()->isExistsRooms(
				$this->checkInDate,
				$this->checkOutDate,
				array(
					'count'             => $roomsCount,
					'room_type_id'      => $roomType->getOriginalId(),
					'exclude_rooms'     => $unavailableRooms,
					'skip_buffer_rules' => false,
				)
			);

			if ( ! $roomsExist ) {
				$this->alreadyBooked = true;
				break;
			}

			$allowedRates = mphb_prices_facade()->getActiveRates(
				$roomType->getId(),
				$this->checkInDate,
				$this->checkOutDate,
				false
			);

			if ( empty( $allowedRates ) ) {
				$this->errors[] = __( 'There are no rates for requested dates.', 'motopress-hotel-booking' );
				continue;
			}

			if ( mphb_availability_facade()->isBookingRulesViolated(
				$roomType->getOriginalId(),
				$this->checkInDate,
				$this->checkOutDate,
				$isIgnoreBookingRules
			)
			) {
				/* translators: %s: accommodation type title */
				$this->errors[] = sprintf( __( 'Selected dates do not meet booking rules for type %s', 'motopress-hotel-booking' ), $roomType->getTitle() );
				continue;
			}

			$linkOccupancies = $this->linkOccupancies[ $roomTypeId ] ?? array();

			if ( ! $this->isLinkOccupancyFit( $linkOccupancies, $roomType ) ) {
				/* translators: %s: accommodation type title */
				$this->errors[] = sprintf( __( 'Accommodation type %s cannot host the requested number of guests.', 'motopress-hotel-booking' ), $roomType->getTitle() );
				continue;
			}

			$defaultRate = reset( $allowedRates );

			if ( 1 === $roomsCount
				&& 1 === count( $selectedRooms )
				&& MPHB()->settings()->main()->isUseOccupancyPresetsOnCheckout()
			) {
				list( $adultsToBook, $childrenToBook ) = $this->getDefaultOccupancyForSingleRoom( $roomType );
			} else {
				$adultsToBook   = $roomType->getAdultsCapacity();
				$childrenToBook = $roomType->getChildrenCapacity();
			}

			$reservedRoomAtts = array(
				'rate_id'  => $defaultRate->getOriginalId(),
				'adults'   => $adultsToBook,
				'children' => $childrenToBook,
			);

			$reservedRoomAtts = apply_filters( 'mphb_sc_checkout_step_checkout_room_to_reserve', $reservedRoomAtts, $roomTypeId );

			for ( $i = 1; $i <= $roomsCount; $i++ ) {
				$roomAtts     = $reservedRoomAtts;
				$roomAdults   = $adultsToBook;
				$roomChildren = $childrenToBook;

				if ( isset( $linkOccupancies[ $i - 1 ] ) ) {
					list( $roomAdults, $roomChildren ) = $linkOccupancies[ $i - 1 ];

					$roomAtts['adults']   = $roomAdults;
					$roomAtts['children'] = $roomChildren;
				}

				$roomRateId = $defaultRate->getOriginalId();
				$linkRateId = $this->linkRateIds[ $roomTypeId ][ $i - 1 ] ?? 0;

				// A rate not allowed for the stay leaves the default one.
				if ( $this->isRateAllowed( $linkRateId, $allowedRates ) ) {
					$roomRateId          = $linkRateId;
					$roomAtts['rate_id'] = $linkRateId;
				}

				$reservedRooms[] = ReservedRoom::create( $roomAtts );
				$roomDetails[]   = array(
					'room_type_id'  => $roomTypeId,
					'rate_id'       => $roomRateId,
					'allowed_rates' => $allowedRates,
					'adults'        => $roomAdults,
					'children'      => $roomChildren,
				);
			}
		}

		// A link asks for all its rooms at once: dropping the invalid ones would book less than asked.
		if ( $isLinkRooms && ! empty( $this->errors ) ) {
			return false;
		}

		$this->roomDetails   = apply_filters( 'mphb_sc_checkout_step_checkout_rooms_details', $roomDetails, $selectedRooms );
		$this->reservedRooms = apply_filters( 'mphb_sc_checkout_step_checkout_reserved_rooms', $reservedRooms, $roomDetails, $selectedRooms );

		if ( empty( $this->reservedRooms ) ) {
			return false;
		}

		return true;
	}

	/**
	 * The checkout link (?mphb_action=checkout) gives one entry per room:
	 * mphb_room_details[i][room_type_id|adults|children|rate_id].
	 *
	 * @return int[] [ room_type_id => rooms count ]
	 */
	protected function parseLinkRooms( array $linkRooms ): array {
		$roomsCount = array();

		$mainSettings = MPHB()->settings()->main();

		foreach ( $linkRooms as $linkRoom ) {
			if ( ! is_array( $linkRoom ) ) {
				$linkRoom = array();
			}

			// An invalid ID becomes 0 and fails the regular validation.
			$roomTypeId = absint( $linkRoom['room_type_id'] ?? 0 );

			$roomsCount[ $roomTypeId ] = ( $roomsCount[ $roomTypeId ] ?? 0 ) + 1;

			// An invalid ID becomes 0 and matches no rate.
			$this->linkRateIds[ $roomTypeId ][] = absint( $linkRoom['rate_id'] ?? 0 );

			// Without guest management the room keeps its default occupancy.
			if ( ! $mainSettings->isGuestsAllowed() ) {
				continue;
			}

			// Not absint(): it turns -1 into 1, and a broken value must fall to the minimum.
			$adults   = max( mphb_get_min_adults(), (int) ( $linkRoom['adults'] ?? 0 ) );
			$children = max( mphb_get_min_children(), (int) ( $linkRoom['children'] ?? 0 ) );

			// With children disabled the site asks only for "Guests", so children count as adults.
			if ( ! $mainSettings->isChildrenAllowed() ) {
				$adults  += $children;
				$children = mphb_get_min_children();
			}

			$this->linkOccupancies[ $roomTypeId ][] = array( $adults, $children );
		}

		if ( ! empty( $this->linkOccupancies ) ) {
			add_filter( 'mphb_sc_checkout_preset_adults', array( $this, 'presetLinkAdults' ), 10, 3 );
			add_filter( 'mphb_sc_checkout_preset_children', array( $this, 'presetLinkChildren' ), 10, 3 );
		}

		add_filter( 'mphb_sc_checkout_preset_rate_id', array( $this, 'presetLinkRateId' ), 10, 2 );

		return $roomsCount;
	}

	/**
	 * @param int                   $rateId
	 * @param \MPHB\Entities\Rate[] $allowedRates
	 */
	protected function isRateAllowed( int $rateId, array $allowedRates ): bool {
		foreach ( $allowedRates as $allowedRate ) {
			if ( (int) $allowedRate->getOriginalId() === $rateId ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param int[][] $linkOccupancies [ [ adults, children ], ... ]
	 */
	protected function isLinkOccupancyFit( array $linkOccupancies, RoomType $roomType ): bool {
		foreach ( $linkOccupancies as list( $adults, $children ) ) {
			if ( $adults > $roomType->getAdultsCapacity()
				|| $children > $roomType->getChildrenCapacity()
				|| $adults + $children > $roomType->calcTotalCapacity()
			) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Selects the guests from the checkout link in the room's adults dropdown.
	 *
	 * @param int|string   $preset
	 * @param RoomType     $roomType
	 * @param ReservedRoom $reservedRoom
	 * @return int
	 */
	public function presetLinkAdults( $preset, $roomType, $reservedRoom ) {
		return $reservedRoom->getAdults();
	}

	/**
	 * Selects the guests from the checkout link in the room's children dropdown.
	 *
	 * @param int|string   $preset
	 * @param RoomType     $roomType
	 * @param ReservedRoom $reservedRoom
	 * @return int
	 */
	public function presetLinkChildren( $preset, $roomType, $reservedRoom ) {
		return $reservedRoom->getChildren();
	}

	/**
	 * Selects the room's rate in the rate chooser: the one from the checkout link
	 * when it is allowed for the stay, the default one otherwise.
	 *
	 * @param int          $preset
	 * @param ReservedRoom $reservedRoom
	 * @return int
	 */
	public function presetLinkRateId( $preset, $reservedRoom ) {
		return $reservedRoom->getRateId();
	}

	protected function parseCustomerData() {
		$userId = get_current_user_id();

		if ( ! $userId ) {
			// User is not logged in
			return;
		}

		$customer = MPHB()->customers()->findBy( 'user_id', $userId );

		if ( null === $customer ) {
			// TODO case if user is not a customer
			return;
		}

		$this->customer = $customer;
	}

	/**
	 * @since 3.7.0 added new action - "mphb_sc_checkout_before_errors".
	 */
	public function render() {
		// Goes first: a fully booked stay also fails the booking data, but has no error text.
		if ( $this->alreadyBooked ) {
			$this->showAlreadyBookedMessage();

			return;
		}

		if ( ! $this->isCorrectBookingData ) {
			do_action( 'mphb_sc_checkout_before_errors' );

			return;
		}

		do_action( 'mphb_sc_checkout_before_form' );

		CheckoutView::renderCheckoutForm( $this->booking, $this->roomDetails, $this->customer );

		do_action( 'mphb_sc_checkout_after_form' );
	}

	/**
	 * @since 3.7.2 added new action - "mphb_enqueue_checkout_scripts".
	 */
	public function enqueueScripts() {
		if ( ! $this->isValidStep ) {
			return;
		}

		$checkoutData = array(
			'min_adults'   => MPHB()->settings()->main()->getMinAdults(),
			'min_children' => MPHB()->settings()->main()->getMinChildren(),
		);

		if ( MPHB()->settings()->main()->getConfirmationMode() === 'payment' ) {
			$checkoutData['deposit_amount'] = $this->booking->calcDepositAmount();
		}

		$checkoutData['total'] = $this->booking->calcPrice();

		MPHB()->getPublicScriptManager()->addCheckoutData( $checkoutData );

		foreach ( MPHB()->gatewayManager()->getListActive() as $gateway ) {
			MPHB()->getPublicScriptManager()->addGatewayData( $gateway->getId(), $gateway->getCheckoutData( $this->booking ) );
		}

		MPHB()->getPublicScriptManager()->enqueue();

		do_action( 'mphb_enqueue_checkout_scripts' );
	}

	/**
	 * @since 4.2.1
	 */
	public function redirectOnFailedLogin() {
		$referrer = wp_get_referer();

		if ( false === $referrer ) {
			return;
		}

		$checkoutPageId = MPHB()->settings()->pages()->getCheckoutPageId();
		$page           = get_post( $checkoutPageId );
		$slug           = $page->post_name;

		if ( strstr( $referrer, $slug ) ) {
			$redirectTo = add_query_arg( 'login_failed', 'error', $referrer );
			wp_safe_redirect( $redirectTo );
			exit;
		}
	}

	/**
	 * @since 4.2.1
	 */
	public function redirectAfterLogout() {
		$referrer = wp_get_referer();

		if ( false === $referrer ) {
			return;
		}

		$checkoutPageId = MPHB()->settings()->pages()->getCheckoutPageId();
		$page           = get_post( $checkoutPageId );
		$slug           = $page->post_name;

		if ( strstr( $referrer, $slug ) ) {
			wp_safe_redirect( $referrer );
			exit;
		}
	}
}
