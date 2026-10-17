<?php
/**
 * Shop-owner dashboard — read-only overview of REAL data only.
 *
 * Every figure is computed live from existing sources: Cellcraze_Reports::compute()
 * (which reads native WooCommerce orders), the T-103 expense table, and the
 * IMEI table. No figure is hardcoded, invented, or cached; nothing here changes
 * accounting, stock, IMEI, payment or order logic. Gated by cellcraze_view_reports.
 *
 * @package CellCraze_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cellcraze_Dashboard
 */
class Cellcraze_Dashboard {

	const CAP = 'cellcraze_view_reports';

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 5 );
	}

	/**
	 * Register the dashboard as the first CellCraze submenu under WooCommerce.
	 */
	public function admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'CellCraze Dashboard', 'cellcraze-core' ),
			__( 'CellCraze Dashboard', 'cellcraze-core' ),
			self::CAP,
			'cellcraze-dashboard',
			array( $this, 'render' )
		);
	}

	/**
	 * Count bank-transfer orders still awaiting verification (on-hold bacs).
	 *
	 * @return array Order objects.
	 */
	private function awaiting_verification() {
		return wc_get_orders(
			array(
				'limit'          => 20,
				'type'           => 'shop_order',
				'status'         => array( 'wc-on-hold' ),
				'payment_method' => 'bacs',
				'return'         => 'objects',
			)
		);
	}

	/**
	 * Products at or below their low-stock threshold (real stock data).
	 *
	 * @return array Of [name, qty, threshold].
	 */
	private function low_stock() {
		$out      = array();
		$products = wc_get_products(
			array(
				'limit'        => -1,
				'status'       => 'publish',
				'stock_status' => 'instock',
				'manage_stock' => true,
				'return'       => 'objects',
			)
		);
		foreach ( $products as $p ) {
			$qty = $p->get_stock_quantity();
			if ( null === $qty ) {
				continue;
			}
			$threshold = $p->get_low_stock_amount();
			$threshold = ( '' === $threshold || null === $threshold ) ? (int) get_option( 'woocommerce_notify_low_stock_amount', 2 ) : (int) $threshold;
			if ( $qty <= $threshold ) {
				$out[] = array(
					'name'      => $p->get_name(),
					'qty'       => (int) $qty,
					'threshold' => $threshold,
					'id'        => $p->get_id(),
				);
			}
		}
		return $out;
	}

	/**
	 * Count available IMEI units (real).
	 *
	 * @return int
	 */
	private function available_imei() {
		global $wpdb;
		$table = Cellcraze_IMEI_DB::table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", Cellcraze_IMEI_DB::STATUS_AVAILABLE ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Purchasing counts via cheap grouped aggregates (no per-row queries).
	 * PO statuses are mutually exclusive, so counts never double-count.
	 *
	 * @return array
	 */
	private function purchasing_stats() {
		global $wpdb;
		$po     = Cellcraze_Purchasing::po_table();
		$counts = $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM {$po} GROUP BY status", OBJECT_K ); // phpcs:ignore WordPress.DB
		$n      = static function ( $s ) use ( $counts ) {
			return isset( $counts[ $s ] ) ? (int) $counts[ $s ]->c : 0;
		};
		return array(
			'draft'      => $n( Cellcraze_Purchasing::STATUS_DRAFT ),
			'ordered'    => $n( Cellcraze_Purchasing::STATUS_ORDERED ),
			'partial'    => $n( Cellcraze_Purchasing::STATUS_PARTIAL ),
			'suppliers'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . Cellcraze_Suppliers::table() . " WHERE status = %s", Cellcraze_Suppliers::STATUS_ACTIVE ) ), // phpcs:ignore WordPress.DB
			'recent_grn' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . Cellcraze_Purchasing::grn_table() . " WHERE created_at >= %s", gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ) ), // phpcs:ignore WordPress.DB
		);
	}

	/**
	 * Render the dashboard.
	 */
	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cellcraze-core' ), '', array( 'response' => 403 ) );
		}

		$today  = gmdate( 'Y-m-d' );
		$m_from = gmdate( 'Y-m-01' );
		$day    = Cellcraze_Reports::compute( array( 'from' => $today, 'to' => $today, 'channel' => '' ) );
		$month  = Cellcraze_Reports::compute( array( 'from' => $m_from, 'to' => $today, 'channel' => '' ) );
		$online = Cellcraze_Reports::compute( array( 'from' => $m_from, 'to' => $today, 'channel' => 'online' ) );
		$pos    = Cellcraze_Reports::compute( array( 'from' => $m_from, 'to' => $today, 'channel' => 'pos' ) );

		$processing = count( wc_get_orders( array( 'limit' => -1, 'status' => 'wc-processing', 'return' => 'ids', 'type' => 'shop_order' ) ) );
		$awaiting    = $this->awaiting_verification();
		$low         = $this->low_stock();
		$avail_imei  = $this->available_imei();
		$pur         = $this->purchasing_stats();

		$card = function ( $label, $value, $cls = '' ) {
			echo '<div class="cc-card ' . esc_attr( $cls ) . '"><div class="cc-card-label">' . esc_html( $label ) . '</div><div class="cc-card-value">' . wp_kses_post( $value ) . '</div></div>';
		};
		?>
		<div class="wrap cc-admin">
			<div class="cc-head">
				<div>
					<h1><?php esc_html_e( 'CellCraze Dashboard', 'cellcraze-core' ); ?></h1>
					<p class="cc-sub"><?php echo esc_html( sprintf( /* translators: %s: month */ __( 'Live figures for today and %s (month to date). Amounts ex-tax.', 'cellcraze-core' ), gmdate( 'F Y' ) ) ); ?></p>
				</div>
				<div class="cc-head-actions">
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cellcraze-reports' ) ); ?>"><?php esc_html_e( 'Profit Report', 'cellcraze-core' ); ?></a>
				</div>
			</div>

			<h2><?php esc_html_e( 'Sales', 'cellcraze-core' ); ?></h2>
			<div class="cc-cards">
				<?php
				$card( __( "Today — net sales", 'cellcraze-core' ), wc_price( $day['net_after_refunds'] ), 'cc-accent' );
				$card( __( 'Month — net sales', 'cellcraze-core' ), wc_price( $month['net_after_refunds'] ) );
				$card( __( 'Month — gross profit', 'cellcraze-core' ), wc_price( $month['gross_profit'] ) . ' <span class="cc-sub">' . esc_html( $month['margin'] ) . '%</span>', $month['gross_profit'] >= 0 ? 'cc-pos' : 'cc-neg' );
				$card( __( 'Month — operating profit', 'cellcraze-core' ), wc_price( $month['operating_profit'] ), $month['operating_profit'] >= 0 ? 'cc-pos' : 'cc-neg' );
				?>
			</div>
			<?php if ( $month['approximate'] ) : ?>
				<p class="description"><?php esc_html_e( '* Some COGS is approximate (missing cost or pre-snapshot orders).', 'cellcraze-core' ); ?></p>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Channel (month to date)', 'cellcraze-core' ); ?></h2>
			<div class="cc-cards">
				<?php
				$card( __( 'Online — net', 'cellcraze-core' ), wc_price( $online['net_after_refunds'] ) . ' <span class="cc-sub">' . (int) $online['orders'] . ' ' . esc_html__( 'orders', 'cellcraze-core' ) . '</span>' );
				$card( __( 'In-store (POS) — net', 'cellcraze-core' ), wc_price( $pos['net_after_refunds'] ) . ' <span class="cc-sub">' . (int) $pos['orders'] . ' ' . esc_html__( 'orders', 'cellcraze-core' ) . '</span>' );
				?>
			</div>

			<h2><?php esc_html_e( 'Operations', 'cellcraze-core' ); ?></h2>
			<div class="cc-cards">
				<?php
				$card( __( 'Orders processing', 'cellcraze-core' ), (string) $processing );
				$card( __( 'Awaiting bank verification', 'cellcraze-core' ), (string) count( $awaiting ), count( $awaiting ) ? 'cc-neg' : '' );
				$card( __( 'Low-stock products', 'cellcraze-core' ), (string) count( $low ), count( $low ) ? 'cc-neg' : '' );
				$card( __( 'Available IMEI units', 'cellcraze-core' ), (string) $avail_imei );
				?>
			</div>

			<h2><?php esc_html_e( 'Purchasing', 'cellcraze-core' ); ?></h2>
			<div class="cc-cards">
				<?php
				$card( __( 'Draft POs', 'cellcraze-core' ), (string) $pur['draft'] );
				$card( __( 'Open POs (ordered)', 'cellcraze-core' ), (string) $pur['ordered'], $pur['ordered'] ? 'cc-accent' : '' );
				$card( __( 'Partially received', 'cellcraze-core' ), (string) $pur['partial'] );
				$card( __( 'Active suppliers', 'cellcraze-core' ), (string) $pur['suppliers'] );
				$card( __( 'Goods received (7 days)', 'cellcraze-core' ), (string) $pur['recent_grn'] );
				?>
			</div>

			<h2><?php esc_html_e( 'Quick links', 'cellcraze-core' ); ?></h2>
			<p class="cc-head-actions">
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cellcraze-suppliers' ) ); ?>"><?php esc_html_e( 'Suppliers', 'cellcraze-core' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cellcraze-purchase-orders' ) ); ?>"><?php esc_html_e( 'Purchase Orders', 'cellcraze-core' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cellcraze-imei' ) ); ?>"><?php esc_html_e( 'IMEI Registry', 'cellcraze-core' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cellcraze-expenses' ) ); ?>"><?php esc_html_e( 'Expenses', 'cellcraze-core' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cellcraze-reports' ) ); ?>"><?php esc_html_e( 'Profit Report', 'cellcraze-core' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cellcraze-audit' ) ); ?>"><?php esc_html_e( 'Stock & Audit Log', 'cellcraze-core' ); ?></a>
			</p>

			<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:8px">
				<div>
					<h2><?php esc_html_e( 'Awaiting bank verification', 'cellcraze-core' ); ?></h2>
					<?php if ( empty( $awaiting ) ) : ?>
						<div class="cc-empty"><?php esc_html_e( 'Nothing awaiting verification.', 'cellcraze-core' ); ?></div>
					<?php else : ?>
						<table class="widefat striped">
							<thead><tr><th><?php esc_html_e( 'Order', 'cellcraze-core' ); ?></th><th><?php esc_html_e( 'Customer', 'cellcraze-core' ); ?></th><th class="cc-num"><?php esc_html_e( 'Total', 'cellcraze-core' ); ?></th></tr></thead>
							<tbody>
							<?php foreach ( $awaiting as $o ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>">#<?php echo esc_html( $o->get_order_number() ); ?></a></td>
									<td><?php echo esc_html( trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ) ); ?></td>
									<td class="cc-num"><?php echo wp_kses_post( wc_price( $o->get_total() ) ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
				<div>
					<h2><?php esc_html_e( 'Low stock', 'cellcraze-core' ); ?></h2>
					<?php if ( empty( $low ) ) : ?>
						<div class="cc-empty"><?php esc_html_e( 'No products below their low-stock threshold.', 'cellcraze-core' ); ?></div>
					<?php else : ?>
						<table class="widefat striped">
							<thead><tr><th><?php esc_html_e( 'Product', 'cellcraze-core' ); ?></th><th class="cc-num"><?php esc_html_e( 'In stock', 'cellcraze-core' ); ?></th><th class="cc-num"><?php esc_html_e( 'Threshold', 'cellcraze-core' ); ?></th></tr></thead>
							<tbody>
							<?php foreach ( $low as $l ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $l['id'] . '&action=edit' ) ); ?>"><?php echo esc_html( $l['name'] ); ?></a></td>
									<td class="cc-num"><?php echo (int) $l['qty']; ?></td>
									<td class="cc-num"><?php echo (int) $l['threshold']; ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
