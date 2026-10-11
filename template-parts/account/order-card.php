<?php
/**
 * One order in the account (Orders page + Overview): number, status, date · items · total, thumbnails, actions.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

/** @var WC_Order $ci_order */
$ci_order   = $args['order'];
$ci_count   = $ci_order->get_item_count() - $ci_order->get_item_count_refunded();
$ci_items   = array_values( $ci_order->get_items() );
$ci_shown   = array_slice( $ci_items, 0, 4 );
$ci_more    = count( $ci_items ) - count( $ci_shown );
$ci_actions = wc_get_account_orders_actions( $ci_order ); // Filterable: plugins add "Track" etc. here.
$ci_view    = $ci_actions['view'] ?? null;
unset( $ci_actions['view'] );
?>
<li class="order-card order-card--<?php echo esc_attr( $ci_order->get_status() ); ?>">
	<div class="order-card__head">
		<h3 class="order-card__title">
			<a href="<?php echo esc_url( $ci_order->get_view_order_url() ); ?>">
				<?php
				/* translators: %s: order number */
				echo esc_html( sprintf( __( 'Order #%s', 'customimpulse' ), $ci_order->get_order_number() ) );
				?>
			</a>
		</h3>
		<?php echo ci_order_status_pill( $ci_order ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the function. ?>
	</div>

	<p class="order-card__meta">
		<time datetime="<?php echo esc_attr( $ci_order->get_date_created()->date( 'c' ) ); ?>"><?php echo esc_html( wc_format_datetime( $ci_order->get_date_created() ) ); ?></time>
		<span aria-hidden="true">·</span>
		<?php
		/* translators: %s: number of items */
		echo esc_html( sprintf( _n( '%s item', '%s items', $ci_count, 'customimpulse' ), number_format_i18n( $ci_count ) ) );
		?>
		<span aria-hidden="true">·</span>
		<span class="order-card__total"><?php echo wp_kses_post( $ci_order->get_formatted_order_total() ); ?></span>
	</p>

	<?php if ( $ci_shown ) : ?>
		<ul class="order-card__thumbs">
			<?php
			foreach ( $ci_shown as $ci_item ) :
				$ci_product = $ci_item instanceof WC_Order_Item_Product ? $ci_item->get_product() : null;
				?>
				<li class="order-card__thumb">
					<?php
					echo $ci_product // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce image HTML.
						? $ci_product->get_image( 'thumbnail', array( 'loading' => 'lazy', 'alt' => $ci_item->get_name() ) )
						: '<span class="order-card__placeholder">' . esc_html( $ci_item->get_name() ) . '</span>';
					?>
				</li>
			<?php endforeach; ?>
			<?php if ( $ci_more > 0 ) : ?>
				<li class="order-card__thumb order-card__thumb--more"><?php echo esc_html( '+' . number_format_i18n( $ci_more ) ); ?></li>
			<?php endif; ?>
		</ul>
	<?php endif; ?>

	<div class="order-card__actions">
		<?php foreach ( $ci_actions as $ci_key => $ci_action ) : ?>
			<a href="<?php echo esc_url( $ci_action['url'] ); ?>" class="btn <?php echo 'pay' === $ci_key ? '' : 'btn--secondary'; ?> order-card__action--<?php echo esc_attr( sanitize_html_class( $ci_key ) ); ?>"
				<?php
				if ( ! empty( $ci_action['aria-label'] ) ) {
					echo ' aria-label="' . esc_attr( $ci_action['aria-label'] ) . '"';
				}
				?>
			><?php echo esc_html( $ci_action['name'] ); ?></a>
		<?php endforeach; ?>
		<?php if ( $ci_view ) : ?>
			<a href="<?php echo esc_url( $ci_view['url'] ); ?>" class="btn btn--secondary order-card__action--view"><?php esc_html_e( 'View order', 'customimpulse' ); ?></a>
		<?php endif; ?>
	</div>
</li>
