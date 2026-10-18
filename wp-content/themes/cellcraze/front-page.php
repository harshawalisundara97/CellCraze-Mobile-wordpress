<?php
/**
 * Homepage: configurable hero, shop-by-category, special offers (deals),
 * new arrivals, configurable promo banner, store-benefits band.
 *
 * All product/category data is pulled live from WooCommerce; sections with no
 * eligible data hide themselves rather than showing placeholders.
 *
 * @package CellCraze
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
$has_woo  = function_exists( 'wc_get_products' );
$arrow    = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>';

/** Render a product loop using our shared card template. */
$render_products = function ( $products, $grid = 'cc-grid-4' ) {
	if ( empty( $products ) ) {
		return;
	}
	echo '<ul class="products cc-grid ' . esc_attr( $grid ) . '">';
	global $product, $post;
	foreach ( $products as $product ) {
		$post = get_post( $product->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $post );
		wc_get_template_part( 'content', 'product' );
	}
	wp_reset_postdata();
	echo '</ul>';
};

$deals = $has_woo ? cellcraze_get_sale_products( 8 ) : array();
?>

<!-- HERO -->
<section class="cc-hero">
	<div class="cc-hero-copy">
		<span class="cc-kicker"><?php echo esc_html( cellcraze_opt( 'cc_hero_kicker' ) ); ?></span>
		<h1><?php echo esc_html( cellcraze_opt( 'cc_hero_heading' ) ); ?></h1>
		<p class="cc-lead"><?php echo esc_html( cellcraze_opt( 'cc_hero_sub' ) ); ?></p>
		<div class="cc-hero-actions">
			<a class="btn btn-primary btn-cta" style="width:auto;" href="<?php echo esc_url( cellcraze_opt( 'cc_hero_cta_link' ) ?: $shop_url ); ?>">
				<?php echo esc_html( cellcraze_opt( 'cc_hero_cta_text' ) ); ?>
				<?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</a>
			<?php if ( ! empty( $deals ) ) : ?>
				<a class="btn btn-secondary" href="<?php echo esc_url( cellcraze_deals_url() ); ?>">Explore deals</a>
			<?php endif; ?>
		</div>
	</div>
	<div class="cc-hero-img<?php echo has_post_thumbnail() ? '' : ' cc-hero-visual'; ?>">
		<?php
		if ( has_post_thumbnail() ) {
			the_post_thumbnail( 'full', array( 'fetchpriority' => 'high' ) );
		} else {
			?>
			<div class="cc-hero-brand" aria-hidden="true">
				<span class="cc-mark"></span>
				<span class="cc-hero-word">CellCraze</span>
				<span class="cc-hero-tag">Phones &middot; Audio &middot; Power</span>
			</div>
			<?php
		}
		?>
	</div>
</section>

