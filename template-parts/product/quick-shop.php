<?php
/**
 * Quick Shop content (loaded into the quick shop modal by cart.js).
 * Reuses the product page hooks: price, variation buttons, selling points, trust badges.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

/** @var WC_Product $ci_product */
$ci_product = $args['product'];
$ci_images  = array_values( array_filter( array_merge( array( $ci_product->get_image_id() ), $ci_product->get_gallery_image_ids() ) ) );
$ci_guide   = ci_product_size_guide( $ci_product );
?>
<div class="quick-shop<?php echo ci_mod( 'product_qty_enable' ) ? '' : ' quick-shop--no-qty'; ?>" data-product-scope>
	<div class="quick-shop__media">
		<?php if ( count( $ci_images ) > 1 ) : ?>
			<ul class="quick-shop__thumbs">
				<?php foreach ( $ci_images as $i => $ci_image_id ) : ?>
					<li>
						<button type="button" class="quick-shop__thumb<?php echo 0 === $i ? ' is-active' : ''; ?>" data-qs-thumb="<?php echo esc_attr( wp_get_attachment_image_url( $ci_image_id, 'woocommerce_single' ) ); ?>" data-qs-srcset="<?php echo esc_attr( (string) wp_get_attachment_image_srcset( $ci_image_id, 'woocommerce_single' ) ); ?>">
							<?php echo wp_get_attachment_image( $ci_image_id, 'woocommerce_gallery_thumbnail', false, array( 'alt' => '', 'loading' => 'lazy' ) ); ?>
							<span class="visually-hidden">
								<?php
								/* translators: %d: image number */
								echo esc_html( sprintf( __( 'Show image %d', 'customimpulse' ), $i + 1 ) );
								?>
							</span>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<div class="quick-shop__main">
			<?php
			echo $ci_images
				? wp_get_attachment_image( $ci_images[0], 'woocommerce_single', false, array( 'data-qs-main' => '', 'sizes' => '(min-width: 769px) 450px, 90vw', 'loading' => 'eager' ) )
				: wc_placeholder_img( 'woocommerce_single' ); // phpcs:ignore WordPress.Security.EscapeOutput
			?>
		</div>
	</div>

	<div class="quick-shop__summary summary">
		<h2 class="product_title"><?php echo esc_html( $ci_product->get_name() ); ?></h2>
		<div class="product-summary__after-title"><?php do_action( 'ci_product_after_title' ); ?></div>
		<hr class="product-summary__divider">
		<div class="product-price" data-product-price><?php echo ci_price_html( $ci_product ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<?php woocommerce_template_single_add_to_cart(); ?>
		<p class="quick-shop__details"><a class="text-link" href="<?php echo esc_url( $ci_product->get_permalink() ); ?>"><?php esc_html_e( 'View full details', 'customimpulse' ); ?></a></p>
	</div>
</div>
<?php
if ( $ci_guide ) {
	echo ci_size_guide_modal( $ci_guide, 'qs-size-guide-title' ); // phpcs:ignore WordPress.Security.EscapeOutput
}
