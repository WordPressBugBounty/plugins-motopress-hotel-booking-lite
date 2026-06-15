<?php

namespace MPHB\Advanced\Api;

defined( 'ABSPATH' ) || exit;

final class RestApiSchemaHelper {

	public static function getGoogleHotelsDataSchema(): array {
		return array(
			'$schema' => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'google_hotels',
			'type'       => 'object',
			'required'   => array(
				'isGoogleHotelsIntegrationOn',
				'properties',
				'roomTypes',
			),
			'validate_callback' => 'rest_validate_request_arg',
			'properties' => array(
	
				'isGoogleHotelsIntegrationOn' => array(
					'type' => 'boolean',
				),
				'isDataSentToRemoteServer' => array(
					'type' => 'boolean',
				),
	
				'properties' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'required'   => array(
							'id',
						),
						'properties' => array(
	
							'id' => array(
								'type' => 'string',
							),
	
							'title' => array(
								'type' => 'string',
							),
	
							'isIndoorAccommodation' => array(
								'type' => 'boolean',
							),
	
							'isOnsiteManaged' => array(
								'type' => 'boolean',
							),
	
							'isAcceptingOvernightGuests' => array(
								'type' => 'boolean',
							),
	
							'isAddressPubliclyListed' => array(
								'type' => 'boolean',
							),
	
							'propertyType' => array(
								'type' => 'string',
								'enum' => array(
									'hotel',
									'vacation_rental',
									'outdoor_lodging',
								),
							),
	
							'propertyCategory' => array(
								'type' => 'string',
								'enum' => array(
									'',
	
									// HOTEL_CATEGORY_VALUE
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
	
									// VACATION_RENTAL_CATEGORY_VALUE
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
							),
	
							'latitude' => array(
								'type' => 'number',
							),
	
							'longitude' => array(
								'type' => 'number',
							),
	
							'contacts' => array(
								'type'       => 'object',
								'required'   => array(
									'mainPhone',
								),
								'properties' => array(
									'mainPhone' => array(
										'type' => 'string',
									),
								),
							),
	
							'address' => array(
								'type'       => 'object',
								'properties' => array(
									'line1' => array(
										'type' => 'string',
									),
									'line2' => array(
										'type' => 'string',
									),
									'city' => array(
										'type' => 'string',
									),
									'province' => array(
										'type' => 'string',
									),
									'postalCode' => array(
										'type' => 'string',
									),
									'countryCode' => array(
										'type' => 'string',
									),
								),
							),

							'propertyImages' => array(
								'type'  => 'array',
								'items' => array(
									'type' => 'integer',
								)
							),

							// errors?: Record<string, string>
							'errors' => array(
								'type' => 'object',
								'description' => 'Validation errors indexed by property (hotel/vacation rental) property name (e.g. "capacity", "title")',
								'additionalProperties' => array(
									'type' => 'string',
								),
							),
						),
					),
				),
	
				'roomTypes' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'required'   => array(
							'id',
							'isIncludeToGoogleHotels',
						),
						'properties' => array(
	
							'id' => array(
								'type' => 'integer',
							),
	
							'title' => array(
								'type' => 'string',
							),
	
							'capacity' => array(
								'type' => 'integer',
								'minimum' => 1,
							),
	
							'isIncludeToGoogleHotels' => array(
								'type' => 'boolean',
							),
	
							'propertyId' => array(
								'type' => 'string',
							),

							'ghImages' => array(
								'type'  => 'array',
								'items' => array(
									'type' => 'integer',
								)
							),

							'ghTitle' => array(
								'type' => 'string',
							),

							'ghDescription' => array(
								'type' => 'string',
							),

							// errors?: Record<string, string>
							'errors' => array(
								'type' => 'object',
								'description' => 'Validation errors indexed by room type property name (e.g. "capacity", "title")',
								'additionalProperties' => array(
									'type' => 'string',
								),
							),
						),
					),
				),
				'errors' => array(
					'type'  => 'array',
					'items' => array(
						'type' => 'string',
					),
				),
			),
		);
	}

	public static function getBlockProperties(): array {
		return array(
			'block_id'         => array(
				'type'    => 'integer',
				'minimum' => 1,
			),
			'comment'          => array(
				'type' => 'string',
			),
			'date_from'        => array(
				'type'        => 'string',
				'description' => 'Date in WordPress timezone (YYYY-MM-DD) (inclusive)',
				'format'      => 'date',
			),
			'date_to'          => array(
				'type'        => 'string',
				'description' => 'Date in WordPress timezone (YYYY-MM-DD) (inclusive)',
				'format'      => 'date',
			),
			'has_restrictions' => array(
				'type' => 'boolean',
			),
			'not_check_in'     => array(
				'type' => 'boolean',
			),
			'not_check_out'    => array(
				'type' => 'boolean',
			),
			'not_stay_in'      => array(
				'type' => 'boolean',
			),
			'room_id'          => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
			'room_type_id'     => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
		);
	}

	public static function getCustomBookingRuleProperties(): array {
		return array(
			'allow_check_in'          => array(
				'type' => 'boolean',
			),
			'allow_check_out'         => array(
				'type' => 'boolean',
			),
			'buffer_days'             => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
			'min_stay_length'         => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
			'max_stay_length'         => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
			'min_advance_reservation' => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
			'max_advance_reservation' => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
		);
	}

	public static function getCustomerProperties(): array {
		return array(
			'id'         => array(
				'type'    => 'integer',
				'minimum' => 1,
			),
			'user_id'    => array(
				'type'    => array( 'integer', 'null' ),
				'minimum' => 1,
			),
			'email'      => array(
				'type'   => 'string',
				'format' => 'email',
			),
			'first_name' => array(
				'type' => 'string',
			),
			'last_name'  => array(
				'type' => 'string',
			),
			'phone'      => array(
				'type' => 'string',
			),
			'country'    => array(
				'type'        => 'string',
				'description' => 'Country code, like "US".',
				'minLength'   => 2,
				'maxLength'   => 2,
			),
			'state'      => array(
				'type' => 'string',
			),
			'city'       => array(
				'type' => 'string',
			),
			'address1'   => array(
				'type' => 'string',
			),
			'zip'        => array(
				'type' => 'string',
			),
			'bookings'   => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
		);
	}
}
