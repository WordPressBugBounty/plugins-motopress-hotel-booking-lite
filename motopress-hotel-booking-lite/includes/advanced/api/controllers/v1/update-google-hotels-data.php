<?php

namespace MPHB\Advanced\Api\Controllers\V1;

use MPHB\Advanced\Api\Controllers\AbstractRestCommandController,
	MPHB\Crons\SyncGoogleHotelsDataCron;

defined( 'ABSPATH' ) || exit;


class UpdateGoogleHotelsData extends AbstractRestCommandController {


	public static function get_route(): string {
		return '/google-hotels-data';
	}

	public static function get_supported_methods(): string {
		return \WP_REST_Server::EDITABLE;
	}

	/**
	 * Check user permissions.
	 * @return \WP_Error|bool
	 */
	public static function is_request_allowed( \WP_REST_Request $request ) {
		return parent::is_request_allowed( $request ) && current_user_can( 'manage_options' );
	}

	/**
	 * https://developer.wordpress.org/rest-api/extending-the-rest-api/schema/
	 */
	protected static function get_request_schema(): array {

		$request_schema = \MPHB\Advanced\Api\RestApiSchemaHelper::getGoogleHotelsDataSchema()['properties'];

		return $request_schema;
	}

	/**
	 * https://developer.wordpress.org/rest-api/extending-the-rest-api/schema/
	 */
	public static function get_response_schema(): array {

		return \MPHB\Advanced\Api\RestApiSchemaHelper::getGoogleHotelsDataSchema();
	}

	/**
	 * @return \WP_Error|null - returns null if all parameters are valid.
	 */
	public static function validate_request( \WP_REST_Request $request ): ?\WP_Error {

		return parent::validate_request( $request );
	}

