<?php
/**
 * (Re)builds the "Main menu" from the catalogue (run by seed.sh; idempotent):
 *   tools/wp eval-file tools/main-menu.php
 *
 * Shop all · New in · Brands (mega: brands with products, most products first, each with its top 6 models =
 * child categories of the category with the brand's slug, brand name dropped) · Best sellers · Help (Track my
 * order, Contact Us, About Us). Edit freely in Appearance → Menus afterwards; re-running replaces the menu.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

$ci_page = fn( $slug ) => ( get_page_by_path( $slug ) )->ID ?? 0;
$ci_shop = (int) wc_get_page_id( 'shop' );
$ci_url  = (string) get_permalink( $ci_shop );

$ci_old = wp_get_nav_menu_object( 'Main menu' );
if ( $ci_old ) {
	wp_delete_nav_menu( $ci_old->term_id );
}
$ci_menu = wp_create_nav_menu( 'Main menu' );

/**
 * Adds a menu item, returns its ID.
 *
 * @param array $args wp_update_nav_menu_item() fields without the menu-item- prefix.
 */
$ci_add = function ( array $args ) use ( $ci_menu ) {
	$fields = array( 'menu-item-status' => 'publish' );
	foreach ( $args as $key => $value ) {
		$fields[ "menu-item-{$key}" ] = $value;
	}
	return (int) wp_update_nav_menu_item( $ci_menu, 0, $fields );
};
$ci_post = fn( $id, $title = '', $parent = 0 ) => $ci_add(
	array(
		'type'      => 'post_type',
		'object'    => 'page',
		'object-id' => $id,
		'title'     => $title,
		'parent-id' => $parent,
	)
);
$ci_link = fn( $title, $url, $parent = 0, $classes = '' ) => $ci_add(
	array(
		'type'      => 'custom',
		'title'     => $title,
		'url'       => $url,
		'parent-id' => $parent,
		'classes'   => $classes,
	)
);
$ci_term = fn( WP_Term $term, $title = '', $parent = 0 ) => $ci_add(
	array(
		'type'      => 'taxonomy',
		'object'    => $term->taxonomy,
		'object-id' => $term->term_id,
		'title'     => $title,
		'parent-id' => $parent,
	)
);

$ci_post( $ci_shop, 'Shop all' );
$ci_link( 'New in', add_query_arg( 'orderby', 'date', $ci_url ) );

$ci_brands_page = $ci_page( 'brands' );
$ci_mega        = $ci_brands_page
	? $ci_add(
		array(
			'type'      => 'post_type',
			'object'    => 'page',
			'object-id' => $ci_brands_page,
			'title'     => 'Brands',
			'classes'   => 'mega',
		)
	)
	: $ci_link( 'Brands', '#', 0, 'mega' );
$ci_brands = get_terms(
	array(
		'taxonomy'   => 'product_brand',
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
	)
);
foreach ( is_array( $ci_brands ) ? $ci_brands : array() as $ci_brand ) {
	$ci_column = $ci_term( $ci_brand, '', $ci_mega );
	$ci_cat    = get_term_by( 'slug', $ci_brand->slug, 'product_cat' );
	if ( ! $ci_cat ) {
		continue;
	}
	$ci_models = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => $ci_cat->term_id,
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => 6,
		)
	);
	foreach ( is_array( $ci_models ) ? $ci_models : array() as $ci_model ) {
		$ci_title = trim( preg_replace( '/^' . preg_quote( $ci_brand->name, '/' ) . '\s+/i', '', $ci_model->name ) );
		$ci_term( $ci_model, $ci_title, $ci_column );
	}
}

$ci_link( 'Best sellers', add_query_arg( 'orderby', 'popularity', $ci_url ) );
$ci_help = $ci_link( 'Help', '#' );
foreach ( array( 'track-my-order' => 'Track my order', 'contact-us' => '', 'about-us' => '' ) as $ci_slug => $ci_title ) {
	if ( $ci_page( $ci_slug ) ) {
		$ci_post( $ci_page( $ci_slug ), $ci_title, $ci_help );
	}
}

$ci_locations            = get_theme_mod( 'nav_menu_locations', array() );
$ci_locations['primary'] = $ci_menu;
set_theme_mod( 'nav_menu_locations', $ci_locations );
WP_CLI::success( 'Main menu: ' . count( wp_get_nav_menu_items( $ci_menu ) ) . ' items.' );
