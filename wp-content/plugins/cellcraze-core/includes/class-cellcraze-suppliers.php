<?php
/**
 * Supplier management (CRUD + soft deactivate).
 *
 * Suppliers are never hard-deleted from the UI — they are deactivated so
 * historical purchase links are preserved. Gated by cellcraze_receive_stock.
 *
 * @package CellCraze_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cellcraze_Suppliers
 */
class Cellcraze_Suppliers {

	const CAP           = 'cellcraze_receive_stock';
	const STATUS_ACTIVE = 'active';
	const STATUS_INACTIVE = 'inactive';

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'cellcraze_supplier';
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
			name VARCHAR(191) NOT NULL DEFAULT '',
			contact_person VARCHAR(191) NOT NULL DEFAULT '',
			phone VARCHAR(50) NOT NULL DEFAULT '',
			email VARCHAR(191) NOT NULL DEFAULT '',
			address VARCHAR(500) NOT NULL DEFAULT '',
			notes VARCHAR(1000) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY name (name),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql );
	}

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 12 );
		add_action( 'admin_post_cellcraze_save_supplier', array( $this, 'handle_save' ) );
		add_action( 'admin_post_cellcraze_toggle_supplier', array( $this, 'handle_toggle' ) );
	}

	/**
	 * Fetch one supplier.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", absint( $id ) ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Active suppliers (for PO dropdowns).
	 *
	 * @return array
	 */
	public static function active() {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM {$t} WHERE status = %s ORDER BY name ASC", self::STATUS_ACTIVE ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Submenu.
	 */
	public function admin_menu() {
		add_submenu_page( 'woocommerce', __( 'Suppliers', 'cellcraze-core' ), __( 'Suppliers', 'cellcraze-core' ), self::CAP, 'cellcraze-suppliers', array( $this, 'render' ) );
	}

	/**
	 * Validate raw input.
	 *
	 * @param array $raw Input.
	 * @return array|WP_Error
	 */
	public static function validate( $raw ) {
		$name = isset( $raw['name'] ) ? trim( sanitize_text_field( $raw['name'] ) ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'cc_sup_name', __( 'Supplier name is required.', 'cellcraze-core' ) );
		}
		$email = isset( $raw['email'] ) ? trim( sanitize_email( $raw['email'] ) ) : '';
		if ( '' !== $email && ! is_email( $email ) ) {
			return new WP_Error( 'cc_sup_email', __( 'The email address is not valid.', 'cellcraze-core' ) );
		}
		return array(
			'name'           => substr( $name, 0, 191 ),
			'contact_person' => isset( $raw['contact_person'] ) ? substr( sanitize_text_field( $raw['contact_person'] ), 0, 191 ) : '',
			'phone'          => isset( $raw['phone'] ) ? substr( sanitize_text_field( $raw['phone'] ), 0, 50 ) : '',
			'email'          => substr( $email, 0, 191 ),
			'address'        => isset( $raw['address'] ) ? substr( sanitize_textarea_field( $raw['address'] ), 0, 500 ) : '',
			'notes'          => isset( $raw['notes'] ) ? substr( sanitize_textarea_field( $raw['notes'] ), 0, 1000 ) : '',
		);
	}

	/**
	 * Save (create/update).
	 */
	public function handle_save() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cellcraze-core' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'cellcraze_save_supplier', 'cc_sup_nonce' );
		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$clean = self::validate( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated within.
		if ( is_wp_error( $clean ) ) {
			$this->redirect( array( 'cc_sup' => $clean->get_error_code() ) + ( $id ? array( 'edit' => $id ) : array() ) );
		}
		global $wpdb;
		$now = current_time( 'mysql' );
		if ( $id && self::get( $id ) ) {
			$clean['updated_at'] = $now;
			$ok                  = $wpdb->update( self::table(), $clean, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		} else {
			$clean['status']     = self::STATUS_ACTIVE;
			$clean['created_at'] = $now;
			$clean['updated_at'] = $now;
			$ok                  = $wpdb->insert( self::table(), $clean ); // phpcs:ignore WordPress.DB
			$id                  = (int) $wpdb->insert_id;
		}
		if ( false === $ok ) {
			$this->redirect( array( 'cc_sup' => 'cc_sup_db' ) );
		}
		if ( class_exists( 'Cellcraze_Audit' ) ) {
			Cellcraze_Audit::log( array( 'event_key' => 'supplier_save:' . $id . ':' . gmdate( 'YmdHis' ) . wp_generate_password( 4, false ), 'event_type' => 'supplier_save', 'object_type' => 'supplier', 'object_id' => $id, 'reason' => 'supplier', 'reference' => '#' . $id ) );
		}
		$this->redirect( array( 'cc_sup' => 'saved' ) );
	}

	/**
	 * Toggle active/inactive (soft; never hard-deletes).
	 */
	public function handle_toggle() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cellcraze-core' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'cellcraze_toggle_supplier_' . $id );
		$row = self::get( $id );
		if ( $row ) {
			$new = ( self::STATUS_ACTIVE === $row->status ) ? self::STATUS_INACTIVE : self::STATUS_ACTIVE;
			global $wpdb;
			$wpdb->update( self::table(), array( 'status' => $new, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		}
		$this->redirect( array( 'cc_sup' => 'toggled' ) );
	}

	/**
	 * Error message map.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	private function msg( $code ) {
		$m = array(
			'cc_sup_name'  => __( 'Supplier name is required.', 'cellcraze-core' ),
			'cc_sup_email' => __( 'The email address is not valid.', 'cellcraze-core' ),
			'cc_sup_db'    => __( 'Could not save the supplier.', 'cellcraze-core' ),
		);
		return isset( $m[ $code ] ) ? $m[ $code ] : __( 'Saved.', 'cellcraze-core' );
	}

	/**
	 * Render the Suppliers page.
	 */
	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cellcraze-core' ), '', array( 'response' => 403 ) );
		}
		global $wpdb;
		$t      = self::table();
		$po_t   = Cellcraze_Purchasing::po_table();
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$fstat  = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$where  = array( '1=1' );
		$params = array();
		if ( '' !== $search ) { $where[] = 's.name LIKE %s'; $params[] = '%' . $wpdb->esc_like( $search ) . '%'; }
		if ( in_array( $fstat, array( self::STATUS_ACTIVE, self::STATUS_INACTIVE ), true ) ) { $where[] = 's.status = %s'; $params[] = $fstat; }
		$where_sql = implode( ' AND ', $where );
		// Single grouped query with a linked PO count (no per-row queries).
		$sql  = "SELECT s.*, COALESCE(po.cnt,0) AS po_count FROM {$t} s LEFT JOIN ( SELECT supplier_id, COUNT(*) cnt FROM {$po_t} GROUP BY supplier_id ) po ON po.supplier_id = s.id WHERE {$where_sql} ORDER BY s.status ASC, s.name ASC LIMIT 500";
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB
		$edit   = isset( $_GET['edit'] ) ? self::get( absint( $_GET['edit'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification
		$notice = isset( $_GET['cc_sup'] ) ? sanitize_key( $_GET['cc_sup'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap cc-admin">
			<div class="cc-head"><div><h1><?php esc_html_e( 'Suppliers', 'cellcraze-core' ); ?></h1><p class="cc-sub"><?php esc_html_e( 'Manage suppliers used by purchase orders. Deactivate instead of deleting to preserve history.', 'cellcraze-core' ); ?></p></div></div>
			<?php if ( 'saved' === $notice || 'toggled' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Supplier updated.', 'cellcraze-core' ); ?></p></div>
			<?php elseif ( $notice ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $this->msg( $notice ) ); ?></p></div>
			<?php endif; ?>

			<h2><?php echo $edit ? esc_html__( 'Edit supplier', 'cellcraze-core' ) : esc_html__( 'Add supplier', 'cellcraze-core' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cc-form">
				<?php wp_nonce_field( 'cellcraze_save_supplier', 'cc_sup_nonce' ); ?>
				<input type="hidden" name="action" value="cellcraze_save_supplier" />
				<?php if ( $edit ) : ?><input type="hidden" name="id" value="<?php echo (int) $edit->id; ?>" /><?php endif; ?>
				<div class="full"><label><?php esc_html_e( 'Supplier name', 'cellcraze-core' ); ?></label><input type="text" name="name" maxlength="191" required value="<?php echo esc_attr( $edit ? $edit->name : '' ); ?>" /></div>
				<div><label><?php esc_html_e( 'Contact person', 'cellcraze-core' ); ?></label><input type="text" name="contact_person" maxlength="191" value="<?php echo esc_attr( $edit ? $edit->contact_person : '' ); ?>" /></div>
				<div><label><?php esc_html_e( 'Phone', 'cellcraze-core' ); ?></label><input type="text" name="phone" maxlength="50" value="<?php echo esc_attr( $edit ? $edit->phone : '' ); ?>" /></div>
				<div><label><?php esc_html_e( 'Email', 'cellcraze-core' ); ?></label><input type="email" name="email" maxlength="191" value="<?php echo esc_attr( $edit ? $edit->email : '' ); ?>" /></div>
				<div><label><?php esc_html_e( 'Address', 'cellcraze-core' ); ?></label><input type="text" name="address" maxlength="500" value="<?php echo esc_attr( $edit ? $edit->address : '' ); ?>" /></div>
				<div class="full"><label><?php esc_html_e( 'Notes', 'cellcraze-core' ); ?></label><textarea name="notes" rows="2" maxlength="1000"><?php echo esc_textarea( $edit ? $edit->notes : '' ); ?></textarea></div>
				<div class="full"><button class="button button-primary"><?php echo $edit ? esc_html__( 'Update supplier', 'cellcraze-core' ) : esc_html__( 'Add supplier', 'cellcraze-core' ); ?></button>
					<?php if ( $edit ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cellcraze-suppliers' ) ); ?>"><?php esc_html_e( 'Cancel', 'cellcraze-core' ); ?></a><?php endif; ?></div>
			</form>

			<h2><?php esc_html_e( 'All suppliers', 'cellcraze-core' ); ?></h2>
			<form method="get" class="cc-filters">
				<input type="hidden" name="page" value="cellcraze-suppliers" />
				<div><label><?php esc_html_e( 'Search name', 'cellcraze-core' ); ?></label><input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" /></div>
				<div><label><?php esc_html_e( 'Status', 'cellcraze-core' ); ?></label>
					<select name="status"><option value=""><?php esc_html_e( 'All', 'cellcraze-core' ); ?></option>
					<option value="active" <?php selected( $fstat, 'active' ); ?>><?php esc_html_e( 'Active', 'cellcraze-core' ); ?></option>
					<option value="inactive" <?php selected( $fstat, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'cellcraze-core' ); ?></option>
					</select></div>
				<div><button class="button"><?php esc_html_e( 'Filter', 'cellcraze-core' ); ?></button></div>
			</form>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Name', 'cellcraze-core' ); ?></th><th><?php esc_html_e( 'Contact', 'cellcraze-core' ); ?></th><th><?php esc_html_e( 'Phone', 'cellcraze-core' ); ?></th><th><?php esc_html_e( 'Email', 'cellcraze-core' ); ?></th><th class="cc-num"><?php esc_html_e( 'POs', 'cellcraze-core' ); ?></th><th><?php esc_html_e( 'Status', 'cellcraze-core' ); ?></th><th></th></tr></thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="7"><div class="cc-empty"><?php esc_html_e( 'No suppliers match.', 'cellcraze-core' ); ?></div></td></tr>
				<?php else : foreach ( $rows as $s ) :
					$active = self::STATUS_ACTIVE === $s->status;
					$toggle = wp_nonce_url( admin_url( 'admin-post.php?action=cellcraze_toggle_supplier&id=' . (int) $s->id ), 'cellcraze_toggle_supplier_' . (int) $s->id ); ?>
					<tr<?php echo $active ? '' : ' style="opacity:.55"'; ?>>
						<td><strong><?php echo esc_html( $s->name ); ?></strong></td>
						<td><?php echo esc_html( $s->contact_person ); ?></td>
						<td><?php echo esc_html( $s->phone ); ?></td>
						<td><?php echo esc_html( $s->email ); ?></td>
						<td class="cc-num"><?php echo ( (int) $s->po_count ) ? '<a href="' . esc_url( admin_url( 'admin.php?page=cellcraze-purchase-orders&supplier=' . (int) $s->id ) ) . '">' . (int) $s->po_count . '</a>' : '0'; // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
						<td><span class="cc-chip <?php echo $active ? 'is-active' : 'is-void'; ?>"><?php echo esc_html( $active ? __( 'Active', 'cellcraze-core' ) : __( 'Inactive', 'cellcraze-core' ) ); ?></span></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=cellcraze-suppliers&edit=' . (int) $s->id ) ); ?>"><?php esc_html_e( 'Edit', 'cellcraze-core' ); ?></a> |
							<a href="<?php echo esc_url( $toggle ); ?>"><?php echo esc_html( $active ? __( 'Deactivate', 'cellcraze-core' ) : __( 'Reactivate', 'cellcraze-core' ) ); ?></a>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Redirect to the Suppliers page.
	 *
	 * @param array $args Query args.
	 */
	private function redirect( $args ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=cellcraze-suppliers' ) ) );
		exit;
	}
}
