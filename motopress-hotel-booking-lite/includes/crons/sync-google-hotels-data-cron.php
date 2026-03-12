<?php

namespace MPHB\Crons;

use MPHB\Advanced\Api\Controllers\V1\GetGoogleHotelsData;


class SyncGoogleHotelsDataCron extends AbstractCron {

	private const MOTOPRESS_SERVER_REST_URL = 'https://api.getmotopress.com/hotels/wp-json';

	private const OPTION_NAME_SYNC_GOOGLE_HOTELS_STATE = 'mphb_sync_google_hotels_state';
	private const OPTION_NAME_GOOGLE_HOTELS_SERVER_ID  = 'mphb_google_hotels_server_id';

	private const TRANSIENT_SYNC_GOOGLE_HOTELS_VERIFICATION_TOKEN = 'mphb_sync_google_hotels_verification_token';

	private const TRANSIENT_SYNC_GOOGLE_HOTELS_DATA_LOCK = 'mpghp_syncGoogleHotelsData_lock';
	private const SYNC_ATTEMPTS_DELAY_IN_SECONDS         = 300; // 5 minutes
	private const MAX_COUNT_OF_SYNC_ATTEMPTS             = 3;

	public const SYNC_STATE_KEY_SYNC_STATUS              = 'sync_status';
	public const SYNC_STATE_KEY_NEEDS_RESYNC             = 'needs_resync';
	public const SYNC_STATE_KEY_ATTEMPTS_COUNT           = 'attempts_count';
	public const SYNC_STATE_KEY_LAST_STATUS_FROM_SERVER  = 'last_server_status';
	public const SYNC_STATE_KEY_LAST_MESSAGE_FROM_SERVER = 'last_server_message';
	public const SYNC_STATE_KEY_LAST_ATTEMPT_TIMESTAMP   = 'last_try_timestamp';

	private const SYNC_STATUS_STARTED  = 'started';
	private const SYNC_STATUS_FAILED   = 'failed';
	private const SYNC_STATUS_FINISHED = 'finished';

	private const SERVER_STATUS_ERROR           = 'error';
	private const SERVER_STATUS_UPDATED         = 'updated';
	private const SERVER_STATUS_NOTHING_TO_SEND = 'nothing_to_send';


	public function __construct() {

		parent::__construct(
			'sync_google_hotels_data',
			CronManager::INTERVAL_WEEKLY
		);
	}

	public static function startNow(): void {

		$state = self::getSyncState();

		if ( self::isExecutingNow() ) {

			self::updateCronState(
				$state[ self::SYNC_STATE_KEY_SYNC_STATUS ],
				$state[ self::SYNC_STATE_KEY_ATTEMPTS_COUNT ],
				$state[ self::SYNC_STATE_KEY_LAST_STATUS_FROM_SERVER ],
				$state[ self::SYNC_STATE_KEY_LAST_MESSAGE_FROM_SERVER ],
				true
			);

			return;
		}

		$cron = new self();
		$cron->doCronJob();
	}

	public static function isDataSentToRemoteServer(): bool {
		return self::isFinished() && self::isServerStatusUpdated();
	}

	public static function getLastSyncAttemptDateTimeInWPTimezone(): ?\DateTime {

		$timestamp = self::getSyncState()[ self::SYNC_STATE_KEY_LAST_ATTEMPT_TIMESTAMP ];

		$last_attempt_datetime = null;

		if ( 0 < $timestamp ) {

			$last_attempt_datetime = new \DateTime( 'now', wp_timezone() );
			$last_attempt_datetime->setTimestamp( $timestamp );
		}

		return $last_attempt_datetime;
	}

