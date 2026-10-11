<?php
/**
 * No orders yet (Orders page + Overview).
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="account-empty">
	<p><?php esc_html_e( 'You haven’t placed any orders yet.', 'customimpulse' ); ?></p>
	<a class="btn" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>"><?php esc_html_e( 'Start shopping', 'customimpulse' ); ?></a>
</div>
