<?php
/**
 * IMEI / serial data layer: table schema + CRUD helpers.
 *
 * @package CellCraze_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cellcraze_IMEI_DB
 *
 * Every physical phone unit is one row keyed by a UNIQUE imei/serial. A unit
 * moves through: available -> reserved -> sold, or available -> damaged, and
 * can be restored back to available when an order is cancelled/refunded.
 */
class Cellcraze_IMEI_DB {

	const STATUS_AVAILABLE = 'available';
	const STATUS_RESERVED  = 'reserved';
	const STATUS_SOLD       = 'sold';
	const STATUS_DAMAGED    = 'damaged';
	const STATUS_RETURNED   = 'returned';

	/**
	 * Full table name including the WP prefix.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'cellcraze_imei';
	}

	/**
	 * Create or upgrade the table. Uses dbDelta so it is idempotent.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		// NOTE: dbDelta is whitespace-sensitive; keep two spaces after PRIMARY KEY
		// and one field per line.
		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			imei VARCHAR(64) NOT NULL,
			product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			variation_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'available',
			order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			warranty_months SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
			supplier VARCHAR(191) NOT NULL DEFAULT '',
			note TEXT NULL,
			received_at DATETIME NULL,
			sold_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY imei (imei),
			KEY product_id (product_id),
			KEY status (status),
			KEY order_id (order_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Insert a new unit. Returns inserted id or WP_Error on duplicate/failure.
	 *
	 * @param array $args Unit fields.
	 * @return int|WP_Error
	 */
	public static function add_unit( $args ) {
		global $wpdb;

		$imei = isset( $args['imei'] ) ? trim( (string) $args['imei'] ) : '';
		if ( '' === $imei ) {
			return new WP_Error( 'cellcraze_imei_empty', __( 'IMEI / serial is required.', 'cellcraze-core' ) );
		}
		if ( self::get_by_imei( $imei ) ) {
			return new WP_Error( 'cellcraze_imei_duplicate', __( 'That IMEI / serial already exists.', 'cellcraze-core' ) );
		}

		$now  = current_time( 'mysql' );
		$data = array(
			'imei'            => $imei,
			'product_id'      => isset( $args['product_id'] ) ? absint( $args['product_id'] ) : 0,
			'variation_id'    => isset( $args['variation_id'] ) ? absint( $args['variation_id'] ) : 0,
			'status'          => isset( $args['status'] ) ? sanitize_key( $args['status'] ) : self::STATUS_AVAILABLE,
			'order_id'        => isset( $args['order_id'] ) ? absint( $args['order_id'] ) : 0,
			'cost'            => isset( $args['cost'] ) ? (float) $args['cost'] : 0,
			'warranty_months' => isset( $args['warranty_months'] ) ? absint( $args['warranty_months'] ) : 0,
			'supplier'        => isset( $args['supplier'] ) ? sanitize_text_field( $args['supplier'] ) : '',
			'note'            => isset( $args['note'] ) ? sanitize_textarea_field( $args['note'] ) : '',
			'received_at'     => ! empty( $args['received_at'] ) ? $args['received_at'] : $now,
			'created_at'      => $now,
			'updated_at'      => $now,
		);

		$formats = array( '%s', '%d', '%d', '%s', '%d', '%f', '%d', '%s', '%s', '%s', '%s', '%s' );

		$ok = $wpdb->insert( self::table(), $data, $formats );
		if ( false === $ok ) {
			return new WP_Error( 'cellcraze_imei_db', __( 'Could not save the unit.', 'cellcraze-core' ) );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Fetch one unit row by IMEI.
	 *
	 * @param string $imei IMEI/serial.
	 * @return object|null
	 */
	public static function get_by_imei( $imei ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE imei = %s", $imei ) // phpcs:ignore WordPress.DB
		);
	}

	/**
	 * Fetch one unit by id.
	 *
	 * @param int $id Row id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) // phpcs:ignore WordPress.DB
		);
	}

	/**
	 * Available units for a product, oldest received first (FIFO).
	 *
	 * @param int $product_id   Product id.
	 * @param int $variation_id Variation id (0 = any).
	 * @param int $limit        Max rows.
	 * @return array
	 */
	public static function get_available( $product_id, $variation_id = 0, $limit = 50 ) {
		global $wpdb;
		$table = self::table();

		if ( $variation_id > 0 ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE product_id = %d AND variation_id = %d AND status = %s ORDER BY received_at ASC, id ASC LIMIT %d", // phpcs:ignore WordPress.DB
				absint( $product_id ),
				absint( $variation_id ),
				self::STATUS_AVAILABLE,
				absint( $limit )
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE product_id = %d AND status = %s ORDER BY received_at ASC, id ASC LIMIT %d", // phpcs:ignore WordPress.DB
				absint( $product_id ),
				self::STATUS_AVAILABLE,
				absint( $limit )
			);
		}
		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Count available units for a product.
	 *
	 * @param int $product_id Product id.
	 * @return int
	 */
	public static function count_available( $product_id ) {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE product_id = %d AND status = %s", // phpcs:ignore WordPress.DB
				absint( $product_id ),
				self::STATUS_AVAILABLE
			)
		);
	}

	/**
	 * Units assigned to an order.
	 *
	 * @param int $order_id Order id.
	 * @return array
	 */
	public static function get_for_order( $order_id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d ORDER BY id ASC", absint( $order_id ) ) // phpcs:ignore WordPress.DB
		);
	}

	/**
	 * Update arbitrary fields on a unit by id.
	 *
	 * @param int   $id   Row id.
	 * @param array $data Column => value.
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		$ok                 = $wpdb->update( self::table(), $data, array( 'id' => absint( $id ) ) );
		return false !== $ok;
	}

	/**
	 * Atomically claim a unit for an order (compare-and-set).
	 *
	 * The UPDATE only succeeds when the row is still `available`, so two
	 * concurrent sales (e.g. web + POS) can never both claim the same unit:
	 * exactly one UPDATE affects the row, the other affects zero rows. This is
	 * the guard against IMEI double-allocation (audit issue CC-03).
	 *
	 * @param int $id       Row id.
	 * @param int $order_id Order id.
	 * @return bool True only if THIS call transitioned the unit to sold.
	 */
	public static function mark_sold( $id, $order_id ) {
		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql' );

		// wpdb->query returns the number of rows changed for an UPDATE.
		$affected = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, order_id = %d, sold_at = %s, updated_at = %s WHERE id = %d AND status = %s", // phpcs:ignore WordPress.DB
				self::STATUS_SOLD,
				absint( $order_id ),
				$now,
				$now,
				absint( $id ),
				self::STATUS_AVAILABLE
			)
		);

		return ( 1 === (int) $affected );
	}

	/**
	 * Restore a unit back to available (cancel / refund).
	 *
	 * @param int $id Row id.
	 * @return bool
	 */
	public static function restore( $id ) {
		return self::update(
			$id,
			array(
				'status'   => self::STATUS_AVAILABLE,
				'order_id' => 0,
				'sold_at'  => null,
			)
		);
	}

	/**
	 * Delete a unit row.
	 *
	 * @param int $id Row id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return false !== $wpdb->delete( self::table(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Human-readable status labels.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			self::STATUS_AVAILABLE => __( 'Available', 'cellcraze-core' ),
			self::STATUS_RESERVED  => __( 'Reserved', 'cellcraze-core' ),
			self::STATUS_SOLD      => __( 'Sold', 'cellcraze-core' ),
			self::STATUS_DAMAGED   => __( 'Damaged', 'cellcraze-core' ),
			self::STATUS_RETURNED  => __( 'Returned', 'cellcraze-core' ),
		);
	}
}
