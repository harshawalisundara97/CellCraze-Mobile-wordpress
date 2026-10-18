<?php
/**
 * Profit & COGS report (task T-104).
 *
 * Read-only aggregation over native WooCommerce orders/refunds + the T-104 COGS
 * snapshot + the T-103 expense table. Recognizes sales only for paid/fulfilled
 * orders (processing, completed); cancelled/refunded/pending/failed are excluded
 * from recognized sales. All money is ex-tax (tax currently off — decision D7).
 * Refunds (v1) are attributed to the parent order's period.
 *
 * @package CellCraze_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cellcraze_Reports
 */
class Cellcraze_Reports {

	const CAP = 'cellcraze_view_reports';

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_post_cellcraze_export_report', array( $this, 'handle_export' ) );
	}

	/**
	 * Submenu under WooCommerce.
	 */
	public function admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Profit Report', 'cellcraze-core' ),
			__( 'Profit Report', 'cellcraze-core' ),
			self::CAP,
			'cellcraze-reports',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Read + sanitize the filter inputs (GET; read-only, no nonce needed).
	 *
	 * @return array { from, to, channel }
	 */
	private function filters() {
		$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$to   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$chan = isset( $_GET['channel'] ) ? sanitize_key( $_GET['channel'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $from || ! DateTime::createFromFormat( 'Y-m-d', $from ) ) {
			$from = gmdate( 'Y-m-01' );
		}
		if ( ! $to || ! DateTime::createFromFormat( 'Y-m-d', $to ) ) {
			$to = gmdate( 'Y-m-d' );
		}
		if ( ! in_array( $chan, array( 'online', 'pos' ), true ) ) {
			$chan = '';
		}
		return array( 'from' => $from, 'to' => $to, 'channel' => $chan );
	}

	/**
	 * Compute the report figures for a filter set.
	 *
	 * @param array $f Filters.
	 * @return array Totals + per-order rows.
	 */
	public static function compute( $f ) {
		$from_ts = strtotime( $f['from'] . ' 00:00:00' );
		$to_ts   = strtotime( $f['to'] . ' 23:59:59' );

		$args = array(
			'limit'        => -1,
			'type'         => 'shop_order',
			'status'       => array( 'wc-processing', 'wc-completed' ),
			'date_created' => $from_ts . '...' . $to_ts,
			'return'       => 'objects',
		);
		$orders = wc_get_orders( $args );

		$t = array(
			'gross_sales' => 0.0,
			'discounts'   => 0.0,
			'net_sales'   => 0.0,
			'refunds'     => 0.0,
			'cogs'        => 0.0,
			'shipping'    => 0.0,
			'opex'        => 0.0,
			'orders'      => 0,
			'approximate' => false,
			'rows'        => array(),
		);

		foreach ( $orders as $order ) {
			$channel = Cellcraze_Channel::get( $order );
			if ( $f['channel'] && $channel !== $f['channel'] ) {
				continue;
			}
			$gross    = (float) $order->get_subtotal(); // ex-tax, pre-discount.
			$discount = (float) $order->get_total_discount(); // ex-tax.
			$refund   = (float) $order->get_total_refunded() - (float) $order->get_total_tax_refunded();
			$cogs     = Cellcraze_COGS::order_cogs( $order );

			$t['gross_sales'] += $gross;
			$t['discounts']   += $discount;
			$t['refunds']     += $refund;
			$t['shipping']    += (float) $order->get_shipping_total();
			$t['cogs']        += $cogs['cogs'];
			$t['orders']++;
			if ( $cogs['approximate'] ) {
				$t['approximate'] = true;
			}

			$net_line = $gross - $discount;
			$t['rows'][] = array(
				'order'   => $order->get_order_number(),
				'date'    => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d' ) : '',
				'channel' => $channel,
				'status'  => $order->get_status(),
				'net'     => round( $net_line, 2 ),
				'refund'  => round( $refund, 2 ),
				'cogs'    => $cogs['cogs'],
				'profit'  => round( $net_line - $refund - $cogs['cogs'], 2 ),
				'approx'  => $cogs['approximate'],
			);
		}

		$t['net_sales'] = round( $t['gross_sales'] - $t['discounts'], 2 );

		// Operating expenses from the T-103 table (active only).
		global $wpdb;
		$etable     = Cellcraze_Expense::table();
		$t['opex']  = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount),0) FROM {$etable} WHERE status = %s AND expense_date BETWEEN %s AND %s", // phpcs:ignore WordPress.DB
				'active',
				$f['from'],
				$f['to']
			)
		);

		$net_after_refunds      = round( $t['net_sales'] - $t['refunds'], 2 );
		$t['net_after_refunds'] = $net_after_refunds;
		$t['gross_profit']      = round( $net_after_refunds - $t['cogs'], 2 );
		$t['operating_profit']  = round( $t['gross_profit'] - $t['opex'], 2 );
		$t['margin']            = $net_after_refunds > 0 ? round( $t['gross_profit'] / $net_after_refunds * 100, 1 ) : 0.0;

		// round summary money
		foreach ( array( 'gross_sales', 'discounts', 'refunds', 'cogs', 'shipping', 'opex' ) as $k ) {
			$t[ $k ] = round( $t[ $k ], 2 );
		}
		return $t;
	}

	/**
	 * Render the report page.
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cellcraze-core' ), '', array( 'response' => 403 ) );
		}
		$f = $this->filters();
		$t = self::compute( $f );
		$export = wp_nonce_url(
			admin_url( 'admin-post.php?action=cellcraze_export_report&from=' . rawurlencode( $f['from'] ) . '&to=' . rawurlencode( $f['to'] ) . '&channel=' . rawurlencode( $f['channel'] ) ),
			'cellcraze_export_report'
		);
		?>
		<div class="wrap cc-admin">
			<div class="cc-head">
				<div>
					<h1><?php esc_html_e( 'Profit Report', 'cellcraze-core' ); ?></h1>
					<p class="cc-sub"><?php esc_html_e( 'Recognized sales = paid/fulfilled orders (processing, completed). Amounts ex-tax; refunds by order period.', 'cellcraze-core' ); ?></p>
				</div>
				<div class="cc-head-actions"><a class="button" href="<?php echo esc_url( $export ); ?>"><?php esc_html_e( 'Export CSV', 'cellcraze-core' ); ?></a></div>
			</div>

			<form method="get" class="cc-filters">
				<input type="hidden" name="page" value="cellcraze-reports" />
				<div><label><?php esc_html_e( 'From', 'cellcraze-core' ); ?></label><br><input type="date" name="from" value="<?php echo esc_attr( $f['from'] ); ?>" /></div>
				<div><label><?php esc_html_e( 'To', 'cellcraze-core' ); ?></label><br><input type="date" name="to" value="<?php echo esc_attr( $f['to'] ); ?>" /></div>
				<div><label><?php esc_html_e( 'Channel', 'cellcraze-core' ); ?></label><br>
					<select name="channel">
						<option value=""<?php selected( $f['channel'], '' ); ?>><?php esc_html_e( 'All', 'cellcraze-core' ); ?></option>
						<option value="online"<?php selected( $f['channel'], 'online' ); ?>><?php esc_html_e( 'Online', 'cellcraze-core' ); ?></option>
						<option value="pos"<?php selected( $f['channel'], 'pos' ); ?>><?php esc_html_e( 'In-store (POS)', 'cellcraze-core' ); ?></option>
					</select></div>
				<div><button class="button button-primary"><?php esc_html_e( 'Run', 'cellcraze-core' ); ?></button></div>
				<div><a class="button" href="<?php echo esc_url( $export ); ?>"><?php esc_html_e( 'Export CSV', 'cellcraze-core' ); ?></a></div>
			</form>

			<?php if ( $t['approximate'] ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Some COGS figures are approximate (missing cost data or orders placed before cost snapshots began).', 'cellcraze-core' ); ?></p></div>
			<?php endif; ?>

			<?php
			// Headline stat cards.
			$op_class = $t['operating_profit'] >= 0 ? 'cc-pos' : 'cc-neg';
			$gp_class = $t['gross_profit'] >= 0 ? 'cc-pos' : 'cc-neg';
			$cards = array(
				array( __( 'Net sales after refunds', 'cellcraze-core' ), wc_price( $t['net_after_refunds'] ), 'cc-accent' ),
				array( __( 'COGS', 'cellcraze-core' ), wc_price( $t['cogs'] ), '' ),
				array( __( 'Gross profit', 'cellcraze-core' ), wc_price( $t['gross_profit'] ) . ' <span class="cc-sub">' . esc_html( $t['margin'] ) . '%</span>', $gp_class ),
				array( __( 'Operating profit', 'cellcraze-core' ), wc_price( $t['operating_profit'] ), $op_class ),
			);
			echo '<div class="cc-cards">';
			foreach ( $cards as $c ) {
				echo '<div class="cc-card ' . esc_attr( $c[2] ) . '"><div class="cc-card-label">' . esc_html( $c[0] ) . '</div><div class="cc-card-value">' . wp_kses_post( $c[1] ) . '</div></div>';
			}
			echo '</div>';
			?>

			<table class="widefat" style="max-width:520px;margin-bottom:20px">
				<tbody>
					<?php
					$lines = array(
						__( 'Orders (recognized)', 'cellcraze-core' ) => (string) $t['orders'],
						__( 'Gross sales', 'cellcraze-core' )         => wc_price( $t['gross_sales'] ),
						__( 'Discounts', 'cellcraze-core' )            => '-' . wc_price( $t['discounts'] ),
						__( 'Net sales', 'cellcraze-core' )            => wc_price( $t['net_sales'] ),
						__( 'Refunds', 'cellcraze-core' )              => '-' . wc_price( $t['refunds'] ),
						__( 'Operating expenses', 'cellcraze-core' )   => '-' . wc_price( $t['opex'] ),
						__( 'Shipping income (memo)', 'cellcraze-core' ) => wc_price( $t['shipping'] ),
					);
					foreach ( $lines as $label => $val ) {
						echo '<tr><td>' . esc_html( $label ) . '</td><td class="cc-num">' . wp_kses_post( $val ) . '</td></tr>';
					}
					?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Orders in range', 'cellcraze-core' ); ?></h2>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Order', 'cellcraze-core' ); ?></th>
					<th><?php esc_html_e( 'Date', 'cellcraze-core' ); ?></th>
					<th><?php esc_html_e( 'Channel', 'cellcraze-core' ); ?></th>
					<th><?php esc_html_e( 'Net', 'cellcraze-core' ); ?></th>
					<th><?php esc_html_e( 'Refund', 'cellcraze-core' ); ?></th>
					<th><?php esc_html_e( 'COGS', 'cellcraze-core' ); ?></th>
					<th><?php esc_html_e( 'Gross profit', 'cellcraze-core' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $t['rows'] ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No recognized sales in this range.', 'cellcraze-core' ); ?></td></tr>
				<?php else : foreach ( $t['rows'] as $r ) : ?>
					<tr>
						<td>#<?php echo esc_html( $r['order'] ); ?></td>
						<td><?php echo esc_html( $r['date'] ); ?></td>
						<td><?php echo esc_html( $r['channel'] ); ?></td>
						<td class="cc-num"><?php echo wp_kses_post( wc_price( $r['net'] ) ); ?></td>
						<td class="cc-num"><?php echo wp_kses_post( wc_price( $r['refund'] ) ); ?></td>
						<td class="cc-num"><?php echo wp_kses_post( wc_price( $r['cogs'] ) ); ?><?php echo $r['approx'] ? ' *' : ''; ?></td>
						<td class="cc-num"><?php echo wp_kses_post( wc_price( $r['profit'] ) ); ?></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * CSV export — capability + nonce gated; not publicly cacheable.
	 */
	public function handle_export() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cellcraze-core' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'cellcraze_export_report' );
		$f = $this->filters();
		$t = self::compute( $f );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="cellcraze-profit-' . $f['from'] . '_' . $f['to'] . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Order', 'Date', 'Channel', 'Status', 'Net', 'Refund', 'COGS', 'GrossProfit', 'Approx' ) );
		foreach ( $t['rows'] as $r ) {
			fputcsv( $out, array( $r['order'], $r['date'], $r['channel'], $r['status'], $r['net'], $r['refund'], $r['cogs'], $r['profit'], $r['approx'] ? 'yes' : '' ) );
		}
		fputcsv( $out, array() );
		fputcsv( $out, array( 'TOTALS', '', '', '', $t['net_sales'], $t['refunds'], $t['cogs'], $t['gross_profit'], '' ) );
		fputcsv( $out, array( 'Operating expenses', '', '', '', '', '', '', $t['opex'], '' ) );
		fputcsv( $out, array( 'Operating profit', '', '', '', '', '', '', $t['operating_profit'], '' ) );
		fclose( $out );
		exit;
	}
}
