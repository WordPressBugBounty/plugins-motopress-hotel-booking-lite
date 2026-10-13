<?php

namespace MPHB\Admin\MenuPages;

use MPHB\BookingsCalendar;

class CalendarMenuPage extends AbstractMenuPage {

	private $newInterfaceUrlParam = 'beta';
	private $calendar;

	public function addActions() {
		parent::addActions();

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAdminScripts' ), 15 );
		add_filter( 'parent_file', array( $this, 'parentFile' ), 10, 1 );
	}

	public function setupCalendar() {
		$this->calendar = new BookingsCalendar();
	}

	public function enqueueAdminScripts() {

		if ( ! $this->isCurrentPage() ) {
			return;
		}

		if ( $this->isNewInterface() ) {
			$this->enqueueNewInterfaceAdminScripts();
			return;
		}

		MPHB()->getAdminScriptManager()->enqueue();
	}

	public function render() {

		if ( $this->isNewInterface() ) {
			$this->renderNewInterface();
			return;
		}


		// #lite
		//      $this->addTitleAction( __( 'New Booking', 'motopress-hotel-booking' ), '#', array( 'class' => 'button-disabled', 'after' => mphb_upgrade_to_premium_message() ) );

		$this->setupCalendar();
		?>
		<div class="wrap">
			<h1 class="mphb-booking-calendar-title wp-heading-inline"><?php esc_html_e( 'Booking Calendar', 'motopress-hotel-booking' ); ?></h1>
			<?php
			$this->calendar->render();
			?>
		</div>
		<?php
	}

	public function onLoad() {

		if ( $this->isNewInterface() ) {
			return;
		}

		if ( ! BookingsCalendar::hasEnoughFilterData() ) {

			$redirectToCustomPeriod = add_query_arg(
				array(
					'page'   => $this->getName(),
					'period' => MPHB()->settings()->main()->getDefaultCalendarPeriod(),
				),
				admin_url( 'admin.php' )
			);

			wp_safe_redirect( $redirectToCustomPeriod );
		}
	}

	protected function getMenuTitle() {
		return __( 'Calendar', 'motopress-hotel-booking' );
	}

	protected function getPageTitle() {
		return __( 'Booking Calendar', 'motopress-hotel-booking' );
	}

	private function isNewInterface() {
		return isset( $_GET[ $this->newInterfaceUrlParam ] );
	}

	private function renderNewInterface() {
		?>
		<div id="mphb-booking-calendar">
		</div>
		<?php
	}

	private function enqueueNewInterfaceAdminScripts() {
		add_action( 'admin_print_footer_scripts', array( MPHB()->getPublicScriptManager(), 'localize' ), 0 );

		$asset_file = include MPHB()->getPluginPath( 'assets/calendar/index.asset.php' );

		wp_enqueue_script(
			'mphb-new-admin-calendar',
			MPHB()->getPluginUrl( 'assets/calendar/index.js' ),
			array_merge( $asset_file['dependencies'], array( 'mphb-global-js' ) ),
			$asset_file['version'],
			true
		);

		$initial_config = $this->preloadInitialConfig();
		wp_add_inline_script(
			'mphb-new-admin-calendar',
			sprintf( 'window.mphbCalendarInitialData = %s;', wp_json_encode( $initial_config ) ),
			'before'
		);

		wp_set_script_translations( 'mphb-new-admin-calendar', 'motopress-hotel-booking' );

		wp_enqueue_style(
			'mphb-new-admin-calendar',
			MPHB()->getPluginUrl( 'assets/calendar/index.css' ),
			array( 'wp-components', 'wp-editor' ),
			$asset_file['version'],
		);

		$guide_asset_file = include MPHB()->getPluginPath( 'assets/calendar/index.asset.php' );
		wp_enqueue_script(
			'mphb-new-admin-calendar-guide',
			MPHB()->getPluginUrl( 'assets/guide/index.js' ),
			array_merge( array( 'mphb-new-admin-calendar' ), $guide_asset_file['dependencies'] ),
			$guide_asset_file['version'],
			true
		);

		wp_set_script_translations( 'mphb-new-admin-calendar-guide', 'motopress-hotel-booking' );

		wp_enqueue_style(
			'mphb-new-admin-calendar-guide',
			MPHB()->getPluginUrl( 'assets/guide/index.css' ),
			array( 'mphb-new-admin-calendar' ),
			$guide_asset_file['version'],
		);

		wp_localize_script(
			'mphb-new-admin-calendar-guide',
			'mphbGuide',
			array(
				'guideID' => 'bookingCalendar',
			)
		);
	}

	/**
	 * Set correct active/current submenu in the WordPress Admin menu for new interface
	 */
	public function parentFile( $parent_file ) {

		global $submenu_file;

		if ( $this->isCurrentPage() && $this->isNewInterface() ) {
			$submenu_file = 'admin.php?page=' . $this->name . '&beta';
		}

		return $parent_file;
	}

	private function preloadInitialConfig() {
		$preload_paths = array(
			'settings' => '/mphb/v1/settings',
		);

		$collection_paths = array(
			'seasons'             => '/mphb/v1/seasons',
			'accommodation_types' => '/mphb/v1/accommodation_types',
			'accommodations'      => '/mphb/v1/accommodations',
		);

		$initial_config = array();

		foreach ( $preload_paths as $key => $preload_path ) {
			$preload_data           = rest_preload_api_request( array(), $preload_path );
			$initial_config[ $key ] = $preload_data[ $preload_path ]['body'] ?? array();
		}

		foreach ( $collection_paths as $key => $collection_path ) {
			$first_page_path        = add_query_arg( 'per_page', 100, $collection_path );
			$preload_data           = rest_preload_api_request( array(), $first_page_path );
			$total_pages            = (int) ( $preload_data[ $first_page_path ]['headers']['X-WP-TotalPages'] ?? 1 );
			$initial_config[ $key ] = $preload_data[ $first_page_path ]['body'] ?? array();

			for ( $page = 2; $page <= $total_pages; $page++ ) {
				$page_path              = add_query_arg(
					array(
						'per_page' => 100,
						'page'     => $page,
					),
					$collection_path
				);
				$preload_data           = rest_preload_api_request( array(), $page_path );
				$initial_config[ $key ] = array_merge(
					$initial_config[ $key ],
					$preload_data[ $page_path ]['body'] ?? array()
				);
			}
		}

		return $initial_config;
	}
}
