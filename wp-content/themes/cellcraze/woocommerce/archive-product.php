<?php
/**
 * Product listing (design 1b): breadcrumb, title row with result count + sort,
 * filter sidebar + ruled product grid + square pagination.
 *
 * @package CellCraze
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header( 'shop' );

?>
<div class="cc-shop">

	<div class="cc-shop-breadcrumb"><?php woocommerce_breadcrumb(); ?></div>

	<div class="cc-shop-titlerow">
		<div class="cc-shop-title">
			<h1><?php woocommerce_page_title(); ?></h1>
			<?php
			if ( woocommerce_product_loop() ) {
				woocommerce_result_count();
			}
			?>
		</div>
		<?php if ( woocommerce_product_loop() ) : ?>
			<div class="cc-shop-sort"><?php woocommerce_catalog_ordering(); ?></div>
		<?php endif; ?>
	</div>

	<div class="cc-shop-layout">

		<aside class="cc-shop-filters">
			<?php if ( is_active_sidebar( 'cc_shop_filters' ) ) : ?>
				<?php dynamic_sidebar( 'cc_shop_filters' ); ?>
			<?php else : ?>
				<!-- Fallback: category list when no filter widgets configured yet. -->
				<div class="cc-filter-group">
					<h3 class="cc-filter-title">Categories</h3>
					<ul class="cc-filter-cats">
						<?php
						wp_list_categories(
							array(
								'taxonomy'   => 'product_cat',
								'title_li'   => '',
								'hide_empty' => false,
								'depth'      => 2,
							)
						);
						?>
					</ul>
					<p class="cc-muted" style="font-size:12px;margin-top:12px;">
						Tip: add WooCommerce &ldquo;Filter by price / attribute / stock&rdquo; blocks to the
						<em>Shop Filters</em> widget area for brand, price and storage filters.
					</p>
				</div>
			<?php endif; ?>
		</aside>

		<section class="cc-shop-main">
			<?php if ( woocommerce_product_loop() ) : ?>

				<?php woocommerce_product_loop_start(); ?>

				<?php while ( have_posts() ) : ?>
					<?php
					the_post();
					wc_get_template_part( 'content', 'product' );
					?>
				<?php endwhile; ?>

				<?php woocommerce_product_loop_end(); ?>

				<div class="cc-shop-pagination"><?php woocommerce_pagination(); ?></div>

			<?php else : ?>
				<?php do_action( 'woocommerce_no_products_found' ); ?>
			<?php endif; ?>
		</section>

	</div>
</div>
<?php

get_footer( 'shop' );
