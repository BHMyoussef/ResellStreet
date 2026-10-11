<?php
/**
 * Account overview (replaces WooCommerce's dashboard): greeting, recent orders, default address, account details.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

$ci_user     = wp_get_current_user();
$ci_customer = new WC_Customer( $ci_user->ID );
$ci_name     = trim( $ci_customer->get_first_name() . ' ' . $ci_customer->get_last_name() );
$ci_orders   = ci_account_orders( 1, 3 );
$ci_address  = wc_get_account_formatted_address( 'shipping' ) ?: wc_get_account_formatted_address( 'billing' );
?>
<div class="account-overview">
	<p class="account-overview__greeting">
		<?php
		/* translators: %s: customer first name or display name */
		echo esc_html( sprintf( __( 'Welcome back, %s', 'customimpulse' ), $ci_customer->get_first_name() ?: $ci_user->display_name ) );
		?>
	</p>

	<section class="account-card account-overview__orders" aria-labelledby="ci-overview-orders">
		<header class="account-card__header">
			<h2 class="account-card__title" id="ci-overview-orders"><?php esc_html_e( 'Recent orders', 'customimpulse' ); ?></h2>
			<?php if ( $ci_orders->total > count( $ci_orders->orders ) ) : ?>
				<a class="account-card__link" href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>"><?php esc_html_e( 'View all', 'customimpulse' ); ?></a>
			<?php endif; ?>
		</header>
		<?php if ( $ci_orders->orders ) : ?>
			<ul class="order-cards order-cards--flush">
				<?php
				foreach ( $ci_orders->orders as $ci_order ) {
					get_template_part( 'template-parts/account/order-card', null, array( 'order' => $ci_order ) );
				}
				?>
			</ul>
		<?php else : ?>
			<?php get_template_part( 'template-parts/account/orders-empty' ); ?>
		<?php endif; ?>
	</section>

	<section class="account-card" aria-labelledby="ci-overview-address">
		<header class="account-card__header">
			<h2 class="account-card__title" id="ci-overview-address"><?php esc_html_e( 'Default address', 'customimpulse' ); ?></h2>
			<a class="account-card__link" href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address' ) ); ?>"><?php $ci_address ? esc_html_e( 'Manage', 'customimpulse' ) : esc_html_e( 'Add', 'customimpulse' ); ?></a>
		</header>
		<?php if ( $ci_address ) : ?>
			<address class="account-card__body"><?php echo wp_kses_post( $ci_address ); ?></address>
		<?php else : ?>
			<p class="account-card__body account-card__muted"><?php esc_html_e( 'Save an address to check out faster.', 'customimpulse' ); ?></p>
		<?php endif; ?>
	</section>

	<section class="account-card" aria-labelledby="ci-overview-details">
		<header class="account-card__header">
			<h2 class="account-card__title" id="ci-overview-details"><?php esc_html_e( 'Account details', 'customimpulse' ); ?></h2>
			<a class="account-card__link" href="<?php echo esc_url( wc_get_endpoint_url( 'edit-account' ) ); ?>"><?php esc_html_e( 'Edit', 'customimpulse' ); ?></a>
		</header>
		<p class="account-card__body">
			<?php if ( $ci_name ) : ?>
				<?php echo esc_html( $ci_name ); ?><br>
			<?php endif; ?>
			<?php echo esc_html( $ci_user->user_email ); ?>
		</p>
	</section>

	<?php
	// WooCommerce's dashboard hooks, for plugins that add content there.
	do_action( 'woocommerce_account_dashboard' );
	do_action( 'woocommerce_before_my_account' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks -- core (deprecated) hook.
	do_action( 'woocommerce_after_my_account' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks -- core (deprecated) hook.
	?>
</div>
