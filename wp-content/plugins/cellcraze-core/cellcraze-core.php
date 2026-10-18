<?php
/**
 * Plugin Name:       CellCraze Core
 * Plugin URI:        https://cellcraze.lk
 * Description:       Custom back-office glue for CellCraze: IMEI/serial tracking, order IMEI assignment, and sales-channel (online vs POS) tagging on top of WooCommerce.
 * Version:           1.9.0
 * Author:            CellCraze
 * Text Domain:       cellcraze-core
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 8.0
 * WC tested up to:   9.4
 *
 * @package CellCraze_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CELLCRAZE_CORE_VERSION', '1.9.0' );
define( 'CELLCRAZE_CORE_FILE', __FILE__ );
define( 'CELLCRAZE_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'CELLCRAZE_CORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load plugin classes.
 */
require_once CELLCRAZE_CORE_PATH . 'includes/class-cellcraze-imei-db.php';
require_once CELLCRAZE_CORE_PATH . 'includes/class-cellcraze-imei-admin.php';
require_once CELLCRAZE_CORE_PATH . 'includes/class-cellcraze-imei-orders.php';
require_once CELLCRAZE_CORE_PATH . 'includes/class-cellcraze-channel.php';
require_once CELLCRAZE_CORE_PATH . 'includes/class-cellcraze-rest.php';
require_once CELLCRAZE_CORE_PATH . 'includes/class-cellcraze-roles.php';
require_once CELLCRAZE_CORE_PATH . 'includes/class-cellcraze-bankslip.php';
require_once CELLCRAZE_CORE_PATH . 'includes/class-cellcraze-audit.php';
require_once CELLCRAZE_CORE_PATH . 'includes/class-cellcraze-expense.php';
require_once CELLCRAZE_CORE_PATH . 'includes/class-cellcraze-cogs.php';
require_once CELLCRAZE_CORE_PATH . 'includes/class-cellcraze-reports.php';
require_once CELLCRAZE_CORE_PATH . 'includes/class-cellcraze-dashboard.php';
require_once CELLCRAZE_CORE_PATH . 'includes/class-cellcraze-suppliers.php';
require_once CELLCRAZE_CORE_PATH . 'includes/class-cellcraze-purchasing.php';

/**
 * Activation: create / upgrade the IMEI table + install staff roles.
 */
function cellcraze_core_activate() {
	Cellcraze_IMEI_DB::install();
	Cellcraze_Audit::install();
	Cellcraze_Expense::install();
	Cellcraze_Suppliers::install();
	Cellcraze_Purchasing::install();
	Cellcraze_Roles::install();
	Cellcraze_BankSlip::private_dir(); // create + harden the private slips dir.
	add_option( 'cellcraze_core_version', CELLCRAZE_CORE_VERSION );
}
register_activation_hook( __FILE__, 'cellcraze_core_activate' );

/**
 * Deactivation: remove the custom roles (never deletes users or data).
 */
function cellcraze_core_deactivate() {
	Cellcraze_Roles::remove();
}
register_deactivation_hook( __FILE__, 'cellcraze_core_deactivate' );

/**
 * Boot the plugin once all other plugins (WooCommerce) are loaded.
 */
function cellcraze_core_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				echo esc_html__( 'CellCraze Core requires WooCommerce to be active.', 'cellcraze-core' );
				echo '</p></div>';
			}
		);
		return;
	}

	// Run a lightweight DB-version check in case the plugin files were updated
	// without re-activation (e.g. deployed over FTP).
	if ( get_option( 'cellcraze_core_version' ) !== CELLCRAZE_CORE_VERSION ) {
		Cellcraze_IMEI_DB::install();
		Cellcraze_Audit::install();
		Cellcraze_Expense::install();
		Cellcraze_Suppliers::install();
		Cellcraze_Purchasing::install();
		Cellcraze_Roles::install();
		update_option( 'cellcraze_core_version', CELLCRAZE_CORE_VERSION );
	}

	new Cellcraze_IMEI_Admin();
	new Cellcraze_IMEI_Orders();
	new Cellcraze_Channel();
	new Cellcraze_REST();
	new Cellcraze_BankSlip();
	new Cellcraze_Audit();
	new Cellcraze_Expense();
	new Cellcraze_COGS();
	new Cellcraze_Reports();
	new Cellcraze_Dashboard();
	new Cellcraze_Suppliers();
	new Cellcraze_Purchasing();
}
add_action( 'plugins_loaded', 'cellcraze_core_init' );

/**
 * Enqueue the admin design-system stylesheet ONLY on CellCraze admin pages.
 * Keeps customer-facing (storefront) and admin styles fully separate.
 */
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		$load = ( false !== strpos( (string) $hook, 'cellcraze-' ) );
		if ( ! $load && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			// Narrow: product edit (IMEI tab) + order edit (bank-slip metabox),
			// legacy and HPOS. Never load across all of wp-admin.
			if ( $screen && in_array( $screen->id, array( 'product', 'shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
				$load = true;
			}
		}
		if ( ! $load ) {
			return;
		}
		wp_enqueue_style(
			'cellcraze-admin',
			CELLCRAZE_CORE_URL . 'assets/css/cc-admin.css',
			array(),
			CELLCRAZE_CORE_VERSION
		);
	}
);

