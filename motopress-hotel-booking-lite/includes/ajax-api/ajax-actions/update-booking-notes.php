<?php

namespace MPHB\AjaxApi;

use MPHB\UsersAndRoles\CapabilitiesAndRoles;
use MPHB\Utils\ValidateUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 5.0.0
 */
class UpdateBookingNotes extends AbstractAjaxApiAction {

	const REQUEST_DATA_BOOKING_ID = 'booking_id';
	const REQUEST_DATA_NOTES = 'notes';

	public static function isActionForCurrentUser(): bool {
		return current_user_can( CapabilitiesAndRoles::EDIT_BOOKINGS );
	}

	public static function isActionForGuestUser() {
		return false;
	}

	public static function getAjaxActionNameWithouPrefix() {
		return 'update_booking_notes';
	}

	/**
	 * @return array [ request_key (string) => request_value (mixed) ]
	 * @throws \Exception when validation of request parameters failed
	 */
	protected static function getValidatedRequestData() {
		$requestData = parent::getValidatedRequestData();

		$bookingId = static::getIntegerFromRequest( static::REQUEST_DATA_BOOKING_ID, $isRequired = true );
		$booking   = mphb_bookings_facade()->findBookingById( $bookingId );

		if ( is_null( $booking ) ) {
			throw new \Exception( esc_html__( 'The booking not found.', 'motopress-hotel-booking' ) );
		}

		$requestData[ static::REQUEST_DATA_BOOKING_ID ] = $bookingId;

		// $isRequired = false to save [] when the list is empty, since jQuery.ajax() skips empty objects
		$requestData[ static::REQUEST_DATA_NOTES ] = static::getNotesFromRequest( static::REQUEST_DATA_NOTES );

		return $requestData;
	}

	/**
	 * @return array [
	 *     [
	 *         note => string,
	 *         date => int,
	 *         user => int,
	 *     ],
	 *     ...
	 * ]
	 * @throws \Exception when validation of request parameters failed
	 */
	protected static function getNotesFromRequest( string $requestDataName, bool $isRequired = false, array $defaultValue = array() ) {
		if ( ! isset( $_REQUEST[ $requestDataName ] ) || ! is_array( $_REQUEST[ $requestDataName ] ) ) {
			if ( $isRequired ) {
				throw new \Exception( esc_html__( 'Please complete all required fields and try again.', 'motopress-hotel-booking' ) );
			} else {
				return $defaultValue;
			}
		}

		$result = array();

		// phpcs:ignore
		foreach ( $_REQUEST[ $requestDataName ] as $note ) {

			$noteText   = isset( $note['note'] ) ? sanitize_text_field( wp_unslash( $note['note'] ) ) : '';
			$noteTime   = isset( $note['date'] ) ? (int) ValidateUtils::validateInt( $note['date'], 0 ) : 0;
			$noteUserId = isset( $note['user'] ) ? (int) ValidateUtils::validateInt( $note['user'], 0 ) : 0;

			if ( ! empty( $noteText ) && $noteTime != 0 && $noteUserId != 0 ) {
				$result[] = array(
					'note' => $noteText,
					'date' => $noteTime,
					'user' => $noteUserId,
				);
			}
		}

		return $result;
	}

	protected static function doAction( array $requestData ) {
		if ( ! current_user_can( CapabilitiesAndRoles::EDIT_BOOKINGS ) ) {
			throw new \Exception( esc_html__( 'Request does not pass security verification. Please refresh the page and try one more time.', 'motopress-hotel-booking' ) );
		}

		$bookingId = $requestData[ static::REQUEST_DATA_BOOKING_ID ];
		$notes = $requestData[ static::REQUEST_DATA_NOTES ];

		update_post_meta( $bookingId, '_mphb_booking_internal_notes', $notes );

		wp_send_json_success( array(), 200 );
	}
}
