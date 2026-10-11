<?php
/**
 * My account (classic shortcode), via hooks only. Same Shopify-account look as the checkout.
 *
 * - Menu: Overview, Orders, Addresses, Account details, Log out. Downloads dropped (no digital products);
 *   Payment methods only for customers with a saved card (Stripe saved cards are on).
 * - Overview replaces WooCommerce's dashboard sentence: recent orders, default address, account details.
 * - Orders: cards (status pill, date, items, total, thumbnails, actions) instead of the table.
 * - Login / register: one card with "Sign in" / "Create account" tabs (account.js; stacked without JS).
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

/* ---------------------------------------------------------------------------
 * Assets
 * ------------------------------------------------------------------------ */

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_account_page() ) {
			return;
		}
		ci_enqueue_style( 'account' );
		ci_enqueue_script( 'account' );
	},
	110
);

/* ---------------------------------------------------------------------------
 * Menu
 * ------------------------------------------------------------------------ */

add_filter(
	'woocommerce_account_menu_items',
	function ( $items ) {
		unset( $items['downloads'] );
		if ( isset( $items['payment-methods'] ) && ! is_wc_endpoint_url( 'payment-methods' ) && ! is_wc_endpoint_url( 'add-payment-method' )
			&& ! wc_get_customer_saved_methods_list( get_current_user_id() ) ) {
			unset( $items['payment-methods'] );
		}
		if ( isset( $items['dashboard'] ) ) {
			$items['dashboard'] = __( 'Overview', 'customimpulse' );
		}
		return $items;
	},
	20
);

/* ---------------------------------------------------------------------------
 * Wording: shopper language instead of WordPress/WooCommerce terms (account pages only)
 * ------------------------------------------------------------------------ */

add_action(
	'template_redirect',
	function () {
		if ( ! is_account_page() ) {
			return;
		}
		$strings = array(
			'Login'                                                => __( 'Sign in', 'customimpulse' ),
			'Log in'                                               => __( 'Sign in', 'customimpulse' ),
			'Register'                                             => __( 'Create account', 'customimpulse' ),
			'Username or email address'                            => __( 'Email or username', 'customimpulse' ),
			'Lost your password?'                                  => __( 'Forgot your password?', 'customimpulse' ),
			'Password change'                                      => __( 'Change password', 'customimpulse' ),
			'Current password (leave blank to leave unchanged)'    => __( 'Current password', 'customimpulse' ),
			'New password (leave blank to leave unchanged)'        => __( 'New password', 'customimpulse' ),
			'Display name'                                         => __( 'Name on your reviews', 'customimpulse' ),
			'This will be how your name will be displayed in the account section and in reviews' => __( 'Shown next to the reviews you write.', 'customimpulse' ),
		);
		add_filter(
			'gettext_woocommerce',
			function ( $translation, $text ) use ( $strings ) {
				return $strings[ $text ] ?? $translation;
			},
			10,
			2
		);
	}
);

/* "Change password" gets a hint instead of "(leave blank to leave unchanged)" on every label. */
add_action(
	'woocommerce_edit_account_form_fields',
	function () {
		echo '<p class="account-form__hint account-form__hint--password">' . esc_html__( 'Leave the password fields empty to keep your current password.', 'customimpulse' ) . '</p>';
	}
);

/* ---------------------------------------------------------------------------
 * Who is signed in (all logged-in account pages; "Log out" is the last menu item)
 * ------------------------------------------------------------------------ */

add_action(
	'woocommerce_account_navigation',
	function () {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return;
		}
		printf(
			'<p class="account-identity">%1$s <span class="account-identity__email">%2$s</span></p>',
			esc_html__( 'Signed in as', 'customimpulse' ),
			esc_html( $user->user_email )
		);
	},
	5
);

/* ---------------------------------------------------------------------------
 * Overview (replaces the dashboard template)
 * ------------------------------------------------------------------------ */

remove_action( 'woocommerce_account_content', 'woocommerce_account_content' );
add_action(
	'woocommerce_account_content',
	function () {
		if ( '' !== WC()->query->get_current_endpoint() ) {
			woocommerce_account_content();
			return;
		}
		wc_print_notices();
		get_template_part( 'template-parts/account/overview' );
	}
);

/**
 * Customer orders (newest first), same query as WooCommerce's Orders page.
 *
 * @param int  $page     Page number.
 * @param int  $per_page Orders per page (0 = WooCommerce default).
 * @return object { orders: WC_Order[], max_num_pages: int, total: int }
 */
function ci_account_orders( $page = 1, $per_page = 0 ) {
	$args = array(
		'customer' => get_current_user_id(),
		'page'     => $page,
		'paginate' => true,
	);
	if ( $per_page ) {
		$args['limit'] = $per_page;
	}
	return wc_get_orders( apply_filters( 'woocommerce_my_account_my_orders_query', $args ) );
}