	public static function getLastSyncAttemptMessage(): string {

		if ( self::isServerStatusNothingToSend() ||
			null === self::getLastSyncAttemptDateTimeInWPTimezone()
		) {
			return '';
		}

		$message = __( 'Google Hotels submission successfully sent for processing.', 'motopress-hotel-booking' );

		$messageFromServer = self::getLastMessageFromServer();

		if ( self::isFinished() ) {

			$message = empty( $messageFromServer ) ? $message : $messageFromServer;

		} elseif ( self::isFailed() ||
			(
				self::isStarted() &&
				! self::isExecutingNow()
			)
		) {

			$message = sprintf(
				// translators: %s - space + message from server (or empty)
				__( 'Google Hotels submission failed.%s To try again, please save your data on this page or wait for the next attempt.', 'motopress-hotel-booking' ),
				empty( $messageFromServer ) ? '' : ' ' . $messageFromServer
			);

		} elseif ( self::isStarted() && self::isExecutingNow() ) {

			$message = __( 'Your Google Hotels submission is being sent…', 'motopress-hotel-booking' );
		}

		if ( ! self::isExecutingNow() && ! self::isFinished() ) {

			$nextSyncDatetime = self::getNextSyncDateTimeInWPTimezone();

			if ( $nextSyncDatetime ) {

				$dateFormat = get_option( 'date_format', 'Y-m-d' );
				$timeFormat = get_option( 'time_format', 'H:i:s' );

				$formattedNextSync = $nextSyncDatetime->format( "$dateFormat $timeFormat" );

				$message .= ' ' . sprintf(
					// translators: %s date and time of the next data transfer
					__( 'The next data transfer is scheduled for %s.', 'motopress-hotel-booking' ),
					$formattedNextSync
				);
			}
		}

		return $message;
	}

	private static function getNextSyncDateTimeInWPTimezone(): ?\DateTime {

		$timestamp = wp_next_scheduled( self::ACTION_PREFIX . 'sync_google_hotels_data' );

		$next_execution_datetime = null;

		if ( false !== $timestamp ) {

			$next_execution_datetime = new \DateTime( 'now', wp_timezone() );
			$next_execution_datetime->setTimestamp( $timestamp );
		}

		return $next_execution_datetime;
	}

	private static function isStarted(): bool {
		return self::SYNC_STATUS_STARTED == self::getSyncState()[ self::SYNC_STATE_KEY_SYNC_STATUS ];
	}

	private static function isFailed(): bool {
		return self::SYNC_STATUS_FAILED == self::getSyncState()[ self::SYNC_STATE_KEY_SYNC_STATUS ];
	}

	private static function isFinished(): bool {
		return self::SYNC_STATUS_FINISHED == self::getSyncState()[ self::SYNC_STATE_KEY_SYNC_STATUS ];
	}

	private static function isServerStatusUpdated(): string {
		return self::SERVER_STATUS_UPDATED === self::getSyncState()[ self::SYNC_STATE_KEY_LAST_STATUS_FROM_SERVER ];
	}

	private static function isServerStatusError(): string {
		return self::SERVER_STATUS_ERROR === self::getSyncState()[ self::SYNC_STATE_KEY_LAST_STATUS_FROM_SERVER ];
	}

	private static function isServerStatusNothingToSend(): string {
		return self::SERVER_STATUS_NOTHING_TO_SEND === self::getSyncState()[ self::SYNC_STATE_KEY_LAST_STATUS_FROM_SERVER ];
	}

	private static function getLastMessageFromServer(): string {
		return self::getSyncState()[ self::SYNC_STATE_KEY_LAST_MESSAGE_FROM_SERVER ];
	}

	private static function isNeedsResync(): bool {
		return (bool) self::getSyncState()[ self::SYNC_STATE_KEY_NEEDS_RESYNC ];
	}

	private static function getSyncState(): array {
		return get_option(
			self::OPTION_NAME_SYNC_GOOGLE_HOTELS_STATE,
			array(
				self::SYNC_STATE_KEY_SYNC_STATUS    => self::SYNC_STATUS_FINISHED,
				self::SYNC_STATE_KEY_NEEDS_RESYNC   => false,
				self::SYNC_STATE_KEY_ATTEMPTS_COUNT => 0,
				self::SYNC_STATE_KEY_LAST_STATUS_FROM_SERVER => '',
				self::SYNC_STATE_KEY_LAST_MESSAGE_FROM_SERVER => '',
				self::SYNC_STATE_KEY_LAST_ATTEMPT_TIMESTAMP => 0,
			)
		);
	}

	private static function updateCronState(
		string $sync_status,
		int $attempts_count,
		string $last_status_from_server = '',
		string $last_message_from_server = '',
		bool $needs_resync = false
	): void {

		update_option(
			self::OPTION_NAME_SYNC_GOOGLE_HOTELS_STATE,
			array(
				self::SYNC_STATE_KEY_SYNC_STATUS    => $sync_status,
				self::SYNC_STATE_KEY_NEEDS_RESYNC   => $needs_resync,
				self::SYNC_STATE_KEY_ATTEMPTS_COUNT => $attempts_count,
				self::SYNC_STATE_KEY_LAST_STATUS_FROM_SERVER => $last_status_from_server,
				self::SYNC_STATE_KEY_LAST_MESSAGE_FROM_SERVER => $last_message_from_server,
				self::SYNC_STATE_KEY_LAST_ATTEMPT_TIMESTAMP => time(),
			)
		);
	}

