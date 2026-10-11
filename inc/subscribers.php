<?php
/**
 * Newsletter subscribers: emails from the footer "Sign up and save" form (WPForms, tools/newsletter-form.php) are
 * saved in their own table ({prefix}ci_subscribers). WPForms Lite keeps no entries, and the store doesn't want an
 * email per sign-up. Admin: Users → Subscribers (search, delete, CSV export). Personal-data export/erase included.
 *
 * The table stays in the database if the theme is switched.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

const CI_SUBSCRIBERS_DB_VERSION = 1;

/**
 * Table name.
 */
function ci_subscribers_table() {
	global $wpdb;
	return $wpdb->prefix . 'ci_subscribers';
}

/**
 * Create/upgrade the table (cheap version check; runs on admin requests and before the first save).
 */
function ci_subscribers_install() {
	if ( (int) get_option( 'ci_subscribers_db_version' ) === CI_SUBSCRIBERS_DB_VERSION ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta(
		'CREATE TABLE ' . ci_subscribers_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(190) NOT NULL,
			source varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email)
		) {$wpdb->get_charset_collate()};"
	);
	update_option( 'ci_subscribers_db_version', CI_SUBSCRIBERS_DB_VERSION, false );
}
add_action( 'admin_init', 'ci_subscribers_install' );

/**
 * Save one subscriber (already subscribed = no change).
 *
 * @param string $email  Email.
 * @param string $source Page URL the form was sent from.
 * @return bool Saved (or already there).
 */
function ci_add_subscriber( $email, $source = '' ) {
	global $wpdb;
	$email = sanitize_email( $email );
	if ( ! is_email( $email ) ) {
		return false;
	}
	ci_subscribers_install(); // The table may not exist yet if no admin page was opened since the theme was installed.
	$sql = $wpdb->prepare(
		'INSERT IGNORE INTO ' . ci_subscribers_table() . ' (email, source, created_at) VALUES (%s, %s, %s)', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		strtolower( $email ),
		esc_url_raw( $source ),
		current_time( 'mysql', true )
	);
	return false !== $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
}

/* Footer newsletter form → table. */
add_action(
	'wpforms_process_complete',
	function ( $fields, $entry, $form_data ) {
		if ( (int) ( $form_data['id'] ?? 0 ) !== (int) get_option( 'ci_newsletter_form_id' ) ) {
			return;
		}
		foreach ( (array) $fields as $field ) {
			if ( 'email' === ( $field['type'] ?? '' ) && ! empty( $field['value'] ) ) {
				ci_add_subscriber( $field['value'], wp_get_referer() ?: (string) ( $_POST['page_url'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- WPForms verified the submission.
				return;
			}
		}
	},
	10,
	3
);

/* ---------------------------------------------------------------------------
 * Admin: Users → Subscribers
 * ------------------------------------------------------------------------ */

add_action(
	'admin_menu',
	function () {
		add_users_page( __( 'Newsletter subscribers', 'customimpulse' ), __( 'Subscribers', 'customimpulse' ), 'manage_options', 'ci-subscribers', 'ci_subscribers_page' );
	}
);

/**
 * Subscribers screen.
 */
function ci_subscribers_page() {
	global $wpdb;
	$table  = ci_subscribers_table();
	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$paged  = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$per    = 50;
	$where  = $search ? $wpdb->prepare( 'WHERE email LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' ) : '';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- table name + prepared $where.
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per, ( $paged - 1 ) * $per ) );
	// phpcs:enable
	$base = admin_url( 'users.php?page=ci-subscribers' );
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Newsletter subscribers', 'customimpulse' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ci_subscribers_csv' ), 'ci_subscribers_csv' ) ); ?>"><?php esc_html_e( 'Export CSV', 'customimpulse' ); ?></a>
		<hr class="wp-header-end">
		<?php if ( isset( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Subscriber deleted.', 'customimpulse' ); ?></p></div>
		<?php endif; ?>
		<p>
			<?php
			/* translators: %s: number of subscribers */
			echo esc_html( sprintf( _n( '%s subscriber from the footer “Sign up and save” form.', '%s subscribers from the footer “Sign up and save” form.', $total, 'customimpulse' ), number_format_i18n( $total ) ) );
			?>
		</p>
		<form method="get">
			<input type="hidden" name="page" value="ci-subscribers">
			<p class="search-box">
				<label class="screen-reader-text" for="ci-subscriber-search"><?php esc_html_e( 'Search subscribers', 'customimpulse' ); ?></label>
				<input type="search" id="ci-subscriber-search" name="s" value="<?php echo esc_attr( $search ); ?>">
				<?php submit_button( __( 'Search', 'customimpulse' ), '', '', false ); ?>
			</p>
		</form>
		<table class="wp-list-table widefat fixed striped">
			<thead><tr><th><?php esc_html_e( 'Email', 'customimpulse' ); ?></th><th><?php esc_html_e( 'Subscribed', 'customimpulse' ); ?></th><th><?php esc_html_e( 'Signed up on', 'customimpulse' ); ?></th><th></th></tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'No subscribers yet.', 'customimpulse' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row->email ); ?></td>
					<td><?php echo esc_html( get_date_from_gmt( $row->created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
					<td><?php echo $row->source ? '<a href="' . esc_url( $row->source ) . '">' . esc_html( wp_parse_url( $row->source, PHP_URL_PATH ) ?: '/' ) . '</a>' : '—'; ?></td>
					<td><a class="submitdelete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ci_subscriber_delete&id=' . (int) $row->id ), 'ci_subscriber_delete_' . (int) $row->id ) ); ?>"><?php esc_html_e( 'Delete', 'customimpulse' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$pages = (int) ceil( $total / $per );
		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput
				array(
					'base'    => add_query_arg( 'paged', '%#%', $search ? add_query_arg( 's', rawurlencode( $search ), $base ) : $base ),
					'format'  => '',
					'current' => $paged,
					'total'   => $pages,
				)
			) . '</div></div>';
		}
		?>
	</div>
	<?php
}

add_action(
	'admin_post_ci_subscriber_delete',
	function () {
		$id = absint( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification -- checked next.
		check_admin_referer( 'ci_subscriber_delete_' . $id );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'customimpulse' ) );
		}
		global $wpdb;
		$wpdb->delete( ci_subscribers_table(), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_safe_redirect( admin_url( 'users.php?page=ci-subscribers&deleted=1' ) );
		exit;
	}
);

