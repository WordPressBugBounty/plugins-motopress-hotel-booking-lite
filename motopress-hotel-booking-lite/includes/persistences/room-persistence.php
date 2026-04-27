<?php

namespace MPHB\Persistences;

use MPHB\Core\BookingHelper;
use MPHB\Utils\DateUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RoomPersistence extends RoomTypeDependencedPersistence {

	/**
	 * @param array $customAtts Optional. Empty array by default.
	 * @return array
	 *
	 * @since 3.7.0 added optional parameter $customAtts.
	 */
	protected function getDefaultQueryAtts( $customAtts = array() ) {

		$atts = array_merge(
			array(
				'orderby' => 'menu_order',
				'order'   => 'ASC',
			),
			$customAtts
		);

		return parent::getDefaultQueryAtts( $atts );
	}

	/**
	 *
	 * @param array $atts
	 */
	protected function modifyQueryAtts( $atts ) {
		$atts = parent::modifyQueryAtts( $atts );
		if ( isset( $atts['post_status'] ) && $atts['post_status'] === 'all' ) {
			$atts['post_status'] = array(
				'publish',
				'pending',
				'draft',
				'future',
				'private',
			);
		}
		return $atts;
	}

	/**
	 * @param array     $atts Optional.
	 *     @param string    $atts['availability'] free|locked|booked|pending. 'free'
	 *            by default.
	 *            'free' - has no bookings with status complete or pending for this days.
	 *            'locked' - has bookings with status complete or pending for this days.
	 *            'booked' - has bookings with status complete for this days.
	 *            'pending' - has bookings with status pending for this days.
	 *     @param \DateTime $atts['from_date'] Today by default.
	 *     @param \DateTime $atts['to_date'] Tomorrow by default.
	 *     @param int       $atts['count'] The number of rooms to search. All by default.
	 *     @param int|int[] $atts['room_type_id'] Type of rooms to search. All
	 *         by default.
	 *     @param int|int[] $atts['exclude_bookings'] One or more booking IDs to
	 *         exclude from the search results.
	 *     @param int[]     $atts['exclude_rooms'] Room IDs to exclude from the
	 *             search results.
	 *     @param bool      $atts['skip_buffer_rules'] True by default.
	 *     @param int       $atts['exclude_booking'] Deprecated. Use "exclude_bookings"
	 *               instead.
	 * @return int[] Room IDs.
	 *
	 * @since 3.9
	 */
	public function searchRooms( $atts = array() ) {
		$defaults = array(
			'availability'      => 'free',
			'from_date'         => mphb_today(),
			'to_date'           => mphb_today( '+1 day' ),
			'count'             => 0, // Previously was null by default
			'room_type_id'      => 0, // Previously was null by default
			'exclude_bookings'  => array(),
			'exclude_rooms'     => array(),
			'skip_buffer_rules' => true, // Don't rewrite the old logic by default
		);

		// Get rid of deprecated parameters
		if ( isset( $atts['exclude_booking'] ) && ! isset( $atts['exclude_bookings'] ) ) {
			$atts['exclude_bookings'] = $atts['exclude_booking'];
		}

		/** @since 3.9 */
		$atts = apply_filters( 'mphb_search_rooms_atts', array_merge( $defaults, $atts ), $defaults );

		// Reset the "count" parameter if searching for free rooms - find all
		// locked rooms instead of min($count, %all%)
		$count = $atts['count'];

		if ( $atts['availability'] == 'free' ) {
			$atts['count'] = 0;
		}

		// Find locked rooms
		$ignoreBookingRules = MPHB()->settings()->main()->isBookingRulesForAdminDisabled();

		if ( $atts['skip_buffer_rules']
			|| ! mphb_availability_facade()->hasBufferDaysRules( $ignoreBookingRules )
		) {

			$roomIds = $this->findLockedRooms( $atts );

		} else {
			$roomIds     = array();
			$roomTypeIds = ! empty( $atts['room_type_id'] ) ? (array) $atts['room_type_id'] : mphb_get_room_type_ids( 'original' );

			// Search rooms for each room type separately (each room type may
			// have different buffer range)
			foreach ( $roomTypeIds as $roomTypeId ) {
				$modifiedAtts = $atts;

				// Force room type ID
				$modifiedAtts['room_type_id'] = $roomTypeId;

				// Expand searched period
				$bufferDays = mphb_availability_facade()->getBufferDaysCount(
					$roomTypeId,
					$atts['from_date'],
					$ignoreBookingRules
				);

				$maxBufferDays = mphb_availability_facade()->getMaxBufferDaysCount( $roomTypeId );

				if ( $maxBufferDays > $bufferDays ) {
					$modifiedAtts['lookup_range'] = BookingHelper::addBufferToCheckInAndCheckOutDates(
						$modifiedAtts['from_date'],
						$modifiedAtts['to_date'],
						$maxBufferDays
					);

				} elseif ( $bufferDays > 0 ) {
					list($fromDate, $toDate) = BookingHelper::addBufferToCheckInAndCheckOutDates(
						$atts['from_date'],
						$atts['to_date'],
						$bufferDays
					);

					$modifiedAtts['from_date'] = $fromDate;
					$modifiedAtts['to_date']   = $toDate;
				}

				// Find rooms
				$roomsPack = $this->findLockedRooms( $modifiedAtts );
				$roomIds   = array_merge( $roomIds, $roomsPack );
			}
		} // If search with buffer rules

		// Get the list of free room
		if ( $atts['availability'] == 'free' ) {
			// Restore the real count
			$atts['count'] = $count;

			// Find free rooms
			$roomIds = $this->findFreeRooms( $roomIds, $atts );
		}

		return $roomIds;
	}

	/**
	 * @since 3.9
	 * @since 6.0.0 added the <code>$lookup_range</code> attribute.
	 *
	 * @param array $atts {
	 *     @type string           $availability     "free"|"booked"|"pending"|"locked".
	 *     @type \DateTime        $from_date        Usually, this is a check-in date.
	 *     @type \DateTime        $to_date          Usually, this is a check-out date.
	 *     @type int|null         $count            Optional.
	 *     @type int|int[]|null   $room_type_id     Optional. 1 or more IDs.
	 *     @type int|int[]|null   $exclude_bookings Optional. 1 or more IDs.
	 *     @type \DateTime[]|null $lookup_range     Optional. A wider period for searching
	 *                                              for bookings with different buffer days.
	 * }
	 * @return int[] Room IDs.
	 *
	 * @global \wpdb $wpdb
	 */
	protected function findLockedRooms( $atts ) {
		global $wpdb;

		$dateFrom = $atts['from_date'];
		$dateTo   = $atts['to_date'];

		if ( isset( $atts['lookup_range'] ) ) {
			list( $lookupFrom, $lookupTo ) = $atts['lookup_range'];

			$isWiderLookup = true;
		} else {
			$isWiderLookup = false;
		}

		switch ( $atts['availability'] ) {
			// For "free" find locked rooms and then find all others (free)
			case 'free':
				$bookingStatuses = MPHB()->postTypes()->booking()->statuses()->getLockedRoomStatuses();
				break;
			case 'booked':
				$bookingStatuses = MPHB()->postTypes()->booking()->statuses()->getBookedRoomStatuses();
				break;
			case 'pending':
				$bookingStatuses = MPHB()->postTypes()->booking()->statuses()->getPendingRoomStatuses();
				break;
			case 'locked':
			default:
				$bookingStatuses = MPHB()->postTypes()->booking()->statuses()->getLockedRoomStatuses();
				break;
		}

		$bookingStatusesPlaceholder = array_fill( 0, count( $bookingStatuses ), '%s' );
		$bookingStatusesPlaceholder = implode( ', ', $bookingStatusesPlaceholder );

		// Build SQL
		$select = 'SELECT DISTINCT room_meta.meta_value AS room_id';

		$from = "FROM {$wpdb->posts} AS reserved_rooms"
			. " INNER JOIN {$wpdb->postmeta} AS room_meta"
				. ' ON room_meta.post_id = reserved_rooms.ID'
				. ' AND room_meta.meta_key = "_mphb_room_id"'
			. " INNER JOIN {$wpdb->posts} AS bookings"
				. ' ON bookings.ID = reserved_rooms.post_parent'
			. " INNER JOIN {$wpdb->postmeta} AS check_in_meta"
				. ' ON check_in_meta.post_id = bookings.ID'
				. ' AND check_in_meta.meta_key = "mphb_check_in_date"'
			. " INNER JOIN {$wpdb->postmeta} AS check_out_meta"
				. ' ON check_out_meta.post_id = bookings.ID'
				. ' AND check_out_meta.meta_key = "mphb_check_out_date"';

		$roomTypeJoinAdded = false;

		$where = 'WHERE reserved_rooms.post_type = %s'
			. ' AND reserved_rooms.post_status = "publish"'
			// Default dates overlap check
			. ' AND check_out_meta.meta_value > %s' // check_out_date > $dateFrom
			. ' AND check_in_meta.meta_value < %s'  // check_in_date  < $dateTo
			// Add booking statuses after dates, so we can easily change
			// $dateFrom and $dateTo in $placeholderData later
			. " AND bookings.post_status IN ({$bookingStatusesPlaceholder})";

		$placeholderData = array(
			MPHB()->postTypes()->reservedRoom()->getPostType(),
			DateUtils::formatDateDB( $dateFrom ),
			DateUtils::formatDateDB( $dateTo ),
			...$bookingStatuses,
		);

		$endSql = ''; // ORDER BY, LIMIT

		// Exclude bookings
		if ( ! empty( $atts['exclude_bookings'] ) ) {
			$bookingIds = (array) $atts['exclude_bookings'];

			$bookingIdsPlaceholder = array_fill( 0, count( $bookingIds ), '%d' );
			$bookingIdsPlaceholder = implode( ', ', $bookingIdsPlaceholder );

			$where .= " AND bookings.ID NOT IN ({$bookingIdsPlaceholder})";

			$placeholderData = array_merge( $placeholderData, $bookingIds );
		}

		// Limit by room type ID
		if ( ! empty( $atts['room_type_id'] ) ) {
			$roomTypeIds = (array) $atts['room_type_id'];

			$roomTypeIdsPlaceholder = array_fill( 0, count( $roomTypeIds ), '%d' );
			$roomTypeIdsPlaceholder = implode( ', ', $roomTypeIdsPlaceholder );

			$from .= " INNER JOIN {$wpdb->postmeta} AS room_type_meta"
				. ' ON room_type_meta.post_id = room_meta.meta_value'
				. ' AND room_type_meta.meta_key = "mphb_room_type_id"';

			$where .= " AND room_type_meta.meta_value IN ({$roomTypeIdsPlaceholder})";

			$roomTypeJoinAdded = true;
			$placeholderData = array_merge( $placeholderData, $roomTypeIds );
		}

		// Add LIMIT %d
		if ( ! empty( $atts['count'] ) ) {
			$endSql = ltrim( $endSql . ' LIMIT %d', ' ' );

			$placeholderData[] = absint( $atts['count'] );
		}

		// Query results
		if ( $isWiderLookup ) {
			// Do a wider lookup checking bookings with different buffer days
			$select = 'SELECT room_meta.meta_value AS room_id,'
				. ' room_type_meta.meta_value AS room_type_id,'
				. ' check_in_meta.meta_value AS check_in_date,'
				. ' check_out_meta.meta_value AS check_out_date';

			if ( ! $roomTypeJoinAdded ) {
				$from .= " INNER JOIN {$wpdb->postmeta} AS room_type_meta"
					. ' ON room_type_meta.post_id = room_meta.meta_value'
					. ' AND room_type_meta.meta_key = "mphb_room_type_id"';
			}

			$placeholderData[1] = DateUtils::formatDateDB( $lookupFrom );
			$placeholderData[2] = DateUtils::formatDateDB( $lookupTo );

			// Find rooms
			$sql = "{$select} {$from} {$where} {$endSql}";
			$sql = $wpdb->prepare( $sql, $placeholderData );

			$reservedRooms = $wpdb->get_results( $sql, ARRAY_A );

			$roomIds = array();
			$dateFormat = MPHB()->settings()->dateTime()->getDateTransferFormat();

			foreach ( $reservedRooms as $reservedRoom ) {
				$roomId       = absint( $reservedRoom['room_id'] );
				$roomTypeId   = absint( $reservedRoom['room_type_id'] );
				$checkInDate  = DateUtils::createCheckInDate( $dateFormat, $reservedRoom['check_in_date'] );
				$checkOutDate = DateUtils::createCheckOutDate( $dateFormat, $reservedRoom['check_out_date'] );

				$bufferDays = mphb_availability_facade()->getBufferDaysCount(
					$roomTypeId,
					$checkInDate,
					$isIgnoreBookingRules = false // Otherwise there would be no lookup dates
				);

				if ( $bufferDays > 0 ) {
					list( $checkInDate, $checkOutDate ) = BookingHelper::addBufferToCheckInAndCheckOutDates(
						$checkInDate,
						$checkOutDate,
						$bufferDays
					);
				}

				$periodsOverlap = DateUtils::isDatesOverlap(
					$dateFrom, $dateTo,
					$checkInDate, $checkOutDate
				);

				if ( $periodsOverlap ) {
					$roomIds[] = $roomId;
				}
			}

			// Remove duplicates and key gaps
			$roomIds = array_values( array_unique( $roomIds ) );

			return $roomIds;

		} else {
			// Find rooms
			$sql = "{$select} {$from} {$where} {$endSql}";
			$sql = $wpdb->prepare( $sql, $placeholderData );

			$roomIds = $wpdb->get_col( $sql ); // Have only DISTINCT room_id
			$roomIds = array_map( 'absint', $roomIds );

			return $roomIds;
		}
	}

	/**
	 * @param int[] $lockedRooms Results of findLockedRooms().
	 * @param array $atts
	 * @return int[]
	 *
	 * @since 3.9
	 */
	protected function findFreeRooms( $lockedRooms, $atts ) {
		$postAtts = array(
			'fields' => 'ids',
		);

		if ( ! empty( $lockedRooms ) ) {
			$postAtts['post__not_in'] = $lockedRooms;
		}

		if ( ! empty( $atts['exclude_rooms'] ) ) {
			if ( isset( $postAtts['post__not_in'] ) ) {
				$postAtts['post__not_in'] = array_merge( $postAtts['post__not_in'], $atts['exclude_rooms'] );
			} else {
				$postAtts['post__not_in'] = $atts['exclude_rooms'];
			}
		}

		if ( ! empty( $atts['room_type_id'] ) ) {
			$postAtts['room_type_id'] = $atts['room_type_id'];
		}

		if ( ! empty( $atts['count'] ) ) {
			$postAtts['posts_per_page'] = $atts['count'];
		}

		/** @since 3.9 */
		$postAtts = apply_filters( 'mphb_search_free_rooms_atts', $postAtts, $atts );

		$roomIds = $this->getPosts( $postAtts );

		return $roomIds;
	}

	/**
	 * @param \DateTime $checkInDate
	 * @param \DateTime $checkOutDate
	 * @param array     $atts Optional. Additional attributes for searchRooms().
	 * @param int       $atts['count']
	 * @param int|int[] $atts['room_type_id']
	 * @param bool      $atts['skip_buffer_rules'] True by default.
	 * @return bool
	 *
	 * @since 3.7.0 added new filter - "mphb_is_rooms_exist_query_atts".
	 * @since 3.9 arguments $count and $roomTypeId was replaced with $atts.
	 */
	public function isExistsRooms( \DateTime $checkInDate, \DateTime $checkOutDate, $atts = array() ) {
		$searchAtts = array_merge(
			array(
				'availability' => 'free',
				'from_date'    => $checkInDate,
				'to_date'      => $checkOutDate,
				'count'        => 1,
			),
			$atts
		);

		$searchAtts = apply_filters( 'mphb_is_rooms_exist_query_atts', $searchAtts );

		$rooms = $this->searchRooms( $searchAtts );

		return count( $rooms ) >= $searchAtts['count'];
	}

	/**
	 * @param \DateTime $checkInDate
	 * @param \DateTime $checkOutDate
	 * @param array     $rooms Rooms to check.
	 * @param array     $args Optional.
	 *     @param int       $args['room_type_id']
	 *     @param int|int[] $args['exclude_bookings']
	 * @return bool
	 *
	 * @since 3.7 added new filter - "mphb_is_rooms_free_query_atts".
	 * @since 3.8 parameter $roomTypeId was replaced with $args. Added new arguments: "room_type_id" and "exclude_bookings".
	 */
	public function isRoomsFree( \DateTime $checkInDate, \DateTime $checkOutDate, $rooms, $args = array() ) {
		$searchAtts = array(
			'availability' => 'free',
			'from_date'    => $checkInDate,
			'to_date'      => $checkOutDate,
		);

		if ( isset( $args['room_type_id'] ) ) {
			$searchAtts['room_type_id'] = (int) $args['room_type_id'];
		}

		if ( isset( $args['exclude_bookings'] ) ) {
			$searchAtts['exclude_bookings'] = $args['exclude_bookings'];
		}

		$searchAtts = apply_filters( 'mphb_is_rooms_free_query_atts', $searchAtts );

		$freeRooms      = $this->searchRooms( $searchAtts );
		$availableRooms = array_intersect( $rooms, $freeRooms );

		return ( count( $rooms ) == count( $availableRooms ) );
	}

	/**
	 *
	 * @param int $typeId
	 * @return int[]
	 */
	public function findAllIdsByType( $typeId ) {
		$allRoomIds = $this->getPosts(
			array(
				'room_type_id'   => $typeId,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
			)
		);

		return $allRoomIds;
	}

}
