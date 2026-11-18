<?php

/**
 * Available variables
 * - int $foundRooms Count of found rooms
 * - string $checkInDate Date in human-readable format
 * - string $checkOutDate Date in human-readable format
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<p class="mphb-search-results-summary">
	<?php
	if ( $foundRooms > 0 ) {
		// translators: %s: Formatted number of matching accommodations.
		echo esc_html( sprintf( _n( '%s accommodation found', '%s accommodations found', $foundRooms, 'motopress-hotel-booking' ), $foundRooms ) );
	} else {
		// translators: No matching physical accommodation records; also used in admin search. Does not necessarily mean all units are booked.
		esc_html_e( 'No accommodations found', 'motopress-hotel-booking' );
	}

		// translators: Unnumbered placeholders in order: stay start date, stay end date. Preserve the leading space.
		echo esc_html( sprintf( __( ' from %s - till %s', 'motopress-hotel-booking' ), $checkInDate, $checkOutDate ) );
	?>
</p>
