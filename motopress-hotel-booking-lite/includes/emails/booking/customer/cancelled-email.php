<?php

namespace MPHB\Emails\Booking\Customer;

class CancelledEmail extends BaseEmail {

	public function getDefaultMessageHeaderText() {
		return __( 'Your booking is cancelled', 'motopress-hotel-booking' );
	}

	public function getDefaultSubject() {
		// translators: Email subject. Keep %site_title% and %booking_id% replacement tags unchanged.
		return __( '%site_title% - Your booking #%booking_id% is cancelled', 'motopress-hotel-booking' );
	}

	protected function initDescription() {
		$this->description = __( 'Email that will be sent to customer when booking is cancelled.', 'motopress-hotel-booking' );
	}

	protected function initLabel() {
		$this->label = __( 'Cancelled Booking Email', 'motopress-hotel-booking' );
	}
}
