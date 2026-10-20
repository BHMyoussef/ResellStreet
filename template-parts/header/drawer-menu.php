<?php
/**
 * Mobile menu drawer: search, primary menu (drill-down panels, global.js), brand logo grid, account + shipping note.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

$ci_brands   = ci_mod( 'menu_brand_grid' ) ? array_filter( ci_menu_mega_children(), 'ci_menu_item_logo' ) : array();
$ci_shipping = ci_menu_shipping_note();
?>
<dialog class="drawer drawer--menu" id="drawer-menu" data-drawer="menu" aria-label="<?php esc_attr_e( 'Menu', 'customimpulse' ); ?>">
	<div class="drawer__inner">
		<div class="drawer__header">
			<button type="button" class="drawer__close" data-drawer-close autofocus>
				<?php echo ci_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="visually-hidden"><?php esc_html_e( 'Close menu', 'customimpulse' ); ?></span>
			</button>
		</div>
		<nav class="mobile-nav" aria-label="<?php esc_attr_e( 'Main', 'customimpulse' ); ?>" data-label-back="<?php esc_attr_e( 'Back', 'customimpulse' ); ?>" data-label-all="<?php esc_attr_e( 'View all', 'customimpulse' ); ?>">
			<div class="mobile-nav__panel mobile-nav__root">
				<a class="mobile-nav__search" href="<?php echo esc_url( add_query_arg( array( 's' => '', 'post_type' => 'product' ), home_url( '/' ) ) ); ?>" data-search-open>
					<?php echo ci_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span><?php esc_html_e( 'Search products', 'customimpulse' ); ?></span>
				</a>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'mobile-nav__list',
						'menu_id'        => 'mobile-nav-list',
						'depth'          => 3,
						'fallback_cb'    => false,
						'walker'         => new CI_Nav_Walker( 'mobile' ),
					)
				);
				?>
				<?php if ( $ci_brands ) : ?>
					<div class="mobile-nav__brands">
						<p class="mobile-nav__title"><?php esc_html_e( 'Shop by brand', 'customimpulse' ); ?></p>
						<ul class="mobile-nav__brand-grid" role="list">
							<?php foreach ( $ci_brands as $ci_item ) : ?>
								<li>
									<a href="<?php echo esc_url( $ci_item->url ); ?>">
										<?php echo ci_menu_logo_img( ci_menu_item_logo( $ci_item ), 30, 26, 72, $ci_item->title, '' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
						<?php echo ci_menu_view_all( ci_menu_mega_item(), 'mobile-nav__brands-all' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				<?php endif; ?>
				<div class="mobile-nav__footer">
					<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
						<a class="mobile-nav__account" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
							<?php echo ci_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<?php is_user_logged_in() ? esc_html_e( 'Account', 'customimpulse' ) : esc_html_e( 'Log in', 'customimpulse' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $ci_shipping ) : ?>
						<p class="mobile-nav__shipping"><?php echo ci_icon( 'truck' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $ci_shipping ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</nav>
	</div>
</dialog>
