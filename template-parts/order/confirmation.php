<?php
/**
 * Order confirmation from link data: same markup and classes as WooCommerce's checkout/thankyou.php,
 * order/order-details.php and order/order-details-customer.php (+ the theme's summary-item thumbnails), so
 * wc-pages.css styles it like the order-received page. Every value comes from the token and is escaped here.
 *
 * @package CustomImpulse
 *
 * @var array $args { @type array|null $data Decoded token (null = missing/invalid link). }
 */

defined( 'ABSPATH' ) || exit;

$data     = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$number   = rs_order_copy_str( $data, 'n' );
$date     = (int) rs_order_copy_str( $data, 'd' );
$payment  = rs_order_copy_str( $data, 'pm' );
$email    = rs_order_copy_str( $data, 'e' );
$phone    = rs_order_copy_str( $data, 'ph' );
$ship_tel = rs_order_copy_str( $data, 'sp' );
$items    = isset( $data['i'] ) && is_array( $data['i'] ) ? $data['i'] : array();
$totals   = isset( $data['t'] ) && is_array( $data['t'] ) ? $data['t'] : array();
$billing  = rs_order_copy_address( $data['b'] ?? null );
$shipping = rs_order_copy_address( $data['s'] ?? null );

$order_total = '';
foreach ( $totals as $row ) {
	if ( is_array( $row ) && ! empty( $row[2] ) && isset( $row[1] ) && is_scalar( $row[1] ) ) {
		$order_total = (string) $row[1];
	}
}
?>
<div class="woocommerce-order">

	<p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received">
		<?php echo esc_html( apply_filters( 'woocommerce_thankyou_order_received_text', __( 'Thank you. Your order has been received.', 'customimpulse' ), false ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>
	</p>

	<?php if ( $number || $date || $order_total || $payment ) : ?>
		<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
			<?php if ( $number ) : ?>
				<li class="woocommerce-order-overview__order order">
					<?php esc_html_e( 'Order number:', 'customimpulse' ); ?>
					<strong><?php echo esc_html( $number ); ?></strong>
				</li>
			<?php endif; ?>
			<?php if ( $date ) : ?>
				<li class="woocommerce-order-overview__date date">
					<?php esc_html_e( 'Date:', 'customimpulse' ); ?>
					<strong><?php echo esc_html( wp_date( wc_date_format(), $date ) ); ?></strong>
				</li>
			<?php endif; ?>
			<?php if ( $order_total ) : ?>
				<li class="woocommerce-order-overview__total total">
					<?php esc_html_e( 'Total:', 'customimpulse' ); ?>
					<strong><?php echo rs_order_copy_prices( $order_total ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in rs_order_copy_prices(). ?></strong>
				</li>
			<?php endif; ?>
			<?php if ( $payment ) : ?>
				<li class="woocommerce-order-overview__payment-method method">
					<?php esc_html_e( 'Payment method:', 'customimpulse' ); ?>
					<strong><?php echo esc_html( $payment ); ?></strong>
				</li>
			<?php endif; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $items || $totals ) : ?>
		<section class="woocommerce-order-details">
			<h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Order details', 'customimpulse' ); ?></h2>
			<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
				<thead>
					<tr>
						<th class="woocommerce-table__product-name product-name"><?php esc_html_e( 'Product', 'customimpulse' ); ?></th>
						<th class="woocommerce-table__product-table product-total"><?php esc_html_e( 'Total', 'customimpulse' ); ?></th>
					</tr>
				</thead>
				<?php if ( $items ) : ?>
					<tbody>
						<?php
						foreach ( $items as $item ) :
							if ( ! is_array( $item ) ) {
								continue;
							}
							$name    = rs_order_copy_str( $item, 'n' );
							$product = ! empty( $item['p'] ) ? wc_get_product( absint( $item['p'] ) ) : null;
							$link    = $product && $product->is_visible() ? $product->get_permalink() : '';
							$thumb   = $product ? $product->get_image( 'thumbnail', array( 'class' => 'summary-item__img', 'loading' => 'lazy', 'alt' => '' ) ) : '';
							$meta    = isset( $item['m'] ) && is_array( $item['m'] ) ? $item['m'] : array();
							?>
							<tr class="woocommerce-table__line-item order_item">
								<td class="woocommerce-table__product-name product-name">
									<span class="summary-item">
										<span class="summary-item__media"><?php echo wp_kses_post( $thumb ); ?><span class="summary-item__qty" aria-label="<?php esc_attr_e( 'Quantity', 'customimpulse' ); ?>"><?php echo (int) ( $item['q'] ?? 1 ); ?></span></span>
										<span class="summary-item__name">
											<?php if ( $link ) : ?>
												<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $name ); ?></a>
											<?php else : ?>
												<?php echo esc_html( $name ); ?>
											<?php endif; ?>
										</span>
									</span>
									<?php if ( $meta ) : ?>
										<ul class="wc-item-meta">
											<?php foreach ( $meta as $pair ) : ?>
												<?php if ( is_array( $pair ) && isset( $pair[0], $pair[1] ) && is_scalar( $pair[0] ) && is_scalar( $pair[1] ) ) : ?>
													<li><strong class="wc-item-meta-label"><?php echo esc_html( $pair[0] ); ?>:</strong> <p><?php echo esc_html( $pair[1] ); ?></p></li>
												<?php endif; ?>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</td>
								<td class="woocommerce-table__product-total product-total"><?php echo rs_order_copy_prices( rs_order_copy_str( $item, 't' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in rs_order_copy_prices(). ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				<?php endif; ?>
				<?php if ( $totals || $payment ) : ?>
					<tfoot>
						<?php foreach ( $totals as $row ) : ?>
							<?php if ( is_array( $row ) && isset( $row[0], $row[1] ) && is_scalar( $row[0] ) && is_scalar( $row[1] ) ) : ?>
								<tr>
									<th scope="row"><?php echo esc_html( $row[0] ); ?></th>
									<?php // One line: whitespace before "&nbsp;via …" would add a space WooCommerce doesn't have. ?>
									<td><?php echo rs_order_copy_prices( $row[1] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in rs_order_copy_prices(). ?><?php echo isset( $row[3] ) && is_scalar( $row[3] ) && '' !== (string) $row[3] ? '&nbsp;<small class="shipped_via">' . esc_html( $row[3] ) . '</small>' : ''; ?></td>
								</tr>
							<?php endif; ?>
						<?php endforeach; ?>
						<?php if ( $payment ) : ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Payment method:', 'customimpulse' ); ?></th>
								<td><?php echo esc_html( $payment ); ?></td>
							</tr>
						<?php endif; ?>
					</tfoot>
				<?php endif; ?>
			</table>
		</section>
	<?php endif; ?>

	<?php if ( $billing || $shipping || $email || $phone ) : ?>
		<section class="woocommerce-customer-details">
			<?php if ( $shipping ) : ?>
				<section class="woocommerce-columns woocommerce-columns--2 woocommerce-columns--addresses col2-set addresses">
					<div class="woocommerce-column woocommerce-column--1 woocommerce-column--billing-address col-1">
			<?php endif; ?>

			<?php if ( $billing || $email || $phone ) : ?>
				<h2 class="woocommerce-column__title"><?php echo $billing ? esc_html__( 'Billing address', 'customimpulse' ) : esc_html__( 'Contact', 'customimpulse' ); ?></h2>
				<address>
					<?php echo wp_kses_post( $billing ); ?>
					<?php if ( $phone ) : ?>
						<p class="woocommerce-customer-details--phone"><?php echo esc_html( $phone ); ?></p>
					<?php endif; ?>
					<?php if ( $email ) : ?>
						<p class="woocommerce-customer-details--email"><?php echo esc_html( $email ); ?></p>
					<?php endif; ?>
				</address>
			<?php endif; ?>

			<?php if ( $shipping ) : ?>
					</div><!-- /.col-1 -->
					<div class="woocommerce-column woocommerce-column--2 woocommerce-column--shipping-address col-2">
						<h2 class="woocommerce-column__title"><?php esc_html_e( 'Shipping address', 'customimpulse' ); ?></h2>
						<address>
							<?php echo wp_kses_post( $shipping ); ?>
							<?php if ( $ship_tel ) : ?>
								<p class="woocommerce-customer-details--phone"><?php echo esc_html( $ship_tel ); ?></p>
							<?php endif; ?>
						</address>
					</div><!-- /.col-2 -->
				</section><!-- /.col2-set -->
			<?php endif; ?>
		</section>
	<?php endif; ?>

</div>
