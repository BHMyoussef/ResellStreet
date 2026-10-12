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
			'freeShipping' => ci_free_shipping_threshold(), // null = no free shipping method; 0 = always free
			'i18n'         => array(
				'remove'        => __( 'Remove', 'customimpulse' ),
				'decrease'      => __( 'Reduce quantity', 'customimpulse' ),
				'increase'      => __( 'Increase quantity', 'customimpulse' ),
				'quantity'      => __( 'Quantity', 'customimpulse' ),
				'error'         => __( 'Something went wrong. Please try again.', 'customimpulse' ),
				'selectOptions' => __( 'Please select your options.', 'customimpulse' ),
				'unavailable'   => __( 'Sorry, this combination is unavailable. Please choose another.', 'customimpulse' ),
				'cartCount'     => __( 'Cart (%d)', 'customimpulse' ),
				'added'         => __( 'Added to cart.', 'customimpulse' ),
				'unlocked'      => __( 'You’ve unlocked free UK shipping.', 'customimpulse' ),
				/* translators: %s: amount left to spend */
				'spendMore'     => __( 'Spend %s more for free UK shipping.', 'customimpulse' ),
				'free'          => __( 'Free', 'customimpulse' ),
				'atCheckout'    => __( 'Calculated at checkout', 'customimpulse' ),
			),
		);
		wp_add_inline_script( 'ci-cart', 'window.ciCart=' . wp_json_encode( $config ) . ';', 'before' );
	}
);

/**
 * Minimum order for WooCommerce's "Free shipping" method (first enabled one, UK zone first): 0 when it needs no
 * minimum, null when there is none (or it only works with a coupon). Drives the drawer's free-shipping message.
 *
 * @return float|null
 */
function ci_free_shipping_threshold() {
	if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
		return null;
	}
	$zones = WC_Shipping_Zones::get_zones();
	usort( $zones, fn( $a, $b ) => (int) ! in_array( 'GB', wp_list_pluck( $a['zone_locations'], 'code' ), true ) <=> (int) ! in_array( 'GB', wp_list_pluck( $b['zone_locations'], 'code' ), true ) );
	foreach ( $zones as $zone ) {
		foreach ( $zone['shipping_methods'] as $method ) {
			if ( 'free_shipping' !== $method->id || 'yes' !== $method->enabled ) {
				continue;
			}
			$requires = (string) $method->get_option( 'requires' );
			if ( in_array( $requires, array( 'coupon', 'both' ), true ) ) {
				continue;
			}
			return in_array( $requires, array( 'min_amount', 'either' ), true ) ? (float) $method->get_option( 'min_amount' ) : 0.0;
		}
	}
	return null;
}

/*
 * Store API cart items get extensions.ci = { brand, title, variant } for the drawer (design v2):
 * "BRAND" / "Boston Runner 13" / "Mint Blue / UK 8". Title drops a trailing " – <colour>" when the colour is in the
 * variant line.
 */
add_action(
	'init', // woocommerce_blocks_loaded fires before the theme loads
	function () {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
				'namespace'       => 'ci',
				'data_callback'   => 'ci_cart_item_display_data',
				'schema_callback' => fn() => array(
					'brand'   => array( 'type' => 'string', 'readonly' => true ),
					'title'   => array( 'type' => 'string', 'readonly' => true ),
					'variant' => array( 'type' => 'string', 'readonly' => true ),
				),
				'schema_type'     => ARRAY_A,
			)
		);
	}
);

/**
 * Brand, short title and variant line of a cart item.
 *
 * @param array $cart_item WooCommerce cart item.
 */
function ci_cart_item_display_data( $cart_item ) {
	$product = $cart_item['data'] ?? null;
	if ( ! $product instanceof WC_Product ) {
		return array( 'brand' => '', 'title' => '', 'variant' => '' );
	}
	$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
	$brand  = function_exists( 'ci_product_brand' ) ? ci_product_brand( $parent->get_id() ) : null;
	$parts  = array();
	$colour = '';
	$attr   = $parent->get_attributes()['pa_color'] ?? null;
	if ( $attr && ! $attr->get_variation() && $attr->get_terms() ) {
		$colour  = $attr->get_terms()[0]->name;
		$parts[] = $colour;
	}
	foreach ( (array) ( $cart_item['variation'] ?? array() ) as $key => $value ) {
		$taxonomy = substr( (string) $key, 10 ); // attribute_pa_size → pa_size
		$term     = taxonomy_exists( $taxonomy ) ? get_term_by( 'slug', $value, $taxonomy ) : null;
		$label    = $term ? $term->name : (string) $value;
		if ( '' === $label ) {
			continue;
		}
		if ( 'pa_color' === $taxonomy && '' === $colour ) {
			$colour = $label;
		}
		$attr_label = wc_attribute_label( $taxonomy );
		$system     = function_exists( 'ci_is_size_attribute' ) && ci_is_size_attribute( $taxonomy, $attr_label ) ? ci_size_system( $attr_label ) : '';
		$parts[]    = '' !== $system ? "{$system} {$label}" : $label;
	}
	$title = $parent->get_name();
	if ( '' !== $colour ) {
		$title = preg_replace( '/\s[–-]\s' . preg_quote( $colour, '/' ) . '$/u', '', $title );
	}
	return array(
		'brand'   => $brand ? $brand->name : '',
		'title'   => html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES ),
		'variant' => implode( ' / ', $parts ),
	);
}

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

/* ---------------------------------------------------------------------------
 * Tracking parity with WooCommerce's classic add to cart
 * ------------------------------------------------------------------------ */

/**
 * Whether the current REST request is a Store API "add item" (also inside /batch).
 *
 * @param bool|null $set Set the flag (internal).
 */
function ci_is_store_api_add_item( $set = null ) {
	static $active = false;
	if ( null !== $set ) {
		$active = $set;
	}
	return $active;
}

add_filter(
	'rest_request_before_callbacks',
	function ( $response, $handler, $request ) {
		ci_is_store_api_add_item( (bool) preg_match( '#^/wc/store(/v\d+)?/cart/add-item$#', $request->get_route() ) );
		return $response;
	},
	10,
	3
);
add_filter(
	'rest_request_after_callbacks',
	function ( $response ) {
		ci_is_store_api_add_item( false );
		return $response;
	}
);

/*
 * WC_Cart::add_to_cart() fires `woocommerce_add_to_cart` for new lines AND for products already in the cart.
 * The Store API's add-item only fires it for new lines (an existing line just gets set_quantity()), so pixel plugins
 * hooked on it (PixelYourSite, Facebook for WooCommerce…) missed every repeat add from the drawer. Fire it here with
 * the added quantity, as the classic flow does.
 */
add_action(
	'woocommerce_after_cart_item_quantity_update',
	function ( $cart_item_key, $quantity, $old_quantity, $cart ) {
		if ( ! ci_is_store_api_add_item() || $quantity <= $old_quantity || empty( $cart->cart_contents[ $cart_item_key ] ) ) {
			return;
		}
		$item = $cart->cart_contents[ $cart_item_key ];
		/** This action is documented in woocommerce/includes/class-wc-cart.php */
		do_action( 'woocommerce_add_to_cart', $cart_item_key, $item['product_id'], $quantity - $old_quantity, $item['variation_id'], $item['variation'], $item );
	},
	10,
	4
);
