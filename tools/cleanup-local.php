<?php
/**
 * DEV ONLY: clear test/sample catalogue data from the local site before the real import.
 *   tools/wp eval-file tools/cleanup-local.php
 * Back up first: tools/wp db export backup.sql
 *
 * Removes: all products + variations (and their reviews), images that belonged to products or came from fixtures /
 * earlier imports, all orders, coupon TEST10, user ci-test-customer, attributes pa_shoe-size-uk / pa_size-usa,
 * all Color/Size terms, product tags, and empty sample/import categories.
 * Keeps: pages, menus, Customizer content, seed media (_ci_seed: logo, hero, tiles, banner, avatars), the categories
 * used by the homepage tiles (Shoes, Accessories, Sets, Bags + parent Clothing), settings.
 */

defined( 'ABSPATH' ) || exit;

$log = fn( $msg ) => WP_CLI::log( $msg );

/* Products, variations (reviews go with them). */
$ids = get_posts(
	array(
		'post_type'   => array( 'product', 'product_variation' ),
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);
foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}
$log( 'Deleted products + variations: ' . count( $ids ) );

/* Images: orphaned (parent product gone), Shopify imports, fixture downloads. Seed media and settings images stay. */
$keep = array_filter(
	array(
		(int) get_theme_mod( 'custom_logo' ),
		(int) get_option( 'woocommerce_placeholder_image' ),
	)
);
foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'ids' ) ) as $term_id ) {
	$keep[] = (int) get_term_meta( $term_id, 'thumbnail_id', true );
}
$fixture_images = array_map( 'intval', (array) get_option( 'ci_fixture_images', array() ) );
$deleted        = 0;
foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1 ) ) as $att ) {
	if ( in_array( $att->ID, $keep, true ) || get_post_meta( $att->ID, '_ci_seed', true ) ) {
		continue;
	}
	$orphan  = $att->post_parent && ! get_post( $att->post_parent );
	$shopify = (bool) get_post_meta( $att->ID, '_shopify_image_src', true );
	// WooCommerce sample-data product images are imported unattached; CusRev test uploads too.
	$file   = basename( (string) get_attached_file( $att->ID ) );
	$sample = (bool) preg_match( '/^(pennant|logo|beanie|beanie-with-logo|t-shirt-with-logo|single|album|polo|long-sleeve-tee|hoodie|hoodie-with-zipper|hoodie-with-pocket|hoodie-with-logo|hoodie-green|hoodie-blue|sunglasses|cap|belt|tshirt|vnech-tee-blue|vnech-tee-green|vneck-tee|avatar-\d+)-\d+\./', $file );
	if ( $orphan || $shopify || $sample || in_array( $att->ID, $fixture_images, true ) ) {
		wp_delete_attachment( $att->ID, true );
		++$deleted;
	}
}
delete_option( 'ci_fixture_images' );
$log( "Deleted images: {$deleted}" );

/* Orders (all test orders), coupon, test customer. */
$orders = wc_get_orders( array( 'limit' => -1, 'status' => array_keys( wc_get_order_statuses() ) ) );
foreach ( $orders as $order ) {
	$order->delete( true );
}
$log( 'Deleted orders: ' . count( $orders ) );
$coupon = wc_get_coupon_id_by_code( 'TEST10' );
if ( $coupon ) {
	wp_delete_post( $coupon, true );
	$log( 'Deleted coupon TEST10' );
}
$user = get_user_by( 'login', 'ci-test-customer' );
if ( $user ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $user->ID );
	$log( 'Deleted user ci-test-customer' );
}

/* Attributes: drop the old per-system size attributes; empty Color/Size so the import starts clean. */
foreach ( wc_get_attribute_taxonomies() as $attribute ) {
	$taxonomy = 'pa_' . $attribute->attribute_name;
	if ( in_array( $attribute->attribute_name, array( 'shoe-size-uk', 'size-usa' ), true ) ) {
		wc_delete_attribute( (int) $attribute->attribute_id );
		$log( "Deleted attribute {$taxonomy}" );
		continue;
	}
	if ( in_array( $attribute->attribute_name, array( 'color', 'size' ), true ) && taxonomy_exists( $taxonomy ) ) {
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );
		foreach ( $terms as $term_id ) {
			wp_delete_term( $term_id, $taxonomy );
		}
		$log( "Emptied {$taxonomy}: " . count( $terms ) . ' terms' );
	}
}

/* Tags + empty categories that the homepage doesn't use. */
foreach ( get_terms( array( 'taxonomy' => 'product_tag', 'hide_empty' => false, 'fields' => 'ids' ) ) as $term_id ) {
	wp_delete_term( $term_id, 'product_tag' );
}
$tile_cats = array();
for ( $i = 1; $i <= 8; $i++ ) {
	$tile_cats[] = (int) get_theme_mod( "home_tile_{$i}_cat" );
}
$protected = array_merge( $tile_cats, array( (int) get_option( 'default_product_cat' ) ) );
foreach ( $tile_cats as $cat ) {
	$protected = array_merge( $protected, get_ancestors( $cat, 'product_cat', 'taxonomy' ) ); // e.g. Clothing (parent of Accessories)
}
foreach ( array( 'decor', 'music', 'hoodies', 'tshirts', 'sneakers', 'sweaters', 'bomber-jackets' ) as $slug ) {
	$term = get_term_by( 'slug', $slug, 'product_cat' );
	if ( $term && ! in_array( (int) $term->term_id, $protected, true ) ) {
		wp_delete_term( $term->term_id, 'product_cat' );
		$log( "Deleted category {$term->name}" );
	}
}

/* Counts + caches. */
wc_delete_product_transients();
wc_recount_all_terms();
update_option( 'ci_reviews_cache_version', (int) get_option( 'ci_reviews_cache_version', 1 ) + 1, false );
WP_CLI::success( 'Local catalogue cleaned.' );
