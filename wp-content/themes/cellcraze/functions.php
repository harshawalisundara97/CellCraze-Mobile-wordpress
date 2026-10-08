<?php
/**
 * CellCraze child theme functions.
 *
 * Recreates the "Modernist" design handoff on WordPress + WooCommerce.
 *
 * @package CellCraze
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CELLCRAZE_VERSION', '1.1.0' );

/**
 * Styles: Archivo (Google Fonts) + Modernist tokens + CellCraze layer.
 * Storefront's own CSS is dequeued so we build on a clean canvas.
 */
function cellcraze_enqueue_styles() {
	// Start from a clean slate — drop Storefront's stylesheets.
	wp_dequeue_style( 'storefront-style' );
	wp_dequeue_style( 'storefront-woocommerce-style' );
	wp_dequeue_style( 'storefront-gutenberg-blocks' );

	wp_enqueue_style(
		'cellcraze-fonts',
		'https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;800&display=swap',
		array(),
		null
	);
	wp_enqueue_style(
		'cellcraze-tokens',
		get_stylesheet_directory_uri() . '/assets/css/tokens.css',
		array( 'cellcraze-fonts' ),
		CELLCRAZE_VERSION
	);
	wp_enqueue_style(
		'cellcraze-main',
		get_stylesheet_directory_uri() . '/assets/css/cellcraze.css',
		array( 'cellcraze-tokens' ),
		CELLCRAZE_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'cellcraze_enqueue_styles', 100 );

/**
 * Theme supports & menus.
 */
function cellcraze_setup() {
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );

	register_nav_menus(
		array(
			'cc_categories' => __( 'Category Bar', 'cellcraze' ),
			'cc_footer_shop' => __( 'Footer — Shop', 'cellcraze' ),
			'cc_footer_help' => __( 'Footer — Help', 'cellcraze' ),
			'cc_footer_account' => __( 'Footer — Account', 'cellcraze' ),
		)
	);
}
add_action( 'after_setup_theme', 'cellcraze_setup' );

/** Add a body class so our CSS is namespaced. */
function cellcraze_body_class( $classes ) {
	$classes[] = 'cellcraze';
	return $classes;
}
add_filter( 'body_class', 'cellcraze_body_class' );

/** Shop grid: 3 columns (design 1b); featured uses 4 via template. */
add_filter( 'loop_shop_columns', function () { return 3; } );
add_filter( 'loop_shop_per_page', function () { return 12; } );

/**
 * Stock indicator matching the design (square + label).
 * 0 => Out of stock (neutral), 1–5 => Only N left (accent), >5 => In stock (ink).
 *
 * @param WC_Product $product Product.
 * @return string HTML.
 */
function cellcraze_stock_indicator( $product ) {
	if ( ! $product ) {
		return '';
	}
	$qty = $product->get_stock_quantity();
	if ( ! $product->is_in_stock() || ( null !== $qty && $qty <= 0 ) ) {
		return '<span class="cc-stock"><span class="cc-sq cc-sq-out"></span>Out of stock</span>';
	}
	if ( null !== $qty && $qty <= 5 ) {
		return '<span class="cc-stock"><span class="cc-sq cc-sq-low"></span>Only ' . intval( $qty ) . ' left</span>';
	}
	return '<span class="cc-stock"><span class="cc-sq cc-sq-in"></span>In stock</span>';
}

/**
 * First brand term for a product (from the "Brand" attribute / pa_brand).
 *
 * @param WC_Product $product Product.
 * @return string
 */
function cellcraze_brand( $product ) {
	if ( ! $product ) {
		return '';
	}
	$terms = wc_get_product_terms( $product->get_id(), 'pa_brand', array( 'fields' => 'names' ) );
	if ( ! empty( $terms ) ) {
		return $terms[0];
	}
	// Fallback to a plain custom attribute named "Brand".
	$attr = $product->get_attribute( 'Brand' );
	return $attr ? $attr : '';
}

/**
 * Flat menu walker: outputs bare <a> links (no <ul>/<li>) for the category bar.
 */
class Cellcraze_Flat_Walker extends Walker_Nav_Menu {
	public function start_lvl( &$output, $depth = 0, $args = null ) {}
	public function end_lvl( &$output, $depth = 0, $args = null ) {}
	public function end_el( &$output, $item = null, $depth = 0, $args = null ) {}
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$current = in_array( 'current-menu-item', (array) $item->classes, true ) ? ' aria-current="page"' : '';
		$output .= sprintf(
			'<a href="%s"%s>%s</a>',
			esc_url( $item->url ),
			$current,
			esc_html( $item->title )
		);
	}
}

/**
 * Register the Brand / Storage / Colour global attributes used by the design.
 * Runs once (guarded) — safe to leave active.
 */
function cellcraze_register_attributes() {
	if ( ! function_exists( 'wc_create_attribute' ) || get_option( 'cellcraze_attributes_done' ) ) {
		return;
	}
	$attributes = array(
		'brand'   => 'Brand',
		'storage' => 'Storage',
		'colour'  => 'Colour',
	);
	foreach ( $attributes as $slug => $name ) {
		if ( ! wc_attribute_taxonomy_id_by_name( $slug ) ) {
			wc_create_attribute(
				array(
					'name'         => $name,
					'slug'         => $slug,
					'type'         => 'select',
					'order_by'     => 'menu_order',
					'has_archives' => false,
				)
			);
		}
	}
	update_option( 'cellcraze_attributes_done', 1 );
}
add_action( 'admin_init', 'cellcraze_register_attributes' );
