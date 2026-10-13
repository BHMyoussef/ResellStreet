<?php
/**
 * Writes the theme's starter settings (assets/starter/settings.json) from this site's Customizer settings:
 *   tools/wp eval-file tools/starter-export.php
 *
 * Imported by inc/starter.php on sites where the settings are missing. Site-specific values become portable:
 * image IDs → file name in assets/starter (from the seed's `_ci_seed` meta), brand/category IDs → slug,
 * site URLs → {shop} / {home}. Images are not copied: assets/starter/*.jpg are the seed media resized
 * (sips -Z 2000 -s format jpeg -s formatOptions 70), brands/*.png = reference/media/brands.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

$ci_skip = array( 'nav_menu_locations', 'custom_css_post_id', 'custom_logo', 'sidebars_widgets' );
$ci_shop = function_exists( 'wc_get_page_id' ) ? (string) get_permalink( wc_get_page_id( 'shop' ) ) : '';
$ci_home = untrailingslashit( home_url() );
$ci_out  = array();

foreach ( get_theme_mods() as $key => $value ) {
	if ( in_array( $key, $ci_skip, true ) || is_array( $value ) ) {
		continue;
	}
	if ( preg_match( '/_image(_mobile)?$/', $key ) ) {
		$file = $value ? basename( (string) get_post_meta( (int) $value, '_ci_seed', true ) ) : '';
		if ( $value && ! $file ) {
			WP_CLI::warning( "{$key}: attachment {$value} has no seed file, skipped." );
			continue;
		}
		$ci_out[ $key ] = $file ? array( 'image' => $file ) : 0;
		continue;
	}
	if ( preg_match( '/^home_brand_\d+$|^home_trending_\d+_cat$/', $key ) ) {
		$term = $value ? get_term( (int) $value ) : null;
		if ( $term && ! is_wp_error( $term ) ) {
			$ci_out[ $key ] = array(
				'term' => $term->taxonomy,
				'slug' => $term->slug,
			);
		}
		continue;
	}
	if ( is_string( $value ) ) {
		if ( $ci_shop && 0 === strpos( $value, $ci_shop ) ) {
			$value = '{shop}' . substr( $value, strlen( $ci_shop ) );
		} elseif ( 0 === strpos( $value, $ci_home ) ) {
			$value = '{home}' . substr( $value, strlen( $ci_home ) );
		}
	}
	$ci_out[ $key ] = $value;
}
ksort( $ci_out );

$ci_file = dirname( __DIR__ ) . '/assets/starter/settings.json';
file_put_contents( $ci_file, wp_json_encode( $ci_out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
WP_CLI::success( count( $ci_out ) . " settings written to {$ci_file}" );
