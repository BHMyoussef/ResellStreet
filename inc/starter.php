<?php
/**
 * Starter settings: a new install (or a renamed theme folder, e.g. installed by WP Pusher) gets the homepage and
 * Customizer content of the reference site instead of an empty homepage.
 *
 * Customizer settings are stored per theme folder (option theme_mods_<folder>). Runs once per site (option
 * ci_starter_version), on theme activation or the next admin page load, and only fills settings that are missing:
 *   1. from another folder of this theme in the same database (IDs still valid), e.g. theme_mods_CustomImpulse-theme;
 *   2. from assets/starter/settings.json (written by tools/starter-export.php): images are added to the media
 *      library from assets/starter, brands/categories are matched by slug, {shop} / {home} become this site's URLs.
 * Brands without a logo get the bundled one (assets/starter/brands/<slug>.png).
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

const CI_STARTER_VERSION = 2;

add_action( 'after_switch_theme', 'ci_starter_import' );
add_action(
	'admin_init',
	function () {
		if ( wp_doing_ajax() || ! current_user_can( 'edit_theme_options' ) || (int) get_option( 'ci_starter_version' ) >= CI_STARTER_VERSION ) {
			return;
		}
		ci_starter_import();
	}
);

function ci_starter_import() {
	$stylesheet = get_stylesheet();
	$mods       = get_theme_mods();
	$mods       = is_array( $mods ) ? $mods : array();
	$before     = $mods;

	// 1. Same theme under another folder name.
	foreach ( ci_starter_other_mods( $stylesheet ) as $other ) {
		$mods += $other;
	}

	// 2. Bundled starter settings.
	$file    = get_theme_file_path( 'assets/starter/settings.json' );
	$starter = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
	foreach ( is_array( $starter ) ? $starter : array() as $key => $value ) {
		if ( array_key_exists( $key, $mods ) ) {
			continue;
		}
		$value = ci_starter_value( $value );
		if ( null !== $value ) {
			$mods[ $key ] = $value;
		}
	}

	if ( $mods !== $before ) {
		update_option( "theme_mods_{$stylesheet}", $mods );
	}
	ci_starter_brand_logos();
	update_option( 'ci_starter_version', CI_STARTER_VERSION, false );
}

/**
 * Settings of this theme saved under other folder names (newest-looking first: the one with a homepage).
 *
 * @param string $stylesheet Current folder.
 * @return array<int,array>
 */
function ci_starter_other_mods( $stylesheet ) {
	global $wpdb;
	$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'theme_mods_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$found = array();
	foreach ( $names as $name ) {
		if ( "theme_mods_{$stylesheet}" === $name ) {
			continue;
		}
		$other = get_option( $name );
		// Only this theme's settings (other themes have no homepage sections).
		if ( is_array( $other ) && isset( $other['home_hero_title'] ) ) {
			unset( $other['custom_css_post_id'] );
			$found[] = $other;
		}
	}
	return $found;
}

/**
 * Starter value → this site's value; null = can't be resolved here (setting left unset).
 *
 * @param mixed $value Value from settings.json.
 * @return mixed
 */
function ci_starter_value( $value ) {
	if ( is_array( $value ) && isset( $value['image'] ) ) {
		return ci_starter_attachment( (string) $value['image'] ) ?: null;
	}
	if ( is_array( $value ) && isset( $value['term'], $value['slug'] ) ) {
		$term = taxonomy_exists( $value['term'] ) ? get_term_by( 'slug', $value['slug'], $value['term'] ) : false;
		return $term ? (string) $term->term_id : null;
	}
	if ( is_string( $value ) && false !== strpos( $value, '{' ) ) {
		if ( false !== strpos( $value, '{home}/brands/' ) ) {
			ci_starter_brands_page();
		}
		$shop = function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'shop' ) > 0 ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/shop/' );
		return str_replace( array( '{shop}', '{home}' ), array( $shop, untrailingslashit( home_url() ) ), $value );
	}
	return $value;
}

/**
 * Attachment ID of a bundled image, added to the media library once.
 *
 * @param string $name File in assets/starter (or assets/starter/brands).
 * @param string $title Attachment title.
 * @return int 0 on failure.
 */
function ci_starter_attachment( $name, $title = '' ) {
	$name     = ltrim( str_replace( '..', '', $name ), '/' );
	$existing = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'OR',
				array( 'key' => '_ci_starter', 'value' => $name ),
				array( 'key' => '_ci_seed', 'value' => 'home-v2/' . $name ), // Local site (tools/seed.sh).
			),
		)
	);
	if ( $existing ) {
		return (int) $existing[0];
	}
	$file = get_theme_file_path( 'assets/starter/' . $name );
	if ( ! is_readable( $file ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = wp_tempnam( $file );
	copy( $file, $tmp );
	$id = media_handle_sideload( array( 'name' => basename( $name ), 'tmp_name' => $tmp ), 0, $title ?: null );
	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		return 0;
	}
	update_post_meta( $id, '_ci_starter', $name );
	return (int) $id;
}

/** "Brands" page ([ci_brands]) for the homepage "All brands" link, if the site has none. */
function ci_starter_brands_page() {
	if ( get_page_by_path( 'brands' ) ) {
		return;
	}
	wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Brands',
			'post_name'    => 'brands',
			'post_content' => '<!-- wp:shortcode -->[ci_brands]<!-- /wp:shortcode -->',
		)
	);
}

/** Bundled logos for existing brands that have none. */
function ci_starter_brand_logos() {
	if ( ! taxonomy_exists( 'product_brand' ) ) {
		return;
	}
	foreach ( glob( get_theme_file_path( 'assets/starter/brands/*.png' ) ) ?: array() as $file ) {
		$term = get_term_by( 'slug', basename( $file, '.png' ), 'product_brand' );
		if ( ! $term || wp_attachment_is_image( (int) get_term_meta( $term->term_id, 'thumbnail_id', true ) ) ) {
			continue;
		}
		$id = ci_starter_attachment( 'brands/' . basename( $file ), "{$term->name} logo" );
		if ( $id ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $term->name );
			update_term_meta( $term->term_id, 'thumbnail_id', $id );
		}
	}
}
