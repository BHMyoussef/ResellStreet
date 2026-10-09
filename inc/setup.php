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
