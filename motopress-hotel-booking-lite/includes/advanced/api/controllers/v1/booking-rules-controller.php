<?php
/**
 * @package MPHB\Advanced\Api
 * @since 4.1.0
 */

namespace MPHB\Advanced\Api\Controllers\V1;

use MPHB\Advanced\Api\Controllers\AbstractRestOptionsController;
use MPHB\Advanced\Api\Data\OptionsSchema;
use MPHB\Advanced\Api\ApiHelper;
use MPHB\Utils\ValidateUtils;
use WP_Error;
use WP_REST_Request;

class BookingRulesController extends AbstractRestOptionsController {

	const ENDPOINT_REPLACEMENT_RULES = array(
		'room_type_id'  => 'accommodation_type_id',
		'room_type_ids' => 'accommodation_type_ids',
		'room_id'       => 'accommodation_id',
	);

	protected $namespace = 'mphb/v1';

	protected $rest_base = 'booking_rules';

	/**
	 * @return OptionsSchema
	 */
	protected function initOptions() {
		$options = new OptionsSchema();

		$options->setComponent( 'booking_rules' );

		$checkInDays           = $this->getDaysAccommodationTypeIdsSeasonIdsOption( 'check_in_days' );
		$checkOutDays          = $this->getDaysAccommodationTypeIdsSeasonIdsOption( 'check_out_days' );
		$minStayLength         = $this->getLengthAccommodationTypeIdsSeasonIds( 'min_stay_length' );
		$maxStayLength         = $this->getLengthAccommodationTypeIdsSeasonIds( 'max_stay_length' );
		$bookingRulesCustom    = $this->getBookingRulesCustom();
		$minAdvanceReservation = $this->getZeroLengthAccommodationTypeIdsSeasonIds( 'min_advance_reservation' );
		$maxAdvanceReservation = $this->getZeroLengthAccommodationTypeIdsSeasonIds( 'max_advance_reservation' );
		$bufferDays            = $this->getZeroLengthAccommodationTypeIdsSeasonIds( 'buffer_days' );

		$options->addOption( 'mphb_check_in_days', 'check_in_days', $checkInDays );
		$options->addOption( 'mphb_check_out_days', 'check_out_days', $checkOutDays );
		$options->addOption( 'mphb_min_stay_length', 'min_stay_length', $minStayLength );
		$options->addOption( 'mphb_max_stay_length', 'max_stay_length', $maxStayLength );
		$options->addOption( 'mphb_booking_rules_custom', 'booking_rules_custom', $bookingRulesCustom );
		$options->addOption( 'mphb_min_advance_reservation', 'min_advance_reservation', $minAdvanceReservation );
		$options->addOption( 'mphb_max_advance_reservation', 'max_advance_reservation', $maxAdvanceReservation );
		$options->addOption( 'mphb_buffer_days', 'buffer_days', $bufferDays );

		return $options;
	}

