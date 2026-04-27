<?php

declare(strict_types=1);

namespace MPHB;

use MPHB\Entities\Booking;
use MPHB\Utils\{ BookingUtils, DateUtils };

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 4.10.0
 */
class LinkedRooms {
	/**
	 * @var array <code>[
	 *     room_type_id (int) => [
	 *         room_id (int) => linked_to_room_ids (int[]),
	 *         ...,
	 *         "all"         => all_linked_to_room_ids (int[]),
	 *         "links"       => [ linked_to_room_id (int) => room_ids (int[]) ],
	 *         "room_ids"    => int[]
	 *     ]
	 * ]</code>
	 */
	private static array $cachedLinkedRooms = array();

	public function __construct() {
		add_filter( 'mphb_has_not_stay_in_rules', array( $this, 'checkForLinkedBookings' ) );
		add_filter( 'mphb_get_booking_rules_for_date', array( $this, 'extendBookingRulesForDate' ), 10, 3 );
		add_filter( 'mphb_get_admin_blocks_for_export', array( $this, 'extendAdminBlocksForExport' ), 10, 3 );
		add_filter( 'mphb_get_calendar_comments_for_room_type', array( $this, 'extendBookingCalendarBlocks' ), 10, 4 );
	}

	/**
	 * @access private
	 *
	 * @see Core\BookingRulesData::__construct()
	 */
	public function checkForLinkedBookings( bool $hasNotStayInRules ): bool {
		return $hasNotStayInRules || static::hasBookedLinkedRooms();
	}

	/**
	 * @access private
	 *
	 * @see Core\BookingRulesData::getBookingRulesForDate()
	 *
	 * @return array Extended booking rules.
	 */
	public function extendBookingRulesForDate( array $bookingRules, int $roomTypeId, \DateTime $date ): array {
		$linkedRooms = static::getLinkedRoomsForRoomType( $roomTypeId );

		if ( empty( $linkedRooms['all'] ) ) {
			return $bookingRules;
		}

		$linkedBookings = static::findLinkedBookingsInPeriod( $roomTypeId, $date, $date );
		$linkedBlocks   = BookingUtils::convertAllToBlocks( $linkedBookings, $this->getBlockComment() );

		foreach ( $linkedBlocks as $block ) {
			$linkedRoomId = $block['room_id'];

			if ( ! in_array( $linkedRoomId, $linkedRooms['all'] ) ) {
				continue;
			}

			foreach ( $linkedRooms['links'][ $linkedRoomId ] as $roomId ) {
				$bookingRules['custom_rules_for_room_id'][ $roomId ]['not_stay_in'] = true;
				$bookingRules['custom_rules_for_room_id'][ $roomId ]['not_check_in'] ??= false;
				$bookingRules['custom_rules_for_room_id'][ $roomId ]['not_check_out'] ??= false;

				if ( empty( $bookingRules['custom_rules_for_room_id'][ $roomId ]['custom_rule_comment'] ) ) {
					$bookingRules['custom_rules_for_room_id'][ $roomId ]['custom_rule_comment'] = $block['comment'];
				} else {
					$bookingRules['custom_rules_for_room_id'][ $roomId ]['custom_rule_comment'] .= ', ' . $block['comment'];
				}
			}
		}

		return $bookingRules;
	}

	/**
	 * @access private
	 *
	 * @see Core\BookingRulesData::getNotStayInRulesData()
	 *
	 * @return array Array of <code>[
	 *     'roomTypeId' => int,
	 *     'roomId'     => int,
	 *     'startDate'  => DateTime,
	 *     'endDate'    => DateTime,
	 *     'comment'    => string
	 * ]</code>
	 */
	public function extendAdminBlocksForExport( array $adminBlocks, int $roomTypeId, int $roomId ): array {
		$linkedRoomIds = static::getLinkedRoomIds( $roomId );

		if ( empty( $linkedRoomIds ) ) {
			return $adminBlocks;
		}

		$linkedBookings = MPHB()->getBookingRepository()->findAll( array(
			'room_locked' => true,
			'rooms'       => $linkedRoomIds,
		) );

		$linkedBlocks = BookingUtils::convertAllToBlocks( $linkedBookings, $this->getBlockComment() );

		foreach ( $linkedBlocks as $block ) {
			if ( in_array( $block['room_id'], $linkedRoomIds ) ) {
				$adminBlocks[] = array(
					'roomTypeId' => $roomTypeId,
					'roomId'     => $roomId,
					'startDate'  => $block['date_from'],
					'endDate'    => $block['date_to'],
					'comment'    => $block['comment'],
				);
			}
		}

		return $adminBlocks;
	}

