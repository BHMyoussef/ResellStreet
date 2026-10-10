<?php
/**
 * Cart drawer shell. Items/totals are rendered by cart.js from the Store API.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

$ci_note = trim( (string) ci_mod( 'cart_note_text' ) );
?>
<dialog class="drawer drawer--cart" id="drawer-cart" data-drawer="cart" aria-labelledby="drawer-cart-title">
	<div class="drawer__inner">
		<div class="cart-drawer__header">
			<h2 class="cart-drawer__title" id="drawer-cart-title"><?php esc_html_e( 'Cart', 'customimpulse' ); ?></h2>
			<button type="button" class="drawer__close" data-drawer-close autofocus>
				<?php echo ci_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="visually-hidden"><?php esc_html_e( 'Close cart', 'customimpulse' ); ?></span>
			</button>
		</div>

		<div class="cart-drawer__body" data-cart-body aria-busy="true">
			<div class="cart-drawer__loading" data-cart-loading><span class="spinner" aria-hidden="true"></span></div>
			<ul class="cart-drawer__items" data-cart-items></ul>
			<p class="cart-drawer__empty" data-cart-empty hidden><?php esc_html_e( 'Your cart is currently empty.', 'customimpulse' ); ?></p>
		</div>

		<p class="cart-drawer__error" data-cart-error role="alert" hidden></p>

		<div class="cart-drawer__footer" data-cart-footer hidden>
			<div class="cart-drawer__subtotal">
				<span class="cart-drawer__subtotal-label"><?php esc_html_e( 'Subtotal', 'customimpulse' ); ?></span>
				<span class="cart-drawer__subtotal-price" data-cart-subtotal></span>
			</div>
			<p class="cart-drawer__note">
				<?php echo esc_html( $ci_note ? $ci_note : __( 'Shipping, taxes, and discount codes calculated at checkout.', 'customimpulse' ) ); ?>
			</p>
			<a class="btn btn--full cart-drawer__checkout" href="<?php echo esc_url( wc_get_checkout_url() ); ?>"><?php esc_html_e( 'Check out', 'customimpulse' ); ?></a>
		</div>
		<p class="visually-hidden" data-cart-status aria-live="polite"></p>
	</div>
</dialog>
