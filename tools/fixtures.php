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
	// Reuse only when every attachment still has its file (a half-failed download leaves a post without one).
	$ok = fn( $id ) => get_post( $id ) && file_exists( (string) get_attached_file( $id ) );
	if ( $ids && count( array_filter( $ids, $ok ) ) === count( $ids ) ) {
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
// Keep the real guide (tools/size-guide.php) when there is one.
if ( '' === trim( (string) get_term_meta( $shoes->term_id, 'ci_size_guide', true ) ) ) {
	update_term_meta( $shoes->term_id, 'ci_size_guide', $table );
}

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

/*
 * "Test Runner" (product page v2 states): real-store shape = one colourway per product, colour as a plain pa_color
 * attribute, sizes UK 3–13 in halves as the only variation attribute. Mint Blue: 4.5 / 9.5 / 11 sold out, UK 8 low
 * stock (2), on sale. Black and Cream are its sibling colourways (same model category → colour swatches).
 */
function ci_fx_runner( $sku, $colour, $hex, $model_id, $brand, array $images, array $sold_out = array(), array $low = array() ) {
	$size_names = array();
	for ( $s = 3; $s <= 13; $s += 0.5 ) {
		$size_names[] = (string) $s;
	}
	list( $size_tax, $sizes ) = ci_fx_attribute( 'Size', 'size', $size_names );
	list( $color_tax, $colors ) = ci_fx_attribute( 'Color', 'color', array( $colour ) );
	update_term_meta( $colors[ $colour ]->term_id, 'ci_swatch', $hex );

	$id      = wc_get_product_id_by_sku( $sku );
	$product = new WC_Product_Variable( $id ? $id : 0 );
	foreach ( $id ? $product->get_children() : array() as $child ) {
		wp_delete_post( $child, true );
	}
	$product->set_name( "Test Runner - {$colour}" );
	$product->set_sku( $sku );
	$product->set_status( 'publish' );
	$product->set_category_ids( array( $model_id ) );
	$product->set_description( '<p>Built for runners chasing their fastest training days: speed, cushioning and precision in one lightweight package.</p><p>An updated foam midsole gives a responsive ride, while the grippy rubber outsole keeps you planted from tempo runs to marathon blocks.</p>' );
	$product->set_image_id( $images[0] );
	$product->set_gallery_image_ids( array_slice( $images, 1 ) );
	$attributes = array();
	foreach ( array( array( $color_tax, $colors, false ), array( $size_tax, $sizes, true ) ) as $position => list( $taxonomy, $terms, $variation ) ) {
		$a = new WC_Product_Attribute();
		$a->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
		$a->set_name( $taxonomy );
		$a->set_options( array_map( fn( $t ) => $t->term_id, array_values( $terms ) ) );
		$a->set_position( $position );
		$a->set_visible( true );
		$a->set_variation( $variation );
		$attributes[] = $a;
	}
	$product->set_attributes( $attributes );
	$product_id = $product->save();
	if ( taxonomy_exists( 'product_brand' ) ) {
		wp_set_object_terms( $product_id, $brand, 'product_brand' );
	}
	foreach ( $sizes as $name => $term ) {
		$name = (string) $name; // PHP turns the key "11" into int 11
		$v    = new WC_Product_Variation();
		$v->set_parent_id( $product_id );
		$v->set_attributes( array( $size_tax => $term->slug ) );
		$v->set_regular_price( '139.99' );
		$v->set_sale_price( '59.99' );
		if ( in_array( $name, $sold_out, true ) ) {
			$v->set_stock_status( 'outofstock' );
		} elseif ( isset( $low[ $name ] ) ) {
			$v->set_manage_stock( true );
			$v->set_stock_quantity( $low[ $name ] );
		}
		$v->save();
	}
	WC_Product_Variable::sync( $product_id );
	wc_delete_product_transients( $product_id );
	WP_CLI::success( "Test Runner - {$colour} (#{$product_id}): " . count( $sizes ) . ' sizes' );
	return $product_id;
}
$ci_fx_model = get_term_by( 'slug', 'test-runner', 'product_cat' );
if ( ! $ci_fx_model ) {
	$res         = wp_insert_term( 'Test Runner', 'product_cat', array( 'slug' => 'test-runner', 'parent' => $shoes->term_id ) );
	$ci_fx_model = get_term( $res['term_id'], 'product_cat' );
}
ci_fx_runner( 'CI-TEST-RUNNER', 'Mint Blue', '#a8dccf', $ci_fx_model->term_id, 'nike', $images, array( '4.5', '9.5', '11' ), array( '8' => 2 ) );
ci_fx_runner( 'CI-TEST-RUNNER-BLACK', 'Black', '#23262d', $ci_fx_model->term_id, 'nike', array_reverse( $images ) );
ci_fx_runner( 'CI-TEST-RUNNER-CREAM', 'Cream', '#ece8df', $ci_fx_model->term_id, 'nike', array_slice( $images, 2 ) );

// ---------------------------------------------------------------------------
// Image ratios (main image must never be cropped, tools/product-check.mjs), simple + out-of-stock products.
// ---------------------------------------------------------------------------

/**
 * Generated test image (GD): colour fill with a 6% dark frame on every edge, so any crop shows. Imported once,
 * tagged `_ci_fixture_ratio` = "<w>x<h>".
 */
function ci_fx_ratio_image( $w, $h, $rgb ) {
	$key   = "{$w}x{$h}-" . implode( '-', $rgb );
	$found = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_key'    => '_ci_fixture_ratio', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'      => 'ids',
			'numberposts' => 1,
		)
	);
	if ( $found && file_exists( (string) get_attached_file( $found[0] ) ) ) {
		return (int) $found[0];
	}
	$im    = imagecreatetruecolor( $w, $h );
	$frame = max( 4, (int) round( min( $w, $h ) * 0.06 ) );
	imagefill( $im, 0, 0, imagecolorallocate( $im, 28, 37, 59 ) );
	imagefilledrectangle( $im, $frame, $frame, $w - 1 - $frame, $h - 1 - $frame, imagecolorallocate( $im, ...$rgb ) );
	$tmp = wp_tempnam( "ci-ratio-{$key}.jpg" );
	imagejpeg( $im, $tmp, 85 );
	imagedestroy( $im );
	$id = media_handle_sideload( array( 'name' => "ci-ratio-{$key}.jpg", 'tmp_name' => $tmp ), 0, "Test image {$w}×{$h}" );
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( $id->get_error_message() );
		return 0;
	}
	update_post_meta( $id, '_ci_fixture_ratio', $key );
	return (int) $id;
}

