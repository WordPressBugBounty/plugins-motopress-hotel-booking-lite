<?php

declare(strict_types=1);

namespace MPHB\Crons;

use MPHB\Entities\Rate;
use MPHB\GoogleHotels\{ GoogleHotelsHelper, RoomTypeSnapshotHelper };
use MPHB\Utils\DateUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SyncGoogleHotelsCalendarCron extends Abstract_Task_Chain_Cron {
	private const RESPONSE_STATUS_FAILED       = 'failed';
	private const RESPONSE_STATUS_UNREGISTERED = 'unregistered';
	private const RESPONSE_STATUS_UPDATED      = 'updated';

	// A room type worth of data: 12 months multiplied by 2 (availability and prices)
	private const SNAPSHOT_BATCH_SIZE = 24;

	private const TASK_RENEW_SNAPSHOT_MONTHS  = 'renew_snapshot_months';
	private const TASK_SEND_PENDING_SNAPSHOTS = 'send_pending_snapshots';
	private const TASK_DONE                   = 'done';

	private const TRANSIENT_MONTHS_RENEWED_TODAY = 'mphb_gh_snapshot_months_renewed_today';

	/**
	 * If cron does not need to know the exact execution percentage, you can
	 * just always return 0 until all tasks are finished and then return 100.
	 *
	 * @param array<string, mixed> $cron_data Subclass-defined cron data bag
	 *     (keys vary per task chain).
	 * @return float 0.0-100.0 - percentage must be rounded to lower value with
	 *     PHP_ROUND_HALF_DOWN to make sure that 100% is calculated when all
	 *     crons tasks are done!
	 */
	protected function calculate_current_execution_percentage( $cron_data ): float {
		return self::TASK_DONE === $cron_data['phase'] ? 100.0 : 0.0;
	}

	/**
	 * Process single task of the current task chain.
	 * It has to be as fast as possible (less than 20 seconds)!
	 *
	 * @param array<string, mixed> $cron_data Subclass-defined cron data bag
	 *     (keys vary per task chain).
	 * @return array<string, mixed> Updated (if necessary) cron data.
	 *
	 * @throws \Exception If something goes wrong.
	 */
	protected function do_task( array $cron_data ): array {

		// The loopback that drives this chain forwards $_COOKIE, WPML's language cookie
		// included, so a chain started from a non-default admin language keeps running in
		// that language. Rates are a translatable post type: an untranslated rate is not
		// found there and the price snapshot comes out with no dates at all. Build every
		// snapshot in the default language (a no-op without WPML).
		//
		// This must run before anything in the request queries rates: the prices facade
		// caches them without the language in the cache key.
		$isActiveWPML = MPHB()->translation()->isActiveWPML();

		if ( $isActiveWPML ) {
			MPHB()->translation()->setupDefaultLanguage();
		}

		try {

			switch ( $cron_data['phase'] ) {
				case self::TASK_RENEW_SNAPSHOT_MONTHS:
					return $this->handle_renew_snapshot_months( $cron_data );
				case self::TASK_SEND_PENDING_SNAPSHOTS:
					return $this->handle_send_pending_snapshots( $cron_data );
				case self::TASK_DONE:
				default:
					return $cron_data;
			}
		} finally {

			if ( $isActiveWPML ) {
				MPHB()->translation()->restoreLanguage();
			}
		}
	}

	/**
	 * @param array $snapshot {
	 *     @type int    $room_type_id
	 *     @type string $period_start
	 *     @type string $snapshot_type
	 *     @type string $status
	 * }
	 * @return array|null Returns null if data processing fails.
	 */
	private function build_availability_data_for_snapshot( array $snapshot ): ?array {
		$period_start = new \DateTime( $snapshot['period_start'], DateUtils::getSiteTimeZone() );
		$period_end   = new \DateTime( $period_start->format( 'Y-m-t' ), DateUtils::getSiteTimeZone() );

		$active_period      = RoomTypeSnapshotHelper::getActivePeriodDates();
		$overlapping_period = false;

		if ( null !== $period_start && null !== $period_end ) {
			$overlapping_period = DateUtils::getOverlappingDates(
				$period_start,
				$period_end,
				$active_period[0],
				$active_period[1]
			);
		}

		if ( false === $overlapping_period ) {
			return null;
		}

		list( $start_date, $end_date ) = $overlapping_period;

		// See Core/Data/Snapshot_Schema::get_availability_schema_v1_0_2() of
		// Google Hotel Prices
		$availability = array(
			'dates'        => array(),
			'period_start' => $snapshot['period_start'],
			'room_type_id' => $snapshot['room_type_id'],
		);

		$date = clone $start_date;

		while ( $date <= $end_date ) {
			$date_str = $date->format( 'Y-m-d' );

			// Booking rules are read with the rules ON. The min/max advance, min/max stay and
			// check-in/out flags are sent as separate fields so the server applies them itself at
			// query time and reports the exact reason.
			$booking_rules_availability = mphb_availability_facade()->getRoomTypeAvailabilityData(
				$snapshot['room_type_id'],
				$date,
				$isIgnoreBookingRules   = false
			);

			$availability['dates'][ $date_str ] = array(
				// Truly bookable unit count: active units minus real bookings, their buffer and
				// blocked ("not stay in") units. Blocks are subtracted here so a date whose units are
				// split between bookings and partial blocks is not over-reported as vacant. A
				// whole-type block drops this to 0 and also sets not_stay_in below; the server reads
				// not_stay_in first, reporting PropertyClosed instead of NoVacancy. This count holds
				// no time-relative rule (advance window, min/max stay), so it does not go stale.
				'available'     => $booking_rules_availability->getAvailableRoomsCount() > 0,
				'min_advance'   => $booking_rules_availability->getMinAdvanceDays(),
				'min_stay'      => $booking_rules_availability->getMinStayNights(),
				// Pure check-in / check-out day rules, read straight from the booking rules. The
				// flags on RoomTypeAvailabilityData carry the calendar logic on top of the rule:
				// they also turn on when a neighbour night can not be sold (blocked, booked, out
				// of season) or already lies in the past. The server walks every night of the stay
				// and evaluates min_stay itself, so that part is redundant here — and, being
				// derived from rates and from today's date, it goes stale between syncs.
				'not_check_in'  => mphb_availability_facade()->isCheckInNotAllowed(
					(int) $snapshot['room_type_id'],
					$date,
					$isIgnoreBookingRules = false
				),
				'not_check_out' => mphb_availability_facade()->isCheckOutNotAllowed(
					(int) $snapshot['room_type_id'],
					$date,
					$isIgnoreBookingRules = false
				),
				'not_stay_in'   => $booking_rules_availability->isStayInNotAllowed(),
			);

			if ( $booking_rules_availability->getMaxAdvanceDays() !== null ) {
				$availability['dates'][ $date_str ]['max_advance'] = $booking_rules_availability->getMaxAdvanceDays();
			}

			if ( $booking_rules_availability->getMaxStayNights() !== null ) {
				$availability['dates'][ $date_str ]['max_stay'] = $booking_rules_availability->getMaxStayNights();
			}

			$date->modify( '+1 day' );
		}

		return $availability;
	}

	/**
	 * @param array $snapshot {
	 *     @type int    $room_type_id
	 *     @type string $period_start
	 *     @type string $snapshot_type
	 *     @type string $status
	 * }
	 * @return array|null Returns null if data processing fails.
	 */
	private function build_prices_data_for_snapshot( array $snapshot ): ?array {
		$period_start = new \DateTime( $snapshot['period_start'], DateUtils::getSiteTimeZone() );
		$period_end   = new \DateTime( $period_start->format( 'Y-m-t' ), DateUtils::getSiteTimeZone() );

		$active_period      = RoomTypeSnapshotHelper::getActivePeriodDates();
		$overlapping_period = false;

		if ( null !== $period_start && null !== $period_end ) {
			$overlapping_period = DateUtils::getOverlappingDates(
				$period_start,
				$period_end,
				$active_period[0],
				$active_period[1]
			);
		}

		if ( false === $overlapping_period ) {
			return null;
		}

		list( $start_date, $end_date ) = $overlapping_period;

		$taxes_and_fees = $this->get_taxes_and_fees( $snapshot['room_type_id'] );

		// See Core/Data/Snapshot_Schema::get_prices_schema_v1_0_2() of
		// Google Hotel Prices
		$prices = array(
			'currency'     => MPHB()->settings()->currency()->getCurrencyCode(),
			'dates'        => array(),
			'fee_taxes'    => $taxes_and_fees['fee_taxes'] ?? array(),
			'fees'         => $taxes_and_fees['fees'] ?? array(),
			'period_start' => $snapshot['period_start'],
			'room_type_id' => $snapshot['room_type_id'],
			'taxes'        => $taxes_and_fees['accommodation_taxes'] ?? array(),
		);

		/** @var Rate[] $active_rates */
		$active_rates = mphb_prices_facade()->getActiveRatesByRoomTypeId( $snapshot['room_type_id'] );

		/** @var array<int, array> <code>[ Rate ID => [ Date (string) => Price (float) ] ]</code> */
		$custom_prices = array_reduce(
			$active_rates,
			function ( array $custom_prices, Rate $rate ) use ( $start_date, $end_date ) {
				$custom_prices[ $rate->getId() ] = mphb_prices_facade()->getCustomPricesForPeriod( $rate->getId(), $start_date, $end_date );

				return $custom_prices;
			},
			array()
		);

		// The site's guest management setting decides whether occupancy affects the price at all, so
		// the snapshot must mirror it (SeasonPrice::getPrice() gates surcharges/variations the same way).
		$are_adults_allowed   = MPHB()->settings()->main()->isAdultsAllowed();
		$are_children_allowed = MPHB()->settings()->main()->isChildrenAllowed();

		$date = clone $start_date;

		while ( $date <= $end_date ) {
			$date_str = $date->format( 'Y-m-d' );

			foreach ( $active_rates as $rate ) {
				$rate_id = $rate->getId();

				if ( isset( $custom_prices[ $rate_id ][ $date_str ] ) ) {
					$prices['dates'][ $date_str ][ $rate_id ] = array(
						'custom' => $custom_prices[ $rate_id ][ $date_str ],
					);
				} elseif ( $rate->isReadonly() ) { // PriceLab
					$price = $rate->getDatePrice( $date_str );

					if ( null !== $price ) {
						$prices['dates'][ $date_str ][ $rate_id ] = array(
							'custom' => $price,
						);
					}
				} else {
					$seasonPrice = $rate->getSeasonPriceForDate( $date );

					if ( null !== $seasonPrice ) {
						$prices['dates'][ $date_str ][ $rate_id ] = $this->apply_guest_management_to_rate_prices(
							$seasonPrice->toArray(),
							$are_adults_allowed,
							$are_children_allowed
						);
					}
				}
			}

			$date->modify( '+1 day' );
		}

		return $prices;
	}

	/**
	 * Applies the site's guest management setting to one rate's occupancy prices, so the snapshot
	 * matches what MPHB would actually charge. A flat custom price carries no occupancy and is
	 * returned unchanged.
	 *
	 * @param array $rate_prices A single rate entry from SeasonPrice::toArray() (or a custom price).
	 */
	private function apply_guest_management_to_rate_prices(
		array $rate_prices,
		bool $are_adults_allowed,
		bool $are_children_allowed
	): array {
		if ( isset( $rate_prices['custom'] ) ) {
			return $rate_prices;
		}

		// "Disable adults and children": rooms are sold without a guest selection, so only the base
		// price applies (SeasonPrice::getPrice() adds no surcharges or variations in this mode).
		if ( ! $are_adults_allowed ) {
			unset( $rate_prices['extra_adult'], $rate_prices['extra_child'], $rate_prices['variations'] );

			return $rate_prices;
		}

		// "Disable children": no per-child surcharge and no variation that adds children.
		if ( ! $are_children_allowed ) {
			unset( $rate_prices['extra_child'] );

			if ( ! empty( $rate_prices['variations'] ) ) {
				$rate_prices['variations'] = array_values(
					array_filter(
						$rate_prices['variations'],
						function ( array $variation ) {
							return empty( $variation['children'] );
						}
					)
				);
			}
		}

		return $rate_prices;
	}

	private function handle_renew_snapshot_months( array $cron_data ): array {
		$months_renewed_today = (bool) get_transient( self::TRANSIENT_MONTHS_RENEWED_TODAY );

		if ( ! $months_renewed_today ) {
			// Add new month
			$last_date_to_sync = RoomTypeSnapshotHelper::getActivePeriodDates()[1];
			$last_saved_month  = RoomTypeSnapshotHelper::getLastMonth();

			if (
				null === $last_saved_month
				|| $last_saved_month->format( 'Y-m-01' ) < $last_date_to_sync->format( 'Y-m-01' )
			) {
				$first_date_of_month_to_sync = ! is_null( $last_saved_month )
					? DateUtils::cloneModify( $last_saved_month, 'first day of next month' )
					: new \DateTime( 'today', DateUtils::getSiteTimeZone() );

				$room_type_ids = GoogleHotelsHelper::getSyncedRoomTypeIds();
				$months        = DateUtils::getMonthsInPeriod( $first_date_of_month_to_sync, $last_date_to_sync );

				RoomTypeSnapshotHelper::pendAvailability( $room_type_ids, array_keys( $months ) );
				RoomTypeSnapshotHelper::pendPrices( $room_type_ids, array_keys( $months ) );
			}

			// Remove old month
			$first_date_to_sync = RoomTypeSnapshotHelper::getActivePeriodDates()[0];

			RoomTypeSnapshotHelper::deleteSnapshotsEarlierThan( $first_date_to_sync->format( 'Y-m-01' ) );

			// Wait until tomorrow
			$tomorrow = new \DateTime( 'tomorrow' );

			set_transient(
				self::TRANSIENT_MONTHS_RENEWED_TODAY,
				true,
				$tomorrow->getTimestamp() - time()
			);
		}

		$cron_data['phase'] = self::TASK_SEND_PENDING_SNAPSHOTS;

		return $cron_data;
	}

	private function handle_send_pending_snapshots( array $cron_data ): array {
		$snapshots  = RoomTypeSnapshotHelper::getPendingSnapshotsBatch(
			self::SNAPSHOT_BATCH_SIZE,
			$offset = $cron_data['failed_snapshots_count']
		);

		$delete_room_type_ids = RoomTypeSnapshotHelper::getRoomTypesToDelete();

		if ( empty( $snapshots ) ) {

			// A removal is not a pending snapshot: getPendingSnapshotsBatch() never returns one,
			// so without this it would wait for an unrelated snapshot to be pended and ride along
			// with its batch — for a site that changes nothing else, forever.
			if ( empty( $delete_room_type_ids ) ) {
				$cron_data['phase'] = self::TASK_DONE;

				return $cron_data;
			}

			// Send it on its own, then end the chain either way: with an empty batch a retry
			// would repeat the very same request, so a failure is left to the next scheduled run.
			$cron_data['phase'] = self::TASK_DONE;
		}

		$request_data = array(
			'availability'         => array(),
			'data_schema_version'  => GoogleHotelsHelper::SCHEMA_VERSION,
			'delete_room_type_ids' => $delete_room_type_ids,
			'id'                   => GoogleHotelsHelper::getSiteServerId(),
			'prices'               => array(),
		);

		$send_stats = array(
			// [ Snapshot type => [ Room type ID => Months ] ]
			'availability' => array(),
			'prices'       => array(),
		);

		$have_data_to_send = ! empty( $request_data['delete_room_type_ids'] );

		foreach ( $snapshots as $snapshot ) {
			list(
				'snapshot_type' => $snapshot_type,
				'room_type_id'  => $room_type_id,
			) = $snapshot;

			$snapshot_data = null;

			switch ( $snapshot_type ) {
				case RoomTypeSnapshotHelper::SNAPSHOT_TYPE_AVAILABILITY:
					$snapshot_data = $this->build_availability_data_for_snapshot( $snapshot );
					break;

				case RoomTypeSnapshotHelper::SNAPSHOT_TYPE_PRICES:
					$snapshot_data = $this->build_prices_data_for_snapshot( $snapshot );
					break;
			}

			if ( null !== $snapshot_data ) {
				$request_data[ $snapshot_type ][]                = $snapshot_data;
				$send_stats[ $snapshot_type ][ $room_type_id ][] = $snapshot['period_start'];
				$have_data_to_send                               = true;
			} else {
				++$cron_data['failed_snapshots_count'];
			}
		}

		if ( $have_data_to_send ) {
			$is_data_sent = false;

			try {
				$status = $this->send_data_to_server( $request_data );

				if ( self::RESPONSE_STATUS_UPDATED === $status ) {
					$is_data_sent = true;

				} elseif ( self::RESPONSE_STATUS_UNREGISTERED === $status ) {
					GoogleHotelsHelper::revokeSiteServerSecret();

					// Requests may take to much time, so make a retry instead
					// of calling the send_data_to_server() again
					$cron_data['max_retries'] = 2; // Give +1 retry
				}
			} catch ( \Exception $e ) {
				error_log( $e->getMessage() ); // phpcs:ignore
			}

			if ( $is_data_sent ) {
				// Update statuses in the database
				foreach ( $send_stats['availability'] as $room_type_id => $months ) {
					RoomTypeSnapshotHelper::availabilitySent( $room_type_id, $months );
				}

				foreach ( $send_stats['prices'] as $room_type_id => $months ) {
					RoomTypeSnapshotHelper::pricesSent( $room_type_id, $months );
				}

				if ( ! empty( $request_data['delete_room_type_ids'] ) ) {
					RoomTypeSnapshotHelper::deleteRoomTypes( $request_data['delete_room_type_ids'] );

					// If there are deleted rooms, run the property sync cron
					// immediately, without waiting a week, to reduce the period
					// of out-of-sync of snapshot data and availability/prices
					SyncGoogleHotelsDataCron::startNow();
				}

				if ( count( $snapshots ) < self::SNAPSHOT_BATCH_SIZE ) {
					$cron_data['phase'] = self::TASK_DONE;
				}

				// Reset retries and attempts
				$cron_data['max_retries']     = 1;
				$cron_data['request_attempt'] = 1;

			} elseif ( $cron_data['request_attempt'] < $cron_data['max_retries'] ) {
				++$cron_data['request_attempt'];

			} else {
				$cron_data['failed_snapshots_count'] += count( $request_data['availability'] );
				$cron_data['failed_snapshots_count'] += count( $request_data['prices'] );

				// Reset retries and attempts
				$cron_data['max_retries']     = 1;
				$cron_data['request_attempt'] = 1;
			}
		}

		return $cron_data;
	}

	/**
	 * @return string Server response status.
	 *
	 * @throws \Exception
	 */
	private function send_data_to_server( array $request_data ): string {
		$server_credentials = GoogleHotelsHelper::getSiteServerCredentials();

		if ( ! $server_credentials['site_id'] || ! $server_credentials['secret'] ) {
			$server_credentials = GoogleHotelsHelper::registerSite();
		}

		$site_id           = $server_credentials['site_id'];
		$request_route     = "/mpghp/v1/sites/{$site_id}/pricing";
		$request_timestamp = time();
		$request_nonce     = \random_int( 1, PHP_INT_MAX );
		$request_body      = wp_json_encode( $request_data );

		if ( false === $request_body ) {
			throw new \Exception( 'Encoding error in Google Hotels prices submission.' );
		}

		// Method, route and body MUST match server-side exactly
		$string_to_sign = implode(
			"\n",
			array(
				'POST',
				$request_route,
				$request_timestamp,
				$request_nonce,
				$request_body,
			)
		);

		$signature  = hash_hmac(
			'sha256',
			$string_to_sign,
			$server_credentials['secret'],
			$binary = false // Hex
		);

		$response = wp_remote_post(
			GoogleHotelsHelper::SERVER_REST_URL . $request_route,
			array(
				'body'      => $request_body,
				'headers'   => array(
					'Content-Type'          => 'application/json',
					'X-MPHB-Nonce'          => (string) $request_nonce,
					'X-MPHB-Site-Id'        => (string) $site_id,
					'X-MPHB-Site-Signature' => $signature,
					'X-MPHB-Timestamp'      => (string) $request_timestamp,
				),
				'sslverify' => false,
				'timeout'   => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore
			throw new \Exception( $response->get_error_message() );
		}

		$response_body    = wp_remote_retrieve_body( $response );
		$decoded_response = json_decode( $response_body, true );
		$response_data    = is_array( $decoded_response ) ? $decoded_response : array();
		$response_status  = $response_data['status'] ?? self::RESPONSE_STATUS_FAILED;

		if ( self::RESPONSE_STATUS_FAILED === $response_status ) {
			throw new \Exception(
				! empty( $response_data['message'] )
					? esc_html( $response_data['message'] )
					: 'Google Hotels prices submission failed.'
			);
		}

		return $response_status;
	}

	/**
	 * Taxes and fees of one accommodation type as they are configured right now. Fee taxes are
	 * read only when there are fees to tax.
	 *
	 * @return array {
	 *     @type array $accommodation_taxes
	 *     @type array $fee_taxes
	 *     @type array $fees
	 * }
	 */
	private function get_taxes_and_fees( int $room_type_id ): array {
		$taxes_and_fees = MPHB()->settings()->taxesAndFees();

		$fees = $taxes_and_fees->getFees( $room_type_id );

		return $this->sanitize_taxes_and_fees(
			array(
				'accommodation_taxes' => $taxes_and_fees->getAccommodationTaxes( $room_type_id ),
				'fee_taxes'           => empty( $fees ) ? array() : $taxes_and_fees->getFeeTaxes(),
				'fees'                => $fees,
			)
		);
	}

	/**
	 * Removes all additional properties that server does not allow.
	 */
	private function sanitize_taxes_and_fees( array $taxes_and_fees ): array {
		$sanitized = array();

		foreach ( $taxes_and_fees as $category => $records ) {
			// See core/data/Snapshot_Schema::get_charge_items_schema()
			if ( 'accommodation_taxes' === $category || 'fees' === $category ) {
				foreach ( $records as $record ) {
					if ( 'per_guest_per_day' === $record['type'] || 'per_room_per_day' === $record['type'] ) {
						$sanitized[ $category ][] = array(
							'amount' => $record['amount'],
							'limit'  => $record['limit'],
							'type'   => $record['type'],
						);
					} elseif ( 'per_room_percentage' === $record['type'] ) {
						$sanitized[ $category ][] = array(
							'amount' => $record['amount'],
							'type'   => $record['type'],
						);
					}
				}

				// See core/data/Snapshot_Schema::get_prices_schema_v1_0_2()
			} elseif ( 'fee_taxes' === $category ) {
				foreach ( $records as $record ) {
					$sanitized[ $category ][] = array(
						'amount' => $record['amount'],
						'type'   => $record['type'],
					);
				}
			}
		}

		return $sanitized;
	}

	public static function get_cron_action_hook_name(): string {
		return 'mphb_cron_sync_google_hotels_calendar';
	}

	protected static function do_before_cron_start( array $cron_data ): array {
		$cron_data = array(
			'phase'                  => self::TASK_RENEW_SNAPSHOT_MONTHS,
			'failed_snapshots_count' => 0,
			'max_retries'            => 1,
			'request_attempt'        => 1,
		);

		return $cron_data;
	}
}
