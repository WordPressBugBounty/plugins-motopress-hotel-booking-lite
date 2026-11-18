<?php
/**
 * The Template for Direct Bank Transfer Instructions email content.
 * This email is sent to customer after booking is placed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<?php echo esc_html( sprintf( /* translators: %1$s: Customer first name. %2$s: Customer last name. Use a greeting that does not require changing the supplied names. */ __( 'Dear %1$s %2$s, we received your request for reservation.', 'motopress-hotel-booking' ), '%customer_first_name%', '%customer_last_name%' ) ); ?>
<br/><br/>
<?php esc_html_e( 'To confirm your booking, please follow the instructions below for payment.', 'motopress-hotel-booking' ); ?>
<br/>
<?php echo esc_html( sprintf( /* translators: %s: Formatted monetary payment amount. */ __( 'Amount: %s', 'motopress-hotel-booking' ), '%payment_amount%' ) ); ?>
<br/>
%payment_instructions%
<h4><?php esc_html_e( 'Details of booking', 'motopress-hotel-booking' ); ?></h4>
<?php echo esc_html( sprintf( /* translators: %s: Booking record ID. */ __( 'ID: #%s', 'motopress-hotel-booking' ), '%booking_id%' ) ); ?>
<br/>
<?php echo esc_html( sprintf( /* translators: %1$s: Check-in date. %2$s: Earliest check-in time. */ __( 'Check-in: %1$s, from %2$s', 'motopress-hotel-booking' ), '%check_in_date%', '%check_in_time%' ) ); ?>
<br/>
<?php echo esc_html( sprintf( /* translators: %1$s: Check-out date. %2$s: Latest check-out time. */ __( 'Check-out: %1$s, until %2$s', 'motopress-hotel-booking' ), '%check_out_date%', '%check_out_time%' ) ); ?>
<br/>
%reserved_rooms_details%
<h4><?php esc_html_e( 'Total Price:', 'motopress-hotel-booking' ); ?></h4>
%booking_total_price%
<br/>
<h4><?php esc_html_e( 'Customer Information', 'motopress-hotel-booking' ); ?></h4>
<?php echo esc_html( sprintf( /* translators: %1$s: Customer first name. %2$s: Customer last name. Keep names in the supplied form. */ __( 'Name: %1$s %2$s', 'motopress-hotel-booking' ), '%customer_first_name%', '%customer_last_name%' ) ); ?>
<br/>
<?php echo esc_html( sprintf( /* translators: %s: Customer email address. */ __( 'Email: %s', 'motopress-hotel-booking' ), '%customer_email%' ) ); ?>
<br/>
<?php echo esc_html( sprintf( /* translators: %s: Customer phone number. */ __( 'Phone: %s', 'motopress-hotel-booking' ), '%customer_phone%' ) ); ?>
<br/>
<?php echo esc_html( sprintf( /* translators: %s: Note entered by the customer. */ __( 'Note: %s', 'motopress-hotel-booking' ), '%customer_note%' ) ); ?>
<br/><br/>
<?php esc_html_e( 'Thank you!', 'motopress-hotel-booking' ); ?>
