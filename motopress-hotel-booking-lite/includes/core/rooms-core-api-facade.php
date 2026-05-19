<?php

namespace MPHB\Core;

use MPHB\Core\BookingHelper;
use MPHB\Entities\{ Room, RoomType };

defined( 'ABSPATH' ) || exit;

/**
 * This facade must contain all methods for working with rooms and
 * accommodation types that are called from outside the core (from
 * templates, shortcodes, Gutenberg blocks, Ajax commands, REST API
 * controllers, other plugins and themes).
 */
class RoomsCoreAPIFacade extends AbstractCoreAPIFacade {

	protected function getHookNamesForClearAllCache(): array {
		return array(
			'save_post_' . MPHB()->postTypes()->room()->getPostType(),
			'save_post_' . MPHB()->postTypes()->roomType()->getPostType(),
		);
	}


	/**
	 * @return int[] ids of all room types on main language
	 */
	public function getAllRoomTypeOriginalIds() {

		$cacheDataId = 'getAllRoomTypeOriginalIds';
		$result      = $this->getCachedData( $cacheDataId );

		if ( static::CACHED_DATA_NOT_FOUND === $result ) {

			$result = MPHB()->getRoomTypePersistence()->getPosts(
				array(
					'mphb_language' => 'original',
				)
			);

			$this->setCachedData( $cacheDataId, '', $result );
		}

		return $result;
	}

	public function getRoomById( int $roomId, bool $ignoreCache = false ): ?Room {
		// Entities already cached by ID in the repository
		return MPHB()->getRoomRepository()->findById( $roomId, $ignoreCache );
	}

	public function getRoomTypeById( int $roomTypeId, bool $ignoreCache = false ): ?RoomType {
		// Entities already cached by ID in the repository
		return MPHB()->getRoomTypeRepository()->findById( $roomTypeId, $ignoreCache );
	}

	/**
	 * @return \MPHB\Entities\RoomType[]
	 */
	public function getAllRoomTypes(): array {
		return MPHB()->getRoomTypeRepository()->findAll();
	}

	/**
	 * @param int[]|string[] $accommodationTypeIdsOrSlugs
	 * @return Entities\RoomType[]
	 */
	public function findRoomTypesByIdsOrSlugs( array $accommodationTypeIdsOrSlugs ): array {
		return MPHB()->getRoomTypeRepository()->findAllByIdsOrSlugs( $accommodationTypeIdsOrSlugs );
	}

	/**
	 * @return int
	 */
	public function getActiveRoomsCountForRoomType( int $roomTypeOriginalId ) {

		$cacheDataId = 'getActiveRoomsCountForRoomType' . $roomTypeOriginalId;
		$result      = $this->getCachedData( $cacheDataId );

		if ( static::CACHED_DATA_NOT_FOUND === $result ) {

			$result = RoomAvailabilityHelper::getActiveRoomsCountForRoomType( $roomTypeOriginalId );

			$this->setCachedData( $cacheDataId, '', $result );
		}

		return $result;
	}

	/**
	 * @since 5.0.0
	 *
	 * @param int $roomTypeOriginalId
	 * @param Booking $booking
	 * @return \DateTime[] [ 0 => Modified check-in date, 1 => Modified check-out date ]
	 */
	public function addRoomTypeBufferToBookingDates( $roomTypeOriginalId, $booking ) {
		return $this->addRoomTypeBufferToDates(
			$roomTypeOriginalId,
			$booking->getCheckInDate(),
			$booking->getCheckOutDate()
		);
	}

	/**
	 * @since 5.0.0
	 *
	 * @param int $roomTypeOriginalId
	 * @param \DateTime $dateFrom
	 * @param \DateTime $dateTo
	 * @return \DateTime[] [ 0 => Buffer start date, 1 => Buffer end date ]
	 */
	public function addRoomTypeBufferToDates( $roomTypeOriginalId, $dateFrom, $dateTo ) {
		$bufferDaysCount = mphb_availability_facade()->getBufferDaysCount(
			$roomTypeOriginalId,
			$dateFrom,
			MPHB()->settings()->main()->isBookingRulesForAdminDisabled()
		);

		return BookingHelper::addBufferToCheckInAndCheckOutDates(
			$dateFrom,
			$dateTo,
			$bufferDaysCount
		);
	}

	/**
	 * @since 5.0.0
	 *
	 * @param RoomType $roomType
	 * @return array [ '', '' ] or [ 0 => adults_preset (int), 1 => children_preset (int) ].
	 */
	public function getRoomTypeOccupancyPresetsFromSearch( $roomType ) {
		$searchParams = MPHB()->searchParametersStorage()->get();

		$adultsPreset   = $searchParams['mphb_adults'];   // int|''
		$childrenPreset = $searchParams['mphb_children']; // int|''

		if ( $adultsPreset !== '' && $childrenPreset !== '' ) {
			// Convert to numbers, but don't exceed room type's adults/children capacity
			return $this->limitOccupancyByRoomType( (int) $adultsPreset, (int) $childrenPreset, $roomType );

		} else {
			// Return without changes
			return [ $adultsPreset, $childrenPreset ];
		}
	}

	/**
	 * @since 5.0.3
	 *
	 * @return int[] [ $adults, $children ]
	 */
	public function limitOccupancyByRoomType( int $adults, int $children, RoomType $roomType ): array {
		$adults   = min( $adults, $roomType->getMaxAdults() );
		$children = min( $children, $roomType->getMaxChildren( $adults ) );

		return [ $adults, $children ];
	}

	public function updateRoomType( RoomType $roomType ): void {
		MPHB()->getRoomTypeRepository()->save( $roomType );
	}

	/**
	 * @return int[]
	 */
	public function getTrashedRoomIds( int $roomTypeId = 0 ): array {
		if ( ! $roomTypeId ) {
			return MPHB()->getRoomPersistence()->getPosts(
				array(
					'fields'         => 'ids',
					'post_status'    => 'trash',
					'posts_per_page' => -1,
				)
			);
		} else {
			return MPHB()->getRoomPersistence()->getPosts(
				array(
					'fields'         => 'ids',
					'post_status'    => 'trash',
					'posts_per_page' => -1,
					'room_type_id'   => $roomTypeId,
				)
			);
		}
	}

	/**
	 * @return int[]
	 */
	public function getTrashedRoomTypeIds(): array {
		return MPHB()->getRoomTypePersistence()->getPosts(
			array(
				'fields'         => 'ids',
				'post_status'    => 'trash',
				'posts_per_page' => -1,
			)
		);
	}
}
