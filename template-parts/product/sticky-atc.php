<?php
/**
 * Sticky add to cart bar (mobile). Shown by product.js once the main button scrolls out of view.
 * Its button clicks the main add to cart button, so validation, AJAX cart and pixel events stay in one place.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

/** @var WC_Product $ci_product */
$ci_product = $args['product'];
?>
<div class="sticky-atc" data-sticky-atc aria-hidden="true" inert>
	<div class="sticky-atc__media">
		<?php echo $ci_product->get_image( 'thumbnail', array( 'loading' => 'lazy', 'alt' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</div>
	<div class="sticky-atc__info">
		<p class="sticky-atc__title"><?php echo esc_html( $ci_product->get_name() ); ?></p>
		<div class="sticky-atc__price" data-sticky-price><?php echo ci_price_html( $ci_product ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
	</div>
	<button type="button" class="btn sticky-atc__button" data-sticky-button>
		<?php echo esc_html( $ci_product->single_add_to_cart_text() ); ?>
	</button>
</div>