	private function getDaysAccommodationTypeIdsSeasonIdsOption( string $optionName ) {
		return array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					$optionName              => $this->getDaysProperty( $optionName ),
					'accommodation_type_ids' => $this->getAccommodationTypeProperty(),
					'season_ids'             => $this->getSeasonIdsProperty(),
				),
			),
		);
	}

	private function getDaysProperty( $name ) {
		return array(
			'name'        => $name,
			'type'        => 'array',
			'uniqueItems' => true,
			'items'       => array(
				'type'    => 'integer',
				'minimum' => 0,
				'maximum' => 6,
			),
			'required'    => true,
		);
	}

	private function getAccommodationTypeProperty() {
		return array(
			'name'        => 'accommodation_type_ids',
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
				'enum' => $this->getAccommodationTypeIds(),
			),
			'uniqueItems' => true,
			'required'    => true,
		);
	}

	/**
	 * @return int[]
	 */
	private function getAccommodationTypeIds() {
		$accommodationTypeIds = MPHB()->getRoomTypePersistence()->getPosts(
			array(
				'fields'      => 'ids',
				'post_status' => 'any',
			)
		);

		array_unshift( $accommodationTypeIds, 0 );

		return $accommodationTypeIds;
	}

	private function getSeasonIdsProperty() {
		$seasonIds = MPHB()->getSeasonPersistence()->getPosts(
			array(
				'fields'      => 'ids',
				'post_status' => 'any',
			)
		);

		array_unshift( $seasonIds, 0 );

		return array(
			'name'        => 'season_ids',
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
				'enum' => $seasonIds,
			),
			'uniqueItems' => true,
			'required'    => true,
		);
	}

	private function getLengthAccommodationTypeIdsSeasonIds( string $optionName ) {
		return array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					$optionName              => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'accommodation_type_ids' => $this->getAccommodationTypeProperty(),
					'season_ids'             => $this->getSeasonIdsProperty(),
				),
			),
		);
	}

	private function getZeroLengthAccommodationTypeIdsSeasonIds( string $optionName ) {
		return array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					$optionName              => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
					'accommodation_type_ids' => $this->getAccommodationTypeProperty(),
					'season_ids'             => $this->getSeasonIdsProperty(),
				),
			),
		);
	}

	private function getBookingRulesCustom() {
		return array(
			'type'        => 'array',
			'arg_options' => array(
				'validate_callback' => array( $this, 'boookingRulesCustomValidate' ),
			),
			'items'       => array(
				'type'       => 'object',
				'properties' => array(
					'accommodation_type_id' => array(
						'type'     => 'integer',
						'enum'     => $this->getAccommodationTypeIds(),
						'required' => true,
					),
					'accommodation_id'      => array(
						'type'     => 'integer',
						'required' => true,
					),
					'date_from'             => array(
						'type'     => 'string',
						'format'   => 'date',
						'required' => true,
					),
					'date_to'               => array(
						'type'     => 'string',
						'format'   => 'date',
						'required' => true,
					),
					'restrictions'          => array(
						'type'     => 'array',
						'items'    => array(
							'type' => 'string',
							'enum' => array( 'check-in', 'check-out', 'stay-in' ),
						),
						'required' => true,
					),
					'comment'               => array(
						'type' => 'string',
					),
				),
			),
		);
	}

	private function isValidAccommodationIdProperty( $value ) {
		$accommodationIds = array_column( $value, 'accommodation_id' );
		$accommodationIds = array_diff( $accommodationIds, array( 0 ) );
		if ( ! count( $accommodationIds ) ) {
			return true;
		}
		/**
		 * @var array '$accommodation_id' => '$accommodation_type_id'
		 */
		$accommodations        = array();
		$atts                  = array(
			'include' => $accommodationIds,
		);
		$accommodationEntities = MPHB()->getRoomRepository()->findAll( $atts );
		foreach ( $accommodationEntities as $accommodationEntity ) {
			$accommodationId                    = $accommodationEntity->getId();
			$accommodations[ $accommodationId ] = $accommodationEntity->getRoomTypeId();
		}

		foreach ( $value as $valueItem ) {
			// Check accommodation ID
			$accommodationId = intval( $valueItem['accommodation_id'] );

			if ( $accommodationId === 0 ) {
				continue;
			}

			if ( ! isset( $accommodations[ $accommodationId ] ) ) {
				return new WP_Error(
					'mphb_rest_invalid_accommodation_id',
					sprintf( '%s accommodation_id: %d', 'Invalid', $accommodationId ),
					array(
						'status' => 400,
						'params' => 'accommodation_type_id',
					)
				);
			}

			// Check accommodation type ID
			$accommodationTypeId = intval( $valueItem['accommodation_type_id'] );

			if ( $accommodations[ $accommodationId ] != $accommodationTypeId ) {
				return new WP_Error(
					'mphb_rest_invalid_accommodation_id',
					sprintf( '%s accommodation_id: %d', 'Invalid', $accommodationId ),
					array(
						'status'  => 400,
						'params'  => 'accommodation_id',
						'details' => sprintf(
							'Accommodation id %d is not of accommodation type id %d',
							$accommodationId,
							$accommodationTypeId
						),
					)
				);
			}
		}

		return true;
	}

	public function boookingRulesCustomValidate( $value, $request, $param ) {
		$isValid = rest_validate_request_arg( $value, $request, $param );
		if ( is_wp_error( $isValid ) ) {
			return $isValid;
		}

		$isValid = $this->isValidAccommodationIdProperty( $value );
		if ( is_wp_error( $isValid ) ) {
			return $isValid;
		}

		return true;
	}

	/**
	 * Retrieves the options.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return array|WP_Error Array on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$options  = $this->options->getOptionsSchema();
		$response = array();

		foreach ( $options as $name => $args ) {
			if ( $name !== 'booking_rules_custom' ) {
				$option = get_option( $args['option_name'], $args['schema']['default'] );
			} else {
				$option = array_values( MPHB()->getBlocksRepository()->getAll() );
			}

			// Calls prepareResponseBookingRulesCustom() for custom booking rules
			$response[ $name ] = $this->prepareResponse( $option, $name, $args['schema'] );
		}

		return $response;
	}

	/**
	 * Converts new blocks to old rules.
	 *
	 * @see AbstractRestOptionsController::prepareResponse()
	 */
	protected function prepareResponseBookingRulesCustom( array $blocks ): array {
		return array_map(
			fn( array $block ) => ApiHelper::prepareCustomBookingRuleResponse( $block ),
			$blocks
		);
	}

	/**
	 * Updates settings for the settings object.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return array|WP_Error Array on success, or error object on failure.
	 */
	public function update_item( $request ) {
		// Calls prepareRequestBookingRulesCustom() for custom booking rules
		$preparedRequest = $this->prepareRequest( $request );

		if ( is_wp_error( $preparedRequest ) ) {
			return $preparedRequest;
		}

		foreach ( $preparedRequest as $option => $value ) {
			if ( $option !== 'mphb_booking_rules_custom' ) {
				/*
				 * A null value for an option would have the same effect as
				 * deleting the option from the database, and relying on the
				 * default value.
				 */
				if ( is_null( $value ) ) {
					delete_option( $option );
				} else {
					update_option( $option, $value );
				}

			} else {
				MPHB()->getBlocksRepository()->deleteAll();
				MPHB()->getBlocksRepository()->insertItems( $value );
			}
		}

		return $this->get_item( $request );
	}

	/**
	 * Converts old rules to new blocks.
	 *
	 * @see AbstractRestOptionsController::prepareRequestItem()
	 */
	protected function prepareRequestBookingRulesCustom( array $rules ): array {
		return ValidateUtils::convertCustomBookingRulesToBlocks( $rules );
	}
}
