<?php
/**
 * COGS snapshot (task T-104).
 *
 * At the point a sale is recognized (order -> processing/completed) we freeze
 * the cost of goods for each line so later product-cost changes never rewrite
 * the profit of past sales:
 *   - Serialized lines (IMEI assigned): NOT snapshotted — cost is read live from
 *     wp_cellcraze_imei (the exact units' `cost`). A restocked/restored unit
 *     leaves status='sold', so its cost drops out automatically (auto-reversal).
 *   - Accessory lines (no IMEI): snapshot qty x product `_cost` into order-item
 *     meta `_cellcraze_line_cost` (frozen). Missing `_cost` is flagged.
 *
 * Capture runs once per line (idempotent guard `_cellcraze_cost_captured`).
 *
 * @package CellCraze_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cellcraze_COGS
 */
class Cellcraze_COGS {

	const META_LINE_COST = '_cellcraze_line_cost';
	const META_CAPTURED  = '_cellcraze_cost_captured';
	const META_MISSING   = '_cellcraze_cost_missing';

	/**
	 * Hooks. Runs AFTER IMEI assignment (priority 20) so assigned IMEIs are
	 * known when we decide serialized vs accessory.
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_processing', array( $this, 'capture' ), 30 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'capture' ), 30 );
	}

	/**
	 * Snapshot accessory line costs once per line.
	 *
	 * @param int $order_id Order id.
	 */
	public function capture( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$captured_total = 0.0;
		$did            = false;
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			if ( $item->get_meta( self::META_CAPTURED ) ) {
				continue; // already captured — idempotent.
			}
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$imeis = $item->get_meta( '_cellcraze_imeis', true );
			$is_serialized = is_array( $imeis ) && ! empty( array_filter( $imeis, 'strlen' ) );

			if ( $is_serialized ) {
				// No snapshot: serialized COGS is read live from the IMEI table.
				$item->update_meta_data( self::META_CAPTURED, 1 );
			} else {
				$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
				$unit_cost  = get_post_meta( $product->get_id(), '_cost', true );
				if ( '' === $unit_cost || ! is_numeric( $unit_cost ) ) {
					// Fall back to parent for a variation, else flag missing.
					$unit_cost = get_post_meta( $product_id, '_cost', true );
				}
				if ( '' === $unit_cost || ! is_numeric( $unit_cost ) ) {
					$item->update_meta_data( self::META_MISSING, 1 );
					$item->update_meta_data( self::META_LINE_COST, 0 );
				} else {
					$line_cost = round( (float) $unit_cost * (int) $item->get_quantity(), 2 );
					$item->update_meta_data( self::META_LINE_COST, $line_cost );
					$captured_total += $line_cost;
				}
				$item->update_meta_data( self::META_CAPTURED, 1 );
			}
			$item->save();
			$did = true;
		}

		if ( $did && class_exists( 'Cellcraze_Audit' ) ) {
			Cellcraze_Audit::log(
				array(
					'event_key'   => 'cogs_capture:' . (int) $order_id,
					'event_type'  => 'cogs_capture',
					'object_type' => 'order',
					'object_id'   => (int) $order_id,
					'reason'      => 'cogs_snapshot',
					'reference'   => $order->get_order_number(),
					'note'        => 'accessory snapshot LKR ' . number_format( $captured_total, 2 ),
				)
			);
		}
	}

	/**
	 * Compute an order's COGS: serialized lines live from the IMEI table +
	 * accessory lines from their frozen snapshot.
	 *
	 * @param WC_Order $order Order.
	 * @return array { cogs:float, approximate:bool } approximate=true if any line
	 *               lacked a captured snapshot or a cost.
	 */
	public static function order_cogs( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return array( 'cogs' => 0.0, 'approximate' => false );
		}
		$cogs        = 0.0;
		$approximate = false;
		$oid         = $order->get_id();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$product_id    = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
			$imeis         = $item->get_meta( '_cellcraze_imeis', true );
			$is_serialized = is_array( $imeis ) && ! empty( array_filter( $imeis, 'strlen' ) );

			if ( $is_serialized ) {
				// Live: only units still sold to this order count.
				$cogs += Cellcraze_IMEI_DB::sold_cost_for_order_product( $oid, $product_id );
			} elseif ( $item->get_meta( self::META_CAPTURED ) ) {
				$cogs += (float) $item->get_meta( self::META_LINE_COST );
				if ( $item->get_meta( self::META_MISSING ) ) {
					$approximate = true;
				}
			} else {
				// Pre-snapshot order (placed before T-104): approximate from
				// current _cost, clearly flagged rather than silently wrong.
				$unit_cost = get_post_meta( $product_id, '_cost', true );
				if ( is_numeric( $unit_cost ) ) {
					$cogs += round( (float) $unit_cost * (int) $item->get_quantity(), 2 );
				}
				$approximate = true;
			}
		}
		return array( 'cogs' => round( $cogs, 2 ), 'approximate' => $approximate );
	}
}
