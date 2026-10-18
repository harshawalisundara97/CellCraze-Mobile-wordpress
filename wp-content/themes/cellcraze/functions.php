<?php
/**
 * CellCraze child theme functions.
 *
 * Recreates the "Modernist" design handoff on WordPress + WooCommerce.
 *
 * @package CellCraze
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CELLCRAZE_VERSION', '1.3.2' );

/**
 * Styles: Archivo (Google Fonts) + Modernist tokens + CellCraze layer.
 * Storefront's own CSS is dequeued so we build on a clean canvas.
 */
function cellcraze_enqueue_styles() {
	// Start from a clean slate — drop Storefront's stylesheets.
	wp_dequeue_style( 'storefront-style' );
	wp_dequeue_style( 'storefront-woocommerce-style' );
	wp_dequeue_style( 'storefront-gutenberg-blocks' );

	wp_enqueue_style(
		'cellcraze-fonts',
		'https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;800&display=swap',
		array(),
		null
	);
	wp_enqueue_style(
		'cellcraze-tokens',
		get_stylesheet_directory_uri() . '/assets/css/tokens.css',
		array( 'cellcraze-fonts' ),
		CELLCRAZE_VERSION
	);
	wp_enqueue_style(
		'cellcraze-main',
		get_stylesheet_directory_uri() . '/assets/css/cellcraze.css',
		array( 'cellcraze-tokens' ),
		CELLCRAZE_VERSION
	);
	wp_enqueue_script(
		'cellcraze-main',
		get_stylesheet_directory_uri() . '/assets/js/cellcraze.js',
		array(),
		CELLCRAZE_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'cellcraze_enqueue_styles', 100 );

/**
 * Theme supports & menus.
 */
function cellcraze_setup() {
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );

	add_image_size( 'cellcraze_card', 600, 600, true );

	register_nav_menus(
		array(
			'cc_categories' => __( 'Category Bar', 'cellcraze' ),
			'cc_footer_shop' => __( 'Footer — Shop', 'cellcraze' ),
			'cc_footer_help' => __( 'Footer — Help', 'cellcraze' ),
			'cc_footer_account' => __( 'Footer — Account', 'cellcraze' ),
		)
	);
}
add_action( 'after_setup_theme', 'cellcraze_setup' );

/** Shop filter sidebar (place WooCommerce "Filter by" widgets here). */
function cellcraze_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( 'Shop Filters', 'cellcraze' ),
			'id'            => 'cc_shop_filters',
			'description'   => __( 'Filters shown on the shop/category pages (add WooCommerce Filter-by widgets).', 'cellcraze' ),
			'before_widget' => '<div class="cc-filter-group %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h3 class="cc-filter-title">',
			'after_title'   => '</h3>',
		)
	);
}
add_action( 'widgets_init', 'cellcraze_widgets_init' );

/** Add a body class so our CSS is namespaced. */
function cellcraze_body_class( $classes ) {
	$classes[] = 'cellcraze';
	return $classes;
}
add_filter( 'body_class', 'cellcraze_body_class' );

/** Shop grid: 3 columns (design 1b); featured uses 4 via template. */
add_filter( 'loop_shop_columns', function () { return 3; } );
add_filter( 'loop_shop_per_page', function () { return 12; } );

/**
 * Branded, neutral placeholder for products that have no media.
 *
 * Display-time only: this swaps the *image source* WooCommerce already renders
 * for products without a featured image. It does not create attachments, touch
 * product records, or alter any media — products with real images are
 * unaffected (this filter only fires when WooCommerce asks for the placeholder).
 *
 * @param string $src Current placeholder image URL.
 * @return string
 */
function cellcraze_branded_placeholder_src( $src ) {
	return get_stylesheet_directory_uri() . '/assets/img/placeholder.svg';
}
add_filter( 'woocommerce_placeholder_img_src', 'cellcraze_branded_placeholder_src' );

/* ============================================================================
 * Homepage content — admin-configurable via the Customizer. Defaults are kept
 * deliberately generic (no invented delivery promises / discounts); the store
 * owner edits these under Appearance → Customize → CellCraze Homepage.
 * ========================================================================== */

/**
 * Get a CellCraze homepage option with a safe default.
 *
 * @param string $key Option key.
 * @return mixed
 */
