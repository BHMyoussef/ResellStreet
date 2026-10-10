<?php
/**
 * AJAX cart drawer (WooCommerce Store API) + Quick Shop.
 *
 * - The drawer renders client-side from Store API cart JSON (works behind full-page caches).
 * - Add to cart forms are submitted to the Store API; forms carrying extra plugin fields fall back to a normal POST.
 * - After every add/update, cart.js also fires jQuery `added_to_cart` / `updated_cart_totals` / `removed_from_cart`
 *   on document.body (when jQuery is on the page) so pixel plugins keep tracking.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the drawer runs on this request (not on cart/checkout, where it would duplicate the page).
 */
function ci_cart_drawer_enabled() {
	return ! is_cart() && ! is_checkout();
}

/**
 * Whether product cards on this request get the Quick Shop button.
 */
function ci_quick_shop_enabled() {
	return ci_mod( 'quick_shop_enable' ) && ci_cart_drawer_enabled() && ! is_product();
}

/* ---------------------------------------------------------------------------
 * Assets + config
 * ------------------------------------------------------------------------ */

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! ci_cart_drawer_enabled() ) {
			return;
		}
		ci_enqueue_style( 'cart-drawer' );
		ci_enqueue_script( 'cart' );

		$tax_incl = wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_cart' );
		$config   = array(
			'storeApi'     => esc_url_raw( rest_url( 'wc/store/v1/' ) ),
			'nonce'        => wp_create_nonce( 'wc_store_api' ),
			'restNonce'    => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			'fragmentsUrl' => esc_url_raw( WC_AJAX::get_endpoint( 'get_refreshed_fragments' ) ),
			'quickShopUrl' => esc_url_raw( rest_url( 'ci/v1/quick-shop/' ) ),
			'productCss'   => esc_url_raw( add_query_arg( 'ver', ci_asset_version( 'assets/css/product.css' ), get_theme_file_uri( 'assets/css/product.css' ) ) ),
			'productJs'    => esc_url_raw( add_query_arg( 'ver', ci_asset_version( 'assets/js/product.js' ), get_theme_file_uri( 'assets/js/product.js' ) ) ),
			'taxIncl'      => $tax_incl,
			'i18n'         => array(
				'remove'        => __( 'Remove', 'customimpulse' ),
				'decrease'      => __( 'Reduce quantity', 'customimpulse' ),
				'increase'      => __( 'Increase quantity', 'customimpulse' ),
				'quantity'      => __( 'Quantity', 'customimpulse' ),
				'error'         => __( 'Something went wrong. Please try again.', 'customimpulse' ),
				'selectOptions' => __( 'Please select your options.', 'customimpulse' ),
				'unavailable'   => __( 'Sorry, this combination is unavailable. Please choose another.', 'customimpulse' ),
				'cartCount'     => __( 'Cart (%d)', 'customimpulse' ),
			),
		);
		wp_add_inline_script( 'ci-cart', 'window.ciCart=' . wp_json_encode( $config ) . ';', 'before' );
	}
);

/* WooCommerce's jQuery scripts are only needed where its own JS still runs (product, cart, checkout, account). */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_product() || is_cart() || is_checkout() || is_account_page() ) {
			return;
		}
		foreach ( array( 'wc-add-to-cart', 'woocommerce', 'wc-jquery-blockui', 'jquery-blockui', 'wc-js-cookie', 'js-cookie', 'wc-cart-fragments' ) as $handle ) {
			wp_dequeue_script( $handle );
		}
	},
	100
);

/* ---------------------------------------------------------------------------
 * Drawer markup
 * ------------------------------------------------------------------------ */

add_action(
	'wp_footer',
	function () {
		if ( ci_cart_drawer_enabled() ) {
			get_template_part( 'template-parts/cart/drawer' );
		}
		if ( ci_quick_shop_enabled() ) {
			?>
			<dialog class="modal modal--quick-shop" data-modal="quick-shop" aria-label="<?php esc_attr_e( 'Quick shop', 'customimpulse' ); ?>">
				<div class="modal__inner">
					<button type="button" class="modal__close" data-modal-close>
						<?php echo ci_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<span class="visually-hidden"><?php esc_html_e( 'Close', 'customimpulse' ); ?></span>
					</button>
					<div class="quick-shop-body" data-quick-shop-body></div>
				</div>
			</dialog>
			<?php
		}
	},
	5
);

/* ---------------------------------------------------------------------------
 * Quick Shop
 * ------------------------------------------------------------------------ */

/* "Shop Now" button: after the card link (a button can't live inside the <a>), positioned over the image. */
add_action(
	'woocommerce_after_shop_loop_item',
	function () {
		global $product;
		if ( ! $product instanceof WC_Product || ! ci_quick_shop_enabled() ) {
			return;
		}
		if ( ! $product->is_purchasable() || ! $product->is_type( array( 'simple', 'variable' ) ) ) {
			return;
		}
		$label = trim( (string) ci_mod( 'quick_shop_text' ) );
		printf(
			'<div class="product-card__quick-wrap"><button type="button" class="product-card__quick" data-quick-shop="%d" aria-label="%s">%s</button></div>',
			absint( $product->get_id() ),
			/* translators: %s: product name */
			esc_attr( sprintf( __( 'Quick shop: %s', 'customimpulse' ), $product->get_name() ) ),
			esc_html( $label ? $label : __( 'Shop Now', 'customimpulse' ) )
		);
	},
	6
);

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'ci/v1',
			'/quick-shop/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
				'callback'            => 'ci_quick_shop_response',
			)
		);
	}
);

/**
 * Quick Shop HTML for one product (same summary hooks as the product page).
 *
 * @param WP_REST_Request $request Request.
 */
function ci_quick_shop_response( WP_REST_Request $request ) {
	global $post, $product;

	$id  = (int) $request['id'];
	$obj = wc_get_product( $id );
	if ( ! $obj || 'publish' !== $obj->get_status() || ! $obj->is_visible() || ! $obj->is_type( array( 'simple', 'variable' ) ) ) {
		return new WP_Error( 'ci_not_found', __( 'Product not found.', 'customimpulse' ), array( 'status' => 404 ) );
	}

	$post    = get_post( $id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$product = $obj; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	setup_postdata( $post );

	// Quick Shop runs on our compact variation map; skip WooCommerce's (much larger) inline variation JSON.
	add_filter( 'woocommerce_ajax_variation_threshold', '__return_zero' );
	ob_start();
	get_template_part( 'template-parts/product/quick-shop', null, array( 'product' => $obj ) );
	$html = ob_get_clean();
	remove_filter( 'woocommerce_ajax_variation_threshold', '__return_zero' );
	wp_reset_postdata();

	$response = rest_ensure_response( array( 'html' => $html ) );
	$response->header( 'Cache-Control', 'public, max-age=60' );
	return $response;
}
