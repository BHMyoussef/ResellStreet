<?php
/**
 * WooCommerce integration via hooks. Template overrides are a last resort (listed in CLAUDE.md).
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

/* Page wrappers: header.php already opens <main>, so WC only needs the page-width container. */
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
add_action(
	'woocommerce_before_main_content',
	function () {
		echo '<div class="page-width wc-page">';
	},
	10
);
add_action(
	'woocommerce_after_main_content',
	function () {
		echo '</div>';
	},
	10
);

/* No sidebar and no breadcrumbs (both off on the reference store). */
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
