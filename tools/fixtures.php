<?php
/**
 * DEV ONLY: test products for the product page until the real Shopify CSV import (step 9).
 *   tools/wp eval-file tools/fixtures.php
 * Idempotent: products are found by SKU and their variations rebuilt; images are imported once.
 *
 * - CI-TEST-SNEAKER     3 colours × 7 sizes = 21 variations (WooCommerce "inline" mode, ≤ 30)
 *   sold out: White/6, White/7.5, Black/10.5 — low stock: Khaki/8 (3 left)
 * - CI-TEST-SNEAKER-XL  3 colours × 11 sizes = 33 variations (WooCommerce AJAX mode, > 30)
 *   sold out: every White, Black/5, Black/5.5
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

/**
 * Global attribute with ordered terms. Returns [taxonomy, slug map label => term slug].
 */
function ci_fx_attribute( $label, $slug, array $terms ) {
	$taxonomy = wc_attribute_taxonomy_name( $slug );
	if ( ! wc_attribute_taxonomy_id_by_name( $taxonomy ) ) {
		wc_create_attribute(
			array(
				'name'     => $label,
				'slug'     => $slug,
				'type'     => 'select',
				'order_by' => 'menu_order',
			)
		);
	}
	if ( ! taxonomy_exists( $taxonomy ) ) {
		register_taxonomy( $taxonomy, array( 'product' ), array( 'hierarchical' => false ) );
	}
	$map = array();
	foreach ( $terms as $i => $name ) {
		$term = get_term_by( 'name', $name, $taxonomy );
		if ( ! $term ) {
			$res  = wp_insert_term( $name, $taxonomy );
			$term = get_term( $res['term_id'], $taxonomy );
		}
		update_term_meta( $term->term_id, 'order', $i );
		$map[ $name ] = $term;
	}
	return array( $taxonomy, $map );
}

function ci_fx_images() {
	$ids = get_option( 'ci_fixture_images' );
	if ( $ids && count( array_filter( array_map( 'get_post', $ids ) ) ) === count( $ids ) ) {
		return $ids;
	}
	$urls = array(
		'https://cdn.shopify.com/s/files/1/0895/0887/2517/files/SCR-20250106-swtw_enhanced.png?v=1736196639',
		'https://cdn.shopify.com/s/files/1/0895/0887/2517/files/product_10105.jpg?v=1736196614',
		'https://cdn.shopify.com/s/files/1/0895/0887/2517/files/product_10104.jpg?v=1736196614',
		'https://cdn.shopify.com/s/files/1/0895/0887/2517/files/SCR-20250106-sxrz_enhanced.png?v=1736196934',
		'https://cdn.shopify.com/s/files/1/0895/0887/2517/files/SCR-20250106-sxwf_enhanced.png?v=1736196967',
	);
	$ids = array();
	foreach ( $urls as $url ) {
		$id = media_sideload_image( $url, 0, 'Test sneaker', 'id' );
		if ( ! is_wp_error( $id ) ) {
			$ids[] = $id;
		} else {
			WP_CLI::warning( $id->get_error_message() );
		}
	}
	update_option( 'ci_fixture_images', $ids, false );
	return $ids;
}

function ci_fx_product( $sku, $name, $category_id, array $color, array $size, array $sold_out, array $low, array $images ) {
	$id      = wc_get_product_id_by_sku( $sku );
	$product = new WC_Product_Variable( $id ? $id : 0 );
	if ( $id ) {
		foreach ( $product->get_children() as $child ) {
			wp_delete_post( $child, true );
		}
	}
	$product->set_name( $name );
	$product->set_sku( $sku );
	$product->set_status( 'publish' );
	$product->set_category_ids( array( $category_id ) );
	$product->set_description(
		"<p><strong>Step Into Style and Comfort</strong><br>Redefine your casual wardrobe with our Men's Casual Leather Sneakers. These sleek, British-inspired sneakers combine elegance and practicality for men who value both style and comfort.</p>\n" .
		"<p><strong>Premium Craftsmanship</strong></p>\n<ul><li><strong>Material</strong>: Made with second-layer cowhide for a luxurious look and feel.</li><li><strong>Durability</strong>: Features a rubber sole for long-lasting wear.</li><li><strong>Design</strong>: Intricate sewing thread details and a low-top silhouette for a timeless style.</li></ul>\n" .
		"<p><strong>Comfort Meets Functionality</strong></p>\n<ul><li><strong>Breathable</strong>: Perfect for keeping your feet fresh all day long.</li><li><strong>Soft Sole</strong>: Provides comfort with every step.</li><li><strong>Fit</strong>: Designed with a true-to-size fit for maximum ease.</li></ul>"
	);
	if ( $images ) {
		$product->set_image_id( $images[0] );
		$product->set_gallery_image_ids( array_slice( $images, 1 ) );
	}

	$attributes = array();
	foreach ( array( $color, $size ) as $position => $attr ) {
		list( $taxonomy, $terms ) = $attr;
		$a = new WC_Product_Attribute();
		$a->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
		$a->set_name( $taxonomy );
		$a->set_options( array_map( fn( $t ) => $t->term_id, array_values( $terms ) ) );
		$a->set_position( $position );
		$a->set_visible( true );
		$a->set_variation( true );
		$attributes[] = $a;
	}
	$product->set_attributes( $attributes );
	$product_id = $product->save();

	foreach ( $color[1] as $color_name => $color_term ) {
		foreach ( $size[1] as $size_name => $size_term ) {
			$key       = "{$color_name}/{$size_name}";
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $product_id );
			$variation->set_attributes(
				array(
					$color[0] => $color_term->slug,
					$size[0]  => $size_term->slug,
				)
			);
			$variation->set_regular_price( '92.99' );
			$variation->set_sale_price( '45.75' );
			if ( in_array( $key, $sold_out, true ) || in_array( "{$color_name}/*", $sold_out, true ) ) {
				$variation->set_stock_status( 'outofstock' );
			} elseif ( isset( $low[ $key ] ) ) {
				$variation->set_manage_stock( true );
				$variation->set_stock_quantity( $low[ $key ] );
			}
			$variation->save();
		}
	}
	WC_Product_Variable::sync( $product_id );
	wc_delete_product_transients( $product_id );
	WP_CLI::success( "{$name} (#{$product_id}): " . count( $color[1] ) * count( $size[1] ) . ' variations' );
	return $product_id;
}

