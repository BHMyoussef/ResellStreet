<?php
/**
 * Mobile menu drawer (primary menu).
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;
?>
<dialog class="drawer drawer--menu" id="drawer-menu" data-drawer="menu" aria-label="<?php esc_attr_e( 'Menu', 'customimpulse' ); ?>">
	<div class="drawer__inner">
		<div class="drawer__header">
			<button type="button" class="drawer__close" data-drawer-close autofocus>
				<?php echo ci_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="visually-hidden"><?php esc_html_e( 'Close menu', 'customimpulse' ); ?></span>
			</button>
		</div>
		<nav class="mobile-nav" aria-label="<?php esc_attr_e( 'Main', 'customimpulse' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'mobile-nav__list',
					'menu_id'        => 'mobile-nav-list',
					'depth'          => 2,
					'fallback_cb'    => false,
				)
			);
			?>
		</nav>
		<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
			<div class="mobile-nav__account">
				<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
					<?php is_user_logged_in() ? esc_html_e( 'Account', 'customimpulse' ) : esc_html_e( 'Log in', 'customimpulse' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
</dialog>
