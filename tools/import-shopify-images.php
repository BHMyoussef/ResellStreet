<?php
/**
 * Step 2 of the Shopify import: bring the product images into the WordPress media library.
 * Reads what tools/import-shopify.php stored: product meta `_shopify_images` [{src, alt}] and variation meta
 * `_shopify_variant_image`, plus Shopify image links inside product descriptions.
 *
 *   tools/wp eval-file tools/import-shopify-images.php                       # all products still to do
 *   CI_LIMIT=10 tools/wp eval-file tools/import-shopify-images.php           # a batch of 10 products
 *   CI_IMAGES_DIR=/path/to/files tools/wp eval-file tools/import-shopify-images.php
 *        # use local files (matched by the file name in the Shopify URL) instead of downloading
 *   CI_DRY_RUN=1 …                                                          # count only, write nothing
 *   (npm run import:images works too)
 *
 * - Featured image = first Shopify image, gallery = the rest, variation image = its Variant Image.
 * - Description <img> pointing to cdn.shopify.com / *.myshopify.com / <store>/cdn/shop → local copy (srcset removed).
 * - Each image is stored once (meta _shopify_image_src, URL without query string) and reused.
 * - Resumable: finished products get meta `_shopify_images_done`; re-runs skip them (CI_FORCE=1 to redo).
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$ci_limit = (int) getenv( 'CI_LIMIT' );
$ci_dir   = getenv( 'CI_IMAGES_DIR' ) ? rtrim( getenv( 'CI_IMAGES_DIR' ), '/' ) : '';
$ci_dry   = (bool) getenv( 'CI_DRY_RUN' );
$ci_force = (bool) getenv( 'CI_FORCE' );

if ( $ci_dir && ! is_dir( $ci_dir ) ) {
	WP_CLI::error( "CI_IMAGES_DIR not found: {$ci_dir}" );
}

/**
 * File name from a Shopify CDN URL (without query string and Shopify size suffixes like _600x).
 */
function ci_img_basename( $url ) {
	$name = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	return rawurldecode( $name );
}

/**
 * Attachment for an image URL: existing copy, local file (CI_IMAGES_DIR) or download.
 */
function ci_img_attachment( $url, $alt, $product_id, $dir ) {
	$url = preg_match( '#^//#', $url ) ? 'https:' . $url : $url;
	$key = strtok( $url, '?' );
	$found = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_key'    => '_shopify_image_src', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'      => 'ids',
			'numberposts' => 1,
		)
	);
	if ( $found ) {
		return (int) $found[0];
	}

	if ( $dir ) {
		$path = $dir . '/' . ci_img_basename( $key );
		if ( ! file_exists( $path ) ) {
			WP_CLI::warning( '  missing local file: ' . ci_img_basename( $key ) );
			return 0;
		}
		$tmp = wp_tempnam( basename( $path ) );
		copy( $path, $tmp );
		$id = media_handle_sideload( array( 'name' => basename( $path ), 'tmp_name' => $tmp ), $product_id, $alt ? $alt : null );
	} else {
		$id = media_sideload_image( $url, $product_id, $alt ? $alt : null, 'id' );
	}
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( '  image failed (' . ci_img_basename( $key ) . '): ' . $id->get_error_message() );
		return 0;
	}
	update_post_meta( $id, '_shopify_image_src', $key );
	if ( $alt ) {
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	}
	return (int) $id;
}

$query = array(
	'post_type'   => 'product',
	'post_status' => 'any',
	'numberposts' => -1,
	'fields'      => 'ids',
	'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery
		array( 'key' => '_shopify_images', 'compare' => 'EXISTS' ),
	),
	'orderby'     => 'ID',
	'order'       => 'ASC',
);
if ( ! $ci_force ) {
	$query['meta_query'][] = array( 'key' => '_shopify_images_done', 'compare' => 'NOT EXISTS' );
}
$ci_ids = get_posts( $query );
if ( $ci_limit > 0 ) {
	$ci_ids = array_slice( $ci_ids, 0, $ci_limit );
}

