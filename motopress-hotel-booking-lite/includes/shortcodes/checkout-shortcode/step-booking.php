<?php

declare(strict_types=1);

namespace MPHB\Shortcodes\CheckoutShortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The reservation has been made, but there is no redirect to the "Reservation
 * Received" page because this is not a confirmation by payment.
 *
 * Previously, this was the StepComplete.
 */
class StepBooking extends Step {
	public function setup() {
		// Clear cookies from checkout
		mphb_unset_cookie( 'mphb_rooms_details' );
		mphb_unset_cookie( 'mphb_check_in_date' );
		mphb_unset_cookie( 'mphb_check_out_date' );
	}

	public function render() {
		$this->showSuccessMessage();
	}
}
