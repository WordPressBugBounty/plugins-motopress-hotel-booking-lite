<?php
/**
 * @package MPHB\Advanced\Api
 * @since 4.1.0
 */

namespace MPHB\Advanced\Api\Controllers\V1;

use MPHB\Advanced\Api\Controllers\AbstractRestObjectController;
use MPHB\Advanced\Api\Data\RateData;
use MPHB\Advanced\Api\ApiHelper;

class RatesController extends AbstractRestObjectController {


	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'mphb/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'rates';

	/**
	 * Post type.
	 *
	 * @var string
	 */
	protected $post_type = 'mphb_rate';

	/**
	 * Get a collection of posts.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function get_items( $request ) {
		$queryArgs = $this->prepareQuery( $request );

		// Find all IDs (including from filters)
		$allRateIds = mphb_prices_facade()->getAllRateIds();

		if ( ! empty( $queryArgs['post__in'] ) ) {
			$searchInRateIds = array_intersect( $allRateIds, $queryArgs['post__in'] );
		} elseif ( ! empty( $queryArgs['post__not_in'] ) ) {
			$searchInRateIds = array_diff( $allRateIds, $queryArgs['post__not_in'] );
		} else {
			$searchInRateIds = $allRateIds;
		}

		// Now apply search filters and restrictions
		$queryArgs['no_found_rows'] = true; // Already known
		$queryArgs['post__in']      = $searchInRateIds;

		if ( isset( $queryArgs['post__not_in'] ) ) {
			unset( $queryArgs['post__not_in'] );
		}

		/**
		 * Not all rates have the "mphb_rate" post type.
		 *
		 * @param string|string[] $postType
		 */
		$queryArgs['post_type'] = apply_filters( 'mphb_rate_query_post_type', $this->post_type );

		$query = new \WP_Query();

		$postIds = $query->query( $queryArgs );
		$posts   = array();

		foreach ( $postIds as $postId ) {
			if ( ! ApiHelper::checkPostPermissions( $this->post_type, 'read', $postId ) ) {
				continue;
			}

			$object = $this->data::findById( $postId );

			if ( is_null( $object ) ) {
				return new \WP_Error(
					"mphb_rest_invalid_{$this->post_type}_id",
					'Invalid ID.',
					array( 'status' => 404 )
				);
			}

			$data    = $this->prepare_item_for_response( $object, $request );
			$posts[] = $this->prepare_response_for_collection( $data );
		}

		$page       = (int) $queryArgs['paged'];
		$totalPosts = count( $allRateIds );
		$maxPages   = ceil( $totalPosts / (int) $queryArgs['posts_per_page'] );

		$response = rest_ensure_response( $posts );
		$response->header( 'X-WP-Total', (int) $totalPosts );
		$response->header( 'X-WP-TotalPages', (int) $maxPages );

		$requestParams = $request->get_query_params();

		if ( ! empty( $requestParams['filter'] ) ) {
			// Normalize the pagination params.
			unset( $requestParams['filter']['posts_per_page'] );
			unset( $requestParams['filter']['paged'] );
		}

		$base = add_query_arg( $requestParams, rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ) );

		if ( $page > 1 ) {
			$prevPage = $page - 1;

			if ( $prevPage > $maxPages ) {
				$prevPage = $maxPages;
			}

			$prevLink = add_query_arg( 'page', $prevPage, $base );

			$response->link_header( 'prev', $prevLink );
		}

		if ( $maxPages > $page ) {
			$nextPage = $page + 1;
			$nextLink = add_query_arg( 'page', $nextPage, $base );

			$response->link_header( 'next', $nextLink );
		}

		return $response;
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param  RateData         $rateData  Rate data object.
	 * @param  \WP_REST_Request $request  Request object.
	 *
	 * @return array Links for the given post.
	 */
	protected function prepare_links( $rateData, $request ) {
		$links = parent::prepare_links( $rateData, $request );

		$links['accommodation_type_id'] = array(
			'href'       => rest_url(
				sprintf(
					'/%s/%s/%d',
					$this->namespace,
					'accommodation_types',
					$rateData->accommodation_type_id
				)
			),
			'embeddable' => true,
		);

		$seasonIds = $rateData->getSeasonIds();
		if ( count( $seasonIds ) ) {
			$seasonIds = array_unique( $seasonIds );
			foreach ( $seasonIds as $seasonId ) {
				$links['season_id'][] = array(
					'href'       => rest_url( sprintf( '/%s/%s/%d', $this->namespace, 'seasons', $seasonId ) ),
					'embeddable' => true,
				);
			}
		}

		return $links;
	}
}
