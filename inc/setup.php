<?php
/**
 * Theme supports, menus, image sizes.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	function () {
		load_theme_textdomain( 'customimpulse', get_template_directory() . '/languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 100,
				'width'       => 450,
				'flex-height' => true,
				'flex-width'  => true,
				'unlink-homepage-logo' => false,
			)
		);

		add_theme_support(
			'woocommerce',
			array(
				'thumbnail_image_width' => 600,
				'single_image_width'    => 900,
				'product_grid'          => array(
					'default_rows'    => 7,
					'min_rows'        => 1,
					'default_columns' => 3,
					'min_columns'     => 2,
					'max_columns'     => 4,
				),
			)
		);
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );

		// Small square crop so product cards get a mobile-sized srcset candidate next to woocommerce_thumbnail (600×600).
		add_image_size( 'ci-card-sm', 360, 360, true );

		register_nav_menus(
			array(
				'primary' => __( 'Main menu (header + mobile drawer)', 'customimpulse' ),
				'footer'  => __( 'Footer menu', 'customimpulse' ),
			)
		);
	}
);

/**
 * Add a `js` class before first paint so no-JS fallbacks (e.g. open footer panels) don't flash.
 */
add_action(
	'wp_head',
	function () {
		echo "<script>document.documentElement.classList.add('js')</script>\n";
	},
	0
);

/**
 * The primary menu renders twice (header + drawer): drop item IDs from the drawer copy to keep IDs unique.
 */
add_filter(
	'nav_menu_item_id',
	function ( $id, $item, $args ) {
		return ( isset( $args->menu_class ) && 'mobile-nav__list' === $args->menu_class ) ? '' : $id;
	},
	10,
	3
);

/* ---------------------------------------------------------------------------
 * Images: lighter files for mobile (paid traffic)
 * ------------------------------------------------------------------------ */

/* Originals are capped at 2048px (plenty for product zoom; WordPress default is 2560). */
add_filter( 'big_image_size_threshold', fn() => 2048 );

/* Generated sizes (and the capped original) of JPEG/PNG uploads are saved as WebP. */
add_filter(
	'image_editor_output_format',
	function ( $formats ) {
		if ( wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			$formats['image/jpeg'] = 'image/webp';
			$formats['image/png']  = 'image/webp';
		}
		return $formats;
	}
);

/* The theme never uses WordPress's 1536px / 2048px sizes: don't generate them. */
add_filter(
	'intermediate_image_sizes_advanced',
	function ( $sizes ) {
		unset( $sizes['1536x1536'], $sizes['2048x2048'] );
		return $sizes;
	}
);
