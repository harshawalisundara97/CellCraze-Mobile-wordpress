<?php
/**
 * Footer — Modernist 4-column footer + bottom bar. Design screen 1a.
 *
 * @package CellCraze
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fallback_shop = array(
	'cc_footer_shop'    => array( 'Phones', 'Headphones', 'Earphones', 'Chargers', 'Smartwatches', 'Accessories' ),
	'cc_footer_help'    => array( 'Delivery', 'Returns', 'Warranty', 'Contact' ),
	'cc_footer_account' => array( 'Sign in', 'My orders', 'Wishlist', 'Track order' ),
);
$titles = array(
	'cc_footer_shop'    => 'Shop',
	'cc_footer_help'    => 'Help',
	'cc_footer_account' => 'Account',
);
?>
</main><!-- .cc-main -->

<footer class="cc-site-footer">
	<div class="cc-footer">
		<div>
			<a class="cc-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span class="cc-mark"></span><?php echo esc_html( get_bloginfo( 'name' ) ?: 'CellCraze' ); ?></a>
			<p class="cc-muted" style="margin-top:12px;max-width:40ch;">Genuine phones and accessories, warranty-backed and delivered across Sri Lanka. Pay by card, cash on delivery, or bank transfer.</p>
		</div>
		<?php foreach ( $titles as $location => $title ) : ?>
			<div>
				<h5><?php echo esc_html( $title ); ?></h5>
				<?php if ( has_nav_menu( $location ) ) : ?>
					<?php
					wp_nav_menu(
						array(
							'theme_location' => $location,
							'container'      => false,
							'items_wrap'     => '<ul>%3$s</ul>',
							'depth'          => 1,
							'fallback_cb'    => false,
						)
					);
					?>
				<?php else : ?>
					<ul>
						<?php foreach ( $fallback_shop[ $location ] as $label ) : ?>
							<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $label ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<div class="cc-footer-bottom">
		<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ?: 'CellCraze' ); ?></span>
		<span>Visa &middot; Mastercard &middot; Cash on delivery &middot; Bank transfer</span>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
