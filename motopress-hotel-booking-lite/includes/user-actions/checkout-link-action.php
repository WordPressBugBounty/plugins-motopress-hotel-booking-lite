<?php

namespace MPHB\UserActions;

defined( 'ABSPATH' ) || exit;

/**
 * Opens the checkout page with the reservation from the link, e.g.
 * ?mphb_action=checkout&mphb_check_in_date=2026-10-04&mphb_check_out_date=2026-10-06
 * &mphb_room_details[0][room_type_id]=999&mphb_room_details[0][adults]=2&mphb_room_details[0][children]=1
 *
 * Every other parameter (utm_* included) is carried over to the checkout page,
 * which reads the reservation from the address itself. Only the home page takes
 * the link: anywhere else the parameter is left to the page it came with.
 */
class CheckoutLinkAction {

	const QUERY_ACTION = 'checkout';

	public function __construct() {

		if ( isset( $_GET['mphb_action'] ) && self::QUERY_ACTION === $_GET['mphb_action'] ) {
			add_action( 'init', array( $this, 'redirectToCheckout' ), 15 );
		}
	}

	public function redirectToCheckout(): void {

		if ( ! $this->isHomePageRequest() ) {
			return;
		}

		// Without the checkout page the visitor stays where the link led.
		if ( ! MPHB()->settings()->pages()->getCheckoutPageId() ) {
			return;
		}

		$queryArgs = stripslashes_deep( $_GET );
		unset( $queryArgs['mphb_action'] );

		wp_safe_redirect( add_query_arg( urlencode_deep( $queryArgs ), MPHB()->settings()->pages()->getCheckoutPageUrl() ) );
		exit;
	}

	/**
	 * Through home_url(): it carries the directory WordPress is installed in,
	 * the multisite subsite path and the WPML language directory, and does not
	 * depend on the "Your homepage displays" reading setting.
	 */
	private function isHomePageRequest(): bool {
		$requestPath = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
		$homePath    = wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		return untrailingslashit( (string) $homePath ) === untrailingslashit( (string) $requestPath );
	}
}
