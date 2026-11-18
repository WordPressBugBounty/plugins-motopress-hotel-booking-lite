<?php

namespace MPHB\Admin\MenuPages;

use MPHB\Admin\DashboardWidget;
use MPHB\UsersAndRoles\CapabilitiesAndRoles;
use MPHB\Crons\SyncGoogleHotelsDataCron;
use MPHB\GoogleHotels\GoogleHotelsHelper;
use MPHB\iCal\Queue;

class DashboardMenuPage extends AbstractMenuPage {

	public function addActions() {
		parent::addActions();

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueScripts' ) );
	}

	public function onLoad() {}

	public function render() {
		?>
		<div class="wrap">
			<div id="mphb-dashboard"></div>
		</div>
		<?php
	}

	public function enqueueScripts() {
		if ( ! $this->isCurrentPage() ) {
			return;
		}

		add_action( 'admin_print_footer_scripts', array( MPHB()->getPublicScriptManager(), 'localize' ), 0 );

		$assetFile = include MPHB()->getPluginPath( 'assets/dashboard/index.asset.php' );

		wp_enqueue_script(
			'mphb-dashboard',
			MPHB()->getPluginUrl( 'assets/dashboard/index.js' ),
			array_merge( $assetFile['dependencies'], array( 'mphb-global-js' ) ),
			$assetFile['version'],
			true
		);

		wp_set_script_translations( 'mphb-dashboard', 'motopress-hotel-booking', MPHB()->getPluginPath( 'languages/' ) );

		wp_add_inline_script(
			'mphb-dashboard',
			'window.mphbDashboard = ' . wp_json_encode( $this->preloadInitialData() ) . ';',
			'before'
		);

		wp_enqueue_style(
			'mphb-dashboard',
			MPHB()->getPluginUrl( 'assets/dashboard/index.css' ),
			array( 'wp-components' ),
			$assetFile['version']
		);

		do_action( 'mphb_enqueue_admin_dashboard_assets' );
	}

	private function preloadInitialData() {
		$today = wp_date( 'Y-m-d' );

		$currencySettings     = MPHB()->settings()->currency();
		$validBookingStatuses = array( 'pending-user', 'pending-payment', 'pending', 'confirmed' );
		$gatewaysList         = array_map(
			function ( $gateway ) {
				$title = $gateway->getTitle();
				return $title !== '' ? $title : $gateway->getAdminTitle();
			},
			MPHB()->gatewayManager()->getList()
		);

		return array(
			'today'                    => $today,
			'bookings'                 => $this->preloadCollection(
				add_query_arg(
					array(
						'before' => current_datetime()->modify( '+1 day' )->format( 'Y-m-d' ),
						'after'  => current_datetime()->modify( '-30 days' )->format( 'Y-m-d' ),
						'filter' => array(
							'post_status' => $validBookingStatuses,
						),
					),
					'/mphb/v1/bookings'
				)
			),
			'recent_bookings'          => $this->preloadRequest(
				add_query_arg(
					array(
						'per_page' => 5,
						'orderby'  => 'date',
						'order'    => 'desc',
						'filter'   => array(
							'meta_query' => array(
								'relation' => 'OR',
								array(
									'key'     => '_mphb_sync_id',
									'compare' => 'NOT EXISTS',
								),
								array(
									'key'   => '_mphb_sync_id',
									'value' => '',
								),
							),
						),
					),
					'/mphb/v1/bookings'
				)
			),
			'active_bookings'          => $this->preloadCollection(
				add_query_arg(
					array(
						'filter' => array(
							'post_status' => $validBookingStatuses,
							'meta_query'  => array(
								array(
									'key'     => 'mphb_check_out_date',
									'value'   => $today,
									'compare' => rawurlencode( '>=' ),
								),
								array(
									'key'     => 'mphb_check_in_date',
									'value'   => $today,
									'compare' => rawurlencode( '<=' ),
								),
							),
						),
					),
					'/mphb/v1/bookings'
				)
			),
			'payments'                 => $this->preloadRequest(
				add_query_arg(
					array(
						'orderby'  => 'date',
						'order'    => 'desc',
						'per_page' => 5,
						'filter'   => array( 'post_status' => 'any' ),
					),
					'/mphb/v1/payments'
				)
			),
			'accommodation_types'      => $this->preloadCollection( '/mphb/v1/accommodation_types' ),
			'service_titles'           => MPHB()->getServiceRepository()->getIdTitleList(),
			'accommodations'           => $this->preloadCollection( '/mphb/v1/accommodations' ),
			'blocks'                   => $this->preloadRequest(
				add_query_arg(
					array(
						'per_page'  => -1,
						'date_from' => $today,
						'date_to'   => $today,
					),
					'/mphb/v1/booking_rules/blocks'
				)
			),
			'links'                    => $this->getQuickLinks(),
			'news'                     => $this->getLatestNews(),
			'news_buttons'             => $this->getLatestNewsButtons(),
			'calendar_sync'            => $this->getCalendarSyncStatus(),
			'calendar_sync_configured' => ! empty( MPHB()->getSyncUrlsRepository()->getAllRoomIds() ),
			'google_hotels_sync'       => $this->getGoogleHotelsSyncStatus(),
			'google_hotels_enabled'    => GoogleHotelsHelper::isIntegrationOn(),
			'admin_url'                => admin_url(),
			'is_lite'                  => defined( 'MPHB_IS_LITE' ),
			'license_status'           => $this->getLicenseStatus(),
			'widgets'                  => $this->getWidgets(),
			'disabled_widgets'         => apply_filters( 'mphb_dashboard_disabled_widgets', array() ),
			'settings'                 => array(
				'currency'                  => $currencySettings->getCurrencyCode(),
				'currency_position'         => $currencySettings->getCurrencyPosition(),
				'currency_symbol'           => $currencySettings->getCurrencySymbol(),
				'price_decimals_count'      => $currencySettings->getPriceDecimalsCount(),
				'price_decimals_separator'  => $currencySettings->getPriceDecimalsSeparator(),
				'price_thousands_separator' => $currencySettings->getPriceThousandSeparator(),
			),
			'gateways'                 => $gatewaysList,
		);
	}

