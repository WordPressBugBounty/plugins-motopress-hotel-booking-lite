<?php

namespace MPHB\Admin\MenuPages;

use MPHB\Crons\SyncGoogleHotelsDataCron;

defined( 'ABSPATH' ) || exit;


class GoogleHotelsMenuPage extends AbstractMenuPage {

	private const OPTION_NAME_GOOGLE_HOTELS_ATTENSION_REQUIRED = 'mphb_gh_attention_required';


	protected function getMenuTitle() {

		$menu_title = __( 'Google Hotels', 'motopress-hotel-booking' );

		$isGoogleHotelsAttenstionRequired = get_option(
			self::OPTION_NAME_GOOGLE_HOTELS_ATTENSION_REQUIRED,
			true
		);

		if ( $isGoogleHotelsAttenstionRequired ) {

			$menu_title .= ' <span class="menu-counter">1</span>';
		}

		return $menu_title;
	}

	protected function getPageTitle() {
		return __( 'Google Hotels', 'motopress-hotel-booking' );
	}

	public function addActions() {

		parent::addActions();

		add_action(
			'admin_menu',
			function () {
				if ( ! $this->isGoogleHotelsAvailable() ) {
					remove_action( 'admin_menu', array( $this, 'createMenu' ), $this->order );
				}
			},
			10 // Priority must be lower than the menu item order
		);

		add_action(
			'admin_enqueue_scripts',
			function () {

				if ( ! $this->isCurrentPage() ) {
					return;
				}

				wp_enqueue_media();

				// we need global script
				MPHB()->getPublicScriptManager()->register();
				MPHB()->getPublicScriptManager()->localize();

				wp_enqueue_style(
					'mphb-google-hotels-menu-page-css',
					MPHB()->getPluginUrl( 'assets/js/google-hotels-menu-page/index.css' ),
					array(),
					MPHB()->getVersion(),
				);

				wp_enqueue_script(
					'mphb-google-hotels-menu-page-js',
					MPHB()->getPluginUrl( 'assets/js/google-hotels-menu-page/index.js' ),
					array( 'mphb-global-js' ),
					MPHB()->getVersion(),
					true
				);
			}
		);

		add_action(
			'admin_bar_menu',
			function ( \WP_Admin_Bar $adminBar ): void {
				$this->showStatusIndicator( $adminBar );
			},
			100
		);

		add_action(
			'admin_notices',
			function (): void {
				$this->showServerFailedNotice();
			}
		);
	}

	/**
	 * Shows an admin notice on the Bookings, Settings and Calendar pages when the
	 * last Google Hotels server response failed and the integration is enabled.
	 */
	private function showServerFailedNotice(): void {

		if ( ! $this->isGoogleHotelsAvailable()
			|| ! MPHB()->settings()->main()->isGoogleHotelsIntegrationOn()
			|| ! current_user_can( $this->capability )
			|| ! SyncGoogleHotelsDataCron::isServerStatusFailed()
		) {
			return;
		}

		$currentScreen = get_current_screen();

		if ( null === $currentScreen ) {
			return;
		}

		// Settings and Calendar menu pages.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		// Show only on Bookings (list and edit), Settings and Calendar pages.
		$isTargetPage = 'mphb_booking' === $currentScreen->post_type
			|| in_array( $page, array( 'mphb_settings', 'mphb_calendar' ), true );

		if ( ! $isTargetPage ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			sprintf(
				// translators: %1$s opening link tag to the Google Hotels page, %2$s closing link tag.
				wp_kses_post(
					__( '<strong>Hotel Booking:</strong> The Google Hotels information is incomplete. Please complete all required fields and %1$s submit the form again %2$s.', 'motopress-hotel-booking' )
				),
				'<a href="' . esc_url( $this->getUrl() ) . '">',
				'</a>'
			)
		);
	}

	private function showStatusIndicator( \WP_Admin_Bar $adminBar ): void {

		if ( ! $this->isGoogleHotelsAvailable()
			|| ! MPHB()->settings()->main()->isGoogleHotelsIntegrationOn()
			|| ! current_user_can( $this->capability )
		) {
			return;
		}

		if ( SyncGoogleHotelsDataCron::isServerStatusFailed() ) {
			$color = '#d63638'; // red
		} elseif ( SyncGoogleHotelsDataCron::isServerStatusUpdated() ) {
			$color = '#46b450'; // green
		} else {
			$color = '#a7aaad'; // gray
		}

		$title = sprintf(
			'<span style="display:flex;align-items:center;gap:6px;"><span style="width:10px;height:10px;border-radius:50%%;background:%2$s;"></span>%1$s</span>',
			esc_html__( 'Google Hotels', 'motopress-hotel-booking' ),
			esc_attr( $color )
		);

		$adminBar->add_node(
			array(
				'id'    => 'mphb_google_hotels_status',
				'title' => $title,
				'href'  => $this->getUrl(),
				'meta'  => array(
					'title' => __( 'Google Hotels Integration Status', 'motopress-hotel-booking' ),
				),
			)
		);
	}

	private function isGoogleHotelsAvailable(): bool {
		return (bool) apply_filters( 'mphb_use_google_hotels', true );
	}

	public function onLoad() {
		if ( ! $this->isCurrentPage() ) {
			return;
		}
	}

	public function render() {

		// hide red circle
		update_option(
			self::OPTION_NAME_GOOGLE_HOTELS_ATTENSION_REQUIRED,
			0
		);

		$locale = get_locale();
		require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		$availableTranslations = \wp_get_available_translations();
		$websiteLanguageName   = $locale;

		if ( 'en_US' === $locale ) {
			$websiteLanguageName = 'English (United States)';
		} elseif ( isset( $availableTranslations[ $locale ] ) ) {
			$websiteLanguageName = $availableTranslations[ $locale ]['native_name'];
		}

		$config = array(
			'pluginUrl'           => MPHB()->getPluginUrl( '/' ),
			'locale'              => $locale,
			'websiteLanguageName' => $websiteLanguageName,
		);
		?>

		<div
			id="mphb-google-hotels-settings"
			data-config='<?php echo esc_attr( wp_json_encode( $config ) ); ?>'
		></div>

		<?php
	}
}
