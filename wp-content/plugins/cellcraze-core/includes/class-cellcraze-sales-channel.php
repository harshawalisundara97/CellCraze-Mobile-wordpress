<?php
/**
 * Sales-channel tagging: mark each order as "online" or "pos".
 *
 * WooCommerce POS plugins create real WooCommerce orders, distinguishable by
 * their `created_via` value (e.g. 'wepos', 'pos-rest-api', 'woocommerce-pos')
 * or a custom order meta. We stamp every order with a normalized channel so
 * reports can split online vs physical-shop sales (modules #134, #160).
 *
 * @package CellCraze_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes and stores the sales channel on each order.
 */
class CellCraze_Sales_Channel {

	const META_KEY       = '_cellcraze_channel';
	const CHANNEL_ONLINE = 'online';
	const CHANNEL_POS    = 'pos';

	/**
	 * Wire hooks.
	 */
	public function hooks() {
		// Stamp as early as the order is created, and again when it is processed
		// (covers POS plugins that set created_via after the initial insert).
		add_action( 'woocommerce_new_order', array( $this, 'tag_order' ), 20 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'tag_order' ), 5 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'tag_order' ), 5 );

		// Show the channel as a column on the orders list.
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_column' ), 20 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_column' ), 20 ); // HPOS.
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_column_hpos' ), 10, 2 );
	}

	/**
	 * Markers in an order's `created_via` that mean "point of sale".
	 * Extend with the `cellcraze_pos_created_via` filter for your chosen plugin.
	 *
	 * @return string[]
	 */
	public static function pos_markers() {
		return apply_filters(
			'cellcraze_pos_created_via',
			array( 'wepos', 'pos-rest-api', 'woocommerce-pos', 'duckpos', 'pos' )
		);
	}

	/**
	 * Decide and store the channel for an order (idempotent).
	 *
	 * @param int $order_id Order id.
	 * @return string The stored channel.
	 */
	public function tag_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return '';
		}

		$existing = $order->get_meta( self::META_KEY );
		// Only (re)compute while still online — once flagged POS it stays POS.
		if ( self::CHANNEL_POS === $existing ) {
			return $existing;
		}

		$channel = $this->detect( $order );
		if ( $channel !== $existing ) {
			$order->update_meta_data( self::META_KEY, $channel );
			$order->save();
		}
		return $channel;
	}

	/**
	 * Detect the channel from the order's created_via / meta.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	protected function detect( $order ) {
		$created_via = strtolower( (string) $order->get_created_via() );
		foreach ( self::pos_markers() as $marker ) {
			if ( '' !== $marker && false !== strpos( $created_via, strtolower( $marker ) ) ) {
				return self::CHANNEL_POS;
			}
		}
		// Some plugins only leave a meta flag.
		if ( $order->get_meta( '_pos' ) || $order->get_meta( '_wepos' ) ) {
			return self::CHANNEL_POS;
		}
		return self::CHANNEL_ONLINE;
	}

	/**
	 * Read an order's channel, computing+storing it on first access.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function get( $order ) {
		$c = $order->get_meta( self::META_KEY );
		return $c ? $c : self::CHANNEL_ONLINE;
	}

	/**
	 * Add the "Channel" column.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_column( $columns ) {
		$columns['cellcraze_channel'] = __( 'Channel', 'cellcraze-core' );
		return $columns;
	}

	/**
	 * Render the column (classic posts table).
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Order post id.
	 */
	public function render_column( $column, $post_id ) {
		if ( 'cellcraze_channel' !== $column ) {
			return;
		}
		$order = wc_get_order( $post_id );
		if ( $order ) {
			echo esc_html( $this->label( self::get( $order ) ) );
		}
	}

	/**
	 * Render the column (HPOS orders table).
	 *
	 * @param string   $column Column key.
	 * @param WC_Order $order  Order.
	 */
	public function render_column_hpos( $column, $order ) {
		if ( 'cellcraze_channel' === $column && $order instanceof WC_Order ) {
			echo esc_html( $this->label( self::get( $order ) ) );
		}
	}

	/**
	 * Human label for a channel slug.
	 *
	 * @param string $channel Slug.
	 * @return string
	 */
	protected function label( $channel ) {
		return self::CHANNEL_POS === $channel
			? __( 'Shop (POS)', 'cellcraze-core' )
			: __( 'Online', 'cellcraze-core' );
	}
}
