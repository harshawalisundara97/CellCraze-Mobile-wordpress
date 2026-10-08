<?php
/**
 * Modernist product card (grid cell). Overrides WooCommerce content-product.php.
 * Design: grayscale image well + discount badge, brand, name, price + compare,
 * stock indicator, Add-to-cart (or Notify me when out of stock).
 *
 * @package CellCraze
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}

$link      = get_permalink( $product->get_id() );
$brand     = cellcraze_brand( $product );
$in_stock  = $product->is_in_stock();
$on_sale   = $product->is_on_sale();

// Discount badge percentage (simple products with regular/sale price).
$badge = '';
if ( $on_sale && $product->get_regular_price() && $product->get_sale_price() ) {
	$reg  = (float) $product->get_regular_price();
	$sale = (float) $product->get_sale_price();
	if ( $reg > 0 && $sale < $reg ) {
		$badge = '-' . round( ( ( $reg - $sale ) / $reg ) * 100 ) . '%';
	}
}
?>
<li <?php wc_product_class( 'cc-card', $product ); ?>>

	<a class="cc-card-media" href="<?php echo esc_url( $link ); ?>">
		<?php if ( $badge ) : ?>
			<span class="cc-badge"><?php echo esc_html( $badge ); ?></span>
		<?php endif; ?>
		<span class="cc-img-well">
			<?php echo $product->get_image( 'woocommerce_thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</span>
	</a>

	<?php if ( $brand ) : ?>
		<span class="cc-brand"><?php echo esc_html( $brand ); ?></span>
	<?php endif; ?>

	<a class="cc-name" href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>

	<div class="cc-price"><?php echo $product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>

	<?php echo cellcraze_stock_indicator( $product ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

	<?php if ( $in_stock ) : ?>
		<?php
		woocommerce_template_loop_add_to_cart(
			array(
				'class' => 'btn btn-secondary btn-block',
			)
		);
		?>
	<?php else : ?>
		<a class="btn btn-secondary btn-block" href="<?php echo esc_url( $link ); ?>">
			Notify me
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square" style="margin-left:auto"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
		</a>
	<?php endif; ?>

</li>
