<?php
/**
 * Starter settings for a theme folder that has none yet (e.g. installed by WP Pusher under the repo name).
 *
 * Customizer settings are stored per theme folder (option theme_mods_<folder>). Runs once per site (option
 * ci_starter_version), on theme activation or the next admin page load, and only fills settings that are missing,
 * from this theme's settings under another folder name in the same database (IDs still valid). No store content
 * is bundled: a new store fills in Customizer settings itself (CLAUDE.md → New store setup).
 * Brands without a logo get the bundled one (assets/starter/brands/<slug>.png: product brands, not the store's).
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

const CI_STARTER_VERSION = 1;

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

	// Same theme under another folder name.
	foreach ( ci_starter_other_mods( $stylesheet ) as $other ) {
		$mods += $other;
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
 * Attachment ID of a bundled image, added to the media library once.
 *
 * @param string $name File in assets/starter (e.g. brands/nike.png).
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
			'meta_query'  => array( array( 'key' => '_ci_starter', 'value' => $name ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
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
