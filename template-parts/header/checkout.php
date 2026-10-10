<?php
/**
 * Minimal checkout header: logo + link back to the cart.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;
?>
<header class="checkout-header">
	<div class="checkout-header__inner">
		<a class="checkout-header__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php echo ci_logo( 'header' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in ci_logo(). ?>
		</a>
		<a class="checkout-header__cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
			<?php echo ci_icon( 'bag' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span class="visually-hidden"><?php esc_html_e( 'Back to cart', 'customimpulse' ); ?></span>
		</a>
	</div>
</header>
