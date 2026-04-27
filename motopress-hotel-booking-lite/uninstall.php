<?php

/**
 * Hotel Booking Uninstall
 *
 * Uninstalling Hotel Boooking deletes user roles and capabilities.
 *
 * @since 4.0.0
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once 'motopress-hotel-booking.php';
require_once 'plugin.php';

global $wpdb;

if ( is_plugin_active_for_network( 'motopress-hotel-booking/motopress-hotel-booking.php' ) && is_multisite() ) {

	$limit   = apply_filters( 'mphb_multisite_limit', 100 );
	$blogIds = $wpdb->get_col( sprintf( "SELECT blog_id FROM $wpdb->blogs LIMIT %d", $limit ) );

	foreach ( $blogIds as $blogId ) {

		switch_to_blog( $blogId );

		// Roles + caps.
		\MPHB\UsersAndRoles\CapabilitiesAndRoles::removeUserRoles();
		restore_current_blog();
	}
} else {

	// Roles + caps.
	\MPHB\UsersAndRoles\CapabilitiesAndRoles::removeUserRoles();
}

wp_cache_flush();