$shopify_img = '#<img\b[^>]*\bsrc=["\'](?P<src>(?:https?:)?//(?:cdn\.shopify\.com|[^"\'/]+\.myshopify\.com|[^"\'/]+/cdn/shop)/[^"\']+)["\'][^>]*>#i';

WP_CLI::log( sprintf( '%s images for %d product(s)%s', $ci_dry ? 'DRY RUN:' : 'Importing', count( $ci_ids ), $ci_dir ? " from {$ci_dir}" : ' (download)' ) );
$totals = array( 'images' => 0, 'variations' => 0, 'description' => 0 );

foreach ( $ci_ids as $n => $id ) {
	$product = wc_get_product( $id );
	if ( ! $product ) {
		continue;
	}
	$list       = (array) get_post_meta( $id, '_shopify_images', true );
	$variations = $product instanceof WC_Product_Variable ? $product->get_children() : array();
	$content    = (string) get_post_field( 'post_content', $id );
	preg_match_all( $shopify_img, $content, $desc );

	if ( $ci_dry ) {
		$with_image = array_filter( $variations, fn( $v ) => get_post_meta( $v, '_shopify_variant_image', true ) );
		WP_CLI::log( sprintf( '• %s: %d images, %d variation images, %d in description', $product->get_name(), count( $list ), count( $with_image ), count( $desc['src'] ) ) );
		$totals['images']      += count( $list );
		$totals['variations']  += count( $with_image );
		$totals['description'] += count( $desc['src'] );
		continue;
	}

	// Featured + gallery.
	$attachments = array();
	foreach ( $list as $image ) {
		$aid = ci_img_attachment( $image['src'] ?? '', $image['alt'] ?? '', $id, $ci_dir );
		if ( $aid ) {
			$attachments[] = $aid;
		}
	}
	if ( $attachments ) {
		$product->set_image_id( $attachments[0] );
		$product->set_gallery_image_ids( array_slice( $attachments, 1 ) );
		$product->save();
	}

	// Variation images.
	$done_variations = 0;
	foreach ( $variations as $variation_id ) {
		$src = (string) get_post_meta( $variation_id, '_shopify_variant_image', true );
		if ( '' === $src ) {
			continue;
		}
		$aid = ci_img_attachment( $src, '', $id, $ci_dir );
		if ( $aid ) {
			$variation = wc_get_product( $variation_id );
			$variation->set_image_id( $aid );
			$variation->save();
			++$done_variations;
		}
	}

	// Description images → local copies.
	$replaced = 0;
	if ( $desc['src'] ) {
		$content = preg_replace_callback(
			$shopify_img,
			function ( $m ) use ( $id, $ci_dir, &$replaced ) {
				$aid = ci_img_attachment( html_entity_decode( $m['src'] ), '', $id, $ci_dir );
				if ( ! $aid ) {
					return $m[0];
				}
				++$replaced;
				$tag = str_replace( $m['src'], esc_url( wp_get_attachment_image_url( $aid, 'large' ) ), $m[0] );
				return preg_replace( '/\s(srcset|sizes)=(["\']).*?\2/i', '', $tag );
			},
			$content
		);
		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => $content,
			)
		);
	}

	update_post_meta( $id, '_shopify_images_done', time() );
	wc_delete_product_transients( $id );
	$totals['images']      += count( $attachments );
	$totals['variations']  += $done_variations;
	$totals['description'] += $replaced;
	WP_CLI::log( sprintf( '[%d/%d] %s: %d images, %d variation images, %d in description', $n + 1, count( $ci_ids ), $product->get_name(), count( $attachments ), $done_variations, $replaced ) );
}

WP_CLI::success(
	sprintf(
		'%s %d product images, %d variation images, %d description images.',
		$ci_dry ? 'Would process' : 'Done:',
		$totals['images'],
		$totals['variations'],
		$totals['description']
	)
);