/**
 * Status pill (also used on the view-order page).
 *
 * @param WC_Order $order Order.
 */
function ci_order_status_pill( WC_Order $order ) {
	$tone = array(
		'completed'  => 'success',
		'processing' => 'progress',
		'on-hold'    => 'progress',
		'pending'    => 'neutral',
		'checkout-draft' => 'neutral',
		'cancelled'  => 'critical',
		'failed'     => 'critical',
		'refunded'   => 'neutral',
	);
	$status = $order->get_status();
	return sprintf(
		'<span class="status-pill status-pill--%1$s">%2$s</span>',
		esc_attr( $tone[ $status ] ?? 'neutral' ),
		esc_html( wc_get_order_status_name( $status ) )
	);
}

/* ---------------------------------------------------------------------------
 * Orders page: cards instead of the table
 * ------------------------------------------------------------------------ */

remove_action( 'woocommerce_account_orders_endpoint', 'woocommerce_account_orders' );
add_action(
	'woocommerce_account_orders_endpoint',
	function ( $current_page ) {
		$current_page = max( 1, absint( $current_page ) );
		$orders       = ci_account_orders( $current_page );
		$has_orders   = 0 < $orders->total;

		do_action( 'woocommerce_before_account_orders', $has_orders ); // Plugins (and WooCommerce's email check) print here.

		if ( ! $has_orders ) {
			get_template_part( 'template-parts/account/orders-empty' );
		} else {
			echo '<ul class="order-cards">';
			foreach ( $orders->orders as $order ) {
				get_template_part( 'template-parts/account/order-card', null, array( 'order' => $order ) );
			}
			echo '</ul>';

			if ( 1 < $orders->max_num_pages ) {
				echo '<nav class="account-pagination" aria-label="' . esc_attr__( 'Orders pages', 'customimpulse' ) . '">';
				if ( 1 !== $current_page ) {
					printf( '<a class="btn btn--secondary" href="%s">%s</a>', esc_url( wc_get_endpoint_url( 'orders', $current_page - 1 ) ), esc_html__( 'Newer orders', 'customimpulse' ) );
				}
				if ( (int) $orders->max_num_pages !== $current_page ) {
					printf( '<a class="btn btn--secondary" href="%s">%s</a>', esc_url( wc_get_endpoint_url( 'orders', $current_page + 1 ) ), esc_html__( 'Older orders', 'customimpulse' ) );
				}
				echo '</nav>';
			}
		}

		do_action( 'woocommerce_after_account_orders', $has_orders );
	}
);

/* ---------------------------------------------------------------------------
 * View order: status pill + date instead of "Order #1 was placed on … and is currently …"
 * ------------------------------------------------------------------------ */

add_filter(
	'woocommerce_order_details_status',
	function ( $text, $order ) {
		if ( ! $order instanceof WC_Order || ! $order->get_date_created() ) {
			return $text;
		}
		return sprintf(
			'<span class="order-status-line">%1$s <span>%2$s</span></span>',
			ci_order_status_pill( $order ),
			/* translators: %s: order date */
			esc_html( sprintf( __( 'Placed on %s', 'customimpulse' ), wc_format_datetime( $order->get_date_created() ) ) )
		);
	},
	10,
	2
);

/**
 * Whether WooCommerce is printing the order details table (view order, order received). Not emails.
 */
function ci_is_order_details_table() {
	return doing_action( 'woocommerce_view_order' ) || doing_action( 'woocommerce_thankyou' );
}

/* Order details (view order + order received): product thumbnail with a quantity badge, like the checkout summary. */
add_filter(
	'woocommerce_order_item_name',
	function ( $name, $item ) {
		if ( ! ci_is_order_details_table() || ! $item instanceof WC_Order_Item_Product ) {
			return $name;
		}
		$product = $item->get_product();
		$thumb   = $product ? $product->get_image( 'thumbnail', array( 'class' => 'summary-item__img', 'loading' => 'lazy', 'alt' => '' ) ) : '';
		return sprintf(
			'<span class="summary-item"><span class="summary-item__media">%1$s<span class="summary-item__qty" aria-label="%2$s">%3$d</span></span><span class="summary-item__name">%4$s</span></span>',
			$thumb,
			esc_attr__( 'Quantity', 'customimpulse' ),
			(int) $item->get_quantity(),
			$name
		);
	},
	10,
	2
);
add_filter(
	'woocommerce_order_item_quantity_html',
	function ( $html, $item ) {
		if ( ! ci_is_order_details_table() || ! $item instanceof WC_Order_Item_Product ) {
			return $html;
		}
		// The badge on the thumbnail shows the quantity; keep WooCommerce's text when part of it was refunded.
		return $item->get_order()->get_qty_refunded_for_item( $item->get_id() ) ? $html : '';
	},
	10,
	2
);
