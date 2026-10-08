<?php
/**
 * Homepage (design 1a): hero, shop-by-category, featured, new arrivals, service band.
 *
 * @package CellCraze
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$shop_url  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
$has_woo   = function_exists( 'wc_get_products' );

/** Helper: render a product loop using our card template. */
$render_products = function ( $products ) {
	if ( empty( $products ) ) {
		echo '<p class="cc-muted" style="padding:20px 40px;">No products yet. Add products in the dashboard.</p>';
		return;
	}
	echo '<ul class="products cc-grid cc-grid-4">';
	global $product, $post;
	foreach ( $products as $product ) {
		$post = get_post( $product->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $post );
		wc_get_template_part( 'content', 'product' );
	}
	wp_reset_postdata();
	echo '</ul>';
};
?>

<!-- HERO -->
<section class="cc-hero">
	<div class="cc-hero-copy">
		<span class="cc-kicker">New this week &mdash; latest arrivals</span>
		<h1>Phones, sound and power. <span class="cc-accent">In stock today.</span></h1>
		<p class="cc-lead">Genuine flagships and the accessories that go with them &mdash; warranty-backed, delivered nationwide, paid the way you like.</p>
		<div class="cc-hero-actions">
			<a class="btn btn-primary btn-cta" style="width:auto;" href="<?php echo esc_url( $shop_url ); ?>">
				Shop phones
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
			</a>
			<a class="btn btn-secondary" href="<?php echo esc_url( $shop_url ); ?>">Browse accessories</a>
		</div>
	</div>
	<div class="cc-img-well cc-hero-img">
		<?php
		// Optional hero image: set a featured image on the front page.
		if ( has_post_thumbnail() ) {
			the_post_thumbnail( 'full' );
		}
		?>
	</div>
</section>

<?php if ( $has_woo ) : ?>

	<!-- SHOP BY CATEGORY -->
	<section class="cc-shop-cats">
		<div class="cc-section-head">
			<h2>Shop by category</h2>
			<a href="<?php echo esc_url( $shop_url ); ?>">All categories</a>
		</div>
		<?php
		$cats = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => 0,
				'hide_empty' => false,
				'number'     => 6,
				'exclude'    => array( get_option( 'default_product_cat' ) ),
			)
		);
		if ( ! is_wp_error( $cats ) && $cats ) :
			?>
			<div class="cc-grid cc-grid-6">
				<?php
				$i = 1;
				foreach ( $cats as $cat ) :
					$thumb_id  = get_term_meta( $cat->term_id, 'thumbnail_id', true );
					$thumb_img = $thumb_id ? wp_get_attachment_image( $thumb_id, 'woocommerce_thumbnail' ) : '';
					?>
					<a class="cc-cat-cell" href="<?php echo esc_url( get_term_link( $cat ) ); ?>">
						<span class="cc-idx"><?php echo esc_html( sprintf( '%02d', $i ) ); ?></span>
						<span class="cc-img-well"><?php echo $thumb_img; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						<span class="cc-cat-name"><?php echo esc_html( $cat->name ); ?></span>
						<span class="cc-cat-count"><?php echo esc_html( $cat->count ); ?> products</span>
						<svg class="cc-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
					</a>
					<?php
					$i++;
				endforeach;
				?>
			</div>
		<?php endif; ?>
	</section>

	<!-- FEATURED -->
	<section class="cc-featured cc-product-grid">
		<div class="cc-section-head">
			<h2>Featured</h2>
			<a href="<?php echo esc_url( $shop_url ); ?>">View all</a>
		</div>
		<?php
		$featured = wc_get_products(
			array(
				'status'   => 'publish',
				'featured' => true,
				'limit'    => 4,
			)
		);
		if ( empty( $featured ) ) {
			// Fall back to recent products if none are flagged featured.
			$featured = wc_get_products( array( 'status' => 'publish', 'limit' => 4, 'orderby' => 'date', 'order' => 'DESC' ) );
		}
		$render_products( $featured );
		?>
	</section>

	<!-- NEW ARRIVALS -->
	<section class="cc-arrivals">
		<div class="cc-arrivals-head">
			<h2>New arrivals</h2>
			<p class="cc-muted">Landed in store this week. Limited first stock.</p>
		</div>
		<div class="cc-arrivals-list">
			<?php
			$arrivals = wc_get_products( array( 'status' => 'publish', 'limit' => 5, 'orderby' => 'date', 'order' => 'DESC' ) );
			$n = 1;
			foreach ( $arrivals as $p ) :
				$cat_names = wp_get_post_terms( $p->get_id(), 'product_cat', array( 'fields' => 'names' ) );
				?>
				<a class="cc-arrival-row" href="<?php echo esc_url( get_permalink( $p->get_id() ) ); ?>">
					<span class="cc-idx"><?php echo esc_html( sprintf( '%02d', $n ) ); ?></span>
					<span class="cc-img-well cc-thumb"><?php echo $p->get_image( 'woocommerce_thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span>
						<span class="cc-a-brand"><?php echo esc_html( cellcraze_brand( $p ) ); ?></span><br>
						<span class="cc-a-name"><?php echo esc_html( $p->get_name() ); ?></span>
					</span>
					<span class="cc-muted"><?php echo esc_html( $cat_names ? $cat_names[0] : '' ); ?></span>
					<span class="cc-a-price"><?php echo $p->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
				</a>
				<?php
				$n++;
			endforeach;
			?>
		</div>
	</section>

<?php endif; ?>

<!-- SERVICE BAND -->
<section class="cc-service">
	<div class="cc-grid cc-grid-3">
		<div class="cc-service-cell">
			<svg class="cc-svc-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M10 17h4V5H2v12h3"></path><path d="M20 17h2v-4l-3-4h-4v8h2"></path><circle cx="7.5" cy="17.5" r="2.5"></circle><circle cx="17.5" cy="17.5" r="2.5"></circle></svg>
			<h4>Delivered in 2&ndash;4 days</h4>
			<p>Nationwide delivery across Sri Lanka.</p>
		</div>
		<div class="cc-service-cell">
			<svg class="cc-svc-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><rect x="2" y="5" width="20" height="14"></rect><path d="M2 10h20"></path></svg>
			<h4>Card, cash or transfer</h4>
			<p>Pay by card, cash on delivery, or bank transfer.</p>
		</div>
		<div class="cc-service-cell">
			<svg class="cc-svc-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="m9 12 2 2 4-4"></path></svg>
			<h4>Genuine, with warranty</h4>
			<p>Official warranty on every device.</p>
		</div>
	</div>
</section>

<?php
get_footer();
