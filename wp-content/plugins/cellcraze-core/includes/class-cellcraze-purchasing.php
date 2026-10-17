<?php
/**
 * Purchase Orders + Goods Received Notes (GRN).
 *
 * Receiving updates inventory ONLY through the existing authoritative paths:
 *   - IMEI products  -> Cellcraze_IMEI_Admin::receive() (creates units + bumps
 *     Woo stock + audit), tagged with po_id for traceability.
 *   - Quantity products -> wc_update_product_stock() (+ audit stock_receive).
 * No competing stock count is kept. Each receipt is one GRN row with a UNIQUE
 * token so a double-submitted form cannot receive the same goods twice.
 *
 * @package CellCraze_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cellcraze_Purchasing
 */
class Cellcraze_Purchasing {

	const CAP = 'cellcraze_receive_stock';

	const STATUS_DRAFT     = 'draft';
	const STATUS_ORDERED   = 'ordered';
	const STATUS_PARTIAL   = 'partial';
	const STATUS_RECEIVED  = 'received';
	const STATUS_CANCELLED = 'cancelled';

	/** @return string */
	public static function po_table() {
		global $wpdb; return $wpdb->prefix . 'cellcraze_po';
	}
	/** @return string */
	public static function item_table() {
		global $wpdb; return $wpdb->prefix . 'cellcraze_po_item';
	}
	/** @return string */
	public static function grn_table() {
		global $wpdb; return $wpdb->prefix . 'cellcraze_grn';
	}

	/**
	 * Create/upgrade tables.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$cc = $wpdb->get_charset_collate();
		$po = self::po_table();
		dbDelta(
			"CREATE TABLE {$po} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			supplier_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			reference VARCHAR(100) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			note VARCHAR(1000) NOT NULL DEFAULT '',
			total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			ordered_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY supplier_id (supplier_id),
			KEY status (status)
		) {$cc};"
		);
		$it = self::item_table();
		dbDelta(
			"CREATE TABLE {$it} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			po_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			variation_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			qty_ordered INT(11) NOT NULL DEFAULT 0,
			qty_received INT(11) NOT NULL DEFAULT 0,
			unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			requires_imei TINYINT(1) NOT NULL DEFAULT 0,
			warranty_months SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY po_id (po_id),
			KEY product_id (product_id)
		) {$cc};"
		);
		$grn = self::grn_table();
		dbDelta(
			"CREATE TABLE {$grn} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			po_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			grn_token VARCHAR(64) NOT NULL DEFAULT '',
			received_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			note VARCHAR(500) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY grn_token (grn_token),
			KEY po_id (po_id)
		) {$cc};"
		);
	}

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 13 );
		add_action( 'admin_post_cellcraze_create_po', array( $this, 'handle_create_po' ) );
		add_action( 'admin_post_cellcraze_add_po_item', array( $this, 'handle_add_item' ) );
		add_action( 'admin_post_cellcraze_po_status', array( $this, 'handle_status' ) );
		add_action( 'admin_post_cellcraze_receive_po', array( $this, 'handle_receive' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Data helpers                                                        */
	/* ------------------------------------------------------------------ */

