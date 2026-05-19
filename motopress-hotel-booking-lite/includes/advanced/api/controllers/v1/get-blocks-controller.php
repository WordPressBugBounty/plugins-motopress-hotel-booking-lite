<?php

declare(strict_types=1);

namespace MPHB\Advanced\Api\Controllers\V1;

use MPHB\Advanced\Api\Controllers\AbstractRestCommandController;
use MPHB\Advanced\Api\RestApiSchemaHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Route: /booking_rules/blocks (GET)
 */
class GetBlocksController extends AbstractRestCommandController {
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
		return \WP_REST_Server::READABLE;
	}

	protected static function get_request_schema(): array {
		return array(
			'date_from'    => array(
				'type'              => 'string',
				'description'       => 'Date in WordPress timezone (YYYY-MM-DD) (inclusive)',
				'format'            => 'date',
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'date_to'      => array(
				'type'              => 'string',
				'description'       => 'Date in WordPress timezone (YYYY-MM-DD) (inclusive)',
				'format'            => 'date',
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'order'        => array(
				'type'              => 'string',
				'description'       => 'Order sort attribute ascending or descending.',
				'enum'              => array( 'asc', 'desc', 'ASC', 'DESC' ),
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'orderby'      => array(
				'type'              => 'string',
				'description'       => 'Sort collection by object attribute.',
				'enum'              => array( 'block_id', 'date', 'date_from', 'date_to', 'ID', 'room_id', 'room_type_id' ),
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'page'         => array(
				'type'              => 'integer',
				'description'       => 'Current page of the collection.',
				'minimum'           => 1,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'per_page'     => array(
				'description'       => 'Maximum number of items to be returned in result set.',
				'oneOf'             => array(
					array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					array(
						'type'    => 'integer',
						'minimum' => -1,
						'maximum' => -1,
					),
				),
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'restrictions' => array(
				'type'              => 'array',
				'items'             => array(
					'type' => 'string',
					'enum' => array( 'check-in', 'check-out', 'stay-in' ),
				),
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
		);
	}

	/**
	 * @return \WP_REST_Response Array of blocks with set headers for
	 *     pagination.
	 */
	protected static function process_and_get_data_by_response_schema( \WP_REST_Request $request ) {
		$requestArgs = $request->get_params();

		$queryArgs   = array();
		$queryParams = array( 'date_from', 'date_to', 'page', 'per_page', 'restrictions' );

		foreach ( $queryParams as $param ) {
			if ( isset( $requestArgs[ $param ] ) ) {
				$queryArgs[ $param ] = $requestArgs[ $param ];
			}
		}

		// Add order
		$order   = $requestArgs['order']   ?? null;
		$orderBy = $requestArgs['orderby'] ?? null;

		if ( $order !== null && $orderBy !== null ) {
			$queryArgs['orderby'] = array( $orderBy => $order );
		} elseif ( $order !== null ) {
			$queryArgs['orderby'] = array( 'block_id' => $order );
		} elseif ( $orderBy !== null ) {
			$queryArgs['orderby'] = array( $orderBy => 'ASC' );
		}

		$repository = MPHB()->getBlocksRepository();

		$blocks = $repository->queryItems( $queryArgs );

		// Get rid of keys
		$blocks = array_values( $blocks );

		// Add pagination headers
		$itemsTotal   = $repository->countItems( $queryArgs );
		$itemsPerPage = $requestArgs['per_page'] ?? $repository->getDefaultItemsPerPage();

		if ( $itemsPerPage === -1 ) {
			$pagesCount = 1;
		} else {
			$pagesCount = ceil( $itemsTotal / $itemsPerPage );
		}

		$response = rest_ensure_response( $blocks );

		$response->header( 'X-WP-Total', $itemsTotal );
		$response->header( 'X-WP-TotalPages', $pagesCount );

		return $response;
	}
}
