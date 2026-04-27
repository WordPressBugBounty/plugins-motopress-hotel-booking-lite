<?php

namespace MPHB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LicenseNotice {

	/**
	 * @since 5.0.0
	 */
	private string $pluginSlug = 'motopress-hotel-booking';

	/**
	 * @since 5.0.0
	 */
	private string $pluginFile = 'motopress-hotel-booking/motopress-hotel-booking.php';

	/**
	 * @since 5.0.0 the <code>$pluginFile</code> parameter was added.
	 *
	 * @param string $pluginFile
	 */
	public function __construct( $pluginFile ) {
		$this->pluginSlug = basename( $pluginFile, '.php' );
		$this->pluginFile = plugin_basename( $pluginFile );

		$this->registerPluginNotice();
		$this->registerAdminNotice();
	}

	/**
	 * @since 5.0.0
	 */
	private function registerPluginNotice() {
		add_action( "after_plugin_row_{$this->pluginFile}", array( $this, 'showPluginNotice' ) );
	}

	/**
	 * @since 5.0.0
	 */
	private function registerAdminNotice() {
		add_action( 'admin_notices', array( $this, 'showAdminNotice' ) );
	}

	/**
	 * @since 5.0.0
	 *
	 * @access private
	 *
	 * @global ?\WP_Plugins_List_Table $wp_list_table Is null when doing search.
	 */
	public function showPluginNotice() {
		global $wp_list_table;

		if ( $wp_list_table === null || ! is_main_site() ) {
			return;
		}

		$hasLicenseKey = ! empty( MPHB()->settings()->license()->getLicenseKey() );
		$licenseStatus = MPHB()->settings()->license()->getLicenseStatus();

		if ( $licenseStatus['status'] == 'valid'
			|| ( $licenseStatus['status'] == 'undefined' && $hasLicenseKey )
		) {
			return;
		}

		$columnsCount = $wp_list_table->get_column_count();

		?>
		<tr class="plugin-update-tr active" id="<?php echo esc_attr( $this->pluginSlug ); ?>-license" data-slug="<?php echo esc_attr( $this->pluginSlug ); ?>" data-plugin="<?php echo esc_attr( $this->pluginFile ); ?>">
			<td colspan="<?php echo esc_attr( $columnsCount ); ?>" class="plugin-update">
				<div class="notice inline notice-warning notice-alt">
					<p>
						<?php
						printf(
							wp_kses(
								__( 'Your License Key is not active. Please, <a href="%s">activate your License Key</a> to get plugin updates.', 'motopress-hotel-booking' ),
								[ 'a' => [ 'href' => [] ] ],
							),
							esc_url( $this->getLicensePageUrl() )
						);
						?>
					</p>
				</div>
			</td>
		</tr>
		<?php

		add_action( 'admin_footer', [ $this, 'printPluginNoticeScript' ] );
	}

	/**
	 * @since 5.0.0
	 *
	 * @access private
	 */
	public function printPluginNoticeScript() {
		?>
		<script type="text/javascript">
			"use strict";

			let pluginRow = document.querySelector( 'tr[data-plugin$="motopress-hotel-booking.php"]' );

			if ( ! pluginRow.classList.contains( 'update' ) ) {
				pluginRow.classList.add( 'update' );

			} else {
				let updateNoticeRow    = pluginRow.nextElementSibling;
				let updateNoticeColumn = updateNoticeRow ? updateNoticeRow.firstElementChild : null;
				let updateNotice       = updateNoticeColumn ? updateNoticeColumn.firstElementChild : null;

				updateNoticeColumn.style.boxShadow = 'none';
				updateNotice.style.marginBottom = '0';
			}
		</script>
		<?php
	}

	/**
	 * @access private
	 *
	 * @global string $pagenow
	 */
	public function showAdminNotice() {

		$isSettingsPage = MPHB()->getSettingsMenuPage()->isCurrentPage();
		$isBookingsPage = MPHB()->postTypes()->booking()->getManagePage()->isCurrentPage();
		$isPaymentsPage = MPHB()->postTypes()->payment()->getManagePage()->isCurrentPage();

		if ( !$isBookingsPage && !$isSettingsPage && !$isPaymentsPage ) {
			return;
		}

		$license = MPHB()->settings()->license()->getLicenseKey();
		$hasLicenseKey = ! empty( $license );
		$licenseStatus = MPHB()->settings()->license()->getLicenseStatus();
		// $licenseStatus['status] = expired, inactive, disabled, site_inactive, invalid, invalid_item_id, item_name_mismatch, undefined, deactivated

		if ( $hasLicenseKey && in_array( $licenseStatus['status'], array( 'valid' ) ) ) {
			return;
		}

		$message = '';
		switch( $licenseStatus['status'] ) {
			case 'inactive':
			case 'deactivated':
			case 'site_inactive':
				$message = sprintf(
					wp_kses(
						__( 'Your License Key is not active for this website. Please <a href="%s">activate your License Key</a> to receive plugin updates and support.', 'motopress-hotel-booking' ),
						array( 'a' => array( 'href' => array() ) ),
					),
					esc_url( $this->getLicensePageUrl() )
				);
				break;
			case 'expired':
				$message = sprintf(
					wp_kses(
						__( 'Your License Key has expired. Please <a href="%s">renew your License Key</a> to continue receiving plugin updates and support.', 'motopress-hotel-booking' ),
						array( 'a' => array( 'href' => array() ) ),
					),
					esc_url( $this->getLicensePageUrl() )
				);
				break;
			case 'disabled':
				$message = sprintf(
					wp_kses(
						__( 'Your License Key has been disabled. Please <a href="%s">check your license details</a> or contact support for assistance.', 'motopress-hotel-booking' ),
						array( 'a' => array( 'href' => array() ) ),
					),
					esc_url( $this->getLicensePageUrl() )
				);
				break;
			case 'invalid':
			case 'invalid_item_id':
			case 'item_name_mismatch':
				$message = sprintf(
					wp_kses(
						__(
							'Your License Key is invalid. Please <a href="%s">enter a valid License Key</a> to enable updates.',
							'motopress-hotel-booking'
						),
						array( 'a' => array( 'href' => array() ) )
					),
					esc_url( $this->getLicensePageUrl() )
				);
				break;
			default :
				$message = __(
					'We could not verify your License Key status. Please try again or contact support if the issue persists.',
					'motopress-hotel-booking'
				);
				break;
		}

		if ( !$hasLicenseKey ) {
			$message = sprintf(
					wp_kses(
						__( 'Your License Key is not set for this website. Please <a href="%s">set and activate your License Key</a> to receive plugin updates and support.', 'motopress-hotel-booking' ),
						array( 'a' => array( 'href' => array() ) ),
					),
					esc_url( $this->getLicensePageUrl() )
				);
		}

		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<b><?php echo esc_html( MPHB()->settings()->license()->getProductName() ); ?>:</b>
				<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $message;
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * @since 5.0.0
	 *
	 * @return string
	 */
	private function getLicensePageUrl() {
		return add_query_arg(
			array(
				'page' => MPHB()->getSettingsMenuPage()->getName(),
				'tab'  => 'license',
			),
			admin_url( 'admin.php' )
		);
	}
}
