<?php

namespace MPHB\Advanced\Api\Controllers\V1;

use MPHB\Advanced\Api\Controllers\AbstractRestCommandController,
	MPHB\Crons\SyncGoogleHotelsDataCron;

defined( 'ABSPATH' ) || exit;


class GetGoogleHotelsData extends AbstractRestCommandController {


	public static function get_route(): string {
		return '/google-hotels-data';
	}

	public static function get_supported_methods(): string {
		return \WP_REST_Server::READABLE;
	}

	/**
	 * Check user permissions.
	 *
	 * @return \WP_Error|bool
	 */
	public static function is_request_allowed( \WP_REST_Request $request ) {
		return parent::is_request_allowed( $request ) && current_user_can( 'manage_options' );
	}

	/**
	 * https://developer.wordpress.org/rest-api/extending-the-rest-api/schema/
	 */
	protected static function get_request_schema(): array {

		return array();
	}

	/**
	 * https://developer.wordpress.org/rest-api/extending-the-rest-api/schema/
	 */
	public static function get_response_schema(): array {

		return \MPHB\Advanced\Api\RestApiSchemaHelper::getGoogleHotelsDataSchema();
	}

	/**
	 * @return mixed|\WP_Error data or error if needed to send some additional error data
	 * @throws \Exception when processing failed
	 */
	protected static function process_and_get_data_by_response_schema( \WP_REST_Request $request ) {

		$roomTypes = mphb_rooms_facade()->getAllRoomTypes();

		$result = self::getGoogleHotelsData( $roomTypes );

		return $result;
	}

	/**
	 * @param \MPHB\Entities\RoomType[]
	 */
	public static function getGoogleHotelsData( array $roomTypes ): array {

		$result = array(
			'isGoogleHotelsIntegrationOn' => MPHB()->settings()->main()->isGoogleHotelsIntegrationOn(),
			'isDataSentToRemoteServer'    => SyncGoogleHotelsDataCron::isDataSentToRemoteServer(),
			'properties'                  => array(),
			'roomTypes'                   => array(),
			'errors'                      => array(),
		);

		$lastSyncDatetime = SyncGoogleHotelsDataCron::getLastSyncAttemptDateTimeInWPTimezone();

		if ( null !== $lastSyncDatetime ) {

			$result['lastSyncDateTime']         = $lastSyncDatetime->format( 'Y-m-d\TH:i:sP' );
			$result['lastSyncMessage']          = wp_kses_post( SyncGoogleHotelsDataCron::getLastSyncAttemptMessage() );
			$result['lastSyncMessageState']     = self::getLastSyncMessageState();
			$result['lastSyncValidationErrors'] = array_map(
				'sanitize_text_field',
				SyncGoogleHotelsDataCron::getLastSyncAttemptValidationErrors()
			);
			$result['nextSyncMessage']          = wp_kses_post( SyncGoogleHotelsDataCron::getNextSyncAttemptMessage() );
		}

		$addedPropertyIds = array();

		foreach ( $roomTypes as $roomType ) {

			$originalRoomType = $roomType->getOriginalRoomType();

			$featuredImageId  = $originalRoomType->getFeaturedImageId();
			$featuredImageUrl = $featuredImageId ? wp_get_attachment_image_url( $featuredImageId ) : '';

			$roomTypeData = array(
				'id'                      => $roomType->getId(),
				'title'                   => wp_strip_all_tags( $roomType->getTitle() ),
				'excerpt'                 => wp_strip_all_tags( $roomType->getExcerpt() ),
				'featuredImage'           => $featuredImageUrl,
				'gallery'                 => $originalRoomType->getGalleryIds(),
				'capacity'                => $originalRoomType->calcTotalCapacity(),
				'isIncludeToGoogleHotels' => $originalRoomType->isIncludeToGoogleHotels(),
				'propertyId'              => $originalRoomType->getPropertyId(),
				'ghImages'                => $originalRoomType->getImages(),
				'ghTitle'                 => wp_strip_all_tags( $originalRoomType->getGHTitle() ),
				'ghDescription'           => wp_strip_all_tags( $originalRoomType->getGHDescription() ),
			);

			if ( $originalRoomType->isIncludeToGoogleHotels() ) {

				$roomTypeDataErrors = self::getRoomTypeDataErrors( $originalRoomType );

				if ( ! empty( $roomTypeDataErrors ) ) {
					$roomTypeData['errors'] = $roomTypeDataErrors;
				}

				// property data
				if ( ! empty( $originalRoomType->getPropertyId() ) &&
					! in_array( $originalRoomType->getPropertyId(), $addedPropertyIds, true )
				) {
					$addedPropertyIds[] = $originalRoomType->getPropertyId();

					$propertyData = array(
						'id'                         => $originalRoomType->getPropertyId(),
						'title'                      => $originalRoomType->getPropertyTitle(),
						'isIndoorAccommodation'      => $originalRoomType->isIndoorAccommodation(),
						'isOnsiteManaged'            => $originalRoomType->isOnsiteManaged(),
						'isAcceptingOvernightGuests' => $originalRoomType->isAcceptingOvernightGuests(),
						'isAddressPubliclyListed'    => $originalRoomType->isAddressPubliclyListed(),
						'propertyType'               => $originalRoomType->getPropertyType(),
						'propertyCategory'           => $originalRoomType->getPropertyCategory(),
						'propertyImages'             => $originalRoomType->getPropertyImages(),
						'latitude'                   => $originalRoomType->getLatitude(),
						'longitude'                  => $originalRoomType->getLongitude(),
						'contacts'                   => array(
							'mainPhone' => $originalRoomType->getContactsMainPhone(),
						),
						'address'                    => array(
							'line1'       => $originalRoomType->getAddressLine1(),
							'line2'       => $originalRoomType->getAddressLine2(),
							'city'        => $originalRoomType->getAddressCity(),
							'province'    => $originalRoomType->getAddressProvince(),
							'postalCode'  => $originalRoomType->getAddressPostalCode(),
							'countryCode' => $originalRoomType->getAddressCountryCode(),
						),
					);

					$propertyDataErrors = self::getPropertyDataErrors( $originalRoomType );

					if ( ! empty( $propertyDataErrors ) ) {
						$propertyData['errors'] = $propertyDataErrors;
					}

					$result['properties'][] = $propertyData;
				}
			}

			$result['roomTypes'][] = $roomTypeData;
		}

		usort(
			$result['roomTypes'],
			function ( $a, $b ) {
				return $a['capacity'] <=> $b['capacity'];
			}
		);

		return $result;
	}

