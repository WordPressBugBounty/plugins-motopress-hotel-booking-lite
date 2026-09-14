<?php

declare(strict_types=1);

namespace MPHB\Utils;

use MPHB\Entities\Rate;
use DateTime;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RateUtils {
	/**
	 * @return array<string, DateTime> Every date is the first day of the month.
	 */
	public static function getRateMonthsInPeriod(
		Rate $rate,
		DateTime $dateFrom,
		DateTime $dateTo
	): array {
		$months = array();

		$date = clone $dateFrom;
		$datePrices = $rate->getDatePrices();

		while ( $date <= $dateTo ) {
			$dateStr = $date->format( 'Y-m-d' );

			if ( array_key_exists( $dateStr, $datePrices ) ) {
				$firstDayOfMonth = DateUtils::cloneModify( $date, 'first day of this month' );
				$monthStr = $firstDayOfMonth->format( 'Y-m-01' );

				$months[ $monthStr ] = $firstDayOfMonth;

				$date->modify( 'first day of next month' );
			} else {
				$date->modify( '+1 day' );
			}
		}

		ksort( $months );

		return $months;
	}
}
