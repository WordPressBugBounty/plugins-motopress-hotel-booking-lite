<?php

declare(strict_types=1);

namespace MPHB\Crons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An adapter between the AbstractCron and Abstract_WP_Cron that uses
 * Abstract_Task_Chain_Cron.
 *
 * Inconsistencies with the Abstract_WP_Cron class:
 * - the schedule() and unschedule() methods already exist in the AbstractCron
 *   class, the new static methods are renamed to schedule_cron() and
 *   unschedule_cron().
 */
abstract class Abstract_WP_Cron_Adapter extends AbstractCron {
	const WP_CRON_START_INTERVAL_ID_WEEKLY      = 'weekly';
	const WP_CRON_START_INTERVAL_ID_DAILY       = 'daily';
	const WP_CRON_START_INTERVAL_ID_TWICE_DAILY = 'twicedaily';
	const WP_CRON_START_INTERVAL_ID_HOURLY      = 'hourly';

	const CRON_START_INTERVAL_ID_EVERY_3_MIN  = CronManager::INTERVAL_EVERY_3_MINS;
	const CRON_START_INTERVAL_ID_EVERY_5_MIN  = CronManager::INTERVAL_EVERY_5_MINS;
	const CRON_START_INTERVAL_ID_EVERY_10_MIN = CronManager::INTERVAL_EVERY_10_MINS;
	const CRON_START_INTERVAL_ID_EVERY_30_MIN = CronManager::INTERVAL_HALF_AN_HOUR;

	private static array $instances = array();

	public function __construct() {
		$id = str_replace( self::ACTION_PREFIX, '', static::get_cron_action_hook_name() );
		$interval = static::get_cron_start_interval_id();

		parent::__construct( $id, $interval );

		self::$instances[ static::class ] = $this;

		static::init();
	}

	public function doCronJob() {
		try {
			$this->execute_cron();
		} catch ( \Throwable $e ) {
			error_log( $e->getMessage() ); // phpcs:ignore
		}
	}

	/**
	 * @throws \Exception If something goes wrong.
	 */
	abstract protected function execute_cron(): void;

	/**
	 * @return string Cron action hook name must starts with "mphb_cron_".
	 */
	abstract public static function get_cron_action_hook_name(): string;

	abstract public static function get_cron_start_interval_id(): string;

	/**
	 * @return int|false The Unix timestamp of the next time the event will
	 *     occur. False if the cron doesn't scheduled.
	 */
	public static function get_next_scheduled_time() {
		return static::get_instance()->scheduledAt();
	}

	public static function init(): void {
		if ( static::is_schedule_needed_on_init() ) {
			static::schedule_cron();
		}
	}

	/**
	 * @param bool $is_reschedule If true then reschedule cron.
	 * @param int $unix_timestamp_utc Timestamp (UTC) for when to next run the
	 *     cron if 0 then gets current time.
	 */
	public static function schedule_cron( bool $is_reschedule = false, int $unix_timestamp_utc = 0 ) {
		if ( 0 === $unix_timestamp_utc ) {
			$unix_timestamp_utc = time();
		}

		if ( $is_reschedule ) {
			static::get_instance()->reScheduleAt( $unix_timestamp_utc );
		} else {
			static::get_instance()->scheduleAt( $unix_timestamp_utc );
		}
	}

	public static function unschedule_cron() {
		static::get_instance()->unschedule();
	}

	/**
	 * @return static
	 */
	protected static function get_instance() {
		if ( ! isset( self::$instances[ static::class ] ) ) {
			new static();
		}

		return self::$instances[ static::class ];
	}

	/**
	 * Whether init() must make sure the cron is scheduled. A recurring cron
	 * always is; crons that run only while some job is in progress override
	 * this.
	 */
	protected static function is_schedule_needed_on_init(): bool {
		return true;
	}
}
