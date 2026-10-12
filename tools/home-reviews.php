<?php
/**
 * Customer reviews for the homepage "Hear from our customers" (Products → Video reviews), from the media the
 * store owner prepared in reference/homepage-v2/assets/reviews (copied to reference/media/home-v2/reviews;
 * video posters = a frame of each clip, "<clip>-poster.jpg").
 * Idempotent (keyed by file name): re-running doesn't duplicate and keeps edits made in the admin.
 * Names and ratings are left empty on purpose (not invented): add them in the admin.
 *
 *   tools/wp eval-file tools/home-reviews.php --user=1
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$ci_dir = dirname( __DIR__ ) . '/reference/media/home-v2/reviews';

// Display order: [media file, product SKU ('' = none)]. Videos alternate with photos.
$ci_reviews = array(
	array( '6bcb0472-de72-4d53-b0c7-8af050b7d339.mp4', '' ),
	array( '0147bf0329d373cd2b0752250e91dbd0.jpg', '' ),
	array( 'fd6c689d668223212bd8a5153a0b7330.jpg', 'ads-gazlle-x-gcci-gg-monogram-beige-edition' ),
	array( '718af4c2-f18e-4f11-8eed-5cbbda009c8f.mp4', '' ),
	array( 'ras_1721505704_3416559935508077171_64159681757.jpg', '' ),
	array( '1ea9cc6ebc2910e3f57319d72cb68346.jpg', '' ),
	array( '6499bbaecff6bca4edec7b2601ffb2a8.jpg', '' ),
	array( '55b0aa5424fd816677255eebbe7ced27.jpg', '' ),
);

/**
 * Attachment for a local file, imported once (tagged _ci_seed = file name).
 *
 * @param string $file Path.
 */
$ci_media = function ( $file ) {
	$key      = basename( $file );
	$existing = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_ci_seed', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	if ( $existing ) {
		return (int) $existing[0];
	}
	if ( ! file_exists( $file ) ) {
		WP_CLI::warning( "Missing {$file}" );
		return 0;
	}
	$tmp = wp_tempnam( $key );
	copy( $file, $tmp );
	$id = media_handle_sideload( array( 'name' => $key, 'tmp_name' => $tmp ), 0, __( 'Customer review', 'customimpulse' ) );
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( "{$key}: " . $id->get_error_message() );
		return 0;
	}
	update_post_meta( $id, '_ci_seed', $key );
	return (int) $id;
};

$ci_created = 0;
foreach ( $ci_reviews as $ci_i => list( $ci_file, $ci_sku ) ) {
	$existing = get_posts(
		array(
			'post_type'   => 'ci_video_review',
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_ci_seed_review', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => $ci_file, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	if ( $existing ) {
		continue; // keep admin edits (name, rating, product, order)
	}
	$is_video = str_ends_with( $ci_file, '.mp4' );
	$poster   = $ci_media( "{$ci_dir}/" . ( $is_video ? preg_replace( '/\.mp4$/', '-poster.jpg', $ci_file ) : $ci_file ) );
	if ( ! $poster ) {
		continue;
	}
	$id = wp_insert_post(
		array(
			'post_type'   => 'ci_video_review',
			'post_status' => 'publish',
			'post_title'  => '',
			'menu_order'  => $ci_i,
		)
	);
	set_post_thumbnail( $id, $poster );
	update_post_meta( $id, '_ci_video_id', $is_video ? $ci_media( "{$ci_dir}/{$ci_file}" ) : 0 );
	update_post_meta( $id, '_ci_video_url', '' );
	update_post_meta( $id, '_ci_rating', 0 );
	update_post_meta( $id, '_ci_product', $ci_sku ? (int) wc_get_product_id_by_sku( $ci_sku ) : 0 );
	update_post_meta( $id, '_ci_seed_review', $ci_file );
	++$ci_created;
}
WP_CLI::success( "{$ci_created} customer review cards created (" . count( $ci_reviews ) . ' in total). Add names/ratings/products in Products → Video reviews.' );
