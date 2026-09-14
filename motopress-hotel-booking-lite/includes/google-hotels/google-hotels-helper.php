<?php

declare(strict_types=1);

namespace MPHB\GoogleHotels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoogleHotelsHelper {

	public const SERVER_REST_URL = 'https://api.getmotopress.com/hotels/wp-json';
	public const SCHEMA_VERSION  = '1.0.2';

	private const OPTION_SITE_SERVER_CREDENTIAL     = 'mphb_google_hotels_server_id';
	private const TRANSIENT_SITE_VERIFICATION_TOKEN = 'mphb_gh_site_verification_token';

	/**
	 * @var int[]|null
	 */
	private static ?array $syncedRoomTypeIds = null;

	/**
	 * @return array {
	 *     @type int    $site_id
	 *     @type string $secret
	 * }
	 */
	public static function getSiteServerCredentials(): array {
		return get_option(
			self::OPTION_SITE_SERVER_CREDENTIAL,
			array(
				'site_id' => 0,
				'secret'  => '',
			)
		);
	}

	public static function getSiteServerId(): int {
		return self::getSiteServerCredentials()['site_id'];
	}

	public static function getSiteVerificationToken(): ?string {
		$token = get_transient( self::TRANSIENT_SITE_VERIFICATION_TOKEN );

		return false !== $token ? $token : null;
	}

	/**
	 * @return int[] Original (default-language) room type IDs included to Google
	 *     Hotels. The "mphb_language => original" query keeps the IDs original even
	 *     when WPML copied the include flag onto a translation (e.g. via Duplicate),
	 *     so every snapshot is keyed by the same ID the metadata sync uses.
	 */
	public static function getSyncedRoomTypeIds(): array {
		if ( is_null( self::$syncedRoomTypeIds ) ) {
			$postIds = MPHB()->getRoomTypePersistence()->getPosts(
				array(
					'mphb_language' => 'original',
					'fields'        => 'ids',
					'meta_key'      => 'mphb_gh_is_include_to_google_hotels',
					'meta_value'    => '1',
				)
			);

			self::$syncedRoomTypeIds = array_map( 'absint', $postIds );
		}

		return self::$syncedRoomTypeIds;
	}

	/**
	 * Unlike the <code>isIntegrationOn()</code> function, it only checks the
	 * option, ignoring the disabling filter.
	 */
	public static function isIntegrationEnabled(): bool {
		return (bool) get_option( 'mphb_google_hotels_integration_on', false );
	}

	public static function isIntegrationOn(): bool {
		return (bool) get_option( 'mphb_google_hotels_integration_on', false )
			&& apply_filters( 'mphb_use_google_hotels', true );
	}

	/**
	 * @return array {
	 *     @type int    $site_id
	 *     @type string $secret
	 * }
	 *
	 * @throws \Exception
	 */
	public static function registerSite(): array {
		// Get verification token from the server
		$clientProofBin  = \random_bytes( 32 );
		$clientProof     = base64_encode( $clientProofBin ); // phpcs:ignore
		$clientProofHash = hash( 'sha256', $clientProofBin, false ); // Hex

		$registerResponse = wp_remote_post(
			self::SERVER_REST_URL . '/mpghp/v1/sites/register',
			array(
				'body'      => wp_json_encode(
					array(
						'client_proof_hash' => $clientProofHash,
						'rest_url'          => get_rest_url(),
						'site_url'          => MPHB()->translation()->getDefaultHomeUrl(),
					)
				),
				'headers'   => array(
					'Content-Type' => 'application/json',
					// Add user agent because Cloudflare can forbif request otherwise
					'User-Agent'   => 'Motopress-Hotel-Booking/' . MPHB()->getVersion(),
				),
				'timeout'   => 20,
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $registerResponse ) ) {
			// phpcs:ignore
			throw new \Exception( $registerResponse->get_error_message() );
		}

		$responseBody = wp_remote_retrieve_body( $registerResponse );
		$responseData = json_decode( $responseBody, true );

		if (
			wp_remote_retrieve_response_code( $registerResponse ) !== 200
			|| json_last_error() !== JSON_ERROR_NONE
			|| empty( $responseData['verify_token'] )
		) {
			throw new \Exception(
				is_array( $responseData ) && ! empty( $responseData['message'] )
					? $responseData['message'] // phpcs:ignore
					: 'Server did not return a verification token.'
			);
		}

		self::saveSiteVerificationToken( $responseData['verify_token'] );

		// Verify site and get secret key
		$verifyResponse = wp_remote_post(
			self::SERVER_REST_URL . '/mpghp/v1/sites/verify',
			array(
				'body'      => wp_json_encode(
					array(
						'client_proof'       => $clientProof,
						'verification_token' => $responseData['verify_token'],
					)
				),
				'headers'   => array(
					'Content-Type' => 'application/json',
					// Add user agent because Cloudflare can forbif request otherwise
					'User-Agent'   => 'Motopress-Hotel-Booking/' . MPHB()->getVersion(),
				),
				'sslverify' => false,
				'timeout'   => 20,
			)
		);

		if ( is_wp_error( $verifyResponse ) ) {
			// phpcs:ignore
			throw new \Exception( $verifyResponse->get_error_message() );
		}

		$responseBody = wp_remote_retrieve_body( $verifyResponse );
		$responseData = json_decode( $responseBody, true );

		if (
			wp_remote_retrieve_response_code( $verifyResponse ) !== 200
			|| json_last_error() !== JSON_ERROR_NONE
			|| empty( $responseData['site_id'] )
			|| empty( $responseData['secret'] )
		) {
			throw new \Exception(
				is_array( $responseData ) && ! empty( $responseData['message'] )
					? $responseData['message'] // phpcs:ignore
					: 'Server did not return "site_id" or "secret".'
			);
		}

		$serverCredentials = array(
			'site_id' => (int) $responseData['site_id'],
			'secret'  => $responseData['secret'],
		);

		self::saveSiteServerCredentials(
			$serverCredentials['site_id'],
			$serverCredentials['secret']
		);

		return $serverCredentials;
	}

	public static function revokeSiteServerSecret(): void {
		self::saveSiteServerCredentials( self::getSiteServerId(), '' );
	}

	public static function toggleIntegration( bool $isEnabled ): void {
		$wasEnabled = (bool) get_option( 'mphb_google_hotels_integration_on', false );

		update_option( 'mphb_google_hotels_integration_on', $isEnabled, true );

		if ( $isEnabled !== $wasEnabled && apply_filters( 'mphb_use_google_hotels', true ) ) {
			if ( $isEnabled ) {
				do_action( 'mphb_enable_google_hotels' );
			} else {
				do_action( 'mphb_disable_google_hotels' );
			}
		}
	}

	private static function saveSiteServerCredentials( int $site_id, string $secret ): void {
		update_option(
			self::OPTION_SITE_SERVER_CREDENTIAL,
			array(
				'site_id' => $site_id,
				'secret'  => $secret,
			)
		);
	}

	private static function saveSiteVerificationToken( string $token ): void {
		set_transient(
			self::TRANSIENT_SITE_VERIFICATION_TOKEN,
			$token,
			600 // 10 min as well as in REST_API\Site_Register on the server
		);
	}
}
