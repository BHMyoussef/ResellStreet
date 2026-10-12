<?php
/**
 * New arrivals: latest published products (shop card, "New" badge). 4 columns on desktop, swipe row on phones.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

if ( ! ci_mod( 'home_new_enable' ) || ! function_exists( 'wc_get_template_part' ) ) {
	return;
}
$ci_html = ci_home_products(
	array(
		'posts_per_page' => min( 16, max( 2, absint( ci_mod( 'home_new_count' ) ) ) ),
		'orderby'        => 'date',
		'order'          => 'DESC',
	),
	'home-products--carousel',
	array( 'ci_badge' => 'new' )
);
if ( '' === $ci_html ) {
	return;
}
$ci_link = (string) ci_mod( 'home_new_link_url' );
if ( '' === $ci_link ) {
	$ci_link = add_query_arg( 'orderby', 'date', wc_get_page_permalink( 'shop' ) );
}
?>
<section class="home-section home-new" aria-labelledby="home-new-title">
	<div class="home-width">
		<?php
		ci_home_heading(
			'home_new',
			array(
				'id'         => 'home-new-title',
				'link_label' => (string) ci_mod( 'home_new_link_label' ),
				'link_url'   => $ci_link,
			)
		);
		echo $ci_html; // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce card templates.
		?>
	</div>
</section>
