<?php

declare(strict_types=1);

namespace MPHB\Repositories;

use MPHB\Utils\DateUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CustomPricesRepository {
	private const TABLE_CUSTOM_PRICES = 'mphb_custom_prices';

	/**
	 * @global \wpdb $wpdb
	 */
	public function createTable(): void {
		global $wpdb;

		$wpdb->query(
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			"CREATE TABLE IF NOT EXISTS {$this->getTableName()} ("
				. ' rate_id INT UNSIGNED NOT NULL,'
				. ' `date` DATE NOT NULL,'
				. ' price DECIMAL(12, 2) NOT NULL,'
				. ' PRIMARY KEY (rate_id, `date`)'
				. ' ) CHARSET=utf8'
		);
	}

	/**
	 * @param string[] $dates
	 * @return array <code>[ Date string ("Y-m-d") => Price (float) ]</code>
	 *
	 * @global \wpdb $wpdb
	 */
	public function addPrices( int $rateId, array $dates, float $price ): array {
		global $wpdb;

		if ( empty( $dates ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL
		$sql = "REPLACE INTO {$this->getTableName()} (rate_id, `date`, price) VALUES";

		foreach ( $dates as $date ) {
			$sql .= $wpdb->prepare(
				' (%d, %s, %f),',
				$rateId,
				$date,
				$price
			);
		}

		$sql = rtrim( $sql, ',' );

		$wpdb->query( $sql );

		return array_fill_keys( $dates, $price );
	}

	/**
	 * @param string|\DateTime $dateFrom
	 * @param string|\DateTime $dateTo
	 *
	 * @global \wpdb $wpdb
	 */
	public function deletePricesInPeriod( int $rateId, $dateFrom, $dateTo ): void {
		global $wpdb;

		$dateFromStr = is_string( $dateFrom ) ? $dateFrom : DateUtils::formatDateDB( $dateFrom );
		$dateToStr   = is_string( $dateTo ) ? $dateTo : DateUtils::formatDateDB( $dateTo );

		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL
				"DELETE FROM {$this->getTableName()} WHERE rate_id = %d AND `date` BETWEEN %s AND %s",
				$rateId,
				$dateFromStr,
				$dateToStr
			)
		);
	}

	/**
	 * @param string|\DateTime $dateFrom
	 * @param string|\DateTime $dateTo
	 * @return array <code>[ Date string ("Y-m-d") => Price (float) ]</code>
	 *
	 * @global \wpdb $wpdb
	 */
	public function getPricesForPeriod( int $rateId, $dateFrom, $dateTo ): array {
		global $wpdb;

		$dateFromStr = is_string( $dateFrom ) ? $dateFrom : DateUtils::formatDateDB( $dateFrom );
		$dateToStr   = is_string( $dateTo ) ? $dateTo : DateUtils::formatDateDB( $dateTo );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL
				"SELECT `date`, price FROM {$this->getTableName()}"
					. ' WHERE rate_id = %d AND `date` BETWEEN %s AND %s',
				$rateId,
				$dateFromStr,
				$dateToStr
			),
			ARRAY_A
		);

		return $this->mapToPrices( $rows );
	}

	/**
	 * @global \wpdb $wpdb
	 */
	public function getTableName(): string {
		global $wpdb;

		return $wpdb->prefix . self::TABLE_CUSTOM_PRICES;
	}

	/**
	 * @global \wpdb $wpdb
	 */
	public function removePricesForRate( int $rateId ): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL
				"DELETE FROM {$this->getTableName()} WHERE rate_id = %d",
				$rateId
			)
		);
	}

	/**
	 * @param array $rows <code>[ date => Date string ("Y-m-d"), price => string ]</code>
	 * @return array <code>[ Date string ("Y-m-d") => Price (float) ]</code>
	 */
	private function mapToPrices( array $rows ): array {
		$dates = wp_list_pluck( $rows, 'date' );

		$prices = wp_list_pluck( $rows, 'price' );
		$prices = array_map( 'floatval', $prices );

		return array_combine( $dates, $prices );
	}
}
