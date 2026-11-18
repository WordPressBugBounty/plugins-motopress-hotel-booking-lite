<?php

namespace MPHB\Admin\ManageCPTPages;

use \MPHB\Entities;

class ServiceManageCPTPage extends ManageCPTPage {

	public function __construct( $postType, $atts = array() ) {
		parent::__construct( $postType, $atts );
		$this->description = __( 'Services are extra offers that you can sell or give for free. E.g. Thai massage, transfer, babysitting. Guests can pre-order them when placing a booking.', 'motopress-hotel-booking' );
	}

	public function filterColumns( $columns ) {
		$customColumns = array(
			'price'               => __( 'Price', 'motopress-hotel-booking' ),
			// translators: Service pricing frequency: once per stay, per night, or a quantity chosen by the guest.
			'price_periodicity'   => __( 'Periodicity', 'motopress-hotel-booking' ),
			// translators: Service pricing basis: per guest or per accommodation. Not a button to capture a payment.
			'price_repeatability' => __( 'Charge', 'motopress-hotel-booking' ),
		);
		$offset        = array_search( 'date', array_keys( $columns ) ); // Set custom columns position before "DATE" column
		$columns       = array_slice( $columns, 0, $offset, true ) + $customColumns + array_slice( $columns, $offset, count( $columns ) - 1, true );

		return $columns;
	}

	public function filterSortableColumns( $columns ) {
		$columns['price'] = 'mphb_price';

		return $columns;
	}

	public function renderColumns( $column, $postId ) {
		$service = MPHB()->getServiceRepository()->findById( $postId );
		switch ( $column ) {
			case 'price':
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $service->getPriceHTML();
				break;
			case 'price_periodicity':
				if ( $service->isPayPerNight() ) {
					// translators: Service charge for each night of the stay; the underlying pricing period is per night.
					esc_html_e( 'Per Day', 'motopress-hotel-booking' );
				} elseif ( $service->isFlexiblePay() ) {
					// translators: Service pricing option in which the guest chooses the quantity to purchase.
					esc_html_e( 'Guest Choice', 'motopress-hotel-booking' );
				} else {
					// translators: Service is charged once for the stay rather than for every night.
					esc_html_e( 'Once', 'motopress-hotel-booking' );
				}
				break;
			case 'price_repeatability':
				echo $service->isPayPerAdult() ? /* translators: Service price applies separately to each guest. */ esc_html__( 'Per Guest', 'motopress-hotel-booking' ) : /* translators: Service price applies separately to each booked accommodation, regardless of guest count. */ esc_html__( 'Per Accommodation', 'motopress-hotel-booking' );
				break;
		}
	}

}
