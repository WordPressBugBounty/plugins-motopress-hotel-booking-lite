<?php

namespace MPHB\Entities;

use MPHB\Entities\Season;
use MPHB\Utils\DateUtils;

class SeasonPrice {

	/**
	 * In fact, it is an index in the array of the post meta "mphb_season_prices".
	 *
	 * @var int
	 */
	private $id;

	/**
	 * @var int
	 */
	private $seasonId;

	/**
	 * @since 5.0.3
	 *
	 * @var int
	 */
	private $roomTypeId = 0;

	/**
	 * @since 5.0.0
	 *
	 * @var int For example: [1, 7, 15, 30].
	 */
	private $periods;

	/**
	 * @since 5.0.0
	 *
	 * @var int
	 */
	private $baseAdults;

	/**
	 * @since 5.0.0
	 *
	 * @var int
	 */
	private $baseChildren;

	/**
	 * @var array
	 */
	private $stockPrices;

	/**
	 * @var float
	 */
	private $basePrice;

	/**
	 * @var array An array of prices where single price can be float number or
	 *     empty string ('').
	 */
	private $basePrices;

	/**
	 * @since 5.0.0
	 *
	 * @var array Array of float|''.
	 */
	private $extraAdultPrices;

	/**
	 * @since 5.0.0
	 *
	 * @var array Array of float|''.
	 */
	private $extraChildPrices;

	/**
	 *
	 * @var bool
	 */
	private $enableVariations;

	/**
	 *
	 * @var array
	 */
	private $variations;

	/**
	 * @param array $atts
	 *     @param int   $atts['id']
	 *     @param int   $atts['season_id']
	 *     @param array $atts['price']
	 */
	protected function __construct( $atts = array() ) {
		$this->id               = $atts['id'];
		$this->seasonId         = $atts['season_id'];
		$this->roomTypeId       = $atts['room_type_id'] ?? 0;
		$this->stockPrices      = $atts['price'];
		$this->periods          = $atts['price']['periods'];
		$this->baseAdults       = $atts['price']['base_adults'];
		$this->baseChildren     = $atts['price']['base_children'];
		$this->basePrice        = (float) reset( $atts['price']['prices'] );
		$this->basePrices       = $atts['price']['prices'];
		$this->extraAdultPrices = $atts['price']['extra_adult_prices'];
		$this->extraChildPrices = $atts['price']['extra_child_prices'];
		$this->enableVariations = $atts['price']['enable_variations'];
		$this->variations       = $atts['price']['variations'];
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return int
	 */
	public function getSeasonId() {
		return $this->seasonId;
	}

	/**
	 * @return Season|null
	 */
	public function getSeason() {
		return MPHB()->getSeasonRepository()->findById( $this->seasonId );
	}

	/**
	 * @since 5.0.0
	 *
	 * @return int
	 */
	public function getBaseAdults() {
		return $this->baseAdults;
	}

	/**
	 * @since 5.0.0
	 *
	 * @return int
	 */
	public function getBaseChildren() {
		return $this->baseChildren;
	}


	protected function getPriceForPeriodLite( $prices, $nightsCount, $defaultPrice ) {
		if ( ! empty( $prices ) && is_numeric( $prices[0] ) ) {
			return $prices[0];
		} else {
			return $defaultPrice;
		}
	}

	/**
	 * @since 5.0.0
	 *
	 * @param int|'' $adults
	 * @param int|'' $children
	 * @param int $nightsCount [-1; ∞)
	 * @return float
	 */
	protected function calcExtraPriceForGuests( $adults, $children, $nightsCount ) {
		$price = 0.0;

		$roomType = mphb_get_room_type( $this->roomTypeId );

		if ( ! is_null( $roomType ) ) {
			list( $adults, $children ) = mphb_rooms_facade()->limitOccupancyByRoomType(
				(int) $adults,
				(int) $children,
				$roomType
			);
		}

		if ( ! empty( $adults ) && MPHB()->settings()->main()->isAdultsAllowed() ) {
			$extraAdults = max( 0, $adults - $this->baseAdults );

			$extraPricePerAdult = $this->getPriceForPeriodLite( $this->extraAdultPrices, $nightsCount, 0 );

			$price += $extraAdults * $extraPricePerAdult;
		}

		if ( ! empty( $children ) && MPHB()->settings()->main()->isChildrenAllowed() ) {
			$extraChildren = max( 0, $children - $this->baseChildren );

			$extraPricePerChild = $this->getPriceForPeriodLite( $this->extraChildPrices, $nightsCount, 0 );

			$price += $extraChildren * $extraPricePerChild;
		}

		return $price;
	}

	/**
	 * @since 3.5.0 removed optional parameter $occupancyParams.
	 *
	 * @return float Base or variation price.
	 */
	public function getPrice() {
		$nightsCount = MPHB()->reservationRequest()->getNightsCount();

		$price = $this->basePrice;

		if ( MPHB()->reservationRequest()->getPricingStrategy() != 'base-price' ) {
			$adults   = MPHB()->reservationRequest()->getAdults();
			$children = MPHB()->reservationRequest()->getChildren();

			$price += $this->calcExtraPriceForGuests( $adults, $children, $nightsCount );

		}

		return $price;
	}

	/**
	 * @return array
	 */
	public function getPricesAndVariations() {
		return $this->stockPrices;
	}

	/**
	 * @since 3.5.0 removed optional parameter $occupancyParams.
	 *
	 * @return array
	 */
	public function getDatePrices() {
		$season = $this->getSeason();

		if ( ! $season ) {
			return array();
		}

		$dates = $season->getDates();
		$dates = array_map( array( DateUtils::class, 'formatDateDB' ), $dates );

		$price = $this->getPrice();

		return array_fill_keys( $dates, $price );
	}

	/**
	 * @param array $atts
	 * @param int   $atts['id']
	 * @param int   $atts['season_id']
	 * @param array $atts['price']
	 * @return self|null
	 */
	public static function create( $atts ) {

		if ( ! isset( $atts['id'], $atts['price'], $atts['season_id'] ) ) {
			return null;
		}

		$atts['id']        = (int) $atts['id'];
		$atts['season_id'] = (int) $atts['season_id'];
		$atts['price']     = mphb_normilize_season_price( $atts['price'] );

		if ( $atts['id'] < 0 ) {
			return null;
		}

		if ( count( $atts['price']['prices'] ) <= 0 || $atts['price']['prices'][0] < 0 ) {
			return null;
		}

		if ( ! MPHB()->getSeasonRepository()->findById( $atts['season_id'] ) ) {
			return null;
		}

		return new self( $atts );
	}

}
