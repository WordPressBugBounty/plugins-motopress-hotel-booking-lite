<?php

namespace MPHB\Crons;

defined( 'ABSPATH' ) || exit;

/**
 * Such crons process all data in tasks (chunks) using a post-requests chain.
 * This approach allows you to avoid disabling the cron script on low
 * traffic sites because of the long execution time of free hosting.
 * If the task chain needs to be run regularly, use another simple cron to
 * start the task chain with some cron data.
 */
abstract class Abstract_Task_Chain_Cron extends Abstract_WP_Cron_Adapter {

	private const TASKS_PROCESSING_TIME_LIMIT_IN_SECONDS = 20;

	private const OPTION_NAME_CRON_LOCK_POSTFIX = '_lock';

	private const OPTION_NAME_CRON_DATA_POSTFIX               = '_data';
	private const OPTION_NAME_CRON_USER_ID_POSTFIX            = '_user_id';
	private const OPTION_NAME_EXECUTION_PERCENTAGE_POSTFIX    = '_execution_percentage';
	private const OPTION_NAME_EXECUTION_STATUS_POSTFIX        = '_execution_status';
	private const OPTION_NAME_EXECUTION_RUN_ID_POSTFIX        = '_execution_run_id';
	private const OPTION_NAME_EXECUTION_ERROR_MESSAGE_POSTFIX = '_execution_error_message';

	private const EXECUTION_STATUS_STARTED   = 'started';
	private const EXECUTION_STATUS_CANCELING = 'canceling';
	private const EXECUTION_STATUS_CANCELED  = 'canceled';
	private const EXECUTION_STATUS_FINISHED  = 'finished';
	private const EXECUTION_STATUS_FAILED    = 'failed';


	public static function init(): void {

		parent::init();

		add_action(
			'wp_ajax_' . static::get_cron_action_hook_name(),
			function () {
				( new static() )->process_http_post_request_chain();
			}
		);
		add_action(
			'wp_ajax_nopriv_' . static::get_cron_action_hook_name(),
			function () {
				( new static() )->process_http_post_request_chain();
			}
		);
	}

	/**
	 * Make sure cron scheduled itself on the right time and make sure we have cron as
	 * healthcheck process just in case when task chain execution is terminated unexpectedly.
	 */
	protected static function is_schedule_needed_on_init(): bool {
		return ! static::is_stopped();
	}


	public static function get_cron_start_interval_id(): string {
		return self::CRON_START_INTERVAL_ID_EVERY_3_MIN;
	}

