<?php
/**
 * CellCraze — bulk product-image importer (local / dev).
 *
 * Matches licensed image files to WooCommerce products by EXACT SKU and sets
 * each as the product's featured image.
 *
 * SAFETY (by design):
 *   - Additive only. Never changes price, stock, description, orders, IMEI,
 *     suppliers, purchasing, accounting, or any business data.
 *   - Never deletes existing media or product images.
 *   - Idempotent: re-running does NOT create duplicate attachments (matched by
 *     SKU + file hash). Does not consume/move your source files.
 *   - Skips products that already have a featured image unless --replace.
 *   - Validates each file exists and is a supported image before importing.
 *   - --dry-run shows exactly what would happen and changes nothing.
 *
 * Supported extensions: jpg, jpeg, png, webp (case-insensitive).
 *
 * Usage (PHP CLI):
 *   C:/xampp/php/php.exe tools/import-product-images.php --dry-run
 *   C:/xampp/php/php.exe tools/import-product-images.php
 *   C:/xampp/php/php.exe tools/import-product-images.php --replace
 *   C:/xampp/php/php.exe tools/import-product-images.php --dir="C:/images" --path="C:/xampp/htdocs/cellcraze"
 *
 * @package CellCraze
 */

if ( 'cli' !== php_sapi_name() ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

$opts = getopt( '', array( 'dir::', 'path::', 'replace', 'dry-run', 'help' ) );

if ( isset( $opts['help'] ) ) {
	echo "CellCraze product-image importer\n\n";
	echo "  --dry-run      Show what would happen; change nothing.\n";
	echo "  --replace      Replace featured image even if one is already set.\n";
	echo "  --dir=PATH     Folder of <SKU>.{jpg,jpeg,png,webp} files (default: ../product-images).\n";
	echo "  --path=PATH    WordPress root containing wp-load.php (default: C:/xampp/htdocs/cellcraze).\n";
	echo "  --help         This help.\n";
	exit( 0 );
}

$dry     = isset( $opts['dry-run'] );
$replace = isset( $opts['replace'] );

$normalize = static function ( $p ) {
	return rtrim( str_replace( '\\', '/', (string) $p ), '/' );
};

$script_dir = $normalize( __DIR__ );
$img_dir    = ( isset( $opts['dir'] ) && false !== $opts['dir'] ) ? $normalize( $opts['dir'] ) : $script_dir . '/../product-images';
$wp_root    = ( isset( $opts['path'] ) && false !== $opts['path'] ) ? $normalize( $opts['path'] ) : 'C:/xampp/htdocs/cellcraze';
$img_dir    = $normalize( $img_dir );

$wp_load = $wp_root . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	fwrite( STDERR, "wp-load.php not found at {$wp_load}\nPass --path=\"<WordPress root>\".\n" );
	exit( 1 );
}

if ( ! is_dir( $img_dir ) ) {
	fwrite( STDERR, "Images directory not found: {$img_dir}\nPass --dir=\"<folder>\".\n" );
	exit( 1 );
}

define( 'WP_USE_THEMES', false );
require $wp_load;

