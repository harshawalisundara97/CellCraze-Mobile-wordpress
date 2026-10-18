<?php
/**
 * Stock-movement + audit ledger (task T-101).
 *
 * An append-only record of inventory movements and inventory-related events.
 * It NEVER changes stock itself — it only records what happened, with a reason,
 * a source reference, the actor (when known) and a timestamp.
 *
 * Idempotency: every row has a UNIQUE `event_key`; writes use INSERT IGNORE, so
 * a hook that fires twice for the same transition produces exactly one row.
 *
 * Quantity accounting (avoid double counting):
 * - Quantity deltas live ONLY on `stock_receive` (+), `order_stock_reduce` (-)
 *   and `order_stock_restore` (+). Summing `qty_delta` gives the net movement.
 * - IMEI events (`imei_assign`, `imei_restore`) are unit STATUS traces with
 *   qty_delta = NULL, so a serialized sale is not counted twice.
 *
 * @package CellCraze_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cellcraze_Audit
 */
class Cellcraze_Audit {

	/**
	 * Table name with prefix.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'cellcraze_audit';
	}

	/**
	 * Create/upgrade the ledger table.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_key VARCHAR(191) NOT NULL,
			event_type VARCHAR(40) NOT NULL,
			object_type VARCHAR(20) NOT NULL DEFAULT '',
			object_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			variation_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			qty_delta INT(11) NULL DEFAULT NULL,
			reason VARCHAR(100) NOT NULL DEFAULT '',
			reference VARCHAR(100) NOT NULL DEFAULT '',
			actor_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			note TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY event_key (event_key),
			KEY event_type (event_type),
			KEY product_id (product_id),
			KEY object_type_id (object_type, object_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Record one ledger event. Idempotent on `event_key`.
	 *
	 * @param array $args {
	 *   @type string   $event_key   REQUIRED unique key for this transition.
	 *   @type string   $event_type  REQUIRED e.g. stock_receive, imei_assign.
	 *   @type string   $object_type imei_unit|product|order.
	 *   @type int      $object_id   Primary affected record id.
	 *   @type int      $product_id  Product id.
	 *   @type int      $variation_id Variation id.
	 *   @type int|null $qty_delta   Signed qty change, or null for status events.
	 *   @type string   $reason      Short machine reason.
	 *   @type string   $reference   Human reference (order #, IMEI, supplier).
	 *   @type int|null $actor_id    User id; defaults to current user.
	 *   @type string   $note        Optional short note (no PII/secrets).
	 * }
	 * @return bool True if a new row was written (false if a duplicate/ignored).
	 */
	public static function log( $args ) {
		global $wpdb;

		if ( empty( $args['event_key'] ) || empty( $args['event_type'] ) ) {
			return false;
		}

		$actor = array_key_exists( 'actor_id', $args ) && null !== $args['actor_id']
			? (int) $args['actor_id']
			: get_current_user_id();

		$qty_delta = array_key_exists( 'qty_delta', $args ) && null !== $args['qty_delta']
			? (int) $args['qty_delta']
			: null;

		$table = self::table();

		// INSERT IGNORE so a retried hook can't create a duplicate row.
		$sql = $wpdb->prepare(
			"INSERT IGNORE INTO {$table}
				(event_key, event_type, object_type, object_id, product_id, variation_id, qty_delta, reason, reference, actor_id, note, created_at)
			 VALUES (%s, %s, %s, %d, %d, %d, " . ( null === $qty_delta ? 'NULL' : '%d' ) . ", %s, %s, %d, %s, %s)", // phpcs:ignore WordPress.DB
			array_values(
				array_filter(
					array(
						'event_key'    => substr( (string) $args['event_key'], 0, 191 ),
						'event_type'   => substr( (string) $args['event_type'], 0, 40 ),
						'object_type'  => isset( $args['object_type'] ) ? substr( sanitize_key( $args['object_type'] ), 0, 20 ) : '',
						'object_id'    => isset( $args['object_id'] ) ? absint( $args['object_id'] ) : 0,
						'product_id'   => isset( $args['product_id'] ) ? absint( $args['product_id'] ) : 0,
						'variation_id' => isset( $args['variation_id'] ) ? absint( $args['variation_id'] ) : 0,
						'qty_delta'    => $qty_delta, // removed by array_filter when null.
						'reason'       => isset( $args['reason'] ) ? substr( sanitize_text_field( $args['reason'] ), 0, 100 ) : '',
						'reference'    => isset( $args['reference'] ) ? substr( sanitize_text_field( $args['reference'] ), 0, 100 ) : '',
						'actor_id'     => $actor,
						'note'         => isset( $args['note'] ) ? substr( sanitize_textarea_field( $args['note'] ), 0, 500 ) : '',
						'created_at'   => current_time( 'mysql' ),
					),
					static function ( $v ) {
						return null !== $v; // drop only the null qty placeholder.
					}
				)
			)
		);

		$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB
		return ( 1 === (int) $result );
	}

