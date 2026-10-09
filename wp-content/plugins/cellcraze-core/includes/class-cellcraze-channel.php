<?php
/**
 * Sales-channel tagging: mark every order "online" or "pos" so reports can
 * split web sales from counter sales. POS plugins / the REST endpoint set the
 * tag to "pos"; everything else defaults to "online".
 *
 * @package CellCraze_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cellcraze_Channel
 */
class Cellcraze_Channel {

	const META_KEY = '_cellcraze_channel';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		// Default-tag new orders as online if nothing set it yet.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'tag_online' ), 10, 1 );
		add_action( 'woocommerce_new_order', array( $this, 'maybe_tag_default' ), 10, 1 );

		// Admin order-list column (supports both legacy posts table and HPOS).
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_column' ), 20 );
		add_filter( 'woocommerce_shop_order_list_table_columns', array( $this, 'add_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_column_legacy' ), 10, 2 );
		add_action( 'woocommerce_shop_order_list_table_custom_column', array( $this, 'render_column_hpos' ), 10, 2 );
	}

	/**
	 * Get an order's channel, defaulting to online.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function get( $order ) {
		$channel = $order->get_meta( self::META_KEY );
		return $channel ? $channel : 'online';
	}

	/**
	 * Set an order's channel.
	 *
	 * @param WC_Order $order   Order.
	 * @param string   $channel 'online' | 'pos'.
	 */
	public static function set( $order, $channel ) {
		$channel = in_array( $channel, array( 'online', 'pos' ), true ) ? $channel : 'online';
		$order->update_meta_data( self::META_KEY, $channel );
		$order->save();
	}

	/**
	 * Tag checkout orders online.
	 *
	 * @param int $order_id Order id.
	 */
	public function tag_online( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && '' === (string) $order->get_meta( self::META_KEY ) ) {
			self::set( $order, 'online' );
		}
	}

	/**
	 * Fallback default for any order created without a channel.
	 *
	 * @param int $order_id Order id.
	 */
	public function maybe_tag_default( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && '' === (string) $order->get_meta( self::META_KEY ) ) {
			$order->update_meta_data( self::META_KEY, 'online' );
			$order->save();
		}
	}

	/**
	 * Insert the Channel column after the order status.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$new['cellcraze_channel'] = __( 'Channel', 'cellcraze-core' );
			}
		}
		if ( ! isset( $new['cellcraze_channel'] ) ) {
			$new['cellcraze_channel'] = __( 'Channel', 'cellcraze-core' );
		}
		return $new;
	}

	/**
	 * Render column (legacy posts table).
	 *
	 * @param string $column   Column key.
	 * @param int    $post_id  Order post id.
	 */
	public function render_column_legacy( $column, $post_id ) {
		if ( 'cellcraze_channel' !== $column ) {
			return;
		}
		$order = wc_get_order( $post_id );
		if ( $order ) {
			echo esc_html( $this->label( self::get( $order ) ) );
		}
	}

	/**
	 * Render column (HPOS list table).
	 *
	 * @param string   $column Column key.
	 * @param WC_Order $order  Order.
	 */
	public function render_column_hpos( $column, $order ) {
		if ( 'cellcraze_channel' !== $column ) {
			return;
		}
		echo esc_html( $this->label( self::get( $order ) ) );
	}

	/**
	 * Display label for a channel key.
	 *
	 * @param string $channel Channel key.
	 * @return string
	 */
	private function label( $channel ) {
		return 'pos' === $channel ? __( 'In-store (POS)', 'cellcraze-core' ) : __( 'Online', 'cellcraze-core' );
	}
}
