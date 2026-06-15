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
 * Route: /booking_rules/blocks/{block_id} (DELETE)
 */
class DeleteBlockController extends AbstractRestCommandController {
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
		return \WP_REST_Server::DELETABLE;
	}

	/**
	 * @return \WP_Error|bool
	 */
	public static function is_request_allowed( \WP_REST_Request $request ) {
		return current_user_can( CapabilitiesAndRoles::MANAGE_RULES );
	}

	protected static function get_request_schema(): array {
		return array(
			'block_id' => array(
				'type'              => 'integer',
				'description'       => 'Unique identifier of the block.',
				'minimum'           => 1,
				'required'          => true,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
		);
	}

	/**
	 * @return array Deleted block data.
	 *
	 * @throws \Exception if the deletion failed or block does not exist.
	 */
	protected static function process_and_get_data_by_response_schema( \WP_REST_Request $request ) {
		$blockId = $request->get_param( 'block_id' );
		$block   = MPHB()->getBlocksRepository()->getItem( $blockId );

		if ( is_null( $block ) ) {
			throw new \Exception( esc_html__( 'Block not found.', 'motopress-hotel-booking' ) );
		}

		$isDeleted = MPHB()->getBlocksRepository()->deleteItem( $blockId );

		if ( ! $isDeleted ) {
			throw new \Exception( esc_html__( 'A database error.', 'motopress-hotel-booking' ) );
		}

		return $block;
	}
}