	/**
	 * @access private
	 *
	 * @see Core\BookingRulesData::getNotStayInComments()
	 *
	 * @param array $blockComments
	 * @param int[] $roomIds
	 * @param \DatePeriod $period
	 * @return array <code>[
	 *     room_id (int) => [
	 *         date (string, "Y-m-d") => "Comment 1, Comment 2, ..."
	 *     ]
	 * ]</code>
	 */
	public function extendBookingCalendarBlocks(
		array $blockComments,
		int $roomTypeId,
		array $roomIds,
		$period
	): array {
		$linkedRooms = self::getLinkedRoomsForRoomType( $roomTypeId );

		list( $dateFrom, $dateTo ) = DateUtils::getPeriodRangeDates( $period );

		$linkedBookings = static::findLinkedBookingsInPeriod( $roomTypeId, $dateFrom, $dateTo );
		$linkedBlocks   = BookingUtils::convertAllToBlocks( $linkedBookings, $this->getBlockComment() );

		foreach ( $linkedBlocks as $block ) {
			$linkedRoomId = $block['room_id'];
			$periodDates  = array_keys( DateUtils::getPeriodDates( $block['date_period'], true ) ); // 'Y-m-d'[]

			// Add this block to each room
			foreach ( $roomIds as $roomId ) {
				if ( ! in_array( $linkedRoomId, $linkedRooms[ $roomId ] ) ) {
					continue;
				}

				foreach ( $periodDates as $date ) {
					if ( empty( $blockComments[ $roomId ][ $date ] ) ) {
						$blockComments[ $roomId ][ $date ] = $block['comment'];
					} else {
						$blockComments[ $roomId ][ $date ] .= ', ' . $block['comment'];
					}
				}
			}
		}

		return $blockComments;
	}

	private function getBlockComment(): string {
		return __( 'Blocked because the linked accommodation is booked', 'motopress-hotel-booking' );
	}

	/**
	 * @global \wpdb $wpdb
	 */
	public static function hasBookedLinkedRooms(): bool {
		global $wpdb;

		$hasBookedLinkedRooms = (bool) $wpdb->get_var(
			"SELECT 1 FROM {$wpdb->posts} AS reserved_rooms"
				. " INNER JOIN `{$wpdb->postmeta}` AS postmeta"
					. ' ON postmeta.meta_value = reserved_rooms.ID'
				. ' WHERE postmeta.meta_key = "mphb_linked_room"'
		);

		return $hasBookedLinkedRooms;
	}

	/**
	 * @param \DateTime|string $dateFrom
	 * @param \DateTime|string $dateTo
	 * @return Booking[]
	 */
	public static function findLinkedBookingsInPeriod( int $roomTypeId, $dateFrom, $dateTo ): array {
		$linkedRooms = self::getLinkedRoomsForRoomType( $roomTypeId );

		return MPHB()->getBookingRepository()->findAllInPeriod( $dateFrom, $dateTo, array(
			'room_locked'         => true,
			'rooms'               => $linkedRooms['all'],
			'period_edge_overlap' => array(
				'check_in'  => true,
				'check_out' => false, // Check-outs will create +1 blocked day
			),
		) );
	}

	/**
	 * @return int[]
	 */
	public static function getLinkedRoomIds( int $roomId ): array {
		$roomTypeId  = MPHB()->getRoomRepository()->getRoomTypeId( $roomId );
		$linkedRooms = self::getLinkedRoomsForRoomType( $roomTypeId );

		return $linkedRooms[ $roomId ] ?? array();
	}

	/**
	 * @return int[]
	 */
	public static function getLinkedRoomIdsForRoomType( int $roomTypeId ): array {
		return self::getLinkedRoomsForRoomType( $roomTypeId )['all'];
	}

	private static function getLinkedRoomsForRoomType( int $roomTypeId ): array {
		if ( ! isset( self::$cachedLinkedRooms[ $roomTypeId ] ) ) {
			$roomIds = MPHB()->getRoomRepository()->findIds( array( 'room_type_id' => $roomTypeId ) );

			if ( ! empty( $roomIds ) ) {
				self::$cachedLinkedRooms[ $roomTypeId ] = MPHB()->getRoomRepository()->getLinkedRooms( $roomIds, 'full' );
			} else {
				self::$cachedLinkedRooms[ $roomTypeId ] = array(
					'all'      => array(),
					'links'    => array(),
					'room_ids' => array(),
				);
			}
		}

		return static::$cachedLinkedRooms[ $roomTypeId ];
	}
}
