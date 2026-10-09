<?php
/**
 * 404.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="page-width page-width--narrow section text-center">
	<h1><?php esc_html_e( 'Page not found', 'customimpulse' ); ?></h1>
	<p><?php esc_html_e( 'The page you were looking for does not exist.', 'customimpulse' ); ?></p>
	<p class="u-mt-30">
		<a class="btn" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>"><?php esc_html_e( 'Continue shopping', 'customimpulse' ); ?></a>
	</p>
</div>
<?php
get_footer();