function cellcraze_opt( $key ) {
	$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
	$defaults = array(
		'cc_announce_on'    => true,
		'cc_announce_text'  => 'Genuine phones & accessories with official warranty.',
		'cc_hero_kicker'    => 'The latest, in stock',
		'cc_hero_heading'   => 'Phones, sound and power. In stock today.',
		'cc_hero_sub'       => 'Genuine flagships and the accessories that go with them — warranty-backed and ready to ship.',
		'cc_hero_cta_text'  => 'Shop now',
		'cc_hero_cta_link'  => $shop,
		'cc_promo_on'       => true,
		'cc_promo_title'    => 'The right accessories for every device',
		'cc_promo_text'     => 'Cases, chargers, cables and audio — the essentials to go with your new phone.',
		'cc_promo_cta_text' => 'Shop accessories',
		'cc_promo_link'     => $shop,
	);
	$default = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	return get_theme_mod( $key, $default );
}

/**
 * Register Customizer controls for the homepage.
 *
 * @param WP_Customize_Manager $wp_customize Customizer.
 */
function cellcraze_customize_register( $wp_customize ) {
	$wp_customize->add_section( 'cellcraze_home', array(
		'title'    => __( 'CellCraze Homepage', 'cellcraze' ),
		'priority' => 30,
	) );

	$fields = array(
		'cc_announce_on'    => array( 'checkbox', __( 'Show announcement bar', 'cellcraze' ) ),
		'cc_announce_text'  => array( 'text', __( 'Announcement bar text', 'cellcraze' ) ),
		'cc_hero_kicker'    => array( 'text', __( 'Hero kicker', 'cellcraze' ) ),
		'cc_hero_heading'   => array( 'text', __( 'Hero heading', 'cellcraze' ) ),
		'cc_hero_sub'       => array( 'textarea', __( 'Hero description', 'cellcraze' ) ),
		'cc_hero_cta_text'  => array( 'text', __( 'Hero button label', 'cellcraze' ) ),
		'cc_hero_cta_link'  => array( 'url', __( 'Hero button link', 'cellcraze' ) ),
		'cc_promo_on'       => array( 'checkbox', __( 'Show promo banner', 'cellcraze' ) ),
		'cc_promo_title'    => array( 'text', __( 'Promo banner title', 'cellcraze' ) ),
		'cc_promo_text'     => array( 'textarea', __( 'Promo banner text', 'cellcraze' ) ),
		'cc_promo_cta_text' => array( 'text', __( 'Promo button label', 'cellcraze' ) ),
		'cc_promo_link'     => array( 'url', __( 'Promo button link', 'cellcraze' ) ),
	);

	foreach ( $fields as $key => $meta ) {
		list( $type, $label ) = $meta;
		$sanitize = 'sanitize_text_field';
		$control  = 'text';
		if ( 'checkbox' === $type ) {
			$sanitize = 'wp_validate_boolean';
			$control  = 'checkbox';
		} elseif ( 'url' === $type ) {
			$sanitize = 'esc_url_raw';
			$control  = 'url';
		} elseif ( 'textarea' === $type ) {
			$sanitize = 'sanitize_textarea_field';
			$control  = 'textarea';
		}
		$wp_customize->add_setting( $key, array(
			'default'           => cellcraze_opt( $key ),
			'sanitize_callback' => $sanitize,
			'transport'         => 'refresh',
		) );
		$wp_customize->add_control( $key, array(
			'label'   => $label,
			'section' => 'cellcraze_home',
			'type'    => $control,
		) );
	}
}
add_action( 'customize_register', 'cellcraze_customize_register' );

/**
 * Is this product a genuine new arrival? (Published within the configured
 * window — default 30 days. Filterable so there's a single, explainable rule.)
 *
 * @param WC_Product $product Product.
 * @return bool
 */
function cellcraze_is_new( $product ) {
	if ( ! $product ) {
		return false;
	}
	$days    = (int) apply_filters( 'cellcraze_new_days', 30 );
	$created = $product->get_date_created();
	if ( ! $created ) {
		return false;
	}
	return ( time() - $created->getTimestamp() ) < ( $days * DAY_IN_SECONDS );
}

/**
 * Make ?on_sale=1 on the shop / category archives show only sale products, so
 * the "View all deals" links resolve to a real, filtered listing.
 *
 * @param WP_Query $q Product query.
 */
