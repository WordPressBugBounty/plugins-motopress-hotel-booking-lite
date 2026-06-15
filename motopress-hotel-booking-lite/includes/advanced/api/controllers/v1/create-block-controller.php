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
 * Route: /booking_rules/blocks (POST/PUT/PATCH)
 */
class CreateBlockController extends AbstractRestCommandController {
	public static function get_response_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'blocks',
			'type'       => 'object',
			'properties' => RestApiSchemaHelper::getBlockProperties(),
		);
	}

	public static function get_route(): string {
		return '/booking_rules/blocks';
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
			'comment'       => array(
				'type' => 'string',
			),
			'date_from'     => array(
				'type'        => 'string',
				'description' => 'Date in WordPress timezone (YYYY-MM-DD) (inclusive)',
				'format'      => 'date',
				'required'    => true,
			),
			'date_to'       => array(
				'type'        => 'string',
				'description' => 'Date in WordPress timezone (YYYY-MM-DD) (inclusive)',
				'format'      => 'date',
				'required'    => true,
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
				'default' => 0,
			),
			'room_type_id'  => array(
				'type'    => 'integer',
				'minimum' => 0,
				'default' => 0,
			),
		);
	}

	/**
	 * @return array Inserted block data.
	 *
	 * @throws \Exception if the insertion failed.
	 */
	protected static function process_and_get_data_by_response_schema( \WP_REST_Request $request ) {
		$requestArgs = $request->get_params();

		$block = array(
			'block_id'         => 0,
			'comment'          => $requestArgs['comment'] ?? '',
			'date_from'        => $requestArgs['date_from'],
			'date_to'          => $requestArgs['date_to'],
			'has_restrictions' => false,
			'not_check_in'     => $requestArgs['not_check_in'] ?? false,
			'not_check_out'    => $requestArgs['not_check_out'] ?? false,
			'not_stay_in'      => $requestArgs['not_stay_in'] ?? false,
			'room_id'          => $requestArgs['room_id'],
			'room_type_id'     => $requestArgs['room_type_id'],
		);

		$block['has_restrictions'] = $block['not_check_in']
			|| $block['not_check_out']
			|| $block['not_stay_in'];

		$isInserted = (bool) MPHB()->getBlocksRepository()->insertItem( $block );

		if ( ! $isInserted ) {
			throw new \Exception( esc_html__( 'A database error.', 'motopress-hotel-booking' ) );
		}

		return $block;
	}
}
