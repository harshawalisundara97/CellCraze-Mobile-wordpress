<?php
/**
 * Bank-slip upload & verification for Direct Bank Transfer (bacs) orders
 * (audit CC-09).
 *
 * Security model:
 * - Slips are stored OUTSIDE the publicly-served media path, in
 *   uploads/cellcraze-private/ with a randomized filename. The path lives in
 *   order meta, never exposed to customers.
 * - A .htaccess deny is written for Apache (defense in depth) BUT the real
 *   protection is the gated download endpoint, which independently enforces
 *   capability/ownership + nonce on every request (works on Nginx too, where
 *   .htaccess is ignored).
 * - Uploads validate nonce, order ownership, size, extension and real MIME.
 * - Verification is capability-gated and idempotent.
 *
 * @package CellCraze_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cellcraze_BankSlip
 */
class Cellcraze_BankSlip {

	const META_PATH        = '_cellcraze_bank_slip';          // relative path under uploads.
	const META_UPLOADED    = '_cellcraze_bank_slip_uploaded'; // mysql datetime.
	const META_VERIFIED_BY = '_cellcraze_slip_verified_by';   // user id.
	const META_VERIFIED_AT = '_cellcraze_slip_verified_at';   // mysql datetime.
	const SUBDIR           = 'cellcraze-private';
	const MAX_BYTES        = 5242880; // 5 MB.

