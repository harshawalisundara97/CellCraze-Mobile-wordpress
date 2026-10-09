<?php
/**
 * REST API for POS / integrations under the `cellcraze/v1` namespace.
 *
 * Endpoints:
 *   GET  /cellcraze/v1/imei/available?product_id=123   -> available units
 *   POST /cellcraze/v1/imei/assign  {imei, order_id}    -> mark a unit sold
 *   POST /cellcraze/v1/channel      {order_id, channel} -> tag online/pos
 *
 * All endpoints require a user who can `manage_woocommerce` (authenticate with
 * WooCommerce REST keys or an application password).
 *
 * @package CellCraze_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cellcraze_REST
 */
class Cellcraze_REST {

	const NS = 'cellcraze/v1';

	/**
	 * Register routes.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Permission callback — gate on WooCommerce management capability.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Register all routes.
	 */
	public function routes() {
		register_rest_route(
			self::NS,
			'/imei/available',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_available' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'product_id'   => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'variation_id' => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/imei/assign',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'assign' ),
				'permission_callback' => array( $this, 'can_manage' ),
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

		register_rest_route(
			self::NS,
			'/channel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'set_channel' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'order_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'channel'  => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
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
	public function get_available( $request ) {
		$product_id   = absint( $request['product_id'] );
		$variation_id = isset( $request['variation_id'] ) ? absint( $request['variation_id'] ) : 0;
		$units        = Cellcraze_IMEI_DB::get_available( $product_id, $variation_id, 200 );

		$data = array();
		foreach ( $units as $unit ) {
			$data[] = array(
				'id'              => (int) $unit->id,
				'imei'            => $unit->imei,
				'product_id'      => (int) $unit->product_id,
				'variation_id'    => (int) $unit->variation_id,
				'warranty_months' => (int) $unit->warranty_months,
			);
		}

		return new WP_REST_Response(
			array(
				'product_id' => $product_id,
				'available'  => count( $data ),
				'units'      => $data,
			),
			200
		);
	}

	/**
	 * POST assign a specific IMEI to an order (hand-pick at the counter).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function assign( $request ) {
		$imei     = sanitize_text_field( $request['imei'] );
		$order_id = absint( $request['order_id'] );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'cellcraze_no_order', __( 'Order not found.', 'cellcraze-core' ), array( 'status' => 404 ) );
		}

		$unit = Cellcraze_IMEI_DB::get_by_imei( $imei );
		if ( ! $unit ) {
			return new WP_Error( 'cellcraze_no_unit', __( 'IMEI not found.', 'cellcraze-core' ), array( 'status' => 404 ) );
		}
		if ( Cellcraze_IMEI_DB::STATUS_AVAILABLE !== $unit->status ) {
			return new WP_Error(
				'cellcraze_unit_unavailable',
				/* translators: %s: current unit status */
				sprintf( __( 'That unit is not available (status: %s).', 'cellcraze-core' ), $unit->status ),
				array( 'status' => 409 )
			);
		}

		// Atomic claim — guards against the unit being sold by a concurrent
		// request between the status check above and this write (CC-03).
		if ( ! Cellcraze_IMEI_DB::mark_sold( $unit->id, $order_id ) ) {
			return new WP_Error(
				'cellcraze_unit_taken',
				__( 'That unit was just claimed by another sale. Pick a different one.', 'cellcraze-core' ),
				array( 'status' => 409 )
			);
		}

		// Append to the matching line item's IMEI meta for receipts/invoices.
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$pid = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
			if ( (int) $pid === (int) $unit->product_id ) {
				$imeis   = $item->get_meta( '_cellcraze_imeis', true );
				$imeis   = is_array( $imeis ) ? array_values( array_filter( $imeis, 'strlen' ) ) : array();
				$imeis[] = $unit->imei;
				$item->update_meta_data( '_cellcraze_imeis', $imeis );
				$item->update_meta_data( __( 'IMEI / Serial', 'cellcraze-core' ), implode( ', ', $imeis ) );
				$item->save();
				break;
			}
		}

		$order->add_order_note(
			sprintf(
				/* translators: %s: IMEI */
				__( 'CellCraze: assigned IMEI %s via POS/API.', 'cellcraze-core' ),
				$unit->imei
			)
		);

		return new WP_REST_Response(
			array(
				'assigned' => true,
				'imei'     => $unit->imei,
				'order_id' => $order_id,
			),
			200
		);
	}

	/**
	 * POST set an order's sales channel.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function set_channel( $request ) {
		$order_id = absint( $request['order_id'] );
		$channel  = sanitize_key( $request['channel'] );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'cellcraze_no_order', __( 'Order not found.', 'cellcraze-core' ), array( 'status' => 404 ) );
		}

		Cellcraze_Channel::set( $order, $channel );

		return new WP_REST_Response(
			array(
				'order_id' => $order_id,
				'channel'  => Cellcraze_Channel::get( $order ),
			),
			200
		);
	}
}
