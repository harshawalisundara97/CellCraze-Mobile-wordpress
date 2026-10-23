<?php
/**
 * REST endpoints for IMEI selection at the point of sale.
 *
 * A WooCommerce POS front-end (any plugin) can:
 *   GET  /cellcraze/v1/imei/available?product_id=123  → list in-stock units
 *   POST /cellcraze/v1/imei/assign  { imei, order_id } → assign a chosen unit
 *
 * This lets the cashier pick a specific physical handset during a sale (module
 * #51) instead of relying on the automatic FIFO assignment used for online
 * orders. Assignment here also writes the IMEI onto the matching order line
 * item so it prints on the receipt.
 *
 * @package CellCraze_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the /cellcraze/v1 IMEI routes.
 */
class CellCraze_IMEI_Rest {

	const NAMESPACE = 'cellcraze/v1';

	/**
	 * Wire hooks.
	 */
	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Only staff who can manage orders may read/assign units.
	 *
	 * @return bool
	 */
	public function permission() {
		return current_user_can( 'edit_shop_orders' ) || current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/imei/available',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'available' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array(
					'product_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/imei/assign',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'assign' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array(
					'imei'     => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'order_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * GET available units for a product.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function available( WP_REST_Request $request ) {
		$product_id = absint( $request['product_id'] );
		$units      = CellCraze_IMEI_Store::available_for_product( $product_id );

		$out = array_map(
			static function ( $u ) {
				return array(
					'id'              => (int) $u['id'],
					'imei'            => $u['imei'],
					'warranty_months' => null !== $u['warranty_months'] ? (int) $u['warranty_months'] : null,
				);
			},
			$units
		);

		return new WP_REST_Response(
			array(
				'product_id' => $product_id,
				'count'      => count( $out ),
				'units'      => $out,
			),
			200
		);
	}

	/**
	 * POST: assign a specific IMEI to an order.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function assign( WP_REST_Request $request ) {
		$imei     = trim( (string) $request['imei'] );
		$order_id = absint( $request['order_id'] );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'cellcraze_no_order', __( 'Order not found.', 'cellcraze-core' ), array( 'status' => 404 ) );
		}

		$unit = CellCraze_IMEI_Store::find_by_imei( $imei );
		if ( ! $unit ) {
			return new WP_Error( 'cellcraze_no_unit', __( 'IMEI not found.', 'cellcraze-core' ), array( 'status' => 404 ) );
		}
		if ( CellCraze_IMEI_Store::STATUS_IN_STOCK !== $unit['status'] ) {
			return new WP_Error(
				'cellcraze_unit_unavailable',
				/* translators: %s: current status of the unit. */
				sprintf( __( 'That unit is not available (status: %s).', 'cellcraze-core' ), $unit['status'] ),
				array( 'status' => 409 )
			);
		}

		// Mark sold and stamp the IMEI onto the matching line item for the receipt.
		CellCraze_IMEI_Store::mark_sold( (int) $unit['id'], $order_id );
		$this->append_to_line_item( $order, (int) $unit['product_id'], $unit['imei'] );

		return new WP_REST_Response(
			array(
				'assigned' => true,
				'imei'     => $unit['imei'],
				'order_id' => $order_id,
			),
			200
		);
	}

	/**
	 * Add the IMEI to the order line item matching the product (for receipts).
	 *
	 * @param WC_Order $order      Order.
	 * @param int      $product_id Product id.
	 * @param string   $imei       IMEI to append.
	 */
	protected function append_to_line_item( $order, $product_id, $imei ) {
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product && (int) $item->get_product_id() === $product_id ) {
				$existing = (string) $item->get_meta( __( 'IMEI / Serial', 'cellcraze-core' ) );
				$value    = $existing ? $existing . ', ' . $imei : $imei;
				$item->update_meta_data( __( 'IMEI / Serial', 'cellcraze-core' ), $value );
				$item->save();
				break;
			}
		}
	}
}