<?php if ( $has_woo ) : ?>

	<!-- SHOP BY CATEGORY -->
	<?php
	$cats = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => 0,
			'hide_empty' => true,
			'number'     => 6,
			'exclude'    => array( get_option( 'default_product_cat' ) ),
		)
	);
	if ( ! is_wp_error( $cats ) && $cats ) :
		?>
		<section class="cc-shop-cats">
			<div class="cc-section-head">
				<h2>Shop by category</h2>
				<a href="<?php echo esc_url( $shop_url ); ?>">View all categories</a>
			</div>
			<div class="cc-grid cc-grid-6">
				<?php
				$i = 1;
				foreach ( $cats as $cat ) :
					$thumb_id  = get_term_meta( $cat->term_id, 'thumbnail_id', true );
					$thumb_img = $thumb_id ? wp_get_attachment_image( $thumb_id, 'woocommerce_thumbnail' ) : '';
					?>
					<a class="cc-cat-cell" href="<?php echo esc_url( get_term_link( $cat ) ); ?>">
						<span class="cc-idx"><?php echo esc_html( sprintf( '%02d', $i ) ); ?></span>
						<span class="cc-img-well">
							<?php
							if ( $thumb_img ) {
								echo $thumb_img; // phpcs:ignore WordPress.Security.EscapeOutput
							} else {
								echo '<img src="' . esc_url( get_stylesheet_directory_uri() . '/assets/img/placeholder.svg' ) . '" alt="" class="woocommerce-placeholder" loading="lazy" />';
							}
							?>
						</span>
						<span class="cc-cat-name"><?php echo esc_html( $cat->name ); ?></span>
						<span class="cc-cat-count"><?php echo esc_html( sprintf( _n( '%d product', '%d products', $cat->count, 'cellcraze' ), $cat->count ) ); ?></span>
						<?php echo str_replace( '<svg', '<svg class="cc-arrow"', $arrow ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</a>
					<?php
					$i++;
				endforeach;
				?>
			</div>
		</section>
	<?php endif; ?>

	<!-- SPECIAL OFFERS (real on-sale products; no fabricated countdown) -->
	<?php if ( ! empty( $deals ) ) : ?>
		<section class="cc-deals-sec cc-product-grid">
			<div class="cc-section-head">
				<div>
					<span class="cc-kicker">Limited-time pricing</span>
					<h2>Special offers</h2>
				</div>
				<a href="<?php echo esc_url( cellcraze_deals_url() ); ?>">View all deals <?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
			</div>
			<?php $render_products( $deals ); ?>
		</section>
	<?php endif; ?>

	<!-- NEW ARRIVALS -->
	<?php
	$arrivals = wc_get_products( array( 'status' => 'publish', 'limit' => 5, 'orderby' => 'date', 'order' => 'DESC' ) );
	if ( $arrivals ) :
		?>
		<section class="cc-arrivals">
			<div class="cc-arrivals-head">
				<h2>New arrivals</h2>
				<p class="cc-muted">The latest products to land in store.</p>
				<a class="cc-arrivals-link" href="<?php echo esc_url( add_query_arg( 'orderby', 'date', $shop_url ) ); ?>">View all <?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
			</div>
			<div class="cc-arrivals-list">
				<?php
				$n = 1;
				foreach ( $arrivals as $p ) :
					$cat_names = wp_get_post_terms( $p->get_id(), 'product_cat', array( 'fields' => 'names' ) );
					?>
					<a class="cc-arrival-row" href="<?php echo esc_url( get_permalink( $p->get_id() ) ); ?>">
						<span class="cc-idx"><?php echo esc_html( sprintf( '%02d', $n ) ); ?></span>
						<span class="cc-img-well cc-thumb"><?php echo $p->get_image( 'woocommerce_thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						<span>
							<span class="cc-a-brand"><?php echo esc_html( cellcraze_brand( $p ) ); ?></span>
							<span class="cc-a-name"><?php echo esc_html( $p->get_name() ); ?><?php echo cellcraze_is_new( $p ) ? ' <span class="cc-pill-new">New</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</span>
						<span class="cc-muted"><?php echo esc_html( $cat_names ? $cat_names[0] : '' ); ?></span>
						<span class="cc-a-price"><?php echo $p->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						<?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</a>
					<?php
					$n++;
				endforeach;
				?>
			</div>
		</section>
	<?php endif; ?>

	<!-- PROMO BANNER (configurable) -->
	<?php
	if ( cellcraze_opt( 'cc_promo_on' ) && '' !== trim( (string) cellcraze_opt( 'cc_promo_title' ) ) ) :
		$promo_link = cellcraze_opt( 'cc_promo_link' ) ?: $shop_url;
		?>
		<section class="cc-promo">
			<div class="cc-promo-inner">
				<div class="cc-promo-copy">
					<h2><?php echo esc_html( cellcraze_opt( 'cc_promo_title' ) ); ?></h2>
					<p><?php echo esc_html( cellcraze_opt( 'cc_promo_text' ) ); ?></p>
					<a class="btn btn-primary btn-cta" style="width:auto;" href="<?php echo esc_url( $promo_link ); ?>">
						<?php echo esc_html( cellcraze_opt( 'cc_promo_cta_text' ) ); ?>
						<?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</a>
				</div>
				<div class="cc-promo-art" aria-hidden="true"><span class="cc-mark"></span></div>
			</div>
		</section>
	<?php endif; ?>

<?php endif; ?>

<!-- STORE BENEFITS -->
<section class="cc-service">
	<div class="cc-grid cc-grid-3">
		<div class="cc-service-cell">
			<svg class="cc-svc-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="m9 12 2 2 4-4"></path></svg>
			<h4>Genuine products</h4>
			<p>Authentic phones and accessories, backed by official warranty.</p>
		</div>
		<div class="cc-service-cell">
			<svg class="cc-svc-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><rect x="2" y="5" width="20" height="14"></rect><path d="M2 10h20"></path></svg>
			<h4>Flexible payment</h4>
			<p>Pay by cash on delivery or bank transfer at checkout.</p>
		</div>
		<div class="cc-service-cell">
			<svg class="cc-svc-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
			<h4>Here to help</h4>
			<p>Questions before you buy? Reach our team any time.</p>
		</div>
	</div>
</section>

<?php
get_footer();
