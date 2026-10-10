<?php
/**
 * Minimal checkout footer: policy links (footer menu).
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

if ( ! has_nav_menu( 'footer' ) ) {
	return;
}
?>
<footer class="checkout-footer">
	<nav aria-label="<?php esc_attr_e( 'Policies', 'customimpulse' ); ?>">
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'footer',
				'container'      => false,
				'menu_class'     => 'checkout-footer__menu',
				'menu_id'        => 'checkout-footer-menu',
				'depth'          => 1,
			)
		);
		?>
	</nav>
</footer>
