<?php

declare(strict_types=1);

namespace MPHB\Upgrades;

use MPHB\Utils\ValidateUtils;
use MPHB\BackgroundPausableProcess;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Moves blocks ("custom rules") from WP options to custom table.
 */
class BackgroundBookingUpgrader_6_0_0 extends BackgroundPausableProcess {
	public const BATCH_SIZE = 250;

	private static ?array $allRules = null;

	/**
	 * @param array $item {
	 *     @type int $items_finished
	 *     @type int $items_total
	 *     @type int $page           1..N
	 * }
	 */
	protected function task( $item ) {
		if ( self::$allRules === null ) {
			self::$allRules = MPHB()->settings()->bookingRules()->getCustomRules();
		}

		$page = $item['page'];
		$itemsPerPage = self::BATCH_SIZE;

		$batch = array_slice( self::$allRules, ( $page - 1 ) * $itemsPerPage, $itemsPerPage );

		static::saveAsBlocks( $batch );

		$item['items_finished'] += count( $batch );
		$item['page'] += 1;

		if ( $item['items_finished'] === $item['items_total'] ) {
			return false; // Finished
		} else {
			return $item; // Next page
		}
	}

	public static function saveAsBlocks( array $batch ): void {
		$blocks = ValidateUtils::convertCustomBookingRulesToBlocks( $batch );

		MPHB()->getBlocksRepository()->insertItems( $blocks );
	}
}