	private function getWidgets() {
		$widgets = apply_filters( 'mphb_dashboard_widgets', array() );

		if ( ! is_array( $widgets ) ) {
			return array();
		}

		$widgetIds = array();
		$valid     = array();

		foreach ( $widgets as $widget ) {
			if (
				! is_array( $widget ) ||
				! isset( $widget['id'], $widget['area'], $widget['callback'] ) ||
				! is_callable( $widget['callback'] ) ||
				isset( $widgetIds[ $widget['id'] ] )
			) {
				continue;
			}

			try {
				$html = call_user_func( $widget['callback'] );
			} catch ( \Throwable $e ) {
				continue;
			}

			if ( ! is_string( $html ) ) {
				continue;
			}

			$priority = isset( $widget['priority'] ) && is_numeric( $widget['priority'] ) ? (float) $widget['priority'] : 10;

			if ( ! is_finite( $priority ) ) {
				continue;
			}

			$widgetIds[ $widget['id'] ] = true;
			$valid[]                    = array(
				'id'         => $widget['id'],
				'title'      => isset( $widget['title'] ) && is_string( $widget['title'] ) ? $widget['title'] : '',
				'area'       => $widget['area'],
				'priority'   => $priority,
				'html'       => wp_kses_post( $html ),
				'attributes' => isset( $widget['attributes'] ) && is_array( $widget['attributes'] ) ? $widget['attributes'] : array(),
			);
		}

		return $valid;
	}

	private function getGoogleHotelsSyncStatus() {
		$lastSyncDateTime = SyncGoogleHotelsDataCron::getLastSyncAttemptDateTimeInWPTimezone();

		if ( is_null( $lastSyncDateTime ) ) {
			return null;
		}

		$status = 'unknown';

		if ( SyncGoogleHotelsDataCron::isServerStatusUpdated() ) {
			$status = 'updated';
		} elseif ( SyncGoogleHotelsDataCron::isServerStatusFailed() ) {
			$status = 'failed';
		}

		return array(
			'date'    => $lastSyncDateTime->getTimestamp() * 1000,
			'message' => wp_strip_all_tags( SyncGoogleHotelsDataCron::getLastSyncAttemptMessage() ),
			'status'  => $status,
		);
	}

	private function getCalendarSyncStatus() {
		if ( defined( 'MPHB_IS_LITE' ) ) {
			return null;
		}

		$lastSync = Queue::getLastSyncRun();

		if ( is_null( $lastSync ) ) {
			return null;
		}

		return array(
			'date'    => $lastSync['timestamp'] * 1000,
			'status'  => $lastSync['status'],
			'succeed' => $lastSync['succeed'],
			'failed'  => $lastSync['failed'],
			'removed' => $lastSync['removed'],
			'skipped' => $lastSync['skipped'],
		);
	}

	private function getLatestNews() {
		$feed = DashboardWidget::getFeed();

		return is_array( $feed ) && ! empty( $feed['news'] ) && is_array( $feed['news'] )
			? $feed['news']
			: array();
	}

