<?php

declare(strict_types=1);

namespace MPHB\Advanced\Api\Controllers\V1;

use MPHB\Advanced\Api\Controllers\AbstractRestCommandController;
use MPHB\Utils\DateUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Route: /rates/{id}/custom_prices (POST/PUT/PATCH)
 */
class SetRateCustomPricesController extends AbstractRestCommandController {
	public static function get_route(): string {
		return '/rates/(?P<id>[\\d]+)/custom_prices';
	}

	public static function get_supported_methods(): string {
		return \WP_REST_Server::EDITABLE;
	}

	/**
	 * @return \WP_Error|bool
	 */
	public static function is_request_allowed( \WP_REST_Request $request ) {
		// "edit_mphb_rates"
		$editRatesCap = 'edit_' . MPHB()->postTypes()->rate()->getCapabilityType()[1];

		return current_user_can( $editRatesCap );
	}

	/**
	 * @return \WP_Error|null Returns null if all parameters are valid.
	 */
	public static function validate_request( \WP_REST_Request $request ): ?\WP_Error {
		$error = parent::validate_request( $request );

		if ( $error !== null ) {
			return $error;
		}

		$rate = mphb_prices_facade()->getRateById( (int) $request->get_param( 'id' ) );

		if ( $rate === null ) {
			return new \WP_Error( '', 'Rate not found.' );
		}

		$startDate = $request->get_param( 'start_date' );
		$endDate   = $request->get_param( 'end_date' );

		if ( $startDate > $endDate ) {
			return new \WP_Error(
				'',
				sprintf(
					'Parameter start_date ("%s") can not be after end_date ("%s").',
					$startDate,
					$endDate
				)
			);
		}

		return null;
	}

	protected static function get_request_schema(): array {
		return array(
			'id' => array(
				'description'       => 'Unique identifier of the rate.',
				'type'              => 'integer',
				'minimum'           => 1,
				'required'          => true,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'start_date' => array(
				'description'       => 'Date in WordPress timezone (YYYY-MM-DD) (inclusive)',
				'type'              => 'string',
				'format'            => 'date',
				'required'          => true,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'end_date' => array(
				'description'       => 'Date in WordPress timezone (YYYY-MM-DD) (inclusive)',
				'type'              => 'string',
				'format'            => 'date',
				'required'          => true,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'price' => array(
				'description'       => 'Custom price to set.',
				'type'              => 'number',
				'minimum'           => 0.0,
				'required'          => true,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
		);
	}

	/**
	 * https://developer.wordpress.org/rest-api/extending-the-rest-api/schema/
	 */
	public static function get_response_schema(): array {
		return array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'component'            => 'custom_prices',
			'title'                => 'rates',
			'type'                 => 'object',
			'patternProperties'    => array(
				'^\d{4}-\d{2}-\d{2}$' => array(
					'type'    => 'number',
					'minimum' => 0.0,
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * @return array <code>[ Date string ("Y-m-d") => Price (float) ]</code>
	 */
	protected static function process_and_get_data_by_response_schema( \WP_REST_Request $request ) {
		$requestArgs = $request->get_params();

		list(
			'id'    => $rateId,
			'price' => $price,
		) = $requestArgs;

		$startDate = DateUtils::createDate( $requestArgs['start_date'] );
		$endDate   = DateUtils::createDate( $requestArgs['end_date'] );

		if ( $startDate === null ) {
			throw new \Exception( 'Failed to transform start_date into DateTime object.' );
		} elseif ( $endDate === null ) {
			throw new \Exception( 'Failed to transform end_date into DateTime object.' );
		}

		$dates  = DateUtils::createDatesInRange( $startDate, $endDate );
		$prices = MPHB()->getCustomPricesRepository()->addPrices( $rateId, array_keys( $dates ), $price );

		return $prices;
	}
}
