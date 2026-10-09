<?php
/**
 * Custom staff roles with server-side capabilities (audit CC-13).
 *
 * These are enforced by WordPress capability checks on the server, not merely
 * by hiding UI. Roles are (re)installed on activation / version change and
 * removed on deactivation.
 *
 * @package CellCraze_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cellcraze_Roles
 */
class Cellcraze_Roles {

	/**
	 * Role slugs this plugin owns.
	 */
	const ROLES = array( 'cc_cashier', 'cc_warehouse', 'cc_order_processing' );

	/**
	 * Create/refresh the custom roles with their capabilities.
	 */
	public static function install() {
		// Base caps every staff role needs just to reach wp-admin.
		$base = array(
			'read'                   => true,
			'cellcraze_manage_imei'  => false,
		);

		// Cashier: run the POS, read products/orders, manage IMEI at the counter.
		add_role(
			'cc_cashier',
			__( 'Cashier (POS)', 'cellcraze-core' ),
			array_merge(
				$base,
				array(
					'read'                    => true,
					'edit_shop_orders'        => true,
					'read_shop_orders'        => true,
					'edit_published_shop_orders' => true,
					'read_product'            => true,
					'cellcraze_use_pos'       => true,
					'cellcraze_manage_imei'   => true,
				)
			)
		);

		// Warehouse: receive stock, manage products/stock + IMEI, no order money ops.
		add_role(
			'cc_warehouse',
			__( 'Warehouse / Inventory', 'cellcraze-core' ),
			array(
				'read'                  => true,
				'edit_products'         => true,
				'edit_published_products' => true,
				'read_product'          => true,
				'manage_product_terms'  => true,
				'cellcraze_receive_stock' => true,
				'cellcraze_manage_imei' => true,
			)
		);

		// Order-processing: work orders (pick/pack/dispatch), view products, no product editing.
		add_role(
			'cc_order_processing',
			__( 'Order Processing', 'cellcraze-core' ),
			array(
				'read'               => true,
				'read_shop_orders'   => true,
				'edit_shop_orders'   => true,
				'edit_published_shop_orders' => true,
				'read_product'       => true,
				'cellcraze_manage_imei' => true,
			)
		);

		// Grant the custom caps to administrator + shop_manager too.
		foreach ( array( 'administrator', 'shop_manager' ) as $slug ) {
			$role = get_role( $slug );
			if ( $role ) {
				foreach ( array( 'cellcraze_use_pos', 'cellcraze_receive_stock', 'cellcraze_manage_imei' ) as $cap ) {
					$role->add_cap( $cap );
				}
			}
		}
	}

	/**
	 * Remove the custom roles (on deactivation). Users keep their accounts but
	 * fall back to no role; admins should reassign. We do NOT delete users.
	 */
	public static function remove() {
		foreach ( self::ROLES as $slug ) {
			remove_role( $slug );
		}
		foreach ( array( 'administrator', 'shop_manager' ) as $slug ) {
			$role = get_role( $slug );
			if ( $role ) {
				foreach ( array( 'cellcraze_use_pos', 'cellcraze_receive_stock', 'cellcraze_manage_imei' ) as $cap ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}
}
