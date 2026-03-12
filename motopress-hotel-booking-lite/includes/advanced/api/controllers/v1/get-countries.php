<?php

namespace MPHB\Advanced\Api\Controllers\V1;

use MPHB\Advanced\Api\Controllers\AbstractRestCommandController,
	MPHB\Core\Country_Enum;

defined( 'ABSPATH' ) || exit;


class GetCountries extends AbstractRestCommandController {

	private const REQUEST_PARAMETER_LOCALE = 'locale';


	public static function get_route(): string {
		return '/countries';
	}

	public static function get_supported_methods(): string {
		return \WP_REST_Server::READABLE;
	}

	/**
	 * Check user permissions.
	 * @return \WP_Error|bool
	 */
	public static function is_request_allowed( \WP_REST_Request $request ) {
		return parent::is_request_allowed( $request );
	}

	/**
	 * https://developer.wordpress.org/rest-api/extending-the-rest-api/schema/
	 */
	protected static function get_request_schema(): array {
		return array(
			'locale' => array(
				'description'       => 'Locale code, example: en_US or en',
				'type'              => 'string',
				'maxLength'         => 5,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
		);
	}

	/**
	 * https://developer.wordpress.org/rest-api/extending-the-rest-api/schema/
	 */
	public static function get_response_schema(): array {
		return array(
			'$schema' => 'http://json-schema.org/draft-04/schema#',
			'type'    => 'object',
			'description' => 'List of countries keyed by ISO 3166-1 alpha-2 country codes (country code => country name, ...)',
			'patternProperties' => array(
				'^[A-Z]{2}$' => array( // country code as key, e.g. US, FR, UA
					'type' => 'string',
					'description' => 'Country name corresponding to the ISO 3166-1 alpha-2 code',
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * @return mixed|\WP_Error data or error if needed to send some additional error data
	 * @throws Exception when processing failed
	 */
	protected static function process_and_get_data_by_response_schema( \WP_REST_Request $request ) {

		$requestedLocale = $request->get_param( self::REQUEST_PARAMETER_LOCALE ) ?? get_locale();

		return Country_Enum::get_all_labels( $requestedLocale );
	}
}
