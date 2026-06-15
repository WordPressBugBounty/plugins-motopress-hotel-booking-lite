<?php

namespace MPHB\Admin\MenuPages;

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
				if ( ! apply_filters( 'mphb_use_google_hotels', true ) ) {
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
