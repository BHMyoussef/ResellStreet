<?php
/**
 * CustomImpulse theme bootstrap.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

define( 'CI_VERSION', wp_get_theme( get_template() )->get( 'Version' ) );

require_once get_theme_file_path( 'inc/setup.php' );
require_once get_theme_file_path( 'inc/customizer.php' );
require_once get_theme_file_path( 'inc/template-tags.php' );
require_once get_theme_file_path( 'inc/enqueue.php' );

if ( class_exists( 'WooCommerce' ) ) {
	require_once get_theme_file_path( 'inc/woocommerce.php' );
	require_once get_theme_file_path( 'inc/product.php' );
	require_once get_theme_file_path( 'inc/cart.php' );
}
