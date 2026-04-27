<?php

namespace MPHB\Core;

use MPHB\Entities\{ Rate, RecurrentSeason };
use MPHB\Utils\DateUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This facade must contain all methods for working with rates
 * and prices that are called from outside the core (from templates,
 * shortcodes, Gutenberg blocks, Ajax commands, REST API controllers,
 * other plugins and themes).
 */
class PricesCoreAPIFacade extends AbstractCoreAPIFacade {
	public function __construct() {
		parent::__construct();

		// Replace rate prices with custom amounts from mphb_custom_prices table
		add_filter(
			'mphb_get_rate_date_prices',
			fn( $datePrices, $rate ) => array_merge( $datePrices, $this->getCustomPricesForRate( $rate ) ),
			10,
			2
		);
	}

	protected function getHookNamesForClearAllCache(): array {
		return array(
			'save_post_' . MPHB()->postTypes()->room()->getPostType(),
			'save_post_' . MPHB()->postTypes()->roomType()->getPostType(),
			'save_post_' . MPHB()->postTypes()->rate()->getPostType(),
			'save_post_' . MPHB()->postTypes()->season()->getPostType(),
			'update_option_mphb_min_stay_length',
			'update_option_mphb_max_stay_length',
			'update_option_mphb_do_not_apply_booking_rules_for_admin',
		);
	}

	/**
	 * RATES SEARCH
	 */

	/**
	 * @param string $languageCode = 'current' or some language code.
	 *                               If empty then returns rate without translation.
	 * @return \MPHB\Entities\Rate|null
	 */
	public function getRateById( int $rateId, string $languageCode = '' ) {

		if ( ! empty( $languageCode ) ) {

			if ( 'current' === $languageCode ) {

				$languageCode = '';
			}

			$rateId = apply_filters( '_mphb_translate_post_id', $rateId );
		}

		$rate = MPHB()->getRateRepository()->findById( $rateId );

		return apply_filters( 'mphb_get_rate_by_id', $rate, $rateId, $languageCode );
	}

	/**
	 * @return array [ date (string Y-m-d), ... ]
	 */
	public function getDatesWithRatesByRoomTypeId( int $roomTypeOriginalId ) {

		$cacheDataId = 'getDatesWithRatesByRoomTypeId' . $roomTypeOriginalId;
		$result      = $this->getCachedData( $cacheDataId );

		if ( static::CACHED_DATA_NOT_FOUND === $result ) {

			$rates = $this->getActiveRatesByRoomTypeId( $roomTypeOriginalId );

			$result = array();

			foreach ( $rates as $rate ) {

				$result = array_merge( $result, array_keys( $rate->getDatePrices() ) );
			}

			$result = apply_filters( 'mphb_get_dates_rates_for_room_type', $result, $roomTypeOriginalId );

			$this->setCachedData( $cacheDataId, '', $result );
		}

		return $result;
	}

	/**
	 * @return \MPHB\Entities\Rate[]
	 */
	public function getActiveRatesByRoomTypeId( int $roomTypeOriginalId ) {

		$cacheDataId = 'getActiveRatesByRoomTypeId' . $roomTypeOriginalId;
		$result      = $this->getCachedData( $cacheDataId );

		if ( static::CACHED_DATA_NOT_FOUND === $result ) {

			$result = MPHB()->getRateRepository()->findAllActiveByRoomType( $roomTypeOriginalId );

			$result = apply_filters( 'mphb_get_room_type_active_rates', $result, $roomTypeOriginalId );

			$this->setCachedData( $cacheDataId, '', $result );
		}

		return $result;
	}

	/**
	 * @return \MPHB\Entities\Rate[]
	 */
	public function getActiveRates( int $roomTypeIdOnAnyLanguage, \DateTime $startDate, \DateTime $endDate, bool $isGetOnDefaultLanguage = true ) {

		$rateArgs = array(
			'check_in_date'  => $startDate,
			'check_out_date' => $endDate,
		);

		if ( $isGetOnDefaultLanguage ) {

			$rateArgs['mphb_language'] = 'original';
		}

		$rates = MPHB()->getRateRepository()->findAllActiveByRoomType(
			$roomTypeIdOnAnyLanguage,
			$rateArgs
		);

		return apply_filters( 'mphb_get_active_rates', $rates, $roomTypeIdOnAnyLanguage, $startDate, $endDate, $isGetOnDefaultLanguage );
	}

