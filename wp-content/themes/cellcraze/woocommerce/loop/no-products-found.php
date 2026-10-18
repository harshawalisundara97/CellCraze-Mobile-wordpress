<?php
/**
 * Empty results state (search with no matches, or an empty category).
 * Overrides WooCommerce loop/no-products-found.php with helpful next steps.
 *
 * @package CellCraze
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cc_shop  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
$cc_search = is_search();
$cc_cats  = function_exists( 'get_terms' ) ? get_terms( array(
	'taxonomy'   => 'product_cat',
	'parent'     => 0,
	'hide_empty' => true,
	'number'     => 6,
	'exclude'    => array( get_option( 'default_product_cat' ) ),
) ) : array();
?>
<div class="cc-empty-state">
	<span class="cc-empty-icon" aria-hidden="true">
		<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path><path d="M8 11h6"></path></svg>
	</span>
	<h2 class="cc-empty-title">
		<?php echo $cc_search ? esc_html__( 'No products matched your search', 'cellcraze' ) : esc_html__( 'Nothing here just yet', 'cellcraze' ); ?>
	</h2>
	<p class="cc-empty-text">
		<?php
		echo $cc_search
			? esc_html__( 'Try a different spelling or a more general term — or browse the full range below.', 'cellcraze' )
			: esc_html__( 'Browse the full range or pick a category below.', 'cellcraze' );
		?>
	</p>
	<div class="cc-empty-actions">
		<a class="btn btn-primary" href="<?php echo esc_url( $cc_shop ); ?>"><?php esc_html_e( 'Shop all products', 'cellcraze' ); ?></a>
	</div>
	<?php if ( ! is_wp_error( $cc_cats ) && $cc_cats ) : ?>
		<div class="cc-empty-cats">
			<?php
			foreach ( $cc_cats as $cc_cat ) {
				$cc_link = get_term_link( $cc_cat );
				if ( ! is_wp_error( $cc_link ) ) {
					printf( '<a class="cc-empty-cat" href="%s">%s</a>', esc_url( $cc_link ), esc_html( $cc_cat->name ) );
				}
			}
			?>
		</div>
	<?php endif; ?>
</div>
