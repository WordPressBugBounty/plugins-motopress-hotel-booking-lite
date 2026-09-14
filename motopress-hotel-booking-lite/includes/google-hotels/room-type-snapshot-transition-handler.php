<?php

declare(strict_types=1);

namespace MPHB\GoogleHotels;

use MPHB\Crons\SyncGoogleHotelsDataCron;
use MPHB\Entities\{ Booking, Rate, ReservedRoom, RoomType, Season };
use MPHB\Utils\{ DateUtils, RateUtils, SeasonDiff, SeasonUtils };
use MPHB\LinkedRooms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RoomTypeSnapshotTransitionHandler {
	private ?Rate $rateBeforeUpdate = null;

	private ?Season $seasonBeforeUpdate = null;

	/**
	 * @var array{adults: int, children: int}|null Room type capacity before the update.
	 */
	private ?array $roomTypeCapacityBeforeUpdate = null;

	private bool $areGuestSettingsHandled = false;

	/**
	 * Reservation rule types of the "Booking Rules" admin page. Every one of them is stored in
	 * its own option as a list of records, and a record scopes the rule with "room_type_ids"
	 * and "season_ids".
	 *
	 * @see \MPHB\Admin\MenuPages\BookingRulesMenuPage::saveReservationRules()
	 */
	private const RESERVATION_RULE_OPTIONS = array(
		'mphb_buffer_days',
		'mphb_check_in_days',
		'mphb_check_out_days',
		'mphb_max_advance_reservation',
		'mphb_max_stay_length',
		'mphb_min_advance_reservation',
		'mphb_min_stay_length',
	);

	public function __construct() {
		// Every callback below is a closure so that the handler methods stay private: this class
		// reacts to WordPress events, it does not offer them as an interface to anyone else.

		// INCLUDE/EXCLUDE ROOM TYPE (part 1)
		// In UpdateGoogleHotelsData REST controller Google Hotel Prices
		// integration is not always enabled when the "mphb_gh_*_room_type"
		// event is triggered
		add_action(
			'mphb_gh_include_room_type',
			function ( $roomType ) {
				$this->onRoomTypeInclude( $roomType );
			}
		);
		add_action(
			'mphb_gh_exclude_room_type',
			function ( $roomType ) {
				$this->onRoomTypeExclude( $roomType );
			}
		);

		// ENABLE INTEGRATION
		// Everything below is listened to only while the integration is on, so prices, bookings,
		// rules and taxes could all have changed unnoticed while it was off. The whole calendar
		// is re-sent instead of guessing what exactly was missed. Registered outside of the check
		// for the same reason as the events above: the option is still off when the handler is
		// built, and turns on later in the same request.
		add_action(
			'mphb_enable_google_hotels',
			function () {
				$this->afterIntegrationEnable();
			}
		);

		if ( GoogleHotelsHelper::isIntegrationEnabled() ) {
			// INCLUDE/EXCLUDE ROOM TYPE (part 2)
			add_action(
				'transition_post_status',
				function ( $newStatus, $oldStatus, $post ) {
					$this->onRoomTypeStatusChange( $newStatus, $oldStatus, $post );
				},
				10,
				3
			);

			// AVAILABILITY
			// Bookings
			add_action(
				'mphb_booking_submitted',
				function ( $booking ) {
					$this->afterBookingSubmit( $booking );
				}
			);
			add_action(
				'mphb_booking_edited',
				function ( $newBooking, $oldBooking ) {
					$this->afterBookingEdit( $newBooking, $oldBooking );
				},
				10,
				2
			);
			add_action(
				'mphb_booking_status_changed',
				function ( $booking, $oldStatus ) {
					$this->afterBookingStatusChange( $booking, $oldStatus );
				},
				10,
				2
			);
			add_action(
				'before_delete_post',
				function ( $postId, $post ) {
					$this->beforeBookingDelete( $postId, $post );
				},
				10,
				2
			);

			// Bookings imported from an iCal feed.
			//
			// The importer keeps its own "mphb_prevent_handle_booking_status_transition" filter on
			// while it inserts a booking, so Statuses::transitionStatus() returns early and
			// "mphb_booking_status_changed" never fires. These two hooks are the only place an
			// imported booking reports itself. Removing an outdated one needs no hook here: that
			// goes through wp_delete_post(), and by then the transition is no longer suppressed.
			add_action(
				'mphb_create_booking_via_ical',
				function ( $booking ) {
					$this->afterBookingSubmit( $booking );
				}
			);
			add_action(
				'mphb_update_booking_via_ical',
				function ( $newBooking, $oldBooking ) {
					$this->afterBookingEdit( $newBooking, $oldBooking );
				},
				10,
				2
			);

			// Booking rules (everything)
			$pendAvailabilityOnBookingRules = function () {
				$this->afterBookingRulesUpdate();
			};

			add_action( 'mphb_booking_rules_updated', $pendAvailabilityOnBookingRules );

			// "Export and import bookings with buffer time included" decides whether the buffer of
			// an imported booking is applied locally (BookingHelper::isBufferApplicableToBooking()),
			// so it changes "available" around every imported booking — the same blast radius as a
			// buffer rule.
			add_action( 'update_option_mphb_ical_sync_with_buffers', $pendAvailabilityOnBookingRules );

			// Reservation rules (admin)
			add_action(
				'mphb_reservation_rules_updated',
				function ( $newRules ) {
					$this->afterReservationRulesUpdate( $newRules );
				}
			);

			// Custom reservation rules
			add_action(
				'mphb_set_custom_booking_rules',
				function ( $bookingRules, $roomTypeId, $startDate, $endDate ) {
					$this->afterCustomReservationRulesUpdate( $bookingRules, $roomTypeId, $startDate, $endDate );
				},
				10,
				4
			);

			// Accommodations
			// Wait for RoomEditCPTPage::saveMetaBoxes() to save meta fields (save_post:10)
			add_action(
				'save_post',
				function ( $postId, $post, $isUpdate ) {
					$this->afterRoomSave( $postId, $post, $isUpdate );
				},
				11,
				3
			);
			add_action(
				'before_delete_post',
				function ( $postId, $post ) {
					$this->beforeRoomDelete( $postId, $post );
				},
				10,
				2
			);

			// Blocks
			add_action(
				'mphb_block_created',
				function ( $block ) {
					$this->afterBlockSave( $block );
				}
			);
			add_action(
				'mphb_block_updated',
				function ( $block ) {
					$this->afterBlockSave( $block );
				}
			);
			add_action(
				'mphb_block_deleted',
				function ( $block ) {
					$this->afterBlockDelete( $block );
				}
			);
			add_action(
				'mphb_blocks_deleted',
				function ( $blocks ) {
					$this->afterBlocksDelete( $blocks );
				}
			);

			// PRICES
			// Seasons
			add_action(
				'pre_post_update',
				function ( $postId ) {
					$this->beforeSeasonUpdate( $postId );
				}
			);
			// Wait for EditCPTPage::saveMetaBoxes() to save meta fields (save_post:10)
			add_action(
				'save_post',
				function ( $postId, $post, $isUpdate ) {
					$this->afterSeasonUpdate( $postId, $post, $isUpdate );
				},
				11,
				3
			);
			add_action(
				'before_delete_post',
				function ( $postId, $post ) {
					$this->beforeSeasonDelete( $postId, $post );
				},
				10,
				2
			);
			add_action(
				'transition_post_status',
				function ( $newStatus, $oldStatus, $post ) {
					$this->onSeasonStatusChange( $newStatus, $oldStatus, $post );
				},
				10,
				3
			);

			// Rates
			add_action(
				'pre_post_update',
				function ( $postId ) {
					$this->beforeRateUpdate( $postId );
				}
			);
			// Wait for RateEditCPTPage::saveMetaBoxes() to save meta fields (save_post:10)
			add_action(
				'save_post',
				function ( $postId, $post, $isUpdate ) {
					$this->afterRateUpdate( $postId, $post, $isUpdate );
				},
				11,
				3
			);

			// Custom prices
			$pendPricesOnCustomPrice = function ( $rateId, $dateFrom, $dateTo ) {
				$this->afterCustomPriceUpdate( $rateId, $dateFrom, $dateTo );
			};

			add_action( 'mphb_set_rate_custom_price', $pendPricesOnCustomPrice, 10, 3 );
			add_action( 'mphb_delete_rate_custom_price', $pendPricesOnCustomPrice, 10, 3 );

			// Prices imported by an add-on (PriceLabs)
			add_action(
				'mphb_external_prices_updated',
				function ( $roomTypeId, $dateFrom, $dateTo ) {
					$this->afterExternalPricesUpdate( $roomTypeId, $dateFrom, $dateTo );
				},
				10,
				3
			);

			// Taxes and fees
			add_action(
				'mphb_taxes_and_fees_updated',
				function () {
					$this->afterTaxesAndFeesUpdate();
				}
			);

			// GUEST SETTINGS (prices + room type metadata)
			$pendEverythingOnGuestSettings = function () {
				$this->afterGuestSettingsUpdate();
			};

			add_action( 'update_option_mphb_guest_management', $pendEverythingOnGuestSettings );
			add_action( 'update_option_mphb_search_max_adults', $pendEverythingOnGuestSettings );
			add_action( 'update_option_mphb_search_max_children', $pendEverythingOnGuestSettings );

			// CURRENCY (prices)
			add_action(
				'update_option_mphb_currency_symbol',
				function () {
					$this->afterCurrencyUpdate();
				}
			);

			// ROOM TYPE CAPACITY (room type metadata)
			add_action(
				'pre_post_update',
				function ( $postId ) {
					$this->beforeRoomTypeUpdate( $postId );
				}
			);
			// Wait for EditCPTPage::saveMetaBoxes() to save meta fields (save_post:10)
			add_action(
				'save_post',
				function ( $postId, $post, $isUpdate ) {
					$this->afterRoomTypeUpdate( $postId, $post, $isUpdate );
				},
				11,
				3
			);
		}
	}

	/**
	 * Marks all room types as "pending" within the blocked period.
	 */
	private function afterBlockDelete( array $block ): void {
		$this->pendAvailabilityByBlock( $block );
	}

	/**
	 * Fires once when a block is created or updated.
	 *
	 * Marks all room types as "pending" within the blocked period.
	 */
	private function afterBlockSave( array $block ): void {
		$this->pendAvailabilityByBlock( $block );
	}

	/**
	 * Marks all room types as "pending" within blocked periods.
	 */
	private function afterBlocksDelete( array $blocks ): void {
		foreach ( $blocks as $block ) {
			$this->pendAvailabilityByBlock( $block );
		}
	}

	/**
	 * Fires once an existing booking has been edited in the admin.
	 *
	 * Only the rooms and the dates move availability, so an edit of anything else (guests,
	 * customer, notes) is ignored. Both states are pended: the booking releases the dates it
	 * held and takes the new ones.
	 */
	private function afterBookingEdit( Booking $newBooking, Booking $oldBooking ): void {
		$roomsDiff    = mphb_array_disjunction( $newBooking->getRoomIds(), $oldBooking->getRoomIds() );
		$roomsChanged = ! empty( $roomsDiff );

		// Loose comparison on purpose: DateTime objects compare by value with "!=", while "!=="
		// would compare identity and report every edit as a date change.
		// phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
		$datesChanged = $newBooking->getCheckInDate() != $oldBooking->getCheckInDate()
			// phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
			|| $newBooking->getCheckOutDate() != $oldBooking->getCheckOutDate();

		if ( $roomsChanged || $datesChanged ) {
			$this->pendAvailabilityByBooking( $newBooking );
			$this->pendAvailabilityByBooking( $oldBooking );
		}
	}

	/**
	 * Fires once all booking rules has been updated via the REST request.
	 */
	private function afterBookingRulesUpdate(): void {
		// TODO: diff changes
		RoomTypeSnapshotHelper::pendAllRemainingAvailability();
	}

	/**
	 * Fires once the status of a booking has changed, trashing and restoring included.
	 *
	 * Availability moves only when the booking crosses the line between holding its rooms and
	 * releasing them — the exact set of statuses that lock a room is owned by
	 * Booking_Statuses::getLockedRoomStatuses(). A move within one side of that line (pending
	 * to abandoned, confirmed to checked-out) changes nothing.
	 *
	 * @param string $oldStatus "new" and "auto-draft" for a booking that is only being created.
	 */
	private function afterBookingStatusChange( Booking $booking, string $oldStatus ): void {
		if ( 'new' === $oldStatus || 'auto-draft' === $oldStatus ) {
			return; // Leave it to "mphb_booking_submitted"
		}

		$wasBlocking = in_array( $oldStatus, MPHB()->postTypes()->booking()->statuses()->getLockedRoomStatuses(), true );
		$nowBlocking = in_array( $booking->getStatus(), MPHB()->postTypes()->booking()->statuses()->getLockedRoomStatuses(), true );

		if ( $nowBlocking !== $wasBlocking ) {
			$this->pendAvailabilityByBooking( $booking );
		}
	}

	/**
	 * Fires once when the booking is submitted via public or admin checkout.
	 */
	private function afterBookingSubmit( Booking $booking ): void {
		$this->pendAvailabilityByBooking( $booking );
	}

	/**
	 * Fires right before a booking is deleted for good, bypassing the trash — that is what
	 * "DELETE /mphb/v1/bookings/{id}?force=true" does, and any direct wp_delete_post() call.
	 *
	 * A booking that still holds its rooms releases them here, and nothing else says so:
	 * "mphb_booking_status_changed" does not run on a delete. Deleting one that has already
	 * released them (trashed, cancelled) changes no availability — the transition into that
	 * status pended it back then.
	 */
	private function beforeBookingDelete( int $postId, \WP_Post $post ): void {
		if ( MPHB()->postTypes()->booking()->getPostType() !== $post->post_type
			|| ! in_array(
				$post->post_status,
				MPHB()->postTypes()->booking()->statuses()->getLockedRoomStatuses(),
				true
			)
		) {
			return;
		}

		$booking = MPHB()->getBookingRepository()->findById( $postId, $force = true );

		if ( ! is_null( $booking ) ) {
			$this->pendAvailabilityByBooking( $booking );
		}
	}

	/**
	 * Fires once when a custom price is added or deleted.
	 *
	 * Marks all room types as "pending" within a changing period.
	 */
	private function afterCustomPriceUpdate( int $rateId, \DateTime $dateFrom, \DateTime $dateTo ): void {
		$activePeriod = RoomTypeSnapshotHelper::getActivePeriodDates();

		$overlappingDates = DateUtils::getOverlappingDates(
			$dateFrom,
			$dateTo,
			$activePeriod[0],
			$activePeriod[1]
		);

		if ( false === $overlappingDates ) {
			return;
		}

		$rate = mphb_prices_facade()->getRateById( $rateId );

		if (
			is_null( $rate )
			|| ! $rate->isActive()
			|| ! in_array( $rate->getRoomTypeId(), GoogleHotelsHelper::getSyncedRoomTypeIds(), true )
		) {
			return;
		}

		$months = DateUtils::getMonthsInPeriod( $overlappingDates[0], $overlappingDates[1] );

		if ( ! empty( $months ) ) {
			RoomTypeSnapshotHelper::pendPrices( $rate->getRoomTypeId(), array_keys( $months ) );
		}
	}

	/**
	 * Fires when an add-on that keeps prices of its own has changed them over a period of dates.
	 *
	 * This exists for one add-on: Hotel Booking PriceLabs Integration. It imports prices from
	 * PriceLabs into a table of its own and substitutes the accommodation type's rates with a
	 * virtual one — "readonly" (Rate::isReadonly()) is the name of that substitution, not a
	 * general MPHB feature. None of it touches an MPHB entity, so nothing here notices an
	 * import: the price snapshots would stay "sent" and Google would go on quoting the price
	 * from before it, until some unrelated change happened to pend the same month.
	 *
	 * Keyed on the accommodation type rather than on a rate. The pend is per accommodation type
	 * anyway, and the PriceLabs rate is virtual — it has no post, so RateRepository::findById()
	 * returns null for it. It resolves only through the add-on's own "mphb_get_rate_by_id"
	 * filter, and depending on that would make this quietly stop working if the add-on changed it.
	 *
	 * @param int       $roomTypeId Original accommodation type ID.
	 * @param \DateTime $dateFrom First changed date.
	 * @param \DateTime $dateTo Last changed date, inclusive.
	 */
	private function afterExternalPricesUpdate( int $roomTypeId, \DateTime $dateFrom, \DateTime $dateTo ): void {
		if ( ! in_array( $roomTypeId, GoogleHotelsHelper::getSyncedRoomTypeIds(), true ) ) {
			return;
		}

		$activePeriod = RoomTypeSnapshotHelper::getActivePeriodDates();

		$overlappingDates = DateUtils::getOverlappingDates(
			$dateFrom,
			$dateTo,
			$activePeriod[0],
			$activePeriod[1]
		);

		if ( false === $overlappingDates ) {
			return;
		}

		$months = DateUtils::getMonthsInPeriod( $overlappingDates[0], $overlappingDates[1] );

		if ( ! empty( $months ) ) {
			RoomTypeSnapshotHelper::pendPrices( $roomTypeId, array_keys( $months ) );
		}
	}

	/**
	 * Fires once the custom booking rules of one accommodation type have been set for a period
	 * of dates via the REST request. Setting a rule to null deletes it, and that comes through
	 * the same action.
	 *
	 * @param array<string, array|null> $bookingRules Keys are dates in the "Y-m-d" format; a
	 *     null value means the rules of that date have been deleted.
	 * @param int                       $roomTypeId Original accommodation type ID, never 0.
	 * @param \DateTime                 $startDate First date of the changed period.
	 * @param \DateTime                 $endDate Last date of the changed period, inclusive.
	 */
	private function afterCustomReservationRulesUpdate(
		array $bookingRules,
		int $roomTypeId,
		\DateTime $startDate,
		\DateTime $endDate
	): void {
		if ( ! in_array( $roomTypeId, GoogleHotelsHelper::getSyncedRoomTypeIds(), true ) ) {
			return;
		}

		$activePeriod = RoomTypeSnapshotHelper::getActivePeriodDates();

		// A custom rule may carry "buffer_days", and the buffer of a booking that checks in
		// within the rule period closes nights outside of it. The exact reach depends on the
		// bookings themselves, so the widest buffer of the room type is used as the bound.
		$bufferDaysCount = self::getMaxBufferDaysCount( array( $roomTypeId ) );

		$overlappingDates = DateUtils::getOverlappingDates(
			DateUtils::cloneModify( $startDate, "-{$bufferDaysCount} days" ),
			DateUtils::cloneModify( $endDate, "+{$bufferDaysCount} days" ),
			$activePeriod[0],
			$activePeriod[1]
		);

		if ( false === $overlappingDates ) {
			return;
		}

		$months = DateUtils::getMonthsInPeriod( $overlappingDates[0], $overlappingDates[1] );

		if ( ! empty( $months ) ) {
			RoomTypeSnapshotHelper::pendAvailability( $roomTypeId, array_keys( $months ) );
		}
	}

	/**
	 * Fires once one of the guest settings has been saved on the "Settings" admin page.
	 *
	 * These three options are not booking rules and never reach Google as fields of their
	 * own, but both halves of what we send are built from them:
	 *
	 * - "mphb_guest_management" — the only one that changes PRICES. With "disable-children"
	 *   SyncGoogleHotelsCalendarCron drops the per-child surcharge and every rate variation
	 *   that adds children; with "disable-all" it keeps the base price alone, because MPHB
	 *   sells the room without a guest selection and charges no surcharges at all. It also
	 *   zeroes "max_children_count" in the room type metadata.
	 * - "mphb_search_max_adults" — caps "max_adults_count" in the room type metadata: a room
	 *   can not be sold for more adults than the search form lets a visitor pick, however
	 *   large its physical capacity is.
	 * - "mphb_search_max_children" — the same cap for "max_children_count".
	 *
	 * Both counts also feed "capacity", so a change here silently makes every occupancy the
	 * server offers wrong until the data is rebuilt. Nothing else pends those snapshots: the
	 * calendar cron sends pending ones only, and its daily renew touches just the month newly
	 * entering the active period, so months already inside it would keep the old prices for
	 * about a year.
	 *
	 * Availability carries none of these options — it is pended together with prices so a
	 * settings change rebuilds the whole calendar in one pass instead of half of it. These
	 * options are saved rarely, so the extra pass costs little. The metadata is not pended
	 * but pushed right away: its cron rebuilds every room type from scratch anyway, and on
	 * its own schedule that would take up to a week.
	 *
	 * Fires once per request even when several of the options are saved together.
	 */
	private function afterGuestSettingsUpdate(): void {
		if ( $this->areGuestSettingsHandled ) {
			return;
		}

		$this->areGuestSettingsHandled = true;

		RoomTypeSnapshotHelper::pendAllRemainingPrices();
		RoomTypeSnapshotHelper::pendAllRemainingAvailability();

		SyncGoogleHotelsDataCron::startNow();
	}

	/**
	 * Fires once the site currency has been changed.
	 *
	 * Every price snapshot carries the currency code, so Google keeps being quoted in the old
	 * currency until the snapshot is rebuilt. Nothing else pends them: the daily renew touches
	 * only the month newly entering the active period, so months already inside it would keep
	 * the old code for about a year.
	 *
	 * Availability carries no currency and the room type metadata carries none either, so only
	 * prices are pended.
	 */
	private function afterCurrencyUpdate(): void {
		RoomTypeSnapshotHelper::pendAllRemainingPrices();
	}

	/**
	 * Remembers the capacity an accommodation type had before the update, so afterRoomTypeUpdate()
	 * can tell a real change from a save that touched something else.
	 */
	private function beforeRoomTypeUpdate( int $postId ): void {
		$post = get_post( $postId );

		if ( is_null( $post ) || 'mphb_room_type' !== $post->post_type ) {
			return;
		}

		$this->roomTypeCapacityBeforeUpdate = array(
			'adults'   => (int) get_post_meta( $postId, 'mphb_adults_capacity', true ),
			'children' => (int) get_post_meta( $postId, 'mphb_children_capacity', true ),
		);
	}

	/**
	 * Fires once an accommodation type has been saved.
	 *
	 * Max adults and max children shape "max_adults_count", "max_children_count" and "capacity"
	 * in the room type metadata, and through them every occupancy the server offers. Prices
	 * carry none of that — they come from the rates — so nothing is pended here: the metadata
	 * is pushed right away instead. Its cron rebuilds every room type from scratch anyway, and
	 * on its own weekly schedule the change would reach Google up to seven days late.
	 *
	 * The search-form limits ("mphb_search_max_adults" / "mphb_search_max_children") cap the very
	 * same counts and are already handled by afterGuestSettingsUpdate().
	 */
	private function afterRoomTypeUpdate( int $postId, \WP_Post $post, bool $isUpdate ): void {
		$capacityBeforeUpdate               = $this->roomTypeCapacityBeforeUpdate;
		$this->roomTypeCapacityBeforeUpdate = null;

		if ( 'mphb_room_type' !== $post->post_type
			|| ! $isUpdate
			|| is_null( $capacityBeforeUpdate )
			|| ! in_array( $postId, GoogleHotelsHelper::getSyncedRoomTypeIds(), true )
		) {
			return;
		}

		$hasCapacityChanged = (int) get_post_meta( $postId, 'mphb_adults_capacity', true ) !== $capacityBeforeUpdate['adults']
			|| (int) get_post_meta( $postId, 'mphb_children_capacity', true ) !== $capacityBeforeUpdate['children'];

		if ( $hasCapacityChanged ) {
			SyncGoogleHotelsDataCron::startNow();
		}
	}

	/**
	 * Fires once an existing post has been updated.
	 *
	 * Also handles the status changes from/to "Active".
	 *
	 * Marks all room types as "pending" within the rate dates (before and after
	 * update).
	 */
	private function afterRateUpdate( int $postId, \WP_Post $post, bool $isUpdate ): void {
		// TODO: diff changes
		if ( 'mphb_rate' !== $post->post_type || ! $isUpdate ) {
			return;
		}

		$rate = mphb_prices_facade()->getRateById( $postId, $language = '', $forceReload = true );

		// The "save_post" action fires multiple times when saved via the admin
		// page. But $rateBeforeUpdate = null for additional calls, which
		// somewhat helps with the situation.
		if ( ! is_null( $this->rateBeforeUpdate ) && ! is_null( $rate ) ) {
			if ( $this->rateBeforeUpdate->isActive() ) {
				$this->pendPricesByRate( $this->rateBeforeUpdate );
			}

			if ( $rate->isActive() ) {
				$this->pendPricesByRate( $rate );
			}
		}

		$this->rateBeforeUpdate = null;
	}

	/**
	 * Fires once the reservation rules has been updated via the admin page.
	 */
	private function afterReservationRulesUpdate( array $newRules ): void {
		// TODO: diff changes
		RoomTypeSnapshotHelper::pendAllRemainingAvailability();
	}

	/**
	 * Fires once the taxes and fees has been updated via the admin page or the
	 * REST request.
	 *
	 * Marks all existing snapshots as "pending".
	 */
	private function afterTaxesAndFeesUpdate(): void {
		// TODO: diff changes
		RoomTypeSnapshotHelper::pendAllRemainingPrices();
	}

	/**
	 * Fires once an accommodation has been created, edited, trashed or restored.
	 *
	 * The number of published accommodations is what "available" counts, and "Linked
	 * Accommodations" make a booking of one accommodation block another. Neither is bound to a
	 * date, so the whole window is pended. The accommodation type is not touched here: the
	 * field is disabled outside of "post-new.php", so it can not change after creation.
	 *
	 * Creation counts as much as an edit — a new accommodation adds a unit to sell — so unlike
	 * afterRateUpdate() and afterSeasonUpdate() this one does not bail out on $isUpdate.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the whole "save_post" signature is kept.
	private function afterRoomSave( int $postId, \WP_Post $post, bool $isUpdate ): void {
		// TODO: pend only when the status or the linked accommodations have actually changed
		if ( 'mphb_room' === $post->post_type ) {
			$this->pendAvailabilityByRoom( $postId );
		}
	}

	/**
	 * Fires immediately before a post is permanently deleted from the database.
	 *
	 * The accommodation is still readable here, so its type is resolved before it is gone.
	 */
	private function beforeRoomDelete( int $postId, \WP_Post $post ): void {
		if ( 'mphb_room' === $post->post_type ) {
			$this->pendAvailabilityByRoom( $postId );
		}
	}

	/**
	 * Fires once an existing post has been updated.
	 *
	 * Marks all room types as "pending" within the season (before and after
	 * update).
	 */
	private function afterSeasonUpdate( int $postId, \WP_Post $post, bool $isUpdate ): void {
		if ( 'mphb_season' !== $post->post_type || ! $isUpdate ) {
			return;
		}

		$season = mphb_get_season( $postId, $force = true );

		if ( ! is_null( $this->seasonBeforeUpdate ) && ! is_null( $season ) ) {
			if ( SeasonDiff::haveSeasonsChanged( $this->seasonBeforeUpdate, $season ) ) {
				$this->pendBySeason( $this->seasonBeforeUpdate );
				$this->pendBySeason( $season );
			}
		}

		$this->seasonBeforeUpdate = null;
	}

	/**
	 * Fires immediately before a post is permanently deleted from the database.
	 *
	 * Marks all room types in the deleted season as "pending".
	 */
	private function beforeSeasonDelete( int $postId, \WP_Post $post ): void {
		if ( 'mphb_season' !== $post->post_type ) {
			return;
		}

		$season = mphb_get_season( $postId );

		if ( ! is_null( $season ) ) {
			$this->pendBySeason( $season );
		}
	}

	/**
	 * Fires immediately before an existing post is updated in the database.
	 *
	 * Saves the state of Rate object before update.
	 */
	private function beforeRateUpdate( int $postId ): void {
		// Null if the post is not a rate (CPTPersistence checks the post type)
		$this->rateBeforeUpdate = mphb_prices_facade()->getRateById( $postId );
	}

	/**
	 * Fires immediately before an existing post is updated in the database.
	 *
	 * Saves the state of Season object before update.
	 */
	private function beforeSeasonUpdate( int $postId ): void {
		// Null if the post is not a season (CPTPersistence checks the post type)
		$this->seasonBeforeUpdate = mphb_get_season( $postId );
	}

	/**
	 * Fires once an accommodation type has been excluded from Google Hotels, either through the
	 * "Google Hotels" page or by losing the "publish" status.
	 *
	 * Its snapshots are not deleted right away: they are marked for deletion so the calendar
	 * cron can tell the server to drop them.
	 */
	private function onRoomTypeExclude( RoomType $roomType ): void {
		RoomTypeSnapshotHelper::markRoomTypeAsDeleted( $roomType->getOriginalId() );
	}

	/**
	 * Fires once the integration has been turned on after being off.
	 *
	 * An accommodation type included right now gets its rows from onRoomTypeInclude(); this
	 * covers the ones that stayed included all along and went stale meanwhile.
	 */
	private function afterIntegrationEnable(): void {
		RoomTypeSnapshotHelper::pendAllRemainingAvailability();
		RoomTypeSnapshotHelper::pendAllRemainingPrices();
	}

	/**
	 * Fires once an accommodation type has been included into Google Hotels, either through the
	 * "Google Hotels" page or by becoming "publish" again.
	 *
	 * Creates a pending row for every month of the window, for both prices and availability.
	 */
	private function onRoomTypeInclude( RoomType $roomType ): void {
		RoomTypeSnapshotHelper::createSnapshotsForRoomType( $roomType->getOriginalId() );
	}

	/**
	 * Trashing a season or restoring it from the trash is not an update: "before_delete_post"
	 * fires only on a permanent delete, and the dates the season carries stay the same, so
	 * afterSeasonUpdate() sees nothing changed. The season stops (or starts) applying all the
	 * same, and with it every rate and every reservation rule scoped to it.
	 */
	private function onSeasonStatusChange( string $newStatus, string $oldStatus, \WP_Post $post ): void {
		if (
			'mphb_season' !== $post->post_type
			|| $newStatus === $oldStatus
			|| ( 'publish' !== $newStatus && 'publish' !== $oldStatus )
		) {
			return;
		}

		$season = mphb_get_season( $post->ID, $force = true );

		if ( ! is_null( $season ) ) {
			$this->pendBySeason( $season );
		}
	}

	/**
	 * Only the transition in or out of "publish" matters: an unpublished accommodation type is
	 * not sold, so it must leave the feed even while it stays included on the "Google Hotels"
	 * page. Every other transition (draft to pending, trash to draft) changes nothing.
	 */
	private function onRoomTypeStatusChange( string $newStatus, string $oldStatus, \WP_Post $post ): void {
		if (
			'mphb_room_type' !== $post->post_type
			|| ! in_array( $post->ID, GoogleHotelsHelper::getSyncedRoomTypeIds(), true )
			|| $newStatus === $oldStatus
		) {
			return;
		}

		$roomType = mphb_rooms_facade()->getRoomTypeById( $post->ID );

		if ( ! is_null( $roomType ) ) {
			if ( 'publish' === $newStatus ) {
				$this->onRoomTypeInclude( $roomType );
			} elseif ( 'publish' === $oldStatus ) {
				$this->onRoomTypeExclude( $roomType );
			}
		}
	}

	/**
	 * @param int[] $roomTypeIds
	 */
	private static function getMaxBufferDaysCount( array $roomTypeIds ): int {
		return array_reduce(
			$roomTypeIds,
			fn( int $maxBuffer, int $roomTypeId ) => max(
				$maxBuffer,
				mphb_availability_facade()->getMaxBufferDaysCount( $roomTypeId )
			),
			0
		);
	}

	/**
	 * Marks the months the block covers as "pending" for the accommodation types it touches.
	 *
	 * @param array $block The payload of the "mphb_block_*" actions. {
	 *     @type int    $block_id
	 *     @type string $comment
	 *     @type string $date_from First blocked date, "Y-m-d".
	 *     @type string $date_to Last blocked date, "Y-m-d", inclusive.
	 *     @type bool   $has_restrictions
	 *     @type bool   $not_check_in
	 *     @type bool   $not_check_out
	 *     @type bool   $not_stay_in
	 *     @type int    $room_id 0 for every accommodation of the type.
	 *     @type int    $room_type_id 0 for every accommodation type.
	 * }
	 */
	private function pendAvailabilityByBlock( array $block ): void {
		$roomTypeIds = array();

		if ( 0 === $block['room_type_id'] ) {
			$roomTypeIds = GoogleHotelsHelper::getSyncedRoomTypeIds();
		} elseif ( in_array( $block['room_type_id'], GoogleHotelsHelper::getSyncedRoomTypeIds(), true ) ) {
			$roomTypeIds = (array) $block['room_type_id'];
		}

		$dateFrom = DateUtils::createDate( $block['date_from'] );
		$dateTo   = DateUtils::createDate( $block['date_to'] );

		if ( empty( $roomTypeIds ) || is_null( $dateFrom ) || is_null( $dateTo ) ) {
			return;
		}

		$activePeriod = RoomTypeSnapshotHelper::getActivePeriodDates();

		$overlappingDates = DateUtils::getOverlappingDates(
			$dateFrom,
			$dateTo,
			$activePeriod[0],
			$activePeriod[1]
		);

		if ( false === $overlappingDates ) {
			return;
		}

		$months = DateUtils::getMonthsInPeriod( $overlappingDates[0], $overlappingDates[1] );

		if ( ! empty( $months ) ) {
			RoomTypeSnapshotHelper::pendAvailability( $roomTypeIds, array_keys( $months ) );
		}
	}

	/**
	 * Marks every month of the window as "pending" for the accommodation type and for the
	 * types linked to it: a booking of a linked accommodation blocks this one, so losing or
	 * gaining an accommodation moves the count on both sides.
	 */
	private function pendAvailabilityByRoom( int $roomId ): void {
		$roomTypeId = mphb_rooms_facade()->getRoomTypeIdByRoomId( $roomId );

		if ( 0 === $roomTypeId ) {
			return;
		}

		$roomTypeIds = array_merge(
			array( $roomTypeId ),
			LinkedRooms::getRoomTypeIdsLinkedToRoomType( $roomTypeId )
		);

		$roomTypeIds = array_intersect( array_unique( $roomTypeIds ), GoogleHotelsHelper::getSyncedRoomTypeIds() );

		if ( empty( $roomTypeIds ) ) {
			return;
		}

		$activePeriod = RoomTypeSnapshotHelper::getActivePeriodDates();
		$months       = DateUtils::getMonthsInPeriod( $activePeriod[0], $activePeriod[1] );

		if ( ! empty( $months ) ) {
			RoomTypeSnapshotHelper::pendAvailability( $roomTypeIds, array_keys( $months ) );
		}
	}

	/**
	 * Marks the months the booking covers as "pending" for its accommodation types and for the
	 * types linked to them — a linked accommodation is blocked by this booking too.
	 */
	private function pendAvailabilityByBooking( Booking $booking ): void {
		$roomTypeIds = array_map(
			function ( ReservedRoom $reservedRoom ) {
				return (int) $reservedRoom->getRoomTypeId();
			},
			$booking->getReservedRooms()
		);

		$roomTypeIds = array_unique( array_filter( $roomTypeIds ) );

		// Buffer days close the nights right before the check-in and right after the check-out of
		// the booked units, so a booking near a month border changes availability in the
		// neighbouring month too. The rule value is looked up by the room type of the booking (see
		// RoomAvailabilityHelper::getBookedDaysForRoomType()); the largest of them covers every
		// unit here, including the linked ones, which lose the same physical rooms.
		$bufferDaysCount = self::getMaxBufferDaysCount( $roomTypeIds );

		$linkedRoomTypeIds = array_reduce(
			$roomTypeIds,
			fn( array $linkedRoomTypeIds, int $roomTypeId ) => array_merge(
				$linkedRoomTypeIds,
				LinkedRooms::getRoomTypeIdsLinkedToRoomType( $roomTypeId )
			),
			array()
		);

		$roomTypeIds = array_unique( array_merge( $roomTypeIds, $linkedRoomTypeIds ) );
		$roomTypeIds = array_intersect( $roomTypeIds, GoogleHotelsHelper::getSyncedRoomTypeIds() );

		if ( empty( $roomTypeIds ) ) {
			return;
		}

		$activePeriod = RoomTypeSnapshotHelper::getActivePeriodDates();

		$overlappingDates = DateUtils::getOverlappingDates(
			DateUtils::cloneModify( $booking->getCheckInDate(), "-{$bufferDaysCount} days" ),
			DateUtils::cloneModify( $booking->getCheckOutDate(), "+{$bufferDaysCount} days" ),
			$activePeriod[0],
			$activePeriod[1]
		);

		if ( false === $overlappingDates ) {
			return;
		}

		$months = DateUtils::getMonthsInPeriod( $overlappingDates[0], $overlappingDates[1] );

		if ( ! empty( $months ) ) {
			RoomTypeSnapshotHelper::pendAvailability( $roomTypeIds, array_keys( $months ) );
		}
	}

	/**
	 * Marks the months the rate covers as "pending" for its accommodation type. The months come
	 * from the seasons of the rate, so a rate that sells nothing inside the window pends nothing.
	 */
	private function pendPricesByRate( Rate $rate ): void {
		if ( ! in_array( $rate->getRoomTypeId(), GoogleHotelsHelper::getSyncedRoomTypeIds(), true ) ) {
			return;
		}

		list( $dateFrom, $dateTo ) = RoomTypeSnapshotHelper::getActivePeriodDates();
		$months                    = RateUtils::getRateMonthsInPeriod( $rate, $dateFrom, $dateTo );

		if ( ! empty( $months ) ) {
			RoomTypeSnapshotHelper::pendPrices( $rate->getRoomTypeId(), array_keys( $months ) );
		}
	}

	/**
	 * A season scopes two different things, and each needs its own pend.
	 *
	 * Rates on the season set the PRICES of their room types within its months — that part is
	 * narrow and is pended per month.
	 *
	 * Reservation rules are scoped by season too (Booking_Rules_Data::getBookingRulesForDate()
	 * resolves them through Season::isDateInSeason()), and they set AVAILABILITY. Here the whole
	 * calendar is pended instead of the months of the season: a buffer rule reaches beyond the
	 * season by the length of a booking, and a repeating season scatters its months across the
	 * whole window. Seasons are edited rarely, so the extra pass costs little. This is the same
	 * trade-off afterReservationRulesUpdate() already makes for a rule change.
	 */
	private function pendBySeason( Season $season ): void {
		$this->pendPricesBySeason( $season );

		if ( self::isSeasonUsedByReservationRules( $season->getId() ) ) {
			RoomTypeSnapshotHelper::pendAllRemainingAvailability();
		}
	}

	/**
	 * Whether any reservation rule is scoped to this season. A rule with no seasons applies to
	 * every date and is therefore untouched by a change of one season.
	 */
	private static function isSeasonUsedByReservationRules( int $seasonId ): bool {
		foreach ( self::RESERVATION_RULE_OPTIONS as $option ) {
			foreach ( (array) get_option( $option, array() ) as $rule ) {
				if ( in_array( $seasonId, (array) ( $rule['season_ids'] ?? array() ), true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Marks the months of the season as "pending" for every accommodation type that has an active
	 * rate on it. A season with no rates pends nothing here — its rules are handled by
	 * pendBySeason().
	 */
	private function pendPricesBySeason( Season $season ): void {
		$ratesForSeason = mphb_prices_facade()->getActiveRatesBySeasonId( $season->getId() );

		$roomTypeIds = array_map( fn( $rate ) => $rate->getRoomTypeId(), $ratesForSeason );
		$roomTypeIds = array_intersect( $roomTypeIds, GoogleHotelsHelper::getSyncedRoomTypeIds() );

		if ( empty( $roomTypeIds ) ) {
			return;
		}

		list( $dateFrom, $dateTo ) = RoomTypeSnapshotHelper::getActivePeriodDates();

		$months = SeasonUtils::getSeasonMonthsInPeriod( $season, $dateFrom, $dateTo );

		if ( ! empty( $months ) ) {
			RoomTypeSnapshotHelper::pendPrices( array_unique( $roomTypeIds ), array_keys( $months ) );
		}
	}
}
