<?php

declare(strict_types=1);

namespace MPHB\Utils;

use MPHB\Entities\Season;
use MPHB\PostTypes\SeasonCPT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SeasonDiff {
	private Season $season1;

	private Season $season2;

	public function __construct( Season $season1, Season $season2 ) {
		$this->season1 = $season1;
		$this->season2 = $season2;
	}

	public function haveAppliedDaysChanged(): bool {
		$daysDiff = mphb_array_disjunction( $this->season1->getDays(), $this->season2->getDays() );

		return ! empty( $daysDiff );
	}

	public function haveDatesChanged(): bool {
		return $this->hasPeriodChanged()
			|| $this->haveAppliedDaysChanged()
			|| $this->hasRepeatChanged();
	}

	public function hasPeriodChanged(): bool {
		$hasChanged = $this->season1->getStartDate() != $this->season2->getStartDate()
			|| $this->season1->getEndDate() != $this->season2->getEndDate();

		return $hasChanged;
	}

	public function hasRepeatChanged(): bool {
		if ( $this->season1->getRepeatPeriod() !== $this->season2->getRepeatPeriod() ) {
			return true;
		}

		if (
			$this->season1->getRepeatPeriod() !== SeasonCPT::REPEAT_PERIOD_NONE
			// Changes of "until date" does not matter if the repeat is "none"
			&& $this->season1->getRepeatUntilDate() != $this->season2->getRepeatUntilDate()
		) {
			return true;
		}

		return false;
	}

	public static function haveSeasonsChanged( Season $season1, Season $season2 ): bool {
		$diff = new static( $season1, $season2 );

		return $diff->haveDatesChanged();
	}
}
