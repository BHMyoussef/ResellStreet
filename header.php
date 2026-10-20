<?php
/**
 * Site header: sticky header, announcement bar, mobile menu drawer. Opens <main>.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

$ci_cart_count = ci_cart_count();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'customimpulse' ); ?></a>
<?php
// Checkout: minimal header, like Shopify's hosted checkout.
if ( function_exists( 'ci_is_minimal_checkout' ) && ci_is_minimal_checkout() ) {
	get_template_part( 'template-parts/header/checkout' );
	echo '<main id="main" class="site-main" tabindex="-1">';
	return;
}
?>

<header class="site-header" id="site-header">
	<div class="site-header__inner page-width">
		<?php $ci_logo_tag = is_front_page() ? 'h1' : 'div'; ?>
		<<?php echo esc_html( $ci_logo_tag ); ?> class="site-header__logo">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<?php echo ci_logo( 'header' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in ci_logo(). ?>
			</a>
		</<?php echo esc_html( $ci_logo_tag ); ?>>

		<nav class="site-nav" aria-label="<?php esc_attr_e( 'Main', 'customimpulse' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'site-nav__list',
					'menu_id'        => 'site-nav-list',
					'depth'          => 3,
					'fallback_cb'    => false,
					'walker'         => new CI_Nav_Walker( 'desktop' ),
				)
			);
			?>
		</nav>

		<div class="site-header__icons">
			<a class="header-icon header-icon--search" href="<?php echo esc_url( add_query_arg( array( 's' => '', 'post_type' => 'product' ), home_url( '/' ) ) ); ?>" data-search-open>
				<?php echo ci_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="visually-hidden"><?php esc_html_e( 'Search', 'customimpulse' ); ?></span>
			</a>
			<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
				<a class="header-icon header-icon--account" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
					<?php echo ci_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="visually-hidden"><?php is_user_logged_in() ? esc_html_e( 'Account', 'customimpulse' ) : esc_html_e( 'Log in', 'customimpulse' ); ?></span>
				</a>
			<?php endif; ?>
			<button type="button" class="header-icon header-icon--menu" data-drawer-open="menu" aria-controls="drawer-menu" aria-expanded="false">
				<?php echo ci_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="visually-hidden"><?php esc_html_e( 'Menu', 'customimpulse' ); ?></span>
			</button>
			<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
				<a class="header-icon header-icon--cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>" data-drawer-open="cart">
					<?php echo ci_icon( 'bag' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="cart-dot" data-cart-dot<?php echo $ci_cart_count ? '' : ' hidden'; ?>></span>
					<span class="visually-hidden">
						<?php
						/* translators: %d: number of items in the cart */
						echo esc_html( sprintf( _n( 'Cart, %d item', 'Cart, %d items', $ci_cart_count, 'customimpulse' ), $ci_cart_count ) );
						?>
					</span>
				</a>
			<?php endif; ?>
		</div>
	</div>
</header>

<?php
get_template_part( 'template-parts/header/search' );
get_template_part( 'template-parts/header/announcement' );
get_template_part( 'template-parts/header/drawer-menu' );
?>

<main id="main" class="site-main" tabindex="-1">
