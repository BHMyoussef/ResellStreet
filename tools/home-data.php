<?php
/**
 * Homepage data (idempotent, run by seed.sh; also on its own):
 *   tools/wp eval-file tools/home-data.php
 *
 * - Brands (WooCommerce product_brand): the store's brands plus Jordan, Reebok, The North Face (12 tiles);
 *   logo = term thumbnail, imported from reference/media/brands/<slug>.png (tools/brand-logos.mjs).
 * - "Sneakers" category: every shoe that isn't a slide/slipper (first Trending tab).
 * - "trending" tag on one product per model (collection), up to 16.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$ci_logo_dir = dirname( __DIR__ ) . '/reference/media/brands';

/* Brands + logos */
$ci_brands = array(
	'nike'           => 'Nike',
	'adidas'         => 'Adidas',
	'new-balance'    => 'New Balance',
	'asics'          => 'ASICS',
	'hoka'           => 'Hoka',
	'on'             => 'On',
	'jordan'         => 'Jordan',
	'puma'           => 'Puma',
	'brooks'         => 'Brooks',
	'ugg'            => 'UGG',
	'reebok'         => 'Reebok',
	'the-north-face' => 'The North Face',
);
foreach ( $ci_brands as $slug => $name ) {
	$term = get_term_by( 'slug', $slug, 'product_brand' );
	if ( ! $term ) {
		$res = wp_insert_term( $name, 'product_brand', array( 'slug' => $slug ) );
		if ( is_wp_error( $res ) ) {
			WP_CLI::warning( "{$name}: " . $res->get_error_message() );
			continue;
		}
		$term = get_term( $res['term_id'], 'product_brand' );
		WP_CLI::log( "Brand created: {$name}" );
	}
	$file = "{$ci_logo_dir}/{$slug}.png";
	if ( ! file_exists( $file ) ) {
		WP_CLI::warning( "No logo for {$name} ({$file})" );
		continue;
	}
	// One attachment per logo file version (re-imported when the PNG changes).
	$hash     = md5_file( $file );
	$existing = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_query'  => array( array( 'key' => '_ci_brand_logo', 'value' => "{$slug}:{$hash}" ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	$id = $existing ? (int) $existing[0] : 0;
	if ( ! $id ) {
		$tmp = wp_tempnam( $file );
		copy( $file, $tmp );
		$id = media_handle_sideload( array( 'name' => "brand-{$slug}.png", 'tmp_name' => $tmp ), 0, "{$name} logo" );
		if ( is_wp_error( $id ) ) {
			WP_CLI::warning( "{$name} logo: " . $id->get_error_message() );
			continue;
		}
		update_post_meta( $id, '_ci_brand_logo', "{$slug}:{$hash}" );
		update_post_meta( $id, '_wp_attachment_image_alt', $name );
		// Older versions of this logo (previous PNG) are no longer used.
		foreach ( get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_query'  => array( array( 'key' => '_ci_brand_logo', 'value' => "{$slug}:", 'compare' => 'LIKE' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		) as $old ) {
			if ( (int) $old !== (int) $id && 0 === strpos( (string) get_post_meta( $old, '_ci_brand_logo', true ), "{$slug}:" ) ) {
				wp_delete_attachment( $old, true );
			}
		}
	}
	update_term_meta( $term->term_id, 'thumbnail_id', $id );
}
WP_CLI::success( count( $ci_brands ) . ' brands with logos.' );

/* Sneakers category */
$ci_sneakers = get_term_by( 'slug', 'sneakers', 'product_cat' );
if ( ! $ci_sneakers ) {
	$res         = wp_insert_term( 'Sneakers', 'product_cat', array( 'slug' => 'sneakers' ) );
	$ci_sneakers = get_term( $res['term_id'], 'product_cat' );
}
$ci_shoes = get_posts(
	array(
		'post_type'   => 'product',
		'post_status' => 'publish',
		'numberposts' => -1,
		'fields'      => 'ids',
		'tax_query'   => array( array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => 'shoes', 'include_children' => true ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
	)
);
$ci_added = 0;
foreach ( $ci_shoes as $id ) {
	if ( preg_match( '/slide|slipper|tasman/i', get_the_title( $id ) ) || str_starts_with( get_the_title( $id ), 'Test ' ) ) {
		continue;
	}
	wp_set_object_terms( $id, array( $ci_sneakers->term_id ), 'product_cat', true );
	++$ci_added;
}
WP_CLI::success( "Sneakers: {$ci_added} products." );

/* Trending tag: one product per model (deepest category), newest first, up to 16 */
$ci_seen  = array();
$ci_trend = array();
foreach ( $ci_shoes as $id ) {
	if ( count( $ci_trend ) >= 16 || ! has_term( $ci_sneakers->term_id, 'product_cat', $id ) ) {
		continue;
	}
	$cats  = wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'ids' ) );
	$model = max( array_diff( $cats, array( $ci_sneakers->term_id ) ) ?: array( 0 ) );
	if ( isset( $ci_seen[ $model ] ) ) {
		continue;
	}
	$ci_seen[ $model ] = true;
	$ci_trend[]        = $id;
}
foreach ( $ci_trend as $id ) {
	wp_set_object_terms( $id, 'trending', 'product_tag', true );
}
WP_CLI::success( count( $ci_trend ) . ' products tagged "trending".' );