	/**
	 * Start or restart the task chain execution if it is not already processing
	 *
	 * @throws \Exception id something goes wrong
	 */
	final protected function execute_cron(): void {

		if ( static::is_canceling() ) {

			static::set_current_execution_status( self::EXECUTION_STATUS_CANCELED );
			static::unschedule_cron();
			static::do_after_cron_canceled( static::get_cron_data() );
			wp_die();
		}

		if ( ! static::is_started() || static::is_canceled() ) {
			// The healthcheck is a recurring event and WP-Cron queues its next occurrence before firing
			// this callback. A run that already finished/canceled elsewhere unschedules only on the path
			// that crosses 100%, so such a stray occurrence would otherwise reschedule itself forever.
			// Drop it here so any post-run firing tears the recurring event down instead of perpetuating it.
			static::unschedule_cron();
			wp_die();
		}

		// Atomically acquire the execution lock so that no other thread of this cron runs tasks
		// at the same time. A non-atomic check-then-set would let a burst of concurrent requests
		// all pass the check before any of them marks the lock, duplicating the work.
		$lock_expiry_token = static::acquire_execution_lock();

		if ( 0 === $lock_expiry_token ) {
			wp_die();
		}

		// Double-checked locking: another thread may have finished or canceled the run between the
		// status gate above and acquiring the lock. Re-read the status now that we hold the lock so
		// only the single thread that still sees a live (started) run processes and finishes it.
		// The status must be read straight from the database here: get_option() cached the "started"
		// value read at the gate above for the rest of this request, so it would not reflect another
		// thread's "finished" write. Without this fresh read every thread that passed the gate before
		// the first finish re-runs the do-loop and logs the completion again, once per request.
		if ( self::EXECUTION_STATUS_STARTED !== static::get_current_execution_status_from_db() ) {
			static::release_execution_lock( $lock_expiry_token );
			wp_die();
		}

		$current_user_id_before_cron_execution = get_current_user_id();

		try {

			$cron_data = static::get_cron_data();

			wp_set_current_user( static::get_cron_user_id() );

			// Set start time of current task processing step
			$finish_task_processing_time = time() + self::TASKS_PROCESSING_TIME_LIMIT_IN_SECONDS;

			do {
				// if task returns empty $nextTaskData then execution is over
				$cron_data = $this->do_task( $cron_data );
				static::set_cron_data( $cron_data );

				$execution_percentage = $this->calculate_current_execution_percentage( $cron_data );
				static::set_current_execution_percentage( $execution_percentage );

				if ( 100 <= $execution_percentage ) {

					static::set_current_execution_status( self::EXECUTION_STATUS_FINISHED );
					static::unschedule_cron();
					static::do_after_cron_finished( $cron_data );
				}
			} while (
				$finish_task_processing_time > time() &&
				! static::is_finished() &&
				! static::is_canceling() &&
				! static::is_canceled()
			);

			wp_set_current_user( $current_user_id_before_cron_execution );

			// Release the lock before continuing the chain so the next chunk can acquire it.
			static::release_execution_lock( $lock_expiry_token );

			if ( static::is_canceling() ) {

				static::cancel_cron_execution();

			} elseif ( ! static::is_finished() && ! static::is_canceled() ) {

				static::send_http_post_request_to_continue_task_chain();
			}
		} catch ( \Throwable $e ) {

			wp_set_current_user( $current_user_id_before_cron_execution );
			// Release the lock so other threads of this cron can acquire it.
			static::release_execution_lock( $lock_expiry_token );
			// Distinct terminal state: an aborted-by-error run is `failed`, not `canceled` (which means
			// a deliberate cancel), so callers can tell a crash from a manual stop.
			static::fail_cron_execution( $e->getMessage() );

			throw $e;
		}
	}

