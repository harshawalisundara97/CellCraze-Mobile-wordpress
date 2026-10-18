<?php
/**
 * Print the current product -> SKU -> category mapping.
 * Run with WP-CLI:
 *   C:/xampp/php/php.exe C:/xampp/wp-cli.phar eval-file tools/print-sku-map.php --path=C:/xampp/htdocs/cellcraze
 *
 * Read-only. Changes nothing.
 *
 * @package CellCraze
 */

if ( ! defined( 'ABSPATH' ) || ! function_exists( 'wc_get_products' ) ) {
	echo "Run via WP-CLI eval-file against a WooCommerce install.\n";
	return;
}

$products = wc_get_products( array( 'limit' => -1, 'status' => 'publish', 'orderby' => 'id', 'order' => 'ASC' ) );

printf( "%-5s | %-12s | %-8s | %-45s | %s\n", 'ID', 'SKU', 'IMAGE', 'NAME', 'CATEGORIES' );
echo str_repeat( '-', 100 ) . "\n";
foreach ( $products as $p ) {
	$cats = wp_get_post_terms( $p->get_id(), 'product_cat', array( 'fields' => 'names' ) );
	printf(
		"%-5d | %-12s | %-8s | %-45s | %s\n",
		$p->get_id(),
		$p->get_sku() ? $p->get_sku() : '(none)',
		$p->get_image_id() ? 'set' : 'none',
		mb_strimwidth( $p->get_name(), 0, 45 ),
		implode( ', ', $cats )
	);
}