	/* ------------------------------------------------------------------ */
	/* Hooks: capture order-driven Woo stock movements + admin viewer      */
	/* ------------------------------------------------------------------ */

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'woocommerce_reduce_order_stock', array( $this, 'on_reduce_order_stock' ), 20 );
		add_action( 'woocommerce_restore_order_stock', array( $this, 'on_restore_order_stock' ), 20 );
		// T-102: record staff-role changes after WP_User::set_role succeeds.
		add_action( 'set_user_role', array( $this, 'on_role_change' ), 20, 3 );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	/**
	 * Log the per-line quantity reduction when WooCommerce reduces order stock.
	 * Fires once per order (Woo guards with the _reduced_stock flag).
	 *
	 * @param WC_Order $order Order.
	 */
	public function on_reduce_order_stock( $order ) {
		$this->log_order_stock( $order, 'order_stock_reduce', -1 );
	}

	/**
	 * Log the per-line quantity restoration when WooCommerce restores stock.
	 *
	 * @param WC_Order $order Order.
	 */
	public function on_restore_order_stock( $order ) {
		$this->log_order_stock( $order, 'order_stock_restore', 1 );
	}

	/**
	 * Shared logger for order stock movements.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $type  Event type.
	 * @param int      $sign  -1 reduce, +1 restore.
	 */
	private function log_order_stock( $order, $type, $sign ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$oid = $order->get_id();
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product || ! $product->managing_stock() ) {
				continue;
			}
			$qty = (int) $item->get_quantity();
			self::log(
				array(
					'event_key'    => $type . ':' . $oid . ':' . $item_id,
					'event_type'   => $type,
					'object_type'  => 'order',
					'object_id'    => $oid,
					'product_id'   => $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(),
					'variation_id' => $product->is_type( 'variation' ) ? $product->get_id() : 0,
					'qty_delta'    => $sign * $qty,
					'reason'       => ( $sign < 0 ) ? 'sale' : 'cancel_or_refund',
					'reference'    => $order->get_order_number(),
				)
			);
		}
	}

	/**
	 * Record a staff-role change after it has succeeded. Logs roles only (not a
	 * person's name/email) — roles are authorization data, not sensitive PII.
	 *
	 * @param int      $user_id   Affected user id.
	 * @param string   $role      New primary role.
	 * @param string[] $old_roles Previous roles.
	 */
	public function on_role_change( $user_id, $role, $old_roles ) {
		$old = is_array( $old_roles ) ? implode( ',', array_map( 'sanitize_key', $old_roles ) ) : '';
		$new = sanitize_key( (string) $role );
		self::log(
			array(
				// Second-resolution timestamp keeps a same-second retry idempotent
				// while allowing distinct legitimate changes over time.
				'event_key'   => 'role_change:' . (int) $user_id . ':' . $new . ':' . gmdate( 'YmdHis' ),
				'event_type'  => 'role_change',
				'object_type' => 'user',
				'object_id'   => (int) $user_id,
				'reason'      => 'staff_role_change',
				'reference'   => $new,
				'note'        => trim( $old . ' -> ' . $new, ' -' ),
			)
		);
	}

	/**
	 * Register the read-only ledger viewer under WooCommerce.
	 */
	public function admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Stock & Audit Log', 'cellcraze-core' ),
			__( 'Stock & Audit Log', 'cellcraze-core' ),
			'manage_woocommerce',
			'cellcraze-audit',
			array( $this, 'render_viewer' )
		);
	}

	/**
	 * Render the ledger viewer (read-only, capability-gated).
	 */
	public function render_viewer() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cellcraze-core' ), '', array( 'response' => 403 ) );
		}
		global $wpdb;
		$table = self::table();
		$type  = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( $type ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE event_type = %s ORDER BY id DESC LIMIT 200", $type ) ); // phpcs:ignore WordPress.DB
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 200" ); // phpcs:ignore WordPress.DB
		}
		?>
		<div class="wrap cc-admin">
			<div class="cc-head"><div><h1><?php esc_html_e( 'Stock & Audit Log', 'cellcraze-core' ); ?></h1><p class="cc-sub"><?php esc_html_e( 'Append-only record of inventory movements and related events. Newest 200 shown.', 'cellcraze-core' ); ?></p></div></div>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'When', 'cellcraze-core' ); ?></th>
					<th><?php esc_html_e( 'Event', 'cellcraze-core' ); ?></th>
					<th><?php esc_html_e( 'Object', 'cellcraze-core' ); ?></th>
					<th><?php esc_html_e( 'Product', 'cellcraze-core' ); ?></th>
					<th><?php esc_html_e( 'Qty', 'cellcraze-core' ); ?></th>
					<th><?php esc_html_e( 'Reason', 'cellcraze-core' ); ?></th>
					<th><?php esc_html_e( 'Reference', 'cellcraze-core' ); ?></th>
					<th><?php esc_html_e( 'Actor', 'cellcraze-core' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No events yet.', 'cellcraze-core' ); ?></td></tr>
				<?php else : foreach ( $rows as $r ) :
					$actor = $r->actor_id ? get_userdata( $r->actor_id ) : null; ?>
					<tr>
						<td><?php echo esc_html( $r->created_at ); ?></td>
						<td><code><?php echo esc_html( $r->event_type ); ?></code></td>
						<td><?php echo esc_html( $r->object_type . ' #' . $r->object_id ); ?></td>
						<td><?php echo $r->product_id ? esc_html( get_the_title( $r->product_id ) ) : '&mdash;'; ?></td>
						<td><?php echo ( null === $r->qty_delta ) ? '&mdash;' : esc_html( ( $r->qty_delta > 0 ? '+' : '' ) . $r->qty_delta ); ?></td>
						<td><?php echo esc_html( $r->reason ); ?></td>
						<td><?php echo esc_html( $r->reference ); ?></td>
						<td><?php echo esc_html( $actor ? $actor->display_name : ( $r->actor_id ? ( '#' . $r->actor_id ) : 'system' ) ); ?></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
