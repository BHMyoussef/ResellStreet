<?php
/**
 * Cart, checkout and account pages (classic shortcodes), via hooks only.
 *
 * Cart: Impulse layout (items left, grey subtotal box right), no shipping calculator / coupon field
 *       ("Shipping, taxes, and discount codes calculated at checkout").
 * Checkout: Shopify-checkout look from settings_data.json (checkout_* colours): minimal header,
 *       form + payment on the left, order summary (with discount code) on the #fafafa sidebar.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Checkout pages use the minimal header/footer (like Shopify's hosted checkout).
 */
function ci_is_minimal_checkout() {
	return function_exists( 'is_checkout' ) && is_checkout();
}

add_filter(
	'body_class',
	function ( $classes ) {
		if ( is_cart() ) {
			$classes[] = 'ci-cart-page';
		}
		if ( ci_is_minimal_checkout() ) {
			$classes[] = 'ci-checkout-page';
			if ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'order-pay' ) ) {
				$classes[] = 'ci-checkout-page--single';
			}
		}
		if ( is_account_page() ) {
			$classes[] = 'ci-account-page';
		}
		return $classes;
	}
);

/* ---------------------------------------------------------------------------
 * Assets
 * ------------------------------------------------------------------------ */

add_action(
	'wp_enqueue_scripts',
	function () {
		// Native selects instead of selectWoo (lighter; WC's country/state script works without it).
		wp_dequeue_script( 'selectWoo' );
		wp_dequeue_style( 'select2' );
		// Only drives AJAX buttons on product cards, which this theme doesn't render.
		wp_dequeue_script( 'wc-add-to-cart' );

		if ( is_cart() || is_checkout() || is_account_page() ) {
			ci_enqueue_style( 'wc-pages' );
			ci_enqueue_script( 'wc-pages' );
			wp_localize_script(
				'ci-wc-pages',
				'ciWcPages',
				array(
					'decrease' => __( 'Reduce quantity', 'customimpulse' ),
					'increase' => __( 'Increase quantity', 'customimpulse' ),
					'summary'  => __( 'Order summary', 'customimpulse' ),
				)
			);
		}
	},
	110
);

/* ---------------------------------------------------------------------------
 * Cart page
 * ------------------------------------------------------------------------ */

/* Shipping and coupons are handled at checkout (reference cart shows only the subtotal). */
add_filter(
	'woocommerce_cart_ready_to_calc_shipping',
	fn( $ready ) => is_cart() ? false : $ready
);
add_filter(
	'woocommerce_coupons_enabled',
	fn( $enabled ) => is_cart() ? false : $enabled
);
remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );

/* "Continue shopping" under the page title, like the reference. */
add_action(
	'woocommerce_before_cart',
	function () {
		printf(
			'<p class="cart-page__continue"><a class="text-link" href="%s">%s</a></p>',
			esc_url( wc_get_page_permalink( 'shop' ) ),
			esc_html__( 'Continue shopping', 'customimpulse' )
		);
	},
	5
);

/* "Check out" (reference wording) instead of "Proceed to checkout". */
remove_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20 );
add_action(
	'woocommerce_proceed_to_checkout',
	function () {
		printf(
			'<a href="%s" class="checkout-button button alt wc-forward">%s</a>',
			esc_url( wc_get_checkout_url() ),
			esc_html__( 'Check out', 'customimpulse' )
		);
	},
	20
);

/* Note under the checkout button in the subtotal box. */
add_action(
	'woocommerce_after_cart_totals',
	function () {
		$note = trim( (string) ci_mod( 'cart_note_text' ) );
		echo '<p class="cart-totals__note">' . esc_html( $note ? $note : __( 'Shipping, taxes, and discount codes calculated at checkout.', 'customimpulse' ) ) . '</p>';
	}
);

/* "Remove" as a text link under the quantity (reference) instead of the × column. */
add_filter(
	'woocommerce_cart_item_remove_link',
	function ( $link, $cart_item_key ) {
		if ( ! is_cart() ) {
			return $link;
		}
		return sprintf(
			'<a href="%s" class="remove cart-item-remove" aria-label="%s" data-product_id="%s">%s</a>',
			esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
			esc_attr__( 'Remove this item', 'customimpulse' ),
			esc_attr( WC()->cart->get_cart_item( $cart_item_key )['product_id'] ?? '' ),
			esc_html__( 'Remove', 'customimpulse' )
		);
	},
	10,
	2
);

/* ---------------------------------------------------------------------------
 * Checkout
 * ------------------------------------------------------------------------ */

/* Payment + "Place order" go to the left column under the address fields (Shopify order).
   WooCommerce refreshes it by class, so its position in the form doesn't matter. */
remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
add_action( 'woocommerce_checkout_after_customer_details', 'woocommerce_checkout_payment', 20 );

/* Discount code lives in the order summary, after the items (Shopify), instead of the "Have a coupon?" notice.
   It's not a nested form: wc-pages.js posts it to wc-ajax=apply_coupon and refreshes the checkout. */
remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
add_action(
	'woocommerce_review_order_after_cart_contents',
	function () {
		if ( ! wc_coupons_enabled() ) {
			return;
		}
		?>
		<tr class="checkout-coupon-row">
			<td colspan="2">
				<div class="checkout-coupon" data-checkout-coupon>
					<label class="visually-hidden" for="ci-coupon-code"><?php esc_html_e( 'Discount code', 'customimpulse' ); ?></label>
					<input type="text" id="ci-coupon-code" class="input-text" placeholder="<?php esc_attr_e( 'Discount code', 'customimpulse' ); ?>" autocomplete="off" enterkeyhint="done">
					<button type="button" class="checkout-coupon__apply" data-coupon-apply><?php esc_html_e( 'Apply', 'customimpulse' ); ?></button>
				</div>
				<p class="checkout-coupon__message" data-coupon-message role="status" hidden></p>
			</td>
		</tr>
		<?php
	}
);

/* Order summary lines: thumbnail with a quantity badge, no "× 1". */
add_filter(
	'woocommerce_cart_item_name',
	function ( $name, $cart_item ) {
		if ( ! is_checkout() || ! did_action( 'woocommerce_review_order_before_cart_contents' ) || did_action( 'woocommerce_review_order_after_cart_contents' ) ) {
			return $name;
		}
		$product = $cart_item['data'] ?? null;
		if ( ! $product instanceof WC_Product ) {
			return $name;
		}
		$thumb = $product->get_image( 'thumbnail', array( 'class' => 'summary-item__img', 'loading' => 'lazy', 'alt' => '' ) );
		return sprintf(
			'<span class="summary-item"><span class="summary-item__media">%s<span class="summary-item__qty" aria-label="%s">%d</span></span><span class="summary-item__name">%s</span></span>',
			$thumb,
			esc_attr__( 'Quantity', 'customimpulse' ),
			(int) $cart_item['quantity'],
			$name
		);
	},
	10,
	2
);
add_filter(
	'woocommerce_checkout_cart_item_quantity',
	fn( $html ) => '<span class="visually-hidden">' . wp_strip_all_tags( $html ) . '</span>'
);