	/**
	 * Maps the last server status to the message background state shown on the page.
	 *
	 * @return string 'updated' | 'failed' | 'unknown'
	 */
	private static function getLastSyncMessageState(): string {

		if ( SyncGoogleHotelsDataCron::isServerStatusUpdated() ) {
			return 'updated';
		}

		if ( SyncGoogleHotelsDataCron::isServerStatusFailed() ) {
			return 'failed';
		}

		return 'unknown';
	}

	public static function getRoomTypeDataErrors( \MPHB\Entities\RoomType $roomType ): array {

		$roomTypeDataErrors = array();

		if ( empty( $roomType->getPropertyId() ) ) {

			$roomTypeDataErrors['propertyId'] = sprintf(
				// translators: %s is data field name
				__( 'Required field "%s" is not set.', 'motopress-hotel-booking' ),
				__( 'Location', 'motopress-hotel-booking' ),
			);
		}

		$title = $roomType->getGHTitle();

		if ( empty( $title ) ) {
			$title = $roomType->getTitle();
		}

		if ( empty( $title ) ) {

			$roomTypeDataErrors['ghTitle'] = sprintf(
				// translators: %s is data field name
				__( 'Required field "%s" is not set.', 'motopress-hotel-booking' ),
				__( 'Title', 'motopress-hotel-booking' ),
			);

		} elseif ( 300 < mb_strlen( $title, 'UTF-8' ) ) {

			$roomTypeDataErrors['ghTitle'] = sprintf(
				// translators: %1$s is field name, %2$d is max length
				__( 'Field "%1$s" must not exceed %2$d characters.', 'motopress-hotel-booking' ),
				__( 'Title', 'motopress-hotel-booking' ),
				300
			);
		}

		$description = $roomType->getGHDescription();

		if ( empty( $description ) ) {
			$description = $roomType->getExcerpt();
		}

		if ( ! empty( $description ) &&
			900 < mb_strlen( $description, 'UTF-8' )
		) {

			$roomTypeDataErrors['ghDescription'] = sprintf(
				// translators: %1$s is field name, %2$d is max length
				__( 'Field "%1$s" must not exceed %2$d characters.', 'motopress-hotel-booking' ),
				__( 'Description', 'motopress-hotel-booking' ),
				900
			);
		}

		if ( 1 > $roomType->calcTotalCapacity() ) {

			$roomTypeDataErrors['capacity'] = sprintf(
				// translators: %1$s is field name, %2$d is min value
				__( 'Field "%1$s" must be greater than %2$d.', 'motopress-hotel-booking' ),
				__( 'Capacity', 'motopress-hotel-booking' ),
				1
			);
		}

		if ( 'vacation_rental' === $roomType->getPropertyType() && 5 > count( $roomType->getImages() ) ) {

			$roomTypeDataErrors['ghImages'] = __( 'Add at least 5 photos to the gallery.', 'motopress-hotel-booking' );
		}

		if ( 'hotel' === $roomType->getPropertyType() && 1 > count( $roomType->getImages() ) ) {

			$roomTypeDataErrors['ghImages'] = __( 'Add at least 1 photo to the gallery.', 'motopress-hotel-booking' );
		}

		return $roomTypeDataErrors;
	}

