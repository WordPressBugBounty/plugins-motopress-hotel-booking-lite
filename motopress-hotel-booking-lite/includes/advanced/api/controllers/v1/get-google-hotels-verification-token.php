<?php

namespace MPHB\Advanced\Api\Controllers\V1;

use MPHB\Advanced\Api\Controllers\AbstractRestCommandController,
	MPHB\Crons\SyncGoogleHotelsDataCron;

defined( 'ABSPATH' ) || exit;


class GetGoogleHotelsVerificationToken extends AbstractRestCommandController {


	public static function get_route(): string {
		return '/google-hotels-data/verification-token';
	}

	public static function get_supported_methods(): string {
		return \WP_REST_Server::READABLE;
	}

	/**
	 * Check user permissions.
	 *
	 * @return \WP_Error|bool
	 */
	public static function is_request_allowed( \WP_REST_Request $request ) {
		return true;
	}

	/**
	 * https://developer.wordpress.org/rest-api/extending-the-rest-api/schema/
	 */
	protected static function get_request_schema(): array {
		return array();
	}

	/**
	 * https://developer.wordpress.org/rest-api/extending-the-rest-api/schema/
	 */
	public static function get_response_schema(): array {
		return array();
	}

	protected static function is_responce_cachable(): bool {
		return false;
	}

	protected static function get_successful_response_content_type(): string {
		return 'text/plain';
	}

	/**
	 * @return mixed|\WP_Error data or error if needed to send some additional error data
	 * @throws Exception when processing failed
	 */
	protected static function process_and_get_data_by_response_schema( \WP_REST_Request $request ) {

		$token = SyncGoogleHotelsDataCron::getVerificationToken();

		return null !== $token ? $token : '';
	}
}
