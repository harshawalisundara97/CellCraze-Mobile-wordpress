<?php
/**
 * Header — utility bar, main bar (logo/search/actions), category bar.
 * Plus a mobile header (shown under 768px). Design screens 1a/1b/1c/1e.
 *
 * @package CellCraze
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cart_count = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
$cart_url   = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
$shop_url   = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="cc-site-header">

	<!-- Utility bar -->
	<div class="cc-utility">
		<div class="cc-util-inner">
			<span>Free delivery over Rs 10,000</span>
			<span>Cash on delivery available</span>
			<span>Official warranty on every device</span>
			<a class="cc-util-right" href="<?php echo esc_url( $account_url ); ?>">Track your order</a>
		</div>
	</div>

	<!-- Main bar (desktop) -->
	<div class="cc-mainbar">
		<a class="cc-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<span class="cc-mark"></span><?php echo esc_html( get_bloginfo( 'name' ) ?: 'CellCraze' ); ?>
		</a>

		<form role="search" method="get" class="cc-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<svg class="cc-search-ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
			<input type="search" name="s" placeholder="Search phones, earbuds, chargers&hellip;" value="<?php echo esc_attr( get_search_query() ); ?>">
			<input type="hidden" name="post_type" value="product">
		</form>

		<div class="cc-actions">
			<a class="btn btn-ghost" href="<?php echo esc_url( $account_url ); ?>">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
				<?php echo is_user_logged_in() ? esc_html( wp_get_current_user()->first_name ?: wp_get_current_user()->display_name ) : 'Sign in'; ?>
			</a>
			<a class="btn btn-icon btn-secondary" href="<?php echo esc_url( home_url( '/wishlist/' ) ); ?>" aria-label="Wishlist">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"></path></svg>
			</a>
			<a class="btn btn-primary cc-cart-btn" href="<?php echo esc_url( $cart_url ); ?>">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><path d="M3 6h18"></path><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
				Cart <span class="cc-cart-count"><?php echo esc_html( $cart_count ); ?></span>
			</a>
		</div>
	</div>

	<!-- Category bar (desktop) -->
	<nav class="cc-catbar">
		<?php
		if ( has_nav_menu( 'cc_categories' ) ) {
			wp_nav_menu(
				array(
					'theme_location' => 'cc_categories',
					'container'      => false,
					'items_wrap'     => '%3$s',
					'fallback_cb'    => false,
					'walker'         => new Cellcraze_Flat_Walker(),
				)
			);
		} else {
			// Fallback: top-level product categories.
			$cats = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => false, 'number' => 7 ) );
			if ( ! is_wp_error( $cats ) ) {
				foreach ( $cats as $cat ) {
					printf( '<a href="%s">%s</a>', esc_url( get_term_link( $cat ) ), esc_html( $cat->name ) );
				}
			}
		}
		?>
		<a class="cc-deals" href="<?php echo esc_url( add_query_arg( 'on_sale', '1', $shop_url ) ); ?>">Deals</a>
	</nav>

	<!-- Mobile header -->
	<div class="cc-mobile-header">
		<div class="cc-mobile-bar">
			<a class="btn btn-icon btn-secondary" href="<?php echo esc_url( $shop_url ); ?>" aria-label="Menu">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M3 6h18M3 12h18M3 18h18"></path></svg>
			</a>
			<a class="cc-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span class="cc-mark"></span><?php echo esc_html( get_bloginfo( 'name' ) ?: 'CellCraze' ); ?></a>
			<a class="btn btn-icon btn-primary cc-cart-btn" href="<?php echo esc_url( $cart_url ); ?>" aria-label="Cart">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><path d="M3 6h18"></path><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
			</a>
		</div>
		<form role="search" method="get" class="cc-mobile-search cc-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<svg class="cc-search-ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
			<input type="search" name="s" placeholder="Search&hellip;" value="<?php echo esc_attr( get_search_query() ); ?>">
			<input type="hidden" name="post_type" value="product">
		</form>
	</div>

</header>

<main class="cc-main">
