<?php
/**
 * Plugin Name:       CellCraze Core
 * Plugin URI:        https://cellcraze.lk
 * Description:       Custom back-office glue for CellCraze: IMEI/serial tracking, order IMEI assignment, and sales-channel (online vs POS) tagging on top of WooCommerce.
 * Version:           1.1.0
 * Author:            CellCraze
 * Text Domain:       cellcraze-core
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 8.0
 * WC tested up to:   9.4
 *
 * @package CellCraze_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CELLCRAZE_CORE_VERSION', '1.1.0' );
define( 'CELLCRAZE_CORE_FILE', __FILE__ );
define( 'CELLCRAZE_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'CELLCRAZE_CORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load plugin classes.
 */
require_once CELLCRAZE_CORE_PATH . 'includes/class-cellcraze-imei-db.php';
require_once CELLCRAZE_CORE_PATH . 'includes/class-cellcraze-imei-admin.php';
require_once CELLCRAZE_CORE_PATH . 'includes/class-cellcraze-imei-orders.php';
require_once CELLCRAZE_CORE_PATH . 'includes/class-cellcraze-channel.php';
require_once CELLCRAZE_CORE_PATH . 'includes/class-cellcraze-rest.php';
require_once CELLCRAZE_CORE_PATH . 'includes/class-cellcraze-roles.php';

/**
 * Activation: create / upgrade the IMEI table + install staff roles.
 */
function cellcraze_core_activate() {
	Cellcraze_IMEI_DB::install();
	Cellcraze_Roles::install();
	add_option( 'cellcraze_core_version', CELLCRAZE_CORE_VERSION );
}
register_activation_hook( __FILE__, 'cellcraze_core_activate' );

/**
 * Deactivation: remove the custom roles (never deletes users or data).
 */
function cellcraze_core_deactivate() {
	Cellcraze_Roles::remove();
}
register_deactivation_hook( __FILE__, 'cellcraze_core_deactivate' );

/**
 * Boot the plugin once all other plugins (WooCommerce) are loaded.
 */
function cellcraze_core_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				echo esc_html__( 'CellCraze Core requires WooCommerce to be active.', 'cellcraze-core' );
				echo '</p></div>';
			}
		);
		return;
	}

	// Run a lightweight DB-version check in case the plugin files were updated
	// without re-activation (e.g. deployed over FTP).
	if ( get_option( 'cellcraze_core_version' ) !== CELLCRAZE_CORE_VERSION ) {
		Cellcraze_IMEI_DB::install();
		Cellcraze_Roles::install();
		update_option( 'cellcraze_core_version', CELLCRAZE_CORE_VERSION );
	}

	new Cellcraze_IMEI_Admin();
	new Cellcraze_IMEI_Orders();
	new Cellcraze_Channel();
	new Cellcraze_REST();
}
add_action( 'plugins_loaded', 'cellcraze_core_init' );

/**
 * Declare HPOS (custom order tables) compatibility.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