	/**
	 * Allowed upload types: extension => MIME.
	 *
	 * @return array
	 */
	private static function allowed_types() {
		return array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'pdf'  => 'application/pdf',
		);
	}

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'admin_post_cellcraze_upload_slip', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_nopriv_cellcraze_upload_slip', array( $this, 'handle_upload' ) ); // guest (order-key auth).
		add_action( 'admin_post_cellcraze_view_slip', array( $this, 'handle_view' ) );
		// Guests (order-key auth) must be able to view their own slip, and
		// unauthorized requests should get the handler's clean 403 rather than
		// WordPress's generic 400. Authorization is enforced inside handle_view
		// (nonce + order ownership or edit_shop_orders capability).
		add_action( 'admin_post_nopriv_cellcraze_view_slip', array( $this, 'handle_view' ) );
		add_action( 'admin_post_cellcraze_verify_slip', array( $this, 'handle_verify' ) );

		// Customer-facing upload form.
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_customer_form' ), 20 );

		// Admin order metabox.
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ), 30 );
	}

	/**
	 * Absolute path to the private directory, created + hardened on demand.
	 *
	 * @return string
	 */
	public static function private_dir() {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . self::SUBDIR;
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		// Defense in depth for Apache; the endpoint is the real gate (Nginx-safe).
		$ht = $dir . '/.htaccess';
		if ( ! file_exists( $ht ) ) {
			@file_put_contents( $ht, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
		}
		$idx = $dir . '/index.html';
		if ( ! file_exists( $idx ) ) {
			@file_put_contents( $idx, '' );
		}
		return $dir;
	}

	/**
	 * Is this a bank-transfer order still awaiting payment?
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	private function is_bacs_awaiting( $order ) {
		return $order
			&& 'bacs' === $order->get_payment_method()
			&& in_array( $order->get_status(), array( 'pending', 'on-hold' ), true );
	}

	/**
	 * Can the current request act on this order as its owner?
	 * Logged-in: user id matches. Guest: order key matches the request.
	 *
	 * @param WC_Order $order    Order.
	 * @param string   $provided Order key from the request (for guests).
	 * @return bool
	 */
	private function owns_order( $order, $provided = '' ) {
		if ( ! $order ) {
			return false;
		}
		$uid = get_current_user_id();
		if ( $uid && (int) $order->get_customer_id() === $uid ) {
			return true;
		}
		// Guest fallback: constant-time compare of the order key.
		if ( $provided && hash_equals( (string) $order->get_order_key(), (string) $provided ) ) {
			return true;
		}
		return false;
	}

	/* ------------------------------------------------------------------ */
	/* Customer upload                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Render the upload form on the customer order view.
	 *
	 * @param WC_Order $order Order.
	 */
	public function render_customer_form( $order ) {
		if ( ! $this->is_bacs_awaiting( $order ) ) {
			return;
		}
		if ( ! $this->owns_order( $order, $order->get_order_key() ) && ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		$existing = $order->get_meta( self::META_PATH );
		?>
		<section class="cc-bankslip">
			<h2><?php esc_html_e( 'Bank transfer payment slip', 'cellcraze-core' ); ?></h2>
			<?php if ( $existing ) : ?>
				<p class="cc-muted">
					<?php esc_html_e( 'A payment slip has been uploaded and is awaiting verification.', 'cellcraze-core' ); ?>
					<a href="<?php echo esc_url( $this->view_url( $order ) ); ?>"><?php esc_html_e( 'View your slip', 'cellcraze-core' ); ?></a>
				</p>
			<?php endif; ?>
			<p class="cc-muted"><?php esc_html_e( 'Upload your bank deposit/transfer receipt (JPG, PNG or PDF, max 5 MB). Use your order number as the reference.', 'cellcraze-core' ); ?></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cellcraze_upload_slip_' . $order->get_id(), 'cellcraze_slip_nonce' ); ?>
				<input type="hidden" name="action" value="cellcraze_upload_slip" />
				<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
				<input type="hidden" name="order_key" value="<?php echo esc_attr( $order->get_order_key() ); ?>" />
				<input type="file" name="bank_slip" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" required />
				<button type="submit" class="button"><?php echo $existing ? esc_html__( 'Replace slip', 'cellcraze-core' ) : esc_html__( 'Upload slip', 'cellcraze-core' ); ?></button>
			</form>
		</section>
		<?php
	}

	/**
	 * Handle a slip upload (customer or staff).
	 */
	public function handle_upload() {
		$order_id  = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order_key = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';

		if ( ! isset( $_POST['cellcraze_slip_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['cellcraze_slip_nonce'] ), 'cellcraze_upload_slip_' . $order_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'cellcraze-core' ), '', array( 'response' => 403 ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'cellcraze-core' ), '', array( 'response' => 404 ) );
		}
		if ( ! $this->owns_order( $order, $order_key ) && ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to upload to this order.', 'cellcraze-core' ), '', array( 'response' => 403 ) );
		}
		if ( ! $this->is_bacs_awaiting( $order ) ) {
			wp_die( esc_html__( 'This order no longer accepts a payment slip.', 'cellcraze-core' ), '', array( 'response' => 400 ) );
		}
		if ( empty( $_FILES['bank_slip']['name'] ) || ! empty( $_FILES['bank_slip']['error'] ) ) {
			$this->redirect_order( $order, array( 'cc_slip' => 'error' ) );
		}

		$file = $_FILES['bank_slip']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below.

		// Size.
		if ( (int) $file['size'] > self::MAX_BYTES || (int) $file['size'] <= 0 ) {
			$this->redirect_order( $order, array( 'cc_slip' => 'size' ) );
		}

		// Extension + declared type.
		$check = wp_check_filetype_and_ext( $file['tmp_name'], sanitize_file_name( $file['name'] ) );
		$ext   = $check['ext'] ? strtolower( $check['ext'] ) : '';
		$allowed = self::allowed_types();
		if ( ! $ext || ! isset( $allowed[ $ext ] ) ) {
			$this->redirect_order( $order, array( 'cc_slip' => 'type' ) );
		}

		// Real MIME from file contents (not the browser-sent type).
		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$real  = finfo_file( $finfo, $file['tmp_name'] );
			finfo_close( $finfo );
			if ( $real !== $allowed[ $ext ] && ! ( 'image/jpeg' === $allowed[ $ext ] && 'image/jpeg' === $real ) ) {
				$this->redirect_order( $order, array( 'cc_slip' => 'type' ) );
			}
		}

		// Store privately with a randomized, non-guessable filename.
		$dir      = self::private_dir();
		$filename = 'slip-' . $order_id . '-' . wp_generate_password( 16, false ) . '.' . $ext;
		$dest     = trailingslashit( $dir ) . $filename;

		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
			$this->redirect_order( $order, array( 'cc_slip' => 'error' ) );
		}
		@chmod( $dest, 0640 );

		// Remove any previous slip for this order.
		$prev = $order->get_meta( self::META_PATH );
		if ( $prev ) {
			$prev_abs = trailingslashit( wp_upload_dir()['basedir'] ) . ltrim( $prev, '/\\' );
			if ( is_file( $prev_abs ) && strpos( realpath( $prev_abs ), realpath( $dir ) ) === 0 ) {
				@unlink( $prev_abs );
			}
		}

		// Store a path RELATIVE to uploads basedir (never a public URL).
		$relative = self::SUBDIR . '/' . $filename;
		$order->update_meta_data( self::META_PATH, $relative );
		$order->update_meta_data( self::META_UPLOADED, current_time( 'mysql' ) );
		$order->save();
		$order->add_order_note( __( 'Customer uploaded a bank transfer payment slip (awaiting verification).', 'cellcraze-core' ), false );

		$this->redirect_order( $order, array( 'cc_slip' => 'ok' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Gated download                                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * Build a nonce'd view URL for an order's slip.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private function view_url( $order ) {
		return add_query_arg(
			array(
				'action'    => 'cellcraze_view_slip',
				'order_id'  => $order->get_id(),
				'order_key' => $order->get_order_key(),
				'_wpnonce'  => wp_create_nonce( 'cellcraze_view_slip_' . $order->get_id() ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Stream the slip to an authorized requester only.
	 */
	public function handle_view() {
		$order_id  = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$order_key = isset( $_GET['order_key'] ) ? sanitize_text_field( wp_unslash( $_GET['order_key'] ) ) : '';

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'cellcraze_view_slip_' . $order_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'cellcraze-core' ), '', array( 'response' => 403 ) );
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'cellcraze-core' ), '', array( 'response' => 404 ) );
		}
		// Independent authorization: staff OR the order's owner. Never public.
		if ( ! current_user_can( 'edit_shop_orders' ) && ! $this->owns_order( $order, $order_key ) ) {
			wp_die( esc_html__( 'You are not allowed to view this file.', 'cellcraze-core' ), '', array( 'response' => 403 ) );
		}

		$relative = $order->get_meta( self::META_PATH );
		if ( ! $relative ) {
			wp_die( esc_html__( 'No slip on file.', 'cellcraze-core' ), '', array( 'response' => 404 ) );
		}
		$basedir = wp_upload_dir()['basedir'];
		$abs     = trailingslashit( $basedir ) . ltrim( $relative, '/\\' );
		$real    = realpath( $abs );
		// Confine strictly to the private dir — no traversal outside it.
		if ( ! $real || strpos( $real, realpath( self::private_dir() ) ) !== 0 || ! is_file( $real ) ) {
			wp_die( esc_html__( 'File not found.', 'cellcraze-core' ), '', array( 'response' => 404 ) );
		}

		$ext   = strtolower( pathinfo( $real, PATHINFO_EXTENSION ) );
		$types = self::allowed_types();
		$mime  = isset( $types[ $ext ] ) ? $types[ $ext ] : 'application/octet-stream';

		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: inline; filename="payment-slip.' . $ext . '"' );
		header( 'Content-Length: ' . filesize( $real ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Admin verification                                                  */
	/* ------------------------------------------------------------------ */

	/**
	 * Register the order metabox (legacy + HPOS screens).
	 */
	public function add_metabox() {
		$screens = array( 'shop_order', 'woocommerce_page_wc-orders' );
		foreach ( $screens as $screen ) {
			add_meta_box(
				'cellcraze_bankslip',
				__( 'Bank transfer slip', 'cellcraze-core' ),
				array( $this, 'render_metabox' ),
				$screen,
				'side',
				'default'
			);
		}
	}

	/**
	 * Metabox content: show slip link + verify action.
	 *
	 * @param WP_Post|WC_Order $post_or_order Screen object.
	 */
	public function render_metabox( $post_or_order ) {
		$order = ( $post_or_order instanceof WC_Order ) ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		echo '<div class="cc-admin">';
		if ( 'bacs' !== $order->get_payment_method() ) {
			echo '<p>' . esc_html__( 'Not a bank-transfer order.', 'cellcraze-core' ) . '</p></div>';
			return;
		}
		$slip        = $order->get_meta( self::META_PATH );
		$verified_by = (int) $order->get_meta( self::META_VERIFIED_BY );
		$verified_at = $order->get_meta( self::META_VERIFIED_AT );

		// Status chip (shape + text, not colour alone).
		if ( $verified_by ) {
			echo '<p><span class="cc-chip is-ok">' . esc_html__( 'Payment verified', 'cellcraze-core' ) . '</span></p>';
		} elseif ( $slip ) {
			echo '<p><span class="cc-chip is-warn">' . esc_html__( 'Slip uploaded — awaiting verification', 'cellcraze-core' ) . '</span></p>';
		} else {
			echo '<p><span class="cc-chip">' . esc_html__( 'Awaiting bank transfer', 'cellcraze-core' ) . '</span></p>';
		}

		if ( $slip ) {
			echo '<p><a class="button" target="_blank" href="' . esc_url( $this->view_url( $order ) ) . '">' . esc_html__( 'View uploaded slip', 'cellcraze-core' ) . '</a></p>';
		} else {
			echo '<p>' . esc_html__( 'No slip uploaded yet.', 'cellcraze-core' ) . '</p>';
		}

		if ( $verified_by ) {
			$user = get_userdata( $verified_by );
			echo '<p>' . esc_html( sprintf( /* translators: 1: user 2: datetime */ __( 'Verified by %1$s on %2$s.', 'cellcraze-core' ), $user ? $user->display_name : ( '#' . $verified_by ), $verified_at ) ) . '</p></div>';
			return;
		}

		if ( in_array( $order->get_status(), array( 'pending', 'on-hold' ), true ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'cellcraze_verify_slip_' . $order->get_id(), 'cellcraze_verify_nonce' );
			echo '<input type="hidden" name="action" value="cellcraze_verify_slip" />';
			echo '<input type="hidden" name="order_id" value="' . esc_attr( $order->get_id() ) . '" />';
			echo '<button type="submit" class="button button-primary" onclick="return confirm(\'' . esc_js( __( 'Confirm the bank transfer has been received and mark this order paid?', 'cellcraze-core' ) ) . '\');">' . esc_html__( 'Mark bank transfer verified', 'cellcraze-core' ) . '</button>';
			echo '</form>';
		}
		echo '</div>';
	}

	/**
	 * Handle the verify action — capability-gated and idempotent.
	 */
	public function handle_verify() {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cellcraze-core' ), '', array( 'response' => 403 ) );
		}
		if ( ! isset( $_POST['cellcraze_verify_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['cellcraze_verify_nonce'] ), 'cellcraze_verify_slip_' . $order_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'cellcraze-core' ), '', array( 'response' => 403 ) );
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'cellcraze-core' ), '', array( 'response' => 404 ) );
		}

		// Idempotency: if already verified, do nothing (no duplicate side effects).
		if ( $order->get_meta( self::META_VERIFIED_BY ) ) {
			$this->redirect_admin_order( $order_id );
		}
		// Only verify a bacs order still awaiting payment.
		if ( 'bacs' !== $order->get_payment_method() || ! in_array( $order->get_status(), array( 'pending', 'on-hold' ), true ) ) {
			$this->redirect_admin_order( $order_id );
		}

		$uid = get_current_user_id();
		$order->update_meta_data( self::META_VERIFIED_BY, $uid );
		$order->update_meta_data( self::META_VERIFIED_AT, current_time( 'mysql' ) );
		$order->save();

		$user = get_userdata( $uid );
		$order->add_order_note( sprintf( /* translators: %s: user */ __( 'Bank transfer verified by %s.', 'cellcraze-core' ), $user ? $user->display_name : ( '#' . $uid ) ), false );

		// payment_complete() moves on-hold -> processing (fires the existing IMEI
		// assignment hook) and records the paid date. Called once (guarded above).
		$order->payment_complete();

		// Audit (T-102): record only after verification fully succeeds. Keyed on
		// the order so a retried request can't create a duplicate (verification
		// is itself once-only via the META_VERIFIED_BY guard). No secrets logged.
		if ( class_exists( 'Cellcraze_Audit' ) ) {
			Cellcraze_Audit::log(
				array(
					'event_key'   => 'slip_verify:' . (int) $order_id,
					'event_type'  => 'slip_verify',
					'object_type' => 'order',
					'object_id'   => (int) $order_id,
					'reason'      => 'bank_transfer_verified',
					'reference'   => $order->get_order_number(),
					'actor_id'    => $uid,
				)
			);
		}

		$this->redirect_admin_order( $order_id );
	}

	/* ------------------------------------------------------------------ */
	/* Redirect helpers                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * Back to the customer order view.
	 *
	 * @param WC_Order $order Order.
	 * @param array    $args  Query args.
	 */
	private function redirect_order( $order, $args = array() ) {
		$url = $order->get_view_order_url();
		wp_safe_redirect( add_query_arg( $args, $url ) );
		exit;
	}

	/**
	 * Back to the admin order edit screen (HPOS-aware).
	 *
	 * @param int $order_id Order id.
	 */
	private function redirect_admin_order( $order_id ) {
		if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$url = admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );
		} else {
			$url = admin_url( 'post.php?post=' . $order_id . '&action=edit' );
		}
		wp_safe_redirect( add_query_arg( array( 'cc_verified' => 1 ), $url ) );
		exit;
	}
}