	private static function startToExecute(): void {
		set_transient(
			self::TRANSIENT_SYNC_GOOGLE_HOTELS_DATA_LOCK,
			time(),
			self::SYNC_ATTEMPTS_DELAY_IN_SECONDS
		);
	}

	private static function isExecutingNow(): bool {
		// clear wp cache to make sure we get last transient value from database
		wp_cache_delete( self::TRANSIENT_SYNC_GOOGLE_HOTELS_DATA_LOCK, 'transient' );
		return false !== get_transient( self::TRANSIENT_SYNC_GOOGLE_HOTELS_DATA_LOCK );
	}

	private static function stopExecution(): void {
		delete_transient( self::TRANSIENT_SYNC_GOOGLE_HOTELS_DATA_LOCK );
	}

	/**
	 * @return array [ 'site_id' => int, 'secret' => string ]
	 */
	private static function getGoogleHotelsServerId(): array {
		return get_option(
			self::OPTION_NAME_GOOGLE_HOTELS_SERVER_ID,
			array(
				'site_id' => 0,
				'secret'  => '',
			)
		);
	}

	/**
	 * @return array [ 'site_id' => int, 'secret' => string ]
	 */
	private static function updateGoogleHotelsServerId( int $site_id, string $secret ): array {

		$googleHotelsServerId = array(
			'site_id' => $site_id,
			'secret'  => $secret,
		);

		update_option(
			self::OPTION_NAME_GOOGLE_HOTELS_SERVER_ID,
			$googleHotelsServerId
		);

		return $googleHotelsServerId;
	}

	public static function getVerificationToken(): ?string {

		// clear wp cache to make sure we get last transient value from database
		wp_cache_delete( self::TRANSIENT_SYNC_GOOGLE_HOTELS_VERIFICATION_TOKEN, 'transient' );
		$token = get_transient( self::TRANSIENT_SYNC_GOOGLE_HOTELS_VERIFICATION_TOKEN );

		return false !== $token ? $token : null;
	}

	private static function updateVerificationToken( string $verificationToken ): void {
		set_transient(
			self::TRANSIENT_SYNC_GOOGLE_HOTELS_VERIFICATION_TOKEN,
			$verificationToken,
			600 // 10 min as well as in Motopress\Google_Hotel_Prices_Server\REST_API\Site_Register
		);
	}

	public function doCronJob() {

		$state          = self::getSyncState();
		$attempts_count = $state[ self::SYNC_STATE_KEY_ATTEMPTS_COUNT ];

		if ( self::isExecutingNow() ) {
			return;
		}

		if ( self::SYNC_STATUS_STARTED === $state[ self::SYNC_STATE_KEY_SYNC_STATUS ] &&
			$attempts_count >= self::MAX_COUNT_OF_SYNC_ATTEMPTS
		) {
			self::updateCronState(
				self::SYNC_STATUS_FAILED,
				$state[ self::SYNC_STATE_KEY_ATTEMPTS_COUNT ],
				$state[ self::SYNC_STATE_KEY_LAST_STATUS_FROM_SERVER ],
				$state[ self::SYNC_STATE_KEY_LAST_MESSAGE_FROM_SERVER ],
				false
			);
			// try to finish next day
			$this->reScheduleAt( time() + DAY_IN_SECONDS );
			return;
		}

		self::startToExecute();

		$attempts_count = self::SYNC_STATUS_STARTED === $state[ self::SYNC_STATE_KEY_SYNC_STATUS ] ?
			$attempts_count + 1 :
			1;

		self::updateCronState( self::SYNC_STATUS_STARTED, $attempts_count );

		$isSyncFinished    = false;
		$statusFromServer  = '';
		$messageFromServer = '';

		try {

			$result = self::syncGoogleHotelsData();

			$statusFromServer  = $result['status'];
			$messageFromServer = $result['message'];
			$isSyncFinished    = true;

		} catch ( \Throwable $e ) {

			$statusFromServer  = self::SERVER_STATUS_ERROR;
			$messageFromServer = $e->getMessage();
			error_log( $e );

		} finally {

			self::stopExecution();
		}

		$isNeedsReSync = self::isNeedsResync();

		if ( $isSyncFinished ) {

			self::updateCronState(
				self::SYNC_STATUS_FINISHED,
				$attempts_count,
				$statusFromServer,
				$messageFromServer,
				false // clear resync flag because we will sync data in the next execution
			);

			$this->reScheduleAt(
				time() + ( $isNeedsReSync ? self::SYNC_ATTEMPTS_DELAY_IN_SECONDS : WEEK_IN_SECONDS )
			);

		} elseif ( $attempts_count >= self::MAX_COUNT_OF_SYNC_ATTEMPTS ) {

			self::updateCronState(
				self::SYNC_STATUS_FAILED,
				$attempts_count,
				$statusFromServer,
				$messageFromServer,
				false // clear resync flag because we will sync data in the next execution
			);

			$this->reScheduleAt(
				time() + ( $isNeedsReSync ? self::SYNC_ATTEMPTS_DELAY_IN_SECONDS : DAY_IN_SECONDS )
			);

		} else {

			self::updateCronState(
				self::SYNC_STATUS_STARTED,
				$attempts_count,
				$statusFromServer,
				$messageFromServer,
				false // clear resync flag because we will sync data in the next execution
			);

			$this->reScheduleAt( time() + self::SYNC_ATTEMPTS_DELAY_IN_SECONDS );
		}
	}

