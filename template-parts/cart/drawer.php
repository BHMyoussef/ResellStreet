<?php
/**
 * Cart drawer shell (design v2). Items/totals are rendered by cart.js from the Store API.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

$ci_note = trim( (string) ci_mod( 'cart_note_text' ) );
?>
<dialog class="drawer drawer--cart" id="drawer-cart" data-drawer="cart" aria-labelledby="drawer-cart-title">
	<div class="drawer__inner">
		<div class="cart-drawer__header">
			<h2 class="cart-drawer__title" id="drawer-cart-title"><?php esc_html_e( 'Your cart', 'customimpulse' ); ?> <span class="cart-drawer__count" data-cart-title-count></span></h2>
			<button type="button" class="drawer__close" data-drawer-close autofocus>
				<?php echo ci_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="visually-hidden"><?php esc_html_e( 'Close cart', 'customimpulse' ); ?></span>
			</button>
		</div>

		<p class="cart-drawer__notice" data-cart-notice hidden>
			<span class="cart-drawer__notice-check" aria-hidden="true"><?php echo ci_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<span><strong data-cart-notice-title></strong> <span data-cart-notice-text></span></span>
		</p>

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
			<p class="cart-drawer__shipping" data-cart-shipping hidden>
				<span><?php esc_html_e( 'UK shipping', 'customimpulse' ); ?></span>
				<span data-cart-shipping-value></span>
			</p>
			<?php if ( $ci_note ) : ?>
				<p class="cart-drawer__note"><?php echo esc_html( $ci_note ); ?></p>
			<?php endif; ?>
			<a class="btn btn--full cart-drawer__checkout" href="<?php echo esc_url( wc_get_checkout_url() ); ?>"><?php esc_html_e( 'Checkout', 'customimpulse' ); ?></a>
			<button type="button" class="cart-drawer__continue" data-drawer-close><?php esc_html_e( 'Continue shopping', 'customimpulse' ); ?></button>
		</div>
		<p class="visually-hidden" data-cart-status aria-live="polite"></p>
	</div>
</dialog>
