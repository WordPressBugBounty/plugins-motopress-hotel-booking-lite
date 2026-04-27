<?php

declare(strict_types=1);

namespace MPHB\AjaxApi\AjaxActions;

use MPHB\AjaxApi\AbstractAjaxApiAction;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GetAccommodationList extends AbstractAjaxApiAction {
	public static function getAjaxActionNameWithouPrefix() {
		return 'get_accommodation_list';
	}

	protected static function doAction( array $requestData ) {
		$roomTypeId = $requestData[ 'room_type_id' ];

		$roomList = MPHB()->getRoomRepository()->getIdTitleListForRoomType( $roomTypeId );

		wp_send_json_success( $roomList, 200 );
	}

	/**
	 * @return array <code>[ Key (string) => Value (mixed) ]</code>
	 *
	 * @throws \Exception When validation of request parameters failed.
	 */
	protected static function getValidatedRequestData() {
		$requestData = parent::getValidatedRequestData();

		$roomTypeId = static::getIntegerFromRequest( 'room_type_id', $isRequired = true );
		$roomType = mphb_rooms_facade()->getRoomTypeById( $roomTypeId );

		if ( ! $roomType ) {
			throw new \Exception(
				sprintf(
					// Translators: %s: Accommodation type ID.
					esc_html__( 'Accommodation type %s not found.', 'motopress-hotel-booking' ),
					$roomTypeId
				)
			);
		}

		$requestData[ 'room_type_id' ] = $roomTypeId;

		return $requestData;
	}
}
