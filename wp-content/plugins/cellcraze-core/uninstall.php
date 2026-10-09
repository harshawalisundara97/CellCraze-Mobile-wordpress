<?php
/**
 * Uninstall handler. Only drops data when the site owner explicitly asks
 * (define CELLCRAZE_CORE_DROP_DATA true), so deactivating/reinstalling never
 * destroys the IMEI history by accident.
 *
 * @package CellCraze_Core
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( defined( 'CELLCRAZE_CORE_DROP_DATA' ) && CELLCRAZE_CORE_DROP_DATA ) {
	global $wpdb;
	$table = $wpdb->prefix . 'cellcraze_imei';
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB
	delete_option( 'cellcraze_core_version' );
}
