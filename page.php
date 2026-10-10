<?php
/**
 * Default page template. Cart / checkout / account pages get the full page width;
 * the checkout has no title (Shopify-style checkout).
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

get_header();

$ci_wc_page  = function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() );
$ci_checkout = function_exists( 'ci_is_minimal_checkout' ) && ci_is_minimal_checkout();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( $ci_wc_page ? 'wc-page-shell page-width section' : 'page-width page-width--narrow section' ); ?>>
		<?php if ( ! $ci_checkout ) : ?>
			<header class="section-header">
				<h1 class="page-title"><?php the_title(); ?></h1>
			</header>
		<?php endif; ?>
		<div class="<?php echo $ci_wc_page ? 'page-content' : 'rte page-content'; ?>"><?php the_content(); ?></div>
	</article>
	<?php
endwhile;

get_footer();
