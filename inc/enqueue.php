<?php
/**
 * Assets: global CSS/JS, font preloads, removal of unused core/WC assets.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cache-busting version from the file's mtime (one stat per asset; theme version as fallback).
 *
 * @param string $rel Path relative to the theme root.
 */
function ci_asset_version( $rel ) {
	$path = get_theme_file_path( $rel );
	return file_exists( $path ) ? (string) filemtime( $path ) : CI_VERSION;
}

/**
 * Enqueue a theme stylesheet from assets/css.
 *
 * @param string $name File name without extension.
 * @param array  $deps Dependencies.
 */
function ci_enqueue_style( $name, $deps = array( 'ci-base' ) ) {
	$rel = "assets/css/{$name}.css";
	wp_enqueue_style( "ci-{$name}", get_theme_file_uri( $rel ), $deps, ci_asset_version( $rel ) );
}

/**
 * Enqueue a deferred theme script from assets/js.
 *
 * @param string $name File name without extension.
 * @param array  $deps Dependencies.
 */
function ci_enqueue_script( $name, $deps = array() ) {
	$rel = "assets/js/{$name}.js";
	wp_enqueue_script(
		"ci-{$name}",
		get_theme_file_uri( $rel ),
		$deps,
		ci_asset_version( $rel ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
}

add_action(
	'wp_enqueue_scripts',
	function () {
		ci_enqueue_style( 'tokens', array() );
		wp_add_inline_style( 'ci-tokens', ci_customizer_css() );
		ci_enqueue_style( 'base', array( 'ci-tokens' ) );
		ci_enqueue_style( 'header' );
		ci_enqueue_style( 'footer' );

		if ( is_front_page() ) {
			ci_enqueue_style( 'collection' ); // Product cards (new arrivals, trending).
			ci_enqueue_style( 'brands' );
			ci_enqueue_style( 'video-reviews' );
			ci_enqueue_style( 'home', array( 'ci-collection' ) );
			ci_enqueue_script( 'video-reviews' );
			ci_enqueue_script( 'home' );
		}
		if ( is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'ci_brands' ) ) {
			ci_enqueue_style( 'brands' );
		}
		if ( function_exists( 'ci_is_collection' ) && ci_is_collection() ) {
			ci_enqueue_style( 'collection' );
		}
		if ( function_exists( 'is_product' ) && is_product() ) {
			ci_enqueue_style( 'product' );
			ci_enqueue_script( 'product' );
			ci_enqueue_style( 'video-reviews' );
			ci_enqueue_script( 'video-reviews' );
			if ( ci_mod( 'related_enable' ) ) {
				ci_enqueue_style( 'collection' ); // Product cards in related/upsells.
			}
		}

		ci_enqueue_script( 'global' );
	}
);

/**
 * Preload the two above-the-fold font files.
 */
add_action(
	'wp_head',
	function () {
		foreach ( array( 'outfit-latin-var.woff2', 'montserrat-800-latin.woff2' ) as $font ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( get_theme_file_uri( "assets/fonts/{$font}" ) )
			);
		}
	},
	1
);

/**
 * Drop assets this classic theme doesn't use.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles', 'core-block-supports', 'wc-blocks-style' ) as $handle ) {
			wp_dequeue_style( $handle );
		}
	},
	100
);
add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );
remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );

// Emoji detection script + styles: native emoji rendering is fine on all target devices.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );

/* ---------------------------------------------------------------------------
 * Default site icon: the ResellStreet "RS" monogram (assets/brand), until one is set in Site Identity.
 * WordPress prints the icon tags (32, 180 apple-touch, 192, 270) and serves /favicon.ico from this URL.
 * ------------------------------------------------------------------------ */

add_filter(
	'get_site_icon_url',
	function ( $url, $size ) {
		if ( get_option( 'site_icon' ) ) { // Not has_site_icon(): it calls this filter.
			return $url;
		}
		if ( $size <= 32 ) {
			$file = 'favicon-32.png';
		} elseif ( $size <= 180 ) {
			$file = 'apple-touch-icon.png';
		} else {
			$file = 'site-icon-512.png';
		}
		return get_theme_file_uri( "assets/brand/{$file}" );
	},
	10,
	2
);

/* Sharp at any size in browsers that take SVG icons (the PNG tags above stay for the rest). */
add_action(
	'wp_head',
	function () {
		if ( ! get_option( 'site_icon' ) ) {
			printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url( get_theme_file_uri( 'assets/brand/favicon.svg' ) ) );
		}
	},
	100 // after wp_site_icon (99)
);

/* WPForms in the store's look: only on pages that contain a form. */
add_action(
	'wp_enqueue_scripts',
	function () {
		$post = get_post();
		if ( is_singular() && $post && ( has_shortcode( $post->post_content, 'wpforms' ) || has_block( 'wpforms/form-selector', $post ) ) ) {
			ci_enqueue_style( 'forms' );
		}
	},
	20
);