/** Simple product, found by SKU. */
function ci_fx_simple( $sku, $name, $category_id, $image_id, $in_stock ) {
	$id      = wc_get_product_id_by_sku( $sku );
	$product = new WC_Product_Simple( $id ? $id : 0 );
	$product->set_name( $name );
	$product->set_sku( $sku );
	$product->set_status( 'publish' );
	$product->set_category_ids( array( $category_id ) );
	$product->set_description( '<p>Test product for the theme checks.</p>' );
	$product->set_regular_price( '24.99' );
	$product->set_image_id( $image_id );
	$product->set_stock_status( $in_stock ? 'instock' : 'outofstock' );
	$product_id = $product->save();
	WP_CLI::success( "{$name} (#{$product_id})" );
	return $product_id;
}

$ci_fx_ratio = array(
	'square'    => ci_fx_ratio_image( 1000, 1000, array( 168, 220, 207 ) ),
	'portrait'  => ci_fx_ratio_image( 800, 1000, array( 236, 232, 223 ) ),   // 4:5
	'tall'      => ci_fx_ratio_image( 750, 1000, array( 193, 165, 132 ) ),   // 3:4
	'landscape' => ci_fx_ratio_image( 1200, 800, array( 200, 210, 230 ) ),   // 3:2
	'small'     => ci_fx_ratio_image( 200, 150, array( 230, 200, 200 ) ),    // very small, 4:3
);
// Main image portrait 4:5, gallery mixed; UK 7 / UK 8 have their own landscape / 3:4 images (variation swap).
$ci_fx_ratio_id = ci_fx_runner(
	'CI-TEST-RATIOS',
	'Ratios',
	'#ece8df',
	$shoes->term_id, // top-level category: no colourway siblings
	'nike',
	array( $ci_fx_ratio['portrait'], $ci_fx_ratio['square'], $ci_fx_ratio['landscape'], $ci_fx_ratio['tall'], $ci_fx_ratio['small'] )
);
$ci_fx_ratios = wc_get_product( $ci_fx_ratio_id );
$ci_fx_ratios->set_name( 'Test Gallery Ratios' );
$ci_fx_ratios->set_slug( 'test-gallery-ratios' );
$ci_fx_ratios->save();
foreach ( $ci_fx_ratios->get_children() as $ci_fx_child ) {
	$ci_fx_var  = wc_get_product( $ci_fx_child );
	$ci_fx_size = $ci_fx_var->get_attribute( 'pa_size' );
	if ( isset( array( '7' => 1, '8' => 1 )[ $ci_fx_size ] ) ) {
		$ci_fx_var->set_image_id( '7' === $ci_fx_size ? $ci_fx_ratio['landscape'] : $ci_fx_ratio['tall'] );
		$ci_fx_var->save();
	}
}
wc_delete_product_transients( $ci_fx_ratio_id );
ci_fx_simple( 'CI-TEST-CAP', 'Test Cap', $shoes->term_id, $ci_fx_ratio['small'], true );
ci_fx_simple( 'CI-TEST-SOLD-OUT', 'Test Sold Out Tee', $shoes->term_id, $ci_fx_ratio['tall'], false );

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

