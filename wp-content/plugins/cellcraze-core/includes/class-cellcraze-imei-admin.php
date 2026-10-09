<?php
/**
 * Admin UI: a product panel to add/list IMEI units, plus a global IMEI
 * registry page under WooCommerce.
 *
 * @package CellCraze_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cellcraze_IMEI_Admin
 */
class Cellcraze_IMEI_Admin {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		// Product data tab + panel.
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'product_panel' ) );
		add_action( 'admin_post_cellcraze_add_imei', array( $this, 'handle_add_imei' ) );
		add_action( 'admin_post_cellcraze_delete_imei', array( $this, 'handle_delete_imei' ) );

		// Global registry page.
		add_action( 'admin_menu', array( $this, 'registry_menu' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'styles' ) );
	}

	/**
	 * Light inline styling for the panels.
	 *
	 * @param string $hook Current admin page.
	 */
	public function styles( $hook ) {
		$css = '.cellcraze-imei-table{width:100%;border-collapse:collapse;margin:8px 0}'
			. '.cellcraze-imei-table th,.cellcraze-imei-table td{text-align:left;padding:6px 8px;border-bottom:1px solid #e0e0e0;font-size:12px}'
			. '.cellcraze-imei-add{display:grid;grid-template-columns:repeat(4,1fr) auto;gap:8px;align-items:end;margin-top:12px}'
			. '.cellcraze-imei-add label{display:block;font-size:11px;color:#50575e}';
		wp_register_style( 'cellcraze-imei-admin', false, array(), CELLCRAZE_CORE_VERSION );
		wp_enqueue_style( 'cellcraze-imei-admin' );
		wp_add_inline_style( 'cellcraze-imei-admin', $css );
	}

	/**
	 * Add an "IMEI / Serial" tab to the product data box.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public function product_tab( $tabs ) {
		$tabs['cellcraze_imei'] = array(
			'label'    => __( 'IMEI / Serial', 'cellcraze-core' ),
			'target'   => 'cellcraze_imei_panel',
			'priority' => 70,
			'class'    => array(),
		);
		return $tabs;
	}

	/**
	 * Render the product IMEI panel: list of units + add form.
	 */
	public function product_panel() {
		global $post;
		$product_id = $post ? $post->ID : 0;
		$units      = $product_id ? $this->units_for_product( $product_id ) : array();
		$statuses   = Cellcraze_IMEI_DB::statuses();
		?>
		<div id="cellcraze_imei_panel" class="panel woocommerce_options_panel">
			<div class="options_group" style="padding:12px;">
				<p>
					<strong><?php esc_html_e( 'Units for this product', 'cellcraze-core' ); ?></strong>
					&mdash;
					<?php
					printf(
						/* translators: %d: available unit count */
						esc_html__( '%d available', 'cellcraze-core' ),
						(int) Cellcraze_IMEI_DB::count_available( $product_id )
					);
					?>
				</p>

				<table class="cellcraze-imei-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'IMEI / Serial', 'cellcraze-core' ); ?></th>
							<th><?php esc_html_e( 'Status', 'cellcraze-core' ); ?></th>
							<th><?php esc_html_e( 'Order', 'cellcraze-core' ); ?></th>
							<th><?php esc_html_e( 'Cost', 'cellcraze-core' ); ?></th>
							<th><?php esc_html_e( 'Warranty', 'cellcraze-core' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $units ) ) : ?>
							<tr><td colspan="6"><?php esc_html_e( 'No units yet.', 'cellcraze-core' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $units as $unit ) : ?>
								<tr>
									<td><code><?php echo esc_html( $unit->imei ); ?></code></td>
									<td><?php echo esc_html( isset( $statuses[ $unit->status ] ) ? $statuses[ $unit->status ] : $unit->status ); ?></td>
									<td>
										<?php if ( $unit->order_id ) : ?>
											<a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $unit->order_id . '&action=edit' ) ); ?>">#<?php echo (int) $unit->order_id; ?></a>
										<?php else : ?>&mdash;<?php endif; ?>
									</td>
									<td><?php echo esc_html( wc_price( $unit->cost ) ); ?></td>
									<td><?php echo (int) $unit->warranty_months; ?><?php esc_html_e( ' mo', 'cellcraze-core' ); ?></td>
									<td>
										<?php if ( Cellcraze_IMEI_DB::STATUS_SOLD !== $unit->status ) : ?>
											<a href="<?php echo esc_url( $this->delete_url( $unit->id, $product_id ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this unit?', 'cellcraze-core' ); ?>');"><?php esc_html_e( 'Delete', 'cellcraze-core' ); ?></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cellcraze-imei-add">
					<?php wp_nonce_field( 'cellcraze_add_imei', 'cellcraze_imei_nonce' ); ?>
					<input type="hidden" name="action" value="cellcraze_add_imei" />
					<input type="hidden" name="product_id" value="<?php echo (int) $product_id; ?>" />
					<div>
						<label><?php esc_html_e( 'IMEI / Serial', 'cellcraze-core' ); ?></label>
						<input type="text" name="imei" required />
					</div>
					<div>
						<label><?php esc_html_e( 'Cost (LKR)', 'cellcraze-core' ); ?></label>
						<input type="number" step="0.01" name="cost" value="0" />
					</div>
					<div>
						<label><?php esc_html_e( 'Warranty (months)', 'cellcraze-core' ); ?></label>
						<input type="number" name="warranty_months" value="12" />
					</div>
					<div>
						<label><?php esc_html_e( 'Supplier', 'cellcraze-core' ); ?></label>
						<input type="text" name="supplier" />
					</div>
					<div>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Add unit', 'cellcraze-core' ); ?></button>
					</div>
				</form>
				<p class="description"><?php esc_html_e( 'Tip: paste multiple IMEIs separated by new lines or commas to add them at once.', 'cellcraze-core' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * All units for a product (any status), newest first.
	 *
	 * @param int $product_id Product id.
	 * @return array
	 */
	private function units_for_product( $product_id ) {
		global $wpdb;
		$table = Cellcraze_IMEI_DB::table();
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE product_id = %d ORDER BY id DESC", absint( $product_id ) ) // phpcs:ignore WordPress.DB
		);
	}

	/**
	 * Build a nonce'd delete URL.
	 *
	 * @param int $id         Unit id.
	 * @param int $product_id Product id.
	 * @return string
	 */
	private function delete_url( $id, $product_id ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=cellcraze_delete_imei&id=' . (int) $id . '&product_id=' . (int) $product_id ),
			'cellcraze_delete_imei_' . (int) $id
		);
	}

	/**
	 * Handle the add-unit form (supports bulk paste).
	 */
	public function handle_add_imei() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cellcraze-core' ) );
		}
		check_admin_referer( 'cellcraze_add_imei', 'cellcraze_imei_nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$raw        = isset( $_POST['imei'] ) ? sanitize_textarea_field( wp_unslash( $_POST['imei'] ) ) : '';
		$cost       = isset( $_POST['cost'] ) ? (float) $_POST['cost'] : 0;
		$warranty   = isset( $_POST['warranty_months'] ) ? absint( $_POST['warranty_months'] ) : 0;
		$supplier   = isset( $_POST['supplier'] ) ? sanitize_text_field( wp_unslash( $_POST['supplier'] ) ) : '';

		$imeis  = preg_split( '/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
		$result = self::receive(
			$product_id,
			$imeis,
			array(
				'cost'            => $cost,
				'warranty_months' => $warranty,
				'supplier'        => $supplier,
			)
		);

		$this->redirect_back( $product_id, array( 'cc_added' => $result['added'] ) );
	}

	/**
	 * Receive one or more units into inventory: create IMEI rows AND increase
	 * WooCommerce stock by the number actually added. Single source of truth
	 * for the stock-sync behaviour, shared by the admin handler and tests.
	 *
	 * @param int   $product_id Product id.
	 * @param array $imeis      IMEI/serial strings.
	 * @param array $meta       Optional cost/warranty_months/supplier/note.
	 * @return array { added:int, skipped:int }
	 */
	public static function receive( $product_id, $imeis, $meta = array() ) {
		$added   = 0;
		$skipped = 0;
		foreach ( (array) $imeis as $imei ) {
			$imei = trim( (string) $imei );
			if ( '' === $imei ) {
				continue;
			}
			$result = Cellcraze_IMEI_DB::add_unit(
				array_merge(
					$meta,
					array(
						'imei'       => $imei,
						'product_id' => absint( $product_id ),
					)
				)
			);
			if ( is_wp_error( $result ) ) {
				$skipped++;
			} else {
				$added++;
			}
		}
		if ( $added > 0 ) {
			self::adjust_stock( $product_id, $added );
		}
		return array( 'added' => $added, 'skipped' => $skipped );
	}

	/**
	 * Increase/decrease a product's managed stock by a delta, enabling stock
	 * management if it is off. Keeps Woo stock aligned with IMEI intake.
	 *
	 * @param int $product_id Product id.
	 * @param int $delta      Positive to add, negative to remove.
	 */
	private static function adjust_stock( $product_id, $delta ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}
		if ( ! $product->get_manage_stock() ) {
			$product->set_manage_stock( true );
			$product->save();
		}
		$operation = $delta >= 0 ? 'increase' : 'decrease';
		wc_update_product_stock( $product, abs( $delta ), $operation );
	}

	/**
	 * Handle unit deletion.
	 */
	public function handle_delete_imei() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cellcraze-core' ) );
		}
		$id         = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
		check_admin_referer( 'cellcraze_delete_imei_' . $id );

		$unit = Cellcraze_IMEI_DB::get( $id );
		if ( $unit && Cellcraze_IMEI_DB::STATUS_SOLD !== $unit->status ) {
			$was_available = ( Cellcraze_IMEI_DB::STATUS_AVAILABLE === $unit->status );
			Cellcraze_IMEI_DB::delete( $id );
			// Removing an available unit reduces sellable stock by one.
			if ( $was_available ) {
				self::adjust_stock( $product_id, -1 );
			}
		}
		$this->redirect_back( $product_id, array( 'cc_deleted' => 1 ) );
	}

	/**
	 * Redirect to the product edit screen (IMEI tab) after an action.
	 *
	 * @param int   $product_id Product id.
	 * @param array $args       Extra query args.
	 */
	private function redirect_back( $product_id, $args = array() ) {
		$url = $product_id
			? admin_url( 'post.php?post=' . (int) $product_id . '&action=edit' )
			: admin_url( 'edit.php?post_type=product' );
		wp_safe_redirect( add_query_arg( $args, $url ) );
		exit;
	}

	/**
	 * Register the global IMEI registry submenu page.
	 */
	public function registry_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'IMEI / Serial Registry', 'cellcraze-core' ),
			__( 'IMEI Registry', 'cellcraze-core' ),
			'manage_woocommerce',
			'cellcraze-imei',
			array( $this, 'registry_page' )
		);
	}

	/**
	 * Render the global registry: search + recent units.
	 */
	public function registry_page() {
		global $wpdb;
		$table    = Cellcraze_IMEI_DB::table();
		$statuses = Cellcraze_IMEI_DB::statuses();
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE imei LIKE %s ORDER BY id DESC LIMIT 200", $like ) ); // phpcs:ignore WordPress.DB
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 200" ); // phpcs:ignore WordPress.DB
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'IMEI / Serial Registry', 'cellcraze-core' ); ?></h1>
			<form method="get">
				<input type="hidden" name="page" value="cellcraze-imei" />
				<p class="search-box">
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search IMEI / serial', 'cellcraze-core' ); ?>" />
					<button class="button"><?php esc_html_e( 'Search', 'cellcraze-core' ); ?></button>
				</p>
			</form>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'IMEI / Serial', 'cellcraze-core' ); ?></th>
						<th><?php esc_html_e( 'Product', 'cellcraze-core' ); ?></th>
						<th><?php esc_html_e( 'Status', 'cellcraze-core' ); ?></th>
						<th><?php esc_html_e( 'Order', 'cellcraze-core' ); ?></th>
						<th><?php esc_html_e( 'Supplier', 'cellcraze-core' ); ?></th>
						<th><?php esc_html_e( 'Received', 'cellcraze-core' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No units found.', 'cellcraze-core' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><code><?php echo esc_html( $row->imei ); ?></code></td>
								<td>
									<?php
									$name = $row->product_id ? get_the_title( $row->product_id ) : '';
									echo $name ? esc_html( $name ) : '&mdash;';
									?>
								</td>
								<td><?php echo esc_html( isset( $statuses[ $row->status ] ) ? $statuses[ $row->status ] : $row->status ); ?></td>
								<td>
									<?php if ( $row->order_id ) : ?>
										<a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $row->order_id . '&action=edit' ) ); ?>">#<?php echo (int) $row->order_id; ?></a>
									<?php else : ?>&mdash;<?php endif; ?>
								</td>
								<td><?php echo esc_html( $row->supplier ); ?></td>
								<td><?php echo esc_html( $row->received_at ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