	public static function getPropertyDataErrors( \MPHB\Entities\RoomType $roomType ): array {

		$propertyDataErrors = array();

		if ( empty( $roomType->getPropertyId() ) ) {

			$propertyDataErrors['id'] = sprintf(
				// translators: %s is data field name
				__( 'Required field "%s" is not set.', 'motopress-hotel-booking' ),
				'id'
			);
		}

		if ( empty( $roomType->getPropertyTitle() ) ) {

			$propertyDataErrors['title'] = sprintf(
				// translators: %s is data field name
				__( 'Required field "%s" is not set.', 'motopress-hotel-booking' ),
				__( 'Name of Hotel or Vacation Rental', 'motopress-hotel-booking' ),
			);

		} elseif ( 300 < mb_strlen( $roomType->getPropertyTitle(), 'UTF-8' ) ) {

			$propertyDataErrors['title'] = sprintf(
				// translators: %1$s is field name, %2$d is max length
				__( 'Field "%1$s" must not exceed %2$d characters.', 'motopress-hotel-booking' ),
				__( 'Name of Hotel or Vacation Rental', 'motopress-hotel-booking' ),
				300
			);
		}

		if ( empty(
			$roomType->getPropertyType() ||
			! in_array(
				$roomType->getPropertyType(),
				array( 'hotel', 'vacation_rental', 'outdoor_lodging' ),
				true
			)
		) ) {

			$propertyDataErrors['propertyType'] = sprintf(
				// translators: %s is data field name
				__( 'Required field "%s" is not set.', 'motopress-hotel-booking' ),
				__( 'Lodging category', 'motopress-hotel-booking' ),
			);
		}

		if ( 'hotel' === $roomType->getPropertyType() &&
			(
				! $roomType->isIndoorAccommodation() ||
				! $roomType->isOnsiteManaged() ||
				! $roomType->isAcceptingOvernightGuests() ||
				! $roomType->isAddressPubliclyListed()
			)
		) {

			$propertyDataErrors['propertyType'] = __( 'To qualify as a hotel, the property must offer overnight stays and have on-site management and a public address.', 'motopress-hotel-booking' );
		}

		if ( 'hotel' === $roomType->getPropertyType() ) {

			if ( ! in_array(
				$roomType->getPropertyCategory(),
				array(
					'hotel',
					'motel',
					'hostel',
					'resort_hotel',
					'mountain_hut',
					'camping_cabin',
					'aparthotel',
					'love_hotel',
					'inn',
					'bed_and_breakfast',
					'farm_stay',
					'japanese_inn',
					'capsule_hotel',
					'religious_accommodation',
					'budget_japanese_inn',
					'holiday_park',
				),
				true
			)
			) {
				$propertyDataErrors['propertyCategory'] = __( 'Hotel property category is invalid.', 'motopress-hotel-booking' );
			}

			if ( 'japanese_inn' === $roomType->getPropertyCategory() &&
				'JP' !== $roomType->getAddressCountryCode()
			) {
				$propertyDataErrors['propertyCategory'] = __( 'Japanese Inn (Ryokan) is only applicable for properties in Japan!', 'motopress-hotel-booking' );
			}

			if ( 'holiday_park' === $roomType->getPropertyCategory() &&
				! in_array(
					$roomType->getAddressCountryCode(),
					array(
						'AU',
						'NZ',
						'AX', // Åland Islands
						'AL', // Albania
						'AD', // Andorra
						'AT', // Austria
						'BY', // Belarus
						'BE', // Belgium
						'BA', // Bosnia & Herzegovina
						'BG', // Bulgaria
						'HR', // Croatia
						'CY', // Cyprus
						'CZ', // Czechia
						'DK', // Denmark
						'EE', // Estonia
						'FO', // Faroe Islands
						'FI', // Finland
						'FR', // France
						'DE', // Germany
						'GI', // Gibraltar
						'GR', // Greece
						'HU', // Hungary
						'IS', // Iceland
						'IE', // Ireland
						'IM', // Isle of Man
						'IT', // Italy
						'JE', // Jersey
						'LV', // Latvia
						'LI', // Liechtenstein
						'LT', // Lithuania
						'LU', // Luxembourg
						'MT', // Malta
						'MD', // Moldova
						'MC', // Monaco
						'ME', // Montenegro
						'NL', // Netherlands
						'MK', // North Macedonia
						'NO', // Norway
						'PL', // Poland
						'PT', // Portugal
						'RO', // Romania
						'RU', // Russia (географически Европа частично)
						'SM', // San Marino
						'RS', // Serbia
						'SK', // Slovakia
						'SI', // Slovenia
						'ES', // Spain
						'SJ', // Svalbard & Jan Mayen
						'SE', // Sweden
						'CH', // Switzerland
						'UA', // Ukraine
						'GB', // United Kingdom
						'VA', // Vatican City
					),
					true
				)
			) {
				$propertyDataErrors['propertyCategory'] = __( 'Holiday park is only applicable for properties in AU, NZ, and European countries!', 'motopress-hotel-booking' );
			}
		}

		if ( 'vacation_rental' === $roomType->getPropertyType() &&
			! in_array(
				$roomType->getPropertyCategory(),
				array(
					'apartment',
					'bungalow',
					'cabin',
					'chalet',
					'cottage',
					'gite',
					'holiday_village_rental',
					'house',
					'villa',
					'vacation_rental',
				),
				true
			)
		) {
			$propertyDataErrors['propertyCategory'] = __( 'Vacation rental property category is invalid.', 'motopress-hotel-booking' );
		}

		// 0 is default not valid coordinate
		if ( 0 === $roomType->getLatitude() || empty( $roomType->getLatitude() ) ) {

			$propertyDataErrors['latitude'] = sprintf(
				// translators: %s is data field name
				__( 'Required field "%s" is not set.', 'motopress-hotel-booking' ),
				__( 'Latitude', 'motopress-hotel-booking' ),
			);
		}

		// 0 is default not valid coordinate
		if ( 0 === $roomType->getLongitude() || empty( $roomType->getLongitude() ) ) {

			$propertyDataErrors['longitude'] = sprintf(
				// translators: %s is data field name
				__( 'Required field "%s" is not set.', 'motopress-hotel-booking' ),
				__( 'Longitude', 'motopress-hotel-booking' ),
			);
		}

		if ( empty( $roomType->getContactsMainPhone() ) ) {

			$propertyDataErrors['contacts.mainPhone'] = sprintf(
				// translators: %s is data field name
				__( 'Required field "%s" is not set.', 'motopress-hotel-booking' ),
				__( 'Primary Phone Number', 'motopress-hotel-booking' ),
			);

		} elseif (
			5 > strlen( $roomType->getContactsMainPhone() ) ||
			32 < strlen( $roomType->getContactsMainPhone() )
		) {

			$propertyDataErrors['contacts.mainPhone'] = sprintf(
				// translators: %1$s is field name, %2$d and %3$d are min and max length
				__( 'Field "%1$s" must contain between %2$d and %3$d characters.', 'motopress-hotel-booking' ),
				__( 'Primary Phone Number', 'motopress-hotel-booking' ),
				5,
				32
			);
		}

		if ( empty( $roomType->getAddressLine1() ) ) {

			$propertyDataErrors['address.line1'] = sprintf(
				// translators: %s is data field name
				__( 'Required field "%s" is not set.', 'motopress-hotel-booking' ),
				__( 'Address Line 1', 'motopress-hotel-booking' ),
			);
		} elseif ( 128 < mb_strlen( $roomType->getAddressLine1(), 'UTF-8' ) ) {

			$propertyDataErrors['address.line1'] = sprintf(
				// translators: %1$s is field name, %2$d is max length
				__( 'Field "%1$s" must not exceed %2$d characters.', 'motopress-hotel-booking' ),
				__( 'Address Line 1', 'motopress-hotel-booking' ),
				128
			);
		}

		if ( ! empty( $roomType->getAddressLine2() ) &&
			128 < mb_strlen( $roomType->getAddressLine2(), 'UTF-8' )
		) {

			$propertyDataErrors['address.line2'] = sprintf(
				// translators: %1$s is field name, %2$d is max length
				__( 'Field "%1$s" must not exceed %2$d characters.', 'motopress-hotel-booking' ),
				__( 'Address Line 2', 'motopress-hotel-booking' ),
				128
			);
		}

		if ( empty( $roomType->getAddressCity() ) ) {

			$propertyDataErrors['address.city'] = sprintf(
				// translators: %s is data field name
				__( 'Required field "%s" is not set.', 'motopress-hotel-booking' ),
				__( 'City', 'motopress-hotel-booking' ),
			);

		} elseif ( 128 < mb_strlen( $roomType->getAddressCity(), 'UTF-8' ) ) {

			$propertyDataErrors['address.city'] = sprintf(
				// translators: %1$s is field name, %2$d is max length
				__( 'Field "%1$s" must not exceed %2$d characters.', 'motopress-hotel-booking' ),
				__( 'City', 'motopress-hotel-booking' ),
				128
			);
		}

		if ( ! empty( $roomType->getAddressProvince() ) &&
			128 < mb_strlen( $roomType->getAddressProvince(), 'UTF-8' )
		) {

			$propertyDataErrors['address.province'] = sprintf(
				// translators: %1$s is field name, %2$d is max length
				__( 'Field "%1$s" must not exceed %2$d characters.', 'motopress-hotel-booking' ),
				__( 'Province', 'motopress-hotel-booking' ),
				128
			);
		}

		if ( empty( $roomType->getAddressPostalCode() ) ) {

			$propertyDataErrors['address.postalCode'] = sprintf(
				// translators: %s is data field name
				__( 'Required field "%s" is not set.', 'motopress-hotel-booking' ),
				__( 'Postal Code', 'motopress-hotel-booking' ),
			);

		} elseif ( 20 < strlen( $roomType->getAddressPostalCode() ) ) {

			$propertyDataErrors['address.postalCode'] = sprintf(
				// translators: %1$s is field name, %2$d is max length
				__( 'Field "%1$s" must not exceed %2$d characters.', 'motopress-hotel-booking' ),
				__( 'Postal Code', 'motopress-hotel-booking' ),
				20
			);
		}

		if ( empty( $roomType->getAddressCountryCode() ) ||
			empty( MPHB()->settings()->main()->getCountriesBundle()->getCountriesList()[ $roomType->getAddressCountryCode() ] )
		) {

			$propertyDataErrors['address.countryCode'] = sprintf(
				// translators: %s is data field name
				__( 'Required field "%s" is not set.', 'motopress-hotel-booking' ),
				__( 'Country', 'motopress-hotel-booking' ),
			);
		}

		if ( 'hotel' === $roomType->getPropertyType() && 1 > count( $roomType->getPropertyImages() ) ) {

			$propertyDataErrors['propertyImages'] = sprintf(
				// translators: %s is data field name
				__( 'Required field "%s" is not set.', 'motopress-hotel-booking' ),
				__( 'Hotel Image', 'motopress-hotel-booking' ),
			);
		}

		return $propertyDataErrors;
	}
}