// ---------------------------------------------------------------------------
// Test reviews (DEV ONLY): native WooCommerce reviews stored exactly like CusRev stores them.
//   rating → comment meta `rating`; verified → `verified` (WooCommerce) and/or `ivole_order` (CusRev order ID);
//   photos → one `ivole_review_image2` row per attachment ID + `ivole_media_count`.
// Re-running deletes the previous fixture reviews (tagged `_ci_fixture`) and recreates them.
// ---------------------------------------------------------------------------

/**
 * Review photo from reference/media: its own attachment (tagged `_ci_fixture_media`), never shared with the store's
 * media or product images — CusRev deletes a review's photos when the review is deleted (fixtures re-run).
 */
function ci_fx_local_media( $file ) {
	$found = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_key'    => '_ci_fixture_media', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => $file, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'      => 'ids',
			'numberposts' => 1,
		)
	);
	if ( $found ) {
		return (int) $found[0];
	}
	$path = get_theme_file_path( 'reference/media/' . $file );
	if ( ! file_exists( $path ) ) {
		WP_CLI::warning( "Missing reference/media/{$file}" );
		return 0;
	}
	$tmp = wp_tempnam( $file );
	copy( $path, $tmp );
	$id = media_handle_sideload( array( 'name' => $file, 'tmp_name' => $tmp ), 0, 'Review photo' );
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( $id->get_error_message() );
		return 0;
	}
	update_post_meta( $id, '_ci_fixture_media', $file );
	return (int) $id;
}

// Remove previous fixture reviews.
foreach ( get_comments( array( 'meta_key' => '_ci_fixture', 'type' => 'review', 'status' => 'all' ) ) as $old ) { // phpcs:ignore WordPress.DB.SlowDBQuery
	wp_delete_comment( $old->comment_ID, true );
}

$sneaker    = wc_get_product_id_by_sku( 'CI-TEST-SNEAKER' );
$sneaker_xl = wc_get_product_id_by_sku( 'CI-TEST-SNEAKER-XL' );
$hoodie     = get_page_by_path( 'hoodie', OBJECT, 'product' );
$hoodie     = $hoodie ? $hoodie->ID : 0;
$any_order  = wc_get_orders( array( 'limit' => 1, 'return' => 'ids' ) );
$fx_images  = (array) get_option( 'ci_fixture_images', array() );

$reviews = array(
	// product, stars, name, verified (true | 'order' = CusRev order meta), photos, days ago, text
	array( $sneaker, 5, 'Liam P.', 'order', array( 'avatar-1.jpg', 'tile-shoes.jpg' ), 3, 'The product looks exactly like the photos, maybe even better. Clean stitching, no issues, and arrived quicker than I expected.' ),
	array( $sneaker, 5, 'James R.', true, array( 'avatar-2.jpg' ), 9, 'Honestly wasn’t expecting this level of quality for the price. The fit is perfect and the material feels premium. Will definitely order again.' ),
	array( $sneaker, 4, 'Oliver M.', true, array(), 14, 'Great fit, runs slightly small, go half a size up.' ),
	array( $sneaker, 3, 'Sam T.', false, array(), 21, 'Comfortable, but the laces came loose a lot.' ),
	array( $sneaker_xl, 5, 'Daniel M.', true, array( 'avatar-3.jpg' ), 5, 'Really comfortable and stylish. I’ve worn it a few times now and keep getting compliments. Great value for money.' ),
	array( $sneaker_xl, 5, 'Adam K.', false, array(), 12, 'Sizing was accurate and the shape holds well. The fabric feels durable but still soft. Honestly impressed.' ),
	array( $hoodie, 5, 'Marc T.', true, array( 'avatar-5.jpg' ), 7, 'Super happy with this purchase. Lightweight, stylish, and feels way more expensive than it is. Will be buying more colours.' ),
	array( $hoodie, 2, 'Chris B.', true, array(), 18, 'Colour faded after the first wash.' ),
);

