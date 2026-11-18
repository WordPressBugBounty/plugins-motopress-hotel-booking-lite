<?php

namespace MPHB\Emails\Booking\Customer;

class ApprovedEmail extends BaseEmail {

	public function getDefaultMessageHeaderText() {
		return __( 'Your booking is approved', 'motopress-hotel-booking' );
	}

	public function getDefaultSubject() {
		// translators: Email subject. Keep %site_title% and %booking_id% replacement tags unchanged.
		return __( '%site_title% - Your booking #%booking_id% is approved', 'motopress-hotel-booking' );
	}

	protected function initDescription() {
		$this->description = __( 'Email that will be sent to customer when booking is approved.', 'motopress-hotel-booking' );
	}

	protected function initLabel() {
		$this->label = __( 'Approved Booking Email', 'motopress-hotel-booking' );
	}
}
