<?php
/**
 *
 * @package MPHB\Advanced\Api
 * @since 4.1.0
 */

namespace MPHB\Advanced\Api;

use MPHB\Advanced\Api\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Class responsible for loading the REST API and all REST API namespaces.
 */
class Server {

	use SingletonTrait;

	const NAMESPACE_V1 = __NAMESPACE__ . '\Controllers\V1\\';

	/**
	 * All controllers are sorted in the order in which they should appear in
	 * the REST API documentation.
	 *
	 * @var array List of all included API controllers for version 1
	 */
	const CONTROLLERS_V1 = array(
		// Bookings
		'bookings'                           => 'BookingsController',
		'booking_availability'               => 'BookingAvailabilityController',

		// Payments
		'payments'                           => 'PaymentsController',

		// Accommodations
		'accommodations'                     => 'AccommodationsController',

		// Accommodation types
		'accommodation_types'                => 'AccommodationTypesController',
		'availability'                       => 'GetAvailabilityData',

		// Accommodation types / Categories
		'accommodation_type_categories'      => 'AccommodationTypeCategoriesController',

		// Accommodation types / Tags
		'accommodation_type_tags'            => 'AccommodationTypeTagsController',

		// Accommodation types / Amenities
		'accommodation_type_amenities'       => 'AccommodationTypeAmenitiesController',

		// Accommodation types / Services
		'accommodation_type_services'        => 'AccommodationTypeServicesController',

		// Accommodation types / Images
		'accommodation_type_images'          => 'AccommodationTypeImagesController',

		// Accommodation types / Attributes
		'accommodation_type_attributes'      => 'AccommodationTypeAttributesController',
		'accommodation_type_attribute_terms' => 'AccommodationTypeAttributeTermsController',

		// Coupons
		'coupons'                            => 'CouponsController',

		// Rates
		'rates'                              => 'RatesController',
		'get_rate_custom_prices'             => 'GetRateCustomPricesController',
		'set_rate_custom_prices'             => 'SetRateCustomPricesController',
		'delete_rate_custom_prices'          => 'DeleteRateCustomPricesController',

		// Seasons
		'seasons'                            => 'SeasonsController',

		// Settings
		'booking_rules'                      => 'BookingRulesController',
		'get_custom_booking_rules'           => 'GetCustomBookingRulesController',
		'set_custom_booking_rules'           => 'SetCustomBookingRulesController',
		'countries'                          => 'GetCountries',
		'get_settings'                       => 'GetSettingsController',
		'taxes_and_fees'                     => 'TaxesAndFeesController',

		// Blocks
		'get_blocks_controller'              => 'GetBlocksController',
		'create_block_controller'            => 'CreateBlockController',
		'delete_blocks_controller'           => 'DeleteBlocksController',
		'get_block_controller'               => 'GetBlockController',
		'update_block_controller'            => 'UpdateBlockController',
		'delete_block_controller'            => 'DeleteBlockController',

		// Checkout
		'submit_checkout'                    => 'SubmitCheckoutController',
		'submit_admin_checkout'              => 'SubmitAdminCheckoutController',
		'submit_payment'                     => 'SubmitPaymentController',

		// Customer
		'get_customers'                      => 'GetCustomersController',
		'get_customer'                       => 'GetCustomerController',

		// Google Hotels
		'google-hotels-data-get'             => 'GetGoogleHotelsData',
		'google-hotels-data-post'            => 'UpdateGoogleHotelsData',
		'google-hotels-verification-token'   => 'GetGoogleHotelsVerificationToken',
	);

	/**
	 * REST API namespaces and endpoints.
	 *
	 * @var array
	 */
	protected $controllers = array();

	/**
	 * Hook into WordPress ready to init the REST API as needed.
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'registerRestRoutes' ), 10 );
	}

	/**
	 * Register REST API routes.
	 */
	public function registerRestRoutes() {
		foreach ( $this->getRestNamespaces() as $namespace => $controllers ) {
			foreach ( $controllers as $controller_name => $controller_class ) {
				$restApiController = new $controller_class();
				$this->controllers[ $namespace ][ $controller_name ][] = $restApiController;
				$restApiController->register_routes();
			}
		}
	}

	/**
	 * Get API namespaces - new namespaces should be registered here.
	 *
	 * @return array List of Namespaces and Main controller classes.
	 */
	private function getRestNamespaces() {
		return apply_filters(
			'mphb_rest_api_get_rest_namespaces',
			array(
				'mphb/v1' => $this->getControllers( 1 ),
			)
		);
	}

	/**
	 * List of controllers whit their namespace for mphb/$version
	 *
	 * @param  int $version  Version of Api
	 *
	 * @return array
	 */
	private function getControllers( int $version ) {
		global $ver;
		$ver = $version;
		if ( ! defined( 'self::NAMESPACE_V' . $version ) ||
			 ! defined( 'self::CONTROLLERS_V' . $version ) ) {
			wp_die( 'Version API of ' . esc_html( $version ) . ' not found.' );
		}

		return array_map(
			function ( $controller ) {
				global $ver;
				return constant( 'self::NAMESPACE_V' . $ver ) . $controller;
			},
			constant( 'self::CONTROLLERS_V' . $ver )
		);
	}
}
