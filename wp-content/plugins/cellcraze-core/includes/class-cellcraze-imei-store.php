<?php
/**
 * IMEI / serial-number data layer.
 *
 * One row per physical unit. A unit moves in_stock -> sold -> returned,
 * and carries the cost, the receiving reference, and warranty data needed
 * downstream by POS, orders, returns and reporting.
 *
 * @package CellCraze_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD + queries for the wp_cellcraze_imei table.
 */
class CellCraze_IMEI_Store {

	const STATUS_IN_STOCK  = 'in_stock';
	const STATUS_RESERVED  = 'reserved';
	const STATUS_SOLD      = 'sold';
	const STATUS_RETURNED  = 'returned';
	const STATUS_DAMAGED   = 'damaged';

	/**
	 * Fully-qualified table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'cellcraze_imei';
	}

	/**
	 * Valid unit statuses.
	 *
	 * @return array<string,string> slug => label.
	 */
	public static function statuses() {
		return array(
			self::STATUS_IN_STOCK => __( 'In stock', 'cellcraze-core' ),
			self::STATUS_RESERVED => __( 'Reserved', 'cellcraze-core' ),
			self::STATUS_SOLD     => __( 'Sold', 'cellcraze-core' ),
			self::STATUS_RETURNED => __( 'Returned', 'cellcraze-core' ),
			self::STATUS_DAMAGED  => __( 'Damaged / lost', 'cellcraze-core' ),
		);
	}

	/**
	 * Create or upgrade the table. Safe to call repeatedly (dbDelta).
	 */
	public static function install() {
		global $wpdb;
		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		// imei is UNIQUE so the same serial can never be entered twice.
		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			imei VARCHAR(64) NOT NULL,
			product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			variation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'in_stock',
			order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			cost DECIMAL(12,2) NULL DEFAULT NULL,
			supplier VARCHAR(191) NULL DEFAULT NULL,
			received_ref VARCHAR(191) NULL DEFAULT NULL,
			warranty_months SMALLINT UNSIGNED NULL DEFAULT NULL,
			sold_at DATETIME NULL DEFAULT NULL,
			note TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY imei (imei),
			KEY product_id (product_id),
			KEY status (status),
			KEY order_id (order_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Add one unit. Returns the new row id, or a WP_Error on duplicate/invalid IMEI.
	 *
	 * @param array $data Fields: imei (required), product_id, variation_id, cost,
	 *                    supplier, received_ref, warranty_months, note.
	 * @return int|WP_Error
	 */
	public static function add( array $data ) {
		global $wpdb;

		$imei = isset( $data['imei'] ) ? sanitize_text_field( $data['imei'] ) : '';
		$imei = trim( $imei );
		if ( '' === $imei ) {
			return new WP_Error( 'cellcraze_imei_empty', __( 'IMEI / serial number is required.', 'cellcraze-core' ) );
		}
		if ( self::find_by_imei( $imei ) ) {
			return new WP_Error(
				'cellcraze_imei_duplicate',
				/* translators: %s: the duplicate IMEI. */
				sprintf( __( 'IMEI %s already exists.', 'cellcraze-core' ), $imei )
			);
		}

		$row = array(
			'imei'            => $imei,
			'product_id'      => isset( $data['product_id'] ) ? absint( $data['product_id'] ) : 0,
			'variation_id'    => isset( $data['variation_id'] ) ? absint( $data['variation_id'] ) : 0,
			'status'          => self::STATUS_IN_STOCK,
			'cost'            => isset( $data['cost'] ) && '' !== $data['cost'] ? (float) $data['cost'] : null,
			'supplier'        => isset( $data['supplier'] ) ? sanitize_text_field( $data['supplier'] ) : null,
			'received_ref'    => isset( $data['received_ref'] ) ? sanitize_text_field( $data['received_ref'] ) : null,
			'warranty_months' => isset( $data['warranty_months'] ) && '' !== $data['warranty_months'] ? absint( $data['warranty_months'] ) : null,
			'note'            => isset( $data['note'] ) ? sanitize_textarea_field( $data['note'] ) : null,
			'created_at'      => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
		);

		$ok = $wpdb->insert( self::table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( false === $ok ) {
			return new WP_Error( 'cellcraze_imei_insert_failed', __( 'Could not save the unit.', 'cellcraze-core' ) );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Fetch one unit by its id.
	 *
	 * @param int $id Row id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', absint( $id ) ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Fetch one unit by its IMEI.
	 *
	 * @param string $imei IMEI / serial.
	 * @return array|null
	 */
	public static function find_by_imei( $imei ) {
		global $wpdb;
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE imei = %s', trim( (string) $imei ) ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Count available (in-stock) units for a product.
	 *
	 * @param int $product_id Product id.
	 * @return int
	 */
	public static function available_count( $product_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table() . ' WHERE product_id = %d AND status = %s',
				absint( $product_id ),
				self::STATUS_IN_STOCK
			)
		);
	}

	/**
	 * List available (in-stock) units for a product, oldest first.
	 *
	 * @param int $product_id Product id.
	 * @return array<int,array>
	 */
	public static function available_for_product( $product_id ) {
		global $wpdb;
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE product_id = %d AND status = %s ORDER BY id ASC',
				absint( $product_id ),
				self::STATUS_IN_STOCK
			),
			ARRAY_A
		);
	}

	/**
	 * Mark a unit sold against an order. Only an in-stock/reserved unit can be sold.
	 *
	 * @param int $id       Unit id.
	 * @param int $order_id Order id.
	 * @return bool
	 */
	public static function mark_sold( $id, $order_id ) {
		return self::set_status(
			$id,
			self::STATUS_SOLD,
			array(
				'order_id' => absint( $order_id ),
				'sold_at'  => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Return a unit to stock (e.g. on order cancellation or refund).
	 *
	 * @param int    $id     Unit id.
	 * @param string $status Target status (returned to stock, or flagged returned).
	 * @return bool
	 */
	public static function restore( $id, $status = self::STATUS_IN_STOCK ) {
		return self::set_status(
			$id,
			$status,
			array(
				'order_id' => 0,
				'sold_at'  => null,
			)
		);
	}

	/**
	 * Low-level status setter.
	 *
	 * @param int    $id     Unit id.
	 * @param string $status New status (must be valid).
	 * @param array  $extra  Extra columns to update.
	 * @return bool
	 */
	public static function set_status( $id, $status, array $extra = array() ) {
		global $wpdb;
		if ( ! array_key_exists( $status, self::statuses() ) ) {
			return false;
		}
		$data = array_merge(
			$extra,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			)
		);
		$ok = $wpdb->update( self::table(), $data, array( 'id' => absint( $id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $ok;
	}

	/**
	 * Units linked to an order.
	 *
	 * @param int $order_id Order id.
	 * @return array<int,array>
	 */
	public static function for_order( $order_id ) {
		global $wpdb;
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE order_id = %d ORDER BY id ASC', absint( $order_id ) ),
			ARRAY_A
		);
	}
}