if ( ! function_exists( 'wc_get_products' ) ) {
	fwrite( STDERR, "WooCommerce is not active on this site.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$allowed_ext  = array( 'jpg', 'jpeg', 'png', 'webp' );
$allowed_mime = array( 'image/jpeg', 'image/png', 'image/webp' );

echo "CellCraze product-image import" . ( $dry ? ' [DRY-RUN]' : '' ) . ( $replace ? ' [REPLACE]' : '' ) . "\n";
echo "  Images: {$img_dir}\n";
echo "  WP root: {$wp_root}\n\n";

$products = wc_get_products( array( 'limit' => -1, 'status' => 'publish', 'orderby' => 'id', 'order' => 'ASC' ) );

$set = 0; $reused = 0; $skipped_existing = 0; $no_file = 0; $errors = 0; $would = 0;

foreach ( $products as $p ) {
	$pid  = $p->get_id();
	$sku  = $p->get_sku();
	$name = $p->get_name();

	if ( ! $sku ) {
		echo "·  [{$pid}] \"{$name}\": no SKU — skipped\n";
		$no_file++;
		continue;
	}

	// Find <SKU>.<ext> (case-insensitive extension).
	$file = null;
	$ext  = null;
	foreach ( $allowed_ext as $e ) {
		foreach ( array( $e, strtoupper( $e ) ) as $variant ) {
			$cand = "{$img_dir}/{$sku}.{$variant}";
			if ( is_file( $cand ) ) {
				$file = $cand;
				$ext  = $e;
				break 2;
			}
		}
	}
	if ( ! $file ) {
		echo "·  [{$pid}] {$sku} \"{$name}\": no image file — skipped\n";
		$no_file++;
		continue;
	}

	// Validate it is a readable, supported image.
	if ( ! is_readable( $file ) ) {
		echo "!  [{$pid}] {$sku}: file not readable — error\n";
		$errors++;
		continue;
	}
	$mime = null;
	if ( function_exists( 'finfo_open' ) ) {
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime  = $finfo ? finfo_file( $finfo, $file ) : null;
	}
	if ( null === $mime ) {
		$info = @getimagesize( $file );
		$mime = ( false !== $info && ! empty( $info['mime'] ) ) ? $info['mime'] : null;
	}
	if ( ! in_array( $mime, $allowed_mime, true ) ) {
		echo "!  [{$pid}] {$sku}: unsupported/invalid image (" . ( $mime ? $mime : 'unknown' ) . ") — error\n";
		$errors++;
		continue;
	}

	// Gate: already has a featured image?
	$has = $p->get_image_id();
	if ( $has && ! $replace ) {
		echo "=  [{$pid}] {$sku} \"{$name}\": already has image (#{$has}) — skipped (use --replace)\n";
		$skipped_existing++;
		continue;
	}

	// Idempotency: reuse an attachment already imported for this SKU + file.
	$md5 = md5_file( $file );
	$found = get_posts(
		array(
			'post_type'   => 'attachment',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_query'  => array(
				'relation' => 'AND',
				array( 'key' => '_cellcraze_import_sku', 'value' => $sku ),
				array( 'key' => '_cellcraze_import_md5', 'value' => $md5 ),
			),
		)
	);
	$att = $found ? (int) $found[0] : 0;

	if ( $dry ) {
		if ( $att ) {
			echo "~  [{$pid}] {$sku} \"{$name}\": WOULD set featured to existing attachment #{$att} (no re-upload)\n";
		} else {
			echo "+  [{$pid}] {$sku} \"{$name}\": WOULD import {$sku}.{$ext} and set as featured\n";
		}
		$would++;
		continue;
	}

	// Import a new attachment only if we have no reusable one.
	if ( ! $att ) {
		$tmp = wp_tempnam( $sku . '.' . $ext );
		if ( ! $tmp || ! @copy( $file, $tmp ) ) {
			@unlink( $tmp );
			echo "!  [{$pid}] {$sku}: could not stage temp copy — error\n";
			$errors++;
			continue;
		}
		$file_array = array( 'name' => $sku . '.' . $ext, 'tmp_name' => $tmp );
		$att        = media_handle_sideload( $file_array, $pid, $name );
		if ( is_wp_error( $att ) ) {
			@unlink( $tmp );
			echo '!  [' . $pid . '] ' . $sku . ': import failed: ' . $att->get_error_message() . " — error\n";
			$errors++;
			continue;
		}
		update_post_meta( $att, '_cellcraze_import_sku', $sku );
		update_post_meta( $att, '_cellcraze_import_md5', $md5 );
		update_post_meta( $att, '_wp_attachment_image_alt', $name );
	} else {
		$reused++;
	}

	set_post_thumbnail( $pid, $att );
	echo "+  [{$pid}] {$sku} \"{$name}\": featured image set (attachment #{$att})" . ( $found ? ' [reused]' : '' ) . "\n";
	$set++;
}

echo "\nSummary" . ( $dry ? ' [DRY-RUN]' : '' ) . ":\n";
if ( $dry ) {
	echo "  would change: {$would}\n";
}
echo "  set: {$set}  reused: {$reused}  skipped (already has image): {$skipped_existing}\n";
echo "  no image file: {$no_file}  errors: {$errors}\n";