	/**
	 * @return array - [ status => string, message => string ]
	 * @throws \Exception if something goes wrong
	 */
	private function syncGoogleHotelsData(): array {

		$allRoomTypes             = mphb_rooms_facade()->getAllRoomTypes();
		$roomTypesForGoogleHotels = array();

		if ( MPHB()->settings()->main()->isGoogleHotelsIntegrationOn() ) {

			foreach ( $allRoomTypes as $roomType ) {

				$originalRoomType = $roomType->getOriginalRoomType();

				if ( $originalRoomType->isIncludeToGoogleHotels() ) {

					$propertyErrors = GetGoogleHotelsData::getPropertyDataErrors( $originalRoomType );
					if ( ! empty( $propertyErrors ) ) {
						return array(
							'status'  => self::SERVER_STATUS_ERROR,
							'message' => __( 'Cannot send the Google Hotels submission because it contains errors.', 'motopress-hotel-booking' ),
						);
					}

					$roomTypeErrors = GetGoogleHotelsData::getRoomTypeDataErrors( $originalRoomType );
					if ( ! empty( $roomTypeErrors ) ) {
						return array(
							'status'  => self::SERVER_STATUS_ERROR,
							'message' => __( 'Cannot send the Google Hotels submission because it contains errors.', 'motopress-hotel-booking' ),
						);
					}

					$roomTypesForGoogleHotels[ $originalRoomType->getId() ] = $originalRoomType;
				}
			}
		}

		$googleHotelsServerId = self::getGoogleHotelsServerId();

		if ( empty( $roomTypesForGoogleHotels ) &&
			empty( $googleHotelsServerId['site_id'] ) // do nothing if we did not send any data to the server
		) {
			return array(
				'status'  => self::SERVER_STATUS_NOTHING_TO_SEND,
				'message' => '',
			);
		}

		if ( empty( $googleHotelsServerId['site_id'] ) ||
			empty( $googleHotelsServerId['secret'] )
		) {

			$googleHotelsServerId = $this->registerSite();
		}

		// this route must start with /mpghp/v1 to match server route for signature generation
		$requestRoute     = '/mpghp/v1/sites/' . $googleHotelsServerId['site_id'];
		$requestTimestamp = time();
		$requestNonce     = \random_int( 1, PHP_INT_MAX );

		$requestBody = wp_json_encode(
			array(
				'site_url'              => home_url(),
				'rest_url'              => get_rest_url(),
				'is_plugin_pro_version' => ! defined( 'MPHB_IS_LITE' ) || ! MPHB_IS_LITE,
				'plugin_version'        => MPHB()->getVersion(),
				'admin_email'           => get_option( 'admin_email' ),
				'data_schema_version'   => '1.0.0',
				'properties'            => $this->getPropertiesData( $roomTypesForGoogleHotels ),
				'room_types'            => $this->getRoomTypesData( $roomTypesForGoogleHotels ),
			),
			JSON_UNESCAPED_SLASHES
		);

		if ( false === $requestBody ) {
			throw new \Exception( 'Encoding error in Google Hotels submission.' );
		}

		/**
		 * CRITICAL: Method, route and body MUST match server-side exactly
		 */
		$string_to_sign = implode(
			"\n",
			array(
				'POST',
				$requestRoute,
				$requestTimestamp,
				$requestNonce,
				$requestBody,
			)
		);

		$signature = hash_hmac(
			'sha256',
			$string_to_sign,
			$googleHotelsServerId['secret'],
			false // hex
		);

		$response = wp_remote_post(
			self::MOTOPRESS_SERVER_REST_URL . $requestRoute,
			array(
				'timeout'   => 20,
				'headers'   => array(
					'Content-Type'          => 'application/json',
					'X-MPHB-Site-Id'        => (string) $googleHotelsServerId['site_id'],
					'X-MPHB-Timestamp'      => (string) $requestTimestamp,
					'X-MPHB-Nonce'          => (string) $requestNonce,
					'X-MPHB-Site-Signature' => $signature,
				),
				'body'      => $requestBody,
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore
			throw new \Exception( $response->get_error_message() );
		}

		$responseBody = wp_remote_retrieve_body( $response );
		$responseData = json_decode( $responseBody, true );

		$responseStatus  = self::SERVER_STATUS_ERROR;
		$responseMessage = '';

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ||
			JSON_ERROR_NONE !== json_last_error()
		) {

			$responseMessage = (
				is_array( $responseData ) &&
				! empty( $responseData['message'] )
			) ?
				$responseData['message'] :
				'Google Hotels submission update failed.';

			if (
				isset( $responseData['data']['action'] ) &&
				'register' === $responseData['data']['action']
			) {
				self::updateGoogleHotelsServerId(
					$googleHotelsServerId['site_id'],
					'' // erase secret to start site registration again
				);
			}

			// phpcs:ignore
			throw new \Exception( $responseMessage );
		} else {
			$responseStatus  = $responseData['status'];
			$responseMessage = $responseData['message'] ?? '';
		}

		return array(
			'status'  => $responseStatus,
			'message' => $responseMessage,
		);
	}