	/** @param int $id @return object|null */
	public static function get_po( $id ) {
		global $wpdb; $t = self::po_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", absint( $id ) ) ); // phpcs:ignore WordPress.DB
	}
	/** @param int $po_id @return array */
	public static function get_items( $po_id ) {
		global $wpdb; $t = self::item_table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE po_id = %d ORDER BY id ASC", absint( $po_id ) ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Recompute PO total from its items.
	 *
	 * @param int $po_id PO id.
	 */
	private function recalc_total( $po_id ) {
		global $wpdb;
		$it    = self::item_table();
		$total = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(qty_ordered * unit_cost),0) FROM {$it} WHERE po_id = %d", absint( $po_id ) ) ); // phpcs:ignore WordPress.DB
		$wpdb->update( self::po_table(), array( 'total' => round( $total, 2 ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => absint( $po_id ) ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Recompute PO status from item receipt progress (ordered/partial/received).
	 *
	 * @param int $po_id PO id.
	 */
	private function refresh_status( $po_id ) {
		$items = self::get_items( $po_id );
		if ( empty( $items ) ) {
			return;
		}
		$all_done = true;
		$any_recv = false;
		foreach ( $items as $i ) {
			if ( (int) $i->qty_received > 0 ) { $any_recv = true; }
			if ( (int) $i->qty_received < (int) $i->qty_ordered ) { $all_done = false; }
		}
		$status = $all_done ? self::STATUS_RECEIVED : ( $any_recv ? self::STATUS_PARTIAL : self::STATUS_ORDERED );
		global $wpdb;
		$wpdb->update( self::po_table(), array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => absint( $po_id ) ) ); // phpcs:ignore WordPress.DB
	}

	/** @return array status => label */
	public static function statuses() {
		return array(
			self::STATUS_DRAFT     => __( 'Draft', 'cellcraze-core' ),
			self::STATUS_ORDERED   => __( 'Ordered', 'cellcraze-core' ),
			self::STATUS_PARTIAL   => __( 'Partially received', 'cellcraze-core' ),
			self::STATUS_RECEIVED  => __( 'Received', 'cellcraze-core' ),
			self::STATUS_CANCELLED => __( 'Cancelled', 'cellcraze-core' ),
		);
	}

	/** @param string $s @return string chip class */
	private static function status_class( $s ) {
		$map = array( 'draft' => '', 'ordered' => 'is-warn', 'partial' => 'is-warn', 'received' => 'is-active', 'cancelled' => 'is-void' );
		return isset( $map[ $s ] ) ? $map[ $s ] : '';
	}

	/* ------------------------------------------------------------------ */
	/* Handlers                                                            */
	/* ------------------------------------------------------------------ */

	/** Create a draft PO. */
	public function handle_create_po() {
		$this->guard( 'cellcraze_create_po' );
		$supplier_id = isset( $_POST['supplier_id'] ) ? absint( $_POST['supplier_id'] ) : 0;
		if ( ! $supplier_id || ! Cellcraze_Suppliers::get( $supplier_id ) ) {
			$this->redirect_list( array( 'cc_po' => 'nosupplier' ) );
		}
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert( // phpcs:ignore WordPress.DB
			self::po_table(),
			array(
				'supplier_id' => $supplier_id,
				'reference'   => isset( $_POST['reference'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['reference'] ) ), 0, 100 ) : '',
				'note'        => isset( $_POST['note'] ) ? substr( sanitize_textarea_field( wp_unslash( $_POST['note'] ) ), 0, 1000 ) : '',
				'status'      => self::STATUS_DRAFT,
				'created_by'  => get_current_user_id(),
				'created_at'  => $now,
				'updated_at'  => $now,
			)
		);
		$this->redirect_detail( (int) $wpdb->insert_id );
	}

	/** Add a line item to a draft PO. */
	public function handle_add_item() {
		$this->guard( 'cellcraze_add_po_item' );
		$po_id = isset( $_POST['po_id'] ) ? absint( $_POST['po_id'] ) : 0;
		$po    = self::get_po( $po_id );
		if ( ! $po || self::STATUS_DRAFT !== $po->status ) {
			$this->redirect_detail( $po_id, array( 'cc_po' => 'notdraft' ) );
		}
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$qty        = isset( $_POST['qty_ordered'] ) ? max( 1, absint( $_POST['qty_ordered'] ) ) : 0;
		$cost       = isset( $_POST['unit_cost'] ) ? round( (float) $_POST['unit_cost'], 2 ) : 0;
		$product    = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! $product || $qty < 1 || $cost < 0 ) {
			$this->redirect_detail( $po_id, array( 'cc_po' => 'baditem' ) );
		}
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB
			self::item_table(),
			array(
				'po_id'           => $po_id,
				'product_id'      => $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(),
				'variation_id'    => $product->is_type( 'variation' ) ? $product->get_id() : 0,
				'qty_ordered'     => $qty,
				'qty_received'    => 0,
				'unit_cost'       => $cost,
				'requires_imei'   => ( isset( $_POST['requires_imei'] ) && '1' === $_POST['requires_imei'] ) ? 1 : 0,
				'warranty_months' => isset( $_POST['warranty_months'] ) ? absint( $_POST['warranty_months'] ) : 0,
			)
		);
		$this->recalc_total( $po_id );
		$this->redirect_detail( $po_id, array( 'cc_po' => 'itemadded' ) );
	}