$touched = array();
foreach ( $reviews as $i => list( $product_id, $stars, $name, $verified, $photos, $days, $text ) ) {
	if ( ! $product_id ) {
		WP_CLI::warning( "Review {$i}: product missing (run the product fixtures first)." );
		continue;
	}
	$date       = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
	$comment_id = wp_insert_comment(
		array(
			'comment_post_ID'      => $product_id,
			'comment_author'       => $name,
			'comment_author_email' => sanitize_title( $name ) . '@example.com',
			'comment_content'      => $text,
			'comment_type'         => 'review',
			'comment_approved'     => 1,
			'comment_date'         => get_date_from_gmt( $date ),
			'comment_date_gmt'     => $date,
		)
	);
	add_comment_meta( $comment_id, 'rating', $stars );
	add_comment_meta( $comment_id, 'verified', $verified ? 1 : 0 );
	if ( 'order' === $verified && $any_order ) {
		add_comment_meta( $comment_id, 'ivole_order', $any_order[0] ); // collected through a CusRev review request
	}
	$count = 0;
	foreach ( $photos as $photo ) {
		$attachment = is_int( $photo ) ? $photo : ci_fx_local_media( $photo );
		if ( $attachment ) {
			add_comment_meta( $comment_id, 'ivole_review_image2', $attachment );
			++$count;
		}
	}
	if ( $count ) {
		update_comment_meta( $comment_id, 'ivole_media_count', $count );
	}
	add_comment_meta( $comment_id, '_ci_fixture', $i + 1 );
	$touched[ $product_id ] = true;
}

// Recalculate average rating / review counts.
foreach ( array_keys( $touched ) as $product_id ) {
	WC_Comments::clear_transients( $product_id );
	$p = wc_get_product( $product_id );
	WP_CLI::log( sprintf( '  %s: %s★ from %d reviews', $p->get_name(), $p->get_average_rating(), $p->get_review_count() ) );
}
WP_CLI::success( count( $reviews ) . ' test reviews created.' );

// Test video reviews (homepage "Hear from our customers"): poster = a product photo, video = MDN's CC0 sample clip.
// Names say "Test" so they can't pass for real customers. Re-running replaces them. Only created when the store has no
// video reviews of its own (seed.sh imports the owner's: tools/home-reviews.php).
foreach ( get_posts( array( 'post_type' => 'ci_video_review', 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => '_ci_fixture' ) ) as $old ) { // phpcs:ignore WordPress.DB.SlowDBQuery
	wp_delete_post( $old->ID, true );
}
$ci_fx_has_real = (bool) get_posts( array( 'post_type' => 'ci_video_review', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) );
$ci_fx_video_products = $ci_fx_has_real ? array() : wc_get_products(
	array(
		'status'  => 'publish',
		'limit'   => 6,
		'orderby' => 'date',
		'order'   => 'DESC',
		'tag'     => array( 'trending' ),
	)
);
foreach ( $ci_fx_video_products as $i => $p ) {
	if ( ! $p->get_image_id() ) {
		continue;
	}
	$id = wp_insert_post(
		array(
			'post_type'   => 'ci_video_review',
			'post_status' => 'publish',
			'post_title'  => 'Test reviewer ' . ( $i + 1 ),
			'menu_order'  => 100 + $i, // after the store's own reviews
		)
	);
	set_post_thumbnail( $id, $p->get_image_id() );
	update_post_meta( $id, '_ci_video_url', 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4' );
	update_post_meta( $id, '_ci_rating', 5 );
	update_post_meta( $id, '_ci_product', $p->get_id() );
	update_post_meta( $id, '_ci_fixture', 1 );
}
WP_CLI::success( $ci_fx_has_real ? 'Video reviews: the store has its own, no test ones created.' : count( $ci_fx_video_products ) . ' test video reviews created.' );