	/**
	 * @param \MPHB\Entities\RoomType[]
	 * @throws \Exception if something goes wrong
	 */
	private function getPropertiesData( array $roomTypes ): array {

		$language       = get_locale();
		$propertiesData = array();

		foreach ( $roomTypes as $roomType ) {

			$propertiesData[ $roomType->getPropertyId() ] = array(
				'id'        => $roomType->getPropertyId(),
				'name'      => array(
					$language => $roomType->getPropertyTitle(),
				),
				'type'      => $roomType->getPropertyType(),
				'contacts'  => array(
					'main_phone' => $roomType->getContactsMainPhone(),
				),
				'latitude'  => $roomType->getLatitude(),
				'longitude' => $roomType->getLongitude(),
				'address'   => array(
					'line1'        => $roomType->getAddressLine1(),
					'line2'        => $roomType->getAddressLine2(),
					'city'         => $roomType->getAddressCity(),
					'province'     => $roomType->getAddressProvince(),
					'postal_code'  => $roomType->getAddressPostalCode(),
					'country_code' => $roomType->getAddressCountryCode(),
				),
			);

			if ( ! empty( $roomType->getPropertyCategory() ) ) {
				$propertiesData[ $roomType->getPropertyId() ]['category'] = $roomType->getPropertyCategory();
			}
		}

		// we need to send an array of properties in JSON
		// so we need to remove property ids from array keys
		return array_values( $propertiesData );
	}