add_action(
	'admin_post_ci_subscribers_csv',
	function () {
		check_admin_referer( 'ci_subscribers_csv' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'customimpulse' ) );
		}
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT email, created_at, source FROM ' . ci_subscribers_table() . ' ORDER BY created_at ASC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=subscribers-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fputcsv( $out, array( 'email', 'subscribed_at_utc', 'source' ) );
		foreach ( $rows as $row ) {
			fputcsv( $out, array( $row['email'], $row['created_at'], $row['source'] ) );
		}
		exit;
	}
);

/* ---------------------------------------------------------------------------
 * Privacy: Tools → Export / Erase Personal Data cover the subscriber list (UK GDPR requests)
 * ------------------------------------------------------------------------ */

add_filter(
	'wp_privacy_personal_data_exporters',
	function ( $exporters ) {
		$exporters['ci-subscribers'] = array(
			'exporter_friendly_name' => __( 'Newsletter subscription', 'customimpulse' ),
			'callback'               => function ( $email ) {
				global $wpdb;
				$row  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ci_subscribers_table() . ' WHERE email = %s', strtolower( $email ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
				$data = $row ? array(
					array(
						'group_id'    => 'ci-subscribers',
						'group_label' => __( 'Newsletter subscription', 'customimpulse' ),
						'item_id'     => 'subscriber-' . $row->id,
						'data'        => array(
							array( 'name' => __( 'Email', 'customimpulse' ), 'value' => $row->email ),
							array( 'name' => __( 'Subscribed (UTC)', 'customimpulse' ), 'value' => $row->created_at ),
						),
					),
				) : array();
				return array( 'data' => $data, 'done' => true );
			},
		);
		return $exporters;
	}
);

add_filter(
	'wp_privacy_personal_data_erasers',
	function ( $erasers ) {
		$erasers['ci-subscribers'] = array(
			'eraser_friendly_name' => __( 'Newsletter subscription', 'customimpulse' ),
			'callback'             => function ( $email ) {
				global $wpdb;
				$removed = (int) $wpdb->delete( ci_subscribers_table(), array( 'email' => strtolower( $email ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return array( 'items_removed' => $removed > 0, 'items_retained' => false, 'messages' => array(), 'done' => true );
			},
		);
		return $erasers;
	}
);