	/**
	 * @return int[]
	 */
	public function getAllRateIds( bool $isGetOnDefaultLanguage = true ): array {
		$rateArgs = array();

		if ( $isGetOnDefaultLanguage ) {
			$rateArgs['mphb_language'] = 'original';
		}

		$rateIds = MPHB()->getRateRepository()->findIds( $rateArgs );

		return apply_filters( 'mphb_get_all_rate_ids', $rateIds, $isGetOnDefaultLanguage );
	}

	public function isRoomTypeHasActiveRate( int $roomTypeIdOnAnyLanguage, \DateTime $startDate, \DateTime $endDate ): bool {

		$isRoomTypeHasActiveRate =  MPHB()->getRateRepository()->isExistsForRoomType(
			$roomTypeIdOnAnyLanguage,
			array(
				'check_in_date'  => $startDate,
				'check_out_date' => $endDate,
			)
		);

		return apply_filters( 'mphb_is_room_type_has_active_rates', $isRoomTypeHasActiveRate, $roomTypeIdOnAnyLanguage, $startDate, $endDate );
	}

	/**
	 * @return \MPHB\Entities\Rate
	 */
	public function duplicateRate( \MPHB\Entities\Rate $rate ) {
		return MPHB()->getRateRepository()->duplicate( $rate );
	}

	/**
	 * @param \MPHB\Entities\Rate $rate Pass the rate by reference, otherwise
	 *     you'll get <code>null</code> instead of ID when creating a rate via
	 *     the REST API. (See task [MPI-11948])
	 */
	public function saveRate( \MPHB\Entities\Rate &$rate ) {
		return MPHB()->getRateRepository()->save( $rate );
	}

	/**
	 * PRICES CALCULATION
	 */

	/**
	 * @return float room type minimal price for min days stay with taxes and fees
	 * @throws Exception if booking is not allowed for given date
	 */
	public function getRoomTypeMinBasePriceForDate( int $roomTypeOriginalId, \DateTime $startDate ) {

		return mphb_get_room_type_base_price( $roomTypeOriginalId, $startDate, $startDate );
	}

	/**
	 * @param array $atts {
	 *     @type bool   $as_html            True by default.
	 *     @type string $currency_position  "after"|"after_space"|"before"|"before_space"
	 *     @type string $currency_symbol
	 *     @type string $decimal_separator
	 *     @type int    $decimals           Number of decimals.
	 *     @type bool   $is_truncate_price  False by default.
	 *     @type bool   $literal_free       Use text "Free" instead of number 0.
	 *     @type bool   $period             Whether to show period or not.
	 *     @type int    $period_nights
	 *     @type string $period_title
	 *     @type string $thousand_separator
	 *     @type bool   $trim_zeros         True by default.
	 * }
	 */
	public function formatPrice( float $price, array $atts = array() ) {
		return PriceHelper::formatPrice( $price, $atts );
	}

	/**
	 * @param int|Rate $rate
	 * @return array <code>[ Date string ("Y-m-d") => Price (float) ]</code>
	 */
	private function getCustomPricesForRate( $rate ): array {
		$rateId = is_int( $rate ) ? $rate : $rate->getOriginalId();

		// Limit the max date to +1 year from the current date, just like
		// RecurrentSeason or GetRoomTypeCalendarData do
		$startDate = new \DateTime( 'today', DateUtils::getSiteTimeZone() );
		$endDate   = new \DateTime( '+1 year', DateUtils::getSiteTimeZone() );

		$customPrices = MPHB()->getCustomPricesRepository()->getPricesForPeriod( $rateId, $startDate, $endDate );

		return $customPrices;
	}
}