	/**
	 * @param \MPHB\Entities\RoomType[]
	 * @throws \Exception if something goes wrong
	 */
	private function getRoomTypesData( array $roomTypes ): array {

		$language      = get_locale();
		$roomTypesData = array();

		foreach ( $roomTypes as $roomType ) {

			$roomTypeData = array(
				'id'          => $roomType->getId(),
				'property_id' => $roomType->getPropertyId(),
				'name'        => array(
					$language => trim( $roomType->getTitle() ),
				),
				'url'         => $roomType->getLink(),
				'capacity'    => $roomType->calcTotalCapacity(),
			);

			$roomtypeDescription = trim( $roomType->getExcerpt() );

			if ( ! empty( $roomtypeDescription ) ) {

				$roomTypeData['description'] = array(
					$language => trim( $roomType->getExcerpt() ),
				);
			}

			$featuredImageId  = $roomType->getFeaturedImageId();
			$featuredImageUrl = $featuredImageId ? wp_get_attachment_image_url( $featuredImageId ) : '';

			if ( ! empty( $featuredImageUrl ) ) {

				$featuredPhotoData = array(
					'url' => $featuredImageUrl,
				);

				$featuredImageCaption = $featuredImageId ? wp_get_attachment_caption( $featuredImageId ) : '';

				if ( ! empty( $featuredImageCaption ) ) {

					$featuredPhotoData['description'] = array(
						$language => $featuredImageCaption,
					);
				}

				$roomTypeData['photos'][] = $featuredPhotoData;
			}

			$roomTypesData[ $roomType->getId() ] = $roomTypeData;
		}

		// we need to send an array of properties in JSON
		// so we need to remove property ids from array keys
		return array_values( $roomTypesData );
	}

	/**
	 * @return array [ 'site_id' => int, 'secret' => string ]
	 * @throws \Exception if something goes wrong
	 */
	private function registerSite(): array {

		// get verification token from the server
		$client_proof_bin  = \random_bytes( 32 );
		$client_proof      = base64_encode( $client_proof_bin );
		$client_proof_hash = hash( 'sha256', $client_proof_bin, false ); // hex

		$registerResponse = wp_remote_post(
			self::MOTOPRESS_SERVER_REST_URL . '/mpghp/v1/sites/register',
			array(
				'timeout'   => 20,
				'headers'   => array(
					'Content-Type' => 'application/json',
					// add user agent because Cloudflare can forbif request otherwise
					'User-Agent'   => 'Motopress-Hotel-Booking/' . MPHB()->getVersion(),
				),
				'body'      => wp_json_encode(
					array(
						'site_url'          => home_url(),
						'rest_url'          => get_rest_url(),
						'client_proof_hash' => $client_proof_hash,
					)
				),
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $registerResponse ) ) {
			// phpcs:ignore
			throw new \Exception( $registerResponse->get_error_message() );
		}

		$registerResponseBody = wp_remote_retrieve_body( $registerResponse );
		$registerResponseData = json_decode( $registerResponseBody, true );

		if ( 200 !== wp_remote_retrieve_response_code( $registerResponse ) ||
			JSON_ERROR_NONE !== json_last_error() ||
			empty( $registerResponseData['verify_token'] )
		) {
			throw new \Exception(
				(
					is_array( $registerResponseData ) &&
					! empty( $registerResponseData['message'] )
				) ?
				// phpcs:ignore
				$registerResponseData['message'] :
				'Server did not return verification token.'
			);
		}

		self::updateVerificationToken( $registerResponseData['verify_token'] );

		// verify site and get sync secret
		$verifyResponse = wp_remote_post(
			self::MOTOPRESS_SERVER_REST_URL . '/mpghp/v1/sites/verify',
			array(
				'timeout'   => 20,
				'headers'   => array(
					'Content-Type' => 'application/json',
					// add user agent because Cloudflare can forbif request otherwise
					'User-Agent'   => 'Motopress-Hotel-Booking/' . MPHB()->getVersion(),
				),
				'body'      => wp_json_encode(
					array(
						'verification_token' => $registerResponseData['verify_token'],
						'client_proof'       => $client_proof,
					)
				),
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $verifyResponse ) ) {
			// phpcs:ignore
			throw new \Exception( $verifyResponse->get_error_message() );
		}

		$verifyResponseBody = wp_remote_retrieve_body( $verifyResponse );
		$verifyResponseData = json_decode( $verifyResponseBody, true );

		if ( 200 !== wp_remote_retrieve_response_code( $verifyResponse ) ||
			JSON_ERROR_NONE !== json_last_error() ||
			(
				empty( $verifyResponseData['site_id'] ) ||
				empty( $verifyResponseData['secret'] )
			)
		) {
			throw new \Exception(
				(
					is_array( $verifyResponseData ) &&
					! empty( $verifyResponseData['message'] )
				) ?
				// phpcs:ignore
				$verifyResponseData['message'] :
				'Server did not return site_id and secret.'
			);
		}

		$googleHotelsServerId = self::updateGoogleHotelsServerId(
			(int) $verifyResponseData['site_id'],
			$verifyResponseData['secret']
		);

		return $googleHotelsServerId;
	}
}
