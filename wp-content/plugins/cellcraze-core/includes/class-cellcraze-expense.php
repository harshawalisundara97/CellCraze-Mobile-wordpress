<?php
/**
 * Expense management module (task T-103).
 *
 * Records operating expenses only (no profit/COGS, no auto-import of supplier
 * purchases). Receipts are stored privately (reusing the CC-09 private dir) and
 * served through a capability-gated endpoint. All mutations are capability- +
 * nonce-checked, validated server-side, and logged via the T-101 audit ledger.
 * Deletion is soft (status=void) so financial history is retained.
 *
 * @package CellCraze_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cellcraze_Expense
 */
class Cellcraze_Expense {

	const CAP         = 'cellcraze_manage_expenses';
	const STATUS_ACTIVE = 'active';
	const STATUS_VOID   = 'void';
	const MAX_BYTES     = 5242880; // 5 MB.
	const MAX_AMOUNT    = 1000000000; // sanity ceiling.

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'cellcraze_expense';
	}

	/**
	 * Allowed expense categories (slug => label).
	 *
	 * @return array
	 */
	public static function categories() {
		return array(
			'rent'         => __( 'Rent', 'cellcraze-core' ),
			'utilities'    => __( 'Utilities', 'cellcraze-core' ),
			'salaries'     => __( 'Salaries', 'cellcraze-core' ),
			'marketing'    => __( 'Marketing', 'cellcraze-core' ),
			'courier'      => __( 'Courier / Delivery', 'cellcraze-core' ),
			'supplies'     => __( 'Supplies', 'cellcraze-core' ),
			'maintenance'  => __( 'Maintenance / Repairs', 'cellcraze-core' ),
			'bank_fees'    => __( 'Bank Fees', 'cellcraze-core' ),
			'gateway_fees' => __( 'Payment Gateway Fees', 'cellcraze-core' ),
			'other'        => __( 'Other', 'cellcraze-core' ),
		);
	}

	/**
	 * Allowed payment methods.
	 *
	 * @return array
	 */
	public static function methods() {
		return array(
			'cash'          => __( 'Cash', 'cellcraze-core' ),
			'bank_transfer' => __( 'Bank Transfer', 'cellcraze-core' ),
			'card'          => __( 'Card', 'cellcraze-core' ),
			'cheque'        => __( 'Cheque', 'cellcraze-core' ),
			'other'         => __( 'Other', 'cellcraze-core' ),
		);
	}

	/**
	 * Allowed receipt types: ext => MIME.
	 *
	 * @return array
	 */
	private static function allowed_types() {
		return array( 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf' );
	}

	/**
	 * Create/upgrade the table.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			expense_date DATE NOT NULL,
			category VARCHAR(50) NOT NULL DEFAULT 'other',
			amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			payment_method VARCHAR(30) NOT NULL DEFAULT 'cash',
			reference VARCHAR(100) NOT NULL DEFAULT '',
			payee VARCHAR(191) NOT NULL DEFAULT '',
			description VARCHAR(500) NOT NULL DEFAULT '',
			receipt_path VARCHAR(255) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY expense_date (expense_date),
			KEY category (category),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql );
	}

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_post_cellcraze_save_expense', array( $this, 'handle_save' ) );
		add_action( 'admin_post_cellcraze_void_expense', array( $this, 'handle_void' ) );
		add_action( 'admin_post_cellcraze_view_receipt', array( $this, 'handle_view_receipt' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Validation + persistence                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Validate raw input into a clean data array, or return WP_Error.
	 *
	 * @param array $raw Raw $_POST-ish input.
	 * @return array|WP_Error
	 */
	public static function validate( $raw ) {
		$cats    = self::categories();
		$methods = self::methods();

		$date = isset( $raw['expense_date'] ) ? trim( (string) $raw['expense_date'] ) : '';
		$d    = DateTime::createFromFormat( 'Y-m-d', $date );
		if ( ! $d || $d->format( 'Y-m-d' ) !== $date ) {
			return new WP_Error( 'cc_exp_date', __( 'A valid date (YYYY-MM-DD) is required.', 'cellcraze-core' ) );
		}

		$category = isset( $raw['category'] ) ? sanitize_key( $raw['category'] ) : '';
		if ( ! isset( $cats[ $category ] ) ) {
			return new WP_Error( 'cc_exp_cat', __( 'Invalid expense category.', 'cellcraze-core' ) );
		}

		$amount_raw = isset( $raw['amount'] ) ? str_replace( ',', '', (string) $raw['amount'] ) : '';
		if ( '' === $amount_raw || ! is_numeric( $amount_raw ) ) {
			return new WP_Error( 'cc_exp_amt', __( 'A numeric amount is required.', 'cellcraze-core' ) );
		}
		$amount = round( (float) $amount_raw, 2 );
		if ( $amount < 0 || $amount > self::MAX_AMOUNT ) {
			return new WP_Error( 'cc_exp_amt_range', __( 'Amount must be between 0 and 1,000,000,000.', 'cellcraze-core' ) );
		}

		$method = isset( $raw['payment_method'] ) ? sanitize_key( $raw['payment_method'] ) : '';
		if ( ! isset( $methods[ $method ] ) ) {
			return new WP_Error( 'cc_exp_method', __( 'Invalid payment method.', 'cellcraze-core' ) );
		}

		return array(
			'expense_date'   => $date,
			'category'       => $category,
			'amount'         => $amount,
			'payment_method' => $method,
			'reference'      => isset( $raw['reference'] ) ? substr( sanitize_text_field( $raw['reference'] ), 0, 100 ) : '',
			'payee'          => isset( $raw['payee'] ) ? substr( sanitize_text_field( $raw['payee'] ), 0, 191 ) : '',
			'description'    => isset( $raw['description'] ) ? substr( sanitize_textarea_field( $raw['description'] ), 0, 500 ) : '',
		);
	}

	/**
	 * Fetch one expense row.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) ); // phpcs:ignore WordPress.DB
	}

	/* ------------------------------------------------------------------ */
	/* Admin UI                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Register the Expenses submenu.
	 */
	public function admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Expenses', 'cellcraze-core' ),
			__( 'Expenses', 'cellcraze-core' ),
			self::CAP,
			'cellcraze-expenses',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the Expenses admin page: filters, list, add/edit form.
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cellcraze-core' ), '', array( 'response' => 403 ) );
		}
		global $wpdb;
		$table = self::table();

		// Filters (read-only query; nonce not required for a GET filter).
		$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$to   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$cat  = isset( $_GET['cat'] ) ? sanitize_key( $_GET['cat'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		$where  = array( '1=1' );
		$params = array();
		if ( $from && DateTime::createFromFormat( 'Y-m-d', $from ) ) { $where[] = 'expense_date >= %s'; $params[] = $from; }
		if ( $to && DateTime::createFromFormat( 'Y-m-d', $to ) ) { $where[] = 'expense_date <= %s'; $params[] = $to; }
		if ( $cat && isset( self::categories()[ $cat ] ) ) { $where[] = 'category = %s'; $params[] = $cat; }
		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY expense_date DESC, id DESC LIMIT 500";
		$rows      = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB

		$editing = null;
		if ( isset( $_GET['edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$editing = self::get( absint( $_GET['edit'] ) );
		}

		$cats    = self::categories();
		$methods = self::methods();
		$notice  = isset( $_GET['cc_exp'] ) ? sanitize_key( $_GET['cc_exp'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap cc-admin">
			<div class="cc-head"><div><h1><?php esc_html_e( 'Expenses', 'cellcraze-core' ); ?></h1><p class="cc-sub"><?php esc_html_e( 'Record and review operating expenses. Voided entries are kept for history.', 'cellcraze-core' ); ?></p></div></div>
			<?php if ( 'saved' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Expense saved.', 'cellcraze-core' ); ?></p></div>
			<?php elseif ( 'voided' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Expense voided (kept for history).', 'cellcraze-core' ); ?></p></div>
			<?php elseif ( $notice && 'saved' !== $notice ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $this->error_message( $notice ) ); ?></p></div>
			<?php endif; ?>

			<h2><?php echo $editing ? esc_html__( 'Edit expense', 'cellcraze-core' ) : esc_html__( 'Add expense', 'cellcraze-core' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="cc-form">
				<?php wp_nonce_field( 'cellcraze_save_expense', 'cc_exp_nonce' ); ?>
				<input type="hidden" name="action" value="cellcraze_save_expense" />
				<?php if ( $editing ) : ?><input type="hidden" name="id" value="<?php echo (int) $editing->id; ?>" /><?php endif; ?>
				<div><label><?php esc_html_e( 'Date', 'cellcraze-core' ); ?></label>
					<input type="date" name="expense_date" required value="<?php echo esc_attr( $editing ? $editing->expense_date : gmdate( 'Y-m-d' ) ); ?>" /></div>
				<div><label><?php esc_html_e( 'Category', 'cellcraze-core' ); ?></label>
					<select name="category"><?php foreach ( $cats as $k => $label ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $editing ? $editing->category : '', $k ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?></select></div>
				<div><label><?php esc_html_e( 'Amount (LKR)', 'cellcraze-core' ); ?></label>
					<input type="number" step="0.01" min="0" name="amount" required value="<?php echo esc_attr( $editing ? $editing->amount : '' ); ?>" /></div>
				<div><label><?php esc_html_e( 'Payment method', 'cellcraze-core' ); ?></label>
					<select name="payment_method"><?php foreach ( $methods as $k => $label ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $editing ? $editing->payment_method : '', $k ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?></select></div>
				<div><label><?php esc_html_e( 'Payee', 'cellcraze-core' ); ?></label>
					<input type="text" name="payee" maxlength="191" value="<?php echo esc_attr( $editing ? $editing->payee : '' ); ?>" /></div>
				<div><label><?php esc_html_e( 'Reference', 'cellcraze-core' ); ?></label>
					<input type="text" name="reference" maxlength="100" value="<?php echo esc_attr( $editing ? $editing->reference : '' ); ?>" /></div>
				<div class="full"><label><?php esc_html_e( 'Description', 'cellcraze-core' ); ?></label>
					<textarea name="description" maxlength="500" rows="2"><?php echo esc_textarea( $editing ? $editing->description : '' ); ?></textarea></div>
				<div class="full"><label><?php esc_html_e( 'Receipt (JPG/PNG/PDF, max 5 MB)', 'cellcraze-core' ); ?></label>
					<input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" />
					<?php if ( $editing && $editing->receipt_path ) : ?>
						<a href="<?php echo esc_url( $this->receipt_url( (int) $editing->id ) ); ?>" target="_blank"><?php esc_html_e( 'View current receipt', 'cellcraze-core' ); ?></a>
					<?php endif; ?></div>
				<div class="full"><button type="submit" class="button button-primary"><?php echo $editing ? esc_html__( 'Update expense', 'cellcraze-core' ) : esc_html__( 'Add expense', 'cellcraze-core' ); ?></button>
					<?php if ( $editing ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cellcraze-expenses' ) ); ?>"><?php esc_html_e( 'Cancel', 'cellcraze-core' ); ?></a><?php endif; ?></div>
			</form>

			<h2><?php esc_html_e( 'Expense records', 'cellcraze-core' ); ?></h2>
			<form method="get" class="cc-filters">
				<input type="hidden" name="page" value="cellcraze-expenses" />
				<div><label><?php esc_html_e( 'From', 'cellcraze-core' ); ?></label><input type="date" name="from" value="<?php echo esc_attr( $from ); ?>" /></div>
				<div><label><?php esc_html_e( 'To', 'cellcraze-core' ); ?></label><input type="date" name="to" value="<?php echo esc_attr( $to ); ?>" /></div>
				<div><label><?php esc_html_e( 'Category', 'cellcraze-core' ); ?></label>
					<select name="cat"><option value=""><?php esc_html_e( 'All', 'cellcraze-core' ); ?></option>
					<?php foreach ( $cats as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $cat, $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></div>
				<div><button class="button"><?php esc_html_e( 'Filter', 'cellcraze-core' ); ?></button></div>
			</form>

			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Date', 'cellcraze-core' ); ?></th>
					<th><?php esc_html_e( 'Category', 'cellcraze-core' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'cellcraze-core' ); ?></th>
					<th><?php esc_html_e( 'Method', 'cellcraze-core' ); ?></th>
					<th><?php esc_html_e( 'Payee', 'cellcraze-core' ); ?></th>
					<th><?php esc_html_e( 'Reference', 'cellcraze-core' ); ?></th>
					<th><?php esc_html_e( 'Receipt', 'cellcraze-core' ); ?></th>
					<th><?php esc_html_e( 'Status', 'cellcraze-core' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="9"><?php esc_html_e( 'No expenses recorded for this filter. Add one above.', 'cellcraze-core' ); ?></td></tr>
				<?php else : foreach ( $rows as $r ) : ?>
					<tr<?php echo ( self::STATUS_VOID === $r->status ) ? ' style="opacity:.55"' : ''; ?>>
						<td><?php echo esc_html( $r->expense_date ); ?></td>
						<td><?php echo esc_html( isset( $cats[ $r->category ] ) ? $cats[ $r->category ] : $r->category ); ?></td>
						<td class="cc-num"><?php echo wp_kses_post( wc_price( $r->amount ) ); ?></td>
						<td><?php echo esc_html( isset( $methods[ $r->payment_method ] ) ? $methods[ $r->payment_method ] : $r->payment_method ); ?></td>
						<td><?php echo esc_html( $r->payee ); ?></td>
						<td><?php echo esc_html( $r->reference ); ?></td>
						<td><?php if ( $r->receipt_path ) : ?><a href="<?php echo esc_url( $this->receipt_url( (int) $r->id ) ); ?>" target="_blank"><?php esc_html_e( 'View', 'cellcraze-core' ); ?></a><?php else : ?>&mdash;<?php endif; ?></td>
						<td><?php
							$is_void = self::STATUS_VOID === $r->status;
							printf( '<span class="cc-chip %s">%s</span>', $is_void ? 'is-void' : 'is-active', esc_html( $is_void ? __( 'Void', 'cellcraze-core' ) : __( 'Active', 'cellcraze-core' ) ) );
						?></td>
						<td>
							<?php if ( self::STATUS_VOID !== $r->status ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=cellcraze-expenses&edit=' . (int) $r->id ) ); ?>"><?php esc_html_e( 'Edit', 'cellcraze-core' ); ?></a> |
								<a href="<?php echo esc_url( $this->void_url( (int) $r->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Void this expense? It stays in history but is excluded from active totals.', 'cellcraze-core' ) ); ?>');"><?php esc_html_e( 'Void', 'cellcraze-core' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Map an error code to a message.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	private function error_message( $code ) {
		$map = array(
			'cc_exp_date'      => __( 'A valid date (YYYY-MM-DD) is required.', 'cellcraze-core' ),
			'cc_exp_cat'       => __( 'Invalid expense category.', 'cellcraze-core' ),
			'cc_exp_amt'       => __( 'A numeric amount is required.', 'cellcraze-core' ),
			'cc_exp_amt_range' => __( 'Amount is out of the allowed range.', 'cellcraze-core' ),
			'cc_exp_method'    => __( 'Invalid payment method.', 'cellcraze-core' ),
			'cc_exp_receipt'   => __( 'Receipt must be a JPG, PNG or PDF under 5 MB, and secure storage must be available.', 'cellcraze-core' ),
			'cc_exp_db'        => __( 'The expense could not be saved (database error). Nothing was recorded.', 'cellcraze-core' ),
			'cc_exp_denied'    => __( 'Permission denied.', 'cellcraze-core' ),
		);
		return isset( $map[ $code ] ) ? $map[ $code ] : __( 'Could not save the expense.', 'cellcraze-core' );
	}

	/* ------------------------------------------------------------------ */
	/* Handlers                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Create or update an expense.
	 */
	public function handle_save() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cellcraze-core' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'cellcraze_save_expense', 'cc_exp_nonce' );

		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$clean = self::validate( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated within.
		if ( is_wp_error( $clean ) ) {
			$this->redirect( array( 'cc_exp' => $clean->get_error_code() ) + ( $id ? array( 'edit' => $id ) : array() ) );
		}

		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql' );

		// Audit is recorded only after the DB write is confirmed below; a failed
		// write redirects with an error and never logs a misleading event.
		if ( $id && self::get( $id ) ) {
			$clean['updated_at'] = $now;
			$ok                  = $wpdb->update( $table, $clean, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
			if ( false === $ok ) {
				$this->redirect( array( 'cc_exp' => 'cc_exp_db', 'edit' => $id ) );
			}
			$event = 'update';
		} else {
			$clean['status']     = self::STATUS_ACTIVE;
			$clean['created_by'] = get_current_user_id();
			$clean['created_at'] = $now;
			$clean['updated_at'] = $now;
			$ok                  = $wpdb->insert( $table, $clean ); // phpcs:ignore WordPress.DB
			$id                  = (int) $wpdb->insert_id;
			if ( false === $ok || ! $id ) {
				$this->redirect( array( 'cc_exp' => 'cc_exp_db' ) );
			}
			$event = 'create';
		}

		// Optional receipt upload (validated, private).
		if ( ! empty( $_FILES['receipt']['name'] ) && empty( $_FILES['receipt']['error'] ) ) {
			$stored = $this->store_receipt( $id, $_FILES['receipt'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( is_wp_error( $stored ) ) {
				$this->redirect( array( 'cc_exp' => $stored->get_error_code(), 'edit' => $id ) );
			}
			$wpdb->update( $table, array( 'receipt_path' => $stored ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		}

		$this->audit( $event, $id, $clean );
		$this->redirect( array( 'cc_exp' => 'saved' ) );
	}

	/**
	 * Void (soft-delete) an expense.
	 */
	public function handle_void() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cellcraze-core' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'cellcraze_void_expense_' . $id );

		$row = self::get( $id );
		if ( $row && self::STATUS_VOID !== $row->status ) {
			global $wpdb;
			$ok = $wpdb->update( self::table(), array( 'status' => self::STATUS_VOID, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
			if ( false !== $ok ) {
				$this->audit( 'void', $id, (array) $row );
			}
		}
		$this->redirect( array( 'cc_exp' => 'voided' ) );
	}

	/**
	 * Validate + move a receipt into the private store.
	 *
	 * @param int   $id   Expense id.
	 * @param array $file $_FILES entry.
	 * @return string|WP_Error Relative path or error.
	 */
	private function store_receipt( $id, $file ) {
		if ( (int) $file['size'] > self::MAX_BYTES || (int) $file['size'] <= 0 ) {
			return new WP_Error( 'cc_exp_receipt', 'size' );
		}
		$check   = wp_check_filetype_and_ext( $file['tmp_name'], sanitize_file_name( $file['name'] ) );
		$ext     = $check['ext'] ? strtolower( $check['ext'] ) : '';
		$allowed = self::allowed_types();
		if ( ! $ext || ! isset( $allowed[ $ext ] ) ) {
			return new WP_Error( 'cc_exp_receipt', 'type' );
		}
		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$real  = finfo_file( $finfo, $file['tmp_name'] );
			finfo_close( $finfo );
			if ( $real !== $allowed[ $ext ] ) {
				return new WP_Error( 'cc_exp_receipt', 'type' );
			}
		}
		$dir = Cellcraze_BankSlip::private_dir(); // reuse hardened private dir.
		// Fail safe: if the directory could not be created or its Apache guard is
		// absent, refuse to store rather than risk placing a file somewhere
		// publicly reachable. (On Nginx, direct access must be blocked by server
		// config; the gated endpoint is the in-app control.)
		if ( ! is_dir( $dir ) || ! is_writable( $dir ) || ! file_exists( trailingslashit( $dir ) . '.htaccess' ) ) {
			return new WP_Error( 'cc_exp_receipt', 'unprotected' );
		}
		$filename = 'expense-' . $id . '-' . wp_generate_password( 16, false ) . '.' . $ext;
		$dest     = trailingslashit( $dir ) . $filename;
		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
			return new WP_Error( 'cc_exp_receipt', 'move' );
		}
		@chmod( $dest, 0640 );
		return Cellcraze_BankSlip::SUBDIR . '/' . $filename;
	}

	/**
	 * Gated receipt download — capability + nonce only.
	 */
	public function handle_view_receipt() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cellcraze-core' ), '', array( 'response' => 403 ) );
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'cellcraze_view_receipt_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'cellcraze-core' ), '', array( 'response' => 403 ) );
		}
		$row = self::get( $id );
		if ( ! $row || ! $row->receipt_path ) {
			wp_die( esc_html__( 'No receipt.', 'cellcraze-core' ), '', array( 'response' => 404 ) );
		}
		$basedir = wp_upload_dir()['basedir'];
		$abs     = trailingslashit( $basedir ) . ltrim( $row->receipt_path, '/\\' );
		$real    = realpath( $abs );
		if ( ! $real || strpos( $real, realpath( Cellcraze_BankSlip::private_dir() ) ) !== 0 || ! is_file( $real ) ) {
			wp_die( esc_html__( 'File not found.', 'cellcraze-core' ), '', array( 'response' => 404 ) );
		}
		$ext   = strtolower( pathinfo( $real, PATHINFO_EXTENSION ) );
		$types = self::allowed_types();
		nocache_headers();
		header( 'Content-Type: ' . ( isset( $types[ $ext ] ) ? $types[ $ext ] : 'application/octet-stream' ) );
		header( 'Content-Disposition: inline; filename="receipt.' . $ext . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Length: ' . filesize( $real ) );
		readfile( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Log an expense event to the T-101 ledger (no secrets).
	 *
	 * @param string $event create|update|void.
	 * @param int    $id    Expense id.
	 * @param array  $data  Clean data (amount/category/method).
	 */
	private function audit( $event, $id, $data ) {
		if ( ! class_exists( 'Cellcraze_Audit' ) ) {
			return;
		}
		// Each successful update is a distinct user action (a synchronous admin
		// POST, not a retried async hook), so give update events a unique key so
		// two legitimate edits — even within the same second — both record.
		$suffix = ( 'update' === $event ) ? ':' . str_replace( array( '.', ' ' ), '-', uniqid( '', true ) ) : '';
		Cellcraze_Audit::log(
			array(
				'event_key'   => 'expense_' . $event . ':' . $id . $suffix,
				'event_type'  => 'expense_' . $event,
				'object_type' => 'expense',
				'object_id'   => $id,
				'reason'      => isset( $data['category'] ) ? (string) $data['category'] : '',
				'reference'   => '#' . $id,
				'note'        => isset( $data['amount'] ) ? ( 'LKR ' . number_format( (float) $data['amount'], 2 ) . ' / ' . ( isset( $data['payment_method'] ) ? $data['payment_method'] : '' ) ) : '',
			)
		);
	}

	/**
	 * Receipt view URL (nonce'd).
	 *
	 * @param int $id Expense id.
	 * @return string
	 */
	private function receipt_url( $id ) {
		return add_query_arg(
			array(
				'action'   => 'cellcraze_view_receipt',
				'id'       => $id,
				'_wpnonce' => wp_create_nonce( 'cellcraze_view_receipt_' . $id ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Void URL (nonce'd).
	 *
	 * @param int $id Expense id.
	 * @return string
	 */
	private function void_url( $id ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=cellcraze_void_expense&id=' . $id ),
			'cellcraze_void_expense_' . $id
		);
	}

	/**
	 * Redirect back to the Expenses page.
	 *
	 * @param array $args Query args.
	 */
	private function redirect( $args ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=cellcraze-expenses' ) ) );
		exit;
	}
}
