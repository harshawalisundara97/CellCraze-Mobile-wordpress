<?php
/**
 * CellCraze child theme functions.
 *
 * @package CellCraze
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

/**
 * Load the parent (Storefront) stylesheet, then the child stylesheet.
 */
function cellcraze_enqueue_styles() {
	$parent = 'storefront-style';

	wp_enqueue_style(
		$parent,
		get_template_directory_uri() . '/style.css',
		array(),
		wp_get_theme( 'storefront' )->get( 'Version' )
	);

	wp_enqueue_style(
		'cellcraze-style',
		get_stylesheet_uri(),
		array( $parent ),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'cellcraze_enqueue_styles' );

/**
 * Declare WooCommerce support so the child theme is a first-class Woo theme.
 */
function cellcraze_woocommerce_support() {
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'cellcraze_woocommerce_support' );

/**
 * Show products in rows of 4 on the shop/category pages.
 */
function cellcraze_loop_columns() {
	return 4;
}
add_filter( 'loop_shop_columns', 'cellcraze_loop_columns' );

/**
 * Products per page on the shop archive.
 */
function cellcraze_products_per_page() {
	return 16;
}
add_filter( 'loop_shop_per_page', 'cellcraze_products_per_page' );