// Category with a size guide.
$shoes = get_term_by( 'slug', 'shoes', 'product_cat' );
if ( ! $shoes ) {
	$res   = wp_insert_term( 'Shoes', 'product_cat', array( 'slug' => 'shoes' ) );
	$shoes = get_term( $res['term_id'], 'product_cat' );
}
$rows  = array( array( '6', '5', '38', '5' ), array( '7', '6', '39', '6' ), array( '7.5', '6.5', '40', '6.5' ), array( '8', '7', '41', '7' ), array( '8.5', '7.5', '42', '7.5' ), array( '9', '8', '43', '8' ), array( '10.5', '9.5', '44', '9.5' ) );
$table = '<table><thead><tr><th>US</th><th>UK</th><th>Europe</th><th>Australia</th></tr></thead><tbody>';
foreach ( $rows as $r ) {
	$table .= '<tr><td>' . implode( '</td><td>', $r ) . '</td></tr>';
}
$table .= '</tbody></table><p>Measure your foot from heel to toe and pick the closest size. Between sizes? Go half a size up.</p>';
update_term_meta( $shoes->term_id, 'ci_size_guide', $table );

$color  = ci_fx_attribute( 'Color', 'color', array( 'Black', 'Khaki', 'White' ) );
$sizes  = ci_fx_attribute( 'Size (USA)', 'size-usa', array( '5', '5.5', '6', '6.5', '7', '7.5', '8', '8.5', '9', '9.5', '10', '10.5' ) );
$subset = fn( array $names ) => array( $sizes[0], array_intersect_key( $sizes[1], array_flip( $names ) ) );
$images = ci_fx_images();

ci_fx_product(
	'CI-TEST-SNEAKER',
	'Test Sneaker - Men\'s Casual Leather Sneakers',
	$shoes->term_id,
	$color,
	$subset( array( '6', '7', '7.5', '8', '8.5', '9', '10.5' ) ),
	array( 'White/6', 'White/7.5', 'Black/10.5' ),
	array( 'Khaki/8' => 3 ),
	$images
);
ci_fx_product(
	'CI-TEST-SNEAKER-XL',
	'Test Sneaker XL - 33 variations',
	$shoes->term_id,
	$color,
	$subset( array( '5', '5.5', '6', '6.5', '7', '7.5', '8', '8.5', '9', '9.5', '10' ) ),
	array( 'White/*', 'Black/5', 'Black/5.5' ),
	array(),
	array_reverse( $images )
);

// ---------------------------------------------------------------------------
// Checkout / account testing (DEV ONLY)
// ---------------------------------------------------------------------------

// A payment method so checkout can be completed locally.
$cod            = get_option( 'woocommerce_cod_settings', array() );
$cod['enabled'] = 'yes';
update_option( 'woocommerce_cod_settings', $cod );

// 10% coupon for the checkout discount field.
if ( ! wc_get_coupon_id_by_code( 'TEST10' ) ) {
	$coupon = new WC_Coupon();
	$coupon->set_code( 'TEST10' );
	$coupon->set_discount_type( 'percent' );
	$coupon->set_amount( 10 );
	$coupon->save();
}

// Customer account for the logged-in cart test (tools/pages-check.mjs).
if ( ! get_user_by( 'login', 'ci-test-customer' ) ) {
	wc_create_new_customer( 'ci-test-customer@example.com', 'ci-test-customer', 'ci-test-Passw0rd!' );
}
WP_CLI::success( 'Checkout fixtures: Cash on delivery enabled, coupon TEST10, customer ci-test-customer.' );
