<?php

declare(strict_types=1);

namespace MPHB\Advanced\Api\Controllers\V1;

use MPHB\Advanced\Api\Controllers\AbstractRestCommandController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Route: /settings (GET)
 */
class GetSettingsController extends AbstractRestCommandController {
	public static function get_route(): string {
		return '/settings';
	}

	public static function get_supported_methods(): string {
		return \WP_REST_Server::READABLE;
	}

	/**
	 * @return \WP_Error|bool
	 */
	public static function is_request_allowed( \WP_REST_Request $request ) {
		return current_user_can( 'manage_options' );
	}

	protected static function get_request_schema(): array {
		return array();
	}

	/**
	 * https://developer.wordpress.org/rest-api/extending-the-rest-api/schema/
	 */
	public static function get_response_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'settings',
			'type'       => 'object',
			'properties' => array(
				'currency'                      => array(
					'type'        => 'string',
					'description' => 'Currency code, like "EUR", "USD" etc.',
				),
				'currency_position'             => array(
					'type' => 'string',
					'enum' => array( 'before', 'after', 'before_space', 'after_space' ),
				),
				'currency_symbol'               => array(
					'type' => 'string',
				),
				'default_admin_calendar_period' => array(
					'type' => 'string',
					'enum' => array( 'custom', 'quarter', 'month', 'year' ),
				),
			),
		);
	}

	/**
	 * @return array
	 */
	protected static function process_and_get_data_by_response_schema( \WP_REST_Request $request ) {
		$currencySettings = MPHB()->settings()->currency();
		$mainSettings     = MPHB()->settings()->main();

		$settings = apply_filters( 'mphb_rest_get_settings', array(
			'currency'                      => $currencySettings->getCurrencyCode(),
			'currency_position'             => $currencySettings->getCurrencyPosition(),
			'currency_symbol'               => $currencySettings->getCurrencySymbol(),
			'default_admin_calendar_period' => $mainSettings->getDefaultCalendarPeriod(),
		) );

		return $settings;
	}
}
