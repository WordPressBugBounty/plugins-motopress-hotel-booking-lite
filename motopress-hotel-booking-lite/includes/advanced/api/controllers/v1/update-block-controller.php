<?php

declare(strict_types=1);

namespace MPHB\Advanced\Api\Controllers\V1;

use MPHB\Advanced\Api\Controllers\AbstractRestCommandController;
use MPHB\Advanced\Api\RestApiSchemaHelper;
use MPHB\UsersAndRoles\CapabilitiesAndRoles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Route: /booking_rules/blocks/{block_id} (POST/PUT/PATCH)
 */
class UpdateBlockController extends AbstractRestCommandController {
	public static function get_response_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'blocks',
			'type'       => 'object',
			'properties' => RestApiSchemaHelper::getBlockProperties(),
		);
	}

	public static function get_route(): string {
		return '/booking_rules/blocks/(?P<block_id>[\\d]+)';
	}

	public static function get_supported_methods(): string {
		return \WP_REST_Server::EDITABLE;
	}

	/**
	 * @return \WP_Error|bool
	 */
	public static function is_request_allowed( \WP_REST_Request $request ) {
		return current_user_can( CapabilitiesAndRoles::MANAGE_RULES );
	}

	protected static function get_request_schema(): array {
		return array(
			'block_id'      => array(
				'type'              => 'integer',
				'description'       => 'Unique identifier of the block.',
				'minimum'           => 1,
				'required'          => true,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'comment'       => array(
				'type' => 'string',
			),
			'date_from'     => array(
				'type'        => 'string',
				'description' => 'Date in WordPress timezone (YYYY-MM-DD) (inclusive)',
				'format'      => 'date',
			),
			'date_to'       => array(
				'type'        => 'string',
				'description' => 'Date in WordPress timezone (YYYY-MM-DD) (inclusive)',
				'format'      => 'date',
			),
			'not_check_in'  => array(
				'type' => 'boolean',
			),
			'not_check_out' => array(
				'type' => 'boolean',
			),
			'not_stay_in'   => array(
				'type' => 'boolean',
			),
			'room_id'       => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
			'room_type_id'  => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
		);
	}

	/**
	 * @return array Updated block data.
	 *
	 * @throws \Exception if the update failed.
	 */
	protected static function process_and_get_data_by_response_schema( \WP_REST_Request $request ) {
		$requestArgs = $request->get_params();

		$block = MPHB()->getBlocksRepository()->getItem( $requestArgs['block_id'] );

		if ( is_null( $block ) ) {
			throw new \Exception( esc_html__( 'Block not found.', 'motopress-hotel-booking' ) );
		}

		foreach ( $requestArgs as $field => $value ) {
			switch ( $field ) {
				case 'comment':
				case 'date_from':
				case 'date_to':
				case 'room_id':
				case 'room_type_id':
					$block[ $field ] = $value;
					break;

				case 'not_check_in':
				case 'not_check_out':
				case 'not_stay_in':
					// We'll update "has_restrictions" later
					$block[ $field ] = $value;
					break;
			}
		}

		$block['has_restrictions'] = $block['not_check_in']
			|| $block['not_check_out']
			|| $block['not_stay_in'];

		$isUpdated = MPHB()->getBlocksRepository()->updateItem( $block );

		if ( ! $isUpdated ) {
			throw new \Exception( esc_html__( 'A database error.', 'motopress-hotel-booking' ) );
		}

		return $block;
	}
}
