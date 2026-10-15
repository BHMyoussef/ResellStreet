<?php
/**
 * Template Name: Order confirmation (from link)
 *
 * Copy of the order-received page that takes the order from the ?o= link parameter instead of the database
 * (WooCommerce → Settings → Advanced → Redirect after order; inc/order-redirect.php). Same minimal checkout header,
 * markup and styles as checkout/order-received.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'wc-page-shell page-width section' ); ?>>
		<h1 class="visually-hidden"><?php the_title(); ?></h1>
		<div class="page-content">
			<div class="woocommerce">
				<?php
				get_template_part(
					'template-parts/order/confirmation',
					null,
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, signed token.
					array( 'data' => function_exists( 'rs_redirect_decode' ) ? rs_redirect_decode( isset( $_GET['o'] ) ? sanitize_text_field( wp_unslash( $_GET['o'] ) ) : '' ) : null )
				);
				?>
			</div>
		</div>
	</article>
	<?php
endwhile;

get_footer();
