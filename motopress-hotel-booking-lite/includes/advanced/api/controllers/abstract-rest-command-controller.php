<?php

namespace MPHB\Advanced\Api\Controllers;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract command based REST API controller.
 */
abstract class AbstractRestCommandController {

	private const REST_API_BASE_URL = 'mphb/v1';

	private static array $request_parameters_schema = array();


	public function register_routes() {

		register_rest_route(
			self::REST_API_BASE_URL,
			static::get_route(),
			array(
				array(
					'methods'             => static::get_supported_methods(),
					'permission_callback' => array( $this, 'is_request_allowed' ),
					'args'                => static::get_request_parameters_schema(),
					'callback'            => array( $this, 'process_request' ),
					'validate_callback'   => array( $this, 'validate_request' ),
				),
				'schema' => array( $this, 'get_response_schema' ),
			)
		);
	}

	/**
	 * @return string REST API route started with /
	 */
	abstract public static function get_route(): string;

	/**
	 * https://developer.wordpress.org/plugins/routes-endpoints/
	 *
	 * @return string can contains: GET, POST, PUT, DELETE, OPTIONS (comma separated)
	 * GET should be used for retrieving data from the API.
	 * POST should be used for creating new resources (i.e users, posts, taxonomies).
	 * PUT should be used for updating resources.
	 * DELETE should be used for deleting resources.
	 * OPTIONS should be used to provide context about our resources.
	 */
	abstract public static function get_supported_methods(): string;

	/**
	 * https://developer.wordpress.org/rest-api/extending-the-rest-api/schema/
	 */
	abstract protected static function get_request_schema(): array;

	/**
	 * https://developer.wordpress.org/rest-api/extending-the-rest-api/schema/
	 */
	abstract public static function get_response_schema(): array;


	final public static function get_request_parameters_schema(): array {

		// each child class has the same static field so we keep cashed schema by class
		$class_name = static::class;

		if ( ! isset( static::$request_parameters_schema[ $class_name ] ) ) {

			static::$request_parameters_schema[ $class_name ] = static::get_request_schema();
		}

		return static::$request_parameters_schema[ $class_name ];
	}

	/**
	 * Check user permissions.
	 * @return \WP_Error|bool
	 */
	public static function is_request_allowed( \WP_REST_Request $request ) {

		// check WP nonce instead of permissions
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! $nonce ) {

			$nonce = $request->get_param( '_wpnonce' );
		}

		return is_user_logged_in() || ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) );
	}

	protected static function get_successful_response_code(): int {
		return 200;
	}

	protected static function is_responce_cachable(): bool {
		return true;
	}

	protected static function get_successful_response_content_type(): string {
		return 'application/json';
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	final public static function process_request( \WP_REST_Request $request ) {

		try {

			$response_data = static::process_and_get_data_by_response_schema( $request );

			if ( is_wp_error( $response_data ) ) {
				return $response_data;
			}

			if ( !static::is_responce_cachable() ) {
				header( 'Cache-Control: no-store' );
			}

			header( 'Content-Type: ' . static::get_successful_response_content_type() );

			if ( 'text/plain' === static::get_successful_response_content_type() ||
				'text/html' === static::get_successful_response_content_type()
			) {
				// phpcs:ignore
				echo $response_data;
				exit();
			}

			$wp_rest_response = rest_ensure_response( $response_data );
			$wp_rest_response->set_status( static::get_successful_response_code() );

			return $wp_rest_response;

		} catch ( \Throwable $e ) {

			error_log( $e );
			return new \WP_Error(
				'rest_process_error',
				$e->getMessage(),
				array(
					'status' => ( 0 < $e->getCode() ? $e->getCode() : 500 ),
				)
			);
		}
	}

	/**
	 * @return \WP_Error|null - returns null if all parameters are valid.
	 */
	public static function validate_request( \WP_REST_Request $request ): ?\WP_Error {

		$attributes                   = $request->get_attributes();
		$missing_required_param_names = array();

		$args = empty( $attributes['args'] ) ? array() : $attributes['args'];

		// check required parameters first because it is cheaper check
		foreach ( $args as $param_name => $param_info ) {

			$param_value = $request->get_param( $param_name );

			if ( isset( $param_info['required'] ) &&
				true === $param_info['required'] &&
				null === $param_value
			) {
				$missing_required_param_names[] = $param_name;
			}
		}

		if ( ! empty( $missing_required_param_names ) ) {

			return new \WP_Error(
				'rest_missing_callback_param',
				sprintf( 'Missing parameter(s): %s', implode( ', ', $missing_required_param_names ) ),
				array(
					'status' => 400,
					'params' => $missing_required_param_names,
				)
			);
		}

		$invalid_params  = array();
		$invalid_details = array();

		foreach ( $args as $param_name => $schema_info ) {

			if ( $request->has_param( $param_name ) ) {

				$param_value = $request->get_param( $param_name );
				$valid_check = rest_validate_value_from_schema( $param_value, $schema_info, $param_name );

				if ( false === $valid_check ) {
					$invalid_params[ $param_name ] = 'Invalid parameter.';
				}

				if ( is_wp_error( $valid_check ) ) {
					$invalid_params[ $param_name ]  = implode( ' ', $valid_check->get_error_messages() );
					$invalid_details[ $param_name ] = rest_convert_error_to_response( $valid_check )->get_data();
				}
			}
		}

		if ( $invalid_params ) {

			return new \WP_Error(
				'rest_invalid_param',
				sprintf( 'Invalid parameter(s): %s', implode( ', ', array_keys( $invalid_params ) ) ),
				array(
					'status'  => 400,
					'params'  => $invalid_params,
					'details' => $invalid_details,
				)
			);
		}

		return null;
	}

	/**
	 * @return mixed|\WP_Error data or error if needed to send some additional error data
	 * @throws Exception when processing failed
	 */
	abstract protected static function process_and_get_data_by_response_schema( \WP_REST_Request $request );
}
