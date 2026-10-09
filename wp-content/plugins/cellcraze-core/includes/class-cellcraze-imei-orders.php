<?php
/**
 * Assign IMEI units to orders, restore on cancel/refund, and display them.
 *
 * @package CellCraze_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cellcraze_IMEI_Orders
 */
class Cellcraze_IMEI_Orders {

	/**
	 * Hook into order lifecycle + display points.
	 */
	public function __construct() {
		// Auto-assign once an order is paid for / being fulfilled.
		add_action( 'woocommerce_order_status_processing', array( $this, 'assign_order' ), 20 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'assign_order' ), 20 );

		// Restore units when the order is no longer a sale.
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'restore_order' ), 20 );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'restore_order' ), 20 );
		add_action( 'woocommerce_order_fully_refunded', array( $this, 'restore_order' ), 20 );

		// Display on the admin order screen.
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'render_admin_order' ), 10 );

		// Display on customer-facing order details + emails.
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_customer_order' ), 10 );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'render_email_order' ), 10, 4 );
	}

	/**
	 * Resolve the numeric order id from a hook argument.
	 *
	 * @param int|WC_Order $order Order id or object.
	 * @return int
	 */
	private function order_id( $order ) {
		if ( $order instanceof WC_Order ) {
			return $order->get_id();
		}
		return absint( $order );
	}

	/**
	 * FIFO-assign one IMEI unit per quantity for each line item that does not
	 * already have the full count assigned. Idempotent: re-running will only
	 * top up any missing units.
	 *
	 * @param int $order_id Order id.
	 */
	public function assign_order( $order_id ) {
		$order_id = $this->order_id( $order_id );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$already = count( Cellcraze_IMEI_DB::get_for_order( $order_id ) );

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			// Only track products that actually have IMEI units in stock.
			$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
			$qty        = (int) $item->get_quantity();

			// How many units already tied to this exact line item?
			$assigned_meta = (array) $item->get_meta( '_cellcraze_imeis', true );
			$need          = $qty - count( $assigned_meta );
			if ( $need <= 0 ) {
				continue;
			}

			$units = Cellcraze_IMEI_DB::get_available( $product_id, 0, $need );
			if ( empty( $units ) ) {
				continue;
			}

			$imeis = $assigned_meta;
			foreach ( $units as $unit ) {
				if ( Cellcraze_IMEI_DB::mark_sold( $unit->id, $order_id ) ) {
					$imeis[] = $unit->imei;
				}
			}

			if ( ! empty( $imeis ) ) {
				$item->update_meta_data( '_cellcraze_imeis', $imeis );
				// Human-readable copy shown on invoices / emails via item meta.
				$item->update_meta_data( __( 'IMEI / Serial', 'cellcraze-core' ), implode( ', ', $imeis ) );
				$item->save();
			}
		}

		$now_assigned = count( Cellcraze_IMEI_DB::get_for_order( $order_id ) );
		if ( $now_assigned > $already ) {
			$order->add_order_note(
				sprintf(
					/* translators: %d: number of IMEI units */
					__( 'CellCraze: assigned %d IMEI/serial unit(s) to this order.', 'cellcraze-core' ),
					$now_assigned - $already
				)
			);
		}
	}

	/**
	 * Restore every unit tied to an order back to available.
	 *
	 * @param int $order_id Order id.
	 */
	public function restore_order( $order_id ) {
		$order_id = $this->order_id( $order_id );
		$units    = Cellcraze_IMEI_DB::get_for_order( $order_id );
		if ( empty( $units ) ) {
			return;
		}
		$count = 0;
		foreach ( $units as $unit ) {
			if ( Cellcraze_IMEI_DB::restore( $unit->id ) ) {
				$count++;
			}
		}

		// Clear the line-item copies so a re-assign starts clean.
		$order = wc_get_order( $order_id );
		if ( $order ) {
			foreach ( $order->get_items() as $item ) {
				$item->delete_meta_data( '_cellcraze_imeis' );
				$item->delete_meta_data( __( 'IMEI / Serial', 'cellcraze-core' ) );
				$item->save();
			}
			if ( $count > 0 ) {
				$order->add_order_note(
					sprintf(
						/* translators: %d: number of IMEI units */
						__( 'CellCraze: restored %d IMEI/serial unit(s) to available stock.', 'cellcraze-core' ),
						$count
					)
				);
			}
		}
	}

	/**
	 * Admin order screen panel.
	 *
	 * @param WC_Order $order Order.
	 */
	public function render_admin_order( $order ) {
		$units = Cellcraze_IMEI_DB::get_for_order( $order->get_id() );
		if ( empty( $units ) ) {
			return;
		}
		echo '<div class="cellcraze-imei-order"><h3>' . esc_html__( 'IMEI / Serial units', 'cellcraze-core' ) . '</h3><ul>';
		foreach ( $units as $unit ) {
			echo '<li><code>' . esc_html( $unit->imei ) . '</code></li>';
		}
		echo '</ul></div>';
	}

	/**
	 * Customer-facing order view.
	 *
	 * @param WC_Order $order Order.
	 */
	public function render_customer_order( $order ) {
		$units = Cellcraze_IMEI_DB::get_for_order( $order->get_id() );
		if ( empty( $units ) ) {
			return;
		}
		echo '<section class="cellcraze-imei-customer"><h2>' . esc_html__( 'Device IMEI / Serial', 'cellcraze-core' ) . '</h2><ul>';
		foreach ( $units as $unit ) {
			echo '<li>' . esc_html( $unit->imei ) . '</li>';
		}
		echo '</ul></section>';
	}

	/**
	 * Order emails.
	 *
	 * @param WC_Order $order         Order.
	 * @param bool     $sent_to_admin Admin copy.
	 * @param bool     $plain_text    Plain text email.
	 * @param WC_Email $email         Email object.
	 */
	public function render_email_order( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {
		$units = Cellcraze_IMEI_DB::get_for_order( $order->get_id() );
		if ( empty( $units ) ) {
			return;
		}
		$imeis = wp_list_pluck( $units, 'imei' );
		if ( $plain_text ) {
			echo "\n" . esc_html__( 'Device IMEI / Serial', 'cellcraze-core' ) . ":\n";
			echo esc_html( implode( "\n", $imeis ) ) . "\n";
		} else {
			echo '<h2>' . esc_html__( 'Device IMEI / Serial', 'cellcraze-core' ) . '</h2><ul>';
			foreach ( $imeis as $imei ) {
				echo '<li>' . esc_html( $imei ) . '</li>';
			}
			echo '</ul>';
		}
	}
}
