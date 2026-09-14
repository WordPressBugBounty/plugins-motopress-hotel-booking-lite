<?php

declare(strict_types=1);

namespace MPHB\GoogleHotels;

use MPHB\Utils\DateUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RoomTypeSnapshotHelper {
	public const SNAPSHOT_PERIOD_DAYS = 360; // 330 days in advance + 30 nights of stay

	public const SNAPSHOT_STATUS_PENDING        = 'pending';
	public const SNAPSHOT_STATUS_SENT           = 'sent';
	public const SNAPSHOT_STATUS_PENDING_DELETE = 'pending_delete';

	public const SNAPSHOT_TYPE_PRICES       = 'prices';
	public const SNAPSHOT_TYPE_AVAILABILITY = 'availability';

	private const TABLE_NAME = 'mphb_gh_room_type_snapshots';

	/**
	 * @param int|int[] $roomTypeIds
	 * @param string[]  $months Date strings in "Y-m-01" format.
	 */
	public static function availabilitySent( $roomTypeIds, array $months ): void {
		self::updateStatuses(
			$roomTypeIds,
			$months,
			self::SNAPSHOT_TYPE_AVAILABILITY,
			self::SNAPSHOT_STATUS_SENT
		);
	}

	public static function createSnapshotsForRoomType( int $roomTypeId ): void {
		list( $dateFrom, $dateTo ) = self::getActivePeriodDates();

		$months = DateUtils::getMonthsInPeriod( $dateFrom, $dateTo );

		self::pendAvailability( $roomTypeId, array_keys( $months ) );
		self::pendPrices( $roomTypeId, array_keys( $months ) );
	}

	/**
	 * @global \wpdb $wpdb
	 */
	public static function createTable(): void {
		global $wpdb;

		$snapshotsTable = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:disable WordPress.DB -- the table name is a class constant; every value goes through prepare().
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$snapshotsTable} ("
				. ' room_type_id BIGINT UNSIGNED NOT NULL,'
				. ' period_start DATE NOT NULL,'
				. ' snapshot_type VARCHAR(20) NOT NULL,'
				. ' status VARCHAR(20) NOT NULL DEFAULT "pending",'
				. ' PRIMARY KEY (room_type_id, period_start, snapshot_type)'
				. ') CHARSET=utf8 AUTO_INCREMENT=0'
		);
		// phpcs:enable WordPress.DB
	}

	/**
	 * @param int[] $roomTypeIds
	 *
	 * @global \wpdb $wpdb
	 */
	public static function deleteRoomTypes( array $roomTypeIds ): void {
		global $wpdb;

		if ( empty( $roomTypeIds ) ) {
			return;
		}

		$snapshotsTable = $wpdb->prefix . self::TABLE_NAME;

		$placeholders = array_fill( 0, count( $roomTypeIds ), '%d' );
		$placeholders = implode( ', ', $placeholders );

		// phpcs:disable WordPress.DB -- the table name is a class constant; every value goes through prepare().
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$snapshotsTable} WHERE `room_type_id` IN ({$placeholders})",
				$roomTypeIds
			)
		);
		// phpcs:enable WordPress.DB
	}

	/**
	 * @global \wpdb $wpdb
	 */
	public static function deleteSnapshotsEarlierThan( string $periodStart ): void {
		global $wpdb;

		$snapshotsTable = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:disable WordPress.DB -- the table name is a class constant; every value goes through prepare().
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$snapshotsTable} WHERE period_start < %s",
				$periodStart
			)
		);
		// phpcs:enable WordPress.DB
	}

	/**
	 * @return \DateTime[] <code>[$startDate, $endDate]</code>
	 */
	public static function getActivePeriodDates(): array {
		$startDate = new \DateTime( 'today', DateUtils::getSiteTimeZone() );
		$endDate   = DateUtils::cloneModify( $startDate, '+' . self::SNAPSHOT_PERIOD_DAYS . ' days' );

		return array( $startDate, $endDate );
	}

	/**
	 * @global \wpdb $wpdb
	 */
	public static function getLastMonth(): ?\DateTime {
		global $wpdb;

		$snapshotsTable = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:disable WordPress.DB -- the table name is a class constant; every value goes through prepare().
		/** @var string|null $maxMonth */
		$maxMonth = $wpdb->get_var( "SELECT MAX(`period_start`) FROM {$snapshotsTable}" );
		// phpcs:enable WordPress.DB

		if ( null !== $maxMonth ) {
			return new \DateTime( $maxMonth, DateUtils::getSiteTimeZone() );
		} else {
			return null;
		}
	}

	/**
	 * @return int[]
	 *
	 * @global \wpdb $wpdb
	 */
	public static function getRoomTypesToDelete(): array {
		global $wpdb;

		$snapshotsTable = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:disable WordPress.DB -- the table name is a class constant; every value goes through prepare().
		$roomTypeIds = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT `room_type_id` FROM {$snapshotsTable}"
					. ' WHERE `status` = %s ORDER BY `room_type_id` ASC',
				self::SNAPSHOT_STATUS_PENDING_DELETE
			)
		);
		// phpcs:enable WordPress.DB

		$roomTypeIds = array_map( 'absint', $roomTypeIds );

		return $roomTypeIds;
	}

	/**
	 * @param int $count 24 by default — 12 months multiplied by 2 (availability
	 *     and prices). However, real data may contain different types and months.
	 * @return array An array of <code>[room_type_id (integer), period_start (string),
	 *     snapshot_type (string), status ("pending")]</code>.
	 *
	 * @global \wpdb $wpdb
	 */
	public static function getPendingSnapshotsBatch( int $count = 24, int $offset = 0 ): array {
		global $wpdb;

		$snapshotsTable = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:disable WordPress.DB -- the table name is a class constant; every value goes through prepare().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$snapshotsTable} WHERE `status` = %s"
					. ' ORDER BY `room_type_id` ASC, `period_start` ASC LIMIT %d, %d',
				self::SNAPSHOT_STATUS_PENDING,
				$offset,
				$count
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB

		// Validate data
		array_walk(
			$rows,
			function ( &$row ) {
				$row['room_type_id'] = absint( $row['room_type_id'] );
			}
		);

		return $rows;
	}

	/**
	 * @global \wpdb $wpdb
	 */
	public static function haveRoomTypesToDelete(): bool {
		global $wpdb;

		$snapshotsTable = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:disable WordPress.DB -- the table name is a class constant; every value goes through prepare().
		$haveStuffToDelete = (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$snapshotsTable} WHERE `status` = %s LIMIT 1",
				self::SNAPSHOT_STATUS_PENDING_DELETE
			)
		);
		// phpcs:enable WordPress.DB

		return $haveStuffToDelete;
	}

	/**
	 * @global \wpdb $wpdb
	 */
	public static function markRoomTypeAsDeleted( int $roomTypeId ): void {
		global $wpdb;

		$snapshotsTable = $wpdb->prefix . self::TABLE_NAME;

		$wpdb->update(
			$snapshotsTable,
			array(
				'status' => self::SNAPSHOT_STATUS_PENDING_DELETE,
			),
			array(
				'room_type_id' => $roomTypeId,
			),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * @global \wpdb $wpdb
	 */
	public static function pendAllRemainingAvailability(): void {
		global $wpdb;

		$snapshotsTable = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:disable WordPress.DB -- the table name is a class constant; every value goes through prepare().
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$snapshotsTable} SET `status` = %s"
					. ' WHERE `snapshot_type` = %s AND `status` != %s',
				self::SNAPSHOT_STATUS_PENDING,
				self::SNAPSHOT_TYPE_AVAILABILITY,
				self::SNAPSHOT_STATUS_PENDING_DELETE
			)
		);
		// phpcs:enable WordPress.DB
	}

	/**
	 * @global \wpdb $wpdb
	 */
	public static function pendAllRemainingPrices(): void {
		global $wpdb;

		$snapshotsTable = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:disable WordPress.DB -- the table name is a class constant; every value goes through prepare().
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$snapshotsTable} SET `status` = %s"
					. ' WHERE `snapshot_type` = %s AND `status` != %s',
				self::SNAPSHOT_STATUS_PENDING,
				self::SNAPSHOT_TYPE_PRICES,
				self::SNAPSHOT_STATUS_PENDING_DELETE
			)
		);
		// phpcs:enable WordPress.DB
	}

	/**
	 * @param int|int[] $roomTypeIds
	 * @param string[]  $months Date strings in "Y-m-01" format.
	 */
	public static function pendAvailability( $roomTypeIds, array $months ): void {
		self::updateStatuses(
			$roomTypeIds,
			$months,
			self::SNAPSHOT_TYPE_AVAILABILITY,
			self::SNAPSHOT_STATUS_PENDING
		);
	}

	/**
	 * @param int|int[] $roomTypeIds
	 * @param string[]  $months Date strings in "Y-m-01" format.
	 */
	public static function pendPrices( $roomTypeIds, array $months ): void {
		self::updateStatuses(
			$roomTypeIds,
			$months,
			self::SNAPSHOT_TYPE_PRICES,
			self::SNAPSHOT_STATUS_PENDING
		);
	}

	/**
	 * @param int|int[] $roomTypeIds
	 * @param string[]  $months Date strings in "Y-m-01" format.
	 */
	public static function pricesSent( $roomTypeIds, array $months ): void {
		self::updateStatuses(
			$roomTypeIds,
			$months,
			self::SNAPSHOT_TYPE_PRICES,
			self::SNAPSHOT_STATUS_SENT
		);
	}

	/**
	 * @param int|int[] $roomTypeIds
	 * @param string[]  $months Date strings in "Y-m-01" format.
	 *
	 * @global \wpdb $wpdb
	 */
	private static function updateStatuses(
		$roomTypeIds,
		array $months,
		string $snapshotType,
		string $status
	): void {
		global $wpdb;

		if (
			( is_array( $roomTypeIds ) && empty( $roomTypeIds ) )
			|| empty( $months )
		) {
			return;
		}

		$placeholders = array();
		$values       = array();

		foreach ( (array) $roomTypeIds as $roomTypeId ) {
			foreach ( $months as $month ) {
				$placeholders[] = '(%d, %s, %s, %s)';
				$values         = array_merge( $values, array( $roomTypeId, $month, $snapshotType, $status ) );
			}
		}

		$snapshotsTable = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:disable WordPress.DB -- the table name is a class constant; every value goes through prepare().
		$wpdb->query(
			$wpdb->prepare(
				"REPLACE INTO {$snapshotsTable}"
					. ' (room_type_id, period_start, snapshot_type, status)'
					. ' VALUES ' . implode( ', ', $placeholders ),
				$values
			)
		);
		// phpcs:enable WordPress.DB
	}
}
