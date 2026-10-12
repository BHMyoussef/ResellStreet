<?php
/**
 * Shop by brand: brand logo tiles (WooCommerce Brands, order from the Customizer) linking to the brand pages.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

if ( ! ci_mod( 'home_brands_enable' ) ) {
	return;
}
$ci_tiles = ci_brand_tiles( ci_home_brands() );
if ( '' === $ci_tiles ) {
	return;
}
?>
<section class="home-section home-brands" id="shop-by-brand" aria-labelledby="home-brands-title">
	<div class="home-width">
		<?php
		ci_home_heading(
			'home_brands',
			array(
				'id'         => 'home-brands-title',
				'link_label' => (string) ci_mod( 'home_brands_link_label' ),
				'link_url'   => (string) ci_mod( 'home_brands_link_url' ),
			)
		);
		echo $ci_tiles; // phpcs:ignore WordPress.Security.EscapeOutput -- built with escaping in ci_brand_tiles().
		?>
	</div>
</section>