	/** Transition PO status (draft->ordered, or cancel). */
	public function handle_status() {
		$po_id = isset( $_POST['po_id'] ) ? absint( $_POST['po_id'] ) : 0;
		$this->guard( 'cellcraze_po_status_' . $po_id, 'cc_po_status_nonce' );
		$to = isset( $_POST['to'] ) ? sanitize_key( $_POST['to'] ) : '';
		$po = self::get_po( $po_id );
		if ( ! $po ) {
			$this->redirect_list( array( 'cc_po' => 'notfound' ) );
		}
		global $wpdb;
		$allowed = false;
		if ( self::STATUS_ORDERED === $to && self::STATUS_DRAFT === $po->status && self::get_items( $po_id ) ) {
			$wpdb->update( self::po_table(), array( 'status' => self::STATUS_ORDERED, 'ordered_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $po_id ) ); // phpcs:ignore WordPress.DB
			$allowed = true;
		} elseif ( self::STATUS_CANCELLED === $to && in_array( $po->status, array( self::STATUS_DRAFT, self::STATUS_ORDERED ), true ) ) {
			// Cancel only before any goods are received (received items are real stock).
			$wpdb->update( self::po_table(), array( 'status' => self::STATUS_CANCELLED, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $po_id ) ); // phpcs:ignore WordPress.DB
			$allowed = true;
		}
		if ( $allowed && class_exists( 'Cellcraze_Audit' ) ) {
			Cellcraze_Audit::log( array( 'event_key' => 'po_status:' . $po_id . ':' . $to, 'event_type' => 'po_status', 'object_type' => 'po', 'object_id' => $po_id, 'reason' => $to, 'reference' => 'PO#' . $po_id ) );
		}
		$this->redirect_detail( $po_id, array( 'cc_po' => $allowed ? 'statusok' : 'statusbad' ) );
	}

	/**
	 * Receive goods against a PO (GRN). Partial-safe, idempotent, IMEI-aware.
	 */
	public function handle_receive() {
		$po_id = isset( $_POST['po_id'] ) ? absint( $_POST['po_id'] ) : 0;
		$this->guard( 'cellcraze_receive_po_' . $po_id, 'cc_grn_nonce' );
		$po = self::get_po( $po_id );
		if ( ! $po || ! in_array( $po->status, array( self::STATUS_ORDERED, self::STATUS_PARTIAL ), true ) ) {
			$this->redirect_detail( $po_id, array( 'cc_po' => 'notreceivable' ) );
		}

		// Idempotency: one GRN per submission token. A repeat submit is a no-op.
		$token = isset( $_POST['grn_token'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['grn_token'] ) ), 0, 64 ) : '';
		if ( '' === $token ) {
			$this->redirect_detail( $po_id, array( 'cc_po' => 'notoken' ) );
		}
		global $wpdb;
		$grn_ok = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO " . self::grn_table() . " (po_id, grn_token, received_by, created_at) VALUES (%d,%s,%d,%s)", $po_id, $token, get_current_user_id(), current_time( 'mysql' ) ) ); // phpcs:ignore WordPress.DB
		if ( 1 !== (int) $grn_ok ) {
			$this->redirect_detail( $po_id, array( 'cc_po' => 'dupe' ) ); // already processed.
		}

		$supplier = Cellcraze_Suppliers::get( $po->supplier_id );
		$sup_name = $supplier ? $supplier->name : '';
		$items    = self::get_items( $po_id );
		$received_any = false;
		$errors       = array();

		foreach ( $items as $item ) {
			$recv = isset( $_POST[ 'recv_' . $item->id ] ) ? absint( $_POST[ 'recv_' . $item->id ] ) : 0;
			if ( $recv < 1 ) {
				continue;
			}
			$remaining = (int) $item->qty_ordered - (int) $item->qty_received;
			if ( $recv > $remaining ) {
				$errors[] = sprintf( /* translators: %d: product id */ __( 'Over-receiving blocked for product %d.', 'cellcraze-core' ), $item->product_id );
				continue;
			}

			if ( (int) $item->requires_imei ) {
				$raw   = isset( $_POST[ 'imei_' . $item->id ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ 'imei_' . $item->id ] ) ) : '';
				$imeis = preg_split( '/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
				if ( count( $imeis ) !== $recv ) {
					$errors[] = sprintf( /* translators: %d: product id */ __( 'IMEI count must equal received qty for product %d.', 'cellcraze-core' ), $item->product_id );
					continue;
				}
				$res = Cellcraze_IMEI_Admin::receive(
					$item->product_id,
					$imeis,
					array( 'cost' => (float) $item->unit_cost, 'warranty_months' => (int) $item->warranty_months, 'supplier' => $sup_name, 'po_id' => $po_id )
				);
				if ( (int) $res['added'] !== $recv ) {
					// Some IMEIs were duplicates — receive only what was truly added.
					$errors[] = sprintf( /* translators: 1: added 2: expected 3: product */ __( 'Only %1$d of %2$d IMEIs accepted for product %3$d (duplicates skipped).', 'cellcraze-core' ), (int) $res['added'], $recv, $item->product_id );
				}
				$recv = (int) $res['added']; // authoritative count actually received.
			} else {
				// Quantity product: increment Woo stock (authoritative) + audit.
				$product = wc_get_product( $item->variation_id ? $item->variation_id : $item->product_id );
				if ( $product ) {
					if ( ! $product->get_manage_stock() ) {
						$product->set_manage_stock( true );
						$product->save();
					}
					wc_update_product_stock( $product, $recv, 'increase' );
					if ( class_exists( 'Cellcraze_Audit' ) ) {
						Cellcraze_Audit::log( array( 'event_key' => 'po_receive_qty:' . $po_id . ':' . $item->id . ':' . $token, 'event_type' => 'stock_receive', 'object_type' => 'product', 'object_id' => (int) $item->product_id, 'product_id' => (int) $item->product_id, 'variation_id' => (int) $item->variation_id, 'qty_delta' => $recv, 'reason' => 'purchase_receipt', 'reference' => 'PO#' . $po_id ) );
					}
				}
			}

			if ( $recv > 0 ) {
				$wpdb->update( self::item_table(), array( 'qty_received' => (int) $item->qty_received + $recv ), array( 'id' => $item->id ) ); // phpcs:ignore WordPress.DB
				$received_any = true;
			}
		}

		if ( $received_any ) {
			$this->refresh_status( $po_id );
			if ( class_exists( 'Cellcraze_Audit' ) ) {
				Cellcraze_Audit::log( array( 'event_key' => 'po_received:' . $po_id . ':' . $token, 'event_type' => 'po_received', 'object_type' => 'po', 'object_id' => $po_id, 'reason' => 'grn', 'reference' => 'PO#' . $po_id ) );
			}
		}
		$code = ! empty( $errors ) ? 'recv_err' : 'received';
		set_transient( 'cellcraze_grn_errors_' . get_current_user_id(), $errors, 60 );
		$this->redirect_detail( $po_id, array( 'cc_po' => $code ) );
	}

	/* ------------------------------------------------------------------ */
	/* Shared guard + redirects                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Capability + nonce guard.
	 *
	 * @param string $action     Nonce action.
	 * @param string $nonce_name Nonce field name (default _wpnonce via check_admin_referer).
	 */
	private function guard( $action, $nonce_name = '_wpnonce' ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cellcraze-core' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action, $nonce_name );
	}

	/** @param array $args */
	private function redirect_list( $args = array() ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=cellcraze-purchase-orders' ) ) );
		exit;
	}
	/** @param int $po_id @param array $args */
	private function redirect_detail( $po_id, $args = array() ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'po' => $po_id ), $args ), admin_url( 'admin.php?page=cellcraze-purchase-orders' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* UI                                                                  */
	/* ------------------------------------------------------------------ */

	/** Submenu. */
	public function admin_menu() {
		add_submenu_page( 'woocommerce', __( 'Purchase Orders', 'cellcraze-core' ), __( 'Purchase Orders', 'cellcraze-core' ), self::CAP, 'cellcraze-purchase-orders', array( $this, 'render' ) );
	}

	/** Route list vs detail. */
	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cellcraze-core' ), '', array( 'response' => 403 ) );
		}
		$po_id = isset( $_GET['po'] ) ? absint( $_GET['po'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $po_id && self::get_po( $po_id ) ) {
			$this->render_detail( $po_id );
		} else {
			$this->render_list();
		}
	}