	private function process_http_post_request_chain(): void {

		try {

			// Don't lock up other requests while processing
			session_write_close();

			if ( static::is_executing_now() ) {
				wp_die();
			}

			check_ajax_referer( static::get_cron_action_hook_name(), 'nonce' );

			// The loopback is a public (nopriv) endpoint guarded only by a ~24h nonce. Bind it to the current
			// run's id (server-generated UUID, rotated each start) too: a leaked loopback URL can then only be
			// replayed within this single run, and a stale loopback from a previous run is dropped.
			$request_run_id = isset( $_REQUEST['run_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['run_id'] ) ) : '';

			if ( '' === $request_run_id || static::get_current_execution_run_id() !== $request_run_id ) {
				wp_die();
			}

			$this->execute_cron();

		} catch ( \Throwable $e ) {

			error_log( $e );
		}

		wp_die();
	}


	private static function send_http_post_request_to_continue_task_chain(): void {

		// initate next chunk to be able to execute it without cron system
		$ajax_url_for_requests_chaine = add_query_arg(
			array(
				'action' => static::get_cron_action_hook_name(),
				'nonce'  => wp_create_nonce( static::get_cron_action_hook_name() ),
				'run_id' => static::get_current_execution_run_id(),
			),
			admin_url( 'admin-ajax.php' )
		);

		wp_remote_post(
			esc_url_raw( $ajax_url_for_requests_chaine ),
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'body'      => array(),
				'cookies'   => $_COOKIE,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			)
		);
	}

	/**
	 * Starts new task chain execution with given cron data.
	 *
	 * @param array<string, mixed> $cron_data must contain keys from public constants CRON_DATA_KEY_...
	 * @return string run_id — unique identifier of this execution run
	 * @throws \Exception if cron already started or canceling.
	 */
	final public static function start_cron_execution( array $cron_data = array() ): string {

		if ( static::is_canceling() ) {
			throw new \Exception(
				'Could not start ' . esc_html( static::get_cron_action_hook_name() ) .
				' because it is canceling now.'
			);
		}

		if ( ! static::is_stopped() ) {
			throw new \Exception(
				'Could not start ' . esc_html( static::get_cron_action_hook_name() ) .
				' because it is working now.'
			);
		}

		// Fresh run starts from a clean slate — drop any error message left by a previous failed run.
		static::set_current_execution_error_message( '' );

		$cron_data = static::do_before_cron_start( $cron_data );
		static::set_cron_user_id( get_current_user_id() );
		static::set_cron_data( $cron_data );
		static::set_current_execution_percentage( 0 );
		$run_id = static::save_new_execution_run_id();
		static::set_current_execution_status( self::EXECUTION_STATUS_STARTED );
		static::schedule_cron( true );

		// send first post request right now to make sure
		// we start processing now even if WordPress will wait before start cron
		static::send_http_post_request_to_continue_task_chain();

		return $run_id;
	}

	/**
	 * @param array<string, mixed> $cron_data subclass-defined cron data bag (keys vary per task chain)
	 * @return array<string, mixed> initialized cron data bag
	 */
	protected static function do_before_cron_start( array $cron_data ): array {
		return $cron_data;
	}

	/**
	 * @param array<string, mixed> $cron_data subclass-defined cron data bag (keys vary per task chain)
	 */
	protected static function do_after_cron_canceled( array $cron_data ): void {}

	/**
	 * @param array<string, mixed> $cron_data subclass-defined cron data bag (keys vary per task chain)
	 */
	protected static function do_after_cron_finished( array $cron_data ): void {}

	/**
	 * An unexpected error aborted the run. Unlike do_after_cron_canceled() this is not a
	 * deliberate stop, so the subclass gets the error message too.
	 *
	 * @param array<string, mixed> $cron_data subclass-defined cron data bag (keys vary per task chain)
	 */
	protected static function do_after_cron_failed( array $cron_data, string $error_message ): void {}

	/**
	 * Cancelling cron can take some time. Check cron status with static::is_canceled()
	 */
	final public static function cancel_cron_execution(): void {

		if ( ! static::is_stopped() ) {

			if ( static::is_executing_now() ) {
				static::set_current_execution_status( self::EXECUTION_STATUS_CANCELING );
			} else {
				static::set_current_execution_status( self::EXECUTION_STATUS_CANCELED );
			}

			static::unschedule_cron();

			if ( static::is_canceled() ) {
				static::do_after_cron_canceled( static::get_cron_data() );
			}
		}
	}


	/**
	 * Terminal failure state: an unexpected error aborted the run. Records the error message,
	 * marks the run `failed` (distinct from a deliberate `canceled`) and stops the schedule.
	 */
	private static function fail_cron_execution( string $error_message = '' ): void {

		static::set_current_execution_error_message( $error_message );
		static::set_current_execution_status( self::EXECUTION_STATUS_FAILED );
		static::unschedule_cron();
		static::do_after_cron_failed( static::get_cron_data(), $error_message );
	}


	public static function get_current_execution_error_message(): string {

		return (string) get_option(
			static::get_cron_action_hook_name() . self::OPTION_NAME_EXECUTION_ERROR_MESSAGE_POSTFIX,
			''
		);
	}

	private static function set_current_execution_error_message( string $error_message ): void {

		update_option(
			static::get_cron_action_hook_name() . self::OPTION_NAME_EXECUTION_ERROR_MESSAGE_POSTFIX,
			$error_message,
			false
		);
	}


	/**
	 * @return array<string, mixed> subclass-defined cron data bag (keys vary per task chain)
	 */
	protected static function get_cron_data(): array {

		$start_task_data = get_option(
			static::get_cron_action_hook_name() . self::OPTION_NAME_CRON_DATA_POSTFIX,
			array()
		);

		return $start_task_data;
	}

	/**
	 * @param array<string, mixed> $cron_data subclass-defined cron data bag (keys vary per task chain)
	 */
	private static function set_cron_data( array $cron_data ): void {

		update_option(
			static::get_cron_action_hook_name() . self::OPTION_NAME_CRON_DATA_POSTFIX,
			$cron_data,
			false
		);
	}

	private static function get_cron_user_id(): int {

		return get_option(
			static::get_cron_action_hook_name() . self::OPTION_NAME_CRON_USER_ID_POSTFIX,
			0
		);
	}

	private static function set_cron_user_id( int $cron_user_id ): void {

		update_option(
			static::get_cron_action_hook_name() . self::OPTION_NAME_CRON_USER_ID_POSTFIX,
			$cron_user_id,
			false
		);
	}

	/**
	 * Atomically acquire the execution lock so only one thread of this cron runs tasks at a time.
	 *
	 * The lock is a single wp_options row whose value is the unix timestamp until which it is held.
	 * Read, write and delete of the lock go through raw SQL only (never the option/transient API), so a
	 * persistent object cache can never serve a stale lock state. The INSERT ... ON DUPLICATE KEY UPDATE
	 * is atomic through the unique index on option_name: of several concurrent requests only one can
	 * insert the row or overwrite an already expired one; the rest see a live lock and fail to acquire.
	 *
	 * @return int the expiry timestamp that was acquired (pass it to release_execution_lock() as the
	 * ownership token), or 0 when the lock is currently held by another thread.
	 */
	private static function acquire_execution_lock(): int {
		global $wpdb;

		$now               = time();
		$lock_expiry_token = $now + self::TASKS_PROCESSING_TIME_LIMIT_IN_SECONDS + 10;

		// phpcs:disable
		$affected_rows = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} ( option_name, option_value, autoload )
				VALUES ( %s, %s, 'no' )
				ON DUPLICATE KEY UPDATE option_value = IF( CAST( option_value AS UNSIGNED ) <= %d, VALUES( option_value ), option_value )",
				static::get_cron_action_hook_name() . self::OPTION_NAME_CRON_LOCK_POSTFIX,
				(string) $lock_expiry_token,
				$now
			)
		);
		// phpcs:enable

		// 1 = row inserted (lock was free), 2 = overwrote an expired lock, 0 = a live lock is held by
		// someone else, false = query error. Only 1 or 2 mean we own the lock now (fail closed otherwise).
		if ( 1 === $affected_rows || 2 === $affected_rows ) {
			return $lock_expiry_token;
		}

		return 0;
	}

	/**
	 * Release the execution lock, but only when the row still holds the exact expiry we acquired. If this
	 * run overran its TTL and another thread meanwhile took a fresh lock, the stored value differs and this
	 * DELETE matches nothing — so a run can never drop a lock that now belongs to someone else.
	 *
	 * @param int $lock_expiry_token expiry timestamp returned by acquire_execution_lock().
	 */
	private static function release_execution_lock( int $lock_expiry_token ): void {
		global $wpdb;

		// phpcs:disable
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				static::get_cron_action_hook_name() . self::OPTION_NAME_CRON_LOCK_POSTFIX,
				(string) $lock_expiry_token
			)
		);
		// phpcs:enable
	}

	private static function is_executing_now(): bool {
		global $wpdb;

		// phpcs:disable
		$lock_expiry = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
				static::get_cron_action_hook_name() . self::OPTION_NAME_CRON_LOCK_POSTFIX
			)
		);
		// phpcs:enable

		return null !== $lock_expiry && (int) $lock_expiry > time();
	}

	final public static function is_started(): bool {
		return self::EXECUTION_STATUS_STARTED === static::get_current_execution_status();
	}

	final public static function is_canceling(): bool {
		return self::EXECUTION_STATUS_CANCELING === static::get_current_execution_status();
	}

	final public static function is_canceled(): bool {
		return self::EXECUTION_STATUS_CANCELED === static::get_current_execution_status();
	}

	final public static function is_finished(): bool {
		return self::EXECUTION_STATUS_FINISHED === static::get_current_execution_status();
	}

	final public static function is_failed(): bool {
		return self::EXECUTION_STATUS_FAILED === static::get_current_execution_status();
	}

	/**
	 * Terminal state: the run has stopped for any reason — completed, deliberately
	 * canceled, or aborted by error. The inverse means a run is still working.
	 */
	final public static function is_stopped(): bool {
		return static::is_finished() || static::is_canceled() || static::is_failed();
	}

	/**
	 * Snapshot of the run for the admin UI to poll.
	 *
	 * @return array{ status: string, progress: int, run_id: string, error_message: string }
	 */
	final public static function get_execution_state(): array {

		// A completed run is always 100%, an interrupted one (canceled or failed) reports
		// nothing — only a working run has a meaningful percentage.
		if ( static::is_finished() ) {

			$progress = 100;

		} elseif ( static::is_stopped() ) {

			$progress = 0;

		} else {

			$progress = (int) floor( static::get_current_execution_percentage() );
		}

		return array(
			'status'        => static::get_current_execution_status(),
			'progress'      => $progress,
			'run_id'        => static::get_current_execution_run_id(),
			'error_message' => static::get_current_execution_error_message(),
		);
	}

	/**
	 * @return string one of status constants in this class
	 */
	public static function get_current_execution_status(): string {

		return get_option(
			static::get_cron_action_hook_name() . self::OPTION_NAME_EXECUTION_STATUS_POSTFIX,
			self::EXECUTION_STATUS_FINISHED
		);
	}

	private static function set_current_execution_status( string $execution_status ): void {

		update_option(
			static::get_cron_action_hook_name() . self::OPTION_NAME_EXECUTION_STATUS_POSTFIX,
			$execution_status,
			false
		);
	}

	/**
	 * Read the execution status straight from the database, bypassing the get_option() request cache.
	 * Used by the double-checked locking in execute_cron(): a thread that read the status at the gate
	 * has it cached for the rest of the request, so only a raw read reflects another thread's write.
	 *
	 * @return string one of status constants in this class
	 */
	private static function get_current_execution_status_from_db(): string {
		global $wpdb;

		// phpcs:disable
		$execution_status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
				static::get_cron_action_hook_name() . self::OPTION_NAME_EXECUTION_STATUS_POSTFIX
			)
		);
		// phpcs:enable

		return null === $execution_status ? self::EXECUTION_STATUS_FINISHED : (string) $execution_status;
	}

	final public static function get_current_execution_percentage(): float {

		return (float) get_option(
			static::get_cron_action_hook_name() . self::OPTION_NAME_EXECUTION_PERCENTAGE_POSTFIX,
			0
		);
	}

	private static function set_current_execution_percentage( float $current_execution_percentage ): void {

		update_option(
			static::get_cron_action_hook_name() . self::OPTION_NAME_EXECUTION_PERCENTAGE_POSTFIX,
			$current_execution_percentage,
			false
		);
	}

	private static function save_new_execution_run_id(): string {

		$run_id = wp_generate_uuid4();
		update_option(
			static::get_cron_action_hook_name() . self::OPTION_NAME_EXECUTION_RUN_ID_POSTFIX,
			$run_id,
			false
		);
		return $run_id;
	}

	public static function get_current_execution_run_id(): string {

		return (string) get_option(
			static::get_cron_action_hook_name() . self::OPTION_NAME_EXECUTION_RUN_ID_POSTFIX,
			''
		);
	}

	/**
	 * If cron does not need to know the exact execution percentage, you can just always return 0 until
	 * all tasks are finished and then return 100.
	 *
	 * @param array<string, mixed> $cron_data subclass-defined cron data bag (keys vary per task chain)
	 * @return float 0.0-100.0 - percentage must be rounded to lower value with PHP_ROUND_HALF_DOWN
	 * to make sure that 100% is calculated when all crons tasks are done!
	 */
	abstract protected function calculate_current_execution_percentage( array $cron_data ): float;

	/**
	 * Process single task of the current task chain.
	 * It has to be as fast as possible (less than 20 seconds)!
	 *
	 * @param array<string, mixed> $cron_data subclass-defined cron data bag (keys vary per task chain)
	 * @return array<string, mixed> of updated (if necessary) cron data
	 * @throws \Exception id something goes wrong
	 */
	abstract protected function do_task( array $cron_data ): array;
}
