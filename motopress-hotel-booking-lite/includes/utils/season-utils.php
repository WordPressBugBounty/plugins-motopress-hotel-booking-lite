<?php

declare(strict_types=1);

namespace MPHB\Utils;

use MPHB\Entities\{ RecurrentSeason, Season };
use DateTime;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SeasonUtils {
	/**
	 * @return array<string, DateTime> Every date is the first day of the month.
	 */
	public static function getSeasonMonthsInPeriod(
		Season $season,
		DateTime $dateFrom,
		DateTime $dateTo
	): array {
		$periods = array();

		if ( $season instanceof RecurrentSeason ) {
			$periods = $season->getPeriods();
		} else {
			if ( ! is_null( $season->getStartDate() ) && ! is_null( $season->getEndDate() ) ) {
				$periods[] = array(
					'start_date' => $season->getStartDate(),
					'end_date'   => $season->getEndDate(),
				);
			}
		}

		if ( empty( $periods ) ) {
			return array();
		}

		$months = array();

		foreach ( $periods as list( 'start_date' => $periodStart, 'end_date' => $periodEnd ) ) {
			if ( ! DateUtils::isDatesOverlap( $dateFrom, $dateTo, $periodStart, $periodEnd ) ) {
				continue;
			}

			$date = clone max( $dateFrom, $periodStart );

			while ( $date <= $dateTo && $date <= $periodEnd ) {
				if ( $season->isDateInSeason( $date ) ) {
					$firstDayOfMonth = DateUtils::cloneModify( $date, 'first day of this month' );
					$monthStr = $firstDayOfMonth->format( 'Y-m-01' );

					if ( ! array_key_exists( $monthStr, $months ) ) {
						$months[ $monthStr ] = $firstDayOfMonth;
					}

					$date->modify( 'first day of next month' );
				} else {
					$date->modify( '+1 day' );
				}
			}
		}

		ksort( $months );

		return $months;
	}
}