function cellcraze_filter_on_sale_query( $q ) {
	if ( empty( $_GET['on_sale'] ) || '1' !== $_GET['on_sale'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$ids = wc_get_product_ids_on_sale();
	$q->set( 'post__in', ! empty( $ids ) ? $ids : array( 0 ) );
}
add_action( 'woocommerce_product_query', 'cellcraze_filter_on_sale_query' );

/** Flag the shop title when the on-sale filter is active. */
function cellcraze_on_sale_title( $title ) {
	if ( ! is_admin() && is_shop() && ! empty( $_GET['on_sale'] ) && '1' === $_GET['on_sale'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		return __( 'Deals &amp; offers', 'cellcraze' );
	}
	return $title;
}
add_filter( 'woocommerce_page_title', 'cellcraze_on_sale_title' );

/**
 * Get currently on-sale products for the homepage Deals section.
 *
 * @param int $limit Max products.
 * @return WC_Product[]
 */
function cellcraze_get_sale_products( $limit = 8 ) {
	$ids = wc_get_product_ids_on_sale();
	if ( empty( $ids ) ) {
		return array();
	}
	return wc_get_products( array(
		'include' => $ids,
		'status'  => 'publish',
		'limit'   => $limit,
		'orderby' => 'date',
		'order'   => 'DESC',
	) );
}

/** URL for the on-sale (deals) listing. */
function cellcraze_deals_url() {
	$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
	return add_query_arg( 'on_sale', '1', $shop );
}

/**
 * Stock indicator matching the design (square + label).
 * 0 => Out of stock (neutral), 1–5 => Only N left (accent), >5 => In stock (ink).
 *
 * @param WC_Product $product Product.
 * @return string HTML.
 */
function cellcraze_stock_indicator( $product ) {
	if ( ! $product ) {
		return '';
	}
	$qty = $product->get_stock_quantity();
	if ( ! $product->is_in_stock() || ( null !== $qty && $qty <= 0 ) ) {
		return '<span class="cc-stock"><span class="cc-sq cc-sq-out"></span>Out of stock</span>';
	}
	if ( null !== $qty && $qty <= 5 ) {
		return '<span class="cc-stock"><span class="cc-sq cc-sq-low"></span>Only ' . intval( $qty ) . ' left</span>';
	}
	return '<span class="cc-stock"><span class="cc-sq cc-sq-in"></span>In stock</span>';
}

/**
 * First brand term for a product (from the "Brand" attribute / pa_brand).
 *
 * @param WC_Product $product Product.
 * @return string
 */
function cellcraze_brand( $product ) {
	if ( ! $product ) {
		return '';
	}
	$terms = wc_get_product_terms( $product->get_id(), 'pa_brand', array( 'fields' => 'names' ) );
	if ( ! empty( $terms ) ) {
		return $terms[0];
	}
	// Fallback to a plain custom attribute named "Brand".
	$attr = $product->get_attribute( 'Brand' );
	return $attr ? $attr : '';
}

/**
 * Flat menu walker: outputs bare <a> links (no <ul>/<li>) for the category bar.
 */
class Cellcraze_Flat_Walker extends Walker_Nav_Menu {
	public function start_lvl( &$output, $depth = 0, $args = null ) {}
	public function end_lvl( &$output, $depth = 0, $args = null ) {}
	public function end_el( &$output, $item = null, $depth = 0, $args = null ) {}
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$current = in_array( 'current-menu-item', (array) $item->classes, true ) ? ' aria-current="page"' : '';
		$output .= sprintf(
			'<a href="%s"%s>%s</a>',
			esc_url( $item->url ),
			$current,
			esc_html( $item->title )
		);
	}
}

/**
 * Register the Brand / Storage / Colour global attributes used by the design.
 * Runs once (guarded) — safe to leave active.
 */
function cellcraze_register_attributes() {
	if ( ! function_exists( 'wc_create_attribute' ) || get_option( 'cellcraze_attributes_done' ) ) {
		return;
	}
	$attributes = array(
		'brand'   => 'Brand',
		'storage' => 'Storage',
		'colour'  => 'Colour',
	);
	foreach ( $attributes as $slug => $name ) {
		if ( ! wc_attribute_taxonomy_id_by_name( $slug ) ) {
			wc_create_attribute(
				array(
					'name'         => $name,
					'slug'         => $slug,
					'type'         => 'select',
					'order_by'     => 'menu_order',
					'has_archives' => false,
				)
			);
		}
	}
	update_option( 'cellcraze_attributes_done', 1 );
}
add_action( 'admin_init', 'cellcraze_register_attributes' );

/* ============================================================================
 * Product detail (PDP, design 1c) — built with WooCommerce hooks so the core
 * variation / add-to-cart JS keeps working. Layout + look come from CSS.
 * ========================================================================== */

/** Brand kicker above the product title. */
function cellcraze_pdp_brand_kicker() {
	global $product;
	$brand = cellcraze_brand( $product );
	if ( $brand ) {
		echo '<span class="cc-kicker cc-pdp-brand">' . esc_html( $brand ) . '</span>';
	}
}
add_action( 'woocommerce_single_product_summary', 'cellcraze_pdp_brand_kicker', 4 );

/** SKU + warranty line under the title. */
function cellcraze_pdp_sku_line() {
	global $product;
	$sku = $product->get_sku();
	if ( ! $sku ) {
		return;
	}
	echo '<p class="cc-pdp-sku">SKU ' . esc_html( $sku ) . '</p>';
}
add_action( 'woocommerce_single_product_summary', 'cellcraze_pdp_sku_line', 6 );

/** Stock line (Modernist square + label) before the add-to-cart form. */
function cellcraze_pdp_stock_line() {
	global $product;
	echo '<div class="cc-pdp-stock">' . cellcraze_stock_indicator( $product ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
}
add_action( 'woocommerce_single_product_summary', 'cellcraze_pdp_stock_line', 25 );

/** Delivery / Payment / Warranty info rows after the add-to-cart area. */
function cellcraze_pdp_info_rows() {
	$rows = array(
		'Delivery' => 'Nationwide delivery across Sri Lanka.',
		'Payment'  => 'Cash on delivery or bank transfer.',
		'Warranty' => 'Manufacturer warranty where applicable.',
	);
	echo '<div class="cc-pdp-info">';
	foreach ( $rows as $label => $text ) {
		echo '<div class="cc-pdp-info-row"><span class="cc-pdp-info-label">' . esc_html( $label ) . '</span><span>' . wp_kses_post( $text ) . '</span></div>';
	}
	echo '</div>';
}
add_action( 'woocommerce_single_product_summary', 'cellcraze_pdp_info_rows', 45 );

/**
 * Specifications section (key/value) from the product's attributes,
 * rendered after the summary, before related products.
 */
function cellcraze_pdp_specifications() {
	global $product;
	$attributes = $product->get_attributes();
	if ( empty( $attributes ) ) {
		return;
	}
	$rows = array();
	foreach ( $attributes as $attribute ) {
		if ( $attribute->get_variation() ) {
			continue; // Variation attributes (Storage/Colour) shown in the form, not specs.
		}
		$name   = wc_attribute_label( $attribute->get_name() );
		$values = $attribute->is_taxonomy()
			? wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) )
			: $attribute->get_options();
		if ( $values ) {
			$rows[ $name ] = implode( ', ', $values );
		}
	}
	if ( empty( $rows ) ) {
		return;
	}
	echo '<section class="cc-pdp-specs"><div class="cc-section-head"><h2>Specifications</h2></div><div class="cc-specs-list">';
	foreach ( $rows as $k => $v ) {
		echo '<div class="cc-spec-row"><span class="cc-spec-key cc-muted">' . esc_html( $k ) . '</span><span class="cc-spec-val">' . esc_html( $v ) . '</span></div>';
	}
	echo '</div></section>';
}
// The product attributes already appear in the WooCommerce "Additional
// information" tab, so the custom specs block is intentionally not hooked here
// (it would duplicate that tab). The function remains available if needed.

/**
 * Replace the default (often empty) related-products output with a
 * "You may also like" section that falls back to recent products when a product
 * has no category siblings. Uses the shared product card for consistency.
 */
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
function cellcraze_related_products() {
	global $product, $post;
	if ( ! $product ) {
		return;
	}
	$current = $product->get_id();
	$ids     = wc_get_related_products( $current, 4 );
	if ( empty( $ids ) ) {
		$recent = wc_get_products( array(
			'status'  => 'publish',
			'limit'   => 4,
			'orderby' => 'date',
			'order'   => 'DESC',
			'exclude' => array( $current ),
		) );
		$ids = array_map( function ( $p ) { return $p->get_id(); }, $recent );
	}
	$ids = array_slice( array_diff( $ids, array( $current ) ), 0, 4 );
	if ( empty( $ids ) ) {
		return;
	}
	echo '<section class="cc-related cc-product-grid">';
	echo '<div class="cc-section-head"><h2>' . esc_html__( 'You may also like', 'cellcraze' ) . '</h2></div>';
	echo '<ul class="products cc-grid cc-grid-4">';
	foreach ( $ids as $rid ) {
		$rp = wc_get_product( $rid );
		if ( ! $rp || ! $rp->is_visible() ) {
			continue;
		}
		$GLOBALS['product'] = $rp; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$post = get_post( $rid ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $post );
		wc_get_template_part( 'content', 'product' );
	}
	wp_reset_postdata();
	echo '</ul></section>';
}
add_action( 'woocommerce_after_single_product_summary', 'cellcraze_related_products', 20 );

/* ============================================================================
 * Checkout (1d) & Order tracking (1g) — step bar + status timeline via hooks.
 * ========================================================================== */

/**
 * Render the 3-step progress bar.
 *
 * @param int $current 1 Cart, 2 Delivery & payment, 3 Confirmation.
 */
function cellcraze_step_bar( $current ) {
	$steps = array( 1 => 'Cart', 2 => 'Delivery &amp; payment', 3 => 'Confirmation' );
	echo '<div class="cc-steps">';
	foreach ( $steps as $n => $label ) {
		$state = ( $n < $current ) ? 'done' : ( ( $n === $current ) ? 'current' : 'upcoming' );
		printf(
			'<div class="cc-step cc-step-%1$s"><span class="cc-step-sq">%2$s</span><span class="cc-step-label">%3$s</span></div>',
			esc_attr( $state ),
			( 'done' === $state ) ? '&check;' : esc_html( sprintf( '%02d', $n ) ),
			wp_kses_post( $label )
		);
	}
	echo '</div>';
}
add_action( 'woocommerce_before_cart', function () { cellcraze_step_bar( 1 ); } );
add_action( 'woocommerce_before_checkout_form', function () { cellcraze_step_bar( 2 ); }, 5 );

/**
 * Order status timeline (design 1g) on the order-received and view-order pages.
 *
 * @param WC_Order $order Order.
 */
function cellcraze_order_timeline( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}
	// Linear fulfilment path we surface to the customer.
	$flow = array(
		'pending'    => 'Order placed',
		'processing' => 'Payment confirmed',
		'on-hold'    => 'Processing',
		'completed'  => 'Shipped &amp; completed',
	);
	$order_status = $order->get_status();
	$rank = array( 'pending' => 0, 'on-hold' => 1, 'processing' => 2, 'completed' => 3, 'cancelled' => -1, 'refunded' => -1, 'failed' => -1 );
	$current_rank = isset( $rank[ $order_status ] ) ? $rank[ $order_status ] : 0;

	echo '<section class="cc-track">';
	echo '<div class="cc-track-head"><p class="cc-muted">Order ' . esc_html( $order->get_order_number() ) . ' &middot; Placed ' . esc_html( wc_format_datetime( $order->get_date_created(), 'D j M' ) ) . '</p>';
	echo '<h1 class="cc-track-status">' . esc_html( wc_get_order_status_name( $order_status ) ) . '</h1></div>';

	echo '<ol class="cc-timeline">';
	$i = 0;
	foreach ( $flow as $status => $label ) {
		$srank = $rank[ $status ];
		$state = ( $srank < $current_rank ) ? 'done' : ( ( $srank === $current_rank ) ? 'current' : 'upcoming' );
		echo '<li class="cc-tl cc-tl-' . esc_attr( $state ) . '"><span class="cc-tl-sq"></span><span class="cc-tl-label">' . wp_kses_post( $label ) . '</span></li>';
		$i++;
	}
	echo '</ol></section>';
}
add_action( 'woocommerce_view_order', 'cellcraze_order_timeline', 5 );
add_action( 'woocommerce_thankyou', function ( $order_id ) {
	$order = wc_get_order( $order_id );
	cellcraze_order_timeline( $order );
}, 5 );
