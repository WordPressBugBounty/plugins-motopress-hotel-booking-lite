<?php

declare(strict_types=1);

namespace MPHB\Advanced\Api\Controllers\V1;

use MPHB\Advanced\Api\Controllers\AbstractRestCommandController;
use MPHB\Advanced\Api\RestApiSchemaHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Route: /booking_rules/blocks (DELETE)
 */
class DeleteBlocksController extends AbstractRestCommandController {
	public static function get_response_schema(): array {
		return array(
			'$schema' => 'http://json-schema.org/draft-04/schema#',
			'title'   => 'blocks',
			'type'    => 'array',
			'items'   => array(
				'type'       => 'object',
				'properties' => RestApiSchemaHelper::getBlockProperties(),
			),
		);
	}

	public static function get_route(): string {
		return '/booking_rules/blocks';
	}

	public static function get_supported_methods(): string {
		return \WP_REST_Server::DELETABLE;
	}

	protected static function get_request_schema(): array {
		return array(
			'block_ids' => array(
				'type'              => 'array',
				'description'       => 'Unique block identifiers.',
				'required'          => true,
				'items'             => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
		);
	}

	/**
	 * @return array Array of deleted blocks.
	 */
	protected static function process_and_get_data_by_response_schema( \WP_REST_Request $request ) {
		$blocks = MPHB()->getBlocksRepository()->queryItems(
			array(
				'include'  => $request->get_param( 'block_ids' ),
				'per_page' => -1,
			)
		);

		if ( ! empty( $blocks ) ) {
			$blockIds  = array_keys( $blocks );
			$isDeleted = MPHB()->getBlocksRepository()->deleteItems( $blockIds );

			if ( ! $isDeleted ) {
				throw new \Exception( esc_html__( 'A database error.', 'motopress-hotel-booking' ) );
			}
		}

		// Get rid of keys
		return array_values( $blocks );
	}
}