	/**
	 * @return mixed|\WP_Error data or error if needed to send some additional error data
	 * @throws Exception when processing failed
	 */
	protected static function process_and_get_data_by_response_schema( \WP_REST_Request $request ) {

		$requestData = $request->get_params();

		// can be translated
		$requestedRoomTypeIds = array();

		$properties = array();

		foreach ( $requestData['properties'] as $propertyData ) {

			$propertyId = $propertyData['id'];

			if (empty( $propertyData['id'] ) ||
				0 === strpos( $propertyData['id'], 'TEMP_' )
			) {
				$propertyId = wp_generate_uuid4(); // 36 symbols including hyphens]
			}

			// keep temp property id as index for later use
			$updatingPropertyData = array(
				'id'                         => $propertyId,
				'title'                      => trim( $propertyData['title'] ) ?? '',
				'isIndoorAccommodation'      => $propertyData['isIndoorAccommodation'] ?? false,
				'isOnsiteManaged'            => $propertyData['isOnsiteManaged'] ?? false,
				'isAcceptingOvernightGuests' => $propertyData['isAcceptingOvernightGuests'] ?? false,
				'isAddressPubliclyListed'    => $propertyData['isAddressPubliclyListed'] ?? false,
				'propertyType'               => trim( $propertyData['propertyType'] ) ?? 'outdoor_lodging',
				'propertyCategory'           => trim( $propertyData['propertyCategory'] ) ?? '',
				'latitude'                   => $propertyData['latitude'] ?? 0.0,
				'longitude'                  => $propertyData['longitude'] ?? 0.0,
				'contactsMainPhone'          => isset( $propertyData['contacts']['mainPhone'] ) ? trim( $propertyData['contacts']['mainPhone'] ) : '',
				'addressLine1'               => isset( $propertyData['address']['line1'] ) ? trim( $propertyData['address']['line1'] ) : '',
				'addressLine2'               => isset( $propertyData['address']['line2'] ) ? trim( $propertyData['address']['line2'] ) : '',
				'addressCity'                => isset( $propertyData['address']['city'] ) ? trim( $propertyData['address']['city'] ) : '',
				'addressProvince'            => isset( $propertyData['address']['province'] ) ? trim( $propertyData['address']['province'] ) : '',
				'addressPostalCode'          => isset( $propertyData['address']['postalCode'] ) ? trim( $propertyData['address']['postalCode'] ) : '',
				'addressCountryCode'         => isset( $propertyData['address']['countryCode'] ) ? trim( $propertyData['address']['countryCode'] ) : '',
			);

			if ( ! $updatingPropertyData['isIndoorAccommodation'] ) {

				$updatingPropertyData['propertyType']     = 'outdoor_lodging';
				$updatingPropertyData['propertyCategory'] = '';
			}

			$properties[ $propertyData['id'] ] = $updatingPropertyData;
		}

		$isMinimumOneRoomTypeIncludeToGoogleHotels = false;

		foreach ( $requestData['roomTypes'] as $roomTypeData ) {

			$requestedRoomTypeIds[] = $roomTypeData['id'];

			$roomType = mphb_rooms_facade()->getRoomTypeById( $roomTypeData['id'] );

			if ( null === $roomType ) {
				continue;
			}

			$originalRoomType = $roomType->getOriginalRoomType();

			$originalRoomType->setIncludeToGoogleHotels(
				$roomTypeData['isIncludeToGoogleHotels'] ?? false
			);

			if ( $roomTypeData['isIncludeToGoogleHotels'] ) {
				$isMinimumOneRoomTypeIncludeToGoogleHotels = true;
			}

			$propertyData = isset( $properties[ $roomTypeData['propertyId'] ] ) ?
				$properties[ $roomTypeData['propertyId'] ] :
				array();

			$originalRoomType->setPropertyId( $propertyData['id'] ?? '' );
			$originalRoomType->setPropertyTitle( $propertyData['title'] ?? '' );
			$originalRoomType->setIndoorAccommodation( $propertyData['isIndoorAccommodation'] ?? false );
			$originalRoomType->setOnsiteManaged( $propertyData['isOnsiteManaged'] ?? false );
			$originalRoomType->setAcceptingOvernightGuests( $propertyData['isAcceptingOvernightGuests'] ?? false );
			$originalRoomType->setAddressPubliclyListed( $propertyData['isAddressPubliclyListed'] ?? false );
			$originalRoomType->setPropertyType( $propertyData['propertyType'] ?? '' );
			$originalRoomType->setPropertyCategory( $propertyData['propertyCategory'] ?? '' );
			$originalRoomType->setLatitude( $propertyData['latitude'] ?? 0.0 );
			$originalRoomType->setLongitude( $propertyData['longitude'] ?? 0.0 );
			$originalRoomType->setContactsMainPhone( $propertyData['contactsMainPhone'] ?? '' );
			$originalRoomType->setAddressLine1( $propertyData['addressLine1'] ?? '' );
			$originalRoomType->setAddressLine2( $propertyData['addressLine2'] ?? '' );
			$originalRoomType->setAddressCity( $propertyData['addressCity'] ?? '' );
			$originalRoomType->setAddressProvince( $propertyData['addressProvince'] ?? '' );
			$originalRoomType->setAddressPostalCode( $propertyData['addressPostalCode'] ?? '' );
			$originalRoomType->setAddressCountryCode( $propertyData['addressCountryCode'] ?? '' );

			mphb_rooms_facade()->updateRoomType( $originalRoomType );
		}

		MPHB()->settings()->main()->updateGoogleHotelsIntegrationOn(
			$isMinimumOneRoomTypeIncludeToGoogleHotels && $requestData['isGoogleHotelsIntegrationOn']
		);

		SyncGoogleHotelsDataCron::startNow();

		$requestedRoomTypes = mphb_rooms_facade()->findRoomTypesByIdsOrSlugs( $requestedRoomTypeIds );
		$result = GetGoogleHotelsData::getGoogleHotelsData( $requestedRoomTypes );

		return $result;
	}
}
