<?php

declare(strict_types=1);

namespace MPHB\Crons;

use MPHB\GoogleHotels\{ GoogleHotelsHelper, RoomTypeSnapshotHelper };

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StartGoogleHotelsCalendarSyncCron extends AbstractCron {
	public function doCronJob() {
		if (
			! GoogleHotelsHelper::isIntegrationOn()
			// When all accommodation types are unchecked, the integration is
			// marked as disabled, but the deleted rooms still need to be sent
			// to the server
			&& ! RoomTypeSnapshotHelper::haveRoomTypesToDelete()
		) {
			$this->unschedule();

			return;
		}

		if ( SyncGoogleHotelsCalendarCron::is_stopped() ) {
			SyncGoogleHotelsCalendarCron::start_cron_execution();
		}
	}
}