	private function getLatestNewsButtons() {
		$feed    = DashboardWidget::getFeed();
		$buttons = is_array( $feed ) && ! empty( $feed['buttons'] ) && is_array( $feed['buttons'] )
			? $feed['buttons']
			: array();

		return array_values(
			array_filter(
				$buttons,
				function ( $button ) {
					return empty( $button['is_lite'] ) || defined( 'MPHB_IS_LITE' );
				}
			)
		);
	}

	private function preloadCollection( $path ) {
		$path        = add_query_arg( 'per_page', 100, $path );
		$preloadData = rest_preload_api_request( array(), $path );
		$items       = $preloadData[ $path ]['body'] ?? array();
		$totalPages  = (int) ( $preloadData[ $path ]['headers']['X-WP-TotalPages'] ?? 1 );

		for ( $page = 2; $page <= $totalPages; $page++ ) {
			$pagePath    = add_query_arg( 'page', $page, $path );
			$pagePreload = rest_preload_api_request( array(), $pagePath );
			$pageItems   = $pagePreload[ $pagePath ]['body'] ?? array();
			$items       = array_merge( $items, $pageItems );
		}

		return $items;
	}

	private function preloadRequest( $path ) {
		$preloadData = rest_preload_api_request( array(), $path );

		return $preloadData[ $path ]['body'] ?? array();
	}

	private function getQuickLinks() {
		$links = array();

		if ( ! defined( 'MPHB_IS_LITE' ) && $this->canEditPostType( MPHB()->postTypes()->booking()->getPostType() ) ) {
			$links['new-booking'] = array(
				'key'   => 'new-booking',
				'label' => __( 'New Booking', 'motopress-hotel-booking' ),
				'url'   => admin_url( 'admin.php?page=mphb_add_new_booking' ),
			);
		}

		if ( current_user_can( CapabilitiesAndRoles::VIEW_CALENDAR ) ) {
			$links['calendar'] = array(
				'key'   => 'calendar',
				'label' => __( 'Calendar', 'motopress-hotel-booking' ),
				'url'   => admin_url( 'admin.php?page=mphb_calendar&beta' ),
			);
		}

		foreach ( array(
			'bookings'            => array( MPHB()->postTypes()->booking()->getPostType(), __( 'Bookings', 'motopress-hotel-booking' ) ),
			'payments'            => array( MPHB()->postTypes()->payment()->getPostType(), __( 'Payments', 'motopress-hotel-booking' ) ),
			'accommodation-types' => array( MPHB()->postTypes()->roomType()->getPostType(), __( 'Accommodations', 'motopress-hotel-booking' ) ),
		) as $key => $link ) {
			if ( $this->canEditPostType( $link[0] ) ) {
				$links[ $key ] = array(
					'key'   => $key,
					'label' => $link[1],
					'url'   => admin_url( 'edit.php?post_type=' . $link[0] ),
				);
			}
		}

		if ( current_user_can( CapabilitiesAndRoles::MANAGE_SETTINGS ) ) {
			$links['settings'] = array(
				'key'   => 'settings',
				'label' => __( 'Settings', 'motopress-hotel-booking' ),
				'url'   => admin_url( 'admin.php?page=mphb_settings' ),
			);
		}

		return (object) $links;
	}

	private function canEditPostType( $postType ) {
		$postTypeObject = get_post_type_object( $postType );

		return $postTypeObject && current_user_can( $postTypeObject->cap->edit_posts );
	}

	private function getLicenseStatus() {
		$license          = MPHB()->settings()->license()->getLicenseKey();
		$hasLicenseKey    = ! empty( $license );
		$licenseStatus    = MPHB()->settings()->license()->getLicenseStatus();
		$licenseIsVisible = MPHB()->settings()->license()->isVisible();

		$current        = get_site_transient( 'update_plugins' );
		$baseName       = plugin_basename( MPHB_PLUGIN_FILE );
		$nextVersion    = '';
		$currentVersion = MPHB()->getVersion();

		if ( isset( $current->response[ $baseName ] ) ) {
			$nextVersion = $current->response[ $baseName ]->new_version;
		}

		return array(
			'has_key'          => $hasLicenseKey,
			'is_visible'       => $licenseIsVisible,
			'status'           => $licenseStatus,
			'next_version'     => $nextVersion,
			'current_version'  => $currentVersion,
			'update_available' => version_compare( $nextVersion, $currentVersion, '>' ),
		);
	}

	protected function getMenuTitle() {
		return __( 'Dashboard', 'motopress-hotel-booking' ) . ' (beta)';
	}

	protected function getPageTitle() {
		return __( 'Dashboard', 'motopress-hotel-booking' );
	}
}