	/** List all POs + create form. */
	private function render_list() {
		global $wpdb;
		$po_t     = self::po_table();
		$sup_t    = Cellcraze_Suppliers::table();
		$item_t   = self::item_table();
		$statuses = self::statuses();
		$active   = Cellcraze_Suppliers::active();

		// Filters (read-only GET; no nonce needed).
		$f_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$f_sup    = isset( $_GET['supplier'] ) ? absint( $_GET['supplier'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$where    = array( '1=1' );
		$params   = array();
		if ( $f_status && isset( $statuses[ $f_status ] ) ) { $where[] = 'p.status = %s'; $params[] = $f_status; }
		if ( $f_sup ) { $where[] = 'p.supplier_id = %d'; $params[] = $f_sup; }
		$where_sql = implode( ' AND ', $where );
		// One grouped query: joins supplier + sums outstanding per PO (no per-row queries).
		$sql = "SELECT p.*, s.name AS supplier_name, COALESCE(SUM(i.qty_ordered - i.qty_received),0) AS outstanding
			FROM {$po_t} p
			LEFT JOIN {$sup_t} s ON s.id = p.supplier_id
			LEFT JOIN {$item_t} i ON i.po_id = p.id
			WHERE {$where_sql}
			GROUP BY p.id ORDER BY p.id DESC LIMIT 200";
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB
		?>
		<div class="wrap cc-admin">
			<div class="cc-head"><div><h1><?php esc_html_e( 'Purchase Orders', 'cellcraze-core' ); ?></h1><p class="cc-sub"><?php esc_html_e( 'Create purchase orders, send them, and receive goods into inventory.', 'cellcraze-core' ); ?></p></div></div>

			<h2><?php esc_html_e( 'New purchase order', 'cellcraze-core' ); ?></h2>
			<?php if ( empty( $active ) ) : ?>
				<div class="cc-empty"><?php printf( wp_kses_post( __( 'Add an active supplier first on the <a href="%s">Suppliers</a> screen.', 'cellcraze-core' ) ), esc_url( admin_url( 'admin.php?page=cellcraze-suppliers' ) ) ); ?></div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cc-form">
					<?php wp_nonce_field( 'cellcraze_create_po' ); ?>
					<input type="hidden" name="action" value="cellcraze_create_po" />
					<div><label><?php esc_html_e( 'Supplier', 'cellcraze-core' ); ?></label>
						<select name="supplier_id" required><option value=""><?php esc_html_e( '— select —', 'cellcraze-core' ); ?></option>
						<?php foreach ( $active as $s ) : ?><option value="<?php echo (int) $s->id; ?>"><?php echo esc_html( $s->name ); ?></option><?php endforeach; ?>
						</select></div>
					<div><label><?php esc_html_e( 'Supplier reference (optional)', 'cellcraze-core' ); ?></label><input type="text" name="reference" maxlength="100" /></div>
					<div class="full"><label><?php esc_html_e( 'Note', 'cellcraze-core' ); ?></label><input type="text" name="note" maxlength="1000" /></div>
					<div class="full"><button class="button button-primary"><?php esc_html_e( 'Create draft PO', 'cellcraze-core' ); ?></button></div>
				</form>
			<?php endif; ?>

			<h2><?php esc_html_e( 'All purchase orders', 'cellcraze-core' ); ?></h2>
			<form method="get" class="cc-filters">
				<input type="hidden" name="page" value="cellcraze-purchase-orders" />
				<div><label><?php esc_html_e( 'Status', 'cellcraze-core' ); ?></label>
					<select name="status"><option value=""><?php esc_html_e( 'All', 'cellcraze-core' ); ?></option>
					<?php foreach ( $statuses as $sk => $sl ) : ?><option value="<?php echo esc_attr( $sk ); ?>" <?php selected( $f_status, $sk ); ?>><?php echo esc_html( $sl ); ?></option><?php endforeach; ?>
					</select></div>
				<div><label><?php esc_html_e( 'Supplier', 'cellcraze-core' ); ?></label>
					<select name="supplier"><option value="0"><?php esc_html_e( 'All', 'cellcraze-core' ); ?></option>
					<?php foreach ( $active as $s ) : ?><option value="<?php echo (int) $s->id; ?>" <?php selected( $f_sup, (int) $s->id ); ?>><?php echo esc_html( $s->name ); ?></option><?php endforeach; ?>
					</select></div>
				<div><button class="button"><?php esc_html_e( 'Filter', 'cellcraze-core' ); ?></button></div>
			</form>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'PO', 'cellcraze-core' ); ?></th><th><?php esc_html_e( 'Supplier', 'cellcraze-core' ); ?></th><th><?php esc_html_e( 'Status', 'cellcraze-core' ); ?></th><th class="cc-num"><?php esc_html_e( 'Outstanding', 'cellcraze-core' ); ?></th><th class="cc-num"><?php esc_html_e( 'Total', 'cellcraze-core' ); ?></th><th><?php esc_html_e( 'Created', 'cellcraze-core' ); ?></th></tr></thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="6"><div class="cc-empty"><?php esc_html_e( 'No purchase orders match.', 'cellcraze-core' ); ?></div></td></tr>
				<?php else : foreach ( $rows as $p ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=cellcraze-purchase-orders&po=' . (int) $p->id ) ); ?>"><strong>PO#<?php echo (int) $p->id; ?></strong></a><?php echo $p->reference ? ' <span class="cc-sub">' . esc_html( $p->reference ) . '</span>' : ''; ?></td>
						<td><?php echo esc_html( $p->supplier_name ); ?></td>
						<td><span class="cc-chip <?php echo esc_attr( self::status_class( $p->status ) ); ?>"><?php echo esc_html( isset( $statuses[ $p->status ] ) ? $statuses[ $p->status ] : $p->status ); ?></span></td>
						<td class="cc-num"><?php echo (int) $p->outstanding; ?></td>
						<td class="cc-num"><?php echo wp_kses_post( wc_price( $p->total ) ); ?></td>
						<td><?php echo esc_html( mysql2date( 'Y-m-d', $p->created_at ) ); ?></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/** PO detail: items, status actions, receive (GRN) form. */
	private function render_detail( $po_id ) {
		$po       = self::get_po( $po_id );
		$items    = self::get_items( $po_id );
		$supplier = Cellcraze_Suppliers::get( $po->supplier_id );
		$statuses = self::statuses();
		$notice   = isset( $_GET['cc_po'] ) ? sanitize_key( $_GET['cc_po'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$errs     = get_transient( 'cellcraze_grn_errors_' . get_current_user_id() );
		delete_transient( 'cellcraze_grn_errors_' . get_current_user_id() );
		$is_draft = self::STATUS_DRAFT === $po->status;
		$can_recv = in_array( $po->status, array( self::STATUS_ORDERED, self::STATUS_PARTIAL ), true );
		?>
		<div class="wrap cc-admin">
			<div class="cc-head">
				<div>
					<h1><?php echo esc_html( 'PO#' . $po_id ); ?> <span class="cc-chip <?php echo esc_attr( self::status_class( $po->status ) ); ?>"><?php echo esc_html( $statuses[ $po->status ] ); ?></span></h1>
					<p class="cc-sub"><?php echo esc_html( ( $supplier ? $supplier->name : __( 'Unknown supplier', 'cellcraze-core' ) ) . ( $po->reference ? ' · ' . $po->reference : '' ) ); ?></p>
				</div>
				<div class="cc-head-actions"><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cellcraze-purchase-orders' ) ); ?>"><?php esc_html_e( '← All POs', 'cellcraze-core' ); ?></a></div>
			</div>
			<?php if ( $notice ) : ?>
				<div class="notice <?php echo in_array( $notice, array( 'received', 'itemadded', 'statusok' ), true ) ? 'notice-success' : ( 'recv_err' === $notice ? 'notice-warning' : 'notice-error' ); ?> is-dismissible"><p>
					<?php echo esc_html( $this->notice_text( $notice ) ); ?>
					<?php if ( 'recv_err' === $notice && is_array( $errs ) ) : foreach ( $errs as $e ) : ?><br><?php echo esc_html( $e ); ?><?php endforeach; endif; ?>
				</p></div>
			<?php endif; ?>

			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Product', 'cellcraze-core' ); ?></th><th><?php esc_html_e( 'IMEI?', 'cellcraze-core' ); ?></th><th class="cc-num"><?php esc_html_e( 'Ordered', 'cellcraze-core' ); ?></th><th class="cc-num"><?php esc_html_e( 'Received', 'cellcraze-core' ); ?></th><th class="cc-num"><?php esc_html_e( 'Unit cost', 'cellcraze-core' ); ?></th><th class="cc-num"><?php esc_html_e( 'Line total', 'cellcraze-core' ); ?></th></tr></thead>
				<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr><td colspan="6"><div class="cc-empty"><?php esc_html_e( 'No items yet. Add items below while the PO is a draft.', 'cellcraze-core' ); ?></div></td></tr>
				<?php else : foreach ( $items as $it ) : $p = wc_get_product( $it->variation_id ? $it->variation_id : $it->product_id ); ?>
					<tr>
						<td><?php echo esc_html( $p ? $p->get_name() : ( '#' . $it->product_id ) ); ?></td>
						<td><?php echo $it->requires_imei ? esc_html__( 'Yes', 'cellcraze-core' ) : '&mdash;'; ?></td>
						<td class="cc-num"><?php echo (int) $it->qty_ordered; ?></td>
						<td class="cc-num"><?php echo (int) $it->qty_received; ?></td>
						<td class="cc-num"><?php echo wp_kses_post( wc_price( $it->unit_cost ) ); ?></td>
						<td class="cc-num"><?php echo wp_kses_post( wc_price( $it->qty_ordered * $it->unit_cost ) ); ?></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
				<tfoot><tr><th colspan="5" class="cc-num"><?php esc_html_e( 'PO total', 'cellcraze-core' ); ?></th><th class="cc-num"><?php echo wp_kses_post( wc_price( $po->total ) ); ?></th></tr></tfoot>
			</table>

			<?php if ( $is_draft ) : ?>
				<h2><?php esc_html_e( 'Add item', 'cellcraze-core' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cc-form">
					<?php wp_nonce_field( 'cellcraze_add_po_item' ); ?>
					<input type="hidden" name="action" value="cellcraze_add_po_item" />
					<input type="hidden" name="po_id" value="<?php echo (int) $po_id; ?>" />
					<div class="full"><label><?php esc_html_e( 'Product', 'cellcraze-core' ); ?></label>
						<select name="product_id" required><option value=""><?php esc_html_e( '— select —', 'cellcraze-core' ); ?></option>
						<?php foreach ( wc_get_products( array( 'limit' => 100, 'status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ) ) as $prod ) : ?>
							<option value="<?php echo (int) $prod->get_id(); ?>"><?php echo esc_html( $prod->get_name() . ' (' . $prod->get_sku() . ')' ); ?></option>
						<?php endforeach; ?>
						</select></div>
					<div><label><?php esc_html_e( 'Quantity', 'cellcraze-core' ); ?></label><input type="number" min="1" name="qty_ordered" value="1" required /></div>
					<div><label><?php esc_html_e( 'Unit cost (LKR)', 'cellcraze-core' ); ?></label><input type="number" step="0.01" min="0" name="unit_cost" value="0" required /></div>
					<div><label><?php esc_html_e( 'Warranty (months)', 'cellcraze-core' ); ?></label><input type="number" min="0" name="warranty_months" value="12" /></div>
					<div><label><input type="checkbox" name="requires_imei" value="1" /> <?php esc_html_e( 'Requires IMEI/serial', 'cellcraze-core' ); ?></label></div>
					<div class="full"><button class="button button-primary"><?php esc_html_e( 'Add item', 'cellcraze-core' ); ?></button></div>
				</form>

				<?php if ( ! empty( $items ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px" onsubmit="return confirm('<?php echo esc_js( __( 'Mark this PO as ordered? Items can no longer be changed.', 'cellcraze-core' ) ); ?>');">
						<?php wp_nonce_field( 'cellcraze_po_status_' . $po_id, 'cc_po_status_nonce' ); ?>
						<input type="hidden" name="action" value="cellcraze_po_status" />
						<input type="hidden" name="po_id" value="<?php echo (int) $po_id; ?>" />
						<input type="hidden" name="to" value="ordered" />
						<button class="button button-primary"><?php esc_html_e( 'Mark as ordered', 'cellcraze-core' ); ?></button>
					</form>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $can_recv ) : ?>
				<h2><?php esc_html_e( 'Receive goods (GRN)', 'cellcraze-core' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'cellcraze_receive_po_' . $po_id, 'cc_grn_nonce' ); ?>
					<input type="hidden" name="action" value="cellcraze_receive_po" />
					<input type="hidden" name="po_id" value="<?php echo (int) $po_id; ?>" />
					<input type="hidden" name="grn_token" value="<?php echo esc_attr( wp_generate_password( 24, false ) ); ?>" />
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Product', 'cellcraze-core' ); ?></th><th class="cc-num"><?php esc_html_e( 'Remaining', 'cellcraze-core' ); ?></th><th class="cc-num"><?php esc_html_e( 'Receive now', 'cellcraze-core' ); ?></th><th><?php esc_html_e( 'IMEIs (one per line, must equal qty)', 'cellcraze-core' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $items as $it ) :
							$remaining = (int) $it->qty_ordered - (int) $it->qty_received;
							$p = wc_get_product( $it->variation_id ? $it->variation_id : $it->product_id );
							if ( $remaining <= 0 ) { continue; } ?>
							<tr>
								<td><?php echo esc_html( $p ? $p->get_name() : ( '#' . $it->product_id ) ); ?></td>
								<td class="cc-num"><?php echo (int) $remaining; ?></td>
								<td class="cc-num"><input type="number" min="0" max="<?php echo (int) $remaining; ?>" name="recv_<?php echo (int) $it->id; ?>" value="0" style="width:80px" /></td>
								<td><?php if ( $it->requires_imei ) : ?><textarea name="imei_<?php echo (int) $it->id; ?>" rows="2" style="width:100%" placeholder="<?php esc_attr_e( 'One IMEI per line', 'cellcraze-core' ); ?>"></textarea><?php else : ?><span class="cc-sub"><?php esc_html_e( 'Quantity product — no IMEIs', 'cellcraze-core' ); ?></span><?php endif; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<p><button class="button button-primary"><?php esc_html_e( 'Receive goods', 'cellcraze-core' ); ?></button></p>
				</form>
			<?php endif; ?>

			<?php
			global $wpdb;
			$grns = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . self::grn_table() . " WHERE po_id = %d ORDER BY id DESC", $po_id ) ); // phpcs:ignore WordPress.DB
			if ( $grns ) : ?>
				<h2><?php esc_html_e( 'Receipt history (GRN)', 'cellcraze-core' ); ?></h2>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'GRN', 'cellcraze-core' ); ?></th><th><?php esc_html_e( 'Received by', 'cellcraze-core' ); ?></th><th><?php esc_html_e( 'When', 'cellcraze-core' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $grns as $g ) : $gu = $g->received_by ? get_userdata( $g->received_by ) : null; ?>
						<tr><td>GRN#<?php echo (int) $g->id; ?></td><td><?php echo esc_html( $gu ? $gu->display_name : ( '#' . (int) $g->received_by ) ); ?></td><td><?php echo esc_html( $g->created_at ); ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( $is_draft || self::STATUS_ORDERED === $po->status ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px" onsubmit="return confirm('<?php echo esc_js( __( 'Cancel this purchase order?', 'cellcraze-core' ) ); ?>');">
					<?php wp_nonce_field( 'cellcraze_po_status_' . $po_id, 'cc_po_status_nonce' ); ?>
					<input type="hidden" name="action" value="cellcraze_po_status" />
					<input type="hidden" name="po_id" value="<?php echo (int) $po_id; ?>" />
					<input type="hidden" name="to" value="cancelled" />
					<button class="button"><?php esc_html_e( 'Cancel PO', 'cellcraze-core' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Notice text.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	private function notice_text( $code ) {
		$m = array(
			'received'      => __( 'Goods received and inventory updated.', 'cellcraze-core' ),
			'recv_err'      => __( 'Some lines were not received:', 'cellcraze-core' ),
			'itemadded'     => __( 'Item added.', 'cellcraze-core' ),
			'statusok'      => __( 'Purchase order updated.', 'cellcraze-core' ),
			'dupe'          => __( 'That receipt was already processed (ignored duplicate submission).', 'cellcraze-core' ),
			'notdraft'      => __( 'Items can only be changed on a draft PO.', 'cellcraze-core' ),
			'baditem'       => __( 'Invalid item.', 'cellcraze-core' ),
			'notreceivable' => __( 'This PO cannot receive goods in its current status.', 'cellcraze-core' ),
			'nosupplier'    => __( 'Select a valid supplier.', 'cellcraze-core' ),
		);
		return isset( $m[ $code ] ) ? $m[ $code ] : __( 'Done.', 'cellcraze-core' );
	}
}