/**
 * Is the current request a CellCraze admin screen (one of our submenu pages)?
 *
 * @return bool
 */
function cellcraze_is_admin_screen() {
	if ( ! is_admin() ) {
		return false;
	}
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 0 === strpos( $page, 'cellcraze-' ) ) {
		return true;
	}
	if ( function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();
		if ( $screen && false !== strpos( (string) $screen->id, 'cellcraze-' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Resolve the source file of a hook callback (plain function, "Class::method"
 * string, [object|class, method] array, or Closure). Returns '' when it cannot
 * be determined — in which case the caller preserves the notice.
 *
 * @param mixed $callback Hook callback.
 * @return string Absolute file path, or ''.
 */
function cellcraze_callback_source_file( $callback ) {
	try {
		if ( $callback instanceof Closure ) {
			$ref = new ReflectionFunction( $callback );
		} elseif ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
			list( $class, $method ) = explode( '::', $callback, 2 );
			$ref = new ReflectionMethod( $class, $method );
		} elseif ( is_array( $callback ) && isset( $callback[0], $callback[1] ) ) {
			$target = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
			$ref    = new ReflectionMethod( $target, (string) $callback[1] );
		} elseif ( is_string( $callback ) && function_exists( $callback ) ) {
			$ref = new ReflectionFunction( $callback );
		} else {
			return '';
		}
		return (string) $ref->getFileName();
	} catch ( \Throwable $e ) {
		return '';
	}
}

/**
 * Remove, on the given hook, only the callbacks that physically originate from
 * a specific set of plugin folders. Everything else is preserved — WordPress
 * core, WooCommerce core, security plugins, any other plugin, and any callback
 * whose source file cannot be resolved.
 *
 * @param string   $hook         'admin_notices' | 'all_admin_notices'.
 * @param string[] $plugin_slugs Plugin folder names whose notices to suppress.
 */
function cellcraze_suppress_plugin_notices( $hook, array $plugin_slugs ) {
	global $wp_filter;
	if ( empty( $wp_filter[ $hook ] ) || ! isset( $wp_filter[ $hook ]->callbacks ) ) {
		return;
	}
	$remove = array();
	foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $cb ) {
			if ( ! isset( $cb['function'] ) ) {
				continue;
			}
			$file = cellcraze_callback_source_file( $cb['function'] );
			if ( '' === $file ) {
				continue; // Unknown source → preserve.
			}
			$file = wp_normalize_path( $file );
			foreach ( $plugin_slugs as $slug ) {
				if ( false !== strpos( $file, '/plugins/' . $slug . '/' ) ) {
					$remove[] = array( $priority, $cb['function'] );
					break;
				}
			}
		}
	}
	// Remove after collecting, so we never mutate the arrays we are iterating.
	foreach ( $remove as $r ) {
		remove_action( $hook, $r[1], $r[0] );
	}
}

/**
 * Declutter CellCraze workflow screens by suppressing the promotional / setup
 * notices pushed by specific noisy third-party plugins — ONLY on our own pages.
 *
 * Deliberately targeted (not a blanket remove_all_actions): WordPress core,
 * WooCommerce core, security plugins, and every other source keep their
 * notices, as do CellCraze's own inline messages (rendered inside each page's
 * render(), not via these hooks) and PHP fatal/parse errors (printed directly).
 * Only notices whose callback physically lives in the listed plugin folders are
 * removed, and only on the seven CellCraze pages.
 */
add_action(
	'in_admin_header',
	function () {
		if ( ! cellcraze_is_admin_screen() ) {
			return;
		}
		$noisy = array(
			'woocommerce-pdf-invoices-packing-slips',
			'ti-woocommerce-wishlist',
		);
		cellcraze_suppress_plugin_notices( 'admin_notices', $noisy );
		cellcraze_suppress_plugin_notices( 'all_admin_notices', $noisy );
	},
	0
);

/**
 * Responsive safety net: wrap any CellCraze admin data table that is not
 * already inside a `.cc-table-wrap` so wide tables scroll within their own
 * container instead of forcing the whole admin page wide on narrow screens.
 *
 * Presentation-only, scoped to CellCraze screens, and progressive: with JS off
 * the tables render exactly as before. No markup/logic in the render methods is
 * changed, and tables already wrapped (e.g. the product IMEI tab) are skipped.
 */
add_action(
	'admin_print_footer_scripts',
	function () {
		if ( ! cellcraze_is_admin_screen() ) {
			return;
		}
		?>
		<script>
		( function () {
			var tables = document.querySelectorAll( '.cc-admin table.widefat' );
			for ( var i = 0; i < tables.length; i++ ) {
				var t = tables[ i ];
				if ( t.closest && t.closest( '.cc-table-wrap' ) ) { continue; }
				var wrap = document.createElement( 'div' );
				wrap.className = 'cc-table-wrap';
				t.parentNode.insertBefore( wrap, t );
				wrap.appendChild( t );
			}
		} )();
		</script>
		<?php
	}
);

/**
 * Declare HPOS (custom order tables) compatibility.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
