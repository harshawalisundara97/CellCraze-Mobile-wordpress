<?php
/**
 * Footer — brand + real, resolvable links (dynamic categories + WooCommerce /
 * wishlist endpoints) + payment line + copyright. Owners can override any
 * column by assigning the matching footer menu; otherwise these safe defaults
 * are used, and every link points at a page that actually exists.
 *
 * @package CellCraze
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cc_shop    = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
$cc_account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
$cc_name    = get_bloginfo( 'name' ) ?: 'CellCraze';

/** Real top-level product categories for the Shop column. */
$cc_footer_cats = function_exists( 'get_terms' ) ? get_terms( array(
	'taxonomy'   => 'product_cat',
	'parent'     => 0,
	'hide_empty' => true,
	'number'     => 12, // UX-002: show all top-level categories incl. Phones (was 6, which cut Phones).
	'exclude'    => array( get_option( 'default_product_cat' ) ),
) ) : array();

/** Account links that always resolve. */
$cc_account_links = array(
	array( __( 'My account', 'cellcraze' ), $cc_account ),
	array( __( 'Orders', 'cellcraze' ), function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'orders' ) : $cc_account ),
);
if ( function_exists( 'wc_get_cart_url' ) ) {
	$cc_account_links[] = array( __( 'Cart', 'cellcraze' ), wc_get_cart_url() );
}
// Wishlist only when the wishlist plugin is actually active.
if ( defined( 'TINVWL_URL' ) && class_exists( 'TInvWL_Public_Wishlist_View' ) ) {
	$cc_wishlist = function_exists( 'tinv_url_wishlist_default' ) ? tinv_url_wishlist_default() : home_url( '/wishlist/' );
	$cc_account_links[] = array( __( 'Wishlist', 'cellcraze' ), $cc_wishlist );
}

/**
 * Help links — genuine support links only (UI-007: no "All products" here).
 * Contact prefers a real Contact page when one exists, else a working mailto.
 * Policy/FAQ links appear only when the matching page actually exists — we never
 * link to a page that would 404.
 */
$cc_help_links   = array();
$cc_contact_page = get_page_by_path( 'contact-us' );
$cc_help_links[] = $cc_contact_page
	? array( __( 'Contact us', 'cellcraze' ), get_permalink( $cc_contact_page ) )
	: array( __( 'Contact us', 'cellcraze' ), 'mailto:' . antispambot( get_option( 'admin_email' ) ) );

foreach ( array(
	'shipping-policy' => __( 'Shipping', 'cellcraze' ),
	'returns-policy'  => __( 'Returns & refunds', 'cellcraze' ),
	'faq'             => __( 'FAQ', 'cellcraze' ),
) as $cc_slug => $cc_label ) {
	$cc_pg = get_page_by_path( $cc_slug );
	if ( $cc_pg ) {
		$cc_help_links[] = array( $cc_label, get_permalink( $cc_pg ) );
	}
}
// Privacy page link only when it is set AND published.
$cc_privacy = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
if ( $cc_privacy ) {
	$cc_help_links[] = array( __( 'Privacy Policy', 'cellcraze' ), $cc_privacy );
}
?>
</main><!-- .cc-main -->

<footer class="cc-site-footer">
	<div class="cc-footer">
		<div>
			<a class="cc-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><img class="cc-logo-img" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/img/cellcraze-logo.jpg' ); ?>" alt="<?php echo esc_attr( $cc_name ); ?>" width="56" height="56" /></a>
			<p class="cc-muted" style="margin-top:12px;max-width:40ch;">Genuine phones and accessories, backed by official warranty. Pay by cash on delivery or bank transfer.</p>
		</div>

		<div>
			<h5><?php esc_html_e( 'Shop', 'cellcraze' ); ?></h5>
			<?php if ( has_nav_menu( 'cc_footer_shop' ) ) : ?>
				<?php wp_nav_menu( array( 'theme_location' => 'cc_footer_shop', 'container' => false, 'items_wrap' => '<ul>%3$s</ul>', 'depth' => 1, 'fallback_cb' => false ) ); ?>
			<?php else : ?>
				<ul>
					<li><a href="<?php echo esc_url( $cc_shop ); ?>"><?php esc_html_e( 'All products', 'cellcraze' ); ?></a></li>
					<?php
					if ( ! is_wp_error( $cc_footer_cats ) ) {
						foreach ( $cc_footer_cats as $cc_cat ) {
							$cc_link = get_term_link( $cc_cat );
							if ( ! is_wp_error( $cc_link ) ) {
								printf( '<li><a href="%s">%s</a></li>', esc_url( $cc_link ), esc_html( $cc_cat->name ) );
							}
						}
					}
					?>
				</ul>
			<?php endif; ?>
		</div>

		<div>
			<h5><?php esc_html_e( 'Account', 'cellcraze' ); ?></h5>
			<?php if ( has_nav_menu( 'cc_footer_account' ) ) : ?>
				<?php wp_nav_menu( array( 'theme_location' => 'cc_footer_account', 'container' => false, 'items_wrap' => '<ul>%3$s</ul>', 'depth' => 1, 'fallback_cb' => false ) ); ?>
			<?php else : ?>
				<ul>
					<?php foreach ( $cc_account_links as $cc_a ) : ?>
						<li><a href="<?php echo esc_url( $cc_a[1] ); ?>"><?php echo esc_html( $cc_a[0] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<div>
			<h5><?php esc_html_e( 'Help', 'cellcraze' ); ?></h5>
			<?php if ( has_nav_menu( 'cc_footer_help' ) ) : ?>
				<?php wp_nav_menu( array( 'theme_location' => 'cc_footer_help', 'container' => false, 'items_wrap' => '<ul>%3$s</ul>', 'depth' => 1, 'fallback_cb' => false ) ); ?>
			<?php else : ?>
				<ul>
					<?php foreach ( $cc_help_links as $cc_h ) : ?>
						<li><a href="<?php echo esc_url( $cc_h[1] ); ?>"><?php echo wp_kses_post( $cc_h[0] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>

	<div class="cc-footer-bottom">
		<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $cc_name ); ?></span>
		<span>Cash on delivery &middot; Bank transfer</span>
	</div>
</footer>

<button type="button" class="cc-to-top" aria-label="<?php esc_attr_e( 'Back to top', 'cellcraze' ); ?>">
	<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M12 19V5"></path><path d="m5 12 7-7 7 7"></path></svg>
</button>

<?php wp_footer(); ?>
</body>
</html>
